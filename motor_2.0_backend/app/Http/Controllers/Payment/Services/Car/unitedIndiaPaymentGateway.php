<?php

namespace App\Http\Controllers\Payment\Services\Car;

use App\Http\Controllers\Paytm\PaymentGatewayController;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use paytm\paytmchecksum\PaytmChecksum;
use Illuminate\Support\Facades\Storage;
use App\Models\PaymentRequestResponse;
use Carbon\Carbon;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class unitedIndiaPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {

        // PAYMENT
        $proposal       = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $enquiryId      = customDecrypt($request->enquiryId);
        $icId           = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        $quote_log_id   = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();

        if(config('constants.IcConstants.united_india.car.PAYMENT_GATEWAY_TYPE') == 'paytm') {
            if (
                config('PAYMENT_GATEWAY.PAYTM.ENABLE') == 'Y' &&
                in_array('united_india', explode(',', config('PAYMENT_GATEWAY.PAYTM.IC_LIST')))
            ) {
                $paytmPaymentGateway = new PaymentGatewayController('united_india', 'car', $enquiryId);
                return $paytmPaymentGateway->initiateTransaction($request);
            }
            return self::paytmMakePayment($request, $proposal, $quote_log_id);
        }

        $checksum       = unitedIndiaPaymentGateway::create_checksum($enquiryId ,$request);

        $user_proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $additional_details     = json_decode($proposal->additional_details);
        $additional_details_data     = json_decode($proposal->additional_details_data);

        $additional_details->united_india->order_id = $checksum['transaction_id'];
        $additional_details_data->united_india->order_id = $checksum['transaction_id'];

        $proposal->additional_details = json_encode($additional_details);
        $proposal->additional_details_data = json_encode($additional_details_data);
        $proposal->save();

        $return_data = [
            'form_action'       => config('constants.IcConstants.united_india.car.END_POINT_URL_PAYMENT_GATEWAY'),
            'form_method'       => 'POST',
            'payment_type'      => 0, // form-submit
            'form_data'         => [
                'msg'               => $checksum['msg'],
            ]
        ];
        $data['user_product_journey_id']    = $user_proposal->user_product_journey_id;
        $data['ic_id']                      = $user_proposal->ic_id;
        $data['stage']                      = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        $checksum = explode('|',$checksum['msg']);

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $proposal->user_product_journey_id)
        ->where('user_proposal_id', $proposal->user_proposal_id)
        ->update([
            'active' => 0
        ]);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $checksum[1],
            'amount'                    => $proposal->final_payable_amount,
            'payment_url'               => config('constants.IcConstants.united_india.car.END_POINT_URL_PAYMENT_GATEWAY'),
            'return_url'                => route(
                'car.payment-confirm',
                [
                    'united_india',
                    'user_proposal_id'      => $proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]
            ),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);


        return response()->json([
            'status'    => true,
            'msg'       => "Payment Reidrectional",
            'data'      => $return_data,
        ]);


    }


    public static function paytmMakePayment($request, $proposal, $quote_log_id)
    {
        // PAYMENT
        $paytmParams = array();
        $paytmParams["body"] = array(
            "requestType" => "Payment",
            "mid" => config('constants.IcConstants.united_india.car.PAYTM.MID'),
            "websiteName" => config('constants.IcConstants.united_india.car.PAYTM.WEBSITENAME'),
            "orderId" => "ORDERID_" . Str::random(15),
            "callbackUrl" => route('car.payment-confirm', [
                    'united_india',
                    'user_proposal_id'      => $proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]),
            "txnAmount" => array(
                "value" => $proposal->final_payable_amount,
                "currency" => "INR",
            ),
            "userInfo" => array(
                "custId" => "CUST_" . $proposal->user_proposal_id,
                "firstName" => $proposal->first_name,
                "lastName" => $proposal->last_name,
            ),
        );
        $checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), config('constants.IcConstants.united_india.car.PAYTM.MERCHANT_KEY'));

        $paytmParams["head"] = array(
            "signature" => $checksum,
        );
        
        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

        $get_response = getWsData(config('constants.IcConstants.united_india.car.PAYTM.TOKENGENERATION_URL') . 'mid=' . $paytmParams['body']['mid'] . '&orderId=' . $paytmParams['body']['orderId'], $post_data, 'united_india', [
            'enquiryId' => $proposal->user_product_journey_id,
            'headers' => [
                'Content-Type: application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => '',
            'method' => 'Payment Token Generation',
            'transaction_type' => 'proposal'
        ]);
        $token_response = $get_response['response'];
        if($token_response){
            $token_response = json_decode($token_response, true);
            if (isset($token_response['body']['resultInfo']['resultStatus']) && $token_response['body']['resultInfo']['resultStatus'] == 'S') {
                $paytmParams['txnToken'] = $token_response['body']['txnToken'];
            } else {
                return response()->json([
                    'status' => false,
                    'msg'    => "Error in Token Generation",
                    'data'   => $token_response
                ]);
            }
        } else {
            return response()->json([
                'status'    => false,
                'msg'       => "Error in Token Generation",
            ]);
        }

        $return_data = [
            'form_action'       => config('constants.IcConstants.united_india.car.END_POINT_URL_PAYMENT_GATEWAY'),
            'form_method'       => 'POST',
            'payment_type'      => "PAYTM", // form-submit
            'form_data'         => $paytmParams
        ];
        $data['user_product_journey_id']    = $proposal->user_product_journey_id;
        $data['ic_id']                      = $proposal->ic_id;
        $data['stage']                      = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $proposal->user_product_journey_id)
        ->where('user_proposal_id', $proposal->user_proposal_id)
        ->update([
            'active' => 0,
        ]);

        $paytmParams['form_data'] = [
            'form_action' => config('IC.UNITED_INDIA.PAYMENT_GATEWAY.PAYTM.SHOWPAYMNTPAGE').'?mid=' . $paytmParams['body']['mid'] . '&orderId=' . $paytmParams['body']['orderId'],
            'form_method' => 'POST',
            'payment_type' => 0, // form-submit
            'form_data' =>
            [
                'mid'       => $paytmParams['body']['mid'],
                'orderId'   => $paytmParams['body']['orderId'],
                'txnToken'  => $token_response['body']['txnToken']
            ]
        ];

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $proposal->user_product_journey_id,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'ic_id'                     => $proposal->ic_id,
            'order_id'                  => $paytmParams['body']['orderId'],
            'amount'                    => $proposal->final_payable_amount,
            'payment_url'               => $return_data['form_action'],
            'return_url'                => $paytmParams["body"]["callbackUrl"],
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'xml_data'                  => json_encode($paytmParams)
        ]);

        if(config('IC.UNITED_INDIA.PAYMENT_GATEWAY.PAYTM.FORM_ENABLE') == 'Y')
        {
            return response()->json([
                'status'    => true,
                'msg'       => "Payment Redirection",
                'data'      => $paytmParams['form_data'],
            ]);
        }
        return response()->json([
            'status'    => true,
            'msg'       => "Payment Redirection",
            'data'      => $return_data,
        ]);
    }

    public static  function  create_checksum($enquiryId ,$request)
    {

        $policy_id=$request['policyId'];
        DB::enableQueryLog();
        $data = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();

        $new_pg_transaction_id = strtoupper(config('constants.IcConstants.united_india.car.PAYMENT_MERCHANT_ID')).date('Ymd').time().rand(10,99);

        $str_arr = [
            config('constants.IcConstants.united_india.car.PAYMENT_MERCHANT_ID'),
            $new_pg_transaction_id,
            'NA',
            (env('APP_ENV') == 'local') ? '1.00' :$data->final_payable_amount,
            'NA',
            'NA',
            'NA',
            'INR',
            'NA',
            'R',
            config('constants.IcConstants.united_india.car.PAYMENT_SECURITY_ID'),
            'NA',
            'NA',
            'F',
            $data->proposal_no,
            $data->vehicale_registration_number,
            $data->mobile_number,
            $data->email,
            $data->chassis_number,
            $data->first_name.' '.$data->last_name,
            'NA',
            route('car.payment-confirm', ['united_india','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),

        ];

        $msg_desc = implode('|', $str_arr);
        $checksum = strtoupper(hash_hmac('sha256', $msg_desc, config('constants.IcConstants.united_india.car.PAYMENT_CHECKSUM_KEY')));

        $new_string = $msg_desc.'|'.$checksum;


        $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
            ->where('user_proposal_id', $data->user_proposal_id)
            ->update([
                'unique_proposal_id'                 => $new_pg_transaction_id,
            ]);


        $quries = DB::getQueryLog();

        return [
            'status' => true,
            'msg' => $new_string,
            'transaction_id' => $new_pg_transaction_id
        ];
    }



    public static function confirm($request)
    {
        $request_data = $request->all();
        if(config('constants.IcConstants.united_india.car.PAYMENT_GATEWAY_TYPE') == 'paytm') {
            if (
                config('PAYMENT_GATEWAY.PAYTM.ENABLE') == 'Y' &&
                in_array('united_india', explode(',', config('PAYMENT_GATEWAY.PAYTM.IC_LIST')))
            ) {
                $paytmPaymentGateway = new PaymentGatewayController('united_india', 'car');
                $response = $paytmPaymentGateway->paymentConfirm($request);
                
                if ($response['status'] ?? false) {

                    $proposal = $response['data']['proposal'];
                    $additional_details     = json_decode($proposal->additional_details);
                    $united_india_data      = $additional_details->united_india;

                    $proposal_data = [
                        'premium_amount'            => $proposal['final_payable_amount'],
                        'num_reference_number'      => $united_india_data->reference_number,
                        'transaction_id'            => $united_india_data->transaction_id,
                        'pg_transaction_id'         => $response['data']['orderId']
                    ];

                    $payment_service = unitedIndiaPaymentGateway::payment_info_service($proposal_data, $proposal);
                    if (!$payment_service['status']) {
                        return redirect(paymentSuccessFailureCallbackUrl($proposal['user_product_journey_id'], 'CAR', 'SUCCESS'));
                    }

                    unitedIndiaPaymentGateway::create_pdf(
                        $proposal,
                        [
                            'policy_number' => $payment_service['policy_no'],
                            'pdf_schedule' => $payment_service['pdf_schedule']
                        ]
                    );
                    return redirect(paymentSuccessFailureCallbackUrl($proposal['user_product_journey_id'], 'CAR', 'SUCCESS'));
                }
                return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
            }
            return self::paytmConfirmPayment($request);
        }
        if($request_data!=null && isset($_REQUEST['msg'])) {

            $response   = $_REQUEST['msg'];
            $response   = explode('|', $response);

            $proposal   = UserProposal::where('proposal_no', $response[16])->first();

            $transaction_auth_status = [
                '0300'  => "Success - Successful Transaction",
                '0399'  => "Invalid Authentication at Bank - Cancel Transaction",
                'NA'    => "Invalid Input in the Request Message - Cancel Transaction",
                '0002'  => "BillDesk is waiting for Response from Bank - Cancel Transaction",
                '0001'  => "Error at BillDesk - Cancel Transaction"
            ];

            if ($response[14] == '0300')
            {

                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'response'      => $_REQUEST['msg'],
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                updateJourneyStage($data);

                $additional_details     = json_decode($proposal->additional_details);
                $additional_details_data = json_decode($proposal->additional_details_data);

                if (isset($additional_details->united_india)) {
                    $united_india_data = $additional_details->united_india;
                } else {
                    $united_india_data = $additional_details_data->united_india;
                }


                $proposal_data = [
                    'premium_amount'            => $proposal->final_payable_amount,
                    'num_reference_number'      => $united_india_data->reference_number,
                    'transaction_id'            => $united_india_data->transaction_id,
                    'pg_transaction_id'         => $response[1],
                ];

                $payment_service = unitedIndiaPaymentGateway::payment_info_service($proposal_data, $proposal);

                if(!$payment_service['status']){
                    return redirect(paymentSuccessFailureCallbackUrl($request_data['enquiry_id'],'CAR','SUCCESS'));
                }

                $pdf_response_data = unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $payment_service['policy_no'],
                        'pdf_schedule' => $payment_service['pdf_schedule']
                    ]
                );

                return redirect(paymentSuccessFailureCallbackUrl($request_data['enquiry_id'],'CAR','SUCCESS'));
            }
            else
            {

                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'response'      => $_REQUEST['msg'],
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'status'        => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','FAILURE'));
            }
        }

        return redirect(Config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));

    }
    public static function paytmConfirmPayment($request)
    {
        $request_data = $request->all();
        if(!isset($request_data['user_proposal_id']))
        {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $proposal = UserProposal::where('user_proposal_id', $request_data['user_proposal_id'])->first();
        $paytmParams["body"] =  [
            /* Find your MID in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys */
            "mid" => $request_data['MID'],

            /* Enter your order id which needs to be check status for */
            "orderId" => $request_data['ORDERID'],

        ];
        $checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), config('constants.IcConstants.united_india.car.PAYTM.MERCHANT_KEY'));
        $paytmParams["head"] = array(

            /* put generated checksum value here */
            "signature"	=> $checksum
        );
        $url = config('constants.IcConstants.united_india.car.PAYTM.CHECK_STATUS_URL');#https://securegw-stage.paytm.in/v3/order/status
        $get_response = getWsData($url , $paytmParams, 'united_india', [
            'enquiryId' => $proposal['user_product_journey_id'],
            'headers' => [
                'Content-Type: application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => '',
            'method' => 'Check Payment Status',
            'transaction_type' => 'proposal'
        ]);
        $verify_payment = $get_response['response'];

        $response_data = json_decode($verify_payment,true);
        if(isset($response_data['body']['resultInfo']['resultStatus']) && $response_data['body']['resultInfo']['resultStatus'] == 'TXN_SUCCESS')
        {
            PaymentRequestResponse::where('user_product_journey_id', $proposal['user_product_journey_id'])
                ->where('user_proposal_id', $proposal['user_proposal_id'])
                ->where('active', 1)
                ->update([
                    'response'      => json_encode($request->all()),
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
                $data['user_product_journey_id']    = $proposal['user_product_journey_id'];
                $data['ic_id']                      = $proposal['ic_id'];
                $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                updateJourneyStage($data);
                $additional_details     = json_decode($proposal->additional_details);
                $united_india_data      = $additional_details->united_india;
                $proposal_data = [
                    'premium_amount'            => $proposal['final_payable_amount'],
                    'num_reference_number'      => $united_india_data->reference_number,
                    'transaction_id'            => $united_india_data->transaction_id,
                    'pg_transaction_id'         => $response_data['body']['orderId'],
                    'utr_number'                => $response_data['body']['bankTxnId']
                ];

                $payment_service = unitedIndiaPaymentGateway::payment_info_service($proposal_data, $proposal);
                if(!$payment_service['status']){
                    return redirect(paymentSuccessFailureCallbackUrl($proposal['user_product_journey_id'],'CAR','SUCCESS'));
                }

                $pdf_response_data = unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $payment_service['policy_no'],
                        'pdf_schedule' => $payment_service['pdf_schedule']
                    ]
                );
                return redirect(paymentSuccessFailureCallbackUrl($proposal['user_product_journey_id'],'CAR','SUCCESS'));
        }
        else
        {

            PaymentRequestResponse::where('user_product_journey_id', $proposal['user_product_journey_id'])
                ->where('user_proposal_id', $proposal['user_proposal_id'])
                ->where('active', 1)
                ->update([
                    'response'      => $request->all(),
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'status'        => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                $data['user_product_journey_id']    = $proposal['user_product_journey_id'];
                $data['ic_id']                      = $proposal['ic_id'];
                $data['stage']                      = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                return redirect(
                    config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL')
                    . '?'
                    . http_build_query(['enquiry_id' => customEncrypt($proposal['user_product_journey_id'])]));
        }
    }

    public static function payment_info_service($proposal_data, $proposal){

        $payent_info_array = [
            'HEADER' => [
                'DAT_UTR_DATE'              => Carbon::parse($proposal->proposal_date)->format('d-m-Y'),
                'NUM_PREMIUM_AMOUNT'        => $proposal_data['premium_amount'],
                'NUM_REFERENCE_NUMBER'      => $proposal_data['num_reference_number'],
                'NUM_UTR_PAYMENT_AMOUNT'    => $proposal_data['premium_amount'],
                'TXT_BANK_CODE'             => '',
                'TXT_BANK_NAME'             => '',
                'TXT_MERCHANT_ID'           =>  config('constants.IcConstants.united_india.car.PAYMENT_GATEWAY_TYPE') == 'paytm' ? config('constants.IcConstants.united_india.car.PAYTM.MID') :config('constants.IcConstants.united_india.car.PAYMENT_MERCHANT_ID'),
                'TXT_TRANSACTION_ID'        => $proposal_data['transaction_id'],
                'TXT_UTR_NUMBER'            => $proposal_data['utr_number'],
                'TXT_MERCHANT_KEY'          =>  '', #config('constants.IcConstants.united_india.car.PAYTM.MERCHANT_KEY'),
                'TXT_ORDER_ID'              => ''  # $proposal_data['pg_transaction_id']  //changes as per git #31881
            ]
        ];

        $request_container = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.uiic.com/">
            <soapenv:Header/>
            <soapenv:Body>
                <ws:paymentInfo>
                    <application>'.config('constants.IcConstants.united_india.car.APPLICATION_ID').'</application>
                    <userid>'.config('constants.IcConstants.united_india.car.USER_ID').'</userid>
                    <password>'.config('constants.IcConstants.united_india.car.USER_PASSWORD').'</password>
                    <paymentXml>
                        <![CDATA[#replace]]>
                    </paymentXml>
                </ws:paymentInfo>
            </soapenv:Body>
        </soapenv:Envelope>';

        // quick quote service input

        $additional_data = [
            'enquiryId'         => $proposal->user_product_journey_id,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'xml',
            'section'           => 'Car',
            'method'            => 'Payment - proposal',
            'transaction_type'  => 'Proposal',
            'root_tag'          => 'ROOT',
            'soap_action'       => 'paymentInfoResponse',
            'container'         => $request_container,
        ];

        $get_response = getWsData(config('constants.IcConstants.united_india.car.END_POINT_URL_SERVICE'), $payent_info_array, 'united_india', $additional_data);
        $response = $get_response['response'];

        if ($response) {

            $payment_output = html_entity_decode($response);
            $payment_output = XmlToArray::convert($payment_output);

            $header = $payment_output['S:Body']['ns2:paymentInfoResponse']['return']['ROOT']['HEADER'];
            $error_message = $header['TXT_ERR_MSG'];

            if($error_message != [] && $error_message != ''){
                return [
                    'status'    => false,
                    'msg'       => $error_message,
                    'message'   => json_encode($error_message)
                ];
            }

            if(isset($header['TXT_NEW_POLICY_NUMBER'])){

                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($data);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_number' => $header['TXT_NEW_POLICY_NUMBER'],
                        'ic_pdf_url' => $header['SCHEDULE'],
                    ]
                );
                return [
                    'status'        => true,
                    'policy_no'     => $header['TXT_NEW_POLICY_NUMBER'],
                    'pdf_schedule'  => $header['SCHEDULE'],
                    'collection_no' => $header['NUM_COLLECTION_NO'],
                    'payment_no'    => $header['NUM_PAYMENT_ID'],
                    'invoice_no'    => $header['TXT_INVOICE_NO'],
                ];
            }
        }
        else{

            $data['user_product_journey_id']    = $proposal->user_product_journey_id;
            $data['ic_id']                      = $proposal->ic_id;
            $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($data);

            return [
                'status'    => false,
                'message'   => 'no response from paymentinfo service'
            ];
        }
    }


    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        if(config('constants.IcConstants.united_india.car.PAYMENT_GATEWAY_TYPE') == 'paytm') {
            if (
                config('PAYMENT_GATEWAY.PAYTM.ENABLE') == 'Y' &&
                in_array('united_india', explode(',', config('PAYMENT_GATEWAY.PAYTM.IC_LIST')))
            ) {
                $paytmPaymentGateway = new PaymentGatewayController('united_india', 'car', $user_product_journey_id);
                $status_data = $paytmPaymentGateway->paymentStatusCheck();
            } else {
                $status_data = self::check_payment_status($user_product_journey_id);
            }
            
            if(!$status_data['status'])
            {
                return  [
                    'status' => false,
                    'msg'    => 'Payment Is Pending'
                ];
            }
        }
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin(
                'policy_details as pd',
                'pd.proposal_id','=','prr.user_proposal_id'
            )
            ->join(
                'user_proposal as up',
                'up.user_product_journey_id','=','prr.user_product_journey_id'
            )
            ->where([
                'prr.user_product_journey_id'   => $user_product_journey_id,
                'prr.active'                    => 1,
                'prr.status'                    => STAGE_NAMES['PAYMENT_SUCCESS']
            ])
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no','up.unique_proposal_id', 'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id'
            )
            ->first();

        if($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found',
                'data'   => []
            ];

            return response()->json($pdf_response_data);
        }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        // echo "<pre>";print_r([$proposal->getAttributes(), $policy_details]);echo "</pre>";die(); 1804003121P100557502

        if($policy_details->pdf_url == '')
        {
            $generatePolicy['status'] = false;

            if(is_null($policy_details->policy_number) || $policy_details->policy_number == ''){
                $generatePolicy = unitedIndiaPaymentGateway::generatePolicy($proposal, $request->all());
            }

            // echo "<pre>";print_r([$generatePolicy, $policy_details]);echo "</pre>";die();

            if($generatePolicy['status'])
            {
                $pdf_response_data = unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $generatePolicy['data']['policy_no'],
                        'pdf_schedule' => $generatePolicy['data']['pdf_schedule']
                    ]
                );
            }
            else if($policy_details->policy_number != '')
            {
                $pdf_response_data = unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_schedule' => $policy_details->ic_pdf_url
                    ]
                );
            }
            else{
                $pdf_response_data = $generatePolicy;
            }
        }
        else
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'ic_pdf_url' => $policy_details->ic_pdf_url,
                    'pdf_url' => $policy_details->pdf_url,
                    'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'united_india/'. md5($proposal->user_proposal_id). '.pdf'),
                    'policy_number' => $policy_details->policy_number
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }


    static public function generatePolicy($proposal, $requestArray)
    {
        $additional_details     = json_decode($proposal->additional_details);
        $additional_details_data = json_decode($proposal->additional_details_data);
        
        if (isset($additional_details->united_india)) {
            $united_india_data = $additional_details->united_india;
        } else {
            $united_india_data = $additional_details_data->united_india;
        }

        $payment_details = PaymentRequestResponse::where([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'active' => 1,
            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
        ])->first();

        $response = json_decode($payment_details['response'], true);

        $transaction_id = '';
        if (!empty($response)) {
            $transaction_id =   $response['data']['items'][0]['id'] ?? NULL;
        }
        
        $proposal_data = [
            'premium_amount'            => $proposal->final_payable_amount,
            'num_reference_number'      => $united_india_data->reference_number,
            'transaction_id'            => $united_india_data->transaction_id,
            'pg_transaction_id'         => $payment_details->order_id,
            'utr_number'                => $transaction_id,
        ];

        $payment_service = unitedIndiaPaymentGateway::payment_info_service($proposal_data, $proposal);

        if ($payment_service['status'])
        {
            return [
                'status' => true,
                'msg'    => 'policy no generated successfully',
                'data'   => $payment_service
            ];
        }
        else
        {
            return [
                'status' => false,
                'msg'    => $payment_service['message'],
                'data'   => []
            ];
        }
    }


    static public function create_pdf($proposal, $policy_data){

        if (empty($policy_data['pdf_schedule'])) {
            return [
                'status' => false,
                'msg'    => 'Issue in pdf service'
            ];
        }
        $pdf_data = (env('APP_ENV') == 'local') ? 'null' : file_get_contents($policy_data['pdf_schedule']);
        
        if (!checkValidPDFData($pdf_data)) {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
        } 

        $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'united_india/'. md5($proposal->user_proposal_id). '.pdf';

        $proposal_pdf = Storage::put($pdf_url, $pdf_data);

        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['ic_id'] = $proposal->ic_id;
        $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
        updateJourneyStage($data);

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $proposal->user_proposal_id],
            [
                'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'united_india/'. md5($proposal->user_proposal_id). '.pdf',
                'status' => 'SUCCESS'
            ]
        );

        return [
            'status' => true,
            'msg' => 'sucess',
            'data' => [
                'policy_number' => $policy_data['policy_number'],
                'pdf_link'      => file_url($pdf_url),
                'ic_pdf_url'    => $policy_data['pdf_schedule'],
                'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'united_india/'. md5($proposal->user_proposal_id). '.pdf'),
            ]
        ];
    }
    public static function check_payment_status($enquiry_id)
    {
        $get_payment_details = PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                                ->where('ic_id', 25)
                                ->select('xml_data','id','user_proposal_id')
                                ->get();
        if(empty($get_payment_details))
        {
            return [
                'status' => false
            ];
        }
        foreach ($get_payment_details as $value)
        {    
            if(empty($value->xml_data))
            {
                continue;
            }
            $payment_data = json_decode($value->xml_data,true);
            $paytmParams["body"] =  [
                /* Find your MID in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys */
                "mid" => $payment_data['body']['mid'], //requirired
        
                /* Enter your order id which needs to be check status for */
                "orderId" => $payment_data['body']['orderId'], //orderid
        
            ];

            if (
                config('PAYMENT_GATEWAY.PAYTM.ENABLE') == 'Y' &&
                in_array('united_india', explode(',', config('PAYMENT_GATEWAY.PAYTM.IC_LIST')))
            ) {
                $paytmCreds = PaymentGatewayController::getCredentials('united_india');
                $merchantKey = $paytmCreds['merchantKey'];
                
            } else {
                $merchantKey = config('constants.IcConstants.united_india.car.PAYTM.MERCHANT_KEY');
            }

            $checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $merchantKey);
            $paytmParams["head"] = array(
        
                /* put generated checksum value here */
                "signature"	=> $checksum
            );
            $url = config('constants.IcConstants.united_india.car.PAYTM.CHECK_STATUS_URL');#https://securegw-stage.paytm.in/v3/order/status
            $get_response = getWsData($url , $paytmParams, 'united_india', [
                'enquiryId' => $enquiry_id,
                'headers' => [
                    'Content-Type: application/json'
                ],
                'requestMethod' => 'post',
                'requestType' => 'json',
                'section' => '',
                'method' => 'Check Payment Status',
                'transaction_type' => 'proposal'
            ]);
            $verify_payment = $get_response['response'];

            $payment_check_response = json_decode($verify_payment,true);
            if(isset($payment_check_response['body']['resultInfo']['resultStatus']) && $payment_check_response['body']['resultInfo']['resultStatus'] == 'TXN_SUCCESS')
            {
                PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                    ->update([
                        'active'  => 0
                    ]);

                PaymentRequestResponse::where('id', $value->id)
                    ->update([
                        'response'      => $payment_check_response,
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                        'active'        => 1
                    ]);
                    $data['user_product_journey_id']    = $enquiry_id;
                    $data['proposal_id']                = $value->user_proposal_id;
                    $data['ic_id']                      = '25';
                    $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                    updateJourneyStage($data);

                    return [
                        'status' => true,
                        'msg' => 'success'
                    ];
                    
            }
        }

        return [
            'status' => false
        ];
            
    }
}
