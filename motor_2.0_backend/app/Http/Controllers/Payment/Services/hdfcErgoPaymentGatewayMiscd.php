<?php

namespace App\Http\Controllers\Payment\Services;

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
class hdfcErgoPaymentGatewayMiscd
{

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function make($request)
    {
        $enquiryId = customDecrypt($request->userProductJourneyId);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $product_data = getProductDataByIc($request['policyId']);
        $icId = MasterPolicy::where('policy_id', $request['policyId'])->pluck('insurance_company_id')->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('quote_id')->first();

        if (in_array($product_data->premium_type_id,[1, 4])) { // Comprehensive or Comprehensive-Breakin

            $return_data = [
                'form_action' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TRACTOR_PAYMENT_URL'),
                'form_method' =>config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_PAYMENT_URL_METHOD'),
                'payment_type' => 0,
                'form_data' => [
                    'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_MISCD_AGENT_CODE'),
                    'CustomerId' => $user_proposal->proposal_no,
                    'TxnAmount' => $user_proposal->final_payable_amount,
                    'AdditionalInfo1' => $user_proposal->business_type == 'rollover' ? 'RO' : 'NB',
                    'AdditionalInfo2' => 'TC',
                    'AdditionalInfo3' => '1',
                    'ProductCd' => 'TC',
                    'hdnPayMode' => 'CC',
                    'hndEMIMode' => 'FULL',
                    'UserName' => $user_proposal->first_name,
                    'UserMailId' => $user_proposal->email,
                    'ProducerCd' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MISCD_PRODUCER_CODE') . "-" . $user_proposal->proposal_no,
                ],
            ];

            PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                ->update(['active' => 0]);

            PaymentRequestResponse::insert([
                'quote_id' => $quote_log_id,
                'user_product_journey_id' => $enquiryId,
                'user_proposal_id' => $user_proposal->user_proposal_id,
                'ic_id' => $icId,
                'order_id' => $user_proposal->proposal_no,
                'proposal_no' => $user_proposal->proposal_no,
                'amount' => $user_proposal->final_payable_amount,
                'payment_url' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TRACTOR_PAYMENT_URL'),
                'return_url' => route('cv.payment-confirm', ['hdfc_ergo']),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'active' => 1,
                'xml_data' => json_encode($return_data)
            ]);

            $journeyStageData = [
                'user_product_journey_id' => customDecrypt($request->userProductJourneyId),
                'stage' => STAGE_NAMES['PAYMENT_INITIATED'],
            ];
            updateJourneyStage($journeyStageData);

            return response()->json([
                'status' => true,
                'msg' => "Payment Reidrectional",
                'data' => $return_data,
            ]);
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function confirm($request)
    {
        if(empty($request->ProposalNo)) {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $payment_record = PaymentRequestResponse::where('order_id', $request->ProposalNo)->first();
        if($payment_record) {
            PaymentRequestResponse::where('order_id', $request->ProposalNo)->update([
                'response' => $request->All(),
                'status' => strtolower($request->Msg) == 'successfull' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                'updated_at' => now()
            ]);
        } else {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        if ($request->Msg == 'Successfull') {
            $user_proposal = UserProposal::where('user_product_journey_id', $payment_record->user_product_journey_id)
                ->orderBy('user_proposal_id', 'desc')
                ->select('*')
                ->first();
            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);
            sleep(3);
            if(!empty($request->PolicyNo)) {
                PolicyDetails::updateOrCreate(
                    [
                        "proposal_id" => $user_proposal->user_proposal_id,
                    ],
                    [
                        'policy_number' => $request->PolicyNo,
                        'policy_start_date' => $user_proposal->policy_start_date,
                        'ncb' => $user_proposal->ncb_discount,
                        'policy_start_date' => $user_proposal->policy_start_date,
                        'premium' => $request->Amt,
                        'status' => 'SUCCESS'
                    ]
                );
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                ]);
            } else {
                return redirect(paymentSuccessFailureCallbackUrl($payment_record->user_product_journey_id, 'CV', 'SUCCESS'));
            }

            $input_array = [
                'PolicyNo' => $request->PolicyNo,
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_MISCD_AGENT_CODE'),
            ];

            $get_response = getWsData(
                config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POLICY_PDF_URL'),
                $input_array, 'hdfc_ergo',
                [
                    'root_tag' => 'PDF',
                    'section' => 'MISC',
                    'method' => 'PDF Generation',
                    'requestMethod' => 'post',
                    'enquiryId' => $user_proposal->user_product_journey_id,
                    'transaction_type' => 'proposal',
                    'type' => 'policyPdfGeneration',
                    'product' => 'Taxi Upto 6 Seater',
                    'headers' => [
                        'Content-type' => 'application/x-www-form-urlencoded'
                    ]
                ]
            );
            $pdf_url = $get_response['response'];
            if(empty($pdf_url)) {
                return redirect(paymentSuccessFailureCallbackUrl($payment_record->user_product_journey_id, 'CV', 'SUCCESS'));
            }
            // If response doesn't include 'base64Binary' keyword in it then something is wrong
            if(!\Illuminate\Support\Str::contains($pdf_url, '<base64Binary')) {
                return redirect(paymentSuccessFailureCallbackUrl($payment_record->user_product_journey_id, 'CV', 'SUCCESS'));
            }
            $replace_all = ['<?xml version="1.0" encoding="utf-8"?>', '<base64Binary xmlns="http://tempuri.org/">', '</base64Binary>'];
            $pdf_url = str_replace($replace_all, '', $pdf_url);
            $pdf_url = base64_decode($pdf_url);

            $pdf_data = Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', $pdf_url);

            if ($pdf_data) {
                PolicyDetails::updateOrCreate(
                    [
                        'proposal_id' => $user_proposal->user_proposal_id
                    ],
                    [
                        'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                    ]
                );
                $journeyStageData = [
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED'],
                ];
                updateJourneyStage($journeyStageData);
            }
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
        } else {
            return redirect(paymentSuccessFailureCallbackUrl($payment_record->user_product_journey_id, 'CV', 'FAILURE'));
        }
    }

    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $record_exists = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id
        ])->get();
        if (empty($record_exists)) 
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Payment details not found.'
            ]);
        }

        if((env('APP_ENV') == 'local'))
            {
                // NOT IMPLEMENTED FOR PROD STILL TESTING IS PENDING ON UAT 
                $get_payment_status = self::xml_payment_check($user_product_journey_id);
                if(!$get_payment_status)
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Payment is still Pending.'
                    ]);
                }
            }
            $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'prr.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->where('prr.user_product_journey_id', $user_product_journey_id)
            ->select('up.user_proposal_id', 'up.policy_start_date', 'up.proposal_no', 'up.unique_proposal_id', 'up.additional_details_data', 'up.final_payable_amount', 'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url', 'prr.response AS payment_response')
            ->first();
            if(empty($policy_details))
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Payment details not found.'
                ]);
            }
            if(!empty(trim($policy_details->policy_number)))
            {
                $input_array = [
                    'PolicyNo' => $policy_details->policy_number,
                    'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_MISCD_AGENT_CODE'),
                ];

                $get_response = getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POLICY_PDF_URL'),
                    $input_array, 'hdfc_ergo',
                    [
                        'root_tag' => 'PDF',
                        'section' => 'MISC',
                        'method' => 'PDF Generation',
                        'requestMethod' => 'post',
                        'enquiryId' => $user_product_journey_id,
                        'transaction_type' => 'proposal',
                        'type' => 'policyPdfGeneration',
                        'product' => 'Taxi Upto 6 Seater',
                        'headers' => [
                            'Content-type' => 'application/x-www-form-urlencoded'
                        ]
                    ]
                );
                $pdf_url = $get_response['response'];
                if(empty($pdf_url)) {
                    return response()->json([
                        'status' => false,
                        'msg'    => 'No response received from HDFC\'s PDF service API',
                    ]);
                }
                // If response doesn't include 'base64Binary' keyword in it then something is wrong
                if(!\Illuminate\Support\Str::contains($pdf_url, '<base64Binary')) {
                    return response()->json([
                        'status' => false,
                        'msg'    => 'something is wrong , in pdf service',
                    ]);
                }
                $replace_all = ['<?xml version="1.0" encoding="utf-8"?>', '<base64Binary xmlns="http://tempuri.org/">', '</base64Binary>'];
                $pdf_url = str_replace($replace_all, '', $pdf_url);
                $pdf_url = base64_decode($pdf_url);

                $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($policy_details->user_proposal_id) . '.pdf';
                $pdf_data = Storage::put($pdf_name , $pdf_url);

                if ($pdf_data) {
                    PolicyDetails::updateOrCreate(
                        [
                            'proposal_id' => $policy_details->user_proposal_id
                        ],
                        [
                            'pdf_url' => $pdf_name,
                        ]
                    );
                    $journeyStageData = [
                        'user_product_journey_id' => $user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED'],
                    ];
                    updateJourneyStage($journeyStageData);
                    return response()->json([
                        'status' => true,
                        'msg' => 'PDF generated successfully',
                        'data' => [
                            'policy_number' => $policy_details->policy_number,
                            'pdf_link' => !empty($pdf_name) ? file_url($pdf_name) : null,
                        ]
                    ]);
                }
                
            }
            else{
                return response()->json([
                    'status' => false,
                    'msg'    => 'Policy Number is empty',
                ]);
            }
        
    }

    public static function xml_payment_check($user_product_journey_id)
    {
        $payment_records = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->get();
        
        foreach($payment_records as $k => $row) {
            if(empty($row->order_id)) {
                continue;
            }
            //'http://202.191.196.210/UAT/OnlineProducts/PaymentStatusService/PaymentStatus.asmx/GetpaymentStatus?PgTransNo='
            
            $url = config('constants.motorConstant.CV_PAYMENT_CHECK_XML_URL'). $row->order_id;

            $get_response = getWsData($url, '', 'hdfc_ergo', [
                'method'           => 'Payment Check Service',
                'section'          => 'CV',
                'enquiryId'        => $user_product_journey_id,
                'productName'      => 'CV Insurance',
                'transaction_type' => 'proposal',
                'company'          => 'hdfc_ergo',
                'requestMethod'    => 'get',
                'headers' => []
            ]);
            $payment_check_req = $get_response['response'];
            $responsedata = html_entity_decode($payment_check_req);
            $payment_check_resp = XmlToArray::convert($responsedata);
            if(isset($payment_check_resp['STATUS']['PAYMENT_STATUS']) && $payment_check_resp['STATUS']['PAYMENT_STATUS'] == 'SUCCESSFUL' && isset($payment_check_resp['STATUS']['ERROR_MSG']) && $payment_check_resp['STATUS']['ERROR_MSG'] == 'NA')
            {
                PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->update([
                    'active'  => 0
                ]);
                // Then Mark single Transaction as Payment Success
                PaymentRequestResponse::where([
                    'user_product_journey_id' => $user_product_journey_id,
                    'id' => $row->id
                ])->update([
                    'active'  => 1,
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_SUCCESS'],
                    'ic_id' => 11
                ]);
                return true;
            }
        }
        return false;
    }
}
