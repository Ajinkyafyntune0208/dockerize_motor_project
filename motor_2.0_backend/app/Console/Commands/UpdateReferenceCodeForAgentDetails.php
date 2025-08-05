<?php

namespace App\Console\Commands;

use App\Http\Controllers\Extra\PosToPartnerUtility;
use App\Models\ProposalExtraFields;
use Illuminate\Console\Command;

class UpdateReferenceCodeForAgentDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:reference_code_for_agent_details';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'updating reference code for agent details';

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

        ProposalExtraFields::whereNull('reference_code')
            ->orWhere('reference_code', 1)
            ->orWhere('reference_code', '')
            ->select('id' , 'original_agent_details', 'enquiry_id')
            ->chunkById(35, function ($results) {

                foreach ($results as $index => $agentData) {

                    try {

                        $traceId = $index + 1;

                        $enquiry_id = $agentData->enquiry_id;
                        $agentData = json_decode($agentData->original_agent_details);

                        if (!empty($agentData->user_name) && !empty($agentData->seller_type)) {

                            $partnerDataUpdate = PosToPartnerUtility::posToPartnerFiftyLakhIdv($agentData, $enquiry_id, true);

                            if ($partnerDataUpdate['status'] && $partnerDataUpdate['msg'] == "offline renewal data upload") {
                                if (!empty($partnerDataUpdate['data'])) {
                                    $partnerDataUpdate = $partnerDataUpdate['data'];

                                    ProposalExtraFields::updateOrCreate(["enquiry_id" => $enquiry_id], [
                                        "reference_code" => $partnerDataUpdate['reference_code']
                                    ]);
                                }

                                $this->info("{$traceId} - Process Success for trace id => {$enquiry_id}");
                            } else {

                                $reason = $partnerDataUpdate['msg'] ?? null;
                                $this->info("Error in Upadation for trace id => {$enquiry_id} | Reason: {$reason}");
                            }
                        }
                    } catch (\Throwable $th) {

                        $this->info("{$traceId} - Process Failed for trace id => {$enquiry_id} due to {$th->getMessage()}");
                    }
                }
            });

        $this->info("Process Completed");
    }
}
