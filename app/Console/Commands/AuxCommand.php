<?php
namespace App\Console\Commands;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use PDO;

use App\Models\Address;
use App\Models\TagAddressRequest;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AuxCommand extends Command{

    const bittrex = 'https://api.bittrex.com/v3/markets/LBC-BTC/ticker';

    const coingeckoURL = 'https://api.coingecko.com/api/v3/simple/price?ids=lbry-credits&vs_currencies=btc';

    const blockchainticker = 'https://blockchain.info/ticker';

    const lbcpricekey = 'lbc.price';

    const pubKeyAddress = [0, 85];

    const scriptAddress = [5, 122];

    const tagrcptaddress = 'bLockNgmfvnnnZw7bM6SPz6hk5BVzhevEp';

    public static string $rpcurl;

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'LBRY Block Explorer - Block auxiliary command';

    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'explorer:aux {function?}';

    /**
     * Execute the console command.
     */
    public function handle(): void{
        self::$rpcurl = config('lbry.rpc_url');

        $function = $this->argument('function');
        if($function){
            $this->$function();
        }else{
            $this->warn('No arguments specified');
        }
    }

    /**
     * @throws \Throwable
     */
    public function verifytags(): void{
        self::lock('auxverify');

        $conn = DB::connection();

        $requests = TagAddressRequest::query()->where('IsVerified','<>',1)->get();
        foreach ($requests AS $req) {
            echo "Verifying tag for $req->Address, amount: $req->VerificationAmount... ";

            $req_date = $req->Created;
            $src_addr = $req->Address;
            $dst_addr = self::tagrcptaddress;

            // find a transaction with the corresponding inputs created on or after the date
            // look for the address ids
            $address = Address::query()->select(['Id'])->where('Address',$src_addr)->first();
            $veri_address = Address::query()->select(['Id'])->where('Address',$dst_addr)->first(); // TODO: Redis cache?
            if (!$address || !$veri_address) {
                echo "could not find source nor verification addresses. Skipping.\n";
                continue;
            }

            $src_addr_id = $address->Id;
            $dst_addr_id = $veri_address->Id;

            // find the inputs for the source address that were created after $req->Created - 1 hour
            $req_date->sub(new DateInterval('PT1H'));
            $stmt = $conn->getPdo()->query('SELECT DISTINCT I.TransactionId FROM Inputs I ' .
                'RIGHT JOIN (SELECT IIA.InputId FROM InputsAddresses IIA WHERE IIA.AddressId = ?) IA ON IA.InputId = I.Id ' .
                'JOIN Transactions T ON T.Id = I.TransactionId ' .
                'LEFT JOIN Blocks B ON B.Hash = T.BlockHash ' .
                'WHERE B.Confirmations > 0 AND DATE(I.Created) >= ?', [$src_addr_id, $req_date->format('Y-m-d')]);
            $tx_inputs = $stmt->fetchAll(PDO::FETCH_OBJ);


            $param_values = [$dst_addr_id];
            $params = [];
            foreach ($tx_inputs as $in) {
                $params[] = '?';
                $param_values[] = $in->TransactionId;
            }

            $num_inputs = count($tx_inputs);
            echo "***found $num_inputs inputs from address $src_addr.\n";

            if ($num_inputs == 0) {
                continue;
            }

            try {
                // check the outputs with the dst address
                $total_amount = 0;
                $stmt = $conn->getPdo()->query(sprintf('SELECT O.Value FROM Outputs O ' .
                    'RIGHT JOIN (SELECT IOA.OutputId, IOA.AddressId FROM OutputsAddresses IOA WHERE IOA.AddressId = ?) OA ON OA.OutputId = O.Id ' .
                    'WHERE O.TransactionId IN (%s)', implode(', ', $params)), $param_values);
                $tx_outputs = $stmt->fetchAll(PDO::FETCH_OBJ);
                foreach ($tx_outputs as $out) {
                    echo "***found output to verification address with value " . $out->Value . "\n";
                    $total_amount = bcadd($total_amount, $out->Value, 8);
                }

                if ($total_amount >= $req->VerificationAmount) {
                    $conn->beginTransaction();
                    echo "***$total_amount is gte verification amount: $req->VerificationAmount.\n";

                    // Update the tag in the DB
                    $conn->statement('UPDATE Addresses SET Tag = ?, TagUrl = ? WHERE Address = ?', [$req->Tag, $req->TagUrl, $src_addr]);

                    // Set the request as verified
                    $conn->statement('UPDATE TagAddressRequests SET IsVerified = 1 WHERE Id = ?', [$req->Id]);

                    $conn->commit();
                    echo "Data committed.\n";
                } else {
                    echo "***$total_amount is NOT up to verification amount: $req->VerificationAmount.\n";
                }
            } catch (Exception $e) {
                print_r($e);
                echo "Rolling back.\n";
                $conn->rollback();
            }
        }

        self::unlock('auxverify');
    }

    public function pricehistory(): void{
        self::lock('pricehistory');

        $conn = DB::connection('localdb');
        $redis = Redis::connection();

        try {

            // Only allow 5-minute update intervals
            $stmt = $conn->getPdo()->query('SELECT MAX(Created) AS LastUpdate FROM PriceHistory');
            $res = $stmt->fetch(PDO::FETCH_OBJ);

            $now = new DateTime('now', new DateTimeZone('UTC'));
            if ($res && strlen(trim($res->LastUpdate)) > 0) {
                $dt = new DateTime($res->LastUpdate, new DateTimeZone('UTC'));
                $diff = $now->diff($dt);
                $diffMinutes = $diff->i;
                if ($diffMinutes < 5) {
                    echo "Last update is less than 5 minutes ago. Quitting.\n";
                    self::unlock('pricehistory');
                    return;
                }
            }

            $coingeckoJSON = Cache::remember('coingecko',60,static function(){
                return json_decode(self::curl_get(self::coingeckoURL));
            });
            $blckjson = json_decode(self::curl_get(self::blockchainticker));

            if ($coingeckoJSON) {
                $btc = $coingeckoJSON->{'lbry-credits'}->btc;
                $usd = 0;
                if (isset($blckjson->USD)) {
                    $usd = $btc * $blckjson->USD->buy;
                    $priceInfo = [
                        'price' => number_format($usd, 3, '.', ''),
                        'time' => $now->format('c'),
                    ];
                    if ($redis) {
                        $redis->client()->set(self::lbcpricekey, json_encode($priceInfo));
                    }

                    // save the price history if both prices are > 0
                    if ($usd > 0 && $btc > 0) {
                        $conn->statement('INSERT INTO PriceHistory (USD, BTC, Created) VALUES (?, ?, UTC_TIMESTAMP())', [$usd, $btc]);
                        echo "Inserted price history item. USD: $usd, BTC: $btc.\n";
                    } else {
                        echo "Could not insert price history item. USD: $usd, BTC: $btc.\n";
                    }
                }
            } else {
                echo "CoinGecko requrest returned an invalid result.\n";
            }
        } catch (Exception $e) {
            print_r($e);
        }

        self::unlock('pricehistory');
    }

    /**
     * Lock
     * @param $process_name
     * @return void
     */
    public static function lock($process_name): void{
        $lock = Cache::lock($process_name);
        if(!$lock->get()){
            echo $process_name." is already running.\n";
            exit(0);
        }
    }

    /**
     * Unlock
     * @param $process_name
     * @return bool
     */
    public static function unlock($process_name): bool{
        return Cache::lock($process_name)->release();
    }

    /**
     * @param $url
     * @return string
     * @throws Exception
     */
    public static function curl_get($url): string{
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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

        return $response;
    }

}
