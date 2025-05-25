<?php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class BlocksJob implements ShouldQueue{

    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void{
        Artisan::call('explorer:block parsenewblocks');
        Cache::lock('parsenewblocks')->forceRelease();
        Artisan::call('explorer:block parsetxs');
        Cache::lock('parsetxs')->forceRelease();
    }

}
