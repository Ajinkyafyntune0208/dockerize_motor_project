<?php
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    if(config('ICICI_LOMBARD_RENEWAL_IDV_SERVICE_CAR') == 'Y')
    {
        include_once app_path() . '/Quotes/Renewal/Car/icici_lombard_idv.php';
        return getRenewalQuoteIdv($enquiryId, $requestData, $productData);
    }
    include_once app_path() . '/Helpers/CarWebServiceHelper.php';
    $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');
    if ($mmv['status'] == 1) 
    {
        $mmv = $mmv['data'];
    } 
    else 
    {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];
    } elseif ($mmv->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];
    }
    
    $mmv_data = [
        'manf_name'             => $mmv->manufacturer_name,
        'model_name'            => $mmv->model_name ?? '',
        'version_name'          => '',
        'seating_capacity'      => ((int) $mmv->seating_capacity) - 1,
        'carrying_capacity'     => $mmv->seating_capacity,
        'cubic_capacity'        => $mmv->cubic_capacity,
        'fuel_type'             => $mmv->fuel_type,
        'gross_vehicle_weight'  => '',
        'vehicle_type'          => 'CAR',
        'version_id'            => $mmv->ic_version_code,
    ];
    
    $additionData = [
        'requestMethod'     => 'post',
        'type'              => 'tokenGeneration',
        'section'           => 'CAR',
        'productName'       => $productData->product_name,
        'enquiryId'         => $enquiryId,
        'transaction_type'  => 'quote'
    ];

    $tokenParam = [
        'grant_type'    => 'password',
        'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
        'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
        'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
        'scope'         => 'esbmotor',
    ];

    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $car_age = ceil($age / 12);

    /* if (($car_age > 5) && ($productData->zero_dep == '0'))
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request' => [
                'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                'car_age' => $car_age
            ]
        ];
    } */
    
    //renewbuy
//    $tokenParam = [
//        'grant_type'    => 'password',
//        'username'      => 'renewBuy',
//        'password'      => 'r3n3w&u4',
//        'client_id'     => 'ro.renewBuy',
//        'client_secret' => 'ro.r3n3w&u4',
//        'scope'         => 'esbmotor',
//    ];
    //bajaj
//    $tokenParam = [
//        'grant_type'    => 'password',
//        'username'      => 'Bajajcapital',
//        'password'      => 'B@j@jc3ap6it@5l',
//        'client_id'     => 'ro.Bajajcapital',
//        'client_secret' => 'ro.B@j@jc3ap6it@5l',
//        'scope'         => 'esbmotor',
//    ];

    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
    $token = $get_response['response']; 
