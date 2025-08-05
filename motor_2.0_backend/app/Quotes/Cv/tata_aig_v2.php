<?php

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Proposal\Services\tataAigV2SubmitProposal as TATA_V2;
use App\Models\MasterPolicy;
use Predis\Response\Status;
use App\Models\TataAigV2SpecialRegNo;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/TataAigV2Helper.php';
function getGcvV2Quotes($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    $configCreds = TATA_V2::getTataAigV2CvCreds();

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
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

    // 

    // if (isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y') {
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


    $check_mmv = checkTataAigMMV($productData, $requestData->version_id, $requestData);
    $partially_build = isset($check_mmv['is_partial_build']) ? $check_mmv['is_partial_build'] : '';

    if (!$check_mmv['status']) {
        return $check_mmv;
    }

    //$mmv = (object)$check_mmv['data'];
    $mmv = (object) array_change_key_case((array) $check_mmv['data'],CASE_LOWER);

    // if(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_MMV_CHANGE_TO_UAT') == 'Y')
    // {
    //     $mmv = TATA_AIG::changeToUAT($mmv);
    // }

    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('d-m-Y') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);

    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = $interval->y;

    if (!$is_liability && ($requestData->previous_policy_type == 'third_party')) {
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

    // if($is_zero_dep && $vehicle_age >= 5){
    //     return [
    //         'status' => false,
    //         'message' => 'Zero Dept is not available above 5 Year Vehicle',
    //     ];
    // } 
    if ($masterProduct->product_identifier == 'PLATINUM' && strtoupper($mmv->make) != 'MARUTI') {
        return
            [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'PLATINUM package is only available for MARUTI.',
                'request' => [
                    'message' => 'PLATINUM package is only available for MARUTI.',
                    'product_identifier' => $masterProduct->product_identifier,
                    'manufacturer' => $mmv->make
                ]
            ];
    }

    if ($masterProduct->product_identifier == 'PEARL++' && $requestData->business_type != 'newbusiness') {
        return
            [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'PEARL++ package is only available for new vehicles.',
                'request' => [
                    'message' => 'PEARL++ package is only available for new vehicles.',
                    'product_identifier' => $masterProduct->product_identifier,
                    'business_type' => $requestData->business_type
                ]
            ];
    }

    // if (!$is_new && !$is_od && !$is_liability && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure') && !in_array($masterProduct->product_identifier, ['SILVER', 'GOLD'])) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Break-In Quotes Not Allowed',
    //         'request' => [
    //             'message' => 'Break-In Quotes Not Allowed',
    //             'previous_policy_typ' => $requestData->previous_policy_type
    //         ]
    //     ];
    // }


    // if (!$is_new && !$is_liability && $requestData->previous_policy_type == 'Not sure') {
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
    
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

    $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);

    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);

    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAPaidDriverConductorCleaner = $PAforaddionaldPassenger = $llpaidDriver = $NonElectricalaccess = $is_Geographical = "No";

    $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = '';



    $externalCNGKIT = 'No';

    $is_electrical = false;
    $is_non_electrical = false;
    $is_lpg_cng = false;

    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value) && !$is_liability) {
            $Electricalaccess = "Yes";
            $ElectricalaccessSI = $value['sumInsured'];
            $is_electrical = true;
        }

        if (in_array('Non-Electrical Accessories', $value) && !$is_liability) {
            $NonElectricalaccess = "Yes";
            $NonElectricalaccessSI = $value['sumInsured'];
            $is_non_electrical = true;
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
            $externalCNGKIT = "Yes";
            $externalCNGKITSI = $value['sumInsured'];
            $is_lpg_cng = true;
            if ($mmv->txt_fuel_type != ' External CNG' || $mmv->txt_fuel_type != ' External LPG') {
                $mmv->txt_fuel_type = 'External CNG';
                $mmv->txt_fuelcode = '5';
            }
        }

    }
    $is_ll_paid = "No";
    $is_pa_paid = "No";
    $is_pa_unnamed = false;
    $LLNumberDriver = 0;
    $LLNumberConductor = 0;
    $LLNumberCleaner = 0;
    $countries = [];
    $imt_23 = "No";
    
    if ($selected_addons && $selected_addons->addons != NULL && $selected_addons->addons != '')
        {
            
            foreach ($selected_addons->addons as $applicable_addon)
            {
                if ($applicable_addon['name'] == 'IMT - 23')
                {
                    $imt_23 = "Yes";
                }
            
            }

            
        }

    foreach ($additional_covers as $key => $value) {
        if ($value['name'] == 'PA paid driver/conductor/cleaner') {
            $PAforaddionaldPaidDriver = "Yes";
            $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            $is_pa_paid = "Yes";
        }
        if ($value['name'] == 'LL paid driver/conductor/cleaner') {
            $LLNumberDriver = $value['LLNumberDriver'] ?? 0;
            $is_ll_paid = "Yes";
            $LLNumberCleaner = $value['LLNumberCleaner'] ?? 0;
            $is_ll_paid = "Yes";
            $LLNumberConductor = $value['LLNumberConductor'] ?? 0;
            $is_ll_paid = "Yes";

            $llpaidDriver = (in_array('DriverLL', $value['selectedLLpaidItmes']) || in_array('CleanerLL', $value['selectedLLpaidItmes']) || in_array('ConductorLL', $value['selectedLLpaidItmes'])) ? 'Yes' : 'No';
        }

        if ($value['name'] == 'Geographical Extension') {
            $countries = $value['countries'] ;
            $is_Geographical = "Yes";
        }
    }
    $is_automobile_assoc = false;
    $is_anti_theft = false;
    $is_voluntary_access = false;
    $is_tppd = false;

    $is_anti_theft_device_certified_by_arai = "false";
    $tppd_amt = 0;
    $voluntary_excess_amt = '';

    foreach ($discounts as $key => $data) {
        if ($data['name'] == 'anti-theft device' && !$is_liability) {
            $is_anti_theft = true;
            $is_anti_theft_device_certified_by_arai = 'true';
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured']) && !$is_liability) {
            $is_voluntary_access = true;
            $voluntary_excess_amt = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover' && !$is_od) {
            $is_tppd = true;
            $tppd_amt = '9999';
        }
    }

    if (config('constants.IcConstants.tata_aig_v2.NO_VOLUNTARY_DISCOUNT') == 'Y') {
        $is_voluntary_access = false;
        $voluntary_excess_amt = '';
    }

    $validateAccessoriesAmount = validateAccessoriesAmount($selected_addons);


    if (!$validateAccessoriesAmount['status']) {
        $validateAccessoriesAmount['product_identifier'] = $masterProduct->product_identifier;
        return $validateAccessoriesAmount;
    }
    // addon

    $vehicle_in_90_days = 0;

    $motor_manf_date = '01-' . $requestData->manufacture_year;

    $current_date = date('Y-m-d');

    if ($is_new) {
        $policyStartDate  = strtotime($requestData->vehicle_register_date); //date('Y-m-d');
        if ($is_liability) {
            $policyStartDate  = strtotime($requestData->vehicle_register_date . '+ 1 day');
        }
        $policy_start_date = date('Y-m-d', $policyStartDate);
        $policy_end_date    = date('Y-m-d', strtotime($policy_start_date . ' - 1 days + 3 year'));
    } else {
        $policy_start_date  = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
  
        if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
        }

        $policy_end_date    = date('Y-m-d', strtotime($policy_start_date . ' - 1 days + 1 year'));
    }

    $mmv_data = [
        'manf_name'             => $mmv->make,
        'model_name'            => $mmv->txt_model,
        'version_name'          => $mmv->txt_model_variant,
        'seating_capacity'      => $mmv->num_seating_capacity,
        'carrying_capacity'     => $mmv->num_seating_capacity - 1,
        'cubic_capacity'        => $mmv->num_cubic_capacity,
        'fuel_type'             => $mmv->txt_fuel_type,
        'gross_vehicle_weight'  => $mmv->num_gross_vehicle_weight,
        'vehicle_type'          => 'CV',
        'version_id'            => $mmv->ic_version_code,
    ];

    $customer_type = $is_individual ? "Individual" : "Organization";

    $btype_code = $requestData->business_type == "rollover" ? "2" : "1";
    $btype_name = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";

    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
        $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
    } else {
        $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
    }



    // ADDONS
    $applicableAddon = TATA_V2::getApplicableAddons($masterProduct, $is_liability, $interval);


    
    // if (($interval->y > 20) || ($interval->y == 20 && $interval->m > 8) || ($interval->y == 20 && $interval->m == 8 && $interval->d > 24))
    // {
    //     $applicableAddon['RoadsideAssistance'] = 'No'; 
    // }

    //  if ($vehicle_age >= 5 )                                  //Removing age validation according to Nirmal sir and Sahil. 17-08-2024
    // {
    //     $applicableAddon['RoadsideAssistance'] = 'No'; 
    // }
    // END ADDONS

    $is_tata_pos_disabled_renewbuy = config('constants.motorConstant.IS_TATA_POS_DISABLED_RENEWBUY');

    $is_pos = ($is_tata_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');

    $pos_testing_mode = ($is_tata_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motor.constants.IcConstants.tata_aig_v2.cv.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2');

    $pos_aadhar = '';
    $pos_pan    = '';
    $sol_id     = ''; //config('constants.IcConstants.tata_aig.SOAL_ID');
    $is_posp = 'N';
    $q_office_location = 0;

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type', 'P')
        ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if (!empty($pos_data->pan_no)) {
            $is_posp = 'Y';
            $sol_id = $pos_data->pan_no;
            $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
        } else {
            // Make this constant N for no quotes while pos login with no tata aig relationship and Y for pos login to non pos quotes
            if (config('constants.IcConstants.tata_aig_v2.cv.IS_TATA_AIG_V2_CAR_POS_TO_NON_POS') == 'N') {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Pos unique id for agent id - ' . $pos_data->agent_id . ' is not available in TATA IC relationship table',
                ];
            }
        }
    } elseif ($pos_testing_mode == 'Y') {
        $is_posp = 'Y';
        $sol_id     = '55554'; //'840372';
        $q_office_location = 90431;//90200;
    } else {
        $is_pos = 'N';
    }

    $rto_code = explode('-', $requestData->rto_code);
    $rto_data = DB::table('tata_aig_v2_rto_master')
        //->where('txt_rto_code', 'like', '%' . $rto_code[0] . $rto_code[1] . '%')
        ->where('txt_rto_code', str_replace('-', '',RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
        ->first();

    if (empty($rto_data)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO Data Not Found',
            'request' => [
                'message' => 'RTO Data Not Found',
                'rto_data' => $rto_data,
                'txt_rto_code' => $rto_code[0] . $rto_code[1]
            ]
        ];
    }

    $token_response = App\Http\Controllers\Proposal\Services\tataAigV2SubmitProposal::getToken($enquiryId, $productData, 'quote');

    if (!$token_response['status']) {
        $token_response['product_identifier'] = $masterProduct->product_identifier;
        return $token_response;
    }

    if (config('constants.IcConstants.tata_aig_v2.NO_ANTITHEFT') == 'Y') {
        $is_anti_theft = false;
    }

    if (config('constants.IcConstants.tata_aig_v2.NO_NCB_PROTECTION') == 'Y') {
        $applicableAddon['NCBProtectionCover'] = 'No';
    }

    if (in_array(strtoupper($mmv->txt_segment), ['MINI', 'COMPACT', 'MPS SUV', 'MPV SUV', 'MID SIZE'])) {
        $engineProtectOption = 'WITH DEDUCTIBLE';
    } else {
        $engineProtectOption = 'WITHOUT DEDUCTIBLE';
    }
    //our 
    $quoteRequest = [
        'quote_id'                      => '',

        'pol_plan_variant'              => ($is_package ? ($is_new ? 'PackagePolicy' : 'PackagePolicy') : ($is_liability ? ($is_new ? 'Standalone TP' : 'Standalone TP') : 'Standalone OD')),
        'pol_plan_id'                   => ($is_package ? ($is_new ? '04' : '02') : ($is_liability ? ($is_new ? '03' : '01') : '05')),

        'q_producer_code'               => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_PRODUCER_CODE'),
        'q_producer_email'              => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_PRODUCER_EMAIL'),

        'business_type_no'              => ($is_new ? '01' : '03'),
        "business_type"                 => ($is_new ? "New Business" : 'Roll Over'),
        // 'product_code'                  => '3184',
        // 'product_id'                    => 'M300000000001',
        // 'product_name'                  => 'Private Car',

        'proposer_type'                 => $customer_type,

        '__finalize'                    => '1',

        'add_towing'                    => 'No',
        'add_towing_amount'             => '',
        'allowance_days_accident'       => '',
        'allowance_days_loss'           => '',

        // DISCOUNTS
        'tppd_discount'                 => $is_tppd ? 'Yes' : 'No',
        'antitheft_cover'               => $is_anti_theft ? 'Yes' : 'No',
        'automobile_association_cover'  => $is_automobile_assoc ? 'Yes' : 'No',
        'voluntary_amount'              => (string)($voluntary_excess_amt),
        // END DISCOUNT

        'cng_lpg_cover'                 => (string)($externalCNGKIT),
        'cng_lpg_si'                    => ($is_liability ? '0' : (string)($externalCNGKITSI)),

        // ASSESORIES
        'electrical_si'                 => (string)($ElectricalaccessSI),
        "electrical_cover"               => $Electricalaccess,
        "electrical_desc"                 => "",


        'non_electrical_si'             => (string)($NonElectricalaccessSI),
        "non_electrical_cover"          => $NonElectricalaccess, #neW_tag_added_changes
        "non_electrical_desc"           => "", #neW_tag_added_changes
        // END ASSESORIES

        // COVERS
        'pa_named'                      => 'No',

        'pa_paid'                       => $is_pa_paid,  // 'pa_paid_no'                    =>  $is_pa_paid == "Yes" ? 1 : 0,
        'pa_paid_si'                    => $PAforaddionaldPaidDriverSI,

        'pa_owner'                      => ($is_individual && !$is_od) ? 'true' : 'false',
        'pa_owner_declaration'          => 'None',
        'pa_owner_tenure'               => ($is_individual && !$is_od) ? '1' : '',

        'pa_unnamed'                    => 'No',

        'll_paid'                       => $is_ll_paid,
        'll_paid_no'                    => $LLNumberDriver + $LLNumberCleaner + $LLNumberConductor,
        "cover_lamps" => $imt_23,
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
        'tyre_secure_options'           => $applicableAddon['TyreSecure'] == 'Yes' ? 'REPLACEMENT BASIS' : '', // 'DEPRECIATION BASIS'

        'engine_secure'                 => $applicableAddon['EngineSecure'],
        // 'engine_secure_options'         => $is_liability ? '' : $engineProtectOption,



        'dep_reimburse'                 => $applicableAddon['DepreciationReimbursement'],
        'dep_reimburse_claims'          => $applicableAddon['NoOfClaimsDepreciation'],

        // END ADDONS

        'claim_last'                    => ($is_new ? 'No' : (($requestData->is_claim == 'N' || $is_liability) ? 'No' : 'Yes')),

        'claim_last_amount'             => '',
        'claim_last_count'              => '',

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
        // 'motor_plan_opted'              => $masterProduct->product_identifier,
        // 'motor_plan_opted_no'           => $applicableAddon['motorPlanOptedNo'],
        // 'plan_opted'                     => $masterProduct->product_identifier,
        // 'plan_opted_no'                  => $applicableAddon['motorPlanOptedNo'],
        'own_premises'                  => 'No',
        'place_reg'                     => $rto_data->txt_rtolocation_name,
        'place_reg_no'                  => $rto_data->txt_rtolocation_code,
        'pre_pol_ncb'                   => (($is_new && $noPrevPolicy) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
        'pre_pol_protect_ncb'           => (($is_new && $noPrevPolicy) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
        'prev_pol_type'                 => (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package (1 year OD + 1 Year TP)' : 'Standalone TP (1 year TP)')),
        //'prev_pol_type'                 => (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package (1 year OD + 1 Year TP)' : 'Standalone TP (1 year TP)')),
        'proposer_pincode'              => $rto_data->num_pincode,
        "regno_1"                       => $vehicle_register_no[0] ?? "",
        "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")), // (string)($vehicle_register_no[1] ?? ""), 
        "regno_3"                       => $vehicle_register_no[2] ?? "",
        "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),
        'uw_discount'                   => '',
        'uw_loading'                    => '',
        'uw_remarks'                    => '',
        'vehicle_blind'                 => 'No',
        'vehicle_idv'                   => '',
        'vehicle_make'                  => $mmv->make,
        'vehicle_make_no'               => (int)($mmv->make_code),
        'vehicle_model'                 => $mmv->txt_model,
        'vehicle_model_no'              => (int)($mmv->num_model_code),
        'vehicle_variant'               => $mmv->txt_model_variant,
        'vehicle_variant_no'            => $mmv->num_model_variant_code,
        
        "gvw" => (int)((!empty($mmv->num_gross_vehicle_weight)) ? $mmv->num_gross_vehicle_weight : 0),
        "make_code" => (int)($mmv->make_code),
        "model_code" => (int)($mmv->num_model_code),
        "variant_code"                  => (int)$mmv->num_model_variant_code,
        // "variant_code" => 116337,
        "vehicle_sub_class" => $mmv->vehicle_sub_class,
        "vehicle_usage_type" => "Non Hazardous Gas Carrier",
        "vehicle_usage_type_code" => $mmv->num_vehicle_sub_class,
        'source'                        => 'P',
        'vintage_car'                   => 'No',
        'proposer_email'                => 'abcd@gmail.com',

        'add_pa_unnamed_si'                =>  0,
        'prev_dep'                          => "Yes",
        'prev_engine'                      => "Yes",
        'prev_tyre'                      => "Yes",
        'prev_rti' =>                       "Yes",

        'prev_cnglpg'     =>                "Yes",

        "geography_extension" => $is_Geographical,
        "geography_extension_bang" => in_array('Bangladesh', $countries) ? "Yes" : "No",
        "geography_extension_bhutan" => in_array('Bhutan', $countries) ? "Yes" : "No",
        "geography_extension_lanka" => in_array('Sri Lanka', $countries) ? "Yes" : "No",
        "geography_extension_maldives" => in_array('Nepal', $countries) ? "Yes" : "No",
        "geography_extension_nepal" =>in_array('Maldives', $countries) ? "Yes" : "No",
        "geography_extension_pak" => in_array('Pakistan', $countries) ? "Yes" : "No",
    ];
    // $quoteRequest['repair_glass']                  = 'Yes'; //$applicableAddon['RepairOfGlasPlastcFibNRubrGlas'];
    // $quoteRequest['return_invoice']                = 'Yes'; //$applicableAddon['ReturnToInvoice'];
    // $quoteRequest['emergency_expense']             = 'Yes'; //$applicableAddon['EmergTrnsprtAndHotelExpense'];
    // $quoteRequest['consumbale_expense']            = 'Yes'; //$applicableAddon['ConsumablesExpenses'];
    // $quoteRequest['key_replace']                   = 'Yes'; //$applicableAddon['KeyReplacement'];
    // $quoteRequest['personal_loss']                 = 'Yes'; //$applicableAddon['LossOfPersonalBelongings'];
    // $quoteRequest['tyre_secure']                   = 'No'; //$applicableAddon['TyreSecure'];
    // $quoteRequest['engine_secure']                 = 'Yes'; //$applicableAddon['EngineSecure'];
    // $quoteRequest['dep_reimburse']                 = 'Yes'; //$applicableAddon['DepreciationReimbursement'];
    if($premium_type == "third_party_breakin" || $premium_type == "third_party")
    {
        $quoteRequest['cover_lamps'] = "No";
    }
    if ($noPrevPolicy || ($noPrevPolicy && $is_liability)) {
        $quoteRequest['no_past_policy'] = 'Yes';
    }
    else{
        $quoteRequest['no_past_policy'] = 'No';

    }
    if ($is_pa_paid == "Yes") {
        $quoteRequest['pa_paid_no'] = 1;
    }

    if ($is_posp == "Y") {
        $quoteRequest['is_posp'] = $is_posp;
        $quoteRequest['sol_id'] = $sol_id;
        $quoteRequest['q_agent_pan'] = $sol_id;
        $quoteRequest['q_office_location'] = $q_office_location;
    }

    if (!$is_new) {
        $quoteRequest['no_past_pol'] = 'N';
    }
    if ($noPrevPolicy) {
        $quoteRequest['no_past_pol'] = 'Y';
    }

    if ($applicableAddon['NCBProtectionCover'] == 'Yes') {
        $quoteRequest['ncb_no_of_claims'] = 1;
    }

    if (!$is_new && !$noPrevPolicy) {
        if ($is_lpg_cng) {
            $quoteRequest['prev_cnglpg'] = 'Yes';
        }

        if ($is_liability) {
            $quoteRequest['prev_cnglpg'] = 'No';
        }


        if ($applicableAddon['ConsumablesExpenses'] == 'Yes') {
            $quoteRequest['prev_consumable'] = 'Yes';
        }

        if ($applicableAddon['ReturnToInvoice'] == 'Yes') {
            $quoteRequest['prev_rti'] = 'Yes';
        }

        if ($applicableAddon['TyreSecure'] == 'Yes') {
            $quoteRequest['prev_tyre'] = 'Yes';
        }

        if ($applicableAddon['EngineSecure'] == 'Yes') {
            $quoteRequest['prev_engine'] = 'Yes';
        }

        if ($applicableAddon['DepreciationReimbursement'] == 'Yes') {
            $quoteRequest['prev_dep'] = 'Yes';
        }
    }

    if (!$is_od) {
        if ($PAforUnnamedPassenger == 'Yes') {
            $quoteRequest['pa_unnamed'] = $PAforUnnamedPassenger;
            $quoteRequest['pa_unnamed_csi'] = '';
            $quoteRequest['pa_unnamed_no'] = (string)($mmv->num_seating_capacity);
            $quoteRequest['pa_unnamed_si'] = (string)$PAforUnnamedPassengerSI;
        }
        if ($llpaidDriver == 'Yes') {
            $quoteRequest['ll_paid'] = $llpaidDriver;
            $quoteRequest['ll_paid_no'] = $LLNumberDriver + $LLNumberCleaner + $LLNumberConductor; # #neW_tag_added_changes "ll_paid_no" => 2,
        }
        if ($PAforaddionaldPaidDriver == 'Yes') {
            $quoteRequest['pa_paid'] = $PAforaddionaldPaidDriver;
            $quoteRequest['pa_paid_no'] = 1;
            $quoteRequest['pa_paid_si'] = $PAforaddionaldPaidDriverSI;
        }
    }

    if ($is_od) {
        $quoteRequest['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
        $quoteRequest['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');

        $quoteRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
        $quoteRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
    }



    $quoteRequest = addAdditionalKeys($quoteRequest, $is_liability);

    $additional_data = [
        'enquiryId'         => $enquiryId,
        'headers'           => [
            'Content-Type'  => 'application/JSON',
            'Authorization'  => 'Bearer ' . $token_response['token'],
            'x-api-key'      => $configCreds->api_key
        ],
        'requestMethod'     => 'post',
        'requestType'       => 'json',
        'section'           => $productData->product_sub_type_code,
        'method'            => 'Premium Calculation',
        'transaction_type'  => 'quote',
        'productName'       => $productData->product_name,
        'token'             => $token_response['token'],
    ];

    // config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_QUOTE')
    // $url = 'https://uatapigw.tataaig.com/gcv-motor/v1/quote';

    $temp_data = $quoteRequest;
    unset($temp_data['regno_4']);
    $checksum_data = checksum_encrypt($temp_data);
    $additional_data['checksum'] = $checksum_data;
    //As per line 728 in CvWebServiceHelper tata_aig_v2 -> tata_aig
    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'tata_aig',$checksum_data,'CV');
    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_quoteResponse = $is_data_exist_for_checksum;
    }else{
        $get_quoteResponse = getWsData(config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
    }
    $quoteResponse = json_decode($get_quoteResponse['response'], true);

    if ($get_quoteResponse['response'] && $get_quoteResponse['response'] != '' && $get_quoteResponse['response'] != null) {
        $quoteResponse = json_decode($get_quoteResponse['response'], true);

        if (!empty($quoteResponse)) {
            if (!isset($quoteResponse['status'])) {
                if (isset($quoteResponse['message'])) {
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

            if ($quoteResponse['status'] != 200) {
                if (!isset($quoteResponse['message_txt'])) {
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
                    'mmv' => $mmv,
                    'QuoteRequest'   => $quoteRequest,
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            } else {

                try {

                    $quoteResponse2 = $quoteResponse;
                    $quoteResponse = $quoteResponse['data'][0]['data'];

                    if ($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true') {
                        return [
                            'status' => false,
                            'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                            'product_identifier' => $masterProduct->product_identifier,
                            'quoteResponse' => $quoteResponse
                        ];
                    }

                    // pass idv

                    $max_idv    = ($is_liability ? 0 : ($quoteResponse['max_idv_body'] ?? 0)  +  ($quoteResponse['max_idv_chassis'] ?? 0));
                    $min_idv    = ($is_liability ? 0 : ($quoteResponse['min_idv_body'] ?? 0) +  ($quoteResponse['min_idv_chassis'] ?? 0));
                    $min_body_idv = $quoteResponse['min_idv_body'];
                    $max_body_idv = $quoteResponse['max_idv_body'];
                    $min_chassis_idv = $quoteResponse['min_idv_chassis'];
                    $max_chassis_idv = $quoteResponse['max_idv_chassis'];

                    $idv = $is_liability ? 0 : ($quoteResponse2['data'][0]['pol_dlts']['vehicle_idv'] ?? 0);

                    $skip_second_call = false;
                    $body_idv = $chassis_idv = 0;
                    if ($requestData->is_idv_changed == "Y")
                    {
                        if ($requestData->edit_idv >= $max_idv)
                        {
                            $idv = round($max_idv);
                            $body_idv = $max_body_idv;
                            $chassis_idv = $max_chassis_idv;
                        }
                        else if ($requestData->edit_idv <= $min_idv)
                        {
                            $idv = round($min_idv);
                            $body_idv = $min_body_idv;
                            $chassis_idv = $min_chassis_idv;
                        }
                        else
                        {
                            $idv = round($requestData->edit_idv);
                        }
                    } else {
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $skip_second_call = true;
                                $idv = round($idv);
                                break;
                            case 'min_idv':
                                $idv = round($min_idv);
                                break;
                            case 'max_idv':
                                // $quoteRequest['max_idv'] = $max_idv;
                                $idv = round($max_idv);
                                break;
                            default:
                                // $quoteRequest['min_idv'] = $min_idv;
                                $idv = round($min_idv);
                                break;
                        }
                        // $idv = round($min_idv);
                    }
                    if($partially_build != false){
                        $quoteRequest['chassis_idv'] = $chassis_idv;
                        $quoteRequest['body_idv'] = $body_idv;
                    } 
                    $quoteRequest['vehicle_idv'] = (string)($idv);
                    $quoteRequest['__finalize'] = '1';

                    $additional_data = [
                        'enquiryId'         => $enquiryId,
                        'headers'           => [
                            'Content-Type'  => 'application/JSON',
                            'Authorization'  => 'Bearer ' . $token_response['token'],
                            'x-api-key'      => $configCreds->api_key
                        ],
                        'requestMethod'     => 'post',
                        'requestType'       => 'json',
                        'section'           => $productData->product_sub_type_code,
                        'method'            => 'Premium Re-Calculation',
                        'transaction_type'  => 'quote',
                        'productName'       => $productData->product_name,
                        'token'             => $token_response['token'],
                    ];
                    
                    if(!$skip_second_call){
                        $temp_data = $quoteRequest;
                        unset($temp_data['regno_4']);
                        $checksum_data = checksum_encrypt($temp_data);
                        $additional_data['checksum'] = $checksum_data;
                        //As per line 728 in CvWebServiceHelper tata_aig_v2 -> tata_aig
                        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'tata_aig', $checksum_data, 'CV');
                        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                            $get_response = $is_data_exist_for_checksum;
                        }else{
                            $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
                        }
                        $quoteResponse = $get_response['response'];

                        $quoteResponse = validaterequest($quoteResponse);
                        
                        if (!$quoteResponse['status']) {
                            $quoteResponse['product_identifier'] = $masterProduct->product_identifier;
                            return $quoteResponse;
                        }


                        $quoteResponse2 = $quoteResponse;

                        $quoteResponse = $quoteResponse['data'][0]['data'];
                        //pa_paid_drive_prem

                        if ($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true') {
                            return [
                                'status' => false,
                                'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                                'product_identifier' => $masterProduct->product_identifier,
                                'quoteResponse' => $quoteResponse
                            ];
                        }
                    }
                    // pass idv

                    $pol = $quoteResponse2['data'][0]['pol_dlts'];
                    

                    // BREAKIN LOGIC GIT ID 16070


                    $isInspectionApplicable = 'N';
                    $quoteResponse2['data'][0]['pol_dlts']['inspectionFlag'] = 'true';
                    if ((!in_array($premium_type, ['breakin', 'third_party', 'third_party_breakin'])) && isset($quoteResponse2['data'][0]['pol_dlts']['inspectionFlag']) && ($quoteResponse2['data'][0]['pol_dlts']['inspectionFlag'] == 'true' && config('TATA_AIG_V2_CHECK_ROLLOVER_BREAKIN_HANDLING') == 'Y')) {
                        $breakin_products = [];
                        if ($premium_type == 'comprehensive') {
                            $breakin_products = MasterPolicy::where('insurance_company_id', $masterProduct->ic_id)
                                ->where('premium_type_id', 4)
                                ->whereIn('product_sub_type_id', [9,10,11,12,13, 14, 15, 16])
                                ->get()->toArray(); 
                                                     
                        } else if ($premium_type == 'own_damage') {
                             $breakin_products = MasterPolicy::where('insurance_company_id', $masterProduct->ic_id)
                                ->where('premium_type_id', 6)
                                ->whereIn('product_sub_type_id', [9,10,11,12,13, 14, 15, 16])
                                ->get()->toArray();                             
                        }
                        if (count($breakin_products) > 0) {
                            $isInspectionApplicable = 'N';
                        }     
                        else {
                            return [
                                'status' => false,
                                'message' => 'Inspection Required',
                                'product_identifier' => $masterProduct->product_identifier,
                                'quoteResponse' => $quoteResponse
                            ];
                        }
                    }
                    if (in_array($premium_type, ['breakin'])) {
                        $isInspectionApplicable = 'Y';
                    } 
                    // BREAKIN LOGIC GIT ID 16070 END
                    $totalOdPremium = $quoteResponse['premium_break_up']['total_od_premium'];
                    $totalAddons    = $quoteResponse['premium_break_up']['total_addOns'];
                    $totalTpPremium = $quoteResponse['premium_break_up']['total_tp_premium'];
                    $netPremium     = (float)(isset($quoteResponse['premium_break_up']['net_premium']) ? $quoteResponse['premium_break_up']['net_premium'] : 0);
                    $finalPremium   = (float)(isset($quoteResponse['premium_break_up']['premium_value']) ? $quoteResponse['premium_break_up']['premium_value'] : 0);
                    $basic_od       = (float)(isset($totalOdPremium['od']['basic_od']) ? $totalOdPremium['od']['basic_od'] : 0);
                    $non_electrical = (float)(isset($pol['non_electrical_prem']) ? $pol['non_electrical_prem'] : 0);
                    $electrical     = (float)(isset($pol['electrical_prem']) ? ($pol['electrical_prem']) : 0);
                    $lpg_cng_od     = (float)(isset($totalOdPremium['od']['cng_lpg_od_prem']) ? $totalOdPremium['od']['cng_lpg_od_prem'] : 0);
                    $basic_tp       = (float)(isset($totalTpPremium['basic_tp']) ? $totalTpPremium['basic_tp'] : 0);
                    $pa_unnamed     = (float)(isset($totalTpPremium['pa_unnamed_prem']) ? $totalTpPremium['pa_unnamed_prem'] : 0);
                    $ll_paid        = (float)(isset($quoteResponse['premium_break_up']['total_tp_premium']['ll_paid_drive_prem']) ? $quoteResponse['premium_break_up']['total_tp_premium']['ll_paid_drive_prem'] : 0);
                    $lpg_cng_tp     = (float)(isset($totalTpPremium['cng_lpg_tp_prem']) ? $totalTpPremium['cng_lpg_tp_prem'] : 0);
                    $pa_paid        = (float)(isset($quoteResponse['premium_break_up']['total_tp_premium']['pa_paid_drive_prem']) ? $quoteResponse['premium_break_up']['total_tp_premium']['pa_paid_drive_prem'] : 0);
                    $pa_owner       = (float)(isset($totalTpPremium['cpa_prem']) ? $totalTpPremium['cpa_prem'] : 0);
                    $tppd_discount  = (float)(isset($pol['tppd_prem']) ? $pol['tppd_prem'] : 0);
                    $tp_gio = (float)(isset($totalTpPremium['geography_extension_tp_prem']) ? $totalTpPremium['geography_extension_tp_prem'] : 0 );
                    $od_gio = (float)(isset($totalOdPremium['od']['geography_extension_od_prem']) ? $totalOdPremium['od']['geography_extension_od_prem'] : 0 );
                    $final_tp_premium = $basic_tp + $pa_unnamed + $ll_paid + $lpg_cng_tp + $pa_paid + $tp_gio;
                    $final_od_premium = $basic_od + $non_electrical + $electrical + $lpg_cng_od + $od_gio;
                    $anti_theft_amount      = (float)(isset($totalOdPremium['discount_od']['atd_disc_prem']) ? $totalOdPremium['discount_od']['atd_disc_prem'] : 0);
                    $automoble_amount       = (float)(isset($totalOdPremium['discount_od']['aam_disc_prem']) ? $totalOdPremium['discount_od']['aam_disc_prem'] : 0);
                    $voluntary_deductible   = (float)(isset($totalOdPremium['discount_od']['vd_disc_prem']) ? $totalOdPremium['discount_od']['vd_disc_prem'] : 0);
                    $ncb_discount_amount    = (float)(isset($pol['curr_ncb_perc']) ? $pol['curr_ncb_perc'] : 0);
                    $final_total_discount = $ncb_discount_amount + $anti_theft_amount + $automoble_amount + $voluntary_deductible + $tppd_discount;
                    $zero_dep_amount            = (float)(isset($totalAddons['dep_reimburse_prem']) ? $totalAddons['dep_reimburse_prem'] : 0);
                    $imt_23 = $totalOdPremium['loading_od']['cover_lapm_prem'];
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
                    // $cpa = (float)(isset($totalAddons['cpa_prem']) ? $totalAddons['cpa_prem'] : 0);
                    $final_addon_amount         = (float)(isset($totalAddons['total_addon']) ? $totalAddons['total_addon'] : 0);
                    // $geog_Extension_OD_Premium = 0;
                    // $geog_Extension_TP_Premium = 0;
                    // $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
                    // 'additional' => [
                    //     'zero_depreciation' => ($is_zero_dep ? (isset($response['data']['C35']) ? round($response['data']['C35']['premium']) : 0) : 0),
                    //     'road_side_assistance' => 0,
                    //     'consumables' => 0,
                    // ]

                    if ($is_zero_dep) {
                        $add_on_data = [
                            'in_built'   => [
                                'zero_depreciation' => $zero_dep_amount
                            ],
                            'additional' => [
                                'imt23' => $imt_23,
                                'road_side_assistance' => $rsa_amount,
                                'tyre_secure' => 0,
                                'consumables' => 0,
                                'return_to_invoice' => 0
                            ],
                            'other'      => []
                        ];
                    } else {
                        $add_on_data = [
                            'in_built'   => [],
                            'additional' => [
                                'road_side_assistance' =>  $rsa_amount,
                                'imt23' => $imt_23,
                                'tyre_secure' => 0,
                                'consumables' => 0,
                                'return_to_invoice' => 0
                            ],
                            'other'      => []
                        ];
                    }

                    // if ($request->show_add_ons_data == 'Y') ;
                    // echo "<pre>".print_r([$add_on_data, $totalAddons]);echo "</pre>".die();

                    $in_built_premium = 0;
                    foreach ($add_on_data['in_built'] as $key => $value) {
                        if ($value === 0) {
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
                            'idv' => $premium_type == 'third_party' ? 0 : round($pol['vehicle_idv']),
                            'min_idv' => $premium_type == 'third_party' ? 0 : round($min_idv),
                            'max_idv' => $premium_type == 'third_party' ? 0 : round($max_idv),
                            'bodyIDV'                   => $body_idv,
                            'minBodyIDV'                => ($premium_type == 'third_party') ? 0 : $min_body_idv,
                            'maxBodyIDV'                => ($premium_type == 'third_party') ? 0 : $max_body_idv,

                            'chassisIDV'                => $chassis_idv,
                            'minChassisIDV'             => ($premium_type == 'third_party') ? 0 : $min_chassis_idv,
                            'maxChassisIDV'             => ($premium_type == 'third_party') ? 0 : $max_chassis_idv,

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
                            'seating_capacity' => $mmv->num_seating_capacity,
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
                            'final_gst_amount' => ($netPremium * 18 / 100),
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
                            'GeogExtension_ODPremium'                     => $od_gio,
                            'GeogExtension_TPPremium'                     => $tp_gio,
                            'isInspectionApplicable'                      => $isInspectionApplicable,
                            "imt_23" => $imt_23,
                            //
                            'motor_electric_accessories_value' => $electrical,
                            'motor_non_electric_accessories_value' => $non_electrical,
                            'LimitedtoOwnPremises_OD' => 0,
                            'LimitedtoOwnPremises_TP' => 0,
                        ],
                        'quoteResponse' => $quoteResponse
                    ];
                    if ($is_electrical) {
                        $data_response['Data']['motor_electric_accessories_value'] = $electrical;
                    }
                    if ($is_non_electrical) {
                        $data_response['Data']['motor_non_electric_accessories_value'] = $non_electrical;
                    }
                    if ($is_lpg_cng) {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng_od;
                        $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                    }
                    if (!empty($lpg_cng_od)) {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng_od;
                    }
                    if (!empty($lpg_cng_tp)) {
                        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                    }

                    if ($is_pa_paid) {
                        $data_response['Data']['motor_additional_paid_driver'] = $pa_paid;
                    }
                    if ($is_pa_unnamed) {
                        $data_response['Data']['cover_unnamed_passenger_value'] = $pa_unnamed;
                    }
                    if ($is_ll_paid) {
                        $data_response['Data']['default_paid_driver'] = $ll_paid;
                    }

                    if ($is_tppd) {
                        $data_response['Data']['tppd_discount'] = $tppd_discount;
                    }

                    if ($is_anti_theft) {
                        $data_response['Data']['antitheft_discount'] = $anti_theft_amount;
                    }
                    if ($is_voluntary_access) {
                        $data_response['Data']['voluntary_excess'] = $voluntary_deductible;
                    }

                    return camelCase($data_response);
                } catch (Exception $e) {

                    // echo "<pre>";print_r([
                    //     $e->getMessage().' '. $e->getLine(),
                    //     $quoteResponse2,
                    //     $quoteRequest,
                    // ]);echo "</pre>";die();
                }
                return [
                    'webservice_id' => $get_quoteResponse['webservice_id'],
                    'table' => $get_quoteResponse['table'],
                    'status'    => false,
                    'msg'       => $e->getMessage() . ' ' . $e->getLine(),
                    'response'     => $quoteResponse,
                ];
            }
        } else {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }
    } else {
        return [
            'status'    => false,
            'msg'       => 'Insurer Not Reachable',
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }
}

function validateAccessoriesAmount($selected_addons)
{
    if (isset($selected_addons->accessories[0]['sumInsured']) && $selected_addons->accessories[0]['sumInsured'] > 50000) {
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

    if (isset($selected_addons->accessories[1]['sumInsured']) && $selected_addons->accessories[1]['sumInsured'] > 50000) {
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

    if (isset($selected_addons->accessories[2]['sumInsured']) && $selected_addons->accessories[2]['sumInsured'] > 50000) {
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
    if ($requestData->business_type == 'newbusiness') {
        return [
            'status' => true,
            'message' => 'success'
        ];
    }
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('d-m-Y') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $vehicle_age = $interval->y;

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
        'ncbProtection',
        'imt23'
    ];

    if ($interval->y >= 7) {
        $applicable_addons = spliceArayKey($applicable_addons, 'zeroDepreciation');
        $applicable_addons = spliceArayKey($applicable_addons, 'tyreSecure');
        $applicable_addons = spliceArayKey($applicable_addons, 'consumables');
        $applicable_addons = spliceArayKey($applicable_addons, 'engineProtector');
    }

    if ($interval->y >= 5 && $requestData->is_claim == 'Y') {
        $applicable_addons = spliceArayKey($applicable_addons, 'zeroDepreciation');
        $applicable_addons = spliceArayKey($applicable_addons, 'tyreSecure');
        $applicable_addons = spliceArayKey($applicable_addons, 'consumables');
        $applicable_addons = spliceArayKey($applicable_addons, 'engineProtector');
    }

    if (($interval->y > 3) || ($interval->y == 3 && $interval->m > 8) || ($interval->y == 3 && $interval->m == 8 && $interval->d > 27)) //if($interval->y >= 3)
    {
        $applicable_addons = spliceArayKey($applicable_addons, 'returnToInvoice');
    }

    if (!($requestData->applicable_ncb > 25)) {
        $applicable_addons = spliceArayKey($applicable_addons, 'ncbProtection');
    }

    if ($is_liability) {
        $applicable_addons = [];
    }

    return $applicable_addons;
}

function spliceArayKey($arr, $key)
{
    if (array_search($key, $arr) !== false) {
        array_splice($arr, array_search($key, $arr), 1);
    }
    return $arr;
}

function checkTataAigMMV($productData, $version_id, $requestData)
{

    $product_sub_type_id = $productData->product_sub_type_id;
    $parent_id = get_parent_code($productData->product_sub_type_id);

    $mmv = get_mmv_details($productData, $version_id, 'tata_aig_v2', ($parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL));
    if ($mmv["status"] == 1) {
        $mmv_data = $mmv["data"];
    } else {
        return [
            "premium_amount" => "0",
            "status" => false,
            "message" => $mmv["message"],
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);

    $getVehiclePartialBuild = getVehiclePartialBuild($mmv_data->fyntune_version['version_id']);
    $mmv = array_merge($mmv, $getVehiclePartialBuild);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == "") {
        return camelCase([
            "premium_amount" => "0",
            "status" => false,
            "message" => "Vehicle Not Mapped",
        ]);
    } elseif ($mmv_data->ic_version_code == "DNE") {
        return camelCase([
            "premium_amount" => "0",
            "status" => false,
            "message" =>
            "Vehicle code does not exist with Insurance company",
        ]);
    }

    return (array)$mmv;
}
// 

function addAdditionalKeys($quoteRequest, $is_liability)
{
    $quoteRequest = array_merge($quoteRequest, [
        "add_pa" => "No",
        "add_pa_si" => "",
        "add_tppd" => "No",
        "add_tppd_si" => "",
        "add_transport" => "No",
        "add_transport_si" => "",
        "add_vehicle_towing" => "No",
        "add_vehicle_towing_amount" => "",
        "aoa_si" => "",
        "aoy_si" => "",
        "body_idv" => 0,
        "chassis_idv" => 0,
        "commerical_private" => "No",
        "confined_ownsite" => "No",
        // "cover_lamps" => "No",
        "cvplying_cluster" => "",
        "email" => "test@t.com",
        // "emergency_expense_si" => $is_liability ? 0 : 55000,
        "emergency_medical" => "No",
        "emergency_medical_si" => "",
        "emi_protector" => "No",
        "emi_protector_si" => "",
        "fleetCode" => 0,
        "fleetName" => "",
        "fleetOpted" => false,
        // GEOGRAPHICAL EXTENTION
        // END GEOGRAPHICAL EXTENTION
        "hired_hirer_35" => "No",
        "idemnity_hirer_36" => "No",
        "idemnity_hirer_44" => "No",
        "idemnity_hirer_45" => "No",
        "imposed_excess" => "",
        // "key_replace_si" => $is_liability ? 0 : 2,
        "liability_theft" => "",
        "ll_operator" => "No",
        "ll_operator_no" => "",
        "ll_pass" => "No",
        "ll_pass_accident" => "No",
        "ll_pass_accident_no" => "",
        "ll_pass_fare" => "No",
        "ll_pass_fare_no" => "",
        "ll_pass_no" => "",
        "ll_pass_non_fare" => "",
        "ll_pass_non_fare_emp" => "No",
        "ll_pass_non_fare_emp_no" => "",
        "ll_pass_non_fare_no" => "",
        "ll_wc" => "No",
        "ll_wc_no" => "",
        "loss_equipments" => "No",
        "loss_income" => "No",
        "loss_income_days" => "",
        "mobile_no" => "9988776655",
        "nature_goods" => "Non-Hazardous",
        // "ncb_protection" => "No",
        // "no_past_policy" => "",
        "optionForCalculation" => "Yearly",
        "overturning_load" => "No",
        // "personal_loss_si" => $is_liability ? 0 : 10000,
        // // PREVIOUS ADDON TAGS
        // "prev_cnglpg" => "No",
        // "prev_dep" => "No",
        // "prev_engine" => "No",
        // "prev_rti" => "No",
        // "prev_tyre" => "No",
        // // END PREVIOUS ADDON TAGS
        //PROPOSAL TAGS
        // "proposer_fname" => "Mahendra",
        // "proposer_fullname" => "",
        // "proposer_lname" => "Saru",
        // "proposer_mname" => "",
        // "proposer_mobile" => "8754285666",
        // "proposer_salutation" => "Mr",
        //END PROPOSAL TAGS

        "rim_guard" => "No",
        "rim_guard_si" => "",
        "route_from" => "",
        "route_to" => "",
        "special_regno" => "No",
        "sub_body_type" => "",
        "system_discount" => "",
        "theft_conversion" => "No",
        "total_idv" => "",
        "trailer_attach" => "No",
        "trailer_idv" => "",
        "trailer_idv1" => "",
        "trailer_idv2" => "",
        "trailer_no" => "",
        "vehicle_built_type" => "",
        "vehicle_carrying" => "2",
        "vehicle_city" => "",
        "vehicle_class" => "",
        "vehicle_color" => "",
        "vehicle_mobility" => "",
        "vehicle_permit" => "",
    ]);
    return $quoteRequest;
}


function validaterequest($response)
{
    // {"a":"d"}
    if (!($response && $response != '' && $response != null)) {
        return [
            'status'    => false,
            'msg'       => 'Insurer Not Reachable',
        ];
    }
    $response = json_decode($response, true);

    if (empty($response)) {
        return [
            'status'    => false,
            'msg'       => 'Insurer Not Reachable',
        ];
    }

    if(!isset($response['status']))
    {
        return [
            'status'    => false,
            'msg'       => $response['message_txt'] ?? 'Insurer Not Reachable',
        ];
    }

    if ($response['status'] != 200) {
        return [
            'status'    => false,
            'msg'       => $response['message_txt'] ?? 'Insurer Not Reachable',
        ];
    } else {
        return [
            'status'    => isset($response['data']) ? true : false,
            'data'      => $response['data'] ?? 'Insurer Not Reachable'
        ];
    }
}
function checkTataAigPcvMMV($productData, $version_id, $requestData)
{
    $product_sub_type_id = $productData->product_sub_type_id;
    $parent_id = get_parent_code($productData->product_sub_type_id);
    $mmv = get_mmv_details($productData, $version_id, 'tata_aig_v2');
    if ($mmv["status"] == 1) {
        $mmv_data = $mmv["data"];
    } else {
        return [
            "premium_amount" => "0",
            "status" => false,
            "message" => $mmv["message"],
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == "") {
        return camelCase([
            "premium_amount" => "0",
            "status" => false,
            "message" => "Vehicle Not Mapped",
        ]);
    } elseif ($mmv_data->ic_version_code == "DNE") {
        return camelCase([
            "premium_amount" => "0",
            "status" => false,
            "message" =>
            "Vehicle code does not exist with Insurance company",
        ]);
    }

    return (array)$mmv;
}

function getPCVV2Quotes($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    // if (($requestData->ownership_changed ?? '') == 'Y') {
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

    $configCreds = TATA_V2::getTataAigV2CvCreds();

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();

    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
    $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
    $is_three_months    = (($premium_type == 'short_term_3') ? true : false);
    $is_six_months      = (($premium_type == 'short_term_6') ? true : false);
    $is_breakin_short_term = ($premium_type == "short_term_3_breakin") ? true : false;
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

 


    $check_mmv = checkTataAigPcvMMV($productData, $requestData->version_id, $requestData);

    if (!$check_mmv['status']) {
        return $check_mmv;
    }

    $mmv = (object) array_change_key_case((array) $check_mmv['data'],CASE_LOWER);

    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('d-m-Y') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $vehicle_age_till_15_allowed = (in_array($mmv->txt_manufacturername, ['Mahindra','Maruti','Hyundai','Honda']) && $mmv->txt_fuel_type == 'PETROL') ? true : false;
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = $interval->y;

    if (!$is_liability && ($requestData->previous_policy_type == 'third_party')) {
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

    // if($is_zero_dep && $vehicle_age >= 5){
    //     return [
    //         'status' => false,
    //         'message' => 'Zero Dept is not available above 5 Year Vehicle',
    //     ];
    // } 
    

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
                //'manufacturer' => $mmv->manufacturer
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


    $DepreciationReimbursement = $ac_opted_in_pp = "N";
    if($is_zero_dep)
    {
        $DepreciationReimbursement  = 'Yes';
        $NoOfClaimsDepreciation     = "2";
    }
    else
    {
        $DepreciationReimbursement  = 'No';
        $NoOfClaimsDepreciation     = "";
    }

    if($is_liability){
        $DepreciationReimbursement = 'No';
        $NoOfClaimsDepreciation = "" ;
    }


    $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);

    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);

    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAPaidDriverConductorCleaner = $PAforaddionaldPassenger = $llpaidDriver = $NonElectricalaccess = $is_Geographical = "No";

    $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = '';



    $externalCNGKIT = 'No';

    $is_electrical = false;
    $is_non_electrical = false;
    $is_lpg_cng = false;

    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value) && !$is_liability) {
            $Electricalaccess = "Yes";
            $ElectricalaccessSI = $value['sumInsured'];
            $is_electrical = true;
        }

        if (in_array('Non-Electrical Accessories', $value) && !$is_liability) {
            $NonElectricalaccess = "Yes";
            $NonElectricalaccessSI = $value['sumInsured'];
            $is_non_electrical = true;
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
            $externalCNGKIT = "Yes";
            $externalCNGKITSI = $value['sumInsured'];
            $is_lpg_cng = true;
            if ($mmv->txt_fuel_type != ' External CNG' || $mmv->txt_fuel_type != ' External LPG') {
                $mmv->txt_fuel_type = 'External CNG';
                $mmv->num_fuel_type = '5';
            }
        }

    }
    $is_ll_paid = "No";
    $is_pa_paid = "No";
    $is_pa_unnamed = false;
    $LLNumberDriver = 0;
    $LLNumberConductor = 0;
    $LLNumberCleaner = 0;
    $countries = [];
    foreach ($additional_covers as $key => $value) {
        if ($value['name'] == 'PA paid driver/conductor/cleaner') {
            $PAforaddionaldPaidDriver = "Yes";
            $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            $is_pa_paid = "Yes";
        }
        if ($value['name'] == 'LL paid driver/conductor/cleaner') {
            $LLNumberDriver = $value['LLNumberDriver'] ?? 0;
            $is_ll_paid = "Yes";
            $LLNumberCleaner = $value['LLNumberCleaner'] ?? 0;
            $is_ll_paid = "Yes";
            $LLNumberConductor = $value['LLNumberConductor'] ?? 0;
            $is_ll_paid = "Yes";

            $llpaidDriver = (in_array('DriverLL', $value['selectedLLpaidItmes']) || in_array('CleanerLL', $value['selectedLLpaidItmes']) || in_array('ConductorLL', $value['selectedLLpaidItmes'])) ? 'Yes' : 'No';
        }
        if($value['name'] == 'LL paid driver'){
            $is_ll_paid = "Yes";
            $llpaidDriver = 'Yes';
            $LLNumberDriver = 1;
        }
        if ($value['name'] == 'Geographical Extension') {
            $countries = $value['countries'] ;
            $is_Geographical = "Yes";
        }
    }
    $is_automobile_assoc = false;
    $is_anti_theft = false;
    $is_voluntary_access = false;
    $is_tppd = false;

    $is_anti_theft_device_certified_by_arai = "false";
    $tppd_amt = 0;
    $voluntary_excess_amt = '';

    foreach ($discounts as $key => $data) {
        if ($data['name'] == 'anti-theft device' && !$is_liability) {
            $is_anti_theft = true;
            $is_anti_theft_device_certified_by_arai = 'true';
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured']) && !$is_liability) {
            $is_voluntary_access = true;
            $voluntary_excess_amt = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover' && !$is_od) {
            $is_tppd = true;
            $tppd_amt = '9999';
        }
    }

    if (config('constants.IcConstants.tata_aig_v2.NO_VOLUNTARY_DISCOUNT') == 'Y') {
        $is_voluntary_access = false;
        $voluntary_excess_amt = '';
    }

    $validateAccessoriesAmount = validateAccessoriesAmount($selected_addons);


    if (!$validateAccessoriesAmount['status']) {
        $validateAccessoriesAmount['product_identifier'] = $masterProduct->product_identifier;
        return $validateAccessoriesAmount;
    }
    // addon

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
    if($is_breakin_short_term)
    {
        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 2 days + 3 month'));
        $policy_start_date = date('Ymd', strtotime($policy_start_date . ' - 1 days'));

        if ($requestData->previous_policy_type == 'Not sure') {
            $policy_start_date = date('Ymd', strtotime($policy_start_date . ' + 1 days '));
            $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 3 month'));
        }
    }

    $mmv_data = [
        'manf_name'             => $mmv->txt_manufacturername,
        'model_name'            => $mmv->txt_model_name,
        'version_name'          => $mmv->txt_model_variant,
        'seating_capacity'      => $mmv->num_seating_capacity,
        'carrying_capacity'     => $mmv->num_carrying_capacity,
        'cubic_capacity'        => $mmv->num_cubic_capacity,
        'fuel_type'             => $mmv->txt_fuel_type,
        'gross_vehicle_weight'  => $mmv->num_gross_vehicle_weight,
        'vehicle_type'          => 'CV',
        'version_id'            => $mmv->ic_version_code,
    ];
    $customer_type = $is_individual ? "Individual" : "Organization";

    $btype_code = $requestData->business_type == "rollover" ? "2" : "1";
    $btype_name = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";

    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
        $vehicle_register_no = explode('-', strtoupper($requestData->vehicle_registration_no));
    } else {
        $vehicle_register_no = array_merge(explode('-', strtoupper($requestData->rto_code)), ['MGK', rand(1111, 9999)]);
    }


    // ADDONS
    // if (($selected_addons->addons[0]["name"] ?? " ") == "Road Side Assistance") {

    //     $applicableAddon['RoadsideAssistance'] = 'Yes';
    // } else {
    //     $applicableAddon['RoadsideAssistance'] = "No";
    // }
    $applicableAddon['RoadsideAssistance'] = "Yes";  
    $applicableAddon['ConsumablesExpenses'] = "Yes";
    $applicableAddon['TyreSecure'] = "No";
    $applicableAddon['EngineSecure'] = "No";
    $applicableAddon['ReturnToInvoice'] = "No";
    $applicableAddon['KeyReplacement'] = "No";
    $applicableAddon['EmergTrnsprtAndHotelExpense'] = "No";
    $applicableAddon['LossOfPersonalBelongings'] = "No";
    $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "Yes";
    $applicableAddon['DepreciationReimbursement'] = "Yes";
    $applicableAddon['NCBProtectionCover'] = 'No';

    // END ADDONS

    $is_tata_pos_disabled_renewbuy = config('constants.motorConstant.IS_TATA_POS_DISABLED_RENEWBUY');

    $is_pos = ($is_tata_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');

    $pos_testing_mode = ($is_tata_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motor.constants.IcConstants.tata_aig_v2.cv.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2');

    $pos_aadhar = '';
    $pos_pan    = '';
    $sol_id     = ''; //config('constants.IcConstants.tata_aig.SOAL_ID');
    $is_posp = 'N';
    $q_office_location = 0;

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type', 'P')
        ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if (!empty($pos_data->pan_no)) {
            $is_posp = 'Y';
            $sol_id = $pos_data->pan_no;
            $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
        } else {
            // Make this constant N for no quotes while pos login with no tata aig relationship and Y for pos login to non pos quotes
            if (config('constants.IcConstants.tata_aig_v2.cv.IS_TATA_AIG_V2_CAR_POS_TO_NON_POS') == 'N') {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Pos unique id for agent id - ' . $pos_data->agent_id . ' is not available in TATA IC relationship table',
                ];
            }
        }
    } elseif ($pos_testing_mode == 'Y') {
        $is_posp = 'Y';
        $sol_id     = '55554'; //'840372';
        $q_office_location = 90431;//90200;
    } else {
        $is_pos = 'N';
    }

    $rto_code = explode('-', $requestData->rto_code);
    $rto_data = DB::table('tata_aig_v2_rto_master')
        //->where('txt_rto_code', 'like', '%' . $rto_code[0] . $rto_code[1] . '%')
        ->where('txt_rto_code', str_replace('-', '',RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
        ->first();

    if (empty($rto_data)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO Data Not Found',
            'request' => [
                'message' => 'RTO Data Not Found',
                'rto_data' => $rto_data,
                'txt_rto_code' => $rto_code[0] . $rto_code[1]
            ]
        ];
    }

    //Special registration number
    $special_regno = "No";
    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
        $pattern = '/^[A-Z]{2}-[0-9]{2}--[0-9]+$/';
        if (preg_match($pattern, $requestData->vehicle_registration_no)) {
            $special_regno = "Yes";
        } else {
            $special_regno = "No";
        }
    }
    
        $special_rto_data = TataAigV2SpecialRegNo::where('rto', str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code, true)))
            ->exists();
        if ($special_rto_data) {
            $special_regno = "Yes";
        }

    if (sizeof($vehicle_register_no) == 3) {
        $special_regno = "Yes";
        $number = $vehicle_register_no[2];
        $reg_no = str_split($number, strlen($number)/2);
        $vehicle_register_no[2] = $reg_no[0];
        $vehicle_register_no[3] = $reg_no[1];
    }

    if (config('constants.IcConstants.tata_aig_v2.NO_ANTITHEFT') == 'Y') {
        $is_anti_theft = false;
    }

    if (config('constants.IcConstants.tata_aig_v2.NO_NCB_PROTECTION') == 'Y') {
        $applicableAddon['NCBProtectionCover'] = 'No';
    }

    if (in_array(strtoupper($mmv->txt_segment), ['MINI', 'COMPACT', 'MPS SUV', 'MPV SUV', 'MID SIZE'])) {
        $engineProtectOption = 'WITH DEDUCTIBLE';
    } else {
        $engineProtectOption = 'WITHOUT DEDUCTIBLE';
    }
    //basic without addon product
    if ($productData->product_identifier == "short_term_3_month_basic") {
        $applicableAddon['RoadsideAssistance'] = "No";
        $applicableAddon['ConsumablesExpenses'] = "No";
        $applicableAddon['TyreSecure'] = "No";
        $applicableAddon['EngineSecure'] = "No";
        $applicableAddon['ReturnToInvoice'] = "No";
        $applicableAddon['KeyReplacement'] = "No";
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "No";
        $applicableAddon['LossOfPersonalBelongings'] = "No";
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "No";
        $applicableAddon['NCBProtectionCover'] = 'No';
    } elseif ($productData->product_identifier == "short_term_3_month_basic_withaddons") {   // basic with addon expect zero_dep   
        $applicableAddon['RoadsideAssistance'] = "Yes";
        $applicableAddon['ConsumablesExpenses'] = "Yes";
        $applicableAddon['TyreSecure'] = "No";
        $applicableAddon['EngineSecure'] = "No";
        $applicableAddon['ReturnToInvoice'] = "No";
        $applicableAddon['KeyReplacement'] = "No";
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "No";
        $applicableAddon['LossOfPersonalBelongings'] = "No";
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "Yes";
        $applicableAddon['DepreciationReimbursement'] = "No";
        $applicableAddon['NCBProtectionCover'] = 'No';
    }

    // if($is_zero_dep)
    // {
    //     $applicableAddon['RoadsideAssistance'] = "No";
    //     $applicableAddon['ConsumablesExpenses'] = "No";
    // }
    $prevPolyStartDate = '';
        if (in_array($requestData->previous_policy_type, ['Comprehensive', 'Third-party', 'Own-damage'])) {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
        }
    
        if ($requestData->prev_short_term == "1") {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subMonth(3)->addDay(1)->format('Y-m-d');
        } 
    //our 
    $quoteRequest = [
        "source" => "P",
        "sub_product_code" => $mmv->num_product,
        "sub_product_name" => "PCV",
        "vehicle_usage_type" => "Corporate Taxi",
        "vehicle_usage_type_code" => "127",

        "q_producer_code" => config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_PRODUCER_CODE'),
        "q_producer_email" => config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_PRODUCER_EMAIL'),
        "sp_code" => "",
        "proposer_type" => $customer_type,
        "email" => "abc@tataaig.com",
        "mobile_no" => "9988776655",

        "product_id" => config("constants.IcConstants.tata_aig.PCV_PRODUCT_ID"),
        "proposer_pincode" => $rto_data->num_pincode,
        "business_type" => ($is_breakin_short_term ? "Used Vehicle" : 'Roll Over'),
        "business_type_no" => ($is_breakin_short_term ? '04' : '03'),
        "dor_first" => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),
        "dor" => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),
        "pol_plan_variant" => ($is_liability ? 'Standalone TP' : "PackagePolicy" ),
        // "pol_plan" => "Package (1 year OD + 1 year TP)",
        "pol_start_date" => Carbon::parse($policy_start_date)->format('Y-m-d'),
        "pol_end_date" => Carbon::parse($policy_end_date)->format('Y-m-d'),
        "prev_pol_end_date" => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d')),
        "prev_pol_start_date" => (($is_new || $noPrevPolicy) ? '' : $prevPolyStartDate),
        "man_year" => (int)(Carbon::parse($requestData->vehicle_register_date)->format('Y')),
        "special_regno" => $special_regno,
        "regno_1"                       => $vehicle_register_no[0] ?? "",
        "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")), // (string)($vehicle_register_no[1] ?? ""), 
        "regno_3"                       => $vehicle_register_no[2] ?? "",
        "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),

        "prev_pol_type" => (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package (1 year OD + 1 Year TP)' : 'Standalone TP (1 year TP)')),
        //addons
        "prev_dep" => "Yes",
        "prev_engine" => "Yes",
        "prev_tyre" => "Yes",
        "prev_rti" => "Yes",
        "prev_cnglpg" => "Yes",

        'claim_last'                    => ($is_new ? 'No' : (($requestData->is_claim == 'N' || $is_liability) ? 'No' : 'Yes')),
        'claim_last_amount'             => '',
        'claim_last_count'              => '',
        "pre_pol_ncb" => ((($is_new && $noPrevPolicy) || ($requestData->is_claim == 'Y')) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
        "pre_pol_protect_ncb" => "NA",

        "vehicle_class" => $mmv->num_vehicle_class,
        "vehicle_sub_class" => $mmv->num_vehicle_sub_class == '103' ? 'C2 PCV 4 Wheeler Exceeding 6 passenger' : 'C1A PCV 4 wheeler not exceeding 6 passengers',//$mmv->num_vehicle_sub_class,
        "vehicle_sub_class_no" => $mmv->num_vehicle_sub_class,
        "vehicle_make" => $mmv->txt_manufacturername,
        "vehicle_model" => $mmv->txt_model_name,
        "vehicle_variant" => $mmv->txt_model_variant,
        "make_code" => (int)$mmv->num_manufacturercode,
        "model_code" => (int)$mmv->num_model_code,
        "variant_code" => (int)$mmv->num_model_variant_code,

        'place_reg'                     => $rto_data->txt_rtolocation_name,
        'place_reg_no'                  => $rto_data->txt_rtolocation_code,

        "gvw" => (int)((!empty($mmv->num_gross_vehicle_weight)) ? $mmv->num_gross_vehicle_weight : 0),
        "sub_body_type" => "null",
        "nature_goods" => "Non-Hazardous",

        "vehicle_carrying" => $mmv->num_carrying_capacity,
        "vehicle_built_type" => "",
        "vehicle_color" => "",
        "vehicle_city" => "",
        "vehicle_mobility" => "",
        "route_from" => "",
        "route_to" => "",
        "vehicle_permit" => "",

        "body_idv" => 0,
        "chassis_idv" => 0,

         'cng_lpg_cover'                 => (string)($externalCNGKIT),
        'cng_lpg_si'                    => ($is_liability ? '0' : (string)($externalCNGKITSI)),

        "electrical_cover"               => $Electricalaccess,
        "non_electrical_cover" => $NonElectricalaccess,
        "electrical_si" => (string)($ElectricalaccessSI),
        "non_electrical_si" => (string)($NonElectricalaccessSI),

        "electrical_desc" => "",
        "non_electrical_desc" => "",
        "add_vehicle_towing" => "No",
        "add_vehicle_towing_amount" => "",
        "trailer_attach" => "No",
        "trailer_no" => "",
        "trailer_idv1" => 0,
        "trailer_idv2" => "",
        "trailer_idv" => "",
        "total_idv" => "",
        "system_discount" => "",
        "uw_loading" => "",
        "uw_discount" => "",
        "uw_remarks" => "",
        "antitheft_cover" =>  $is_anti_theft ? 'Yes' : 'No',
        "voluntary_amount" => (string)($voluntary_excess_amt),
        "tppd_discount" =>  $is_tppd ? 'Yes' : 'No',
        "own_premises" => "No",
        "vehicle_blind" => "No",
        "confined_ownsite" => "No",
        "ncb_protection" =>  $applicableAddon['NCBProtectionCover'],
        "ncb_no_of_claims" => 0,
        'tyre_secure'                   => $applicableAddon['TyreSecure'],
        'tyre_secure_options'           => $applicableAddon['TyreSecure'] == 'Yes' ? 'REPLACEMENT BASIS' : '',
        "engine_secure" => $applicableAddon['EngineSecure'],
        "engine_secure_options" => "",
        'dep_reimburse'                 => $DepreciationReimbursement,
        'dep_reimburse_claims'          =>  $NoOfClaimsDepreciation,
        "add_towing" => "No",
        "add_towing_amount" => "",
        "return_invoice" =>  $applicableAddon['ReturnToInvoice'],
        "consumbale_expense" => $applicableAddon['ConsumablesExpenses'],
        "rsa" => $applicableAddon['RoadsideAssistance'],
        "loss_equipments" => "No",

        "key_replace" => $applicableAddon['KeyReplacement'],
        "key_replace_si" => "",
        "emergency_expense" =>  $applicableAddon['EmergTrnsprtAndHotelExpense'],
        "emergency_expense_si" => "",
        "aoa_si" => "",
        "aoy_si" => "",
        "emergency_medical" => "No",
        "emergency_medical_si" => "",
        "personal_loss" => $applicableAddon['LossOfPersonalBelongings'],

        "personal_loss_si" => "",
        "add_tppd" => "No",
        "add_tppd_si" => "",
        "loss_income" => "No",
        "loss_income_days" => "",
        "emi_protector" => "No",
        "emi_protector_si" => "",
        "rim_guard" => "No",
        "rim_guard_si" => "",
        "add_transport" => "No",
        "add_transport_si" => "",
        "repair_glass" => $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'],
        'll_paid'                       => $is_ll_paid,
        'll_paid_no'                    => $LLNumberDriver + $LLNumberCleaner + $LLNumberConductor,

        "ll_pass" => "No",
        "ll_pass_no" => "",
        "ll_pass_fare" => "No",
        "ll_pass_fare_no" => "",
        "ll_pass_non_fare" => "No",
        "ll_pass_non_fare_no" => "",
        "ll_pass_non_fare_emp" => "No",
        "ll_pass_non_fare_emp_no" => "",
        "ll_operator" => "No",
        "ll_operator_no" => "",
        "ll_wc" => "No",
        "ll_wc_no" => "",
        "ll_pass_accident" => "No",
        "ll_pass_accident_no" => "",

        "load_fibre" => "No",
        "load_tuition" => "No",
        "cover_lamps" => "No",
        "load_imported" => "No",
        "commerical_private" => "No",
        "liability_theft" => "",
        "theft_conversion" => "No",
        "idemnity_hirer_36" => "No",
        "hired_hirer_35" => "No",
        "overturning_load" => "No",
        "imposed_excess" => "",
        "idemnity_hirer_44" => "No",
        "idemnity_hirer_45" => "No",
        "geography_extension" => $is_Geographical,
        "geography_extension_bang" => in_array('Bangladesh', $countries) ? "Yes" : "No",
        "geography_extension_bhutan" => in_array('Bhutan', $countries) ? "Yes" : "No",
        "geography_extension_lanka" => in_array('Sri Lanka', $countries) ? "Yes" : "No",
        "geography_extension_maldives" => in_array('Nepal', $countries) ? "Yes" : "No",
        "geography_extension_nepal" =>in_array('Maldives', $countries) ? "Yes" : "No",
        "geography_extension_pak" => in_array('Pakistan', $countries) ? "Yes" : "No",
        "add_pa" => "No",
        "add_pa_si" => "",
        "add_pa_unnamed" => "No",
        "add_pa_unnamed_si" => "",
        "cvplying_cluster" => "",

        'pa_owner'                      => ($is_individual && !$is_od) ? 'true' : 'false',
        'pa_owner_declaration'          => 'None',
        'pa_owner_tenure'               => ($is_individual && !$is_od) ? '1' : '',

        "pa_unnamed" => "No",
        "pa_unnamed_no" => "",
        "pa_unnamed_si" => "",
        
        'pa_paid'                       => $is_pa_paid,  // 'pa_paid_no'                    =>  $is_pa_paid == "Yes" ? 1 : 0,
        'pa_paid_si'                    => $PAforaddionaldPaidDriverSI,
        "pa_paid_no" => "",

        "no_past_pol" => "",
        "fleetCode"             => config('IC.TATA_AIG.V2.PCV.FLEET_CODE'),//"2182129",
        "fleetName"             => config('IC.TATA_AIG.V2.PCV.FLEET_NAME'),//"PCV PARTNER FLEET",
        "fleetOpted"            => true,
        "optionForCalculation"  => "Pro Rata",
        "quote_id" => "",
        "__finalize" => "1"
    ];
    // $quoteRequest['repair_glass']                  = 'Yes'; //$applicableAddon['RepairOfGlasPlastcFibNRubrGlas'];
    // $quoteRequest['return_invoice']                = 'Yes'; //$applicableAddon['ReturnToInvoice'];
    // $quoteRequest['emergency_expense']             = 'Yes'; //$applicableAddon['EmergTrnsprtAndHotelExpense'];
    // $quoteRequest['consumbale_expense']            = 'Yes'; //$applicableAddon['ConsumablesExpenses'];
    // $quoteRequest['key_replace']                   = 'Yes'; //$applicableAddon['KeyReplacement'];
    // $quoteRequest['personal_loss']                 = 'Yes'; //$applicableAddon['LossOfPersonalBelongings'];
    // $quoteRequest['tyre_secure']                   = 'No'; //$applicableAddon['TyreSecure'];
    // $quoteRequest['engine_secure']                 = 'Yes'; //$applicableAddon['EngineSecure'];
    // $quoteRequest['dep_reimburse']                 = 'Yes'; //$applicableAddon['DepreciationReimbursement'];
    if ($requestData->previous_policy_type == 'Not sure') {
        $quoteRequest['no_past_policy'] = 'Yes';
    }
    else{
        $quoteRequest['no_past_policy'] = 'No';

    }
    if ($is_pa_paid == "Yes") {
        $quoteRequest['pa_paid_no'] = 1;
    }

    if ($is_posp == "Y") {
        $quoteRequest['is_posp'] = $is_posp;
        $quoteRequest['sol_id'] = $sol_id;
        $quoteRequest['q_agent_pan'] = $sol_id;
        $quoteRequest['q_office_location'] = $q_office_location;
    }

    if ($applicableAddon['NCBProtectionCover'] == 'Yes') {
        $quoteRequest['ncb_no_of_claims'] = 1;
    }

    if (!$is_new && !$noPrevPolicy) {
        if ($is_lpg_cng) {
            $quoteRequest['prev_cnglpg'] = 'Yes';
        }

        if ($is_liability) {
            $quoteRequest['prev_cnglpg'] = 'No';
        }


        if ($applicableAddon['ConsumablesExpenses'] == 'Yes') {
            $quoteRequest['prev_consumable'] = 'Yes';
        }

        if ($applicableAddon['ReturnToInvoice'] == 'Yes') {
            $quoteRequest['prev_rti'] = 'Yes';
        }

        if ($applicableAddon['TyreSecure'] == 'Yes') {
            $quoteRequest['prev_tyre'] = 'Yes';
        }

        if ($applicableAddon['EngineSecure'] == 'Yes') {
            $quoteRequest['prev_engine'] = 'Yes';
        }

        if ($applicableAddon['DepreciationReimbursement'] == 'Yes') {
            $quoteRequest['prev_dep'] = 'Yes';
        }
    }

    if (!$is_od) {
        if ($PAforUnnamedPassenger == 'Yes') {
            $quoteRequest['pa_unnamed'] = $PAforUnnamedPassenger;
            $quoteRequest['pa_unnamed_csi'] = '';
            $quoteRequest['pa_unnamed_no'] = (string)($mmv->num_seating_capacity);
            $quoteRequest['pa_unnamed_si'] = (string)$PAforUnnamedPassengerSI;
        }
        if ($llpaidDriver == 'Yes') {
            $quoteRequest['ll_paid'] = $llpaidDriver;
            $quoteRequest['ll_paid_no'] = $LLNumberDriver + $LLNumberCleaner + $LLNumberConductor; # #neW_tag_added_changes "ll_paid_no" => 2,
        }
        if ($PAforaddionaldPaidDriver == 'Yes') {
            $quoteRequest['pa_paid'] = $PAforaddionaldPaidDriver;
            $quoteRequest['pa_paid_no'] = 1;
            $quoteRequest['pa_paid_si'] = $PAforaddionaldPaidDriverSI;
        }
    }

    if ($is_od) {
        $quoteRequest['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
        $quoteRequest['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');

        $quoteRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
        $quoteRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
    }

    $tokenResponse = TataAigV2Helper::getToken($enquiryId,$productData);

        if(!$tokenResponse['status']) {
            return $tokenResponse;
        }
    $temp_data = $quoteRequest;
    unset($temp_data['regno_4']);
    $checksum_data = checksum_encrypt($temp_data);

    $additional_data = [
        'enquiryId'         => $enquiryId,
        'requestMethod'     => 'post',
        'productName'       => $productData->product_name,
        'company'           => 'tata_aig_v2',
        'section'           => $productData->product_sub_type_code,
        'checksum'          => $checksum_data,
        'method'            => 'Premium Calculation',
        'transaction_type'  => "quote",
        'headers' => [
            'Content-Type'   => "application/json",
            "x-api-key" => $configCreds->api_key,
            'Authorization' =>  $tokenResponse["token"],
        ]

    ];

    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'tata_aig', $checksum_data, 'CV');
    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_quoteResponse = $is_data_exist_for_checksum;
      }
    else{
    $get_quoteResponse = getWsData(config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
    }
    $quoteResponse = json_decode($get_quoteResponse['response'], true);
    if ($get_quoteResponse['response'] && $get_quoteResponse['response'] != '' && $get_quoteResponse['response'] != null) {
        $quoteResponse = json_decode($get_quoteResponse['response'], true);

        if (!empty($quoteResponse)) {
            if (!isset($quoteResponse['status'])) {
                if (isset($quoteResponse['message'])) {
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

            if ($quoteResponse['status'] != 200) {
                if (!isset($quoteResponse['message_txt'])) {
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
                    'mmv' => $mmv,
                    'QuoteRequest'   => $quoteRequest,
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            } else {

                try {

                    $quoteResponse2 = $quoteResponse;
                    $quoteResponse = $quoteResponse['data'][0]['data'];

                    if ($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true') {
                        return [
                            'status' => false,
                            'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                            'product_identifier' => $masterProduct->product_identifier,
                            'quoteResponse' => $quoteResponse
                        ];
                    }

                    // pass idv

                    $max_idv    = ($is_liability ? 0 : ($quoteResponse['max_idv_body'] ?? 0)  +  ($quoteResponse['max_idv_chassis'] ?? 0));
                    $min_idv    = ($is_liability ? 0 : ($quoteResponse['min_idv_body'] ?? 0) +  ($quoteResponse['min_idv_chassis'] ?? 0));

                    $idv = $is_liability ? 0 : ($quoteResponse2['data'][0]['pol_dlts']['vehicle_idv'] ?? 0);

                    $skip_second_call = false;

                    if ($requestData->is_idv_changed == "Y")
                    {
                        if ($requestData->edit_idv >= $max_idv)
                        {
                            $idv = round($max_idv);
                        }
                        else if ($requestData->edit_idv <= $min_idv)
                        {
                            $idv = round($min_idv);
                        }
                        else
                        {
                            $idv = round($requestData->edit_idv);
                        }
                    } else {
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $skip_second_call = true;
                                $idv = round($idv);
                                break;
                            case 'min_idv':
                                $idv = round($min_idv);
                                break;
                            case 'max_idv':
                                // $quoteRequest['max_idv'] = $max_idv;
                                $idv = round($max_idv);
                                break;
                            default:
                                // $quoteRequest['min_idv'] = $min_idv;
                                $idv = round($min_idv);
                                break;
                        }
                        // $idv = round($min_idv);
                    }

                    $quoteRequest['vehicle_idv'] = (string)($idv);
                    $quoteRequest['__finalize'] = '1';
                    $quoteRequest["chassis_idv"] = ($idv);
                   
                    if ($premium_type != 'third_party' && $premium_type != 'third_party_breakin') {
                        $validationForAccessories = (15 / 100) * ($idv);
                        $NonElectricalaccessAmount = $NonElectricalaccessSI == "" ? 0 : $NonElectricalaccessSI;
                        $ElectricalaccessAmount = $ElectricalaccessSI == "" ? 0 : $ElectricalaccessSI;
                        $addditionElecNonelec =  $NonElectricalaccessAmount + $ElectricalaccessAmount;
                        if ( (int)$validationForAccessories <= $NonElectricalaccessAmount ) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Accessories Amount (Electrical, Non Electrical) cannot be grater than 15% of the vehicle idv',
                                'request' => [
                                    'message' => 'Accessories Amount (Electrical, Non Electrical) cannot be grater than 15% of the vehicle idv',
                                    'idv_amount' => $idv,
                                    'Acessories_amount' => $NonElectricalaccessSI
                                ]
                            ];
                        } elseif ( (int)$validationForAccessories <= $ElectricalaccessAmount ) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Accessories Amount (Electrical, Non Electrical) cannot be grater than 15% of the vehicle idv',
                                'request' => [
                                    'message' => 'Accessories Amount (Electrical, Non Electrical) cannot be grater than 15% of the vehicle idv',
                                    'idv_amount' => $idv,
                                    'Acessories_amount' => $ElectricalaccessSI
                                ]
                            ];
                        } elseif ( (int)$validationForAccessories <= $addditionElecNonelec ) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Accessories Amount (Electrical, Non Electrical) cannot be grater than 15% of the vehicle idv',
                                'request' => [
                                    'message' => 'Accessories Amount (Electrical, Non Electrical) cannot be grater than 15% of the vehicle idv',
                                    'idv_amount' => $idv,
                                    'Acessories_amount' => $addditionElecNonelec
                                ]
                            ];
                        }
                    }
                    $temp_data = $quoteRequest;
                    unset($temp_data['regno_4']);
                    $checksum_data = checksum_encrypt($temp_data);

                    $additional_data = [
                        'enquiryId'         => $enquiryId,
                        'requestMethod'     => 'post',
                        'productName'       => $productData->product_name,
                        'company'           => 'tata_aig_v2',
                        'section'           => $productData->product_sub_type_code,
                        'checksum'          => $checksum_data,
                        'method'            => 'Premium Re-Calculation',
                        'transaction_type'  => "quote",
                        'headers' => [
                            'Content-Type'   => "application/json",
                            "x-api-key"      => $configCreds->api_key,
                            'Authorization'  =>  $tokenResponse["token"],
                        ]
                
                    ];
                    if(!$skip_second_call){
                        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'tata_aig', $checksum_data, 'CV');
                        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                            $get_response = $is_data_exist_for_checksum;
                          }
                        else{
                        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
                        }
                        $quoteResponse = $get_response['response'];
                        $quoteResponse = validaterequest($quoteResponse);
                        
                        if (!$quoteResponse['status']) {
                            $quoteResponse['product_identifier'] = $masterProduct->product_identifier;
                            return $quoteResponse;
                        }


                        $quoteResponse2 = $quoteResponse;

                        $quoteResponse = $quoteResponse['data'][0]['data'];
                        //pa_paid_drive_prem

                        if ($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true') {
                            return [
                                'status' => false,
                                'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                                'product_identifier' => $masterProduct->product_identifier,
                                'quoteResponse' => $quoteResponse
                            ];
                        }
                    }
                    // pass idv

                    $pol = $quoteResponse2['data'][0]['pol_dlts'];
                    

                    // BREAKIN LOGIC GIT ID 16070

                    $isInspectionApplicable = 'N';
                    $quoteResponse2['data'][0]['pol_dlts']['inspectionFlag'] = 'true';
                    if ((!in_array($premium_type, ['breakin', 'third_party', 'third_party_breakin'])) && isset($quoteResponse2['data'][0]['pol_dlts']['inspectionFlag']) && ($quoteResponse2['data'][0]['pol_dlts']['inspectionFlag'] == 'true' && config('TATA_AIG_V2_CHECK_ROLLOVER_BREAKIN_HANDLING') == 'Y')) {
                        $breakin_products = [];
                        if ($premium_type == 'comprehensive') {
                            $breakin_products = MasterPolicy::where('insurance_company_id', $masterProduct->ic_id)
                                ->where('premium_type_id', 4)
                                ->where('product_sub_type_id', 1)
                                ->get()->toArray();
                        } else if ($premium_type == 'own_damage') {
                            $breakin_products = MasterPolicy::where('insurance_company_id', $masterProduct->ic_id)
                                ->where('premium_type_id', 6)
                                ->where('product_sub_type_id', 1)
                                ->get()->toArray();
                        }
                        if (count($breakin_products) > 0) {
                            $isInspectionApplicable = 'Y';
                        } else {
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
                    $finalPremium   = (float)(isset($quoteResponse['premium_break_up']['premium_value']) ? $quoteResponse['premium_break_up']['premium_value'] : 0);
                    $basic_od       = (float)(isset($totalOdPremium['od']['basic_od']) ? $totalOdPremium['od']['basic_od'] : 0);
                    $non_electrical = (float)(isset($pol['non_electrical_prem']) ? $pol['non_electrical_prem'] : 0);
                    $electrical     = (float)(isset($pol['electrical_prem']) ? ($pol['electrical_prem']) : 0);
                    $lpg_cng_od     = (float)(isset($totalOdPremium['od']['cng_lpg_od_prem']) ? $totalOdPremium['od']['cng_lpg_od_prem'] : 0);
                    $basic_tp       = (float)(isset($totalTpPremium['basic_tp']) ? $totalTpPremium['basic_tp'] : 0);
                    $pa_unnamed     = (float)(isset($totalTpPremium['pa_unnamed_prem']) ? $totalTpPremium['pa_unnamed_prem'] : 0);
                    $ll_paid        = (float)(isset($quoteResponse['premium_break_up']['total_tp_premium']['ll_paid_drive_prem']) ? $quoteResponse['premium_break_up']['total_tp_premium']['ll_paid_drive_prem'] : 0);
                    $lpg_cng_tp     = (float)(isset($totalTpPremium['cng_lpg_tp_prem']) ? $totalTpPremium['cng_lpg_tp_prem'] : 0);
                    $pa_paid        = (float)(isset($quoteResponse['premium_break_up']['total_tp_premium']['pa_paid_drive_prem']) ? $quoteResponse['premium_break_up']['total_tp_premium']['pa_paid_drive_prem'] : 0);
                    $pa_owner       = (float)(isset($totalTpPremium['cpa_prem']) ? $totalTpPremium['cpa_prem'] : 0);
                    $tppd_discount  = (float)(isset($pol['tppd_prem']) ? $pol['tppd_prem'] : 0);
                    $tp_gio = (float)(isset($totalTpPremium['geography_extension_tp_prem']) ? $totalTpPremium['geography_extension_tp_prem'] : 0 );
                    $od_gio = (float)(isset($totalOdPremium['od']['geography_extension_od_prem']) ? $totalOdPremium['od']['geography_extension_od_prem'] : 0 );
                    $final_tp_premium = $basic_tp + $pa_unnamed + $ll_paid + $lpg_cng_tp + $pa_paid + $tp_gio;
                    $final_od_premium = $basic_od + $non_electrical + $electrical + $lpg_cng_od + $od_gio;
                    $anti_theft_amount      = (float)(isset($totalOdPremium['discount_od']['atd_disc_prem']) ? $totalOdPremium['discount_od']['atd_disc_prem'] : 0);
                    $automoble_amount       = (float)(isset($totalOdPremium['discount_od']['aam_disc_prem']) ? $totalOdPremium['discount_od']['aam_disc_prem'] : 0);
                    $voluntary_deductible   = (float)(isset($totalOdPremium['discount_od']['vd_disc_prem']) ? $totalOdPremium['discount_od']['vd_disc_prem'] : 0);
                    $ncb_discount_amount    = (float)(isset($pol['curr_ncb_perc']) ? $pol['curr_ncb_perc'] : 0);
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
                    // $cpa = (float)(isset($totalAddons['cpa_prem']) ? $totalAddons['cpa_prem'] : 0);
                    $final_addon_amount         = (float)(isset($totalAddons['total_addon']) ? $totalAddons['total_addon'] : 0);
                    // $geog_Extension_OD_Premium = 0;
                    // $geog_Extension_TP_Premium = 0;
                    // $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
                    // 'additional' => [
                    //     'zero_depreciation' => ($is_zero_dep ? (isset($response['data']['C35']) ? round($response['data']['C35']['premium']) : 0) : 0),
                    //     'road_side_assistance' => 0,
                    //     'consumables' => 0,
                    // ]

                    if ($is_zero_dep) {
                        $add_on_data = [
                            'in_built'   => [
                                'zero_depreciation' => $zero_dep_amount
                            ],
                            'additional' => [
                                //'imt23' => 0,
                                'road_side_assistance' => $rsa_amount,
                                'consumables' => $counsumable_amount

                            ],
                            'other'      => []
                        ];
                    } else {
                        $add_on_data = [
                            'in_built'   => [],
                            'additional' => [
                                'road_side_assistance' =>  $rsa_amount,
                                //'imt23' => 0,
                                'consumables' => $counsumable_amount
                            ],
                            'other'      => []
                        ];
                        if($productData->product_identifier == 'short_term_3_month_basic')
                        {
                            $add_on_data = [
                                'in_built'   => [],
                                'additional' => [],
                                'other'      => []
                            ];
                        }
                    }

                    // if ($request->show_add_ons_data == 'Y') ;
                    // echo "<pre>".print_r([$add_on_data, $totalAddons]);echo "</pre>".die();

                    $in_built_premium = 0;
                    foreach ($add_on_data['in_built'] as $key => $value) {
                        if ($value === 0) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'No value for In-Built addon'
                            ];
                        }
                        $in_built_premium = $in_built_premium + $value;
                    }

                    $applicable_addons = [];
                    if($productData->product_identifier != 'short_term_3_month_basic')
                    {
                        if (isset($add_on_data['additional']['consumables']) && $add_on_data['additional']['consumables'] !== 0) {
                            $applicable_addons = ['consumables'];
                        }
                        if (isset($add_on_data['additional']['road_side_assistance']) && $add_on_data['additional']['road_side_assistance'] !== 0) {
                            $applicable_addons = ['roadSideAssistance'];
                        }
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

                    // $applicable_addons = getApplicableAddonList($requestData, $interval, $is_liability);

                    $data_response = [
                        'webservice_id' => $get_response['webservice_id'] ?? ($get_quoteResponse['webservice_id'] ?? ''),
                        'table' => $get_response['table'] ?? ($get_quoteResponse['table'] ?? ''),
                        'status' => true,
                        'msg' => 'Found',
                        'product_identifier' => $masterProduct->product_identifier,
                        'Data' => [
                            'idv' => $premium_type == 'third_party' ? 0 : round($quoteRequest['vehicle_idv']),
                            'min_idv' => $premium_type == 'third_party' ? 0 : round($min_idv),
                            'max_idv' => $premium_type == 'third_party' ? 0 : round($max_idv),
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
                            'seating_capacity' => $mmv->num_seating_capacity,
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
                            'final_gst_amount' => ($netPremium * 18 / 100),
                            'final_payable_amount' => $finalPremium,
                            'service_data_responseerr_msg' => '',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'business_type' => ($is_new ? 'New Business' : ($is_breakin_short_term ? 'Break-in' : 'Roll over')),
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
                            'GeogExtension_ODPremium'                     => $od_gio,
                            'GeogExtension_TPPremium'                     => $tp_gio,
                            'isInspectionApplicable'                      => $isInspectionApplicable,
                            //
                            'motor_electric_accessories_value' => $electrical,
                            'motor_non_electric_accessories_value' => $non_electrical,
                            'LimitedtoOwnPremises_OD' => 0,
                            'LimitedtoOwnPremises_TP' => 0,
                        ],
                        'quoteResponse' => $quoteResponse
                    ];
                    if ($is_electrical) {
                        $data_response['Data']['motor_electric_accessories_value'] = $electrical;
                    }
                    if ($is_non_electrical) {
                        $data_response['Data']['motor_non_electric_accessories_value'] = $non_electrical;
                    }
                    if ($is_lpg_cng) {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng_od;
                        $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                    }
                    if (!empty($lpg_cng_od)) {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng_od;
                    }
                    if (!empty($lpg_cng_tp)) {
                        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                    }

                    if ($is_pa_paid) {
                        $data_response['Data']['motor_additional_paid_driver'] = $pa_paid;
                    }
                    if ($is_pa_unnamed) {
                        $data_response['Data']['cover_unnamed_passenger_value'] = $pa_unnamed;
                    }
                    if ($is_ll_paid) {
                        $data_response['Data']['default_paid_driver'] = $ll_paid;
                    }

                    if ($is_tppd) {
                        $data_response['Data']['tppd_discount'] = $tppd_discount;
                    }

                    if ($is_anti_theft) {
                        $data_response['Data']['antitheft_discount'] = $anti_theft_amount;
                    }
                    if ($is_voluntary_access) {
                        $data_response['Data']['voluntary_excess'] = $voluntary_deductible;
                    }
                    
                    if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                        $data_response['Data']['premiumTypeCode'] = $premium_type;
                    }

                    return camelCase($data_response);
                } catch (Exception $e) {

                    // echo "<pre>";print_r([
                    //     $e->getMessage().' '. $e->getLine(),
                    //     $quoteResponse2,
                    //     $quoteRequest,
                    // ]);echo "</pre>";die();
                }
                return [
                    'webservice_id' => $get_quoteResponse['webservice_id'],
                    'table' => $get_quoteResponse['table'],
                    'status'    => false,
                    'msg'       => $e->getMessage() . ' ' . $e->getLine(),
                    'response'     => $quoteResponse,
                ];
            }
        } else {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }
    } else {
        return [
            'status'    => false,
            'msg'       => 'Insurer Not Reachable',
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }
}

