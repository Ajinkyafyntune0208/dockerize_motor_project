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
use Illuminate\Support\Facades\Http;
use App\Models\UserProposal;
use App\Models\JourneyStage;
use App\Models\PaymentRequestResponse;
use Illuminate\Http\Request;
use Razorpay\Api\Api;

class SbiPaymentStatusCheck implements ShouldQueue
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
        //Creating object of RazorPay
        $api = new Api(config('constants.IcConstants.sbi.SBI_ROZER_PAY_KEY_ID'), config('constants.IcConstants.sbi.SBI_ROZER_PAY_SECREAT_KEY'));
        
        $offset = $request->start_index; // start row index.
        $limit = $request->limit; // no of records to fetch/ get .
        $payment_request_response = DB::table('payment_request_response as prr')
                ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'prr.user_product_journey_id')
                ->where('prr.is_processed','=','N')
                //->whereNotIn('cjs.user_product_journey_id', PaymentCheckStatus::pluck('user_product_journey_id'))
                ->where('prr.ic_id','=',34)
                ->select('prr.*','cjs.stage')
                ->orderBy('prr.id', 'DESC')
                //->orderBy('prr.id', 'ASC')
                ->offset($offset)
                ->limit($limit)
                ->get();
//        print_r($payment_request_response);
//        die;
        foreach ($payment_request_response as $key => $data) 
        {
            if($data->order_id != NULL)
            {
                //Payment Check status API
                $response = $api->order->fetch($data->order_id)->payments()->toArray();
                if (isset($response['items'][0]['status']) && $response['items'][0]['status'] == 'captured' && $response['items'][0]['captured'] == true)
                {                    
                    $updatePaymentResponse = [
                        'status'  => STAGE_NAMES['PAYMENT_SUCCESS']
                    ];

                    PaymentRequestResponse::where('id', $data->id)
                                        ->update($updatePaymentResponse);
       
                    $enquiryId = [
                        'enquiryId' => customEncrypt($data->user_product_journey_id)
                    ];
                    //$pdf_response = httpRequestNormal('https://apicarbike.gramcover.com/api/generatePdf', 'POST', $enquiryId)['response'];
                    $pdf_response = httpRequestNormal(url('api/generatePdf'), 'POST', $enquiryId)['response'];//uncomment on server
                    
                    $JourneyStage_data = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
                    $status_data = [
                        'payment_request_response_id'   => $data->id,
                        'user_product_journey_id'   => $data->user_product_journey_id,
                        'proposal_id'               => $data->user_proposal_id,
                        'ic_id'                     => 34,
                        'order_id'                  => $data->order_id,
                        'existing_payment_status'   => $data->status,
                        'updated_payment_status'    => STAGE_NAMES['PAYMENT_SUCCESS'],
                        'existing_journey_stage'    => $data->stage,
                        'updated_journey_stage'     => $JourneyStage_data->stage,
                        'required_payment_data'     => '',
                        'pdf_status'                => json_encode($pdf_response),
                        'response'                  => json_encode($response),
                        'created_at'                => date('Y-m-d H:i:s')
                    ];
                    DB::table('payment_check_status')->insert($status_data);
                    //break;
                }
                else
                {
                    $payment_status = NULL;
                    if(isset($response['items'][0]['status']) && $response['items'][0]['status'] == 'failed')
                    {
                       $payment_status = STAGE_NAMES['PAYMENT_FAILED']; 
                       
                       $updatePaymentResponse = [
                            'status'  => $payment_status
                        ];

                        PaymentRequestResponse::where('id', $data->id)
                                        ->update($updatePaymentResponse);
                    }
                    $status_data = [
                        'payment_request_response_id'   => $data->id,
                        'user_product_journey_id'   => $data->user_product_journey_id,
                        'proposal_id'               => $data->user_proposal_id,
                        'ic_id'                     => 34,
                        'order_id'                  => $data->order_id,
                        'existing_payment_status'   => $data->status,
                        'updated_payment_status'    => $payment_status,
                        'existing_journey_stage'    => $data->stage,
                        //'updated_journey_stage'     => $JourneyStage_data->stage,
                        'response'                  => json_encode($response),
                        'created_at'                => date('Y-m-d H:i:s')
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
                    'ic_id'                         => 34,
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
