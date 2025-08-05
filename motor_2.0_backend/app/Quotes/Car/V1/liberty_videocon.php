<?php

namespace App\Quotes\Car\V1;

include_once app_path().'/Helpers/CarWebServiceHelper.php';
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Proposal\Services\Car\V1\LibertyVideoconSubmitProposal as  Liberty;

class liberty_videocon {
    /*

    * PRODUCTS INFORMATION 
    1. Basic (without any addon)
    2. DC_PA_LCA ("zeroDepreciation", "consumables", "roadSideAssistance", "keyReplace")
    3. DCE_PA_LCA ("zeroDepreciation", "consumables", "engineProtector", "roadSideAssistance", "keyReplace")
    4. DCET_PA_LCA ("zeroDepreciation", "consumables", "roadSideAssistance", "engineProtector", "keyReplace")
    5. DCEG_PA_LCA ("zeroDepreciation", "consumables", "engineProtector", "roadSideAssistance", "returnToInvoice", "keyReplace")
    6. DCEV_PA_LCA ("zeroDepreciation", "consumables", "roadSideAssistance", "evSecure")
    7. DCEVT_PA_LCA ("zeroDepreciation", "consumables", "roadSideAssistance", "evSecure", "tyreSecure")
    8. DCEVTG_PA_LCA ("zeroDepreciation", "consumables", "roadSideAssistance", "returnToInvoice", "evSecure", "tyreSecure")
    9. BASIC_ADDON ("keyReplace")

    */
public static function getQuote($enquiryId, $requestData, $productData)
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
    if (empty($requestData->rto_code)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO not available'
        ];
    }

    $mmv = get_mmv_details($productData, $requestData->version_id, 'liberty_videocon');

    if ($mmv['status'] == 1) {
      $mmv = $mmv['data'];
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    // if (strtotime($requestData->vehicle_register_date) > strtotime('2018-08-31')) {
    //     return [
    //         'status' => false,
    //         'premium_amount' => 0,
    //         'message' => 'Premium not available for vehicles registered after 31-08-2018'
    //     ];
    // }
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    $m_seating_capacity = $mmv_data->seating_capacity;

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } elseif ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
    $is_indivisual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

    $is_breakin     = (
        (
            (strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')
        ) ? false
        : true);

    $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

    $noPreviousData = ($requestData->previous_policy_type == 'Not sure');

    $current_date = date('Y-m-d');

    $is_breakin = (
        ($requestData->business_type == 'breakin')
        || $noPreviousData
        || (!$is_liability && $requestData->previous_policy_type == 'Third-party')
        || (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)));

    
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new \DateTime($vehicleDate);
    $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $vehicle_age = $interval->y;//ceil($age / 12);
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    // if (($interval->y >= 20) && ($tp_check == 'true')){
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 20 years',
    //     ];
    // }

    // $zero_dep_age_limit = ($interval->y > 4 || ($interval->y == 4 && $interval->m > 11) || ($interval->y == 4 && $interval->m == 11 && $interval->d > 28));

