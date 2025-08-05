<?php

namespace App\Http\Controllers\Payment\Services\Pcv\V2;
include_once app_path().'/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/GoDigitHelper.php';

use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use App\Models\CkycGodigitFailedCasesData;
use App\Models\CvAgentMapping;
use App\Models\PaymentRequestResponse;
use App\Models\JourneyStage;

class GoDigitPaymentGateway
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

            $access_token_resp = getToken($proposal->user_product_journey_id, $productData, 'proposal', $request->business_type);
            $access_token = ($access_token_resp['token']);

            $integrationId = config('IC.GODIGIT.V2.CV.PAYMENT_INTEGRATION_ID');
            
            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $enquiryId,
                'seller_type' => 'P'
            ])
            ->first();
            if (!empty($posData)) {

                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $posData->agent_id,
                    'productSubTypeId' => $productData->product_sub_type_id,
                    'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $integrationId = $credentials['data']['integration_id'];
                }
            }

            if ($productData->premium_type_id == 4 && $proposal->is_breakin_case == 'Y')
            {
                $breakinDetails = DB::table('cv_breakin_status')
                    ->where('cv_breakin_status.user_proposal_id', '=', trim($proposal->user_proposal_id))
                    ->first();
                if ($breakinDetails->breakin_status_final == STAGE_NAMES['INSPECTION_APPROVED'])
                {
                    $pg_request =  [
                        "motorNewPaymentGenService" => [
                            "paymentMode" => "EB",
                            "applicationId" => $proposal->unique_proposal_id,
                            "cancelReturnUrl" => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']]),
                            "successReturnUrl" => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']]),
                        ]
                    ];

                    if(config('IC.GODIGIT.V2.CV.REMOVE_GODIGIT_IDENTIFIER') == 'Y'){
                        $pg_request = $pg_request['motorNewPaymentGenService'];
                    }

                    $get_response = getWsData(config('IC.GODIGIT.V2.CV.ENDPOINT_URL'),$pg_request,'godigit',
                    [
                        'enquiryId' => $proposal->user_product_journey_id,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'godigit',
                        'section' => $productData->product_sub_type_code,
                        'authorization' => $access_token,
                        'method'   => 'oneapi PG Redirectional',
                        'integrationId' => $integrationId,
                        'transaction_type' => 'proposal'
                    ]);
                    $data = $get_response['response'];
                    if (!empty($data))
                    {
                        $check = json_decode($data);
                        if (empty($check->paymentLink)) { 
                            return [
                                'status' => false,
                                'msg' => 'Insurer not reachable'
                            ];
                        }
                      
                        if(isset($check->timestamp) && isset($check->message))
                        {
                            return [
                                'status' => false,
                                'msg' => $check->message ?? 'Insurer not reachable'
                            ];
                        }
                        else
                        {
                            $pg_url = $check->paymentLink;
                            $breakin_make_time = strtotime('18:00:00');
                            $policy_start_date = date('d-m-Y'); 
                            if($breakin_make_time > time())
                            {
                               $policy_start_date = date('d-m-Y');
                            }
                            else
                            {
                              $policy_start_date = date('d-m-Y');
                            }

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
                $pg_request =  [
                    "motorNewPaymentGenService" => [
                        "paymentMode" => "EB",
                        "applicationId" => $proposal->unique_proposal_id,
                        "cancelReturnUrl" =>  route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']]),
                        "successReturnUrl" => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']]),
                    ]
                ];

                if(config('IC.GODIGIT.V2.CV.REMOVE_GODIGIT_IDENTIFIER') == 'Y'){
                    $pg_request = $pg_request['motorNewPaymentGenService'];
                }

                $get_response = getWsData(config('IC.GODIGIT.V2.CV.ENDPOINT_URL'),$pg_request,'godigit',
                [
                  
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' => 'post',
                    'productName' => $productData->product_name,
                    'company' => 'godigit',
                    'section' => 'PG Redirectional',
                    'authorization' => $access_token,
                    'method'   => 'oneapi PG Redirectional',
                    'integrationId' => $integrationId,
                    'transaction_type' => 'proposal'
                ]);
                $data = $get_response['response'];

                if (!empty($data)) {
                    $check = json_decode($data);
                    if (isset($check->timestamp) && isset($check->error->message) || (empty($check->paymentLink))) {
                        return [
                            'status' => false,
                            'msg' => $check->error->message?? 'Insurer not reachable'
                        ];
                    } else if (!empty($check->paymentLink)) {
                        $pg_url = $check->paymentLink;
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
                } elseif (json_decode($data)->error) {
                    $error_msg = json_decode($data)->error->message;
                    return [
                        $error_msg
                    ];
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
        $policyid = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)->pluck('master_policy_id')->first();
        
        $enquiryId = $proposal->user_product_journey_id;
        $JourneyStage_data = JourneyStage::where('user_product_journey_id', $enquiryId)->first();

        if(isset($JourneyStage_data->stage) && (in_array($JourneyStage_data->stage, [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]))) {
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CV','SUCCESS'));
        }
        $check_payment_status = checkGodigitPaymentStatus($proposal->user_product_journey_id, $policyid, $request, $proposal->proposal_no); 
        
        if (!$check_payment_status['status']) {
            $enquiryId = $proposal->user_product_journey_id;
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('active', 1)
                ->update([
                    'response' => $request->All(),
                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                ]);

            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CV', 'FAILURE'));
        }        


        $product = getProductDataByIc($request_data['policy_id']);

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
        ]);

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $proposal->user_proposal_id],
            [
                'policy_number' => $proposal->proposal_no,
                'status' => 'SUCCESS'
            ]
        );
        $productData = getProductDataByIc($request['policyId']);
        $access_token_resp = getToken($proposal->user_product_journey_id, $product, 'proposal', $request->business_type);
        $access_token = ($access_token_resp['token']);
        $appno = [
            "motorPolicyPDF(usingapplicationid)" => [
                'policyId' => $proposal['unique_proposal_id'],
                "headerParam" => [
                    'Authorization' => config('IC.GODIGIT.V2.CV.PG_AUTHORIZATION'),
                ],
            ]
        ];

        if(config('IC.GODIGIT.V2.CV.REMOVE_PDF_GODIGIT_IDENTIFIER') == 'Y'){
            $appno = $appno['motorPolicyPDF(usingapplicationid)'];
        }

        if (config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y') {
            if (config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') !== 'Y') {
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $KycStatusApiResponse = GetKycStatusGoDIgitOneapi($proposal->user_product_journey_id,$proposal->proposal_no,  $request->product_name,$proposal->user_proposal_id,customEncrypt( $proposal->user_product_journey_id),$productData);
                    if ($KycStatusApiResponse['status'] !== true) {
                        $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)->where('status', STAGE_NAMES['PAYMENT_SUCCESS'])->first();
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
                    } else {

                        $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)->where('status', STAGE_NAMES['PAYMENT_SUCCESS'])->first();
                        CkycGodigitFailedCasesData::updateOrCreate(
                            ['user_product_journey_id' => $proposal->user_product_journey_id],
                            [
                                'policy_no' => $proposal->proposal_no,
                                'return_url' => $PaymentRequestResponse->return_url,
                                'post_data' => $PaymentRequestResponse->response,
                                'status' => 'success',
                            ]
                        );

                        UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update(['is_ckyc_verified' => 'Y']);
                    }
                }
            }
        }

        $integrationId = config('IC.GODIGIT.V2.CV.PDF_INTEGRATION_ID');
        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $enquiryId,
            'seller_type' => 'P'
        ])
        ->first();
        if (!empty($posData)) {

            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $productData->product_sub_type_id,
                'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $integrationId = $credentials['data']['integration_id'];
            }
        }

        $get_response = getWsData(
            config('IC.GODIGIT.V2.CV.END_POINT_URL'),
            $appno,
            'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'post',
                'section' => 'Policy PDF',
                'productName'  => $product->product_name,
                'company'  => 'godigit',
                'authorization' => $access_token,
                'integrationId' => $integrationId,
                'method'   => 'oneapi Policy PDF',
                'transaction_type' => 'proposal'
            ]
        );
        $data = $get_response['response'];

        if (!empty($data)) {
            $pdf_response = json_decode($data);

            if (isset($pdf_response->schedulePath)) {
                $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/' . md5($proposal->user_proposal_id) . '.pdf';
                if (Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->exists($pdf_name)) {
                    Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->delete($pdf_name);
                }
                $pdfData = httpRequestNormal($pdf_response->schedulePath, 'GET', [], [], [], [], false)['response'];
                $policypdf = false;
                if (stripos($pdfData, '%PDF') !== 0) {
                    $policypdf = false;
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_number' => $proposal->proposal_no,
                            'ic_pdf_url' => $pdf_response->schedulePath,
                        ]
                    );
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CV', 'SUCCESS'));
                } else {
                    $policypdf = true;
                    $pdf_data = Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/' . md5($proposal->user_proposal_id) . '.pdf', $pdfData);
                }

                if ($policypdf == true) {
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_number' => $proposal->proposal_no,
                            'ic_pdf_url' => $pdf_response->schedulePath,
                            'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'godigit/' . md5($proposal->user_proposal_id) . '.pdf',
                            'status' => 'SUCCESS'
                        ]
                    );
                }
            } else {
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
            }
        } else {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
        }
        $enquiryId = $proposal->user_product_journey_id;
        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CV', 'SUCCESS'));
    }

    static public function retry_pdf($proposal)
    {
        $product = getProductDataByIc($proposal['policy_id']);

        $integrationId = config('IC.GODIGIT.V2.CV.PDF_INTEGRATION_ID');
        $pgAuthKey = config('IC.GODIGIT.V2.CV.PG_AUTHORIZATION');

        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'seller_type' => 'P'
        ])
        ->first();
        if (!empty($posData)) {

            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $product->product_sub_type_id,
                'ic_integration_type' => $product->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $integrationId = $credentials['data']['integration_id'];
                $pgAuthKey = $credentials['data']['authorization_key'];
            }
        }

        $get_response = getWsData(
            config('IC.GODIGIT.V2.CV.END_POINT_URL'),
            ['policyId' => $proposal->unique_proposal_id],
            'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'post',
                'section' => 'Policy PDF',
                'productName'  => $product->product_name,
                'company'  => 'godigit',
                'authorization' => $pgAuthKey,
                'integrationId' => $integrationId,
                'method'   => 'oneapi Policy PDF',
                'transaction_type' => 'proposal'
            ]
        );
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
        $productData = getProductDataByIc($policyid);
        $check_payment_status = checkGodigitPaymentStatus($user_product_journey_id, $policyid, $request, $policy_details->proposal_no);
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
        
        if ($policy_details->ic_pdf_url != '') {
            $pdf_url_check = httpRequestNormal($policy_details->ic_pdf_url, 'GET', [], [], [], [], false)['response'];
            if (isset($pdf_url_check['statusMessage']) && strpos($pdf_url_check['statusMessage'], 'This link expired on') !== false) {
                $pdf_url_check = false;
            } else {
                $pdf_url_check = true;
            }
        }

        if($policy_details->ic_pdf_url != '' && $pdf_url_check) 
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
                        $KycStatusApiResponse = GetKycStatusGoDIgitOneapi($user_product_journey_id,$policy_details->proposal_no,  $request->product_name,$policy_details->user_proposal_id,customEncrypt( $user_product_journey_id),$productData);
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
            $access_token_resp = getToken($request->user_product_journey_id, $productData, 'proposal', $request->business_type);
            $access_token = ($access_token_resp['token']);

            $integrationId = config('IC.GODIGIT.V2.CV.PDF_INTEGRATION_ID');
            $pgAuthKey = config('IC.GODIGIT.V2.CV.PG_AUTHORIZATION');

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $user_product_journey_id,
                'seller_type' => 'P'
            ])
            ->first();
            if (!empty($posData)) {

                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $posData->agent_id,
                    'productSubTypeId' => $productData->product_sub_type_id,
                    'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $integrationId = $credentials['data']['integration_id'];
                    $pgAuthKey = $credentials['data']['authorization_key'];
                }
            }
            

            $appno = [
                "motorPolicyPDF(usingapplicationid)" => [
                    "policyId" => $policy_details->unique_proposal_id,
                    "headerParam" => [
                        "Authorization" => $pgAuthKey
                    ],
                ]
            ];

            if(config('IC.GODIGIT.V2.CV.REMOVE_PDF_GODIGIT_IDENTIFIER') == 'Y'){
                $appno = $appno['motorPolicyPDF(usingapplicationid)'];
            }

            $get_response = getWsData(
                config('IC.GODIGIT.V2.CV.END_POINT_URL'),
                $appno,
                'godigit',
                [
                    'enquiryId'     => $user_product_journey_id,
                    'requestMethod' => 'post',
                    'section'       => strtoupper($request->product_type),
                    'productName'   => $request->product_name,
                    'company'       => 'godigit',
                    'authorization' => $access_token,
                    'integrationId' => $integrationId,
                    'method'        => 'oneapi Policy PDF',
                    'transaction_type' => 'proposal'
                ]
            );
            // $policy_pdf = "Motor-Policy PDF (using application id)";
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