//    print_r($token);
//    die;
//$token = '{"access_token":"eyJhbGciOiJSUzI1NiIsImtpZCI6IjZCN0FDQzUyMDMwNUJGREI0RjcyNTJEQUVCMjE3N0NDMDkxRkFBRTEiLCJ0eXAiOiJKV1QiLCJ4NXQiOiJhM3JNVWdNRnY5dFBjbExhNnlGM3pBa2ZxdUUifQ.eyJuYmYiOjE2NTA1MzQ4NjMsImV4cCI6MTY1MDUzODQ2MywiaXNzIjoiaHR0cHM6Ly9pbGVzYnNhbml0eS5pbnN1cmFuY2VhcnRpY2xlei5jb20vY2VyYmVydXMiLCJhdWQiOlsiaHR0cHM6Ly9pbGVzYnNhbml0eS5pbnN1cmFuY2VhcnRpY2xlei5jb20vY2VyYmVydXMvcmVzb3VyY2VzIiwiZXNibW90b3IiXSwiY2xpZW50X2lkIjoicm8ucmVuZXdCdXkiLCJzdWIiOiIwYzBmZTc2OC0zOTdkLTQ5OWQtYTdjZC0zOTZiYTk3ZDE3NDEiLCJhdXRoX3RpbWUiOjE2NTA1MzQ4NjMsImlkcCI6ImxvY2FsIiwic2NvcGUiOlsiZXNibW90b3IiXSwiYW1yIjpbImN1c3RvbSJdfQ.ojXDOW0b6Dj0Nnfnn-5ZrR_zYf6W-dsWg9VsUo0ClUIXZ-4fecrhn-zWk-GpxnIrHQcaP32hV0-3898qA-PfP4AOmCJzmUeop8VGO6cB7UCiexx5MqBTowH8EVncQcQooVkSMNqlwbUCCBD64pg2G0FRgKww4QisaHwq3JhiKaPs4sbK86mtqLq2-waxZsqfysUNF2iy87PbmG-Ue0KQgODqdFVcl6s2KvvlXTuW-bYImGmxDuN-o6dHKIlIU7jnUdzOnriQ7MTq3JqqyS8TPZU0gN4CPfActP43XZYEwVEspH0y6CMjiNpNXWfh_W3IztMd8LKXeuVyjfiIdC1LnA","expires_in":3600,"token_type":"Bearer"}';
    if (!empty($token))
    {
        $token = json_decode($token, true);

        if(isset($token['access_token']))
        {
            $access_token = $token['access_token'];
        }
        else
        {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => "Issue in Token Generation service"
            ];
        }
        
        $premium_type_array = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->select('premium_type_code','premium_type')
            ->first();
        $premium_type = $premium_type_array->premium_type_code;
        $policy_type = $premium_type_array->premium_type;
        //Comprehensive Package
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
            $policy_type = 'Comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
            $policy_type = 'Third Party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
            $policy_type = 'Own Damage';
        }
        
        switch($premium_type)
        {
            case "comprehensive":
                 $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
            break;
            case "own_damage":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');
            break;
            case "third_party":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
            break;
        }
        if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
        {
            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');
        }
        
        $DealID = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $deal_id : config('DEAL_ID_ICICI_LOMBARD_TESTING');
        
        $businessType = '';
        switch ($requestData->business_type)
        {
            case 'newbusiness':
                $businessType = 'New Business';
            break;
            case 'rollover':
                $businessType = 'Roll Over';
            break;

            case 'breakin':
                $businessType = 'Break- In';
            break;
        }
               
        $corelationId = getUUID($enquiryId);
//        $fetch_policy_data = [	
//            "PolicyNumber"              => "3001/51828628/00/000",
//            "CorrelationId"             => $corelationId,
//            "EngineNumberLast5Char"     => "65456",
//            "ChassisNumberLast5Char"    => "49645",
//            "DealID"                    => "DL-3001/913908"
//        ];
        
        
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();   
        $fetch_policy_data = [	
            "PolicyNumber"              => trim($proposal->previous_policy_number),
            "CorrelationId"             => $corelationId,
            "EngineNumberLast5Char"     => substr($proposal->engine_number,-5),
            "ChassisNumberLast5Char"    => substr($proposal->chassis_number,-5),
            "DealID"                    => $DealID
        ];
        
        
//        $fetch_policy_data = [	
//            "PolicyNumber"              => "3001/51832974/00/000",
//            "CorrelationId"             => $corelationId,
//            "EngineNumberLast5Char"     => "45456",
//            "ChassisNumberLast5Char"    => "",
//            "DealID"                    => "DL-3001/913908"
//        ];
//        $fetch_policy_data = [	
//            "PolicyNumber"              => "3001/51857559/00/000",
//            "CorrelationId"             => $corelationId,
//            "EngineNumberLast5Char"     => "36434",
//            "ChassisNumberLast5Char"    => "",
//            "DealID"                    => "DL-3001/913908"
//        ];
        
        $additionPremData = [
            'requestMethod'     => 'post',
            'type'              => 'Fetch Policy Details',
            'section'           => 'CAR',
            'productName'       => $productData->product_name,
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote',
            'token'             => $access_token
        ];
        
        //$url  = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/PrivateCar/Fetch';
        $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_MOTOR_FETCH_POLICY_DATA');
        $get_response = getWsData($url, $fetch_policy_data, 'icici_lombard', $additionPremData);
        $data = $get_response['response'];
       
