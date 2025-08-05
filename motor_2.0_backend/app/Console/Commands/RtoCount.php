<?php

namespace App\Console\Commands;

use App\Models\JourneyStage;
use App\Models\RtoCount as ModelsRtoCount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RtoCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:rtocount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update rto count and start date with RTO details';

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
        // return 0;
        $this->insertToRtoCount();
    }
    private function insertToRtoCount()
    {   
        $this->info("Starting the first process: Inserting initial details...");

        $userProposals = $this->getUserProposals();
        
        foreach ($userProposals as $value) {
            $rto_code = $value['rto_code'];

            $existingRecord = ModelsRtoCount::where('rto_code', $rto_code)->first();

            if ($existingRecord) {
                $existingRecord->policy_count = $value['policy_count'];
                $existingRecord->save();
                $this->info("Policy count updated for version ID: $rto_code.");
                $this->updateRtoCityNames($existingRecord->id, $rto_code);
            }else {
                $newRecord = ModelsRtoCount::create([
                    'rto_code' => $rto_code,
                    'policy_count' => $value['policy_count'],
                ]);
                $this->info("Initial details inserted for RTO Code: $rto_code.");
    
                $this->updateRtoCityNames($newRecord->id, $rto_code);
            }
        }

        $this->info("First process completed.");
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
                ->join('master_rto', 'corporate_vehicles_quotes_request.rto_code', '=', 'master_rto.rto_code')
                ->where('cv_journey_stages.stage', 'Policy Issued')
                ->whereDate('cv_journey_stages.created_at', '>=', $startDate)
                ->groupBy(
                    'corporate_vehicles_quotes_request.rto_code',
                )
                ->select(
                    'corporate_vehicles_quotes_request.rto_code',
                    DB::raw('COUNT(corporate_vehicles_quotes_request.version_id) as policy_count')
                )
                ->orderByDesc('policy_count')
                ->get();
    }
    private function updateRtoCityNames($rto_id, $rto_code)
{
    $rtoName = DB::table('master_rto')
        ->where('rto_code', $rto_code)
        ->value('rto_name');

    if ($rtoName) {
        DB::table('rto_city_names')->updateOrInsert(
            ['rto_id' => $rto_id],
            [
                'rto_city_name' => $rtoName,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->info("RTO city name updated for RTO ID: $rto_id with name: $rtoName.");
    } else {
        $this->info("RTO name not found for RTO Code: $rto_code.");
    }
}
}
