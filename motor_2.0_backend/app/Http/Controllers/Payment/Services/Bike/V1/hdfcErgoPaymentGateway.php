<?php

namespace App\Http\Controllers\Payment\Services\Bike\V1;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Quotes\Bike\V1\hdfc_ergo;

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

        $transaction_no = (int) (config('IC.HDFC_ERGO.V1.BIKE.TRANSACTION_NO_SERIES_GIC') . date('ymd') . rand(10000, 99999));
        $str = 'TransactionNo=' . $transaction_no;
        $str .= '&TotalAmount=' . $user_proposal->final_payable_amount;
        $str .= '&AppID=' . config('IC.HDFC_ERGO.V1.BIKE.APP_ID');
        $str .= '&SubscriptionID=' . config('IC.HDFC_ERGO.V1.BIKE.SUB_ID');
        $str .= '&SuccessUrl=' . route('bike.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
        $str .= '&FailureUrl=' . route('bike.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
        $str .= '&Source=' . 'POST';
        $checksum_url = config('IC.HDFC_ERGO.V1.BIKE.GENERATE_CHECKSUM') . '?' . $str;

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

        $payment_url = config('IC.HDFC_ERGO.V1.BIKE.PAYMENT_URL');

        PaymentRequestResponse::insert([
            'quote_id' => $quote_log_id,
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'user_proposal_id' => $user_proposal->user_proposal_id,
            'ic_id' => $icId,
            'order_id' => $user_proposal->proposal_no,
            'amount' => $user_proposal->final_payable_amount,
            'payment_url' => $payment_url,
            'return_url' => route('bike.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
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
                'trnsno' => $transaction_no, //$user_proposal->user_product_journey_id,
                'Amt' => $user_proposal->final_payable_amount,
                'Appid' => config('IC.HDFC_ERGO.V1.BIKE.APP_ID'),
                'Subid' => config('IC.HDFC_ERGO.V1.BIKE.SUB_ID'),
                'Chksum' => $checksum,
                'Src' => 'POST',
                'Surl' => route('bike.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'Furl' => route('bike.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
            ],
        ];

        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);
        return response()->json([
            'status' => true,
            'msg' => "Payment Redirectional",
            'data' => $return_data,
        ]);
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_BIKE') == 'Y') {
            $return_url = config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL');
            $user_proposal = UserProposal::find($request->user_proposal_id);
            try {
                PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                    ->where('active', 1)
                    ->update(['response' => $request->All()]);
                $message = STAGE_NAMES['PAYMENT_FAILED'];

                if (!empty($request->hdnmsg)) {
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
                    $status = $PaymentStatus == 'SPD' ? true : false;

                    PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                        ->where('active', 1)
                        ->update([
                            'status' => $status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                            'response' => $request->hdnmsg,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    if ($status) {
                        $message = STAGE_NAMES['PAYMENT_SUCCESS'];
                        $return_url = config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL');
                        $requestData = getQuotation($user_proposal->user_product_journey_id);
                        $enquiryId = $user_proposal->user_product_journey_id;
                        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                            ->first();
                        $productData = getProductDataByIc($master_policy_id->master_policy_id);
                        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                            ->pluck('premium_type_code')
                            ->first();
                        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
                        $ProductCode = $tp_only ? '2320' : '2312';
                        if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                            $ProductCode = '2367';
                        }
                        if ($requestData->business_type == 'newbusiness') {
                            $business_type = 'New Business';
                        } else if ($requestData->business_type == 'rollover') {
                            $business_type = 'Roll Over';
                        } else if ($requestData->business_type == 'breakin') {
                            $business_type = 'Break-In';
                        }

                        $productName = $productData->product_name . " ($business_type)";
                        $token = hdfc_ergo::hdfcErgoGetToken($enquiryId, $user_proposal->unique_proposal_id, $productName, $ProductCode, 'payment');

                        $proposal_array = json_decode(base64_decode($user_proposal->additional_details_data), true);
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
                            'Req_PolicyConfirmation' => null,
                            'TransactionID' => $transactionid,
                            'Proposal_no' => $user_proposal['proposal_no']

                        ];
                        // $proposal_array['Proposal_no'] =  $user_proposal['proposal_no'];
                        $proposal_array['Payment_Details'] = [
                            'GC_PaymentID' => null,
                            // 'productcode'=>$ProductCode,
                            'BANK_NAME' => 'BIZDIRECT',
                            'BANK_BRANCH_NAME' => 'Andheri',
                            'PAYMENT_MODE_CD' => 'EP',
                            'PAYER_TYPE' => 'DEALER',
                            'PAYMENT_AMOUNT' => $TxnAmount,
                            'INSTRUMENT_NUMBER' => $TransctionRefNo,
                            'PAYMENT_DATE' => date('d/m/Y'),
                            'OTC_Transaction_No' =>  '',
                            'IsReserved' =>  0,
                            'IsPolicyIssued' =>  '0',
                            'Elixir_bank_code' =>  null
                        ];
                        // dd($proposal_array);
                        $user_proposal->additional_details_data = base64_encode(json_encode($proposal_array));

                        UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                            ->where('user_proposal_id', $user_proposal->user_proposal_id)
                            ->update(['additional_details_data' => $user_proposal->additional_details_data]);

                        if ($token['status']) {
                            $generate_policy = self::generate_policy($user_proposal, $token['message'], $productName)->getOriginalContent();

                            if ($generate_policy['status']) {
                                $user_proposal->policy_no = $generate_policy['policy_no'];
                                $generate_pdf = self::generate_pdf($user_proposal, $token['message'], $productName)->getOriginalContent();
                                $message = $generate_pdf['msg'];
                            }
                        }
                    }
                }

                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => $message
                ]);

                if ($status) {
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                } else {
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
                }

                return redirect($return_url . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
            } catch (\Exception $e) {
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
                //return redirect($return_url . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
            }
        } else {
            $return_url = config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL');
            $user_proposal = UserProposal::find($request->user_proposal_id);
            try {
                PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                    ->where('active', 1)
                    ->update(['response' => $request->All()]);
                $message = STAGE_NAMES['PAYMENT_FAILED'];

                if (!empty($request->hdnmsg)) {
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
                    $status = $PaymentStatus == 'SPD' ? true : false;

                    PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                        ->where('active', 1)
                        ->update([
                            'status' => $status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                            'response' => $request->hdnmsg,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    if ($status) {
                        $message = STAGE_NAMES['PAYMENT_SUCCESS'];
                        $return_url = config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL');
                        $requestData = getQuotation($user_proposal->user_product_journey_id);
                        $enquiryId = $user_proposal->user_product_journey_id;
                        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                            ->first();
                        $productData = getProductDataByIc($master_policy_id->master_policy_id);
                        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                            ->pluck('premium_type_code')
                            ->first();
                        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
                        $ProductCode = $tp_only ? '2320' : '2312';
                        if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                            $ProductCode = '2367';
                        }
                        if ($requestData->business_type == 'newbusiness') {
                            $business_type = 'New Business';
                        } else if ($requestData->business_type == 'rollover') {
                            $business_type = 'Roll Over';
                        } else if ($requestData->business_type == 'breakin') {
                            $business_type = 'Break-In';
                        }

                        $productName = $productData->product_name . " ($business_type)";
                        $token = hdfc_ergo::hdfcErgoGetToken($enquiryId, $user_proposal->unique_proposal_id, $productName, $ProductCode, 'payment');

                        $proposal_array = json_decode(base64_decode($user_proposal->additional_details_data), true);

                        $proposal_array['Payment_Details'] = [
                            'GC_PaymentID' => null,
                            // 'productcode'=>$ProductCode,
                            'BANK_NAME' => 'BIZDIRECT',
                            'BANK_BRANCH_NAME' => 'Andheri',
                            'PAYMENT_MODE_CD' => 'EP',
                            'PAYER_TYPE' => 'DEALER',
                            'PAYMENT_AMOUNT' => $TxnAmount,
                            'INSTRUMENT_NUMBER' => $TransctionRefNo,
                            'PAYMENT_DATE' => date('d/m/Y'),
                            'OTC_Transaction_No' =>  '',
                            'IsReserved' =>  0,
                            'IsPolicyIssued' =>  '0',
                            'Elixir_bank_code' =>  null
                        ];

                        $user_proposal->additional_details_data = base64_encode(json_encode($proposal_array));

                        UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                            ->where('user_proposal_id', $user_proposal->user_proposal_id)
                            ->update(['additional_details_data' => $user_proposal->additional_details_data]);

                        if ($token['status']) {
                            $generate_policy = self::generate_policy($user_proposal, $token['message'], $productName)->getOriginalContent();

                            if ($generate_policy['status']) {
                                $user_proposal->policy_no = $generate_policy['policy_no'];
                                $generate_pdf = self::generate_pdf($user_proposal, $token['message'], $productName)->getOriginalContent();
                                $message = $generate_pdf['msg'];
                            }
                        }
                    }
                }

                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => $message
                ]);

                if ($status) {
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                } else {
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
                }

                return redirect($return_url . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
            } catch (\Exception $e) {
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
                //return redirect($return_url . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
            }
        }
    }

    public static function generatePdf($request)
    {
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_BIKE') == 'Y') {
            return self::generatePdfNewFlow($request);
        }

        $status = false;
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $business_type = [
            'newbusiness' => 'New Business',
            'rollover' => 'Roll Over',
            'breakin' => 'Break-In',
        ];
        $enquiryId = $user_proposal->user_product_journey_id;
        $master_policy_id = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);
        $productName = $productData->product_name . " (" . $business_type[$user_proposal['business_type']] . ")";
        $productCode = $user_proposal->product_code;
        $token = hdfc_ergo::hdfcErgoGetToken($enquiryId, $user_proposal->unique_proposal_id, $productName, $productCode, 'payment');
        $message = $token['message'];
        $message = STAGE_NAMES['PAYMENT_SUCCESS'];
        $gen_pdf_flag = true;
        $pdf_url = '';

        if ($token['status']) {
            if (empty($user_proposal->policy_no)) {
                $generate_policy = self::generate_policy($user_proposal, $token['message'], $productName)->getOriginalContent();
                $gen_pdf_flag = $generate_policy['status'];
            }

            if ($gen_pdf_flag) {
                $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $generate_pdf = self::generate_pdf($user_proposal, $token['message'], $productName)->getOriginalContent();
                $status = $generate_pdf['status'];
                $message = $generate_pdf['msg'];
                $pdf_url = $generate_pdf['pdf'];
            }

            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => $message
            ]);
        }

        return response()->json([
            'status' => $status,
            'msg' => $message,
            'data' => [
                'policy_number' => $user_proposal->policy_no,
                'pdf_link'      => file_url($pdf_url)
            ]
        ]);
    }

    public static function generate_pdf($user_proposal, $token, $productName = '')
    {
        $status = false;
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        $enquiryId = $user_proposal->user_product_journey_id;
        $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
        // $transactionid = customEncrypt($enquiryId);
        $pdf_array = [
            'TransactionID' => $transactionid,
            'Req_Policy_Document' => [
                'Policy_Number' => $user_proposal->policy_no,
            ],
        ];

        $additionData = [
            'type' => 'withToken',
            'method' => 'PDF Generation',
            'requestMethod' => 'post',
            'section' => 'bike',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'proposal',
            'productName' => $productName,
            'TOKEN' => $token,
            'PRODUCT_CODE' => $user_proposal->product_code,
            'TRANSACTIONID' => $transactionid,
            'SOURCE' => config('IC.HDFC_ERGO.V1.BIKE.SOURCE_ID'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID'),
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.BIKE.CREDENTIAL'),
        ];

        $get_response = getWsData(config('IC.HDFC_ERGO.V1.BIKE.GENERATE_PDF'), $pdf_array, 'hdfc_ergo', $additionData);
        $pdf_data = $get_response['response'];
        $pdf_response = json_decode($pdf_data, TRUE);
        $pdf_path = '';
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];

        if ($pdf_response['StatusCode'] == 200) {
            $pdf_path = config('IC.HDFC_ERGO.V1.BIKE.PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf';

            try {
                Storage::put($pdf_path, base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id], [
                    'pdf_url' => $pdf_path,
                    'status' => 'SUCCESS'
                ]);
                $message = STAGE_NAMES['POLICY_ISSUED'];
            } catch (\Throwable $th) {
                $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
            }
        }

        return response()->json([
            'status' => $status,
            'msg' => $message,
            'pdf' => $pdf_path
        ]);
    }

    public static function generate_policy($user_proposal, $token, $productName = '')
    {
        $status = false;
        $policy_no = '';
        $proposal_array = json_decode(base64_decode($user_proposal->additional_details_data), true);
        $transactionid = $proposal_array['TransactionID'];
        $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->orderBy('Active', 'DESC')
            ->get()
            ->toArray();

        foreach ($PaymentRequestResponse as $p_key => $p_value) {
            if (!isset($proposal_array['Payment_Details'])) {
                $proposal_array['Payment_Details'] = [
                    'GC_PaymentID' => null,
                    'BANK_NAME' => 'BIZDIRECT',
                    'BANK_BRANCH_NAME' => 'Andheri',
                    'PAYMENT_MODE_CD' => 'EP',
                    'PAYER_TYPE' => 'DEALER',
                    'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount,
                    'INSTRUMENT_NUMBER' => $p_value['customer_id'],
                    'PAYMENT_DATE' => date('d-m-Y', strtotime($p_value['updated_at'])),
                    'OTC_Transaction_No' =>  '',
                    'IsReserved' =>  0,
                    'IsPolicyIssued' =>  '0',
                    'Elixir_bank_code' =>  null
                ];
            }

            $additionData = [
                'type' => 'withToken',
                'method' => 'Policy Generation ' . $p_value['customer_id'],
                'requestMethod' => 'post',
                'section' => 'bike',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'TOKEN' => $token,
                'transaction_type' => 'proposal',
                'productName' => $productName,
                'PRODUCT_CODE' => $user_proposal->product_code,
                'TRANSACTIONID' => $transactionid,
                'SOURCE' => config('IC.HDFC_ERGO.V1.BIKE.SOURCE_ID'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID'),
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.BIKE.CREDENTIAL'),
            ];
            $get_response = getWsData(config('IC.HDFC_ERGO.V1.BIKE.SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
            $getpremium = $get_response['response'];
            $arr_proposal = json_decode($getpremium, true);

            if ($arr_proposal['StatusCode'] == 200 && !empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
                $status = true;
                $policy_no = $arr_proposal['Policy_Details']['PolicyNumber'];
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $policy_no,
                        'policy_start_date' => $user_proposal->policy_start_date,
                        'premium' => $user_proposal->final_payable_amount,
                        'created_on' => date('Y-m-d H:i:s'),
                        'status' => 'SUCCESS'
                    ]
                );

                PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)->update([
                    'active'  => 0
                ]);
                PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('id', $p_value['id'])
                    ->update([
                        'proposal_no' => $arr_proposal['Policy_Details']['ProposalNumber'],
                        'active' => '1',
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);


                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('user_proposal_id', $user_proposal->user_proposal_id)
                    ->update(['policy_no' => $policy_no]);
                break;
            }
        }

        return response()->json([
            'status' => $status,
            'policy_no' => $policy_no,
        ]);
    }

    public static function check_payment_status($enquiry_id, $proposal, $request)
    {
        $payment_status_request = [
            'TransactionNo' => $proposal->proposal_no,
            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
            'Checksum' => strtoupper(hash('sha512', config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY') . '|' . $proposal->proposal_no . '|' . config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN') . '|S001'))
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
                    'status'    => true
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
        $master_policy_id = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        // $productData = getProductDataByIc($master_policy_id->master_policy_id);
        $ProductCode = $user_proposal->product_code;

        $additionData = [
            'type' => 'getToken',
            'method' => 'Token Generation',
            'section' => 'bike',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'proposal',
            'productName' => '',
            'PRODUCT_CODE' => $ProductCode,
            'TRANSACTIONID' => $enquiryId,
            'SOURCE' => config('IC.HDFC_ERGO.V1.BIKE.SOURCE_ID'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID'),
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.BIKE.CREDENTIAL'),
        ];

        $get_response = getWsData(config('IC.HDFC_ERGO.V1.BIKE.GET_TOKEN'), '', 'hdfc_ergo', $additionData);
        $token = $get_response['response'];
        $token_data = json_decode($token, TRUE);
        if (!isset($token_data['Authentication']['Token'])) {
            return [
                "status" => false,
                'message' => "Token Generation Failed"
            ];
        }
        $additionData = [
            'type' => 'withToken',
            'method' => 'Policy Generation',
            'requestMethod' => 'post',
            'section' => 'car',
            'enquiryId' => $user_proposal->user_product_journey_id,
            'TOKEN' => $token_data['Authentication']['Token'],
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode,
            'SOURCE' => config('IC.HDFC_ERGO.V1.BIKE.SOURCE_ID'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID'),
            'TRANSACTIONID' => $enquiryId,
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.BIKE.CREDENTIAL'),
        ];
        if ($policy_details->policy_number == '') {
            $proposal_array = json_decode(base64_decode($user_proposal->additional_details_data), true);
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
            $proposal_array['Proposal_no'] =  $user_proposal->proposal_no;
            $proposal_array['TransactionID'] = $transactionid;
            $proposal_array['Payment_Details'] = [
                'GC_PaymentID'          => null,
                'BANK_NAME'             => 'BIZDIRECT',
                'BANK_BRANCH_NAME'      => 'Andheri',
                'Elixir_bank_code'      => null,
                'PAYMENT_MODE_CD'       => 'EP',
                'IsPolicyIssued'        => "0",
                'IsReserved'            => 0,
                'OTC_Transaction_No'    => "",
                'PAYER_TYPE'            => 'DEALER',
                'PAYMENT_AMOUNT'        => $user_proposal->final_payable_amount
            ];

            foreach ($PaymentRequestResponse as $p_key => $p_value) {
                if (empty($p_value['customer_id'])) {
                    continue;
                }
                $proposal_array['Payment_Details']['INSTRUMENT_NUMBER'] = $p_value['customer_id'];
                $proposal_array['Payment_Details']['PAYMENT_DATE'] = date('d/m/Y', strtotime($p_value['updated_at']));
                $additionData['method'] = 'Policy Generation - Re Hit';
                $get_response = getWsData(config('IC.HDFC_ERGO.V1.BIKE.SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                $getpremium = $get_response['response'];
                $arr_proposal = json_decode($getpremium, true);
                if (isset($arr_proposal['StatusCode']) && $arr_proposal['StatusCode'] == 200 && !empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
                    $arr_proposal['Error'] = null;
                    $arr_proposal['Warning'] = null;
                    break;
                }
            }
            if (!empty($arr_proposal['Error'] ?? '')) { // && ($arr_proposal['Error'], 'ALREADY GENERATED')){
                $contains = Str::contains($arr_proposal['Error'], [
                    ' ALREADY GENERATED FOR THIS TRANSACTION ID'
                ]);
                if ($contains) {
                    $errorString = (explode(' ALREADY GENERATED FOR THIS TRANSACTION ID', $arr_proposal['Error'])[0]) ?? '';
                    $policyNumber = (explode('Policy No ', $errorString)[1]) ?? null;
                    if (!empty($policyNumber)) {
                        $arr_proposal['Policy_Details']['PolicyNumber'] = $policyNumber;
                        $arr_proposal['StatusCode'] = 200;
                        $arr_proposal['Error'] = null;
                        $arr_proposal['Warning'] = null;
                    }
                }
            } else if (empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
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
                'type' => 'withToken',
                'method' => 'Pdf Generation',
                'requestMethod' => 'post',
                'section' => 'car',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'TOKEN' => $token_data['Authentication']['Token'],
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode, config('IC.HDFC_ERGO.V1.BIKE.PRODUCT_CODE_GIC'),
                'SOURCE' => config('IC.HDFC_ERGO.V1.BIKE.SOURCE_ID'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID'),
                'TRANSACTIONID' => $enquiryId,
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.BIKE.CREDENTIAL'),
            ];
            $policy_array = [
                "TransactionID" => $user_proposal->proposal_no,
                "Req_Policy_Document" => [
                    "Policy_Number" => $policy_details->policy_number
                ]
            ];
            $get_response = getWsData(config('IC.HDFC_ERGO.V1.BIKE.GENERATE_PDF'), $policy_array, 'hdfc_ergo', $additionData);
            $pdf_data = $get_response['response'];
            $pdf_response = json_decode($pdf_data, TRUE);
            //Generate Policy PDF - Start

            if ($pdf_response['StatusCode'] == 200) {
                $pdf_final_data = Storage::put(config('IC.HDFC_ERGO.V1.BIKE.PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $policy_details->policy_number,
                        'ic_pdf_url' => '',
                        'pdf_url' => config('IC.HDFC_ERGO.V1.BIKE.PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                        'status' => 'SUCCESS'
                    ]
                );
                return response()->json([
                    'status' => true,
                    'msg' => 'PDF Generated Successfully',
                    'data' => [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_link'      => file_url(config('IC.HDFC_ERGO.V1.BIKE.PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf')
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
                        'pdf_link'      => ''
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
