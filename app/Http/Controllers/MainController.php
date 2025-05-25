<?php
namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Block;
use App\Models\Claim;
use App\Models\Input;
use App\Models\Output;
use App\Models\TagAddressRequest;
use App\Models\Transaction;
use App\Models\TransactionAddress;

use Cake\Utility\Xml;

use DateInterval;
use DateTime;
use DateTimeZone;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

use Exception;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\View\View;

use PDO;
use RedisException;
use stdClass;

class MainController extends Controller{

    const lbcPriceKey = 'lbc.price';

    const txOutSetInfo = 'lbrcrd.tosi';

    const bittrexMarketUrl = 'https://api.bittrex.com/v3/markets/LBC-BTC/ticker';

    const blockchainTickerUrl = 'https://blockchain.info/ticker';

    const tagReceiptAddress = 'bLockNgmfvnnnZw7bM6SPz6hk5BVzhevEp';

    const blockedListUrl = 'https://api.odysee.com/file/list_blocked?with_claim_id=true';

    protected $redis;
    protected $rpcurl;

    public function __construct(){
        $this->redis = Redis::connection()->client();
        $this->rpcurl = config('lbry.rpc_url');
        try {
            $this->redis->info('mem');
        } catch (RedisException) {
            $this->redis = null;
        }
    }

    /**
     * @return string
     * @throws RedisException
     * @throws Exception
     */
    protected function _getLatestPrice(): string{
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $priceInfo = new stdClass();
        $priceInfo->time = $now->format('c');

        $shouldRefreshPrice = false;
        if (!$this->redis) {
            $shouldRefreshPrice = true;
        } else {
            if (!$this->redis->exists(self::lbcPriceKey)) {
                $shouldRefreshPrice = true;
            } else {
                $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
                $lastPriceDt = new DateTime($priceInfo->time);
                $diff = $now->diff($lastPriceDt);
                $diffMinutes = $diff->i;
                if ($diffMinutes >= 15 || $priceInfo->price == 0) { // 15 minutes (or if the price is 0)
                    $shouldRefreshPrice = true;
                }
            }
        }

        if ($shouldRefreshPrice) {
            $btrxjson = json_decode(self::curl_get(self::bittrexMarketUrl));
            $blckjson = json_decode(self::curl_get(self::blockchainTickerUrl));

            if ($btrxjson) {
                $onelbc = $btrxjson->bidRate;
                $lbcPrice = 0;
                if (isset($blckjson->USD)) {
                    $lbcPrice = $onelbc * $blckjson->USD->buy;
                    if ($lbcPrice > 0) {
                        $priceInfo->price = number_format($lbcPrice, 3, '.', '');
                        $priceInfo->time = $now->format('c');
                        if ($this->redis) {
                            $this->redis->set(self::lbcPriceKey, json_encode($priceInfo));
                        }
                    }
                }
            }
        }

        $lbcUsdPrice = (isset($priceInfo->price) && ($priceInfo->price > 0)) ? '$' . $priceInfo->price : 'N/A';
        return $lbcUsdPrice;
    }

    /**
     * @return JsonResponse|Response|View
     * @throws RedisException
     */
    public function index(): JsonResponse|Response|View{
        $lbcUsdPrice = $this->_getLatestPrice();
        $blocks = Block::query()->select(['Chainwork','Confirmations','Difficulty','Hash','Height','BlockTime','BlockSize'])->selectRaw('JSON_LENGTH(`TransactionHashes`) AS tx_count')->orderByDesc('Height')->limit(6)->get();
        $claims = Claim::query()->limit(5)->get();
        //$claims = $this->Claims->find()->select($this->Claims)->select(['publisher' => 'C.name'])->leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->order(['Claims.created_at' => 'DESC'])->limit(5)->toArray();
        $hashRate = $this->_formatHashRate($this->_gethashrate());

        return self::generateResponse('main.index',[
            'lbcUsdPrice' => $lbcUsdPrice,
            'recentBlocks' => $blocks,
            'recentClaims' => $claims,
            'hashRate' => $hashRate,
        ]);
    }

