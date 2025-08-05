<?php
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    if(config('ICICI_LOMBARD_RENEWAL_IDV_SERVICE_BIKE') == 'Y')
    {
        include_once app_path() . '/Quotes/Renewal/Bike/icici_lombard_idv.php';
        return getRenewalQuoteIdv($enquiryId, $requestData, $productData);
    }
    // vehicle age calculation
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $bike_age = ceil($age / 12);
    
    if (($bike_age > 5) && ($productData->zero_dep == '0'))
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request'=> [
                'bike_age'      =>  $bike_age,
                'product_Data'  =>  $productData->zero_dep
            ]
        ];
    }
    include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
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
    
    //print_r($mmv);
   
    $mmv_data = [
        'manf_name'             => $mmv->manufacturer_name,
        'model_name'            => $mmv->vehiclemodel,
        'version_name'          => '',
        'seating_capacity'      => $mmv->seatingcapacity,
        'carrying_capacity'     => $mmv->carryingcapacity,
        'cubic_capacity'        => $mmv->cubiccapacity,
        'fuel_type'             => $mmv->fueltype,
        'gross_vehicle_weight'  => '',
        'vehicle_type'          => 'BIKE',
        'version_id'            => $mmv->ic_version_code,
    ];
    
    $additionData = [
        'requestMethod'     => 'post',
        'type'              => 'tokenGeneration',
        'section'           => 'BIKE',
        'productName'       => $productData->product_name,
        'enquiryId'         => $enquiryId,
        'transaction_type'  => 'quote'
    ];

    $tokenParam = [
        'grant_type'    => 'password',
        'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
        'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
        'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
        'scope'         => 'esbmotor',
    ];
    
//    $tokenParam = [
//        'grant_type'    => 'password',
//        'username'      => 'renewBuy',
//        'password'      => 'r3n3w&u4',
//        'client_id'     => 'ro.renewBuy',
//        'client_secret' => 'ro.r3n3w&u4',
//        'scope'         => 'esbmotor',
//    ];


    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
    $token = $get_response['response'];
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
                'message' => $token['error'] ?? "Issue in Token Generation service"
            ];
        }
        
        $premium_type_array = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->select('premium_type_code','premium_type')
            ->first();
        $premium_type = $premium_type_array->premium_type_code;
        $policy_type = $premium_type_array->premium_type;

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
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
            break;
        
            case "own_damage":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
            break;
        
            case "third_party":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
            break;
        }
        
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
//            "PolicyNumber"              => "3005/51706157/00/000",
//            "CorrelationId"             => $corelationId,
//            "EngineNumberLast5Char"     => "23456",
//            "ChassisNumberLast5Char"    => "",
//            "DealID"                    => "DEAL-3005-0206146",
//             "TenureList"               => [1]
//        ];
        $DealID = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $deal_id : config('DEAL_ID_ICICI_LOMBARD_TESTING');
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $fetch_policy_data = [	
            "PolicyNumber"              => $proposal->previous_policy_number,
            "CorrelationId"             => $corelationId,
            "EngineNumberLast5Char"     => substr($proposal->engine_number,-5),
            "ChassisNumberLast5Char"    => substr($proposal->chassis_number,-5),
            "DealID"                    => $DealID,
            "TenureList"               => $premium_type == 'own_damage' ? [6] : [1]
        ];
        
        $additionPremData = [
            'requestMethod'     => 'post',
            'type'              => 'Fetch Policy Details',
            'section'           => 'BIKE',
            'productName'       => $productData->product_name,
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote',
            'token'             => $access_token
        ];
        //$url  = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/TwoWheeler/Fetch/';
        
        $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_BIKE_FETCH_POLICY_DATA');
        
        $get_response = getWsData($url, $fetch_policy_data, 'icici_lombard', $additionPremData);
        $data = $get_response['response'];
        
