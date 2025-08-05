<?php

namespace App\Http\Controllers\Payment\Services;
include_once app_path().'/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/GoDigitHelper.php';

use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use App\Models\CkycGodigitFailedCasesData;
use App\Models\PaymentRequestResponse;
use App\Models\JourneyStage;
use App\Http\Controllers\Payment\Services\Pcv\V2\GoDigitPaymentGateway as oneapi;
use App\Models\CvAgentMapping;

class goDigitPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        if($proposal)
        {
            $enquiryId = customDecrypt($request['userProductJourneyId']);

            $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();
            $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('quote_id')
                ->first();
            $productData = getProductDataByIc($request['policyId']);

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $enquiryId,
                'seller_type' => 'P'
            ])
            ->first();
    
            $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_PG_AUTHORIZATION');

            if (!empty($posData)) {
    
                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $posData->agent_id,
                    'productSubTypeId' => $productData->product_sub_type_id,
                    'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $pgAuthKey = $credentials['data']['authorization_key'];
                }
            }

            if($productData->premium_type_id == 4)
            {
                $breakinDetails = DB::table('cv_breakin_status')
                    ->where('cv_breakin_status.user_proposal_id', '=', trim($proposal->user_proposal_id))
                    ->first();
                if ($breakinDetails->breakin_status_final == STAGE_NAMES['INSPECTION_APPROVED'])
                {
                    $pg_request = [
                        "applicationId" => $proposal->unique_proposal_id,
                        "cancelReturnUrl" => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']]),
                        "successReturnUrl" => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']]),
                    ];

                    $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_PAYMENT_GATEWAY_REDIRECTIONAL'),$pg_request,'godigit',
                    [
                        'enquiryId' => $proposal->user_product_journey_id,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'godigit',
                        'section' => $productData->product_sub_type_code,
                        'authorization' => $pgAuthKey,
                        'method'   => 'PG Redirection',
                        'transaction_type' => 'proposal'
                    ]);
                    $data = $get_response['response'];

                    if (!empty($data))
                    {
                        $check = json_decode($data);
                        if(isset($check->timestamp) && isset($check->message))
                        {
                            return [
                                'status' => false,
                                'msg' => $check->message,
                            ];
                        }
                        else
                        {
                            $pg_url = trim($data);
                            $breakin_make_time = strtotime('18:00:00');
                            $policy_start_date = date('d-m-Y'); 
                            // if($breakin_make_time > time())
                            // {
                            //    $policy_start_date = date('d-m-Y');
                            // }
                            // else
                            // {
                            //   $policy_start_date = date('d-m-Y');
                            // }

                            $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));

                            //$proposal->payment_url = $pg_url;
                            $proposal->policy_start_date = $policy_start_date;
                            $proposal->policy_end_date = $policy_end_date;
                            $proposal->save();


                            DB::table('payment_request_response')
                                ->where('user_product_journey_id', $enquiryId)
                                ->where('ic_id', $icId)
                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                ->update(['active' => 0]);

                            DB::table('payment_request_response')->insert([
                                'quote_id'                  => $quote_log_id,
                                'user_product_journey_id'   => $enquiryId,
                                'user_proposal_id'          => $proposal->user_proposal_id,
                                'ic_id'                     => $icId,
                                'order_id'                  => $proposal->proposal_no,
                                'amount'                    => $proposal->final_payable_amount,
                                'proposal_no'               => $proposal->proposal_no,
                                'payment_url'               => $pg_url,
                                'return_url'                => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                                'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                                'active'                    => 1
                            ]);

                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
                            ]);

                            return [
                                'status' => true,
                                'data' => [
                                    'payment_type' => 1,
                                    'paymentUrl' => $pg_url
                                ]
                            ];
                        }
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'msg' => 'Insurer not reachable'
                        ];
                    }
                }
                else
                {
                    return [
                            'status' => false,
                            'message' => 'Proposal is not Recommended.'
                        ];
                }
            }
            else
            {
                $pg_request = [
                    "applicationId" => $proposal->unique_proposal_id,
                    "cancelReturnUrl" => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                    "successReturnUrl" => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                ];

                $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_PAYMENT_GATEWAY_REDIRECTIONAL'),$pg_request,'godigit',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'section' => $productData->product_sub_type_code,
                    'authorization' => $pgAuthKey,
                    'method'   => 'PG Redirection',
                    'transaction_type' => 'proposal'
                ]);
                $data = $get_response['response'];

                if (!empty($data))
                {
                    $check = json_decode($data);
                    if(isset($check->timestamp) && isset($check->message))
                    {
                        return [
                            'status' => false,
                            'msg' => $check->message,
                        ];
                    }
                    else
                    {
                        $pg_url = trim($data);

                        DB::table('payment_request_response')
                                ->where('user_product_journey_id', $enquiryId)
                                ->update(['active' => 0]);

                        DB::table('payment_request_response')->insert([
                            'quote_id'                  => $quote_log_id,
                            'user_product_journey_id'   => $enquiryId,
                            'user_proposal_id'          => $proposal->user_proposal_id,
                            'ic_id'                     => $icId,
                            'order_id'                  => $proposal->proposal_no,
                            'amount'                    => $proposal->final_payable_amount,
                            'proposal_no'               => $proposal->proposal_no,
                            'payment_url'               => $pg_url,
                            'return_url'                => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                            'active'                    => 1,
                            'xml_data'                    => json_encode($pg_request)
                        ]);

                        updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
                            ]);

                        return [
                            'status' => true,
                            'data' => [
                                'payment_type' => 1,
                                'paymentUrl' => $pg_url
                            ]
                        ];
                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'msg' => 'Insurer not reachable'
                    ];
                }
            }
        }
        else
        {
            return [
                'status' => false,
                'msg' => 'Proposal data not found'
            ];
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        sleep(10);
        $request_data = $request->all();

        $proposal = UserProposal::where('user_proposal_id', $request_data['user_proposal_id'])->first();
        $product = getProductDataByIc($request_data['policy_id']);

        
        $enquiryId = $proposal->user_product_journey_id;
        $JourneyStage_data = JourneyStage::where('user_product_journey_id', $enquiryId)->first();

        if(isset($JourneyStage_data->stage) && (in_array($JourneyStage_data->stage, [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]))) {
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CV','SUCCESS'));
        }

        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $enquiryId,
            'seller_type' => 'P'
        ])
        ->first();

        $webuserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
        $password = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');
        $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_PG_AUTHORIZATION');

        if (!empty($posData)) {

            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $product->product_sub_type_id,
                'ic_integration_type' => $product->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $webuserId = $credentials['data']['web_user_id'];
                $password = $credentials['data']['password'];
                $pgAuthKey = $credentials['data']['authorization_key'];
            }
        }
        
        $url = config('constants.IcConstants.godigit.GODIGIT_BREAKIN_STATUS').trim($proposal->proposal_no);

        // sleep(15);

        $get_response = getWsData($url, $url, 'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'get',
                'section' => $product->product_sub_type_code,
                'productName' => $product->product_name,
                'company' => 'godigit',
                'method' => 'Check Policy Status',
                'transaction_type' => 'proposal',
                'webUserId' => $webuserId,
                'password' => $password
            ]
        );
        $data = $get_response['response'];

        if ($data) {
            $policy_status_data = json_decode($data, TRUE);

            if ( isset($policy_status_data['policyStatus']) && ( in_array($policy_status_data['policyStatus'],['EFFECTIVE','COMPLETE','UW_REFFERED']) ||  ( $policy_status_data['policyStatus'] == 'INCOMPLETE' && isset($policy_status_data['kycStatus']['paymentStatus']) && in_array($policy_status_data['kycStatus']['paymentStatus'], ['PAID','DONE'])) )) {
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
                        'policy_number' => $proposal->proposal_no,
                        'status' => 'SUCCESS'
                    ]
                );
                $appno = ['policyId' => $proposal['unique_proposal_id']];

                if(config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y')
                {
                    if(config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') !== 'Y')
                    {
                        if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                        {
                            $KycStatusApiResponse = GetKycStatusGoDIgit( $proposal->user_product_journey_id,$proposal->proposal_no,  $product->product_name,$proposal->user_proposal_id,customEncrypt( $proposal->user_product_journey_id));
                            if($KycStatusApiResponse['status'] !== true)
                            {
                                $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)->where('status',STAGE_NAMES['PAYMENT_SUCCESS'])->first();
                                CkycGodigitFailedCasesData::updateOrCreate(
                                    ['user_product_journey_id' => $proposal->user_product_journey_id],
                                    [
                                        'policy_no' => $proposal->proposal_no,
                                        'kyc_url' => $KycStatusApiResponse['message'] ?? '',
                                        'return_url' => $PaymentRequestResponse->return_url,
                                        'post_data' => $PaymentRequestResponse->response,
                                        'status' => 'failed',
                                    ]
                                );
                
                            }else
                            {
            
                                $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)->where('status',STAGE_NAMES['PAYMENT_SUCCESS'])->first();
                                CkycGodigitFailedCasesData::updateOrCreate(
                                    ['user_product_journey_id' => $proposal->user_product_journey_id],
                                    [
                                        'policy_no' => $proposal->proposal_no,
                                        'return_url' => $PaymentRequestResponse->return_url,
                                        'post_data' => $PaymentRequestResponse->response,
                                        'status' => 'success',
                                    ]
                                );
                                UserProposal::where('user_product_journey_id',$proposal->user_product_journey_id)
                                            ->where('user_proposal_id' ,$proposal->user_proposal_id)
                                            ->update(['is_ckyc_verified' => 'Y']);
                
                            }
                        }
                    }
                }

                $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_POLICY_PDF'),$appno,'godigit',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'section' => $product->product_sub_type_code,
                    'productName'  => $product->product_name,
                    'company'  => 'godigit',
                    'authorization' => $pgAuthKey,
                    'method'   => 'Policy PDF',
                    'transaction_type' => 'proposal'
                ]);
                $data = $get_response['response'];

                if(!empty($data))
                {
                    $pdf_response = json_decode($data);
                    if(isset($pdf_response->schedulePath))
                    {
                        $pdfData = self::getPDFData($pdf_response->schedulePath);
                        $pdf_data= Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($proposal->user_proposal_id). '.pdf', $pdfData);

                        if($pdf_data == true)
                        {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' => $proposal->proposal_no,
                                    'ic_pdf_url' => $pdf_response->schedulePath,
                                    'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($proposal->user_proposal_id). '.pdf',
                                    'status' => 'SUCCESS'
                                ]
                            );
                        }
                    }
                    else
                    {
                        $pdf_response = (object)[
                            'schedulePath' => ''
                        ];

                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $proposal->user_proposal_id],
                            [
                                'policy_number' => $proposal->proposal_no,
                                'status' => 'SUCCESS'
                            ]
                        );
                    }
                }

                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'SUCCESS'));
            }
            elseif (isset($policy_status_data['policyStatus']) && ($policy_status_data['policyStatus'] == 'INCOMPLETE' || $policy_status_data['policyStatus'] == 'DECLINED'))
            {
                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('active',1)
                ->update([
                    'response' => $request->All(),
                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'FAILURE'));
            } else {
                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('active', 1)
                    ->update([
                        'response' => $request->All()
                    ]);

                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'FAILURE'));
            }
        } else {
            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
    }

    static public function retry_pdf($proposal)
    {
        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'seller_type' => 'P'
        ])
        ->first();

        $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_PG_AUTHORIZATION');

        if (!empty($posData)) {

            $policyId = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->pluck('master_policy_id')
            ->first();

            $productData = getProductDataByIc($policyId);

            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $productData->product_sub_type_id,
                'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $pgAuthKey = $credentials['data']['authorization_key'];
            }
        }

        $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_POLICY_PDF'),['policyId' => $proposal->unique_proposal_id],'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' =>'post',
                'section' => 'Policy PDF',
                'productName'  => 'Taxi Upto 6 Seater',
                'company'  => 'godigit',
                'authorization' => $pgAuthKey,
                'method'   => 'Policy PDF',
                'transaction_type' => 'proposal'
            ]);
            $data = $get_response['response'];
            if(!empty($data))
            {
                $pdf_response = json_decode($data);
                if(isset($pdf_response->schedulePath))
                {
                    $pdfData = self::getPDFData($pdf_response->schedulePath);
                    $pdf_data= Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. $proposal->user_proposal_id. '.pdf', $pdfData);
                    if($pdf_data) {
                        PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                            'proposal_id' => $proposal->user_proposal_id,
                            'policy_number' => $proposal->policy_no,
                            'policy_start_date' => $proposal->policy_start_date,
                            'ncb' => $proposal->ncb_discount,
                            'policy_start_date' => $proposal->policy_start_date,
                            'premium' => $proposal->total_premium,
                            'ic_pdf_url' => $pdf_response->schedulePath,
                            'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. $proposal->user_proposal_id. '.pdf',
                            'status' => 'SUCCESS'
                        ]);
                    return response()->json([
                        'status' => true,
                        'msg' => 'PDF Regenrated SuccessFully..!',
                    ]);
                    }
                }
            }
        return response()->json([
            'status' => false,
            'msg' => 'PDF Regenration Failed...!'
        ]);
    }

    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $policy_details = DB::table('payment_request_response as prr')
                      ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
                      ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
                      ->where('prr.user_product_journey_id',$user_product_journey_id)
                      ->where('prr.active',1)
                      ->select(
                        'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                        'pd.policy_number','pd.pdf_url','pd.ic_pdf_url'
                        )
                      ->first();
        if(empty($policy_details))
        {
            return response()->json([
                'status' => false,
                'msg' => 'Payment details not found.'
            ]);
        }
        $policyid = QuoteLog::where('user_product_journey_id',$user_product_journey_id)->pluck('master_policy_id')->first();
        $check_payment_status = godigitPaymentStatusCheck($user_product_journey_id, $policyid ,$policy_details->proposal_no);
        if(!$check_payment_status['status'])
        {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_product_journey_id)
                ->where('active', 1)
                ->update([
                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                ]);

            updateJourneyStage([
                'user_product_journey_id' => $user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);
        }
        if($policy_details->ic_pdf_url != '')
        {
            $pdfData = self::getPDFData($policy_details->ic_pdf_url);
            Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id). '.pdf', $pdfData);
            $pdf_url = file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id). '.pdf');
            $pdf_response_data = [
                'status' => true,
                'msg' => 'sucess',
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => $pdf_url
                ]
            ];

            PolicyDetails::updateOrCreate(
                ['proposal_id' => $policy_details->user_proposal_id],
                [
                    'policy_number' => $policy_details->proposal_no,
                    'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id). '.pdf',
                    'status' => 'SUCCESS'
                ]
            );

            updateJourneyStage([
                'user_product_journey_id' => $user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED']
            ]);

            DB::table('payment_request_response')
                ->where('user_product_journey_id', $user_product_journey_id)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
        }
        else
        {
            if(config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y')
            {
                if(config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') !== 'Y')
                {
                    if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                    {
                        $KycStatusApiResponse = GetKycStatusGoDIgit( $user_product_journey_id,$policy_details->proposal_no,  $request->product_name,$policy_details->user_proposal_id,customEncrypt( $user_product_journey_id));
                        if($KycStatusApiResponse['status'] !== true)
                        {
                            $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->where('status',STAGE_NAMES['PAYMENT_SUCCESS'])->first();
                            CkycGodigitFailedCasesData::updateOrCreate(
                                ['user_product_journey_id' => $user_product_journey_id],
                                [
                                    'policy_no' => $policy_details->proposal_no,
                                    'kyc_url' => $KycStatusApiResponse['message'] ?? '',
                                    'return_url' => $PaymentRequestResponse->return_url ?? '',
                                    'post_data' => $PaymentRequestResponse->response ?? '',
                                    'status' => 'failed',
                                ]
                            );
            
                        }else
                        {

                            $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->where('status',STAGE_NAMES['PAYMENT_SUCCESS'])->first();
                            CkycGodigitFailedCasesData::updateOrCreate(
                                ['user_product_journey_id' => $user_product_journey_id],
                                [
                                    'policy_no' => $policy_details->proposal_no,
                                    'return_url' => $PaymentRequestResponse->return_url ?? '',
                                    'post_data' => $PaymentRequestResponse->response ?? '',
                                    'status' => 'success',
                                ]
                            );

                            UserProposal::where('user_product_journey_id',$user_product_journey_id)
                            ->where('user_proposal_id' ,$policy_details->user_proposal_id)
                            ->update(['is_ckyc_verified' => 'Y']);
            
                        }
                    }
                }
            }

            $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_PG_AUTHORIZATION');

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $user_product_journey_id,
                'seller_type' => 'P'
            ])
            ->first();

            if (!empty($posData)) {

                $productData = getProductDataByIc($policyid);

                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $posData->agent_id,
                    'productSubTypeId' => $productData->product_sub_type_id,
                    'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                ]);

                if ($credentials['status'] ?? false) {
                    $pgAuthKey = $credentials['data']['authorization_key'];
                }
            }

            $appno = ['policyId' => $policy_details->unique_proposal_id];
            $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_POLICY_PDF'),$appno,'godigit',
            [
                'enquiryId'     => $user_product_journey_id,
                'requestMethod' => 'post',
                'section'       => strtoupper($request->product_type),
                'productName'   => $request->product_name,
                'company'       => 'godigit',
                'authorization' => $pgAuthKey,
                'method'        => 'RE HIT PDF',
                'transaction_type' => 'proposal'
            ]);
            $pdf_response = $get_response['response'];
            if(!empty($pdf_response))
            {
                $pdf_response = json_decode($pdf_response);
                if(isset($pdf_response->schedulePath))
                {
                    $pdfData = self::getPDFData($pdf_response->schedulePath);
                    Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id). '.pdf', $pdfData);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $policy_details->user_proposal_id],
                        [
                            'policy_number' => $policy_details->proposal_no,
                            'ic_pdf_url' => $pdf_response->schedulePath,
                            'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id). '.pdf',
                            'status' => 'SUCCESS'
                        ]
                    );
                    $pdf_url = file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id). '.pdf');
                    $pdf_response_data = [
                        'status' => true,
                        'msg' => 'sucess',
                        'data' => [
                            'policy_number' => $policy_details->proposal_no,
                            'pdf_link'      => $pdf_url
                        ]
                    ];

                    updateJourneyStage([
                        'user_product_journey_id' => $user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);

                    DB::table('payment_request_response')
                        ->where('user_product_journey_id', $user_product_journey_id)
                        ->where('active',1)
                        ->update([
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]);
                }
                else
                {
                    $pdf_response_data = [
                        'status' => false,
                        'msg'    => 'Error : Service Error',
                        'dev_log'=> $pdf_response->message ?? 'Error : Service Error',
                    ];
                }
            } else {
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'Error : Service Error',
                ];
            }
        }
        return response()->json($pdf_response_data);
    }
    static public function getPDFData($pdf_path)
    {
        return httpRequestNormal($pdf_path, 'GET', [], [], [], [], false)['response'];
    }
}