    /**
     * @param null $id
     * @return JsonResponse|Response|RedirectResponse|View
     * @throws RedisException
     * @throws Exception
     */
    public function claims($id = null): JsonResponse|Response|RedirectResponse|View{
        $canConvert = false;
        if(isset($this->redis)) {
            $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
        }
        if (isset($priceInfo->price)) {
            $canConvert = true;
        }

        if (!$id) {
            // paginate claims
            $offset = 0;
            $pageLimit = 96;
            $page = intval(request()->query('page'));

            $conn = DB::connection();
//            $stmt = $conn->execute('SELECT COUNT(id) AS Total FROM claim');
//            $count = $stmt->fetch(\PDO::FETCH_OBJ);
            $numClaims = 20000000;

            $stmt = $conn->getPdo()->query('SELECT MAX(id) AS MaxId FROM claim');
            $res = $stmt->fetch(PDO::FETCH_OBJ);
            $maxClaimId = $res->MaxId;

            $numPages = ceil($numClaims  / $pageLimit);
            if ($page < 1) {
                $page = 1;
            }
            if ($page > $numPages) {
                $page = $numPages;
            }

            $startLimitId = $maxClaimId - ($page * $pageLimit);
            $endLimitId = $startLimitId + $pageLimit;
            if ($endLimitId > $maxClaimId) {
                $endLimitId = $maxClaimId;
            }

            $blockedList = json_decode($this->_getBlockedList());
            $claims = Claim::query()->addSelect(['publisher' => 'C.name', 'publisher_transaction_hash_id' => 'C.transaction_hash_id', 'publisher_vout' => 'C.vout'])->leftJoin('Claims','claim_id','=','Claims.publisher_id')->where('Claims.id','>',$startLimitId)->where( 'Claims.id','<=',$endLimitId)->orderByDesc('Claims.id')->get();

            for ($i = 0; $i < count($claims); $i++) {
                if ($canConvert && $claims[$i]->fee > 0 && $claims[$i]->fee_currency == 'USD') {
                    $claims[$i]->price = $claims[$i]->fee / $priceInfo->price;
                }

                if (isset($claims[$i]->Stream)) {
                    $json = json_decode($claims[$i]->Stream->Stream);
                    if (isset($json->metadata->license)) {
                        $claims[$i]->License = $json->metadata->license;
                    }
                    if (isset($json->metadata->licenseUrl)) {
                        $claims[$i]->LicenseUrl = $json->metadata->licenseUrl;
                    }
                }

                $claimChannel = null;
                if ($claims[$i]->publisher_transaction_hash_id) {
                    $claimChannel = new stdClass;
                    $claimChannel->transaction_hash_id = $claims[$i]->publisher_transaction_hash_id;
                    $claimChannel->vout = $claims[$i]->publisher_vout;
                }

                $blocked = $this->_isClaimBlocked($claims[$i], $claimChannel, $blockedList);
                $claims[$i]->isBlocked = $blocked;
                $claims[$i]->thumbnail_url = $blocked ? null : $claims[$i]->thumbnail_url; // don't show the thumbnails too
            }

            return self::generateResponse('main.claims',[
                'pageLimit' => $pageLimit,
                'numPages' => $numPages,
                'numRecords' => $numClaims,
                'currentPage' => $page,
                'claims' => $claims,
            ]);
        } else {
            $claim = Claim::query()->addSelect(['publisher' => 'C.name'])->leftJoin('Claims','claim_id','=','Claims.publisher_id')->where('Claims.claim_id',$id)->orderByDesc('Claims.created_at')->first();
            if (!$claim) {
                return Redirect::to('/');
            }

            if ($canConvert && $claim->fee > 0 && $claim->fee_currency == 'USD') {
                $claim->price = $claim->fee / $priceInfo->price;
            }

            if (isset($claim->Stream)) {
                $json = json_decode($claim->Stream->Stream);
                if (isset($json->metadata->license)) {
                    $claim->License = $json->metadata->license;
                }
                if (isset($json->metadata->licenseUrl)) {
                    $claim->LicenseUrl = $json->metadata->licenseUrl;
                }
            }

            $moreClaims = [];
            if (isset($claim->publisher) || $claim->claim_type == 1) {
                // find more claims for the publisher
                $moreClaimsQuery = Claim::query()->select([
                    'claim_id', 'bid_state', 'fee', 'fee_currency', 'is_nsfw', 'claim_type', 'name',
                    'title', 'description', 'content_type', 'language', 'author', 'license', 'content_type',
                    'created_at'
                ])->select(['publisher' => 'C.name'])->leftJoin('Claims','claim_id','=','Claims.publisher_id')->where('Claims.claim_type',1)->where('Claims.id','<>',$claim->id)->where('Claims.publisher_id',isset($claim->publisher) ? $claim->publisher_id : $claim->claim_id)->limit(9);
                if (isset($claim->publisher) && $claim->publisher_id !== 'f2cf43b86b9d70175dc22dbb9ff7806241d90780') { // prevent ORDER BY for this particular claim
                    $moreClaimsQuery = $moreClaimsQuery->orderByDesc('Claims.fee')->orderByDesc('RAND()');
                    $moreClaims = $moreClaimsQuery->get();
                }
                for ($i = 0; $i < count($moreClaims); $i++) {
                    if ($canConvert && $moreClaims[$i]->fee > 0 && $moreClaims[$i]->fee_currency == 'USD') {
                        $moreClaims[$i]->price = $moreClaims[$i]->fee / $priceInfo->price;
                    }

                    if (isset($moreClaims[$i]->Stream)) {
                        $json = json_decode($moreClaims[$i]->Stream->Stream);
                        if (isset($json->metadata->license)) {
                            $moreClaims[$i]->License = $json->metadata->license;
                        }
                        if (isset($json->metadata->licenseUrl)) {
                            $moreClaims[$i]->LicenseUrl = $json->metadata->licenseUrl;
                        }
                    }
                }
            }

            // fetch blocked list
            $blockedList = json_decode($this->_getBlockedList());
            $claimChannel = Claim::query()->select(['transaction_hash_id', 'vout'])->where('claim_id',$claim->publisher_id)->first();
            $claimIsBlocked = $this->_isClaimBlocked($claim, $claimChannel, $blockedList);

            return self::generateResponse('main.claims',[
                'claim' => $claim,
                'claimIsBlocked' => $claimIsBlocked,
                'moreClaims' => $claimIsBlocked ? [] : $moreClaims,
            ]);
        }
    }

