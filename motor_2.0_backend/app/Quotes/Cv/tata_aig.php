<?php

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\PcvApplicableAddon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Proposal\Services\tataAigSubmitProposal as TATA_AIG;

include_once app_path() . '/Quotes/Cv/tata_aig_v2.php';
include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/V2/PCV/tata_aig.php';
function getQuote($enquiryId, $requestData, $productData)
{

    if( config('IC.TATA_AIG.V2.PCV.ENABLE') == 'Y' && in_array(policyProductType($productData->policy_id)->parent_id , [8]) ) {
        return getQuotes($enquiryId, $requestData, $productData);
    } 

    if (policyProductType($productData->policy_id)->parent_id == 4) {
        return getGcvV2Quotes($enquiryId, $requestData, $productData);
    } elseif ( config('TATA_AIG_V2_PCV_FLOW') == 'Y' && in_array(policyProductType($productData->policy_id)->parent_id , [6,8])) {
        return getPCVV2Quotes($enquiryId, $requestData, $productData);
    } else {
        return getPcvQuotes($enquiryId, $requestData, $productData);
    }
}

function getPcvQuotes($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
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
    $premium_type = DB::table('master_premium_type')
    ->where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();

    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);

    $is_three_months    = (($premium_type == 'short_term_3') ? true : false);
    $is_six_months      = (($premium_type == 'short_term_6') ? true : false);

    $is_indivisual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

    $is_breakin     = (
    (
    (strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')
    ) ? false
    : true);

    $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

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
            'request' => [
                'message' => 'RTO not available',
                'rto_code' => $requestData
            ]
        ];
    }
    
    $check_mmv = TATA_AIG::checkTataAigMMV($productData, $requestData->version_id);

    if(!$check_mmv['status'])
    {
        return $check_mmv;
    }

    $mmv = (object)$check_mmv['data'];

    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
    ->first();

    $DepreciationReimbursement = $ac_opted_in_pp = "N";
    if($is_zero_dep)
    {
        $DepreciationReimbursement  = 'Y';
        $NoOfClaimsDepreciation     = '2';
    }
    else
    {
        $DepreciationReimbursement  = 'N';
        $NoOfClaimsDepreciation     = '0';
    }

    if($is_liability){
        $DepreciationReimbursement = 'N';
        $NoOfClaimsDepreciation = '0';
    }

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $Electricalaccess = $ElectricalaccessSI = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassenger = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccess = $NonElectricalaccessSI = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $llpaidDriver = $llpaidDriverSI = "N";

    $is_anti_theft = "N";
    $is_anti_theft_device_certified_by_arai = "false";
    $is_tppd = 'N';
    $tppd_amt = 0;
    $is_voluntary_access = 'N';
    $voluntary_excess_amt = '';

    $txt_fuel = $mmv->txt_fuel;
    foreach ($accessories as $key => $value)
    {
        if (in_array('Electrical Accessories', $value))
        {
            $Electricalaccess = "Y";
            $ElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('Non-Electrical Accessories', $value))
        {
            $NonElectricalaccess = "Y";
            $NonElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value))
        {
            $externalCNGKIT = "Y";
            $externalCNGKITSI = $value['sumInsured'];
            if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                $mmv->txt_fuel = 'External CNG';
                $mmv->txt_fuelcode = '5';
            }
        }

        if (in_array('PA To PaidDriver Conductor Cleaner', $value))
        {
            $PAPaidDriverConductorCleaner = "Y";
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        }
    }

    $is_unnamed = false;

    foreach ($additional_covers as $key => $value)
    {
        if (in_array('PA cover for additional paid driver', $value))
        {
            $PAforaddionaldPaidDriver = "Y";
            $PAforaddionaldPaidDriverSI = $value['sumInsured'];
        }

        if (in_array('Unnamed Passenger PA Cover', $value))
        {
            $is_unnamed = true;
            $PAforUnnamedPassenger = "Y";
            $PAforUnnamedPassengerSI = $value['sumInsured'];
        }

        if (in_array('LL paid driver', $value))
        {
            $llpaidDriver = "Y";
            $llpaidDriverSI = $value['sumInsured'];
        }
    }

    foreach ($discounts as $key => $data)
    {
        if ($data['name'] == 'anti-theft device' && !$is_liability)
        {
            $is_anti_theft = 'Y';
            $is_anti_theft_device_certified_by_arai = 'true';
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured']))
        {
            $is_voluntary_access = 'Y';
            $voluntary_excess_amt = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover' && !$is_od)
        {
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
    $vehicle_age = $interval->y;

    $zero_dep_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 24 days - 8 months - 5 year')) : date('d-m-Y');
    if (
        !$is_new
        &&
            strtotime($zero_dep_age_limit) > strtotime($requestData->vehicle_register_date)
    )
    {
        $DepreciationReimbursement = 'N';
        $NoOfClaimsDepreciation = '0';
    }

    if(!$is_liability && ($requestData->previous_policy_type == 'third_party'))
    {
        return
        [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Liability policies can be renew offline only.',
            'request' => [
                'message' => 'Liability policies can be renew offline only',
                'previous_policy_typ' => $requestData->previous_policy_type
            ]
        ];
    }


    if(!$is_new && !$is_liability && ( $requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Break-In Quotes Not Allowed',
            'request' => [
                'message' => 'Break-In Quotes Not Allowed',
                'previous_policy_typ' => $requestData->previous_policy_type
            ]
        ];
    }

    $vehicle_age_till_15_allowed = (in_array($mmv->manufacturer, ['Mahindra','Maruti','Hyundai','Honda']) && $mmv->txt_fuel == 'PETROL') ? true : false;

    if ($vehicle_age >= 15) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Car Age greater than 15 years',
            'request' => [
                'message' => 'Car Age greater than 15 years',
                'vehicle_age' => $vehicle_age
            ]
        ];
    }

    if ($vehicle_age >= 10 && !$vehicle_age_till_15_allowed) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Car Age greater than 10 years',
            'request' => [
                'message' => 'Car Age greater than 10 years',
                'vehicle_age' => $vehicle_age,
                'manufacturer' => $mmv->manufacturer
            ]
        ];
    }

    if (isset($selected_addons->accessories[0]['sumInsured']) && $selected_addons->accessories[0]['sumInsured'] > 50000)
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Motor Electric/Non Electric Accessories should not be greater than 50000',
            'request' => [
                'message' => 'Motor Electric/Non Electric Accessories should not be greater than 50000',
                'accessories_amount' => $selected_addons->accessories[0]['sumInsured']
            ]
        ];
    }

    if (isset($selected_addons->accessories[1]['sumInsured']) && $selected_addons->accessories[1]['sumInsured'] > 50000)
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Motor Electric/Non Electric Accessories should not be greater than 50000',
            'request' => [
                'message' => 'Motor Electric/Non Electric Accessories should not be greater than 50000',
                'accessories_amount' => $selected_addons->accessories[1]['sumInsured']
            ]
        ];
    }

    if (isset($selected_addons->accessories[2]['sumInsured']) && $selected_addons->accessories[2]['sumInsured'] > 50000)
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Motor Electric/Non Electric Accessories should not be greater than 50000',
            'request' => [
                'message' => 'Motor Electric/Non Electric Accessories should not be greater than 50000',
                'accessories_amount' => $selected_addons->accessories[2]['sumInsured']
            ]
        ];
    }

    $vehicle_in_90_days = 0;

    $motor_manf_date = '01-' . $requestData->manufacture_year;

    $current_date = date('Y-m-d');

    if($is_new){
        $policy_start_date = date('d-m-Y', strtotime($requestData->vehicle_register_date));
        if($is_liability){
            $policy_start_date = date('d-m-Y', strtotime($requestData->vehicle_register_date . '+ 1day'));
        }
        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));
    }
    else
    {
        $policy_start_date = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

        if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date))
        {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
        }

        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));
    }

    if($is_three_months)
    {
        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 3 month'));
    }
    if($is_six_months)
    {
        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 6 month'));
    }
    $policy_start_date = date('Ymd', strtotime($policy_start_date));

    $mmv_data = [
        'manf_name'             => $mmv->manufacturer,
        'model_name'            => $mmv->vehiclemodel,
        'version_name'          => $mmv->txt_variant,
        'seating_capacity'      => $mmv->seatingcapacity,
        'carrying_capacity'     => $mmv->seatingcapacity - 1,
        'cubic_capacity'        => $mmv->cubiccapacity,
        'fuel_type'             => $txt_fuel,
        'gross_vehicle_weight'  => $mmv->grossvehicleweight,
        'vehicle_type'          => 'CAR',
        'version_id'            => $mmv->ic_version_code,
    ];

    $customer_type = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

    $btype_code = $requestData->business_type == "rollover" ? "2" : "1";
    $btype_name = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";


    $rto_code = $requestData->rto_code;  
    $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code 

    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null && !$requestData->vehicle_registration_no == 'NEW')
    {
        $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
    }
    else
    {
        $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
    }

    if(!isset($vehicle_register_no[3]) && is_numeric($vehicle_register_no[2]))
    {
        $vehicle_register_no[3] = $vehicle_register_no[2];
        $vehicle_register_no[2] = '';
    }

    $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

    $pos_aadhar = '';
    $pos_pan    = '';
    $sol_id     = config('constants.IcConstants.tata_aig.SOAL_ID');

    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id', $requestData->user_product_journey_id)
    ->where('seller_type','P')
    ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if($pos_data) {
            $pos_aadhar = $pos_data->aadhar_no;
            $pos_pan    = $pos_data->pan_no;
            $sol_id     = $pos_data->pan_no;
        }
    }
    else
    {
        $is_pos = 'N';
    }


    $rto_location = DB::table('tata_aig_vehicle_rto_location_master')
        ->where('txt_rto_location_code', $rto_code)
        ->first();

    $vehicleBodyType = DB::table('tata_aig_body_type_master')
        ->where('vehiclebodytypecode', $mmv->bodytypecode)
        ->first();

    $subproduct_name = 'Taxi';

    $subproduct_code = TATA_AIG::getSubProductName($subproduct_name);

    $mmv->txt_segmentcode = TATA_AIG::getSegmentType($mmv->txt_segmenttype);

    $mmv->txt_fuelcode = TATA_AIG::getFuelCode($mmv->txt_fuel);

    $subclass_name = 'C1A PCV 4 Wheeler not exceeding 6 passengers';
    $subclass_code = '63';

    // quick Quote Service
    $input_array_info =  [
        "quotation_no" => "",

        "sol_id"            => $sol_id,
        "lead_id"           => "",
        "mobile_no"         => "",
        "email_id"          => "",
        "emp_email_id"      => "",
        "customer_type"     => $customer_type,

        "product_code"      => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID"),
        "product_name"      => "Commercial Vehicle",
        "subproduct_code"   => $subproduct_code,
        "subproduct_name"   => $subproduct_name,
        "subclass_code"     => $subclass_code,
        "subclass_name"     => $subclass_name,

        "btype_code"        => ($is_new || $is_od) ? "1" : "2",
        "btype_name"        => ($is_new || $is_od) ? "New Business" : 'Roll Over',
        "covertype_code"    => ($is_package || $is_three_months || $is_six_months) ? "1" : "2",
        "covertype_name"    => ($is_package || $is_three_months || $is_six_months) ? 'Package' : 'Liability',

        "risk_startdate"    => $policy_start_date,
        "risk_enddate"      => $policy_end_date,
        "purchase_date"     => Carbon::parse($vehicleDate)->format("Ymd"),
        'regi_date'         => Carbon::parse($requestData->vehicle_register_date)->format('Ymd'),
        "veh_age"           => $vehicle_age,
        "manf_year"         => explode("-", $requestData->manufacture_year)[1],

        "gvw"               => "",
        "age"               => "",
        "miscdtype_code"    => "",
        "bodytype_id"       => $vehicleBodyType->vehiclebodytypecode ?? '',
        'bodytype_desc'     => $vehicleBodyType->vehiclebodytypedescription ?? '',
        'type_of_body'      => '',
        'veh_sub_body'      => '',

        "idv"               => "",
        "revised_idv"       => "",

        "segment_code"      => $mmv->txt_segmentcode,
        "segment_name"      => $mmv->txt_segmenttype,
        "cc"                => $mmv->cubiccapacity,
        "sc"                => $mmv->seatingcapacity,
        "make_code"         => $mmv->manufacturercode,
        "make_name"         => $mmv->manufacturer,
        "model_code"        => $mmv->num_parent_model_code,
        "model_name"        => $mmv->vehiclemodel,
        "variant_code"      => $mmv->vehiclemodelcode,
        "variant_name"      => $mmv->txt_variant,
        "model_parent_code" => $mmv->num_parent_model_code,
        "fuel_code"         => $mmv->txt_fuelcode,
        "fuel_name"         => $mmv->txt_fuel,

        "regno_1"           => $vehicle_register_no[0] ?? "",
        "regno_2"           => $vehicle_register_no[1] ?? "",
        "regno_3"           => $vehicle_register_no[2] ?? "",
        "regno_4"           => $vehicle_register_no[3] ?? "",

        "rto_loc_code"      => $rto_code,
        "rto_loc_name"      => $rto_location->txt_rto_location_desc,
        "rtolocationgrpcd"  => $rto_location->txt_rto_location_grp_cd,
        "rto_zone"          => $rto_location->txt_registration_zone,

        "rating_logic" => "Campaign", //"Campaign",
        "campaign_id" => "",
        "fleet_id" => "",
        "discount_perc" => "",
        "pp_covertype_code" => (($requestData->previous_policy_type == 'Comprehensive') ? '1' : '2'),
        "pp_covertype_name" => ($is_new ? '' : (($requestData->previous_policy_type == 'Comprehensive') ? 'Package' : 'Liability')),
        "pp_enddate"        => ($is_new ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Ymd')),
        "pp_claim_yn"       => ($is_new ? '' : $requestData->is_claim),
        "pp_prev_ncb"       => ($is_new ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
        "pp_curr_ncb"       => ($is_new ? '' : (($is_liability) ? '0' : $requestData->applicable_ncb)),
        "ac_opted_in_pp" => ($is_new ? '' : 'Y'),
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
        "tenure" => (($is_new && $is_liability) ? '1' : '1'),
        "uw_discount" => "",
        "Uw_DisDb" => "",
        "uw_load" => "",
        "uw_loading_discount" => "",
        "uw_loading_discount_flag" => "",
        "engine_no" => "",
        "chasis_no" => "",
        // 'tppolicytype' => ($is_od ? 'Comprehensive Package' : ''),
        // 'tppolicytenure' => ($is_od ? '3' : ''),
        'driver_declaration' => (($is_od || !$is_indivisual) ? 'ODD01' : ''),

        'basis_of_rating'     => 'Underwriting Discount',
        'driver_nominee_age'     => '40',
        'driver_nominee_name'     => 'dsdgfhfdg',
        'driver_nominee_relation'     => 'OTHERS',
        'external_built'     => '',
        'goods_normally_carry'     => 'non-hazardous',
        'misclass_code'     => '',
        'misclass_name'     => 'Select Miscellaneous Vehicle',
        'odometer_reading'    => '1',
        'rating_zone'    => 'B',
        'regi_date'    => Carbon::parse($requestData->vehicle_register_date)->format('Ymd'),
        'towed_by'    => '',
        'trailer_idv'    => '',
        'trailer_under_tow'    => '',
        'veh_cng_lpg_insured'    => 'N',
        'veh_type'    => 'indigenous',
    ];

    if($is_three_months || $is_six_months)
    {
        $input_array_info['option_for_calc'] = 'Pro Rata';
        $input_array_info['pre_policy_start_date'] = (
            $is_new
            ? ''
            : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Ymd')
        );
    }

    $temp_data = $input_array_info;
    unset($temp_data['regno_4']);
    $checksum_data = checksum_encrypt($temp_data);

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
                "opted" => (($is_indivisual && !$is_od) ? 'Y' : 'N'),
                'tenure'  => ($is_new ? '1' : ($is_indivisual ? '1' : '0'))
            ]
        ]
    ];

    $additional_data = [
        'enquiryId'         => $enquiryId,
        'headers'           => [],
        'requestMethod'     => 'post',
        'requestType'       => 'json',
        'checksum'          => $checksum_data,
        'section'           => $productData->product_sub_type_code,
        'method'            => 'Premium Calculation',
        'transaction_type'  => 'quote',
        'productName'       => $productData->product_name,
    ];

    $inputArray = [
        'QDATA'         => json_encode($input_array),
        'SRC'           => config('constants.IcConstants.tata_aig.SRC'),
        'T'             => config('constants.IcConstants.tata_aig.TOKEN'),
        'productid'     => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID"),//config('constants.IcConstants.tata_aig.PRODUCT_ID'),
    ];

    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'tata_aig', $checksum_data, 'CV');
    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_response = $is_data_exist_for_checksum;
    }else{
        $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE'), $inputArray, 'tata_aig', $additional_data);
    }
    $response = $get_response['response'];
    if(!$response)
    {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer Not Reachable',
        ]);
    }


    $response = json_decode($response, true);

    if (empty($response) || !isset($response['data']['status'])) {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer Not Reachable',
        ]);
    }

    if ($response['data']['status'] == '0')
    {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => $response['data']['message'],
        ]);
    }

    // pass idv
    $input_array_info['idv']            = $response['data']['quotationdata']['idv'];
    $input_array_info['revised_idv']    = $response['data']['quotationdata']['revised_idv'];
    $max_idv                            = $response['data']['quotationdata']['idvupperlimit'];
    $min_idv                            = $response['data']['quotationdata']['idvlowerlimit'];
    if ($requestData->is_idv_changed == "Y")
    {
        if ($requestData->edit_idv >= $max_idv)
        {
            $input_array_info['idv'] = round($max_idv);
            $input_array_info['revised_idv'] = round($max_idv);
        }
        else if ($requestData->edit_idv <= $min_idv)
        {
            $input_array_info['idv'] = round($min_idv);
            $input_array_info['revised_idv'] = round($min_idv);
        }
        else
        {
            $input_array_info['idv'] = round($requestData->edit_idv);
            $input_array_info['revised_idv'] = round($requestData->edit_idv);
        }
    }
    else
    {
        $getIdvSetting = getCommonConfig('idv_settings');
        switch ($getIdvSetting) {
            case 'default':
                $skip_second_call = true;
                $input_array_info['idv'] = $response['data']['quotationdata']['idv'];
                $input_array_info['revised_idv'] = $response['data']['quotationdata']['revised_idv'];
                break;
            case 'min_idv':
                $input_array_info['idv'] = round($min_idv);
                $input_array_info['revised_idv'] = round($min_idv);
                break;
            case 'max_idv':
                $input_array_info['idv'] = round($max_idv);
                $input_array_info['revised_idv'] = round($max_idv);
                break;
            default:
                $input_array_info['idv'] = round($min_idv);
                $input_array_info['revised_idv'] = round($min_idv);
                break;
        }
        /* $input_array_info['idv'] = round($min_idv);
        $input_array_info['revised_idv'] = round($min_idv); */
    }
    // pass idv

    $temp_data = $input_array_info;
    unset($temp_data['regno_4']);
    $checksum_data = checksum_encrypt($temp_data);
    //full quote service input
    $input_array = [
        'functionality' => 'validatequote',
        'quote_type' => 'full',
        'vehicle' => $input_array_info,
        'cover' => [
            'C1' => [
                'opted' => (($is_liability) ? 'N' : 'Y')
            ],
            'C2' => [
                'opted' => ($is_od ? 'N' : 'Y')
            ],
            'C3' => [
                'opted' => (($is_indivisual && !$is_od) ? 'Y' : 'N'),
                'tenure'  => ($is_new ? '3' : ($is_indivisual ? '1' : '0'))
            ],
            'C4' => [
                'opted' => (!$is_liability ? $Electricalaccess : 'N'),
                'SI' => (!$is_liability ? $ElectricalaccessSI : '0')
            ],
            'C5' => [
                'opted' => (!$is_liability ? $NonElectricalaccess : 'N'),
                'SI' => (!$is_liability ? $NonElectricalaccessSI : '0')
            ],
            'C7' => [
                'opted' => (!$is_liability ? $externalCNGKIT : 'N'),
                'SI' => (!$is_liability ? $externalCNGKITSI : '0'), //'10000'
            ],
            'C11' => [
                'opted' => $is_anti_theft
            ],
            'C12' => [
                'opted' => $is_tppd
            ],
            'C18' => [
                'opted' => (!$is_od ? $llpaidDriver : 'N'),
                'persons' => (!$is_od ? '1' : '0'),
            ],
            'C21' => [
                'opted' => 'N',
                'persons' => '0',
            ],
            'C22' => [
                'opted' => 'N',
                'persons' => '0',
            ],
            'C23' => [
                'opted' => 'N',
                'persons' => '0',
            ],
            'C24' => [
                'opted' => ($is_package ? 'N' : 'N'),
            ],
            'C25' => [
                'opted' => 'N',
                'persons' => '0',
            ],
            'C26' => [
                'opted' => 'N',
                'persons' => '0',
            ],
            'C29' => [
                'opted' => (!$is_od ? $externalCNGKIT : 'N')
            ],
            "C35" => [
                "opted" => $DepreciationReimbursement,
                "no_of_claims" => $NoOfClaimsDepreciation,
                "Deductibles" => "0"
            ],
            'C53' => [
                'opted' => 'N',
                'SI' => NULL,
            ]
        ]
    ];

    $inputArray = [
        'QDATA' => json_encode($input_array),
        'SRC' => config('constants.IcConstants.tata_aig.SRC'),
        'T' => config('constants.IcConstants.tata_aig.TOKEN'),
        'productid' => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID")
    ];

    $additional_data['method'] = 'Premium Calculation - Full Quote';
    $additional_data['checksum'] = $checksum_data;
    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'tata_aig', $checksum_data, 'CV');
    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_response = $is_data_exist_for_checksum;
      }
    else{
        $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE'), $inputArray, 'tata_aig', $additional_data);
        }
    $response = $get_response['response'];
    $response = json_decode($response, true);

    if (empty($response) || !isset($response['data']['status'])) {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer Not Reachable',
        ]);
    }
    if ($response['data']['status'] == '0') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => $response['data']['message'],
        ]);
    }

    $response_data =  $response['data'];


    $add_on_data = [
        'in_built'   => [],
        'additional' => [
            'zero_depreciation' => ($is_zero_dep ? (isset($response['data']['C35']) ? round($response['data']['C35']['premium']) : 0) : 0),
            'road_side_assistance' => 0,
            'consumables' => 0,
        ],
        'other' => []
    ];

    $in_built_premium = 0;
    foreach ($add_on_data['in_built'] as $key => $value) {
        if($value === 0){
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'No value for In-Built addon'
            ];
        }
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

    $applicable_addons = [];
    if($add_on_data['additional']['zero_depreciation'] !== 0)
    {
        $applicable_addons = ['zeroDepreciation'];
    }

    $final_total_discount = (isset($response_data['C15']) ? round($response_data['C15']['premium']) : 0)
        + (isset($response_data['C11']) ? round($response_data['C11']['premium']) : 0)
        + (isset($response_data['C10']) ? round($response_data['C10']['premium']) : 0)
        + (isset($response_data['C12']) ? round($response_data['C12']['premium']) : 0);

    $final_od_premium = (isset($response_data['C1']) ? round($response_data['C1']['premium']) : 0)
        + (isset($response_data['C4']) ? round($response_data['C4']['premium']) : 0)
        + (isset($response_data['C5']) ? round($response_data['C5']['premium']) : 0)
        + (isset($response_data['C7']) ? round($response_data['C7']['premium']) : 0);

    $final_tp_premium = (isset($response_data['C2']) ? round($response_data['C2']['premium']) : 0)
        + (isset($response_data['C17']) ? round($response_data['C17']['premium']) : 0)
        + (isset($response_data['C18']) ? round($response_data['C18']['premium']) : 0)
        + (isset($response_data['C29']) ? round($response_data['C29']['premium']) : 0);

    $total_payable_amount = $final_od_premium + $final_tp_premium - $final_total_discount;

    $data_response = [
        'status' => true,
        'msg' => 'Found',
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'premium_type' => $premium_type,
        'Data' => [
            'idv' => $is_liability ? 0 : round($response_data['quotationdata']['revised_idv']),
            'min_idv' => $is_liability ? 0 : round($min_idv),
            'max_idv' => $is_liability ? 0 : round($max_idv),
            'qdata' => NULL,
            'pp_enddate' => $requestData->previous_policy_expiry_date,
            'addonCover' => NULL,
            'addon_cover_data_get' => '',
            'rto_decline' => NULL,
            'rto_decline_number' => NULL,
            'mmv_decline' => NULL,
            'mmv_decline_name' => NULL,
            'policy_type' => (($is_package) ? 'Comprehensive' : (($is_liability) ? 'Third Party' : 'Short Term')),
            'cover_type' => '1YC',
            'hypothecation' => '',
            'hypothecation_name' => "", //$premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
            'vehicle_registration_no' => $requestData->rto_code,
            'voluntary_excess' => isset($response_data['C10']) ? round($response_data['C10']['rate']) : 0,
            'version_id' => $requestData->version_id,
            'selected_addon' => [],
            'showroom_price' => $premium_type == 'third_party' ? 0 : $response_data['quotationdata']['idv'],
            'fuel_type' => $requestData->fuel_type,
            'vehicle_idv' => $premium_type == 'third_party' ? 0 : $response_data['quotationdata']['idv'],
            'ncb_discount' => isset($response_data['C15']) ? round($response_data['C15']['rate']) : 0,
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
                'car_age' => $response_data['quotationdata']['veh_age'],
                'aai_discount' => 0,
                'ic_vehicle_discount' => '', // round($insurer_discount)
            ],
            'basic_premium' => isset($response_data['C1']) ? round($response_data['C1']['premium']) : 0,
            'deduction_of_ncb' => isset($response_data['C15']) ? round($response_data['C15']['premium']) : 0,
            'tppd_premium_amount' => isset($response_data['C2']) ? round($response_data['C2']['premium']) : 0,
            'tppd_discount' => isset($response_data['C12']) ? round($response_data['C12']['premium']) : 0,
            'motor_electric_accessories_value' => isset($response_data['C4']) ? round($response_data['C4']['premium']) : 0,
            'motor_non_electric_accessories_value' => isset($response_data['C5']) ? round($response_data['C5']['premium']) : 0,
            'motor_lpg_cng_kit_value' => isset($response_data['C7']) ? round($response_data['C7']['premium']) : 0,
            // 'cover_unnamed_passenger_value' => isset($response_data['C17']) ? round($response_data['C17']['premium']) : 0,
            'seating_capacity' => $mmv->seatingcapacity,
            'default_paid_driver' => isset($response_data['C18']) ? round($response_data['C18']['premium']) : 0,
            'll_paid_driver_premium' => isset($response_data['C18']) ? round($response_data['C18']['premium']) : 0,
            'll_paid_conductor_premium' => 0,
            'll_paid_cleaner_premium' => 0,
            'motor_additional_paid_driver' => isset($response_data['C50']) ? round($response_data['C50']['premium']) : 0,
            'compulsory_pa_own_driver' => isset($response_data['C3']) ? round($response_data['C3']['premium']) : 0,
            'total_accessories_amount(net_od_premium)' => "", //$total_accessories_amount,
            'total_own_damage' => $response_data['TOTALOD'], //$final_od_premium,
            'cng_lpg_tp' => isset($response_data['C29']) ? round($response_data['C29']['premium']) : 0, //$tp_cng_pcv,
            'total_liability_premium' => $final_tp_premium,//isset($response_data['NETTP']) ? round($response_data['NETTP']) : 0,
            'net_premium' => isset($response_data['NETPREM']) ? round($response_data['NETPREM']) : 0, //$result['result']['plans'][0]['tenures'][0]['premium']['net']['value'],
            'service_tax_amount' => round($response_data['TAX']['total_prem']) ?? 0, //$result['result']['plans'][0]['tenures'][0]['premium']['gst']['value'],
            'service_tax' => 18,
            'total_discount_od' => 0,
            'add_on_premium_total' => 0,
            'addon_premium' => 0,
            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
            'quotation_no' => '',
            'premium_amount' => '', //$result['result']['plans'][0]['tenures'][0]['premium']['gross']['value'],
            'antitheft_discount' => (isset($response_data['C11']) ? round($response_data['C11']['premium']) : 0),
            'final_od_premium' => $final_od_premium,
            'final_tp_premium' => $final_tp_premium,//(round($response_data['NETTP']) -  (isset($response_data['C3']) ? round($response_data['C3']['premium']) : 0)),
            'final_total_discount' =>  $final_total_discount,
            'final_net_premium' => round($response_data['NETPREM']) ?? 0,
            'final_gst_amount' => round($response_data['TAX']['total_prem']) ?? 0,
            'final_payable_amount' => round($total_payable_amount * 1.18),
            'service_data_responseerr_msg' => '',
            'user_id' => $requestData->user_id,
            'product_sub_type_id' => $productData->product_sub_type_id,
            'user_product_journey_id' => $requestData->user_product_journey_id,
            'business_type' => ($is_new ? 'New Business' : 'Rollover'),
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
            'add_ons_data' =>  $add_on_data,
            'applicable_addons' => $applicable_addons,
        ]
    ];

    if($is_unnamed)
    {
        // $data_response['Data']['cover_unnamed_passenger_value'] = isset($response_data['C17']) ? round($response_data['C17']['premium']) : 0;
    }

    if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
        $data_response['Data']['premiumTypeCode'] = $premium_type;
    }

    return camelCase($data_response);
}

