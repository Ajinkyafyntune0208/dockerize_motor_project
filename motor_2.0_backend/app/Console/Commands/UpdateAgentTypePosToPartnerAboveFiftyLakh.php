<?php

namespace App\Console\Commands;

use App\Http\Controllers\Extra\PosToPartnerUtility;
use App\Models\CvAgentMapping;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class UpdateAgentTypePosToPartnerAboveFiftyLakh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:posToPartnerData'; //encrypt:existingData

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Seller Type from pos => partner for above 50 lakh IDV';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Process started");

        CvAgentMapping::join('cv_journey_stages', 'cv_journey_stages.user_product_journey_id', '=', 'cv_agent_mappings.user_product_journey_id')
            ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'cv_journey_stages.user_product_journey_id')
            ->where(
                [
                    ['cv_journey_stages.stage', '=', 'Policy Issued'],
                    ['cv_agent_mappings.seller_type', '=', 'P'],
                    ['quote_log.idv', '>=', 5000000],
                    ['cv_agent_mappings.user_name', '!=', ''],
                ]
            )->whereNotNull('cv_agent_mappings.user_name')
            ->select('cv_agent_mappings.seller_type', 'cv_agent_mappings.user_name', 'cv_agent_mappings.user_product_journey_id')
            ->chunk(50, function ($results) {

                foreach ($results as $index => $agentData) {

                    try {

                        $traceId = $index + 1;

                        $enquiry_id = $agentData->user_product_journey_id;
                        $partnerDataUpdate = PosToPartnerUtility::posToPartnerFiftyLakhIdv($agentData, $enquiry_id, false);

                        if ($partnerDataUpdate['status'] && $partnerDataUpdate['msg'] == "Partner data Updated.") {

                            \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                                'enquiryId' => $enquiry_id,
                            ]), $enquiry_id, false);

                            $this->info("{$traceId} - Process Success for trace id => {$enquiry_id}");
                        } else {
                            
                            $reason = $partnerDataUpdate['msg'] ?? null;
                            $this->info("Error in Upadation for trace id => {$enquiry_id} | Reason: {$reason}");
                        }
                    } catch (\Throwable $th) {

                        $this->info("{$traceId} - Process Failed for trace id => {$enquiry_id} due to {$th->getMessage()}");
                    }
                }
            });

        $this->info("Process Completed");
    }
}