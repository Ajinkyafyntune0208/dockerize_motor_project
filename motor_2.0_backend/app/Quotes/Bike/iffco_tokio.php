<?php

use Illuminate\Support\Carbon;
use App\Models\SelectedAddons;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Support\Facades\DB;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    // if(($requestData->ownership_changed ?? '' ) == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }
    $mmv_data = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');

    if ($mmv_data['status'] == 1) {
        $mmv_data = (object) array_change_key_case((array) $mmv_data['data'],CASE_LOWER);
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv_data['message'],
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    }

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } elseif ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    }

    if (empty($requestData->rto_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available',
            'request'=> $requestData->rto_code
        ];
    }

    $rto_code = $requestData->rto_code;
    $city_name = DB::table('master_rto as mr')
        ->where('mr.rto_number', $rto_code)
        ->select('mr.*')
        ->first();

    if (empty($city_name->iffco_city_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO City Code not Found'
        ];
    }
    
    $rto_data = DB::table('iffco_tokio_city_master as ift')
        ->where('rto_city_code',$city_name->iffco_city_code)
        ->select('ift.*')->first();

    /* $rto_cities = explode('/',  $city_name->rto_name);
    foreach($rto_cities as $rto_city)
    {
        $rto_city = strtoupper($rto_city);
        $rto_data = DB::table('iffco_tokio_city_master as ift')
        ->where('rto_city_name',$rto_city)
        ->select('ift.*')->first();

        if(!empty($rto_data))
        {
            break;
        }
    } */

    if (empty($rto_data) || empty($rto_data->rto_city_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available'
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id',$productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    $tp_only = in_array($premium_type,['third_party','third_party_breakin']);
    if($premium_type == 'comprehensive' && $requestData->previous_policy_type == "Third-party") {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quote not available when previous policy type is Third Party',
            'request'=> [
                'previous_policy_type' => $requestData->previous_policy_type,
                'premium_type'=>$premium_type
            ]
        ];
    }

    $prev_policy_end_date = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($prev_policy_end_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $vehicle_age = ceil($age / 12);

    if ($vehicle_age > 5 && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request'=> [
                'vehicle_age' => $vehicle_age,
                'productData'=>$productData->zero_dep
            ]
        ];
    }
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    if (($interval->y >= 20) && ($tp_check == 'true')) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 20 years',
        ];
    }

    $root_tag = 'getMotorPremium';
    $isNewVehicle = 'N';
    $current_ncb_rate = 0;
    $applicable_ncb_rate = $requestData->applicable_ncb;
    $first_reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));
    $prev_policy_end_date = date('m/d/Y 23:59:59', strtotime($prev_policy_end_date));
    $is_previous_claim = $requestData->is_claim == 'Y' ? true : false;
    $policyType = 'Comprehensive';
    $premium_url = config('constants.IcConstants.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_PREMIUM_VA');
    $tenure = '1';

    if ($requestData->business_type == 'newbusiness') {
        $tenure = '5';
        $businessType = 'New Business';
        $policy_start_date = date('m/d/Y 00:00:00');
        $prev_policy_end_date = '';
        $root_tag = 'prem:getNewVehiclePremium';
        $isNewVehicle = 'Y';
        $applicable_ncb_rate = 0;
        $is_previous_claim = false;
        $premium_url = config('constants.IcConstants.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_PREMIUM_NB_VA');
    } else if ($requestData->business_type == 'rollover') {
        $businessType = 'Roll Over';
        $policy_start_date = date('m/d/Y 00:00:00', strtotime('+1 day', strtotime($prev_policy_end_date)));
        $policy_end_date = date('m/d/Y 23:59:59', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $applicable_ncb_rate = $requestData->applicable_ncb;
    } else if ($requestData->business_type == 'breakin') {
        $businessType = 'Break-In';
        $policy_start_date = date('m/d/Y 00:00:00', strtotime('+3 day'));
        $applicable_ncb_rate = $requestData->applicable_ncb;
    }

    if ($tp_only) {
        $policyType = 'Third Party';
    } elseif ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin'){
        $policyType = 'Own Damage';
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id',$productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $vehicle_in_90_days = 'N';
    if ($isNewVehicle == 'N' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
        $date_difference = get_date_diff('day', $prev_policy_end_date);
        if ($date_difference > 0) {
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('m/d/Y 00:00:00', strtotime('+1 day')) : date('m/d/Y 00:00:00',strtotime('+3 day'));
        }

        if($date_difference > 90){
            $vehicle_in_90_days = 'Y';
            $applicable_ncb_rate = 0;
        }
    }

    $policy_end_date = date('m/d/Y 23:59:59', strtotime("+$tenure year -1 day", strtotime($policy_start_date)));

    if (in_array($requestData->previous_policy_type, ['Not sure'])) {
        $vehicle_in_90_days = 'Y';
        $applicable_ncb_rate = 0;
    }

    $applicable_ncb_rate = $is_previous_claim ? 0 : $applicable_ncb_rate;

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $cpa_tenure = '';

    if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
        $addons = $selected_addons->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ? '5' : '1';

            }
        }
    }
    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage")
    {
        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '5'; 
        }
        else{
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '1';
        }
    }
    // $additional_covers = json_decode($selected_addons->additional_covers);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
    $compulsory_personal_accident = ($selected_addons->compulsory_personal_accident == null ? [] : $selected_addons->compulsory_personal_accident);

    $year = explode('-', $requestData->manufacture_year);
    $yearOfManufacture = trim(end($year));

    $make_code = "TWP"."-".$mmv_data->make_code."-".$yearOfManufacture;

    $zero_dep = (($productData->zero_dep == '0') ? true : false);
    $applicable_addons = [
        'zeroDepreciation', 'roadSideAssistance'
    ];
    $date_difference = get_date_diff('year', $requestData->vehicle_register_date);

    if ($vehicle_age > 5) {
        array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
        array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
    }
    if($tp_only)
    {
        $applicable_addons = [];
    }
    $model_config_idv = [
        'idvWebServiceRequest' =>   [
            'dateOfRegistration' =>  $first_reg_date,
            'inceptionDate' =>  $policy_start_date,
            'makeCode' =>  $make_code,
            'rtoCity' =>  $rto_data->rto_city_code
        ]
    ];
    if ($premium_type == 'third_party' && $requestData->business_type == 'newbusiness') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party policy not applicable for new vehicle.',
        ];
    }

    if(!$tp_only)
    {
        $get_response = getWsData(
            config('constants.IcConstants.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_IDV'),
            $model_config_idv,
            'iffco_tokio',
            [
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:prem="http://premiumwrapper.motor.itgi.com"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                'section' => $productData->product_sub_type_code,
                'method' => 'IDV Service',
                'company' => $productData->company_name,
                'productName' => $productData->product_name. " ($businessType)",
                'transaction_type' => 'quote',
                'root_tag' => 'getVehicleIdv',
            ]
        );
        $data = $get_response['response'];

        if(!$data)
        {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => 'Insurer not reachable',
            ];
        }

        $arr_idv_response = XmlToArray::convert((string)$data);
        $arr_idv = $arr_idv_response['soapenv:Body']['getVehicleIdvResponse']['ns1:getVehicleIdvReturn'];
        
        if(in_array($arr_idv['ns1:erorMessage'], ['Error getting IDV', 'Error occurred while fetching IDV. Please try again or try after sometime'])){
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Error getting IDV'
            ];
        }
        unset($data);
        
        $idv = $arr_idv['ns1:idv'];
        $min_idv = $arr_idv['ns1:minimumIdvAllowed'];
        $max_idv = $arr_idv['ns1:maximumIdvAllowed'];
    }else
    {
        $idv = 0;
        $min_idv = 0;
        $max_idv = 0;  
    }
    
    if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y')
    {
        if ($requestData->edit_idv >= $max_idv)
        {
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number'] = floor($max_idv);
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured'] = floor($max_idv);
        }
        elseif ($requestData->edit_idv <= $min_idv)
        {
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number']  = floor($min_idv);
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured']  = floor($min_idv);
        }
        else
        {
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number']  = floor($requestData->edit_idv);
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured']  = floor($requestData->edit_idv);
        }
    }else{
        /* $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number']  = floor($min_idv);
        $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured']  = floor($min_idv); */

        $getIdvSetting = getCommonConfig('idv_settings');
        switch ($getIdvSetting) {
            case 'default':
                $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number']  = floor($idv);
                $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured']  = floor($idv);
                break;
            case 'min_idv':
                $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number']  = floor($min_idv);
                $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured']  = floor($min_idv);
                break;
            case 'max_idv':
                $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number']  = floor($max_idv);
                $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured']  = floor($max_idv);
                break;
            default:
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number']  = floor($min_idv);
            $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['sumInsured']  = floor($min_idv);
                break;
        }
    }

    $zero_dep == true ? $zero_dep_sum = 'Y' : $zero_dep_sum = 'N';
    // $zero_dep_sum == 'Y' ? $towing_related = 'Y' : $towing_related = 'N';
    $towing_related = 'Y';
    $dep_value = 0; $towing_related_cover = 0;
    $cpa_cover = 'Y';
    $voluntary_insurer_discounts = 'N';
    $tppd_cover = '750000';
    $is_tppd_cover = false;
    foreach ($discounts as $key => $value) {
        if (!empty($value['name']) && !empty($value['sumInsured']) && $value['name'] == 'voluntary_insurer_discounts' && $value['sumInsured'] > 0) {
            $voluntary_insurer_discounts = $value['sumInsured'];
        }
        if($value['name'] == 'TPPD Cover')
        {
            $is_tppd_cover = true;
        }
        if ($value['name'] == 'TPPD Cover' && ($requestData->business_type != 'newbusiness')) {
            $is_tppd_cover = true;
            $tppd_cover = '6000';
        }
    }

    $pa_unnamed = '0';
    $unnamed_passenger = 'N';
    $is_zero_dep = 'N';
    foreach ($additional_covers as $key => $value) {
        if ($value['name'] == 'Unnamed Passenger PA Cover') {
            $unnamed_passenger = 'Y';
            $pa_unnamed = $value['sumInsured'];
            if(empty($pa_unnamed) || $pa_unnamed > 100000) {
                $pa_unnamed = '0';
            }
        }
    }

    // foreach ($addons as $key => $value) {
    //     if (in_array('Road Side Assistance', $value)) {
    //         $towing_related = "Y"; //Road Side Assistance
    //     }
    //     if (in_array('Zero Depreciation', $value)) {
    //         $is_zero_dep = 'Y';
    //     }
    // }

    // foreach ($compulsory_personal_accident  as $key => $value) {
    //     if (in_array('Compulsory Personal Accident', $value)) {
    //         $cpa_cover = "Y"; 
    //     }
    // }

    if ($premium_type == 'third_party') {
        $requestData->applicable_ncb = 0;
    }

    $motor_non_electric_accessories = 0;
    if (!empty($accessories)) {
        foreach ($accessories as $key => $data) {

            if ($data['name'] == 'Non-Electrical Accessories') {
                $motor_non_electric_accessories = $data['sumInsured'];
            }
        }
    }

    $VehicleCoverages_arr = [
        [
            'coverageId' => 'IDV Basic',
            'number' =>  (!in_array($premium_type, ['third_party', 'third_party_breakin'])) ? $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number'] : 1,
            'sumInsured' =>  (!in_array($premium_type, ['third_party', 'third_party_breakin'])) ? $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number'] : 1,
        ]
    ];

    if(!in_array($premium_type, ['own_damage', 'own_damage_breakin'])) {
        if($cpa_cover == 'Y'){
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'PA Owner / Driver',
                    'number' => ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" && isset($cpa_tenure))? $cpa_tenure :'',
                    'sumInsured' => $requestData->vehicle_owner_type == 'I' ? 'Y' : 'N',
                ]
            ]);
        }
        if($is_tppd_cover == true){
        $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
            [
                'coverageId' => 'TPPD',
                'number' => '',
                'sumInsured' => $tppd_cover,
            ]
        ]);
        }
        if($unnamed_passenger == 'Y'){
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'PA to Passenger',
                    'number'  => '',
                    'sumInsured' => $pa_unnamed,
                ]
            ]);
        }
    }

    if(!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
        if ($isNewVehicle != 'Y') {
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'No Claim Bonus',
                    'number' => '',
                    'sumInsured' => $applicable_ncb_rate,
                ]
            ]);
        }
        if($zero_dep){
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'Depreciation Waiver',
                    'number' => '',
                    'sumInsured' => $zero_dep_sum,
                ]
            ]);
        }
        if($voluntary_insurer_discounts != 'N'){
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'Voluntary Excess',
                    'number' => '',
                    'sumInsured' => $voluntary_insurer_discounts,
                ]
            ]);
        }
        if ($towing_related == 'Y') {
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'Towing & Related',
                    'number' => '',
                    'sumInsured' => $towing_related,
                ]
            ]);
        }
        if ($motor_non_electric_accessories != 0 && $motor_non_electric_accessories != '0') {
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'Cost of Accessories',
                    'number' => '',
                    'sumInsured' => (($motor_non_electric_accessories != '') ? $motor_non_electric_accessories : 0),
                ],
            ]);
        }
    }

    $VehicleCoverages_arr = array_values($VehicleCoverages_arr);
    $vol_excess_od = 0; $vol_excess_tp = 0;
    $model_config_premium = [
        'policyHeader' => [
            'messageId' => '1964',
        ],
        'policy' => [
            'contractType' => config('constants.IcConstants.iffco_tokio.contractType_Bike'),
            'expiryDate' => $policy_end_date,
            'inceptionDate' => $policy_start_date,
            'previousPolicyEndDate' => $prev_policy_end_date,
            'vehicle' => [
                'newVehicleFlag'=> $isNewVehicle,
                'aaiExpiryDate' => '',
                'aaiNo' => '',
                'capacity' => $mmv_data->seating_capacity,
                'engineCpacity' => $mmv_data->cc,
                'exShPurPrice' => '',
                'grossVehicleWeight' => '',
                'grossVehicleWt' => '',
                'itgiRiskOccupationCode' => '',
                'itgiZone' => $rto_data->irda_zone,
                'make' =>   $mmv_data->make_code,
                'regictrationCity' => $rto_data->rto_city_code,
                'registrationDate' => $first_reg_date,
                'seatingCapacity' => $mmv_data->seating_capacity,
                'type' => ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? 'OD' : '',
                'vehicleBody' => '',
                'vehicleClass' => config('constants.IcConstants.iffco_tokio.contractType_Bike'),
                'vehicleCoverage' => [
                    'item' => $VehicleCoverages_arr
                ],
                'vehicleInsuranceCost' => '',
                'vehicleSubclass' => config('constants.IcConstants.iffco_tokio.contractType_Bike'),
                'yearOfManufacture' => $yearOfManufacture,
                'zcover' => ($premium_type == "third_party" || $premium_type == "third_party_breakin") ? config('constants.IcConstants.iffco_tokio.zcover_bike_tp') : config('constants.IcConstants.iffco_tokio.zcover_bike_co'),
            ],
        ],
        'partner' => [
            'partnerBranch' => config('constants.IcConstants.iffco_tokio.partnerBranchBike'),
            'partnerCode' => config('constants.IcConstants.iffco_tokio.partnerCodeBike'),
            'partnerSubBranch' => config('constants.IcConstants.iffco_tokio.partnerSubBranchBike'),
        ],
    ];

    $get_response = getWsData(
        $premium_url,
        $model_config_premium,
        'iffco_tokio',
        [
            'root_tag' => $root_tag,
            'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:prem="http://premiumwrapper.motor.itgi.com"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
            'requestMethod' => 'post',
            'section' => 'bike',
            'method' => 'Premium Calculation',
            'company' => $productData->company_name,
            'productName' => $productData->product_name. " ($businessType)",
            'enquiryId' => $enquiryId,
            'transaction_type' => 'quote'
        ]
    );

    $data = $get_response['response'];
    if($data) {
        $premium_data = XmlToArray::convert((string)$data);
        if(!empty($premium_data['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn'][1]['error']['errorCode']))
        {
            $error = $premium_data['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn'][1]['error'];
            return [
                'webservice_id'     => $get_response['webservice_id'],
                'table'             => $get_response['table'],
                'premium_amount'    => 0,
                'status'            => false,
                'message'           => $error['errorCode'].' - '.$error['errorMessage']
            ];
        }
        if(!empty($premium_data['soapenv:Body']['soapenv:Fault']['faultstring'] ?? '')) {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => $premium_data['soapenv:Body']['soapenv:Fault']['faultstring'],
            ];
        }
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Premium Calculation Success", "Success");
        $ncb_amount = $tppd_discount = $pa_owner_driver = $pa_unnamed = $voluntary_deductible = $non_electric_accessories = 0;

        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $ns = (($zero_dep == false) || $tp_only) ? 'ns1:' : 'ns2:';
            
            if(!empty($premium_data['soapenv:Body']['getMotorPremiumResponse'][$ns.'getMotorPremiumReturn'][$ns.'error'][$ns.'errorCode']))
            {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => $premium_data['soapenv:Body']['getMotorPremiumResponse'][$ns.'getMotorPremiumReturn'][$ns.'error'][$ns.'errorMessage']
                ];
            }

            $checkResponse = checkResponse($premium_data, $get_response, $ns);
            if(!$checkResponse['status'])
            {
                return $checkResponse;
            }
            $premium_data = $checkResponse['premium_data'] ?? [];
            $ns = $checkResponse['ns'] ?? $ns;

            if (is_array($premium_data) && count($premium_data) < 2) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => 'Invalid response recieved from IC',
                ];
            }

            $coveragepremiumdetail = $premium_data[$ns.'coveragePremiumDetail'];

            foreach($coveragepremiumdetail as $k => $v) {
                $coverage_name = $v[$ns.'coverageName'];

                if(is_array($v[$ns.'odPremium'])) {
                    $v[$ns.'odPremium'] = (!empty($v[$ns.'odPremium']['@value']) ? $v[$ns.'odPremium']['@value'] : '0' );
                }

                if(is_array($v[$ns.'tpPremium'])) {
                    $v[$ns.'tpPremium'] = (!empty($v[$ns.'tpPremium']['@value']) ? $v[$ns.'tpPremium']['@value'] : '0' );
                }

                if($coverage_name == 'IDV Basic') {
                    $od_premium = $v[$ns.'odPremium'];
                    $tp_premium = $v[$ns.'tpPremium'];
                } else if ($coverage_name == 'No Claim Bonus') {
                    $ncb_amount = $v[$ns.'odPremium'];
                } else if ($coverage_name == 'PA Owner / Driver') {
                    $pa_owner_driver = $v[$ns.'tpPremium'];
                } else if ($coverage_name == 'Depreciation Waiver') {
                    $dep_value = $v[$ns.'coveragePremium'];
                } else if ($coverage_name == 'Towing & Related') {
                    $towing_related_cover = $v[$ns.'coveragePremium'];
                } else if ($coverage_name == 'PA to Passenger') {
                    $pa_unnamed = $v[$ns.'tpPremium'];
                } else if ($coverage_name == 'Voluntary Excess') {
                    $voluntary_deductible = $v[$ns.'odPremium'] + $v[$ns.'tpPremium'];
                    $vol_excess_od = $v[$ns.'odPremium'];
                    $vol_excess_tp = $v[$ns.'tpPremium'];
                } else if ($coverage_name == "TPPD") {
                    $tppd_discount = intval($v[$ns.'tpPremium']) == 1 ? 0 : $v[$ns.'tpPremium'];
                } else if($coverage_name == 'Cost of Accessories') {
                    $non_electric_accessories = ($v[$ns.'odPremium']);
                }
            }

            $total_od_premium = $premium_data[$ns.'totalODPremium'];
            $total_tp_premium = $premium_data[$ns.'totalTPPremium'];
            $discount_amount = abs($premium_data[$ns.'discountLoadingAmt']);
            intval($pa_owner_driver) == 1 ? $pa_owner_driver = 0 : abs($pa_owner_driver);
            $od_premium = $od_premium + $discount_amount;
            $service_tax = $premium_data[$ns.'serviceTax'];
            $base_premium_amount = $total_amount_payable = $premium_data[$ns.'premiumPayable'];
        } else if ($requestData->business_type == 'newbusiness') {
            $premium_data = $premium_data['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn'];
            $premium_data = ($zero_dep == false) ? $premium_data[0] : $premium_data[1];
            if(isset($premium_data['error']['errorCode']) && !empty($premium_data['error']['errorCode'])){
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $premium_data['error']['errorMessage']
                ];
            }

            if (!isset($premium_data['inscoverageResponse'])) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Something went wrong'
                ];
            }
            $coveragepremiumdetail = $premium_data['inscoverageResponse']['coverageResponse']['coverageResponse'];

            foreach($coveragepremiumdetail as $k => $v) {
                $coverage_name = $v['coverageCode'];
                if($coverage_name == 'IDV Basic') {
                    $prm = calculateNBPremium(($v));
                    $od_premium = $prm['od'];
                    $tp_premium = $prm['tp'];
                } else if ($coverage_name == 'PA Owner / Driver') {
                    $prm = calculateNBPremium(($v));
                    $pa_owner_driver = $prm['tp'];
                } else if ($coverage_name == 'Depreciation Waiver') {
                    $prm = calculateNBPremium(($v));
                    $dep_value = $prm['od'];
                } else if ($coverage_name == 'Towing & Related') {
                    $prm = calculateNBPremium(($v));
                    $towing_related_cover = $prm['od'];
                } else if ($coverage_name == 'PA to Passenger') {
                    $prm = calculateNBPremium(($v));
                    $pa_unnamed = $prm['tp'];
                } else if ($coverage_name == 'Voluntary Excess') {
                    $prm = calculateNBPremium(($v));
                    $voluntary_deductible = $prm['od'] + $prm['tp'];
                    $vol_excess_od = $prm['od'];
                    $vol_excess_tp = $prm['tp'];
                } else if($coverage_name == 'Cost of Accessories') {
                    $prm = calculateNBPremium(($v));
                    $non_electric_accessories = $prm['od'];
                } /* else if ($coverage_name == "TPPD") {
                    $prm = calculateNBPremium(($v));
                    $tppd_discount = $prm['tp'];
                } */
            }

            $total_od_premium = $premium_data['totalODPremium'];
            $total_tp_premium = $premium_data['totalTPPremium'];
            $discount_amount = (abs($premium_data['discountLoadingAmt']));
            intval($pa_owner_driver) == 1 ? $pa_owner_driver = 0 : (abs($pa_owner_driver));
            $od_premium = $od_premium + $discount_amount;
            $service_tax = $premium_data['gstAmount'];
            $base_premium_amount = $total_amount_payable = $premium_data['premiumPayable'];
        }

        intval($od_premium) == 1 ? $od_premium = 0 : $od_premium;
        intval($pa_unnamed) == 1 ? $pa_unnamed = 0 : $pa_unnamed;
        intval($voluntary_deductible) == 1 ?  $voluntary_deductible = 0 : $voluntary_deductible;
        intval($ncb_amount) == 1 ?  $ncb_amount = 0 : (abs($ncb_amount));
        $total_od_premium = ($od_premium) + $non_electric_accessories;
        $total_tp_premium = $tp_premium + $pa_unnamed;
        $total_discount_premium = (abs($ncb_amount)) + ($discount_amount) + abs($tppd_discount) + abs($voluntary_deductible);
        $total_base_premium = ($total_od_premium) +  $total_tp_premium - $total_discount_premium;

        $add_ons_data = [
            'in_built' => [],
            'additional' => [],
            'other' => []
        ];

        if ($premium_type != 'third_party' && $productData->zero_dep == 0) {
            $add_ons_data['in_built']['zeroDepreciation'] = $dep_value;
            $add_ons_data['additional']['roadSideAssistance'] = $towing_related_cover;
        }

        $add_ons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
        $add_ons_data['additional_premium'] = array_sum($add_ons_data['additional']);
        $add_ons_data['other_premium'] = 0;

        $geog_Extension_OD_Premium = 0;
        $geog_Extension_TP_Premium = 0;

        if ($premium_data) {
            $data_response = [
                'status' => true,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Found',
                'Data' => [
                    'idv' => ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 0 : $model_config_premium['policy']['vehicle']['vehicleCoverage']['item'][0]['number'],
                    'min_idv' => $min_idv,
                    'max_idv' => $max_idv,
                    'vehicle_idv' => $idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' =>  $policyType,
                    'business_type' => $businessType,
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'rto_no' => $rto_code,
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => 0,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => $mmv_data,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('Y-m-d', strtotime($policy_start_date)),
                        'policy_end_date' => date('Y-m-d', strtotime($policy_end_date)),
                        'sum_insured' => $productData->sum_insured,
                        'corp_client_id' => $productData->corp_client_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'insurance_company_id' => $productData->company_id,
                        'status' => $productData->status,
                        'corp_name' => '',
                        'company_name' => $productData->company_name,
                        'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => '',
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                    ],
                    'motor_manf_date' => $requestData->vehicle_register_date,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => 2, //$car_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => abs($discount_amount),
                    ],
                    'ic_vehicle_discount' => abs($discount_amount),
                    'basic_premium' => $od_premium,
                    'bike_electric_accessories_value' => 0,
                    'bike_non_electric_accessories_value' => 0,
                    'motor_electric_accessories_value' => 0,
                    'motor_non_electric_accessories_value' => $non_electric_accessories,
                    'bike_lpg_cng_kit_value' => 0,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => $total_od_premium,
                    'tppd_premium_amount' => $tp_premium,
                    'compulsory_pa_own_driver' => $pa_owner_driver, // Not added in Total TP Premium
                    'cover_unnamed_passenger_value' => $pa_unnamed, //$pa_unnamed,
                    'default_paid_driver' => 0,
                    'bike_additional_paid_driver' => 0,
                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                    'cng_lpg_tp' => 0,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'deduction_of_ncb' => abs($ncb_amount),
                    'antitheft_discount' => 0,
                    'aai_discount' => '', //$automobile_association,
                    'voluntary_excess' => abs($voluntary_deductible),
                    'other_discount' => abs($discount_amount),
                    'total_liability_premium' => $tp_premium,
                    'net_premium' => $base_premium_amount,
                    'service_tax_amount' => $service_tax,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => $total_amount_payable,
                    // 'tppd_discount' => round(abs($tppd_discount)),
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'service_err_code' => null,
                    'service_err_msg' => null,
                    'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => $vehicle_in_90_days,
                    'get_policy_expiry_date' => null,
                    'get_changed_discount_quoteid' => 0,
                    'vehicle_discount_detail' => [
                        'discount_id' => null,
                        'discount_rate' => null,
                    ],
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                    'policy_id' => $productData->policy_id,
                    'insurane_company_id' => $productData->company_id,
                    'max_addons_selection' => null,

                    'add_ons_data' => $add_ons_data,
                    'applicable_addons' =>$applicable_addons,

                    'final_od_premium' => $total_od_premium,
                    'final_tp_premium' => $total_tp_premium,
                    'final_total_discount' => abs($total_discount_premium),
                    'final_net_premium' => $total_base_premium,
                    'final_gst_amount' => $service_tax,
                    'final_payable_amount' => $total_amount_payable,
                    'mmv_detail' => [
                        'manf_name' => $mmv_data->manufacture,
                        'model_name' => $mmv_data->model,
                        'version_name' => $mmv_data->variant,
                        'fuel_type' => $mmv_data->fuel_type,
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'carrying_capacity' => $mmv_data->seating_capacity,
                        'cubic_capacity' => $mmv_data->cc,
                        'gross_vehicle_weight' => '',
                        'vehicle_type' => '2W',
                    ],
                ],
            ];
        }

        if(isset($cpa_tenure))
        {
        if($requestData->business_type == 'newbusiness' && $cpa_tenure  == '5')
        {
            // unset($data_response['Data']['compulsory_pa_own_driver']);
            $data_response['Data']['multi_Year_Cpa'] = $pa_owner_driver;
        }
        }
        if($is_tppd_cover)
        {
            $data_response['Data']['tppd_discount'] = abs($tppd_discount);
        }
        if($is_tppd_cover)
        {
            $data_response['Data']['tppd_discount'] = abs($tppd_discount);
        }
        if($is_tppd_cover)
        {
            $data_response['Data']['tppd_discount'] = abs($tppd_discount);
        }
        return camelCase($data_response);

    }
    else
    {
        return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Insurur Not Reachable'
        ];
    }
}

