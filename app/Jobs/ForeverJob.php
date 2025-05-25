<?php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ForeverJob implements ShouldQueue{

    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void{
        //TODO: What is this? `pkill -f forevermempool`
        Cache::lock('forevermempool')->forceRelease();
        Artisan::call('explorer:block forevermempool');
    }

}
