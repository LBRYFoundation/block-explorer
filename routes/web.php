<?php
use App\Http\Controllers\MainController;

use Illuminate\Support\Facades\Route;

Route::prefix('/')->group(static function(): void{
    Route::get('/',								[MainController::class,'index']);
    Route::get('/address/{address?}',			[MainController::class,'address'])->where('address','.*');
    Route::get('/blocks/{height?}',				[MainController::class,'blocks'])->where('height','.*');
    Route::get('/claims/{claim?}',				[MainController::class,'claims'])->where('claim','.*');
    Route::get('/find',							[MainController::class,'find']);
    Route::get('/realtime',						[MainController::class,'realtime']);
    Route::get('/stats',						[MainController::class,'stats']);
    Route::get('/tx/{transaction?}',			[MainController::class,'tx'])->where('transaction','.*');
    Route::get('/qr/{data?}',					[MainController::class,'qr'])->where('data','.*');
});
