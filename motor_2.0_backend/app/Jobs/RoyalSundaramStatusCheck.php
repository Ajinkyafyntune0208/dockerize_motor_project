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
use App\Http\Controllers\Payment\Services\Car\royalSundaramPaymentGateway;

class RoyalSundaramStatusCheck implements ShouldQueue
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
    public function handle(Request $request)
    {
        $offset = $request->start_index; // start row index.
        $limit = $request->limit; // no of records to fetch/ get .

       //Model::where(['zone_id'=>1])->offset($offset)->limit($limit)->get();
       $payment_request_response = DB::table('payment_request_response as prr')
                ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'prr.user_product_journey_id')
                ->where('prr.is_processed','=','N')
                //->whereNotIn('cjs.user_product_journey_id', PaymentCheckStatus::pluck('user_product_journey_id'))
                //->where('prr.status','!=',STAGE_NAMES['PAYMENT_SUCCESS'])
                //->whereNotIn('cjs.stage', [ STAGE_NAMES['POLICY_ISSUED']])
                ->where('prr.ic_id','=',35)
                ->select('prr.*','cjs.stage')
                ->orderBy('prr.id', 'DESC')
                ->offset($offset)
                ->limit($limit)
                //->orderBy('prr.id', 'ASC')
                //->limit(10)
                ->get();
       
        foreach ($payment_request_response as $key => $data) 
        {
            if($data->order_id != NULL)
            {
                $payment_status_data = [
                        'enquiryId'       => $data->user_product_journey_id,
                        'payment_request_response_id'   => $data->id,
                        'order_id'                      => $data->order_id
                    ];
                $payment_response = royalSundaramPaymentGateway::payment_status((object) $payment_status_data);
//                $payment_response = '{
//                                    "data": {
//                                        "quoteId": "BA502965VPC12046426",
//                                        "premium": "6587.94",
//                                        "policyNumber": "VPRB044585000100",
//                                        "policyDownloadLink": "http://10.46.194.192/Services/Mailer/DownloadPdf?quoteId=BA502965VPC12046426&type=PurchasedPdf&expiryDate=01/07/2023&proposerDob=05/11/1984",
//                                        "policyConverted": "Yes",
//                                        "transactionNumber": "WHMP1230604835"
//                                    },
//                                    "code": "S-1701",
//                                    "message": "Transaction Check Status Fetched Successfully"
//                                }';

                $payment_response  = json_decode($payment_response,True);
//                print_r($payment_response);
//                die;
                if(isset($payment_response['data']) && $payment_response['data']['policyConverted'] == 'Yes')
                {
                    $JourneyStage_data = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
                    $payment_status = STAGE_NAMES['PAYMENT_SUCCESS'];                         
                    $updatePaymentResponse = [
                        'status'  => $payment_status
                    ];
                    PaymentRequestResponse::where('id', $data->id)
                        ->update($updatePaymentResponse);

                    $policyNumber = $payment_response['data']['policyNumber'] ?? NULL;
                    if($policyNumber != NULL)
                    {
                        UserProposal::where('user_proposal_id' , $data->user_proposal_id)->update([
                            'policy_no' => $policyNumber
                        ]);
                        $PolicyDetails = PolicyDetails::where('proposal_id','=',$data->user_proposal_id)->get()->first();
                        if(empty($PolicyDetails))
                        {
                            $policy_data = [
                                    'proposal_id'   => $data->user_proposal_id,
                                    'policy_number' => $policyNumber,
                                    'status'        => 'SUCCESS'
                                ];
                            PolicyDetails::insert($policy_data);
                        }
                        else
                        {
                            if($PolicyDetails->policy_number == '')
                            {
                                $policy_data = [
                                    'policy_number' => $policyNumber,
                                ];
                                PolicyDetails::where('proposal_id','=',$data->user_proposal_id)->update($policy_data);
                            }
                        }
                    }

                    $enquiryId = [
                        'enquiryId' => customEncrypt($data->user_product_journey_id)
                    ];

                    //$pdf_response = httpRequestNormal('https://motor_api_uat.adityabirlainsurancebrokers.com/api/generatePdf', 'POST', $enquiryId)['response'];
                    $pdf_response = httpRequestNormal(url('api/generatePdf'), 'POST', $enquiryId)['response'];
                    //$pdf_response = '';
                    $status_data = [
                        'payment_request_response_id'   => $data->id,
                        'user_product_journey_id'       => $data->user_product_journey_id,
                        'proposal_id'                   => $data->user_proposal_id,
                        'ic_id'                         => 35,
                        'order_id'                      => $data->order_id,
                        'existing_payment_status'       => $data->status,
                        'updated_payment_status'        => $payment_status,
                        'existing_journey_stage'        => $data->stage,
                        'updated_journey_stage'         => $JourneyStage_data->stage,
                        'pdf_status'                    => json_encode($pdf_response),
                        'response'                      => json_encode($payment_response),
                        'created_at'                    => date('Y-m-d H:i:s')
                    ];
                    DB::table('payment_check_status')->insert($status_data);
                } 
                else
                {                 
                    $status_data = [
                        'payment_request_response_id'   => $data->id,
                        'user_product_journey_id'       => $data->user_product_journey_id,
                        'proposal_id'                   => $data->user_proposal_id,
                        'ic_id'                         => 35,
                        'order_id'                      => $data->order_id,
                        'existing_payment_status'       => $data->status,
                        'existing_journey_stage'        => $data->stage,
                        'response'                      => json_encode($payment_response),
                        'created_at'                    => date('Y-m-d H:i:s')
                    ];
                    DB::table('payment_check_status')->insert($status_data);                 
                }                
            }
            else
            {
                $status_data = [
                    'payment_request_response_id'   => $data->id,
                    'user_product_journey_id'       => $data->user_product_journey_id,
                    'proposal_id'                   => $data->user_proposal_id,
                    'ic_id'                         => 35,
                    'order_id'                      => $data->order_id,
                    'existing_payment_status'       => $data->status,
                    'existing_journey_stage'        => $data->stage,
                    //'response'                      => json_encode($payment_respone->response),
                    'created_at'                    => date('Y-m-d H:i:s')
                ];
                DB::table('payment_check_status')->insert($status_data);
            }            
            PaymentRequestResponse::where('id', $data->id)
                ->update(['is_processed'  => 'Y']);
        }
    }
}
