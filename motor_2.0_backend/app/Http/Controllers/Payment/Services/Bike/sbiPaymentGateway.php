<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
use Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use DateTime;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\ThirdPartyPaymentReqResponse;
use Illuminate\Support\Facades\Http;
include_once app_path() . '/Helpers/CkycHelpers/SbiCkycHelper.php';
class sbiPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    /* public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $enquiryId = customDecrypt($request->enquiryId);
        $product_data = getProductDataByIc($request['policyId']);

        $ic_id = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('quote_id')
                ->first();
        $checksumreturn=sbiPaymentGateway::create_checksum($enquiryId ,$request);
        $msg = $checksumreturn['msg'];
        $return_data = [
            'form_action' => config('constants.IcConstants.sbi.PAYMENT_GATEWAY_LINK_SBI'),
            'form_method' => 'POST',
            'payment_type' => 0,
            'form_data' => [
                'msg' => $msg
            ]
        ];
        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
              ->where('user_proposal_id', $enquiryId)
              ->update(['active' => 0]);

        PaymentRequestResponse::insert([
            'quote_id'                  => $quote_log_id,
            'ic_id' => $proposal->ic_id,
            'user_product_journey_id'   => $request['userProductJourneyId'],
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id' => $proposal->user_proposal_id,
            'payment_url'               => config('constants.IcConstants.sbi.PAYMENT_GATEWAY_LINK_SBI'),
            'proposal_no'               => $proposal->proposal_no,
            'return_url'                =>  route('bike.payment-confirm', ['sbi', 'user_proposal_id' => $proposal->user_proposal_id]),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);

        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['ic_id'] = $proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);
        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    } */

    public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $enquiryId = customDecrypt($request->enquiryId);
        $product_data = getProductDataByIc($request['policyId']);

        $ic_id = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('quote_id')
                ->first();

        // $api = new Api('rzp_test_HxRHmUmojTTNs4', 'JEkpsKDaDPZ9RNoBpomvm2ib');
        $api = new Api(config('constants.IcConstants.sbi.SBI_ROZER_PAY_KEY_ID'), config('constants.IcConstants.sbi.SBI_ROZER_PAY_SECREAT_KEY'));

        $orderRequest = [
            'amount' => $proposal->final_payable_amount * 100, // multiply by hundred because Razorpay takes amount in indian paisa
            'payment_capture' => 1,
            'currency' => 'INR',
            // "receipt" => $request['userProductJourneyId'], //enquiry id for reciept
            "receipt" => $proposal->unique_proposal_id, //enquiry id for reciept
            'notes' => [
                "AgreementCode"     => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID'),
                "QuotationNumber"   => $proposal->unique_proposal_id, //Quotation Number
                "PartnerName"       => strtoupper(config('constants.motorConstant.SMS_FOLDER')),
                "ProductName"       => $product_data->product_name
            ]
        ];

        try {
            $order = $api->order->create($orderRequest)->toArray();
        } catch(\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => "An issue occured while initializing the RazorPay Payment Gateway : " . $e->getMessage(),
                'dev' => __CLASS__ . ' - ' . __LINE__
            ]);
        }

        ThirdPartyPaymentReqResponse::insert(
            [
                'enquiry_id' => $enquiryId,
                'request' => json_encode($orderRequest),
                'response' => json_encode($order)
            ]);

        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
              ->update(['active' => 0]);

        PaymentRequestResponse::insert([
            'quote_id'                  => $quote_log_id,
            'ic_id'                     => $proposal->ic_id,
            'user_product_journey_id'   => $request['userProductJourneyId'],
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'order_id'                  => $order['id'],
            'payment_url'               => config('constants.IcConstants.sbi.PAYMENT_GATEWAY_LINK_SBI'),
            'proposal_no'               => $proposal->proposal_no,
            'return_url'                => route('bike.payment-confirm', ['sbi', 'enquiry_id' => $enquiryId]),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'amount'                    => $proposal->final_payable_amount
        ]);

        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['ic_id'] = $proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        $return_data = [
            'paymentGateway' => 'razorpay',
            'clientKey' => config('constants.IcConstants.sbi.SBI_ROZER_PAY_KEY_ID'),
            'orderId' => $order['id'],
            "amount" => $order['amount_due'],
            'returnUrl' =>  route('bike.payment-confirm', ['sbi'])
        ];


        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);

    }
    public static function create_checksum($enquiryId ,$request) {
      $proposal = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();
       $base_url = route('bike.payment-confirm', ['sbi', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]);
       $quote_data = getQuotation($enquiryId);
       $randomstring =substr(str_shuffle(str_repeat('0123456789', ceil(8 / strlen('0123456789')))), 0, 8);
       $custid = $randomstring;
       $amount = $proposal->final_payable_amount;
       $quoteid = $proposal->proposal_no;
       $custName = trim($proposal->first_name);
       $returnUrl =route('bike.payment-confirm', ['sbi', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]);
    //    if (ENVIRONMENT == 'development') {
       $req = "HMACUAT|$custid|NA|$amount|NA|NA|NA|INR|NA|R|hmacuat|NA|NA|F|$custid|$quoteid|$custName|PM2W001|NA|NA|NA|$returnUrl";
       $checkSumKey = 'uIZ2iayX70hc';
       
    //    $newDataWithChecksumKey = $req . "|" . $checkSumKey;
    //    $checksum = crc32($newDataWithChecksumKey);
      // if (ENVIRONMENT == 'development') {
           $newDataWithChecksumKey = $req;
           $checksum = hash_hmac('sha256', $newDataWithChecksumKey, $checkSumKey, false);
     //  }
     $checksum = strtoupper($checksum);
     $dataWithCheckSumValue = $req . "|" . $checksum;
       return [
           'msg' => $dataWithCheckSumValue,
       ];
   }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    /* public static function confirm($request)
    {
        $request_data = $request->all();
        $link = explode('|', ($request_data['msg']));
        $AuthStatus = $link['14'];
        $proposal = UserProposal::where('user_proposal_id', $request_data['user_proposal_id'])->first();
        $enquiryId = $proposal->user_product_journey_id;
        $productData = getProductDataByIc($request_data['policy_id']);
    if($AuthStatus == '0300')
    {
        if ($request_data['hidRequestId'] != 'NULL') {
            
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
            
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('active',1)
            ->update([
                'response' => $request->All(),
                'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'status' => 'SUCCESS'
                    ]
                );
                
                $date = new DateTime();
                $input = 1;
                $PaymentReferNo = date_format($date, "ymd") . sprintf('%04u', $input);
                $proposal_array = [
                    'RequestHeader' =>
                    [
                    'requestID' => mt_rand(100000, 999999),
                    'action' => 'getIssurance',
                    'channel' => 'SBIG',
                    'transactionTimestamp' => date('d-M-Y-H:i:s'),
                ],
                'RequestBody' =>
                [
                    'QuotationNo' => $proposal->proposal_no,
                    'Amount' => (int)$proposal->final_payable_amount,
                    'CurrencyId' => 1,
                    'PayMode' => 212,
                    'FeeType' => 11,
                    'Payer' => 'Customer',
                    'TransactionDate' => date('Y-m-d') . 'T23:59:59',
                    'PaymentReferNo' => $link[2],
                    'InstrumentNumber' => $link[2],
                    'InstrumentDate' => date('Y-m-d'),
                    'BankCode' => '2',
                    'BankName' => 'STATE BANK OF INDIA',
                    'BankBranchName' => 'KHED-SHIVAPUR',
                    'BankBranchCode' => '7000003582',
                    //'PayableCity' => 'PayableCity',
                    //'PayInSlipNo' => 'PayInSlipNo',
                    // 'PayInSlipDate' => getDateTime('date', 'Y-m-d'),
                    'LocationType' => '2',
                    'RemitBankAccount' => '30',
                    'ReceiptCreatedBy' => '',
                    'PickupDate' => date('Y-m-d'),
                    'CreditCardType' => '',
                    'CreditCardName' => '',
                    'IFSCCode' => '',
                    'MICRNumber' => '',
                    'PANNumber' => '',
                    'ReceiptBranch' => '',
                    'ReceiptTransactionDate' => date('Y-m-d'),
                    'ReceiptDate' => date('Y-m-d'),
                    'EscalationDepartment' => '',
                    'Comment' => '',
                    'AccountNumber' => '',
                    'AccountName' => '',
                ],
            ];
            $token = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
                'enquiryId' => $enquiryId,
                'requestMethod' =>'get',
                'productName'  => $productData->product_name,
                'company'  => 'sbi',
                'section' => $productData->product_sub_type_code,
                'method' =>'Get Token',
                'transaction_type' => 'token'
            ]);
            
            $token_data = json_decode($token, true);
            $data = getWsData(
                config('constants.IcConstants.sbi.END_POINT_URL_SBI_POLICY_ISSURANCE'), $proposal_array, 'sbi', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'authorization' => $token_data['access_token'],
                    'productName'  => $productData->product_name,
                    'company'  => 'sbi',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Policy Issurance',
                    'transaction_type' => 'policy genration'
                ]
            );
            $proposal_resp_array = json_decode($data, true);
            if(isset($proposal_resp_array['ValidateResult']['message']) && ($proposal_resp_array['ValidateResult']['message'] !=''))
            {
                $msg_string = [];
                $msg_string1 = [];
                $msg_string1 = explode("'",$proposal_resp_array['ValidateResult']['message']);
                $msg_string = explode(" ",$msg_string1[0]);

                $policy_no = $msg_string[7];
                $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'policy_no' => $policy_no,
                                    ]);
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                }
                else
                {
                $policy_no =$proposal_resp_array['PolicyNo'];
                    $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'policy_no' => $policy_no,
                                    ]);
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                }
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_number' => $policy_no,
                        'status' => 'SUCCESS'
                    ]
                );
                $pdftoken = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'get',
                    'productName'  => $productData->product_name,
                    'company'  => 'sbi',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Get Token',
                    'transaction_type' => 'Generate PDF TOKEN'
                ]);
                $accessToken = json_decode($pdftoken, true);
                $url = config('constants.IcConstants.sbi.POLICY_DWLD_LINK_SBI'). '?policyNumber=' . $policy_no . '';
                sleep(30);
                $data = getWsData(
                    $url, '', 'sbi', [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'get',
                        'authorization' => $accessToken['access_token'],
                        'productName'  => $productData->product_name,
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'PDF Service',
                        'transaction_type' => 'Generate PDF'
                    ]
                );
                $pdf_response = json_decode($data, true);
                if ($pdf_response) {
                    if (isset($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0]) && ($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0] != 'No Results found for the given Criteria')) {
                        if(isset($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0]) && ($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0] != 'Please specify the search criteria value.')){
                        $data_pdf = $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                        $policypdf = $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];
                       
                    Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));  
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_no,
                            'ic_pdf_url' => $policypdf,
                            'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf',
                            'status' => 'SUCCESS'
                        ]
                    );
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);
                    return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));   
                    }
                    return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));   
                }
                return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));   
            }
            return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));   
   
        }
    }
    DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active',1)
                ->update(
                    [
                        'status' => STAGE_NAMES['PAYMENT_FAILED']
                    ]
                );
        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['PAYMENT_FAILED']
        ]);
        return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
    } */
    public static function confirm($request)
    {

        $order_id = $request->razorpay_order_id;
        $razorpay_payment_id = $request->razorpay_payment_id;
        $key_secret = config('constants.IcConstants.sbi.SBI_ROZER_PAY_SECREAT_KEY');
        $key_id = config('constants.IcConstants.sbi.SBI_ROZER_PAY_KEY_ID');
        $enquiry_id = customDecrypt($request->enquiryId);
        $api = new Api($key_id, $key_secret);
        try {
            $razorpay_signature = $request->razorpay_signature;
            $attributes  = array('razorpay_signature'  => $razorpay_signature,  'razorpay_payment_id'  => $razorpay_payment_id ,  'razorpay_order_id' => $order_id);
             $order  = $api->utility->verifyPaymentSignature($attributes);  

            // payment-success
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'redirectUrl' => config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => $request->enquiryId]),
                    'message' => $e->getMessage(),
                    'line_no' => $e->getLine(),
                    'file' => pathinfo($e->getFile())['basename']
                ]);
            }
            $transaction_info = $api->payment->fetch($razorpay_payment_id)->toArray();
            ThirdPartyPaymentReqResponse::insert(
                [
                    'enquiry_id' => $enquiry_id,
                    'request' => $razorpay_payment_id,
                    'response' => json_encode($transaction_info)
                ]);

            $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
            // $enquiryId = $proposal->user_product_journey_id;

            if ($transaction_info) {
                if($transaction_info['status'] == 'authorized')
                {
                    try{
                        $payment_status=$api->payment->fetch($razorpay_payment_id)->capture(array('amount'=>$transaction_info['amount'],'currency' => 'INR'));
                        $transaction_info = $api->payment->fetch($razorpay_payment_id)->toArray();
                        ThirdPartyPaymentReqResponse::insert(
                            [
                                'enquiry_id' => $enquiry_id,
                                'request' => $razorpay_payment_id,
                                'response' => json_encode($transaction_info)
                            ]);
                    }catch(Exception $e)
                    {
                        if($e->getMessage() != 'This payment has already been captured')
                        {
                            return response()->json([
                                'status' => false
                            ]);
                        }
                    }
                }
                $master_policy_id = QuoteLog::where('user_product_journey_id', $enquiry_id)
                ->first();
                $productData = getProductDataByIc($master_policy_id->master_policy_id);
                if ($transaction_info['error_code'] == null && $transaction_info['status'] == 'captured' && $transaction_info['captured'] == true) {

                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);

                    DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('active',1)
                    ->update([
                        'response' => $request->All(),
                        'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);
                    // No need to create a row unless we receive policy number
                    // PolicyDetails::updateOrCreate(
                    //     ['proposal_id' => $proposal->user_proposal_id],
                    //     [
                    //         'status' => 'SUCCESS'
                    //         ]
                    //     );
                        $additional_details = json_decode($proposal->additional_details, true);
                        $CKYCUniqueId = $additional_details['CKYCUniqueId'] ?? '';
                        $date = new DateTime();
                        $input = 1;
                        $PaymentReferNo = date_format($date, "ymd") . sprintf('%04u', $input);
                        $proposal_array = [
                            'RequestHeader' =>
                            [
                            'requestID' => mt_rand(100000, 999999),
                            'action' => 'getIssurance',
                            'channel' => 'SBIG',
                            'transactionTimestamp' => date('d-M-Y-H:i:s'),
                        ],
                        'RequestBody' =>
                        [
                            'QuotationNo' => $proposal->proposal_no,
                            'Amount' => (int)$proposal->final_payable_amount,
                            'CurrencyId' => 1,
                            'PayMode' => 212,
                            'FeeType' => 11,
                            'Payer' => 'Customer',
                            'TransactionDate' => date('Y-m-d'),
                            'PaymentReferNo' => $transaction_info['id'],//$link[2], // transaction id
                            'InstrumentNumber' => $transaction_info['id'], //$link[2], // transaction id
                            'InstrumentDate' => date('Y-m-d'),
                            'BankCode' => '', //'2',
                            'BankName' => '', // 'STATE BANK OF INDIA',
                            'BankBranchName' => '', // 'KHED-SHIVAPUR',
                            'BankBranchCode' => '', // '7000003582',
                            //'PayableCity' => 'PayableCity',
                            //'PayInSlipNo' => 'PayInSlipNo',
                            // 'PayInSlipDate' => getDateTime('date', 'Y-m-d'),
                            'LocationType' => '2',
                            'RemitBankAccount' => '30',
                            'ReceiptCreatedBy' => '',
                            'PickupDate' => date('Y-m-d'),
                            'CreditCardType' => '',
                            'CreditCardName' => '',
                            'IFSCCode' => '',
                            'MICRNumber' => '',
                            'PANNumber' => '',
                            'ReceiptBranch' => '',
                            'ReceiptTransactionDate' => date('Y-m-d'),
                            'ReceiptDate' => date('Y-m-d'),
                            'EscalationDepartment' => '',
                            'Comment' => '',
                            'AccountNumber' => '',
                            'AccountName' => '',
                            //NEW TAGS
                            "CKYCVerified"=> $proposal->is_ckyc_verified,
                            "KYCCKYCNo"=> $proposal->is_ckyc_verified == 'Y' ? $proposal->ckyc_number: '',
                            "CKYCUniqueId"=> $CKYCUniqueId, //($proposal->is_ckyc_verified == "Y" ? $proposal->ckyc_reference_id : $CKYCUniqueId), //CKYCUniqueID to be passed according to git #30366
                            "SourceType"=> config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y' ? config('constants.IcConstants.sbi.SBI_CAR_SOURCE_TYPE') : '9',
                            //NEW TAGS FOR REGULATORY CHANGES
                            "Prop_AccountNo" => $additional_details['owner']['accountNumber'] ?? '',
                            "Prop_IFSCCode"  => $additional_details['owner']['ifsc'] ?? '',
                            "Prop_BankName"  => $additional_details['owner']['bankName'] ?? '',
                            "Prop_BankBranch"=> $additional_details['owner']['branchName'] ?? ''
                        ],
                    ];

                    $token = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), '', 'sbi', [
                        'requestMethod' => 'get',
                        'method' => 'Token Generation',
                        'section' => $productData->product_sub_type_code,
                        'enquiryId' => $enquiry_id,
                        'productName'   => $productData->product_name,
                        'transaction_type' => 'proposal',
                    ]);

                    $token_data = json_decode($token['response'], true);

                    $data = getWsData(config('constants.IcConstants.sbi.END_POINT_URL_SBI_POLICY_ISSURANCE'), $proposal_array, 'sbi', [
                            'enquiryId' => $enquiry_id,
                            'requestMethod' =>'post',
                            'authorization' => $token_data['access_token'],
                            'productName'  => $productData->product_name,
                            'company'  => 'sbi',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Policy Issurance',
                            'transaction_type' => 'proposal',
                        ]
                    );
                    $proposal_resp_array = json_decode($data['response'], true);
                    if(isset($proposal_resp_array['ValidateResult']['message']) && ($proposal_resp_array['ValidateResult']['message'] !=''))
                    {
                        $msg_string = $msg_string1 = [];
                        $msg_string1 = explode("'",$proposal_resp_array['ValidateResult']['message']);
                        $msg_string = explode(" ",$msg_string1[0]);
                        $policy_no = $msg_string[7];
                    } else {
                        $policy_no =$proposal_resp_array['PolicyNo'];
                    }
                    UserProposal::where('user_product_journey_id', $enquiry_id)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_no' => $policy_no,
                        ]);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_no,
                            'status' => 'SUCCESS'
                        ]
                    );
                    // try {
                    //     if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y' && $proposal->proposer_ckyc_details?->is_document_upload  == 'Y') {
                    //         ckycVerifications($proposal);
                    //     }
                    // } catch (Exception $e) {
                    //     \Illuminate\Support\Facades\Log::error('SBI KYC EXCEPTION trace_id='.customEncrypt($enquiry_id), array($e));
                    // }

                        $pdftoken = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_PDF_GET_TOKEN'), '', 'sbi', [
                            'requestMethod' => 'get',
                            'method' => 'Generate PDF TOKEN',
                            'section' => $productData->product_sub_type_code,
                            'enquiryId' => customDecrypt($request->enquiryId),
                            'productName'   => $productData->product_name,
                            'transaction_type' => 'proposal',
                        ]);

                        $accessToken = json_decode($pdftoken['response'], true);
                        // New PDF Generation Service
                        if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1') == 'Y')
                        {
                            $policyPdfRequest = [
                                "RequestHeader" => [
                                    "requestID" => mt_rand(100000, 999999),
                                    "action" => "getPDF",
                                    "channel" => "SBIG",
                                    "transactionTimestamp" => date('d-M-Y-H:i:s')
                                ],
                                "RequestBody" => [
                                    "PolicyNumber" => $policy_no,
                                    "ProductName" => $productData->product_name,
                                    "Regeneration" => "N",
                                    "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                                    "IntermediateCode" => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID'),
                                    "Offline" => "Y"
                                ]
                            ];
                            
                            $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$policyPdfRequest,'sbi',[
                                'enquiryId' => customDecrypt($request->enquiryId),
                                    'requestMethod' =>'post',
                                    'authorization' => (isset($accessToken['access_token'])) ? $accessToken['access_token']:$accessToken['accessToken'],
                                    'productName'  => $productData->product_name,
                                    'company'  => 'sbi',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'PDF Service',
                                    'transaction_type' => 'proposal',
                            ]);
                            #Log::info('pdf response => ' . $data);
                            $pdf_response = json_decode($data['response'], true);
                            if (isset($pdf_response['DocBase64'])) {
                                if(isset($pdf_response['Description']) && ($pdf_response['Description'] == 'Success')){
                                    $data_pdf = $pdf_response['DocBase64']; //$pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                                    $policypdf = false; // $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];
    
                                    if (strpos(base64_decode($data_pdf,true), '%PDF') !== 0) 
                                    {
                                        $policypdf = false;
                                        
                                    }else
                                    {
                                    $policypdf = true;
                                    Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                                    }
                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $proposal->user_proposal_id],
                                        [
                                            'policy_number' =>$policy_no,
                                            'ic_pdf_url' => $policypdf ? $policypdf : null,
                                            'pdf_url' => $policypdf ? config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
                                            'status' => 'SUCCESS'
                                        ]
                                    );
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
    
                                    return [
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'),
                                    ];
    
                                // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                                }
    
                                return [
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'),
                                    ];
    
                                // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                            }

                        }else{
                            $policyPdfRequest = [
                                "RequestHeader" => [
                                    "requestID" => mt_rand(100000, 999999),
                                    "action" => "getPDF",
                                    "channel" => "SBIG",
                                    "transactionTimestamp" => date('d-M-Y-H:i:s')
                                ],
                                "RequestBody" => [
                                    "PolicyNumber" => $policy_no,
                                    "ProductName" => $productData->product_name,
                                    "Regeneration" => "N",
                                    "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                                    "IntermediateCode" => '',
                                    "Offline" => "Y",
                                    "AgreementCode" => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID')
                                ]
                            ];
                            $encrypt_req = [
                                'data' => json_encode($policyPdfRequest),
                                'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                                'action' => 'encrypt'
                                ];
                               
                                // $encrpt_resp = HTTP::withoutVerifying()->accept('Content-type: application/x-www-form-urlencoded')->asForm()->acceptJson()->post(config('MOTOR_ENCRYPTION_DECRYPTION_URL'),$encrypt_req)->body();
                                $encrpt_resp = httpRequest('sbi_encrypt', $encrypt_req, [],[],[],true, true)['response'];
                            
                                $encrpt_pdf_req['ciphertext'] = trim($encrpt_resp);
                            $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$encrpt_pdf_req,'sbi',[
                                'enquiryId' => customDecrypt($request->enquiryId),
                                    'requestMethod' =>'post',
                                    'authorization' => (isset($accessToken['access_token'])) ? $accessToken['access_token']:$accessToken['accessToken'],
                                    'productName'  => $productData->product_name,
                                    'company'  => 'sbi',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'PDF Service',
                                    'transaction_type' => 'proposal',
                            ]);
                            #Log::info('pdf response => ' . $data);
                            $pdf_response = json_decode($data['response'], true);
                            if (isset($pdf_response['ciphertext'])) {
                                $decrypt_req = [
                                    'data' => $pdf_response['ciphertext'],
                                    'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                                    'action' => 'decrypt',
                                    'file'  => 'true'
                                    ];
                                
                                    // $decrpt_resp = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('MOTOR_ENCRYPTION_DECRYPTION_URL'),$decrypt_req)->body();
                                    $decrpt_resp = httpRequest('sbi_encrypt', $decrypt_req, [],[],[],true, true)['response'];
                                    
                            // $pdf_data = json_decode(trim($decrpt_resp), true);
                                $pdf_data = $decrpt_resp;
                                if(isset($pdf_data['StatusCode']) && $pdf_data['StatusCode'] == '0'){
                                
                                    $data_pdf = $pdf_data['DocBase64']; //$pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                                    $policypdf = false; // $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];
    
                                    if (strpos(base64_decode($data_pdf,true), '%PDF') !== 0) 
                                    {
                                        $policypdf = false;
                                        
                                    }else
                                    {
                                        $policypdf = true;
                                        Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));

                                    }
                    
                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $proposal->user_proposal_id],
                                        [
                                            'policy_number' =>$policy_no,
                                            'ic_pdf_url' => ($policypdf == true ) ? $policypdf : null,
                                            'pdf_url' => ($policypdf == true ) ? config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
                                            'status' => 'SUCCESS'
                                        ]
                                    );
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
    
                                    return [
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'),
                                    ];
    
                                // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                                }
    
                                return [
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'),
                                    ];
    
                                // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                            }
                        }
                        
                        return [
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'),
                                    ];

                        // return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                ]);

                return [
                    'status' => false,
                    'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','FAILURE'),
                ];

                // return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }else{
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                ]);

                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('active',1)
                ->update([
                    'response' => $request->All(),
                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                ]);

                return [
                    'status' => false,
                    'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','FAILURE'),
                ];
                

                // return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }


       

    }
    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $payment_status = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id
        ])
        ->where('ic_id', 34)->get();
        if (empty($payment_status)) 
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Payment Details Not Found'
            ]);
        }
        else
        {
            $api = new Api(config('constants.IcConstants.sbi.SBI_ROZER_PAY_KEY_ID'), config('constants.IcConstants.sbi.SBI_ROZER_PAY_SECREAT_KEY'));
            $break_loop = false;
            foreach ($payment_status as $key => $value) {
                if (!empty($value->order_id)) {
                    //Payment Check status API
                    $response = $api->order->fetch($value->order_id)->payments()->toArray();
                    foreach ($response['items'] as $k => $v) {
                        //echo json_encode($v);
                        if (isset($v['status']) && $v['status'] == 'captured' && $v['captured'] == true) {
                            PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->update([
                                'active' => 0
                            ]);
                            $updatePaymentResponse = [
                                'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'active' => 1,
                                'response' => json_encode($v),
                            ];

                            PaymentRequestResponse::where('id', $value->id)->update($updatePaymentResponse);
                            $break_loop = true;
                            updateJourneyStage([
                                'user_product_journey_id' => $user_product_journey_id,
                                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
                            ]);
                            break;
                        }
                    }
                }
                if ($break_loop == true) {
                    break;
                }
            }
            if(!$break_loop) {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Payment is Pending'
                ]);
            }
        }
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            ->where(array('prr.active'=>1,'prr.status'=>STAGE_NAMES['PAYMENT_SUCCESS']))
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id','prr.response', 'prr.created_at'
            )
            ->first();
            if(empty($policy_details))
            {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'Data Not Found'
                ];
                return response()->json($pdf_response_data);
            }
            $enquiryId =$user_product_journey_id;
            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
            $policyid= QuoteLog::where('user_product_journey_id',$enquiryId)->pluck('master_policy_id')->first();
            $productData = getProductDataByIc($policyid);
            //$transaction=ThirdPartyPaymentReqResponse::where('enquiry_id', $user_product_journey_id)->first();
            //echo "ddddddddddddd";
