<?php

use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

include_once app_path() . '/Quotes/Car/icici_lombard_plan.php';
include_once app_path() . '/Quotes/Car/icici_lombard_plan_separate.php';
include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if(env('APP_ENV') == 'local' && config('ICICI_LOMBARD_CAR_PLAN_TYPE_ENABLE') == 'Y' && !isset($requestData->addons))
    {
      return getQuotePlan($enquiryId, $requestData, $productData);
    }else if(isset($requestData->addons))
    {
        return getQuotePlanSeparate($enquiryId, $requestData, $productData);
    }
    try {
        $isInspectionApplicable = 'N';
    $premium_type_array = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->select('premium_type_code','premium_type')
        ->first();
    $premium_type = $premium_type_array->premium_type_code;
    $policy_type = $premium_type_array->premium_type;

    if ($requestData->ownership_changed  == 'Y' && !in_array($premium_type,['third_party','third_party_breakin'])) {
        $isInspectionApplicable = 'Y';
    }

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

    $mmv = get_mmv_details($productData,$requestData->version_id,'icici_lombard');
    $is_breakin         = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);  



    if($mmv['status'] == 1)
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
                'mmv' => $mmv,
            ]
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }

    $mmv_data->version_name = $mmv_data->fyntune_version['version_name'];
    /*if ($requestData->is_claim == 'Y')
    {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle not allowed if claim made in last policy',
            'request' => [
                'is_claim' => $requestData->is_claim,
                'message' => 'Vehicle not allowed if claim made in last policy',
            ]
        ];
    }*/

    // vehicle age calculation

    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $car_age = ceil($age / 12);
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    
    // if (($interval->y >= 15) && ($tp_check == 'true')) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
    //     ];
    // }

    // if($mmv_data->manufacturer_name == 'HYNDAI' && strpos($mmv_data->model_name,'I20') && ($car_age > 3) && ($productData->zero_dep == 0))
    // {
    //         return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Zero Dep is not allowed for Car Age Greater than 3 Years for Given Variant',
    //         'request' => [
    //             'message' => 'Zero Dep is not allowed for Car Age Greater than 3 Years for Given Variant',
    //             'car_age' => $car_age,
    //             'manufacturer_name' => $mmv_data->manufacturer_name,
    //             'model_name' => $mmv_data->model_name,
    //         ]
    //     ];
    // }
    // if(trim($mmv_data->car_segment) == 'Premium Cars C'  && ($car_age > 3) && ($productData->zero_dep == 0))
    // {
    //         return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Zero Dep is not allowed for Car Age Greater than 3 Years for Given Variant',
    //         'request' => [
    //             'message' => 'Zero Dep is not allowed for Car Age Greater than 3 Years for Given Variant',
    //             'car_age' => $car_age,
    //             'car_segment' => $mmv_data->car_segment,
    //         ]
    //     ];
    // }

    // if (($car_age > 8) && ($productData->zero_dep == '0'))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Zero dep is not allowed for vehicle age greater than 8 years',
    //         'request' => [
    //             'message' => 'Zero dep is not allowed for vehicle age greater than 8 years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }


    //    if($premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
    //    {
    //        return [
    //            'premium_amount' => 0,
    //            'status' => false,
    //            'message' => 'Break-In Quotes Not Allowed'
    //        ];
    //    }


        // check for rto location



    $master_rto = MasterRto::where('rto_code', RtoCodeWithOrWithoutZero($requestData->rto_code, true))->first();
    if (empty($master_rto->icici_4w_location_code))
    {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $requestData->rto_code.' RTO Location Code Not Found',
            'request' => [
                'rto_code' => $requestData->rto_code
            ]
        ];
    }
    $state_name = MasterState::where('state_id', $master_rto->state_id)->first();
    $state_name = strtoupper($state_name->state_name);
    /* $state_id = $rto_cities->state_id;
    $state_name = MasterState::where('state_id', $state_id)->first();
    $state_name = strtoupper($state_name->state_name);
    $rto_cities = explode('/',  $rto_cities->rto_name); */


    /* foreach($rto_cities as $rto_city)
    {

        $rto_city = strtoupper($rto_city);

        $rto_data = DB::table('car_icici_lombard_rto_location')
                    ->where('txt_rto_location_desc', $state_name ."-". $rto_city)
                    ->first();

        if($rto_data)
        {

           break;
        }
    } */


    $rto_data = DB::table('car_icici_lombard_rto_location')
    ->where('txt_rto_location_code', $master_rto->icici_4w_location_code)
    ->first();

    if (empty($rto_data))
    {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $requestData->rto_code.' RTO Location Not Found',
            'request' => [
                'rto_code' => $requestData->rto_code
            ]
        ];
    }
    else
    {
        $txt_rto_location_code = $rto_data->txt_rto_location_code;
    }

    $mmv_data->manf_name = $mmv_data->manufacturer_name;
    //$mmv_data->version_name = $mmv_data->model_name;

    // token Generation

    $additionData = [
        'requestMethod' => 'post',
        'type' => 'tokenGeneration',
        'section' => 'car',
        'productName'  => $productData->product_name,
        'enquiryId' => $enquiryId,
        'transaction_type' => 'quote'
    ];

    $tokenParam = [
        'grant_type' => 'password',
        'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
        'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
        'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
        'scope' => 'esbmotor',
    ];

    // If token API is not working then don't store it in cache - @Amit - 07-10-2022
    //$token_cache_name = 'constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR.car.' . $enquiryId;
    //$token_cache = Cache::get($token_cache_name);
    $token_cache = NULL;
    if(empty($token_cache)) {
        $token_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token_decoded = json_decode($token_response['response'], true);
        if(isset($token_decoded['access_token'])) {
            update_quote_web_servicerequestresponse($token_response['table'], $token_response['webservice_id'], "Token Generation Success", "Success" );
            // $token = cache()->remember($token_cache_name, 60 * 45, function () use ($token_response) {
            //     return $token_response;
            // });
            $token = $token_response;
        } else {
            return [
                'webservice_id' => $token_response['webservice_id'],
                'table' => $token_response['table'],
                'status' => false,
                'message' => "Insurer not reachable,Issue in Token Generation service"
            ];
        }
    } else {
        $token = $token_cache;
    }
    // $token = cache()->remember('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR', 60 * 45, function() use ($tokenParam ,$additionData){
    //     return getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);

    // });

    if (!empty($token))
    {
        $token = json_decode($token['response'], true);

        if(isset($token['access_token']))
        {
            $access_token = $token['access_token'];
        }
        else
        {
            return [
                'webservice_id' => $token_response['webservice_id'],
                'table' => $token_response['table'],
                'status' => false,
                'message' => "Insurer not reachable,Issue in Token Generation service"
            ];
        }

        $corelationId = getUUID();
        $IsLLPaidDriver = false;
        $IsPAToUnnamedPassengerCovered = false;
        $PAToUnNamedPassenger_IsChecked = false;
        $IsElectricalItemFitted = false;
        $IsNonElectricalItemFitted = false;
        $bifuel = false;
        $tppd_limit = 750000;
        $breakingFlag = false;
        $isGddEnabled = config('constants.motorConstant.IS_GDD_ENABLED_ICICI') == 'Y' ? true : false;

        $isInspectionWaivedOff = false;
        $waiverExpiry = null;
        if (
            $is_breakin &&
            !empty($requestData->previous_policy_expiry_date) &&
            strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
            !in_array($premium_type, ['third_party', 'third_party_breakin']) &&
            config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_INSPECTION_WAIVED_OFF') == 'Y'
        ) {
            $date1 = new DateTime($requestData->previous_policy_expiry_date);
            $date2 = new DateTime();
            $interval = $date1->diff($date2);
            
            //inspection is not required for breakin within 1 days
            if ($interval->days <= 1) {
                $isInspectionWaivedOff = true;
                $ribbonMessage = 'No Inspection Required';
                $waiverExpiry = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date .' +1 days'));
            }
        }

        if ($requestData->business_type == 'newbusiness')
        {
            $BusinessType = 'New Business';
            $Registration_Number = 'NEW';
            $PolicyStartDate = date('Y-m-d');
            $IsPreviousClaim = 'N';
            $od_term_type = '13';
            $cpa = '1';
            $od_text = 'od_one_three';
            $applicable_ncb_rate = 0;
            $current_ncb_rate = 0;
        }
        else
        {
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));

            }
            $BusinessType = 'Roll Over';
            $PolicyStartDate = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $breakin_days = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if (($requestData->business_type == 'breakin'))
            {
                if($isInspectionWaivedOff ===true)
                {
                    $PolicyStartDate = date('Y-m-d');
                }
                else
                {
                    $PolicyStartDate = date('Y-m-d', strtotime('+1 day'));
                }
            }
            if ($requestData->is_claim == 'N'  && $premium_type != 'third_party')
            {
                $applicable_ncb_rate = $requestData->applicable_ncb;
                $current_ncb_rate = $requestData->previous_ncb;
            }
            else
            {
                $applicable_ncb_rate = 0;
                $current_ncb_rate = 0;
            }

            if($requestData->is_claim == 'Y'  && $premium_type != 'third_party') {
                $current_ncb_rate = $requestData->previous_ncb;
            }

            if($breakin_days > 90)
            {
                $applicable_ncb_rate = 0;
                $current_ncb_rate = 0;
            }
            $IsPreviousClaim = ($requestData->is_claim == 'Y') ? 'Y' : 'N';
            if($requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party' ||
                $requestData->business_type == 'breakin')
            {
                $requestData->business_type = 'breakin';
                $breakingFlag = true;
            }

            if ($requestData->vehicle_registration_no != '') {
                $Registration_Number = $requestData->vehicle_registration_no;
            } else {
                $Registration_Number = $requestData->rto_code . '-AB-1234';
            }
                     
            if (isBhSeries($requestData->vehicle_registration_no)) 
            {
                $Registration_Number = getRegisterNumberWithHyphen($requestData->vehicle_registration_no); 
            }    
 

        }

        $tenure_year = ($premium_type == 'third_party' && $requestData->business_type == 'newbusiness') ? 3 : 1;
        $PolicyEndDate = date('Y-m-d', strtotime(date('Y-m-d', strtotime("+$tenure_year year -1 day", strtotime(strtr($PolicyStartDate, ['-' => '']))))));

        $first_reg_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));

        if ($requestData->previous_policy_expiry_date == '')
        {
            $prepolstartdate = '01/01/1900';
            $prepolicyenddate = '01/01/1900';
        }
        else
        {
            if($requestData->previous_policy_type_identifier_code == '33')
            {
                $prepolstartdate = date('Y-m-d', strtotime(date('Y-m-d', strtotime('-3 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            else
            {
               $prepolstartdate = date('Y-m-d', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }

            $prepolicyenddate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
        }

        $IsConsumables = false;
        $IsTyreProtect = false;
        $IsRTIApplicableflag = false;
        $IsEngineProtectPlus = false;
        $LossOfPersonalBelongingPlanName = '';
        $KeyProtectPlan = '';
        $RSAPlanName = '';
        $EMECover_Plane_name = $ZeroDepPlanName = '';//new addon emi cover
                        //valid upto 15 years
        $ILSmartAssistPlanName = '';
        if($car_age <= 15)
        {
            $EMECover_Plane_name = (env('APP_ENV') == 'local') ? 'Premium Segment' : '';
        }
        /* if($car_age <= 5 && $premium_type != 'third_party')
        {
            $IsConsumables = true;
            $IsRTIApplicableflag = true;
            $IsEngineProtectPlus = true;
            $LossOfPersonalBelongingPlanName = 'PLAN A';
            $KeyProtectPlan      = 'KP1';
        }
        if($car_age <= 3 && $premium_type != 'third_party')
        {
            $IsTyreProtect = true;
        }
        if($productData->zero_dep == 0)
        {
            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
            if($car_age <= 8)
            {
                $IsConsumables = true;
            }

            if($car_age > 5 && $car_age <= 8)
            {

                $RSAPlanName = 'RSA-including Key Protect';
            }

        }
        else
        {
            $ZeroDepPlanName = '';

        }

        if($premium_type == 'third_party')
        {
            $ZeroDepPlanName = '';
            $RSAPlanName = '';
        } */
        //implement Addon's Bundle Logic

        switch ($productData->product_identifier) {
            case 'silver':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                // $IsRTIApplicableflag = true;
                break;
            case 'silver_with_rti':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                $IsRTIApplicableflag = true;
                break;
            case 'gold':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $IsTyreProtect = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                // $IsRTIApplicableflag = true;
                break;
            case 'gold_with_rti':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $IsTyreProtect = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                $IsRTIApplicableflag = true;
                break;
            case 'gold_plus':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $IsEngineProtectPlus = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                // $IsRTIApplicableflag = true;
                break;
            case 'gold_plus_with_rti':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $IsEngineProtectPlus = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                $IsRTIApplicableflag = true;
                break;
            case 'platinum':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $IsEngineProtectPlus = true;
                $IsTyreProtect = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                // $IsRTIApplicableflag = true;
                break;
            case 'premium_segment':
                break;
            case 'platinum_with_rti':
                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                $IsConsumables = true;
                $IsEngineProtectPlus = true;
                $IsTyreProtect = true;
                $RSAPlanName =  'RSA-including Key Protect';
                $LossOfPersonalBelongingPlanName = 'PLAN A';
                $IsRTIApplicableflag = true;
                break;
            default:
                if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    if ($requestData->business_type == 'newbusiness' && $productData->product_identifier == 'zero_dep') {
                        $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                        $IsConsumables = true;
                        $IsRTIApplicableflag = true;
                        $IsEngineProtectPlus = true;
                        $IsTyreProtect = true;
                        $LossOfPersonalBelongingPlanName = 'PLAN A';
                        $KeyProtectPlan      = '';
                        $RSAPlanName =  'RSA-including Key Protect';
                    } else if($productData->product_identifier == 'BASIC'){
                        $ZeroDepPlanName = '';
                        $IsConsumables = false;
                        $IsRTIApplicableflag = true;
                        $IsEngineProtectPlus = false;
                        $IsTyreProtect = false;
                        $LossOfPersonalBelongingPlanName = 'PLAN A';
                        $KeyProtectPlan      = '';
                        $RSAPlanName =  '';
                    } else {
                        if ($car_age <= 5) {
                            $IsRTIApplicableflag = true;
                            $IsEngineProtectPlus = true;
                            $LossOfPersonalBelongingPlanName = 'PLAN A';
                            $KeyProtectPlan      = '';
                            $RSAPlanName = 'RSA-Plus';
                        }else if($car_age > 5 && $car_age <= 7){
                            $RSAPlanName = 'RSA-including Key Protect';
                        }
                        if ($car_age <= 3) {
                            $IsTyreProtect = true;
                        }
                    }
                }
                break;
        }
        // if($productData->zero_dep == 0 && trim($mmv_data->car_segment) == 'Premium Cars C') {
        //     $RSAPlanName = 'RSA-Plus';
        //     $KeyProtectPlan   = 'KP1';
        // }
        // if(trim($mmv_data->car_segment) == 'Premium Cars C' && $car_age <= 10)
        // {
        //     $KeyProtectPlan   = 'KP1';
        // }else if($car_age <= 10)
        // {
        //     $KeyProtectPlan   = 'KP2';
        // }

        // if($productData->zero_dep == 0 && $RSAPlanName == 'RSA-including Key Protect') {
        //     $RSAPlanName = 'RSA-Plus';
        //     if($car_age > 5 && $car_age <= 7) {
        //         $RSAPlanName = 'RSA-including Key Protect';
        //         $KeyProtectPlan   = '';
        //     }
        // }

        if (in_array($productData->product_identifier, ["silver", "gold", "gold_plus", "platinum","platinum_with_rti","silver_with_rti", "gold_with_rti", "gold_plus_with_rti"]) && $car_age <= 10) {
            if($car_age <= 7){
                $KeyProtectPlan   = '';
                $RSAPlanName = 'RSA-including Key Protect';
            }
            if ($productData->zero_dep == 0) {
                if (trim($mmv_data->car_segment) == 'Premium Cars C') {
                    $RSAPlanName = 'RSA-Plus';
                    $KeyProtectPlan = 'KP8';
                } else {
                    $RSAPlanName = 'RSA-Standard';
                    $KeyProtectPlan = 'KP1';
                }
            }
        } else if (!in_array($productData->product_identifier, ["silver", "gold", "gold_plus", "platinum","platinum_with_rti", "silver_with_rti", "gold_with_rti", "gold_plus_with_rti"]) && $productData->zero_dep == 0) {
            if (trim($mmv_data->car_segment) == 'Premium Cars C' && $car_age <= 10) {
                $RSAPlanName = 'RSA-Plus';
                $KeyProtectPlan   = 'KP1';
            } else if ($car_age <= 10) {
                $RSAPlanName = 'RSA-Standard';
                $KeyProtectPlan   = 'KP2';
            } else {
                $RSAPlanName = '';
                $KeyProtectPlan   = '';
            }
        } else {
            $RSAPlanName = '';
            $KeyProtectPlan   = '';
        }
        if(in_array($productData->product_identifier, ['silver']) && in_array($mmv_data->manufacturer_name, ['TOYOTA', 'FORD','NISSAN']) || (stripos($mmv_data->manufacturer_name, 'RENAULT') !== false)) {
            $KeyProtectPlan = '';
            $RSAPlanName = '';
        }
        if(trim($mmv_data->car_segment) == 'Premium Cars C' && $car_age > 5)
        {
            $IsTyreProtect = false;
        }else if($car_age > 3 && trim($mmv_data->car_segment) != 'Premium Cars C'){
            $IsTyreProtect = false;
        }
            //new addons changes
            if ((in_array($productData->product_identifier, ["silver", "gold", "gold_plus", "platinum", "platinum_with_rti","silver_with_rti", "gold_with_rti", "gold_plus_with_rti"]) || $productData->zero_dep == 0) && round($age / 12, 2) <= 4.80) {
                $ILSmartAssistPlanName = "Prestige";
                if ($productData->zero_dep == 0) {
                    if (trim($mmv_data->car_segment) == 'Premium Cars C') {
                        $ILSmartAssistPlanName = "Elite";
                        $KeyProtectPlan = 'KP8';
                    } elseif (trim($mmv_data->car_segment) != 'Premium Cars C') {
                        $ILSmartAssistPlanName = "Pro";
                        $KeyProtectPlan = 'KP2';
                    }
                }
            } elseif (
                (in_array($productData->product_identifier, ["silver", "gold", "gold_plus", "platinum", "platinum_with_rti","silver_with_rti", "gold_with_rti", "gold_plus_with_rti"]) || $productData->zero_dep == 0) && round($age / 12, 2) >= 4.81
                && round($age / 12, 2) <= 9.8 && $requestData->applicable_ncb != 0
            ) {
                $ILSmartAssistPlanName = "Prestige";
                if (trim($mmv_data->car_segment) == 'Premium Cars C') {
                    $ILSmartAssistPlanName = "Elite";
                    $KeyProtectPlan   = 'KP8';
                } elseif (trim($mmv_data->car_segment) != 'Premium Cars C' && $mmv_data->manufacturer_name != "Maruti") {
                    $ILSmartAssistPlanName = "Pro";
                    $KeyProtectPlan   = 'KP2';
                }
        }

        $IRDALicenceNumber = '';
        $CertificateNumber = '';
        $PanCardNo = '';
        $AadhaarNo = '';
        $ProductCode = '';
        $IsPos = 'N';

        if ($requestData->vehicle_owner_type == 'I')
        {
            $customertype = 'INDIVIDUAL';
            $ispacoverownerdriver = true;
        }
        else
        {
            $customertype = 'CORPORATE';
            $ispacoverownerdriver = false;
        }



        $ElectricalItemsTotalSI = $NonElectricalItemsTotalSI = $BiFuelKitSi = $PAToUnNamedPassengerSI = 0;



        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

        $IsPAToUnnamedPassengerCovered = false;
        $PAToUnNamedPassengerSI = 0;
        $IsElectricalItemFitted = false;
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = false;
        $NonElectricalItemsTotalSI = 0;
        $bifuel = false;
        $BiFuelKitSi = 0;
        $voluntary_deductible_amount = 0;
        $IsVehicleHaveLPG =false;
        $IsLLPaidEmployee = false;
        $geoExtension = false;
        $extensionCountryName = '';

        if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
        {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if($value['name'] == 'Electrical Accessories')
                {
                    $IsElectricalItemFitted = true;
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                }
                else if($value['name'] == 'Non-Electrical Accessories')
                {
                    $IsNonElectricalItemFitted = true;
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                }
                else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                {
                    $type_of_fuel = '5';
                    $bifuel = true;
                    $Fueltype = 'CNG';
                    $IsVehicleHaveLPG = false;
                    $BiFuelKitSi = $value['sumInsured'];
                }
            }
        }

        if(isset($mmv_data->fyntune_version['fuel_type']) && $mmv_data->fyntune_version['fuel_type'] == 'CNG')
        {
            $requestData->fuel_type = $mmv_data->fyntune_version['fuel_type'];
            $bifuel = true;
            $BiFuelKitSi = ($tp_check == 'true' && $car_age > 5) ? 1 : 0;
            $IsVehicleHaveLPG = false;
            $mmv_data->fuelType = $mmv_data->fyntune_version['fuel_type'];
        }else if(isset($mmv_data->fyntune_version['fuel_type']) && $mmv_data->fyntune_version['fuel_type'] == 'LPG')
        {
            $requestData->fuel_type = $mmv_data->fyntune_version['fuel_type'];
            $bifuel = false;
            $BiFuelKitSi = 0;
            $IsVehicleHaveLPG = true;
            $mmv_data->fuelType = $mmv_data->fyntune_version['fuel_type'];
        }
        if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
                $additional_covers = $selected_addons->additional_covers;
                foreach ($additional_covers as $value) {
                   if($value['name'] == 'Unnamed Passenger PA Cover')
                   {
                        $IsPAToUnnamedPassengerCovered = true;
                        $PAToUnNamedPassenger_IsChecked = true;
                        $PAToUnNamedPassenger_NoOfItems = '1';
                        $PAToUnNamedPassengerSI = $value['sumInsured'];
                   }
                   if($value['name'] == 'LL paid driver')
                   {
                        $IsLLPaidDriver = true;
                   }
                   if($value['name'] == 'Geographical Extension')
                   {
                        $geoExtension = true;
                        $geoExtensionCountryName = array_filter($value['countries'], fn($country) => $country !== false);
                        $extensionCountryName = !empty($geoExtensionCountryName) ? implode(', ', $geoExtensionCountryName) : 'No Extension';
                   }
                }
            }

        if($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '')
        {
            $discounts_opted = $selected_addons->discounts;

            foreach ($discounts_opted as $value) {
               if($value['name'] == 'TPPD Cover')
               {
                    $tppd_limit = 6000;
               }
               if($value['name'] == 'voluntary_insurer_discounts')
               {
                   $voluntary_deductible_amount = $value['sumInsured'];
               }
            }
        }

        if($premium_type == 'own_damage')
        {
            $tppd_limit = 750000;
            $ispacoverownerdriver = false;
            $IsLLPaidDriver = false;
            $IsPAToUnnamedPassengerCovered = false;
            $PAToUnNamedPassengerSI = 0;
        }

        if(($requestData->business_type == 'breakin') && config('constants.IcConstants.icici_lombard.IS_TYRE_PROTECT_DISABLED_FOR_BREAKIN') == 'Y'){
            $IsTyreProtect = false;
        }
        // if(config('constants.IcConstants.icici_lombard.IS_EME_COVER_DISABLED_FOR_ICICI') == 'N'){
        //     $EMECover_Plane_name = '';
        // }
        $EMECover_Plane_name = '';
        if($ZeroDepPlanName == '' & $car_age > 5 && $car_age <= 7)
        {
            $RSAPlanName = '';
        }
        if ($requestData->vehicle_owner_type == 'C')
        {
            $IsLLPaidEmployee = true;
        }
        if (strtoupper($requestData->fuel_type) == 'ELECTRIC'){
            $IsEngineProtectPlus = false;
        }
        $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                    
                }
            }
        }
        if ($requestData->business_type == 'newbusiness') {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '3';
            // $cpa_year = '1'; // By Default CPA will be 1 year
        } else {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '1';
        }
        $model_config_premium =
        [
            'BusinessType'                    => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
            'CustomerType'                    => $customertype,
            'PolicyStartDate'                 => date('Y-m-d', strtotime($PolicyStartDate)),
            'PolicyEndDate'                   => $PolicyEndDate,
            'VehicleMakeCode'                 => $mmv_data->manufacturer_code,
            'VehicleModelCode'                => $mmv_data->model_code,
            'RTOLocationCode'                 => $rto_data->txt_rto_location_code,
            "RegistrationNumber"              => strtoupper($Registration_Number),
            'ManufacturingYear'               => date('Y', strtotime('01-' . $requestData->manufacture_year)),
            'DeliveryOrRegistrationDate'      => date('Y-m-d', strtotime($vehicleDate)) ?? $first_reg_date,
            'FirstRegistrationDate'           => $first_reg_date,
            'ExShowRoomPrice'                 => 0,
            'Tenure'                          => '1',
            'TPTenure'                        => ($requestData->business_type == 'newbusiness') ? '3' :'1',
            'IsValidDrivingLicense'           => false,
            'IsMoreThanOneVehicle'            => false,
            'IsNoPrevInsurance'               => ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party') ? true : false,
            'IsTransferOfNCB'                 => false,
            'TransferOfNCBPercent'            => 0,
            'IsLegalLiabilityToPaidDriver'    => $IsLLPaidDriver,
            'IsPACoverOwnerDriver'            => $ispacoverownerdriver,
            "isPACoverWaiver"                 => false,                       //PACoverWaiver should be true in case already having PACover i.e PAOwner is false
            // "PACoverTenure"                   => '1',
            "PACoverTenure"                   => isset($cpa_tenure) ? $cpa_tenure : '1',
            'IsVehicleHaveLPG'                => $IsVehicleHaveLPG,
            'IsVehicleHaveCNG'                => $bifuel,
            'SIVehicleHaveLPG_CNG'            => $BiFuelKitSi,
            'TPPDLimit'                       => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
            'SIHaveElectricalAccessories'     => $ElectricalItemsTotalSI,
            'SIHaveNonElectricalAccessories'  => $NonElectricalItemsTotalSI,
            'IsPACoverUnnamedPassenger'       => $IsPAToUnnamedPassengerCovered,
            'SIPACoverUnnamedPassenger'       => $PAToUnNamedPassengerSI * ($mmv_data->seating_capacity),
            'IsLegalLiabilityToPaidEmployee'  => $IsLLPaidEmployee,
            'NoOfEmployee'                    => $mmv_data->seating_capacity,
            'IsLegaLiabilityToWorkmen'        => false,
            'NoOfWorkmen'                     => 0,
            'IsFiberGlassFuelTank'            => false,
            'IsVoluntaryDeductible'           => ($voluntary_deductible_amount != 0) ? false: false,
            'VoluntaryDeductiblePlanName'     => ($voluntary_deductible_amount != 0) ? 0 : 0,
            'IsAutomobileAssocnFlag'          => false,
            'IsAntiTheftDisc'                 => false,
            'IsHandicapDisc'                  => false,
            'IsExtensionCountry'              => $geoExtension,
            'ExtensionCountryName'            => $extensionCountryName,
            'IsGarageCash'                    => false,
            'GarageCashPlanName'              => 4,
            'ZeroDepPlanName'                 => $ZeroDepPlanName,
            // 'RSAPlanName'                     => $RSAPlanName,
            'IsEngineProtectPlus'             => $IsEngineProtectPlus,
            'IsConsumables'                   => $IsConsumables,
            'IsTyreProtect'                   => $IsTyreProtect,
            'KeyProtectPlan'                  => $KeyProtectPlan,
            'LossOfPersonalBelongingPlanName' => $LossOfPersonalBelongingPlanName,
            'IsRTIApplicableflag'             => $IsRTIApplicableflag,
            'EMECover'                        => $EMECover_Plane_name,
            'NoOfPassengerHC'                 => $mmv_data->seating_capacity - 1,
            'IsApprovalRequired'             => false,
            'ProposalStatus'                  => null,
            'OtherLoading'                    => 0,
            'OtherDiscount'                   => 0,
            'GSTToState'                      => $state_name,
            'CorrelationId'                   => $corelationId,
            'SmartAssistPlanName'             => $ILSmartAssistPlanName
        ];

        $IsPos = 'N';
        if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){
                $is_icici_pos_disabled_renewbuy = 'Y';
        }
        else{
            $is_icici_pos_disabled_renewbuy = config('constants.motorConstant.IS_ICICI_POS_DISABLED_RENEWBUY');
        }

        $is_pos_enabled = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
        $pos_testing_mode = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
        $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = $ProductCode = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($IsPos == 'N')
        {
            switch($premium_type)
            {
                case "comprehensive":
                    $deal_id= config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
                    $ProductCode = '2311';
                break;
                case "own_damage":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');
                    $ProductCode = '2311';

                break;
                case "third_party":
                    $deal_id= config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
                    $ProductCode = '2319';
                break;

            }
            if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
            {
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');
                $ProductCode = '2311';
            }

            #for third party 
            if(($premium_type_array->premium_type_code ?? '') == 'third_party'){

                $ProductCode = '2319';
            }

        }

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
                $CertificateNumber = 'AB000001';
                $PanCardNo = 'ATAPK3554C';
                $AadhaarNo = '689505607468';
            }

            // $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');

        }
        // elseif($pos_testing_mode === 'Y')
        // {
        //     $IsPos = 'Y';
        //     $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
        //     $CertificateNumber = 'AB000001';
        //     $PanCardNo = 'ATAPK3554C';
        //     $AadhaarNo = '689505607468';
        //     $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');
        // }
        else
        {
            $model_config_premium['DealId'] = $deal_id;
        }

        if($IsPos == 'Y')
        {
            if(isset($model_config_premium['DealId']))
            {
                unset($model_config_premium['DealId']);
            }
        }
        else
        {
            if(!isset($model_config_premium['DealId']))
            {
               $model_config_premium['DealId'] = $deal_id;
            }
        }


        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' || $premium_type == 'own_damage')
        {
            if(($requestData->previous_policy_type == 'Third-party'))
            {
                $model_config_premium['IsNoPrevInsurance'] = false;
            }
            $model_config_premium['PreviousPolicyDetails'] = [
                'previousPolicyStartDate' => $prepolstartdate,
                'previousPolicyEndDate'   => $prepolicyenddate,
                'ClaimOnPreviousPolicy'   => ($IsPreviousClaim == 'Y') ? true : false,
                'PreviousPolicyType'      => ($requestData->previous_policy_type == 'Third-party') ? 'TP': 'Comprehensive Package',
                'TotalNoOfODClaims'       => ($IsPreviousClaim == 'Y') ? '1' : '0',
                'NoOfClaimsOnPreviousPolicy'   => ($IsPreviousClaim == 'Y') ? '1' : '0',
                'BonusOnPreviousPolicy'   => $requestData->previous_policy_type == 'Third-party' ? 0 :$current_ncb_rate,
            ];
        }else
        {
                $model_config_premium['Tenure']= 1;
                $model_config_premium['TPTenure']= 3;
                //$model_config_premium['PACoverTenure']= 3;
                // $model_config_premium['PACoverTenure'] = 1; // By default CPA tenure will be 1 Year
                $model_config_premium['PACoverTenure'] = isset($cpa_tenure) ? $cpa_tenure : '1'; // By default CPA tenure will be 1 Year
        }




        if($premium_type == 'own_damage')
        {
            $model_config_premium['TPStartDate'] = $prepolstartdate;
            $model_config_premium['TPEndDate'] = date('Y-m-d', strtotime('+3 year -1 day', strtotime($prepolstartdate)));
            $model_config_premium['TPInsurerName'] = 'GIC';#'BAJAJALLIANZ';
            $model_config_premium['TPPolicyNo'] = '123456789';
            $model_config_premium['Tenure']= 1;
            $model_config_premium['TPTenure']= 0;
            $model_config_premium['PreviousPolicyDetails']['PreviousPolicyType']= 'Bundled Package Policy';
            $model_config_premium['IsLegalLiabilityToPaidDriver']= false;
            $model_config_premium['IsPACoverOwnerDriver']= false;
            $model_config_premium['IsPACoverUnnamedPassenger']= false;
        }

        if(($premium_type == 'own_damage') && $requestData->previous_policy_type == 'Third-party')
        {
            unset($model_config_premium['PreviousPolicyDetails']);
        }
        if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure')
        {
            unset($model_config_premium['PreviousPolicyDetails']);
        }

        $enable_idv_service = config('constants.ICICI_LOMBARD.ENABLE_ICICI_IDV_SERVICE');

        $vehicle_idv = 0;
        $idv = $max_idv =$min_idv = $minimumprice = $maximumprice = 0;
        if($premium_type != 'third_party' && $enable_idv_service == 'Y')
        {
            $access_token_for_idv = '';

            $tokenParam =
            [
                'grant_type' => 'password',
                'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
                'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
                'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
                'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
                'scope' => 'esbmotormodel',
            ];

            // $token = cache()->remember('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR_scope', 60 * 45, function() use ($tokenParam, $additionData){
            //     return getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
            // });
            $token = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
            if(!empty($token['response']))
            {
                $token_data = json_decode($token['response'], true);

                if(isset($token_data['access_token']))
                {
                    update_quote_web_servicerequestresponse($token['table'], $token['webservice_id'], "Token Generation SuccessFully...!", "Success" );
                    $access_token_for_idv= $token_data['access_token'];
                }
            }
            else
            {
                return
                [
                    'webservice_id' => $token['webservice_id'],
                    'table' => $token['table'],
                    'status'=> false,
                    'message'=> 'No response received from IDV service Token Generation'
                ];
            }

           $idv_service_request =
           [
               'manufacturercode'=> $mmv_data->manufacturer_code,
               'BusinessType' => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
               'rtolocationcode' => $rto_data->txt_rto_location_code,
               'DeliveryOrRegistrationDate'=> date('Y-m-d', strtotime($vehicleDate)) ?? $first_reg_date,
               'PolicyStartDate'=> date('Y-m-d', strtotime($PolicyStartDate)),
               'DealID'=> $deal_id,
               'vehiclemodelcode' => $mmv_data->model_code,
               'correlationId' => $model_config_premium['CorrelationId'],
           ];

            if($IsPos == 'Y')
            {
                if(isset($idv_service_request['DealID']))
                {
                    unset($idv_service_request['DealID']);
                }
            }
            else
            {
                if(!isset($idv_service_request['DealID']))
                {
                   $idv_service_request['DealID'] = $deal_id;
                }
            }

           $additionPremData = [
            'requestMethod' => 'post',
            'type' => 'idvService',
            'section' => 'car',
            'productName'  => $productData->product_name,
            'token' => $access_token_for_idv,
            'enquiryId' => $enquiryId,
            'transaction_type' => 'quote'
          ];

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
                $additionPremData = array_merge($additionPremData,$pos_details);
            }

           $url = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IDV_SERVICE_END_POINT_URL_MOTOR');
           $data = getWsData($url, $idv_service_request, 'icici_lombard', $additionPremData);

           if(!empty($data['response']))
           {
              $idv_service_response = json_decode($data['response'], true);
              if(isset($idv_service_response['status']) && $idv_service_response['status'] == true)
              {
                update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success" );
                  $idvDepreciation = (1 - $idv_service_response['idvdepreciationpercent']);
                  if(isset($idv_service_response['maxidv']))
                  {
                     $max_idv = $idv_service_response['maxidv'];
                     $vehicle_idv = $max_idv;
                  }
                  if(isset($idv_service_response['minidv']))
                  {
                    $min_idv = $idv_service_response['minidv'];
                    $vehicle_idv = $min_idv ;
                  }
                  if(isset($idv_service_response['minimumprice']))
                  {
                     $minimumprice = $idv_service_response['minimumprice'];
                     $vehicle_idv = $minimumprice;
                  }
                  if(isset($idv_service_response['maximumprice']))
                  {
                    $maximumprice = $idv_service_response['maximumprice'];
                    $vehicle_idv = $maximumprice;
                  }

                  $model_config_premium['ExShowRoomPrice'] = ($minimumprice);
              }
              else
              {
                 return
                 [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status'=> false,
                    'message'=> isset($idv_service_response['statusmessage']) ? $idv_service_response['statusmessage'] : 'Issue in IDV service'
                 ];
              }

           }
           else
           {
             return
             [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status'=> false,
                'message'=> 'No response received from IDV service'
             ];
           }
           // idv service end
        }
        else
        {
            $model_config_premium['ExShowRoomPrice'] = 0;
        }

        $additionPremData = [
            'requestMethod' => 'post',
            'type' => 'premiumCalculation',
            'section' => 'car',
            'productName'  => $productData->product_name,
            'token' => $access_token,
            'enquiryId' => $enquiryId,
            'transaction_type' => 'quote',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ];


        $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;

        if(!empty($pos_data) && $vehicle_idv <= 5000000 && $IsPos == 'Y')
        {
            $pos_details['pos_details'] = [
                    'IRDALicenceNumber' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR'),
                    'CertificateNumber' => $pos_data->unique_number,
                    'PanCardNo'         => $pos_data->pan_no,
                    'AadhaarNo'         => $pos_data->aadhar_no,
                    'ProductCode'       => $ProductCode//config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR')
            ];
            // unset($model_config_premium['DealId']); Need dealId when it's non pos.
            $additionPremData = array_merge($additionPremData,$pos_details);
        }

        if ($premium_type == 'third_party')
        {
            $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR_TP');
        }
        else
        {

            $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR');
        }

        if($productData->good_driver_discount=="Yes" && $isGddEnabled)
        {

            if(!empty($requestData->distance))
            {
                $distance = $requestData->distance;
            }
            else
            {
                $distance = 0;
            }
            $selectedPayud = "0";
            $payud_Arr['0']['minKMRange']="0.0";
            $payud_Arr['0']['maxKMRange']="5000";
            $payud_Arr['0']['InitialPlan']="5000";
            $payud_Arr['0']['OdometerCaptureDate']="2023-02-01T00:00:00";
            $payud_Arr['0']['OdometerReading']="10000";
            $payud_Arr['0']['isOptedByCustomer']=false;
            $payud_Arr['1']['minKMRange']="5001";
            $payud_Arr['1']['maxKMRange']="7500";
            $payud_Arr['1']['InitialPlan']="7500";
            $payud_Arr['1']['OdometerCaptureDate']="2023-02-01T00:00:00";
            $payud_Arr['1']['OdometerReading']="10000";
            $payud_Arr['1']['isOptedByCustomer']=false;
            foreach($payud_Arr as $key_payud=>$payud)
            {
                if($payud['InitialPlan'] == $distance)
                {
                    // dd("turr");
                    $selectedPayud = $key_payud;
                }
                else
                {
                    $selectedPayud = "0";
                }

            }
            $payud_initialplan=$payud_Arr[$selectedPayud]['InitialPlan'];
            $payud_odometercapturedate=$payud_Arr[$selectedPayud]['OdometerCaptureDate'];
            $payud_odometerreading=$payud_Arr[$selectedPayud]['OdometerReading'];
            $model_config_premium['IsPAYU']="true";
            $model_config_premium['PAYUDetails']['InitialPlan']="$payud_initialplan";
            $model_config_premium['PAYUDetails']['OdometerCaptureDate']="$payud_odometercapturedate";
            $model_config_premium['PAYUDetails']['OdometerReading']="$payud_odometerreading";
        }

        #for removing correlationId
        unset($model_config_premium['CorrelationId']);
        
        $checksum_data = checksum_encrypt($model_config_premium);
        $additionPremData['checksum'] = $checksum_data;
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'icici_lombard',$checksum_data,'CAR');

        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
        {
            $data = $is_data_exits_for_checksum;
        }else{
            $model_config_premium['CorrelationId'] = $corelationId;
            $data = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
        }
        update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Success", "Success" );


        #offline idv calculation
        if($enable_idv_service != 'Y' && $requestData->is_idv_changed != 'Y' && $premium_type != 'third_party')
        {
            if($data['response'])
            {
                $dataResponse = json_decode($data['response'], true);

                if (isset($dataResponse['status']) && $dataResponse['status'] == 'Success')
                {
                    $idvDepreciation = (1 - ($dataResponse['generalInformation']['percentageOfDepriciation'] / 100));
                    $offline_idv = (int) ($dataResponse['generalInformation']['depriciatedIDV']);
                    $idv_data = get_ic_min_max($offline_idv, 0.95, 1.05, 0, 0, 0);
                    $min_idv =  $idv_data->min_idv;
                    $max_idv =  $idv_data->max_idv;

                    $VehiclebodyPrice = ($min_idv/$idvDepreciation);

                    $model_config_premium['ExShowRoomPrice'] = $VehiclebodyPrice;
                    
                    $checksum_data = checksum_encrypt($model_config_premium);
                    $additionPremData['checksum'] = $checksum_data;
                    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'icici_lombard',$checksum_data,'CAR');

                    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                    {
                        $data = $is_data_exits_for_checksum;
                    }else{
                        $data = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
                    }
                }
                else
                {
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => false,
                        'message' => isset($dataResponse['message'])? $dataResponse['message']:"Insurer not reachable"
                    ];
                }
            }
        }

        // if($requestData->business_type == 'newbusiness'){
        //     $model_config_premium['PACoverTenure'] = '3';
        //     unset($model_config_premium['CorrelationId']);   #for removing correlationId

        //     #for appliying checksum for plans as well
        //     $checksum_data = checksum_encrypt($model_config_premium);
        //     $additionPremData['checksum'] = $checksum_data;
        //     $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'icici_lombard', $checksum_data, 'CAR');

        //     if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {

        //         $get_response_cpa = $is_data_exits_for_checksum;
        //     } else {

        //         $model_config_premium['CorrelationId'] = $corelationId;
        //         $additionPremData['productName'] = $productData->product_name." CPA 3 Year";
        //         $get_response_cpa = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
        //     }
        // $cpa_multiyear = json_decode($get_response_cpa['response'], true);
        // if (isset($cpa_multiyear['status']) && strtolower($cpa_multiyear['status']) == 'success') {
        //     update_quote_web_servicerequestresponse($get_response_cpa['table'], $get_response_cpa['webservice_id'], "Premium Calculation Success", "Success");
        // }
        // }

        if (!empty($data['response']))
        {
           $arr_premium = json_decode($data['response'], true);

            if(isset($arr_premium['status']) && strtolower($arr_premium['status']) == 'success')
            {
                update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success" );
                $idv = ($arr_premium['generalInformation']['depriciatedIDV']);
                if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true))
                {
                    $msg = isset($arr_premium['deviationMessage']) ? $arr_premium['deviationMessage'] : 'Ex-Showroom price provided is not under permissable limits';
                     return [
                       'webservice_id' => $data['webservice_id'],
                       'table' => $data['table'],
                       'status' => false,
                       'message' => $msg
                    ];

                }

                if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true))
                {
                    $msg = "Proposal application didn't pass underwriter approval";
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => false,
                        'message' => $msg
                    ];
                }




               if($premium_type != 'third_party')
               {

                   // idv change condition
                    if ($requestData->is_idv_changed == 'Y')
                    {

                        if ($enable_idv_service != 'Y')
                        {
                            $offline_idv = (int) ($arr_premium['generalInformation']['depriciatedIDV']);
                            $idv_data = get_ic_min_max($offline_idv, 0.95, 1.05, 0, 0, 0);
                            $min_idv =  $idv_data->min_idv;
                            $max_idv =  $idv_data->max_idv;

                            $idvDepreciation = (1 - ($arr_premium['generalInformation']['percentageOfDepriciation'] / 100));
                            $maximumprice = ($max_idv/$idvDepreciation);
                            $minimumprice = ($min_idv/$idvDepreciation);

                        }


                        if ($max_idv != "" && $requestData->edit_idv >= ($max_idv))
                        {

                            $model_config_premium['ExShowRoomPrice'] = ($maximumprice);
                            $idv = ($max_idv);

                        }
                        elseif ($min_idv != "" && $requestData->edit_idv <= ($min_idv))
                        {

                            $model_config_premium['ExShowRoomPrice'] = ($minimumprice);
                            $idv = ($min_idv);
                        }
                        else
                        {
                            $model_config_premium['ExShowRoomPrice'] = ($requestData->edit_idv / $idvDepreciation);
                            $idv = $requestData->edit_idv;
                        }

                        if ($premium_type == 'third_party')
                        {
                            $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR_TP');
                        }
                        else
                        {

                            $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR');
                        }

                        $additionPremData = [
                            'requestMethod' => 'post',
                            'type' => 'premiumRecalculation',
                            'section' => 'car',
                            'productName'  => $productData->product_name,
                            'token' => $access_token,
                            'checksum' => $checksum_data,
                            'enquiryId' => $enquiryId,
                            'transaction_type' => 'quote'
                        ];

                        if($IsPos == 'Y')
                        {
                            if(isset($model_config_premium['DealId']))
                            {
                                unset($model_config_premium['DealId']);
                            }
                        }
                        else
                        {
                            if(!isset($model_config_premium['DealId']))
                            {
                               $model_config_premium['DealId'] = $deal_id;
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
                            $additionPremData = array_merge($additionPremData,$pos_details);
                        }

                        #for removing correlationId
                        unset($model_config_premium['CorrelationId']);      

                        $checksum_data = checksum_encrypt($model_config_premium);
                        $additionPremData['checksum'] = $checksum_data;

                        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'icici_lombard',$checksum_data,'CAR');
                        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                        {
                            $data = $is_data_exits_for_checksum;
                        }else{
                            $model_config_premium['CorrelationId'] = $corelationId;
                            // $model_config_premium['PACoverTenure'] = '1';
                            $model_config_premium['PACoverTenure'] = isset($cpa_tenure) ? $cpa_tenure : '1';
                            $data = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
                        }
                        // if($requestData->business_type == 'newbusiness'){
                        //     $model_config_premium['PACoverTenure'] = '3';
                        //     unset($model_config_premium['CorrelationId']);   #for removing correlationId
                            
                        //     #for appliying checksum for plans as well
                        //     $checksum_data = checksum_encrypt($model_config_premium);
                        //     $additionPremData['checksum'] = $checksum_data;
                        //     $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'icici_lombard', $checksum_data, 'CAR');

                        //     if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {

                        //         $get_response_cpa = $is_data_exits_for_checksum;
                        //     }else{
                        //         $model_config_premium['CorrelationId'] = $corelationId;
                        //         $additionPremData['productName'] = $productData->product_name." CPA 3 Year";
                        //         $get_response_cpa = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
                        //     }
                        //         $cpa_multiyear = json_decode($get_response_cpa['response'], true);

                        //         if (isset($cpa_multiyear['status']) && strtolower($cpa_multiyear['status']) == 'success') {
                        //             update_quote_web_servicerequestresponse($get_response_cpa['table'], $get_response_cpa['webservice_id'], "Premium ReCalculation Success", "Success");
                        //         }
                        // }
                        if ($data['response'])
                        {
                            $arr_premium = json_decode($data['response'], true);
                           if(isset($arr_premium['status']) && strtolower($arr_premium['status']) == 'success')
                           {
                            update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success" );
                            if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true))
                            {
                                $msg = isset($arr_premium['deviationMessage']) ? $arr_premium['deviationMessage'] : 'Ex-Showroom price provided is not under permissable limits';
                                 return [
                                   'webservice_id' => $data['webservice_id'],
                                   'table' => $data['table'],
                                   'status' => false,
                                   'message' => $msg
                                ];

                            }


                            if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true))
                            {
                                $msg = "Proposal application didn't pass underwriter approval";
                                return [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'status' => false,
                                    'message' => $msg
                                ];
                            }
                            $idv = ($arr_premium['generalInformation']['depriciatedIDV']);

                            }
                            else
                            {
                                return [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                       'status' => false,
                                       'message' => isset($arr_premium['message']) ? $arr_premium['message'] : 'Insurer not reachable'

                                    ];

                            }
                        } else {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Insurer not reachable'
                            ];
                        }
                    }
               }
               else
               {
                 $idv = $min_idv = $max_idv = 0;
               }


                $od_premium = 0;
                $breakingLoadingAmt =0;
                $automobile_assoc = 0;
                $anti_theft = 0;
                $voluntary_deductible = 0;
                $elect_acc = 0;
                $non_elec_acc = 0;
                $lpg_cng_od = 0;
                $lpg_cng_tp = 0;
                $tp_premium = 0;
                $llpd_amt = 0;
                $ncb_discount = 0;
                $unnamed_pa_amt = 0;
                $zero_dept = 0;
                $tppd_discount = 0;
                $llpdemp_amt = 0;
                $rsa = $zero_dept = $eng_protect = $key_replace = $consumable_cover = $return_to_invoice = $loss_belongings = $cpa_cover = $tyreSecure = 0 ;
                $geog_Extension_OD_Premium = 0;
                $geog_Extension_TP_Premium = $emeCover = 0;

                $geog_Extension_OD_Premium = isset($arr_premium['riskDetails']['geographicalExtensionOD'])  ? ($arr_premium['riskDetails']['geographicalExtensionOD']) : '0';
                $geog_Extension_TP_Premium = isset($arr_premium['riskDetails']['geographicalExtensionTP'])  ? ($arr_premium['riskDetails']['geographicalExtensionTP']) : '0';
                $od_premium = isset($arr_premium['riskDetails']['basicOD'])  ? ($arr_premium['riskDetails']['basicOD']) : '0';
                $breakingLoadingAmt = isset($arr_premium['riskDetails']['breakinLoadingAmount']) ? $arr_premium['riskDetails']['breakinLoadingAmount'] : '0'; # As per git 23963
                $automobile_assoc = isset($arr_premium['riskDetails']['automobileAssociationDiscount']) ? ($arr_premium['riskDetails']['automobileAssociationDiscount']) : '0';
                $anti_theft =  isset($arr_premium['riskDetails']['antiTheftDiscount']) ? ($arr_premium['riskDetails']['antiTheftDiscount']) : '0';
                $elect_acc = isset($arr_premium['riskDetails']['electricalAccessories']) ? ($arr_premium['riskDetails']['electricalAccessories']) : '0';
                $non_elec_acc = isset($arr_premium['riskDetails']['nonElectricalAccessories']) ? ($arr_premium['riskDetails']['nonElectricalAccessories']) : '0';
                $lpg_cng_od = isset($arr_premium['riskDetails']['biFuelKitOD']) ? ($arr_premium['riskDetails']['biFuelKitOD']) : '0';
                $ncb_discount = isset($arr_premium['riskDetails']['bonusDiscount']) ? $arr_premium['riskDetails']['bonusDiscount'] : '0';
                $tppd_discount = isset($arr_premium['riskDetails']['tppD_Discount']) ? $arr_premium['riskDetails']['tppD_Discount'] : '0';


                $tp_premium = ($arr_premium['riskDetails']['basicTP']);
                $lpg_cng_tp = isset($arr_premium['riskDetails']['biFuelKitTP']) ? ($arr_premium['riskDetails']['biFuelKitTP']) : '0';
                $llpd_amt = isset($arr_premium['riskDetails']['paidDriver']) ? ($arr_premium['riskDetails']['paidDriver']) : 0 ;
                $unnamed_pa_amt = isset($arr_premium['riskDetails']['paCoverForUnNamedPassenger']) ? $arr_premium['riskDetails']['paCoverForUnNamedPassenger'] : '0';
                // $rsa = isset($arr_premium['riskDetails']['roadSideAssistance']) ? $arr_premium['riskDetails']['roadSideAssistance'] : '0';
                $rsa = isset($arr_premium['riskDetails']['smartAssist']) ? $arr_premium['riskDetails']['smartAssist'] : '0';
                $zero_dept = isset($arr_premium['riskDetails']['zeroDepreciation']) ? $arr_premium['riskDetails']['zeroDepreciation'] : '0';
                $eng_protect = isset($arr_premium['riskDetails']['engineProtect']) ? $arr_premium['riskDetails']['engineProtect'] : '0';
                $key_replace = isset($arr_premium['riskDetails']['keyProtect']) ? $arr_premium['riskDetails']['keyProtect'] : '0';
                $consumable_cover = isset($arr_premium['riskDetails']['consumables']) ? $arr_premium['riskDetails']['consumables'] : '0';
                $return_to_invoice = isset($arr_premium['riskDetails']['returnToInvoice']) ? $arr_premium['riskDetails']['returnToInvoice'] : '0';
                $loss_belongings = isset($arr_premium['riskDetails']['lossOfPersonalBelongings']) ? $arr_premium['riskDetails']['lossOfPersonalBelongings'] : '0';
                $cpa_cover = isset($arr_premium['riskDetails']['paCoverForOwnerDriver']) ? $arr_premium['riskDetails']['paCoverForOwnerDriver'] : '0';
                $tyreSecure = $arr_premium['riskDetails']['tyreProtect'] ??  0;
                $emeCover = $arr_premium['riskDetails']['emeCover'] ??  0;
                $llpdemp_amt = $arr_premium['riskDetails']['employeesOfInsured'] ?? 0;


                

                if(isset($arr_premium['riskDetails']['voluntaryDiscount']))
                {
                    $voluntary_deductible = $arr_premium['riskDetails']['voluntaryDiscount'];
                }
                else
                {
                    $voluntary_deductible = voluntary_deductible_calculation($od_premium,$requestData->voluntary_excess_value,'car');

                }
                if(isset($arr_premium['payuDetails']['discount']))
                {
                    $gdd_discount=$arr_premium['payuDetails']['discount'];
                }
                else
                {
                    $gdd_discount=0;
                }



                /* if ($productData->zero_dep == 0)
                {
                    if(trim($mmv_data->car_segment) == 'Premium Cars C' && $car_age <= 3)
                    {
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'keyReplace'                => (int)$key_replace,
                                'lopb'                      => (int)$loss_belongings,
                            ],
                            'additional' => [
                                'roadSideAssistance'        => (int)$rsa,

                                'engineProtector'            => (int)$eng_protect,
                                'ncbProtection'              => 0,
                                'consumables'                  => (int)$consumable_cover,
                                'tyreSecure'                 => $tyreSecure,
                                'returnToInvoice'           => (int)$return_to_invoice,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                        ];

                    }
                    elseif($car_age <= 5)
                    {
                        $add_ons_data = [
                                'in_built'   => [
                                    'zeroDepreciation'          => (int)$zero_dept,
                                ],
                                'additional' => [
                                    'roadSideAssistance'        => (int)$rsa,
                                    'engineProtector'           => (int)$eng_protect,
                                    'ncbProtection'             => 0,
                                    'keyReplace'                => (int)$key_replace,
                                    'consumables'               => (int)$consumable_cover,
                                    'tyreSecure'                => $tyreSecure,
                                    'returnToInvoice'           => (int)$return_to_invoice,
                                    'lopb'                      => (int)$loss_belongings,
                                    //'cpa_cover'                   => $cpa_cover,
                                ],
                                'other'      => [],
                        ];
                    }
                    elseif($car_age <= 8)
                    {
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'roadSideAssistance'        => (int)$rsa,
                                'consumables'               => (int)$consumable_cover,
                            ],
                            'additional' => [
                                'engineProtector'            => 0,
                                'ncbProtection'              => 0,
                                'keyReplace'                 => 0,
                                'tyreSecure'                 => $tyreSecure,
                                'returnToInvoice'            => 0,
                                'lopb' => 0,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                        ];
                    }
                    else
                    {
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                            ],
                            'additional' => [
                                'roadSideAssistance'        => (int)$rsa,
                                'engineProtector'           => (int)$eng_protect,
                                'ncbProtection'             => 0,
                                'keyReplace'                => (int)$key_replace,
                                'consumables'               => (int)$consumable_cover,
                                'tyreSecure'                => $tyreSecure,
                                'returnToInvoice'           => (int)$return_to_invoice,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                        ];
                    }
                }
                else
                {
                    $add_ons_data = [
                        'in_built'   => [],
                        'additional' => [
                            'roadSideAssistance'        => (int)$rsa,
                            'zeroDepreciation'          => 0,
                            'engineProtector'           => (int)$eng_protect,
                            'ncbProtection'             => 0,
                            'keyReplace'                => (int)$key_replace,
                            'consumables'               => (int)$consumable_cover,
                            'tyreSecure'                => $tyreSecure,
                            'returnToInvoice'           => (int)$return_to_invoice,
                            'lopb'                      => (int)$loss_belongings,
                            //'cpa_cover'                   => $cpa_cover,
                        ],
                        'other'      => [],
                    ];
                } */
                //validation for return_to_invoice
                if(($return_to_invoice == '0' || empty($return_to_invoice) || $return_to_invoice == 'NULL' || $return_to_invoice == '') && (in_array($productData->product_identifier, ["platinum_with_rti","silver_with_rti", "gold_with_rti", "gold_plus_with_rti"]))) {
                    return [
                        'value' => $return_to_invoice,
                        'message' => "Premium is not available for Return to Invoice ",
                        'status' => false
                    ];
                }
                switch ($productData->product_identifier) {
                    case 'silver':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'roadSideAssistance'        => (int)$rsa,
                            ],
                            'additional' => [
                                'engineProtector'           => (int)$eng_protect,
                                'ncbProtection'             => 0,
                                'tyreSecure'                => $tyreSecure,
                                'returnToInvoice'           => (int)$return_to_invoice,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                       ];
                        break;
                    case 'silver_with_rti':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'roadSideAssistance'        => (int)$rsa,
                                'returnToInvoice'           => (int)$return_to_invoice,
                            ],
                            'additional' => [
                                'engineProtector'           => (int)$eng_protect,
                                'ncbProtection'             => 0,
                                'tyreSecure'                => $tyreSecure,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                        ];
                        break;
                    case 'gold':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'tyreSecure'                => (int)$tyreSecure,
                                'roadSideAssistance'        => (int)$rsa,
                            ],
                            'additional' => [
                                'engineProtector'           => (int)$eng_protect,
                                'ncbProtection'             => 0,
                                'returnToInvoice'           => (int)$return_to_invoice,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                       ];
                        break;
                    case 'gold_with_rti':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'tyreSecure'                => (int)$tyreSecure,
                                'roadSideAssistance'        => (int)$rsa,
                                'returnToInvoice'           => (int)$return_to_invoice
                            ],
                            'additional' => [
                                'engineProtector'           => (int)$eng_protect,
                                'ncbProtection'             => 0,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                        ];
                        break;
                    case 'gold_plus':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'roadSideAssistance'        => (int)$rsa,
                                'engineProtector'           => (int)$eng_protect,
                            ],
                            'additional' => [
                                'tyreSecure'                => (int)$tyreSecure,
                                'ncbProtection'             => 0,
                                'returnToInvoice'           => (int)$return_to_invoice,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                       ];
                        break;
                    case 'gold_plus_with_rti':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'roadSideAssistance'        => (int)$rsa,
                                'engineProtector'           => (int)$eng_protect,
                                'returnToInvoice'           => (int)$return_to_invoice
                            ],
                            'additional' => [
                                'tyreSecure'                => (int)$tyreSecure,
                                'ncbProtection'             => 0,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                        ];
                        break;
                    case 'platinum':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'tyreSecure'                => (int)$tyreSecure,
                                'roadSideAssistance'        => (int)$rsa,
                                'engineProtector'           => (int)$eng_protect,
                            ],
                            'additional' => [
                                'ncbProtection'             => 0,
                                'returnToInvoice'           => (int)$return_to_invoice,
                                'lopb'                      => (int)$loss_belongings,
                                //'cpa_cover'                   => $cpa_cover,
                            ],
                            'other'      => [],
                       ];
                        break;
                    case 'premium_segment':
                    break;
                    case 'platinum_with_rti':
                        $add_ons_data = [
                            'in_built'   => [
                                'zeroDepreciation'          => (int)$zero_dept,
                                'consumables'               => (int)$consumable_cover,
                                'keyReplace'                => (int)$key_replace,
                                'tyreSecure'                => (int)$tyreSecure,
                                'roadSideAssistance'        => (int)$rsa,
                                'engineProtector'           => (int)$eng_protect,
                                'returnToInvoice'           => (int)$return_to_invoice,
                            ],
                            'additional' => [
                                // 'ncbProtection'             => 0,
                                'lopb'                      => (int)$loss_belongings,
                            ],
                            'other'      => [],
                        ];
                        break;
                    default:
                        if ($requestData->business_type == 'newbusiness' && $productData->product_identifier == 'zero_dep') {
                            $add_ons_data = [
                                'in_built'   => [
                                    'zeroDepreciation'          => (int)$zero_dept,
                                    'consumables'               => (int)$consumable_cover,
                                ],
                                'additional' => [
                                    'keyReplace'                => (int)$key_replace,
                                    'tyreSecure'                => (int)$tyreSecure,
                                    'roadSideAssistance'        => (int)$rsa,
                                    'engineProtector'           => (int)$eng_protect,
                                    'ncbProtection'             => 0,
                                    'returnToInvoice'           => (int)$return_to_invoice,
                                    'lopb'                      => (int)$loss_belongings,
                                    //'cpa_cover'                   => $cpa_cover,
                                ],
                                'other'      => [],
                           ];
                        }
                        else if ($productData->product_identifier == 'BASIC'){
                            $add_ons_data = [
                                'in_built'   => [],
                                'additional' => [],
                                'other'      => [],
                           ];
                        }else{
                            $add_ons_data = [
                                'in_built'   => [
                                ],
                                'additional' => [
                                    'consumables'               => (int)$consumable_cover,
                                    'keyReplace'                => (int)$key_replace,
                                    'tyreSecure'                => (int)$tyreSecure,
                                    'roadSideAssistance'        => (int)$rsa,
                                    'engineProtector'           => (int)$eng_protect,
                                    'ncbProtection'             => 0,
                                    'returnToInvoice'           => (int)$return_to_invoice,
                                    'lopb'                      => (int)$loss_belongings,
                                    //'cpa_cover'                   => $cpa_cover,
                                ],
                                'other'      => [],
                           ];
                        }
                        break;
                }
                if($emeCover != 0)
                {
                    $add_ons_data['additional']['emergencyMedicalExpenses'] = $emeCover;
                }
                if(in_array($productData->product_identifier, ['silver','gold','gold_plus','platinum','platinum_with_rti', "silver_with_rti", "gold_with_rti", "gold_plus_with_rti"]) && (in_array($mmv_data->manufacturer_name, ['MARUTI'])) /*&&  (in_array($premium_type,['own_damage', 'own_damage_breakin'])) refer git:https://github.com/Fyntune/motor_2.0_backend/issues/35552#issuecomment-3043687776*/) {
                    unset($add_ons_data['in_built']['keyReplace']);
                    $add_ons_data['additional']['keyReplace'] = (int)$key_replace;
                }

                // if(in_array($productData->product_identifier, ['silver']) && in_array($mmv_data->manufacturer_name, ['TOYOTA', 'FORD','NISSAN']) || (stripos($mmv_data->manufacturer_name, 'RENAULT') !== false))
                // {
                //     unset($add_ons_data['in_built']['keyReplace']);
                //     unset($add_ons_data['in_built']['roadSideAssistance']);
                //     $add_ons_data['additional']['keyReplace'] = (int)$key_replace;
                //     $add_ons_data['additional']['roadSideAssistance'] = (int)$rsa;   //refer git:https://github.com/Fyntune/motor_2.0_backend/issues/35552#issuecomment-3043687776
                // }

                $add_ons_data['additional']['lopb'] = (int)$loss_belongings ;
                if(trim($mmv_data->car_segment) == 'Premium Cars C' && $car_age <=10)
                {
                    $add_ons_data['in_built']['lopb'] = (int)$loss_belongings ;
                    unset($add_ons_data['additional']['lopb']);
                }

                if (in_array(0, array_values($add_ons_data['in_built']))) {
                    foreach ($add_ons_data['in_built'] as $k => $v) {
                        if ($v == 0) {
                            unset($add_ons_data['in_built'][$k]);
                        }
                    }
                }
                if($productData->zero_dep == 0 && $RSAPlanName == 'RSA-including Key Protect')
                {
                    $add_ons_data['in_built']['keyReplace'] = 0;
                    unset($add_ons_data['additional']['keyReplace']);
                }
                if($premium_type != 'third_party')
                {

                 $applicable_addons = [
                            'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'lopb','engineProtector','consumables','returnToInvoice','tyreSecure','emergencyMedicalExpenses'
                        ];
                }
                else
                {
                     $applicable_addons = [];
                }

                $total_od = $od_premium + $elect_acc + $non_elec_acc + $lpg_cng_od + $geog_Extension_OD_Premium;#breaking loading amount remove from here
                $total_tp = $tp_premium + $llpd_amt + $unnamed_pa_amt + $lpg_cng_tp + $llpdemp_amt + $geog_Extension_TP_Premium;
                $total_discount = $ncb_discount + $automobile_assoc + $anti_theft + $voluntary_deductible + $tppd_discount;
                $total_discount=$total_discount+$gdd_discount;
                $basePremium = $total_od + $total_tp - $total_discount;

                $totalTax = $basePremium * 0.18;

                $final_premium = $basePremium + $totalTax;


                $selected_addons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
                $selected_addons_data['additional_premium'] = array_sum($add_ons_data['additional']);


                /* if($key_replace == '0')
                {
                   array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                   unset($add_ons_data['in_built']['keyReplace']);
                } */
                if($loss_belongings == '0')
                {
                    array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                    unset($add_ons_data['in_built']['lopb']);
                }

                if($eng_protect == '0')
                {
                    array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                }
                if($return_to_invoice == '0')
                {
                    array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                }
                if($consumable_cover == '0')
                {
                    array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                    unset($add_ons_data['in_built']['consumables']);
                }
                if($rsa == '0')
                {
                    array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                    unset($add_ons_data['in_built']['roadSideAssistance']);
                }
                if($zero_dept == '0')
                {
                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                    unset($add_ons_data['in_built']['zeroDepreciation']);
                }
                if($car_age > 5)
                {
                    if(in_array('zeroDepreciation',$applicable_addons))
                    {
                        unset($applicable_addons['zeroDepreciation']);
                    }
                }
                if($tyreSecure == 0)
                {
                    array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                }
                if($emeCover == 0)
                {
                    array_splice($applicable_addons, array_search('emergencyMedicalExpenses', $applicable_addons), 1);
                }




                $business_type = '';
                switch ($requestData->business_type)
                {
                    case 'newbusiness':
                        $business_type = 'New Business';
                    break;
                    case 'rollover':
                        $business_type = 'Roll Over';
                    break;

                    case 'breakin':
                        $business_type = 'Breakin';
                        if(($requestData->previous_policy_type == 'Third-party' && $premium_type == 'third_party'))
                        {
                            $business_type = 'Roll Over';
                        }
                    break;

                }

                if($zero_dept <= 0 && $productData->zero_dep == 0 && config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CAR_PLAN_ZERO_DEP_RESTRICTION') == 'Y')
                {
                    return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => 'Zero Dep is not allowed for Given Variant',
                            'request' => [
                                'message' => 'Zero Dep is not allowed for Given Variant',
                                'car_age' => $car_age,
                                'car_segment' => $mmv_data->car_segment,
                            ]
                        ];

                }

                $data_response =
                    [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status' => true,
                    'msg' => 'Found',
                    'Data' => [
                        'idv' => $premium_type == 'third_party' ? 0 : ($idv),
                        'vehicle_idv' => $idv,
                        'min_idv' => $min_idv,
                        'max_idv' => $max_idv,
                        'rto_decline' => NULL,
                        'rto_decline_number' => NULL,
                        'mmv_decline' => NULL,
                        'mmv_decline_name' => NULL,
                        'policy_type' => $policy_type,
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'rto_no' => $requestData->rto_code,
                        'version_id' => $mmv_data->ic_version_code,
                        'showroom_price' => $model_config_premium['ExShowRoomPrice'],
                        'fuel_type' => $requestData->fuel_type,
                        'ncb_discount' => $applicable_ncb_rate,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_name' => $productData->product_sub_type_name . ' - ' . ucwords(str_replace('_', ' ', strtolower($productData->product_identifier))),
                        'mmv_detail' => $mmv_data,
                        'master_policy_id' => [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $productData->policy_no,
                            'policy_start_date' => date('d-m-Y',strtotime($PolicyStartDate)),
                            'policy_end_date' =>   date('d-m-Y',strtotime($PolicyEndDate)),
                            'sum_insured' => $productData->sum_insured,
                            'corp_client_id' => $productData->corp_client_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'insurance_company_id' => $productData->company_id,
                            'status' => $productData->status,
                            'corp_name' => '',
                            'company_name' => $productData->company_name,
                            'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online
                        ],
                        'motor_manf_date' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'vehicleDiscountValues' => [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => 0,
                            'rto_cluster_id' => 0,
                            'car_age' => $car_age,
                            'aai_discount' => 0,
                            'ic_vehicle_discount' =>  0,
                        ],
                        'basic_premium' => ($od_premium),
                        'deduction_of_ncb' => ($ncb_discount),
                        'voluntary_excess' => $voluntary_deductible,
                        'tppd_premium_amount' => ($tp_premium),
                        'tppd_discount' => ($tppd_discount),
                        // 'total_loading_amount' => $breakingLoadingAmt, 
                        'total_loading_amount' => 0, //As per the new ICICI guidelines, we are not required to calculate the loading amount . 
                        'motor_electric_accessories_value' =>($elect_acc),
                        'motor_non_electric_accessories_value' => ($non_elec_acc),
                        /* 'motor_lpg_cng_kit_value' => ($lpg_cng_od), */
                        'cover_unnamed_passenger_value' => ($unnamed_pa_amt),
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'default_paid_driver' => ($llpd_amt),
                        'motor_additional_paid_driver' => 0,
                        'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                        'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                        'compulsory_pa_own_driver' => $cpa_cover,
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' =>  ($total_od),
                        /* 'cng_lpg_tp' => $lpg_cng_tp, */
                        'total_liability_premium' => ($total_tp),
                        'net_premium' => ($basePremium),
                        'service_tax_amount' => 0,
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'quotation_no' => '',
                        'premium_amount' => ($final_premium),
                        'antitheft_discount' => $anti_theft,
                        'final_od_premium' => ($total_od),
                        'final_tp_premium' => ($total_tp),
                        'final_total_discount' => ($total_discount),
                        'final_net_premium' => ($final_premium),
                        'final_payable_amount' => ($final_premium),
                        'service_data_responseerr_msg' => 'true',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => $business_type,
                        'service_err_code' => NULL,
                        'service_err_msg' => NULL,
                        'policyStartDate' => ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y',strtotime($PolicyStartDate)),
                        'policyEndDate' => date('d-m-Y',strtotime($PolicyEndDate)),
                        'ic_of' => $productData->company_id,
                        'ic_vehicle_discount' => 0,
                        'vehicle_in_90_days' => 0,
                        'get_policy_expiry_date' => NULL,
                        'get_changed_discount_quoteid' => 0,
                        'vehicle_discount_detail' => [
                            'discount_id' => NULL,
                            'discount_rate' => NULL
                        ],
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                        'policy_id' => $productData->policy_id,
                        'insurane_company_id' => $productData->company_id,
                        "max_addons_selection" => NULL,
                        'add_ons_data' => $add_ons_data,
                        'applicable_addons' => $applicable_addons,
                        'isInspectionApplicable' => $isInspectionApplicable,
                        'company_alias' => $productData->company_alias,
                    ]
                ];
                if (!empty($llpdemp_amt) && $requestData->vehicle_owner_type == 'C') 
                {
                    $data_response['Data']['other_covers']['LegalLiabilityToEmployee'] = $llpdemp_amt;
                    $data_response['Data']['LegalLiabilityToEmployee'] = $llpdemp_amt;
                }
                if(isset($cpa_tenure) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '3'){
                    $data_response['Data']['multi_year_cpa'] = $cpa_cover;
                }
                if(!empty($arr_premium['payuDetails']) && $isGddEnabled)
                {
                    $data_response['Data']['gdd'] ="Y";
                    $data_response['Data']['PayAsYouDrive'] = $payud_Arr;
                    $data_response['Data']['PayAsYouDrive'][$selectedPayud]['Premium']=$gdd_discount;
                    $data_response['Data']['PayAsYouDrive'][$selectedPayud]['isOptedByCustomer']=true;

                }

                if($isInspectionWaivedOff) {
                    $data_response['Data']['isInspectionWaivedOff'] = true;
                    $data_response['Data']['waiverExpiry'] = $waiverExpiry;
                    $data_response['Data']['ribbon'] =  $ribbonMessage;
                }
                 if($bifuel || $IsVehicleHaveLPG)
                 {
                    $data_response['Data']['motor_lpg_cng_kit_value'] = ($lpg_cng_od);
                    $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                 }
                return camelCase($data_response);

            }
            else
            {
                return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                       'status' => false,
                       'message' => isset($arr_premium['message']) ? $arr_premium['message'] : ''

                    ];

            }

        } else {
            return [
                'status' => false,
                'message' => "Issue in premium calculation service"
            ];
        }
    } else {
        return [
            'status' => false,
            'message' => "Issue in Token Generation service"
        ];
    }

    }
    catch (Exception $e)
    {
         return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Car Insurer Not found' . $e->getMessage() . ' line ' . $e->getLine()
        ];
    }
}