//        $data = '{
//    "nomineeDetails": [
//        {
//            "nomineeDetails": "",
//            "nomineeName": "Teststs",
//            "nomineeAge": "33",
//            "nomineeRelationship": "FATHER"
//        },
//        {
//            "nomineeDetails": "",
//            "nomineeName": "",
//            "nomineeAge": "0",
//            "nomineeRelationship": ""
//        }
//    ],
//    "previousPolicyDetails": {
//        "previousPolicyType": "Comprehensive Package",
//        "previousPolicyStartDate": "2019-04-08T00:00:00",
//        "previousPolicyEndDate": "2022-04-07T00:00:00",
//        "previousPolicyNumber": "3001/51828628/00/000",
//        "policyPremium": 44704.0,
//        "policyYear": "2019"
//    },
//    "vehicleDetails": {
//        "engineNumber": "78956465456",
//        "chassisNumber": "464649645",
//        "registrationNumber": "MH05LP4587",
//        "vehicleModelCode": "16889",
//        "vehicleMakeCode": "7"
//    },
//    "proposalDetails": {
//        "tpStartDate": null,
//        "tpEndDate": null,
//        "tpPolicyNo": "",
//        "tpInsurerName": "",
//        "isEMIProtect": false,
//        "emiAmount": null,
//        "noOfEMI": null,
//        "timeExcessInDays": null,
//        "riskDetails": {
//            "breakinLoadingAmount": 0.0,
//            "garageCash": 0.0,
//            "biFuelKitOD": 0.0,
//            "biFuelKitTP": 0.0,
//            "tyreProtect": 0.0,
//            "fibreGlassFuelTank": 0.0,
//            "emiProtect": 0.0,
//            "basicOD": 4064.0,
//            "geographicalExtensionOD": 0.0,
//            "electricalAccessories": 0.0,
//            "nonElectricalAccessories": 0.0,
//            "consumables": 0.0,
//            "zeroDepreciation": 0.0,
//            "returnToInvoice": 0.0,
//            "roadSideAssistance": 499.0,
//            "engineProtect": 0.0,
//            "keyProtect": 0.0,
//            "lossOfPersonalBelongings": 0.0,
//            "voluntaryDiscount": 0.0,
//            "antiTheftDiscount": 0.0,
//            "automobileAssociationDiscount": 0.0,
//            "handicappedDiscount": 0.0,
//            "emeCover": 0.0,
//            "basicTP": 3360.0,
//            "paidDriver": 50.0,
//            "employeesOfInsured": 0.0,
//            "geographicalExtensionTP": 0.0,
//            "paCoverForUnNamedPassenger": 0.0,
//            "paCoverForOwnerDriver": 375.0,
//            "tppD_Discount": 0.0,
//            "bonusDiscount": 1626.0,
//            "paCoverWaiver": false,
//            "ncbPercentage": 40.0
//        },
//        "totalOwnDamagePremium": 2937.0,
//        "packagePremium": 6722.0,
//        "roadSideAssistanceService": "Breakdown support over phone,Accommodation Benefits,Taxi Benefits,Arrangement/ Supply of fuel",
//        "deviationMessage": "",
//        "isQuoteDeviation": false,
//        "breakingFlag": false,
//        "proposalStatus": "",
//        "isApprovalRequired": false,
//        "correlationId": "604d27fc-0e78-447c-b81b-3a727d329c4e",
//        "generalInformation": {
//            "vehicleModel": "BRIO E",
//            "manufacturerName": "HONDA",
//            "manufacturingYear": "2019",
//            "vehicleDescription": null,
//            "rtoLocation": "MAHARASHTRA-MUMBAI",
//            "showRoomPrice": 401668.0,
//            "chassisPrice": null,
//            "bodyPrice": null,
//            "seatingCapacity": null,
//            "carryingCapacity": null,
//            "policyInceptionDate": "08/04/2022",
//            "policyEndDate": "07/04/2023",
//            "transactionType": "Renewal Business",
//            "cubicCapacity": "1198",
//            "proposalDate": "08/04/2019",
//            "referenceProposalDate": "21/04/2022",
//            "depriciatedIDV": 241001.0,
//            "tenure": "1",
//            "tpTenure": "0",
//            "registrationDate": "07/04/2019",
//            "percentageOfDepriciation": "40.00",
//            "proposalNumber": null,
//            "referenceProposalNo": "1201464653",
//            "customerId": "101086649569",
//            "customerType": "Individual",
//            "rtoLocationCode": "8"
//        },
//        "totalLiabilityPremium": 3785.0,
//        "specialDiscount": 0.0,
//        "totalTax": 1210.0,
//        "finalPremium": 7932.0,
//        "message": null,
//        "status": "Success"
//    },
//    "message": "",
//    "status": true,
//    "statusMessage": "Success",
//    "correlationId": "604d27fc-0e78-447c-b81b-3a727d329c4e"
//}';
        //print_r($data);
        
        $reponse            = json_decode($data,true);
        
        if(isset($reponse['status']) && $reponse['status'] == true && 
        $reponse['proposalDetails']['isQuoteDeviation'] == false && 
        $reponse['proposalDetails']['breakingFlag'] == false &&
        $reponse['proposalDetails']['isApprovalRequired'] == false
        )
        {
            $proposalDetails    = $reponse['proposalDetails'];
            $previousPolicyDetails = $reponse['previousPolicyDetails'];
            $riskDetails        = $proposalDetails['riskDetails'];
            $generalInformation = $proposalDetails['generalInformation'];
            
            $policy_start_date = str_replace('/','-',$generalInformation['policyInceptionDate']);
            $policy_end_date = str_replace('/','-',$generalInformation['policyEndDate']);
            
            $policy_start_date_check = \Carbon\Carbon::parse($policy_start_date);
            $breakin_date = now()->subDay(1);
            if($breakin_date > $policy_start_date_check)
            {
                return [
                    'status'  => false,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'message' => 'Policy Start Date is '.$policy_start_date.' It Should be Current Or Future Date'
                ];
            }

            $policyType = null;

            if ($previousPolicyDetails['previousPolicyType'] ?? null) {
                if ($previousPolicyDetails['previousPolicyType'] == 'Comprehensive Package') {
                    $policyType = 'comprehensive';
                } elseif ($previousPolicyDetails['previousPolicyType'] == 'TP') {
                    $policyType = 'third_party';
                } elseif(in_array($previousPolicyDetails['previousPolicyType'], ['Bundled Package Policy', 'Standalone Own Damage'])) {
                    $policyType = 'own_damage';
                }
            }

            if (!empty($policyType) && $requestData->business_type != 'breakin' && $policyType != $premium_type) {
                return [
                    'status' => false,
                    'message' => 'Missmatched policy type',
                    'request' => [
                        'message' => 'Missmatched policy type',
                        'business_type' => $requestData->business_type,
                        'premium_type_code' => $premium_type,
                        'policyType' => $policyType
                    ]
                ];
            }
            
            $zeroDepreciation           = $riskDetails['zeroDepreciation'];
            $engineProtect              = $riskDetails['engineProtect'];
            $keyProtect                 = $riskDetails['keyProtect'];
            $tyreProtect                = $riskDetails['tyreProtect'];
            $returnToInvoice            = $riskDetails['returnToInvoice'];
            $lossOfPersonalBelongings   = $riskDetails['lossOfPersonalBelongings'];
            $roadSideAssistance         = $riskDetails['roadSideAssistance'];
            $consumables                = $riskDetails['consumables'];
            
            $addons_list = [
                'zero_depreciation'     => round($zeroDepreciation),
                'engine_protector'      => round($engineProtect),
                'ncb_protection'        => 0,
                'key_replace'           => round($keyProtect),
                'tyre_secure'           => round($tyreProtect),
                'return_to_invoice'     => round($returnToInvoice),
                'lopb'                  => round($lossOfPersonalBelongings),
                'consumables'           => round($consumables),
                'road_side_assistance'  => round($roadSideAssistance)
            ];
            //removed age validation #33490
            // if (($car_age > 5) && ($productData->zero_dep == '1'))
            // {
            //     $addons_list['zero_depreciation']=0;
            // }

            // if ($car_age > 4) {
            //     $addons_list['consumables'] = 0;
            // }

            // if ($car_age > 5)
            // {
            //     $addons_list['zero_depreciation']=0;
            //     $addons_list['engine_protector']=0;
            //     $addons_list['ncb_protection']=0;
            //     $addons_list['key_replace']=0;
            //     $addons_list['tyre_secure']=0;
            //     $addons_list['return_to_invoice']=0;
            //     $addons_list['lopb']=0;
            // }

            // if ($car_age > 15) {
            //     $addons_list['road_side_assistance'] = 0;
            // }

            $in_bult = [];
            $additional = [];
            foreach ($addons_list as $key => $value) 
            {
                if($value > 0)
                {
                  $in_bult[$key] =  $value;
                }
                else
                {
                    $additional[$key] =  $value;
                }
            }
            $addons_data = [
                'in_built'   => $in_bult,
                'additional' => $additional,
                'other'=>[]
            ];
            $applicable_addons = array_keys($in_bult);
            
            $breakinLoadingAmount           = $riskDetails['breakinLoadingAmount'];
            $biFuelKitOD                    = $riskDetails['biFuelKitOD'];
            $biFuelKitTP                    = $riskDetails['biFuelKitTP'];
            $basicOD                        = $riskDetails['basicOD'];
            $geographicalExtensionOD        = $riskDetails['geographicalExtensionOD'];
            $electricalAccessories          = $riskDetails['electricalAccessories'];
            $nonElectricalAccessories       = $riskDetails['nonElectricalAccessories'];
            
            $voluntaryDiscount              = $riskDetails['voluntaryDiscount'];
            $antiTheftDiscount              = $riskDetails['antiTheftDiscount'];
            $automobileAssociationDiscount  = $riskDetails['automobileAssociationDiscount'];
            $basicTP                        = $riskDetails['basicTP'];
            $paidDriver                     = $riskDetails['paidDriver'];
            $geographicalExtensionTP        = $riskDetails['geographicalExtensionTP'];
            $paCoverForUnNamedPassenger     = $riskDetails['paCoverForUnNamedPassenger'];
            $paCoverForOwnerDriver          = $riskDetails['paCoverForOwnerDriver'];
            $tppD_Discount                  = $riskDetails['tppD_Discount'];
            $bonusDiscount                  = $riskDetails['bonusDiscount'];
            
            $idv                            = $generalInformation['depriciatedIDV'];
            
            $totalOwnDamagePremium          = $proposalDetails['totalOwnDamagePremium'];            
            $totalLiabilityPremium          = $proposalDetails['totalLiabilityPremium'];
            $netPremium                     = $proposalDetails['packagePremium'];
            $totalTax                       = $proposalDetails['totalTax'];
            $finalPremium                   = $proposalDetails['finalPremium'];
            $final_od_premium = $basicOD + $electricalAccessories + $nonElectricalAccessories + $geographicalExtensionOD + $biFuelKitOD;   
            $final_total_discount = $voluntaryDiscount + $antiTheftDiscount + $automobileAssociationDiscount + $tppD_Discount + $bonusDiscount;
            $final_tp_premium = $basicTP + $biFuelKitTP + $paidDriver + $geographicalExtensionTP + $paCoverForUnNamedPassenger;
            $motor_manf_date = '01-'.$requestData->manufacture_year;
            
            
            $data = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'Data' => [
                    'isRenewal'                 => 'Y',
                    'quote_id'                  =>  $corelationId,
                    'idv'                       =>  round($idv),
                    'min_idv'                   =>  round($idv),
                    'max_idv'                   =>  round($idv),
                    'default_idv'               =>  round($idv),
                    'modified_idv'              =>  round($idv),
                    'original_idv'              =>  round($idv),
                    'vehicle_idv'               =>  round($idv),
                    'showroom_price'            =>  round($idv),
                    'pp_enddate'                =>  $requestData->previous_policy_expiry_date,                
                    'policy_type'               => $policy_type,
                    'cover_type'                => '1YC',
                    'vehicle_registration_no'   => $requestData->rto_code,
                    'voluntary_excess'          => (int) $voluntaryDiscount,
                    'version_id'                => $mmv->ic_version_code,                
                    'fuel_type'                 => $mmv->fuel_type,
                    'ncb_discount'              => (int) $requestData->applicable_ncb,
                    'company_name'              => $productData->company_name,
                    'company_logo'              => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name'              => $productData->product_sub_type_name,
                    'mmv_detail'                => $mmv_data,
                    'vehicle_register_date'     => $requestData->vehicle_register_date,
                    'master_policy_id'          => [
                        'policy_id'             => $productData->policy_id,
                        //'policy_no'           => $productData->policy_no,
                        //'policy_start_date'   => date('d-m-Y', strtotime($policy_start_date)),
                        //'policy_end_date'     => date('d-m-Y', strtotime($policy_end_date)),
                       // 'sum_insured'         => $productData->sum_insured,
                        //'corp_client_id'      => $productData->corp_client_id,
                        'product_sub_type_id'   => $productData->product_sub_type_id,
                        'insurance_company_id'  => $productData->company_id,
                        //'status'              => $productData->status,
                        //'corp_name'           => "Ola Cab",
                        'company_name'          => $productData->company_name,
                        'logo'                  => url(config('constants.motorConstant.logos').$productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount'         => $productData->default_discount,
                        //'predefine_series'    => "",
                        'is_premium_online'     => $productData->is_premium_online,
                        'is_proposal_online'    => $productData->is_proposal_online,
                        'is_payment_online'     => $productData->is_payment_online
                    ],
                    'motor_manf_date'           => $motor_manf_date,
                    'basic_premium'             => (int)$basicOD,
                    'deduction_of_ncb'          => (int)$bonusDiscount,
                    'tppd_premium_amount'       => (int)$basicTP,
                    'tppd_discount'             => (int)$tppD_Discount,
                    'total_loading_amount'      => $breakinLoadingAmount,
                    'motor_electric_accessories_value' => (int)$electricalAccessories,
                    'motor_non_electric_accessories_value' => (int)$nonElectricalAccessories,
                    'motor_lpg_cng_kit_value'   => (int)$biFuelKitOD,
                    'cover_unnamed_passenger_value' => (int)$paCoverForUnNamedPassenger,
                    'seating_capacity'          => $mmv->seating_capacity,
                    'default_paid_driver'       => (int)$paidDriver,
                    'motor_additional_paid_driver' => 0,
                    'compulsory_pa_own_driver'  => (int)$paCoverForOwnerDriver,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage'          => (int)$totalOwnDamagePremium,
                    'cng_lpg_tp'                => (int)$biFuelKitTP,
                    'total_liability_premium'   => (int)$totalLiabilityPremium,
                    'net_premium'               => (int)$netPremium,
                    'service_tax_amount'        => (int)$totalTax,
                    'service_tax'               => 18,
                    'total_discount_od'         => 0,
                    'add_on_premium_total'      => 0,
                    'addon_premium'             => 0,
                    'vehicle_lpg_cng_kit_value' => (int)$requestData->bifuel_kit_value,
                    'premium_amount'            => (int)$finalPremium,
                    'antitheft_discount'        => (int)$antiTheftDiscount,
                    'final_od_premium'          => (int)$final_od_premium,
                    'final_tp_premium'          => (int)$final_tp_premium,
                    'final_total_discount'      => round($final_total_discount),
                    'final_net_premium'         => (int)$netPremium,
                    'final_gst_amount'          => (int)$totalTax,
                    'final_payable_amount'      => (int)$finalPremium,
                    'product_sub_type_id'       => $productData->product_sub_type_id,
                    'user_product_journey_id'   => $requestData->user_product_journey_id,
                    'business_type'             => $businessType,
                    'policyStartDate'           => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate'             => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of'                     => $productData->company_id,
                    'vehicle_in_90_days'        => NULL,
                    'vehicle_discount_detail'   => [
                        'discount_id'           => NULL,
                        'discount_rate'         => NULL
                    ],
                    'is_premium_online'         => $productData->is_premium_online,
                    'is_proposal_online'        => $productData->is_proposal_online,
                    'is_payment_online'         => $productData->is_payment_online,
                    'policy_id'                 => $productData->policy_id,
                    'insurane_company_id'       => $productData->company_id,
                    'add_ons_data'              => $addons_data,
                    'applicable_addons'         => $applicable_addons
                ]
            ];
            return camelCase($data); 
        }
        else
        {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => $reponse['message'] ?? 'Validation Issue'
            ];            
        }
        
    }
    else
    {
        return [
            'status' => false,
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'message' => "Issue in Token Generation service"
        ];
    }
}