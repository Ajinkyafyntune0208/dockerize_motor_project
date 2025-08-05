<?php

namespace App\Http\Controllers\Payment\Services\Bike\V2;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/GoDigitHelper.php';

use App\Models\QuoteLog;
use App\Models\JourneyStage;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\CkycGodigitFailedCasesData;
use App\Http\Controllers\Payment\Services\Bike\goDigitPaymentGateway as godigit;

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
            $is_agent_float = 'N';
            $float_agent_data = DB::table('cv_agent_mappings')
                                ->where('user_product_journey_id', $enquiryId)
                                ->where('seller_type','P')
                                ->first();
            if( isset($float_agent_data->category) && $float_agent_data->category == 'Essone')
            {
                $is_agent_float ='Y';
            }
            if($is_agent_float == 'Y')
            {
                DB::table('payment_request_response')
                                ->where('user_product_journey_id', $enquiryId)
                                ->update(['active' => 0]);

                            DB::table('payment_request_response')->insert([
                                'quote_id'                  => $quote_log_id,
                                'user_product_journey_id'   => $enquiryId,
                                'user_proposal_id'          => $proposal->user_proposal_id,
                                'ic_id'                     => $icId,
                                'order_id'                  => $proposal->unique_quote,
                                'amount'                    => $proposal->final_payable_amount,
                                'payment_url'               => '',
                                'return_url'                => route('bike.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                                'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                                'active'                    => 1
                            ]);
                $apdpayment = godigit::ApdPayment($enquiryId ,$request);
                if($apdpayment['status'] == true)
                {
                    return [
                        'status' => true,
                        'data' => [
                            'payment_type' => 1,
                            'user_proposal_id' => $proposal->user_proposal_id,
                            'policy_id' => $request->policyId,
                            'transactionNumber' => $proposal->unique_quote,
                            'is_agent_float' => 'Y',
                            'paymentUrl' => route('bike.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId, 'transactionNumber' => $proposal->unique_quote])
                        ]
                    ];
                }else
                {
                    return [
                        'status' => false,
                        'data' => [
                            'payment_type' => 1,
                            'user_proposal_id' => $proposal->user_proposal_id,
                            'policy_id' => $request->policyId,
                            'transactionNumber' => $proposal->unique_quote,
                            'is_agent_float' => 'Y',
                            'msg'            => isset($apdpayment['msg']) ? $apdpayment['msg'] :'',
                            'paymentUrl' => config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id),'msg' => $apdpayment['msg']])
                        ]
                    ];
                }
            }
            $access_token_resp = getToken($proposal->user_product_journey_id, $productData, 'proposal', $request->business_type);
            $access_token = ($access_token_resp['token']);
            $pg_request =  [
                "motorNewPaymentGenService" => [
                    "paymentMode" => "EB",
                    "applicationId" => $proposal->unique_proposal_id,
                    "cancelReturnUrl" => route('bike.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']]),
                    "successReturnUrl" => route('bike.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request['policyId']])
                ]
            ];
                if(config('IC.GODIGIT.V2.BIKE.ENVIRONMENT') == 'UAT'){
                    $pg_request = $pg_request['motorNewPaymentGenService'];
                }
                $get_response = getWsData(config('IC.GODIGIT.V2.BIKE.END_POINT_URL'),$pg_request,'godigit',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'section' => 'BIKE',
                    'authorization' => $access_token,
                    'method'   => 'oneapi PG Redirectional',
                    'integrationId' => config("IC.GODIGIT.V2.BIKE.PAYMENT_INTEGRATION_ID"),
                    'transaction_type' => 'proposal'
                ]);

                $data = $get_response['response'];
                if (!empty($data)) {
                    $check = json_decode($data);
                    if ((isset($check->timestamp) && !empty($check->error->message)) || (($check->statusCode ?? '' == 400)) || (empty($check->paymentLink))) {
                        return [
                            'status' => false,
                            'msg' => $check->error->message ?? 'Insurer not reachable' ,
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
                                'order_id'                  => $proposal->unique_quote,
                                'amount'                    => $proposal->final_payable_amount,
                                'payment_url'               => '',
                                'return_url'                => route('bike.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                                'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                                'active'                    => 1
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
                } else {
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
                'msg' => 'Proposal data not found'
            ];
        }
    }

    public static function confirm($request)
    {
        sleep(10);
        $request_data = $request->all();

        $proposal = UserProposal::where('user_proposal_id', $request_data['user_proposal_id'])->first();
        $enquiryId = $proposal->user_product_journey_id;
        $JourneyStage_data = JourneyStage::where('user_product_journey_id', $enquiryId)->first();

        if(isset($JourneyStage_data->stage) && (in_array($JourneyStage_data->stage, [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]))) {
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
        }
        
        $policyid = QuoteLog::where('user_product_journey_id',$proposal->user_product_journey_id)->pluck('master_policy_id')->first();
        $check_payment_status = checkGodigitPaymentStatus($proposal->user_product_journey_id, $policyid, $request, $proposal->proposal_no);
        if(!$check_payment_status['status'])
        {
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
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'BIKE', 'FAILURE'));
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
                        'Authorization' => config('IC.GODIGIT.V2.BIKE.PG_AUTHORIZATION'),
                    ],
                ]
            ];
    
            if(config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y')
            {
                if(config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') !== 'Y')
                {
                    if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                    {
                        $KycStatusApiResponse = GetKycStatusGoDIgitOneapi($proposal->user_product_journey_id,$proposal->proposal_no,  $request->product_name,$proposal->user_proposal_id,customEncrypt( $proposal->user_product_journey_id),$productData);
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
            if(config('IC.GODIGIT.V2.BIKE.ENVIRONMENT') == 'UAT'){
                $appno = $appno['motorPolicyPDF(usingapplicationid)'];
            }
            $get_response = getWsData(config('IC.GODIGIT.V2.BIKE.END_POINT_URL'),$appno,'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' =>'post',
                'section' => 'BIKE',
                'productName'  => $product->product_sub_type_name,
                'company'  => 'godigit',
                'authorization' => $access_token,
                'integrationId' =>config("IC.GODIGIT.V2.BIKE.PDF_INTEGRATION_ID"),
                'method'   => 'oneapi Policy PDF',
                'transaction_type' => 'proposal'
            ]);

            $data = $get_response['response'];
            if (!empty($data)) {
                $pdf_response = json_decode($data);
    
                if (isset($pdf_response->schedulePath)) {
                    $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/' . md5($proposal->user_proposal_id) . '.pdf';
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
                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'BIKE', 'SUCCESS'));
                    } else {
                        $policypdf = true;
                        $pdf_data = Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/' . md5($proposal->user_proposal_id) . '.pdf', $pdfData);
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
                                'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/' . md5($proposal->user_proposal_id) . '.pdf',
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
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
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
        $policyid = QuoteLog::where('user_product_journey_id',$user_product_journey_id)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($request['policyId']);
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

        $pdf_url_check = true;
        if ($policy_details->ic_pdf_url != '') {
            $pdf_url_check = httpRequestNormal($policy_details->ic_pdf_url, 'GET', [], [], [], [], false)['response'];
            if (isset($pdf_url_check['statusMessage']) && strpos($pdf_url_check['statusMessage'], 'This link expired on') !== false) {
                $pdf_url_check = false;
            }
        }

        if($policy_details->ic_pdf_url != '' && $pdf_url_check)
        {
            $filename=config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id).strtotime("now"). '.pdf';
            if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($filename))
            {
               Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($filename);
            }
                
            Storage::put($filename, httpRequestNormal($policy_details->ic_pdf_url,'GET',[],[],[],[],false)['response']);
            $pdf_url = file_url($filename);
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $policy_details->user_proposal_id],
                [
                    'policy_number' => $policy_details->proposal_no,
                    'pdf_url' => $filename,
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
            
                        } else {

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
            $productData = getProductDataByIc($request['policyId']);
            $access_token_resp = getToken($request->user_product_journey_id, $productData, 'proposal', $request->business_type);
            $access_token = ($access_token_resp['token']);

            $appno = [
                "motorPolicyPDF(usingapplicationid)" => [
                    'policyId' => $policy_details->unique_proposal_id,
                    "headerParam" => [
                        'Authorization' => config('IC.GODIGIT.V2.BIKE.PG_AUTHORIZATION')
                    ],
                ]
            ];

            if(config('IC.GODIGIT.V2.BIKE.ENVIRONMENT') == 'UAT'){
                $appno = $appno['motorPolicyPDF(usingapplicationid)'];
            }

            $get_response = getWsData(config('IC.GODIGIT.V2.BIKE.END_POINT_URL'),$appno,'godigit',
            [
                'enquiryId'     => $user_product_journey_id,
                'requestMethod' => 'post',
                'section'       => strtoupper($request->product_type),
                'productName'   => $request->product_name,
                'company'       => 'godigit',
                'authorization' => $access_token,
                'integrationId' => config("IC.GODIGIT.V2.BIKE.PDF_INTEGRATION_ID"),
                'method'        => 'oneapi Policy PDF',
                'transaction_type' => 'proposal'
            ]);
            $pdf_response = $get_response['response'];
            if(!empty($pdf_response))
            {
                $pdf_response = json_decode($pdf_response);
                if(isset($pdf_response->schedulePath))
                {
                    PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)
                        ->where('active', 1)
                        ->update([
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]);
                    $filename=config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id).strtotime("now"). '.pdf';

                    if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($filename))
                    {
                       Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($filename);
                    }
                    
                    Storage::put($filename, httpRequestNormal($pdf_response->schedulePath,'GET',[],[],[],[],false)['response']);
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $policy_details->user_proposal_id],
                        [
                            'policy_number' => $policy_details->proposal_no,
                            'ic_pdf_url' => $pdf_response->schedulePath,
                            'pdf_url' => $filename,
                            'status' => 'SUCCESS'
                        ]
                    );
                    $pdf_url = file_url($filename);
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
                }
                else
                {
                    $pdf_response_data = [
                        'status' => false,
                        'msg'    => 'Error : Service Error',
                        'dev'    => $pdf_response->message ?? 'Error : Service Error'
                    ];
                }
            }else {
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'Error : Service Error'
                ];
            }
        }
        return response()->json($pdf_response_data);
    }
}
