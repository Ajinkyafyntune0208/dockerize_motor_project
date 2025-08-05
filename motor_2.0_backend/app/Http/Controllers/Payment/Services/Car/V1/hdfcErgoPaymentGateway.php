<?php

namespace App\Http\Controllers\Payment\Services\Car\V1;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

use App\Models\MasterPolicy;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Storage;
use App\Models\PaymentRequestResponse;
use App\Models\MasterPremiumType;

class hdfcErgoPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->pluck('quote_id')
            ->first();

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        $transaction_no = (int) (config('IC.HDFC_ERGO.V1.CAR.TRANSACTION_NO_SERIES_GIC') . date('ymd') . rand(10000, 99999));
        $str = 'TransactionNo=' . $transaction_no;
        $str .= '&TotalAmount=' . $user_proposal->final_payable_amount;
        $str .= '&AppID=' . config('IC.HDFC_ERGO.V1.CAR.APPID_PAYMENT_GIC');
        $str .= '&SubscriptionID=' . config('IC.HDFC_ERGO.V1.CAR.SubscriptionID_GIC');
        $str .= '&SuccessUrl=' . route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
        $str .= '&FailureUrl=' . route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
        $str .= '&Source=' . 'POST';
        $checksum_url = config('IC.HDFC_ERGO.V1.CAR.PAYMENT_CHECKSUM_LINK_GIC') . '?' . $str;
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
        $payment_url = config('IC.HDFC_ERGO.V1.CAR.PAYMENT_GATEWAY_LINK_GIC');
        PaymentRequestResponse::insert([
            'quote_id' => $quote_log_id,
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'user_proposal_id' => $user_proposal->user_proposal_id,
            'ic_id' => $icId,
            'order_id' => $user_proposal->proposal_no,
            'amount' => $user_proposal->final_payable_amount,
            'payment_url' => $payment_url,
            'return_url' => route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'xml_data' => json_encode($checksum_data, JSON_UNESCAPED_SLASHES),
            'customer_id' => $transaction_no
        ]);

        $return_data = [
            'form_action' => $payment_url,
            'form_method' => 'POST',
            'payment_type' => 0, // form-submit
            'form_data' => [
                'trnsno' => $transaction_no,//$user_proposal->user_product_journey_id,
                'Amt' => $user_proposal->final_payable_amount,
                'Appid' => config('IC.HDFC_ERGO.V1.CAR.APPID_PAYMENT_GIC'),
                'Subid' => config('IC.HDFC_ERGO.V1.CAR.SubscriptionID_GIC'),
                'Chksum' => $checksum,
                'Src' => 'POST',
                'Surl' => route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'Furl' => route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
            ],
        ];


        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];

        updateJourneyStage($data);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        if (config('IC.HDFC_ERGO.V1.CAR.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_CAR') == 'Y') {
            $user_proposal = UserProposal::find($request->user_proposal_id);
            try {
                $request_data = $request->all();
                $requestData = getQuotation($user_proposal->user_product_journey_id);
                $enquiryId = $user_proposal->user_product_journey_id;
                $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->first();
                $productData = getProductDataByIc($master_policy_id->master_policy_id);
                $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                    ->pluck('premium_type_code')
                    ->first();
                if ($premium_type == 'third_party_breakin') {
                    $premium_type = 'third_party';
                }
                if ($premium_type == 'own_damage_breakin') {
                    $premium_type = 'own_damage';
                }

                $ProductCode = $user_proposal->product_code;

                switch ($requestData->business_type) {

                    case 'rollover':
                        $business_type = 'Roll Over';
                        break;

                    case 'newbusiness':
                        $business_type = 'New Business';
                        break;

                    default:
                        $business_type = $requestData->business_type;
                        break;

                }


                PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'response' => $request->hdnmsg,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                if ($request->hdnmsg != null) {
                    $response = explode('|', $request->hdnmsg);
                    $MerchantID = $response[0];
                    $TransactionNo = $response[1];
                    $TransctionRefNo = $response[1];
                    $BankReferenceNo = $response[3];
                    $TxnAmount = $response[4];
                    $BankCode = $response[5];
                    $IsSIOpted = $response[6];
                    $PaymentMode = $response[7];
                    $PG_Remarks = $response[8];
                    $PaymentStatus = $response[9];
                    $TransactionDate = $response[10];
                    $AppID = $response[11];
                    $Checksum = $response[12];
                    $status = $PaymentStatus == 'SPD' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'];

                    PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                        ->where('active', 1)
                        ->update([
                            'status' => $status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => $status
                    ]);

                    if ($status == STAGE_NAMES['PAYMENT_FAILED']) {
                        $enquiryId = $user_proposal->user_product_journey_id;
                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                    }
                    $proposal_array = json_decode($user_proposal->additional_details_data, true);
                    $transactionid = $proposal_array['TransactionID'];
                    $proposal_array['proposal_no'] = $user_proposal->proposal_no;

                    $proposal_array = [
                        'GoGreen' => false,
                        'IsReadyToWait' => null,
                        'PolicyCertifcateNo' => null,
                        'PolicyNo' => null,
                        'Inward_no' => null,
                        'Request_IP' => null,
                        'Customer_Details' => null,
                        'Policy_Details' => null,
                        'Req_GCV' => null,
                        'Req_MISD' => null,
                        'Req_PCV' => null,
                        'IDV_DETAILS' => null,
                        'Req_ExtendedWarranty' => null,
                        'Req_Policy_Document' => null,
                        'Req_PEE' => null,
                        'Req_TW' => null,
                        'Req_GHCIP' => null,
                        'Req_PolicyConfirmation' => null
                    ];

                    $additionData = [
                        'type' => 'gettoken',
                        'method' => 'tokenGeneration',
                        'section' => 'car',
                        'enquiryId' => $enquiryId,
                        'productName' => $productData->product_name . " ($business_type)",
                        'transaction_type' => 'proposal',
                        'PRODUCT_CODE' => $ProductCode,// config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                        'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                        'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                        'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
                    ];

                    $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.TOKEN_LINK_URL_GIC'), '', 'hdfc_ergo', $additionData);
                    $token = $get_response['response'];
                    $token_data = json_decode($token, TRUE);
                    if (isset($token_data['Authentication']['Token'])) {
                        $additionData = [
                            'type' => 'PremiumCalculation',
                            'method' => 'Policy Generation',
                            'requestMethod' => 'post',
                            'section' => 'car',
                            'enquiryId' => $user_proposal->user_product_journey_id,
                            'productName' => $productData->product_name . " ($business_type)",
                            'TOKEN' => $token_data['Authentication']['Token'],
                            'transaction_type' => 'proposal',
                            'PRODUCT_CODE' => $ProductCode,
                            config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                            'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                            'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
                        ];
                        $proposal_array['Proposal_no'] = $user_proposal->proposal_no;
                        $proposal_array['TransactionID'] = $transactionid;
                        $proposal_array['Payment_Details'] = [
                            'GC_PaymentID' => null,
                            'BANK_NAME' => 'BIZDIRECT',
                            'BANK_BRANCH_NAME' => 'Andheri',
                            'Elixir_bank_code' => null,
                            'PAYMENT_MODE_CD' => 'EP',
                            'IsPolicyIssued' => "0",
                            'IsReserved' => 0,
                            'OTC_Transaction_No' => "",
                            'PAYER_TYPE' => 'DEALER',
                            'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount,
                            'INSTRUMENT_NUMBER' => $TransctionRefNo,
                            'PAYMENT_DATE' => date('d/m/Y'),
                        ];
                        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                        $getpremium = $get_response['response'];

                        //                print_r(json_encode([config('IC.HDFC_ERGO.V1.CAR.HDFC_ERGO_MOTOR_GIC_PROPOSAL'), $additionData, $proposal_array, json_decode($getpremium)]));
                        //
                        $arr_proposal = json_decode($getpremium, true);
                        if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null) {
                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $user_proposal->user_proposal_id],
                                [
                                    'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                    'policy_start_date' => $user_proposal->policy_start_date,
                                    'premium' => $user_proposal->final_payable_amount,
                                    'created_on' => date('Y-m-d H:i:s')
                                ]
                            );
                            UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                                ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
                            $pdf_array = [
                                'TransactionID' => $arr_proposal['TransactionID'],
                                'Req_Policy_Document' => [
                                    'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                ],
                            ];
                            PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                                ->where('active', 1)
                                ->update([
                                    'order_id' => $arr_proposal['TransactionID'],
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                            $additionData = [
                                'type' => 'PdfGeneration',
                                'method' => 'Pdf Generation',
                                'requestMethod' => 'post',
                                'section' => 'car',
                                'productName' => $productData->product_name . " ($business_type)",
                                'enquiryId' => $user_proposal->user_product_journey_id,
                                'TOKEN' => $token_data['Authentication']['Token'],
                                'transaction_type' => 'proposal',
                                'PRODUCT_CODE' => $ProductCode,
                                config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                                'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                                'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),

                            ];
                            $policy_array = [
                                "TransactionID" => $arr_proposal['TransactionID'],
                                "Req_Policy_Document" => [
                                    "Policy_Number" => $arr_proposal['Policy_Details']['PolicyNumber']
                                ]
                            ];
                            $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
                            $pdf_data = $get_response['response'];

                            if ($pdf_data === null || $pdf_data == '') {
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }

                            $pdf_response = json_decode($pdf_data, TRUE);

                            if ($pdf_response === null || empty($pdf_response)) {
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }


                            if ($pdf_response['StatusCode'] == 200) {
                                $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                                ]);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id],
                                    [
                                        'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                        'ic_pdf_url' => '',
                                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                                        'status' => STAGE_NAMES['POLICY_ISSUED']
                                    ]
                                );

                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            } else {
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id],
                                    [
                                        'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                        'ic_pdf_url' => '',
                                        'pdf_url' => '',
                                        'status' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]
                                );
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }
                        } else {
                            $enquiryId = $user_proposal->user_product_journey_id;
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                        }
                    } else {
                        $enquiryId = $user_proposal->user_product_journey_id;
                        // return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
                        // return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                    }

                } else {
                    $enquiryId = $user_proposal->user_product_journey_id;
                    // return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                }

            } catch (\Exception $e) {
                $enquiryId = $user_proposal->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
            }
        } else {
            $user_proposal = UserProposal::find($request->user_proposal_id);
            try {
                $request_data = $request->all();
                $requestData = getQuotation($user_proposal->user_product_journey_id);
                $enquiryId = $user_proposal->user_product_journey_id;
                $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->first();
                $productData = getProductDataByIc($master_policy_id->master_policy_id);
                $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                    ->pluck('premium_type_code')
                    ->first();
                if ($premium_type == 'third_party_breakin') {
                    $premium_type = 'third_party';
                }
                if ($premium_type == 'own_damage_breakin') {
                    $premium_type = 'own_damage';
                }

                $ProductCode = $user_proposal->product_code;

                switch ($requestData->business_type) {

                    case 'rollover':
                        $business_type = 'Roll Over';
                        break;

                    case 'newbusiness':
                        $business_type = 'New Business';
                        break;

                    default:
                        $business_type = $requestData->business_type;
                        break;

                }


                PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'response' => $request->hdnmsg,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                if ($request->hdnmsg != null) {
                    $response = explode('|', $request->hdnmsg);
                    $MerchantID = $response[0];
                    $TransactionNo = $response[1];
                    $TransctionRefNo = $response[1];
                    $BankReferenceNo = $response[3];
                    $TxnAmount = $response[4];
                    $BankCode = $response[5];
                    $IsSIOpted = $response[6];
                    $PaymentMode = $response[7];
                    $PG_Remarks = $response[8];
                    $PaymentStatus = $response[9];
                    $TransactionDate = $response[10];
                    $AppID = $response[11];
                    $Checksum = $response[12];
                    $status = $PaymentStatus == 'SPD' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'];

                    PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                        ->where('active', 1)
                        ->update([
                            'status' => $status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => $status
                    ]);

                    if ($status == STAGE_NAMES['PAYMENT_FAILED']) {
                        $enquiryId = $user_proposal->user_product_journey_id;
                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                    }
                    $proposal_array = json_decode($user_proposal->additional_details_data, true);
                    $transactionid = $proposal_array['TransactionID'];

                    $additionData = [
                        'type' => 'gettoken',
                        'method' => 'tokenGeneration',
                        'section' => 'car',
                        'enquiryId' => $enquiryId,
                        'productName' => $productData->product_name . " ($business_type)",
                        'transaction_type' => 'proposal',
                        'PRODUCT_CODE' => $ProductCode,// config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                        'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                        'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                        'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
                    ];
                    $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.TOKEN_LINK_URL_GIC'), '', 'hdfc_ergo', $additionData);
                    $token = $get_response['response'];
                    $token_data = json_decode($token, TRUE);
                    if (isset($token_data['Authentication']['Token'])) {
                        $additionData = [
                            'type' => 'PremiumCalculation',
                            'method' => 'Policy Generation',
                            'requestMethod' => 'post',
                            'section' => 'car',
                            'enquiryId' => $user_proposal->user_product_journey_id,
                            'productName' => $productData->product_name . " ($business_type)",
                            'TOKEN' => $token_data['Authentication']['Token'],
                            'transaction_type' => 'proposal',
                            'PRODUCT_CODE' => $ProductCode,
                            config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                            'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                            'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
                        ];
                        $proposal_array['Proposal_no'] = $user_proposal['proposal_no'];
                        $proposal_array['Payment_Details'] = [
                            'GC_PaymentID' => null,
                            'BANK_NAME' => 'BIZDIRECT',
                            'BANK_BRANCH_NAME' => 'Andheri',
                            'Elixir_bank_code' => null,
                            'PAYMENT_MODE_CD' => 'EP',
                            'IsPolicyIssued' => "0",
                            'IsReserved' => 0,
                            'OTC_Transaction_No' => "",
                            'PAYER_TYPE' => 'DEALER',
                            'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount,
                            'INSTRUMENT_NUMBER' => $TransctionRefNo,
                            'PAYMENT_DATE' => date('d/m/Y'),
                        ];
                        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                        $getpremium = $get_response['response'];

                        //                print_r(json_encode([config('IC.HDFC_ERGO.V1.CAR.HDFC_ERGO_MOTOR_GIC_PROPOSAL'), $additionData, $proposal_array, json_decode($getpremium)]));
                        //
                        $arr_proposal = json_decode($getpremium, true);
                        if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null) {
                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $user_proposal->user_proposal_id],
                                [
                                    'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                    'policy_start_date' => $user_proposal->policy_start_date,
                                    'premium' => $user_proposal->final_payable_amount,
                                    'created_on' => date('Y-m-d H:i:s')
                                ]
                            );
                            UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                                ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
                            $pdf_array = [
                                'TransactionID' => $arr_proposal['TransactionID'],
                                'Req_Policy_Document' => [
                                    'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                ],
                            ];
                            PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                                ->where('active', 1)
                                ->update([
                                    'order_id' => $arr_proposal['TransactionID'],
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                            $additionData = [
                                'type' => 'PdfGeneration',
                                'method' => 'Pdf Generation',
                                'requestMethod' => 'post',
                                'section' => 'car',
                                'productName' => $productData->product_name . " ($business_type)",
                                'enquiryId' => $user_proposal->user_product_journey_id,
                                'TOKEN' => $token_data['Authentication']['Token'],
                                'transaction_type' => 'proposal',
                                'PRODUCT_CODE' => $ProductCode,
                                config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                                'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                                'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),

                            ];
                            $policy_array = [
                                "TransactionID" => $arr_proposal['TransactionID'],
                                "Req_Policy_Document" => [
                                    "Policy_Number" => $arr_proposal['Policy_Details']['PolicyNumber']
                                ]
                            ];
                            $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
                            $pdf_data = $get_response['response'];

                            if ($pdf_data === null || $pdf_data == '') {
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }

                            $pdf_response = json_decode($pdf_data, TRUE);

                            if ($pdf_response === null || empty($pdf_response)) {
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }


                            if ($pdf_response['StatusCode'] == 200) {
                                $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                                ]);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id],
                                    [
                                        'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                        'ic_pdf_url' => '',
                                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                                        'status' => STAGE_NAMES['POLICY_ISSUED']
                                    ]
                                );

                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            } else {
                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id],
                                    [
                                        'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                        'ic_pdf_url' => '',
                                        'pdf_url' => '',
                                        'status' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]
                                );
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }
                        } else {
                            $enquiryId = $user_proposal->user_product_journey_id;
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                        }
                    } else {
                        $enquiryId = $user_proposal->user_product_journey_id;
                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                        // return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                    }

                } else {
                    $enquiryId = $user_proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                }

            } catch (\Exception $e) {
                $enquiryId = $user_proposal->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
            }
        }

    }

    public static function generatePdf($request)
    {
        if (config('IC.HDFC_ERGO.V1.CAR.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_CAR') == 'Y') {
            return self::generatePdfNewFlow($request);
        }

        $enquiryId = customDecrypt($request->enquiryId);
        $policy_details = PaymentRequestResponse::leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'payment_request_response.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'payment_request_response.user_product_journey_id')
            ->where('payment_request_response.user_product_journey_id', $enquiryId)
            ->where(array('payment_request_response.active' => 1, 'payment_request_response.status' => STAGE_NAMES['PAYMENT_SUCCESS']))
            ->select(
                'up.user_proposal_id',
                'up.user_proposal_id',
                'up.proposal_no',
                'up.unique_proposal_id',
                'pd.policy_number',
                'pd.pdf_url',
                'pd.ic_pdf_url',
                'payment_request_response.order_id',
                'payment_request_response.response'
            )
            ->first();
        if ($policy_details == null) {
            $pdf_response_data = [
                'status' => false,
                'msg' => 'Data Not Found',
                'data' => []
            ];
            return response()->json($pdf_response_data);
        }
        $response = explode('|', $policy_details->response);
        $TransctionRefNo = $response[1];
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $productData = getProductDataByIc($request->master_policy_id);
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $ProductCode = $user_proposal->product_code;

        $additionData = [
            'type' => 'gettoken',
            'method' => 'tokenGeneration',
            'section' => 'car',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode,// config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
            'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
            'TRANSACTIONID' => $enquiryId,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
        ];

        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.TOKEN_LINK_URL_GIC'), '', 'hdfc_ergo', $additionData);
        $token = $get_response['response'];
        $token_data = json_decode($token, TRUE);
        //        return $token_data;
        if (!isset($token_data['Authentication']['Token'])) {
            return [
                "status" => false,
                'message' => "Token Generation Failed",
                //                'Response' => $token_data,
            ];
        }
        $additionData = [
            'type' => 'PremiumCalculation',
            'method' => 'Policy Generation',
            'requestMethod' => 'post',
            'section' => 'car',
            'enquiryId' => $user_proposal->user_product_journey_id,
            'TOKEN' => $token_data['Authentication']['Token'],
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode, //config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
            'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
            'TRANSACTIONID' => $enquiryId,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
        ];
        if ($policy_details->policy_number == '') {

            $proposal_array = json_decode($user_proposal->additional_details_data, true);
            $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)->get()->toArray();

            foreach ($PaymentRequestResponse as $p_key => $p_value) {
                if (empty($p_value['customer_id'])) {
                    continue;
                }
                $proposal_array['Payment_Details'] = [
                    'GC_PaymentID' => null,
                    'BANK_NAME' => 'BIZDIRECT',
                    'BANK_BRANCH_NAME' => 'Andheri',
                    'PAYMENT_MODE_CD' => 'EP',
                    'PAYER_TYPE' => 'DEALER',
                    'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount,
                    'INSTRUMENT_NUMBER' => $p_value['customer_id'],
                    'PAYMENT_DATE' => date('d-m-Y', strtotime($p_value['updated_at'])),
                ];

                $additionData['method'] = 'Policy Generation ' . $p_value['customer_id'];

                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                $getpremium = $get_response['response'];
                $arr_proposal = json_decode($getpremium, true);
                if (isset($arr_proposal['StatusCode']) && $arr_proposal['StatusCode'] == 200 && !empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
                    $arr_proposal['Error'] = null;
                    $arr_proposal['Warning'] = null;
                    break;
                }

                unset($proposal_array['Payment_Details']);
            }

            if (empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Policy number not found'
                ]);
            }
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal->user_proposal_id],
                [
                    'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                    'policy_start_date' => $user_proposal->policy_start_date,
                    'premium' => $user_proposal->final_payable_amount,
                    'created_on' => date('Y-m-d H:i:s')
                ]
            );
            UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
            $pdf_array = [
                'TransactionID' => $arr_proposal['TransactionID'],
                'Req_Policy_Document' => [
                    'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                ],
            ];
            $user_proposal->proposal_no = $arr_proposal['TransactionID'];
            $policy_details->policy_number = $arr_proposal['Policy_Details']['PolicyNumber'];
            PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'order_id' => $arr_proposal['TransactionID'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            $arr_proposal['StatusCode'] = 200;
            $arr_proposal['Error'] = null;
            $arr_proposal['Warning'] = null;
        }
        if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null && $arr_proposal['Warning'] == null) {

            $additionData = [
                'type' => 'PdfGeneration',
                'method' => 'Pdf Generation',
                'requestMethod' => 'post',
                'section' => 'car',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'TOKEN' => $token_data['Authentication']['Token'],
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode,
                config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                'TRANSACTIONID' => $enquiryId,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),

            ];
            $policy_array = [
                "TransactionID" => $user_proposal->proposal_no,
                "Req_Policy_Document" => [
                    "Policy_Number" => $policy_details->policy_number
                ]
            ];
            $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
            $pdf_data = $get_response['response'];
            $pdf_response = json_decode($pdf_data, TRUE);
            //Generate Policy PDF - Start

            if ($pdf_response['StatusCode'] == 200) {
                $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $policy_details->policy_number,
                        'ic_pdf_url' => '',
                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                        'status' => 'SUCCESS'
                    ]
                );
                return response()->json([
                    'status' => true,
                    'msg' => 'PDF Generated Successfully',
                    'data' => [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_link' => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf')
                    ]
                ]);
            } else {
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $policy_details->policy_number,
                        'ic_pdf_url' => '',
                        'pdf_url' => '',
                        'status' => 'SUCCESS'
                    ]
                );
                return response()->json([
                    'status' => false,
                    'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                    'data' => [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_link' => ''
                    ]
                ]);
            }

        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Policy Generation Failed',
                'data' => []
            ]);
        }

    }

    public static function check_payment_status($enquiry_id, $proposal, $request)
    {
        $payment_status_request = [
            'TransactionNo' => $proposal->proposal_no,
            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
            'Checksum' => strtoupper(hash('sha512', config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY') . '|' . $proposal->proposal_no . '|' . config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN') . '|S001'))
        ];

        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_PAYMENT_STATUS_CHECK_URL'), $payment_status_request, 'hdfc_ergo', [
            'section' => $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
            'method' => 'Payment Status check',
            'requestMethod' => 'post',
            'enquiryId' => $proposal->user_product_journey_id,
            'productName' => $proposal->quote_log->master_policy->master_product->product_name,
            'transaction_type' => 'proposal',
            'headers' => [
                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                'Content-Type' => 'application/json',
                'User-Agent' => $request->userAgent(),
                'Accept-Language' => 'en-US,en;q=0.5'
            ]
        ]);
        $payment_status_response = $get_response['response'];

        if ($payment_status_response) {
            $payment_status_response = json_decode($payment_status_response, TRUE);

            if (isset($payment_status_response['VENDOR_AUTH_STATUS']) && $payment_status_response['VENDOR_AUTH_STATUS'] == 'SPD') {

                $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)
                    ->first();
                $additional_details_data = json_encode($payment_status_response);
                UserProposal::where('user_product_journey_id', $enquiry_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'additional_details_data' => $additional_details_data,
                    ]);

                return [
                    'status' => true
                ];
            } else {
                return [
                    'status' => false,
                    'msg' => $payment_status_response['Error Message'] ?? 'Unable to check the payment status. Please try after sometime.'
                ];

            }

        } else {
            return [
                'status' => false,
                'msg' => 'An error occured while generating policy'
            ];

        }
    }

    public static function generatePdfNewFlow($request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $policy_details = PaymentRequestResponse::leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'payment_request_response.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'payment_request_response.user_product_journey_id')
            ->where('payment_request_response.user_product_journey_id', $enquiryId)
            ->where(array('payment_request_response.active' => 1))
            ->select(
                'up.user_proposal_id',
                'up.user_proposal_id',
                'up.proposal_no',
                'up.unique_proposal_id',
                'pd.policy_number',
                'pd.pdf_url',
                'pd.ic_pdf_url',
                'payment_request_response.order_id',
                'payment_request_response.response'
            )
            ->first();
        if ($policy_details == null) {
            $pdf_response_data = [
                'status' => false,
                'msg' => 'Data Not Found',
                'data' => []
            ];
            return response()->json($pdf_response_data);
        }
        // $response = explode('|', $policy_details->response);
        // $TransctionRefNo = $response[1];
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $productData = getProductDataByIc($request->master_policy_id);
        // $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
        //     ->pluck('premium_type_code')
        //     ->first();

        //            $ProductCode = '2311';
        //            if ($premium_type == "third_party") {
        //                $ProductCode = '2319';
        //            }
        $ProductCode = $user_proposal->product_code;
        $additionData = [
            'type' => 'gettoken',
            'method' => 'tokenGeneration',
            'section' => 'car',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode,// config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
            'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
            'TRANSACTIONID' => $enquiryId,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
        ];

        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.TOKEN_LINK_URL_GIC'), '', 'hdfc_ergo', $additionData);
        $token = $get_response['response'];
        $token_data = json_decode($token, TRUE);
        if (!isset($token_data['Authentication']['Token'])) {
            return [
                "status" => false,
                'message' => "Token Generation Failed"
            ];
        }
        $additionData = [
            'type' => 'PremiumCalculation',
            'method' => 'Policy Generation',
            'requestMethod' => 'post',
            'section' => 'car',
            'enquiryId' => $user_proposal->user_product_journey_id,
            'TOKEN' => $token_data['Authentication']['Token'],
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode, //config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
            'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
            'TRANSACTIONID' => $enquiryId,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
        ];
        if ($policy_details->policy_number == '') {
            $proposal_array = json_decode($user_proposal->additional_details_data, true);
            $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)->get()->toArray();
            $transactionid = $proposal_array['TransactionID'];
            $proposal_array = [
                'GoGreen' => false,
                'IsReadyToWait' => null,
                'PolicyCertifcateNo' => null,
                'PolicyNo' => null,
                'Inward_no' => null,
                'Request_IP' => null,
                'Customer_Details' => null,
                'Policy_Details' => null,
                'Req_GCV' => null,
                'Req_MISD' => null,
                'Req_PCV' => null,
                'IDV_DETAILS' => null,
                'Req_ExtendedWarranty' => null,
                'Req_Policy_Document' => null,
                'Req_PEE' => null,
                'Req_TW' => null,
                'Req_GHCIP' => null,
                'Req_PolicyConfirmation' => null
            ];
            $proposal_array['Proposal_no'] = $user_proposal->proposal_no;
            $proposal_array['TransactionID'] = $transactionid;
            $proposal_array['Payment_Details'] = [
                'GC_PaymentID' => null,
                'BANK_NAME' => 'BIZDIRECT',
                'BANK_BRANCH_NAME' => 'Andheri',
                'Elixir_bank_code' => null,
                'PAYMENT_MODE_CD' => 'EP',
                'IsPolicyIssued' => "0",
                'IsReserved' => 0,
                'OTC_Transaction_No' => "",
                'PAYER_TYPE' => 'DEALER',
                'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount
            ];

            foreach ($PaymentRequestResponse as $p_key => $p_value) {
                if (empty($p_value['customer_id'])) {
                    continue;
                }
                $proposal_array['Payment_Details']['INSTRUMENT_NUMBER'] = $p_value['customer_id'];
                $proposal_array['Payment_Details']['PAYMENT_DATE'] = date('d/m/Y', strtotime($p_value['updated_at']));
                $additionData['method'] = 'Policy Generation - Re Hit';
                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                $getpremium = $get_response['response'];
                $arr_proposal = json_decode($getpremium, true);
                if (isset($arr_proposal['StatusCode']) && $arr_proposal['StatusCode'] == 200 && !empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
                    $arr_proposal['Error'] = null;
                    $arr_proposal['Warning'] = null;
                    break;
                }
            }
            if (empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Policy number not found'
                ]);
            }
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal->user_proposal_id],
                [
                    'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                    'policy_start_date' => $user_proposal->policy_start_date,
                    'premium' => $user_proposal->final_payable_amount,
                    'created_on' => date('Y-m-d H:i:s')
                ]
            );
            UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
            $pdf_array = [
                'TransactionID' => $arr_proposal['TransactionID'],
                'Req_Policy_Document' => [
                    'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                ],
            ];
            $user_proposal->proposal_no = $arr_proposal['TransactionID'];
            $policy_details->policy_number = $arr_proposal['Policy_Details']['PolicyNumber'];
            PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                    'order_id' => $arr_proposal['TransactionID'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            $arr_proposal['StatusCode'] = 200;
            $arr_proposal['Error'] = null;
            $arr_proposal['Warning'] = null;
        }
        if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null && $arr_proposal['Warning'] == null) {
            $additionData = [
                'type' => 'PdfGeneration',
                'method' => 'Pdf Generation',
                'requestMethod' => 'post',
                'section' => 'car',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'TOKEN' => $token_data['Authentication']['Token'],
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode,
                config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC'),
                'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                'TRANSACTIONID' => $enquiryId,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),

            ];
            $policy_array = [
                "TransactionID" => $user_proposal->proposal_no,
                "Req_Policy_Document" => [
                    "Policy_Number" => $policy_details->policy_number
                ]
            ];
            $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
            $pdf_data = $get_response['response'];
            $pdf_response = json_decode($pdf_data, TRUE);
            //Generate Policy PDF - Start

            if ($pdf_response['StatusCode'] == 200) {
                $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $policy_details->policy_number,
                        'ic_pdf_url' => '',
                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                        'status' => 'SUCCESS'
                    ]
                );
                return response()->json([
                    'status' => true,
                    'msg' => 'PDF Generated Successfully',
                    'data' => [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_link' => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf')
                    ]
                ]);
            } else {
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $policy_details->policy_number,
                        'ic_pdf_url' => '',
                        'pdf_url' => '',
                        'status' => 'SUCCESS'
                    ]
                );
                return response()->json([
                    'status' => false,
                    'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                    'data' => [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_link' => ''
                    ]
                ]);
            }

        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Policy Generation Failed',
                'data' => []
            ]);
        }
    }
}
