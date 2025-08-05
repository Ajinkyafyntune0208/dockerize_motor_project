<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use DB;
use Config;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\Storage;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;

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
                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $enquiryId)
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
                    'return_url' => route('bike.payment-confirm', ['raheja', 'user_proposal_id' => $proposal->user_proposal_id]),
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
echo "<pre>";
        print_r($request_data);

        if(!empty($request_data)) {
            $response='success';
            $proposal = UserProposal::where('user_proposal_id', $request_data['user_proposal_id'])->first();
            if ($proposal) {
                $quote_data = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->first();

                if ($quote_data) {
                    $product = getProductDataByIc($quote_data->master_policy_id);
                }
                if ($response == 'success')
                {
                    //Create Receipt Serviec Start
                    $create_receipt_data=[
                        'TxnNo' => $proposal['unique_proposal_id'],
                        "objPolicy" => [
                            'TPSourceName' => config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                            'QuoteNo' => $proposal['quote_no'],
                            'PaymentStatus' => "Success",
                            'TraceID' => $proposal['trace_id'],
                            'UserName' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                        ],
                    ];
                    print_r($create_receipt_data);
                    //Create Receipt Service End
                    $get_response = getWsData(
                        config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_ISSUE_POLICY'), $create_receipt_data, 'raheja', [

                            "webUserId" => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                            "password"=> config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                            'request_data' => [
                                'proposal_id' => $proposal['user_proposal_id'],
                                'method' => 'create receipt',
                                'section' => 'bike',
                            ],
                        ]
                    );
                    $create_receipt_resp = $get_response['response'];
                    print_r($create_receipt_resp);
                    die;
                    if($create_receipt_resp){
                        $create_receipt_resp=json_decode($create_receipt_resp, true);

                        if($create_receipt_resp['objFault']['ErrorMessage']==null){
                            DB::table('payment_request_response')
                                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active',1)
                                ->update([
                                'response' => $request->All(),
                                'status'   => STAGE_NAMES['PAYMENT_SUCCESS'],
                            ]);
                            updateJourneyStage([
                                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
                            ]);

                            if($create_receipt_resp['PolicyPDFDownloadLink'] === ''){
                                $u_link = '';
                            } else {
                                $u_link = $create_receipt_resp['PolicyPDFDownloadLink'];
                            }

                            if($data != '') {
                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $proposal->$proposal->$proposal_id],
                                    [
                                        'policy_number' => $create_receipt_resp['PolicyNo'],
                                        'ic_pdf_url' => $u_link,
                                        'policy_start_date' => $proposal->policy_start_date,
                                        'ncb' => $proposal->ncb_discount,
                                        'policy_start_date' => $proposal->policy_start_date,
                                        'premium' => $proposal->total_premium,
                                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'raheja/'. md5($user_proposal->user_proposal_id). '.pdf',
                                        'transcript_pdf_url'=> $create_receipt_resp['TranscriptPDFDownloadLink'],
                                        'status' => 'SUCCESS'
                                    ]
                                );
                            }
                            try {
                                Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'raheja/'.md5($user_proposal->user_proposal_id).'.pdf', $u_link);

                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                                ]);
                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id],
                                    [
                                        'policy_number' => $ $policy,
                                        'ic_pdf_url' => '',
                                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'raheja/'. md5($user_proposal->user_proposal_id). '.pdf',
                                        'status' => 'SUCCESS'
                                    ]
                                );
                            } catch(\Exception $e) {
                                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
                            }
                        } else{
                            DB::table('payment_request_response')
                                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active',1)
                                ->update([
                                'response' => $request->All(),
                                'status'   => $create_receipt_resp['objFault']['ErrorMessage'],
                            ]);
                            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
                        }
                    } else{
                        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
                    }
                }
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
            }
        } else {
            return redirect(Config::get('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL'));
        }

    }


}
