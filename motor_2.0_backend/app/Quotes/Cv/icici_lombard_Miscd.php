<?php

use Carbon\Carbon;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use App\Models\IcVersionMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
use App\Models\Quotes\Cv\CvQuoteModel;
include_once app_path() . '/Helpers/CvWebServiceHelper.php';
function getMiscQuote($enquiryId, $requestData, $productData )
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

    $parentCode = get_parent_code($productData->product_sub_type_id);

    if($parentCode != 'PCV' && $requestData->is_claim == 'Y')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes not allowed if vehicle have Claim History',
            'request' => [
                'message' => 'Quotes not allowed if vehicle have Claim History',
                'requestData' => $requestData
            ]
        ];
    }

    $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
 
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $master_product_sub_type_code = MasterPolicy::find($productData->policy_id)->product_sub_type_code->product_sub_type_code;

    // Defined constant
    if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' ||$master_product_sub_type_code == 'TRUCK' ||$master_product_sub_type_code == 'TRACTOR' ||$master_product_sub_type_code == 'TANKER/BULKER') {
        $type = 'GCV';
        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_TP');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE_TP');
        }elseif ($premium_type == 'breakin') {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
        }else {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
        }
    }elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
        $type = 'PCV';
        if ($premium_type == 'third_party'|| $premium_type == 'third_party_breakin') {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_TP_PRODUCT_CODE');
        }elseif ($premium_type == 'breakin') {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
        }
        elseif ($premium_type == 'short_term_3_breakin')
        {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_3_BREAKIN');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
        }
        elseif ($premium_type == 'short_term_6_breakin')
        {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_6_BREAKIN');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
        }
        else {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
        }
    }elseif ($master_product_sub_type_code === 'MISCELLANEOUS-CLASS' || $master_product_sub_type_code === 'AGRICULTURAL-TRACTOR' ) {
        $type = 'MISC';
        if ($premium_type == 'third_party' ||$premium_type == 'third_party_breakin') {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP_MISC'); #TP Deal for misc
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_TP_PRODUCT_CODE');
        }elseif ($premium_type == 'breakin') {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN'); # breakin deal for misc
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
        }else {
            $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_DEAL_ID');
            $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
        }
    }else{
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Premium is not available for this product',
            'request' => [
                'message' => 'Premium is not available for this product',
                'master_product_sub_type_code' => $master_product_sub_type_code
            ]
        ];
    }