//            print_r($transaction);
//            die;
            //$policy_details->policy_number = '';
            if($policy_details->policy_number == '')
            {
                    $transaction_date = empty($policy_details->created_at) ? date('Y-m-d') : date('Y-m-d', strtotime($policy_details->created_at));
                    //$transaction_info=json_decode($transaction->response);
                    $transaction_info = json_decode($policy_details->response);
                    if(isset($transaction_info->id)  && empty($transaction_info->id))
                    {
                        $pdf_response_data = [
                            'status' => false,
                            'msg'    => 'Third Party Payment Data Not Found'
                        ];
                        return response()->json($pdf_response_data);
                    }
                    $date = new DateTime();
                    $input = 1;
                    $PaymentReferNo = date_format($date, "ymd") . sprintf('%04u', $input);
                    $additional_details = json_decode($proposal->additional_details, true);
                    $CKYCUniqueId = $additional_details['CKYCUniqueId'] ?? '';
                    $proposal_array = [
                        'RequestHeader' =>
                        [
                        'requestID' => mt_rand(100000, 999999),
                        'action' => 'getIssurance',
                        'channel' => 'SBIG',
                        'transactionTimestamp' => date('d-M-Y-H:i:s'),
                    ],
                    'RequestBody' =>
                    [
                        'QuotationNo' => $proposal->proposal_no,
                        'Amount' => (int)$proposal->final_payable_amount,
                        'CurrencyId' => 1,
                        'PayMode' => 212,
                        'FeeType' => 11,
                        'Payer' => 'Customer',
                        'TransactionDate' => $transaction_date, //date('Y-m-d') . 'T23:59:59',
                        'PaymentReferNo' => $transaction_info->id,//$link[2], // transaction id
                        'InstrumentNumber' => $transaction_info->id, //$link[2], // transaction id
                        'InstrumentDate' => $transaction_date, //date('Y-m-d'),
                        'BankCode' => '', //'2',
                        'BankName' => '', // 'STATE BANK OF INDIA',
                        'BankBranchName' => '', // 'KHED-SHIVAPUR',
                        'BankBranchCode' => '', // '7000003582',
                        'LocationType' => '2',
                        'RemitBankAccount' => '30',
                        'ReceiptCreatedBy' => '',
                        'PickupDate' => $transaction_date, //date('Y-m-d'),
                        'CreditCardType' => '',
                        'CreditCardName' => '',
                        'IFSCCode' => '',
                        'MICRNumber' => '',
                        'PANNumber' => '',
                        'ReceiptBranch' => '',
                        'ReceiptTransactionDate' => $transaction_date, //date('Y-m-d'),
                        'ReceiptDate' => $transaction_date, //date('Y-m-d'),
                        'EscalationDepartment' => '',
                        'Comment' => '',
                        'AccountNumber' => '',
                        'AccountName' => '',
                        //NEW TAGS
                        "CKYCVerified"=> $proposal->is_ckyc_verified,
                        "KYCCKYCNo"=> $proposal->is_ckyc_verified == 'Y' ? $proposal->ckyc_number: '',
                        "CKYCUniqueId"=> $CKYCUniqueId, //($proposal->is_ckyc_verified == "Y" ? $proposal->ckyc_reference_id : $CKYCUniqueId), //CKYCUniqueID to be passed according to git #30366
                        "SourceType"=> config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y' ? config('constants.IcConstants.sbi.SBI_CAR_SOURCE_TYPE') : '9',
                        //NEW TAGS FOR REGULATORY CHANGES
                        "Prop_AccountNo" => $additional_details['owner']['accountNumber'] ?? '',
                        "Prop_IFSCCode"  => $additional_details['owner']['ifsc'] ?? '',
                        "Prop_BankName"  => $additional_details['owner']['bankName'] ?? '',
                        "Prop_BankBranch"=> $additional_details['owner']['branchName'] ?? ''
                    ],
                ];
                $token = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), '', 'sbi', [
                    'requestMethod' => 'get',
                    'method' => 'Token Generation',
                    'section' => $productData->product_sub_type_code,
                    'enquiryId' => $enquiryId,
                    'productName'   => $productData->product_name,
                    'transaction_type' => 'proposal',
                ]);

                $token_data = json_decode($token['response'], true);

                $data = getWsData(config('constants.IcConstants.sbi.END_POINT_URL_SBI_POLICY_ISSURANCE'), $proposal_array, 'sbi', [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'authorization' => $token_data['access_token'],
                        'productName'  => $productData->product_name,
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Policy Issurance',
                        'transaction_type' => 'proposal',
                    ]
                );
                $proposal_resp_array = json_decode($data['response'], true);
                if(isset($proposal_resp_array['ValidateResult']['message']) && ($proposal_resp_array['ValidateResult']['message'] !=''))
                {
                    $msg_string = $msg_string1 = [];
                    $msg_string1 = explode("'",$proposal_resp_array['ValidateResult']['message']);
                    $msg_string = explode(" ",$msg_string1[0]);
                    $policy_no = $msg_string[7];
                } else {
                    $policy_no =$proposal_resp_array['PolicyNo'];
                }
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_no' => $policy_no,
                ]);

                $pdftoken = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_PDF_GET_TOKEN'), '', 'sbi', [
                    'requestMethod' => 'get',
                    'method' => 'Generate PDF TOKEN',
                    'section' => $productData->product_sub_type_code,
                    'enquiryId' => customDecrypt($request->enquiryId),
                    'productName'   => $productData->product_name,
                    'transaction_type' => 'proposal',
                ]);

                $accessToken = json_decode($pdftoken['response'], true);
                // New PDF Generation Service

                if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1') == 'Y')
                {

                    $policyPdfRequest = [
                        "RequestHeader" => [
                            "requestID" => mt_rand(100000, 999999),
                            "action" => "getPDF",
                            "channel" => "SBIG",
                            "transactionTimestamp" => date('d-M-Y-H:i:s')
                        ],
                        "RequestBody" => [
                            "PolicyNumber" => $policy_no,
                            "ProductName" => $productData->product_name,
                            "Regeneration" => "N",
                            "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                            "IntermediateCode" => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID'),
                            "Offline" => "Y"
                        ]
                    ];
                    $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$policyPdfRequest,'sbi',[
                        'enquiryId' => customDecrypt($request->enquiryId),
                            'requestMethod' =>'post',
                            'authorization' => (isset($accessToken['access_token'])) ? $accessToken['access_token']:$accessToken['accessToken'],
                            'productName'  => $productData->product_name,
                            'company'  => 'sbi',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'PDF Service',
                            'transaction_type' => 'proposal',
                    ]);
    
                   # Log::info('pdf response => ' . $data);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_no,
                            'status' => 'SUCCESS'
                        ]
                    );
                    $pdf_response = json_decode($data['response'], true);
                    if (isset($pdf_response['DocBase64'])) {
                        if(isset($pdf_response['Description']) && ($pdf_response['Description'] == 'Success')){
                            $data_pdf = $pdf_response['DocBase64'];
                            #$data_pdf = $pdf_response['DocBase64']; //$pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                            $policypdf = false; // $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];
                            if (strpos(base64_decode($data_pdf,true), '%PDF') !== 0) 
                            {
                                $policypdf = false;
                                
                            }else
                            {
                            $policypdf = true;
                            Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                            }
                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' =>$policy_no,
                                    'ic_pdf_url' => $policypdf ? $policypdf : null,
                                    'pdf_url' => $policypdf ? config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
                                    'status' => 'SUCCESS'
                                ]
                            );
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            $pdf_response_data = [
                                'status' => true,
                                'msg' => 'Pdf Generated successfully',
                                'data' => [
                                    'policy_number' => $policy_no,
                                    'pdf_link'      => $policypdf ? file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/' . md5($proposal->user_proposal_id). '.pdf') : null
                                ]
                            ];
    
                        }else{
                            $pdf_response_data = [
                                'status' => false,
                                'msg' => 'Issue In Pdf Service',
                            ];
                        }
    
                        
                    }else{
                        $pdf_response_data = [
                            'status' => false,
                            'msg' => 'Issue In Pdf Service',
                        ];
                    }
                
                }else{
                    $policyPdfRequest = [
                        "RequestHeader" => [
                            "requestID" => mt_rand(100000, 999999),
                            "action" => "getPDF",
                            "channel" => "SBIG",
                            "transactionTimestamp" => date('d-M-Y-H:i:s')
                        ],
                        "RequestBody" => [
                            "PolicyNumber" => $policy_no,
                            "ProductName" => $productData->product_name,
                            "Regeneration" => "N",
                            "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                            "IntermediateCode" => '',
                            "Offline" => "Y",
                            "AgreementCode" => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID')
                        ]
                    ];
                    $encrypt_req = [
                        'data' => json_encode($policyPdfRequest),
                        'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                        'action' => 'encrypt'
                        ];
                       
                        // $encrpt_resp = HTTP::withoutVerifying()->accept('Content-type: application/x-www-form-urlencoded')->asForm()->acceptJson()->post(config('MOTOR_ENCRYPTION_DECRYPTION_URL'),$encrypt_req)->body();
                        $encrpt_resp = httpRequest('sbi_encrypt', $encrypt_req, [],[],[],true, true)['response'];
                    
                        $encrpt_pdf_req['ciphertext'] = trim($encrpt_resp);
                    $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$encrpt_pdf_req,'sbi',[
                        'enquiryId' => customDecrypt($request->enquiryId),
                            'requestMethod' =>'post',
                            'authorization' => (isset($accessToken['access_token'])) ? $accessToken['access_token']:$accessToken['accessToken'],
                            'productName'  => $productData->product_name,
                            'company'  => 'sbi',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'PDF Service',
                            'transaction_type' => 'proposal',
                    ]);
    
                   # Log::info('pdf response => ' . $data);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_no,
                            'status' => 'SUCCESS'
                        ]
                    );
                    $pdf_response = json_decode($data['response'], true);
                    if (isset($pdf_response['ciphertext'])) {
                        $decrypt_req = [
                            'data' => $pdf_response['ciphertext'],
                            'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                            'action' => 'decrypt',
                            'file'  => 'true'
                            ];
                           
                            // $decrpt_resp = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('MOTOR_ENCRYPTION_DECRYPTION_URL'),$decrypt_req)->body();
                            $decrpt_resp = httpRequest('sbi_encrypt', $decrypt_req, [],[],[],true, true)['response'];
                      // $pdf_data = json_decode(trim($decrpt_resp), true);
                            $pdf_data = $decrpt_resp;
                        if(isset($pdf_data['StatusCode']) && $pdf_data['StatusCode'] == '0'){
                           
                            $data_pdf = $pdf_data['DocBase64'];
                            #$data_pdf = $pdf_response['DocBase64']; //$pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                            $policypdf = false; // $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];
    
                            if (strpos(base64_decode($data_pdf), '%PDF') !== 0) 
                            {
                                $policypdf = false;
                            }
                            else
                            {
                                $policypdf = true;
                                Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                            }

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' =>$policy_no,
                                    'ic_pdf_url' => ($policypdf == true) ? $policypdf : null,
                                    'pdf_url' => ($policypdf == true) ? config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
                                    'status' => 'SUCCESS'
                                ]
                            );
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => ($policypdf == true) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            $pdf_response_data = [
                                'status' => true,
                                'msg' => 'Pdf Generated successfully',
                                'data' => [
                                    'policy_number' => $policy_no,
                                    'pdf_link'      => ($policypdf == true) ? file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/' . md5($proposal->user_proposal_id). '.pdf') : ''
                                ]
                            ];
    
                        }else{
                            $pdf_response_data = [
                                'status' => false,
                                'msg' => 'Issue In Pdf Service',
                            ];
                        }
    
                        
                    }else{
                        $pdf_response_data = [
                            'status' => false,
                            'msg' => 'Issue In Pdf Service',
                        ];
                    }
                }
        }
        else if($policy_details->pdf_url == '')
        {
            $pdftoken = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_PDF_GET_TOKEN'), '', 'sbi', [
                'requestMethod' => 'get',
                'method' => 'Generate PDF TOKEN',
                'section' => $productData->product_sub_type_code,
                'enquiryId' => customDecrypt($request->enquiryId),
                'productName'   => $productData->product_name,
                'transaction_type' => 'proposal',
            ]);
            $accessToken = json_decode($pdftoken['response'], true);
            // New PDF Generation Service
            if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1') == 'Y')
            {
                $policyPdfRequest = [
                    "RequestHeader" => [
                        "requestID" => mt_rand(100000, 999999),
                        "action" => "getPDF",
                        "channel" => "SBIG",
                        "transactionTimestamp" => date('d-M-Y-H:i:s')
                    ],
                    "RequestBody" => [
                        "PolicyNumber" => $policy_details->policy_number,
                        "ProductName" => $productData->product_name,
                        "Regeneration" => "N",
                        "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                        "IntermediateCode" => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID'),
                        "Offline" => "Y"
                    ]
                ];
                $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$policyPdfRequest,'sbi',[
                    'enquiryId' => customDecrypt($request->enquiryId),
                        'requestMethod' =>'post',
                        'authorization' => (isset($accessToken['access_token'])) ? $accessToken['access_token']:$accessToken['accessToken'],
                        'productName'  => $productData->product_name,
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'PDF Service',
                        'transaction_type' => 'proposal',
                ]);
                
                #Log::info('pdf response => ' . $data);
                $pdf_response = json_decode($data['response'], true);
                if (isset($pdf_response['DocBase64'])) {
                    if(isset($pdf_response['Description']) && ($pdf_response['Description'] == 'Success')){
                        $data_pdf = $pdf_response['DocBase64'];
                        $policypdf = false;
                            
                        if (strpos(base64_decode($data_pdf,true), '%PDF') !== 0) 
                        {
                            $policypdf = false;
                            
                        }else
                        {
                            $policypdf = true;
                        Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf', base64_decode($data_pdf));
                        }
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $policy_details->user_proposal_id],
                            [
                                'policy_number' => $policy_details->policy_number,
                                'ic_pdf_url' => $policypdf ? $policypdf : null,
                                'pdf_url' =>  $policypdf ? config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf' : null,
                                'status' => 'SUCCESS'
                            ]
                        );
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' =>  ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        $pdf_response_data = [
                            'status' => true,
                            'msg' => 'sucess',
                            'data' => [
                                'policy_number' => $policy_details->policy_number,
                                'pdf_link'      => $policypdf ? file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/' . md5($policy_details->user_proposal_id). '.pdf') : null
                            ]
                        ];
                    }else
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                    $pdf_response_data = [
                        'status' => false,
                        'msg' => 'Issue in pdf service',
                    ];
                    }
                }else
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'Issue in pdf service',
                ];
                }
            }
            else{
                $policyPdfRequest = [
                    "RequestHeader" => [
                        "requestID" => mt_rand(100000, 999999),
                        "action" => "getPDF",
                        "channel" => "SBIG",
                        "transactionTimestamp" => date('d-M-Y-H:i:s')
                    ],
                    "RequestBody" => [
                        "PolicyNumber" => $policy_details->policy_number,
                        "ProductName" => $productData->product_name,
                        "Regeneration" => "N",
                        "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                        "IntermediateCode" => '',
                        "Offline" => "Y",
                        "AgreementCode" => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID')
                    ]
                ];
                $encrypt_req = [
                    'data' => json_encode($policyPdfRequest),
                    'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                    'action' => 'encrypt'
                    ];
                   
                    // $encrpt_resp = HTTP::withoutVerifying()->accept('Content-type: application/x-www-form-urlencoded')->asForm()->acceptJson()->post(config('MOTOR_ENCRYPTION_DECRYPTION_URL'),$encrypt_req)->body();
                    $encrpt_resp = httpRequest('sbi_encrypt', $encrypt_req, [],[],[],true, true)['response'];
    
                    $encrpt_pdf_req['ciphertext'] = trim($encrpt_resp);
                    $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$encrpt_pdf_req,'sbi',[
                    'enquiryId' => customDecrypt($request->enquiryId),
                        'requestMethod' =>'post',
                        'authorization' => (isset($accessToken['access_token'])) ? $accessToken['access_token']:$accessToken['accessToken'],
                        'productName'  => $productData->product_name,
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'PDF Service',
                        'transaction_type' => 'proposal',
                ]);
                #Log::info('pdf response => ' . $data);
                $pdf_response = json_decode($data['response'], true);
                    if (isset($pdf_response['ciphertext'])) {
                        $decrypt_req = [
                            'data' => $pdf_response['ciphertext'],
                            'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                            'action' => 'decrypt',
                            'file'  => 'true'
                            ];
                           
                            // $decrpt_resp = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('MOTOR_ENCRYPTION_DECRYPTION_URL'),$decrypt_req)->body();
                            $decrpt_resp = httpRequest('sbi_encrypt', $decrypt_req, [],[],[],true, true)['response'];
                      // $pdf_data = json_decode(trim($decrpt_resp), true);
                        $pdf_data = $decrpt_resp;
                        if(isset($pdf_data['StatusCode']) && $pdf_data['StatusCode'] == '0'){
                           
                            $data_pdf = $pdf_data['DocBase64'];
                            $policypdf = false;
                            
                            if (strpos(base64_decode($data_pdf,true), '%PDF') !== 0) 
                            {
                                $policypdf = false;
                            }else
                            {
                                $policypdf = true;
                                Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf', base64_decode($data_pdf));
                            }

                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $policy_details->user_proposal_id],
                            [
                                'policy_number' => $policy_details->policy_number,
                                'ic_pdf_url' => ($policypdf == true) ? $policypdf : null,
                                'pdf_url' => ($policypdf == true) ? config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf' : null,
                                'status' => 'SUCCESS'
                            ]
                        );
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => ($policypdf == true) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] 
                        ]);
                        $pdf_response_data = [
                            'status' => true,
                            'msg' => 'sucess',
                            'data' => [
                                'policy_number' => $policy_details->policy_number,
                                'pdf_link'      => ($policypdf == true) ? file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'sbi/' . md5($policy_details->user_proposal_id). '.pdf') : ''
                            ]
                        ];
                    }else
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                    $pdf_response_data = [
                        'status' => false,
                        'msg' => 'Issue in pdf service',
                    ];
                    }
                }else
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'Issue in pdf service',
                ];
                }
            }
            
        }else
        {
        $pdf_response_data = [
                'status' => true,
                'msg' => 'success',
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'=> file_url($policy_details->pdf_url),
                ]
                
            ];
        }
        return response()->json($pdf_response_data);
    }
}