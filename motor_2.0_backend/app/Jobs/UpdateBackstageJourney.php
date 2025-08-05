<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentCheckStatus;
use App\Models\UserProposal;
use App\Models\JourneyStage;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use Illuminate\Http\Request;

class UpdateBackstageJourney implements ShouldQueue
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
        $payment_data = DB::table('payment_request_response as prr')
                ->leftjoin('policy_details as d', 'd.proposal_id', '=', 'prr.user_proposal_id')
                ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'prr.user_product_journey_id')
                ->join('master_company as c', 'c.company_id', '=', 's.ic_id')
                ->where('prr.active','1')
                ->where('prr.status',STAGE_NAMES['PAYMENT_SUCCESS'])
                ->whereNotIn('s.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS']])
                //->groupBy('prr.user_product_journey_id')
                ->select('prr.user_product_journey_id','prr.response','prr.status','s.stage', 's.updated_at', 'd.policy_number', 'd.pdf_url' ,'c.company_alias','c.company_id')
                ->orderBy('prr.user_product_journey_id')
                ->get();

            foreach ($payment_data as $key => $data)
            {
                if(!empty($data->policy_number) && !empty($data->pdf_url))
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $data->user_product_journey_id,
                        'ic_id' => $data->company_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);
                }
                else if(empty($data->policy_number) || empty($data->pdf_url))
                {
                    $company_name = $data->company_alias;
                    $enquiry_id = $data->user_product_journey_id;
                    $payload = [
                        'enquiryId' => customEncrypt($enquiry_id),
                        //companyAlias' => $company_name
                    ];
                    $result[$key] = httpRequestNormal(url('api/generatePdf'), 'POST', $payload)['response'];

                    $JourneyStage_data = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
                    $old_stage = $data->stage;
                    $new_stage = $JourneyStage_data->stage;

                    if($old_stage == $new_stage)
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $data->user_product_journey_id,
                            'ic_id' => $data->company_id,
                            'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]); 
                    }
                }
            }
    }
}
