<?php

namespace App\Http\Controllers\Payment\Services\Bike;

use App\Http\Controllers\Proposal\Services\Bike\iciciLombardSubmitProposal;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class iciciLombardPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $enquiryId = $request['userProductJourneyId'];
        $request['userProductJourneyId'] = customDecrypt($request['userProductJourneyId']);

        $proposalData = UserProposal::where('user_product_journey_id', $request['userProductJourneyId'])->first();
        $productData = getProductDataByIc($request['policyId']);

        $requestData = getQuotation($proposalData->user_product_journey_id);

        if ($requestData->is_renewal == 'Y' && $requestData->rollover_renewal != 'Y' && app('env') == 'local') {
            $renewalRequest = [
                'enquiryId' => $enquiryId,
                'policyId' => $request['policyId']
            ];
                $submitProposalResponse = iciciLombardSubmitProposal::renewalSubmit($proposalData, $renewalRequest);
            $submitProposalResponse = is_object($submitProposalResponse) ? $submitProposalResponse->original : $submitProposalResponse;

            if(!($submitProposalResponse['status'] ??  false))
            {
                return response()->json([
                    'status' => false,
                    'msg' => $submitProposalResponse['message'] ?? 'Failed to submit proposal'
                ]);
            }
        }

        /* $payment_status_data = self::update_pg_response($request['userProductJourneyId'],$proposalData['unique_proposal_id']);
        if($payment_status_data['status'] == 'true')
        {
            return response()->json([
                'status' => false,
                'msg' => $payment_status_data['message'],
            ]);
    
        } */
        
        $payemntTokenRequest = [
            "TransactionId" => getUUID($request['userProductJourneyId']),
            "Amount" => $proposalData['final_payable_amount'],
            "ApplicationId" => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_APPLICATION_ID'),
            "ReturnURL" => route('bike.payment-confirm', 'icici_lombard'),
            "AdditionalInfo1" => $proposalData['proposal_no'],
            "AdditionalInfo2" => $proposalData['customer_id'],
            "AdditionalInfo3" => str_replace("-", "", $proposalData['vehicale_registration_number']),
            "AdditionalInfo4" => $proposalData['mobile_number'],
            "AdditionalInfo5" => "",
            "AdditionalInfo6" => "",
            "AdditionalInfo7" => "",
            "AdditionalInfo8" => "",
            "AdditionalInfo9" => "",
            "AdditionalInfo10" => "",
            "AdditionalInfo11" => "",
            "AdditionalInfo12" => "",
            "AdditionalInfo13" => "",
            "AdditionalInfo14" => "",
            "AdditionalInfo15" => "",
        ];

        if (config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CUSTOM_TOKEN_REQUIRED_FOR_PAYMENT') == 'Y') {
            $payment_token_request = [
                'AuthType' => 'Custom',
                'ApplicationId' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_APPLICATION_ID'),
                'Username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_USERNAME'),
                'Password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CUSTOM_PAYMENT_PASSWORD'),
                'Key' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_KEY'),
                'IV' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_IV')
            ];

            $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_CUSTOM_TOKEN_GENERATION_URL'), $payment_token_request, 'icici_lombard', [
                'requestMethod' => 'post',
                'type' => 'customTokenForPayment',
                'section' => 'bike',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name,
                'enquiryId' => $request['userProductJourneyId'],
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            $payment_token_response = $get_response['response'];

            if ($payment_token_response) {
                $payment_token_response = json_decode($payment_token_response, true);

                if (isset($payment_token_response['AuthToken']) && ! empty($payment_token_response['AuthToken'])) {
                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_TOKEN_URL'), $payemntTokenRequest, 'icici_lombard', [
                        'requestMethod' => 'post',
                        'type' => 'paymentTokenGeneration',
                        'section' => 'bike',
                        'transaction_type' => 'proposal',
                        'productName'  => $productData->product_name,
                        'enquiryId' => $request['userProductJourneyId'],
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Basic ' . $payment_token_response['AuthToken']
                        ]
                    ]);
                    $premRecalculateResponse = $get_response['response'];
                } else {
                    return response()->json([
                        'status' => false,
                        'msg' => 'An error occurred while generating custom token'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'Insurer not reachable'
                ]);
            }
        } else {
            $additionPremData = [
                'requestMethod' => 'post',
                'type' => 'paymentTokenGeneration',
                'section' => 'bike',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name,
                'enquiryId' => $request['userProductJourneyId'],
                'userName' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_USERNAME'),
                'userPass' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_PASSWORD'),
            ];

            $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_TOKEN_URL'), $payemntTokenRequest, 'icici_lombard', $additionPremData);
            $premRecalculateResponse = $get_response['response'];
        }

        if (!empty($premRecalculateResponse)) {

            $quoteDetail = QuoteLog::where('user_product_journey_id', $request['userProductJourneyId'])
                ->select('quote_id')
                ->get();

            $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->select('insurance_company_id')
                ->get();

            $paymentUrl = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_URL') . str_replace('"', '', $premRecalculateResponse);
            
            PaymentRequestResponse::where('user_product_journey_id', $request['userProductJourneyId'])
                            ->update(['active' => 0]);

            PaymentRequestResponse::insert([
                'user_product_journey_id' => $request['userProductJourneyId'],
                'quote_id'                => $quoteDetail[0]->quote_id,
                'user_proposal_id'        => $proposalData->user_proposal_id,
                'ic_id'                   => $icId[0]->insurance_company_id,
                'payment_url'             => $paymentUrl,
                'order_id'                => $payemntTokenRequest['TransactionId'],
                'amount'                  => $proposalData->final_payable_amount,
                'return_url'              => route('bike.payment-confirm', 'icici_lombard'),
                'customer_id'             => $proposalData['customer_id'],
                'proposal_no'             => $proposalData['proposal_no'],
                'status'                  => STAGE_NAMES['PAYMENT_INITIATED'],
                'active'                  => 1
            ]);
            
            $data['user_product_journey_id'] = $proposalData->user_product_journey_id;
            $data['ic_id'] = $proposalData->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
            updateJourneyStage($data);

            return response()->json([
                'status' => true,
                'data' => [
                    "payment_type" => '1',
                    "paymentUrl" => $paymentUrl,
                ],
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => "Insurer not reachable",
            ]);
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        if(count($request->all()) == 1 && isset($request->enquiry_id)){
            updateJourneyStage([
                'user_product_journey_id' => $request->enquiry_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED'],
                'ic_id' => 40
            ]);
            //$enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($request->enquiry_id,'BIKE','FAILURE'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($request->enquiry_id)]));
        }
        if(empty($request->AdditionalInfo1))
        {
            return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $response = array(
            "TransactionId" => $request->TransactionId,
            "Amount" => $request->Amount,
            "MerchantId" => $request->MerchantId,
            "GatewayId" => $request->GatewayId,
            "GatewayName" => $request->GatewayName,
            "Success" => $request->Success,
            "AdditionalInfo1" => $request->AdditionalInfo1,
            "AdditionalInfo2" => $request->AdditionalInfo2,
            "AdditionalInfo3" => $request->AdditionalInfo3,
            "AdditionalInfo4" => $request->AdditionalInfo4,
            "AdditionalInfo5" => $request->AdditionalInfo5,
            "AdditionalInfo6" => $request->AdditionalInfo6,
            "AdditionalInfo7" => $request->AdditionalInfo7,
            "AdditionalInfo8" => $request->AdditionalInfo8,
            "AdditionalInfo9" => $request->AdditionalInfo9,
            "AdditionalInfo10" => $request->AdditionalInfo10,
            "AdditionalInfo11" => $request->AdditionalInfo11,
            "AdditionalInfo12" => $request->AdditionalInfo12,
            "AdditionalInfo13" => $request->AdditionalInfo13,
            "AdditionalInfo14" => $request->AdditionalInfo14,
            "AdditionalInfo15" => $request->AdditionalInfo15,
            "GatewayErrorCode" => $request->GatewayErrorCode,
            "GatewayErrorText" => $request->PGIMasterErrorCode,
            "PGIMasterErrorCode" => $request->PGIMasterErrorCode,
            "pgiUserErrorCode" => $request->pgiUserErrorCode,
            "AuthCode" => $request->AuthCode,
            "PGTransactionId" => $request->PGTransactionId,
            "PGTransactionDate" => $request->PGTransactionDate,
            "PGPaymentId" => $request->PGPaymentId,
            "OrderId" => $request->OrderId,
        );

        $response = json_encode($response, true);

        $userDetails = UserProposal::where('proposal_no', $request->AdditionalInfo1)->select('*')->get();
        if(empty($userDetails))
        {
            return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $pg_status = false;
        if(strtolower($request->Success) == 'true' && !empty($request->AuthCode)) {
            $pg_status = true;
        }
        PaymentRequestResponse::where('order_id', $request->TransactionId)->update([
            "status" => $pg_status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
            "response" => $response,
            "updated_at" => date('Y-m-d H:i:s')
        ]);

        if(!$pg_status) {
            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','FAILURE'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
        }

        if (isset($request->pgiUserErrorCode) && $request->pgiUserErrorCode == 'User_Cancelled') 
        {
            updateJourneyStage([
                'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED'],
                'ic_id' => 40
            ]);

            PaymentRequestResponse::where('user_product_journey_id',$userDetails[0]->user_product_journey_id)->update([
                "status" => STAGE_NAMES['PAYMENT_FAILED'],
                "response" => $request->All(),
                "updated_at" => date('Y-m-d H:i:s'),
            ]);
            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','FAILURE'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));

        }

        $master_policy_id = QuoteLog::where('user_product_journey_id', $userDetails[0]->user_product_journey_id)
            ->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);

        $premium_type_id = MasterPolicy::where('policy_id', $master_policy_id->master_policy_id)->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $premium_type_id->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }

        $requestData = getQuotation($userDetails[0]->user_product_journey_id);

        #for adding product code
        $ProductCode = '';

        switch($premium_type)
        {
            case "comprehensive":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
                $ProductCode = '2312';
            break;
            case "own_damage":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
                $ProductCode = '2312';
            break;
            case "third_party":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
                $ProductCode = '2320';
            break;

        }

        if ($requestData->business_type == 'breakin' && $premium_type != 'third_party') {
            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_BREAKIN');
            $ProductCode = '2312';
        }

        #for third party 
        if(($premium_type_array->premium_type_code ?? '') == 'third_party'){

            $ProductCode = '2320';
        }
        
        $tokenUrl = config('constants.IcConstants.icici_lombard.TRANSACTION_ENQUIRY_END_POINT_URL_ICICI_LOMBARD_MOTOR') . $request->TransactionId;

        if (config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CUSTOM_TOKEN_REQUIRED_FOR_PAYMENT') == 'Y') {
            $payment_token_request = [
                'AuthType' => 'Custom',
                'ApplicationId' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_APPLICATION_ID'),
                'Username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_USERNAME'),
                'Password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CUSTOM_PAYMENT_PASSWORD'),
                'Key' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_KEY'),
                'IV' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_IV')
            ];
        
            $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_CUSTOM_TOKEN_GENERATION_URL'), $payment_token_request, 'icici_lombard', [
                'requestMethod' => 'post',
                'type' => 'customTokenForPayment',
                'section' => 'bike',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name,
                'enquiryId' => $request['userProductJourneyId'],
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            $payment_token_response = $get_response['response'];
        
            if ($payment_token_response) {
                $payment_token_response = json_decode($payment_token_response, true);
        
                if (isset($payment_token_response['AuthToken']) && ! empty($payment_token_response['AuthToken'])) {
                    $additionalData = [
                        'requestMethod' => 'get',
                        'type' => 'transactionIdForPG',
                        'section' => 'bike',
                        'transaction_type' => 'proposal',
                        'productName'  => $productData->product_name,
                        'enquiryId' => $userDetails[0]->user_product_journey_id,
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Basic ' . $payment_token_response['AuthToken']
                        ]
                    ];
                }
            }

            if ( ! isset($additionalData)) {
                updateJourneyStage([
                    'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                    'ic_id' => 40
                ]);
                return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
            }
        } else {
            $additionalData = [
                'requestMethod' => 'get',
                'type' => 'transactionIdForPG',
                'section' => 'bike',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name,
                'enquiryId' => $userDetails[0]->user_product_journey_id,
            ];
        }

        $get_response = getWsData($tokenUrl, '', 'icici_lombard', $additionalData);
        $result = $get_response['response'];
        $response = json_decode($result, true);
        if (!empty($result) && isset($response['Status'])) 
        {
           

            if ($response['Status'] == '0' || $response['Status'] == 'True') 
            {

                
                $tokenParam = [
                    'grant_type' => 'password',
                    'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
                    'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
                    'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
                    'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
                    'scope' => 'esbpayment',
                ];


                $additionData = [
                    'requestMethod' => 'post',
                    'type' => 'tokenGeneration',
                    'section' => 'bike',
                    'transaction_type' => 'proposal',
                    'productName'  => $productData->product_name,
                    'enquiryId' => $userDetails[0]->user_product_journey_id,
                ];

                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                $token = $get_response['response'];
                $tokenResponse = json_decode($token, true);

                if (!empty($token) && isset($tokenResponse['access_token'])) 
                {
                    

                    $access_token = $tokenResponse['access_token'];

                    $paymentEntryRequestArr = [
                        'CorrelationId' => $userDetails[0]?->unique_proposal_id,//$request->TransactionId,#Proposal Page corelation id
                        // 'DealId' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID'),
                        //'DealId' => $deal_id,
                        'isTaggingRequired' => true,
                        'isMappingRequired' => true,
                        'PaymentEntry' => [
                            'onlineDAEntry' => [
                                'AuthCode' => $request->AuthCode,
                                'MerchantID' => $request->MerchantId,
                                'TransactionId' => $request->PGTransactionId,
                                'CustomerID' => $request->AdditionalInfo2,
                                'InstrumentDate' => date('d-m-Y'),
                                'ReceiptDate' => date('d-m-Y'),
                                'PaymentAmount' => $request->Amount,
                            ],
                        ],
                        'PaymentMapping' => [
                            'customerProposal' => [[
                                'CustomerID' => $request->AdditionalInfo2,
                                'ProposalNo' => $request->AdditionalInfo1,
                            ]],
                        ],
                        'PaymentTagging' => [
                            'customerProposal' => [[
                                'CustomerID' => $request->AdditionalInfo2,
                                'ProposalNo' => $request->AdditionalInfo1,
                            ]],
                        ],
                    ];

                    $additionData = [
                        'requestMethod' => 'post',
                        'type' => 'policyGeneration',
                        'token' => $access_token,
                        'section' => 'bike',
                        'transaction_type' => 'proposal',
                        'productName'  => $productData->product_name,
                        'enquiryId' => $userDetails[0]->user_product_journey_id,
                    ];
                    $IsPos = 'N';
                    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                    $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
                    $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo =  '';
                    $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id',$userDetails[0]->user_product_journey_id)
                        ->where('user_proposal_id',$userDetails[0]->user_proposal_id)
                        ->where('seller_type','P')
                        ->first();
                        
                    if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                    {

                        if($pos_data)
                        {
                            $IsPos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                            $CertificateNumber = $pos_data->unique_number;#$pos_data->user_name;
                            $PanCardNo = $pos_data->pan_no;
                            $AadhaarNo = $pos_data->aadhar_no;
                        }

                        if($pos_testing_mode === 'Y')
                        {
                            $IsPos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                            $CertificateNumber = 'TMI0001';
                            $PanCardNo = 'ABGTY8890Z';
                            $AadhaarNo = '569278616999';
                        }

                        if(config('ICICI_LOMBARD_IS_NON_POS') == 'Y')
                        {
                            $IsPos = 'N';
                            $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo  = '';
                            $paymentEntryRequestArr['DealId'] = $deal_id;
                        }
                        //$ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
                    }
                    elseif($pos_testing_mode === 'Y')
                    {
                        $IsPos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                        $CertificateNumber = 'TMI0001';
                        $PanCardNo = 'ABGTY8890Z';
                        $AadhaarNo = '569278616999';
                        //$ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
                    }
                    else
                    {
                        $paymentEntryRequestArr['DealId'] = $deal_id;
                    }

                    if($IsPos == 'Y')
                    {
                        if(isset($paymentEntryRequestArr['DealId']))
                        {
                            unset($paymentEntryRequestArr['DealId']);
                        }
                    }
                    else
                    {
                        if(!isset($paymentEntryRequestArr['DealId']))
                        {
                           $paymentEntryRequestArr['DealId'] = $deal_id;
                        }
                    }



                    if($IsPos == 'Y')
                    {
                        $pos_details = [
                            'pos_details' => [
                                'IRDALicenceNumber' => $IRDALicenceNumber,
                                'CertificateNumber' => $CertificateNumber,
                                'PanCardNo'         => $PanCardNo,
                                'AadhaarNo'         => $AadhaarNo,
                                'ProductCode'       => $ProductCode
                            ]
                        ];
                        $additionData = array_merge($additionData,$pos_details);
                    }

                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY'), $paymentEntryRequestArr, 'icici_lombard', $additionData);
                    $policyResponse = $get_response['response'];
                    $generatedPolicy = json_decode($policyResponse, true);
                    if (!empty($policyResponse) && isset($generatedPolicy['status'])) 
                    {
                       

                        if ($generatedPolicy['status'] == 'true' || $generatedPolicy['statusMessage'] == 'Success') 
                        {

                            if ($generatedPolicy['paymentTagResponse'] != 'null') 
                            {
                                if(strtolower($generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['status']) == 'success')
                                {
                                    $policyNo = $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['policyNo'];
                            
                                    $updateProposal = UserProposal::where('user_product_journey_id',  $userDetails[0]->user_product_journey_id)
                                        ->where('user_proposal_id',  $userDetails[0]->user_proposal_id)
                                        ->update([
                                            'policy_no'             =>trim($policyNo)
                                        ]);


                                    PolicyDetails::create([
                                        'proposal_id' => $userDetails[0]->user_proposal_id,
                                        'policy_number' => $policyNo,
                                        'idv' => '',
                                        'status' => 'SUCCESS',
                                        'policy_start_date' => $userDetails[0]->policy_start_date,
                                        'ncb' => null,
                                        'premium' => $userDetails[0]->final_payable_amount,
                                    ]);

                                    updateJourneyStage([
                                        'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                        'ic_id' => 40,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);

                                    $additionData = [
                                        'requestMethod' => 'post',
                                        'type' => 'tokenGeneration',
                                        'section' => 'bike',
                                        'transaction_type' => 'proposal',
                                        'productName'  => $productData->product_name,
                                        'enquiryId' => $userDetails[0]->user_product_journey_id,
                                    ];

                                    
                                    $tokenParam = [
                                        'grant_type' => 'password',
                                        'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
                                        'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
                                        'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
                                        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
                                        'scope' => 'esbpolicypdf',#token for pdf
                                    ];


                                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                                    $tokenforPdfGen = $get_response['response'];
                                    $generatedtoken = json_decode($tokenforPdfGen, true);
                                    if (!empty($tokenforPdfGen) && isset($generatedtoken['access_token'])) 
                                    {
                                        
                                        $access_token = $generatedtoken['access_token'];

                                        $policypdfdata=[
                                            "CorrelationId" => $userDetails[0]?->unique_proposal_id,
                                            "policyNo"      => $policyNo,
                                            #"DealId"        => $deal_id,
                                            "customerId"    =>$request->AdditionalInfo2,
                                         ];
                                        
                                        $policyPdfUrl =config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY_PDF');
                                        $additionData = [
                                            'requestMethod' => 'post',
                                            'type' => 'policyPdfGeneration',
                                            'section' => 'bike',
                                            'transaction_type' => 'proposal',
                                            'token' => $access_token,
                                            'enquiryId' => $userDetails[0]->user_product_journey_id,
                                            'productName'  => $productData->product_name,
                                        ];

                                        $additionData = [
                                            'requestMethod' => 'post',
                                            'type' => 'policyPdfGeneration',
                                            'section' => 'bike',
                                            'token' => $access_token,
                                            'enquiryId' => $userDetails[0]->user_product_journey_id,
                                            'transaction_type' => 'proposal',
                                            'productName'  => $productData->product_name,
                                        ];
                                        /* if($IsPos == 'Y')
                                        {
                                            unset($policypdfdata['DealId']);
                                            $pos_details = [
                                                'pos_details' => [
                                                    'IRDALicenceNumber' => $IRDALicenceNumber,
                                                    'CertificateNumber' => $CertificateNumber,
                                                    'PanCardNo'         => $PanCardNo,
                                                    'AadhaarNo'         => $AadhaarNo,
                                                    'ProductCode'       => $ProductCode
                                                ]
                                            ];
                                            $additionData = array_merge($additionData,$pos_details);
                                        } */
                                        $get_response = getWsData($policyPdfUrl, $policypdfdata, 'icici_lombard', $additionData);
                                        $pdfGenerationResponse = $get_response['response'];

                                        if(preg_match("/^%PDF-/", $pdfGenerationResponse))
                                        {

                                            $proposal_pdf = Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'icici_lombard/' . md5($userDetails[0]->user_proposal_id) . '.pdf', $pdfGenerationResponse);

                                            PolicyDetails::where('proposal_id', $userDetails[0]->user_proposal_id)
                                                ->update([
                                                    'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'icici_lombard/' . md5($userDetails[0]->user_proposal_id) . '.pdf',
                                                ]);

                                            updateJourneyStage([
                                                'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED'],
                                                'ic_id' => 40
                                            ]);
                                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                                            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));

                                        } else {

                                            updateJourneyStage([
                                                'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                                'ic_id' => 40
                                            ]);
                                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                                            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                                        }
                                    } 
                                    else 
                                    {

                                        updateJourneyStage([
                                                'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                                'ic_id' => 40
                                            ]);
                                        return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                                        //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                                    }

                                }
                                else
                                {


                                    if(isset($generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText']))
                                    {
                                        $already_str = $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText'];
                                        $contains = Str::contains($already_str, 'and policy number is : ');
                                        if($contains)
                                        {
                                            $p = explode('policy number is :', $already_str);
                                            $policyNo = $p[1];

                                            PolicyDetails::updateOrCreate(['proposal_id' => $userDetails[0]->user_proposal_id],
                                            [
                                                'policy_number'             => $policyNo,
                                                'status' => 'SUCCESS'
                                            ]);

                                            $updateProposal = UserProposal::where('user_product_journey_id',$userDetails[0]->user_product_journey_id)
                                            ->where('user_proposal_id', $userDetails[0]->user_proposal_id)
                                            ->update([
                                                'policy_no'             => $policyNo
                                            ]);

                                            updateJourneyStage([
                                                        'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                                        'ic_id' => 40
                                                    ]);
                                            $response = Self::retry_pdf($userDetails[0]); 
                                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                                            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));

                                        } else {
                                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                                            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                                        }
                                    }
                                    else
                                    {
                                        return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                                        //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                                    }

                                }
                            } 
                            else 
                            {
                                return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                                //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                            }
                        } 
                        else 
                        {
                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                        }
                    } 
                    else 
                    {
                        return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                       // return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                    }
                } 
                else 
                {
                    return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','SUCCESS'));
                    //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
                }
            }
            else
            {
                PaymentRequestResponse::where('user_product_journey_id', $userDetails[0]->user_product_journey_id)
                ->where('active', 1)
                ->update([
                    "status" => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                updateJourneyStage([
                    'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED'],
                    'ic_id' => 40
                ]);
                return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','FAILURE'));
                //return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
            }
        } 
        else 
        {
            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id,'BIKE','FAILURE'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
            return response()->json([
                'status' => false,
                'msg' => "Insurer not reachable. No response received from Transaction Enquiry service",
            ]);
        }

    }

    static public function retry_pdf($user_proposal)
    {

        $policy_details = PolicyDetails::where('proposal_id', $user_proposal->user_proposal_id)->first();
        $user_details = UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();
        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->first();
        $premium_type_id = MasterPolicy::where('policy_id', $master_policy_id->master_policy_id)->first();
        $IsPos = 'N';
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
        $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = $ProductCode = '';
        $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id',$user_details->user_product_journey_id)
                        ->where('user_proposal_id',$user_details->user_proposal_id)
                        ->where('seller_type','P')
                        ->first();
        $premium_type = DB::table('master_premium_type')
            ->where('id', $premium_type_id->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }


        switch($premium_type)
        {
            case "comprehensive":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
            break;
            case "own_damage":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
            break;
            case "third_party":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
            break;

        }
        $productData = getProductDataByIc($master_policy_id->master_policy_id);
        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
            'scope' => 'esbpolicypdf',#pdftoken
        ];


        $additionData = [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'bike',
            'enquiryId' => $user_proposal->user_product_journey_id,
        ];
       
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
        $token = json_decode($token, true);

        $additionData = [
            'requestMethod' => 'post',
            'type' => 'policyPdfGeneration',
            'section' => 'bike',
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name,
            'token' => $token['access_token'],
            'enquiryId' => $user_proposal->user_product_journey_id,
        ];

        $policyPdfUrl = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY_PDF');

        #sleep(2);
        $policypdfdata=[
            "CorrelationId" => $user_details?->unique_proposal_id,
            "policyNo"      => trim($user_details->policy_no),
            #"DealId"        => $deal_id,
            "customerId"    =>$user_details->customer_id,
         ];
         if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {

                if($pos_data)
                {
                    $IsPos = 'Y';
                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                    $CertificateNumber = $pos_data->unique_number;#$pos_data->user_name;
                    $PanCardNo = $pos_data->pan_no;
                    $AadhaarNo = $pos_data->aadhar_no;
                }

                if($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                    $CertificateNumber = 'TMI0001';
                    $PanCardNo = 'ABGTY8890Z';
                    $AadhaarNo = '569278616999';
                }
                $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
            }
            elseif($pos_testing_mode === 'Y')
            {
                $IsPos = 'Y';
                $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                $CertificateNumber = 'TMI0001';
                $PanCardNo = 'ABGTY8890Z';
                $AadhaarNo = '569278616999';
                $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
            }
         /* if($IsPos == 'Y')
            {
                unset($policypdfdata['DealId']);
                $pos_details = [
                    'pos_details' => [
                        'IRDALicenceNumber' => $IRDALicenceNumber,
                        'CertificateNumber' => $CertificateNumber,
                        'PanCardNo'         => $PanCardNo,
                        'AadhaarNo'         => $AadhaarNo,
                        'ProductCode'       => $ProductCode
                    ]
                ];
                $additionData = array_merge($additionData,$pos_details);
            } */
        $get_response = getWsData($policyPdfUrl, $policypdfdata, 'icici_lombard', $additionData);
        $pdfGenerationResponse = $get_response['response'];
        
        if(preg_match("/^%PDF-/", $pdfGenerationResponse))
        {
            Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'icici_lombard/' . md5($user_proposal->user_proposal_id) . '.pdf', $pdfGenerationResponse);

            PolicyDetails::where('proposal_id', $user_proposal->user_proposal_id)
              ->update([
                'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'icici_lombard/' . md5($user_proposal->user_proposal_id) . '.pdf',
             ]);
             updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED'],
                'ic_id' => 40
            ]);

           return response()->json([
                'status' => true,
                'msg' => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data' => [
                    'pdf_link' => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'icici_lombard/' . md5($user_proposal->user_proposal_id) . '.pdf'),
                    "policy_number" => trim($user_details->policy_no),
                ]
            ]);

        }
        else 
        {
            return response()->json([
                'status' => false,
                'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                'data' => [
                    'pdf_link' => '',
                ]
            ]);

        }

        


    }

    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $requestData = getQuotation($user_product_journey_id);

