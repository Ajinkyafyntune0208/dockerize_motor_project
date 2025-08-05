<?php

namespace App\Http\Controllers\Payment\Services\Car;
include_once app_path().'/Helpers/CarWebServiceHelper.php';

use App\Models\MasterPolicy;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use DateTime;

class magmaPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        if ($proposal)
        {
            $enquiryId = customDecrypt($request['userProductJourneyId']);

            $icId = MasterPolicy::where('policy_id', $request['policyId'])
                    ->pluck('insurance_company_id')
                    ->first();

            $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                    ->pluck('quote_id')
                    ->first();

            $productData = getProductDataByIc($request['policyId']);

            $tokenParam = [
                'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
                'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
                'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
                'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
            ];

            $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
                'section'          => $productData->product_sub_type_code,
                'method'           => 'Token Generation',
                'requestMethod'    => 'post',
                'type'             => 'tokenGeneration',
                'enquiryId'        => $enquiryId,
                'productName'      => $productData->product_sub_type_name,
                'transaction_type' => 'proposal'
            ]);
            $token = $get_response['response'];

            if ($token)
            {
                $token_data = json_decode($token, true);

                if (isset($token_data['access_token']))
                {
                    /* $proposal_status_requset = [
                        'ProposalNumber' => $proposal->proposal_no
                    ];

                    $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_PROPOSAL_STATUS'), $proposal_status_requset, 'magma', [
                        'section'          => $productData->product_sub_type_code,
                        'method'           => 'Proposal Status',
                        'requestMethod'    => 'post',
                        'type'             => 'proposalStatus',
                        'enquiryId'        => $enquiryId,
                        'token'            => $token_data['access_token'],
                        'productName'      => $productData->product_sub_type_name,
                        'transaction_type' => 'proposal'
                    ]);
                    $proposal_status = $get_response['response'];

                    if ($proposal_status)
                    {
                        $proposal_status = json_decode($proposal_status, TRUE);

                        if ($proposal_status['ServiceResult'] == 'Success' && $proposal_status['OutputResult']['PaymentStatus'] == 'Pending For Payment')
                        { */
                            $get_payment_url = [
                                'ProposalNumber' => $proposal->proposal_no,
                                'CustomerID' => $proposal->customer_id,
                                'SuccessURL' => route('car.payment-confirm', ['magma', 'user_proposal_id' => $proposal->user_proposal_id]),
                                'FailureURL' => route('car.payment-confirm', ['magma', 'user_proposal_id' => $proposal->user_proposal_id]),
                                'PartnerSource' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
                                'CustomerEmailID' => $proposal->email,
                                'BillingAmount' => $proposal->final_payable_amount,
                                'CustomerMobileNumber' => $proposal->mobile_number,
                                'PaymentGateway' => config('constants.IcConstants.magma.MAGMA_PAYMENT_GATEWAY_TYPE'),#'IBANK'
                            ];

                            $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETPAYMENTURL'), $get_payment_url, 'magma', [
                                'section'          => $productData->product_sub_type_code,
                                'method'           => 'PG Redirection',
                                'requestMethod'    => 'post',
                                'type'             => 'GetPaymentURL',
                                'token'            => $token_data['access_token'],
                                'enquiryId'        => $enquiryId,
                                'productName'      => $productData->product_sub_type_name,
                                'transaction_type' => 'proposal'
                            ]);
                            $payment_data = $get_response['response'];

                            if ($payment_data)
                            {
                                $payment_result = json_decode($payment_data, true);

                                if ($payment_result['ServiceResult'] == 'Success')
                                {
                                    $pg_url = $payment_result['OutputResult']['PaymentURL'];
                
                                    DB::table('payment_request_response')
                                            ->where('user_product_journey_id', $enquiryId)
                                            ->update(['active' => 0]);
                
                                    DB::table('payment_request_response')->insert([
                                        'quote_id'                => $quote_log_id,
                                        'user_product_journey_id' => $enquiryId,
                                        'user_proposal_id'        => $proposal->user_proposal_id,
                                        'ic_id'                   => $icId,
                                        'proposal_no'             => $proposal->proposal_no,
                                        'order_id'                => $payment_result['OutputResult']['TransactionID'],
                                        'amount'                  => $proposal->final_payable_amount,
                                        'payment_url'             => $pg_url,
                                        'return_url'              => route('car.payment-confirm', ['magma', 'user_proposal_id' => $proposal->user_proposal_id]),
                                        'status'                  => STAGE_NAMES['PAYMENT_INITIATED'],
                                        'active'                  => 1
                                    ]);
                
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage'                   => STAGE_NAMES['PAYMENT_INITIATED']
                                    ]);
                
                                    return [
                                        'status' => true,
                                        'msg'    => "Payment Redirectional",
                                        'data'   => [
                                            "payment_type" => 1,
                                            "paymentUrl"   => $pg_url,
                                        ],
                                    ];
                                }
                                else
                                {
                                    return [
                                        'status' => false,
                                        'msg'    => $payment_result['ErrorText'],
                                    ];
                                }
                            }
                            else
                            {
                                return [
                                    'status' => false,
                                    'msg'    => "Error in payment redirection service",
                                ];
                            }
                        /* }
                        else
                        {
                            return [
                                'status' => false,
                                'msg' => $proposal_status['ErrorText'] ?? 'Error in proposal status service'
                            ];
                        }
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'msg' => "Error in proposal status service",
                        ];
                    } */
                }
                else
                {
                    return [
                        'status' => false,
                        'msg' => $token_data['ErrorText'] ?? 'Error in token generation service'
                    ];
                }
            }
            else
            {
                return [
                    'status' => false,
                    'msg' => "Error in token generation service",
                ];
            }
        }
        else
        {
            return [
                'status' => false,
                'msg' => "Proposal data not found",
            ];
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        $user_proposal = UserProposal::find($request['user_proposal_id']);

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('active', 1)
            ->update([
                'response' => $request->All()
            ]);

        if (isset($request['ErrorMessage']) && ($request['ErrorMessage'] == null || $request['ErrorMessage'] == ''))
        {
            $quote_data = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->first();

            $productData = getProductDataByIc($quote_data->master_policy_id);

            if (isset($request['PolicyNo']) && ($request['PolicyNo'] != '' || $request['PolicyNo'] != NULL))
            {
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage'                   => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('active', 1)
                    ->update([
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $request['PolicyNo'],
                        'status'        => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]
                );

                $tokenParam = [
                    'grant_type'  => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
                    'username'    => config('constants.IcConstants.magma.MAGMA_USERNAME'),
                    'password'    => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
                    'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
                ];

                $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
                    'section'          => $productData->product_sub_type_code,
                    'method'           => 'Token Generation',
                    'requestMethod'    => 'post',
                    'type'             => 'tokenGeneration',
                    'enquiryId'        => $user_proposal->user_product_journey_id,
                    'productName'      => $productData->product_sub_type_name,
                    'transaction_type' => 'proposal'
                ]);
                $token = $get_response['response'];

                if ($token)
                {
                    $token_data = json_decode($token, true);

                    if (isset($token_data['access_token']))
                    {
                        $pdf_request_data = [
                            'PolicyNumber' => $request['PolicyNo'],
                            'CustomerID' => $request['CustomerID'],
                            'IntermediaryCode' => config('constants.IcConstants.magma.MAGMA_INTERMEDIARYCODE'),
                            'ProposalNumber' => $request['ProposalNumber']
                        ];

                        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_POLICY_PDF_MAGMA_MOTOR'), $pdf_request_data, 'magma', [
                            'section'          => $productData->product_sub_type_code,
                            'method'           => 'Policy PDF',
                            'requestMethod'    => 'post',
                            'type'             => 'policyPdfGeneration',
                            'token'            => $token_data['access_token'],
                            'enquiryId'        => $user_proposal->user_product_journey_id,
                            'productName'      => $productData->product_sub_type_name,
                            'transaction_type' => 'proposal'
                        ]);
                        $pdf_response = $get_response['response'];

                        if ($pdf_response)
                        {
                            $pdf_response = json_decode($pdf_response, true);

                            if ($pdf_response['ServiceResult'] == 'Success')
                            {
                                Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'magma/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['OutputResult']['PolicyBase64String']));

                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage'                   => STAGE_NAMES['POLICY_ISSUED']
                                ]);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id], [
                                        'policy_number' => $pdf_response['OutputResult']['PolicyNumber'],
                                        'pdf_url'       => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'magma/' . md5($user_proposal->user_proposal_id) . '.pdf',
                                        'status'        => 'SUCCESS'
                                    ]
                                );
                            }
                        }
                    }
                }

                #return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','SUCCESS'));
            }
            else
            {
                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('active', 1)
                    ->update([
                        'response' => $request->All(),
                        'status' => STAGE_NAMES['PAYMENT_FAILED']
                    ]);

                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                ]);

                #return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','FAILURE'));
            }
        }
        else
        {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('active', 1)
                ->update([
                    'response' => $request->All(),
                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                ]);

            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage'                   => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));          
        }
        return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
    }

    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'prr.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->leftJoin('quote_log AS ql', 'prr.user_product_journey_id', '=', 'ql.user_product_journey_id')
            ->where('prr.user_product_journey_id', $user_product_journey_id)
            ->where('prr.active', 1)
            ->select('up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no', 'up.customer_id', 'pd.policy_number', 'ql.master_policy_id')
            ->first();

        if ($policy_details)
        {
            if (isset($policy_details->proposal_no, $policy_details->customer_id, $policy_details->policy_number))
            {
                $productData = getProductDataByIc($policy_details->master_policy_id);

                $tokenParam = [
                    'grant_type'  => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
                    'username'    => config('constants.IcConstants.magma.MAGMA_USERNAME'),
                    'password'    => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
                    'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
                ];

                $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
                    'section'          => $productData->product_sub_type_code,
                    'method'           => 'Token Generation',
                    'requestMethod'    => 'post',
                    'type'             => 'tokenGeneration',
                    'enquiryId'        => $user_product_journey_id,
                    'productName'      => $productData->product_sub_type_name,
                    'transaction_type' => 'proposal'
                ]);
                $token = $get_response['response'];

                if ($token)
                {
                    $token_data = json_decode($token, true);

                    if (isset($token_data['access_token']))
                    {
                        $pdf_request_data = [
                            'PolicyNumber' => $policy_details->policy_number,
                            'CustomerID' => $policy_details->customer_id,
                            'IntermediaryCode' => config('constants.IcConstants.magma.MAGMA_INTERMEDIARYCODE'),
                            'ProposalNumber' => $policy_details->proposal_no
                        ];

                        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_POLICY_PDF_MAGMA_MOTOR'), $pdf_request_data, 'magma', [
                            'section'          => $productData->product_sub_type_code,
                            'method'           => 'Policy PDF',
                            'requestMethod'    => 'post',
                            'type'             => 'policyPdfGeneration',
                            'token'            => $token_data['access_token'],
                            'enquiryId'        => $user_product_journey_id,
                            'productName'      => $productData->product_sub_type_name,
                            'transaction_type' => 'proposal'
                        ]);
                        $pdf_response = $get_response['response'];

                        if ($pdf_response)
                        {
                            $pdf_response = json_decode($pdf_response, true);

                            if ($pdf_response['ServiceResult'] == 'Success')
                            {
                                Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'magma/' . md5($policy_details->user_proposal_id) . '.pdf', base64_decode($pdf_response['OutputResult']['PolicyBase64String']));

                                updateJourneyStage([
                                    'user_product_journey_id' => $user_product_journey_id,
                                    'stage'                   => STAGE_NAMES['POLICY_ISSUED']
                                ]);

                                DB::table('payment_request_response')
                                    ->where('user_product_journey_id', $user_product_journey_id)
                                    ->where('active',1)
                                    ->update([
                                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                                    ]);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $policy_details->user_proposal_id], [
                                        'policy_number' => $pdf_response['OutputResult']['PolicyNumber'],
                                        'pdf_url'       => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'magma/' . md5($policy_details->user_proposal_id) . '.pdf',
                                        'status'        => 'SUCCESS'
                                    ]
                                );

                                $pdf_url = file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'magma/'. md5($policy_details->user_proposal_id). '.pdf');

                                $pdf_response_data = [
                                    'status' => true,
                                    'msg' => 'sucess',
                                    'data' => [
                                        'policy_number' => $policy_details->policy_number,
                                        'pdf_link'      => $pdf_url
                                    ]
                                ];
                            }
                            else
                            {
                                $pdf_response_data = [
                                    'status' => false,
                                    'msg'    => 'Error : Service Error',
                                    'dev_log'=> $pdf_response['ErrorText'] ?? 'Error : Service Error',
                                    'data'   => []
                                ];
                            }
                        }
                        else
                        {
                            $pdf_response_data = [
                                'status' => false,
                                'msg'    => 'Error : Service Error',
                                'data'   => []
                            ];
                        }
                    }
                    else
                    {
                        $pdf_response_data = [
                            'status' => false,
                            'msg'    => 'Error : Service Error',
                            'dev_log'=> $token_data['ErrorText'] ?? 'Error : Service Error',
                            'data'   => []
                        ];
                    }
                }
                else
                {
                    $pdf_response_data = [
                        'status' => false,
                        'msg'    => 'Error : Service Error',
                        'data'   => []
                    ];
                }
            }
            else
            {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'No data found',
                    'data'   => []
                ];
            }
        }
        else
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'No data found',
                'data'   => []
            ];
        }

        return response()->json($pdf_response_data);
    }
}
