<?php

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\MasterProduct;
use App\Models\ProposalExtraFields;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Proposal\Services\Car\tataAigV2SubmitProposal as TATA_AIG;
use App\Models\MasterPolicy;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getV2Quote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    $premium_type = DB::table('master_premium_type')
    ->where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();

    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
    ->first();

    //Uat Plans array
    $uatPlansArray = [
        'SILVER'    => 'P1',
        'GOLD'      => 'P2',
        'PEARL'     => 'P3',
        'PEARL+'    => 'P4',
        'SAPPHIRE'  => 'P5',
        'SAPPHIREPLUS' => 'P6',
        'SAPPHIRE++'=> 'P7',
        'PLATINUM'  => 'P9', 
        'CORAL'     => 'P10',
        'PEARL++'   => 'P11' 
    ];
    $productIdentifier = $masterProduct->product_identifier ?? null;
    $planName = array_search($productIdentifier, array_flip($uatPlansArray), true);
    $planName = ($planName === false) ? "" : $planName;

    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);


    $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

    $is_breakin     = (((strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')) ? false
    : true);

    $noPrevPolicy = ($requestData->previous_policy_type == 'Not sure');

    $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

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

    $check_mmv = TATA_AIG::checkTataAigMMV($productData, $requestData->version_id);

    if(!$check_mmv['status'])
    {
        return $check_mmv;
    }

    $mmv = (object)$check_mmv['data'];

    if(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_MMV_CHANGE_TO_UAT') == 'Y')
    {
        $mmv = TATA_AIG::changeToUAT($mmv);
    }

    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('d-m-Y') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = $interval->y;

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

    if($masterProduct->product_identifier == 'PLATINUM' && strtoupper($mmv->manufacturer) != 'MARUTI')
    {
        return
        [
            'premium_amount' => 0,
            'status' => false,
            'message' => config('TATA_AIG_V2_CAR_PLAN_ERROR_MESSAGE', 'PLATINUM package is only available for MARUTI.'),
            'request' => [
                'message' => config('TATA_AIG_V2_CAR_PLAN_ERROR_MESSAGE', 'PLATINUM package is only available for MARUTI.'),
                'product_identifier' => $masterProduct->product_identifier,
                'manufacturer' => $mmv->manufacturer
            ]
        ];
    }

    if($masterProduct->product_identifier == 'PEARL++' && $requestData->business_type != 'newbusiness')
    {
        return
        [
            'premium_amount' => 0,
            'status' => false,
            'message' => config('TATA_AIG_V2_CAR_PLAN_ERROR_MESSAGE', 'PEARL++ package is only available for new vehicles.'),
            'request' => [
                'message' => config('TATA_AIG_V2_CAR_PLAN_ERROR_MESSAGE', 'PEARL++ package is only available for new vehicles.'),
                'product_identifier' => $masterProduct->product_identifier,
                'business_type' => $requestData->business_type
            ]
        ];
    }

    if(!$is_new && !$is_od && !$is_liability && ( $requestData->previous_policy_type == 'Third-party') && !in_array($masterProduct->product_identifier, ['SILVER', 'GOLD']))
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

    // if(!$is_new && !$is_liability && $requestData->previous_policy_type == 'Not sure')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Previous policy details are mandatory',
    //         'request' => [
    //             'message' => 'Previous policy details are mandatory',
    //             'previous_policy_typ' => $requestData->previous_policy_type
    //         ]
    //     ];
    // }

    $vehicle_age_till_15_allowed = (in_array(strtoupper($mmv->manufacturer), ['MAHINDRA','MARUTI','HYUNDAI','HONDA']) && $mmv->txt_fuel == 'PETROL') ? true : false;

    $isbetween20YearsLimit = !(($interval->y > 20) || ($interval->y == 20 && $interval->m > 8) || ($interval->y == 20 && $interval->m == 8 && $interval->d > 23));

    $isbetween10YearsLimit = !(($interval->y > 10) || ($interval->y == 10 && $interval->m > 8) || ($interval->y == 10 && $interval->m == 8 && $interval->d > 25));
    //Removing age validation for all iC's  #31637 
    // if(!$isbetween20YearsLimit)
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Car Age greater than 20 years 8 months and 23 days',
    //         'request' => [
    //             'message' => 'Car Age greater than 20 years 8 months and 23 days',
    //             'vehicle_age' => $vehicle_age,
    //             $interval
    //         ],
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }

    // if (!$isbetween10YearsLimit && !$vehicle_age_till_15_allowed)
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Car Age greater than 10 years 8 months and 23 days',
    //         'request' => [
    //             'message' => 'Car Age greater than 10 years 8 months and 23 days',
    //             'vehicle_age' => $vehicle_age,
    //             'manufacturer' => $mmv->manufacturer
    //         ],
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }

    // if((($interval->y > 15) || ($interval->y == 15 && $interval->m > 8) || ($interval->y == 15 && $interval->m == 8 && $interval->d > 26)) && in_array($masterProduct->product_identifier, ['SILVER', 'GOLD', 'PEARL']))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Car Age greater than 15 years 8 months and 26 days',
    //         'request' => [
    //             'message' => 'Car Age greater than 15 years 8 months and 26 days',
    //             'vehicle_age' => $vehicle_age,
    //             'product_identifier' => $masterProduct->product_identifier,
    //             $interval
    //         ],
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }
    if (config('DISABLE_TATA_AIG_V2_PACKAGE_AGE_VALIDATION') != 'Y') {
        $packageAgeValidation = packageAgeValidation($requestData, $masterProduct);

        if (!$packageAgeValidation['status']) {
            return $packageAgeValidation;
        }
    }
    $ownership_count = ProposalExtraFields::where('enquiry_id', $enquiryId)->value('vahan_serial_number_count');

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();


    if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
        $addons = $selected_addons->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ? '3' : '1';

            }
        }   
    }

    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
    {
        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '3'; 
        }
        else{
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure :'1';
        }
    }

    $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAPaidDriverConductorCleaner = $PAforaddionaldPassenger = $llpaidDriver = $NonElectricalaccess = "No";

    $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = '';

    $externalCNGKIT = 'No';

    $is_electrical = false;
    $is_non_electrical = false;
    $is_lpg_cng = false;

    foreach ($accessories as $key => $value)
    {
        if (in_array('Electrical Accessories', $value) && !$is_liability)
        {
            $Electricalaccess = "Yes";
            $ElectricalaccessSI = $value['sumInsured'];
            $is_electrical = true;
        }

        if (in_array('Non-Electrical Accessories', $value) && !$is_liability)
        {
            $NonElectricalaccess = "Yes";
            $NonElectricalaccessSI = $value['sumInsured'];
            $is_non_electrical = true;
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value))
        {
            $externalCNGKIT = "Yes";
            $externalCNGKITSI = $value['sumInsured'];
            $is_lpg_cng = true;
            if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                $mmv->txt_fuel = 'External CNG';
                $mmv->txt_fuelcode = '5';
            }
        }

        if (in_array('PA To PaidDriver Conductor Cleaner', $value))
        {
            $PAPaidDriverConductorCleaner = "Yes";
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        }
    }

    $is_pa_paid = false;
    $is_pa_unnamed = false;
    $is_ll_paid = false;
    foreach ($additional_covers as $key => $value)
    {
        if (in_array('PA cover for additional paid driver', $value))
        {
            $PAforaddionaldPaidDriver = "Yes";
            $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            $is_pa_paid = true;
        }

        if (in_array('Unnamed Passenger PA Cover', $value))
        {
            $PAforUnnamedPassenger = "Yes";
            $PAforUnnamedPassengerSI = $value['sumInsured'];
            $is_pa_unnamed = true;
        }

        if (in_array('LL paid driver', $value))
        {
            $llpaidDriver = "Yes";
            $llpaidDriverSI = $value['sumInsured'];
            $is_ll_paid = true;
        }
    }

    $is_automobile_assoc = false;
    $is_anti_theft = false;
    $is_voluntary_access = false;
    $is_tppd = false;

    $is_anti_theft_device_certified_by_arai = "false";
    $tppd_amt = 0;
    $voluntary_excess_amt = '';

    foreach ($discounts as $key => $data)
    {
        if ($data['name'] == 'anti-theft device' && !$is_liability)
        {
            $is_anti_theft = true;
            $is_anti_theft_device_certified_by_arai = 'true';
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured']) && !$is_liability)
        {
            $is_voluntary_access = true;
            $voluntary_excess_amt = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover' && !$is_od)
        {
            $is_tppd = true;
            $tppd_amt = '9999';
        }
    }

    if(config('constants.IcConstants.tata_aig_v2.NO_VOLUNTARY_DISCOUNT') == 'Y')
    {
        $is_voluntary_access = false;
        $voluntary_excess_amt = '';
    }

    /*
    $validateAccessoriesAmount = validateAccessoriesAmount($selected_addons);

    if(!$validateAccessoriesAmount['status'])
    {
        $validateAccessoriesAmount['product_identifier'] = $masterProduct->product_identifier;
        return $validateAccessoriesAmount;
    }
    */

    // addon

    $vehicle_in_90_days = 0;

    $motor_manf_date = '01-' . $requestData->manufacture_year;

    $current_date = date('Y-m-d');

    if($is_new){
        $policyStartDate  = strtotime($requestData->vehicle_register_date);//date('Y-m-d');
        if($is_liability){
            $policyStartDate  = strtotime($requestData->vehicle_register_date . '+ 1 day');
        }
        $policy_start_date = date('Y-m-d', $policyStartDate);
        $policy_end_date    = date('Y-m-d', strtotime($policy_start_date . ' - 1 days + 3 year'));
    }
    else
    {
        $policy_start_date  = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

        if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date))
        {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
        }

        $policy_end_date    = date('Y-m-d', strtotime($policy_start_date . ' - 1 days + 1 year'));
    }

    $mmv_data = [
        'manf_name'             => $mmv->manufacturer,
        'model_name'            => $mmv->vehiclemodel,
        'version_name'          => $mmv->txt_varient,
        'seating_capacity'      => $mmv->seatingcapacity,
        'carrying_capacity'     => $mmv->seatingcapacity - 1,
        'cubic_capacity'        => $mmv->cubiccapacity,
        'fuel_type'             => $mmv->txt_fuel,
        'gross_vehicle_weight'  => $mmv->grossvehicleweight,
        'vehicle_type'          => 'CAR',
        'version_id'            => $mmv->ic_version_code,
    ];

    $customer_type = $is_individual ? "Individual" : "Organization";

    $btype_code = $requestData->business_type == "rollover" ? "2" : "1";
    $btype_name = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";

    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null)
    {
        $vehicle_register_no = parseVehicleNumber($requestData->vehicle_registration_no);
    }
    else
    {
        $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
    }

    // ADDONS
    $applicableAddon = TATA_AIG::getApplicableAddons($masterProduct, $is_liability, $interval);

    if ($is_new || $requestData->applicable_ncb < 25) {//NCB protection cover is not allowed for NCB less than or equal to 20%
        $applicableAddon['NCBProtectionCover'] = "No";
    }

    if($isbetween10YearsLimit && $requestData->is_claim == 'Y')
    {
        $applicableAddon['RoadsideAssistance'] = 'No';
    }
    // END ADDONS



    if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){
        $is_tata_pos_disabled_renewbuy = 'Y';
    }
    else{
        $is_tata_pos_disabled_renewbuy = config('constants.motorConstant.IS_TATA_POS_DISABLED_RENEWBUY');
    }
    $is_pos = ($is_tata_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');

    $pos_testing_mode = ($is_tata_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motor.constants.IcConstants.tata_aig_v2.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2');

    $pos_aadhar = '';
    $pos_pan    = '';
    $sol_id     = '';//config('constants.IcConstants.tata_aig.SOAL_ID');
    $is_posp = 'N';
    $q_office_location = 0;

    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id', $requestData->user_product_journey_id)
    ->where('seller_type','P')
    ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
    {
        if(!empty($pos_data->pan_no)){
            $is_posp = 'Y';
            // $sol_id = $pos_data->pan_no;
            // if(!empty($pos_data->relation_tata_aig))
            // {
            //     $q_office_location = $pos_data->relation_tata_aig ;

            // }
            // else{
            //     $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
            // }
            $sol_id = $pos_data->relation_tata_aig;
            $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
        }else
        {
            // Make this constant N for no quotes while pos login with no tata aig relationship and Y for pos login to non pos quotes
            if(config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_POS_TO_NON_POS') == 'N')
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Pos unique id for agent id - '.$pos_data->agent_id.' is not available in TATA IC relationship table',
                ];

            }
        }
    }
    elseif($pos_testing_mode == 'Y')
    {
        $is_posp = 'Y';
        $sol_id     = '840372';
        $q_office_location = 90200;
    }
    else
    {
        $is_pos = 'N';
    }

    $rto_code = explode('-', $requestData->rto_code);

    $rto_data = DB::table('tata_aig_v2_rto_master')
        ->where('txt_rto_code', 'like', '%'.$rto_code[0].$rto_code[1].'%')
        ->first();

    if(empty($rto_data))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO Data Not Found',
            'request' => [
                'message' => 'RTO Data Not Found',
                'rto_data' => $rto_data,
                'txt_rto_code' => $rto_code[0].$rto_code[1]
            ]
        ];
    }

    $token_response = TATA_AIG::getToken($enquiryId, $productData, 'quote');

    if(!$token_response['status'])
    {
        $token_response['product_identifier'] = $masterProduct->product_identifier;
        return $token_response;
    }

    if(config('constants.IcConstants.tata_aig_v2.NO_ANTITHEFT') == 'Y')
    {
        $is_anti_theft = false;
    }

    if(config('constants.IcConstants.tata_aig_v2.NO_NCB_PROTECTION') == 'Y')
    {
        $applicableAddon['NCBProtectionCover'] = 'No';
    }

    if(in_array(strtoupper($mmv->txt_segmenttype), ['MINI','COMPACT', 'MPS SUV', 'MPV SUV', 'MID SIZE']))
    {
        $engineProtectOption = 'WITH DEDUCTIBLE';
    }
    else
    {
        $engineProtectOption = 'WITHOUT DEDUCTIBLE';
    }

    $quoteRequest = [
        'quote_id'                      => '',

        'pol_plan_variant'              => ($is_package ? ($is_new ? 'PackagePolicy' : 'PackagePolicy') : ($is_liability ? ($is_new ? 'Standalone TP' : 'Standalone TP') : 'Standalone OD')),
        'pol_plan_id'                   => ($is_package ? ($is_new ? '04' : '02') : ($is_liability ? ($is_new ? '03' : '01') : '05')),

        'q_producer_code'               => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_PRODUCER_CODE'),
        'q_producer_email'              => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_PRODUCER_EMAIL'),

        'business_type_no'              => ($is_new ? '01' : '03'),
        'product_code'                  => '3184',
        'product_id'                    => 'M300000000001',
        'product_name'                  => 'Private Car',

        'proposer_type'                 => $customer_type,

        '__finalize'                    => '0',

        'add_towing'                    => 'No',
        'add_towing_amount'             => '',
        'allowance_days_accident'       => '',
        'allowance_days_loss'           => '',

        // DISCOUNTS
        'tppd_discount'                 => 'No', //$is_tppd ? 'Yes' : 'No',     //commented
        'antitheft_cover'               => $is_anti_theft ? 'Yes' : 'No',
        'automobile_association_cover'  => $is_automobile_assoc ? 'Yes' : 'No',
        'voluntary_amount'              => (string)($voluntary_excess_amt),
        // END DISCOUNT

        'cng_lpg_cover'                 => (string)($externalCNGKIT),
        'cng_lpg_si'                    => ($is_liability ? '0' : (string)($externalCNGKITSI)),

        // ASSESORIES
        'electrical_si'                 => (string)($ElectricalaccessSI),
        'non_electrical_si'             => (string)($NonElectricalaccessSI),
        // END ASSESORIES

        // COVERS
        'pa_named'                      => 'No',

        'pa_paid'                       => 'No',

        'pa_owner'                      => ($is_individual && !$is_od) ? 'true' : 'false',
        'pa_owner_declaration'          => 'None',
        'pa_owner_tenure'               => ($is_individual && !$is_od && isset($cpa_tenure)) ? $cpa_tenure : '',

        'pa_unnamed'                    => 'No',

        'll_paid'                       => 'No',
        // END COVERS

        // ADDONS
        'repair_glass'                  => $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'],
        'return_invoice'                => $applicableAddon['ReturnToInvoice'],
        'rsa'                           => $applicableAddon['RoadsideAssistance'],
        'emergency_expense'             => $applicableAddon['EmergTrnsprtAndHotelExpense'],

        'ncb_protection'                => $applicableAddon['NCBProtectionCover'],
        'ncb_no_of_claims'              => '',

        'consumbale_expense'            => $applicableAddon['ConsumablesExpenses'],
        'key_replace'                   => $applicableAddon['KeyReplacement'],
        'personal_loss'                 => $applicableAddon['LossOfPersonalBelongings'],

        'tyre_secure'                   => $applicableAddon['TyreSecure'],
        'tyre_secure_options'           => $is_liability ? '' : 'REPLACEMENT BASIS',// 'DEPRECIATION BASIS'

        'engine_secure'                 => $applicableAddon['EngineSecure'],
        'engine_secure_options'         => $is_liability ? '' : $engineProtectOption,

        'dep_reimburse'                 => $applicableAddon['DepreciationReimbursement'],
        'dep_reimburse_claims'          => $applicableAddon['NoOfClaimsDepreciation'],
        // END ADDONS

        'claim_last'                    => ($is_new ? 'false' : (($requestData->is_claim == 'N' || $is_liability) ? 'false' : 'true')),
        'claim_last_amount'             => null,
        'claim_last_count'              => null,

        'daily_allowance'               => 'No',
        'daily_allowance_plus'          => 'No',
        'daily_allowance_limit'         => '',

        'pol_start_date'                => $policy_start_date,

        'prev_pol_end_date'             => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d')),
        'prev_pol_start_date'             => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d')),

        'dor'                           => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),
        'man_year'                      => (int)(Carbon::parse($requestData->vehicle_register_date)->format('Y')),

        'franchise_days'                => '',
        'load_fibre'                    => 'No',
        'load_imported'                 => 'No',
        'load_tuition'                  => 'No',

        'motor_plan_opted'              => $masterProduct->product_identifier,
        'motor_plan_opted_no'           => $applicableAddon['motorPlanOptedNo'],

        // 'plan_opted'              => $masterProduct->product_identifier,
        // 'plan_opted_no'           => $applicableAddon['motorPlanOptedNo'],

        'own_premises'                  => 'No',

        'place_reg'                     => $rto_data->txt_rtolocation_name,
        'place_reg_no'                  => $rto_data->txt_rtolocation_code,

        'pre_pol_ncb'                   => (($is_new && $noPrevPolicy) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
        'pre_pol_protect_ncb'           => null,
        'prev_pol_type'                 => (($is_new && $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package' : (in_array($requestData->previous_policy_type, [ 'Own-damage']) ? 'Standalone OD' :'Liability'))),

        'proposer_pincode'              => $rto_data->num_pincode,

        "regno_1"                       => $vehicle_register_no[0] ?? "",
        "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")),// (string)($vehicle_register_no[1] ?? ""),
        "regno_3"                       => $vehicle_register_no[2] ?? "",
        "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),

        'uw_discount'                   => '',
        'uw_loading'                    => '',
        'uw_remarks'                    => '',

        'vehicle_blind'                 => 'No',
        'vehicle_idv'                   => '',
        'vehicle_make'                  => $mmv->manufacturer,
        'vehicle_make_no'               => (int)($mmv->manufacturercode),
        'vehicle_model'                 => $mmv->vehiclemodel,
        'vehicle_model_no'              => (int)($mmv->num_parent_model_code),
        'vehicle_variant'               => $mmv->txt_varient,
        'vehicle_variant_no'            => $mmv->vehiclemodelcode,

        'source'                        => 'P',
        'vintage_car'                   => 'No',
        'proposer_email'                => 'abcd@gmail.com',
    ];

    $quoteRequest['repair_glass']                  = 'No';//$applicableAddon['RepairOfGlasPlastcFibNRubrGlas'];
    $quoteRequest['return_invoice']                = 'No';//$applicableAddon['ReturnToInvoice'];
    $quoteRequest['emergency_expense']             = 'No';//$applicableAddon['EmergTrnsprtAndHotelExpense'];
    $quoteRequest['consumbale_expense']            = 'No';//$applicableAddon['ConsumablesExpenses'];
    $quoteRequest['key_replace']                   = 'No';//$applicableAddon['KeyReplacement'];
    $quoteRequest['personal_loss']                 = 'No';//$applicableAddon['LossOfPersonalBelongings'];
    $quoteRequest['tyre_secure']                   = 'No';//$applicableAddon['TyreSecure'];
    $quoteRequest['engine_secure']                 = 'No';//$applicableAddon['EngineSecure'];
    $quoteRequest['dep_reimburse']                 = 'No';//$applicableAddon['DepreciationReimbursement'];

    $quoteRequest['rc_owner_sr'] = (string)($ownership_count) ?? "";
    if($is_posp == "Y" )
    {
        $quoteRequest['is_posp'] = $is_posp;
        $quoteRequest['sol_id'] = $sol_id;
        $quoteRequest['q_office_location'] = $q_office_location;
    }

    if(!$is_new)
    {
        $quoteRequest['no_past_pol'] = 'N';
    }
    if($noPrevPolicy){
        $quoteRequest['no_past_pol'] = 'Y';
    }

    if($applicableAddon['NCBProtectionCover'] == 'Yes')
    {
        $quoteRequest['ncb_no_of_claims'] = '1';
    }

    if(!$is_new && !$noPrevPolicy)
    {
        if($is_lpg_cng)
        {
            $quoteRequest['prev_cnglpg'] = 'Yes';
        }

        if($is_liability)
        {
            $quoteRequest['prev_cnglpg'] = 'No';
        }


        if($applicableAddon['ConsumablesExpenses'] == 'Yes')
        {
            $quoteRequest['prev_consumable'] = 'Yes';
        }

        if($applicableAddon['ReturnToInvoice'] == 'Yes')
        {
            $quoteRequest['prev_rti'] = 'Yes';
        }

        if($applicableAddon['TyreSecure'] == 'Yes')
        {
            $quoteRequest['prev_tyre'] = 'Yes';
        }

        if($applicableAddon['EngineSecure'] == 'Yes')
        {
            $quoteRequest['prev_engine'] = 'Yes';
        }

        if($applicableAddon['DepreciationReimbursement'] == 'Yes')
        {
            $quoteRequest['prev_dep'] = 'Yes';
        }
    }

    if(!$is_od)
    {
        if($PAforUnnamedPassenger == 'Yes')
        {
            $quoteRequest['pa_unnamed'] = $PAforUnnamedPassenger;
            $quoteRequest['pa_unnamed_csi'] = '';
            $quoteRequest['pa_unnamed_no'] = (string)($mmv->seatingcapacity);
            $quoteRequest['pa_unnamed_si'] = (string)$PAforUnnamedPassengerSI;
        }
        if($llpaidDriver == 'Yes')
        {
            $quoteRequest['ll_paid'] = $llpaidDriver;
            $quoteRequest['ll_paid_no'] = '1';
        }
        if($PAforaddionaldPaidDriver == 'Yes')
        {
            $quoteRequest['pa_paid'] = $PAforaddionaldPaidDriver;
            $quoteRequest['pa_paid_no'] = '1';
            $quoteRequest['pa_paid_si'] = $PAforaddionaldPaidDriverSI;
        }
    }

    if($is_od)
    {
        $quoteRequest['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
        $quoteRequest['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');

        $quoteRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
        $quoteRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
    }

    if(config('constants.motor.tata_aig_v2.TATA_AIG_V2_PLAN_OPT') == 'Y'){
        $quoteRequest['motor_plan_opted_no'] = $planName;
    }
    if(config('constants.motorConstant.SMS_FOLDER') === 'edme'){
        $quoteRequest["fleetCode"] = config('constant.IcConstant.TATA_AIG_V2_CAR_FLEET_CODE');
        $quoteRequest["fleetName"] = config('constant.IcConstant.TATA_AIG_V2_CAR_FLEET_NAME');
        $quoteRequest["fleetOpted"] = "true";
        $quoteRequest["optionForCalculation"] = "Yearly";
    }
    $data = $quoteRequest;
    unset($data['regno_4']);
    $checksum_data =checksum_encrypt($data);

    $additional_data = [
        'enquiryId'         => $enquiryId,
        'headers'           => [
            'Content-Type'  => 'application/JSON',
            'Authorization'  => 'Bearer '.$token_response['token'],
            'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
        ],
        'requestMethod'     => 'post',
        'requestType'       => 'json',
        'section'           => $productData->product_sub_type_code,
        'method'            => 'Premium Calculation',
        'transaction_type'  => 'quote',
        'checksum'          => $checksum_data,
        'productName'       => $productData->product_name,
        'token'             => $token_response['token'],
    ];

    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'tata_aig_v2',$checksum_data,'CAR');
    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
    {
        $get_quoteResponse = $is_data_exits_for_checksum;
    }
    else
    {
        $get_quoteResponse = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
    }

    if($get_quoteResponse['response'] && $get_quoteResponse['response'] != '' && $get_quoteResponse['response'] != null)
    {
        $quoteResponse = json_decode($get_quoteResponse['response'], true);
        $skip_second_call = false;

        if(!empty($quoteResponse))
        {
            if(!isset($quoteResponse['status']))
            {
                if(isset($quoteResponse['message']))
                {
                    return [
                        'webservice_id' => $get_quoteResponse['webservice_id'],
                        'table' => $get_quoteResponse['table'],
                        'status'    => false,
                        'msg'       => $quoteResponse['message'],
                        'product_identifier' => $masterProduct->product_identifier,
                    ];
                }

                return [
                    'webservice_id' => $get_quoteResponse['webservice_id'],
                    'table' => $get_quoteResponse['table'],
                    'status'    => false,
                    'msg'       => 'Insurer Not Reachable',
                    'product_identifier' => $masterProduct->product_identifier,
                ];

            }

            if($quoteResponse['status'] != 200)
            {
                if(!isset($quoteResponse['message_txt']))
                {
                    return [
                        'webservice_id' => $get_quoteResponse['webservice_id'],
                        'table' => $get_quoteResponse['table'],
                        'status'    => false,
                        'msg'       => 'Insurer Not Reachable',
                        'QuoteRequest'   => $quoteRequest,
                        'product_identifier' => $masterProduct->product_identifier,
                    ];
                }
                return [
                    'webservice_id' => $get_quoteResponse['webservice_id'],
                    'table' => $get_quoteResponse['table'],
                    'status'    => false,
                    'msg'       => $quoteResponse['message_txt'],
                    'QuoteRequest'   => $quoteRequest,
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }
            else
            {

                try{

                    $quoteResponse2 = $quoteResponse;
                    $quoteResponse = $quoteResponse['data'][0]['data'];

                    if($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true')
                    {
                        return [
                            'status' => false,
                            'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                            'product_identifier' => $masterProduct->product_identifier,
                            'quoteResponse' => $quoteResponse
                        ];
                    }

                    // pass idv
                    $max_idv    = ($is_liability ? 0 : $quoteResponse['max_idv']);
                    $min_idv    = ($is_liability ? 0 : $quoteResponse['min_idv']);
                    $idv        = $quoteResponse2['data'][0]['pol_dlts']['vehicle_idv'];

                    if ($requestData->is_idv_changed == "Y")
                    {
                        if ($requestData->edit_idv >= $max_idv)
                        {
                            $idv = ($max_idv);
                        }
                        else if ($requestData->edit_idv <= $min_idv)
                        {
                            $idv = ($min_idv);
                        }
                        else
                        {
                            $idv = ($requestData->edit_idv);
                        }
                    }
                    else
                    {
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $skip_second_call = true;
                                $idv = ($idv);
                                break;
                            case 'min_idv':
                                $idv = ($min_idv);
                                break;
                            case 'max_idv':
                                $quoteRequest['max_idv'] = $max_idv;
                                $idv = ($max_idv);
                                break;
                            default:
                                $quoteRequest['min_idv'] = $min_idv;
                                $idv = ($min_idv);
                                break;
                        }
                        // $idv = ($min_idv);
                    }

                    $quoteRequest['vehicle_idv'] = (string)($idv);
                    $quoteRequest['__finalize'] = '1';

                    if (config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $idv >= 5000000)
                    {
                        $quoteRequest['is_posp'] = 'N';
                        $quoteRequest['sol_id'] = '';
                        $quoteRequest['q_office_location'] = 0;
                    } elseif(!empty($pos_data) && $is_posp == "Y"){
                        $quoteRequest['is_posp'] = 'Y';
                        $quoteRequest['sol_id'] = $pos_data->pan_no;
                        $quoteRequest['q_office_location'] = config('constants.motor.constants.IcConstants.tata_aig_v2.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
                    }

                    $data = $quoteRequest;
                    unset($data['regno_4']);
                    $checksum_data =checksum_encrypt($data);

                    $additional_data = [
                        'enquiryId'         => $enquiryId,
                        'headers'           => [
                            'Content-Type'  => 'application/JSON',
                            'Authorization'  => 'Bearer '.$token_response['token'],
                            'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
                        ],
                        'requestMethod'     => 'post',
                        'requestType'       => 'json',
                        'section'           => $productData->product_sub_type_code,
                        'method'            => 'Premium Re-Calculation',
                        'transaction_type'  => 'quote',
                        'checksum'          => $checksum_data,
                        'productName'       => $productData->product_name,
                        'token'             => $token_response['token'],
                    ];

                    if(!$skip_second_call) {

                        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'tata_aig_v2',$checksum_data,'CAR');
                        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                        {
                            $get_response = $is_data_exits_for_checksum;
                        }
                        else
                        {
                            $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
                        }

                        $quoteResponse = $get_response['response'];

                        $quoteResponse = TATA_AIG::validaterequest($quoteResponse);

                        if(!$quoteResponse['status'])
                        {
                            $quoteResponse['product_identifier'] = $masterProduct->product_identifier;
                            return $quoteResponse;
                        }
                        $quoteResponse2 = $quoteResponse;
                        $quoteResponse = $quoteResponse['data'][0]['data'];

                        if($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true')
                        {
                            return [
                                'status' => false,
                                'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                                'product_identifier' => $masterProduct->product_identifier,
                                'quoteResponse' => $quoteResponse
                            ];
                        }
                    }
                    // pass idv


                    // BREAKIN LOGIC GIT ID 16070
                    $isInspectionApplicable = 'N';
                    if((!in_array($premium_type,['breakin','own_damage_breakin','third_party','third_party_breakin'])) && isset($quoteResponse2['data'][0]['pol_dlts']['inspectionFlag']) && ($quoteResponse2['data'][0]['pol_dlts']['inspectionFlag'] == 'true' && config('TATA_AIG_V2_CHECK_ROLLOVER_BREAKIN_HANDLING') == 'Y'))
                    {
                        $breakin_products = [];
                        if($premium_type == 'comprehensive')
                        {
                            $breakin_products = MasterPolicy::where('insurance_company_id',$masterProduct->ic_id)
                            ->where('premium_type_id',4)
                            ->where('product_sub_type_id',1)
                            ->get()->toArray();

                        }else if($premium_type == 'own_damage')
                        {
                            $breakin_products = MasterPolicy::where('insurance_company_id',$masterProduct->ic_id)
                            ->where('premium_type_id',6)
                            ->where('product_sub_type_id',1)
                            ->get()->toArray();

                        }
                        if(count($breakin_products) > 0)
                        {
                            $isInspectionApplicable = 'Y';
                        }else
                        {
                            return [
                                'status' => false,
                                'message' => 'Inspection Required',
                                'product_identifier' => $masterProduct->product_identifier,
                                'quoteResponse' => $quoteResponse
                            ];

                        }


                    }
                    // BREAKIN LOGIC GIT ID 16070 END

                    $totalOdPremium = $quoteResponse['premium_break_up']['total_od_premium'];
                    $totalAddons    = $quoteResponse['premium_break_up']['total_addOns'];
                    $totalTpPremium = $quoteResponse['premium_break_up']['total_tp_premium'];

                    $netPremium     = (float)(isset($quoteResponse['premium_break_up']['net_premium']) ? $quoteResponse['premium_break_up']['net_premium'] : 0);
                    $finalPremium   = (float)(isset($quoteResponse['premium_break_up']['final_premium']) ? $quoteResponse['premium_break_up']['final_premium'] : 0);


                    $basic_od       = (float)(isset($totalOdPremium['od']['basic_od']) ? $totalOdPremium['od']['basic_od'] : 0);
                    $non_electrical = (float)(isset($totalOdPremium['od']['non_electrical_prem']) ? $totalOdPremium['od']['non_electrical_prem'] : 0);
                    $electrical     = (float)(isset($totalOdPremium['od']['electrical_prem']) ? $totalOdPremium['od']['electrical_prem'] : 0);
                    $lpg_cng_od     = (float)(isset($totalOdPremium['od']['cng_lpg_od_prem']) ? $totalOdPremium['od']['cng_lpg_od_prem'] : 0);

                    $final_od_premium = $basic_od + $non_electrical + $electrical + $lpg_cng_od;


                    $basic_tp       = (float)(isset($totalTpPremium['basic_tp']) ? $totalTpPremium['basic_tp'] : 0);
                    $pa_unnamed     = (float)(isset($totalTpPremium['pa_unnamed_prem']) ? $totalTpPremium['pa_unnamed_prem'] : 0);
                    $ll_paid        = (float)(isset($totalTpPremium['ll_paid_prem']) ? $totalTpPremium['ll_paid_prem'] : 0);
                    $lpg_cng_tp     = (float)(isset($totalTpPremium['cng_lpg_tp_prem']) ? $totalTpPremium['cng_lpg_tp_prem'] : 0);


                    $pa_paid        = (float)(isset($quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem']) ? $quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem'] : 0);

                    $final_tp_premium = $basic_tp + $pa_unnamed + $ll_paid + $lpg_cng_tp + $pa_paid;

                    $pa_owner       = (float)(isset($totalTpPremium['pa_owner_prem']) ? $totalTpPremium['pa_owner_prem'] : 0);
                    $tppd_discount  = (float)(isset($totalTpPremium['tppd_prem']) ? $totalTpPremium['tppd_prem'] : 0);


                    $anti_theft_amount      = (float)(isset($totalOdPremium['discount_od']['atd_disc_prem']) ? $totalOdPremium['discount_od']['atd_disc_prem'] : 0);
                    $automoble_amount       = (float)(isset($totalOdPremium['discount_od']['aam_disc_prem']) ? $totalOdPremium['discount_od']['aam_disc_prem'] : 0);
                    $voluntary_deductible   = (float)(isset($totalOdPremium['discount_od']['vd_disc_prem']) ? $totalOdPremium['discount_od']['vd_disc_prem'] : 0);
                    $ncb_discount_amount    = (float)(isset($totalOdPremium['discount_od']['ncb_prem']) ? $totalOdPremium['discount_od']['ncb_prem'] : 0);

                    $final_total_discount = $ncb_discount_amount + $anti_theft_amount + $automoble_amount + $voluntary_deductible + $tppd_discount;


                    $zero_dep_amount            = (float)(isset($totalAddons['dep_reimburse_prem']) ? $totalAddons['dep_reimburse_prem'] : 0);
                    $rsa_amount                 = (float)(isset($totalAddons['rsa_prem']) ? $totalAddons['rsa_prem'] : 0);
                    $ncb_protect_amount         = (float)(isset($totalAddons['ncb_protection_prem']) ? $totalAddons['ncb_protection_prem'] : 0);
                    $engine_seccure_amount      = (float)(isset($totalAddons['engine_secure_prem']) ? $totalAddons['engine_secure_prem'] : 0);
                    $tyre_secure_amount         = (float)(isset($totalAddons['tyre_secure_prem']) ? $totalAddons['tyre_secure_prem'] : 0);
                    $rti_amount                 = (float)(isset($totalAddons['return_invoice_prem']) ? $totalAddons['return_invoice_prem'] : 0);
                    $counsumable_amount         = (float)(isset($totalAddons['consumbale_expense_prem']) ? $totalAddons['consumbale_expense_prem'] : 0);
                    $key_replacment_amount      = (float)(isset($totalAddons['key_replace_prem']) ? $totalAddons['key_replace_prem'] : 0);
                    $personal_belongings_amount = (float)(isset($totalAddons['personal_loss_prem']) ? $totalAddons['personal_loss_prem'] : 0);

                    $emergency_expense_amount   = (float)(isset($totalAddons['emergency_expense_prem']) ? $totalAddons['emergency_expense_prem'] : 0);
                    $repair_glass_prem          = (float)(isset($totalAddons['repair_glass_prem']) ? $totalAddons['repair_glass_prem'] : 0);

                    $final_addon_amount         = (float)(isset($totalAddons['total_addon']) ? $totalAddons['total_addon'] : 0);
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;




                    $add_on_data = [
                        'in_built'   => [],
                        'additional' => [],
                        'other'      => []
                    ];

                    switch ($masterProduct->product_identifier) {
                        case 'SILVER':
                            $add_on_data = [
                                'in_built'   => [],
                                'additional' => [
                                    'key_replace'           => $key_replacment_amount,
                                    'consumables'           => $counsumable_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'engine_protector'      => $engine_seccure_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                ]
                            ];
                        break;

                        case 'GOLD':
                            $add_on_data = [
                                'in_built'   => [
                                    'road_side_assistance'  => $rsa_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                ],
                                'additional' => [
                                    'consumables'           => $counsumable_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'engine_protector'      => $engine_seccure_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'PEARL':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                ],
                                'additional' => [
                                    'engine_protector'      => $engine_seccure_amount,
                                    'consumables'           => $counsumable_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'PEARL+':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'engine_protector'      => $engine_seccure_amount,
                                    'consumables'           => $counsumable_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                ],
                                'additional' => [
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'CORAL':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'consumables'           => $counsumable_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                ],
                                'additional' => [
                                    'engine_protector'      => $engine_seccure_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'PLATINUM':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'engine_protector'      => $engine_seccure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                ],
                                'additional' => [
                                    'consumables'           => $counsumable_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'SAPPHIRE':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'consumables'           => $counsumable_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                ],
                                'additional' => [
                                    'engine_protector'      => $engine_seccure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'SAPPHIREPLUS':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'consumables'           => $counsumable_amount,
                                    'engine_protector'      => $engine_seccure_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                ],
                                'additional' => [
                                    'return_to_invoice'     => $rti_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'SAPPHIRE++':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'consumables'           => $counsumable_amount,
                                    'engine_protector'      => $engine_seccure_amount,
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                ],
                                'additional' => [
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;

                        case 'PEARL++':
                            $add_on_data = [
                                'in_built'   => [
                                    'zero_depreciation'     => $zero_dep_amount,
                                    'road_side_assistance'  => $rsa_amount,
                                    'lopb'                  => $personal_belongings_amount,
                                    'key_replace'           => $key_replacment_amount,
                                    'consumables'           => $counsumable_amount,
                                    'engine_protector'      => $engine_seccure_amount,
                                    'return_to_invoice'     => $rti_amount,
                                ],
                                'additional' => [
                                    'tyre_secure'           => $tyre_secure_amount,
                                    'ncb_protection'        => $ncb_protect_amount,
                                ],
                                'other'      => [
                                    'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                                    'emergency_transport_and_hotel_expenses'  => $emergency_expense_amount,
                                ]
                            ];
                        break;
                    }
                    // echo "<pre>";print_r([$add_on_data, $totalAddons]);echo "</pre>";die();

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

                    $applicable_addons = getApplicableAddonList($requestData, $interval, $is_liability);

                    $data_response = [
                        'webservice_id' => $get_response['webservice_id'] ?? ($get_quoteResponse['webservice_id'] ?? ''),
                        'table' => $get_response['table'] ?? ($get_quoteResponse['table'] ?? ''),
                        'status' => true,
                        'msg' => 'Found',
                        'product_identifier' => $masterProduct->product_identifier,
                        'Data' => [
                            'idv' => $premium_type == 'third_party' ? 0 : ($quoteRequest['vehicle_idv']),
                            'min_idv' => $premium_type == 'third_party' ? 0 : ($min_idv),
                            'max_idv' => $premium_type == 'third_party' ? 0 : ($max_idv),
                            'qdata' => NULL,
                            'pp_enddate' => $requestData->previous_policy_expiry_date,
                            'addonCover' => NULL,
                            'addon_cover_data_get' => '',
                            'rto_decline' => NULL,
                            'rto_decline_number' => NULL,
                            'mmv_decline' => NULL,
                            'mmv_decline_name' => NULL,
                            'policy_type' => (($is_package) ? 'Comprehensive' : (($is_liability) ? 'Third Party' : 'Own Damage')),
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => "",
                            'vehicle_registration_no' => $requestData->rto_code,
                            'version_id' => $requestData->version_id,
                            'selected_addon' => [],
                            'showroom_price' => $premium_type == 'third_party' ? 0 : (int)$quoteRequest['vehicle_idv'],
                            'fuel_type' => $requestData->fuel_type,
                            'vehicle_idv' => $premium_type == 'third_party' ? 0 : (int)$quoteRequest['vehicle_idv'],
                            'ncb_discount' => $requestData->applicable_ncb,
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
                                'car_age' => $vehicle_age,
                                'aai_discount' => 0,
                                'ic_vehicle_discount' => '',
                            ],
                            'basic_premium' => $basic_od,
                            'deduction_of_ncb' => $ncb_discount_amount,
                            'tppd_premium_amount' => $basic_tp,
                            'seating_capacity' => $mmv->seatingcapacity,
                            'compulsory_pa_own_driver' => $pa_owner,
                            'total_accessories_amount(net_od_premium)' => "",
                            'total_own_damage' => $final_od_premium,
                            'total_liability_premium' => $final_tp_premium,
                            'net_premium' => $final_tp_premium,
                            'service_tax_amount' => "",
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'quotation_no' => '',
                            'premium_amount' => '',
                            'final_od_premium' => $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' =>  $final_total_discount,
                            'final_net_premium' => $netPremium,
                            'final_gst_amount' => ($netPremium * 18/100),
                            'final_payable_amount' => $finalPremium,
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
                            'add_ons_data' =>  $add_on_data,
                            'applicable_addons' => $applicable_addons,
                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                            'isInspectionApplicable'                      => $isInspectionApplicable,
                        ],
                        'quoteResponse' => $quoteResponse
                    ];

                    if(isset($cpa_tenure))
                    {
                    if($requestData->business_type == 'newbusiness' && $cpa_tenure  == '3')
                    {
                        // unset($data_response['Data']['compulsory_pa_own_driver']);
                        $data_response['Data']['multi_Year_Cpa'] =  $pa_owner;
                    }
                    }

                    if($is_electrical)
                    {
                        $data_response['Data']['motor_electric_accessories_value'] = $electrical;
                    }
                    if($is_non_electrical)
                    {
                        $data_response['Data']['motor_non_electric_accessories_value'] = $non_electrical;
                    }
                    if($is_lpg_cng)
                    {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng_od;
                        $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                    }
                    if(!empty($lpg_cng_od))
                    {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng_od;
                    }
                    if(!empty($lpg_cng_tp))
                    {
                        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                    }

                    if($is_pa_paid)
                    {
                        $data_response['Data']['motor_additional_paid_driver'] = $pa_paid;
                    }
                    if($is_pa_unnamed)
                    {
                        $data_response['Data']['cover_unnamed_passenger_value'] = $pa_unnamed;
                    }
                    if($is_ll_paid)
                    {
                        $data_response['Data']['default_paid_driver'] = $ll_paid;
                    }

                    if($is_tppd)
                    {
                        $data_response['Data']['tppd_discount'] = $tppd_discount;
                    }

                    if($is_anti_theft)
                    {
                        $data_response['Data']['antitheft_discount'] = $anti_theft_amount;
                    }
                    if($is_voluntary_access)
                    {
                        $data_response['Data']['voluntary_excess'] = $voluntary_deductible;
                    }

                    return camelCase($data_response);
                }
                catch(Exception $e)
                {

                    // echo "<pre>";print_r([
                    //     $e->getMessage().' '. $e->getLine(),
                    //     $quoteResponse2,
                    //     $quoteRequest,
                    // ]);echo "</pre>";die();
                }

                return [
                    'webservice_id' => $get_response['webservice_id'] ?? ($get_quoteResponse['webservice_id'] ?? ''),
                    'table' => $get_response['table'] ?? ($get_quoteResponse['table'] ?? ''),
                    'status'    => false,
                    'msg'       => $e->getMessage().' '. $e->getLine(),
                    'response'     => $quoteResponse,
                ];
            }
        }
        else
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }
    }
    else
    {
        return [
            'status'    => false,
            'msg'       => 'Insurer Not Reachable',
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }





}

function validateAccessoriesAmount($selected_addons)
{
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
    return [
        'status' => true
    ];
}

function packageAgeValidation($requestData, $masterProduct)
{
    if($requestData->business_type == 'newbusiness')
    {
        return [
            'status' => true,
            'message' => 'success'
        ];
    }
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('d-m-Y') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $vehicle_age = $interval->y;

    if ((($interval->y > 3) || ($interval->y == 3 && $interval->m > 8) || ($interval->y == 3 && $interval->m == 8 && $interval->d > 27)) && (in_array($masterProduct->product_identifier, ['SAPPHIRE++', 'PLATINUM'])))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Car Age greater than 03 Years 08 months and 27 Days',
            'request' => [
                'message' => 'Car Age greater than 03 Years 08 months and 27 Days',
                'vehicle_age' => $vehicle_age,
                'product_identifier' => $masterProduct->product_identifier
            ],
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    if(in_array($masterProduct->product_identifier, ['SAPPHIREPLUS', 'SAPPHIRE', 'PEARL+', 'PEARL', 'CORAL'])){
        if($requestData->is_claim == 'N')
        {
            if (($interval->y > 7) || ($interval->y == 7 && $interval->m > 8) || ($interval->y == 7 && $interval->m == 8 && $interval->d > 26))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Car Age greater than 07 Years 08 months and 26 Days',
                    'request' => [
                        'message' => 'Car Age greater than 07 Years 08 months and 26 Days',
                        'vehicle_age' => $vehicle_age,
                        'product_identifier' => $masterProduct->product_identifier,
                        'is_claim' => $requestData->is_claim
                    ],
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }
        }
        else
        {
            if (($interval->y > 5) || ($interval->y == 5 && $interval->m > 8) || ($interval->y == 5 && $interval->m == 8 && $interval->d > 27))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Car Age greater than 05 Years 08 months and 27 Days',
                    'request' => [
                        'message' => 'Car Age greater than 05 Years 08 months and 27 Days',
                        'vehicle_age' => $vehicle_age,
                        'product_identifier' => $masterProduct->product_identifier,
                        'is_claim' => $requestData->is_claim
                    ],
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }
        }
    }

    if((($interval->y > 10) || ($interval->y == 10 && $interval->m > 8) || ($interval->y == 10 && $interval->m == 8 && $interval->d > 25))
    && in_array($masterProduct->product_identifier, ['GOLD']))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Car Age greater than 10 Years 08 months and 25 Days',
            'request' => [
                'message' => 'Car Age greater than 10 Years 08 months and 25 Days',
                'vehicle_age' => $vehicle_age,
                'product_identifier' => $masterProduct->product_identifier
            ],
            'product_identifier' => $masterProduct->product_identifier
        ];
    }

    if((($interval->y > 15) || ($interval->y == 15 && $interval->m > 8) || ($interval->y == 15 && $interval->m == 8 && $interval->d > 24))
    && in_array($masterProduct->product_identifier, ['SILVER']))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Car Age greater than 15 Years 08 months and 24 Days',
            'request' => [
                'message' => 'Car Age greater than 15 Years 08 months and 24 Days',
                'vehicle_age' => $vehicle_age,
                'product_identifier' => $masterProduct->product_identifier
            ],
            'product_identifier' => $masterProduct->product_identifier
        ];
    }

    return [
        'status' => true,
        'message' => 'success'
    ];
}

