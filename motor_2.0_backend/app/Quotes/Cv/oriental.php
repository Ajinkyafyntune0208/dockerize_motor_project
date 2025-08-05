<?php
use App\Models\MasterRto;
use App\Models\UserProposal;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\CvAgentMapping;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

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
    $parent_id = get_parent_code($productData->product_sub_type_id);

    $mmv = get_mmv_details($productData, $requestData->version_id, 'oriental');

    if(!$mmv['status'] && isset($mmv['message']))
    {
        return $mmv;
    }

    $mmv_data = (object) array_change_key_case((array) $mmv['data'], CASE_LOWER);

    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id',$productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    $rto_code = $requestData->rto_code;

    $rto_code = explode('-', $rto_code);

    if ((int) $rto_code[1] < 10) {
        $rto_code[1] = '0' . (int) $rto_code[1];
    }

    $rto_code = implode('-', $rto_code);

    $rto_data = MasterRto::where('rto_code', $rto_code)->where('status', 'Active')->first();

    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO code does not exist',
            'request' => [
                'rto_code' => $rto_code,
                'message' => 'RTO code does not exists',
            ]
        ];
    }

    $rto_location = DB::table('oriental_rto_master')
            ->where('rto_code', 'like', '%' . $rto_data->rto_number . '%')
            ->first();

    $cv_rto_location = DB::table('oriental_cv_rto_masters')
    ->where('rto_code', 'like', '%' . $rto_data->rto_number . '%')
    ->first();

    if (empty($cv_rto_location)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO details does not exist with insurance company',
            'request' => [
                'rto_code' => $rto_code,
                'message' => 'RTO details does not exist with insurance company',
            ]
        ];
    }

    if (empty($rto_location)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO details does not exist with insurance company',
            'request' => [
                'rto_code' => $rto_code,
                'message' => 'RTO details does not exist with insurance company',
            ]
        ];
    }

    if ($requestData->business_type == 'newbusiness') {
        $policy_start_date = date('d-M-Y');
    } else {
        $date_difference = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);

        if ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        } else {
            $policy_start_date = date('d-M-Y');
        }                
    }

    $policy_end_date = date('d-M-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    $DateOfPurchase = date('d-M-Y', strtotime($requestData->vehicle_register_date));
    $vehicle_register_date = explode('-',$requestData->vehicle_register_date);
    $vehicle_in_90_days = 'N';

    $previous_policy_start_date = $requestData->business_type== 'newbusiness' ? '' : date('d-M-Y', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
    $previous_policy_end_date = $requestData->business_type== 'newbusiness' ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date));

    $car_age = 0;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    $idv = 0;

    if ($premium_type != 'third_party') {
        if ($age > 132) {
            $idv = $mmv_data->upto_12_year;
        } elseif ($age > 120) {
            $idv = $mmv_data->upto_11_year;
        } elseif ($age > 108) {
            $idv = $mmv_data->upto_10_year;
        } elseif ($age > 96) {
            $idv = $mmv_data->upto_9_year;
        } elseif ($age > 84) {
            $idv = $mmv_data->upto_8_year;
        } elseif ($age > 72) {
            $idv = $mmv_data->upto_7_year;
        } elseif ($age > 60) {
            $idv = $mmv_data->upto_6_year;
        } elseif ($age > 48) {
            $idv = $mmv_data->upto_5_year;
        } elseif ($age > 36) {
            $idv = $mmv_data->upto_4_year;
        } elseif ($age > 24) {
            $idv = $mmv_data->upto_3_year;
        } elseif ($age > 12) {
            $idv = $mmv_data->upto_2_year;
        } elseif ($age > 6) {
            $idv = $mmv_data->upto_1_year;
        } else {
            $idv = (isset($mmv_data->upto_6_months) ? $mmv_data->upto_6_months : $mmv_data->ex_price);
        }
    }

    if ($interval->y >= 5 && $productData->zero_dep == '0' && in_array($productData->company_alias, explode(',', config('CV_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'premium' => '0',
            'status' => false,
            'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request' => [
                'car_age' => $car_age,
                'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
            ]
        ];
    }
    $is_bus = false;
    if(in_array($productData->product_sub_type_code,['PASSENGER-BUS','SCHOOL-BUS']))
    {
        $is_bus = true;
        $class_code = 'CLASS_4C2A';
    }
    else if ($productData->product_sub_type_code == 'TAXI') {
        if ($mmv_data->seating_capacity <= 6) {
            $class_code = 'CLASS_4C1A';
        } else {
            $class_code = 'CLASS_4C2';
        }
    } elseif ($productData->product_sub_type_code == 'AUTO-RICKSHAW') {
        if ($mmv_data->seating_capacity <= 6)
        {
            $class_code = 'CLASS_4C1B';
        }
        elseif ($mmv_data->seating_capacity > 6 && $mmv_data->seating_capacity < 17)
        {
            $class_code = 'CLASS_4C3';
        }
        else
        {
            $class_code = 'CLASS_4C2B';
        }
    } elseif ($productData->product_sub_type_code == 'ELECTRIC-RICKSHAW') {
        $class_code = 'CLASS_5E1A';
    } else {
        if ($requestData->gcv_carrier_type == 'PUBLIC') {
            // if ($mmv_data->wheels > 3) {
                $class_code = 'CLASS_4A1';
            // } elseif ($mmv_data->wheels == 3) {
                // $class_code = 'CLASS_4A3';
            // }
        } else {
            // if ($mmv_data->wheels > 3) {
                $class_code = 'CLASS_4A2';
            // } elseif ($mmv_data->wheels == 3) {
                // $class_code = 'CLASS_4A4';
            // }
        }
    }

    $fuelType = [
        'PETROL' => 'MFT1',
        'DIESEL' => 'MFT2',
        'CNG' => 'MFT3',
        'OCTANE' => 'MFT4',
        'LPG' => 'MFT5',
        'BATTERY POWERED - ELECTRICAL' => 'MFT6',
        'PETROL + CNG' => 'MFT7',
        'PETROL + LPG' => 'MFT8',
        'OTHERS' => 'MFT99',
        'ELECTRIC' => 'MFT6'
    ];

    $selected_addons = DB::table('selected_addons')
        ->where('user_product_journey_id', $enquiryId)
        ->first();
    $addons_v2 = json_decode($selected_addons->addons);
    $addons_v2 = empty($addons_v2) ? [] : $addons_v2;
    $addtowcharge = 20000;
        foreach ($addons_v2 as $key => $value) {    
            if ($value->name == "Additional Towing") {
                $addtowcharge = isset($value->sumInsured) ? $value->sumInsured : 20000;
            }
        }

    $is_electrical_accessories = NULL;
    $is_non_electrical_accessories = NULL;
    $is_external_cng_kit = false;
    $external_kit_value = 0;
    $electrical_accessories_value = 0;
    $non_electrical_accessories_value = 0;
    $pa_paid_driver_sum_insured = 0;
    $no_of_unnamed_passenger = 0;
    $unnamed_passenger_sum_insured = 0;
    $no_of_drivers = 0;
    $no_of_ll_paid_drivers = 0;
    $no_of_ll_paid_conductors = 0;
    $no_of_ll_paid_cleaners = 0;
    $is_anti_theft = 0;
    $voluntary_excess_value = NULL;
    $is_tppd_cover = 0;
    $is_geo_ext = false;
    $misp_code = null;
    $misp_name = null;
    $nfpp = false;
    $nfpp_si = 0;

    if ($selected_addons && !empty($selected_addons)) {
        $voluntary_excess_master = [
            0 => 'PCVE1',
            2500 => 'PCVE2',
            5000 => 'PCVE3',
            7500 => 'PCVE4',
            15000 => 'PCVE5'
        ];

        if ($selected_addons->accessories != NULL && $selected_addons->accessories != '') {
            $accessories = json_decode($selected_addons->accessories, TRUE);

            foreach ($accessories as $accessory) {
                if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $is_external_cng_kit = true;
                    $external_kit_value = $accessory['sumInsured'];
                } elseif ($accessory['name'] == 'Electrical Accessories') {
                    $is_electrical_accessories = 'Y';
                    $electrical_accessories_value = $accessory['sumInsured'];
                } elseif ($accessory['name'] == 'Non-Electrical Accessories') {
                    $is_non_electrical_accessories = 'Y';
                    $non_electrical_accessories_value = $accessory['sumInsured'];
                }
            }
        }

        if($requestData->fuel_type == 'CNG' || $requestData->fuel_type == 'LPG')
        {
            $mmv_data->fuel_type = $requestData->fuel_type;
            $mmv_data->veh_fuel_desc = $requestData->fuel_type;
        }

        $is_internal_cng = false;
        if(strtoupper($mmv_data->veh_fuel_desc) == 'CNG' || strtoupper($mmv_data->veh_fuel_desc) == 'LPG')
        {
            $is_internal_cng = true;
        }

        if ($selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = json_decode($selected_addons->additional_covers, TRUE);

            foreach ($additional_covers as $additional_cover) {
                if ($additional_cover['name'] == 'PA cover for additional paid driver') {
                    $pa_paid_driver_sum_insured = $additional_cover['sumInsured'];
                    $no_of_drivers = 1;
                } elseif ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                    $no_of_unnamed_passenger = 1;
                    $unnamed_passenger_sum_insured = $additional_cover['sumInsured'];
                } elseif ($additional_cover['name'] == 'LL paid driver') {
                    $no_of_drivers = 1;
                    $no_of_ll_paid_drivers = 1;
                } elseif ($additional_cover['name'] == 'LL paid driver/conductor/cleaner') {
                    if (isset($additional_cover['LLNumberCleaner']) && $additional_cover['LLNumberCleaner'] > 0) {
                        $no_of_ll_paid_cleaners = $additional_cover['LLNumberCleaner'];
                    }

                    if (isset($additional_cover['LLNumberDriver']) && $additional_cover['LLNumberDriver'] > 0) {
                        $no_of_ll_paid_drivers = $additional_cover['LLNumberDriver'];
                    }

                    if (isset($additional_cover['LLNumberConductor']) && $additional_cover['LLNumberConductor'] > 0) {
                        $no_of_ll_paid_conductors = $additional_cover['LLNumberConductor'];
                    }
                } elseif ($additional_cover['name'] == 'PA paid driver/conductor/cleaner' && isset($additional_cover['sumInsured'])) {
                    $pa_paid_driver_sum_insured = $additional_cover['sumInsured'];
                    $no_of_drivers = $mmv_data->seating_capacity;
                } elseif ($additional_cover['name'] == 'Geographical Extension') {
                    $is_geo_ext = true;
                    $countries = $additional_cover['countries'];
                } elseif($additional_cover['name'] == 'NFPP Cover'){
                    $nfpp = true;
                    $nfpp_si = $additional_cover['nfppValue'];
                }
            }
        }

        if ($selected_addons->discounts != NULL && $selected_addons->discounts != '') {
            $discounts = json_decode($selected_addons->discounts, TRUE);

            foreach ($discounts as $discount) {
                if ($discount['name'] == 'anti-theft device') {
                    $is_anti_theft = 1;
                } elseif ($discount['name'] == 'voluntary_insurer_discounts') {
                    $voluntary_excess_value = $voluntary_excess_master[$discount['sumInsured']];
                } elseif ($discount['name'] == 'TPPD Cover') {
                    $is_tppd_cover = 1;
                }
            }
        }
    }

    
    if ($requestData->vehicle_registration_no != '') {
        $Registration_Number = $requestData->vehicle_registration_no;
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        if(!empty($proposal->chassis_number) && !empty($proposal->engine_number))
        {
            $engine_number = $proposal->engine_number;
            $chassis_number = $proposal->chassis_number;
        }else{
            $Registration_Number = (($parent_id == 'PCV') ? config('PCV_QUOTE_REGISTRATION_NUMBER') : config('GCV_QUOTE_REGISTRATION_NUMBER'));
            $engine_number = (($parent_id == 'PCV') ? config('PCV_QUOTE_ENGINE_NUMBER') : config('GCV_QUOTE_ENGINE_NUMBER'));
            $chassis_number = (($parent_id == 'PCV') ? config('PCV_QUOTE_CHASSIS_NUMBER') : config('GCV_QUOTE_CHASSIS_NUMBER'));
            if($is_bus)
            {
                $Registration_Number = config('BUS_QUOTE_REGISTRATION_NUMBER');
                $engine_number = config('BUS_QUOTE_ENGINE_NUMBER');
                $chassis_number = config('BUS_QUOTE_CHASSIS_NUMBER');
            }
        }
        
    } else {
        $Registration_Number = (($parent_id == 'PCV') ? config('PCV_QUOTE_REGISTRATION_NUMBER') : config('GCV_QUOTE_REGISTRATION_NUMBER'));
        $engine_number = (($parent_id == 'PCV') ? config('PCV_QUOTE_ENGINE_NUMBER') : config('GCV_QUOTE_ENGINE_NUMBER'));
        $chassis_number = (($parent_id == 'PCV') ? config('PCV_QUOTE_CHASSIS_NUMBER') : config('GCV_QUOTE_CHASSIS_NUMBER'));
        if($is_bus)
        {
            $Registration_Number = config('BUS_QUOTE_REGISTRATION_NUMBER');
            $engine_number = config('BUS_QUOTE_ENGINE_NUMBER');
            $chassis_number = config('BUS_QUOTE_CHASSIS_NUMBER');
        }
    }

    // echo '<pre>'; print_r([$Registration_Number, $engine_number, $chassis_number]); echo '</pre';die();
    $is_pos_enabled = 'N';//config('constants.motorConstant.IS_POS_ENABLED');
    $is_pos = FALSE;
    $posp_code = '';

    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    $misp_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','misp')
        ->first();

    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        $pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
            ->pluck('oriental_code')
            ->first();
        if (empty($pos_code) || is_null($pos_code)) {
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'POS details Not Available'
            ];
        } else {
            $posp_code = $pos_code;
        }
        $is_pos = true;
    }

    if ($parent_id == 'PCV')
    {
        $product_code = $is_pos ? 'MOT-POS-005' : 'MOT-PRD-005';
    }
    elseif ($parent_id == 'MISCELLANEOUS-CLASS')
    {
        $product_code = $is_pos ? 'MOT-POS-006' : 'MOT-PRD-006';
    }
    else
    {
        $product_code = $is_pos ? 'MOT-POS-003' : 'MOT-PRD-003';
    }

    $discount_percentage = config('constants.IcConstants.oriental.DISCOUNT_PERCENTAGE', 70);
    $agentDiscountSelected = false;
    $userSelectedPercentage = $discount_percentage;
    if ($premium_type != 'third_party') {
        $agentDiscount = calculateAgentDiscount($enquiryId, 'oriental', strtolower($parent_id));
        if ($agentDiscount['status'] ?? false) {
            $discount_percentage = $agentDiscount['discount'];//$discount_percentage >= $agentDiscount['discount'] ? $agentDiscount['discount'] : $discount_percentage;
            $agentDiscountSelected = true;
            $userSelectedPercentage = $agentDiscount['discount'];
        } else {
            if (!empty($agentDiscount['message'] ?? '')) {
                return [
                    'status' => false,
                    'message' => $agentDiscount['message']
                ];
            }
        }
    }

    $min_idv = ceil($idv * 0.85);
    $max_idv = floor($idv * 1.15);
    if ($tp_only == 'false') 
    {
        if ($requestData->is_idv_changed == 'Y') 
        {
            if ($requestData->edit_idv >= $max_idv) {
                $idv = $max_idv;
            } elseif ($requestData->edit_idv <= $min_idv) {
                $idv = $min_idv;
            } else {
                $idv = $requestData->edit_idv;
            }
        } 
        else 
        {
            $idv = $min_idv;
        }
    }

        $misp_code = config('IC.ORIENTAL.CV.MISP_CODE');
        $misp_name = config('IC.ORIENTAL.CV.MISP_NAME');

    $vehIdv = '';
    if ($tp_only != 'true') {
        $vehIdv = $idv + $non_electrical_accessories_value + $electrical_accessories_value + $external_kit_value;
    }

    $premium_calculation_request = [
        'soap:Body' => [
            'GetQuoteMotor' => [
                'objGetQuoteMotorETT' => [
                    'LOGIN_ID' => config('constants.IcConstants.oriental.LOGIN_ID_ORIENTAL_CV'),
                    'DLR_INV_NO' => config('constants.motor.oriental.LOGIN_ID_ORIENTAL_CV'). '-' .customEncrypt($enquiryId),
                    'DLR_INV_DT' => date('d-M-Y'),
                    'PRODUCT_CODE' => $product_code,
                    'POLICY_TYPE' => $premium_type == 'third_party' ? 'MOT-PLT-002' : 'MOT-PLT-001',
                    'START_DATE' => $policy_start_date,
                    'END_DATE' => $policy_end_date,
                    'INSURED_NAME' => 'TEST',
                    'ADDR_01' => 'TEST',
                    'ADDR_02' => 'TEST',
                    'ADDR_03' => 'TEST',
                    'CITY' => 'Adampur',
                    'STATE' => 'HR',
                    'PINCODE' => '123456',
                    'COUNTRY' => 'IND',
                    'EMAIL_ID' => 'test@test.com',
                    'MOBILE_NO' => '8987656789',
                    'TEL_NO' => NULL,
                    'FAX_NO' => NULL,
                    'INSURED_KYC_VERIFIED' => 1,
                    'MANUF_VEHICLE_CODE' => $mmv_data->ven_manf_code,
                    'VEHICLE_MODEL_CODE' => $mmv_data->vehicle_model_code,
                    'TYPE_OF_BODY_CODE' => $parent_id == 'PCV' ? 11 : 'MMOPEN',
                    'VEHICLE_COLOR' => 'Black',
                    'VEHICLE_REG_NUMBER' => ($requestData->business_type == 'newbusiness' && $parent_id == 'PCV') ? 'NEW-3448' : $Registration_Number,
                    'VEHICLE_CODE' => $mmv_data->vehicle_code,
                    'VEHICLE_TYPE_CODE' => ($requestData->business_type == 'newbusiness' && $parent_id == 'PCV') ? 'W' : 'P',
                    'VEHICLE_CLASS_CODE' => $class_code,
                    'MANUF_CODE' => $mmv_data->ven_manf_code,
                    'FIRST_REG_DATE' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                    'ENGINE_NUMBER' => $engine_number,#'ENGINE12345',
                    'CHASSIS_NUMBER' => $chassis_number,#'CHASSIS1234567891',
                    'VEH_IDV' => $vehIdv,
                    'CUBIC_CAPACITY' => $mmv_data->cubic_capacity,
                    'SEATING_CAPACITY' => $mmv_data->seating_capacity,
                    'NO_OF_DRIVERS' => $no_of_ll_paid_drivers,
                    'NO_OF_CONDUCTORS' => $no_of_ll_paid_conductors,
                    'NO_OF_CLEANERS' => $no_of_ll_paid_cleaners,
                    'FUEL_TYPE_CODE' => $fuelType[strtoupper($mmv_data->veh_fuel_desc)],
                    'RTO_CODE' => $requestData->rto_code,
                    // 'ZONE_CODE' => $rtoZone,
                    'ZONE_CODE' => $class_code == 'CLASS_4C1A' ? $rto_location->rto_zone : $cv_rto_location->rto_zone ,
                    'VOLUNTARY_EXCESS' => $voluntary_excess_value,
                    'MEMBER_OF_AAI' => 0,
                    'ANTITHEFT_DEVICE_DESC' => $is_anti_theft,
                    'NON_ELEC_ACCESS_DESC' => $is_non_electrical_accessories,
                    'NON_ELEC_ACCESS_VALUE' => $non_electrical_accessories_value,
                    'ELEC_ACCESS_DESC' => $is_electrical_accessories,
                    'ELEC_ACCESS_VALUE' => $electrical_accessories_value,
                    'NCB_DECL_SUBMIT_YN' => ($requestData->is_claim == 'Y') ? 'N': 'Y',
                    'LIMITED_TPPD_YN' => $is_tppd_cover,
                    'RALLY_COVER_YN' => NULL,
                    'RALLY_DAYS' => NULL,
                    'NIL_DEP_YN' => $productData->zero_dep == 0 ? 1 : 0,
                    'FIBRE_TANK_VALUE' => 0,
                    'RETN_TO_INVOICE' => (($car_age > 2) || ($interval->y == 2 && ($interval->m > 0 || $interval->d > 0))) ? '0' : '1', //Return to invoice
                    'PERS_EFF_COVER' => NULL,
                    'NO_OF_PA_OWNER_DRIVER' => 1,
                    'NO_OF_PA_NAMED_PERSONS' => NULL,
                    'PA_NAMED_PERSONS_SI' => NULL,
                    'NO_OF_PA_UNNAMED_PERSONS' => $no_of_unnamed_passenger,
                    'PA_UNNAMED_PERSONS_SI' => $unnamed_passenger_sum_insured,
                    'NO_OF_PA_UNNAMED_HIRER' => NULL,
                    'NO_OF_LL_PAID_DRIVER' => $no_of_ll_paid_drivers,
                    'NO_OF_LL_EMPLOYEES' => NULL,
                    'NO_OF_LL_SOLDIERS' => NULL,
                    'OTH_SINGLE_FUEL_CVR' => NULL,
                    'IMP_CAR_WO_CUSTOMS_CVR' => NULL,
                    'DRIVING_TUITION_EXT_CVR' => NULL,
                    'DLR_PA_NOMINEE_NAME' => NULL,
                    'DLR_PA_NOMINEE_DOB' => NULL,
                    'DLR_PA_NOMINEE_RELATION' => NULL,
                    'HYPO_TYPE' => NULL,
                    'HYPO_COMP_NAME' => NULL,
                    'HYPO_COMP_ADDR_01' => NULL,
                    'HYPO_COMP_ADDR_02' => NULL,
                    'HYPO_COMP_ADDR_03' => NULL,
                    'HYPO_COMP_CITY' => NULL,
                    'HYPO_COMP_STATE' => NULL,
                    'HYPO_COMP_PINCODE' => NULL,
                    'PAYMENT_TYPE' => 'CD',
                    'NCB_PERCENTAGE' => ($requestData->is_claim == 'Y') ? '' : $requestData->previous_ncb,
                    'PREV_INSU_COMPANY' => $requestData->business_type == 'newbusiness' ? NULL : 'DHFL General Insurance Ltd',
                    'PREV_POL_NUMBER' => $requestData->business_type == 'newbusiness' ? NULL : '474F93G4934',
                    'PREV_POL_START_DATE' => $previous_policy_start_date,
                    'PREV_POL_END_DATE' => $previous_policy_end_date,
                    'EXIS_POL_FM_OTHER_INSR' => NULL,
                    'IP_ADDRESS' => NULL,
                    'MAC_ADDRESS' => NULL,
                    'WIN_USER_ID' => NULL,
                    'WIN_MACHINE_ID' => NULL,
                    'DISCOUNT_PERC' => $discount_percentage,
                    'TP_PREMIUM_OUT' => NULL,
                    'OD_PREMIUM_OUT' => NULL,
                    'ANNUAL_PREMIUM_OUT' => NULL,
                    'NCB_PERCENTAGE_OUT' => NULL,
                    'NCB_AMOUNT_OUT' => NULL,
                    'SERVICE_TAX_OUT' => NULL,
                    'PROPOSAL_NO_OUT' => NULL,
                    'POLICY_SYS_ID_OUT' => NULL,
                    'ERROR_CODE' => NULL,
                    'ROAD_TRANSPORT_YN' => 0,
                    'MOU_ORG_MEM_ID' => NULL,
                    'MOU_ORG_MEM_VALI' => NULL,
                    'THREEWHEELER_YN' => 0,
                    // 'VEHICLE_GVW' => $parent_id == 'GCV' ? 2510 : 0,
                    'VEHICLE_GVW' => $mmv_data->vehicle_gvw ?? '',
                    'SIDE_CAR_ACCESS_DESC' => NULL,
                    'SIDE_CARS_VALUE' => NULL,
                    'TRAILER_DESC' => NULL,
                    'TRAILER_VALUE' => NULL,
                    'ARTI_TRAILER_DESC' => NULL,
                    'ARTI_TRAILER_VALUE' => NULL,
                    'NO_OF_COOLIES' => NULL,
                    'NO_OF_LL_PAID_DRIVER' => $no_of_ll_paid_drivers,
                    'TOWING_TYPE' => NULL,
                    'NO_OF_TRAILERS_TOWED' => NULL,
                    'NO_OF_NFPP_EMPL' => ($nfpp) ? $nfpp_si : 0,
                    'NO_OF_NFPP_OTH_THAN_EMPL' => 0,
                    'FLEX_01' => $pa_paid_driver_sum_insured,
//                    'FLEX_02' => $parent_id == 'GCV' && $premium_type != 'third_party' && $productData->zero_dep == 0 ? 1 : NULL,
                    'FLEX_02' => $parent_id == 'GCV' && $premium_type != 'third_party' && $productData->product_identifier == 'imt_23' ? 1 : NULL,
                    'FLEX_03' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                    'FLEX_05' => NULL,
                    'FLEX_06' => NULL,
                    'FLEX_07' => NULL,
                    'FLEX_08' => NULL,
                    'FLEX_09' => isset($addtowcharge) ? $addtowcharge : 20000,
                    'FLEX_10' => NULL,
                    'FLEX_12' => ($productData->zero_dep == 0 && $discount_percentage > 0) ? 20 : 0,
                    'FLEX_17' => $misp_code,
                    'FLEX_18' => $misp_name,
                    'FLEX_19' => $posp_code,
                    'FLEX_20' => $requestData->vehicle_owner_type == 'I' ? 'N' : 'Y',
                    'FLEX_21' => NULL,
                    'FLEX_22' => NULL,
                    'FLEX_25' => 'N~Y',
                    'FLEX_31' => '1_MONTH_EMI~~'.$vehIdv.'~~Y'
                ],
                '_attributes' => [
                    'xmlns' => 'http://MotorService/'
                ]
            ]
        ]
    ];

    if(!empty($misp_data) && !empty($misp_data->relation_oriental) && $parent_id == 'GCV'){
        $misp_codes = json_decode($misp_data->relation_oriental);
        $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['LOGIN_ID'] = $misp_codes->login_id ?? null;
        $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['FLEX_18'] = $misp_codes->Flex_18 ?? null;
        $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['FLEX_17'] = $misp_codes->Flex_17 ?? null;
    }

    if ($is_external_cng_kit) {
        $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['CNG_KIT_VALUE'] = $external_kit_value;
        $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['FLEX_04'] = '';
    }

    if($is_internal_cng)
    {
        $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['FLEX_04'] = 'Y';
    }

    if ($is_geo_ext)
    {
        if (in_array('Sri Lanka',$countries))
        {
            $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['GEO_EXT_CODE'][]='GEO-EXT-COD5';
        }
        elseif (in_array('Bangladesh',$countries))
        {
            $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['GEO_EXT_CODE'][]='GEO-EXT-COD1'; 
        }
        elseif (in_array('Bhutan',$countries))
        {
            $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['GEO_EXT_CODE'][]='GEO-EXT-COD2'; 
        }
        elseif (in_array('Nepal',$countries))
        {
             $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['GEO_EXT_CODE'][]='GEO-EXT-COD3'; 
        }
        elseif (in_array('Pakistan',$countries))
        {
            $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['GEO_EXT_CODE'][]='GEO-EXT-COD4'; 
        }
        elseif (in_array('Maldives',$countries))
        {
            $premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['GEO_EXT_CODE'][]='GEO-EXT-COD6'; 
        }
    }
    $root = [
        'rootElementName' => 'soap:Envelope',
        '_attributes' => [
            'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema'
        ]
    ];
    $input_array = ArrayToXml::convert($premium_calculation_request, $root, false,'utf-8');
    //$refer_webservice = config('ENABLE_TO_GET_DATA_FROM_WEBSERVICE_ORIENTAL_CV') == 'Y';
    $refer_webservice = $productData->db_config['quote_db_cache'];
    $checksum_data = checksum_encrypt($input_array);
    $service_rehit = true;
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'oriental',$checksum_data,'CV');
    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
    {
        $get_response = $is_data_exits_for_checksum;
    }
    else
    {
        if($is_data_exits_for_checksum['found'] && !$is_data_exits_for_checksum['status'] && trim(config('ERROR_CODE.ORIENTAL'))!= null )
        {
            $error_codes = explode(',',trim(config('ERROR_CODE.ORIENTAL')));
            foreach($error_codes as $code)
            {
                if(str_contains($is_data_exits_for_checksum['message'],$code))
                {
                    $service_rehit = false;
                    $get_response = $is_data_exits_for_checksum;
                    break;
                }
            }
        }
        if($service_rehit)
        {
            $get_response = getWsData(
                config('constants.IcConstants.oriental.QUOTE_URL_ORIENTAL_CV'),
                $input_array,
                'oriental',
                [
                    'enquiryId' => $enquiryId,
                    'headers' => [
                        'Content-Type' => 'text/xml; charset="utf-8"',
                    ],
                    'requestMethod' => 'post',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Premium Calculation',
                    'productName' => $productData->product_name,
                    'transaction_type' => 'quote',
                    'checksum' => $checksum_data,
                    'policy_id' => $productData->policy_id
                ]
            );
        }
    }

    // $get_response = getWsData(
    //     config('constants.IcConstants.oriental.QUOTE_URL_ORIENTAL_CV'),
    //     $input_array,
    //     'oriental',
    //     [
    //         'enquiryId' => $enquiryId,
    //         'headers' => [
    //             'Content-Type' => 'text/xml; charset="utf-8"',
    //         ],
    //         'requestMethod' => 'post',
    //         'section' => $productData->product_sub_type_code,
    //         'method' => 'Premium Calculation',
    //         'productName' => $productData->product_name,
    //         'transaction_type' => 'quote'
    //     ]
    // );

    $premium_calculation_response = $get_response['response'];
    if ($premium_calculation_response) {
        $response = XmlToArray::convert($premium_calculation_response);

        if (isset($response['soap:Body']['soap:Fault'])) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' =>  $response['soap:Body']['soap:Fault']['faultstring']
            ];
        }

        $response = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];

        if ($response['ERROR_CODE'] == '0 0') 
        {
            // $min_idv = ceil($idv * 0.85);
            // $max_idv = floor($idv * 1.15);

            // if ($premium_type != 'third_party') 
            // {
            //     if ($requestData->is_idv_changed == 'Y') 
            //     {
            //         if ($requestData->edit_idv >= $max_idv) {
            //             $idv = $max_idv;
            //         } elseif ($requestData->edit_idv <= $min_idv) {
            //             $idv = $min_idv;
            //         } else {
            //             $idv = $requestData->edit_idv;
            //         }
            //     } 
            //     else 
            //     {
            //         $idv = $min_idv;
            //     }

            //     //$premium_calculation_request['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['VEH_IDV'] = $idv;

            //     //$input_array = ArrayToXml::convert($premium_calculation_request, $root, false,'utf-8');

            //     // $checksum_data = checksum_encrypt($input_array);
            //     // $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'oriental',$checksum_data,'quote');
                
            //     // if($is_data_exits_for_checksum['found'] && $refer_webservice)
            //     // {
            //     //     $get_response = $is_data_exits_for_checksum;
            //     // }
            //     // else
            //     // {
            //     //     $get_response = getWsData(
            //     //         config('constants.IcConstants.oriental.QUOTE_URL_ORIENTAL_CV'),
            //     //         $input_array,
            //     //         'oriental',
            //     //         [
            //     //             'enquiryId' => $enquiryId,
            //     //             'headers' => [
            //     //                 'Content-Type' => 'text/xml; charset="utf-8"',
            //     //             ],
            //     //             'requestMethod' => 'post',
            //     //             'section' => $productData->product_sub_type_code,
            //     //             'method' => 'Premium Recalculation',
            //     //             'productName' => $productData->product_name,
            //     //             'transaction_type' => 'quote',
            //     //             'checksum' => $checksum_data,
            //     //             'policy_id' => $productData->policy_id
            //     //         ]
            //     //     );     
            //     // }

            //     // $get_response = getWsData(
            //     //     config('constants.IcConstants.oriental.QUOTE_URL_ORIENTAL_CV'),
            //     //     $input_array,
            //     //     'oriental',
            //     //     [
            //     //         'enquiryId' => $enquiryId,
            //     //         'headers' => [
            //     //             'Content-Type' => 'text/xml; charset="utf-8"',
            //     //         ],
            //     //         'requestMethod' => 'post',
            //     //         'section' => $productData->product_sub_type_code,
            //     //         'method' => 'Premium Recalculation',
            //     //         'productName' => $productData->product_name,
            //     //         'transaction_type' => 'quote'
            //     //     ]
            //     // );

            //     $premium_calculation_response = $get_response['response'];
            //     $response = XmlToArray::convert($premium_calculation_response);

            //     if (isset($response['soap:Body']['soap:Fault'])) {
            //         return [
            //             'premium_amount' => 0,
            //             'status' => false,
            //             'webservice_id' => $get_response['webservice_id'],
            //             'table' => $get_response['table'],
            //             'message' =>  $response['soap:Body']['soap:Fault']['faultstring']
            //         ];
            //     }
    
            //     $response = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];

            //     if ($response['ERROR_CODE'] != '0 0') {
            //         return [
            //             'premium_amount' => 0,
            //             'status' => false,
            //             'webservice_id' => $get_response['webservice_id'],
            //             'table' => $get_response['table'],
            //             'message' => $response['ERROR_CODE'],
            //         ];
            //     }
            // }

            $basic_od = 0;
            $tppd = 0;
            $pa_owner = 0;
            $pa_unnamed = 0;
            $pa_paid_driver = 0;
            $electrical_accessories = 0;
            $non_electrical_accessories = 0;
            $zero_dep_amount = 0;
            $ncb_discount = 0;
            $lpg_cng = 0;
            $lpg_cng_tp = 0;
            $automobile_association = 0;
            $anti_theft = 0;
            $tppd_discount_amt = 0;
            $other_addon_amount = 0;
            $ll_paid_driver = 0;
            $liabilities = 0;
            $ll_paid_cleaner = 0;
            $imt_23 = 0;
            $ic_vehicle_discount = 0;
            $voluntary_excess = 0;
            $other_discount = 0;
            $other_fuel_1 = 0;
            $other_fuel_2 = 0;
            $other_discount_zd = 0;
            $GeogExtension_od = 0;
            $GeogExtension_tp = 0;
            $consumables = 0;
            $rti_prem = 0;
            $addtowprem = 0;
            $nfppprem = 0;
            $emi_protector = 0;

            $flex_01 = !empty($response['FLEX_02_OUT']) ? $response['FLEX_01_OUT'].$response['FLEX_02_OUT'] : $response['FLEX_01_OUT'];

            $flex = explode(',', $flex_01);

            foreach ($flex as $val) {
                $cover = explode('~', $val);

                if ($cover[0] == 'MOT-CVR-001') {
                    $basic_od = $cover[1];
                    // $idv = $cover[2];
                } elseif ($cover[0] == 'MOT-CVR-002') {
                    $electrical_accessories = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-003') {
                    $lpg_cng = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-006') {
                    $GeogExtension_od = $cover[1];
                }
                elseif ($cover[0] == 'MOT-CVR-051')
                {
                    $GeogExtension_tp = $cover[1];
                }
                elseif ($cover[0] == 'MOT-CVR-007') {
                    $tppd = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-008') {
                    $lpg_cng_tp = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-010') {
                    $pa_owner = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-012') {
                    $pa_unnamed = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-013') {
                    $pa_paid_driver = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-015') {
                    $ll_paid_driver = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-019') {
                    $tppd_discount_amt = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-053') {
                    $other_fuel_1 = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-058') {
                    $other_fuel_2 = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-150') {
                    $zero_dep_amount = (int) $cover[1];
                } elseif ($cover[0] == 'MOT-DIS-002') {
                    $anti_theft = $cover[1];
                } elseif ($cover[0] == 'MOT-DIS-004') {
                    $voluntary_excess = $cover[1];
                } elseif ($cover[0] == 'MOT-DIS-310') {
                    $ncb_discount = $cover[1];
                } elseif ($cover[0] == 'MOT-DLR-IMT') {
                    $other_discount = (int)$cover[1];
                } elseif ($cover[0] == 'MOT-LOD-007') {
                    $imt_23 = (int)$cover[1];
                } elseif ($cover[0] == "MOT-CVR-155") {

                    $consumables = $cover[1];
                } elseif ($cover[0] == "MOT-CVR-070") {

                    $rti_prem = $cover[1];
                } elseif ($cover[0] == 'MOT-LOD-008') {
                    $addtowprem = $cover[1];
                } elseif ($cover[0] == 'MOT-LOD-010') {
                    $nfppprem = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-157') {
                    $emi_protector = $cover[1];
                } elseif ($cover[0] == 'MOT-DIS-ACN') {
                    $other_discount_zd = $cover[1];
                }
            }
            $final_od_premium = $basic_od + $electrical_accessories + $lpg_cng + $GeogExtension_od + $other_fuel_1; //+ $non_electrical_accessories
            $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $ll_paid_driver + $GeogExtension_tp + $other_fuel_2 + $nfppprem;

            // $other_discount = ($final_od_premium - $GeogExtension_od) * 70/100;

            //Add ZD discount in other_discount
            $other_discount = $other_discount_zd + $other_discount;

            $ribbonMessage = null;
            if ($agentDiscountSelected  && $other_discount > 0) {
                $agentDiscountPercentage = round(($other_discount / $final_od_premium) * 100);
                if ($userSelectedPercentage != $agentDiscountPercentage && $userSelectedPercentage > $agentDiscountPercentage) {
                    $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount').' '.$agentDiscountPercentage.'%';
                }
            }

            $final_total_discount =  $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount;

            // $ncb_discount = ($final_od_premium - $final_total_discount) * ($requestData->applicable_ncb/100);

            $final_total_discount = $final_total_discount + $tppd_discount_amt + $ncb_discount;

            $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);

            if ($parent_id == 'GCV') {
                $final_gst_amount = round(($tppd * 0.12) + (($final_net_premium - $tppd) * 0.18));
            } else {
                $final_gst_amount = round($final_net_premium * 0.18);
            }

            $final_payable_amount = $final_net_premium + $final_gst_amount;

            $applicable_addons = ['consumables','returnToInvoice','imt23','zeroDepreciation'];

            // if ($productData->zero_dep == 0) {
            //     $applicable_addons = ['zeroDepreciation','imt23'];
            // } elseif ($productData->product_identifier == 'imt_23') {
            //     $applicable_addons = ['imt23'];
            // } else {
            //     $applicable_addons = [];
            // }

            // if ($parent_id == 'PCV') {
            //     array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
            // }

            // if ($interval->y >= 5) {
            //     array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            // }

            $business_types = [
                'rollover' => 'Rollover',
                'newbusiness' => 'New Business',
                'breakin' => 'Break-in'
            ];

            $addons_data = [ 
                'in_built' => [],
                'additional' => []
            ];
            
            if ($productData->zero_dep == 0 && $parent_id == 'GCV') {
                $addons_data = [
                    'in_built' => [
                        'zero_depreciation' => $zero_dep_amount,
                        'imt23' => $imt_23,
                        'road_side_assistance' => 0,
                    ],
                    'additional' => [
                        'road_side_assistance' => 0,
                        'consumables' => round($consumables),
                        'return_to_invoice' => round($rti_prem),
                        'additional_towing' => round($addtowprem),
                        'emi_protection' => round($emi_protector),
                    ],
                    'other' => [
                        // 'other_fuel' => $other_fuel_1 + $other_fuel_2
                    ]
                ];
            } elseif ($productData->product_identifier == 'imt_23') {
                $addons_data = [
                    'in_built' => [
                        'imt23' => $imt_23,
                        'road_side_assistance' => 0,
                    ],
                    'additional' => [
                        'zero_depreciation' => $zero_dep_amount,
                        'consumables' => round($consumables),
                        'return_to_invoice' => round($rti_prem),
                        'additional_towing' => round($addtowprem),
                        'emi_protection' => round($emi_protector),
                    ],
                    'other' => [
                        // 'other_fuel' => $other_fuel_1 + $other_fuel_2
                    ]
                ];
            } elseif ($productData->zero_dep == 0 && $parent_id == 'PCV') {
                $addons_data = [
                    'in_built' => [
                        'zero_depreciation' => $zero_dep_amount,
                    ],
                    'additional' => [
                        'imt23' => $imt_23,
                        'road_side_assistance' => 0,
                        'additional_towing' => round($addtowprem),
                    ]
                ];
            } else {
                $addons_data = [
                    'in_built' => [
                        'road_side_assistance' => 0,
                    ],
                    'additional' => [
                        'zero_depreciation' => 0,
                        'imt23' => $imt_23,
                        'consumables' => round($consumables),
                        'return_to_invoice' => round($rti_prem),
                        'additional_towing' => round($addtowprem),
                        'emi_protection' => round($emi_protector),
                    ],
                    'other' => [
                        // 'other_fuel' => $other_fuel_1 + $other_fuel_2
                    ]
                ];
            }

            // if ($parent_id == 'PCV') {
            //     unset($addons_data['additional']['imt23']);
            // }

            // if ($addons_data['other']['other_fuel'] == 0) {
            //     unset($addons_data['other']['other_fuel']);
            // }

            if(!round($consumables) > 0)
            {
                array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
            }
            if(!round($rti_prem) > 0)
            {
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            }
            if(!round($imt_23) > 0)
            {
                array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
            }
            if(!round($zero_dep_amount) > 0)
            {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            }

            $mmv_data->version_name = $mmv_data->fyntune_version['version_name'];

            $data_response = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Data' => [
                    'idv' => $tp_only == 'true' ? 0 : round($idv),
                    'min_idv' => $tp_only == 'true' ? 0 : round($min_idv),
                    'max_idv' => $tp_only == 'true' ? 0 : round($max_idv),
                    'vehicle_idv' => $tp_only == 'true' ? 0 : round($idv),
                    'qdata' => NULL,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => NULL,
                    'addon_cover_data_get' => '',
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => $tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                    'business_type' => $business_types[$requestData->business_type],
                    'cover_type' => '1YC',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'rto_no' => $rto_code,
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'distance' => isset($addtowcharge) ? $addtowcharge : 20000,
                    'additional_towing_options' => [
                        '1000', '2000', '3000', '4000',
                        '5000', '6000', '7000', '8000',
                        '9000', '10000','11000','12000',
                        '13000','14000','15000','16000',
                        '17000','18000','19000','20000'
                    ],
                    'showroom_price' => 0,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => (int)$ncb_discount > 0 ? $requestData->applicable_ncb : 0,
                    'tppd_discount' => $tppd_discount_amt,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos')).'/'.$productData->logo,
                    'product_name' => $productData->product_sub_type_name,
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
                        'logo' => env('APP_URL').config('constants.motorConstant.logos').$productData->logo,
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => '',
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $requestData->vehicle_register_date,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $car_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => 0//round($insurer_discount)
                    ],
                    'ribbon' => $ribbonMessage,
                    'basic_premium' => $basic_od,
                    'motor_electric_accessories_value' => $electrical_accessories,
                    // 'motor_non_electric_accessories_value' => $non_electrical_accessories,
                    'motor_lpg_cng_kit_value' => $lpg_cng,
                    'total_accessories_amount(net_od_premium)' => $electrical_accessories  + $lpg_cng,//$non_electrical_accessories 
                    'total_own_damage' => $final_od_premium,
                    'tppd_premium_amount' => $tppd,
                    'compulsory_pa_own_driver' => $pa_owner,  // Not added in Total TP Premium
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    'default_paid_driver' => $liabilities + $ll_paid_driver,
                    // 'll_paid_driver_premium' => $liabilities + $ll_paid_driver,
                    // 'll_paid_conductor_premium' => 0,
                    // 'll_paid_cleaner_premium' => 0,
                    'motor_additional_paid_driver' => $pa_paid_driver,
                    'cng_lpg_tp' => $lpg_cng_tp,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'deduction_of_ncb' => $ncb_discount,
                    'antitheft_discount' => $anti_theft,
                    'aai_discount' => $automobile_association,
                    'voluntary_excess' => $voluntary_excess,
                    'other_discount' => $other_discount,
                    'ic_vehicle_discount' => $other_discount,
                    'total_liability_premium' => $final_tp_premium,
                    'net_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => $final_payable_amount,
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
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
                    'max_addons_selection' => NULL,
                    'add_ons_data' => $addons_data,
                    'GeogExtension_ODPremium' => round($GeogExtension_od),
                    'GeogExtension_TPPremium' => round($GeogExtension_tp),
                    'LimitedtoOwnPremises_OD' => 0,
                    'LimitedtoOwnPremises_TP' => 0,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => $final_total_discount,
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => round($final_gst_amount),
                    'final_payable_amount' => round($final_payable_amount),
                    'applicable_addons' => $applicable_addons,
                    'mmv_detail' => [
                        'manf_name' => $mmv_data->veh_manf_desc,
                        'model_name' => $mmv_data->veh_model_desc,
                        'version_name' => '',
                        'fuel_type' => $mmv_data->veh_fuel_desc,
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'carrying_capacity' => (int) $mmv_data->seating_capacity + 1,
                        'cubic_capacity' => $mmv_data->cubic_capacity,
                        'gross_vehicle_weight' => $mmv_data->vehicle_gvw ?? '',//$mmv_data->gross_weight,
                        'vehicle_type' => '',//$mmv_data->veh_type_name,
                    ],
                    'response' => $response
                ]
            ];
            $included_additional = [
                'included' =>[]
            ];
            if($is_non_electrical_accessories == 'Y')
            {
                $data_response['Data']['motor_non_electric_accessories_value'] = 0;
                $included_additional['included'][] = 'motorNonElectricAccessoriesValue';
            }

            if($other_fuel_2 !== 0)
            {
                $data_response['Data']['cng_lpg_tp'] = $other_fuel_2;
            }

            if($other_fuel_1 !== 0)
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = $other_fuel_1;
            }
            if($nfpp){
                $data_response['Data']['nfpp'] = $nfppprem;
            }
            $data_response['Data']['included_additional'] = $included_additional;
            return camelCase($data_response);
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => empty($response['ERROR_CODE'] ?? '') ? 'Invalid response from IC service' : $response['ERROR_CODE'],
            ];
        }
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Server Error',
        ];
    }
}