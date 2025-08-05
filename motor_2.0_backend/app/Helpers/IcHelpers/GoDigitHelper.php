<?php

use App\Models\UserProposal;
use App\Http\Controllers\CkycController;
use Illuminate\Http\Request;
use App\Http\Controllers\Ckyc\CkycCommonController;
use App\Models\CkycLogsRequestResponse;
use App\Models\CvAgentMapping;

class GoDigitHelper
{
    public static function getToken($enquiryId, $productData, $transaction_type = 'quote', $type = 'renewal')
    {
        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $enquiryId,
            'seller_type' => 'P'
        ])
        ->first();

        $userName = config('IC.GODIGIT.V2.USERNAME');
        $password = config('IC.GODIGIT.V2.PASSWORD');

        if (!empty($posData)) {
            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $productData->product_sub_type_id,
                'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $userName = $credentials['data']['user_name'];
                $password = $credentials['data']['password'];
            }
        }
        
        $tokenrequest  = [
            "username" => $userName,
            "password" => $password
        ];

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name ?? "cv",
            'company'           => 'godigit',
            'section'           => $productData->product_sub_type_code ?? "cv",
            'method'            => 'token generation',
            'transaction_type'  => $transaction_type,
            'type'              => $type,
            'headers' => [
                'Content-Type'   => "application/json",
                "Connection" => "Keep-Alive",
                'Accept'        => "application/json",
            ]

        ];
        
        $tokenservice = getWsData(config('IC.GODIGIT.V2.TOKEN_GENERATION_URL'), $tokenrequest, 'godigit', $additional_data);

        $tokenserviceresponse  = $tokenservice['response'];
        $tokenservicejson = json_decode($tokenserviceresponse);
        if (empty($tokenservicejson) || !isset($tokenservicejson->access_token)) {
            return [
                'status' => false,
                'message' => 'Getting error in token service '
            ];
        } else {
            return [
                'status' => true,
                'token' => $tokenservicejson->access_token
            ];
        }
    }

    function loadWebServiceHelper($product)
    {
        if ($product->product_sub_type_code == 'CAR') {
            include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        } else if ($product->product_sub_type_code == 'BIKE') {
            include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
        } else {
            include_once app_path() . '/Helpers/CvWebServiceHelper.php';
        }
    }
  
    function checkGodigitPaymentStatus($user_product_journey_id, $policyid, $request, $proposal_no)
    {
        $product = getProductDataByIc($policyid);
        $this->loadWebServiceHelper($product);
        $integrationId = '';
        if ($product->product_sub_type_code == 'CAR') {
            $integrationId = config('IC.GODIGIT.V2.CAR.PAYMENT_CHECK_INTEGRATION_ID');
        } else if ($product->product_sub_type_code == 'BIKE') {
            $integrationId = config('IC.GODIGIT.V2.BIKE.PAYMENT_CHECK_INTEGRATION_ID');
        } else {
            $integrationId = config('IC.GODIGIT.V2.CV.PAYMENT_CHECK_INTEGRATION_ID');
        }
            if ($product->product_sub_type_code == 'CAR') {
                $integrationId = config('IC.GODIGIT.V2.CAR.PAYMENT_CHECK_INTEGRATION_ID');
            } else if ($product->product_sub_type_code == 'BIKE') {
                $integrationId = config('IC.GODIGIT.V2.BIKE.PAYMENT_CHECK_INTEGRATION_ID');
            } else {
                $integrationId = config('IC.GODIGIT.V2.CV.PAYMENT_CHECK_INTEGRATION_ID');
            }

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $user_product_journey_id,
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
                }
            }
            
        $access_token_resp = getToken($user_product_journey_id, $product, 'proposal', $request->business_type);
        $access_token = ($access_token_resp['token']);

        $policy_status = [
            "motorMotorPolicystatussearchApi" => [
                "queryParam" => [
                    'policyNumber' => $proposal_no,
                ],
            ]
        ];
        if(config('IC.GODIGIT.V2.CAR.ENVIRONMENT') == 'UAT'){
            $policy_status = $policy_status['motorMotorPolicystatussearchApi'];
        }
        $get_response = getWsData(
            config('IC.GODIGIT.V2.PAYMENT_END_POINT_URL'),
            $policy_status,
            'godigit',
            [
                'enquiryId' => $user_product_journey_id,
                'requestMethod' => 'post',
                'section' => $product->product_sub_type_code,
                'productName' => $product->product_name,
                'company' => 'godigit',
                'method' => 'Check Policy Status',
                'authorization' => $access_token,
                'transaction_type' => 'proposal',
                'integrationId' => $integrationId
            ]
        );
      
        $data = $get_response['response'];
        return $this->checkPaymentStatusServiceResponse($data);
 
        // return $return_data;
    }

    function checkPaymentStatusServiceResponse($data)
    {
        if ($data) {
            $policy_status_data = json_decode($data,true);

            if (isset($policy_status_data['policyStatus']) && (in_array($policy_status_data['policyStatus'], ['EFFECTIVE', 'COMPLETE', 'UW_REFFERED']) ||  ($policy_status_data['policyStatus'] == 'INCOMPLETE' && in_array($policy_status_data['kycStatus']['paymentStatus'] ?? '', ['PAID', 'DONE'])))) {
                $return_data = [
                    'status' => true,
                    'msg' => $policy_status_data['policyStatus'],
                ];
            } elseif (isset($policy_status_data['policyStatus']) && ($policy_status_data['policyStatus'] == 'INCOMPLETE' || $policy_status_data['policyStatus'] == 'DECLINED')) {
                $return_data = [
                    'status' => false,
                    'msg' => $policy_status_data['policyStatus'],
                ];
            } else {
                $return_data = [
                    'status' => false,
                    'msg' => 'Error in service.'
                ];
            }
        } else {

            $return_data = [
                'status' => false,
                'msg' => 'Error in service',
            ];
        }

        return $return_data;
    }
}

