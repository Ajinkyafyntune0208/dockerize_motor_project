<?php

namespace App\Jobs;

use App\Models\UserProductJourney;
use App\Models\UserProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateCityStateInUserProposal implements ShouldQueue
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
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $proposal_data = UserProposal::leftJoin('cv_agent_mappings', 'cv_agent_mappings.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->leftJoin('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->whereNotNull('user_proposal.pincode')
            ->whereNull('user_proposal.city')
            ->whereNull('user_proposal.state')
            ->where('cv_agent_mappings.agent_name', '=', 'embedded_scrub')
            ->whereNotNull('quote_log.premium_json')
            ->whereBetween('cv_agent_mappings.created_at', [config('constants.motor.UPDATE_STATE_CITY_IN_EMBEDDED_LINK_FROM_DATE'), config('constants.motor.UPDATE_STATE_CITY_IN_EMBEDDED_LINK_TO_DATE')])
            ->select('user_proposal.user_product_journey_id', 'user_proposal.pincode', 'user_proposal.state', 'user_proposal.city', 'quote_log.premium_json')
            ->get();

        if ($proposal_data)
        {
            foreach ($proposal_data as $proposal)
            {
                $premium_json = json_decode($proposal->premium_json, TRUE);

                $address_details = httpRequestNormal(url('/api/getPincode?pincode=' . $proposal->pincode . '&companyAlias=' . $premium_json['company_alias'] . '&enquiryId=' . customEncrypt($proposal->user_product_journey_id)), 'GET');

                if (isset($address_details['response']['status']) && $address_details['response']['status'])
                {
                    UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->update([
                            'state' => $address_details['response']['data']['state']['state_name'],
                            'city' => $address_details['response']['data']['city'][0]['city_name']
                        ]);
                }
            }
        }
    }
}