    public function realtime(): JsonResponse|Response|View{
        // Load 10 blocks and transactions
        $blocks = Block::query()->select(['Height','BlockTime'])->selectRaw('JSON_LENGTH(`TransactionHashes`) AS tx_count')->orderByDesc('Height')->limit(10)->get();
        $transactions = Transaction::query()->select(['Id','Hash','Value','InputCount','OutputCount','TransactionTime','Created'])->orderByDesc('Created')->limit(10)->get();

        return self::generateResponse('main.realtime',[
            'blocks' => $blocks,
            'txs' => $transactions,
        ]);
    }

    public function find(): JsonResponse|RedirectResponse|View{
        $criteria = request()->query('q');

        if(is_numeric($criteria)){
            $height = (int) $criteria;
            $block = Block::query()->select(['Id'])->where('Height',$height)->first();
            if($block){
                return Redirect::to('/blocks/'.$height);
            }
        }elseif(strlen(trim($criteria)) === 34){
            // Address
            $address = Address::query()->select(['Id','Address'])->where('Address',$criteria)->first();
            if($address){
                return Redirect::to('/address/'.$address->Address);
            }
        }elseif(strlen(trim($criteria)) === 40){
            // Claim ID
            $claim = Claim::query()->select(['ClaimId'])->where('ClaimId',$criteria)->first();
            if($claim){
                return Redirect::to('/claims/'.$claim->ClaimId);
            }
        }elseif(strlen(trim($criteria)) === 64) { // block or tx hash
            // Try block hash first
            $block = Block::query()->select(['Height'])->where('Hash',$criteria)->first();
            if($block){
                return Redirect::to('/blocks/'.$block->Height);
            }else{
                $tx = Transaction::query()->select(['Hash'])->where('Hash',$criteria)->first();
                if($tx){
                    return Redirect::to('/tx/'.$tx->Hash);
                }
            }
        }else{
            // finally, try exact claim name match
            $claims = Claim::query()->distinct('ClaimId')->where('Name',$criteria)->orderByDesc('CreatedAt')->limit(10)->get(); //TODO Fix ordering by BidState (Controlling)
            if(count($claims)===1){
                return Redirect::to('/claims/'.$claims[0]->ClaimId);
            }
            return self::generateResponse('main.find',[
                'claims' => $claims,
            ]);
        }

        return self::generateResponse('main.find',[]);
    }

    public function blocks($height = null): JsonResponse|Response|RedirectResponse|View{
        if ($height === null) {
            // paginate blocks
            $offset = 0;
            $pageLimit = 50;
            $page = intval(request()->query('page'));

            $conn = DB::connection();
            $stmt = $conn->getPdo()->query('SELECT height AS Total FROM block order by id desc limit 1');
            $count = $stmt->fetch(PDO::FETCH_OBJ);
            $numBlocks = $count->Total;

            $numPages = ceil($numBlocks  / $pageLimit);
            if ($page < 1) {
                $page = 1;
            }
            if ($page > $numPages) {
                $page = $numPages;
            }

            $offset = ($page - 1) * $pageLimit;
            $currentBlock = Block::query()->select(['height'])->orderByDesc('height')->first();
            $blocks = Block::query()->select(['height', 'difficulty', 'block_size', 'nonce', 'block_time','tx_count'])->offset($offset)->limit($pageLimit)->orderByDesc('height')->get();

            return self::generateResponse('main.blocks',[
                'currentBlock' => $currentBlock,
                'blocks' => $blocks,
                'pageLimit' => $pageLimit,
                'numPages' => $numPages,
                'numRecords' => $numBlocks,
                'currentPage' => $page,
            ]);
        } else {
            $height = intval($height);
            if ($height < 0) {
                return Redirect::to('/');
            }

            $block = Block::query()->where('height',$height)->first();
            if (!$block) {
                return Redirect::to('/');
            }

            // Get the basic block transaction info
            $txs = Transaction::query()->select(['Transactions.id', 'Transactions.value', 'Transactions.input_count', 'Transactions.output_count', 'Transactions.hash', 'Transactions.version'])->where('Transactions.block_hash_id',$block->hash)->get();
            $last_block = Block::query()->select(['height'])->orderByDesc('height')->first();
            $confirmations = $last_block->height - $block->height + 1;

            return self::generateResponse('main.blocks',[
                'block' => $block,
                'blockTxs' => $txs,
                'confirmations' => $confirmations,
            ]);
        }
    }

