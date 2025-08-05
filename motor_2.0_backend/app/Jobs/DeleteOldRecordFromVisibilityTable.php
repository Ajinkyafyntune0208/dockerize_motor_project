<?php

namespace App\Jobs;

use App\Models\QuoteVisibilityLogs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteOldRecordFromVisibilityTable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $idsToBeDeleted = [];
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ids = [])
    {
        $this->idsToBeDeleted = $ids;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!empty($this->idsToBeDeleted)) {
            QuoteVisibilityLogs::destroy($this->idsToBeDeleted);
        }
    }
}
