<?php
use App\Http\Controllers\MainController;

use App\Models\Claim;
use Illuminate\Support\Facades\Route;

Route::prefix('/')->group(static function(): void{
    Route::redirect('/',						'https://mempool.lbry.org');
    Route::redirect('/address/{address?}',		'https://mempool.lbry.org/address/{address}')->where('address','.*');
    Route::redirect('/blocks/{height?}',		'https://mempool.lbry.org/block/{height}')->where('height','.*');
    Route::get('/claims/{claim?}',				static function(string $claim){
        $ch = curl_init('https://nova.lbry.org/api/rpc?url=http://localhost:5279');
        curl_setopt($ch,CURLOPT_HTTPHEADER,[
            'Content-Type: application/json',
        ]);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode([
            'jsonrpc' => '2.0',
            'id' => rand(),
            'method' => 'claim_search',
            'params' => [
                'claim_id' => bin2hex(strrev(hex2bin($claim))),
            ],
        ]));
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        $resp = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($resp,true);

        $canonicalURL = $json['result']['items'][0]['canonical_url'] ?? null;
        if($canonicalURL){
            return redirect('https://nova.lbry.org/claim/'.$canonicalURL);
        }
        return redirect('https://nova.lbry.org');
    })->where('claim','.*');
    Route::redirect('/find',					'https://mempool.lbry.org');
    Route::redirect('/realtime',				'https://mempool.lbry.org');
    Route::redirect('/stats',					'https://mempool.lbry.org');
    Route::redirect('/tx/{transaction?}',		'https://mempool.lbry.org/nl/tx/{transaction}')->where('transaction','.*');
    Route::redirect('/qr/{data?}',				'https://mempool.lbry.org')->where('data','.*');
});
