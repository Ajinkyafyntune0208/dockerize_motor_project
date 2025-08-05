<?php

namespace App\Http\Controllers\Payment\Services;

use App\Models\MasterCompany;
use Config;
use Exception;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use App\Models\Quotes\Cv\CvQuoteModel;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';


class iciciLombardPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $request['userProductJourneyId'] = customDecrypt($request['userProductJourneyId']);

        $proposalData = UserProposal::where('user_product_journey_id', $request['userProductJourneyId'])->first();

        $payemntTokenRequest = [
            "TransactionId" => getUUID($request['userProductJourneyId']),
            "Amount" => $proposalData['final_payable_amount'],
            "ApplicationId" => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_APPLICATION_ID'),
            "ReturnURL" => route('cv.payment-confirm', ['icici_lombard', 'enquiry_id' => $request['userProductJourneyId']]),
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
                'section' => 'cv',
                'transaction_type' => 'proposal',
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
                        'section' => 'cv',
                        'transaction_type' => 'proposal',
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
            'section' => 'taxi',
            'enquiryId' => $request['userProductJourneyId'],
            'userName' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_USERNAME'),
            'userPass' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_PASSWORD'),
            'transaction_type' => 'proposal',
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
                'amount'                  => $proposalData->final_payable_amount,
                'payment_url'             => $paymentUrl,
                'return_url'              => route('cv.payment-confirm', ['icici_lombard', 'user_proposal_id' => $proposalData->user_proposal_id]),
                'customer_id'             => $proposalData['customer_id'],
                'proposal_no'             => $proposalData['proposal_no'],
                'order_id'                => $payemntTokenRequest['TransactionId'],
                'status'                  => STAGE_NAMES['PAYMENT_INITIATED'],
                'active'                  => 1
            ]);

            updateJourneyStage([
                'user_product_journey_id' => $request['userProductJourneyId'],
                'ic_id' => '40',
                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            ]);

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

        if (count($request->all()) == 1 && isset($request->enquiry_id)) {
            updateJourneyStage([
                'user_product_journey_id' => $request->enquiry_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);
            return redirect(paymentSuccessFailureCallbackUrl($request->enquiry_id, 'CV', 'FAILURE'));
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

        /* PaymentRequestResponse::where('proposal_no', $request->AdditionalInfo1)
            ->where('active', 1)
            ->update([
                "order_id" => $request->TransactionId,
                "status" => $request->Success,
                "amount" => $request->Amount,
                "response" => $response,
            ]); */
        if(empty($request->TransactionId))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $pg_status = false;
        if(strtolower($request->Success) == 'true' && !empty($request->AuthCode)) {
            $pg_status = true;
        }
        PaymentRequestResponse::where('user_product_journey_id', $request->enquiry_id)
            ->where('order_id', $request->TransactionId)
            ->update([
                "status" => $pg_status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                # "amount" => $request->Amount,
                "response" => $response,
            ]);


        $paymentDetails = PaymentRequestResponse::where('order_id', $request->TransactionId)->first();

        $userDetails = UserProposal::where('user_product_journey_id', $paymentDetails->user_product_journey_id)->select('*')->get();

        if (isset($request->pgiUserErrorCode) && $request->pgiUserErrorCode == 'User_Cancelled') {
            updateJourneyStage([
                'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'FAILURE'));
        }

        $master_policy_id = QuoteLog::where('user_product_journey_id', $userDetails[0]->user_product_journey_id)
            ->first();

        $premium_type_id = MasterPolicy::where('policy_id', $master_policy_id->master_policy_id)->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $premium_type_id->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $master_product_sub_type_code = MasterPolicy::find($master_policy_id->master_policy_id)->product_sub_type_code->product_sub_type_code;


        // Defined constant
        if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' || $master_product_sub_type_code == 'TRUCK' || $master_product_sub_type_code == 'TRACTOR' || $master_product_sub_type_code == 'TANKER/BULKER') {
            if ($premium_type == 'third_party') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_TP');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE_TP');
            } elseif ($premium_type == 'breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
            } else {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
            }
        } elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
            if ($premium_type == 'third_party') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_TP_PRODUCT_CODE');
            } elseif ($premium_type == 'breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            elseif ($premium_type == 'short_term_3_breakin')
            {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_3_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            elseif ($premium_type == 'short_term_6_breakin')
            {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_6_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            else {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
        } elseif ($master_product_sub_type_code === 'MISCELLANEOUS-CLASS') {
            $type = 'MISC';
            if ($premium_type == 'third_party'|| $premium_type == 'third_party_breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP_MISC'); #TP Deal for misc
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_TP_PRODUCT_CODE');
            }elseif (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN'); # breakin deal for misc
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
            }else {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_DEAL_ID');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
            }
        } else {
            return redirect(paymentSuccessFailureCallbackUrl($request->enquiry_id, 'CV', 'FAILURE'));
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
                'section' => 'car',
                'transaction_type' => 'proposal',
                'productName'  => 'CV',
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
                        'section' => 'car',
                        'transaction_type' => 'proposal',
                        'productName'  => 'CV',
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
                $enquiryId = $userDetails[0]->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CV','SUCCESS'));
               // return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($userDetails[0]->user_product_journey_id)]));
            }
        } else {
        $additionalData = [
            'requestMethod' => 'get',
            'type' => 'transactionIdForPG',
            'section' => 'taxi',
            'enquiryId' => $userDetails[0]->user_product_journey_id,
            'transaction_type' => 'proposal',
        ];
      }
        include_once app_path() . '/Helpers/CvWebServiceHelper.php';

        $get_response = getWsData($tokenUrl, '', 'icici_lombard', $additionalData);
        $result = $get_response['response'];

        if (!empty($result)) {
            $response = json_decode($result, true);

            if ($response['Status'] == '0' || $response['Status'] == 'True') {


                PaymentRequestResponse::where('user_product_journey_id', $userDetails[0]->user_product_journey_id)
                    ->where('active', 1)
                    ->update([
                        "status" => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);

                $tokenParam = [
                    'grant_type' => 'password',
                    'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
                    'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
                    'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
                    'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
                    'scope' => 'esbpayment',
                ];

                $additionData = [
                    'requestMethod' => 'post',
                    'type' => 'tokenGeneration',
                    'section' => 'taxi',
                    'enquiryId' => $userDetails[0]->user_product_journey_id,
                    'transaction_type' => 'proposal',
                ];

                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                $token = $get_response['response'];

                if (!empty($token)) {
                    $tokenResponse = json_decode($token, true);

                    $access_token = $tokenResponse['access_token'];

                    $paymentEntryRequestArr = [
                        // 'CorrelationId' => $request->TransactionId,
                        'CorrelationId' => $userDetails[0]?->unique_proposal_id,
                        // 'DealId' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID'),
                        //'DealId' => $ICICI_LOMBARD_DEAL_ID,
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
                        'section' => 'taxi',
                        'enquiryId' => $userDetails[0]->user_product_journey_id,
                        'transaction_type' => 'proposal',
                    ];
                    $is_pos = 'N';
                    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                    $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
                    $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id', $userDetails[0]->user_product_journey_id)
                        ->where('user_proposal_id', $userDetails[0]->user_proposal_id)
                        ->where('seller_type', 'P')
                        ->first();
                    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                        if ($pos_data) {
                            $is_pos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                            $CertificateNumber = $pos_data->unique_number; #$pos_data->user_name;
                            $PanCardNo = $pos_data->pan_no;
                            $AadhaarNo = $pos_data->aadhar_no;
                        }

                        if ($pos_testing_mode === 'Y')
                        {
                            $is_pos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                            $CertificateNumber = 'TMI0001';
                            $PanCardNo = 'ABGTY8890Z';
                            $AadhaarNo = '569278616999';
                            $ProductCode = $PRODUCT_CODE;
                        }

                        if(config('ICICI_LOMBARD_IS_NON_POS') == 'Y')
                        {
                            $is_pos = 'N';
                            $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = $ProductCode = '';
                            $paymentEntryRequestArr['DealId'] = $ICICI_LOMBARD_DEAL_ID;
                        }
                        $ProductCode = $PRODUCT_CODE;
                    } elseif ($pos_testing_mode === 'Y') {
                        $is_pos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                        $CertificateNumber = 'TMI0001';
                        $PanCardNo = 'ABGTY8890Z';
                        $AadhaarNo = '569278616999';
                        $ProductCode = $PRODUCT_CODE;
                    } else {
                        $paymentEntryRequestArr['DealId'] = $ICICI_LOMBARD_DEAL_ID;
                    }

                    if ($is_pos == 'Y') {
                        $pos_details = [
                            'pos_details' => [
                                'IRDALicenceNumber' => $IRDALicenceNumber,
                                'CertificateNumber' => $CertificateNumber,
                                'PanCardNo'         => $PanCardNo,
                                'AadhaarNo'         => $AadhaarNo,
                                'ProductCode'       => $ProductCode
                            ]
                        ];
                        $additionData = array_merge($additionData, $pos_details);
                    }

                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY'), $paymentEntryRequestArr, 'icici_lombard', $additionData);
                    $policyResponse = $get_response['response'];

                    if (!empty($policyResponse)) {
                        $generatedPolicy = json_decode($policyResponse, true);

                        if ($generatedPolicy['status'] == 'true' || $generatedPolicy['statusMessage'] == 'Success') {

                            if ($generatedPolicy['paymentTagResponse'] != 'null') {

                                $policyNo = $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['policyNo'];
                                // $policyNo = '3001/51993381/00/000';
                                if ( empty($policyNo) ) {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                        'stage' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                        'ic_id' => 40
                                    ]); 
                                /* if ($policyNo == null) { */
                                    return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                                } 

                                PolicyDetails::updateOrCreate([
                                    'proposal_id' => $userDetails[0]->user_proposal_id
                                ], [
                                    'policy_number' => $policyNo,
                                    'idv' => '',
                                    'policy_start_date' => $userDetails[0]->policy_start_date,
                                    'ncb' => null,
                                    'premium' => $userDetails[0]->final_payable_amount,
                                ]);

                                updateJourneyStage([
                                    'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                    'ic_id' => 40
                                ]);
                                $additionData = [
                                    'requestMethod' => 'post',
                                    'type' => 'tokenGeneration',
                                    'section' => 'taxi',
                                    'enquiryId' => $userDetails[0]->user_product_journey_id,
                                    'transaction_type' => 'proposal',
                                ];

                                $tokenParam = [
                                    'grant_type' => 'password',
                                    'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
                                    'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
                                    'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
                                    'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
                                    'scope' => 'esbpolicypdf', #esbpolicypdf
                                ];

                                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                                $tokenforPdfGen = $get_response['response'];

                                if (!empty($tokenforPdfGen)) {
                                    $generatedtoken = json_decode($tokenforPdfGen, true);
                                    $access_token = $generatedtoken['access_token'];

                                    $policyPdfUrl = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY_PDF');
                                    $policypdfdata = [
                                        "CorrelationId" => $userDetails[0]?->unique_proposal_id,
                                        "policyNo"      => $policyNo,
                                        #"DealId"        => $ICICI_LOMBARD_DEAL_ID,
                                        "customerId"    => $request->AdditionalInfo2,
                                    ];
                                    $additionData = [
                                        'requestMethod' => 'post', #post
                                        'type' => 'policyPdfGeneration',
                                        'section' => 'taxi',
                                        'token' => $access_token,
                                        'enquiryId' => $userDetails[0]->user_product_journey_id,
                                        'transaction_type' => 'proposal',
                                    ];
                                    /* if($is_pos == 'Y')
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

                                    if (!empty($pdfGenerationResponse)) {

                                        if (preg_match("/^%PDF-/", $pdfGenerationResponse)) {

                                            $proposal_pdf = Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $userDetails[0]->user_proposal_id . '.pdf', $pdfGenerationResponse);

                                            PolicyDetails::where('proposal_id', $userDetails[0]->user_proposal_id)
                                                ->update([
                                                    'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $userDetails[0]->user_proposal_id . '.pdf',
                                                ]);

                                            updateJourneyStage([
                                                'user_product_journey_id' => $userDetails[0]->user_product_journey_id,
                                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                                            ]);

                                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                                        } else {
                                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                                        }
                                    } else {

                                        return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                                    }
                                } else {
                                    return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                                }
                            } else {
                                return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                            }
                        } else {
                            return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                        }
                    } else {
                        return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                    }
                } else {
                    return redirect(paymentSuccessFailureCallbackUrl($userDetails[0]->user_product_journey_id, 'CV', 'SUCCESS'));
                }
            } else {
                /* PaymentRequestResponse::where('user_product_journey_id', $userDetails[0]->user_product_journey_id)
                    ->where('active', 1)
                    ->update([
                        "status" => STAGE_NAMES['PAYMENT_FAILED']
                    ]); */

                return redirect(paymentSuccessFailureCallbackUrl($request->enquiry_id, 'CV', 'FAILURE'));
            }
        } else {
            return redirect(paymentSuccessFailureCallbackUrl($request->enquiry_id, 'CV', 'FAILURE'));
        }
    }

    static public function retry_pdf($user_proposal)
    {

        $policy_details = PolicyDetails::where('proposal_id', $user_proposal->user_proposal_id)->first();
        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->first();
        $user_details = UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();
        $premium_type_id = MasterPolicy::where('policy_id', $master_policy_id->master_policy_id)->first();

        $master_product_sub_type_code = MasterPolicy::find($master_policy_id->master_policy_id)->product_sub_type_code->product_sub_type_code;
        $premium_type = DB::table('master_premium_type')
            ->where('id', $premium_type_id->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' || $master_product_sub_type_code == 'TRUCK' || $master_product_sub_type_code == 'TRACTOR' || $master_product_sub_type_code == 'TANKER/BULKER') {
            if ($premium_type == 'third_party') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_TP');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE_TP');
            } elseif ($premium_type == 'breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
            } else {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
            }
        } elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
            if ($premium_type == 'third_party') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_TP_PRODUCT_CODE');
            } elseif ($premium_type == 'breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            elseif ($premium_type == 'short_term_3_breakin')
            {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_3_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            elseif ($premium_type == 'short_term_6_breakin')
            {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_6_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            else {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
        }
        $is_pos = 'N';
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->where('seller_type', 'P')
            ->first();
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if ($pos_data) {
                $is_pos = 'Y';
                $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                $CertificateNumber = $pos_data->unique_number; #$pos_data->user_name;
                $PanCardNo = $pos_data->pan_no;
                $AadhaarNo = $pos_data->aadhar_no;
            }

            $ProductCode = $PRODUCT_CODE;
        } elseif ($pos_testing_mode === 'Y') {
            $is_pos = 'Y';
            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
            $CertificateNumber = 'TMI0001';
            $PanCardNo = 'ABGTY8890Z';
            $AadhaarNo = '569278616999';
            $ProductCode = $PRODUCT_CODE;
        }
        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
            'scope' => 'esbpolicypdf', #pdf
        ];

        $additionData = [
            'requestMethod' => 'post', #pdftoken post
            'type' => 'tokenGeneration',
            'section' => 'taxi',
            'enquiryId' => $user_proposal->user_product_journey_id,
            'transaction_type' => 'proposal',
        ];
        include_once app_path() . '/Helpers/CvWebServiceHelper.php';

        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
        $token = json_decode($token, true);

        $additionData = [
            'requestMethod' => 'post', #pdf post
            'type' => 'policyPdfGeneration',
            'section' => 'taxi',
            'token' => $token['access_token'],
            'enquiryId' => $user_proposal->user_product_journey_id,
            'transaction_type' => 'proposal',
        ];
        $policypdfdata = [
            "CorrelationId" => $user_proposal->unique_proposal_id,
            "policyNo"      => trim($policy_details->policy_number),
            # "DealId"        => $ICICI_LOMBARD_DEAL_ID,
            "customerId"    => $user_proposal->customer_id,
        ];
        $policyPdfUrl = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY_PDF');
        /* if($is_pos == 'Y')
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
        if (preg_match("/^%PDF-/", $pdfGenerationResponse)) {
            Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $user_proposal->user_proposal_id . '.pdf', $pdfGenerationResponse);

            PolicyDetails::where('proposal_id', $user_proposal->user_proposal_id)
                ->update([
                    'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $user_proposal->user_proposal_id . '.pdf',
                ]);
            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED'],
                'ic_id' => 40
            ]);
            return [
                'status' => true,
                'msg' => 'sucess',
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link' => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $user_proposal->user_proposal_id . '.pdf'),
                ]
            ];
        }else{
            return response()->json([
                'status' => false,
                'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                'data' => [
                    'policy_number' => $policy_details->policy_number
                ]
            ]);
        }
    }

    public static function generatePdf($request)
    {

        $user_product_journey_id = customDecrypt($request->enquiryId);
        $policy_details = DB::table('payment_request_response as prr')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'up.user_proposal_id')
            ->where('prr.user_product_journey_id', $user_product_journey_id)
            ->where('prr.active', 1)
            ->select(
                'up.user_proposal_id',
                'up.user_proposal_id',
                'up.proposal_no',
                'up.unique_proposal_id',
                'pd.policy_number',
                'pd.pdf_url',
                'pd.ic_pdf_url',
            )
            ->first();

        if ($policy_details?->policy_number) {
            try {
                $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $response = Self::retry_pdf($proposal);
                return $response;
            } catch (\Exception $e) {
                return [
                    'status' => false,
                    'msg' => 'Error Occured',
                    'dev' => $e->getMessage(),
                ];
            }
        } else {
            try {

                $tokenParam = [
                    'grant_type' => 'password',
                    'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
                    'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
                    'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
                    'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
                    'scope' => 'esbpayment',
                ];

                $additionData = [
                    'requestMethod' => 'post',
                    'type' => 'tokenGeneration',
                    'section' => 'taxi',
                    'enquiryId' => $user_product_journey_id,
                    'transaction_type' => 'proposal',
                ];

                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                $token = $get_response['response'];

                if (!empty($token)) {
                    $tokenResponse = json_decode($token, true);

                    $access_token = $tokenResponse['access_token'];

                    $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                    $payment_response = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                        ->where('active', 1)
                        ->first();

                    $response_data = json_decode($payment_response->response, true);

                    #verifyPaymentResponse
                    $verify_payment = self::verifyPaymentResponse($payment_response, $user_product_journey_id);
                    if (!$verify_payment['status']) {
                        throw new Exception('Payment Pending');
                    }

                    $payment_date = date("d-m-Y");
                    if ($verify_payment['step'] == 'backward') {
                        $idd = $verify_payment['id'];
                        $payment_response = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                            ->where('id', $idd)
                            ->first();
                        $payment_date = date("d-m-Y", strtotime($payment_response->created_at));
                        $response_data = json_decode($payment_response->response, true);
                    }

                    $policy_id = QuoteLog::where('user_product_journey_id', $user_product_journey_id)->pluck('master_policy_id')->first();

                    $cvQuoteModel = new CvQuoteModel();

                    $productData = $cvQuoteModel->getProductDataByIc($policy_id);

                    $premium_type = DB::table('master_premium_type')->where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

                    $master_product_sub_type_code = MasterPolicy::find($productData->policy_id)->product_sub_type_code->product_sub_type_code;

                    if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' || $master_product_sub_type_code == 'TRUCK' || $master_product_sub_type_code == 'TRACTOR' || $master_product_sub_type_code == 'TANKER/BULKER') {
                        $type = 'GCV';
                        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_TP');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE_TP');
                        } elseif ($premium_type == 'breakin') {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
                        } else {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
                        }
                    } elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
                        $type = 'PCV';
                        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_TP_PRODUCT_CODE');
                        } elseif ($premium_type == 'breakin') {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
                        }
                        elseif ($premium_type == 'short_term_3_breakin')
                        {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_3_BREAKIN');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
                        }
                        elseif ($premium_type == 'short_term_6_breakin')
                        {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_6_BREAKIN');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
                        }
                        else {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
                        }
                    } elseif ($master_product_sub_type_code === 'MISCELLANEOUS-CLASS') {
                        $type = 'MISC';
                        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP_MISC'); #TP Deal for misc
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_TP_PRODUCT_CODE');
                        } elseif (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN'); # breakin deal for misc
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
                        } else {
                            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_DEAL_ID');
                            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
                        }
                    }

                    $paymentEntryRequestArr = [
                        'CorrelationId' => $proposal->unique_proposal_id,
                        'isTaggingRequired' => true,
                        'isMappingRequired' => true,
                        'PaymentEntry' => [
                            'onlineDAEntry' => [
                                'AuthCode' => $response_data['AuthCode'],
                                'MerchantID' => $response_data['MerchantId'],
                                'TransactionId' => $response_data['PGTransactionId'],
                                'CustomerID' => $response_data['AdditionalInfo2'],
                                'InstrumentDate' => $payment_date,
                                'ReceiptDate' => date('d-m-Y'),
                                'PaymentAmount' => $proposal->final_payable_amount, #$response_data['Amount'],
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
                        'section' => 'taxi',
                        'enquiryId' => $user_product_journey_id,
                        'transaction_type' => 'proposal',
                    ];
                    $is_pos = 'N';
                    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                    $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
                    $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id', $user_product_journey_id)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->where('seller_type', 'P')
                        ->first();
                    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                        if ($pos_data) {
                            $is_pos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                            $CertificateNumber = $pos_data->unique_number; #$pos_data->user_name;
                            $PanCardNo = $pos_data->pan_no;
                            $AadhaarNo = $pos_data->aadhar_no;
                        }

                        if ($pos_testing_mode === 'Y')
                        {
                            $is_pos = 'Y';
                            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                            $CertificateNumber = 'TMI0001';
                            $PanCardNo = 'ABGTY8890Z';
                            $AadhaarNo = '569278616999';
                            $ProductCode = $PRODUCT_CODE;
                        }
                        if(config('ICICI_LOMBARD_IS_NON_POS') == 'Y')
                        {
                            $is_pos = 'N';
                            $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo ='';
                            $paymentEntryRequestArr['DealId'] = $ICICI_LOMBARD_DEAL_ID;
                        }
                        $ProductCode = $PRODUCT_CODE;
                    } elseif ($pos_testing_mode === 'Y') {
                        $is_pos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                        $CertificateNumber = 'TMI0001';
                        $PanCardNo = 'ABGTY8890Z';
                        $AadhaarNo = '569278616999';
                        $ProductCode = $PRODUCT_CODE;
                    } else {
                        $paymentEntryRequestArr['DealId'] = $ICICI_LOMBARD_DEAL_ID;
                    }

                    if ($is_pos == 'Y') {
                        $pos_details = [
                            'pos_details' => [
                                'IRDALicenceNumber' => $IRDALicenceNumber,
                                'CertificateNumber' => $CertificateNumber,
                                'PanCardNo'         => $PanCardNo,
                                'AadhaarNo'         => $AadhaarNo,
                                'ProductCode'       => $ProductCode
                            ]
                        ];
                        $additionData = array_merge($additionData, $pos_details);
                    }

                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY'), $paymentEntryRequestArr, 'icici_lombard', $additionData);
                    $policyResponse = $get_response['response'];

                    if (!empty($policyResponse)) {
                        $generatedPolicy = json_decode($policyResponse, true);

                        if ($generatedPolicy['status'] == 'true' || $generatedPolicy['statusMessage'] == 'Success') {

                            if ($generatedPolicy['paymentTagResponse'] != 'null') {
                                $policyNo = $generatedPolicy['paymentTagResponse']['paymentTagResponseList'][0]['policyNo'];

                                PolicyDetails::updateOrCreate(
                                    [
                                        'proposal_id' => $proposal->user_proposal_id
                                    ],
                                    [
                                        'policy_number' => $policyNo,
                                        'idv' => '',
                                        'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $proposal->user_proposal_id . '.pdf',
                                        'policy_start_date' => $proposal->policy_start_date,
                                        'ncb' => null,
                                        'premium' => $proposal->final_payable_amount,
                                        'status' => 'SUCCESS'
                                    ]
                                );

                                $additionData = [
                                    'requestMethod' => 'post',
                                    'type' => 'tokenGeneration',
                                    'section' => 'taxi',
                                    'enquiryId' => $proposal->user_product_journey_id,
                                    'transaction_type' => 'proposal',
                                ];

                                $tokenParam = [
                                    'grant_type' => 'password',
                                    'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
                                    'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
                                    'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
                                    'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
                                    'scope' => 'esbpolicypdf', #token pdf
                                ];

                                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                                $tokenforPdfGen = $get_response['response'];

                                if (!empty($tokenforPdfGen)) {
                                    $generatedtoken = json_decode($tokenforPdfGen, true);
                                    $access_token = $generatedtoken['access_token'];

                                    $policyPdfUrl = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_POLICY_PDF');

                                    $additionData = [
                                        'requestMethod' => 'post',
                                        'type' => 'policyPdfGeneration',
                                        'section' => 'taxi',
                                        'token' => $access_token,
                                        'enquiryId' => $proposal->user_product_journey_id,
                                        'transaction_type' => 'proposal',
                                    ];
                                    $policypdfdata = [
                                        "CorrelationId" => $proposal->unique_proposal_id,
                                        "policyNo"      => $policyNo,
                                        #"DealId"        => $ICICI_LOMBARD_DEAL_ID,
                                        "customerId"    => $response_data['AdditionalInfo2'],
                                    ];
                                    /* if($is_pos == 'Y')
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
                                    if (!empty($pdfGenerationResponse)) {

                                        $proposal_pdf = Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $proposal->user_proposal_id . '.pdf', $pdfGenerationResponse);

                                        PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                            ->update([
                                                'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $proposal->user_proposal_id . '.pdf',
                                            ]);

                                        updateJourneyStage([
                                            'user_product_journey_id' => $proposal->user_product_journey_id,
                                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                                        ]);

                                        $pdf_response_data = [
                                            'status' => true,
                                            'msg' => 'sucess',
                                            'data' => [
                                                'policy_number' => $policyNo,
                                                'pdf_link'      => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $proposal->user_proposal_id . '.pdf'),
                                            ]
                                        ];

                                        return $pdf_response_data;
                                    } else {
                                        throw new \Exception("Insurer not reachable");
                                    }
                                } else {
                                }
                            } else {
                                throw new \Exception("Insurer not reachable");
                            }
                        } else {
                            throw new \Exception("Insurer not reachable");
                        }
                    } else {
                        throw new \Exception("Insurer not reachable");
                    }
                } else {
                    throw new \Exception("Insurer not reachable");
                }
            } catch (\Exception $e) {

                return [
                    'status' => false,
                    'msg'    => 'Error Occured',
                    'dev'    => $e->getMessage() . ' on ' . $e->getLine(),
                    'data'   => []
                ];
            }
        }
    }

    private static function verifyPaymentResponse($payload, $enqId)
    {



        /* $data = json_decode($payload->response, true);
        if ((!empty($data['Success']) && $data['Success'] != 'False') || !empty($data['PGTransactionId'])) {
            return [
                'status' => true,
                'step' => 'forward',
                'id' => ''
            ];
        } else { */
            $payment_response = PaymentRequestResponse::where('user_product_journey_id', $enqId)->get();

            

            include_once app_path() . '/Helpers/CvWebServiceHelper.php';

            foreach ($payment_response as $key => $value) {

                $tokenUrl = config('constants.IcConstants.icici_lombard.TRANSACTION_ENQUIRY_END_POINT_URL_ICICI_LOMBARD_MOTOR') . $value->order_id;

                //new custome token code start here
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
                    'section' => 'car',
                    'transaction_type' => 'proposal',
                    'productName'  => 'CV',
                    'enquiryId' => $enqId,
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
                            'section' => 'car',
                            'transaction_type' => 'proposal',
                            'productName'  => 'CV',
                            'enquiryId' => $enqId,
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Basic ' . $payment_token_response['AuthToken']
                            ]
                        ];
                    }
                }else if (!isset($additionalData))
                {
                    $additionalData = [
                        'requestMethod' => 'get',
                        'type' => 'transactionIdForPG',
                        'section' => 'taxi',
                        'enquiryId' => $enqId,
                        'transaction_type' => 'proposal',
                    ];
                }
    
                //end here
                $get_response = getWsData($tokenUrl, '', 'icici_lombard', $additionalData);
                $result = $get_response['response'];

                if (!empty($result)) {
                    $response = json_decode($result, true);
                    if (isset($response['Status']) && ($response['Status'] == '0' || $response['Status'] == 'True')) {

                        $payment_response = PaymentRequestResponse::where('user_product_journey_id', $enqId)
                            ->where('id', $value->id)
                            ->first();

                        $proposal_detail = UserProposal::where('user_product_journey_id', $enqId)->first();

                        $response_data = json_decode($payment_response->response, true);

                        //if (!$response_data) {

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
                            PaymentRequestResponse::where('user_product_journey_id', $enqId)
                                ->update([
                                    'active'  => 0
                                ]);

                            PaymentRequestResponse::where('user_product_journey_id', $enqId)
                                ->where('id', $value->id)
                                ->update([
                                    'response' => $updatePaymentResponse,
                                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                    'active'  => 1
                                ]);
                            updateJourneyStage([
                                'user_product_journey_id' => $enqId,
                                'stage' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'ic_id' => 40
                            ]);
                        //}

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
        //}
    }

    public static function offlineMake($request)
    {
        return response()->json([
            'status' => true,
            'data' => [
                "payment_type" => '1',
                "paymentUrl" => route('cv.payment-confirm', [
                    $request->companyAlias,
                    'user_product_journey_id' => $request->userProductJourneyId,
                    'policy_id' => $request->policyId
                ]),
            ],
        ]);
    }

    public static function offlineConfirm($request)
    {
        $enquiry_id = customDecrypt($request->user_product_journey_id);
        $user_proposal = \App\Models\UserProposal::with('policy_details')->where('user_product_journey_id', $enquiry_id)->first();
        $user_product_journey = UserProductJourney::with(['corporate_vehicles_quote_request', 'quote_log'])->find($enquiry_id);
        $master_company = MasterCompany::find($user_proposal->ic_id);
        $pdf_data = [
            'created_at' => now()->toString(),
            'show_policy_no' => $user_proposal->policy_details->policy_number,
            'show_product_name' => 'PCV',
            'name_of_financer' => 'SBI',
            'show_nominee_details' => rand(),
            'show_previous_policy_no' => rand(),
            'show_previous_insurance_company' => rand(),
            'pre_policy_expiry_date' => now()->format('y.M.d'),
            'show_full_name' => $user_proposal->first_name . ' ' . $user_proposal->last_name,
            'show_address' => $user_proposal->address_line1 . ', ' . $user_proposal->address_line2 . ', ' . $user_proposal->address_line3,
            'show_state' => $user_proposal->state,
            'show_city' => $user_proposal->city,
            'show_pincode' => $user_proposal->pincode,
            'show_mobile_no' => $user_proposal->mobile_number,
            'show_email_id' => $user_proposal->email,
            'show_broker_code' => '',
            'broker_name' => '',
            'broker_telephone_no' => '',
            'show_ic_address' => '',
            'show_policy_issuedon_date' => now()->toString(),
            'show_policy_start_date' => $user_proposal->policy_start_date,
            'show_policy_end_date' => $user_proposal->policy_end_date,
            'show_rto' => $user_proposal->rto_location,
            'rto_location' => $user_proposal->rto_location,
            'show_make' => $user_product_journey->quote_log->premium_json['mmvDetail']['manfName'],
            'show_model' => $user_product_journey->quote_log->premium_json['mmvDetail']['modelName'],
            'show_varient' => $user_product_journey->quote_log->premium_json['mmvDetail']['versionName'],
            'show_engin_no' => $user_proposal->engine_number,
            'show_chassis_no' => $user_proposal->chassis_number,
            'show_mfg_year' => $user_proposal->vehicle_manf_year,
            'show_cc' => $user_product_journey->quote_log->premium_json['mmvDetail']['cubicCapacity'],
            'show_carryring_capacity' => $user_product_journey->quote_log->premium_json['mmvDetail']['carryingCapicity'],
            'show_idv' => $user_proposal->idv,
            'show_elec_accessories_amts' => '',
            'show_non_elec_accessories_amt' => '',
            'bifuel_kit_value' => '',
            'show_gross_idv' => $user_proposal->idv,
            'show_compulsory_pa_own_driver' => '',
            'show_cover_unnamed_passenger_value' => '',
            'addon_breakup' => '',
            'show_total_own_damage' => $user_proposal->od_premium,
            'show_cng_lpg_tp' => '',
            'show_elec_accessories_amt' => '',
            'show_antitheft_discount' => '',
            'show_ncb_discount' => $user_proposal->applicable_ncb,
            'show_deduction_of_ncb' => $user_proposal->ncb_discount,
            'show_voluntary_excess' => '',
            'show_od_premium' => $user_proposal->od_premium,
            'show_tppd_premium_amount' => '',
            'show_lld_paid_driver_liability' => '',
            'show_total_liability_premium' => '',
            'show_net_premium' => '',
            'show_cgst' => $user_proposal->service_tax_amount / 2,
            'show_sgst' => $user_proposal->service_tax_amount / 2,
            'show_final_premium' => $user_proposal->total_premium,
            'show_imt_code' => '',
            'gstin_no' => $user_proposal->gst_number,
            'insurance_company_pan_no' => '',
            'insurance_company_branch_gstin' => '',
            'show_pos_details' => '',
            'show_company_name' => $user_proposal->ic_name,
            'show_ic_contact' => '',
            'show_ic_url' => '',
            'show_company_logo' => url('uploads/logos/' . $master_company->logo),
        ];
        $html_data = view('offline_pdf/icici_lombard')->render();
        foreach ($pdf_data as $key => $value) {
            $html_data = Str::replace('#' . $key . '#', $value, $html_data);
        }
        $pdf_path = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $user_proposal->user_proposal_id . '.pdf';
        // echo $html_data;
        $pdf_file_data = \PDF::loadHtml($html_data)->output();
        if (Storage::exists($pdf_path)) {
            Storage::delete($pdf_path);
        }

        Storage::put($pdf_path, $pdf_file_data);

        PolicyDetails::where('proposal_id', $user_proposal->user_proposal_id)
            ->update([
                'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'icici_lombard/' . $user_proposal->user_proposal_id . '.pdf',
            ]);

        updateJourneyStage([
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['POLICY_ISSUED']
        ]);
        return redirect(config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($enquiry_id)]));
    }
}