function getApplicableAddonList($requestData, $interval, $is_liability)
{
    $applicable_addons = [
        'zeroDepreciation',
        'roadSideAssistance',
        'lopb',
        'keyReplace',
        'consumables',
        'tyreSecure',
        'engineProtector',
        'returnToInvoice',
        'ncbProtection'
    ];

    if ($interval->y >= 7)
    {
        $applicable_addons = spliceArayKey($applicable_addons, 'zeroDepreciation');
        $applicable_addons = spliceArayKey($applicable_addons, 'tyreSecure');
        $applicable_addons = spliceArayKey($applicable_addons, 'consumables');
        $applicable_addons = spliceArayKey($applicable_addons, 'engineProtector');
    }

    if ($interval->y >= 5 && $requestData->is_claim == 'Y')
    {
        $applicable_addons = spliceArayKey($applicable_addons, 'zeroDepreciation');
        $applicable_addons = spliceArayKey($applicable_addons, 'tyreSecure');
        $applicable_addons = spliceArayKey($applicable_addons, 'consumables');
        $applicable_addons = spliceArayKey($applicable_addons, 'engineProtector');
    }

    if(($interval->y > 3) || ($interval->y == 3 && $interval->m > 8) || ($interval->y == 3 && $interval->m == 8 && $interval->d > 27))//if($interval->y >= 3)
    {
        $applicable_addons = spliceArayKey($applicable_addons, 'returnToInvoice');
    }

    if (!($requestData->applicable_ncb > 25)) {
        $applicable_addons = spliceArayKey($applicable_addons, 'ncbProtection');
    }

    if($is_liability){
        $applicable_addons = [];
    }

    return $applicable_addons;
}

function spliceArayKey($arr, $key)
{
    if(array_search($key, $arr) !== false){
        array_splice($arr, array_search($key, $arr), 1);
    }
    return $arr;
}
