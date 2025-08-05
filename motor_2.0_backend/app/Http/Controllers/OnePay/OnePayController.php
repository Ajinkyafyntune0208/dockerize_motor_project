<?php

namespace App\Http\Controllers\OnePay;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\PolicyDetails;
use MongoDB\Operation\Update;
use App\Models\PaymentResponse;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\OnePayTransactionLog;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Payment\Services\orientalPaymentGateway;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

class OnePayController extends Controller
{
    public static function payRouter($proposal, $request)
    {
        $companyAlias = $proposal->quote_log->premium_json['company_alias'];
        $commanconfig = self::commanconfig($companyAlias);
        $merchantId = $commanconfig['merchantId'];
        $apiKey = $commanconfig['apiKey'];
        $encryption_key = $commanconfig['encryptionkey'];
        $transaction_id = $request->enquiryId.time();
        $enquiryId = $proposal->user_product_journey_id;
        $quote_log_id = $proposal->quote_log->quote_id;
        $icId = $proposal->quote_log->master_policy->insurance_company_id;
        
        # Build OnePay request Payload
        $apiRequest = [
            'merchantId' => $merchantId,
            'apiKey' => $apiKey,
            'txnId' => $transaction_id,
            'amount' => number_format(( config('constants.IcConstants.onepay.ONEPAY_DUMMY_AMOUNT') ?? $proposal->final_payable_amount), 2, ".", "" ),
            'dateTime' => date('Y-m-d h:i:s'),
            'custMobile' => $proposal->mobile_number,
            'custMail' => $proposal->email,
            'udf1' => $request->enquiryId,
            'udf2' => $proposal->user_proposal_id,
            'udf3' => 'NA',
            'udf4' => 'NA',
            'udf5' => 'NA',
            'udf6' => 'NA',
            'returnURL' => route(
                'cv.payment-confirm',
                [
                    $companyAlias,
                    'user_proposal_id'      => $proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]
            ),
            'productId' => 'DEFAULT',
            'channelId' => 0,
            'isMultiSettlement' => 0,
            'txnType' => 'DIRECT',
            'instrumentId' => 'NA',
            'cardDetails' => 'NA',
            'cardType' => 'NA',
            'ResellerTxnId' => 'NA',
            'Rid' => 'NA',
            'type' => '1.1'
        ];
        $jsonRequest = json_encode($apiRequest);
        $encryptedRequest = self::get_encrypt($jsonRequest, $encryption_key);
        $url = config('constants.IcConstants.onepay.cv.END_POINT_URL_PAYMENT_GATEWAY_CV');
        $return_data = [
            'form_action'       => $url,
            'form_method'       => 'POST',
            'payment_type'      => 0,
            'form_data'         => [
                'merchantId' => $merchantId,
                'reqData' => $encryptedRequest
            ]
        ];
        $json_data = json_encode($return_data);
        $journeyStageData['user_product_journey_id']    = $proposal->user_product_journey_id;
        $journeyStageData['ic_id']                      = $proposal->ic_id;
        $journeyStageData['stage']                      = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($journeyStageData);

        PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('user_proposal_id', $proposal->user_proposal_id)
            ->update([
                'active' => 0
            ]);

        PaymentRequestResponse::create([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $transaction_id,
            'amount'                    => $proposal->final_payable_amount,
            'xml_data'                  => $json_data,
            'payment_url'               => $url,
            'return_url'                => route(
                'cv.payment-confirm',
                [
                    $companyAlias,
                    'user_proposal_id'      => $proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]
            ),
            'lead_source'               => 'ONEPAY',
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);

        return response()->json([
            'status'    => true,
            'msg'       => "Payment Reidrectional",
            'data'      => $return_data,
        ]);
    }

    public static function confirm($request)
    {
        $commanconfig = self::commanconfig($request->ic_name);
        $merchantId = $request->merchantId;
        $respData = $request->respData;
        $encryption_key = $commanconfig['encryptionkey'];
        $respDecrypt = self::get_decrypt($respData, $encryption_key);
        $jsonResponse = json_decode($respDecrypt);
        $txnid = $jsonResponse->txn_id;
        $merchantid = $jsonResponse->merchant_id;
        $pgRefId = $jsonResponse->pg_ref_id;
        $respdatetime = $jsonResponse->resp_date_time;
        $txndateTime = $jsonResponse->txn_date_time;
        $txnstatus = $jsonResponse->trans_status;
        $txnamount = $jsonResponse->txn_amount;
        $message = $jsonResponse->resp_message;
        $resp_code = $jsonResponse->resp_code;
        $email = $jsonResponse->cust_email_id;
        $phnumber = $jsonResponse->cust_mobile_no;
        $banktxnId = $jsonResponse->bank_ref_id;
        $payment_mode = $jsonResponse->payment_mode;
        $dateTime = $request['datetime'];

        $paymentResponse =  PaymentRequestResponse::where('order_id', $txnid)
        ->first();
        $user_proposal = UserProposal::find($paymentResponse->user_proposal_id);
        
        if(empty($paymentResponse))
        {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
        
        $user_proposal_id = $paymentResponse->user_proposal_id;
        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);

        PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('user_proposal_id', $user_proposal->user_proposal_id)
        ->where('active', 1)
        ->update([
            'response'      => $request->All(),
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        if ( strtolower( $txnstatus ) == "ok" )
        {
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->update([
                'status' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]); 
            sleep(rand(0, 5));
            return  orientalPaymentGateway::OnepayConfirm($request,$jsonResponse,$user_proposal); exit;
        }
        else
        {
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_FAILED']
                ]); 
            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE')); exit;
        }
    }

    public static function get_encrypt($input, $encryption_key)
    {
        $iv = substr($encryption_key,0,16);        
        return openssl_encrypt($input, "aes-256-cbc", $encryption_key, 0, $iv); 
    }
        
    public static  function get_decrypt($respData, $encryption_key)
    {
        $iv = substr($encryption_key,0,16); 
        return openssl_decrypt($respData, "aes-256-cbc", $encryption_key, 0, $iv);
    }
    public static function paymentStatusCheck(Request $request)
    {
        if (!empty($request->enquiryId)) {
            try {
                $enquiryId = acceptBothEncryptDecryptTraceId($request->enquiryId);
                $paymentResponse =  PaymentRequestResponse::where('user_product_journey_id', ltrim($enquiryId,'0'))
                    ->where(['status' => STAGE_NAMES['PAYMENT_SUCCESS']])
                    ->first();
            } catch (Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'please enter valid enquiry Id'
                ]);
            }
        } elseif (!empty($request->orderId)) {
            $order_Id = $request->orderId;
            $paymentResponse =  PaymentRequestResponse::where('order_id', $order_Id)
                ->where(['status' => STAGE_NAMES['PAYMENT_SUCCESS']])
                ->first();

             if (empty($paymentResponse)) {
                return response()->json([
                    'status' => false,
                    'message' => 'please enter valid order Id'
                ]);
            }

            $enquiryId = $paymentResponse->user_product_journey_id;
        } else {
            return response()->json([
                'status' => false,
                'message' => 'please enter valid enquiry Id/order Id'
            ]);
        }