    public function tx($hash = null): JsonResponse|Response|RedirectResponse|View{
        $sourceAddress = request()->query('address');

        $tx = Transaction::query()->where('Transactions.hash',$hash)->first();
        if (!$tx) {
            return Redirect::to('/');
        }

        $block = Block::query()->select(['confirmations', 'height'])->where(['hash' => $tx->block_hash_id])->first();
        $confirmations = 0;
        if($tx->block_hash_id == 'MEMPOOL') {
            $confirmations = 0;
        }
        else {
            $last_block = Block::query()->select(['height'])->orderByDesc('height')->first();
            $confirmations = $last_block->height - $block->height + 1;
        }
        $inputs = Input::query()->where('transaction_id',$tx->id)->orderBy('prevout_n')->get();
        foreach($inputs as $input) {
            $inputAddresses = Address::query()->select(['id', 'address'])->where('id',$input->input_address_id)->get();
            $input->input_addresses = $inputAddresses;
        }

        $outputs = Output::query()->addSelect(['spend_input_hash' => 'I.transaction_hash', 'spend_input_id' => 'I.id'])->where('Outputs.transaction_id',$tx->id)->leftJoin('Inputs','id','=','Outputs.spent_by_input_id')->orderBy('Outputs.vout')->get();
        for ($i = 0; $i < count($outputs); $i++) {
            $outputs[$i]->IsClaim = (strpos($outputs[$i]->script_pub_key_asm, 'CLAIM') > -1);
            $outputs[$i]->IsSupportClaim = (strpos($outputs[$i]->script_pub_key_asm, 'SUPPORT_CLAIM') > -1);
            $outputs[$i]->IsUpdateClaim = (strpos($outputs[$i]->script_pub_key_asm, 'UPDATE_CLAIM') > -1);
            $claim = Claim::query()->select(['id', 'claim_id', 'claim_address', 'vout', 'transaction_hash_id'])->where('transaction_hash_id',$tx->hash)->where('vout',$outputs[$i]->vout)->first();
            $outputs[$i]->Claim = $claim;

            $output_address = trim($outputs[$i]->address_list, '["]');
            if(!$output_address && $claim) {
                $output_address = $claim->claim_address;
            }
            $address = Address::query()->select(['address'])->where('address',$output_address)->first();
            $outputs[$i]->output_addresses = [$address];
        }

        $totalIn = 0;
        $totalOut = 0;
        $fee = 0;
        foreach ($inputs as $in) {
            $totalIn = bcadd($totalIn, $in->value, 8);
        }
        foreach ($outputs as $out) {
            $totalOut = bcadd($totalOut, $out->value, 8);
        }
        $fee = bcsub($totalIn, $totalOut, 8);

        return self::generateResponse('main.tx',[
            'tx' => $tx,
            'block' => $block,
            'confirmations' => $confirmations,
            'fee' => $fee,
            'inputs' => $inputs,
            'outputs' => $outputs,
            'sourceAddress' => $sourceAddress,
        ]);
    }

