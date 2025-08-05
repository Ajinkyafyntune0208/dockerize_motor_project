<?php

namespace App\Jobs;

use App\Http\Controllers\MasterDataManagementController;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MasterDataManagementSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected int $master_id)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->master_id)) {
            throw new Exception('MDM Sync Issue : Master ID is empty.');
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1800);

        $controller = new MasterDataManagementController();
        $controller->syncSingleMaster($this->master_id);
    }
}
