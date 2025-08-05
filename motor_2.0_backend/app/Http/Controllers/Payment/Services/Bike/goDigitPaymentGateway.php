<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\CkycGodigitFailedCasesData;
use App\Models\CvAgentMapping;
use App\Models\JourneyStage;

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
            #$is_agent_float =config('constants.motorConstant.IS_APD_ENABLED');
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
                $user_id = ($is_agent_float == 'Y') ? config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID_AGENT_FLOAT') : config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');

                $password = ($is_agent_float == 'Y') ? config('constants.IcConstants.godigit.GODIGIT_PASSWORD_AGENT_FLOAT') : config('constants.IcConstants.godigit.GODIGIT_PASSWORD');
                $apdpayment=goDigitPaymentGateway::ApdPayment($enquiryId ,$request);
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
                $pg_request = [
                    "applicationId" => $proposal->unique_proposal_id,
                    "cancelReturnUrl" => route('bike.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                    "successReturnUrl" => route('bike.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
                ];

                $posData = CvAgentMapping::where([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'seller_type' => 'P'
                ])
                ->first();
                $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_BIKE_PG_AUTHORIZATION');

                if (!empty($posData)) {

                    $credentials = getPospImdMapping([
                        'sellerType' => 'P',
                        'sellerUserId' => $posData->agent_id,
                        'productSubTypeId' => 2,
                        'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                    ]);
        
                    if ($credentials['status'] ?? false) {
                        $pgAuthKey = $credentials['data']['authorization_key'];
                    }
                }

              // return $pg_request;
                $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_PAYMENT_GATEWAY_REDIRECTIONAL'),$pg_request,'godigit',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'section' => 'BIKE',
                    'authorization' => $pgAuthKey,
                    'method'   => 'PG Redirectional',
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
                                'payment_url'               => $pg_url,
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
                'msg' => 'Proposal data not found'
            ];
        }
    }

    public static function ApdPayment($enquiryId ,$request)
    {
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();
        $productData = getProductDataByIc($request['policyId']);
        #$enquiry_id = getUUID();
        $apdRequest =[
            'payment' =>
                [
                    'paymentType' => 'AGENT_FLOAT',
                    'premiumAmount' =>'INR '.$proposal->final_payable_amount,
                    'transactionId' => $proposal->unique_quote,#transaction id
                    'paymentDate'   => date('Y-m-d'),
                    'instrumentNumber' => null,
                    'ifscCode'      => null,
                    'micrCode'      => null,
                ],
                'enquiryId' => $proposal->unique_quote,#enquiryid
                'externalPolicyNumber' => ''
            ];
            
            $url = config('constants.IcConstants.godigit.GODIGIT_BIKE_PAYMENT_APD_URL').$proposal->unique_proposal_id.'/issuance';
            $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID_AGENT_FLOAT');
            $password  = config('constants.IcConstants.godigit.GODIGIT_PASSWORD_AGENT_FLOAT');

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $proposal->user_product_journey_id,
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
                    $webUserId = $credentials['data']['web_user_id'];
                    $password = $credentials['data']['password'];
                }
            }
            
            $get_response = getWsData($url,$apdRequest,'godigit',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'put',
                    'productName'  => $productData->product_sub_type_name,
                    'company'  => 'godigit',
                    'section' => 'PG Redirectional',
                    'method'   => 'Issue Contract',
                    'transaction_type' => 'payment',
                    'webUserId' => $webUserId,
                    'password' => $password
                ]);
            $data = $get_response['response'];
            if(!empty($data))
            {
                $apd_response = json_decode($data,TRUE);
                if(isset($apd_response['policyStatus']) && $apd_response['policyStatus'] == 'COMPLETE')
                {
                    $url = config('constants.IcConstants.godigit.GODIGIT_BREAKIN_STATUS').trim($proposal->proposal_no);

                        #sleep(15);

                        $get_response = getWsData($url, $url, 'godigit',
                            [
                                'enquiryId' => $proposal->user_product_journey_id,
                                'requestMethod' => 'get',
                                'section' => $productData->product_sub_type_code,
                                'productName' => $productData->product_name,
                                'company' => 'godigit',
                                'webUserId' => $webUserId,
                                'password' => $password,
                                'method' => 'Check Policy Status',
                                'transaction_type' => 'payment'
                            ]
                        );
                        $data = $get_response['response'];

                        if ($data) {
                            $policy_status_data = json_decode($data, TRUE);
                            if (isset($policy_status_data['policyStatus']) && ($policy_status_data['policyStatus'] == 'EFFECTIVE' || $policy_status_data['policyStatus'] == 'COMPLETE')) {
                                
                            DB::table('payment_request_response')
                                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                    ->where('active',1)
                                    ->update([
                                        'response' => $request->All(),
                                        'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
                                    ]);
                                return [
                                    'status' => true,
                                    'msg'    => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                    'is_agent_float'   => 'Y'
                                ];
                                
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
                                return [
                                    'status' => false,
                                    'msg'    => STAGE_NAMES['PAYMENT_FAILED'],
                                ];
                                
                            }else
                            {
                                /* DB::table('payment_request_response')
                                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active',1)
                                ->update([
                                    'response' => $request->All(),
                                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                                ]);
                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                                ]); */
                                return [
                                    'status' => false,
                                    'msg'    => STAGE_NAMES['PAYMENT_FAILED'],
                                ];
                                
                            }
                        }
                }
                else{
                    /* DB::table('payment_request_response')
                                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active',1)
                                ->update([
                                    'response' => $request->All(),
                                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                                ]);
                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                                ]); */
                    return [
                        'status' => false,
                        'msg'    => isset($apd_response['error']['validationMessages'][0])?$apd_response['error']['validationMessages'][0] : 'Service Issue',
                    ];
                    
                }

            }else{
                /* DB::table('payment_request_response')
                                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active',1)
                                ->update([
                                    'response' => $request->All(),
                                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                                ]);
                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                                ]); */
                return [
                    'status' => false,
                    'msg'    => 'Service Issue',
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
        $enquiryId = $proposal->user_product_journey_id;
        $JourneyStage_data = JourneyStage::where('user_product_journey_id', $enquiryId)->first();

        if(isset($JourneyStage_data->stage) && (in_array($JourneyStage_data->stage, [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]))) {
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
        }
        
        $policyid = QuoteLog::where('user_product_journey_id',$proposal->user_product_journey_id)->pluck('master_policy_id')->first();
        $check_payment_status = godigitPaymentStatusCheck($proposal->user_product_journey_id, $policyid ,$proposal->proposal_no);
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
        /* if(isset($request_data['transactionNumber']))
        {
            $transaction_no = $request_data['transactionNumber']; */
            //$proposal->is_policy_issued = STAGE_NAMES['PAYMENT_SUCCESS'];
            //$proposal->save();
            $product = getProductDataByIc($request_data['policy_id']);
//            PaymentRequestResponse::updateOrCreate(
//                            ['user_product_journey_id' => $proposal->user_product_journey_id],
//                            [
//                                //'quote_id' => $proposal->user_proposal_id,
//                                //'user_product_journey_id' => $proposal->user_product_journey_id,
//                                //'ic_id' => $product->company_id,
//                                //'payment_url' => $proposal->payment_url,
//                                //'return_url' => route('cv.payment-confirm', ['godigit', 'user_proposal_id' => $proposal->user_product_journey_id, 'policy_id' => $request['policyId']]),
//                                //'order_id' => $proposal->proposal_no,
//                                //'amount' => $proposal->total_premium,
//                                'response' => $transaction_no,
//                                'status' => STAGE_NAMES['PAYMENT_SUCCESS']
//                            ]);
            updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
            PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_number' => $proposal->proposal_no,
                        'status' => 'SUCCESS'
                    ]
                );
            DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('active',1)
                    ->update([
                        'response' => $request->All(),
                        'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);
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
            // sleep(10);

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'seller_type' => 'P'
            ])
            ->first();
            $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_BIKE_PG_AUTHORIZATION');

            if (!empty($posData)) {
                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $posData->agent_id,
                    'productSubTypeId' => $product->product_sub_type_id,
                    'ic_integration_type' => 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $pgAuthKey = $credentials['data']['authorization_key'];
                }
            }

            $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_POLICY_PDF'),$appno,'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' =>'post',
                'section' => 'BIKE',
                'productName'  => $product->product_sub_type_name,
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
                    $filename=config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. md5($proposal->user_proposal_id).strtotime("now"). '.pdf';
                    if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($filename))
                    {
                       Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($filename);
                    }
                    //$pdf_data= Storage::put($filename, file_get_contents($pdf_response->schedulePath));
                    $pdf_data= Storage::put($filename, httpRequestNormal($pdf_response->schedulePath ,'GET',[],[],[],[],false)['response']);
                    
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
                                'pdf_url' => $filename,
                                'status' => 'SUCCESS'
                            ]
                        );
                    }
                }
