<?php

use Carbon\Carbon;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;


include_once app_path() . '/Quotes/Bike/V2/tata_aig.php';
include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
function getQuote($enquiryId, $requestData, $productData)
{

    if(config('IC.TATA_AIG.V2.BIKE.ENABLE') == 'Y')
    {
      return getV2Quote($enquiryId, $requestData, $productData);
    }
      
    $refer_webservice = $productData->db_config['quote_db_cache'];              
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
    $is_indivisual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

    $is_breakin     = ($requestData->business_type != "breakin" ? false : true);

    $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
    $zero_dep_age_available = true;

    if (!$is_new && $requestData->previous_policy_type == 'Not sure') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Previous policy details are mandatory',
            'request'=>[
                'previous_policy_type'=> $requestData->previous_policy_type,
                'request_data'=>$requestData
            ]
        ];
    }

    if (empty($requestData->rto_code)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO not available',
            'request'=>[
                'rto_code'=>$requestData->rto_code,
                'request_data'=>$requestData
            ]
        ];
    }
    
    // if(isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y')
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

    $mmv = get_mmv_details($productData, $requestData->version_id, 'tata_aig');
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return    [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request'=>[
               'mmv'=> $mmv,
               'version_id'=>$requestData->version_id
            ]
        ];
    }
    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ]);
    } elseif ($mmv->ic_version_code == 'DNE') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ]);
    }

    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();

    $DepreciationReimbursement = $RoadsideAssistance = $ac_opted_in_pp = "N";

    $ConsumablesExpenses = 'N';
    $ReturnToInvoice = 'N';

    if($is_zero_dep)
    {
        $DepreciationReimbursement = 'Y';
        // $RoadsideAssistance = 'Y';
        // $ConsumablesExpenses = 'Y';
        $NoOfClaimsDepreciation = '2';
    }
    else
    {
        $DepreciationReimbursement = 'N';
        // $RoadsideAssistance = 'Y';
        // $ConsumablesExpenses = 'Y';
        $NoOfClaimsDepreciation = '0';
    }


    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $Electricalaccess = $ElectricalaccessSI = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassenger = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccess = $NonElectricalaccessSI = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $llpaidDriver = $llpaidDriverSI = $is_voluntary_access = $is_anti_theft = "N";
    $is_tppd = 'N';
    $tppd_amt = 0;
    $voluntary_excess_amt = '';

    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value)) {
            $Electricalaccess = "Y";
            $ElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('Non-Electrical Accessories', $value)) {
            $NonElectricalaccess = "Y";
            $NonElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
            $externalCNGKIT = "Y";
            $externalCNGKITSI = $value['sumInsured'];
            if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                $mmv->txt_fuel = 'External CNG';
                $mmv->txt_fuelcode = '5';
            }
        }

        if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
            $PAPaidDriverConductorCleaner = "Y";
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        }
    }

    foreach ($additional_covers as $key => $value) {
        if (in_array('PA cover for additional paid driver', $value)) {
            $PAforaddionaldPaidDriver = "Y";
            $PAforaddionaldPaidDriverSI = $value['sumInsured'];
        }

        if (in_array('Unnamed Passenger PA Cover', $value)) {
            $PAforUnnamedPassenger = "Y";
            $PAforUnnamedPassengerSI = $value['sumInsured'];
        }

        if (in_array('LL paid driver', $value)) {
            $llpaidDriver = "Y";
            $llpaidDriverSI = $value['sumInsured'];
        }
    }

    foreach ($discounts as $key => $data) {
        if ($data['name'] == 'anti-theft device' && !$is_liability) {
            $is_anti_theft = 'Y';
            $is_anti_theft_device_certified_by_arai = 'true';
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
            $is_voluntary_access = 'Y';
            $voluntary_excess_amt = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover' && !$is_od) {
            $is_tppd = 'Y';
            $tppd_amt = '9999';
        }
    }

    // addon

    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = $interval->y;//floor($age / 12);

    if(!$is_liability && ($requestData->previous_policy_type == 'third_party'))
    {

        return ['premium_amount' => 0, 'status' => true, 'message' => 'Liability policies can be renew offline only.'];
    }

    $rollover_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 28 days - 8 months - 15 year')) : date('d-m-Y');
    if (
        !$is_new
        &&
            !(strtotime($rollover_age_limit) <= strtotime($requestData->vehicle_register_date))
    )
    {
        if(!$is_liability)
        {
            return [
                'premium_amount' => 0,
                'status' => true,
                'message' => 'Bike Age greater than 15 years 8 months 28 days',
                'request' => [
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'rollover_age_limit' => $rollover_age_limit
                ]
            ];
        }
    }
    // if (!$is_liability && $vehicle_age > 10) {
    //     return ['premium_amount' => 0, 'status' => false, 'message' => 'Bike Age greater than 10 years'];
    // }

    $tp_rollover_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 28 days - 8 months - 20 year')) : date('d-m-Y');
    if (
        !$is_new
        &&
            !(strtotime($tp_rollover_age_limit) <= strtotime($requestData->vehicle_register_date))
    )
    {
        return [
            'premium_amount' => 0,
            'status' => true,
            'message' => 'Bike Age greater than 20 years 8 months 28 days',
            'request' => [
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'rollover_age_limit' => $tp_rollover_age_limit
            ]
        ];
    }

    if ($vehicle_age > 20) {
        return ['premium_amount' => 0, 'status' => false, 'message' => 'Bike Age greater than 20 years'];
    }

    $zero_dep_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 28 days - 8 months - 3 year')) : date('d-m-Y');
    if (
        !$is_new
        &&
            strtotime($zero_dep_age_limit) > strtotime($requestData->vehicle_register_date)
    )
    {
        if($is_zero_dep)
        {
            return [
                'premium_amount' => 0,
                'status' => true,
                'message' => 'Zero dep is not allowed for vehicle age greater than 3 years',
                'request' => [
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'zero_dep_age_limit' => $zero_dep_age_limit
                ]
            ];
        }
        $zero_dep_age_available = false;
    }

    if (!$requestData->applicable_ncb > 25) {
        $NCBProtectionCover = "N";
    }

    $vehicle_in_90_days = 0;

    $motor_manf_date = '01-' . $requestData->manufacture_year;

    $current_date = date('Y-m-d');

    if($is_new){
        $policy_start_date  = date('Ymd', strtotime($requestData->vehicle_register_date));
        if($is_liability){
            $policy_start_date  = date('Ymd', strtotime($requestData->vehicle_register_date. '+ 1day'));
        }
        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 5 year'));
    }
    else{
        $datetime2 = new DateTime($current_date);
        $datetime1 = new DateTime($requestData->previous_policy_expiry_date);
        $intervals = $datetime1->diff($datetime2);
        $difference = $intervals->invert;

        $policy_start_date = date('Ymd', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

        if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
            $policy_start_date = date('Ymd', strtotime('+2 day', time()));
            if($is_liability)
            {
                $policy_start_date = date('Ymd', strtotime('+1 day', time()));
            }
        }

        $policy_end_date = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));

    }

    $mmv_data = [
        'manf_name' => $mmv->manufacturer,
        'model_name' => $mmv->vehiclemodel,
        'version_name' => $mmv->txt_varient,
        'seating_capacity' => $mmv->seatingcapacity,
        'carrying_capacity' => $mmv->seatingcapacity - 1,
        'cubic_capacity' => $mmv->cubiccapacity,
        'fuel_type' =>    $requestData->fuel_type,
        'gross_vehicle_weight' => $mmv->grossvehicleweight,
        'vehicle_type' => 'BIKE',
        'version_id' => $mmv->ic_version_code,
        'kw' => $mmv->cubiccapacity,
    ];

    $customer_type = $requestData->vehicle_owner_type == "I" ? "Individual" : "Organization";

    $btype_code = ($requestData->business_type == "rollover" || $is_breakin) ? "2" : "1";
    $btype_name = ($requestData->business_type == "rollover" || $is_breakin) ? "Roll Over" : "New Business";

    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
        $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
    } else {
        $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
    }

    if($zero_dep_age_available){
        $ReturnToInvoice = 'Y';
        $RoadsideAssistance = 'Y';
    }

    $ConsumablesExpenses = ($vehicle_age < 4) ?  'Y' : 'N';

    if($is_liability){
        $DepreciationReimbursement = 'N';
        $NoOfClaimsDepreciation = '0';
        $RoadsideAssistance = 'N';
        $ac_opted_in_pp = 'N';

        $ConsumablesExpenses = 'N';
        $ReturnToInvoice = 'N';
    }

    if($is_od && $is_breakin)
    {
        $RoadsideAssistance = 'N';
        $ConsumablesExpenses = 'N';
        $ReturnToInvoice = 'N';
    }
    // return $requestData;

    $rto_code = $requestData->rto_code;  
    $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code 

    $rto_location = DB::table('tata_aig_vehicle_rto_location_master')->where('txt_rto_location_code', $rto_code)->first();
    // quick Quote Service

    // return (array)$mmv;

    $is_pos     = (config('constants.IcConstants.INCLUDE_POS_ON_QUOTE_PAGE') == 'Y') ? config('constants.motorConstant.IS_POS_ENABLED') : 'N';#config('constants.motorConstant.IS_POS_ENABLED');

    $pos_aadhar = '';
    $pos_pan    = '';
    $sol_id     = config('constants.IcConstants.tata_aig.SOAL_ID');

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_TATA_AIG') == 'Y')
        {
            $sol_id = '5415415411';
        }
        else if($pos_data)
        {
            $pos_aadhar = $pos_data->aadhar_no;
            $pos_pan    = $pos_data->pan_no;
            $sol_id     = $pos_data->pan_no;
        }
    }
    else
    {
        $is_pos = 'N';
    }

    $input_array_info =    [
        "quotation_no" => "",
        "segment_code" => $mmv->txt_segmentcode,
        "segment_name" => $mmv->txt_segmenttype,
        "cc" => $mmv->cubiccapacity,
        "sc" => $mmv->seatingcapacity,
        "sol_id" => $sol_id,
        "lead_id" => "",
        "mobile_no" => "",
        "email_id" => "",
        "emp_email_id" => "",
        "customer_type" => ($is_indivisual ? 'Individual' : 'Organization'), //"Individual",
        "product_code" => config('constants.IcConstants.tata_aig.bike.PRODUCT_CODE'),
        "product_name" => "Two Wheeler",
        "subproduct_code" => config('constants.IcConstants.tata_aig.bike.SUB_PRODUCT_CODE'),
        "subproduct_name" => "Two Wheeler",
        "subclass_code" => "",
        "subclass_name" => "",
        "covertype_code" => (($is_package) ? '1' : (($is_liability) ? '2' : '3')),
        "covertype_name" => (($is_package) ? 'Package' : (($is_liability) ? 'Liability' : 'Standalone Own Damage')),
        'btype_code'         => ($is_od ? '1' : '2'),
        'btype_name'         => $is_od ? 'New Business' : $btype_name,
        "risk_startdate" => $policy_start_date,
        "risk_enddate" => $policy_end_date,
        "purchase_date" => Carbon::parse($vehicleDate)->format('Ymd'),
        "veh_age" => $vehicle_age,
        "manf_year" => explode('-', $requestData->manufacture_year)[1],
        "make_code" => $mmv->manufacturercode,
        "make_name" => $mmv->manufacturer,
        "model_code" => $mmv->num_parent_model_code,
        "model_name" => $mmv->vehiclemodel,
        "variant_code" => $mmv->vehiclemodelcode,
        "variant_name" => $mmv->txt_varient,
        "model_parent_code" => $mmv->num_parent_model_code,
        "fuel_code" => $mmv->txt_fuelcode,
        "fuel_name" => $mmv->txt_fuel,
        "gvw" => "",
        "age" => "",
        "miscdtype_code" => "",
        "bodytype_id" => "34",
        "idv" => "",
        "revised_idv" => "",
        "regno_1" => $vehicle_register_no[0] ?? '',
        "regno_2" => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")),// (string)($vehicle_register_no[1] ?? ""), 
        "regno_3" => $vehicle_register_no[2] ?? '',
        "regno_4" => $vehicle_register_no[3] ?? '',
        "rto_loc_code" => $rto_location->txt_rto_location_code,
        "rto_loc_name" => $rto_location->txt_rto_location_desc,
        "rtolocationgrpcd" => (string) $rto_location->txt_rto_location_grp_cd,
        "rto_zone" => $rto_location->txt_registration_zone,
        "rating_logic" => "Campaign", //"Campaign",
        "campaign_id" => "",
        "fleet_id" => "",
        "discount_perc" => "",

        // "pp_covertype_code" => (($requestData->previous_policy_type == 'Comprehensive') ? '1' : '2'),
        // "pp_covertype_name" => (($requestData->previous_policy_type == 'Comprehensive') ? 'Package' : 'Liability'),
        // "pp_enddate" => Carbon::parse($requestData->previous_policy_expiry_date)->format('Ymd'),
        // "pp_claim_yn" => $requestData->is_claim,
        // "pp_prev_ncb" => (($is_liability) ? '0' : $requestData->previous_ncb),
        // "pp_curr_ncb" => (($is_liability) ? '0' : $requestData->applicable_ncb),

        "pp_covertype_code" => ($is_new ? '' : (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? '1' : '2')),
        "pp_covertype_name" => ($is_new ? '' : (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Package' : 'Liability')),
        "pp_enddate"        => ($is_new ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Ymd')),
        "pp_claim_yn"       => ($is_new ? '' : $requestData->is_claim),
        "pp_prev_ncb"       => ($is_new ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
        "pp_curr_ncb"       => ($is_new ? '' : (($is_liability) ? '0' : $requestData->applicable_ncb)),
        "ac_opted_in_pp"    => ($is_new ? '' : 'Y'),

        "ac_opted_in_pp" => "Y",
        "addon_plan_code" => "",
        "addon_choice_code" => "",
        "cust_name" => "",
        "ab_cust_id" => "",
        "ab_emp_id" => "",
        "usr_name" => "",
        "producer_code" => "",
        "pup_check" => $is_pos,
        "pos_panNo" => $pos_pan,
        "pos_aadharNo" => $pos_aadhar,
        "is_cust_JandK" => "NO",
        "cust_pincode" => "400002",
        "cust_gstin" => "",
        "tenure" => (($is_new && $is_liability) ? '5' : '1'),
        "uw_discount" => "",
        "Uw_DisDb" => "",
        "uw_load" => "",
        "uw_loading_discount" => "",
        "uw_loading_discount_flag" => "",
        "engine_no" => "",
        "chasis_no" => "",
        'tppolicytype' => ($is_od ? 'Comprehensive Package' : ''),
        'tppolicytenure' => ($is_od ? '5' : ''),
        'driver_declaration' => ($is_od ? 'ODD01' : ''),
    ];
    $input_array = [
        "functionality" => "validatequote",
        "quote_type" => "quick",
        "vehicle" => $input_array_info,
        "cover" => [
            "C1" => [
                "opted" => (($is_liability) ? 'N' : 'Y')
            ],
            "C2" => [
                "opted" => ($is_od ? 'N' : 'Y')
            ],
            "C3" => [
                "opted" => (($is_indivisual && !$is_od) ? 'Y' : 'N')
            ]
        ]
    ];

    $temp_data = $input_array;
    if(!empty($vehicle_register_no[3] ?? '')){
        unset($temp_data['vehicle']['regno_4']);
    }
    $checksum_data = checksum_encrypt($temp_data);
    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'tata_aig',$checksum_data,'BIKE');
    // quick quote service input

    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [],
        'requestMethod' => 'post',
        'requestType' => 'json',
        'section' => $productData->product_sub_type_code,
        'method' => 'Premium Calculation',
        'transaction_type' => 'quote',
        'checksum'  => $checksum_data,
        'productName' => $productData->product_name,
    ];

    $inputArray = [
        'QDATA' => json_encode($input_array),
        'SRC' => config('constants.IcConstants.tata_aig.SRC'),
        'T' => config('constants.IcConstants.tata_aig.TOKEN'),
        'productid' => config('constants.IcConstants.tata_aig.bike.PRODUCT_CODE'),
    ];

    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_response = $is_data_exist_for_checksum;
    }else{
        $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE'), $inputArray, 'tata_aig', $additional_data);
    }

    $response = $get_response['response'];
    if (empty($response)) {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Insurer not reachable',
        ];
    }

    $response = json_decode($response, true);
    
    $skip_second_call = false;

    if($response == NULL){
        return camelCase([
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Insurer Not Reachable',
        ]);
    }

    if(!isset($response['data']['quotationdata'])){
        return camelCase([
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => $response['data']['message'],
        ]);
    }

    if($response){
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Premium Calculation Success", "Success" );
    }
    // pass idv
    $input_array_info['idv'] = $response['data']['quotationdata']['idv'];
    $input_array_info['revised_idv'] = $response['data']['quotationdata']['revised_idv'];
    if ($requestData->is_idv_changed == "Y") {

        if ($requestData->edit_idv >= $response['data']['quotationdata']['idvupperlimit']) {
            $input_array_info['idv'] = ($response['data']['quotationdata']['idvupperlimit']);
            $input_array_info['revised_idv'] = ($response['data']['quotationdata']['idvupperlimit']);
        } else if ($requestData->edit_idv <= $response['data']['quotationdata']['idvlowerlimit']) {
            $input_array_info['idv'] = ($response['data']['quotationdata']['idvlowerlimit']);
            $input_array_info['revised_idv'] = ($response['data']['quotationdata']['idvlowerlimit']);
        } else {
            $input_array_info['idv'] = ($requestData->edit_idv);
            $input_array_info['revised_idv'] = ($requestData->edit_idv);
        }
    } else {

        $getIdvSetting = getCommonConfig('idv_settings');
        switch ($getIdvSetting) {
            case 'default':
                $skip_second_call = true;
                $input_array_info['idv'] = $response['data']['quotationdata']['idv'];
                $input_array_info['revised_idv'] = $response['data']['quotationdata']['revised_idv'];
                break;
            case 'min_idv':
                $input_array_info['idv'] = ($response['data']['quotationdata']['idvlowerlimit']);
                $input_array_info['revised_idv'] = ($response['data']['quotationdata']['idvlowerlimit']);
                break;
            case 'max_idv':
                $input_array_info['idv'] = ($response['data']['quotationdata']['idvupperlimit']);
                $input_array_info['revised_idv'] = ($response['data']['quotationdata']['idvupperlimit']);
                break;
            default:
                $input_array_info['idv'] = ($response['data']['quotationdata']['idvlowerlimit']);
                $input_array_info['revised_idv'] = ($response['data']['quotationdata']['idvlowerlimit']);
                break;
        }

        /* $input_array_info['idv'] = ($response['data']['quotationdata']['idvlowerlimit']);
        $input_array_info['revised_idv'] = ($response['data']['quotationdata']['idvlowerlimit']); */
    }
    // pass idv

    //full quote service input
    $input_array = [
        "functionality" => "validatequote",
        "quote_type" => "full",
        "vehicle" => $input_array_info,
        "cover" => [
            "C1" => [
                "opted" => (($is_liability) ? 'N' : 'Y')
            ],
            "C2" => [
                "opted" => ($is_od ? 'N' : 'Y')
            ],
            "C3" => [
                "opted" => (($is_indivisual && !$is_od) ? 'Y' : 'N')
            ],
            "C4" => [
                "opted" => (!$is_liability ? $Electricalaccess : 'N'),
                "SI"    => (!$is_liability ? $ElectricalaccessSI : '0')
            ],
            "C5" => [
                "opted" => (!$is_liability ? $NonElectricalaccess : 'N'),
                "SI"    => (!$is_liability ? $NonElectricalaccessSI : '0')
            ],
            "C6" => [
                "opted" => "N",
                "SI" => ""
            ],
            "C7" => [
                "opted" => "N",
                "SI" => ""
            ],
            "C8" => [
                "opted" => "N"
            ],
            "C10" => [
                "opted" => 'N',//$is_voluntary_access,
                "SI" => ''
            ],
            "C11" => [
                "opted" => $is_anti_theft
            ],
            "C12" => [
                "opted" => $is_tppd
            ],
            "C13" => [
                "opted" => "N"
            ],
            "C14" => [
                "opted" => "N"
            ],
            "C15" => [
                "opted" => "N",
                "perc" => ""
            ],
            "C17" => [
                "opted" => (!$is_od ? $PAforUnnamedPassenger : 'N'),
                "SI" => (!$is_od ? $PAforUnnamedPassengerSI : '0'),
                "persons" => (!$is_od ? $mmv->seatingcapacity : '0')
            ],
            "C18" => [
                "opted" => (!$is_od ? $llpaidDriver : 'N'),
                "persons" => (!$is_od ? '1' : '0'),
            ],
            "C29" => [
                "opted" => "N",
            ],
            "C35" => [
                "opted" => $DepreciationReimbursement,
                "no_of_claims" => $NoOfClaimsDepreciation,
                "Deductibles" => "0"
            ],
            "C37" => [
                "opted" => $ConsumablesExpenses,
            ],
            "C38" => [
                "opted" => $ReturnToInvoice,
            ],
            "C39" => [
                "opted" => "N",
            ],
            "C40" => [
                "opted" => "N",
            ],
            "C41" => [
                "opted" => "N",
            ],
            "C42" => [
                "opted" => "N"
            ],
            "C43" => [
                "opted" => "N",
            ],
            "C44" => [
                "opted" => "N",
            ],
            "C45" => [
                "opted" => "N",
            ],
            "C47" => [
                "opted" => $RoadsideAssistance
            ],
            "C48" => [
                "opted" => "N", // $EmergTrnsprtAndHotelExpense,
                "SI" => null
            ],
            "C49" => [
                "opted" => 'N',
                "SI" => ''
            ],
            "C50" => [
                "opted" => "N", //$PAforaddionaldPaidDriver,
                "SI" => "", //$PAforaddionaldPaidDriverSI
            ],
            "C51" => [
                "opted" => "N",
                "SI" => NULL,
            ]
        ]
    ];

    $temp_data = $input_array;
    if(!empty($vehicle_register_no[3] ?? '')){
        unset($temp_data['vehicle']['regno_4']);
    }
    $checksum_data = checksum_encrypt($temp_data);
    $additional_data['checksum'] = $checksum_data;
    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'tata_aig',$checksum_data,'BIKE');

    $inputArray = [
        'QDATA' => json_encode($input_array),
        'SRC' => config('constants.IcConstants.tata_aig.SRC'),
        'T' => config('constants.IcConstants.tata_aig.TOKEN'),
        'productid' => config('constants.IcConstants.tata_aig.bike.PRODUCT_CODE'),
    ];

    $additional_data['method'] = 'Premium Calculation - Full Quote';

    if(!$skip_second_call) {
        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
            $get_response = $is_data_exist_for_checksum;
        }else{
            $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE'), $inputArray, 'tata_aig', $additional_data);
        }
    }

    $response = $get_response['response'];
    if (empty($response)) {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Insurer not reachable',
        ];
    }

    $response = json_decode($response, true);

    if ($response['data']['status'] == '0') {
        return camelCase([
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => $response['data']['message'],
        ]);
    }

    if($response){
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Premium Calculation - Full Quote Success", "Success" );
    }

    if ($is_zero_dep) {
        $add_on_data = [
            'in_built' => [
                'zero_depreciation' => isset($response['data']['C35']) ? ($response['data']['C35']['premium']) : 0,
            ],
            'additional' => [
                'road_side_assistance' => isset($response['data']['C47']) ? ($response['data']['C47']['rate']) : 0,
                'return_to_invoice' => isset($response['data']['C38']) ? ($response['data']['C38']['premium']) : 0,
                'consumables' => isset($response['data']['C37']) ? ($response['data']['C37']['premium']) : 0,
             ],
            'other' => []
        ];
    }else{
        $add_on_data = [
            'in_built'      => [],
            'additional'    => [
                'zero_depreciation'         => isset($response['data']['C35']) ? ($response['data']['C35']['premium']) : 0,
                'road_side_assistance'      => isset($response['data']['C47']) ? ($response['data']['C47']['rate']) : 0,
                'return_to_invoice' => isset($response['data']['C38']) ? ($response['data']['C38']['premium']) : 0,
                'consumables' => isset($response['data']['C37']) ? ($response['data']['C37']['premium']) : 0,
            ],
            'other'         => []
        ];
    }

    if($is_liability){
        $add_on_data = [
            'in_built'      => [],
            'additional'    => [
                'zero_depreciation'         => 0,
                'road_side_assistance'      => 0,
                'return_to_invoice' => 0,
                'consumables' => 0,
            ],
            'other'         => []
        ];
    }

    $in_built_premium = 0;
    foreach ($add_on_data['in_built'] as $key => $value) {
        $in_built_premium = $in_built_premium + $value;
    }

    $additional_premium = 0;
    foreach ($add_on_data['additional'] as $key => $value) {
        $additional_premium = $additional_premium + $value;
    }

    $other_premium = 0;
    foreach ($add_on_data['other'] as $key => $value) {
        $other_premium = $other_premium + $value;
    }

    $add_on_data['in_built_premium'] = $in_built_premium;
    $add_on_data['additional_premium'] = $additional_premium;
    $add_on_data['other_premium'] = $other_premium;

    $applicable_addons = [
        'zeroDepreciation',
        'roadSideAssistance',
        'consumables',
        'returnToInvoice'
    ];

    if (!$zero_dep_age_available) {
        array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
        array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
        array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
    }

    if(!($vehicle_age < 4))
    {
        array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
    }

    $final_total_discount = (isset($response['data']['C15']) ? ($response['data']['C15']['premium']) : 0) + (isset($response['data']['C11']) ? ($response['data']['C11']['premium']) : 0) + (isset($response['data']['C10']) ? ($response['data']['C10']['premium']) : 0) + (isset($response['data']['C12']) ? ($response['data']['C12']['premium']) : 0);

    $final_tp_premium = (isset($response['data']['C2']) ? ($response['data']['C2']['premium']) : 0) + (isset($response['data']['C17']) ? ($response['data']['C17']['premium']) : 0) + (isset($response['data']['C18']) ? ($response['data']['C18']['premium']) : 0);

    $total_od_premium = (isset($response['data']['C1']) ? ($response['data']['C1']['premium']) : 0) + (isset($response['data']['C4']) ? ($response['data']['C4']['premium']) : 0) + (isset($response['data']['C5']) ? ($response['data']['C5']['premium']) : 0) + (isset($response['data']['C7']) ? ($response['data']['C7']['premium']) : 0);

    if($premium_type == 'third_party_breakin')
    {
        $premium_type = 'third_party';
    }

    $data_response = [
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'status' => true,
        'msg' => 'Found',
        'premium_type' => $premium_type,
        'Data' => [
            'idv' => $premium_type == 'third_party' ? 0 : ($response['data']['quotationdata']['revised_idv']),
            'min_idv' => $premium_type == 'third_party' ? 0 : ($response['data']['quotationdata']['idvlowerlimit']),
            'max_idv' => $premium_type == 'third_party' ? 0 : ($response['data']['quotationdata']['idvupperlimit']),
            'qdata' => NULL,
            'pp_enddate' => ($is_new ? '' : $requestData->previous_policy_expiry_date),
            'addonCover' => NULL,
            'addon_cover_data_get' => '',
            'rto_decline' => NULL,
            'rto_decline_number' => NULL,
            'mmv_decline' => NULL,
            'mmv_decline_name' => NULL,
            'policy_type' => (($is_package) ? 'Comprehensive' : (($is_liability) ? 'Third Party' : 'Own Damage')),
            'cover_type' => '1YC',
            'hypothecation' => '',
            'hypothecation_name' => "", //$premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
            'vehicle_registration_no' => $requestData->rto_code,
            'voluntary_excess' => isset($response['data']['C10']) ? ($response['data']['C10']['rate']) : 0,
            'version_id' => $requestData->version_id,
            'selected_addon' => [],
            'showroom_price' => $premium_type == 'third_party' ? 0 : $response['data']['quotationdata']['idv'],
            'fuel_type' => $requestData->fuel_type,
            'vehicle_idv' => $premium_type == 'third_party' ? 0 : $response['data']['quotationdata']['idv'],
            'ncb_discount' => isset($response['data']['C15']) ? ($response['data']['C15']['rate']) : 0,
            'company_name' => $productData->company_name,
            'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
            'product_name' => $productData->product_name,
            'mmv_detail' => $mmv_data,
            'master_policy_id' => [
                'policy_id' => $productData->policy_id,
                'policy_no' => $productData->policy_no,
                'policy_start_date' => $policy_start_date,
                'policy_end_date' => $policy_end_date,
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
                'predefine_series' => "",
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online
            ],
            'motor_manf_date' => $motor_manf_date,
            'vehicle_register_date' => $requestData->vehicle_register_date,
            'vehicleDiscountValues' => [
                'master_policy_id' => $productData->policy_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'segment_id' => 0,
                'rto_cluster_id' => 0,
                'car_age' => $response['data']['quotationdata']['veh_age'],
                'aai_discount' => 0,
                'ic_vehicle_discount' => '', // ($insurer_discount)
            ],
            'ic_vehicle_discount' => 0,
            'basic_premium' => isset($response['data']['C1']) ? ($response['data']['C1']['premium']) : 0,
            'deduction_of_ncb' => isset($response['data']['C15']) ? ($response['data']['C15']['premium']) : 0,
            'tppd_premium_amount' => isset($response['data']['C2']) ? ($response['data']['C2']['premium']) : 0,
            'seating_capacity' => $mmv->seatingcapacity,
            'GeogExtension_ODPremium' => 0,
            'GeogExtension_TPPremium' => 0,
            'compulsory_pa_own_driver' => isset($response['data']['C3']) ? ($response['data']['C3']['premium']) : 0,
            'total_accessories_amount(net_od_premium)' => "", //$total_accessories_amount,
            'total_own_damage' => $total_od_premium, //$final_od_premium,
            'total_liability_premium' => $final_tp_premium,
            'net_premium' => isset($response['data']['NETPREM']) ? ($response['data']['NETPREM']) : 0, //$result['result']['plans'][0]['tenures'][0]['premium']['net']['value'],
            'service_tax_amount' => "", //$result['result']['plans'][0]['tenures'][0]['premium']['gst']['value'],
            'service_tax' => 18,
            'total_discount_od' => 0,
            'add_on_premium_total' => 0,
            'addon_premium' => 0,
            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
            'quotation_no' => '',
            'premium_amount' => '', //$result['result']['plans'][0]['tenures'][0]['premium']['gross']['value'],
            'final_od_premium' => $total_od_premium,
            'final_tp_premium' => $final_tp_premium,
            'final_total_discount' =>    $final_total_discount,
            'final_net_premium' => ($response['data']['NETPREM']) ?? 0,
            'final_gst_amount' => ($response['data']['TAX']['total_prem']) ?? 0,
            'final_payable_amount' => ($response['data']['TOTALPAYABLE']) ?? 0,
            'service_data_responseerr_msg' => '',
            'user_id' => $requestData->user_id,
            'product_sub_type_id' => $productData->product_sub_type_id,
            'user_product_journey_id' => $requestData->user_product_journey_id,
            'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
            'service_err_code' => NULL,
            'service_err_msg' => NULL,
            'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
            'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
            'ic_of' => $productData->company_id,
            'vehicle_in_90_days' => $vehicle_in_90_days,
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
            'add_ons_data' =>    $add_on_data,
            'applicable_addons' => $applicable_addons,
        ]
    ];

    if($externalCNGKIT == 'Y')
    {
        $data_response['Data']['cng_lpg_tp'] = (isset($response['data']['C29']) ? ($response['data']['C29']['premium']) : 0);
    }
    if($llpaidDriver == 'Y')
    {
        $data_response['Data']['default_paid_driver'] = (isset($response['data']['C18']) ? ($response['data']['C18']['premium']) : 0);
    }
    if($PAforUnnamedPassenger == 'Y')
    {
        $data_response['Data']['cover_unnamed_passenger_value'] = (isset($response['data']['C17']) ? ($response['data']['C17']['premium']) : 0);
    }

    if($is_anti_theft == 'Y')
    {
        $data_response['Data']['antitheft_discount'] = (isset($response['data']['C11']) ? ($response['data']['C11']['premium']) : 0);
    }
    if($is_tppd == 'Y')
    {
        $data_response['Data']['tppd_discount'] = (isset($response['data']['C12']) ? ($response['data']['C12']['premium']) : 0);
    }
    if($is_voluntary_access == 'Y')
    {
        $data_response['Data']['voluntary_excess'] = (isset($response['data']['C10']) ? ($response['data']['C10']['rate']) : 0);
    }
    if($Electricalaccess == 'Y')
    {
        $data_response['Data']['motor_electric_accessories_value'] = (isset($response['data']['C4']) ? ($response['data']['C4']['premium']) : 0);
    }
    if($NonElectricalaccess == 'Y')
    {
        $data_response['Data']['motor_non_electric_accessories_value'] = (isset($response['data']['C5']) ? ($response['data']['C5']['premium']) : 0);
    }
    if($externalCNGKIT == 'Y')
    {
        $data_response['Data']['motor_lpg_cng_kit_value'] = (isset($response['data']['C7']) ? ($response['data']['C7']['premium']) : 0);
    }

    return camelCase($data_response);
}