//        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_product_journey_id)
//            ->first();

        $productData = getProductDataByIc($request->master_policy_id);

        $policy_details = DB::table('payment_request_response as prr')
                      ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
                      ->leftjoin('policy_details as pd','pd.proposal_id','=','up.user_proposal_id')
                      ->where('prr.user_product_journey_id',$user_product_journey_id)
                      ->where('prr.active',1)
                      ->select(
                        'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                        'pd.policy_number','pd.pdf_url','pd.ic_pdf_url',
                        )
                      ->first();
//        print_r($policy_details);
//        die;
//        if(empty($policy_details->response))
//        {
//            $payment_status_data = self::update_pg_response($user_product_journey_id,$policy_details->unique_proposal_id);
//            if($payment_status_data['status'] == 'false')
//            {
//                $pdf_response_data = [
//                    'status' => false,
//                    'msg'    => $payment_status_data['message'] ,
//                    'data'   => []
//              ];
//               return response()->json($pdf_response_data);
//            }
//            
//        }
        
        if($policy_details->pdf_url != '')
        {
             $pdf_response_data = [
                'status' => true,
                'msg' => 'sucess',
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => file_url($policy_details->pdf_url)
                ]
            ];
            return response()->json($pdf_response_data);
        }
        elseif($policy_details->policy_number != '')
        {
            try 
            {
                $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $response = Self::retry_pdf($proposal);
                return $response;
            } 
            catch (\Exception $e) 
            {

                return [
                    'status' => false,
                    'msg' => 'Error Occured',
                    'dev' => $e->getMessage(),
                    'Line_No'=> $e->getLine(),
                ];
            }
        }
        else
        {
            try 
            {
                $master_policy_id = QuoteLog::where('user_product_journey_id', $user_product_journey_id)
                    ->first();
                    
                $premium_type_id = MasterPolicy::where('policy_id', $master_policy_id->master_policy_id)->first();

                $premium_type = DB::table('master_premium_type')
                    ->where('id', $premium_type_id->premium_type_id)
                    ->pluck('premium_type_code')
                    ->first();

                if($premium_type == 'breakin')
                {
                    $premium_type = 'comprehensive';
                }
                if($premium_type == 'third_party_breakin')
                {
                    $premium_type = 'third_party';
                }
                if($premium_type == 'own_damage_breakin')
                {
                    $premium_type = 'own_damage';
                }
                
                #for adding product code
                $ProductCode = '';

                switch($premium_type)
                {
                    case "comprehensive":
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
                        $ProductCode = '2312';
                    break;
                    case "own_damage":
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
                        $ProductCode = '2312';
                    break;
                    case "third_party":
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
                        $ProductCode = '2320';
                    break;

                }
                
                if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
                {
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_BREAKIN');
                    $ProductCode = '2312';
                }

                #for third party 
                if (($premium_type_array->premium_type_code ?? '') == 'third_party') {

                    $ProductCode = '2320';
                }

                $tokenParam = [
                    'grant_type' => 'password',
                    'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
                    'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
                    'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
                    'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
                    'scope' => 'esbpayment',
                ];

                $additionData = [
                    'requestMethod' => 'post',
                    'type' => 'tokenGeneration',
                    'section' => 'bike',
                    'transaction_type' => 'proposal',
                    'productName'  => $productData->product_name,
                    'enquiryId' => $user_product_journey_id
                ];

                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                $token = $get_response['response'];
                
                $tokenResponse = json_decode($token, true);
        
                 if (!empty($token) && isset($tokenResponse['access_token'])) 
                 {
                    #verifyPaymentResponse
                    $updated_pg_response = self::update_pg_response($user_product_journey_id,$policy_details->unique_proposal_id);
                    if (!$updated_pg_response['status']) {
                        $pdf_response_data = [
                            'status' => false,
                            'msg'    => 'Payment Pending'
                      ];
                       return response()->json($pdf_response_data);
                    }

                    if ($updated_pg_response['step'] == 'backward') {
                        $idd = $updated_pg_response['id'];
                        $payment_response = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                            ->where('id', $idd)
                            ->first();
                        $payment_date = date("d-m-Y", strtotime( $payment_response->created_at));
                        $response_data = json_decode($payment_response->response, true);
                    }

                    $access_token = $tokenResponse['access_token'];

                    $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                    /* $payment_response = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->first();

                    $response_data = json_decode($payment_response->response, true); */


                    $paymentEntryRequestArr = [
                        'CorrelationId' => $proposal->unique_proposal_id,
                        // 'DealId' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID'),
                        //'DealId' => $deal_id,
                        'isTaggingRequired' => true,
                        'isMappingRequired' => true,
                        'PaymentEntry' => [
                            'onlineDAEntry' => [
                                'AuthCode' => $response_data['AuthCode'],
                                'MerchantID' => $response_data['MerchantId'],
                                'TransactionId' => $response_data['PGTransactionId'],
                                'CustomerID' =>$response_data['AdditionalInfo2'],
                                'InstrumentDate' => $payment_date,#date('d-m-Y'),
                                'ReceiptDate' => date('d-m-Y'),
                                'PaymentAmount' => $response_data['Amount'],
                            ],
                        ],
                        'PaymentMapping' => [
                            'customerProposal' => [[
                                'CustomerID' => $response_data['AdditionalInfo2'],
                                'ProposalNo' => $response_data['AdditionalInfo1'],
                            ]],
                        ],
                        'PaymentTagging' => [
                            'customerProposal' => [[
                                'CustomerID' => $response_data['AdditionalInfo2'],
                                'ProposalNo' => $response_data['AdditionalInfo1'],
                            ]],
                        ],
                    ];

                    $additionData = [
                        'requestMethod' => 'post',
                        'type' => 'policyGeneration',
                        'token' => $access_token,
                        'section' => 'bike',
                        'transaction_type' => 'proposal',
                        'productName'  => $productData->product_name,
                        'enquiryId' => $user_product_journey_id,
                    ];
                    $IsPos = 'N';
                    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                    $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
                    $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo =  '';
                    $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id',$proposal->user_product_journey_id)
                        ->where('user_proposal_id',$proposal->user_proposal_id)
                        ->where('seller_type','P')
                        ->first();
                        
                    if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                    {

                        if($pos_data)
                        {
                            $IsPos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                            $CertificateNumber = $pos_data->unique_number;#$pos_data->user_name;
                            $PanCardNo = $pos_data->pan_no;
                            $AadhaarNo = $pos_data->aadhar_no;
                        }

                        if($pos_testing_mode === 'Y')
                        {
                            $IsPos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                            $CertificateNumber = 'TMI0001';
                            $PanCardNo = 'ABGTY8890Z';
                            $AadhaarNo = '569278616999';
                        }
                        if(config('ICICI_LOMBARD_IS_NON_POS') == 'Y')
                        {
                            $IsPos = 'N';
                            $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo =  '';
                        }
                        //$ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
                    }
                    elseif($pos_testing_mode === 'Y')
                    {
                        $IsPos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                        $CertificateNumber = 'TMI0001';
                        $PanCardNo = 'ABGTY8890Z';
                        $AadhaarNo = '569278616999';
                     // $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
                    }
                    else
                    {
                        $paymentEntryRequestArr['DealId'] = $deal_id;
                    }

                    if($IsPos == 'Y')
                    {
                        if(isset($paymentEntryRequestArr['DealId']))
                        {
                            unset($paymentEntryRequestArr['DealId']);
                        }
                    }
                    else
                    {
                        if(!isset($paymentEntryRequestArr['DealId']))
                        {
                           $paymentEntryRequestArr['DealId'] = $deal_id;
                        }
                    }



                    if($IsPos == 'Y')
                    {
                        $pos_details = [
                            'pos_details' => [
                                'IRDALicenceNumber' => $IRDALicenceNumber,
                                'CertificateNumber' => $CertificateNumber,
                                'PanCardNo'         => $PanCardNo,
                                'AadhaarNo'         => $AadhaarNo,
                                'ProductCode'       => $ProductCode
                            ]
                        ];
                        $additionData = array_merge($additionData,$pos_details);
                    }

                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY'), $paymentEntryRequestArr, 'icici_lombard', $additionData);
                    $policyResponse = $get_response['response'];
                    if (!empty($policyResponse)) 
                    {
                        $generatedPolicy = json_decode($policyResponse, true);

                        if (!isset($generatedPolicy['status'])) {
                            return response()->json([
                                'status' => false,
                                'msg' => 'Issue in policy number generation service.',
                                'error' => 'Issue in policy number generation service.'
                                ]
                            );
                        }

                        if ($generatedPolicy['status'] == 'true' || $generatedPolicy['statusMessage'] == 'Success') 
                        {
                            
                            if ($generatedPolicy['paymentTagResponse'] != 'null') 
                            {
                                if(strtolower($generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['status']) == 'success')
                                {

                                    $policyNo = $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['policyNo'];
                                    PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id],
                                    [
                                                'policy_number'             => $policyNo
                                    ]);


                                    $updateProposal = UserProposal::where('user_product_journey_id',  $user_product_journey_id)
                                    ->where('user_proposal_id',  $proposal->user_proposal_id)
                                    ->update([
                                        'policy_no'             => $policyNo,
                                        'status' => 'SUCCESS'
                                    ]);

                                    updateJourneyStage([
                                                'user_product_journey_id' => $user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                                'ic_id' => 40
                                            ]);

                                    $additionData = [
                                        'requestMethod' => 'post',
                                        'type' => 'tokenGeneration',
                                        'section' => 'bike',
                                        'transaction_type' => 'proposal',
                                        'productName'  => $productData->product_name,
                                        'enquiryId' => $user_product_journey_id,
                                    ];

                                    $tokenParam = [
                                        'grant_type' => 'password',
                                        'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
                                        'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
                                        'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
                                        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
                                        'scope' => 'esbpolicypdf',#pdftoken
                                    ];


                                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                                    $tokenforPdfGen = $get_response['response'];
                                    $generatedtoken = json_decode($tokenforPdfGen, true);
                                    if (!empty($tokenforPdfGen) && isset($generatedtoken['access_token']))
                                    {
                                        
                                        $access_token = $generatedtoken['access_token'];

                                        $policyPdfUrl = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY_PDF');

                                        $additionData = [
                                            'requestMethod' => 'post',
                                            'type' => 'policyPdfGeneration',
                                            'section' => 'bike',
                                            'transaction_type' => 'proposal',
                                            'productName'  => $productData->product_name,
                                            'token' => $access_token,
                                            'enquiryId' => $user_product_journey_id,
                                        ];

                                        #sleep(2);
                                        $policypdfdata=[
                                            "CorrelationId" => $proposal->unique_proposal_id,
                                            "policyNo"      => $policyNo,
                                            #"DealId"        => $deal_id,
                                            "customerId"    =>$response_data['AdditionalInfo2'],
                                         ];
                                         /* if($IsPos == 'Y')
                                        {
                                            unset($policypdfdata['DealId']);
                                            $pos_details = [
                                                'pos_details' => [
                                                    'IRDALicenceNumber' => $IRDALicenceNumber,
                                                    'CertificateNumber' => $CertificateNumber,
                                                    'PanCardNo'         => $PanCardNo,
                                                    'AadhaarNo'         => $AadhaarNo,
                                                    'ProductCode'       => $ProductCode
                                                ]
                                            ];
                                            $additionData = array_merge($additionData,$pos_details);
                                        } */
                                        $get_response = getWsData($policyPdfUrl,$policypdfdata, 'icici_lombard', $additionData);
                                        $pdfGenerationResponse = $get_response['response'];

                                        if(preg_match("/^%PDF-/", $pdfGenerationResponse))
                                        {
                                            $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'icici_lombard/' . md5($proposal->user_proposal_id) . '.pdf';
                                            Storage::put($pdf_name, $pdfGenerationResponse);

                                            PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                                ->update([
                                                    'pdf_url' => $pdf_name,
                                                ]);

                                            updateJourneyStage([
                                                'user_product_journey_id' => $user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED'],
                                                'ic_id' => 40
                                            ]);
                                            

                                            $pdf_response_data = [
                                            'status' => true,
                                            'msg' => 'sucess',
                                            'data' => [
                                                    'policy_number' => $policyNo,
                                                    'pdf_link'      => file_url($pdf_name)
                                                ]
                                            ];

                                        } 
                                        else 
                                        {

                                            updateJourneyStage([
                                                'user_product_journey_id' => $user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                                'ic_id' => 40
                                            ]);

                                           $pdf_response_data = [
                                            'status' => false,
                                            'msg' => 'Issue in PDF generation service',
                                            'data' => [
                                                    'policy_number' => $policyNo,
                                                    'pdf_link'      => ''
                                                ]
                                            ];
                                            
                                        }
                                    } 
                                    else 
                                    {
                                        updateJourneyStage([
                                                'user_product_journey_id' => $user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                                'ic_id' => 40
                                            ]);

                                        $pdf_response_data = [
                                            'status' => false,
                                            'msg' => 'Issue in creating token for PDF generation',
                                            'data' => [
                                                    'policy_number' => $policyNo,
                                                    'pdf_link'      => ''
                                                ]
                                            ];
                                    }

                                }
                                else
                                {
                                    if(isset($generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText']))
                                    {
                                        $already_str = $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText'];
                                        $contains = Str::contains($already_str, 'and policy number is : ');
                                        if($contains)
                                        {
                                            $p = explode('policy number is :', $already_str);
                                            $policyNo = $p[1];

                                            PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id],
                                            [
                                                'policy_number'             => $policyNo,
                                                'status' => 'SUCCESS'
                                            ]);

                                            $updateProposal = UserProposal::where('user_product_journey_id',  $user_product_journey_id)
                                            ->where('user_proposal_id',  $proposal->user_proposal_id)
                                            ->update([
                                                'policy_no'             => $policyNo
                                            ]);

                                            updateJourneyStage([
                                                        'user_product_journey_id' => $user_product_journey_id,
                                                        'stage' => STAGE_NAMES['POLICY_ISSUED'],
                                                        'ic_id' => 40
                                                    ]);
                                            $response = Self::retry_pdf($proposal);
                                            return $response;

                                        }
                                        else
                                        {
                                            $pdf_response_data = 
                                            [
                                                    'status' => false,
                                                    'msg' => "Error message from policy Generation service :-" . 
                                                    isset($generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText']) ? $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText'] :'',
                                                    'error' => "Error message from policy Generation service :-" . 
                                                    isset($generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText']) ? $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['errorText'] :''
                                                    ];

                                        }
                                    }
                                    else
                                    {
                                        $pdf_response_data = 
                                        [
                                                'status' => false,
                                                'msg' => 'Issue in policy number generation service',
                                                'error' => 'Issue in policy number generation service'
                                                ];
                                    }
                                    
                                     

                                   

                                }
                                
                            } 
                            else 
                            {
                                $pdf_response_data = 
                                    [
                                            'status' => false,
                                            'msg' => 'Issue in policy number generation service',
                                            'error' => 'Issue in policy number generation service'
                                            ];
                            }
                        }
                        else 
                        {
                            $pdf_response_data = 
                            [
                                    'status' => false,
                                    'msg' => 'Issue in policy number generation service1',
                                    'error' => 'Issue in policy number generation service1'
                                    ];
                        }
                    } 
                    else 
                    {
                        $pdf_response_data = 
                        [
                                'status' => false,
                                'msg' => 'No response received from policy number generation service',
                                'error' => 'No response received from policy number generation service'
                                ];
                    }
                } 
                else 
                {
                    $pdf_response_data = 
                        [
                                'status' => false,
                                'msg' => 'No response received from policy Token Generation service',
                                'error' => 'No response received from policy Token Generation service'
                                ];
                }
                return response()->json($pdf_response_data);

            } 
            catch (\Exception $e) 
            {

                return [
                    'status' => false,
                    'msg'    => 'Error Occured',
                    'dev'    => $e->getMessage(),
                    'Line_No'=> $e->getLine(),
                    'data'   => []
                ];

            }
        }

    }

    static public function update_pg_response($enquiry_id,$correlation_id)
    {
        $user_product_journey_id = $enquiry_id;
        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_product_journey_id)
            ->first();

        $productData = getProductDataByIc($master_policy_id->master_policy_id);
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $payment_response = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                          ->get();
        $additionalData = [
            'requestMethod' => 'get',
            'type' => 'transactionIdForPG',
            'section' => 'bike',
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name,
            'enquiryId' => $user_product_journey_id,
        ];
        foreach ($payment_response as $key => $value) 
        {
            if (config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CUSTOM_TOKEN_REQUIRED_FOR_PAYMENT') == 'Y') {
                $paymentTokenRequest = [
                    'AuthType' => 'Custom',
                    'ApplicationId' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_APPLICATION_ID'),
                    'Username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_USERNAME'),
                    'Password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CUSTOM_PAYMENT_PASSWORD'),
                    'Key' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_KEY'),
                    'IV' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_IV')
                ];

                $getResponse = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_CUSTOM_TOKEN_GENERATION_URL'), $paymentTokenRequest, 'icici_lombard', [
                    'requestMethod' => 'post',
                    'type' => 'customTokenForPayment',
                    'section' => 'bike',
                    'transaction_type' => 'proposal',
                    'productName'  => $productData->product_name,
                    'enquiryId' => $user_product_journey_id,
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ]
                ]);
                $paymentTokenResponse = $getResponse['response'];

                if ($paymentTokenResponse) {
                    $paymentTokenResponse = json_decode($paymentTokenResponse, true);

                    if (isset($paymentTokenResponse['AuthToken']) && !empty($paymentTokenResponse['AuthToken'])) {
                        $additionalData = [
                            'requestMethod' => 'get',
                            'type' => 'transactionIdForPG',
                            'section' => 'bike',
                            'transaction_type' => 'proposal',
                            'productName'  => $productData->product_name,
                            'enquiryId' => $user_product_journey_id,
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Basic ' . $paymentTokenResponse['AuthToken']
                            ]
                        ];
                    } else {
                        return ['status' => false];
                    }
                } else {
                    return ['status' => false];
                }
            }
        $tokenUrl = config('constants.IcConstants.icici_lombard.TRANSACTION_ENQUIRY_END_POINT_URL_ICICI_LOMBARD_MOTOR') . $value->order_id;
        $get_response = getWsData($tokenUrl, '', 'icici_lombard', $additionalData);
        $result = $get_response['response'];
        if (!empty($result)){
            $response = json_decode($result, true);
                if (isset($response['Status']) && ($response['Status'] == '0' || $response['Status'] == 'True')) 
                {
                    $payment_response = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                        ->where('id', $value->id)
                        ->first();
                    $proposal_detail = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

                    $response_data = json_decode($payment_response->response, true);
                    /* if (!$response_data) 
                    { */

                        $updatePaymentResponse = [
                            "TransactionId" => $value->id,
                            "Amount" => $payment_response->amount,
                            "MerchantId" => $response['MerchantId'],
                            "GatewayId" => $response['GatewayId'],
                            "GatewayName" => $response['GatewayName'],
                            "Success" => "True",
                            "AdditionalInfo1" => $payment_response->proposal_no,
                            "AdditionalInfo2" => $payment_response->customer_id,
                            "AdditionalInfo3" => str_replace('-', '', $proposal_detail->vehicale_registration_number),
                            "AdditionalInfo4" => $proposal_detail->mobile_number,
                            "AdditionalInfo5" => null,
                            "AdditionalInfo6" => null,
                            "AdditionalInfo7" => null,
                            "AdditionalInfo8" => null,
                            "AdditionalInfo9" => null,
                            "AdditionalInfo10" => null,
                            "AdditionalInfo11" => null,
                            "AdditionalInfo12" => null,
                            "AdditionalInfo13" => null,
                            "AdditionalInfo14" => null,
                            "AdditionalInfo15" => null,
                            "GatewayErrorCode" => null,
                            "GatewayErrorText" => null,
                            "PGIMasterErrorCode" => null,
                            "pgiUserErrorCode" => null,
                            "AuthCode" => $response['AuthCode'],
                            "PGTransactionId" => $response['PGtransactionId'],
                            "PGTransactionDate" => null,
                            "PGPaymentId" => null,
                            "OrderId" => null
                        ];

                        $updatePaymentResponse = json_encode($updatePaymentResponse);
                        PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                            ->update([
                                'active'  => 0
                            ]);

                        PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                            ->where('id', $value->id)
                            ->update([
                                'response' => $updatePaymentResponse,
                                'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'active'  => 1
                            ]);
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'ic_id' => 40
                        ]);
                    /* } */
                    return [
                        'status' => true,
                        'id' => $value->id,
                        'step' => 'backward',
                    ];
                }
            }
        
        }
        return [
            'status' => false
        ];
        
        /* if ($response['Status'] == '0' || $response['Status'] == 'True')
        {
            $updateResponseFromIc = PaymentRequestResponse::where('proposal_no', $proposal->proposal_no)
            ->where('user_product_journey_id', $user_product_journey_id)
            ->update([
                "status" => STAGE_NAMES['PAYMENT_SUCCESS'],
                "response" => $response,
            ]);
            updateJourneyStage([
                'user_product_journey_id' => $user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);
            return 
            [
                'status' => 'true',
                'message' => 'Payment Done Successfully for this proposal'
            ];

        }
        else
        {
            return 
            [
                'status' => 'false',
                'message' => 'Payment not yet made for this proposal'
            ];
        } */

    }
}