//        $data = '{
//    "nomineeDetails": [
//        {
//            "nomineeDetails": "",
//            "nomineeName": "",
//            "nomineeAge": "",
//            "nomineeRelationship": ""
//        },
//        {
//            "nomineeDetails": "",
//            "nomineeName": "",
//            "nomineeAge": "",
//            "nomineeRelationship": ""
//        }
//    ],
//    "previousPolicyDetails": {
//        "previousPolicyType": "Comprehensive Package",
//        "previousPolicyStartDate": "2017-10-07T00:00:00",
//        "previousPolicyEndDate": "2019-09-07T00:00:00",
//        "previousPolicyNumber": "3005/51706157/00/000",
//        "policyPremium": 2159.0,
//        "policyYear": "2017"
//    },
//    "vehicleDetails": {
//        "engineNumber": "123456",
//        "chassisNumber": "123456",
//        "registrationNumber": "MH01B1234",
//        "vehicleModelCode": "1704",
//        "vehicleMakeCode": "31"
//    },
//    "proposalDetails": [
//        {
//            "tpStartDate": null,
//            "tpEndDate": null,
//            "tpPolicyNo": "",
//            "tpInsurerName": "",
//            "riskDetails": {
//                "basicOD": 234.0,
//                "geographicalExtensionOD": 0.0,
//                "electricalAccessories": 0.0,
//                "nonElectricalAccessories": 0.0,
//                "consumables": 0.0,
//                "zeroDepreciation": 0.0,
//                "returnToInvoice": 0.0,
//                "roadSideAssistance": 0.0,
//                "engineProtect": 0.0,
//                "keyProtect": 0.0,
//                "lossOfPersonalBelongings": 0.0,
//                "voluntaryDiscount": 0.0,
//                "antiTheftDiscount": 0.0,
//                "automobileAssociationDiscount": 0.0,
//                "handicappedDiscount": 0.0,
//                "emeCover": 0.0,
//                "basicTP": 1193.0,
//                "paidDriver": 0.0,
//                "employeesOfInsured": 0.0,
//                "geographicalExtensionTP": 0.0,
//                "paCoverForUnNamedPassenger": 140.0,
//                "paCoverForOwnerDriver": 0.0,
//                "tppD_Discount": 0.0,
//                "bonusDiscount": 59.0,
//                "paCoverWaiver": false,
//                "ncbPercentage": 25.0
//            },
//            "totalOwnDamagePremium": 175.0,
//            "packagePremium": 1508.0,
//            "roadSideAssistanceService": null,
//            "deviationMessage": "",
//            "isQuoteDeviation": false,
//            "breakingFlag": false,
//            "proposalStatus": "",
//            "isApprovalRequired": false,
//            "correlationId": "3e9adff8-1d02-4842-933d-1de3ac0b9691",
//            "generalInformation": {
//                "vehicleModel": "AVENGER",
//                "manufacturerName": "BAJAJ",
//                "manufacturingYear": "2016",
//                "vehicleDescription": "",
//                "rtoLocation": "MAHARASHTRA-MUMBAI",
//                "showRoomPrice": 72380.0,
//                "chassisPrice": null,
//                "bodyPrice": null,
//                "seatingCapacity": null,
//                "carryingCapacity": null,
//                "policyInceptionDate": "10/07/2019",
//                "policyEndDate": "09/07/2020",
//                "transactionType": "Renewal Business",
//                "cubicCapacity": "180",
//                "proposalDate": "10/07/2017",
//                "referenceProposalDate": null,
//                "depriciatedIDV": 43428.0,
//                "tenure": "1",
//                "tpTenure": "0",
//                "registrationDate": "10/07/2016",
//                "percentageOfDepriciation": "40.00",
//                "proposalNumber": null,
//                "referenceProposalNo": "",
//                "customerId": "101470294554",
//                "customerType": "Individual",
//                "rtoLocationCode": "192"
//            },
//            "totalLiabilityPremium": 1333.0,
//            "specialDiscount": 0.0,
//            "totalTax": 271.0,
//            "finalPremium": 1779.0,
//            "message": "",
//            "status": "Success"
//        }
//    ],
//    "message": "",
//    "status": true,
//    "statusMessage": "Success",
//    "correlationId": "3e9adff8-1d02-4842-933d-1de3ac0b9691"
//}';
       
        
        $reponse            = json_decode($data,true);
        if(isset($reponse['status']) && $reponse['status'] == true && 
        $reponse['proposalDetails'][0]['isQuoteDeviation'] == false && 
        $reponse['proposalDetails'][0]['breakingFlag'] == false &&
        $reponse['proposalDetails'][0]['isApprovalRequired'] == false
        )
        {
            $proposalDetails    = $reponse['proposalDetails'][0];        
            $riskDetails        = $proposalDetails['riskDetails'];
            $generalInformation = $proposalDetails['generalInformation'];
            $policy_start_date = str_replace('/','-',$generalInformation['policyInceptionDate']);
            $policy_end_date = str_replace('/','-',$generalInformation['policyEndDate']);
            
            $zeroDepreciation           = $riskDetails['zeroDepreciation'];
            $engineProtect              = $riskDetails['engineProtect'];
            $keyProtect                 = $riskDetails['keyProtect'];
            //$tyreProtect                = $riskDetails['tyreProtect'];
            $returnToInvoice            = $riskDetails['returnToInvoice'];
            $lossOfPersonalBelongings   = $riskDetails['lossOfPersonalBelongings'];
            $roadSideAssistance         = $riskDetails['roadSideAssistance'];
            $consumables                = $riskDetails['consumables'];
            
            $addons_list = [
                'zero_depreciation'     => round($zeroDepreciation),
                'engine_protector'      => round($engineProtect),
                'ncb_protection'        => 0,
                'key_replace'           => round($keyProtect),
                //'tyre_secure'           => round($tyreProtect),
                'return_to_invoice'     => round($returnToInvoice),
                'lopb'                  => round($lossOfPersonalBelongings),
                'consumables'           => round($consumables),
                'road_side_assistance'  => round($roadSideAssistance)
            ];
            if($productData->zero_dep == '1')
            {
               $addons_list['zero_depreciation'] = 0; 
            }
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

            $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
            $paCoverForUnNamedPassengerAmount = 0;
            if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
                $additional_covers = $selected_addons->additional_covers;
                foreach ($additional_covers as $value) {
                   if($value['name'] == 'Unnamed Passenger PA Cover')
                   {
                    $paCoverForUnNamedPassengerAmount = $riskDetails['paCoverForUnNamedPassenger'];

                   }
                }
            }
            
            $breakinLoadingAmount           = $riskDetails['breakinLoadingAmount'] ?? 0;
            $biFuelKitOD                    = $riskDetails['biFuelKitOD'] ?? 0;
            $biFuelKitTP                    = $riskDetails['biFuelKitTP']?? 0;
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
            $paCoverForUnNamedPassenger     = $paCoverForUnNamedPassengerAmount;
            $paCoverForOwnerDriver          = $riskDetails['paCoverForOwnerDriver'];
            $tppD_Discount                  = $riskDetails['tppD_Discount'];
            $bonusDiscount                  = $riskDetails['bonusDiscount'];
            
            $idv                            = $generalInformation['depriciatedIDV'];
            
            $totalOwnDamagePremium          = $proposalDetails['totalOwnDamagePremium'];            
            $totalLiabilityPremium          = $proposalDetails['totalLiabilityPremium'];
            $netPremium                     = $proposalDetails['packagePremium'];
            $totalTax                       = $proposalDetails['totalTax'];
            $finalPremium                   = $proposalDetails['finalPremium'];
            $final_od_premium = $basicOD + $electricalAccessories + $nonElectricalAccessories + $geographicalExtensionOD;   
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
                    'fuel_type'                 => $mmv->fueltype,
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
                    'seating_capacity'          => $mmv->seatingcapacity,
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
                'message' => $reponse['message'] ?? 'Service Issue'
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