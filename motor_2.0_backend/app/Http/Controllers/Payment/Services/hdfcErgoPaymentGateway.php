<?php

namespace App\Http\Controllers\Payment\Services;

use App\Http\Controllers\Finsall\FinsallController;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Payment\Services\hdfcErgoPaymentGatewayMiscd as HDFC_ERGO_MISCD;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
class hdfcErgoPaymentGateway
{

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function make($request)
    {
        if(policyProductType($request->policyId)->parent_id == 3)
        {
          return HDFC_ERGO_MISCD::make($request);
        }
        $enquiryId = customDecrypt($request->userProductJourneyId);

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $product_data = getProductDataByIc($request['policyId']);

        $icId = MasterPolicy::where('policy_id', $request['policyId'])->pluck('insurance_company_id')->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('quote_id')->first();

        if (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_REQUEST_TYPE') == 'JSON')  {
            $transaction_no = (int) (config('constants.IcConstants.hdfc_ergo.TRANSACTION_NO_SERIES_HDFC_ERGO_CV_JSON_MOTOR') . date('ymd').rand(10000, 99999));

            $str = 'TransactionNo='.$transaction_no;//'TransactionNo=' . $user_proposal->user_product_journey_id;
            $str .= '&TotalAmount=' . $user_proposal->final_payable_amount;
            $str .= '&AppID=' . config('constants.IcConstants.hdfc_ergo.APPID_PAYMENT_HDFC_ERGO_GIC_CV');
            $str .= '&SubscriptionID=' . config('constants.IcConstants.hdfc_ergo.SubscriptionID_HDFC_ERGO_GIC_CV');
            $str .= '&SuccessUrl=' . route('cv.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
            $str .= '&FailureUrl=' . route('cv.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
            $str .= '&Source=POST'; // config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE');
            $checksum_url = config('constants.IcConstants.hdfc_ergo.PAYMENT_CHECKSUM_LINK_HDFC_ERGO_GIC_MOTOR').'?'.$str;

            $checksum = file_get_contents($checksum_url);

            $checksum_data = [
                'checksum_url' => $checksum_url,
                'checksum' => $checksum
            ];

            $check_sum_response = preg_replace('#</\w{1,10}:#', '</', preg_replace('#<\w{1,10}:#', '<', preg_replace('/ xsi[^=]*="[^"]*"/i', '$1', preg_replace('/ xmlns[^=]*="[^"]*"/i', '$1', preg_replace('/ xml:space[^=]*="[^"]*"/i', '$1', $checksum)))));
            $xmlString = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $check_sum_response);
            $checksum = strip_tags(trim($xmlString));

            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->update(['active' => 0]);

            $payment_url =config('constants.IcConstants.hdfc_ergo.PAYMENT_GATEWAY_LINK_HDFC_ERGO_GIC_MOTOR');

            PaymentRequestResponse::insert([
                'quote_id' => $quote_log_id,
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'user_proposal_id' => $user_proposal->user_proposal_id,
                'ic_id' => $icId,
                'order_id' => $user_proposal->proposal_no,
                'amount' => $user_proposal->final_payable_amount,
                'payment_url' => $payment_url,
                'return_url' => route('cv.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'xml_data' => json_encode($checksum_data),
                'customer_id' => $transaction_no
            ]);

            $return_data = [
                'form_action' => $payment_url,
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'Trnsno' => $transaction_no,//$user_proposal->user_product_journey_id,
                    'Amt' => (int) $user_proposal->final_payable_amount,
                    'Appid' => config('constants.IcConstants.hdfc_ergo.APPID_PAYMENT_HDFC_ERGO_GIC_CV'),
                    'Subid' => config('constants.IcConstants.hdfc_ergo.SubscriptionID_HDFC_ERGO_GIC_CV'),
                    'Chksum' => $checksum,
                    'Src' => 'POST',
                    'Surl' => route('cv.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                    'Furl' => route('cv.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                ],
            ];

            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'ic_id' => $user_proposal->ic_id,
                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            ]);

            return response()->json([
                'status' => true,
                'msg' => "Payment Reidrectional",
                'data' => $return_data,
            ]);
        } else {
            if (in_array($product_data->premium_type_id,[1, 4])) { // Comprehensive or Comprehensive-Breakin

                $return_data = [
                    'form_action' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_PAYMENT_URL'),
                    'form_method' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_PAYMENT_URL_METHOD'),
                    'payment_type' => 0,
                    'form_data' => [
                        'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE'),
                        'CustomerId' => $user_proposal->proposal_no,
                        'TxnAmount' => $user_proposal->final_payable_amount,
                        'hdnPayMode' => 'CC',
                        'hndEMIMode' => 'FULL',
                        'UserName' => $user_proposal->first_name,
                        'UserMailId' => $user_proposal->email,
                        'ProducerCd' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE') . "-" . $user_proposal->proposal_no,
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
                    'payment_url' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_PAYMENT_URL'),
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
            } else if (in_array($product_data->premium_type_id, [2, 7])) { // TP or BreakIn TP
                $return_data = [
                    'form_action' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_TP_PAYMENT_URL'),
                    'form_method' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_PAYMENT_URL_METHOD'),
                    'payment_type' => 0,
                    'form_data' => [
                        'CustomerID' => $user_proposal->proposal_no,
                        'TxnAmount' => $user_proposal->final_payable_amount,
                        'AdditionalInfo1' => $user_proposal->business_type == 'rollover' ? 'RO' : 'NB',
                        'AdditionalInfo2' => 'MOTLP',
                        'AdditionalInfo3' => '1',
                        'hdnPayMode' => 'CC',
                        'UserName' => $user_proposal->first_name,
                        'UserMailId' => $user_proposal->email,
                        'ProductCd' => 'MOTLP',
                        'ProducerCd' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_AGENT_CODE') . "-" . $user_proposal->proposal_no,
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
                    'payment_url' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_TP_PAYMENT_URL'),
                    'return_url' => route('cv.payment-confirm', ['hdfc_ergo']),
                    'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                    'active' => 1,
                    'xml_data' => json_encode($return_data, JSON_UNESCAPED_SLASHES)
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
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function confirm($request)
    {
        if (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_REQUEST_TYPE') == 'JSON') {
            $user_proposal = UserProposal::find($request->user_proposal_id);
            $enquiryId = $user_proposal->user_product_journey_id;
            $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->first();
            $productData = getProductDataByIc($master_policy_id->master_policy_id);
            $premium_type = DB::table('master_premium_type')
                ->where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

            $parent_id = get_parent_code($productData->product_sub_type_id);

            if ($parent_id == 'PCV')
            {
                $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2313 : 2314;
            }
            else
            {
                $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2317 : 2315;
            }

            DB::table('payment_request_response')
                ->where('user_proposal_id', $request->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'response'   => $request->all(),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            if (isset($request->hdnmsg) && $request->hdnmsg != null) {
                $response = explode('|', $request->hdnmsg);
                $merchant_id = $response[0];
                $transaction_number = $response[1];
                $transaction_ref_number = $response[1];#$response[2]
                $bank_reference_no = $response[3];
                $txn_amount = $response[4];
                $bank_code = $response[5];
                $is_si_opted = $response[6];
                $payment_mode = $response[7];
                $pg_remarks = $response[8];
                $payment_status = $response[9];
                $transaction_date = $response[10];
                $app_id = $response[11];
                $checksum = $response[12];

                if ($payment_status == 'SPD')    //Payment Successful
                {
                    DB::table('payment_request_response')
                        ->where('user_proposal_id', $request->user_proposal_id)
                        ->where('active', 1)
                        ->update([
                            'status'     => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    $proposal_array = json_decode($user_proposal->additional_details_data, true);

                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_AUTHENTICATE_URL'), [], 'hdfc_ergo', [
                        'method'           => 'Token Generation',
                        'section'          => $productData->product_sub_type_code,
                        'enquiryId'        => $enquiryId,
                        'productName'      => $productData->product_name,
                        'transaction_type' => 'proposal',
                        'product_code'     => $product_code,
                        'transaction_id'   => $proposal_array['TransactionID'],
                        'headers' => [
                            'Content-type' => 'application/json',
                            'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                            'PRODUCT_CODE' => $product_code,
                            'TransactionID' => $proposal_array['TransactionID'],
                            'Accept' => 'application/json',
                            'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CREDENTIAL')
                        ]
                    ]);
                    $token = $get_response['response'];

                    if ($token) {
                        $token_data = json_decode($token, TRUE);

                        if (isset($token_data['StatusCode']) && $token_data['StatusCode'] == 200) {
                            $proposal_array['Payment_Details'] = [
                                'GC_PaymentID'      => null,
                                'BANK_NAME'         => config('constants.IcConstants.hdfc_ergo.BANK_NAME'),
                                'BANK_BRANCH_NAME'  => config('constants.IcConstants.hdfc_ergo.BANK_BRANCH_NAME'),
                                'PAYMENT_MODE_CD'   => config('constants.IcConstants.hdfc_ergo.PAYMENT_MODE_CD'),
                                'PAYER_TYPE'        => config('constants.IcConstants.hdfc_ergo.PAYER_TYPE'),
                                'PAYMENT_AMOUNT'    => $user_proposal->final_payable_amount,
                                'INSTRUMENT_NUMBER' => $transaction_ref_number,
                                'PAYMENT_DATE'      => date('d/m/Y'),
                            ];

                            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_PROPOSAL'), $proposal_array, 'hdfc_ergo', [
                                'method'           => 'Policy Generation',
                                'requestMethod'    => 'post',
                                'section'          => $productData->product_sub_type_code,
                                'enquiryId'        => $user_proposal->user_product_journey_id,
                                'token'            => $token_data['Authentication']['Token'],
                                'transaction_type' => 'proposal',
                                'productName'      => $productData->product_name,
                                'product_code'     => $product_code,
                                'transaction_id'   => $proposal_array['TransactionID'],
                                'headers' => [
                                    'Content-type' => 'application/json',
                                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                                    'PRODUCT_CODE' => $product_code,
                                    'TransactionID' => $proposal_array['TransactionID'],
                                    'Accept' => 'application/json',
                                    'Token' => $token_data['Authentication']['Token']
                                ]
                            ]);
                            $policy_data = $get_response['response'];

                            if ($policy_data) {
                                $policy_data = json_decode($policy_data, TRUE);

                                if (isset($policy_data['StatusCode']) && $policy_data['StatusCode'] == 200) {
                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $user_proposal->user_proposal_id],
                                        [
                                            'policy_number'     => $policy_data['Policy_Details']['PolicyNumber'],
                                            'policy_start_date' => $user_proposal->policy_start_date,
                                            'premium'           => $user_proposal->final_payable_amount,
                                            'created_on'        => date('Y-m-d H:i:s')
                                        ]
                                    );

                                    DB::table('payment_request_response')
                                        ->where('user_proposal_id', $request->user_proposal_id)
                                        ->where('active', 1)
                                        ->update([
                                            'order_id'   => $policy_data['TransactionID'],
                                            'updated_at' => date('Y-m-d H:i:s')
                                        ]);

                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage'                   => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);

                                    $policy_array = [
                                        "TransactionID"       => $policy_data['TransactionID'],
                                        "Req_Policy_Document" => [
                                            "Policy_Number" => $policy_data['Policy_Details']['PolicyNumber']
                                        ]
                                    ];

                                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', [
                                        'method'           => 'Pdf Generation',
                                        'requestMethod'    => 'post',
                                        'section'          => $productData->product_sub_type_code,
                                        'enquiryId'        => $user_proposal->user_product_journey_id,
                                        'token'            => $token_data['Authentication']['Token'],
                                        'transaction_type' => 'proposal',
                                        'productName'      => $productData->product_name,
                                        'product_code'     => $product_code,
                                        'transaction_id'   => $proposal_array['TransactionID'],
                                        'headers' => [
                                            'Content-type' => 'application/json',
                                            'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                                            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                                            'PRODUCT_CODE' => $product_code,
                                            'TransactionID' => $proposal_array['TransactionID'],
                                            'Accept' => 'application/json',
                                            'Token' => $token_data['Authentication']['Token']
                                        ]
                                    ]);
                                    $pdf_data = $get_response['response'];

                                    if ($pdf_data) {
                                        $pdf_response = json_decode($pdf_data, TRUE);

                                        if (isset($pdf_response['StatusCode']) && $pdf_response['StatusCode'] == 200) {
                                            if(!checkValidPDFData(base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES'])))
                                            {
                                                updateJourneyStage([
                                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                                ]);

                                                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                                            }
                                            Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));

                                            updateJourneyStage([
                                                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                                'stage'                   => STAGE_NAMES['POLICY_ISSUED']
                                            ]);

                                            PolicyDetails::updateOrCreate(
                                                ['proposal_id' => $user_proposal->user_proposal_id],
                                                [
                                                    'policy_number' => $policy_data['Policy_Details']['PolicyNumber'],
                                                    'pdf_url'       => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                                                    'status'        => 'SUCCESS'
                                                ]
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }

                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                }
                elseif ($payment_status == 'UPD')    //Payment Unsuccessful
                {
                    DB::table('payment_request_response')
                        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->where('active',1)
                        ->update([
                            'status'     => STAGE_NAMES['PAYMENT_FAILED'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage'                   => STAGE_NAMES['PAYMENT_FAILED']
                    ]);

                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
                }
                else    //Payment Incomplete
                {
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
                }
            } else {
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
            }
        } else {
            if(empty($request->ProposalNo)) {
                return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
            }
            $payment_record = PaymentRequestResponse::where('order_id', $request->ProposalNo)->first();
            $user_proposal = UserProposal::where('user_proposal_id', $payment_record->user_proposal_id)
                ->orderBy('user_proposal_id', 'desc')
                ->select('*')
                ->first();
            $result = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();
            $productData = getProductDataByIc($result->master_policy_id);
            if (policyProductType($productData->policy_id)->parent_id == 3) {
                return HDFC_ERGO_MISCD::confirm($request);
            }
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
                $type = $request->ProposalNo;

                if (strpos($type, 'CP') !== false) {
                    $agentcode = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE');
                } elseif (strpos($type, 'LP') !== false) {
                    $agentcode = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_AGENT_CODE');
                }
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
                    'AgentCode' => $agentcode,
                ];

                $get_response = getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POLICY_PDF_URL'),
                    $input_array, 'hdfc_ergo',
                    [
                        'root_tag' => 'PDF',
                        'section' => 'TAXI',
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
        $policy_details = DB::table('payment_request_response as prr')
        ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'prr.user_proposal_id')
        ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
        ->where('prr.user_product_journey_id', $user_product_journey_id)
        ->where('prr.active', 1)
        ->select('up.user_proposal_id', 'up.policy_start_date', 'up.proposal_no', 'up.unique_proposal_id', 'up.additional_details_data', 'up.final_payable_amount', 'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url', 'prr.response AS payment_response', 'prr.lead_source')
        ->first();

        if ($policy_details->lead_source == 'finsall') {
            return self::finsallRehitService($user_product_journey_id, $policy_details->proposal_no);
        }

        if (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_REQUEST_TYPE') == 'JSON') {

            $master_policy_id = QuoteLog::where('user_product_journey_id', $user_product_journey_id)
                ->first();
            $productData = getProductDataByIc($master_policy_id->master_policy_id);
            $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

            $parent_id = get_parent_code($productData->product_sub_type_id);

            if ($parent_id == 'PCV') {
                $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2313 : 2314;
            } else {
                $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2317 : 2315;
            }

            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_AUTHENTICATE_URL'), [], 'hdfc_ergo', [
                'method'           => 'Token Generation',
                'section'          => $productData->product_sub_type_code,
                'enquiryId'        => $user_product_journey_id,
                'productName'      => $productData->product_name,
                'transaction_type' => 'proposal',
                'product_code'     => $product_code,
                'transaction_id'   => $policy_details->proposal_no,
                'headers' => [
                    'Content-type' => 'application/json',
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                    'PRODUCT_CODE' => $product_code,
                    'TransactionID' => $policy_details->proposal_no,
                    'Accept' => 'application/json',
                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CREDENTIAL')
                ]
            ]);
            $token = $get_response['response'];

            if ($token) {
                $token_data = json_decode($token, TRUE);

                if (isset($token_data['StatusCode']) && $token_data['StatusCode'] == 200) {
                    if (!isset($policy_details->policy_number) || $policy_details->policy_number == '' || $policy_details->policy_number == NULL) {
                        $proposal_array = json_decode($policy_details->additional_details_data, true);

                        if ($policy_details->payment_response != NULL || $policy_details->payment_response != '') {
                            $payment_response = json_decode($policy_details->payment_response, TRUE);

                            if ($payment_response['hdnmsg'] != null) {
                                $response = explode('|', $payment_response['hdnmsg']);
                                $merchant_id = $response[0];
                                $transaction_number = $response[1];
                                $transaction_ref_number = $response[1]; #$response[2]
                                $bank_reference_no = $response[3];
                                $txn_amount = $response[4];
                                $bank_code = $response[5];
                                $is_si_opted = $response[6];
                                $payment_mode = $response[7];
                                $pg_remarks = $response[8];
                                $payment_status = $response[9];
                                $transaction_date = $response[10];
                                $app_id = $response[11];
                                $checksum = $response[12];

                                if ($payment_status != 'SPD')    //Payment Successful
                                {
                                    return response()->json([
                                        'status' => false,
                                        'msg'    => 'Payment is not completed or failed',
                                    ]);
                                }
                            } else {
                                return response()->json([
                                    'status' => false,
                                    'msg'    => 'Payment response is not correct',
                                ]);
                            }
                        } else {
                            return response()->json([
                                'status' => false,
                                'msg'    => 'Payment response not available',
                            ]);
                        }

                        return self::policyGeneration(
                            $transaction_ref_number,
                            $productData,
                            $user_product_journey_id,
                            $product_code
                        );
                    }

                    $policy_array = [
                        "TransactionID"       => $policy_details->proposal_no,
                        "Req_Policy_Document" => [
                            "Policy_Number" => $policy_details->policy_number
                        ]
                    ];

                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', [
                        'method'           => 'Re-hit PDF',
                        'requestMethod'    => 'post',
                        'section'          => $productData->product_sub_type_code,
                        'enquiryId'        => $user_product_journey_id,
                        'token'            => $token_data['Authentication']['Token'],
                        'transaction_type' => 'proposal',
                        'productName'      => $productData->product_name,
                        'product_code'     => $product_code,
                        'transaction_id'   => $policy_details->proposal_no,
                        'headers' => [
                            'Content-type' => 'application/json',
                            'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                            'PRODUCT_CODE' => $product_code,
                            'TransactionID' => $policy_details->proposal_no,
                            'Accept' => 'application/json',
                            'Token' => $token_data['Authentication']['Token']
                        ]
                    ]);
                    $pdf_data = $get_response['response'];


                    if ($pdf_data) {
                        $pdf_response = json_decode($pdf_data, TRUE);

                        if (isset($pdf_response['StatusCode']) && $pdf_response['StatusCode'] == 200) {
                            if(!checkValidPDFData(base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES'])))
                            {
                                
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);

                                return  [
                                    'status' => true,
                                    'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                    'data' => [
                                        'policy_number' => $policy_details->policy_number,
                                    ]
                                ];
                                
                                // return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                            }
                            Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($policy_details->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));

                            updateJourneyStage([
                                'user_product_journey_id' => $user_product_journey_id,
                                'stage'                   => STAGE_NAMES['POLICY_ISSUED']
                            ]);

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $policy_details->user_proposal_id],
                                [
                                    'pdf_url'       => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($policy_details->user_proposal_id) . '.pdf',
                                    'status'        => 'SUCCESS'
                                ]
                            );

                            PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                                ->where('active', 1)
                                ->update([
                                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                                ]);

                            $pdf_response_data = [
                                'status' => true,
                                'msg' => 'success',
                                'data' => [
                                    'policy_number' => $policy_details->policy_number,
                                    'pdf_link'      => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($policy_details->user_proposal_id) . '.pdf')
                                ]
                            ];
                        } else {
                            $pdf_response_data = [
                                'status' => false,
                                'msg'    => 'Error : Service Error',
                                'dev_log' => $pdf_response['Error'] ?? 'Error : Service Error',
                            ];
                        }
                    } else {
                        $pdf_response_data = [
                            'status' => false,
                            'msg'    => 'Error : Service Error',
                        ];
                    }
                } else {
                    $pdf_response_data = [
                        'status' => false,
                        'msg'    => 'Error : Service Error',
                    ];
                }
            } else {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'Policy number not found',
                ];
            }

            return response()->json($pdf_response_data);
        }else{
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
                $payment_data = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                    ->where('active', 1)
                    ->first();

                $type = $payment_data->order_id;

                if (strpos($type, 'CP') !== false) {
                    $agentcode = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE');
                } elseif (strpos($type, 'LP') !== false) {
                    $agentcode = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_AGENT_CODE');
                }
                $input_array = [
                    'PolicyNo' => $policy_details->policy_number,
                    'AgentCode' => $agentcode,
                ];

                $get_response = getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POLICY_PDF_URL'),
                    $input_array, 'hdfc_ergo',
                    [
                        'root_tag' => 'PDF',
                        'section' => 'TAXI',
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
    }

    public static function policyGeneration($transaction_ref_number, $productData, $user_product_journey_id, $product_code)
    {
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)
        ->first();

        $token = self::tokenGeneration($productData, $product_code, $user_product_journey_id, $proposal);

        if (!$token['status']) {
            return response()->json([
                'status' => false,
                'msg'    => 'Token service issue',
            ]);
        }

        $token = $token['token'];

        $proposal_array = json_decode($proposal->additional_details_data, true);
        $proposal_array['Payment_Details'] = [
            'GC_PaymentID'      => null,
            'BANK_NAME'         => config('constants.IcConstants.hdfc_ergo.BANK_NAME'),
            'BANK_BRANCH_NAME'  => config('constants.IcConstants.hdfc_ergo.BANK_BRANCH_NAME'),
            'PAYMENT_MODE_CD'   => config('constants.IcConstants.hdfc_ergo.PAYMENT_MODE_CD'),
            'PAYER_TYPE'        => config('constants.IcConstants.hdfc_ergo.PAYER_TYPE'),
            'PAYMENT_AMOUNT'    => $proposal->final_payable_amount,
            'INSTRUMENT_NUMBER' => $transaction_ref_number,
            'PAYMENT_DATE'      => date('d/m/Y'),
        ];

        $get_response = getWsData(
            config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_PROPOSAL'),
            $proposal_array,
            'hdfc_ergo',
            [
                'method'           => 'Policy Generation',
                'requestMethod'    => 'post',
                'section'          => $productData->product_sub_type_code,
                'enquiryId'        => $user_product_journey_id,
                'token'            => $token,
                'transaction_type' => 'proposal',
                'productName'      => $productData->product_name,
                'product_code'     => $product_code,
                'transaction_id'   => $proposal_array['TransactionID'],
                'headers' => [
                    'Content-type' => 'application/json',
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                    'PRODUCT_CODE' => $product_code,
                    'TransactionID' => $proposal_array['TransactionID'],
                    'Accept' => 'application/json',
                    'Token' => $token
                ]
            ]
        );
        $policy_data = $get_response['response'];

        if ($policy_data) {
            $policy_data = json_decode($policy_data, true);

            if (isset($policy_data['StatusCode']) && $policy_data['StatusCode'] == 200) {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_number'     => $policy_data['Policy_Details']['PolicyNumber'],
                        'policy_start_date' => $proposal->policy_start_date,
                        'premium'           => $proposal->final_payable_amount,
                        'created_on'        => date('Y-m-d H:i:s')
                    ]
                );

                PaymentRequestResponse::where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'order_id'   => $policy_data['TransactionID'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey_id,
                    'stage'                   => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                $proposal->proposal_no = $policy_data['TransactionID'];
                $proposal->policy_number = $policy_data['Policy_Details']['PolicyNumber'];

                return self::pdfGeneration($proposal, $productData, $user_product_journey_id, $product_code);
            } else {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Error: Service Error',
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'msg'    => 'Error: Service Error',
            ]);
        }
    }

    public static function pdfGeneration($proposal, $productData, $user_product_journey_id, $product_code)
    {
        $token = self::tokenGeneration($productData, $product_code, $user_product_journey_id, $proposal);

        if (!$token['status']) {
            return response()->json([
                'status' => false,
                'msg'    => 'Token service issue',
            ]);
        }

        $policy_details = PolicyDetails::where('proposal_id', $proposal->user_proposal_id)->first();

        $token = $token['token'];

        $policy_array = [
            "TransactionID"       => $proposal->proposal_no,
            "Req_Policy_Document" => [
                "Policy_Number" => $policy_details->policy_number
            ]
        ];

        $get_response = getWsData(
            config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD'),
            $policy_array,
            'hdfc_ergo',
            [
                'method'           => 'Re-hit PDF',
                'requestMethod'    => 'post',
                'section'          => $productData->product_sub_type_code,
                'enquiryId'        => $user_product_journey_id,
                'token'            => $token,
                'transaction_type' => 'proposal',
                'productName'      => $productData->product_name,
                'product_code'     => $product_code,
                'transaction_id'   => $proposal->proposal_no,
                'headers' => [
                    'Content-type' => 'application/json',
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                    'PRODUCT_CODE' => $product_code,
                    'TransactionID' => $proposal->proposal_no,
                    'Accept' => 'application/json',
                    'Token' => $token
                ]
            ]
        );
        $pdf_data = $get_response['response'];

        if (!empty($pdf_data)) {
            $pdf_response = json_decode($pdf_data, true);

            if (($pdf_response['StatusCode'] ?? '') == 200) {
                if(!checkValidPDFData(base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']))) {
                    updateJourneyStage([
                        'user_product_journey_id' => $user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                    return  [
                        'status' => true,
                        'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                        'data' => [
                            'policy_number' => $policy_details->policy_number,
                        ]
                    ];
                }
                $pdf_url = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) . '.pdf';
                Storage::put($pdf_url, base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey_id,
                    'stage'                   => STAGE_NAMES['POLICY_ISSUED']
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $policy_details->proposal_id],
                    [
                        'pdf_url'       => $pdf_url,
                        'status'        => 'SUCCESS'
                    ]
                );

                PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);

                return response()->json([
                    'status' => true,
                    'msg' => 'success',
                    'data' => [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_link'      => file_url($pdf_url)
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Error : Service Error',
                    'dev_log' => $pdf_response['Error'] ?? 'Error : Service Error',
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'msg'    => 'Error : Service Error',
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

    public static function tokenGeneration($productData, $product_code, $user_product_journey_id, $proposal)
    {
        $get_response = getWsData(
            config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_AUTHENTICATE_URL'),
            [],
            'hdfc_ergo',
            [
                'method'           => 'Token Generation',
                'section'          => $productData->product_sub_type_code,
                'enquiryId'        => $user_product_journey_id,
                'productName'      => $productData->product_name,
                'transaction_type' => 'proposal',
                'product_code'     => $product_code,
                'transaction_id'   => $proposal->proposal_no,
                'headers' => [
                    'Content-type' => 'application/json',
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                    'PRODUCT_CODE' => $product_code,
                    'TransactionID' => $proposal->proposal_no,
                    'Accept' => 'application/json',
                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CREDENTIAL')
                ]
            ]
        );

        $token = $get_response['response'];

        if (!empty($token)) {
            $token_data = json_decode($token, TRUE);
            if (($token_data['StatusCode'] ?? '') == 200) {
                return [
                    'status' => true,
                    'token' => $token_data['Authentication']['Token']
                ];
            }
        }

        return [
            'status' => false
        ];
    }

    public static function finsallRehitService($user_product_journey_id, $proposal_no, $paymentStatusService = [])
    {
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if (empty($proposal_no) || empty($proposal)) {
            return response()->json([
                'status' => false,
                'msg' => 'Details not found'
            ]);
        }

        if (empty($paymentStatusService) || !($paymentStatusService['status'] ?? false)) {
            $paymentStatusService = FinsallController::paymentStatus($proposal, $proposal_no);
        }

        if ($paymentStatusService['status']) {
            PaymentRequestResponse::where('order_id', $proposal_no)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);

            $request = (object)[];
            $request->txnRefNo = $paymentStatusService['txnRefNo'];
            $request->txnDateTime = $paymentStatusService['txnDateTime'];
            $master_policy_id = QuoteLog::where('user_product_journey_id', $user_product_journey_id)
                ->first();
            $productData = getProductDataByIc($master_policy_id->master_policy_id);
            $parent_id = get_parent_code($productData->product_sub_type_id);

            $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

            if ($parent_id == 'PCV') {
                $product_code = in_array($premium_type, ['third_party', 'third_party_breakin']) ? 2313 : 2314;
            } else {
                $product_code = in_array($premium_type, ['third_party', 'third_party_breakin']) ? 2317 : 2315;
            }
            return self::policyGeneration(
                $paymentStatusService['txnRefNo'],
                $productData,
                $user_product_journey_id,
                $product_code
            );
        }

        return response()->json([
            'status' => false,
            'msg' => STAGE_NAMES['PAYMENT_FAILED']
        ]);
    }
}