//                else
//                {
//                    $pdf_response->schedulePath = '';
//                }
            }
            $enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
        /* }
        else
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
            $enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','FAILURE'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
        } */
    }

    static public function retry_pdf($proposal)
    {
        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'seller_type' => 'P'
        ])
        ->first();
        $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_BIKE_PG_AUTHORIZATION');

        if (!empty($posData)) {

            $policyId = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->pluck('master_policy_id')
            ->first();

            $productData = getProductDataByIc($policyId);

            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => 2,
                'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $pgAuthKey = $credentials['data']['authorization_key'];
            }
        }

        $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_POLICY_PDF'),['policyId' => $proposal->unique_proposal_id],'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' =>'post',
                'section' => 'BIKE',
                'productName'  => 'BIKE',
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
                   // $pdf_data= Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. $proposal->user_proposal_id. '.pdf', file_get_contents($pdf_response->schedulePath));
                    $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. $proposal->user_proposal_id. '.pdf';
                    if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($pdf_name))
                    {
                       Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($pdf_name);
                    }
                    
                    $pdf_data= Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. $proposal->user_proposal_id. '.pdf', httpRequestNormal($pdf_response->schedulePath,'GET',[],[],[],[],false)['response']);
                    if($pdf_data) {
                        PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                            'proposal_id' => $proposal->user_proposal_id,
                            'policy_number' => $proposal->policy_no,
                            'policy_start_date' => $proposal->policy_start_date,
                            'ncb' => $proposal->ncb_discount,
                            'policy_start_date' => $proposal->policy_start_date,
                            'premium' => $proposal->total_premium,
                            'ic_pdf_url' => $pdf_response->schedulePath,
                            'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. md5($proposal->user_proposal_id). '.pdf',
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
            $filename=config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'godigit/'. md5($policy_details->user_proposal_id).strtotime("now"). '.pdf';
            //Storage::put($filename, file_get_contents($policy_details->ic_pdf_url));
            
            //$pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf';
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
            $appno = ['policyId' => $policy_details->unique_proposal_id];

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $user_product_journey_id,
                'seller_type' => 'P'
            ])
            ->first();
            $pgAuthKey = config('constants.IcConstants.godigit.GODIGIT_BIKE_PG_AUTHORIZATION');

            if (!empty($posData)) {

                $productData = getProductDataByIc($policyid);

                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $posData->agent_id,
                    'productSubTypeId' => 2,
                    'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $pgAuthKey = $credentials['data']['authorization_key'];
                }
            }

            $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_POLICY_PDF'),$appno,'godigit',
            [
                'enquiryId'     => $user_product_journey_id,
                'requestMethod' => 'post',
                'section'       => strtoupper($request->product_type),
                'productName'   => $request->product_name,
                'company'       => 'godigit',
                'authorization' => $pgAuthKey,
                'method'        => 'Policy PDF',
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
                    //Storage::put($filename, file_get_contents($pdf_response->schedulePath));
                    //$pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf';
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
