<?php

namespace App\Http\Controllers\Payment\Services;
use Config;
use Illuminate\Support\Facades\Storage;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ixudra\Curl\Facades\Curl;
use Razorpay\Api\Api;
use App\Models\ThirdPartyPaymentReqResponse;
use Illuminate\Support\Facades\Http;
include_once app_path() . '/Helpers/CkycHelpers/SbiCkycHelper.php';

include_once app_path().'/Helpers/CvWebServiceHelper.php';


class sbiConstantClass
{
    const TOKEN_URL = 'https://devapi.sbigeneral.in/cld/v1/token';
    const X_IBM_Client_Id = 'f7ddba7b-f392-4b0b-869e-5019dd98c620';
    const X_IBM_Client_Secret = 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF';
    const END_POINT_URL_CV_QUICK_QUOTE = 'https://devapi.sbigeneral.in/cld/v1/quickquote/CMVPC01';
    const END_POINT_URL_CV_FULL_QUOTE = 'https://devapi.sbigeneral.in/cld/v1/fullquote/CMVPC01';
}

class SbiPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
   /*  public static function make($request)
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
            'return_url'                => route('car.payment-confirm', ['sbi', 'enquiry_id' => $enquiryId]),
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
                "AgreementCode"     => config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE'),
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
                'enquiry_id' => $request['userProductJourneyId'],
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
            'return_url'                => route('cv.payment-confirm', ['sbi', 'enquiry_id' => $enquiryId]),
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
            'returnUrl' =>  route('cv.payment-confirm', ['sbi'])
        ];


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
    /* public static function confirm($request)
    {
        echo "<pre>";
        print_r($request->all());
        exit();


        $request_data = $request->all();
        $link = explode('|', ($request_data['msg']));

        $proposal = UserProposal::where('user_proposal_id', $request_data['user_proposal_id'])->first();
        $enquiryId = $proposal->user_product_journey_id;

        if (isset($link[14]) && $link[14] == '0300') {
            $productData = getProductDataByIc($request_data['policy_id']);
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

                $token = getWsData('https://devapi.sbigeneral.in/cld/v1/token', '', 'sbi', [
                    'requestMethod' => 'get',
                    'method' => 'Token Generation',
                    'section' => $productData->product_sub_type_code,
                    'enquiryId' => $enquiryId,
                    'productName'   => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'client_id' => 'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                    'client_secret' => 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                ]);

                $token_data = json_decode($token, true);

                $data = getWsData('https://devapi.sbigeneral.in/cld/v1/issurance', $proposal_array, 'sbi', [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'authorization' => $token_data['access_token'],
                        'productName'  => $productData->product_name,
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Policy Issurance',
                        'transaction_type' => 'policy genration',
                        'client_id' => 'f7ddba7b-f392-4b0b-869e-5019dd98c620',
                        'client_secret' => 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF'
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

                    $pdftoken = getWsData('https://devapi.sbigeneral.in/cld/v1/token', '', 'sbi', [
                        'requestMethod' => 'get',
                        'method' => 'Token Generation',
                        'section' => $productData->product_sub_type_code,
                        'enquiryId' => $enquiryId,
                        'productName'   => $productData->product_name,
                        'transaction_type' => 'proposal',
                        'client_id' => 'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                        'client_secret' => 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                    ]);

                    $accessToken = json_decode($pdftoken, true);
                    $url = 'https://devapi.sbigeneral.in/customers/v1/policies/documents?policyNumber=' . $policy_no . '';
                    sleep(10);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_no,
                            'status' => 'SUCCESS'
                        ]
                    );
                    $data = getWsData(
                        $url, '', 'sbi', [
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'get',
                            'authorization' => $accessToken['access_token'],
                            'productName'  => $productData->product_name,
                            'company'  => 'sbi',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Pdf Genration',
                            'transaction_type' => 'proposal',
                            'client_id' => 'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                            'client_secret' => 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                        ]
                    );
                    $pdf_response = json_decode($data, true);
                    if (isset($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0]) && ($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0] != 'No Results found for the given Criteria')) {
                        if(isset($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0]) && ($pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0] != 'Please specify the search criteria value.')){
                            $data_pdf = $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                            $policypdf = $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];

                        Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $proposal->user_proposal_id],
                            [
                                'policy_number' =>$policy_no,
                                'ic_pdf_url' => $policypdf,
                                'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf',
                                'status' => 'SUCCESS'
                            ]
                        );
                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);
                        return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                        }
                        return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                    }
                    return redirect(config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
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

            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
        }

    } */


    public static function confirm($request)
    {

        # constant for SBI
        $TOKEN_URL = config('constants.IcConstants.sbi.CV_TOKEN_URL');
        $X_IBM_Client_Id = config('constants.IcConstants.sbi.CV_X_IBM_Client_Id');
        $X_IBM_Client_Secret = config('constants.IcConstants.sbi.X_IBM_Client_Secret');
        $X_IBM_Client_Id_Pcv = config('constants.IcConstants.sbi.X_IBM_Client_Id_PCV');#pcv 
        $X_IBM_Client_Secret_Pcv = config('constants.IcConstants.sbi.X_IBM_Client_Secret_PCV');#pcv
        $END_POINT_URL_CV_QUICK_QUOTE = config('constants.IcConstants.sbi.END_POINT_URL_CV_QUICK_QUOTE');
        $END_POINT_URL_CV_FULL_QUOTE = config('constants.IcConstants.sbi.END_POINT_URL_CV_FULL_QUOTE');
        $END_POINT_URL_GCV_FULL_QUOTE = config('constants.IcConstants.sbi.END_POINT_URL_GCV_FULL_QUOTE');

        $order_id = $request->razorpay_order_id;
        $razorpay_payment_id = $request->razorpay_payment_id;
        $key_secret = config('constants.IcConstants.sbi.SBI_ROZER_PAY_SECREAT_KEY'); // 'ihcTD8qpM1ruxnS8L1OI8TFq';
        $key_id = config('constants.IcConstants.sbi.SBI_ROZER_PAY_KEY_ID'); //'rzp_test_TNM7NAj5tr8DiY';
        $api = new Api($key_id, $key_secret);
        //


        try {
            $razorpay_signature = $request->razorpay_signature;
            $attributes  = array('razorpay_signature'  => $razorpay_signature,  'razorpay_payment_id'  => $razorpay_payment_id ,  'razorpay_order_id' => $order_id);
            $order  = $api->utility->verifyPaymentSignature($attributes);

            // payment-success
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'redirectUrl' => config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => $request->enquiryId]),
                    'message' => $e->getMessage(),
                    'line_no' => $e->getLine(),
                    'file' => pathinfo($e->getFile())['basename']
                ]);
            }
            $transaction_info = $api->payment->fetch($razorpay_payment_id)->toArray();

            // $card_details = $api->card->fetch($transaction_info['card_id']);

            ThirdPartyPaymentReqResponse::insert(
                [
                    'enquiry_id' => customDecrypt($request->enquiryId),
                    'request' => $razorpay_payment_id,
                    'response' => json_encode($transaction_info)
                ]);

            $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();

            // $enquiryId = $proposal->user_product_journey_id;

            if ($transaction_info) {

                if($transaction_info['status'] == 'authorized')
                {
                    try{
                        $payment_status=$api->payment->fetch($razorpay_payment_id)->capture(array('amount'=>$transaction_info['amount'],'currency' => 'INR'));
                        $transaction_info = $api->payment->fetch($razorpay_payment_id)->toArray();
                        ThirdPartyPaymentReqResponse::insert(
                            [
                                'enquiry_id' => customDecrypt($request->enquiryId),
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

                $master_policy_id = QuoteLog::where('user_product_journey_id', customDecrypt($request->enquiryId))
                ->first();
                $productData = getProductDataByIc($master_policy_id->master_policy_id);
                $master_product_sub_type_code = MasterPolicy::find($productData->policy_id)->product_sub_type_code->product_sub_type_code;

                $is_misc = false;
                if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' ||$master_product_sub_type_code == 'TRUCK' ||$master_product_sub_type_code == 'TRACTOR' ||$master_product_sub_type_code == 'TANKER/BULKER') {
                    $type = 'GCV';
                }elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
                    $type = 'PCV';
                }else {
                    $is_misc = true;
                    $type = 'GCV';
                }
                if ($transaction_info['error_code'] == null && $transaction_info['status'] == 'captured' && $transaction_info['captured'] == true) {

                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
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
                        $additional_details = json_decode($proposal->additional_details, true);
                        $CKYCUniqueId = $additional_details['CKYCUniqueId'] ?? NULL;
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
                            'TransactionDate' => $type == 'GCV' ? date('Y-m-d') : date('Y-m-d') . 'T23:59:59',
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
                            "CKYCUniqueId"=> $CKYCUniqueId, //($proposal->is_ckyc_verified == "Y" ? $CKYCUniqueId : $CKYCUniqueId), //CKYCUniqueID to be passed according to git #30366
                            "SourceType"=> config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y' ? config('constants.IcConstants.sbi.SBI_CAR_SOURCE_TYPE') : '9',
                            //NEW TAGS FOR REGULATORY CHANGES
                            "Prop_AccountNo" => $additional_details['owner']['accountNumber'] ?? '',
                            "Prop_IFSCCode"  => $additional_details['owner']['ifsc'] ?? '',
                            "Prop_BankName"  => $additional_details['owner']['bankName'] ?? '',
                            "Prop_BankBranch"=> $additional_details['owner']['branchName'] ?? ''
                        ],
                    ];

                    $token = getWsData($TOKEN_URL, '', 'sbi', [
                        'requestMethod' => 'get',
                        'method' => 'Token Generation',
                        'section' => $productData->product_sub_type_code,
                        'enquiryId' => customDecrypt($request->enquiryId),
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                        'transaction_type' => 'proposal',
                        'client_id' => $type == 'PCV' ? $X_IBM_Client_Id_Pcv :$X_IBM_Client_Id, //'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                        'client_secret' => $type == 'PCV' ? $X_IBM_Client_Secret_Pcv :$X_IBM_Client_Secret, //'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                        'headers' => [
                            'client_id' => $type == 'PCV' ? $X_IBM_Client_Id_Pcv :$X_IBM_Client_Id, // 'f7ddba7b-f392-4b0b-869e-5019dd98c620',
                            'client_secret' => $type == 'PCV' ? $X_IBM_Client_Secret_Pcv :$X_IBM_Client_Secret,
                        ]
                    ]);

                    $token_data = json_decode($token['response'], true);

                    $data = getWsData(config('constants.IcConstants.sbi.END_POINT_URL_CV_ISSUE_QUOTE'), $proposal_array, 'sbi', [
                            'enquiryId' => customDecrypt($request->enquiryId),
                            'requestMethod' =>'post',
                            'authorization' => $token_data['access_token'],
                            'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                            'company'  => 'sbi',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Policy Issurance',
                            'transaction_type' => 'proposal',
                            'client_id' => $type == 'PCV' ? $X_IBM_Client_Id_Pcv :$X_IBM_Client_Id, // 'f7ddba7b-f392-4b0b-869e-5019dd98c620',
                            'client_secret' => $type == 'PCV' ? $X_IBM_Client_Secret_Pcv :$X_IBM_Client_Secret, // 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF'
                            'headers' => [
                                'authorization' => $token_data['access_token'],
                                'client_id' => $type == 'PCV' ? $X_IBM_Client_Id_Pcv :$X_IBM_Client_Id, // 'f7ddba7b-f392-4b0b-869e-5019dd98c620',
                                'client_secret' => $type == 'PCV' ? $X_IBM_Client_Secret_Pcv :$X_IBM_Client_Secret,
                            ]
                        ]
                    );
                    $proposal_resp_array = json_decode($data['response'], true);
                    if(isset($proposal_resp_array['ValidateResult']['message']) && ($proposal_resp_array['ValidateResult']['message'] !=''))
                    {
                        if(!isset($proposal_resp_array['PolicyNo'])){
                            updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
                                    ]);
                            return response()->json([
                                'status' => true,
                                'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                            ]);
                        }
                        //Handling Policy number not being generated in response above.
                        // $msg_string = [];
                        // $msg_string1 = [];
                        // $msg_string1 = explode("'",$proposal_resp_array['ValidateResult']['message']);
                        // $msg_string = explode(" ",$msg_string1[0]);

                        // $policy_no = $msg_string[7];
                        // $updateProposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))
                        //                     ->where('user_proposal_id', $proposal->user_proposal_id)
                        //                     ->update([
                        //                         'policy_no' => $policy_no,
                        //                     ]);
                        //     updateJourneyStage([
                        //         'user_product_journey_id' => $proposal->user_product_journey_id,
                        //         'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        //     ]);
                        }
                        else
                        {
                        $policy_no =$proposal_resp_array['PolicyNo'];
                            $updateProposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))
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
                            try {
                                if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y' && $proposal->proposer_ckyc_details?->is_document_upload  == 'Y') {
                                    ckycVerifications($proposal);
                                }
                            } catch (Exception $e) {
                                \Illuminate\Support\Facades\Log::error('SBI KYC EXCEPTION trace_id='.customEncrypt($proposal->user_product_journey_id), array($e));
                            }

                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                        }

                        $pdftoken = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_PDF_GET_TOKEN'), '', 'sbi', [
                            'requestMethod' => 'get',
                            'method' => 'Token Generation',
                            'section' => $productData->product_sub_type_code,
                            'enquiryId' => customDecrypt($request->enquiryId),
                            'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                            'transaction_type' => 'proposal',
                            'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID'):config('constants.IcConstants.sbi.CV_PDF_CLIENT_ID'), //'08e9c64bf82247c97639733335cae869',//'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                            'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET'):config('constants.IcConstants.sbi.CV_PDF_CLIENT_SECRET'), // '96b28412afa9d441f981349a0f12539f' //'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                        ]);

                        $accessToken = json_decode($pdftoken['response'], true);
                        // If any of the tags are not present the we'll return the error message.
                        if (!isset($accessToken['accessToken']) && !isset($accessToken['access_token'])) {
                            /* return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS')); */
                            return [
                                'status' => true,
                                'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                            ];
                        }

                        // New PDF Generation Service
                        $product_code = '';
                        if ($type == 'GCV' && $is_misc == true) {
                            $product_code = 'CMVMI01';
                        } elseif ($type == 'PCV') {
                            $product_code = 'CMVPC01';
                        } elseif ($type == 'GCV') {
                            $product_code = 'CMVGC01';
                        }

                        //Encrypted PDF Service
                        if (config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1_ENCRYPTED_CV') == 'Y') {
                            $policyPdfRequest = [
                                "RequestHeader" => [
                                    "requestID" => mt_rand(100000, 999999),
                                    "action" => "getPDF",
                                    "channel" => "SBIG",
                                    "transactionTimestamp" => date('d-M-Y-H:i:s')
                                ],
                                "RequestBody" => [
                                    "PolicyNumber" => $policy_no,
                                    "AgreementCode" => $type == 'GCV' ? config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE') : config('constants.IcConstants.sbi.PCV_AGREEMENT_CODE'),
                                    "ProductName" => $product_code,
                                    "Regeneration" => "Y",
                                    "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                                    "IntermediateCode" => null,
                                    "Offline" => "Y"
                                ]
                            ];
                        
                            $encryptedReq = [
                                'data' => json_encode($policyPdfRequest),
                                'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                                'action' => 'encrypt'
                            ];
                        
                            $encrptedResp = httpRequest('sbi_encrypt', $encryptedReq, [], [], [], true, true)['response'];
                        
                            $encrptedPdfReq['ciphertext'] = trim($encrptedResp);
                        
                            $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI_ENCRYPTED_CV'), $encrptedPdfReq, 'sbi', [
                                'enquiryId' => customDecrypt($request->enquiryId),
                                'requestMethod' => 'post',
                                'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken'] : $accessToken['access_token'],
                                'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                                'company'  => 'sbi',
                                'section' => $productData->product_sub_type_code,
                                'method' => 'Pdf Genration',
                                'transaction_type' => 'proposal',
                                'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID') : config('constants.IcConstants.sbi.CV_PDF_CLIENT_ID'), //'08e9c64bf82247c97639733335cae869', //'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                                'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET') : config('constants.IcConstants.sbi.CV_PDF_CLIENT_SECRET'), // '96b28412afa9d441f981349a0f12539f', //'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                            ]);
                        
                            $pdf_response = json_decode($data['response'], true);

                            if (isset($pdf_response['ciphertext']) && !empty($pdf_response['ciphertext'])) {
                                $decryptedReq = [
                                    'data' => $pdf_response['ciphertext'],
                                    'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                                    'action' => 'decrypt',
                                    'file'  => 'true'
                                ];
                                $decrptedResp = httpRequest('sbi_encrypt', $decryptedReq, [], [], [], true, true)['response'];
    
                                $pdf_data = $decrptedResp;

                                //Creating log for decrypted request and response
                                $startTime = new DateTime(date('Y-m-d H:i:s'));
                                $endTime = new DateTime(date('Y-m-d H:i:s'));
                                $wsLogdata = [
                                    'enquiry_id'        => customDecrypt($request->enquiryId),
                                    'product'           => $productData->product_name,
                                    'section'           => $productData->product_sub_type_code,
                                    'method_name'       => 'Decrypted Request Response for PDF',
                                    'company'           => 'sbi',
                                    'method'            => 'post',
                                    'transaction_type'  => 'proposal',
                                    'request'           => $policyPdfRequest,
                                    'response'          => $pdf_data,
                                    'endpoint_url'      => config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI_ENCRYPTED'),
                                    'ip_address'        => request()->ip(),
                                    'start_time'        => $startTime->format('Y-m-d H:i:s'),
                                    'end_time'          => $endTime->format('Y-m-d H:i:s'),
                                    'response_time'	    => $endTime->getTimestamp() - $startTime->getTimestamp(),
                                    'created_at'        => date('Y-m-d H:i:s'),
                                    'headers'           => NULL
                                ];
                                \App\Models\WebServiceRequestResponse::create($wsLogdata);

                                if (isset($pdf_data['DocBase64']) && !empty($pdf_data['DocBase64'])) {
                                    $pdf_data['DocBase64'] = stripslashes($pdf_data['DocBase64']);

                                    if (isset($pdf_data['Description']) && ($pdf_data['Description'] == 'Success')) {
                                        $data_pdf = $pdf_data['DocBase64']; 
                                        $policypdf = false; 
                                    
                                        if (strpos(base64_decode($data_pdf, true), '%PDF') !== 0) {
                                            $policypdf = false;
                                        } else {
                                            $policypdf = true;
                                            Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/' . md5($proposal->user_proposal_id) . '.pdf', base64_decode($data_pdf));
                                        }
                                        PolicyDetails::updateOrCreate(
                                            ['proposal_id' => $proposal->user_proposal_id],
                                            [
                                                'policy_number' => $policy_no,
                                                'ic_pdf_url' => $policypdf ? $policypdf : null,
                                                'pdf_url' => $policypdf ? config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/' . md5($proposal->user_proposal_id) . '.pdf' : null,
                                                'status' => 'SUCCESS'
                                            ]
                                        );
                                    
                                        updateJourneyStage([
                                            'user_product_journey_id' => $proposal->user_product_journey_id,
                                            'stage' => ($policypdf == true) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                        ]);
                                    
                                        return response()->json([
                                            'status' => true,
                                            'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                                        ]);
                                    }

                                    return response()->json([
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                                    ]);
                                }
                            }
                        }
                        else if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1') == 'Y')
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
                                    "AgreementCode" => $type == 'GCV' ? config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE') : config('constants.IcConstants.sbi.PCV_AGREEMENT_CODE'),
                                    "ProductName" => $product_code,
                                    "Regeneration" => "Y",
                                    "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                                    "IntermediateCode" => null,
                                    "Offline" => "Y"
                                ]
                            ];
                            $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$policyPdfRequest,'sbi',[
                                'enquiryId' => customDecrypt($request->enquiryId),
                                    'requestMethod' =>'post',
                                    'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                                    'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                                    'company'  => 'sbi',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'Pdf Genration',
                                    'transaction_type' => 'proposal',
                                    'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID'):config('constants.IcConstants.sbi.CV_PDF_CLIENT_ID'), //'08e9c64bf82247c97639733335cae869', //'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                                    'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET'):config('constants.IcConstants.sbi.CV_PDF_CLIENT_SECRET'), // '96b28412afa9d441f981349a0f12539f', //'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                            ]);
    
                            // Log::info('pdf response => ' . $data);
    
                            /* $url = 'https://devapi.sbigeneral.in/customers/v1/policies/documents?policyNumber=' . $policy_no . '';
                            sleep(10);
                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' => $policy_no,
                                    'status' => 'SUCCESS'
                                ]
                            );
                            $data = getWsData(
                                $url, '', 'sbi', [
                                    'enquiryId' => customDecrypt($request->enquiryId),
                                    'requestMethod' =>'get',
                                    'authorization' => $accessToken['access_token'],
                                    'productName'  => $productData->product_name,
                                    'company'  => 'sbi',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'Pdf Genration',
                                    'transaction_type' => 'proposal',
                                    'client_id' => 'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                                    'client_secret' => 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                                ]
                            ); */
    
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
                                    Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                                    }
                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $proposal->user_proposal_id],
                                        [
                                            'policy_number' =>$policy_no,
                                            'ic_pdf_url' => $policypdf ? $policypdf : null,
                                            'pdf_url' => $policypdf ? config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
                                            'status' => 'SUCCESS'
                                        ]
                                    );
                                    // try {
                                    //     if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y' && $proposal->proposer_ckyc_details?->is_document_upload  == 'Y') {
                                    //         ckycVerifications($proposal);
                                    //     }
                                    // } catch (Exception $e) {
                                    //     \Illuminate\Support\Facades\Log::error('SBI KYC EXCEPTION trace_id='.customEncrypt($proposal->user_product_journey_id), array($e));
                                    // }
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
    
                                    // return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCES

                                    return response()->json([
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                                    ]);

    
                                // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                                }
    
                                return response()->json([
                                    'status' => true,
                                    'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                                ]);
    
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
                                    "AgreementCode" => config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE')
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
                                    'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                                    'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                                    'company'  => 'sbi',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'Pdf Genration',
                                    'transaction_type' => 'proposal',
                                    'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID'):config('constants.IcConstants.sbi.CV_PDF_CLIENT_ID'), //'08e9c64bf82247c97639733335cae869', //'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                                    'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET'):config('constants.IcConstants.sbi.CV_PDF_CLIENT_SECRET'), // '96b28412afa9d441f981349a0f12539f', //'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                            ]);
    
                            // Log::info('pdf response => ' . $data);
    
                            /* $url = 'https://devapi.sbigeneral.in/customers/v1/policies/documents?policyNumber=' . $policy_no . '';
                            sleep(10);
                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' => $policy_no,
                                    'status' => 'SUCCESS'
                                ]
                            );
                            $data = getWsData(
                                $url, '', 'sbi', [
                                    'enquiryId' => customDecrypt($request->enquiryId),
                                    'requestMethod' =>'get',
                                    'authorization' => $accessToken['access_token'],
                                    'productName'  => $productData->product_name,
                                    'company'  => 'sbi',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'Pdf Genration',
                                    'transaction_type' => 'proposal',
                                    'client_id' => 'f7ddba7b-f392-4b0b-869e-5019dd98c620', //sbiConstantClass::X_IBM_Client_Id,
                                    'client_secret' => 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF' //sbiConstantClass::X_IBM_Client_Secret
                                ]
                            ); */
    
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
                                    Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                                    }
                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $proposal->user_proposal_id],
                                        [
                                            'policy_number' =>$policy_no,
                                            'ic_pdf_url' => $policypdf ? $policypdf : null,
                                            'pdf_url' => $policypdf ? config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
                                            'status' => 'SUCCESS'
                                        ]
                                    );
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
    
                                    /* return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS')); */
                                    return [
                                        'status' => true,
                                        'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                                    ];
    
                                // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                                }
    
                                return [
                                    'status' => true,
                                    'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                                ];
    
                                // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                            }
                        }
                        

                        return [
                            'status' => true,
                            'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'),
                        ];

                        // return redirect(config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                ]);

                /* return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'FAILURE')); */
                return [
                    'status' => false,
                    'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'FAILURE'),
                ];
                // return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
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

                /* return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'FAILURE')); */
                return [
                    'status' => false,
                    'redirectUrl' => paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'FAILURE'),
                ];

                // return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }




    }

    public static function generatePdf($request)
    {
        $api = new Api(config('constants.IcConstants.sbi.SBI_ROZER_PAY_KEY_ID'), config('constants.IcConstants.sbi.SBI_ROZER_PAY_SECREAT_KEY'));
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $payment_status = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id
        ])
        ->where('ic_id', 34)
        ->get();
        if (empty($payment_status)) 
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Payment Details Not Found'
            ]);
        }
        else
        {
            $break_loop = false;
            foreach ($payment_status as $key => $value) 
            {
                if(!empty($value->order_id))
                {
                    //Payment Check status API
                    $response = $api->order->fetch($value->order_id)->payments()->toArray();
                    foreach ($response['items'] as $k => $v) 
                    {
                        //echo json_encode($v);
                        if (isset($v['status']) && $v['status'] == 'captured' && $v['captured'] == true)
                        {
                            PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                                ->update(['active' => 0]);
                            $updatePaymentResponse = [
                                'status'  => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'active'  => 1,
                                'response' => json_encode($v)
                            ];

                            PaymentRequestResponse::where('id', $value->id)
                                            ->update($updatePaymentResponse);
                            $break_loop = true;
                            updateJourneyStage([
                                'user_product_journey_id' => $user_product_journey_id,
                                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
                            ]);
                            break;
                        }
                    }
                }
                if($break_loop == true)
                {
                    break;
                }
            }
        }
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            ->where(array('prr.active'=>1,'prr.status'=>STAGE_NAMES['PAYMENT_SUCCESS']))
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id','prr.response'
            )
            ->first();
            if($policy_details == null)
            {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'Data Not Found'
                ];
                return response()->json($pdf_response_data);
            }
            $enquiryId =$user_product_journey_id;
            $policyid= QuoteLog::where('user_product_journey_id',$enquiryId)->pluck('master_policy_id')->first();
            $productData = getProductDataByIc($policyid);
            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
           // $transaction=ThirdPartyPaymentReqResponse::where('enquiry_id', $user_product_journey_id)->first();
            $transaction_info = json_decode($policy_details->response);

            $master_product_sub_type_code = MasterPolicy::find($productData->policy_id)->product_sub_type_code->product_sub_type_code;

            $is_misc = false;
            if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' ||$master_product_sub_type_code == 'TRUCK' ||$master_product_sub_type_code == 'TRACTOR' ||$master_product_sub_type_code == 'TANKER/BULKER') {
                $type = 'GCV';
            }elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
                $type = 'PCV';
            }else{
                $type = 'GCV';
                $is_misc = true;
            }

            $product_code = '';
            if ($type == 'GCV' && $is_misc == true) {
                $product_code = 'CMVMI01';
            } elseif ($type == 'PCV') {
                $product_code = 'CMVPC01';
            } elseif ($type == 'GCV') {
                $product_code = 'CMVGC01';
            }

        if($policy_details->policy_number == '')
        {
                $date = new DateTime();
                $transaction_date = empty($policy_details->created_at) ? date('Y-m-d') : date('Y-m-d', strtotime($policy_details->created_at));
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
                    'TransactionDate' => $type == 'GCV' ? $transaction_date : $transaction_date . 'T23:59:59',//date('Y-m-d') . 'T23:59:59',
                    'PaymentReferNo' => $transaction_info->id,//$link[2], // transaction id
                    'InstrumentNumber' => $transaction_info->id, //$link[2], // transaction id
                    'InstrumentDate' => $transaction_date,//date('Y-m-d'),
                    'BankCode' => '', //'2',
                    'BankName' => '', // 'STATE BANK OF INDIA',
                    'BankBranchName' => '', // 'KHED-SHIVAPUR',
                    'BankBranchCode' => '', // '7000003582',
                    'LocationType' => '2',
                    'RemitBankAccount' => '30',
                    'ReceiptCreatedBy' => '',
                    'PickupDate' => $transaction_date,//date('Y-m-d'),
                    'CreditCardType' => '',
                    'CreditCardName' => '',
                    'IFSCCode' => '',
                    'MICRNumber' => '',
                    'PANNumber' => '',
                    'ReceiptBranch' => '',
                    'ReceiptTransactionDate' => $transaction_date,//date('Y-m-d'),
                    'ReceiptDate' => $transaction_date,//date('Y-m-d'),
                    'EscalationDepartment' => '',
                    'Comment' => '',
                    'AccountNumber' => '',
                    'AccountName' => '',
                    //NEW TAGS
                    "CKYCVerified"=> $proposal->is_ckyc_verified,
                    "KYCCKYCNo"=> $proposal->is_ckyc_verified == 'Y' ? $proposal->ckyc_number: '',
                    "CKYCUniqueId"=> $CKYCUniqueId, //($proposal->is_ckyc_verified == "Y" ? $proposal->ckyc_reference_id : $CKYCUniqueId),
                    "SourceType"=> config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y' ? config('constants.IcConstants.sbi.SBI_CAR_SOURCE_TYPE') : '9',
                    //NEW TAGS FOR REGULATORY CHANGES
                    "Prop_AccountNo" => $additional_details['owner']['accountNumber'] ?? '',
                    "Prop_IFSCCode"  => $additional_details['owner']['ifsc'] ?? '',
                    "Prop_BankName"  => $additional_details['owner']['bankName'] ?? '',
                    "Prop_BankBranch"=> $additional_details['owner']['branchName'] ?? ''
                ],
            ];

            $token = getWsData(config('constants.IcConstants.sbi.CV_TOKEN_URL'), '', 'sbi', [
                'requestMethod' => 'get',
                'method' => 'Token Generation',
                'section' => $productData->product_sub_type_code,
                'enquiryId' => $enquiryId,
                'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                'transaction_type' => 'proposal',
                'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Id_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_CV'),
                'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Secret_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_CV'),
                'headers' => [
                    'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Id_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_CV'),
                    'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Secret_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_CV'),
                ]
            ]);

            $token_data = json_decode($token['response'], true);

            $data = getWsData(config('constants.IcConstants.sbi.END_POINT_URL_CV_ISSUE_QUOTE'), $proposal_array, 'sbi', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'authorization' => $token_data['access_token'],
                    'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                    'company'  => 'sbi',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Policy Issurance',
                    'transaction_type' => 'proposal',
                    'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Id_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_CV'),
                    'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Secret_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_CV'),
                    'headers' => [
                        'authorization' => $token_data['access_token'],
                        'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Id_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_CV'),
                    'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.X_IBM_Client_Secret_PCV') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_CV'),
                    ]
                ]
            );
            $proposal_resp_array = json_decode($data['response'], true);
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

            $pdftoken = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_PDF_GET_TOKEN'), '', 'sbi', [
                'requestMethod' => 'get',
                'method' => 'Token Generation',
                'section' => $productData->product_sub_type_code,
                'enquiryId' => customDecrypt($request->enquiryId),
                'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                'transaction_type' => 'proposal',
                'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
            ]);

            $accessToken = json_decode($pdftoken['response'], true);
            // If any of the tags are not present the we'll return the error message.
            if (!isset($accessToken['accessToken']) && !isset($accessToken['access_token'])) {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Error occured while generating access token. Please check logs for reference.'
                ]);
            }
            // New PDF Generation Service
            if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1_ENCRYPTED_CV') == 'Y')
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
                        "AgreementCode" => $type == 'GCV' ? config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE') : config('constants.IcConstants.sbi.PCV_AGREEMENT_CODE'),
                        "ProductName" => $product_code,
                        "Regeneration" => "Y",
                        "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                        "IntermediateCode" => null,
                        "Offline" => "Y"
                    ]
                ];
                
                $encryptedReq = [
                    'data' => json_encode($policyPdfRequest),
                    'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                    'action' => 'encrypt'
                ];

                $encrptedResp = httpRequest('sbi_encrypt', $encryptedReq, [], [], [], true, true)['response'];

                $encrptedPdfReq['ciphertext'] = trim($encrptedResp);

                $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI_ENCRYPTED_CV'),$encrptedPdfReq,'sbi',[
                    'enquiryId' => customDecrypt($request->enquiryId),
                        'requestMethod' =>'post',
                        'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Pdf Genration',
                        'transaction_type' => 'proposal',
                        'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                        'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
                ]);
    
                $pdf_response = json_decode($data['response'], true);

                if (isset($pdf_response['ciphertext']) && !empty($pdf_response['ciphertext'])) {
                     $decryptedReq = [
                        'data' => $pdf_response['ciphertext'],
                        'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                        'action' => 'decrypt',
                        'file'  => 'true'
                    ];

                    $decrptedResp = httpRequest('sbi_encrypt', $decryptedReq, [], [], [], true, true)['response'];

                    $pdf_data = $decrptedResp;

                    //Creating log for decrypted request and response
                    $startTime = new DateTime(date('Y-m-d H:i:s'));
                    $endTime = new DateTime(date('Y-m-d H:i:s'));
                    $wsLogdata = [
                        'enquiry_id'        => customDecrypt($request->enquiryId),
                        'product'           => $productData->product_name,
                        'section'           => $productData->product_sub_type_code,
                        'method_name'       => 'Decrypted Request Response for PDF',
                        'company'           => 'sbi',
                        'method'            => 'post',
                        'transaction_type'  => 'proposal',
                        'request'           => $policyPdfRequest,
                        'response'          => $pdf_data,
                        'endpoint_url'      => config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI_ENCRYPTED'),
                        'ip_address'        => request()->ip(),
                        'start_time'        => $startTime->format('Y-m-d H:i:s'),
                        'end_time'          => $endTime->format('Y-m-d H:i:s'),
                        'response_time'	    => $endTime->getTimestamp() - $startTime->getTimestamp(),
                        'created_at'        => date('Y-m-d H:i:s'),
                        'headers'           => NULL
                    ];
                    \App\Models\WebServiceRequestResponse::create($wsLogdata);

                    if (isset($pdf_data['DocBase64']) && !empty($pdf_data['DocBase64'])) {
                        $pdf_data['DocBase64'] = stripslashes($pdf_data['DocBase64']);

                        if (isset($pdf_data['Description']) && ($pdf_data['Description'] == 'Success')) {
                            $data_pdf = $pdf_data['DocBase64'];
                            $policypdf = false;

                            if (strpos(base64_decode($data_pdf, true), '%PDF') !== 0) {
                                $policypdf = false;
                            } else {
                                $policypdf = true;
                                Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($proposal->user_proposal_id) . '.pdf', base64_decode($data_pdf));
                            }
                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' => $policy_no,
                                    'ic_pdf_url' => $policypdf ? $policypdf : null,
                                    'pdf_url' => $policypdf ? config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($proposal->user_proposal_id) . '.pdf' : null,
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
                                'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($proposal->user_proposal_id) . '.pdf',
                            ];
                        } else {
                            $pdf_response_data = [
                                'status' => false,
                                'msg' => 'Issue In Pdf Service',
                            ];
                        }
                    } else{
                        $pdf_response_data = [
                            'status' => false,
                            'msg' => 'Issue In Pdf Service',
                        ];
                    }
                }
            }
            else if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1') == 'Y')
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
                        "AgreementCode" => $type == 'GCV' ? config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE') : config('constants.IcConstants.sbi.PCV_AGREEMENT_CODE'),
                        "ProductName" => $product_code,
                        "Regeneration" => "Y",
                        "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                        "IntermediateCode" => null,
                        "Offline" => "Y"
                    ]
                ];
                
                $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$policyPdfRequest,'sbi',[
                    'enquiryId' => customDecrypt($request->enquiryId),
                        'requestMethod' =>'post',
                        'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Pdf Genration',
                        'transaction_type' => 'proposal',
                        'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                        'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
                ]);
    
                // Log::info('pdf response => ' . $data);
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
                        $data_pdf = $pdf_response['DocBase64'];//$pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                        $policypdf = false; // $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];
    
                        if (strpos(base64_decode($data_pdf,true), '%PDF') !== 0) 
                        {
                            $policypdf = false;
                            
                        }else
                        {
                        $policypdf = true;
                        Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                        }
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $proposal->user_proposal_id],
                            [
                                'policy_number' =>$policy_no,
                                'ic_pdf_url' => $policypdf ? $policypdf : null,
                                'pdf_url' => $policypdf ? config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
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
                            'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf',
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
                        "AgreementCode" => config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE')
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
                        'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Pdf Genration',
                        'transaction_type' => 'proposal',
                        'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                        'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET'):config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
                ]);
    
                // Log::info('pdf response => ' . $data);
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
                       
                        $data_pdf = $pdf_data['DocBase64']; //$pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][1];
                        $policypdf = false; // $pdf_response['getPolicyDocumentResponseBody']['payload']['URL'][0];
    
                        if (strpos(base64_decode($data_pdf,true), '%PDF') !== 0) 
                        {
                            $policypdf = false;
                            
                        }else
                        {
                            $policypdf = true;
                        Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf', base64_decode($data_pdf));
                        }
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $proposal->user_proposal_id],
                            [
                                'policy_number' =>$policy_no,
                                'ic_pdf_url' => $policypdf ? $policypdf : null,
                                'pdf_url' => $policypdf ? config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf' : null,
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
                            'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($proposal->user_proposal_id). '.pdf',
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
                'method' => 'Token Generation',
                'section' => $productData->product_sub_type_code,
                'enquiryId' => customDecrypt($request->enquiryId),
                'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                'transaction_type' => 'proposal',
                'client_id' =>  $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID') : config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                'client_secret' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
            ]);
            $accessToken = json_decode($pdftoken['response'], true);
            // If any of the tags are not present the we'll return the error message.
            if (!isset($accessToken['accessToken']) && !isset($accessToken['access_token'])) {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Error occured while generating access token. Please check logs for reference.'
                ]);
            }
            // New PDF Generation Service
            if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1_ENCRYPTED_CV') == 'Y')
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
                        "AgreementCode" => $type == 'GCV' ? config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE') : config('constants.IcConstants.sbi.PCV_AGREEMENT_CODE'),
                        "ProductName" => $product_code,
                        "Regeneration" => "Y",
                        "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                        "IntermediateCode" => null,
                        "Offline" => "Y"
                    ]
                ];

                $encryptedReq = [
                    'data' => json_encode($policyPdfRequest),
                    'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                    'action' => 'encrypt'
                ];

                $encrptedResp = httpRequest('sbi_encrypt', $encryptedReq, [], [], [], true, true)['response'];

                $encrptedPdfReq['ciphertext'] = trim($encrptedResp);

                $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI_ENCRYPTED_CV'),$encrptedPdfReq,'sbi',[
                    'enquiryId' => customDecrypt($request->enquiryId),
                        'requestMethod' =>'post',
                        'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Pdf Genration',
                        'transaction_type' => 'proposal',
                        'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                        'client_secret' =>  $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
                ]);
                #Log::info('pdf response => ' . $data);
                $pdf_response = json_decode($data['response'], true);

                if (isset($pdf_response['ciphertext']) && !empty($pdf_response['ciphertext'])) {
                    $decryptedReq = [
                        'data' => $pdf_response['ciphertext'],
                        'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                        'action' => 'decrypt',
                        'file'  => 'true'
                    ];

                    $decrptedResp = httpRequest('sbi_encrypt', $decryptedReq, [], [], [], true, true)['response'];

                    $pdf_data = $decrptedResp;

                    //Creating log for decrypted request and response
                    $startTime = new DateTime(date('Y-m-d H:i:s'));
                    $endTime = new DateTime(date('Y-m-d H:i:s'));
                    $wsLogdata = [
                        'enquiry_id'        => customDecrypt($request->enquiryId),
                        'product'           => $productData->product_name,
                        'section'           => $productData->product_sub_type_code,
                        'method_name'       => 'Decrypted Request Response for PDF',
                        'company'           => 'sbi',
                        'method'            => 'post',
                        'transaction_type'  => 'proposal',
                        'request'           => $policyPdfRequest,
                        'response'          => $pdf_data,
                        'endpoint_url'      => config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI_ENCRYPTED'),
                        'ip_address'        => request()->ip(),
                        'start_time'        => $startTime->format('Y-m-d H:i:s'),
                        'end_time'          => $endTime->format('Y-m-d H:i:s'),
                        'response_time'	    => $endTime->getTimestamp() - $startTime->getTimestamp(),
                        'created_at'        => date('Y-m-d H:i:s'),
                        'headers'           => NULL
                    ];
                    \App\Models\WebServiceRequestResponse::create($wsLogdata);

                    if (isset($pdf_data['DocBase64']) && !empty($pdf_data['DocBase64'])) {
                        $pdf_data['DocBase64'] = stripslashes($pdf_data['DocBase64']);

                        if (isset($pdf_data['Description']) && ($pdf_data['Description'] == 'Success')) {
                            $data_pdf = $pdf_data['DocBase64'];
                            $policypdf = false;

                            if (strpos(base64_decode($data_pdf, true), '%PDF') !== 0) {
                                $policypdf = false;
                            } else {
                                $policypdf = true;
                                Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($policy_details->user_proposal_id) . '.pdf', base64_decode($data_pdf));
                            }
                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $policy_details->user_proposal_id],
                                [
                                    'policy_number' => $policy_details->policy_number,
                                    'ic_pdf_url' => $policypdf ? $policypdf : null,
                                    'pdf_url' => $policypdf ? config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($policy_details->user_proposal_id) . '.pdf' : null,
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
                                    'pdf_link'      => $policypdf ? file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($policy_details->user_proposal_id) . '.pdf') : null
                                ]
                            ];
                        } else {
                            updateJourneyStage([
                                'user_product_journey_id' => $user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            $pdf_response_data = [
                                'status' => false,
                                'msg' => 'Issue in pdf service',
                            ];
                        }
                    } else {
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
            }
            else if(config('constants.IcConstants.sbi.SBI_PDF_SERVICE_V1') == 'Y')
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
                        "AgreementCode" => $type == 'GCV' ? config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE') : config('constants.IcConstants.sbi.PCV_AGREEMENT_CODE'),
                        "ProductName" => $product_code,
                        "Regeneration" => "Y",
                        "SourceSystem" => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                        "IntermediateCode" => null,
                        "Offline" => "Y"
                    ]
                ];
                $data = getWsData(config('constants.IcConstants.sbi.POLICY_PDF_LINK_SBI'),$policyPdfRequest,'sbi',[
                    'enquiryId' => customDecrypt($request->enquiryId),
                        'requestMethod' =>'post',
                        'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Pdf Genration',
                        'transaction_type' => 'proposal',
                        'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                        'client_secret' =>  $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
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
                            Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf', base64_decode($data_pdf));
                        }
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $policy_details->user_proposal_id],
                        [
                            'policy_number' => $policy_details->policy_number,
                            'ic_pdf_url' => $policypdf ? $policypdf : null,
                            'pdf_url' => $policypdf ? config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf' : null,
                            'status' => 'SUCCESS'
                        ]
                    );
                    updateJourneyStage([
                        'user_product_journey_id' => $user_product_journey_id,
                        'stage' => ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                    $pdf_response_data = [
                        'status' => true,
                        'msg' => 'sucess',
                        'data' => [
                            'policy_number' => $policy_details->policy_number,
                            'pdf_link'      => $policypdf ? file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($policy_details->user_proposal_id). '.pdf') : null
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
            }else{
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
                        "AgreementCode" => config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE')
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
                        'authorization' => isset($accessToken['accessToken']) ? $accessToken['accessToken']:$accessToken['access_token'],
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Pdf Genration',
                        'transaction_type' => 'proposal',
                        'client_id' => $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_ID') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF_CV'),
                        'client_secret' =>  $type == 'PCV' ? config('constants.IcConstants.sbi.PCV_PDF_CLIENT_SECRET') :config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF_CV'),
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
                            Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf', base64_decode($data_pdf));
                        }
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $policy_details->user_proposal_id],
                        [
                            'policy_number' => $policy_details->policy_number,
                            'ic_pdf_url' => $policypdf ? $policypdf : null,
                            'pdf_url' => $policypdf ? config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/'. md5($policy_details->user_proposal_id). '.pdf' : null,
                            'status' => 'SUCCESS'
                        ]
                    );
                    updateJourneyStage([
                        'user_product_journey_id' => $user_product_journey_id,
                        'stage' => ($policypdf == true ) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                    $pdf_response_data = [
                        'status' => true,
                        'msg' => 'sucess',
                        'data' => [
                            'policy_number' => $policy_details->policy_number,
                            'pdf_link'      => $policypdf ? file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'sbi/' . md5($policy_details->user_proposal_id). '.pdf') : null
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
            
        }else{
        $pdf_response_data = [
            'status' => true,
            'msg' => STAGE_NAMES['POLICY_PDF_GENERATED'],
            'data'=>  Storage::url($policy_details->pdf_url),
            ];
        }
    return response()->json($pdf_response_data);

}

}