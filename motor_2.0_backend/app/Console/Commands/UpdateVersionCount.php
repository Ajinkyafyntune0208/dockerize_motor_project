<?php

namespace App\Console\Commands;

use App\Models\JourneyStage;
use Illuminate\Console\Command;
use App\Models\VersionCount;
use App\Models\MasterProductSubType;
use App\Models\UserProposal;
use App\Models\MasterCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateVersionCount extends Command
{
    protected $signature = 'update:versioncount {type}';
    protected $description = 'Update version count and start date with MMV details';
    
    public function handle()
    {
        if($this->argument('type')=='fetch') {
            
            $this->processInsertInitialDetails();
        }
        elseif($this->argument('type')=='update') {
            
            $this->processUpdateDetails();
        }
        else {
            $this->error('Invalid type provided.');
        }
    }

    private function processInsertInitialDetails()
    {   
        $this->info("Starting the first process: Inserting initial details...");

        $userProposals = $this->getUserProposals();
        
        foreach ($userProposals as $value) {
            $versionId = $value['version_id'];

            $existingRecord = VersionCount::where('version', $versionId)->first();

            if ($existingRecord) {
                $existingRecord->policy_count = $value['policy_count'];
                $existingRecord->save();
                $this->info("Policy count updated for version ID: $versionId.");
            } else {
                $newVersionCount = new VersionCount();
                $newVersionCount->version = $versionId;
                $newVersionCount->status = 'N';  
                $newVersionCount->policy_count = $value['policy_count'];
                $newVersionCount->save();

                $this->info("Initial details inserted for version ID: $versionId.");
            }
        }

        $this->info("First process completed.");
    }

    private function processUpdateDetails()
    {
        $this->info("Starting the second process: Updating existing details...");
        $get_mmv = VersionCount::select('version')->where('status', 'N')->pluck('version')->toArray();
        foreach($get_mmv as $versionId) {
            
            $dummy_data = get_fyntune_mmv_details(substr($versionId, 0, 3),$versionId);
            if (!empty($dummy_data['status']) && $dummy_data['status'] === true) {
                $make = $dummy_data['data']['manufacturer']['manf_name'] ?? null;
                $model = $dummy_data['data']['model']['model_name'] ?? null;
                $variant = $dummy_data['data']['version']['version_name'] ?? null;
        
                if ($make && $model && $variant) {
                    VersionCount::where('version', $versionId)->update([
                        'make' => $make,
                        'model' => $model,
                        'variant' => $variant,
                        'status' => 'Y',
                    ]);
    
                    $this->info("Version ID $versionId updated with make: $make, model: $model, variant: $variant, and status: Y.");
                } else {
                    $this->warn("Version ID $versionId does not have complete details to update.");
                }
            } else {
                $this->warn("No valid data found for version ID: $versionId.");
            }
                
        }
        
    }

    private function getUserProposals()
    {
        $duration = config('top_preferences_duration');
        $durationDays = is_numeric($duration) ? (int)$duration : 60;
        $startDate = now()->subDays($durationDays)->startOfDay();
        return JourneyStage::join(
            'corporate_vehicles_quotes_request',
            'corporate_vehicles_quotes_request.user_product_journey_id',
            '=',
            'cv_journey_stages.user_product_journey_id'
        )
        ->where('cv_journey_stages.stage', 'Policy Issued')
        ->whereDate('cv_journey_stages.created_at', '>=', $startDate)
        ->groupBy(
            'corporate_vehicles_quotes_request.version_id',
        )
        ->select(
            'corporate_vehicles_quotes_request.version_id',
            DB::raw('count(corporate_vehicles_quotes_request.version_id) as policy_count')
        )
        ->orderByDesc('policy_count')
        ->get();
    }

}