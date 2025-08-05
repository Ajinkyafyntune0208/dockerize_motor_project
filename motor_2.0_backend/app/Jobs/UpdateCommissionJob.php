<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Bus\Batchable;

class UpdateCommissionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $traceId;
    public $checkConfId;
    public $confId;
    public $extras;

    public function __construct($traceId, $checkConfId, $confId, $extras = [])
    {
        $this->traceId = $traceId;
        $this->checkConfId = $checkConfId;
        $this->confId = $confId;
        $this->extras = $extras;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $commissionDetails = \App\Models\PremiumDetails::where('user_product_journey_id', $this->traceId)
                ->select('commission_conf_id', 'commission_details')
                ->first();

            $confId = $commissionDetails?->commission_conf_id;

            $oldConfig = $commissionDetails?->commission_details;
            if (!empty($oldConfig)) {
                unset($oldConfig['productData']);
            }

            $log = \App\Models\SyncBrokerageLogs::create([
                'retrospective_conf_id' => $this->confId ?? $confId,
                'user_product_journey_id' => $this->traceId,
                'old_config' => $oldConfig,
                'old_conf_id' => $commissionDetails->commission_conf_id
            ]);


            $policyDate = \App\Models\PaymentRequestResponse::where('user_product_journey_id', $this->traceId)
                ->latest()
                ->pluck('created_at')
                ->first();

            $policyDate = date('Y-m-d', strtotime($policyDate));

            // Retrieve and validate commission rules from the brocore database.
            // This will involve checking the rules associated with the enquiry and product type.
            // If the rules are valid, we will store the calculated commission in the database.
            
            \App\Http\Controllers\BrokerCommissionController::saveCommissionDetails($this->traceId, [
                'confId' => $this->checkConfId ?  $confId : null,
                'retrospectiveChange' => true,
                'policyDate' => $policyDate,
                'isPayIn' => $this->extras['isPayIn'] ?? false
            ]);

            $commissionDetails = \App\Models\PremiumDetails::where('user_product_journey_id', $this->traceId)
                ->select('commission_conf_id', 'commission_details')
                ->first();

            $newConfig = $commissionDetails?->commission_details;
            if (!empty($newConfig)) {
                unset($newConfig['productData']);
            }
            
            \App\Models\SyncBrokerageLogs::updateOrCreate([
                'id' => $log->id
            ],[
                'new_config' => $newConfig,
                'new_conf_id' => $commissionDetails->commission_conf_id
            ]);

            // Trigger a manual data push to update the dashboard with the new commission data
            \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                'enquiryId' => $this->traceId,
            ]), $this->traceId, false);
        } catch (\Throwable $th) {
            Log::error($th . 'user Product Journey Id' . $this->traceId);
        }
    }
}
