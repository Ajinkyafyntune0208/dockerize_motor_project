<?php

use Carbon\Carbon;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MasterProduct;
use App\Models\IciciRtoMaster;
use App\Models\SelectedAddons;
use App\Models\IcVersionMapping;
use App\Models\MasterPremiumType;
use App\Models\MotorModelVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\IciciRtoLocationMaster;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Quotes\Bike\Multiyear\iciciLombardMultiyear;



include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if(in_array($productData->tenure , [22,33,02,03]))
    {   
        $a = new iciciLombardMultiyear();
        return $a->getQuote($enquiryId, $requestData, $productData);
    }
    try {


    $premium_type_array = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->select('premium_type_code','premium_type')
        ->first();
    $premium_type = $premium_type_array->premium_type_code;
    $policy_type = $premium_type_array->premium_type;

    $isInspectionApplicable = 'N';
    $ribbonMessage = null;

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
            'request'=> $mmv
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    }

    if($mmv_data->cubiccapacity >= 350 && config('constants.motorConstant.IS_ICICI_CUBIC_CAPACITY_CONDITION_ALLOWED') == 'Y' )
     {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'vehicles above 350 CC is not allowed.',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
     }
     
     # for breakin
     if (!(in_array($premium_type, ['third_party', 'third_party_breakin']))) {
        if ($requestData->business_type == 'breakin' && ($mmv_data->cubiccapacity <= 350)) {
            $isInspectionApplicable = 'N';
        }
        elseif($requestData->business_type == 'breakin')
        {
            $isInspectionApplicable = 'Y';
            $ribbonMessage = 'Inspection Required';  
        }
    }

    // vehicle age calculation
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $bike_age = ceil($age / 12);
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    // if (($interval->y >= 15) && ($tp_check == 'true')){
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
    //     ];
    // }


    if (($bike_age > 5) && ($productData->zero_dep == '0') && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC'))))
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request'=> [
                'bike_age'=>$bike_age,
                'product_Data'=>$productData->zero_dep
            ]
        ];
    }




    // check for rto location



    $master_rto = MasterRto::where('rto_code', $requestData->rto_code)->first();
    if (empty($master_rto->icici_2w_location_code))
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
    $rto_cities = explode('/',  $rto_cities->rto_name);
    foreach($rto_cities as $rto_city)
    {
        $rto_city = strtoupper($rto_city);
        $rto_data = DB::table('bike_icici_lombard_rto_location')
                    ->where('txt_rto_location_desc', $state_name ."-". $rto_city)
                    ->first();
        if($rto_data)
        {

            break;
        }
    } */

    $rto_data = DB::table('bike_icici_lombard_rto_location')
                    ->where('txt_rto_location_code', $master_rto->icici_2w_location_code)
                    ->first();

    if (empty($rto_data))
    {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $requestData->rto_code.' RTO Location Not Found',
            'request'=> [
                'rto_data' => $requestData->rto_code
            ]
        ];
    }
    else
    {
       $txt_rto_location_code = $rto_data->txt_rto_location_code;
    }

    $mmv_data->manf_name = $mmv_data->manufacturer_name;
    $mmv_data->version_name = $mmv_data->vehiclemodel;
    $mmv_data->model_name = $mmv_data->vehiclemodel;
    $mmv_data->kw = $mmv_data->cubiccapacity;
    $mmv_data->seatingcapacity = $mmv_data->seatingcapacity;
    $mmv_data->seatingCapacity = $mmv_data->seatingcapacity;
    // token Generation

    $additionData = [
        'requestMethod' => 'post',
        'type' => 'tokenGeneration',
        'section' => 'bike',
        'productName'  => $productData->product_name,
        'enquiryId' => $enquiryId,
        'transaction_type' => 'quote'
    ];

    $tokenParam = [
        'grant_type' => 'password',
        'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
        'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
        'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
        'scope' => 'esbmotor',
    ];


    // If token API is not working then don't store it in cache - @Amit - 07-10-2022
    $token_cache_name = 'constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE' . $enquiryId;
    $token_cache = Cache::get($token_cache_name);
    if(empty($token_cache)) {
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token_decoded = json_decode($get_response['response'], true);
        if(isset($token_decoded['access_token'])) {
            $token = cache()->remember($token_cache_name, 60 , function () use ($get_response) {
                return $get_response;
            });
            $token = $token['response'];
        } else {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => "Insurer not reachable,Issue in Token Generation service"
            ];
        }
    } else {
        $token = $token_cache['response'];
    }
    // $get_response = cache()->remember('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE', 60 * 45 , function() use ($additionData, $tokenParam){
    //     return $token = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
    // });

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
                'webservice_id'=> $get_response['webservice_id'] ?? $token_cache["webservice_id"],
                'table'=> $get_response['table'] ?? $token_cache["table"],
                'status' => false,
                'message' => "Insurer not reachable,Issue in Token Generation service",
            ];
        }

        $corelationId = getUUID();
        $IsLLPaidDriver = false;
        $IsPAToUnnamedPassengerCovered = false;
        $PAToUnNamedPassenger_IsChecked = false;
        $IsElectricalItemFitted = false;
        $IsNonElectricalItemFitted = false;
        $bifuel = false;
        $current_ncb_rate = 0;
        $applicable_ncb_rate = 0;

        if ($requestData->business_type == 'newbusiness')
        {
            $BusinessType = 'New Business';
            $PolicyStartDate = date('Y-m-d');
            $IsPreviousClaim = 'N';
            $od_term_type = '13';
            $cpa = '1';
            $od_text = 'od_one_three';
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
            if($breakin_days > 0)
            {
                $PolicyStartDate = date('Y-m-d', strtotime('+1 day', strtotime(date('d-m-Y'))));
            }

            if ($requestData->is_claim == 'N'  && $premium_type != 'third_party')
            {
                $applicable_ncb_rate = $requestData->applicable_ncb;
                $current_ncb_rate = $requestData->previous_ncb;
            }
            if($breakin_days > 90 ||  $premium_type == 'third_party')
            {
                $applicable_ncb_rate = 0;
                $current_ncb_rate = 0;
            }

            if($requestData->is_claim == 'Y'  && $premium_type != 'third_party') {
                $current_ncb_rate = $requestData->previous_ncb;
            }

            $IsPreviousClaim = ($requestData->is_claim == 'Y') ? 'Y' : 'N';

        }

        $tenure_year = ($premium_type == 'third_party' && $requestData->business_type == 'newbusiness') ? 5 : 1;
        $PolicyEndDate = date('Y-m-d', strtotime(date('Y-m-d', strtotime("+$tenure_year year -1 day", strtotime(strtr($PolicyStartDate, ['-' => '']))))));

        $first_reg_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));

        if ($requestData->previous_policy_expiry_date == '')
        {
            $prepolstartdate = '01/01/1900';
            $prepolicyenddate = '01/01/1900';
        }
        else
        {
            if($requestData->is_multi_year_policy == 'Y' && $premium_type != 'own_damage')
            {
                $prepolstartdate = date('Y-m-d', strtotime(date('Y-m-d', strtotime('-5 year +1 day', strtotime($requestData->previous_policy_expiry_date))))); 
            }
            else
            {
                $prepolstartdate = date('Y-m-d', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            
            $prepolicyenddate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
        }

        $IsConsumables = false;
        $IsRTIApplicableflag = false;
        $IsEngineProtectPlus = false;
        $LossOfPersonalBelongingPlanName = '';
        $KeyProtectPlan = '';
        $RSAPlanName = 'RSA-Plus';
        $tppd_limit = 0;             //valid upto 15 years
        if($bike_age <= 5)
        {
            $IsConsumables = true;
            $IsRTIApplicableflag = true;
            $IsEngineProtectPlus = true;
            $LossOfPersonalBelongingPlanName = 'PLAN A';
            $KeyProtectPlan      = 'KP1';
        }
        if($productData->zero_dep == 0)
        {
            // if($requestData->business_type != 'newbusiness')
            // {
            //     if($breakin_days > 90)
            //     {
            //         return [
            //             'webservice_id' => $token_cache['webservice_id'],
            //             'table' => $token_cache['table'],
            //             'premium_amount' => 0,
            //             'status'         => false,
            //             'message'        => 'Zero Depreciation Plan is Not Allowed if policy expired is greater than 3 months.',
            //             'request'=>[
            //                 'product_data'=>$productData->zero_dep,
            //                 'policy_expired_days'=> $breakin_days
            //             ]
            //         ];
            //     }

            // }

            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver TW' : 'Silver';
            if($bike_age <= 8)
            {
                $IsConsumables = true;
            }

            if($bike_age > 5 && $bike_age <= 8)
            {
                $RSAPlanName = 'RSA-With Key Protect';
            }
        }
        else
        {
            $ZeroDepPlanName = '';
        }
        $EMECover_Plane_name = '';
        if($bike_age <= 15 && !(in_array($premium_type,['third_party', 'third_party_breakin'])))
        {
            $EMECover_Plane_name = (env('APP_ENV') == 'local') ? 'Premium Segment' : '';
        }


        if ($requestData->vehicle_owner_type == 'I')
        {
            $customertype = 'INDIVIDUAL';
            $ispacoverownerdriver = true;
        }
        else
        {
           // $customertype = 'CORPORATE';
            $customertype = '';
            $ispacoverownerdriver = false;
        }


        $ElectricalItemsTotalSI = $NonElectricalItemsTotalSI = $BiFuelKitSi = $PAToUnNamedPassengerSI = 0;

        $SIPACoverUnnamedPassenger = '0';
        $IsPACoverUnnamedPassenger = false;
        $llpd_flag = false;
        $voluntary_deductible_amount = 0;
        $geoExtension = false;
        $extensionCountryName = '';

        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

        if($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '')
        {
            $discounts_opted = $selected_addons->discounts;
            foreach ($discounts_opted as $value)
            {
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

        if($selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
        {
            $additional_covers = $selected_addons->additional_covers;

            foreach ($additional_covers as $value)
            {
                if($value['name'] == 'Unnamed Passenger PA Cover' && $premium_type != 'own_damage')
                {
                    $SIPACoverUnnamedPassenger = $value['sumInsured'];
                    $IsPACoverUnnamedPassenger = true;
                }
                if($value['name'] == 'LL paid driver' && $premium_type != 'own_damage')
                {
                    $llpd_flag = true;
                }
                if($value['name'] == 'Geographical Extension')
                {
                    $geoExtension = true;
                    $geoExtensionCountryName = array_filter($value['countries'], fn($country) => $country !== false);
                    $extensionCountryName = !empty($geoExtensionCountryName) ? implode(', ', $geoExtensionCountryName) : 'No Extension';
                }
            }
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
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '5';
            // $cpa_year = '1'; // By Default CPA will be 1 year
        } else {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '1';
        }

        $model_config_premium =
        [
            'RegistrationNumber'            => '',
            'EngineNumber'                  => '',
            'ChassisNumber'                 => '',
            'VehicleDescription'            => '',
            'VehicleModelCode'              => $mmv_data->vehiclemodelcode,
            'RTOLocationCode'               => $rto_data->txt_rto_location_code,
            'ManufacturingYear'             => date('Y', strtotime('01-' . $requestData->manufacture_year)),
            'ExShowRoomPrice'               => 0,
            'BusinessType'                  => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
            'VehicleMakeCode'               => $mmv_data->manufacturer_code,
            'Tenure'                        => '1',
            // 'PACoverTenure'                 => '1',
            'PACoverTenure'                 => isset($cpa_tenure) ? $cpa_tenure : '1',
            'IsLegaLiabilityToWorkmen'      => false,
            'NoOfWorkmen'                   => 0,
            'DeliveryOrRegistrationDate'    => date('Y-m-d', strtotime($vehicleDate)) ?? $first_reg_date,
            'FirstRegistrationDate'         => $first_reg_date,
            'PolicyStartDate'               => date('Y-m-d', strtotime($PolicyStartDate)),
            'PolicyEndDate'                 => $PolicyEndDate,
            'GSTToState'                    => $state_name,
            'IsPACoverOwnerDriver'          => $ispacoverownerdriver,
            'IsPACoverWaiver'               => 'false',
            'IsNoPrevInsurance'             => ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') ? true : false,
            'CustomerType'                  => $customertype,
            'IsAntiTheftDisc'               => false,
            'IsHandicapDisc'                => false,
            'IsExtensionCountry'            => $geoExtension,
            'ExtensionCountryName'          => $extensionCountryName,
            'IsMoreThanOneVehicle'          => false,
            'IsLegalLiabilityToPaidDriver'  => $llpd_flag,
            'IsLegalLiabilityToPaidEmployee'=> false,
            'NoOfEmployee'                  => 0,
            'IsVoluntaryDeductible'           => ($voluntary_deductible_amount != 0) ? false: false,
            'VoluntaryDeductiblePlanName'     => ($voluntary_deductible_amount != 0) ? 0 : 0,
            'IsTransferOfNCB'               => false,
            'NoOfDriver'                    => 1,
            'ZeroDepPlanName'               => $ZeroDepPlanName,
            'RSAPlanName'                   => ($premium_type == 'third_party') ? '' : 'TW-299',
            //'IsValidDrivingLicense'         => ($bike_data['premium_type'] =='O') ? true : 'false',
            'IsValidDrivingLicense'         => 'false',
            'IsAutomobileAssocnFlag'        => false,
            'IsRTIApplicableflag'           => false,
            'EMECover'                      => $EMECover_Plane_name,#eme cover
            'NoOfPassengerHC'               => $mmv_data->seatingcapacity - 1,
            'IsPACoverUnnamedPassenger'     => $IsPACoverUnnamedPassenger,
            'SIPACoverUnnamedPassenger'     => $SIPACoverUnnamedPassenger * ($mmv_data->seatingcapacity),
            'IsHaveElectricalAccessories'   => false,
            'SIHaveElectricalAccessories'   => 0,
            'IsHaveNonElectricalAccessories'=> false,
            'SIHaveNonElectricalAccessories'=> 0,
            'OtherLoading'                  => 0,
            // 'TPPDLimit'                     => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000, 
            'TPPDLimit'                     => 100000, // As per git id 15093
            'CorrelationId'                 => $corelationId,
        ];



        $IsPos = 'N';
        $is_icici_pos_disabled_renewbuy = config('constants.motorConstant.IS_ICICI_POS_DISABLED_RENEWBUY');
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
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
                    $ProductCode = '2312';
                break;
                case "own_damage":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
                    $ProductCode = '2312';

                break;
                case "third_party":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
                    $ProductCode = '2320';
                break;

            }

            if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
            {
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_BREAKIN');
                $ProductCode = '2312';
            }

            #for third party 
            if(($premium_type_array->premium_type_code ?? '') == 'third_party'){

                $ProductCode = '2320';
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
                $CertificateNumber = 'TMI0001';
                $PanCardNo = 'ABGTY8890Z';
                $AadhaarNo = '569278616999';
            }

            //$ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');

        }
        elseif($pos_testing_mode === 'Y')
        {
            $IsPos = 'Y';
            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
            $CertificateNumber = 'TMI0001';
            $PanCardNo = 'ABGTY8890Z';
            $AadhaarNo = '569278616999';
            //$ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
        }
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

        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')
        {
            $model_config_premium['PreviousPolicyDetails'] = [
                'previousPolicyStartDate' => $prepolstartdate,
                'previousPolicyEndDate'   => $prepolicyenddate,
                'ClaimOnPreviousPolicy'   => ($IsPreviousClaim == 'Y') ? true : false,
                'PreviousPolicyType'      => ($requestData->previous_policy_type == 'Third-party') ? 'TP': 'Comprehensive Package',
                'TotalNoOfODClaims'       => ($IsPreviousClaim == 'Y') ? '1' : '0',
                'BonusOnPreviousPolicy'   => $current_ncb_rate,
            ];
            $model_config_premium['TPTenure'] = 1;
        }
        else
        {
               $model_config_premium['Tenure'] = 1;
               $model_config_premium['TPTenure'] = 5;
               //$model_config_premium['PACoverTenure'] = 5;
            //    $model_config_premium['PACoverTenure'] = 1; // By default CPA tenure will be 1 Year
               $model_config_premium['PACoverTenure'] = isset($cpa_tenure) ? $cpa_tenure : '1'; // By default CPA tenure will be 1 Year
        }

        if($IsPreviousClaim == 'Y')
        {
            $model_config_premium['PreviousPolicyDetails']['NoOfClaimsOnPreviousPolicy'] = 1;
        }

        if($premium_type == 'own_damage')
        {
            $model_config_premium['TPStartDate'] = $prepolstartdate;
            $model_config_premium['TPEndDate'] = date('Y-m-d', strtotime('+5 year -1 day', strtotime($prepolstartdate)));
            $model_config_premium['TPInsurerName'] = 'GIC';#'BAJAJALLIANZ';
            $model_config_premium['TPPolicyNo'] = '123456789';
            $model_config_premium['Tenure']= 1;
            $model_config_premium['TPTenure']= 0;
            $model_config_premium['PreviousPolicyDetails']['PreviousPolicyType']= 'Bundled Package Policy';
            $model_config_premium['IsLegalLiabilityToPaidDriver']= false;
            $model_config_premium['IsPACoverOwnerDriver']= false;
            $model_config_premium['IsPACoverUnnamedPassenger']= false;
        }

        if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure')
        {
            unset($model_config_premium['PreviousPolicyDetails']);
        }

        $enable_idv_service = config('constants.ICICI_LOMBARD.ENABLE_ICICI_IDV_SERVICE');

        $idv = $max_idv =$min_idv = $minimumprice = $maximumprice = 0;
        if($premium_type != 'third_party' && $enable_idv_service == 'Y')
        {
            $access_token_for_idv = '';

            $tokenParam =
            [
                'grant_type' => 'password',
                'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
                'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
                'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
                'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
                'scope' => 'esbmotormodel',
            ];


            // $token = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
            $get_response = cache()->remember('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE', 60 , function() use ($additionData, $tokenParam){
                return $token = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
            });

            $token = $get_response['response'];
            if(!empty($token))
            {
                $token = json_decode($token, true);

                if(isset($token['access_token']))
                {
                    $access_token_for_idv= $token['access_token'];
                }
            }
            else
            {
                return
                [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status'=> false,
                    'message'=> 'No response received from IDV service Token Generation'
                ];
            }

           $idv_service_request =
           [
               'manufacturercode'=> $mmv_data->manufacturer_code,
               'BusinessType' => $BusinessType,
               'rtolocationcode' => $rto_data->txt_rto_location_code,
               'DeliveryOrRegistrationDate'=> date('Y-m-d', strtotime($vehicleDate)) ?? $first_reg_date,
               'PolicyStartDate'=> date('Y-m-d', strtotime($PolicyStartDate)),
               'DealID'=> $deal_id,
               'vehiclemodelcode' => $mmv_data->vehiclemodelcode,
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
           $checksum_data = checksum_encrypt($idv_service_request);
           $additionPremData = [
            'requestMethod' => 'post',
            'type' => 'idvService',
            'section' => 'bike',
            'productName'  => $productData->product_name,
            'token' => $access_token_for_idv,
            'enquiryId' => $enquiryId,
            'checksum'  => $checksum_data,
            'transaction_type' => 'quote',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
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
           
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'icici_lombard',$checksum_data,'BIKE');
            if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
            {
                $get_response = $is_data_exits_for_checksum;
            }
            else
            {
               $get_response = getWsData($url, $idv_service_request, 'icici_lombard', $additionPremData);
            }
           $data = $get_response['response'];

           if(!empty($data))
           {
              $idv_service_response = json_decode($data, true);
              if(isset($idv_service_response['status']) && $idv_service_response['status'] == true)
              {
                  $idvDepreciation = (1 - $idv_service_response['idvdepreciationpercent']);
                  if(isset($idv_service_response['maxidv']))
                  {
                     $max_idv = $idv_service_response['maxidv'];
                  }
                  if(isset($idv_service_response['minidv']))
                  {
                    $min_idv = $idv_service_response['minidv'];
                  }
                  if(isset($idv_service_response['minimumprice']))
                  {
                     $minimumprice = $idv_service_response['minimumprice'];
                  }
                  if(isset($idv_service_response['maximumprice']))
                  {
                    $maximumprice = $idv_service_response['maximumprice'];
                  }

                  $model_config_premium['ExShowRoomPrice'] = ($minimumprice);
              }
              else
              {
                 return
                 [
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'status'=> false,
                    'message'=> isset($idv_service_response['statusmessage']) ? $idv_service_response['statusmessage'] : 'Issue in IDV service'
                 ];
              }


           }
           else
           {
             return
             [
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
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


        if ($premium_type == 'third_party')
        {
            $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_BIKE_TP');
        }
        else
        {

            $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_BIKE');
        }


        $checksum_data = checksum_encrypt($model_config_premium);
        $additionPremData = [
            'requestMethod' => 'post',
            'type' => 'premiumCalculation',
            'section' => 'bike',
            'productName'  => $productData->product_name,
            'token' => $access_token,
            'checksum'  => $checksum_data,
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

        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'icici_lombard',$checksum_data,'BIKE');
        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
        {
            $get_response = $is_data_exits_for_checksum;
        }
        else
        {
            $get_response = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
        }

        $data = $get_response['response'];

        #offline idv calculation
        if($enable_idv_service != 'Y' && $requestData->is_idv_changed != 'Y' && $premium_type != 'third_party')
        {
            if($data)
            {
                $dataResponse = json_decode($data, true);

                if (isset($dataResponse['status']) && $dataResponse['status'] == 'Success')
                {

                    $offline_idv = (int) ($dataResponse['generalInformation']['depriciatedIDV']);

                    #because we are getting max idv in ic service response
                    # +5% and -5% IDV Deviation towards median
                    $median_idv = $offline_idv * 100/105;

                    $idvDepreciation = (1 - ($dataResponse['generalInformation']['percentageOfDepriciation'] / 100));
                    $idv_data = get_ic_min_max($median_idv, 0.95, 1.05, 0, 0, 0);
                    $min_idv =  $idv_data->min_idv;
                    $max_idv =  $idv_data->max_idv;

                    $VehiclebodyPrice = ($min_idv/$idvDepreciation);

                    $model_config_premium['ExShowRoomPrice'] = (isset($dataResponse['generalInformation']['showRoomPrice']) && $dataResponse['generalInformation']['showRoomPrice'] > 0 ) ? $dataResponse['generalInformation']['showRoomPrice'] : $VehiclebodyPrice;
                $checksum_data = checksum_encrypt($model_config_premium);
                $additionPremData['checksum'] = $checksum_data;
                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'icici_lombard',$checksum_data,'BIKE');
                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                {
                    $get_response = $is_data_exits_for_checksum;
                }
                else
                {
                    $get_response = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
                }
                    $data=$get_response['response'];

                }
                else
                {
                    $message = "Insurer not reachable";
                    if(!empty($dataResponse['message']))
                    {
                       $message =  $dataResponse['message'];
                    }
                    return [
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'status' => false,
                        'message' => $message
                    ];
                }
            }
        }
        // if($requestData->business_type == 'newbusiness'){
        //     $model_config_premium['PACoverTenure'] = '5';
        //     $additionPremData['productName'] = $productData->product_name." CPA 5 Year";
        //     $get_response_cpa = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
        //     $cpa_multiyear = json_decode($get_response_cpa['response'], true);
        // }
        if (!empty($data))
        {
           $arr_premium = json_decode($data, true);
           if(!isset($arr_premium['status']))
           {
                return [
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'status' => false,
                    'message' => $arr_premium
                ];
           }
            if(strtolower($arr_premium['status']) == 'success')
            {
                    $idv = ($arr_premium['generalInformation']['depriciatedIDV']);
                    if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true))
                    {
                        $msg = isset($arr_premium['deviationMessage']) ? $arr_premium['deviationMessage'] : 'Ex-Showroom price provided is not under permissable limits';
                         return [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                           'status' => false,
                           'message' => $msg
                        ];

                    }
                    // if (isset($arr_premium['breakingFlag']) && ($arr_premium['breakingFlag'] == true)) 
                    // {
                    //     $msg = "breakingFlag is true in service response,so quotes are not available in two wheeler";
                    //     return [
                    //         'webservice_id'=>$get_response['webservice_id'],
                    //         'table'=>$get_response['table'],
                    //         'status' => false,
                    //         'message' => $msg
                    //     ];
                    // }
                    if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true))
                    {
                        $msg = "Proposal application didn't pass underwriter approval";
                        return [
                            'webservice_id'=> $get_response['webservice_id'],
                            'table'=> $get_response['table'],
                            'status' => false,
                            'message' => $msg
                        ];
                    }
            }
            else
            {
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => isset($arr_premium['message']) ? $arr_premium['message'] : ''

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

                        #Refer line no 756
                        $median_idv = (int) ($offline_idv * 100/105);

                        $idv_data = get_ic_min_max($median_idv, 0.95, 1.05, 0, 0, 0);
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
                        $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_BIKE_TP');
                    }
                    else
                    {

                        $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_BIKE');
                    }

                    $checksum_data = checksum_encrypt($model_config_premium);
                    $additionPremData = [
                        'requestMethod' => 'post',
                        'type' => 'premiumRecalculation',
                        'section' => 'bike',
                        'productName'  => $productData->product_name,
                        'token' => $access_token,
                        'checksum'  => $checksum_data,
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

                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'icici_lombard',$checksum_data,'BIKE');
                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                {
                    $get_response = $is_data_exits_for_checksum;
                }
                else
                {
                    // $model_config_premium['PACoverTenure'] = '1';
                    $model_config_premium['PACoverTenure'] = isset($cpa_tenure) ? $cpa_tenure : '1';
                    $get_response = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
                }
                // if($requestData->business_type == 'newbusiness'){
                //     $model_config_premium['PACoverTenure'] = '5';
                //     $additionPremData['productName'] = $productData->product_name." CPA 5 Year";
                //     $get_response_cpa = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
                //     $cpa_multiyear = json_decode($get_response_cpa['response'], true);
                // }
                    $data = $get_response['response'];
                    if ($data)
                    {
                        $arr_premium = json_decode($data, true);
                        if(!isset($arr_premium['status']))
                        {
                            return [
                                'webservice_id'=> $get_response['webservice_id'],
                                'table'=> $get_response['table'],
                                'status' => false,
                                'message' => $arr_premium
                            ];
                        }
                        if(strtolower($arr_premium['status']) == 'success')
                        {
                            $idv = ($arr_premium['generalInformation']['depriciatedIDV']);
                            if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true))
                            {
                                $msg = isset($arr_premium['deviationMessage']) ? $arr_premium['deviationMessage'] : 'Ex-Showroom price provided is not under permissable limits';
                                 return [
                                    'webservice_id'=>$get_response['webservice_id'],
                                    'table'=>$get_response['table'],
                                   'status' => false,
                                   'message' => $msg
                                ];

                            }
                            if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true))
                            {
                                $msg = "Proposal application didn't pass underwriter approval";
                                return [
                                    'webservice_id'=> $get_response['webservice_id'],
                                    'table'=> $get_response['table'],
                                    'status' => false,
                                    'message' => $msg
                                ];
                            }


                        }
                        else
                        {
                            return [
                                   'webservice_id'=> $get_response['webservice_id'],
                                    'table'=> $get_response['table'],
                                   'status' => false,
                                   'message' => isset($arr_premium['message']) ? $arr_premium['message'] : ''

                                ];

                        }

                    }
                }

            }
            else
            {
                $idv =$min_idv =$max_idv = 0;
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
            $rsa = $zero_dept = $eng_protect = $key_replace = $consumable_cover = $return_to_invoice = $loss_belongings = $cpa_cover = $emeCover = 0 ;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;

            $geog_Extension_OD_Premium = isset($arr_premium['riskDetails']['geographicalExtensionOD'])  ? ($arr_premium['riskDetails']['geographicalExtensionOD']) : '0';
            $geog_Extension_TP_Premium = isset($arr_premium['riskDetails']['geographicalExtensionTP'])  ? ($arr_premium['riskDetails']['geographicalExtensionTP']) : '0';
            $od_premium = isset($arr_premium['riskDetails']['basicOD'])  ? ($arr_premium['riskDetails']['basicOD']) : 0;
            $breakingLoadingAmt = isset($arr_premium['riskDetails']['breakinLoadingAmount']) ? $arr_premium['riskDetails']['breakinLoadingAmount'] : 0; #As per git 23963
            $automobile_assoc = isset($arr_premium['riskDetails']['automobileAssociationDiscount']) ? ($arr_premium['riskDetails']['automobileAssociationDiscount']) : 0;
            $anti_theft =  isset($arr_premium['riskDetails']['antiTheftDiscount']) ? ($arr_premium['riskDetails']['antiTheftDiscount']) : 0;
            $elect_acc = isset($arr_premium['riskDetails']['electricalAccessories']) ? ($arr_premium['riskDetails']['electricalAccessories']) : 0;
            $non_elec_acc = isset($arr_premium['riskDetails']['nonElectricalAccessories']) ? ($arr_premium['riskDetails']['nonElectricalAccessories']) : 0;
            $lpg_cng_od = isset($arr_premium['riskDetails']['biFuelKitOD']) ? ($arr_premium['riskDetails']['biFuelKitOD']) : 0;
            $ncb_discount = isset($arr_premium['riskDetails']['bonusDiscount']) ? $arr_premium['riskDetails']['bonusDiscount'] : 0;
            $tppd_discount = isset($arr_premium['riskDetails']['tppD_Discount']) ? $arr_premium['riskDetails']['tppD_Discount'] : 0;

            $tp_premium = ($arr_premium['riskDetails']['basicTP']);
            $lpg_cng_tp = isset($arr_premium['riskDetails']['biFuelKitTP']) ? ($arr_premium['riskDetails']['biFuelKitTP']) : 0;
            $llpd_amt = isset($arr_premium['riskDetails']['paidDriver']) ? ($arr_premium['riskDetails']['paidDriver']) : 0 ;
            $unnamed_pa_amt = isset($arr_premium['riskDetails']['paCoverForUnNamedPassenger']) ? $arr_premium['riskDetails']['paCoverForUnNamedPassenger'] : 0;
            $rsa = isset($arr_premium['riskDetails']['roadSideAssistance']) ? $arr_premium['riskDetails']['roadSideAssistance'] : 0;
            $zero_dept = isset($arr_premium['riskDetails']['zeroDepreciation']) ? $arr_premium['riskDetails']['zeroDepreciation'] : 0;
            $eng_protect = isset($arr_premium['riskDetails']['engineProtect']) ? $arr_premium['riskDetails']['engineProtect'] : 0;
            $key_replace = isset($arr_premium['riskDetails']['keyProtect']) ? $arr_premium['riskDetails']['keyProtect'] : 0;
            $consumable_cover = isset($arr_premium['riskDetails']['consumables']) ? $arr_premium['riskDetails']['consumables'] : 0;
            $return_to_invoice = isset($arr_premium['riskDetails']['returnToInvoice']) ? $arr_premium['riskDetails']['returnToInvoice'] : 0;
            $loss_belongings = isset($arr_premium['riskDetails']['lossOfPersonalBelongings']) ? $arr_premium['riskDetails']['lossOfPersonalBelongings'] : 0;
            $cpa_cover = isset($arr_premium['riskDetails']['paCoverForOwnerDriver']) ? $arr_premium['riskDetails']['paCoverForOwnerDriver'] : 0;

            $breakingLoadingAmt = ((isset($arr_premium['breakingFlag']) && $arr_premium['breakingFlag'] == true ) ? $breakingLoadingAmt : 0 );
            if(isset($arr_premium['riskDetails']['voluntaryDiscount']))
            {
                $voluntary_deductible = $arr_premium['riskDetails']['voluntaryDiscount'];
            }
            else
            {
                $voluntary_deductible = voluntary_deductible_calculation($od_premium,$requestData->voluntary_excess_value,'bike');

            }
            $emeCover = $arr_premium['riskDetails']['emeCover'] ??  0;

            if(($productData->zero_dep == 0) && (int)$zero_dept == 0)
            {
                return [
                    'premium_amount' => 0,
                    'status'         => false,
                    'message'        => 'Zero dep product is not allowed, as ZD premium is 0',
                    'request'=> [
                        'bike_age'=>$bike_age,
                        'product_Data'=>$productData->zero_dep
                    ]
                ];
            }
            if ($productData->zero_dep == 0)
            {

                  $add_ons_data = [
                    'in_built' => [
                        'zeroDepreciation' =>$zero_dept
                    ],
                    'additional' => [                        
                        'roadSideAssistance' => $rsa,
                    ],
                    'other' => []
                ];
            }
            else
            {
                $add_ons_data = [
                    'in_built' => [],
                    'additional' => [
                        'zeroDepreciation' =>$zero_dept,
                        'roadSideAssistance' => $rsa,
                    ],
                    'other' => []
                ];
            }

            $applicable_addons = [
                'zeroDepreciation','roadSideAssistance'
            ];

            if($emeCover != 0)
            {
                $add_ons_data['additional']['emergencyMedicalExpenses'] = $emeCover;
                array_push($applicable_addons, 'emergencyMedicalExpenses');
            }

            $total_od = $od_premium  + $elect_acc + $non_elec_acc + $lpg_cng_od + $geog_Extension_OD_Premium;#remove breakingLoadingAmt
            $total_tp = $tp_premium + $llpd_amt + $unnamed_pa_amt + $lpg_cng_tp + $geog_Extension_TP_Premium;
            $total_discount = $ncb_discount + $automobile_assoc + $anti_theft + $voluntary_deductible + $tppd_discount;
            $basePremium = $total_od + $total_tp - $total_discount;

            $totalTax = $basePremium * 0.18;

            $final_premium = $basePremium + $totalTax;

            $selected_addons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
            $selected_addons_data['additional_premium'] = array_sum($add_ons_data['additional']);

            if ($bike_age > 5)#$bike_age > 5
            {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            }
            if($rsa == 0)
            {
                array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
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
                    $business_type = 'Break- In';
                break;
            }

            $data_response =
            [
                'status' => true,
                'msg' => 'Found',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
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
                    'voluntary_excess' => $voluntary_deductible,
                    'version_id' => $mmv_data->ic_version_code,
                    'showroom_price' => $model_config_premium['ExShowRoomPrice'],
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $applicable_ncb_rate,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => $mmv_data,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('d-m-Y',strtotime($PolicyStartDate)),
                        'policy_end_date' => date('d-m-Y',strtotime($PolicyEndDate)),
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
                        'bike_age' => $bike_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' =>  0,
                    ],
                    'basic_premium' => ($od_premium),
                    'deduction_of_ncb' => ($ncb_discount),
                    'voluntary_excess' => ($voluntary_deductible),
                    'tppd_premium_amount' => ($tp_premium),
                    'tppd_discount' => ($tppd_discount),
                    'motor_electric_accessories_value' =>($elect_acc),
                    'motor_non_electric_accessories_value' => ($non_elec_acc),
                    'motor_lpg_cng_kit_value' => ($lpg_cng_od),
                    'cover_unnamed_passenger_value' => ($unnamed_pa_amt),
                    'seating_capacity' => $mmv_data->seatingcapacity,
                    'default_paid_driver' => ($llpd_amt),
                    'motor_additional_paid_driver' => 0,
                    'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
                    'compulsory_pa_own_driver' => $cpa_cover,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' =>  ($total_od),
                    'underwriting_loading_amount'=> $breakingLoadingAmt,
                    'cng_lpg_tp' => $lpg_cng_tp,
                    'total_liability_premium' => ($total_tp),
                    'net_premium' => ($basePremium),
                    'service_tax_amount' => 0,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'voluntary_excess' => $voluntary_deductible,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => ($final_premium),
                    'antitheft_discount' => '',
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
                    'policyStartDate' => date('d-m-Y',strtotime($PolicyStartDate)),
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
                    'ribbon' => $ribbonMessage
                ]
            ];
            if(isset($cpa_tenure) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '5'){
                $data_response['Data']['multi_year_cpa'] = $cpa_cover;
            }
            return camelCase($data_response);
        }
        else
        {
            return [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'status' => false,
                'message' => "Issue in premium calculation service"
            ];
        }
    }
    else
    {
        return [
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
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
            'message'        => 'bike Insurer Not found' . $e->getMessage() . ' line ' . $e->getLine()
        ];
    }
}