        if (!empty($paymentResponse)) {
            $enquiryId  = $paymentResponse->user_product_journey_id;
            $paymentArray = [];
            $statuscheckapi = self::paymentStatusService($request, $paymentResponse, $paymentResponse->user_product_journey_id);
            $paymentArray[] = $statuscheckapi;
            if ($statuscheckapi['status']) {
                $request->enquiryId = customEncrypt($enquiryId);
                orientalPaymentGateway::generatePdf($request); // $paymentArray);
                return response()->json([
                    'status'    => true,
                    'msg'       => STAGE_NAMES['PAYMENT_SUCCESS'],
                    'data' => $paymentArray
                ]);
                // return $paymentArray;
            }
            // $paymentArray);
            $response = [
                'status'    => true,
                'msg'       => STAGE_NAMES['PAYMENT_SUCCESS'],
                'order_id' => $paymentResponse->order_id,
                'data' => $paymentArray
            ];

            return response()->json([
                'status'    => true,
                'msg'       => STAGE_NAMES['PAYMENT_SUCCESS'],
                'data' => [$response]
            ]);
        }
        $paymentResponse =  PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
            // ->where(['status' => STAGE_NAMES['PAYMENT_INITIATED']])
            ->get();

        if (!empty($paymentResponse)) {
            $paymentArray = [];
            foreach ($paymentResponse as $value) {
                $statuscheckapi = self::paymentStatusService($request, $value, $enquiryId);
                $paymentArray[] = $statuscheckapi;
                if ($statuscheckapi['status']) {
                    // dump();
                    orientalPaymentGateway::generatePdf($request); // $paymentArray);
                    return response()->json([
                        'status'    => true,
                        'msg'       => "Success",
                        'data' => $paymentArray
                    ]);
                    // return $paymentArray;
                }
            }
            return response()->json([
                'status'    => false,
                'msg'       => empty($paymentArray) ? "No records found against given Id" : "Invalid Payment response",
                'data' => $paymentArray
            ]);
        } else {
            return response()->json([
                'status'    => false,
                'msg'       => "Invalid Payment response"
            ]);
        }
    }

    public static function paymentStatusService($request, $paymentRequestResponseEntry, $enquiryId)
    {
        $premiumJson = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('premium_json')->first();
        $companyAlias = $premiumJson['company_alias'] ?? '';
        $commanconfig = self::commanconfig($companyAlias);
        $merchantId = $commanconfig['encryptionkey'];
        $headers = lanninsportCode($request);

        $apiRequest = [
            'merchantId' => $merchantId,
            'txnId' => $paymentRequestResponseEntry->order_id
        ];

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                "lanninsport"=>$headers
                        ],
            'requestMethod' => 'post',
            'section' => 'cv',
            'method' => 'Payment status check',
            'product' => 'cv',
            'transaction_type' => 'proposal',
        ];

        $url = config('constants.IcConstants.onepay.cv.END_POINT_URL_PAYMENT_CHECK_CV') . '?' . http_build_query($apiRequest);

        $get_response = getWsData($url, $apiRequest, 'onepay', $additional_data);

        if (empty($get_response['response'])) {
            $response = [
                'status'    => false,
                'msg'       => "Invalid Payment response received from onepay"
            ];
        } else {

            $serviceResponse = json_decode($get_response['response']);
            if (empty($serviceResponse)) {
                $response = [
                    'status'    => false,
                    'msg'       => "Invalid Payment response received from onepay",
                ];
            } else {
                if (!isset($serviceResponse->trans_status)) {
                    $response = [
                        'status'    => false,
                        'msg'       => $serviceResponse->resp_message ?? "Invalid Payment response received from onepay"
                    ];
                } else {
                    $paymentlog['url'] = $url;
                    $paymentlog['enquiryId'] = customEncrypt($enquiryId);
                    $paymentlog['orderId'] = $paymentRequestResponseEntry->order_id;
                    $paymentlog['response']  = $get_response['response'];
                    $paymentlog['request']   = json_encode($apiRequest);
                    if ($serviceResponse->trans_status == 'Ok') {
                        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                            ->where('order_id', $paymentRequestResponseEntry->order_id)
                            ->update([
                                'status'                 => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'response'               => json_encode($paymentlog)
                            ]);
                        $paymentRequestResponseEntry = PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                            ->where('order_id', $paymentRequestResponseEntry->order_id)
                            ->first();

                        $response = [
                            'status'    => true,
                            'msg'       => $serviceResponse->resp_message ?? STAGE_NAMES['PAYMENT_SUCCESS']
                        ];
                    } elseif ($serviceResponse->trans_status == 'F') {
                        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                            ->where('order_id', $paymentRequestResponseEntry->order_id)
                            ->update([
                                'status'                 => STAGE_NAMES['PAYMENT_FAILED'],
                                'response'               => json_encode($paymentlog)

                            ]);
                        $paymentRequestResponseEntry = PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                            ->where('order_id', $paymentRequestResponseEntry->order_id)
                            ->first();

                        $response = [
                            'status'    => false,
                            'msg'       => $serviceResponse->resp_message ?? "Invalid Payment response received from onepay"
                        ];
                    } else {
                        $response = [
                            'status'    => false,
                            'msg'       => $serviceResponse->resp_message ?? "Invalid Payment response received from onepay"
                        ];
                    }
                }
            }
        }


        $response['data'] = $paymentlog;
        return $response;
    }

    public static function rehitAll(Request $request)
    {
        $reports = \App\Models\UserProductJourney::with([
            'journey_stage',
        ]);
        $paymentRequest = PaymentRequestResponse::join('cv_journey_stages', 'cv_journey_stages.user_product_journey_id', 'payment_request_response.user_product_journey_id')
        ->whereIn('cv_journey_stages.stage', [ STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_FAILED']])
        ->where('payment_request_response.lead_source', 'ONEPAY')
        ->groupBY('payment_request_response.user_product_journey_id')
        ->get();

        

        $result = [];
        foreach ($paymentRequest as $key => $report) {
            $enquiryId = customEncrypt($report->user_product_journey_id);
            // dd($enquiryId);
            $data = [
                'enquiryId' => $enquiryId,
            ];
            try {
                $result[$key] = self::paymentStatusCheck(new Request($data))->getOriginalContent();
            } catch (\Throwable $th) {
                Log::info('Cron Rehit failed for Trace ID : ' . $report->journey_id . ' ' . $th);
                $result[$key] = [
                    'Cron Rehit failed for Trace ID : ' . $report->journey_id . ' ' . $th
                ];
            }

            return response()->json($result);
        }
    }
    public static function kmdtransactionstatus(Request $request){

        if ( empty($request->header()['lanninsport'][0]) ||( !empty($request->header()['lanninsport'][0]) && $request->header()['lanninsport'][0] != lanninsportCode($request) ) )
        { 
        
            $response['data'] = [
                'status'    => false,
                'msg'       => "Unauthorized request, please check the request format."
            ];
            return $response; exit;
        }
        else{
           $response =  self::paymentStatusCheck($request);
           return $response; exit;
           
        }

    }
    public static function commanconfig($companyAlias)
    {
        $configType = getCommonConfig('paymentGateway.onepay.configType', null);

        if (!empty($configType)) {
            if ($configType == 'global') {

                //global config
                $merchantIdKeyName = 'paymentGateway.onepay.merchantId';
                $apiKeyName = 'paymentGateway.onepay.apiKey';
                $encryptionKeyName = 'paymentGateway.onepay.encryptionKey';
            } else {

                //ic wise config
                $merchantIdKeyName = 'paymentGateway.onepay.' . $companyAlias . 'merchantId';
                $apiKeyName = 'paymentGateway.onepay.' . $companyAlias . 'apiKey';
                $encryptionKeyName = 'paymentGateway.onepay.' . $companyAlias . 'encryptionKey';
            }

            $merchantId = getCommonConfig($merchantIdKeyName, null);
            $apiKey = getCommonConfig($apiKeyName, null);
            $encryption_key = getCommonConfig($encryptionKeyName, null);
        } else {
            $merchantId = config('constants.IcConstants.onepay.ONEPAY_PAYMENT_MERCHANT_ID');
            $apiKey = config("constants.IcConstants.onepay.ONEPAY_ENCRYPT_DECRYPT");
            $encryption_key =  config('constants.IcConstants.onepay.ONEPAY_ENCRYPT_DECRYPT');
        }

        return [
            'merchantId' => $merchantId,
            'apiKey' => $apiKey,
            'encryptionkey' => $encryption_key,
        ];
    }
}
