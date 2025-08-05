<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use App\Models\UserProductJourney;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use App\Http\Controllers\LeadController;
use App\Models\PolicyDetails;
use App\Models\RenewalNotificationTemplates;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
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
//        WITH duplicates AS 
//        (SELECT RANK() OVER (PARTITION BY d.policy_number ORDER BY d.proposal_id) AS `rank`, j.user_product_journey_id, d.policy_number FROM user_product_journey j
//        INNER JOIN user_proposal l ON l.user_product_journey_id = j.user_product_journey_id
//        INNER JOIN policy_details d ON d.proposal_id = l.user_proposal_id
//        WHERE j.lead_source = 'HYUNDAI' ORDER BY `rank` DESC)
//        UPDATE cv_journey_stages s JOIN duplicates dd ON dd.user_product_journey_id = s.user_product_journey_id  SET s.stage = 'duplicate'  WHERE  dd.`rank` > 1;


//        DB::table('user_product_journey AS j')
//            ->select('RANK() OVER (PARTITION BY d.policy_number ORDER BY d.proposal_id) AS rank', 'j.user_product_journey_id', 'd.policy_number')
//            ->join('user_proposal AS l', 'l.user_product_journey_id', '=', 'j.user_product_journey_id')
//            ->join('policy_details AS d', 'd.proposal_id', '=', 'l.user_proposal_id')
//            ->where('j.lead_source', 'HYUNDAI')
//            ->orderByDesc('rank')
//            ->update(['s.stage' => 'duplicate'])
//            ->joinSub(function ($query) {
//                $query->select('RANK() OVER (PARTITION BY d.policy_number ORDER BY d.proposal_id) AS rank', 'j.user_product_journey_id', 'd.policy_number')
//                    ->from('user_product_journey AS j')
//                    ->join('user_proposal AS l', 'l.user_product_journey_id', '=', 'j.user_product_journey_id')
//                    ->join('policy_details AS d', 'd.proposal_id', '=', 'l.user_proposal_id')
//                    ->where('j.lead_source', 'HYUNDAI')
//                    ->orderByDesc('rank')
//                    ->limit(1)
//                    ->offset(1)
//                    ->as('dd');
//            }, 'dd', function ($join) {
//                $join->on('dd.user_product_journey_id', '=', 's.user_product_journey_id')
//                    ->where('dd.rank', '>', 1);
//            });

    }
}
