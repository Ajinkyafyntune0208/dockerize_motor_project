<?php

namespace App\Http\Controllers\Payment\Services\V1;

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
class HdfcErgoPaymentGateway
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

        $transaction_no = (int) (config('IC.HDFC_ERGO.V1.CV.TRANSACTION_NO_SERIES') . date('ymd') . rand(10000, 99999));

        $str = 'TransactionNo=' . $transaction_no; //'TransactionNo=' . $user_proposal->user_product_journey_id;
        $str .= '&TotalAmount=' . $user_proposal->final_payable_amount;
        $str .= '&AppID=' . config('IC.HDFC_ERGO.V1.CV.APPID_PAYMENT');
        $str .= '&SubscriptionID=' . config('IC.HDFC_ERGO.V1.CV.SUBSCRIPTION_ID');
        $str .= '&SuccessUrl=' . route('cv.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
        $str .= '&FailureUrl=' . route('cv.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
        $str .= '&Source=POST'; // config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE');
        $checksum_url = config('IC.HDFC_ERGO.V1.CV.PAYMENT_CHECKSUM_LINK') . '?' . $str;

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

        $payment_url = config('IC.HDFC_ERGO.V1.CV.PAYMENT_GATEWAY_LINK');

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
                'Trnsno' => $transaction_no, //$user_proposal->user_product_journey_id,
                'Amt' => (int) $user_proposal->final_payable_amount,
                'Appid' => config('IC.HDFC_ERGO.V1.CV.APPID_PAYMENT'),
                'Subid' => config('IC.HDFC_ERGO.V1.CV.SUBSCRIPTION_ID'),
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
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function confirm($request)
    {
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

        if ($parent_id == 'PCV') {
            $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2313 : 2314;
        } else {
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

            if ($payment_status == 'SPD')    //Payment Successful
            {
                DB::table('payment_request_response')
                ->where('user_proposal_id', $request->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'status'     => STAGE_NAMES['PAYMENT_SUCCESS'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                $prop_array = json_decode($user_proposal->additional_details_data, true);

                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.AUTHENTICATE_URL'), [], 'hdfc_ergo', [
                    'method'           => 'Token Generation',
                    'section'          => $productData->product_sub_type_code,
                    'enquiryId'        => $enquiryId,
                    'productName'      => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'product_code'     => $product_code,
                    'transaction_id'   => $prop_array['TransactionID'],
                    'headers' => [
                        'Content-type' => 'application/json',
                        'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                        'PRODUCT_CODE' => $product_code,
                        'TransactionID' => $prop_array['TransactionID'],
                        'Accept' => 'application/json',
                        'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CV.CREDENTIAL')
                    ]
                ]);
                $token = $get_response['response'];

                if ($token) {
                    $token_data = json_decode($token, TRUE);

                    if (isset($token_data['StatusCode']) && $token_data['StatusCode'] == 200) {
                        $proposal_array['TransactionID'] = $prop_array['TransactionID'];
                        $proposal_array['Proposal_no'] =  $user_proposal->proposal_no;
                        $proposal_array['CIS_Flag'] = 'Y';
                        $proposal_array['Payment_Details'] = [
                            'GC_PaymentID'      => null,
                            'BANK_NAME'         => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.BANK_NAME'),
                            'BANK_BRANCH_NAME'  => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.BANK_BRANCH_NAME'),
                            'PAYMENT_MODE_CD'   => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.PAYMENT_MODE_CD'),
                            'PAYER_TYPE'        => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.PAYER_TYPE'),
                            'PAYMENT_AMOUNT'    => $user_proposal->final_payable_amount,
                            'INSTRUMENT_NUMBER' => $transaction_ref_number,
                            'PAYMENT_DATE'      => date('d/m/Y'),
                        ];

                        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.SUBMIT_PAYMENT'), $proposal_array, 'hdfc_ergo', [
                            'method'           => 'Policy Generation',
                            'requestMethod'    => 'post',
                            'section'          => $productData->product_sub_type_code,
                            'enquiryId'        => $user_proposal->user_product_journey_id,
                            'token'            => $token_data['Authentication']['Token'],
                            'transaction_type' => 'proposal',
                            'productName'      => $productData->product_name,
                            'product_code'     => $product_code,
                            'transaction_id'   => $prop_array['TransactionID'],
                            'headers' => [
                                'Content-type' => 'application/json',
                                'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                                'PRODUCT_CODE' => $product_code,
                                'TransactionID' => $prop_array['TransactionID'],
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
                                    "ApplicationNumber"   => null,
                                    "TransactionID"       => $policy_data['TransactionID'],
                                    "Req_Policy_Document" => [
                                        "Policy_Number" => $policy_data['Policy_Details']['PolicyNumber']
                                    ]
                                ];

                                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', [
                                    'method'           => 'Pdf Generation',
                                    'requestMethod'    => 'post',
                                    'section'          => $productData->product_sub_type_code,
                                    'enquiryId'        => $user_proposal->user_product_journey_id,
                                    'token'            => $token_data['Authentication']['Token'],
                                    'transaction_type' => 'proposal',
                                    'productName'      => $productData->product_name,
                                    'product_code'     => $product_code,
                                    'transaction_id'   => $prop_array['TransactionID'],
                                    'headers' => [
                                        'Content-type' => 'application/json',
                                        'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                                        'PRODUCT_CODE' => $product_code,
                                        'TransactionID' => $prop_array['TransactionID'],
                                        'Accept' => 'application/json',
                                        'Token' => $token_data['Authentication']['Token']
                                    ]
                                ]);
                                $pdf_data = $get_response['response'];

                                if ($pdf_data) {
                                    $pdf_response = json_decode($pdf_data, TRUE);

                                    if (isset($pdf_response['StatusCode']) && $pdf_response['StatusCode'] == 200) {
                                        if (!checkValidPDFData(base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']))) {
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
            } elseif ($payment_status == 'UPD')    //Payment Unsuccessful
            {
                DB::table('payment_request_response')
                ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('active', 1)
                    ->update([
                        'status'     => STAGE_NAMES['PAYMENT_FAILED'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage'                   => STAGE_NAMES['PAYMENT_FAILED']
                ]);

                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
            } else    //Payment Incomplete
            {
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
            }
        } else {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
    }

    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $record_exists = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id
        ])->get();

        if (empty($record_exists)) {
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

        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.AUTHENTICATE_URL'), [], 'hdfc_ergo', [
            'method'           => 'Token Generation',
            'section'          => $productData->product_sub_type_code,
            'enquiryId'        => $user_product_journey_id,
            'productName'      => $productData->product_name,
            'transaction_type' => 'proposal',
            'product_code'     => $product_code,
            'transaction_id'   => $policy_details->proposal_no,
            'headers' => [
                'Content-type' => 'application/json',
                'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                'PRODUCT_CODE' => $product_code,
                'TransactionID' => $policy_details->proposal_no,
                'Accept' => 'application/json',
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CV.CREDENTIAL')
            ]
        ]);
        $token = $get_response['response'];

        if ($token) {
            $token_data = json_decode($token, TRUE);

            if (isset($token_data['StatusCode']) && $token_data['StatusCode'] == 200) {
                if (!isset($policy_details->policy_number) || $policy_details->policy_number == '' || $policy_details->policy_number == NULL) {
                    $prop_array = json_decode($policy_details->additional_details_data, true);

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

                            if ($payment_status != 'SPD')    //Payment UnSuccessful
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

                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', [
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
                        'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
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
                        if (!checkValidPDFData(base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']))) {

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

        $prop_array = json_decode($proposal->additional_details_data, true);
        $proposal_array['TransactionID'] = $prop_array['TransactionID'];
        $proposal_array['Proposal_no'] =  $proposal->proposal_no;
        $proposal_array['CIS_Flag'] = 'Y';
        $proposal_array['Payment_Details'] = [
            'GC_PaymentID'      => null,
            'BANK_NAME'         => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.BANK_NAME'),
            'BANK_BRANCH_NAME'  => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.BANK_BRANCH_NAME'),
            'PAYMENT_MODE_CD'   => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.PAYMENT_MODE_CD'),
            'PAYER_TYPE'        => config('IC.HDFC_ERGO.V1.CV.HDFC_ERGO.PAYER_TYPE'),
            'PAYMENT_AMOUNT'    => $proposal->final_payable_amount,
            'INSTRUMENT_NUMBER' => $transaction_ref_number,
            'PAYMENT_DATE'      => date('d/m/Y'),
        ];

        $get_response = getWsData(
            config('IC.HDFC_ERGO.V1.CV.SUBMIT_PAYMENT'),
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
                'transaction_id'   => $prop_array['TransactionID'],
                'headers' => [
                    'Content-type' => 'application/json',
                    'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                    'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                    'PRODUCT_CODE' => $product_code,
                    'TransactionID' => $prop_array['TransactionID'],
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
            config('IC.HDFC_ERGO.V1.CV.POLICY_DOCUMENT_DOWNLOAD'),
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
                    'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                    'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
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

    public static function tokenGeneration($productData, $product_code, $user_product_journey_id, $proposal)
    {
        $get_response = getWsData(
            config('IC.HDFC_ERGO.V1.CV.AUTHENTICATE_URL'),
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
                    'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                    'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                    'PRODUCT_CODE' => $product_code,
                    'TransactionID' => $proposal->proposal_no,
                    'Accept' => 'application/json',
                    'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CV.CREDENTIAL')
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

        $proposalNumbers = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
            ->pluck('proposal_no')
            ->unique()
            ->toArray();

        if (empty($paymentStatusService) || !($paymentStatusService['status'] ?? false)) {
            foreach ($proposalNumbers as $proposalNo) {
                $paymentStatusService = FinsallController::paymentStatus($proposal, $proposalNo);
                if (($paymentStatusService['status'] ?? false) || ($paymentStatusService['statusCode'] ?? '') === 'FA_200') {
                    $proposal->proposal_no = $proposalNo;
                    break;
                }
            }
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