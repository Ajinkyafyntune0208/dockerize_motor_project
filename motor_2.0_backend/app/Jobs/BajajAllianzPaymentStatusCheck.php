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
use App\Http\Controllers\Payment\Services\Car\bajaj_allianzPaymentGateway;
use App\Http\Controllers\Payment\Services\Car\V1\BajajAllianzPaymentGateway as BajajAllianzPaymentGatewayV1;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class BajajAllianzPaymentStatusCheck implements ShouldQueue
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
                ->where('prr.is_processed','=','N')
                ->where('prr.ic_id','=',3)
                ->select('prr.*','cjs.stage')
                ->orderBy('prr.id', 'DESC')
                ->offset($offset)
                ->limit($limit)
                ->get();
        foreach ($payment_request_response as $key => $data) 
        {
            if($data->order_id != NULL)
            {
                $payment_status_data = [
                    'enquiryId'                     => $data->user_product_journey_id,
                    'payment_request_response_id'   => $data->id,
                    'order_id'                      => $data->order_id
                ];
                if(config('IC.BAJAJ_ALLIANZ.V1.CAR.ENABLE') == 'Y'){
                    $payment_response = BajajAllianzPaymentGatewayV1::payment_status((object) $payment_status_data);
                } else {
                    $payment_response = bajaj_allianzPaymentGateway::payment_status((object) $payment_status_data);
                }
                $payment_response = json_decode($payment_response,true);
//                print_r($payment_response);
//                die;
                if(isset($payment_response['pTransStatus']) && $payment_response['pTransStatus'] == 'Y')
                {
                    $payment_status = STAGE_NAMES['PAYMENT_SUCCESS'];
                    $updatePaymentResponse = [
                        'status'  => $payment_status
                    ];

                    PaymentRequestResponse::where('id', $data->id)
                                 ->update($updatePaymentResponse);
                    $policy_number = $payment_response['pPolicyRef'] ?? NULL;
                    if($policy_number != NULL)
                    {
                        $PolicyDetails = PolicyDetails::where('proposal_id','=',$data->user_proposal_id)->get()->first();
                        if(empty($PolicyDetails))
                        {
                            $policy_data = [
                                    'proposal_id'   => $data->user_proposal_id,
                                    'policy_number' => $policy_number,
                                    'status'        => 'SUCCESS'
                                ];
                            PolicyDetails::insert($policy_data);
                        }
                        else
                        {
                            if($PolicyDetails->policy_number == '')
                            {
                                $policy_data = [
                                    'policy_number' => $policy_number
                                ];
                                PolicyDetails::where('proposal_id','=',$data->user_proposal_id)->update($policy_data);
                            }
                        }
                        
                        UserProposal::where('user_proposal_id' , $data->user_proposal_id)
                        ->update([
                            'policy_no' => $policy_number,
                        ]);
                    } 
                    
                    $enquiryId = [
                        'enquiryId' => customEncrypt($data->user_product_journey_id)
                    ];
                    $pdf_response = httpRequestNormal(url('api/generatePdf'), 'POST', $enquiryId)['response'];
                    $JourneyStage_data = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
                    
                    $status_data = [
                        'payment_request_response_id'   => $data->id,
                        'user_product_journey_id'       => $data->user_product_journey_id,
                        'proposal_id'                   => $data->user_proposal_id,
                        'ic_id'                         => 3,
                        'order_id'                      => $data->order_id,
                        'existing_payment_status'       => $data->status,
                        'updated_payment_status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
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
                        'ic_id'                         => 3,
                        'order_id'                      => $data->order_id,
                        'existing_payment_status'       => $data->status,
                        //'updated_payment_status'    => STAGE_NAMES['PAYMENT_FAILED'],
                        'existing_journey_stage'        => $data->stage,
                        //'updated_journey_stage'     => $JourneyStage_data->stage,
                        //'required_payment_data'     => '',
                        //'pdf_status'                => json_encode($pdf_response),
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
                    'ic_id'                         => 3,
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
