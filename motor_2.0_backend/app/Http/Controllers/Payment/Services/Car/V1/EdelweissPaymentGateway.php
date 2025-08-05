<?php

namespace App\Http\Controllers\Payment\Services\Car\V1;

use App\Models\QuoteLog;
use App\Models\JourneyStage;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
class EdelweissPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $enquiryId = customDecrypt($request->enquiryId);

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();;
        $checksumreturn = EdelweissPaymentGateway::create_checksum($enquiryId, $request);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'] ;
        updateJourneyStage($data);

        $checksum = explode('|', $checksumreturn['msg']);

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->update(['active' => 0]);

        DB::table('payment_request_response')->insert([
            'quote_id' => $quote_log_id,
            'user_product_journey_id' => $enquiryId,
            'user_proposal_id' => $proposal->user_proposal_id,
            'ic_id' => $icId,
            'order_id' => $checksum[1],
            'amount' => $proposal->final_payable_amount,
            'payment_url' => $checksumreturn['msg'],
            'return_url' => route('car.payment-confirm', ['edelweiss', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'active' => 1
        ]);
        return response()->json([
            'status' => true,
            'data' => [
                'payment_type' => 1,
                'paymentUrl' => $checksumreturn['msg']
            ]
        ]);
    }

    public static  function  create_checksum($enquiryId, $request)
    {
        DB::enableQueryLog();
        $data = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();
        //$new_pg_transaction_id = strtoupper(config('CARDEMO')).date('Ymd').time().rand(10,99);
        // $new_pg_transaction_id  = config('constants.IcConstants.edelweiss.MOTOR_USERNAME') . 'EGI' . time();
        // $new_pg_transaction_id  = config('constants.IcConstants.edelweiss.MOTOR_USERNAME') . customEncrypt($enquiryId) . date('His', strtotime(now()));
        $new_pg_transaction_id  = config('constants.IcConstants.edelweiss.MOTOR_USERNAME') . substr(strtoupper(md5(mt_rand())), 0, 7) . date('His', strtotime(now()));
        $bill_desk_request =
            [
                'MerchantID'        => config('IC.EDELWEISS.V1.CAR.MOTOR_MERCHANT_ID'), //'EDGENBKAGR', constants.IcConstants.edelweiss.MOTOR_MERCHANT_ID
                'CustomerID'        => $new_pg_transaction_id,
                'Filler1'           => 'NA',
                'TxnAmount'         => $data['final_payable_amount'],
                'BankID'            => 'NA',
                'Filler2'           => 'NA',
                'Filler3'           => 'NA',
                'CurrencyType'      => 'INR',
                'ItemCode'          => 'NA',
                'TypeField1'        => 'R',
                'UserID'            => config('IC.EDELWEISS.V1.CAR.MOTOR_USER_ID'), //'EDGNMIBLPC-NA', //constants.IcConstants.edelweiss.MOTOR_USER_ID
                'Filler4'           => 'NA',
                'Filler5'           => 'NA',
                'TypeField2'        => 'F',
                'AdditionalInfo1'   => $data['proposal_no'],
                'AdditionalInfo2'   => config('constants.IcConstants.edelweiss.MOTOR_USERNAME'), //'MIBL',        
                'AdditionalInfo3'   => 'NA',
                'AdditionalInfo4'   => 'NA',
                'AdditionalInfo5'   => 'NA',
                'AdditionalInfo6'   => 'NA',
                'AdditionalInfo7'   => 'NA',
                'RU'                => route('car.payment-confirm', ['edelweiss'])
            ];
        $bill_desk_msg = implode("|", $bill_desk_request);
        //$checksum = hash_hmac('sha256',$bill_desk_msg,'zu5c8pqbYSzUCJBr2O1LWjCVuZ478Nc6', false);
        //constants.IcConstants.edelweiss.MOTOR_CHECKSUM_KEY
        $checksum = hash_hmac('sha256', $bill_desk_msg, config('IC.EDELWEISS.V1.CAR.MOTOR_CHECKSUM_KEY'), false);
        $bill_desk_request['checksum'] = strtoupper($checksum);
        $bill_desk_request = implode("|", $bill_desk_request);
        //$payment_link = 'https://uat.billdesk.com/pgidsk/PGIMerchantPayment'.'?msg='.$bill_desk_request;
        $payment_link = config('IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_GATEWAY') . '?msg=' . $bill_desk_request;
        //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PAYMENT_GATEWAY
        $new_string = $payment_link;
        UserProposal::where('user_product_journey_id', $enquiryId)
            ->where('user_proposal_id', $data->user_proposal_id)
            ->update([
                'unique_proposal_id' => $new_pg_transaction_id,
            ]);


        //$quries = DB::getQueryLog();

        return [
            'status' => 'true',
            'msg' => $new_string,
            'transaction_id' => $new_pg_transaction_id
        ];
    }

    public static function confirm($request)
    {
        $request_data = $request->all();
        $response = $_REQUEST['msg'];
        $response_array = explode('|', $response);
        $AuthStatus = $response_array['14'];
        $instrumentNo = $response_array['2'];
        $order_id = $response_array['1'];
        $payment_data = PaymentRequestResponse::where('order_id', $order_id)
            ->first();
        if (empty($payment_data)) {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $request_data['enquiry_id'] = $payment_data->user_product_journey_id;
        $policyid = QuoteLog::where('user_product_journey_id', $request_data['enquiry_id'])->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policyid);
        $proposal = UserProposal::where('user_product_journey_id', $request_data['enquiry_id'])->first();

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);
        }

        if ($request_data != null && isset($_REQUEST['msg'])) {
            if ($AuthStatus == '0300') {
                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update(
                        [
                            'response' => implode(' ', $request_data),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]
                    );
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_start_date' => $proposal->policy_start_date,
                        'idv' => $proposal->idv,
                        'ncb' => $proposal->ncb_discount,
                        'premium' => $proposal->final_payable_amount
                    ]
                );
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'] ;
                updateJourneyStage($data);

                $isPolicyNumberGenerated = PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])
                ->first()?->policy_number;

                if (!empty($isPolicyNumberGenerated)) {
                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
                }
                $get_response = getWsData(
                    config('IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION'),//constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION
                    '',
                    'edelweiss',
                    [
                        'enquiryId' => $request_data['enquiry_id'],
                        'requestMethod' => 'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'edelweiss',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Token genration',
                        'userId' => config('IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME
                        'password' => config('IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD
                        'type' => 'Token genration',
                        'transaction_type' => 'proposal',
                    ]
                );
                $token_data = $get_response['response'];
                $token_data = json_decode($token_data, TRUE);
                $request_policy_gen =
                    [
                        'product' =>
                        [
                            'name' => 'EGICProductWebServicesV1',
                            'version' => '1',
                        ],
                        'policyRequest' =>
                        [
                            'issuePolicyList' =>
                            [
                                [
                                    'issuePolicy' =>
                                    [
                                        'quoteNo' => $proposal['proposal_no'],
                                        'quoteOptionNo' => $proposal['unique_quote']
                                    ]
                                ]
                            ],
                            'ipContextInfo' =>
                            [
                                'productName' => 'String',
                                'productVersion' => '1',
                                'LeadID' => '',
                                'eKYCFlag' => 'Y'
                            ]
                        ]
                    ];

                    if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                        $request_policy_gen['policyRequest']['issuePolicyList'][0]['issuePolicy']['VISoF_KYC_Req_No'] = $ckyc_meta_data['VISoF_KYC_Req_No'];
                        $request_policy_gen['policyRequest']['issuePolicyList'][0]['issuePolicy']['IC_KYC_No'] = $ckyc_meta_data['IC_KYC_No'];
                    }

                    //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_POLICY_GENERATION
                $get_response = getWsData(
                    config('IC.EDELWEISS.V1.CAR.END_POINT_URL_POLICY_GENERATION'),
                    $request_policy_gen,
                    'edelweiss',
                    [
                        'enquiryId' => $request_data['enquiry_id'],
                        'requestMethod' => 'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'edelweiss',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Policy Generation',
                        'authorization'  => $token_data['access_token'],
                        'transaction_type' => 'proposal',
                    ]
                );
                $response_policy_gen = $get_response['response'];
                $response_policy_gen = json_decode($response_policy_gen, TRUE);
                if (isset($response_policy_gen['issuePolicyObject']['issuepolicy']['policynrTt'])) {
                    $policy_no = $response_policy_gen['issuePolicyObject']['issuepolicy']['policynrTt'];
                    $policy_no = ltrim($policy_no, '0');

                    $updateProposal = UserProposal::where('user_product_journey_id', $request_data['enquiry_id'])
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_no' => $policy_no,
                        ]);
                    $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                    $data['ic_id'] = $proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($data);

                    $payment_request_data = [
                        'itPay' => [
                            'itemList' => [
                                [
                                    'policyId'        => $policy_no,
                                    'bpId'            => config('IC.EDELWEISS.V1.CAR.MOTOR_BPID'), //constants.IcConstants.edelweiss.MOTOR_BPID //'1000012208',
                                    'paymentType'     => '39',
                                    'premAmt'         => $proposal->final_payable_amount,
                                    'paymentAmount'   => $proposal->final_payable_amount,
                                    'instrumentDate'  => date('Y-m-d'),
                                    'instrumentNo'    => $instrumentNo,//'PQMP6965903466',
                                    'valueDate'       => date('Y-m-d'),
                                    'receiptNo'       => '9439',
                                    'receiptDt'       => date('Y-m-d'),
                                    'pportalId'       => 'string',
                                ]
                            ]
                        ],
                        'ivIssuedBy' => 'CUST',
                    ];
                    //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PAYMENT_REQUEST
                    $get_response = getWsData(
                        config('IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_REQUEST'),
                        $payment_request_data,
                        'edelweiss',
                        [
                            'enquiryId' => $request_data['enquiry_id'],
                            'requestMethod' => 'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'edelweiss',
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Payment Request',
                            'authorization'  => $token_data['access_token'],
                            'transaction_type' => 'proposal',
                        ]
                    );
                    $payment_response_data = $get_response['response'];

                    if ($payment_response_data) {

                        $edelweiss_pdf_request = [
                            'pdfAttachmentList' => [
                                [
                                    'policyNumber' => $policy_no
                                ]
                            ]
                        ];

                        //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PDF_SERVICE
                        $get_response = getWsData(
                            config('IC.EDELWEISS.V1.CAR.END_POINT_URL_PDF_SERVICE'),
                            $edelweiss_pdf_request,
                            'edelweiss',
                            [
                                'enquiryId' => $request_data['enquiry_id'],
                                'requestMethod' => 'post',
                                'productName'  => $productData->product_name,
                                'company'  => 'edelweiss',
                                'section' => $productData->product_sub_type_code,
                                'method' => 'Pdf Service',
                                'authorization'  => $token_data['access_token'],
                                'transaction_type' => 'proposal',
                                'headers' => [
                                    "Content-type" => "application/json",
                                    "Authorization" => "Bearer " . $token_data['access_token'],
                                    "Accept" => "application/json",
                                    "x-api-key" => config('IC.EDELWEISS.V1.CAR.X_API_KEY'), //constants.IcConstants.edelweiss.EDELWEISS_X_API_KEY
                                ]
                            ]
                        );
                        $edelweiss_pdf_response = $get_response['response'];

                        $check_reposne = $edelweiss_pdf_response;
                        $check_reposne = json_decode($check_reposne, TRUE);
                        $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'edelweiss/' . md5($proposal->user_proposal_id) . '.pdf';
                        if (!is_array($check_reposne)) {
                            $pdf_data = Storage::put($pdf_name, base64_decode($edelweiss_pdf_response));
                            if ($pdf_data) {
                                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                                    'policy_number' => $policy_no,
                                    'premium' => $proposal->final_payable_amount,
                                    'policy_start_date' => $proposal->policy_start_date,
                                    'ic_pdf_url' => '',
                                    'pdf_url' => $pdf_name,
                                    'status' => 'SUCCESS'
                                ]);
                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                                ]);
                                $enquiryId = $proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                            } //pdfdata
                        } else {
                            PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                                'policy_number' => $policy_no,
                                'premium' => $proposal->final_payable_amount,
                                'policy_start_date' => $proposal->policy_start_date,
                                'status' => 'SUCCESS'
                            ]);
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            $enquiryId = $proposal->user_product_journey_id;
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                        }
                    } //payment ressponse
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['PAYMENT_FAILED'] 
                    ]);
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                } //policy genration req
                else {
                    $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                    $data['ic_id'] = $proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'] ;
                    updateJourneyStage($data);
                    
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }
            } //authcode
            
            $enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
            //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
        } else {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update(
                    [
                        'response' => implode(' ', $request_data),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => STAGE_NAMES['PAYMENT_FAILED']
                    ]
                );
            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            
            $enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
            //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
        }
    }

    static public function generatePdf($request)
    {
        $pdf_response_data = [
            'status' => false,
            'msg'    => 'Payment details data not found.'
        ];
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'prr.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->where('prr.user_product_journey_id', $user_product_journey_id)
            ->where(array('prr.active' => 1, 'prr.status' => STAGE_NAMES['PAYMENT_SUCCESS']))
            ->select(
                'up.user_proposal_id',
                'up.user_proposal_id',
                'up.proposal_no',
                'up.unique_proposal_id',
                'pd.policy_number',
                'pd.pdf_url',
                'pd.ic_pdf_url',
                'prr.order_id'
            )
            ->first();
        if ($policy_details == null) {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Payment details data not found.'
            ];
            return response()->json($pdf_response_data);
        }

        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $policyid = QuoteLog::where('user_product_journey_id', $user_product_journey_id)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policyid);
        $webUserId = config('IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME'); //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME
        $password = config('IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD'); //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD
        if (empty($proposal->policy_no)) {
            $check = self::policyGeneration($proposal, $productData);
            if (isset($check['status']) && !$check['status']) {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Policy Number Is Empty'
                ]);
            }
            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        }
        $get_response = getWsData(
            config('IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION'), //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION
            '',
            'edelweiss',
            [
                'enquiryId' => $user_product_journey_id,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'edelweiss',
                'section' => $productData->product_sub_type_code,
                'method' => 'Token genration',
                'userId' => $webUserId,
                'password' => $password,
                'type' => 'Token genration',
                'transaction_type' => 'proposal',
                'headers' => [
                    "Authorization" => "Basic " . base64_encode("$webUserId:$password"),
                    "Content-type" => "application/x-www-form-urlencoded"
                ]
            ]
        );
        $token_data = $get_response['response'];
        $token_data = json_decode($token_data, TRUE);

        self::add_policy_in_payment_slot($proposal, $productData, $token_data['access_token']);

        

        if ($policy_details->ic_pdf_url == '') {
            $edelweiss_pdf_request = [
                'pdfAttachmentList' => [
                    [
                        'policyNumber' => $proposal->policy_no
                    ]
                ]
            ];

            //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PDF_SERVICE
            $get_response = getWsData(
                config('IC.EDELWEISS.V1.CAR.END_POINT_URL_PDF_SERVICE'),
                $edelweiss_pdf_request,
                'edelweiss',
                [
                    'enquiryId' => $user_product_journey_id,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'edelweiss',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Pdf Service',
                    'authorization'  => $token_data['access_token'],
                    'transaction_type' => 'proposal',
                    'headers' => [
                        "Content-type" => "application/json",
                        "Authorization" => "Bearer " . $token_data['access_token'],
                        "Accept" => "application/json",
                        "x-api-key" => config('IC.EDELWEISS.V1.CAR.X_API_KEY'), //constants.IcConstants.edelweiss.EDELWEISS_X_API_KEY
                    ]
                ]
            );
            $edelweiss_pdf_response = $get_response['response'];
            $check_reposne = $edelweiss_pdf_response;
            $check_reposne = json_decode($check_reposne, TRUE);
            if (!is_array($check_reposne)) {
                $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'edelweiss/' . md5($proposal->user_proposal_id) . '.pdf';
                $pdf_data = Storage::put($pdf_name, base64_decode($edelweiss_pdf_response));

                if ($pdf_data) {
                    PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                        'policy_number' => $proposal->policy_no,
                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'edelweiss/' . md5($proposal->user_proposal_id) . '.pdf',
                        'status' => 'SUCCESS'
                    ]);
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);
                    $pdf_response_data = [
                        'status' => true,
                        'msg' => 'sucess',
                        'data' => [
                            'policy_number' => $proposal->policy_no,
                            'pdf_link'      => file_url($pdf_name)
                        ]
                    ];
                } else {
                    $pdf_response_data = [
                        'status' => false,
                        'msg'    => 'Error Occured while generating PDF',
                        'dev'    => $edelweiss_pdf_response
                    ];
                }
            } else {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => $check_reposne['message'] ?? 'Invalid response from IC PDF service. Please check logs for reference.',
                ];
            }
        }
        return response()->json($pdf_response_data);
    }

    public static function serverToServer($request)
    {
        $response_data   = $request;
        $response   = explode('|', $response_data['msg']);
        $order_id = $response['1'];
        $AuthStatus = $response['14'];
        $instrumentNo = $response['2'];
        $payment_data = PaymentRequestResponse::where('order_id', $order_id)
            ->first();
        if (empty($payment_data)) {
            return [
                'status' => false,
                'msg' => 'No Data Found'
            ];
        }
        $enquiry_id = $payment_data->user_product_journey_id;
        $policyid = QuoteLog::where('user_product_journey_id', $enquiry_id)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policyid);
        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
        $check_journey_stages = JourneyStage::where('user_product_journey_id', $enquiry_id)->first();
        $check_policy_no = PolicyDetails::where('proposal_id', $proposal->user_proposal_id)->first();

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);
        }

        if (isset($check_policy_no->policy_number) && !empty($check_policy_no->policy_number)) {
            return [
                'status' => true,
                'msg' => 'policy already issued'
            ];
        }
        if (in_array($check_journey_stages, [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])) {
            return [
                'status' => true,
                'msg' => 'policy already issued'
            ];
        }
        if (empty($proposal)) {
            return [
                'status' => false,
                'msg' => 'Proposal Data not Found'
            ];
        }
        if ($AuthStatus == '0300') {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update(
                    [
                        'response' => implode(' ', $response_data),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]
                );
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_start_date' => $proposal->policy_start_date,
                    'idv' => $proposal->idv,
                    'ncb' => $proposal->ncb_discount,
                    'premium' => $proposal->final_payable_amount
                ]
            );
            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($data);
            $get_response = getWsData(
                config('IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION'), //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION
                '',
                'edelweiss',
                [
                    'enquiryId' => $enquiry_id,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'edelweiss',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Token genration',
                    'userId' => config('IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME
                    'password' => config('IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD
                    'type' => 'Token genration',
                    'transaction_type' => 'proposal',
                ]
            );
            $token_data = $get_response['response'];
            $token_data = json_decode($token_data, TRUE);
            $request_policy_gen =
                [
                    'product' =>
                    [
                        'name' => 'EGICProductWebServicesV1',
                        'version' => '1',
                    ],
                    'policyRequest' =>
                    [
                        'issuePolicyList' =>
                        [
                            [
                                'issuePolicy' =>
                                [
                                    'quoteNo' => $proposal['proposal_no'],
                                    'quoteOptionNo' => $proposal['unique_quote']
                                ]
                            ]
                        ],
                        'ipContextInfo' =>
                        [
                            'productName' => 'String',
                            'productVersion' => '1',
                            'LeadID' => '',
                            'eKYCFlag' => 'Y'
                        ]
                    ]
                ];

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $request_policy_gen['policyRequest']['issuePolicyList'][0]['issuePolicy']['VISoF_KYC_Req_No'] = $ckyc_meta_data['VISoF_KYC_Req_No'];
                    $request_policy_gen['policyRequest']['issuePolicyList'][0]['issuePolicy']['IC_KYC_No'] = $ckyc_meta_data['IC_KYC_No'];
                }
//constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_POLICY_GENERATION
            $get_response = getWsData(
                config('IC.EDELWEISS.V1.CAR.END_POINT_URL_POLICY_GENERATION'),
                $request_policy_gen,
                'edelweiss',
                [
                    'enquiryId' => $enquiry_id,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'edelweiss',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Policy Generation',
                    'authorization'  => $token_data['access_token'],
                    'transaction_type' => 'proposal',
                ]
            );
            $response_policy_gen = $get_response['response'];
            $response_policy_gen = json_decode($response_policy_gen, TRUE);
            if (isset($response_policy_gen['issuePolicyObject']['issuepolicy']['policynrTt'])) {
                $policy_no = $response_policy_gen['issuePolicyObject']['issuepolicy']['policynrTt'];
                $policy_no = ltrim($policy_no, '0');

                $updateProposal = UserProposal::where('user_product_journey_id', $enquiry_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_no' => $policy_no,
                    ]);
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($data);

                $payment_request_data = [
                    'itPay' => [
                        'itemList' => [
                            [
                                'policyId'        => $policy_no,
                                'bpId'            => config('IC.EDELWEISS.V1.CAR.MOTOR_BPID'), //'1000012208', //constants.IcConstants.edelweiss.MOTOR_BPID
                                'paymentType'     => '39',
                                'premAmt'         => $proposal->final_payable_amount,
                                'paymentAmount'   => $proposal->final_payable_amount,
                                'instrumentDate'  => date('Y-m-d'),
                                'instrumentNo'    => $instrumentNo, //'PQMP6965903466',
                                'valueDate'       => date('Y-m-d'),
                                'receiptNo'       => '9439',
                                'receiptDt'       => date('Y-m-d'),
                                'pportalId'       => 'string',
                            ]
                        ]
                    ],
                    'ivIssuedBy' => 'CUST',
                ];
                //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PAYMENT_REQUEST
                $get_response = getWsData(
                    config('IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_REQUEST'),
                    $payment_request_data,
                    'edelweiss',
                    [
                        'enquiryId' => $enquiry_id,
                        'requestMethod' => 'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'edelweiss',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Payment Request',
                        'authorization'  => $token_data['access_token'],
                        'transaction_type' => 'proposal',
                    ]
                );
                $payment_response_data = $get_response['response'];

                if ($payment_response_data) {

                    $edelweiss_pdf_request = [
                        'pdfAttachmentList' => [
                            [
                                'policyNumber' => $policy_no
                            ]
                        ]
                    ];

                    //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PDF_SERVICE
                    $get_response = getWsData(
                        config('IC.EDELWEISS.V1.CAR.END_POINT_URL_PDF_SERVICE'),
                        $edelweiss_pdf_request,
                        'edelweiss',
                        [
                            'enquiryId' => $enquiry_id,
                            'requestMethod' => 'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'edelweiss',
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Pdf Service',
                            'authorization'  => $token_data['access_token'],
                            'transaction_type' => 'proposal',
                            'headers' => [
                                "Content-type" => "application/json",
                                "Authorization" => "Bearer " . $token_data['access_token'],
                                "Accept" => "application/json",
                                "x-api-key" => config('IC.EDELWEISS.V1.CAR.X_API_KEY'), //constants.IcConstants.edelweiss.EDELWEISS_X_API_KEY
                            ]
                        ]
                    );
                    $edelweiss_pdf_response = $get_response['response'];
                    $check_reposne = $edelweiss_pdf_response;
                    $check_reposne = json_decode($check_reposne, TRUE);
                    $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'edelweiss/' . md5($proposal->user_proposal_id) . '.pdf';
                    if (!is_array($check_reposne)) {
                        $pdf_data = Storage::put($pdf_name, base64_decode($edelweiss_pdf_response));
                        if ($pdf_data) {
                            PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                                'policy_number' => $policy_no,
                                'premium' => $proposal->final_payable_amount,
                                'policy_start_date' => $proposal->policy_start_date,
                                'ic_pdf_url' => '',
                                'pdf_url' => $pdf_name,
                                'status' => 'SUCCESS'
                            ]);
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);

                            return response()->json([
                                'status' => true,
                                'msg' => STAGE_NAMES['POLICY_ISSUED'],
                                'policy_no' => $policy_no,
                                'pdf_url' => Storage::url($pdf_name)
                            ]);
                        } //pdfdata
                    } else {
                        PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                            'policy_number' => $policy_no,
                            'premium' => $proposal->final_payable_amount,
                            'policy_start_date' => $proposal->policy_start_date,
                            'status' => 'SUCCESS'
                        ]);
                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);

                        return response()->json([
                            'status' => true,
                            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                            'policy_no' => $policy_no
                        ]);
                    }
                } //payment ressponse
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                return response()->json([
                'status' => true,
                'msg' => STAGE_NAMES['PAYMENT_FAILED'],
                'data' => $payment_response_data,
            ]);
            } //policy genration req
            else {
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
                updateJourneyStage($data);
                
                return response()->json([
                'status' => true,
                'msg' => STAGE_NAMES['PAYMENT_SUCCESS'],
                'data' => json_encode($response_policy_gen),
            ]);
            }
        } //authcode
        else {
            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);
            return response()->json([
                'status' => true,
                'msg' => STAGE_NAMES['PAYMENT_FAILED'],
                'data' => $response,
            ]);
        }
    }

    static public function add_policy_in_payment_slot($proposal, $productData, $token_data)
    {
        //constants.IcConstants.edelweiss.ENABLE_PAYMENT_LOT_CREATION
        if (config('IC.EDELWEISS.V1.CAR.PAYMENT_LOT_CREATION') != "Y") {
            return false;
        }
        
        try {
        $paymentDetails = PaymentRequestResponse::where([
            'active' => 1,
            'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'ic_id' => 43
        ])->first();
        $instrumentNo   = explode('|', $paymentDetails->response)[2];
        $payment_request_data = [
            'itPay' => [
                'itemList' => [
                    [
                        'policyId'        => $proposal->policy_no,
                        'bpId'            => config('IC.EDELWEISS.V1.CAR.MOTOR_BPID'), //'1000012208', //constants.IcConstants.edelweiss.MOTOR_BPID
                        'paymentType'     => '39',
                        'premAmt'         => $proposal->final_payable_amount,
                        'paymentAmount'   => $proposal->final_payable_amount,
                        'instrumentDate'  => date('Y-m-d',strtotime($paymentDetails->created_at)),
                        'instrumentNo'    => $instrumentNo,
                        'valueDate'       => date('Y-m-d',strtotime($paymentDetails->created_at)),
                        'receiptNo'       => '9439',
                        'receiptDt'       => date('Y-m-d',strtotime($paymentDetails->created_at)),
                        'pportalId'       => 'string',
                    ]
                ]
            ],
            'ivIssuedBy' => 'CUST',
        ];
        //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PAYMENT_REQUEST
        $get_response = getWsData(
            config('IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_REQUEST'),
            $payment_request_data,
            'edelweiss',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'edelweiss',
                'section' => $productData->product_sub_type_code,
                'method' => 'Payment Request',
                'authorization'  => $token_data,
                'transaction_type' => 'proposal',
            ]
        );
        $payment_response_data = $get_response['response'];
        $check_payment_response = json_decode($payment_response_data, TRUE);
        if (isset($check_payment_response['item'][1]['messageV1']) && $check_payment_response['item'][1]['messageV1'] == 'Item Added Successfully in Lot') {
            UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'additional_details_data'                 => json_encode($check_payment_response),
                ]);

            return [
                'status' => true,
                'msg'    => 'Item Added Successfully in Lot'
            ];
        } else {
            return [
                'status' => false,
                'msg'    => 'Error in payment Lot Service'
            ];
        }
        } catch (\Throwable $th) {
            Log::error($th);
            return [
                'status' => false,
                'msg'    =>$th->getMessage()
            ];
        }
    }

    public static function policyGeneration($proposal, $productData)
    {
        try {
            //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION
            $get_response = getWsData(
                config('IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION'),
                '',
                'edelweiss',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'edelweiss',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Token genration',
                    'userId' => config('IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME
                    'password' => config('IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD
                    'type' => 'Token genration',
                    'transaction_type' => 'proposal',
                ]
            );
            $tokenData = $get_response['response'];
            $tokenData = json_decode($tokenData, true);

            $policyRequest = [
                'product' =>
                [
                    'name' => 'EGICProductWebServicesV1',
                    'version' => '1',
                ],
                'policyRequest' =>
                [
                    'issuePolicyList' =>
                    [
                        [
                            'issuePolicy' =>
                            [
                                'quoteNo' => $proposal->proposal_no,
                                'quoteOptionNo' => $proposal->unique_quote
                            ]
                        ]
                    ],
                    'ipContextInfo' =>
                    [
                        'productName' => 'String',
                        'productVersion' => '1',
                        'LeadID' => '',
                        'eKYCFlag' => 'Y'
                    ]
                ]
            ];
    
            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);
                $policyRequest['policyRequest']['issuePolicyList'][0]['issuePolicy']['VISoF_KYC_Req_No'] = $ckyc_meta_data['VISoF_KYC_Req_No'];
                $policyRequest['policyRequest']['issuePolicyList'][0]['issuePolicy']['IC_KYC_No'] = $ckyc_meta_data['IC_KYC_No'];
            }
            //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_POLICY_GENERATION
            $getResponse = getWsData(
                config('IC.EDELWEISS.V1.CAR.END_POINT_URL_POLICY_GENERATION'),
                $policyRequest,
                'edelweiss',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'edelweiss',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Policy Generation',
                    'authorization'  => $tokenData['access_token'],
                    'transaction_type' => 'proposal',
                ]
            );
            $response = json_decode($getResponse['response'], true);
    
            if (isset($response['issuePolicyObject']['issuepolicy']['policynrTt'])) {
                $policyNo = $response['issuePolicyObject']['issuepolicy']['policynrTt'];
                $policyNo = ltrim($policyNo, '0');
    
                UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_no' => $policyNo,
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_start_date' => $proposal->policy_start_date,
                        'idv' => $proposal->idv,
                        'ncb' => $proposal->ncb_discount,
                        'premium' => $proposal->final_payable_amount,
                        'policy_number' => $policyNo
                    ]
                );
                return ['status' => true];
            }
            return ['status' => false];
        } catch (\Throwable $th) {
            return [
                'status' =>false
            ];
        }
    }
}
