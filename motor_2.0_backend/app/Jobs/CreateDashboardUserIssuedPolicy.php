<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CvAgentMapping;
use App\Models\UserProductJourney;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class CreateDashboardUserIssuedPolicy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $offset;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Int $offset)
    {
        $this->offset = $offset;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('constants.motorConstant.IS_USER_ENABLED') != "Y") {
            return;
        }
        $limit = 150;
        set_time_limit(0);
        // $user_product_journeys = UserProductJourney::with(['journey_stage', 'user_proposal'])/* ->doesntHave('agent_details') */->whereHas('journey_stage', function ($query) {
        //     $query->whereIn('stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]);
        // })->offset($this->offset)->limit($limit)->get();

        $user_product_journeys = UserProductJourney::with(['user_proposal'])/* ->doesntHave('agent_details') */->whereHas('user_proposal', function ($query) {
            $query->whereNotNull('mobile_number');
        })->whereHas('journey_stage', function ($query) {
            // $query->whereIn('stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]);
        })->offset($this->offset)->limit($limit)->get();

        $data = [];

        foreach ($user_product_journeys as $key => $user_product_journey) {
            $response = httpRequestNormal(config('constants.motorConstant.BROKER_USER_CREATION_API'), 'POST', [
                'mobile_no' => $user_product_journey->user_proposal->mobile_number,
                'email' => !empty($user_product_journey->user_proposal->email) ? $user_product_journey->user_proposal->email : ($user_product_journey->user_email ?? /* config('constants.brokerConstant.support_emai') */""),
                'first_name' => $user_product_journey->user_proposal->first_name,
                'last_name' => $user_product_journey->user_proposal->last_name,
            ], [
                "Content-Type" => "application/x-www-form-urlencoded"
            ],[],[], true, true, true);
            if (isset($response['response']['status']) && $response['response']['status'] == "true") {
                CvAgentMapping::updateorCreate([
                    'user_product_journey_id' => $user_product_journey->user_product_journey_id,
                    // 'seller_type' => 'U',
                    // 'agent_name' => $user_product_journey->user_proposal->first_name . " " . $user_product_journey->user_proposal->last_name,
                    // 'agent_id' => $response['response']['user_id'],
                    // 'agent_mobile' => $user_product_journey->user_proposal->mobile_number,
                    // 'agent_email' => $user_product_journey->user_proposal->email,
                    'user_id' => $response['response']['user_id'],
                    'stage' => "quote"
                ]);
            } else {
                $data[$user_product_journey->user_product_journey_id] = $response;
            }
        }
        // \Illuminate\Support\Facades\Storage::put($this->offset . '_' . time()."_demo.json", json_encode($data));
        if ($user_product_journeys->count() > 0) {
            self::dispatch($this->offset +  $limit)/* ->delay(now()->addSeconds(2)) */;
        }
    }
}