function calculateNBPremium($cov) {
    $od_premium = $tp_premium = 0;
    for ($i=1; $i <= 5; $i++) {
        if (!empty($cov['OD'.$i]) && !is_array($cov['OD'.$i])) {
            $od_premium += (float) $cov['OD'.$i];
        }
        if (!empty($cov['TP'.$i]) && !is_array($cov['TP'.$i])) {
            $tp_premium += (float) $cov['TP'.$i];
        }
    }

    return ['od' => $od_premium, 'tp' => $tp_premium];
}

function checkResponse($premiumData, $data, $ns) {

    $ns1data = $premiumData['soapenv:Body']['getMotorPremiumResponse']['ns1:getMotorPremiumReturn'];
    if(isset($ns1data['ns1:premiumPayable']) && ($ns1data['ns1:premiumPayable'] > 0))
    {
        return [
            'status'=> true,
            'premium_data' => $premiumData['soapenv:Body']['getMotorPremiumResponse'][$ns.'getMotorPremiumReturn'],
            'ns' => $ns
        ];
    }
    else {
        $premium_data = $premiumData['soapenv:Body']['getMotorPremiumResponse']['ns2:getMotorPremiumReturn'] ?? [];

        if(isset($premium_data['ns2:error']['ns2:errorCode']) && !empty($premium_data['ns2:error']['ns2:errorCode'])){
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => ($premium_data['ns2:error']['ns2:errorMessage'] ?? json_encode($premium_data))
            ];
        }
        return [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => json_encode($premium_data)
        ];
    }
}