function getToken($enquiryId, $productData, $transaction_type, $type)
{
    
    return GoDigitHelper::getToken($enquiryId, $productData, $transaction_type, $type);
}

if (!function_exists('checkGodigitPaymentStatus')) {
    function checkGodigitPaymentStatus($user_product_journey_id, $policyid, $request, $proposal_no)
    {
        $obj = new GoDigitHelper();
        return $obj->checkGodigitPaymentStatus($user_product_journey_id, $policyid, $request, $proposal_no);
    }
}

if (!function_exists('godigitProposalKycMessage')) {
    function godigitProposalKycMessage($proposal, $message)
    {
        if (!empty(config('GODIGIT_PROPOSAL_KYC_ERROR_MESSAGE'))) {
            $kycMessageArray = json_decode(config('GODIGIT_PROPOSAL_KYC_ERROR_MESSAGE'), true);
            $message = ($proposal->ckyc_type == 'ckyc_number' ? $kycMessageArray['ckyc'] :
            $kycMessageArray['other']);
            $message = str_replace('##MODE', getModeValue($proposal->ckyc_type), $message);
            $message = str_replace('##VALUE', $proposal->ckyc_type_value, $message);
            $message = str_replace('##DOB_DOI', ($proposal->owner_type == 'I' ? 'Date of Birth' : 'Date of Incorporation'), $message);
            $message = str_replace('##DOB', $proposal->dob, $message);
        } else {
            $message = $message;
        }
        return $message;
    }
}

function getModeValue($mode) {
    if($mode == 'pan_number_with_dob') {
        return 'PAN Number';
    } else if($mode == 'aadhar_with_dob') {
        return 'Aadhaar Number';
    } else if($mode == 'ckyc_number') {
        return 'CKYC Number';
    } else {
        return ucwords(str_replace('_', ' ', $mode));
    }
}