//    if(($interval->y > 5) && in_array($productData->product_identifier,['DG_PA_LCA','DCG_PA_LCA','DCEG_PA_LCA','DCET_PA_LCA','DCETG_PA_LCA']))
//    {
//         return [
//             'premium_amount' => 0,
//             'status' => false,
//             'message' => 'Quotes are not allowed for Vehicle age greater than 5 years',
//             'request' => [
//                 'product_identifier' => $productData->product_identifier,
//             ]
//         ];
//    }
//    if(($interval->y > 7) && in_array($productData->product_identifier,['D_PA_LCA','DC_PA_LCA','DCE_PA_LCA']))
//    {
//         return [
//             'premium_amount' => 0,
//             'status' => false,
//             'message' => 'Quotes are not allowed for Vehicle age greater than 7 years',
//             'request' => [
//                 'product_identifier' => $productData->product_identifier,
//             ]
//         ];
//    }

    if ($mmv_data->fuel_type == 'Battery' && in_array($productData->product_identifier, ['DCE_PA_LCA', 'DCET_PA_LCA', 'DCEG_PA_LCA'])) 
    {
        // For Fuel types (Electric)
        return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message'  => $productData->product_identifier . ' is not allowed for electrical vehicles.',
                    'request' => [
                        'product_identifier' => $productData->product_identifier,
                        'message' => $productData->product_identifier . ' is not allowed for electrical vehicles.',
                    ]
                ];
    } 
    else if ($mmv_data->fuel_type != 'Battery' && in_array($productData->product_identifier, ['DCEV_PA_LCA', 'DCEVT_PA_LCA', 'DCEVTG_PA_LCA']))
    {
        // For Fuel types (Petrol, Diesel, Hybrid, CNG & LPG)
        return [
            'status' => false,
            'premium_amount' => 0,
            'message'  => $productData->product_identifier . ' is not allowed for non-electrical vehicles.',
            'request' => [
                'product_identifier' => $productData->product_identifier,
                'message' => $productData->product_identifier . ' is not allowed for non-electrical vehicles.',
            ]
        ];
    }

    $motor_manf_date = '01-'.$requestData->manufacture_year;

    $vehicle_registration_no = explode('-', $requestData->vehicle_registration_no ?? RtoCodeWithOrWithoutZero($requestData->rto_code).'-'.substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 2).'-'.substr(str_shuffle('1234567890'), 1, 4));

    // echo "<pre>";print_r([$requestData]);echo "</pre>";die();
    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no && $requestData->vehicle_registration_no != "None" && strtoupper($requestData->vehicle_registration_no) != 'NEW') {
       
        $vehicle_registration_no = explode('-', $requestData->vehicle_registration_no);
        if ($vehicle_registration_no[0] == 'DL') {
            $registration_no = RtoCodeWithOrWithoutZero($vehicle_registration_no[0].$vehicle_registration_no[1],true); 
            $vehicle_registration_no = $registration_no.'-'.$vehicle_registration_no[2].'-'.$vehicle_registration_no[3];
            $vehicle_registration_no = explode('-', $vehicle_registration_no);
        }
    } else {
        $vehicle_registration_no = array_merge(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code,true)), ['MG', rand(1111, 9999)]);
    }
    if($vehicle_registration_no[0] == 'DL')
    {
        $vehicle_registration_no = explode('-',getRegisterNumberWithHyphen($vehicle_registration_no[0].$vehicle_registration_no[1].$vehicle_registration_no[2].$vehicle_registration_no[3]));
        $requestData->rto_code = $vehicle_registration_no[0].'-'.$vehicle_registration_no[1];
    }
    if ($is_new)
    {
        $BusinessType = 'New Business';
        $PreviousPolicyStartDate = '';
        $PreviousPolicyEndDate = '';

        $policy_start_date = date('Y-m-d');
        // $policy_end_date = $is_liability ? date('Y-m-d', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));

    }
    elseif (!$is_new)
    {
        $BusinessType = 'Roll Over';
        $PreviousPolicyStartDate = date('d/m/Y', strtotime('-1 Year +1 dayr', strtotime($requestData->previous_policy_expiry_date)));
        $PreviousPolicyEndDate = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));

        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));

        if ($is_breakin || $is_liability) {
            $policy_start_date = date('Y-m-d');
            if($premium_type=='third_party_breakin')
            {
                $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
            }

            if(!(strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)))
            {
                $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime($requestData->previous_policy_expiry_date)));
            }
        }

        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    }

    if ($requestData->business_type == "rollover" && $premium_type == 'third_party') {
        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    }

    $zero_depreciation_cover = 'No';
    $road_side_assistance_cover = 'No';
    $consumables_cover = 'No';
    $engine_protection_cover = 'No';
    $key_and_lock_protection_cover = 'No';
    $return_to_invoice_cover = 'No';
    $passengerAssistCover = 'No';

    switch ($productData->product_identifier) {
        case 'basic':
            $zero_depreciation_cover = 'No';
            $road_side_assistance_cover = 'No';
            $consumables_cover = 'No';
            $engine_protection_cover = 'No';
            $key_and_lock_protection_cover = 'No';
            $return_to_invoice_cover = 'No';
            $ev_secure = 'No';
            $tyre_secure = 'No';
            break;

        case 'DC_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $consumables_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;

        case 'DCE_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $consumables_cover = 'Yes';
            $engine_protection_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;
        
        case 'DCET_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $consumables_cover = 'Yes';
            $engine_protection_cover = 'Yes';
            $tyre_secure = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;
        
        case 'DCEG_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $consumables_cover = 'Yes';
            $engine_protection_cover = 'Yes';
            $tyre_secure = 'Yes';
            $return_to_invoice_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;
       
            case 'D_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;

        case 'BASIC_ADDON':
            $key_and_lock_protection_cover = 'Yes';
            break;

        case 'DG_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $return_to_invoice_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;

        case 'DCG_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $return_to_invoice_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $consumables_cover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;

        case 'DCETG_PA_LCA':
            $zero_depreciation_cover = 'Yes';
            $consumables_cover = 'Yes';
            $engine_protection_cover = 'Yes';
            $tyre_secure = 'Yes';
            $return_to_invoice_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            $key_and_lock_protection_cover = 'Yes';
            break;

        case 'BASIC_WITH_ADDONS':
            $key_and_lock_protection_cover = 'Yes';
            $road_side_assistance_cover = 'Yes';
            $passengerAssistCover = 'Yes';
            break;
        // EV Products Start
        // case 'DCEV_PA_LCA':
        //     $zero_depreciation_cover = 'Yes';
        //     $consumables_cover = 'Yes';
        //     $ev_secure = 'Yes';
        //     $road_side_assistance_cover = 'Yes';
        //     break;

        // case 'DCEVT_PA_LCA':
        //     $zero_depreciation_cover = 'Yes';
        //     $consumables_cover = 'Yes';
        //     $engine_protection_cover = 'Yes';
        //     $ev_secure = 'Yes';
        //     $tyre_secure = 'Yes';
        //     $road_side_assistance_cover = 'Yes';
        //     break;

        // case 'DCEVTG_PA_LCA':
        //     $zero_depreciation_cover = 'Yes';
        //     $consumables_cover = 'Yes';
        //     $engine_protection_cover = 'Yes';
        //     $ev_secure = 'Yes';
        //     $tyre_secure = 'Yes';
        //     $return_to_invoice_cover = 'Yes';
        //     $road_side_assistance_cover = 'Yes';
        //     break;
        // EV Products End
        
    }
    if($vehicle_age > 10)
    {
        $key_and_lock_protection_cover = 'No';
    }

    // if($interval->y > 2 || ($interval->y == 2 && $interval->m > 11) || ($interval->y == 2 && $interval->m == 11 && $interval->d > 28))
    // {
    //     $return_to_invoice_cover = 'No';
    // }

    $return_to_invoice_cover = $return_to_invoice_cover == 'Yes' ? 'Yes' : 'No';

    // if($is_new && config('constants.IcConstants.liberty_videocon.NO_ENGINE_SAFE') == 'true' && $productData->product_identifier == 'engprotect'){
    //     return [
    //         'status' => false,
    //         'premium_amount' => 0,
    //         'message'  => 'Engine Safe plan not configured',
    //         'request' => [
    //             'product_identifier' => $productData->product_identifier,
    //             'message' => 'Engine Safe plan not configured',
    //         ]
    //     ];
    // }

    // $road_side_assistance_cover = (($interval->y > 9) || ($interval->y == 9 && $interval->m > 10)) ? 'No' : 'Yes';
    // $road_side_assistance_cover = (($interval->y > 9) || ($interval->y == 9 && $interval->m > 11) || ($interval->y == 9 && $interval->m == 11 && $interval->d > 28)) ? 'No' : 'Yes'; r

    // $road_side_assistance_cover = $vehicle_age < 10 ? 'Yes' : 'No';

    if($is_liability)
    {
        $zero_depreciation_cover = 'No';
        $road_side_assistance_cover = 'No';
        $consumables_cover = 'No';
        $engine_protection_cover = 'No';
        $key_and_lock_protection_cover = 'No';
        $return_to_invoice_cover = 'No';
        $passengerAssistCover = 'No';
    }

    if(config('IC.LIBERTY_VIDEOCON.V1.CAR.RTI_DISABLED') == 'Y') {
        $return_to_invoice_cover = 'No'; // RTI is disable by IC
    }

    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
    ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
    ->first();

    $electrical_accessories = 'No';
    $electrical_accessories_details = '';
    $non_electrical_accessories = 'No';
    $non_electrical_accessories_details = '';
    $external_fuel_kit = 'No';
    $fuel_type = $mmv_data->fuel_type;
    $external_fuel_kit_amount = '';

    if (!empty($additional['accessories'])) {
        foreach ($additional['accessories'] as $key => $data) {
            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                if ($data['sumInsured'] < 15000 || $data['sumInsured'] > 50000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle non-electric accessories value should be between 15000 to 50000',
                        'request' => [
                            'cng_lpg_amt' => $data['sumInsured'],
                            'message' => 'Vehicle non-electric accessories value should be between 15000 to 50000',
                        ]
                    ];
                } else {
                    $external_fuel_kit = 'Yes';
                    $fuel_type = 'CNG';
                    $external_fuel_kit_amount = $data['sumInsured'];
                }
            }

            if ($data['name'] == 'Non-Electrical Accessories') {
                if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                        'request' => [
                            'non_elec_acc_value' => $data['sumInsured'],
                            'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                        ]
                    ];
                } else {
                    $non_electrical_accessories = 'Yes';
                    $non_electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }
            }

            if ($data['name'] == 'Electrical Accessories') {
                if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle electric accessories value should be between 10000 to 25000',
                        'request' => [
                            'electrical_amount' => $data['sumInsured'],
                            'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                        ]
                    ];
                } else {
                    $electrical_accessories = 'Yes';
                    $electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }
            }
        }
    }

    $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = 'No';
    $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
    $no_of_pa_unnamed_passenger = 1;

    if (!empty($additional['additional_covers'])) {
        foreach ($additional['additional_covers'] as $key => $data) {
            if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                $cover_pa_paid_driver = 'Yes';
                $cover_pa_paid_driver_amt = $data['sumInsured'];
            }

            if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                $cover_pa_unnamed_passenger = 'Yes';
                $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                $no_of_pa_unnamed_passenger = $mmv_data->seating_capacity;
            }

            if ($data['name'] == 'LL paid driver') {
                $cover_ll_paid_driver = 'Yes';
            }
        }
    }

    $is_anti_theft = 'No';
    $is_anti_theft_device_certified_by_arai = 'false';
    $is_voluntary_access = 'No';
    $voluntary_excess_amt = 0;

    if (!empty($additional['discounts'])) {
        foreach ($additional['discounts'] as $key => $data) {
            if ($data['name'] == 'anti-theft device') {
                $is_anti_theft = 'Yes';
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                $is_voluntary_access = 'Yes';
                $voluntary_excess_amt = $data['sumInsured'];
            }
        }
    }

    if (isset($requestData->is_claim) && ($requestData->is_claim == 'Y')){
        $no_of_claims = '1';
        $claim_amount = '';
    }
    else{
        $no_of_claims = '';
        $claim_amount = '';
    }
    if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){
        $is_pos_disable = 'Y'; 
    }else{
        $is_pos_disable = config('constants.motorConstant.IS_LIBERTY_POS_DISABLED');
    }
    $is_pos     = (($is_pos_disable == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED'));

    $pos_name   = '';
    $pos_type   = '';
    $pos_code   = '';
    $pos_aadhar = '';
    $pos_pan    = '';
    $pos_mobile = '';
    $imd_number = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER');
    $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME');

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {

        if(config('IS_LIBERTY_CAR_NON_POS') == 'Y') {
            $pos_name = '';$pos_type = '';$pos_code = '';$pos_aadhar = '';$pos_pan = '';$pos_mobile = '';
        }else{
            $pos_name   = $pos_data->agent_name;
            $pos_type   = 'POSP';
            $pos_code   = $pos_data->pan_no;
            $pos_aadhar = $pos_data->aadhar_no;
            $pos_pan    = $pos_data->pan_no;
            $pos_mobile = $pos_data->agent_mobile;
            $imd_number = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER_POS');
            $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME_POS');
        }
    }

    if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_LIBERTY_VIDEOCON') == 'Y')
    {
        $pos_name   = 'Agent';
        $pos_type   = 'POSP';
        $pos_code   = 'ABGTY8890Z';
        $pos_aadhar = '569278616999';
        $pos_pan    = 'ABGTY8890Z';
        $pos_mobile = '8850386204';
    }

    // if(!$is_new && !$is_liability && ( $requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
    // {
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

    $productcode = ($is_od ? config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_OD') : ($is_liability ? config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_TP') :config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_PACKAGE')));#tp-3155

    //ownership changed as per git id :31203
    if(isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes not allowed for ownership changed vehicle',
            'request' => [
                'message' => 'Quotes not allowed for ownership changed vehicle',
                'requestData' => $requestData
            ]
        ];
    }

    $KeyLossCoverSI = Liberty::getKeyLossCoverSI($mmv_data);    

    if (isset($vehicle_registration_no[1]) && !is_numeric($vehicle_registration_no[1])) {
        preg_match_all("/[a-zA-Z]/", $vehicle_registration_no[1], $matches);
        $vehicle_registration_no[1] = preg_replace("/[a-zA-Z]/", "", $vehicle_registration_no[1]);
        if (isset($matches[0][0])) {
            $extras = implode('', $matches[0]);
            $vehicle_registration_no[2] = $extras.($vehicle_registration_no[2] ?? '');
        }
    }

    $rtoCode = explode('-', $requestData->rto_code);
    if (isset($rtoCode[1]) && !is_numeric($rtoCode[1])) {
        $rtoCode[1] = preg_replace("/[a-zA-Z]/", "", $rtoCode[1]);
    }
    $rtoCode = implode('-', $rtoCode);

    $isLiabilityToEmployeeCovered = "No";
    if ($requestData->vehicle_owner_type == 'C' && !($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin')) {
        $isLiabilityToEmployeeCovered = "Yes";
    }
    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_year_data = isset($value['tenure']) ? $value['tenure'] : '1';
                    
                }
            }
        }
        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage")
        {
            if ($requestData->business_type == 'newbusiness')
            {
                $PA_Owner_Driver_Tenure = isset($cpa_year_data) ? $cpa_year_data : '3';
            }
            else{
                $PA_Owner_Driver_Tenure = isset($cpa_year_data) ? $cpa_year_data : '1';
            }
        }
        else{
            $PA_Owner_Driver_Tenure = '';
        }
    $premium_request = [
        'QuickQuoteNumber' => config('IC.LIBERTY_VIDEOCON.V1.CAR.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
        'IMDNumber' => $imd_number,
        'AgentCode' => '',
        'TPSourceName' => $tp_source_name,
        'ProductCode' => $productcode,
        'IsFullQuote' => 'false',// ($is_breakin ? 'false' : 'true'),
        'BusinessType' => ($is_new ? 'New Business' : 'Roll Over'),
        'MakeCode' => $mmv_data->manufacturer_code,
        'ModelCode' => $mmv_data->vehicle_model_code,
        'ManfMonth' => date('m', strtotime('01-'.$requestData->manufacture_year)),
        'ManfYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
        'RtoCode' => RtoCodeWithOrWithoutZero($rtoCode, true), //$requestData->rto_code,
        'RegNo1' => $vehicle_registration_no[0],
        'RegNo2' => $vehicle_registration_no[1],
        'RegNo3' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[2] : '',
        'RegNo4' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[3] : $vehicle_registration_no[2],
        'DeliveryDate' => date('d/m/Y', strtotime($vehicleDate)),
        'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
        'VehicleIDV' => '0',
        'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
        'PolicyEndDate' => date('d/m/Y', strtotime($policy_end_date)),
        'PolicyTenure' => $is_new && $is_liability ? '1':'1',
        'GeographicalExtn' => 'No',
        'GeographicalExtnList' => '',
        'DrivingTuition' => '',
        'VintageCar' => '',
        'LegalLiabilityToPaidDriver' => $cover_ll_paid_driver,
        'NoOfPassengerForLLToPaidDriver' => '1',
        'LegalliabilityToEmployee' => $isLiabilityToEmployeeCovered,
        'NoOfPassengerForLLToEmployee' => $requestData->vehicle_owner_type == 'C' ? $m_seating_capacity : '',
        'PAUnnnamed' => $cover_pa_unnamed_passenger,
        'NoOfPerunnamed' => $no_of_pa_unnamed_passenger,
        'UnnamedPASI' => $cover_pa_unnamed_passenger_amt,
        'PAOwnerDriver' => $requestData->vehicle_owner_type == 'I' ? 'Yes' : 'No',
        'PAOwnerDriverTenure' => $PA_Owner_Driver_Tenure,
        'LimtedtoOwnPremise' => '',
        'ElectricalAccessories' => $electrical_accessories,
        'lstAccessories' => $electrical_accessories_details,
        'NonElectricalAccessories' => $non_electrical_accessories,
        'lstNonElecAccessories' => $non_electrical_accessories_details,
        'ExternalFuelKit' => $external_fuel_kit,
        'FuelType' => $fuel_type,
        'FuelSI' => $external_fuel_kit_amount,
        'PANamed' => 'No',
        'NoOfPernamed' => '0',
        'NamedPASI' => '0',
        'PAToPaidDriver' => $cover_pa_paid_driver,
        'NoOfPaidDriverPassenger' => '1',
        'PAToPaidDriverSI' => $cover_pa_paid_driver_amt,
        'AAIMembship' => 'No',
        'AAIMembshipNumber' => '',
        'AAIAssociationCode' => '',
        'AAIAssociationName' => '',
        'AAIMembshipExpiryDate' => '',
        'AntiTheftDevice' => $is_anti_theft,
        'IsAntiTheftDeviceCertifiedByARAI' => $is_anti_theft_device_certified_by_arai,
        'TPPDDiscount' => 'No',
        'ForeignEmbassy' => '',
        'VoluntaryExcess' => $is_voluntary_access,
        'VoluntaryExcessAmt' => $voluntary_excess_amt,
        'NoNomineeDetails' => $requestData->vehicle_owner_type == 'I' ? 'false' : 'true',
        'NomineeFirstName' => 'john',
        'NomineelastName' => 'doe',
        'NomineeRelationship' => 'brother',
        'OtherRelation' => '',
        'IsMinor' => 'false',
        'RepFirstName' => '',
        'RepLastName' => '',
        'RepRelationWithMinor' => '',
        'RepOtherRelation' => '',
        'NoPreviousPolicyHistory'   => ($is_new ? 'true' : 'false'),
        'IsNilDepOptedInPrevPolicy' => ($is_new ? 'true' : 'false'),
        'PreviousPolicyInsurerName' => ($is_new ? 'true' : 'IFFCO TOKIO'),
        'PreviousPolicyType'        => ($is_new ? 'true' : (($requestData->previous_policy_type == 'Third-party') ? 'LIABILITYPOLICY' :'PackagePolicy')),#LIABILITYPOLICY
        'PreviousPolicyStartDate'   => ($is_new ? 'true' : date('d/m/Y', strtotime('-1 Year +1 dayr', strtotime($requestData->previous_policy_expiry_date)))),
        'PreviousPolicyEndDate'     => ($is_new ? 'true' : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date))),
        'PreviousPolicyNumber'      => ($is_new ? 'true' : '123456789'),
        'PreviousYearNCBPercentage' => ($is_new ? 'true' : $requestData->previous_ncb),
        'ClaimAmount' => $claim_amount,
        'NoOfClaims' => $no_of_claims,
        'PreviousPolicyTenure'      => '1',
        'IsInspectionDone' => 'false',
        'InspectionDoneByWhom' => '',
        'ReportDate' => '',
        'InspectionDate' => '',
        'ConsumableCover' => $consumables_cover,
        'DepreciationCover' => $zero_depreciation_cover,
        'RoadSideAsstCover' => $road_side_assistance_cover,
        'GAPCover' => $return_to_invoice_cover,
        'GAPCoverSI' => '0',
        'EngineSafeCover' => $engine_protection_cover,
        'KeyLossCover' => $key_and_lock_protection_cover,  
        'KeyLossCoverSI' => ($key_and_lock_protection_cover == 'Yes' ? $KeyLossCoverSI : '0'),
        'PassengerAsstCover' => $passengerAssistCover,
        'EngineNo' => '',
        'ChassisNo' => '',
        'BuyerState' => 'MAHARASHTRA',
        'POSPName'          => $pos_name,
        'POSPType'          => $pos_type,
        'POSPCode'          => $pos_code,
        'POSPAadhar'        => $pos_aadhar,
        'POSPPAN'           => $pos_pan,
        'POSPMobileNumber'  => $pos_mobile,
    ];

    if($noPreviousData)
    {
        $premium_request['NoPreviousPolicyHistory']   = 'true';
        $premium_request['IsNilDepOptedInPrevPolicy'] = '';
        $premium_request['PreviousPolicyInsurerName'] = '';
        $premium_request['PreviousPolicyType']        = '';
        $premium_request['PreviousPolicyStartDate']   = '';
        $premium_request['PreviousPolicyEndDate']     = '';
        $premium_request['PreviousYearNCBPercentage'] = '';
    }

    if (!$is_new && !$is_liability) {#for tp not required
        $premium_request['lstPreviousAddonDetails'] = [
            [
                'IsConsumableOptedInPrevPolicy' => $consumables_cover == 'Yes' ? 'true' : 'false',
                'IsEngineSafeOptedInPrevPolicy' => $engine_protection_cover == 'Yes' ? 'true' : 'false',
                'IsGAPCoverOptedInPrevPolicy' => $return_to_invoice_cover == 'Yes' ? 'true' : 'false',
                'IsKeyLossOptedInPrevPolicy' => $key_and_lock_protection_cover == 'Yes' ? 'true' : 'false',
                'IsNilDepreicationOptedInPrevPolicy' => $zero_depreciation_cover == 'Yes' ? 'true' : 'false',
                'IsPassengerAsstOptedInPrevPolicy' => 'false',
                'IsRoadSideAsstOptedInPrevPolicy' => $road_side_assistance_cover == 'Yes' ? 'true' : 'false'
            ]
        ];
    }

    if ($is_od) {
        $premium_request['PreviousPolicyODStartDate'] = $PreviousPolicyStartDate;
        $premium_request['PreviousPolicyODEndDate'] = $PreviousPolicyEndDate;
        $premium_request['PreviousPolicyTPStartDate'] = date('d/m/Y', strtotime('-1 Year +1 day', strtotime($requestData->previous_policy_expiry_date)));
        $premium_request['PreviousPolicyTPEndDate'] = date('d/m/Y', strtotime('2 Year', strtotime($requestData->previous_policy_expiry_date)));
        $premium_request['LegalLiabilityToPaidDriver'] = 'No';
        $premium_request['PAOwnerDriver'] = 'No';
        $premium_request['PAToPaidDriver'] = 'No';

        if($requestData->previous_policy_type == 'Comprehensive')
        {
            $premium_request['PreviousPolicyType'] = 'BUNDLEDPOLICY';
            $premium_request['PreviousPolicyInsurerNameOD'] = 'BAJAJ ALLIANZ';
            $premium_request['PreviousPolicyNumberOD'] = '57657687456';

        }

        if($requestData->previous_policy_type == 'Own-damage')
        {
            $premium_request['PreviousPolicyType'] = 'STANDALONEPOLICY';

            $premium_request['PreviousPolicyInsurerNameTP'] = 'HDFC ERGO';
            $premium_request['PreviousPolicyInsurerNameOD'] = 'BAJAJ ALLIANZ';
            $premium_request['PreviousPolicyNumberTP'] = '25654556';
            $premium_request['PreviousPolicyNumberOD'] = '57657687456';
            $premium_request['PreviousTPPolicyTenure'] = '3';
            $premium_request['isActiveTPPolicyAvailable'] = 'true';
        }
        if($requestData->previous_policy_type == 'Third-party' && $is_od)
        {
            $premium_request['isActiveTPPolicyAvailable'] = 'true';
            $premium_request['IsInspectionDone'] = 'true';
            $premium_request['InspectionDoneByWhom']   = 'test';
            $premium_request['ReportDate']             = date('d/m/Y');
            $premium_request['InspectionDate']         = date('d/m/Y');
        }
    }
    if ($premium_type == 'third_party_breakin') {
        $premium_request['IsInspectionDone']       = 'true';
        $premium_request['InspectionDoneByWhom']   = 'Self';
        $premium_request['ReportDate']             = date('d/m/Y');
        $premium_request['InspectionDate']         = date('d/m/Y');
    }
    $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;

            if ($is_renewbuy) {

                $premium_request['POSPName'] = '';
                $premium_request['POSPType'] = '';
                $premium_request['POSPCode'] = '';
                $premium_request['POSPAadhar'] = '';
                $premium_request['POSPPAN'] = '';
                $premium_request['POSPMobileNumber'] = '';
            }
    $data = $premium_request;
    unset($data['QuickQuoteNumber'], $data['RegNo4']);
    $checksum_data = checksum_encrypt($data);
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'liberty_videocon', $checksum_data, 'CAR');
    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
        $data = $is_data_exits_for_checksum;
    }else{
        $data = getWsData(config('IC.LIBERTY_VIDEOCON.V1.CAR.END_POINT_URL_PREMIUM_CALCULATION'), $premium_request, 'liberty_videocon', [
            'enquiryId' => $enquiryId,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'liberty_videocon',
            'section' => $productData->product_sub_type_code,
            'method' =>'Premium Calculation',
            'checksum' => $checksum_data,
            'transaction_type' => 'quote',
            'headers' => [
                'Content-Type' => 'Application/json'
                ]
            ]);
        }
            
    if ($data['response']) {
        if (str_contains($data['response'], '503 Service Temporarily Unavailable'))
        {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => '503 Service Temporarily Unavailable'
            ];
        }
        update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], 'success', 'success');
        $premium_response = json_decode($data['response'], TRUE);
        $skip_second_call = false;
        // echo "<pre>";print_r([$premium_response, $premium_request, json_encode($premium_request)]);echo "</pre>";die();

        if ($premium_response !== null && trim($premium_response['ErrorText']) == "") {
            $vehicle_idv = ($premium_response['CurrentIDV']);
            $min_idv = ($premium_response['MinIDV']);
            $max_idv = ($premium_response['MaxIDV']);
            $default_idv = ($premium_response['CurrentIDV']);

            if ($requestData->is_idv_changed == 'Y') {
                if ($requestData->edit_idv >= $max_idv) {
                    $premium_request['VehicleIDV'] = $max_idv;
                    $vehicle_idv_check = $max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $premium_request['VehicleIDV'] = $min_idv;
                    $vehicle_idv_check = $min_idv;
                } else {
                    $premium_request['VehicleIDV'] = $requestData->edit_idv;
                    $vehicle_idv_check = $requestData->edit_idv;
                }
            } else {
                //$premium_request['VehicleIDV'] = $min_idv;
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $premium_request['VehicleIDV'] = $vehicle_idv;
                        $vehicle_idv_check = $vehicle_idv;
                        $skip_second_call = false;
                        break;
                    case 'min_idv':
                        $premium_request['VehicleIDV'] = $min_idv;
                        $vehicle_idv_check = $min_idv;
                        break;
                    case 'max_idv':
                        $premium_request['VehicleIDV'] = $max_idv;
                        $vehicle_idv_check = $max_idv;
                        break;
                    default:
                        $premium_request['VehicleIDV'] = $min_idv;
                        $vehicle_idv_check = $min_idv;
                        break;
                }
            }

            if (config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $vehicle_idv_check >= 5000000)
            {
                $premium_request['POSPName']= '';
                $premium_request['POSPType' ]= '';
                $premium_request['POSPCode' ]= '';
                $premium_request['POSPAadhar']= '';
                $premium_request['POSPPAN']= '' ;
                $premium_request['POSPMobileNumber']= '' ;
            } elseif(!empty($pos_data))
            {
                $premium_request['POSPName']  = $pos_data->agent_name;
                $premium_request['POSPType' ]   = 'POSP';
                $premium_request['POSPCode' ]   = $pos_data->pan_no;
                $premium_request['POSPAadhar'] = $pos_data->aadhar_no;
                $premium_request['POSPPAN'] = $pos_data->pan_no;
                $premium_request['POSPMobileNumber'] = $pos_data->agent_mobile;
            }
            $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;

            if ($is_renewbuy) {

                $premium_request['POSPName'] = '';
                $premium_request['POSPType'] = '';
                $premium_request['POSPCode'] = '';
                $premium_request['POSPAadhar'] = '';
                $premium_request['POSPPAN'] = '';
                $premium_request['POSPMobileNumber'] = '';
            }
            if(!$skip_second_call){
            $data = $premium_request;
            unset($data['QuickQuoteNumber'], $data['RegNo4']);
            $checksum_data = checksum_encrypt($data);
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'liberty_videocon', $checksum_data, 'CAR');
            if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                $data = $is_data_exits_for_checksum;
            }else{
                $data = getWsData(config('IC.LIBERTY_VIDEOCON.V1.CAR.END_POINT_URL_PREMIUM_CALCULATION'), $premium_request, 'liberty_videocon', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'liberty_videocon',
                    'section' => $productData->product_sub_type_code,
                    'checksum' => $checksum_data,
                    'method' =>'Premium Recalculation',
                    'transaction_type' => 'quote',
                    'headers' => [
                        'Content-Type' => 'Application/json'
                        ]
                    ]);
            }
        }
            if (!empty($data['response'])) {
                $premium_response = json_decode($data['response'], TRUE);

                if (trim($premium_response['ErrorText']) == "") {
                    $vehicle_idv = ($premium_response['CurrentIDV']);
                    $default_idv = ($premium_response['CurrentIDV']);
                } else {
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => preg_replace('/\d+.\z/', '', $premium_response['ErrorText'])
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

            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $llpaiddriver_premium = ($premium_response['LegalliabilityToPaidDriverValue']);
            $ll_to_employee_premium = ($premium_response['LegalliabilityToEmployeeValue']) ?? 0;
            $cover_pa_owner_driver_premium = ($premium_response['PAToOwnerDrivervalue']);
            $cover_pa_paid_driver_premium = ($premium_response['PatoPaidDrivervalue']);
            $cover_pa_unnamed_passenger_premium = ($premium_response['PAToUnnmaedPassengerValue']);
            $voluntary_excess = ($premium_response['VoluntaryExcessValue']);
            $anti_theft = ($premium_response['AntiTheftDiscountValue']);
            $ic_vehicle_discount = ($premium_response['Loading']);
            $ncb_discount = ($premium_response['DisplayNCBDiscountvalue']);
            $od = ($premium_response['BasicODPremium']);
            $tppd = ($premium_response['BasicTPPremium']);
            $cng_lpg = ($premium_response['FuelKitValueODpremium']);
            $cng_lpg_tp = ($premium_response['FuelKitValueTPpremium']);
            $electrical_accessories_amt = ($premium_response['ElectricalAccessoriesValue']);
            $non_electrical_accessories_amt = ($premium_response['NonElectricalAccessoriesValue']);

            $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $ll_to_employee_premium;
            $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount;
            $final_net_premium = ($final_od_premium + $final_tp_premium - $final_total_discount);
            $final_gst_amount = ($final_net_premium * 0.18);
            $final_payable_amount  = ($final_net_premium + $final_gst_amount);
            
            $applicable_addons = [];
            $addons_data = [];
            $addons_data['in_built']=$addons_data['additional'] = [];

            // NEW PRODUCT START 
            switch($productData->product_identifier){
                case 'DC_PA_LCA':
                    $addons_data = [
                        'in_built'   => [
                            'zero_depreciation' => ($premium_response['NilDepValue']),
                            'consumables' => ($premium_response['ConsumableCoverValue']),
                            'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                            'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])
                        ],
                        'additional' => [
                            'ncb_protection' => 0,
                            'tyre_secure' => 0,
                            'return_to_invoice' => 0,
                            'key_replace' => ($premium_response['KeyLossCoverValue']),
                            'engine_protector' => 0,
                            'lopb' => 0,
                        ]
                    ];
                    array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "keyReplace");
                    break;

                case 'DCE_PA_LCA':
                    $addons_data = [
                        'in_built'   => [
                            'zero_depreciation' => ($premium_response['NilDepValue']),
                            'consumables' => ($premium_response['ConsumableCoverValue']),
                            'engine_protector' => ($premium_response['EngineCoverValue']),
                            'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                            'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])
                        ],
                        'additional' => [
                            'ncb_protection' => 0,
                            'tyre_secure' => 0,
                            'key_replace' => ($premium_response['KeyLossCoverValue']),
                            'return_to_invoice' => 0,
                            'lopb' => 0,
                        ]
                    ];
                    array_push($applicable_addons, "zeroDepreciation", "consumables", "engineProtector", "roadSideAssistance", "keyReplace");
                    break;
                
                // Not required as IC does not  give premium of tyre secure in response
                case 'DCET_PA_LCA':
                    $addons_data = [
                        'in_built'   => [
                            'zero_depreciation' => ($premium_response['NilDepValue']),
                            'consumables' => ($premium_response['ConsumableCoverValue']),
                            'engine_protector' => ($premium_response['EngineCoverValue']),
                            // 'tyre_secure' => 0,
                            'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                            'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])

                        ],
                        'additional' => [
                            'engine_protector' => 0,
                            'ncb_protection' => 0,
                            'key_replace' => ($premium_response['KeyLossCoverValue']),
                            'return_to_invoice' => 0,
                            'lopb' => 0,
                        ]
                    ];
                    array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "engineProtector", "tyreSecure", "keyReplace");
                    break;
                
                case 'DCEG_PA_LCA':
                    $addons_data = [
                        'in_built'   => [
                            'zero_depreciation' => ($premium_response['NilDepValue']),
                            'consumables' => ($premium_response['ConsumableCoverValue']),
                            'engine_protector' => ($premium_response['EngineCoverValue']),
                            'return_to_invoice' => ($premium_response['GAPCoverValue']),
                            'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                            'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])

                        ],
                        'additional' => [
                            'ncb_protection' => 0,
                            'key_replace' => ($premium_response['KeyLossCoverValue']),
                            'lopb' => 0,
                        ]
                    ];
                    array_push($applicable_addons, "zeroDepreciation", "consumables", "engineProtector", "roadSideAssistance", "returnToInvoice", "keyReplace");
                    break;

                case 'D_PA_LCA':
                    $addons_data = [
                        'in_built'   => [
                            'zero_depreciation' => ($premium_response['NilDepValue']),
                            'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                            'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])

                        ],
                        'additional' => [
                            'key_replace' => ($premium_response['KeyLossCoverValue']),
                        ],
                    ];

                    array_push($applicable_addons, "zeroDepreciation", "roadSideAssistance", "keyReplace");
                    break;

                    case 'DG_PA_LCA':
                        $addons_data = [
                            'in_built'   => [
                                'zero_depreciation' => ($premium_response['NilDepValue']),
                                'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                                'return_to_invoice' => ($premium_response['GAPCoverValue']),
                                'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])

                            ],
                            'additional' => [
                                'key_replace' => ($premium_response['KeyLossCoverValue']),
                            ],
                        ];
                        array_push($applicable_addons, "zeroDepreciation", "roadSideAssistance", "returnToInvoice", "keyReplace");
                        break;

                    case 'DCG_PA_LCA':
                        $addons_data = [
                        'in_built'   => [
                                'zero_depreciation' => ($premium_response['NilDepValue']),
                                'consumables' => ($premium_response['ConsumableCoverValue']),
                                'return_to_invoice' => ($premium_response['GAPCoverValue']),
                                'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                                'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])

                            ],
                            'additional' => [
                                'key_replace' => ($premium_response['KeyLossCoverValue']),
                                'ncb_protection' => 0,
                                'lopb' => 0,
                            ]
                        ];
                        array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "returnToInvoice", "keyReplace");
                        break;  
                    
                    case 'DCEG_PA_LCA':
                        $addons_data = [
                                'in_built'   => [
                                    'zero_depreciation' => ($premium_response['NilDepValue']),
                                    'consumables' => ($premium_response['ConsumableCoverValue']),
                                    'engine_protector' => ($premium_response['EngineCoverValue']),
                                    'return_to_invoice' => ($premium_response['GAPCoverValue']),
                                    'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                                    'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])

                                ],
                                'additional' => [
                                    'key_replace' => ($premium_response['KeyLossCoverValue']),
                                    'ncb_protection' => 0,
                                    'lopb' => 0,
                                ]
                            ];
                        array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "returnToInvoice", "keyReplace");
                        break;
                    case 'DCETG_PA_LCA':
                        $addons_data = [
                                'in_built'   => [
                                    'zero_depreciation' => ($premium_response['NilDepValue']),
                                    'consumables' => ($premium_response['ConsumableCoverValue']),
                                    'engine_protector' => ($premium_response['EngineCoverValue']),
                                    // 'tyre_protector' => ($premium_response['TyreCoverValue']),
                                    'return_to_invoice' => ($premium_response['GAPCoverValue']),
                                    'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                                    'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])

                                ],
                                'additional' => [
                                    'key_replace' => ($premium_response['KeyLossCoverValue']),
                                    'ncb_protection' => 0,
                                    'lopb' => 0,
                                ]
                            ];
                        array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "returnToInvoice", "keyReplace");
                        break;

                case 'BASIC_WITH_ADDONS':
                    $addons_data = [
                        'in_built'   => [],
                        'additional' => [
                            'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                            'key_replace' => ($premium_response['KeyLossCoverValue']),
                            'emergencyMedicalExpenses' => ($premium_response['PassengerAssistCoverValue'])
                        ],
                    ];
                    array_push($applicable_addons, "roadSideAssistance", "keyReplace");
                    break;


                case 'BASIC_ADDON':
                    $addons_data = [
                        'in_built'   => [],
                        'additional' => [
                            'zero_depreciation' => 0,
                            'consumables' => 0,
                            'engine_protector' => 0,
                            'return_to_invoice' => 0,
                            'road_side_assistance' => 0,
                            'key_replace' => ($premium_response['KeyLossCoverValue']),
                            'tyre_secure' => 0,
                            'ncb_protection' => 0,
                            'lopb' => 0,
                        ]
                    ];
                    array_push($applicable_addons, "keyReplace");
                    break;
                
                // EV PRODUCTS START
                // case 'DCEV_PA_LCA':
                //     $addons_data = [
                //         'in_built'   => [
                //             'zero_depreciation' => ($premium_response['NilDepValue']),
                //             'consumables' => ($premium_response['ConsumableCoverValue']),
                //             'ev_secure'   => 0,
                //             'road_side_assistance' => ($premium_response['RoadAssistCoverValue'])
                //         ],
                //         'additional' => [
                //             'ncb_protection' => 0,
                //             'key_replace' => ($premium_response['KeyLossCoverValue']),
                //             'tyre_secure' => 0,
                //             'return_to_invoice' => 0,
                //             'lopb' => 0,
                //         ]
                //     ];
                //     array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "evSecure", "keyReplace");
                //     break;

                // case 'DCEVT_PA_LCA':
                //     $addons_data = [
                //         'in_built'   => [
                //             'zero_depreciation' => ($premium_response['NilDepValue']),
                //             'consumables' => ($premium_response['ConsumableCoverValue']),
                //             'ev_secure'   => 0,
                //             // 'tyre_secure' => 0,
                //             'road_side_assistance' => ($premium_response['RoadAssistCoverValue'])
                //         ],
                //         'additional' => [
                //             'ncb_protection' => 0,
                //             'key_replace' => ($premium_response['KeyLossCoverValue']),
                //             'return_to_invoice' => 0,
                //             'lopb' => 0,
                //         ]
                //     ];
                //     array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "evSecure", "tyreSecure", "keyReplace");
                //     break;
                
                // case 'DCEVTG_PA_LCA':
                //     $addons_data = [
                //         'in_built'   => [
                //             'zero_depreciation' => ($premium_response['NilDepValue']),
                //             'consumables' => ($premium_response['ConsumableCoverValue']),
                //             'ev_secure'   => 0,
                //             // 'tyre_secure' => 0,
                //             'road_side_assistance' => ($premium_response['RoadAssistCoverValue']),
                //             'return_to_invoice' => ($premium_response['GAPCoverValue']),
                //         ],
                //         'additional' => [
                //             'ncb_protection' => 0,
                //             'key_replace' => ($premium_response['KeyLossCoverValue']),
                //             'return_to_invoice' => 0,
                //             'lopb' => 0,
                //         ]
                //     ];
                //     array_push($applicable_addons, "zeroDepreciation", "consumables", "roadSideAssistance", "returnToInvoice", "evSecure", "tyreSecure", "keyReplace");
                //     break;
                // EV PRODUCTS END

                
            }

            // NEW PRODUCTS END
            $null_arr = [0,false,null,'','null','false','0',0.0];
            if(!empty(array_intersect($null_arr, $addons_data['in_built'])))
            {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $productData->product_identifier.' Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                ];
            }



            $addons_data['in_built_premium'] = !empty(array_sum($addons_data['in_built'])) ? array_sum($addons_data['in_built']) : 0;
            $addons_data['additional_premium'] = !empty(array_sum($addons_data['additional'])) ? array_sum($addons_data['additional']) : 0;
            $addons_data['other_premium'] = $addons_data['other']['passenger_assist_cover'] ?? 0;

            // $applicable_addons = [
            //     'zeroDepreciation', 'roadSideAssistance', 'engineProtector', 'consumables'
            // ];
            // if($zero_dep_age_limit)
            // {
            //     array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            //     array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
            //     array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
            // }
            /* if($vehicle_age > 2) {
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            } */
            foreach($addons_data['additional'] as $k=>$v){
                if(empty($v)){
                    unset($addons_data['additional'][$k]);
                }
            }
            $data_response = [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => !$is_liability ? $vehicle_idv : 0,
                    'min_idv' => !$is_liability ? ($min_idv) : 0,
                    'max_idv' => !$is_liability ? ($max_idv) : 0,
                    'default_idv' => $default_idv,
                    'vehicle_idv' => $vehicle_idv,
                    'qdata' => null,
                    'pp_enddate' => ($is_new ? '' : $requestData->previous_policy_expiry_date),
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => (($is_package) ? 'Comprehensive' : (($is_liability) ? 'Third Party' : 'Own Damage')),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'version_id' => $mmv_data->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => $vehicle_idv,
                    'fuel_type' => $mmv_data->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_name,
                    'mmv_detail' => [
                        'manf_name'             => $mmv_data->manufacturer,
                        'model_name'            => $mmv_data->vehicle_model,
                        'version_name'          => $mmv_data->variant,
                        'fuel_type'             => $mmv_data->fuel_type,
                        'seating_capacity'      => $mmv_data->seating_capacity,
                        'carrying_capacity'     => $mmv_data->carrying_capacity,
                        'cubic_capacity'        => $mmv_data->cubic_capacity,
                        'gross_vehicle_weight'  => $mmv_data->gross_weight ?? 1,
                        'vehicle_type'          => $mmv_data->vehicle_class_desc,
                    ],
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                        'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                        'sum_insured' => $productData->sum_insured,
                        'corp_client_id' => $productData->corp_client_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'insurance_company_id' => $productData->company_id,
                        'status' => $productData->status,
                        'corp_name' => "",
                        'company_name' => $productData->company_name,
                        'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => "",
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $motor_manf_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'ic_vehicle_discount' => $ic_vehicle_discount,
                    ],
                    'ic_vehicle_discount' => $ic_vehicle_discount,
                    'basic_premium' => $od,
                    'deduction_of_ncb' => $ncb_discount,
                    'tppd_premium_amount' => $tppd,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => $final_od_premium,
                    'total_liability_premium' => $final_tp_premium,
                    'net_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'quotation_no' => '',
                    'premium_amount'  => $final_payable_amount,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => ($final_total_discount),
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => NULL,
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
                    'add_ons_data' => $addons_data,
                    'applicable_addons' => !$is_liability ? $applicable_addons : [],
                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                    'tppd_discount' => 0, //tppd
                ]
            ];
            if(!empty($ll_to_employee_premium) && $requestData->vehicle_owner_type == 'C')
            {
                $data_response['Data']['other_covers']['LegalLiabilityToEmployee'] = $ll_to_employee_premium;
                $data_response['Data']['LegalLiabilityToEmployee'] = $ll_to_employee_premium;
            }
            if($is_anti_theft == 'Yes')
            {
                $data_response['Data']['antitheft_discount'] = $anti_theft;
            }
            if($is_voluntary_access == 'Yes')
            {
                $data_response['Data']['voluntary_excess'] = $voluntary_excess;
            }
            if(isset($cpa_year_data) && $requestData->business_type == 'newbusiness' && $cpa_year_data == '3')
            {
                // unset($data_response['Data']['compulsory_pa_own_driver']);
                $data_response['Data']['multi_Year_Cpa'] = $cover_pa_owner_driver_premium;
            }
            if($electrical_accessories == 'Yes')
            {
                $data_response['Data']['motor_electric_accessories_value'] = $electrical_accessories_amt;
            }
            if($non_electrical_accessories == 'Yes')
            {
                $data_response['Data']['motor_non_electric_accessories_value'] = $non_electrical_accessories_amt;
            }
            if($external_fuel_kit == 'Yes')
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = $cng_lpg;
                $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
            }

            if($cover_pa_paid_driver == 'Yes')
            {
                $data_response['Data']['motor_additional_paid_driver'] = $cover_pa_paid_driver_premium;
            }
            if($cover_pa_unnamed_passenger == 'Yes')
            {
                $data_response['Data']['cover_unnamed_passenger_value'] = (isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0);
            }
            if($cover_ll_paid_driver == 'Yes')
            {
                $data_response['Data']['default_paid_driver'] = $llpaiddriver_premium;
            }
            if($external_fuel_kit == 'Yes')
            {
                $data_response['Data']['cng_lpg_tp'] = $cng_lpg_tp;
                $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
            }
            if(!empty($cng_lpg_tp))
            {
                $data_response['Data']['cng_lpg_tp'] = $cng_lpg_tp;
            }
            if(!empty($cng_lpg))
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = $cng_lpg;
            }
            return camelCase($data_response);

        } else {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status' => false,
                'premium' => '0',
                'message' => isset($premium_response['ErrorText']) ? preg_replace('/\d+.\z/', '', $premium_response['ErrorText']) : 'Insurer not reachable'
            ];
        }
    } else {
        return [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'status' => false,
            'premium' => '0',
            'message' => 'Insurer not reachable'
        ];
    }
}
}
