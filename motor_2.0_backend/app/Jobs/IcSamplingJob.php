<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Http\Controllers\IcConfig\IcConfigurationController;
use Illuminate\Support\Facades\Log;
use App\Models\IcIntegrationType;

class IcSamplingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $single;
    public $data;

    public function __construct($single = false, $data = [])
    {
        $this->single = $single;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->single) {
            IcConfigurationController::extractIcAttributes($this->data);
        } else {
            $integrationTypes = IcIntegrationType::whereNull('deleted_at')
            ->get()
            ->toArray();

            Log::info("IC sampling schedular started..");
            foreach ($integrationTypes as $value) {
                IcSamplingJob::dispatch(true, $value);
            }
        }
    }
}