if (!function_exists('GetKycStatusGoDIgitOneapi')) 
{

    function GetKycStatusGoDIgitOneapi($user_product_journey_id,$proposal_no, $product_name,$user_proposal_id,$userProductJourneyId,$product)
        {
            $proposal = UserProposal::where('user_product_journey_id',$user_product_journey_id)
            ->first();
            if ($product->product_sub_type_code == 'CAR') {
                include_once app_path() . '/Helpers/CarWebServiceHelper.php';
            } else if ($product->product_sub_type_code == 'BIKE') {
                include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
            } else {
                include_once app_path() . '/Helpers/CvWebServiceHelper.php';
            }
         
            $KycVerfiyApi = config('IC.GODIGIT.V2.KYC_END_POINT_URL');
            $integrationId = '';
            if ($product->product_sub_type_code == 'CAR') {
                $integrationId = config('IC.GODIGIT.V2.CAR.KYC_INTEGRATION_ID');
            } else if ($product->product_sub_type_code == 'BIKE') {
                $integrationId = config('IC.GODIGIT.V2.BIKE.KYC_INTEGRATION_ID');
            } else {
                $integrationId = config('IC.GODIGIT.V2.CV.KYC_INTEGRATION_ID');
            }

            $webUserId = config('IC.GODIGIT.V2.USERNAME');
            $password  = config('IC.GODIGIT.V2.PASSWORD');

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $user_product_journey_id,
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
                    $webUserId = $credentials['data']['web_user_id'];
                    $password = $credentials['data']['password'];
                }
            }

            $mode = $proposal->ckyc_type ?? 'NA';
            $mode_type =  strtoupper(str_replace("_"," ",$mode));
            $start_time = microtime(true) * 1000;;

            $access_token_resp = getToken($user_product_journey_id, $product, 'proposal', $proposal->business_type);
            $access_token = ($access_token_resp['token']);
    
            $Kyc_status = [
                "motorKYCstatussearchApi" => [
                    "queryParam" => [
                        'policyNumber' => $proposal_no,
                    ],
                ]
            ];
            if(config('IC.GODIGIT.V2.CAR.ENVIRONMENT') == 'UAT'){
                $Kyc_status = $Kyc_status['motorKYCstatussearchApi'];
            }
            $KycVerfiyApiResponse = getWsData($KycVerfiyApi,$Kyc_status,'godigit',
            [
                'enquiryId' => $user_product_journey_id,
                'requestMethod' =>'post',
                'section' => $product->product_sub_type_code,
                'productName'  => $product_name,
                'company'  => 'godigit',
                'method'   => 'Kyc Status',
                'transaction_type' => 'proposal',
                'authorization' => $access_token,
                'integrationId' => $integrationId
            ]);
            $end_time = microtime(true)* 1000;
            $response_time = $end_time - $start_time . ' ms';
            $KycVerfiyApiResponseData = $KycVerfiyApiResponse['response'];
            $request = ["URL" => $KycVerfiyApi];
            $reqHeaders =[
                'Content-type'  => 'application/json',
                'Authorization: Basic '.base64_encode("$webUserId:$password"),
                'Accept: application/json'
            ];
            if (!empty($KycVerfiyApiResponseData)) 
            {
                $KycVerfiyApiResponseDecoded = json_decode($KycVerfiyApiResponseData);
                CkycCommonController::GodigitSaveCkyclog($user_product_journey_id,$KycVerfiyApi,$request,$KycVerfiyApiResponseDecoded,$reqHeaders,$end_time,$start_time);
               
                if (isset($KycVerfiyApiResponseDecoded->kycVerificationStatus) && in_array($KycVerfiyApiResponseDecoded->kycVerificationStatus, ['DONE','SKIP'])) 
                {
                    CkycLogsRequestResponse::create([
                        'enquiry_id' => $user_product_journey_id,
                        'company_alias' => 'godigit',
                        'mode' => $mode_type,
                        'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                        'response' => json_encode($KycVerfiyApiResponseDecoded, JSON_UNESCAPED_SLASHES),
                        'headers' => json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
                        'endpoint_url' => $KycVerfiyApi,
                        'status' => 'Success',
                        'failure_message' => null,
                        'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                        'start_time' => date('Y-m-d H:i:s', $start_time / 1000),
                        'end_time' => date('Y-m-d H:i:s', $end_time / 1000),
                        'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2)
                    ]);

                    $updateProposal = UserProposal::where('user_proposal_id' , $user_proposal_id)
                    ->first();

                    if (!empty($updateProposal)) {
                        $updateProposal->update([
                            'ckyc_reference_id' => $KycVerfiyApiResponseDecoded->referenceId ?? ''
                        ]);
                    }
                    
                    if(\Illuminate\Support\Facades\Storage::exists('ckyc_photos/'.$userProductJourneyId)) 
                    {
                        \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/'.$userProductJourneyId);
                    }

                    return [
                        'status' => true,
                        'message' => $KycVerfiyApiResponseDecoded->link,
                        'response' => $KycVerfiyApiResponseDecoded ?? ''
                    ];

                }else if (isset($KycVerfiyApiResponseDecoded->kycVerificationStatus) && (in_array($KycVerfiyApiResponseDecoded->kycVerificationStatus, ['FAILED','NOT_DONE','NA'] ) )) 
                {

                    CkycLogsRequestResponse::create([
                        'enquiry_id' => $user_product_journey_id,
                        'company_alias' => 'godigit',
                        'mode' => $mode_type,
                        'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                        'response' => json_encode($KycVerfiyApiResponseDecoded, JSON_UNESCAPED_SLASHES),
                        'headers' => json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
                        'endpoint_url' => $KycVerfiyApi,
                        'status' =>'not_a_failure',
                        'failure_message' => null,
                        'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                        'start_time' => date('Y-m-d H:i:s', $start_time / 1000),
                        'end_time' => date('Y-m-d H:i:s', $end_time / 1000),
                        'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2)
                    ]);

                    if(!empty(($KycVerfiyApiResponseDecoded->link)))
                    {
                        // return redirect($KycVerfiyApiResponseDecoded->link);\
                        return [
                            'status' => false,
                            'message' => $KycVerfiyApiResponseDecoded->link ?? '',
                            'response' => $KycVerfiyApiResponseDecoded ?? ''
                        ];
                    }else
                    {
                        //removing code for creating loop 
                        // $unique_proposal_id = UserProposal::select('unique_proposal_id')
                        //               ->where('user_proposal_id',$user_proposal_id)
                        //               ->first();
                        // if(!empty($unique_proposal_id))
                        // {
                        //     return GetKycStatusGoDIgitOneapi($user_product_journey_id,$unique_proposal_id->unique_proposal_id, $product_name,$user_proposal_id,$userProductJourneyId,$product);
                        // }

                        return [
                            'status' => false,
                            'message' => $KycVerfiyApiResponseDecoded->link ?? '',
                            'response' => $KycVerfiyApiResponseDecoded ?? ''
                        ];
                    }
                    
                }else
                {
                    return [
                        'status' => false,
                        'message' => $KycVerfiyApiResponseDecoded->link ?? '',
                        'response' => $KycVerfiyApiResponseDecoded ?? ''
                    ];

                }
            }else
            {
                CkycLogsRequestResponse::create([
                    'enquiry_id' => $user_product_journey_id,
                    'company_alias' => 'godigit',
                    'mode' => $mode_type,
                    'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                    'response' => json_encode($KycVerfiyApiResponseData, JSON_UNESCAPED_SLASHES),
                    'headers' => json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
                    'endpoint_url' => $KycVerfiyApi,
                    'status' =>'Failed',
                    'failure_message' => "No Response from service",
                    'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                    'start_time' => date('Y-m-d H:i:s', $start_time / 1000),
                    'end_time' => date('Y-m-d H:i:s', $end_time / 1000),
                    'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2)
                ]);

                return [
                    'status' => false,
                    'message' => 'No response from KYC STATUS API.'
                ];

            }
        }
}

