<?php

namespace App\Providers;

use App\Events\CKYCInitiated;
use App\Events\clickedOnBuyNow;
use App\Events\JourneyStageUpdated;
use App\Events\LandedOnQuotePage;
use App\Events\PaymentInitiated;
use App\Events\PolicyGenerated;
use App\Events\ProposalSaved;
use App\Events\ProposalSubmitted;
use App\Events\PushDashboardData;
use App\Jobs\DashboardDataPush;
use App\Jobs\KafkaCvDataPushJob;
use App\Jobs\KafkaDataPushJob;
use App\Jobs\TmiPolicySuccessDataPushJob;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class DataPushServiceProvider extends ServiceProvider
{
    public $proposalSaveStages = [
        '1' => 'Proposal_VehicleOwner_Details',
        '2' => 'proposal_nominee_details',
        '3' => 'proposal_vehicle_details',
        '4' => 'previous_policy_details'
    ];
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // This event will be called only when 1st time user lands on Quote page
        Event::listen(function (LandedOnQuotePage $event) {
            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;
            $this->dispatchDataPushJob($event->enquiryId, $product_id, 'LandedOnQuotePage', true);
        });

        // This event will be called when user clicked on buyNow and lands on proposal page
        Event::listen(function (clickedOnBuyNow $event) {
            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;
            $this->dispatchDataPushJob($event->enquiryId, $product_id, 'clickedOnBuyNow', false);
        });
        // This event will be called when user initiates the KYC on proposal page
        Event::listen(function (CKYCInitiated $event) {
            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;
            $this->dispatchDataPushJob($event->enquiryId, $product_id, 'ckycInitiated', false);
        });
        // This event will be called whenever the proposal is saved
        Event::listen(function (ProposalSaved $event){
            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;
            $this->dispatchDataPushJob($event->enquiryId, $product_id, $this->proposalSaveStages[$event->proposalSavedStage] ?? 'ft_dashboard', true);
            
        });

        Event::listen(function (ProposalSubmitted $event) {
            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;
            $this->dispatchDataPushJob($event->enquiryId, $product_id, 'proposal', true);
        });

        Event::listen(function (PaymentInitiated $event) {

            //on payment initiated save the payout details
            \App\Http\Controllers\BrokerCommissionController::saveCommissionDetails($event->enquiryId, [
                'storeConfId' => true,
                'transactionType' => 'PAYMENT'
            ]);

            //on payment initiated save the payin details
            \App\Http\Controllers\BrokerCommissionController::saveCommissionDetails($event->enquiryId, [
                'storeConfId' => true,
                'isPayIn' => true,
                'transactionType' => 'PAYMENT'
            ]);


            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;

            if (in_array($product_id, [1, 2])) {
                $this->dispatchDataPushJob($event->enquiryId, $product_id, 'payment', false);
            } else {
                $this->dispatchDataPushJob($event->enquiryId, $product_id, 'payment', true);
            }
        });

        Event::listen(function (PolicyGenerated $event) {
            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;
            $this->dispatchDataPushJob($event->enquiryId, $product_id, 'policy', true);
        });

        //Calling this event when Journey Stages are Updated/Created
        Event::listen(function (JourneyStageUpdated $event) {

            $oldData = $event->journeyStage->getOriginal();
            $oldStage = null;
            $pushData = true;
            if (!empty($oldData['stage'])) {
                $oldStage = $oldData['stage'];
                $incomingStage = $event->journeyStage->stage;
                if (
                    config('constants.brokerConstant.BLOCK_SAME_STAGE_DATA_PUSH') == 'Y' &&
                    in_array(strtolower($oldStage), array_map( 'strtolower', [
                        STAGE_NAMES['PAYMENT_INITIATED'],
                        STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                        STAGE_NAMES['PAYMENT_SUCCESS'],
                    ] ) ) && strtolower($oldStage) == strtolower($incomingStage)
                ) {
                    //block data push for same stage updation
                    $pushData = false;
                }
            }
            storeJourneyStageLog($event->journeyStage->user_product_journey_id, $oldStage, $event->journeyStage->stage);

            if ($pushData) {
                $product_id = UserProductJourney::find($event->journeyStage->user_product_journey_id)?->product_sub_type_id;
                if (in_array(
                    strtolower($event->journeyStage->stage),
                    array_map( 'strtolower', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_FAILED']] )
                )) {
                    try {
                        if (in_array(
                            strtolower($event->journeyStage->stage),
                            array_map( 'strtolower', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS']] )
                        )) {
                            $this->tmiDataPushJob($event->journeyStage->user_product_journey_id, $product_id);
                        }
                    } catch (\Exception $e) {
                        log::error($e->getMessage());
                    }
                    $this->dispatchDataPushJob($event->journeyStage->user_product_journey_id, $product_id, 'policy', true);
                } elseif (in_array(
                    strtolower($event->journeyStage->stage),
                    array_map( 'strtolower', [ STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_REJECTED']] )
                )) {
                    $this->dispatchDataPushJob($event->journeyStage->user_product_journey_id, $product_id, 'inspection', true);
                } else {
                    $this->dispatchDataPushJob($event->journeyStage->user_product_journey_id, $product_id, 'ft_dashboard');
                }
            }
        });

        // Below event responsible for Dashboard DataPush
        Event::listen(function (PushDashboardData $event) {
            $product_id = UserProductJourney::find($event->enquiryId)?->product_sub_type_id;
            $this->dispatchDataPushJob($event->enquiryId, $product_id, 'ft_dashboard');
        });

    }

    /**
     * Dispatch Data Push Job (Car-Bike or Commercial Vehicle based on parameters)
     * @param Integer $enquiryId - User Journey ID
     * @param Integer $product_sub_id - User selected Product subtype ID
     * @param String Triggered event name ('ckycInitiated', 'clickedOnBuyNow', 'LandedOnQuotePage', etc)
     * @param Boolean $include_cv - If true then will push data using Kafka's CV Job file.
     * @return void
     */
    public function dispatchDataPushJob($enquiryId, $product_sub_id, $eventName, $include_cv = true): void
    {
        if (config('constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED') == 'Y') {
            DashboardDataPush::dispatch($enquiryId)->onQueue(env('DASHBOARD_PUSH_QUEUE_NAME'));
        } else if (config('constants.motorConstant.KAFKA_DATA_PUSH_ENABLED') == 'Y') {
            if (in_array($eventName, array_values($this->proposalSaveStages)) && config('ProposalKafkaDataPushEnabled') == 'Y') {
                $corporateData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->select('is_renewal')->first();
                if ($corporateData && $corporateData->is_renewal == 'Y') {
                    return;
                }
            }
            if ($eventName == 'ckycInitiated') {
                $corporateData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->select('is_renewal')->first();
                // We don't need to trigger kafka in renewal case - Jira ID :4367 
                if ((config('IS_CKYC_KAFKA_DATA_PUSH_ENABLED') == 'Y') && $corporateData?->is_renewal == 'Y') {
                    return;
                } else if (config('IS_CKYC_KAFKA_DATA_PUSH_ENABLED') != 'Y') {
                    return;
                }
            } else if (in_array($eventName, ['clickedOnBuyNow', 'LandedOnQuotePage']) && (config('IS_PRE_PAYMENT_KAFKA_DATA_PUSH_ENABLED') != 'Y')) {
                return;
            } else if ($eventName == 'ft_dashboard') {
                // We don't need to trigger the Kafka in case of FT dashboard data push
                return;
            } elseif (in_array($eventName, array_values($this->proposalSaveStages)) && (config('ProposalKafkaDataPushEnabled') != 'Y')) {
                return;
            }

            if (!in_array($enquiryId, [15, 0])) {
                if (in_array($product_sub_id, [1, 2])) { // If Enquiry ID is not of car and bike then it is Commercial Vehicle
                    KafkaDataPushJob::dispatch($enquiryId, $eventName)->onQueue(env('QUEUE_NAME'));
                } else if (is_numeric($product_sub_id) && $include_cv) {
                    KafkaCvDataPushJob::dispatch($enquiryId, $eventName)->onQueue(env('QUEUE_NAME'));
                }
            }
        }

    }
    public function tmiDataPushJob($enquiryId)
    {
        if (config('constants.motorConstant.SMS_FOLDER') == 'tmibasl' && config ('TMI_PUSH_DATA_ENABLE') == 'Y') {
            TmiPolicySuccessDataPushJob::dispatch($enquiryId)->onQueue(env('TMI_PUSH_QUEUE'));
        }
    }
}
