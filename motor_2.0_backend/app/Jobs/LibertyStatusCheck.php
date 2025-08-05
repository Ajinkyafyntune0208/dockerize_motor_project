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

class LibertyStatusCheck implements ShouldQueue
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
        $limit = $request->limit; // no of records to fetch
        $payment_request_response = DB::table('payment_request_response as prr')
                ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'prr.user_product_journey_id')
                ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
                //->whereNotIn('cjs.user_product_journey_id', PaymentCheckStatus::pluck('user_product_journey_id'))
                ->where('prr.is_processed','=','N')
                //->where('prr.status','!=',STAGE_NAMES['PAYMENT_SUCCESS'])
                //->whereNotIn('cjs.stage', [ STAGE_NAMES['POLICY_ISSUED']])
                ->where('prr.ic_id','=',32)
                //->where('prr.user_product_journey_id','=',270)
                ->select('up.unique_proposal_id','prr.*','cjs.stage')
                ->orderBy('prr.id', 'DESC')
                ->offset($offset)
                ->limit($limit)
                ->get();
//        print_r($payment_request_response);
//                    die;
        foreach ($payment_request_response as $key => $data) 
        {
            if($data->unique_proposal_id != NULL)
            {
                $company_alias = "liberty_videocon";
                $like_str = "%".$data->unique_proposal_id."%";
                $payment_response_data = DB::table('payment_response as pr')
                    ->where('company_alias','=',$company_alias)
                    ->where('response', 'LIKE', $like_str)
                    ->get();
                if(count($payment_response_data) > 0)
                {
                    foreach ($payment_response_data as $key => $payment_respone) 
                    {
                        $payment_respone_array = (array) json_decode($payment_respone->response);
                        $JourneyStage_data = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
                        
                        if($payment_respone_array['txnid'] == $data->unique_proposal_id)
                        {
                            if($payment_respone_array['status'] == 'success')
                            {
                                $updatePaymentResponse = [
                                    'status'  => STAGE_NAMES['PAYMENT_SUCCESS']
                                ];

                                PaymentRequestResponse::where('id', $data->id)
                                             ->update($updatePaymentResponse);
                                
                                //$PolicyDetails = PolicyDetails::where('proposal_id','=',$data->user_proposal_id)->get()->first();
//                                if(empty($PolicyDetails))
//                                {
//                                    $policy_data = [
//                                            'proposal_id'   => $data->user_proposal_id,
//                                            'policy_number' => $payment_respone_array[1],
//                                            'status'        => 'SUCCESS'
//                                        ];
//                                    PolicyDetails::insert($policy_data);
//                                }
//                                else
//                                {
//                                    if($PolicyDetails->policy_number == '')
//                                    {
//                                        $policy_data = [
//                                            'policy_number' => $payment_respone_array['polRef'],
//                                        ];
//                                        PolicyDetails::where('proposal_id','=',$data->user_proposal_id)->update($policy_data);
//                                    }
//                                }
                                
                                $enquiryId = [
                                    'enquiryId' => customEncrypt($data->user_product_journey_id)
                                ];
                                //$pdf_response = httpRequestNormal('https://motor_api_uat.adityabirlainsurancebrokers.com/api/generatePdf', 'POST', $enquiryId)['response'];

                                $pdf_response = httpRequestNormal(url('api/generatePdf'), 'POST', $enquiryId)['response'];
                                //$pdf_response = '';
                                $status_data = [
                                    'payment_request_response_id'   => $data->id,
                                    'user_product_journey_id'   => $data->user_product_journey_id,
                                    'proposal_id'               => $data->user_proposal_id,
                                    'ic_id'                     => 32,
                                    'order_id'                  => $data->unique_proposal_id,
                                    'existing_payment_status'   => $data->status,
                                    'updated_payment_status'    => STAGE_NAMES['PAYMENT_SUCCESS'],
                                    'existing_journey_stage'    => $data->stage,
                                    'updated_journey_stage'     => $JourneyStage_data->stage,
                                    'required_payment_data'     => '',
                                    'pdf_status'                => json_encode($pdf_response),
                                    'response'                  => $payment_respone->response,
                                    'created_at'                => date('Y-m-d H:i:s')
                                ];
                                DB::table('payment_check_status')->insert($status_data);
                                break;
                            }
                            else
                            {   
                                $payment_status = NULL;
                                if($payment_respone_array['status'] == 'failure')
                                {
                                   $payment_status = STAGE_NAMES['PAYMENT_FAILED'];
                                   PaymentRequestResponse::where('id', $data->id)
                                             ->update(['status'  => $payment_status]);
                                }
                                
                                $status_data = [
                                    'payment_request_response_id'   => $data->id,
                                    'user_product_journey_id'   => $data->user_product_journey_id,
                                    'proposal_id'               => $data->user_proposal_id,
                                    'ic_id'                     => 32,
                                    'order_id'                  => $data->unique_proposal_id,
                                    'existing_payment_status'   => $data->status,
                                    'updated_payment_status'    => $payment_status,
                                    'existing_journey_stage'    => $data->stage,
                                    'updated_journey_stage'     => $JourneyStage_data->stage,
                                    'required_payment_data'     => '',
                                    //'pdf_status'                => json_encode($pdf_response),
                                    'response'                  => $payment_respone->response,
                                    'created_at'                => date('Y-m-d H:i:s')
                                ];
                                DB::table('payment_check_status')->insert($status_data);                                
                            }
                        }
                    }                
                }
                else
                {
                    $status_data = [
                        'payment_request_response_id'   => $data->id,
                        'user_product_journey_id'       => $data->user_product_journey_id,
                        'proposal_id'                   => $data->user_proposal_id,
                        'ic_id'                         => 32,
                        'order_id'                      => $data->unique_proposal_id,
                        'existing_payment_status'       => $data->status,
                        'existing_journey_stage'        => $data->stage,
                        //'response'                      => json_encode($payment_respone->response),
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
                    'ic_id'                         => 32,
                    'order_id'                      => $data->unique_proposal_id,
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
