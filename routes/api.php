<?php
use App\Http\Controllers\ClaimsController;
use App\Http\Controllers\MainController;

use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(static function(){
    Route::get('/address/{addr}/tag',					[MainController::class,'apiaddrtag'])->where('addr','[A-Za-z0-9,]+');
    Route::get('/address/{addr}/utxo',					[MainController::class,'apiaddrutxo'])->where('addr','[A-Za-z0-9,]+');
    Route::get('/address/{addr}/balance',				[MainController::class,'apiaddrbalance'])->where('addr','[A-Za-z0-9,]+');
    Route::get('/address/{addr}/transactions',			[MainController::class,'apiaddrtx'])->where('addr','[A-Za-z0-9,]+');

    Route::get('/charts/blocksize/{period}',			[MainController::class,'apiblocksize'])->where('period','[012346789dhy]+');

    Route::get('/realtime/blocks',						[MainController::class,'apirealtimeblocks']);
    Route::get('/realtime/tx',							[MainController::class,'apirealtimetx']);
    Route::get('/recentblocks',							[MainController::class,'apirecentblocks']);
    Route::get('/status',								[MainController::class,'apistatus']);
    Route::get('/supply',								[MainController::class,'apiutxosupply']);
    Route::get('/recenttxs',							[MainController::class,'apirecenttxs']);

    Route::get('/claims/browse',						[ClaimsController::class,'apibrowse']);
});
