<?php

namespace App\Http\Controllers\Payment\Services\Car;
include_once app_path().'/Helpers/CarWebServiceHelper.php';

use Config;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;

class rahejaPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        if ($proposal) {
            $enquiryId = customDecrypt($request['userProductJourneyId']);

            $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

            $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('quote_id')
                ->first();

            $productData = getProductDataByIc($request['policyId']);

            $payment_url =  $proposal->payment_url;
            if(!empty($payment_url)){
                $return_data = [
                    'form_action' => $payment_url,
                    'form_method' => 'POST',
                    'payment_type' => 0, // form-submit
                ];
                PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                                        ->update(['active' => 0]);
                DB::table('payment_request_response')->insert([
                    'quote_id' => $quote_log_id,
                    'user_product_journey_id' => $enquiryId,
                    'user_proposal_id' => $proposal->user_proposal_id,
                    'ic_id' => $icId,
                    'order_id' => $proposal->proposal_no,
                    'amount' => $proposal->final_payable_amount,
                    'proposal_no' => $proposal->proposal_no,
                    'payment_url' => $payment_url,
                    'return_url' => route('car.payment-confirm', ['raheja', 'user_proposal_id' => $proposal->user_proposal_id, 'status' => 'success']),
                    'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                    'active' => 1
                ]);
    
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'ic_id' => $productData->company_id,
                    'stage' => STAGE_NAMES['PAYMENT_INITIATED']
                ]);
    
                return response()->json([
                    'status' => true,
                    'msg' => "Payment Redirectional",
                    'data' => $return_data,
                ]);
            } else {
                return [
                    'status' => false,
                    'msg' => 'Payment Url Not generated. Please Try again'
                ];
            }
        } else {
            return [
                'status' => false,
                'msg' => 'Proposal data not found'
            ];
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        $request_data = $request->all();
        if((isset($request_data['txnid']) && $request_data['txnid'] == '') && (isset($request_data['status']) && $request_data['status'] != 'success'))
        {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        if(!empty($request_data)) {
            $proposal = UserProposal::where('unique_proposal_id', $request_data['txnid'])->first();
            if ($proposal) { 
                $quote_data = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->first();
                $productData = getProductDataByIc($quote_data->master_policy_id);
                if (isset($request_data['status']) && $request_data['status'] == 'success') 
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);
                    //Create Receipt Serviec Start 
                    $create_receipt_data=[
                        'TxnNo' => $proposal['unique_proposal_id'],
                        "objPolicy" => [
                            'TPSourceName' => config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_MOTOR'),
                            'QuoteNo' => $proposal['unique_quote'],
                            'PaymentStatus' => "Success",
                            'TraceID' => $proposal['additional_details_data'],
                            'UserName' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                        ],
                    ];
                    //Create Receipt Service End
                    sleep(10);
                    $get_response = getWsData(
                        config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_MOTOR_ISSUE_POLICY'), $create_receipt_data, 'raheja', [

                            "webUserId" => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                            "password"=> config('constants.IcConstants.raheja.PASSWORD_RAHEJA_MOTOR'),
                            'request_data' => [
                                'proposal_id' => $proposal['user_proposal_id'],
                                'method' => 'create receipt',
                            ],
                            'method' => 'create receipt',
                            'section' => 'car',
                            'requestMethod' =>'post',
                            'request_method' => 'post',
                            'company' => $productData->company_name,
                            'productName' => $productData->product_sub_type_name,
                            'enquiryId' => $proposal->user_product_journey_id,
                            'transaction_type' => 'proposal',
                        ]
                    );
                    $create_receipt_resp = $get_response['response'];
                    if($create_receipt_resp){

                        $create_receipt_resp = json_decode($create_receipt_resp, true);

                        if(isset($create_receipt_resp['PolicyNo']) && $create_receipt_resp['PolicyNo'] != '' )
                        {
                                DB::table('payment_request_response')
                                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                    ->where('active',1)
                                    ->update([
                                    'response' => $request->All(),
                                    'status'   => STAGE_NAMES['PAYMENT_SUCCESS'],
                                ]);
                                UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                            ->update([
                                                'policy_no' => $create_receipt_resp['PolicyNo'],
                                            ]);

                            //check policy no received or not
                            if(($create_receipt_resp['PolicyNo'] != '')) 
                            {

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $proposal->user_proposal_id],
                                    [
                                        'policy_number' => $create_receipt_resp['PolicyNo'],
                                        'policy_start_date' => $proposal->policy_start_date,
                                        'ncb' => $proposal->ncb_discount,
                                        'premium' => $proposal->total_premium,
                                        'status' => 'SUCCESS'
                                    ]
                                );

                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);
                            }
                            //pdf data
                            $pdf_url = $create_receipt_resp['PolicyPDFDownloadLink'] ?? '';
                            sleep(5);
                            if($pdf_url != '')
                            {
                                Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'raheja/'.md5($proposal->user_proposal_id).'.pdf', httpRequestNormal($pdf_url,'GET',[],[],[],[],false)['response']);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $proposal->user_proposal_id],
                                    [
                                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'raheja/'. md5($proposal->user_proposal_id). '.pdf',
                                        'ic_pdf_url' => $pdf_url,
                                        'status' => 'SUCCESS'
                                    ]
                                );
    
                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                                ]);
                            }
                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));   
                        } else{
                            DB::table('payment_request_response')
                                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active',1)
                                ->update([
                                'response' => $request->All(),
                                'status'   => STAGE_NAMES['PAYMENT_FAILED'],
                            ]);
                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
                        }
                    } else{
                        return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
                    }
                }
                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
            }
        } else {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
       
    }

    static public function retry_pdf($proposal)
    {
        
    }
}
