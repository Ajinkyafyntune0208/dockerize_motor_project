<?php
namespace App\Http\Controllers\Proposal\Services;

use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\MasterRto;
use Illuminate\Http\Request;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Services\OrientalPremiumDetailController;
use App\Models\AgentIcRelationship;
use App\Models\CvAgentMapping;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

class orientalSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote_log_data = DB::table('quote_log')
            ->where('user_product_journey_id',$enquiryId)
            ->select('idv')
            ->first();
        $idv = $quote_log_data->idv;

        $parent_id = get_parent_code($productData->product_sub_type_id);

        $mmv = get_mmv_details($productData, $requestData->version_id, 'oriental');

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

        $state_city_details = DB::table('oriental_state_city_pincode_master')
            ->where('pincode', $proposal->pincode)
            ->first();

        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-M-Y');
            $tp_start_date = $policy_start_date;
        } else {
            $date_difference = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);
    
            if ($requestData->business_type == 'rollover') {
                $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                $tp_start_date = $policy_start_date;
            } else {
                $policy_start_date = date('d-M-Y');
                $tp_start_date = $policy_start_date;
            }                
        }
    
        $policy_end_date = date('d-M-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $tp_end_date = $policy_end_date ;
        $DateOfPurchase = date('d-M-Y', strtotime($requestData->vehicle_register_date));
        $vehicle_register_date = explode('-',$requestData->vehicle_register_date);
        $vehicle_in_90_days = 'N';
    
        $previous_policy_start_date = $requestData->business_type== 'newbusiness' ? '' : date('d-M-Y', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
        $previous_policy_end_date = $requestData->business_type== 'newbusiness' ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date));
    
        $car_age = 0;
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new \DateTime($vehicleDate);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);
        
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

        $is_cpa = 0;
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
        $is_imt_23 = NULL;
        $is_zero_depreciation = 0;
        $is_geo_ext = false;
        $misp_code = null;
        $misp_name = null;
        $consumable = 'N';
        $rti = 0;
        $addtowcharge = 0;
        $nfpp = false;
        $nfpp_si = 0;
        $emi_protector = 'N~N';


        foreach ($addons_v2 as $key => $value) {
            if ($value->name == "Additional Towing") {
                $addtowcharge = isset($value->sumInsured) ? $value->sumInsured : 20000;
            }
        }

        if ($selected_addons && !empty($selected_addons)) {
            $voluntary_excess_master = [
                0 => 'PCVE1',
                2500 => 'PCVE2',
                5000 => 'PCVE3',
                7500 => 'PCVE4',
                15000 => 'PCVE5'
            ];

            if ($selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
                $compulsory_personal_accident = json_decode($selected_addons->compulsory_personal_accident, TRUE);
    
                foreach ($compulsory_personal_accident as $cpa) {
                    if (isset($cpa['name']) && $cpa['name'] == 'Compulsory Personal Accident') {
                        $is_cpa = 1;
                    }
                }
            }

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
                    }
                    elseif ($additional_cover['name'] == 'Geographical Extension') {
                        $is_geo_ext = true;
                        $countries = $additional_cover['countries'];
                    } 
                    elseif($additional_cover['name'] == 'NFPP Cover'){
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

            $vehIdv = '';

            if (!empty($idv)) {
                $vehIdv = $idv + $non_electrical_accessories_value + $electrical_accessories_value + $external_kit_value;
            }

            if ($selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') {
                $addons = json_decode($selected_addons->applicable_addons, TRUE);
    
                foreach ($addons as $addon) {
                    if ($addon['name'] == 'IMT - 23') {
                        $is_imt_23 = 1;
                    } elseif ($addon['name'] == 'Zero Depreciation') {
                        $is_zero_depreciation = 1;
                    } elseif ($addon['name'] == 'Consumable') {
                        $consumable = 'Y';
                    } elseif ($addon['name'] == 'Return To Invoice') {
                        $rti = 1;
                    } elseif ($addon['name'] == 'EMI Protection') {
                        $emi_protector = '1_MONTH_EMI~~'.$vehIdv.'~~Y';
                    }
                }
            }
        }
        if (!$car_age > 5) {
            if ($parent_id == 'GCV' && (($is_zero_depreciation == 1 && $is_imt_23 == NULL) || ($is_zero_depreciation == 0 && $is_imt_23 == 1))) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Zero dep is not allowed if IMT-23 is not selected'
                ];
            }
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_oriental_non_pos =config('IS_ORIENTAL_NON_POS');
        $is_pos = FALSE;
        $posp_code = "";
        
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        $misp_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','misp')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $is_oriental_non_pos != 'Y')
        {
            $pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('oriental_code')
                    ->first();
            if(empty($pos_code) || is_null($pos_code))
            {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => 'POS details Not Available'
                ];
            }
            else
            {
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

        if ($premium_type != 'third_party') {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'oriental', strtolower($parent_id));
            if ($agentDiscount['status'] ?? false) {
                $discount_percentage = $agentDiscount['discount']; //$discount_percentage >= $agentDiscount['discount'] ? $agentDiscount['discount'] : $discount_percentage;
            } else {
                if (!empty($agentDiscount['message'] ?? '')) {
                    return [
                        'status' => false,
                        'message' => $agentDiscount['message']
                    ];
                }
            }
        }
        
        $misp_code = config('IC.ORIENTAL.CV.MISP_CODE');
        $misp_name = config('IC.ORIENTAL.CV.MISP_NAME');

        $premium_calculation_request = [
            'soap:Body' => [
                'GetQuoteMotor' => [
                    'objGetQuoteMotorETT' => [
                        'LOGIN_ID' => config('constants.IcConstants.oriental.LOGIN_ID_ORIENTAL_CV'),
                        'DLR_INV_NO' => config('constants.motor.oriental.LOGIN_ID_ORIENTAL_MOTOR'). '-' .customEncrypt($enquiryId),
                        'DLR_INV_DT' => date('d-M-Y'),
                        'PRODUCT_CODE' => $product_code,
                        'POLICY_TYPE' => $premium_type == 'third_party' ? 'MOT-PLT-002' : 'MOT-PLT-001',
                        'START_DATE' => $policy_start_date,
                        'END_DATE' => $policy_end_date,
                        'INSURED_NAME' => $proposal->first_name.' '.$proposal->last_name,
                        'ADDR_01' => $proposal->address_line1,
                        'ADDR_02' => $proposal->address_line2,
                        'ADDR_03' => $proposal->address_line3,
                        'CITY' => $proposal->city_id,
                        'STATE' => $state_city_details->state_code,
                        'PINCODE' => $proposal->pincode,
                        'COUNTRY' => 'IND',
                        'EMAIL_ID' => $proposal->email,
                        'MOBILE_NO' => $proposal->mobile_number,
                        'TEL_NO' => NULL,
                        'FAX_NO' => NULL,
                        'INSURED_KYC_VERIFIED' => 1,
                        'MANUF_VEHICLE_CODE' => $mmv_data->ven_manf_code,
                        'VEHICLE_MODEL_CODE' => $mmv_data->vehicle_model_code,
                        'TYPE_OF_BODY_CODE' => $parent_id == 'PCV' ? 11 : 'MMOPEN',
                        'VEHICLE_COLOR' => $proposal->vehicle_color ? $proposal->vehicle_color : 'Black',
                        'VEHICLE_REG_NUMBER' => $requestData->business_type== 'newbusiness' ? 'NEW-3448' : $proposal->vehicale_registration_number,
                        'VEHICLE_CODE' => $mmv_data->vehicle_model_code,
                        'VEHICLE_TYPE_CODE' => $requestData->business_type== 'newbusiness' ? 'W' : 'P',
                        'VEHICLE_CLASS_CODE' => $class_code,
                        'MANUF_CODE' => $mmv_data->ven_manf_code,
                        'FIRST_REG_DATE' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                        'ENGINE_NUMBER' => $proposal->engine_number,
                        'CHASSIS_NUMBER' => $proposal->chassis_number,
                        'VEH_IDV' => $vehIdv,
                        'CUBIC_CAPACITY' => $mmv_data->cubic_capacity,
                        'SEATING_CAPACITY' => $mmv_data->seating_capacity,
                        'NO_OF_DRIVERS' => $no_of_ll_paid_drivers,
                        'NO_OF_CONDUCTORS' => $no_of_ll_paid_conductors,
                        'NO_OF_CLEANERS' => $no_of_ll_paid_cleaners,
                        'FUEL_TYPE_CODE' =>$fuelType[strtoupper($mmv_data->veh_fuel_desc)],
                        'RTO_CODE' => $requestData->rto_code,
                        // 'ZONE_CODE' => $parent_id == 'GCV' ? 37 : 35,//$mmv_data->zone,
                        'ZONE_CODE' => $class_code == 'CLASS_4C1A' ? $rto_location->rto_zone : $cv_rto_location->rto_zone,
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
                        'NIL_DEP_YN' => $is_zero_depreciation,
                        'FIBRE_TANK_VALUE' => 0,
                        'RETN_TO_INVOICE' => $rti,
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
                        'DLR_PA_NOMINEE_NAME' => $proposal->nominee_name != '' && $proposal->nominee_name != NULL ? $proposal->nominee_name : NULL,
                        'DLR_PA_NOMINEE_DOB' => $proposal->nominee_dob != '' && $proposal->nominee_dob != NULL ? date('d-M-Y', strtotime($proposal->nominee_dob)) : NULL,
                        'DLR_PA_NOMINEE_RELATION' => $proposal->nominee_relationship != '' && $proposal->nominee_relationship != NULL ? $proposal->nominee_relationship : NULL,
                        'HYPO_TYPE' => $proposal->is_vehicle_finance == 1 ? $proposal->financer_agreement_type : NULL,
                        'HYPO_COMP_NAME' => $proposal->is_vehicle_finance == 1 ? $proposal->name_of_financer : NULL,
                        'HYPO_COMP_ADDR_01' => NULL,
                        'HYPO_COMP_ADDR_02' => NULL,
                        'HYPO_COMP_ADDR_03' => NULL,
                        'HYPO_COMP_CITY' => $proposal->is_vehicle_finance == 1 ? $proposal->hypothecation_city : NULL,
                        'HYPO_COMP_STATE' => NULL,
                        'HYPO_COMP_PINCODE' => NULL,
                        'PAYMENT_TYPE' => 'CD',
                        'NCB_PERCENTAGE' => $requestData->is_claim == 'Y' ? '' : $requestData->previous_ncb,
                        'PREV_INSU_COMPANY' => $requestData->business_type == 'newbusiness' ? '' : $proposal->previous_insurance_company,
                        'PREV_POL_NUMBER' => $requestData->business_type == 'newbusiness' ? '' : $proposal->previous_policy_number,
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
                        'FLEX_02' => $parent_id == 'PCV' ? '' : $is_imt_23,
                        'FLEX_03' => date('Y', strtotime('01-'.$proposal->vehicle_manf_year)),
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
                        'FLEX_20' => $is_cpa ? 'N' : 'Y',
                        'FLEX_21' => NULL,
                        'FLEX_22' => NULL,
                        'FLEX_25' => 'N~' . $consumable, //NULL
                        'FLEX_31' => $emi_protector,
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

        if($is_geo_ext)
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
                'transaction_type' => 'proposal'
            ]
        );
        $premium_calculation_response = $get_response['response'];

        $response = XmlToArray::convert($premium_calculation_response);

        if (isset($response['soap:Body']['soap:Fault'])) {
            return [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' =>  $response['soap:Body']['soap:Fault']['faultstring']
            ];
        }

        $response = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];

        if ($response['ERROR_CODE'] == '0 0') {    
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

            $is_breakin = '';
            $inspection_id = '';
            
            $GeogExtension_od = 0;
            $GeogExtension_tp = 0;
            $rti_prem = 0;
            $consumables = 0;
            $emi_protect_prem = 0;
            $addtowprem = 0;
            $nfppprem = 0;
    
            $flex_01 = !empty($response['FLEX_02_OUT']) ? $response['FLEX_01_OUT'].$response['FLEX_02_OUT'] : $response['FLEX_01_OUT'];

            $flex = explode(',', $flex_01);

            foreach ($flex as $val) {
                $cover = explode('~', $val);

                if ($cover[0] == 'MOT-CVR-001') {
                    $basic_od = $cover[1];
                    $idv = $cover[2];
                } elseif ($cover[0] == 'MOT-CVR-002') {
                    $electrical_accessories = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-003') {
                    $lpg_cng = $cover[1];
                }elseif ($cover[0] == 'MOT-CVR-006') {
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
                }
                elseif ($cover[0] == 'MOT-LOD-008') {
                    $addtowprem = $cover[1];
                } elseif ($cover[0] == 'MOT-LOD-010') {
                    $nfppprem = $cover[1];
                } elseif ($cover[0] == 'MOT-CVR-157') {
                    $emi_protect_prem = $cover[1];
                } elseif ($cover[0] == 'MOT-DIS-ACN') {
                    $other_discount_zd = $cover[1];
                }
            }

            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt + $other_discount_zd;

            $addon_premium = $other_fuel_1 + $other_fuel_2 + $zero_dep_amount + $imt_23 + $electrical_accessories + $lpg_cng + $rti_prem + $consumables + $addtowprem + $emi_protect_prem;
            $final_od_premium = ($basic_od + $GeogExtension_od) - $final_total_discount;
            $final_tp_premium = $tppd + $pa_unnamed + $pa_paid_driver + $ll_paid_driver + $lpg_cng_tp + $pa_owner + $GeogExtension_tp + $nfppprem;            

            $final_net_premium = round($final_od_premium + $final_tp_premium - $ncb_discount);
    
            $final_gst_amount = round($response['SERVICE_TAX']);

            $final_payable_amount = $response['ANNUAL_PREMIUM'];

            $ic_vehicle_details = [
                'manufacture_name' => $mmv_data->veh_manf_desc,
                'model_name' => $mmv_data->veh_model_desc,
                'version' => $mmv_data->veh_model_desc,
                'fuel_type' => $mmv_data->veh_fuel_desc,
                'seating_capacity' => $mmv_data->seating_capacity,
                'carrying_capacity' => (int) $mmv_data->seating_capacity + 1,
                'cubic_capacity' => $mmv_data->cubic_capacity,
                'gross_vehicle_weight' => $mmv_data->vehicle_gvw ?? '',//$mmv_data->gross_weight,
                'vehicle_type' => ''//$mmv_data->veh_type_name,
            ];

            UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'proposal_no' => $response['PROPOSAL_NO_OUT'],
                    'unique_proposal_id' => $response['POLICY_SYS_ID'],
                    'od_premium' => round($final_od_premium),
                    'tp_premium' => round($final_tp_premium),
                    'ncb_discount' => round($ncb_discount), 
                    'addon_premium' => round($addon_premium),
                    'cpa_premium' => round($pa_owner),
                    'total_discount' => round($final_total_discount),
                    'total_premium' => round($final_net_premium),
                    'service_tax_amount' => round($final_gst_amount),
                    'final_payable_amount' => round($final_payable_amount),
                    'product_code' => $class_code,
                    'ic_vehicle_details' => json_encode($ic_vehicle_details),
                    'tp_start_date' =>$tp_start_date,
                    'tp_end_date' => $tp_end_date,
                ]);

            updateJourneyStage([
                'user_product_journey_id' => $enquiryId,
                'ic_id' => $productData->company_id,
                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                'proposal_id' => $proposal->user_proposal_id
            ]);

            OrientalPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

            $user_proposal_data = UserProposal::where('user_product_journey_id',$enquiryId)
                ->where('user_proposal_id',$proposal->user_proposal_id)
                ->select('*')
                ->first();

            $proposal_data = $user_proposal_data;

            $proposal_addtional_details = json_decode($proposal->additional_details, true);

            $proposal_addtional_details['oriental']['proposal_policy_sys_id']   = $response['POLICY_SYS_ID'];
            $proposal_addtional_details['oriental']['proposal_no']   = $response['PROPOSAL_NO_OUT'];

            $proposal->additional_details       = $proposal_addtional_details;
            $proposal->save();

            $kyc_token = $clientId = $clientSecret = null;
            $enable_hyperverse = config('ENABLE_HYPERVERGE_FOR_ORIENTAL') == "Y";
            if ($proposal->is_ckyc_verified != 'Y' && $enable_hyperverse) {
                $request_data = [
                    'companyAlias' => 'oriental',
                    'mode' =>  'ckyc',
                    'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    'lastProposalModifiedTime' =>  now()
                ];

                $ckycController = new CkycController;
                $response = $ckycController->ckycVerifications(new  Request($request_data));
                $response = $response->getOriginalContent();
                if(empty($response['data']['meta_data']['accessToken'])){
                    return[
                        'status' => false,
                        'msg' => $response['message'] ?? 'Token Generation Failed...!',
                    ];
                }
                $kyc_token = $response['data']['meta_data']['accessToken'];
                $clientId = $response['data']['meta_data']['clientId'] ?? null;
                $clientSecret = $response['data']['meta_data']['clientSecret'] ?? null;

                $additional_details_data = json_decode($proposal->additional_details_data, true);

                $additional_details_data['access_token'] = $kyc_token;
                $additional_details_data['clientId'] = $clientId;
                $additional_details_data['clientSecret'] = $clientSecret;

                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'additional_details_data'     => json_encode($additional_details_data)
                    ]);
            }

            return response()->json([
                'status' => true,
                'msg' => "Proposal Submitted Successfully!",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal_data->user_proposal_id,
                    'userProductJourneyId' => $proposal_data->user_product_journey_id,
                    'proposalNo' => $proposal_data->proposal_no,
                    'finalPayableAmount' => $proposal_data->final_payable_amount,
                    'is_breakin' => $is_breakin,
                    'inspection_number' => $inspection_id,
                    'verification_status' => $proposal->is_ckyc_verified == 'Y' ? true : false
                    // 'token'=> $kyc_token ?? null,
                    // 'clientId' =>  $clientId,
                    // 'clientSecret' =>  $clientSecret
                ],
                // 'token'=> $kyc_token ?? null
            ]);
        } else {
            $errorMessage = (empty($response['ERROR_CODE'])) ? "Invalid Response From IC" : $response['ERROR_CODE'];

            return [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => $errorMessage,
            ];
        }
    }
}