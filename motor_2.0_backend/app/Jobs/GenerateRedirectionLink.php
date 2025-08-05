<?php

namespace App\Jobs;
use Illuminate\Bus\Queueable;
use App\Models\UserProductJourney;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Http\Controllers\ProposalReportController;
use Illuminate\Http\Request;
use App\Http\Controllers\LeadController;
use App\Models\PolicyDetails;
use App\Models\RenewalNotificationTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Carbon;

class GenerateRedirectionLink implements ShouldQueue,ShouldBeUniqueUntilProcessing
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
        $renewal_days = config('LINK_GENERATION_DAYS');
        $renewal_days = explode(',',$renewal_days); 
        foreach ($renewal_days as $key => $days) 
        {
            //$records = DB::table('communication_logs')->select(DB::raw('old_user_product_journey_id'))->whereRaw('Date(created_at) = CURDATE()')->where('communication_module','RENEWAL')->where('days',$days)->get()->pluck('old_user_product_journey_id')->toArray();
            $user_product_journeys = UserProductJourney::with(['user_proposal' => function ($query) {
            $query->select(['user_proposal_id','user_product_journey_id', 'policy_end_date','vehicale_registration_number','first_name','last_name','mobile_number']);
                }])->whereHas('user_proposal', function ($query) use ($days) {
                    $query->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') = CURDATE() + INTERVAL {$days} DAY");
                })->whereHas('journey_stage', function ($query) {
                    $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                })->whereHas('corporate_vehicles_quote_request', function ($query)
                {
                    $query->whereNotNull('version_id')->whereRaw('version_id != ""');
                })->whereNotIn('user_product_journey_id', function ($query) use ($days) {
                    $query->select('old_user_product_journey_id')
                    ->from('link_generation_report');
                })->get(['user_product_journey_id', 'lead_source','created_on']);
            //dd($user_product_journeys);
                $i = 0;
            foreach ($user_product_journeys as $key => $value) 
	    {
                $i++;
                $policy_no = PolicyDetails::where('proposal_id',$value->user_proposal->user_proposal_id)
                                ->get(['policy_number'])->pluck('policy_number')[0];
                $policy_end_date = $value->user_proposal->policy_end_date;
                $old_user_product_journey_id = customDecrypt($value->journey_id);        
                $vehicale_registration_number = $value->user_proposal->vehicale_registration_number;
                $source = $value->lead_source;
                $payload = [
                    'reg_no'        => $vehicale_registration_number,
                    'policy_no'     => $policy_no,
                    'source'        => $source,
                    'segment'       => 'CAR',
                    'redirection'   => 'N'// for getting link of renewal
                ];
                $LeadController = new LeadController();
		$get_lead_link = $LeadController->getleads(request()->replace($payload));
                
                if(!isset($get_lead_link['new_user_product_journey_id']))
                {
                    continue;
                }
                $new_user_product_journey_id = $get_lead_link['new_user_product_journey_id'];
                $redirection_link = $get_lead_link['redirection_url'];
                $mobile_number = $value->user_proposal->mobile_number;
                $name = $value->user_proposal->first_name.' '.$value->user_proposal->last_name;
                
                $link_generation_data = [
                    'old_user_product_journey_id'   => ($old_user_product_journey_id),
                    'user_product_journey_id'       => ($new_user_product_journey_id),                
                    'name'                          => $name,
                    'mobile_number'                 => $mobile_number,
                    'vehicle_reg_no'                => $vehicale_registration_number,
                    'policy_no'                     => $policy_no,
                    'redirection_link'              => $redirection_link,
                    'source'                        => $source,
                    'days'                          => $days,
                    'prev_policy_end_end'           => Carbon::parse($policy_end_date)->format('Y-m-d'),
                    'created_at'                    => date('Y-m-d H:i:s')
                ];
                //dd($link_generation_data);
                DB::table('link_generation_report')->insert($link_generation_data);
                // if($i == 10)
                // {
                //     return;
                // }
            }  
        }
    }
}