    /**
     * @throws RedisException
     */
    public function stats(): JsonResponse|Response|View{
        // exclude bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf (genesis block)
        $richList = Address::query()->where('address','<>','bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf')->orderByDesc('balance')->limit(500)->get();

        $priceRate = 0;
        if(isset($this->redis)) {
            $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
            if (isset($priceInfo->price)) {
                $priceRate = $priceInfo->price;
            }
        }

        $lbryAddresses = ['rEqocTgdPdoD8NEbrECTUPfpquJ4zPVCJ8', 'rKaAUDxr24hHNNTQuNtRvNt8SGYJMdLXo3', 'r7hj61jdbGXcsccxw8UmEFCReZoCWLRr7t', 'bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt', 'bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2', 'bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD', 'bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz', 'bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393', 'bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH', 'bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h', 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc', 'bX6napXtY2nVTBRc8PwULBuGWn2i3SCtrN', 'bG1fEEqDVepDy3AbvM8outQ3FQUu76aDot'];
        $totalBalance = 0;
        $maxBalance = 0;
        $minBalance = 0;
        foreach ($richList as $item) {
            $totalBalance = bcadd($totalBalance, $item->balance, 8);
            $minBalance = $minBalance == 0 ? $item->balance : min($minBalance, $item->balance);
            $maxBalance = max($maxBalance, $item->balance);
        }
        for ($i = 0; $i < count($richList); $i++) {
            $item = $richList[$i];
            $percentage = bcdiv($item->balance, $totalBalance, 8) * 100;
            $richList[$i]->Top500Percent = $percentage;
            $richList[$i]->MinMaxPercent = bcdiv($item->balance, $maxBalance, 8) * 100;
        }

        return self::generateResponse('main.stats',[
            'richList' => $richList,
            'rate' => $priceRate,
            'lbryAddresses' => $lbryAddresses,
        ]);
    }

    public function address($addr = null): JsonResponse|Response|RedirectResponse|View{
        set_time_limit(0);

        if (!$addr) {
            return Redirect::to('/');
        }

        $offset = 0;
        $pageLimit = 50;
        $numTransactions = 0;
        $page = intval(request()->query('page'));

        $canTag = false;
        $totalRecvAmount = 0;
        $totalSentAmount = 0;
        $balanceAmount = 0;
        $recentTxs = [];

        $tagRequestAmount = 0;
        $address = Address::query()->where('address',$addr)->first();
        if (!$address) {
            if (strlen($addr) === 34) {
                $address = new stdClass;
                $address->address = $addr;
            } else {
                return Redirect::to('/');
            }
        } else {
            $conn = DB::connection();

            $canTag = true;
            $transactionAddresses = TransactionAddress::query()->where('address_id',$address->id)->get();
            $numTransactions = count($transactionAddresses);

            $all = request()->query('all');
            if ($all === 'true') {
                $offset = 0;
                $pageLimit = $numTransactions;
                $numPages = 1;
                $page = 1;
            } else {
                $numPages = ceil($numTransactions / $pageLimit);
                if ($page < 1) {
                    $page = 1;
                }
                if ($page > $numPages) {
                    $page = $numPages;
                }

                $offset = ($page - 1) * $pageLimit;
            }

            $stmt = $conn->getPdo()->query(sprintf(
                'SELECT T.id, T.hash, T.input_count, T.output_count, T.block_hash_id, ' .
                '    TA.debit_amount, TA.credit_amount, ' .
                '    B.height, B.confirmations, ' .
                '    IFNULL(T.transaction_time, T.created_at) AS transaction_time ' .
                'FROM transaction T ' .
                'LEFT JOIN block B ON T.block_hash_id = B.hash ' .
                'RIGHT JOIN (SELECT transaction_id, debit_amount, credit_amount FROM transaction_address ' .
                '            WHERE address_id = ?) TA ON TA.transaction_id = T.id ' .
                'ORDER BY transaction_time DESC LIMIT %d, %d', $offset, $pageLimit), [$address->id]);
            $recentTxs = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach($transactionAddresses as $ta) {
                $totalRecvAmount += $ta->credit_amount + 0;
                $totalSentAmount += $ta->debit_amount + 0;
            }
            $balanceAmount = $totalRecvAmount - $totalSentAmount;
        }

        return self::generateResponse('main.address',[
            'offset' => $offset,
            'canTag' => $canTag,
            'address' => $address,
            'totalReceived' => $totalRecvAmount,
            'totalSent' => $totalSentAmount,
            'balanceAmount' => $balanceAmount,
            'recentTxs' => $recentTxs,
            'numRecords' => $numTransactions,
            'numPages' => $numPages,
            'currentPage' => $page,
            'pageLimit' => $pageLimit,
        ]);
    }

    public function qr(?string $data = null): Response{
        if (!$data || strlen(trim($data)) == 0 || strlen(trim($data)) > 50) {
            return response();
        }
        $qrCode = new QrCode($data,errorCorrectionLevel:ErrorCorrectionLevel::High,foregroundColor:new Color(0,0,0,0),backgroundColor:new Color(255,255,255,0));

        $result = (new PngWriter)->write($qrCode);

        return response($result->getString())->header('Content-Type',$result->getMimeType());
    }

    /**
     * @throws Exception
     */
    public function apiblocksize($timePeriod = '24h'): array|JsonResponse{
        if (!request()->isMethod('get')) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid HTTP request method.'
            ],400);
        }

        $validPeriods = ['24h', '72h', '168h', '30d', '90d', '1y'];
        if (!in_array($timePeriod, $validPeriods)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid time period specified.'
            ],400);
        }

        $isHourly = (strpos($timePeriod, 'h') !== false);
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $dateFormat = $isHourly ? 'Y-m-d H:00:00' : 'Y-m-d';
        $sqlDateFormat = $isHourly ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
        $intervalPrefix = $isHourly ? 'PT' : 'P';
        $start = $now->sub(new DateInterval($intervalPrefix . strtoupper($timePeriod)));

        $resultSet = [];

        // get avg prices
        /*
        $conn_local = ConnectionManager::get('localdb');
        $stmt_price = $conn_local->execute("SELECT AVG(USD) AS AvgUSD, DATE_FORMAT(Created, '$sqlDateFormat') AS TimePeriod " .
                               "FROM PriceHistory WHERE DATE_FORMAT(Created, '$sqlDateFormat') >= ? GROUP BY TimePeriod ORDER BY TimePeriod ASC", [$start->format($dateFormat)]);
        $avgPrices = $stmt_price->fetchAll(\PDO::FETCH_OBJ);
        foreach ($avgPrices as $price) {
            if (!isset($resultSet[$price->TimePeriod])) {
                $resultSet[$price->TimePeriod] = [];
            }
            $resultSet[$price->TimePeriod]['AvgUSD'] = (float) $price->AvgUSD;
        }
        */

        $conn = DB::connection();
        // get avg block sizes for the time period
        $stmt = $conn->getPdo()->query("SELECT AVG(block_size) AS AvgBlockSize, DATE_FORMAT(FROM_UNIXTIME(block_time), '$sqlDateFormat') AS TimePeriod " .
            "FROM block WHERE DATE_FORMAT(FROM_UNIXTIME(block_time), '$sqlDateFormat') >= ? GROUP BY TimePeriod ORDER BY TimePeriod ASC", [$start->format($dateFormat)]);
        $avgBlockSizes = $stmt->fetchAll(PDO::FETCH_OBJ);
        foreach ($avgBlockSizes as $size) {
            if (!isset($resultSet[$size->TimePeriod])) {
                $resultSet[$size->TimePeriod] = [];
            }
            $resultSet[$size->TimePeriod]['AvgBlockSize'] = (float) $size->AvgBlockSize;
        }

        return [
            'success' => true,
            'data' => $resultSet
        ];
    }

    public function apirealtimeblocks(): array{
        // Load 10 blocks
        $blocks = Block::query()->select(['Height' => 'height', 'BlockTime' => 'block_time', 'TransactionCount'=>'tx_count'])->orderByDesc('Height')->limit(10)->get();

        return [
            'success' => true,
            'blocks' => $blocks
        ];
    }

    public function apirealtimetx(): array{
        // Load 10 transactions
        $txs = Transaction::query()->select(['id', 'Value' => 'value', 'Hash' => 'hash', 'InputCount' => 'input_count', 'OutputCount' => 'output_count', 'TxTime' => 'transaction_time'])->orderByDesc('TxTime')->limit(10)->get();

        return [
            'success' => true,
            'txs' => $txs
        ];
    }

    /*protected function _gettxoutsetinfo() {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $txOutSetInfo = new \stdClass();
        $txOutSetInfo->time = $now->format('c');

        $shouldRefreshSet = false;
        if (!$this->redis) {
            $shouldRefreshSet = true;
        } else {
            if (!$this->redis->exists(self::txOutSetInfo)) {
                $shouldRefreshSet = true;
            } else {
                $txOutSetInfo = json_decode($this->redis->get(self::txOutSetInfo));
                $lastTOSIDt = new \DateTime($txOutSetInfo->time);
                $diff = $now->diff($lastTOSIDt);
                $diffMinutes = $diff->i;
                if ($diffMinutes >= 15 || $txOutSetInfo->set == 'N/A') {
                    $shouldRefreshSet = true;
                }
            }
        }

        if ($shouldRefreshSet) {
            $req = ['method' => 'gettxoutsetinfo', 'params' => [],'id'=>rand()];
            try {
                $res = json_decode(self::curl_json_post(self::$rpcurl, json_encode($req)));
                if (!isset($res->result)) {
                    $txOutSetInfo->tosi = 'N/A';
                }
                $txOutSetInfo->tosi = $res->result;
            } catch (\Exception $e) {
                $txOutSetInfo->tosi = 'N/A';
            }
            $txOutSetInfo->time = $now->format('c');
            if ($this->redis) {
                $this->redis->set(self::txOutSetInfo, json_encode($txOutSetInfo));
            }
        }

        return (isset($txOutSetInfo->tosi)) ? $txOutSetInfo->tosi : 'N/A';
    }*/

    /**
     * @return array
     * @throws RedisException
     */
    public function apistatus(): array{
        // Get the max height block
        $height = 0;
        $difficulty = 0;
        $highestBlock = Block::query()->select(['height', 'difficulty'])->orderByDesc('height')->first();
        $height = $highestBlock->height;
        $difficulty = $highestBlock->difficulty;
        $lbcUsdPrice = $this->_getLatestPrice();

        // Calculate hash rate
        $hashRate = $this->_formatHashRate($this->_gethashrate());

        return [
            'success' => true,
            'status' => [
                'height' => $height,
                'difficulty' => number_format($difficulty, 2, '.', ''),
                'price' => $lbcUsdPrice,
                'hashrate' => $hashRate
            ]
        ];
    }

    public function apirecentblocks(): array{
        $blocks = Block::query()->select(['Difficulty' => 'difficulty', 'Hash' => 'hash', 'Height' => 'height', 'BlockTime' => 'block_time', 'BlockSize' => 'block_size', 'TransactionCount' => 'tx_count'])->orderByDesc('Height')->limit(6)->get();
        for ($i = 0; $i < count($blocks); $i++) {
            $blocks[$i]->Difficulty = number_format($blocks[$i]->Difficulty, 2, '.', '');
        }
        return [
            'success' => true,
            'blocks' => $blocks
        ];
    }

    public function apiaddrtag($base58address = null): array|JsonResponse{
        if (!isset($base58address) || strlen(trim($base58address)) !== 34) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid base58 address specified.'
            ],400);
        }
        if (strtolower(request()->method())!=='post') {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid HTTP request method.'
            ],400);
        }

        if (trim($base58address) == self::tagReceiptAddress) {
            return new JsonResponse([
                'error' => true,
                'message' => 'You cannot submit a tag request for this address.'
            ],400);
        }

        $data = [
            'Address' => $base58address,
            'Tag' => trim(request()->json('tag')),
            'TagUrl' => trim(request()->json('url')),
            'VerificationAmount' => request()->json('vamount')
        ];

        // verify
        $entity = new TagAddressRequest($data);
        if (strlen($entity->Tag) === 0 || strlen($entity->Tag) > 30) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Oops! Please specify a valid tag. It should be no more than 30 characters long.'
            ],400);
        }

        if (strlen($entity->TagUrl) > 0) {
            if (strlen($entity->TagUrl) > 200) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Oops! The link should be no more than 200 characters long.'
                ],400);
            }
            if (!filter_var($entity->TagUrl, FILTER_VALIDATE_URL)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Oops! The link should be a valid URL.'
                ],400);
            }
        } else {
            unset($entity->TagUrl);
        }

        if ($entity->VerificationAmount < 25.1 || $entity->VerificationAmount > 25.99999999) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Oops! The verification amount is invalid. Please refresh the page and try again.'
            ],400);
        }

        // check if the tag is taken
        $addrTag = Address::query()->select(['id'])->where('LOWER(Tag)',strtolower($entity->Tag))->first();
        if ($addrTag) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Oops! The tag is already taken. Please specify a different tag.'
            ],400);
        }

        // check for existing verification
        $exist = TagAddressRequest::query()->select(['id'])->where('Address',$base58address)->where('IsVerified',0)->first();
        if ($exist) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Oops! There is a pending tag verification for this address.'
            ],400);
        }

        // save the request
        if (!$entity->save()) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Oops! The verification request could not be saved. If this problem persists, please send an email to hello@aureolin.co'
            ]);
        }

        return [
            'success' => true,
            'tag' => $entity->Tag
        ];
    }

    public function apiaddrbalance($base58address = null): array|JsonResponse{
        if (!isset($base58address)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Base58 address not specified.'
            ],400);
        }

        $address = Address::query()->select(['id', 'balance'])->where('address',$base58address)->first();
        if (!$address) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Could not find address.'
            ],400);
        }

        return [
            'success' => true,
            ['balance' => ['confirmed' => $address->balance, 'unconfirmed' => 0]]
        ];
    }

    public function apiaddrutxo($base58address = null): array|JsonResponse{

        if (!isset($base58address)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Base58 address not specified.'
            ],400);
        }

        $arr = explode(',', $base58address);
        $addresses = Address::query()->select(['id'])->where('address','IN',$arr)->get();
        if (count($addresses) == 0) {
            return new JsonResponse([
                'error' => true,
                'message' => 'No base58 address matching the specified parameter was found.'
            ],404);
        }

        $addressIds = [];
        $params = [];
        foreach ($addresses as $address) {
            $addressIds[] = $address->id;
            $params[] = '?';
        }

        // Get the unspent outputs for the address
        $conn = DB::connection();
        $stmt = $conn->getPdo()->query(sprintf(
            'SELECT T.hash AS transaction_hash, O.vout, O.value, O.address_list, O.script_pub_key_asm, O.script_pub_key_hex, O.type, O.required_signatures, B.confirmations ' .
            'FROM transaction T ' .
            'JOIN output O ON O.transaction_id = T.id ' .
            'JOIN block B ON B.hash = T.block_hash_id ' .
            'WHERE O.id IN (SELECT O2.id FROM output O2 WHERE address_id IN (%s)) AND O.is_spent <> 1 ORDER BY T.transaction_time ASC', implode(',', $params)), $addressIds);
        $outputs = $stmt->fetchAll(PDO::FETCH_OBJ);

        $utxo = [];
        foreach ($outputs as $out) {
            $utxo[] = [
                'transaction_hash' => $out->transaction_hash,
                'output_index' => $out->vout,
                'value' => (int) bcmul($out->value, 100000000),
                'addresses' => json_decode($out->address_list),
                'script' => $out->script_pub_key_asm,
                'script_hex' => $out->script_pub_key_hex,
                'script_type' => $out->type,
                'required_signatures' => (int) $out->required_signatures,
                'spent' => false,
                'confirmations' => (int) $out->confirmations
            ];
        }

        return [
            'success' => true,
            'utxo' => $utxo,
        ];
    }

    public function apiutxosupply(): array{
        $circulating = 0;
        $txoutsetinfo = $this->_gettxoutsetinfo();

        $reservedcommunity = ['rEqocTgdPdoD8NEbrECTUPfpquJ4zPVCJ8'];
        $reservedoperational = ['r7hj61jdbGXcsccxw8UmEFCReZoCWLRr7t'];
        $reservedinstitutional = ['rKaAUDxr24hHNNTQuNtRvNt8SGYJMdLXo3'];
        $reservedaux = [
            'bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt',
            'bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2',
            'bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD',
            'bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz',
            'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc',
            'bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393',
            'bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH',
            'bCrboXVztuSbZzVToCWSsu1pEr2oxKHu9v',
            'bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h',
            'bX6napXtY2nVTBRc8PwULBuGWn2i3SCtrN'
        ];
        $allAddresses = array_merge($reservedcommunity, $reservedoperational, $reservedinstitutional, $reservedaux);

        $reservedtotal = Address::query()->selectRaw('SUM(balance) AS balance')->where('Addresses.address','IN',$allAddresses)->first();

        $circulating = (isset($txoutsetinfo) ? $txoutsetinfo->total_amount : 0) - ($reservedtotal->balance);
        return [
            'success' => true,
            'utxosupply' => ['total' => isset($txoutsetinfo) ? $txoutsetinfo->total_amount : 0, 'circulating' => $circulating]
        ];
    }

    protected function _formatHashRate($value): string{
        if ($value === 'N/A') {
            return $value;
        }

        /*if ($value > 1000000000000) {
            return number_format( $value / 1000000000000, 2, '.', '' ) . ' TH';
        }*/
        if ($value > 1000000000) {
            return number_format( $value / 1000000000, 2, '.', '' ) . ' GH/s';
        }
        if ($value > 1000000) {
            return number_format( $value / 1000000, 2, '.', '' ) . ' MH/s';
        }
        if ($value > 1000) {
            return number_format( $value / 1000, 2, '.', '' ) . ' KH/s';
        }

        return number_format($value) . ' H/s';
    }

    /**
     * @throws Exception
     */
    public static function curl_get($url): string|bool{
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        Log::debug('Request execution completed.');

        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new Exception(sprintf('The request failed: %s', $error), $errno);
        } else {
            curl_close($ch);
        }

        return $response;
    }

    private function _gethashrate(): mixed{
        $req = ['method' => 'getnetworkhashps', 'params' => [],'id'=>rand()];
        try {
            $res = json_decode(self::curl_json_post($this->rpcurl, json_encode($req)));
            if (!isset($res->result)) {
                return 0;
            }
            return $res->result;
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * @throws Exception
     */
    private static function curl_json_post($url, $data, $headers = []): string|bool{
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new Exception(sprintf('The request failed: %s', $error), $errno);
        } else {
            curl_close($ch);
        }

        // Close any open file handle
        return $response;
    }

    private function _isClaimBlocked($claim, $claimChannel, $blockedList): bool{
        if (!$blockedList || !isset($blockedList->data)) {
            // invalid blockedList response
            return false;
        }

        $blockedOutpoints = $blockedList->data->outpoints;
        $blockedClaims = $blockedList->data;
        $claimIsBlocked = false;
        foreach ($blockedClaims as $blockedClaim) {
            // $parts[0] = txid
            // $parts[1] = vout
            if ($claim->claim_id == $blockedClaim->claim_id) {
                $claimIsBlocked = true;
                break;
            }

            // check if the publisher (channel) is blocked
            // block the channel if that's the case
            if ($claimChannel && $claimChannel->claim_id == $blockedClaim->claim_id) {
                $claimIsBlocked = true;
                break;
            }
        }

        return $claimIsBlocked;
    }

    private function _gettxoutsetinfo(): mixed{
        $cachedOutsetInfo = Cache::get('api_requests:gettxoutsetinfo');
        if ($cachedOutsetInfo !== false) {
            $res = json_decode($cachedOutsetInfo);
            if (isset($res->result)) {
                return $res->result;
            }
        }

        $req = ['method' => 'gettxoutsetinfo', 'params' => [],'id'=>rand()];
        try {
            $response = self::curl_json_post($this->rpcurl, json_encode($req));
            $res = json_decode($response);
            if (!isset($res->result)) {
                return null;
            }
            Cache::set('api_requests:gettxoutsetinfo', $response);
            return $res->result;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @throws Exception
     */
    private function _getBlockedList(): mixed{
        $cachedList = Cache::get('api_requests:list_blocked');
        if ($cachedList !== false) {
            return $cachedList;
        }

        // get the result from the api
        $response = self::curl_get(self::blockedListUrl);
        Cache::set('api_requests:list_blocked', $response);
        return $response;
    }

    /**
     * @param string $viewName
     * @param array $data
     * @return JsonResponse|Response|View
     */
    public static function generateResponse(string $viewName,array $data): JsonResponse|Response|View{
        if(request()->wantsJson()){
            return response()->json($data);
        }
        $acceptable = request()->getAcceptableContentTypes();
        if(isset($acceptable[0]) && Str::contains(strtolower($acceptable[0]), ['/xml', '+xml'])){
            return response(Xml::fromArray(['response'=>$data])->saveXML())->header('Content-Type','application/xml');
        }
        return view($viewName,$data);
    }

}
