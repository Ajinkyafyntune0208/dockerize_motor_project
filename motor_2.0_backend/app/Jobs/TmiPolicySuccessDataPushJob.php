<?php

namespace App\Jobs;

use App\Http\Controllers\TmipushdataController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TmiPolicySuccessDataPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /*
     * Create a new job instance.
     *
     * @return void
     */
    protected $enquiry_id;

    public function __construct($enquiry_id)
    {
        $this->enquiry_id = $enquiry_id;
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('constants.motorConstant.SMS_FOLDER') == 'tmibasl' ) {

            if ($this->enquiry_id == 0) {
                Log::info('Enquiry ID found as 0,No Data will be pushed.value passed is: ' . $this->enquiry_id);
                return false;
            }
            if(config ('TMI_PUSH_DATA_ENABLE') == 'Y'){
                TmipushdataController::pushapidata($this->enquiry_id);
                return true;    
            } else {  
                Log::info("TMI push data is disable. Please enable it to use this service.");
            }
        
        }
        return false;
    }

}
