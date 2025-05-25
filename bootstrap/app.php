<?php
use App\Jobs\AddrTXJob;
use App\Jobs\BlocksJob;
use App\Jobs\BlockTXJob;
use App\Jobs\ClaimIndexJob;
use App\Jobs\FixZeroJob;
use App\Jobs\ForeverJob;
use App\Jobs\LiveTXJob;
use App\Jobs\PriceHistoryJob;
use App\Jobs\SpendsJob;
use App\Jobs\VerifyTagsJob;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions()
    ->withMiddleware(static function(Middleware $middleware){
        $middleware->removeFromGroup('api',$middleware->getMiddlewareGroups()['api']);
        $middleware->removeFromGroup('web',$middleware->getMiddlewareGroups()['web']);
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(static function(Schedule $schedule){
        $schedule->job(new AddrTXJob)->everyMinute();
        $schedule->job(new BlocksJob)->everyMinute();
        $schedule->job(new BlockTXJob)->everyMinute();
        $schedule->job(new ClaimIndexJob)->everyMinute();
        $schedule->job(new FixZeroJob)->everyMinute();
        $schedule->job(new ForeverJob)->everyMinute();
        $schedule->job(new LiveTXJob)->everyMinute();
        $schedule->job(new PriceHistoryJob)->everyMinute();
        $schedule->job(new SpendsJob)->everyMinute();
        $schedule->job(new VerifyTagsJob)->everyMinute();
    })
    ->create();