//    $mmvData = IcVersionMapping::leftjoin('icici_mmv_master', function ($join) {
//        $join->on('icici_mmv_master.model_code', '=', 'ic_version_mapping.ic_version_code');
//    })
//        ->where([
//            'ic_version_mapping.fyn_version_id' => $requestData->version_id,
//            'ic_version_mapping.ic_id' => $productData->company_id
//        ])
//        ->select('ic_version_mapping.*', 'icici_mmv_master.*')
//        ->first();

    if ($type == 'MISC') {
        $mmv = get_mmv_details($productData,$requestData->version_id,'icici_lombard');

        if (empty($mmv)) {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'mmv details not found',
                'request' => [
                    'mmv' => $mmv,
                ]
            ];
        }
        
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
                    'mmv' => $mmv
                ]
            ];
        }
        $mmvData = (object) array_change_key_case((array) $mmv,CASE_LOWER);
        if (empty($mmvData->ic_version_code) || $mmvData->ic_version_code == '') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'message' => 'Vehicle Not Mapped',
                    'mmvData' => $mmvData
                ]
            ];
        } else if ($mmvData->ic_version_code == 'DNE') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'message' => 'Vehicle code does not exist with Insurance company',
                    'mmvData' => $mmvData
                ]
            ];
        }
    }


    // $claim_allowed = config('constants.IcConstants.icici_lombard.CLAIM_ALLOWED') ?? 'N';

    // if ($requestData->is_claim == 'Y' && $claim_allowed == 'N') {
    //     return  [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Vehicle not allowed if claim made in last policy',
    //         'request' => [
    //             'message' => 'Vehicle not allowed if claim made in last policy',
    //             'requestData' => $requestData,
    //             'is_claim' => $requestData->is_claim
    //         ]
    //     ];
    // }




    if(isset($mmvData->model_build))
    {
        $model_build = $mmvData->model_build;
    }else{
        $model_build = '';
    }

    if($type == 'MISC')
    {
        $mmvDetails['manf_name'] = $mmvData->manf_name;
        $mmvDetails['model_name'] = $mmvData->model_name;
        // $mmvDetails['version_name'] = $mmvData->model_name;
        $mmvDetails['version_id'] = $mmvData->model_code;
        $mmvDetails['seating_capacity'] = $mmvData->seating_capacity;
        $mmvDetails['cubic_capacity'] = $mmvData->cubic_capacity;
        $mmvDetails['fuel_type'] = $mmvData->fuel_type;
    }

    // vehicle age calculation
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);
    $zeroDepPlanName = '';

    $product_name = policyProductType($productData->policy_id)->product_name;
    if($interval->y >= 3 && $is_zero_dep){
        if($product_name == 'imt_23') {
            $is_zero_dep = false;
        } else {
            return [
                'status' => false,
                'message' => 'Zero not available for vehicle above 3 years'
            ];
        }
    }
    // if($is_zero_dep && $car_age < 3){
    //  $zeroDepPlanName = 'Silver MISD';
    // }
    // zero dep condition for gcv
    // $zeroDepPlanName = '';
    // /* if ($master_product_sub_type_code == 'PICKUP-DELIVERY-VAN') {
    //     if ($mmvDet->grosss_vehicle_weight < 3500 && $car_age < 3) {
    //         $zeroDepPlanName = 'Silver GCV';
    //     }
    // } */

    // check for rto location

    $proposerVehDet = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)
        ->select('*')
        ->first();

    /* $rtoLocationCode = IciciRtoMaster::where('rto_code', $proposerVehDet->rto_code)
        ->where('product_sub_type_id', $productData->product_sub_type_id)
        ->first(); */

    $state_code = '';
    $city_district_code = '';
    $country_code = '';

    $rto_cities = MasterRto::where('rto_code', $proposerVehDet->rto_code)->first();
    $state_id = $rto_cities->state_id;
    $state_name = MasterState::where('state_id', $state_id)->first();
    $state_name = strtoupper($state_name->state_name);
    $rto_cities = explode('/',  $rto_cities->rto_name);
    foreach($rto_cities as $rto_city)
    {
        $rto_city = strtoupper($rto_city);
        $rto_data = DB::table('icici_lombard_city_disctrict_master')
                    ->where('TXT_CITYDISTRICT', $rto_city)
                    ->where('GST_STATE', $state_name)
                    ->first();
        $rto_data = keysToLower($rto_data);
        if($rto_data)
        {
            $state_code = $rto_data->il_state_cd;
            $city_district_code = $rto_data->il_citydistrict_cd;
            $country_code = $rto_data->il_country_cd;
            break;
        }
    }

    if($master_product_sub_type_code == 'TAXI' || $master_product_sub_type_code === 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW'){
        $pcv_rto_master_user_from_master_rto = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_RTO_MASTER_FROM_MASTER_RTO') == 'Y' ? true : false;
        if(Schema::hasColumn('master_rto', 'icici_pcv_location_code') && $pcv_rto_master_user_from_master_rto)
        {
            $rto_cities = MasterRto::where('rto_code', $proposerVehDet->rto_code)->first();

            if(!blank($rto_cities) && !empty($rto_cities->icici_pcv_location_code))
            {
                $rto_location_code = DB::table('pcv_icici_lombard_rto_master')
                ->where('RTOLocationCode', trim($rto_cities->icici_pcv_location_code))
                ->where('ActiveFlag', 'Y');
            }
        }else
        {
            $rto_location_code = DB::table('icici_lombard_rto_master')
            ->where('ILStateCode', $state_code)
            ->where('CityDistrictCode', $city_district_code)
            ->where('ActiveFlag', 'Y');
        }
        if ($master_product_sub_type_code == 'AUTO-RICKSHAW') {
            $rto_location_code = $rto_location_code->where('Vehicle_Subclass', '2')->get();
        }elseif ($master_product_sub_type_code == 'ELECTRIC-RICKSHAW') {
            $rto_location_code = $rto_location_code->where('Vehicle_Subclass', '8')->get();
        }else {
            $rto_location_code = $rto_location_code->where('Vehicle_Subclass', '1')->get();
        }
        $rto_location_code = keysToLower($rto_location_code);

        if (count($rto_location_code) > 1) {
            foreach( $rto_location_code as $key => $value){
                if($value->rtolocationdesciption == $state_name.'-'.$rto_city.'-C1-C4-2WD-4WD') {
                    $rtoLocationCode = $value->rtolocationcode;
                    break;
                }
            }
        }
        elseif (count($rto_location_code) == 1)
        {
            $rtoLocationCode = $rto_location_code[0]->rtolocationcode;
        }
        else
        {
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'RTO not available'
            ];
        }

    }
    // elseif($master_product_sub_type_code == 'MISCELLANEOUS-CLASS'){
    //     $rtoLocationCode = '5207'; # for testing purpose
    // }
    else {
        $rto_location_code = DB::table('misc_icici_lombard_rto_master')
            ->where('ILStateCode', $state_code)
            ->where('CityDistrictCode', $city_district_code)
            ->first();
        $rto_location_code = keysToLower($rto_location_code);
        if ($rto_location_code) {
            $rtoLocationCode = $rto_location_code->rtolocationcode;
        } else {
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'RTO not available'
            ];
        }

        
    }


    if (empty($rtoLocationCode)) {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $requestData->rto_code.' RTO Location Not Found',
            'request' => [
                'rto_no' => $requestData->rto_code
            ]
        ];
    }

    // token Generation

    $additionData = [
        'requestMethod' => 'post',
        'type' => 'tokenGeneration',
        'section' => 'MISC',
        'enquiryId' => $enquiryId,
        'transaction_type' => 'quote',
        'productName'  => $productData->product_name,
    ];

    $tokenParam = [
        'grant_type' => 'password',
        'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
        'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
        'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
        'scope' => 'esbmotor',
    ];

    // If token API is not working then don't store it in cache - @Amit - 07-10-2022
    $token_cache_name = 'constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL.cv.' . $enquiryId;
    $token_cache = Cache::get($token_cache_name);
    if(empty($token_cache)) {
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token_decoded = json_decode($get_response['response'], true);
        if(isset($token_decoded['access_token'])) {
            $token = cache()->remember($token_cache_name, 60 * 45, function () use ($get_response) {
                return $get_response;
            });
            $token = json_decode($get_response['response'], true);
        } else {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => "Insurer not reachable,Issue in Token Generation service"
            ];
        }
    } else {
        $token = json_decode($token_cache['response'], true);
    }
    // $token = cache()->remember('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL', 60 * 45, function () use ($tokenParam, $additionData) {
    //     $token = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
    //     return $token = json_decode($token, true);
    // });
    if ($token) {
        // $token = json_decode($token, true);
        $access_token = $token['access_token'];

        $corelationId = getUUID($enquiryId);


        if ($requestData->business_type == 'rollover') {
            $businessType = "Roll Over";
            $policyStartDate = Carbon::createFromDate($proposerVehDet->previous_policy_expiry_date)->addDay(1);
            if ($premium_type == 'short_term_3') {
                $policyEndDate = Carbon::createFromDate($proposerVehDet->previous_policy_expiry_date)->addMonth(3);
            }elseif($premium_type == 'short_term_6') {
                $policyEndDate = Carbon::createFromDate($proposerVehDet->previous_policy_expiry_date)->addMonth(6);
            }else {
                $policyEndDate = Carbon::createFromDate($proposerVehDet->previous_policy_expiry_date)->addYear(1);
            }
        } elseif ($requestData->business_type == 'newbusiness') {
            $businessType = "New Business";
            $policyStartDate = Carbon::today();
            $policyEndDate = Carbon::today()->addYear(1)->subDay(1);
        }

        $brekinFlag = ($proposerVehDet->business_type == 'brekin') ? true : false;

        $proposerVehDet->previous_policy_expiry_date = $proposerVehDet->business_type == 'newbusiness' ? "" : $proposerVehDet->previous_policy_expiry_date;

        $previousPolicyExpiryDate = $proposerVehDet->previous_policy_expiry_date == 'New' ? '' : Carbon::createFromDate($proposerVehDet->previous_policy_expiry_date);

        $previousPolicyStartDate = $proposerVehDet->previous_policy_expiry_date == 'New' ? '' : Carbon::createFromDate($proposerVehDet->previous_policy_expiry_date)->subYear(1)->addDay(1);

        $date_diff_in_prev_policy = 0;

        if(!empty( $previousPolicyExpiryDate))
        {
        if($previousPolicyExpiryDate->lt(Carbon::today())){
            $policyStartDate = Carbon::today()->addDay(3);
            $policyEndDate = Carbon::today()->addDay(2)->addYear(1);
            $businessType = "Roll Over";
            if (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                $brekinFlag = true;

                if ($premium_type == 'short_term_3_breakin')
                {
                    $policyEndDate = Carbon::today()->addDay(2)->addMonth(3);
                }
                elseif ($premium_type == 'short_term_6_breakin')
                {
                    $policyEndDate = Carbon::today()->addDay(2)->addMonth(6);
                }

                $date_diff_in_prev_policy = $previousPolicyExpiryDate->diffInDays(Carbon::today());
                if ($date_diff_in_prev_policy > 90) {
                    $applicable_ncb_rate = 0;
                    $current_ncb_rate = 0;
                }
            }
        }
        }else if($requestData->business_type != 'newbusiness') {
            $businessType = 'Roll Over';
            $policyStartDate = Carbon::today()->addDay(3);
            $policyEndDate = Carbon::today()->addDay(2)->addYear(1);

            if ($premium_type == 'short_term_3' || $premium_type == 'short_term_3_breakin')
            {
                $policyEndDate = Carbon::today()->addDay(2)->addMonth(3);
            }
            elseif ($premium_type == 'short_term_6' || $premium_type == 'short_term_6_breakin')
            {
                $policyEndDate = Carbon::today()->addDay(2)->addMonth(6);
            }
        }

        $selectedAddons = SelectedAddons::where('user_product_journey_id', $enquiryId)
            ->select('*')
            ->get();

        $eleAccessories = [];
        $nonEleAccessories = [];
        $lpgAndCng = [];
        $addionalPaidDriver = [];
        $ownerDriver = [];
        $unnamedPassenger = [];
        $antiTheftDisc = [];
        $compulsorypaOwnDriver = [];
        $compulsorypaOwnDriver['status'] = "true";
        $llPaidDriverCC =  [];
        $llPaidDriverCC['noOfDriver'] = 1;
        $LiabilityToPaidDriver_IsChecked = 'false';
        $is_imt = false;
        // $is_zero_dept = false;


        if($product_name === 'imt_23'){
            $is_imt  = true;
        }
        if (!$selectedAddons->isEmpty()) {
            //     if (!empty($selectedAddons[0]->addons)) {
            //         if($product_name === 'imt_23'){
            //          foreach ($selectedAddons[0]->addons as $addonVal) {
            //             // if (in_array('IMT - 23', $addonVal)) {
            //             //     $is_imt  = true;
            //             // }
            //             // if (in_array('Zero Depreciation', $addonVal)) {
            //             //     $is_zero_dept  = true;
            //             // }
            //         }
            //     }
            //  }        
            if (!empty($selectedAddons[0]->accessories)) {

                foreach ($selectedAddons[0]->accessories as $addonVal) {

                    if (in_array('Electrical Accessories', $addonVal)) {
                        $eleAccessories['status'] = 'true';
                        $eleAccessories['name'] = $addonVal['name'];
                        $eleAccessories['addonSI'] = $addonVal['sumInsured'];
                    }

                    if (in_array('Non-Electrical Accessories', $addonVal)) {
                        $nonEleAccessories['status'] = 'true';
                        $nonEleAccessories['name'] = $addonVal['name'];
                        $nonEleAccessories['addonSI'] = $addonVal['sumInsured'];
                    }

                    if (in_array('External Bi-Fuel Kit CNG/LPG', $addonVal)) {
                        $lpgAndCng['status'] = 'true';
                        $lpgAndCng['name'] = $addonVal['name'];
                        $lpgAndCng['addonSI'] = $addonVal['sumInsured'];
                    }
                }
            }

            if (!empty($selectedAddons[0]->additional_covers)) {

                foreach ($selectedAddons[0]->additional_covers as $addon) {
                    if (in_array('PA cover for additional paid driver', $addon)) {
                        $addionalPaidDriver['status'] = 'true';
                        $addionalPaidDriver['name'] = 'Silver PCV';
                        $addionalPaidDriver['addonSI'] = $addon['sumInsured'];
                    }

                    if (in_array('Owner Driver PA Cover', $addon)) {
                        $ownerDriver['status'] = 'true';
                        $ownerDriver['name'] = 'RSA1';
                        $ownerDriver['addonSI'] = $addon['sumInsured'];
                    }

                    if (in_array('Unnamed Passenger PA Cover', $addon)) {
                        $unnamedPassenger['status'] = 'true';
                        $unnamedPassenger['name'] = 'RSA1';
                        $unnamedPassenger['addonSI'] = $addon['sumInsured'];
                    }

                    
                    if (in_array('LL paid driver/conductor/cleaner', $addon)) {
                        $LiabilityToPaidDriver_IsChecked = 'true';
                        $llPaidDriverCC['status'] = 'true';
                        $llPaidDriverCC['noOfDriver'] = isset($addon['LLNumberDriver']) ? $addon['LLNumberDriver'] : 0;
                        $llPaidDriverCC['noOfCleaner'] = isset($addon['LLNumberCleaner']) ? $addon['LLNumberCleaner'] : 0;
                        $llPaidDriverCC['noOfConductor'] = isset($addon['LLNumberConductor']) ? $addon['LLNumberConductor'] : 0;
                    }

                    if (in_array('LL paid driver', $addon)) {
                        $LiabilityToPaidDriver_IsChecked = 'true';
                    }
                }
            }

            $tppdUser = false;
            if (!empty($selectedAddons[0]->discounts)) {
                foreach ($selectedAddons[0]->discounts as $discount) {
                    if (in_array('anti-theft device', $discount)) {
                        $antiTheftDisc['status'] = 'true';
                    }

                    if (in_array('TPPD Cover', $discount)) {
                        $tppdUser = true;
                    }
                }
            }


             if (!empty($selectedAddons[0]->compulsory_personal_accident)) {
                foreach ($selectedAddons[0]->compulsory_personal_accident as $addon) {
                    if (in_array('Compulsory Personal Accident', $addon)) {
                        $compulsorypaOwnDriver['status'] = "true";
                    }
                }
            }
        }


        // Applying IC condition on cleaner and conductor
        $noOfCleanerAndConductor = 0;
        if(isset($llPaidDriverCC['noOfCleaner']) || isset($llPaidDriverCC['noOfConductor'])){
            $noOfCleanerAndCond = $llPaidDriverCC['noOfCleaner'] + $llPaidDriverCC['noOfConductor'];
            if ($noOfCleanerAndCond <= $mmvDetails['seating_capacity']) {
                $noOfCleanerAndConductor = $noOfCleanerAndCond;
            }else{
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id'=> $get_response['webservice_id'] ?? $token_cache["webservice_id"],
                    'table'=> $get_response['table'] ?? $token_cache["table"],
                    'message' => 'Number of cleaner and conductor should not be greater than vehicle seating capacity',
                    'request' => [
                        'message' => 'Number of cleaner and conductor should not be greater than vehicle seating capacity',
                        'seating_capacity' => $mmvDetails['seating_capacity'],
                        'no_of_cleaner_and_conductor' => $noOfCleanerAndCond
                    ]
                ];
            }
        }

        $vehicleHaveLPG = false;

        // # inbuilt CNG Logic :
        // if($type !== 'MISC'){ #short term misc condition
        //     if(isset($mmvData->fyntune_version['fuel_type']) && $mmvData->fyntune_version['fuel_type'] == 'CNG')
        //     {
        //         $vehicleHaveLPG = false;
        //         $lpgAndCng['status'] = true;
        //         $lpgAndCng['addonSI'] = 0;
        //         $mmvDetails['fuel_type'] = $mmvData->fyntune_version['fuel_type'];
        //     }else if(isset($mmvData->fyntune_version['fuel_type']) && $mmvData->fyntune_version['fuel_type'] == 'LPG')
        //     {
        //         $vehicleHaveLPG = true;
        //         $lpgAndCng['status'] = false;
        //         $lpgAndCng['addonSI'] = 0;
        //         $mmvDetails['fuel_type'] = $mmvData->fyntune_version['fuel_type'];
        //     }
        // }



        //check carrier type
        if ($proposerVehDet->gcv_carrier_type == 'PRIVATE') {
            $gcvCarrierType = true;
        }else{
            $gcvCarrierType = false;
        }

        // set addons plan name according to its vehicle sub type
        switch($master_product_sub_type_code){
            case 'TAXI':
            case 'ELECTRIC-RICKSHAW':
            case 'AUTO-RICKSHAW' :
                $zero_dep_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_ZERO_DEP_PLAN_NAME');
                $rsa_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_RSA_PLAN_NAME');
            break;
            case 'PICK UP/DELIVERY/REFRIGERATED VAN':
            case 'DUMPER/TIPPER' :
            case 'TRUCK' :
            case 'TRACTOR' :
            case 'TANKER/BULKER' :
            case 'MISCELLANEOUS-CLASS' :
            case 'AGRICULTURAL-TRACTOR' :
            $zero_dep_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_ZERO_DEP_PLAN_NAME');
            $rsa_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_RSA_PLAN_NAME');
                break;
        }
        // // zero dep condition for gcv pcv
        //  if ($type == 'GCV')
        //  {
        //     if ($mmvData->gvw < 3500 && $car_age <= 3) {
        //         $zeroDepPlanName = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_ZERO_DEP_PLAN_NAME');
        //     }
        // }elseif($type == 'PCV')
        // {
        //     if($mmvData->carrying_capacity <= 6 && $car_age <= 3)
        //     {
        //         $zeroDepPlanName = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_ZERO_DEP_PLAN_NAME');
        //     }
        // }
        // else{
        //     $zeroDepPlanName ='';
        // }

        #query for fetching POS details
        $is_pos = 'N';
        $is_icici_pos_disabled_renewbuy = config('constants.motorConstant.IS_ICICI_POS_DISABLED_RENEWBUY');
        $is_pos_enabled = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
        $pos_testing_mode = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$enquiryId)
            ->where('seller_type','P')
            ->first();
        if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
        {
            if($pos_data)
            {
                $is_pos = 'Y';
                $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                $CertificateNumber = $pos_data->unique_number; #$pos_data->user_name;
                $PanCardNo = $pos_data->pan_no;
                $AadhaarNo = $pos_data->aadhar_no;
            }

            if($pos_testing_mode === 'Y')
            {
                $is_pos = 'Y';
                $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                $CertificateNumber = 'TMI0001';
                $PanCardNo = 'ABGTY8890Z';
                $AadhaarNo = '569278616999';
            }

            $ProductCode = $PRODUCT_CODE;
        }
        elseif($pos_testing_mode === 'Y')
        {
            $is_pos = 'Y';
            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
            $CertificateNumber = 'TMI0001';
            $PanCardNo = 'ABGTY8890Z';
            $AadhaarNo = '569278616999';
            $ProductCode = $PRODUCT_CODE;
        }

        if ($is_pos == 'Y') {
            $pos_details = [
                'pos_details' => [
                    'IRDALicenceNumber' => $IRDALicenceNumber,
                    'CertificateNumber' => $CertificateNumber,
                    'PanCardNo'         => $PanCardNo,
                    'AadhaarNo'         => $AadhaarNo,
                    'ProductCode'       => $ProductCode
                ]
            ];
        }

        #IDV Service

        $VehiclebodyPrice = 0;
        $max_idv = 0;
        $min_idv = 0;

        $enable_idv_service = config('constants.ICICI_LOMBARD.ENABLE_ICICI_IDV_SERVICE');
        
       if ($enable_idv_service == 'Y') {
            $idv_request = [
                // "DealID" => $ICICI_LOMBARD_DEAL_ID,
                "manufacturercode" => $mmvData->manf_code,#366
                "BusinessType" => $businessType,
                "rtolocationcode" => $rtoLocationCode,#5207
                "DeliveryOrRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet->vehicle_register_date)),
                "FirstRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet->vehicle_register_date)),
                "PolicyStartDate" => $policyStartDate->toDateString(),
                "vehiclemodelcode" => $mmvData->ic_version_code,#6338
                "correlationId" => $corelationId
            ];

            if($premium_type == 'comprehensive' || $premium_type == 'short_term_3' || $premium_type == 'short_term_6' || $premium_type == 'breakin') {
                $tokenParam['scope'] = 'esbmotormodel'; # scope for IDV
                $idv_token_response = cache()->remember('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL',  60 * 45 ,function () use ($tokenParam,$additionData) {
                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                    $token_for_idv = $get_response['response'];
                    return $token = json_decode($token_for_idv, true);
                });
                // $idv_token_response = json_decode($token_for_idv, true);

                if (!$idv_token_response) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'] ?? null,
                        'table' => $get_response['table'] ?? null,
                        'message' => 'Insurer not reachable'
                    ];
                }

                $idvAdditionalRequest = [
                    'requestMethod' => 'post',
                    'type' => 'IdvGeneration',
                    'section' => 'MISC',
                    'token' => $idv_token_response['access_token'],
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'quote',
                    'productName'  => $productData->product_name,
                ];

                if($is_pos == 'Y')
                {
                    $idvAdditionalRequest = array_merge($idvAdditionalRequest, $pos_details);
                }

                 # checkpost for non pos
                if($is_pos !== 'Y')
                {
                    $idv_request['DealId'] = $ICICI_LOMBARD_DEAL_ID;
                }

                $url = config('constants.IcConstants.icici_lombard.CV_IDV_END_POINT_URL');
                $get_response = getWsData($url, $idv_request, 'icici_lombard', $idvAdditionalRequest);
                $idv_response = $get_response['response'];

                $idv_response = json_decode($idv_response);

               
                if(!$idv_response)
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable'
                    ];
                }

                if ($idv_response->status === false) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable'
                    ];
                }



                // $VehiclebodyPrice = $idv_response->minimumprice;
                // $vehiclebodyPrice = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('ex_showroom_price_idv')->first() ;
                $vehiclebodyPrice = QuoteLog::where('user_product_journey_id', $requestData->user_product_journey_id)->pluck('ex_showroom_price_idv')->first();
              
                $VehiclebodyPrice = $idv_response->minimumprice;
                $max_idv = $idv_response->maxidv;
                $min_idv = $idv_response->minidv;

            }
        }

        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
           $breakin_days = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);
        }
        if ($is_zero_dep) {
            $zeroDepPlanName = "Silver MISD";
        }
        $premiumRecalcRequest = [
            // "DealId" => $ICICI_LOMBARD_DEAL_ID,
            "CustomerType" => ($proposerVehDet->vehicle_owner_type == 'I') ? "INDIVIDUAL" : "Corporate",
            "CorrelationId" => $corelationId,
            "PolicyEndDate" => $policyEndDate->toDateString(),
            "PolicyStartDate" => $policyStartDate->toDateString(),
            "RTOLocationCode" => $rtoLocationCode, //$rtoLocationCode->rto_location_code,#5207
            "VehicleMakeCode" =>  $mmvData->manf_code,#366
            "VehicleModelCode" =>  $mmvData->ic_version_code,#6338
            "ManufacturingYear" => date('Y', strtotime('01-' . $requestData->manufacture_year)),
            "DeliveryOrRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet->vehicle_register_date)),
            "FirstRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet->vehicle_register_date)),
            "GSTToState" => $state_name, //$rtoLocationCode->state_name,
            "BusinessType" => ($premium_type == 'third_party' && $businessType == 'Roll Over' ) ? 'Used' : $businessType,
            "ProductCode" => $PRODUCT_CODE,
            // "ProductCode" => '2316',
            "IsVehicleHaveCNG" => (!empty($lpgAndCng['status'])) ? $lpgAndCng['status'] : false,
            "IsVehicleHaveLPG" => $vehicleHaveLPG,
            "SI_VehicleLPGCNG_KIT" => (!empty($lpgAndCng['addonSI'])) ? $lpgAndCng['addonSI'] : "0",
            "IsPrivateUse" => "false",
            "IsLimitedToOwnPremises" => "false",
            "IsNonFarePayingPassengers" => "false",
            // "IsNCBApproved" => (($requestData->is_claim == 'Y') || ($requestData->business_type == 'breakin' && $breakin_days > 90)) || $requestData->previous_policy_expiry_date == 'New' ? 'false' : 'true',
            // "IsNCBApplicable" => (($requestData->is_claim == 'Y') || ($requestData->business_type == 'breakin' && $breakin_days > 90)) || $requestData->previous_policy_expiry_date == 'New'  ? 'false' : 'true',
            "NoOfNonFarePayingPassenger" => 2,
            "IsGarageCash" => "false",
            "IsTyreProtect" => "false",
            "IsHireOrHiresEmployee" => "false", // default
            "IsPACoverOwnerDriver" => (!empty($compulsorypaOwnDriver)) ? $compulsorypaOwnDriver['status'] : 'false',
            "ISPACoverWaiver" => 'false',
            "IsNoPrevInsurance" => $requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure' ? "true" : "false",
            "IsAntiTheftDisc" => (!empty($antiTheftDisc)) ? $antiTheftDisc['status'] : 'false',
            "IsConsumables" => "false", // default
            "ZeroDepPlanName" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '' : $zeroDepPlanName,
            "RSAPlanName" => $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? '' : $rsa_plan_name,
            "InclusionOfIMT" => $is_imt,
            "IsHaveElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? 'false' : ((!empty($eleAccessories)) ? $eleAccessories['status'] : 'false'),
            "SIHaveElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '0' : ((!empty($eleAccessories)) ? $eleAccessories['addonSI'] : '0'),
            "IsHaveNonElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? 'false' : ((!empty($nonEleAccessories)) ? $nonEleAccessories['status'] : 'false'),
            "SIHaveNoNElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '0' : ((!empty($nonEleAccessories)) ? $nonEleAccessories['addonSI'] : '0'),
            "IsLegalLiabilityToPaidDriver" =>  $type == 'GCV' && isset($llPaidDriverCC['noOfDriver']) && $llPaidDriverCC['noOfDriver'] == 0 ? false : $LiabilityToPaidDriver_IsChecked,
            "NoOfDriver" => $llPaidDriverCC['status'] = 'true' ? $llPaidDriverCC['noOfDriver'] : "1",
            "NoOfCleanerOrConductor" => $noOfCleanerAndConductor,
            "IsAutomobileAssocnFlag" => false,
            "AutomobileAssociationNumber" => "",
            "RegistrationNumber" => str_replace("-", "", $requestData->vehicle_registration_no),
            "EngineNumber" => '',
            "ChassisNumber" => '',
            "IsPrivateCarrier" =>  $gcvCarrierType,
            "CustomerDetails" => [
                "CustomerType" => ($proposerVehDet->vehicle_owner_type == 'I') ? "INDIVIDUAL" : "Corporate",
                "CustomerName" => $requestData->user_fname,
                "DateOfBirth" => '',
                "PinCode" => '',
                "PANCardNo" =>  "",
                "Email" => '',
                "MobileNumber" => '',
                "AddressLine1" => '',
                "CountryCode" => $country_code, //$rtoLocationCode->country_code,
                "StateCode" => $state_code, //$rtoLocationCode->state_code,
                "CityCode" => $city_district_code, //$rtoLocationCode->city_code,
                "AdharNumber" => ""
            ],
            "PreviousPolicyDetails" => [
                "previousPolicyStartDate" => $requestData->previous_policy_type == 'Not sure' ? '' : $previousPolicyStartDate->toDateString(),
                "previousPolicyEndDate" => $requestData->previous_policy_type == 'Not sure' ? '' : $previousPolicyExpiryDate->toDateString(),
                "ClaimOnPreviousPolicy" => ($date_diff_in_prev_policy > 90) ? false : (($requestData->is_claim == 'Y') ? true : false),
                "PreviousPolicyType" => $requestData->previous_policy_type == 'Third-party' ? "TP" : "Comprehensive Package",
                "PreviousInsurerName" => 'GIC',
                "PreviousPolicyNumber" => '4567898765',
                "BonusOnPreviousPolicy" => $requestData->previous_policy_type == 'Third-party' || $date_diff_in_prev_policy > 90 ? 0 :  $requestData->previous_ncb
            ]
        ];

        if ($requestData->previous_policy_type == 'Not sure') {
            unset( $premiumRecalcRequest['PreviousPolicyDetails']);
        }


        if ($tppdUser) {
            $premiumRecalcRequest['tppdLimit'] = config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? 6000 : 750000;
        }

        // if ($model_build) {
        //     if ($model_build == 'FULLY BUILT') {
        //         $premiumRecalcRequest['VehiclechasisPrice'] = $VehiclebodyPrice;
        //         $premiumRecalcRequest['vehiclebodyPrice'] = '0';
        //     }elseif ($model_build == 'PARTIALLY BUILT') {
        //         $premiumRecalcRequest['VehiclechasisPrice'] = '0';
        //         $premiumRecalcRequest['vehiclebodyPrice'] = $VehiclebodyPrice;
        //     }else {
        //         $premiumRecalcRequest['VehiclechasisPrice'] = $VehiclebodyPrice;
        //         $premiumRecalcRequest['vehiclebodyPrice'] = '0';
        //     }
        // }else{
            $premiumRecalcRequest['VehiclechasisPrice'] = $VehiclebodyPrice;
            $premiumRecalcRequest['vehiclebodyPrice'] =  0;
        // }

        if ($requestData->business_type == 'newbusiness') {
            unset($premiumRecalcRequest['PreviousPolicyDetails']);
        }

        if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
            $premiumRecalcRequest['TypeOfCalculation'] = "Pro Rata";
        }

        # checkpost for non pos
        if($is_pos !== 'Y')
        {
            $premiumRecalcRequest['DealId'] = $ICICI_LOMBARD_DEAL_ID;
        }
       
        $additionPremData = [
            'requestMethod' => 'post',
            'type' => 'premiumCalculation',
            'section' => 'MISC',
            'token' => $access_token,
            'enquiryId' => $enquiryId,
            'transaction_type' => 'quote',
            'productName'  => $productData->product_name,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ];

        if($is_pos == 'Y')
        {
            $additionPremData = array_merge($additionPremData,$pos_details);
        }

        $tokenParam['scope'] = 'esbmotor'; # scope for Premium Calculation
        $get_response  = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_QUOTE_PREMIUM_CALC_URL'), $premiumRecalcRequest, 'icici_lombard', $additionPremData);
        $premRecalculateResponse = $get_response['response'];
         # Entry Gaurd on offline idv Service
         $allowed = 'Y';
         if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
             $allowed = 'N';
         }

         # IDV CHANGED LOGIC

        if($requestData->is_idv_changed == 'Y' && $allowed == 'Y')
        {
            if (!empty($premRecalculateResponse))
            {
                $premiumResponse = json_decode($premRecalculateResponse, true);
                if (isset($premiumResponse['status']) && $premiumResponse['status'] == 'true' && isset($premiumResponse['statusMessage']) && $premiumResponse['statusMessage'] == 'SUCCESS')
                {
                    $idvDepreciation = (1 - ($premiumResponse['generalInformation']['percentageOfDepriciation'] / 100));

                    if ($enable_idv_service != 'Y')
                    {
                        $offline_idv = (int) round($premiumResponse['generalInformation']['depriciatedIDV']);
                        $idv_data = get_ic_min_max($offline_idv, 0.95, 1.05, 0, 0, 0);
                        $min_idv =  $idv_data->min_idv;
                        $max_idv =  $idv_data->max_idv;
                    }

                    // dd($offline_idv, $min_idv, $max_idv);

                    if ($requestData->is_idv_changed == 'Y') {
                        if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                            $VehiclebodyPrice = floor($max_idv/$idvDepreciation);
                        } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                            $VehiclebodyPrice = ceil($min_idv/$idvDepreciation);
                        } else {
                            $VehiclebodyPrice = ceil($requestData->edit_idv/$idvDepreciation);
                        }

                        $premiumRecalcRequest['VehiclechasisPrice'] = $VehiclebodyPrice;

                        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_QUOTE_PREMIUM_CALC_URL'), $premiumRecalcRequest, 'icici_lombard', $additionPremData);
                        $premRecalculateResponse = $get_response['response'];
                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Insurer not reachable"
                    ];
                }
            }
            else
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Insurer not reachable"
                ];
            }
        }


        # offline idv calculation
        if ($enable_idv_service != 'Y' && $requestData->is_idv_changed != 'Y' && $allowed == 'Y') {
            if (!empty($premRecalculateResponse))
            {
                $premiumResponse = json_decode($premRecalculateResponse, true);
                if ((isset($premiumResponse['status']) && $premiumResponse['status'] == 'true')&& isset($premiumResponse['statusMessage']) && $premiumResponse['statusMessage'] == 'SUCCESS')
                {
                    $idvDepreciation = (1 - ($premiumResponse['generalInformation']['percentageOfDepriciation'] / 100));

                    $offline_idv = (int) round($premiumResponse['generalInformation']['depriciatedIDV']);
                    $idv_data = get_ic_min_max($offline_idv, 0.95, 1.05, 0, 0, 0);
                    $min_idv =  $idv_data->min_idv;
                    $max_idv =  $idv_data->max_idv;

                   /*  if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                        $VehiclebodyPrice = floor($max_idv/$idvDepreciation);
                    } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                        $VehiclebodyPrice = ceil($min_idv/$idvDepreciation);
                    } else {
                        $VehiclebodyPrice = ceil($requestData->edit_idv/$idvDepreciation);
                    } */
                    $VehiclebodyPrice = ceil($min_idv/$idvDepreciation);
                    $premiumRecalcRequest['VehiclechasisPrice'] = $VehiclebodyPrice;
                    $additionPremData = [
                        'requestMethod' => 'post',
                        'type' => 'premiumReCalculation',
                        'section' => 'MISC',
                        'token' => $access_token,
                        'enquiryId' => $enquiryId,
                        'transaction_type' => 'quote',
                        'productName'  => $productData->product_name,
                        'headers' => [
                            'Authorization' => 'Bearer ' . $access_token
                        ]
                    ];

                    if($is_pos == 'Y')
                    {
                        $additionPremData = array_merge($additionPremData,$pos_details);
                    }

                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_QUOTE_PREMIUM_CALC_URL'), $premiumRecalcRequest, 'icici_lombard', $additionPremData);
                    $premRecalculateResponse = $get_response['response'];

                }
                else
                {
                    return [
                        'status' => false,
                        'message' => isset($premiumResponse['Message']) ? $premiumResponse['Message'] :"Insurer not reachable"
                    ];
                }
            }
            else
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Insurer not reachable"
                ];
            }
        }

        if (!empty($premRecalculateResponse)) {
            $premiumResponse = json_decode($premRecalculateResponse, true);

            $response_status = isset($premiumResponse['status']) ? (($premiumResponse['status'] == 'true') ? true : false) : false;

            if ($response_status && isset($premiumResponse['statusMessage']) && $premiumResponse['statusMessage'] == 'SUCCESS') {
                if ( ! empty($premiumResponse['isQuoteDeviation']) && $premiumResponse['isQuoteDeviation'] && $parentCode == 'PCV') {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Quotes are not allowed if Deviation flag is true"
                    ];
                }

                $zeroDepreciation = isset($premiumResponse['riskDetails']['zeroDepreciation']) ? $premiumResponse['riskDetails']['zeroDepreciation'] : 0;
                $roadSideAssistance = isset($premiumResponse['riskDetails']['roadSideAssistance']) ? $premiumResponse['riskDetails']['roadSideAssistance'] : 0;
                $imT23OD = isset($premiumResponse['riskDetails']['imT23OD']) ? $premiumResponse['riskDetails']['imT23OD'] : 0;
                $basicOd = isset($premiumResponse['riskDetails']['basicOD']) ? $premiumResponse['riskDetails']['basicOD'] : 0;
                $basicTp = isset($premiumResponse['riskDetails']['basicTP']) ? $premiumResponse['riskDetails']['basicTP'] : 0;
                $electricalAccessories = isset($premiumResponse['riskDetails']['electricalAccessories']) ? $premiumResponse['riskDetails']['electricalAccessories'] : 0;
                $nonElectricalAccessories = isset($premiumResponse['riskDetails']['nonElectricalAccessories']) ? $premiumResponse['riskDetails']['nonElectricalAccessories'] : 0;
                $voluntaryDiscount = isset($premiumResponse['riskDetails']['voluntaryDiscount']) ? $premiumResponse['riskDetails']['voluntaryDiscount'] : 0;
                $antiTheftDiscount = isset($premiumResponse['riskDetails']['antiTheftDiscount']) ? $premiumResponse['riskDetails']['antiTheftDiscount'] : 0;
                $paidDriver = isset($premiumResponse['riskDetails']['paidDriver']) ? $premiumResponse['riskDetails']['paidDriver'] : 0;
                $paCoverForUnNamedPassenger = isset($premiumResponse['riskDetails']['paCoverForUnNamedPassenger']) ?: 0;
                $paCoverForOwnerDriver = isset($premiumResponse['riskDetails']['paCoverForOwnerDriver']) ? $premiumResponse['riskDetails']['paCoverForOwnerDriver'] : 0;
                $bonusDiscount = isset($premiumResponse['riskDetails']['bonusDiscount']) ? $premiumResponse['riskDetails']['bonusDiscount'] : 0;//ic value
                $ncbPercentage = isset($premiumResponse['riskDetails']['ncbPercentage']) ? $premiumResponse['riskDetails']['ncbPercentage'] : 0;
                $biFuelKitOD = isset($premiumResponse['riskDetails']['biFuelKitOD']) ? $premiumResponse['riskDetails']['biFuelKitOD'] : 0;
                $biFuelKitTP = isset($premiumResponse['riskDetails']['biFuelKitTP']) ? $premiumResponse['riskDetails']['biFuelKitTP'] : 0;
                $tppd_discount = isset($premiumResponse['riskDetails']['tppD_Discount']) ? $premiumResponse['riskDetails']['tppD_Discount'] : 0;
                $llCC = isset($premiumResponse['riskDetails']['legalLiabilityforCCC']) ? $premiumResponse['riskDetails']['legalLiabilityforCCC'] : 0;

                $own_premises_od = 0;
                $own_premises_tp = 0;

                $totalOdPremium = round($basicOd + $electricalAccessories + $nonElectricalAccessories + $biFuelKitOD);
                $totalTpPremium = round($basicTp + $biFuelKitTP + $paidDriver + $llCC);
                $totalDiscount = round($antiTheftDiscount + $voluntaryDiscount);#$tppd_discount
                $addon_premium = $zeroDepreciation + $roadSideAssistance;
                // $bonusDiscount = (!in_array($premium_type,['third_party','third_party_breakin'])) ?($totalOdPremium - $totalDiscount)  *  ($requestData->applicable_ncb/100) : 0;//manual
               
                $final_net_premium = round($totalOdPremium + $totalTpPremium - $totalDiscount + $bonusDiscount);

                // tax calculation for gcv
                if ($master_product_sub_type_code === ('PICKUP-DELIVERY-VAN' || 'TRUCK-TANKER' || 'TIPPER' || 'DUMPER' || 'TRAILER')) {
                    $totalTax = ($basicTp * 0.12) + ($final_net_premium - $basicTp) * 0.18;
                }else{
                    $totalTax = $final_net_premium * 0.18;
                }

                $final_premium = $final_net_premium + $totalTax;


                $business_type = '';
                if ($requestData->business_type == 'rollover') {
                    $business_type = 'Rollover';
                }elseif($requestData->business_type == 'breakin'){
                    $business_type = 'Break-in';
                }elseif($requestData->business_type == 'newbusiness'){
                    $business_type = 'New Business';
                }

                switch ($premium_type) {
                    case 'comprehensive':
                    case 'breakin':
                        $policy_type = 'Comprehensive';
                        break;

                    case 'third_party':
                    case 'third_party_breakin':
                        $policy_type = 'Third Party';
                        break;

                    case 'short_term_3':
                    case 'short_term_3_breakin':
                    case 'short_term_6':
                    case 'short_term_6_breakin':
                        $policy_type = 'Short Term';
                        break;
                }
                $addon_data = [
                    'in_built'   => [],
                    'additional' => []
                ];
                if((int)$zeroDepreciation == 0 && $productData->product_identifier == "zero_dep"){
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Zero Dep premium is not Available"
                    ];
                }

                if ($productData->product_identifier == "imt_23") {
                    $addon_data = [
                        'in_built'   => [
                            'imt23' => round($imT23OD)
                        ],
                        'additional' => [
                            'zero_depreciation' => round($zeroDepreciation),
                        ]
                    ];
                } elseif ($productData->product_identifier == "zero_dep") {
                    $addon_data = [
                        'in_built'   => [
                            'zero_depreciation' => round($zeroDepreciation)
                        ],
                        'additional' => []
                    ];
                }              

                if($requestData->previous_policy_type == 'Third-party' && $premium_type == 'breakin')
                {
                    $business_type = 'Break-in';
                }
                $applicable_addons = [
                    'zeroDepreciation', 'roadSideAssistance','imt23'
                ];
                if ($imT23OD == 0) {
                    array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
                }
                if ($zeroDepreciation == 0) {
                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                }
                if ($roadSideAssistance == 0) {
                    array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                }
                $data_response = [
                    'status' => true,
                    'msg' => 'Found',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Data' => [
                        'idv' => $premiumResponse['generalInformation']['depriciatedIDV'],
                        'vehicle_idv' => $premiumResponse['generalInformation']['depriciatedIDV'],
                        'min_idv' => $min_idv,
                        'max_idv' => $max_idv,
                        'rto_decline' => NULL,
                        'rto_decline_number' => NULL,
                        'mmv_decline' => NULL,
                        'mmv_decline_name' => NULL,
                        'business_type' => $business_type,
                        'policy_type' => $policy_type,
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'rto_no' => $requestData->rto_code,
                        'voluntary_excess' => $requestData->voluntary_excess_value,
                        'version_id' => $type === 'MISC' ? '' : $mmvData->ic_version_code,
                        'selected_addon' => [],
                        'showroom_price' => $VehiclebodyPrice, //$chassisPrice, //$premiumResponse['generalInformation']['showRoomPrice'],
                        'fuel_type' => $requestData->fuel_type,
                        'ncb_discount' => $premiumResponse['riskDetails']['ncbPercentage'] ?? 0,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_name' => $productData->product_sub_type_name,
                        'mmv_detail' =>$mmvDetails,
                        'master_policy_id' => [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $productData->policy_no,
                            'policy_start_date' => $requestData->previous_policy_type == 'Not sure' ? '' : Carbon::parse($previousPolicyStartDate->toDateString())->format('d-m-Y'),
                            'policy_end_date' => $requestData->previous_policy_type == 'Not sure' ? '' : Carbon::parse($previousPolicyExpiryDate->toDateString())->format('d-m-Y'),
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
                            'car_age' => 19,
                            'aai_discount' => 0,
                            'ic_vehicle_discount' => round($bonusDiscount)
                        ],
                        'basic_premium' => round($basicOd),
                        'deduction_of_ncb' => round($bonusDiscount),
                        'tppd_premium_amount' => round($basicTp),
                        'motor_electric_accessories_value' => round($electricalAccessories),
                        'motor_non_electric_accessories_value' => round($nonElectricalAccessories),
                        /* 'motor_lpg_cng_kit_value' => $biFuelKitOD, */
                        'cover_unnamed_passenger_value' => $result['vPAForUnnamedPassengerPremium'] ?? 0,
                        'seating_capacity' => $premiumResponse['generalInformation']['seatingCapacity'],
                        'default_paid_driver' => (int) ($paidDriver + $llCC),
                        'll_paid_driver_premium' => (int) ($paidDriver),
                        'll_paid_conductor_premium' => (int) ($llCC),
                        'll_paid_cleaner_premium' => 0,
                        'motor_additional_paid_driver' => 0,
                        'compulsory_pa_own_driver' => round($paCoverForOwnerDriver),
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' => round($totalOdPremium),
                        /* 'cng_lpg_tp' => round($biFuelKitTP), */
                        'total_liability_premium' => 0,
                        'net_premium' => $final_net_premium,
                        'service_tax_amount' => $totalTax,
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'voluntary_excess' => 0,
                        'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'tppd_discount' => (int) $tppd_discount,
                        'quotation_no' => '',
                        'premium_amount' => round($final_premium),
                        'antitheft_discount' => round($antiTheftDiscount),
                        'final_od_premium' => round($totalOdPremium),
                        'final_tp_premium' => round($totalTpPremium),
                        'final_total_discount' =>  (!in_array($premium_type,['third_party','third_party_breakin'])) ? round($totalDiscount + $bonusDiscount + $tppd_discount) : round($totalDiscount + $bonusDiscount + $tppd_discount),
                        'final_net_premium' => round($final_premium),
                        'final_gst_amount' => round($totalTax),
                        'final_payable_amount' => round($final_premium),
                        'service_data_responseerr_msg' => $premiumResponse['status'],
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => $business_type,
                        'service_err_code' => NULL,
                        'service_err_msg' => NULL,
                        'policyStartDate' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : Carbon::parse($policyStartDate->toDateString())->format('d-m-Y'),
                        'policyEndDate' => $requestData->previous_policy_type == 'Not sure' ? '' : Carbon::parse($previousPolicyExpiryDate->toDateString())->format('d-m-Y'),
                        'ic_of' => $productData->company_id,
                        'vehicle_in_90_days' => '0',
                        'get_policy_expiry_date' => NULL,
                        'get_changed_discount_quoteid' => 0,
                        'GeogExtension_ODPremium' => 0,
                        'GeogExtension_TPPremium' => 0,
                        'LimitedtoOwnPremises_OD' => round($own_premises_od),
                        'LimitedtoOwnPremises_TP' => round($own_premises_tp),
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
                        "applicable_addons"=> $applicable_addons,
                        'add_ons_data' =>   $addon_data
                    ]
                ];



                if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                    $data_response['Data']['premiumTypeCode'] = $premium_type;
                }

                if($imT23OD <= 0)
                {
                    unset($data_response['Data']['add_ons_data']['additional']['imt23']);
                }
                

                if(!empty($lpgAndCng['status']))
                {
                    $data_response['Data']['motor_lpg_cng_kit_value'] = $biFuelKitOD;
                    $data_response['Data']['cng_lpg_tp'] = round($biFuelKitTP);
                }
                return camelCase($data_response);
            } else {
                return [
                    'status' => false,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'message' => isset($premiumResponse['status']) && $premiumResponse['status'] == false ? (isset($premiumResponse['message']) ? $premiumResponse['message'] : 'Insurer not reachable') : "Insurer not reachable"
                ];
            }
        } else {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => "Insurer not reachable"
            ];
        }
    } else {
        return [
            'status' => false,
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'message' => "Insurer not reachable"
        ];
    }
}


