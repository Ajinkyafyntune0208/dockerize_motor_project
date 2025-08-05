<?php

use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MotorIdv;
use App\Quotes\Cv\hdfc_ergo_short_term;
use App\Quotes\Cv\V1\hdfc_ergo AS HDFC_ERGO_V1;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/hdfc_ergo_miscd.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $is_MISC = policyProductType($productData->policy_id)->parent_id;
    if ($is_MISC == 3) {
        return getMiscQuote($enquiryId, $requestData, $productData);
    }elseif (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_REQUEST_TYPE') == 'JSON') {
        if (in_array($productData->premium_type_code, ['short_term_3', 'short_term_3_breakin', 'short_term_6', 'short_term_6_breakin'])) {
            return hdfc_ergo_short_term::getQuote($enquiryId, $requestData, $productData);
        }
        
        if (config('IC.HDFC_ERGO.V1.CV.ENABLED') == 'Y'){
            return HDFC_ERGO_V1::getQuote($enquiryId, $requestData, $productData);
        }else{
            return getJsonQuote($enquiryId, $requestData, $productData);
        }
    } else {
        return getXmlQuote($enquiryId, $requestData, $productData);
    }
}

function getXmlQuote($enquiryId, $requestData, $productData)
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
    $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
    if ($is_GCV) {
        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo', $requestData->gcv_carrier_type);
    }else{
        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
    }
    if (isset($mmv['status']) && $mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'] ?? 'Something went wrong',
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }
    $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    $mmv_data['manf_name'] = $get_mapping_mmv_details->manufacturer;
    $mmv_data['model_name'] = $get_mapping_mmv_details->vehicle_model;
    $mmv_data['version_name'] = $get_mapping_mmv_details->txt_variant;
    $mmv_data['seating_capacity'] = $get_mapping_mmv_details->seating_capacity;
    $mmv_data['cubic_capacity'] = $get_mapping_mmv_details->cubic_capacity;
    $mmv_data['fuel_type'] = $get_mapping_mmv_details->txt_fuel;

    $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();

    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO code does not exist',
            'request' => [
                'message' => 'RTO code does not exist',
                'rto_code' => $requestData->rto_code
            ]
        ];
    }

    $state_name = MasterState::where('state_id', $rto_data->state_id)->first();

    if (empty($state_name)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'State does not exist with insurance company',
            'request' => [
                'message' => 'State does not exist with insurance company',
                'rto_code' => $requestData->rto_code,
                'state_id' => $rto_data->state_id,
            ]
        ];
    }
   
    $rto_cities = explode('/',  $rto_data->rto_name);
    foreach ($rto_cities as $rto_city) 
    {
        $rto_city = strtoupper($rto_city);
        $rto_location = DB::table('cv_hdfc_ergo_rto_location')
        ->where('Txt_Rto_Location_desc', 'like', '%' . $rto_city . '%')
        ->where('Num_Vehicle_Subclass_Code', '=', $get_mapping_mmv_details->vehicle_subclass_code)
        ->where('Num_Vehicle_Class_code', '=', $get_mapping_mmv_details->vehicle_class_code)
        ->first();
        $rto_location = keysToLower($rto_location);

        if (!empty($rto_location)) 
        {
            break;
        }
    }
    
    if (empty($rto_location)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO details does not exist with insurance company',
            'request' => [
                'message' => 'RTO details does not exist with insurance company',
                'rto_code' => $requestData->rto_code
            ]
        ];
    }

    $current_date = implode('', explode('-', date('Y-m-d')));

    $motor_manf_date = '01-' . $requestData->manufacture_year;

    $car_age = 0;
    $car_age = ((date('Y', strtotime($current_date)) - date('Y', strtotime($motor_manf_date))) * 12) + (date('m', strtotime($current_date)) - date('m', strtotime($motor_manf_date)));
    $car_age = $car_age < 0 ? 0 : $car_age;

    // $motor_depreciation = MotorIdv::whereRaw($car_age . " BETWEEN age_min AND age_max")->first();

    // if (empty($motor_depreciation)) {
    //     return [
    //         'status' => false,
    //         'premium' => 0,
    //         'message' => 'Motor depreciation not found for this vehicle.',
    //         'request' => [
    //             'message' => 'Motor depreciation not found for this vehicle.',
    //             'car_age' => $car_age,
    //             'motor_depreciation' => $motor_depreciation
    //         ]
    //     ];
    // }
    $tp_only = in_array($productData->premium_type_id, [2, 7]);
    $policy_expiry_date = $requestData->previous_policy_expiry_date;
    $is_breakin = false;
    if ($requestData->business_type == 'newbusiness') {
        $businesstype = 'New Business';
        $policy_start_date = date('d-m-Y');
        $IsPreviousClaim = '0';
        $prepolstartdate = '01-01-1900';
        $prepolicyenddate = '01-01-1900';
    } else {
        $businesstype = 'Rollover';
        $policy_start_date = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
        if ($requestData->business_type == 'breakin') {
            $policy_start_date = date('d-m-Y');
            $is_breakin = true;
            if ($tp_only) {
                $is_breakin = false;
                $today = date('d-m-Y');
                $policy_start_date = date('d-m-Y', strtotime($today . ' + 1 days'));
            }
        }
        $IsPreviousClaim = $requestData->is_claim == 'N' ? 1 : 0;
        $prepolstartdate = date('d-m-Y', strtotime($policy_expiry_date . '-1 year +1 day'));
        $prepolicyenddate = $policy_expiry_date;
    }
    $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
    $previous_policy_type = $requestData->previous_policy_type;
    if ($previous_policy_type == 'Third-party' && $businesstype == 'Rollover' && !$tp_only) {
        $is_breakin = true;
    }
    $prev_policy_end_date = $requestData->previous_policy_expiry_date;
    $manufacturingyear = date('Y', strtotime('01-' . $requestData->manufacture_year));
    $first_reg_date = $requestData->vehicle_register_date;
    $vehicle_idv = 0; //(int)$journey_data->showroom_price * (1 - $motor_depreciation->depreciation_rate / 100);
    $vehicle_in_90_days = 0;

    if (isset($term_start_date)) {
        $vehicle_in_90_days = (strtotime(date('Y-m-d')) - strtotime($term_start_date)) / (60 * 60 * 24);

        if ($vehicle_in_90_days > 90) {
            $requestData->ncb_percentage = 0;
        }
    }

    $selected_addons = DB::table('selected_addons')
        ->where('user_product_journey_id', $enquiryId)
        ->first();

    $PAToPaidDriver_SumInsured = '0';

    if (!empty($selected_addons) && $selected_addons->additional_covers != null && $selected_addons->additional_covers != '') {
        $additional_covers = json_decode($selected_addons->additional_covers);
        foreach ($additional_covers as $value) {
            //PCV
            if ($value->name == 'PA cover for additional paid driver') {
                $PAToPaidDriver_SumInsured = $value->sumInsured;
            }
            if ($value->name == 'LL paid driver') {
                $nooflldrivers = '1';
            }

            //GCV
            if ($value->name == 'LL paid driver/conductor/cleaner' && isset($value->selectedLLpaidItmes[0])) {
                if ($value->selectedLLpaidItmes[0] == 'DriverLL' && !empty($value->LLNumberDriver)) {
                    $nooflldrivers = $value->LLNumberDriver;
                }
            }
            if ($value->name == 'PA paid driver/conductor/cleaner') {
                $PAToPaidDriver_SumInsured = $value->sumInsured;
            }
        }
    }

    if ($is_GCV) {
        // $rto_location->Txt_Rto_Location_code = '69227';
        // $rto_location->Txt_Rto_Location_code = '65572';
        // $get_mapping_mmv_details->vehicle_model_code = '24813';
        // $get_mapping_mmv_details->vehicle_class_code = '24';
        // $get_mapping_mmv_details->vehicle_subclass_code = '7';
        // $get_mapping_mmv_details->manufacturer_code = '18';
        // $rto_location->Num_State_Code = '14';
    }
    $model_config_idv = [
        'str',
        [
            'IDV' => [
                'policy_start_date' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                'vehicle_class_cd' => $get_mapping_mmv_details->vehicle_class_code,
                'vehicle_subclass_cd' => $get_mapping_mmv_details->vehicle_subclass_code,
                'RTOLocationCode' => $rto_location->txt_rto_location_code,
                'vehiclemodelcode' => $get_mapping_mmv_details->vehicle_model_code,
                'manufacturer_code' => $get_mapping_mmv_details->manufacturer_code,
                'purchaseregndate' => Carbon::createFromFormat('d-m-Y', $first_reg_date)->format('d/m/Y'),
                'manufacturingyear' => $manufacturingyear,
                'prev_policy_end_date' => (strtoupper($prev_policy_end_date) == 'NEW') || empty($prev_policy_end_date) ? '' : Carbon::createFromFormat('d-m-Y', $prev_policy_end_date)->format('d/m/Y'),
                'typeofbusiness' => $businesstype,
            ],
        ],
    ];

    $get_response = getWsData(
        config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_IDV_URL'),
        $model_config_idv,
        'hdfc_ergo',
        [
            'root_tag' => 'IDV',
            'section' => $productData->product_sub_type_code,
            'method' => 'IDV Calculation',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_sub_type_name,
            'transaction_type' => 'quote',
        ]
    );
    $idv_data = $get_response['response'];
    $skip_second_call = false;
    if (preg_match('/Service Unavailable/i', $idv_data) || preg_match('/Could not allocate space for object/i', $idv_data)) {
        return camelCase(
            [
                'status' => false,
                'msg' => 'Insurer not reachable',
            ]
        );
    }
    if ($idv_data) {
        $idv_data = html_entity_decode($idv_data);
        $idv_data = XmlToArray::convert($idv_data);
        $idv_data = $idv_data['IDV'];

        $idv = $idv_data['IDV_AMOUNT_VEH'];
        $idv_min = $idv_data['IDV_AMOUNT_VEH_MIN'];
        $idv_max = $idv_data['IDV_AMOUNT_VEH_MAX'];


        if ($requestData->is_idv_changed == 'Y') {
            if ($requestData->edit_idv >= $idv_data['IDV_AMOUNT_VEH_MAX']) {
                $idv_amount = $idv_data['IDV_AMOUNT_VEH_MAX'];
            } elseif ($requestData->edit_idv <= $idv_data['IDV_AMOUNT_VEH_MIN']) {
                $idv_amount = $idv_data['IDV_AMOUNT_VEH_MIN'];
            } else {
                $idv_amount = $requestData->edit_idv;
            }
        } else {
            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $idv_data['IDV'] = $idv;
                    $skip_second_call = true;
                    $idv_amount = $idv;
                    break;
                case 'min_idv':
                    $idv_data['IDV_AMOUNT_VEH_MIN'] = $idv_min;
                    $idv_amount =  $idv_min;
                    break;
                case 'max_idv':
                    $idv_data['IDV_AMOUNT_VEH_MAX'] = $idv_max;
                    $idv_amount =  $idv_max;
                    break;
                default:
                $idv_data['IDV_AMOUNT_VEH_MIN'] = $idv_min;
                    $idv_amount =  $idv_min;
                    break;
            }
            // $idv_amount = $idv_data['IDV_AMOUNT_VEH_MIN'];
        }

        if ($productData->premium_type_id == 1 || $productData->premium_type_id == 4) {

            $productcode = $is_GCV ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMP_GCV_PRODUCT_CODE') : config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMP_PCV_PRODUCT_CODE');
            $model_config_premium = [
                'VehicleClassCode=' . $get_mapping_mmv_details->vehicle_class_code . '&str',
                [
                    'CommercialVehicleInput' => [
                        'agentcode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE'),
                        'productcode' => $productcode,
                        'typeofbusiness' => $businesstype,
                        'policystartdate' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                        'policyenddate' => Carbon::createFromFormat('d-m-Y', $policy_end_date)->format('d/m/Y'),
                        'rtolocationcode' => $rto_location->txt_rto_location_code,
                        'vehiclemodelcode' => $get_mapping_mmv_details->vehicle_model_code,
                        'vehicleclasscode' => $get_mapping_mmv_details->vehicle_class_code,
                        'vehiclesubclasscode' => $get_mapping_mmv_details->vehicle_subclass_code,
                        'manufacturer_code' => $get_mapping_mmv_details->manufacturer_code,
                        'purchaseregndate' => Carbon::createFromFormat('d-m-Y', $first_reg_date)->format('d/m/Y'),
                        'IdvAmount' => $idv_amount,
                        'InclusionIMT23' => $productData->product_sub_type_code == 'AUTO-RICKSHAW' ? 1 : 0, //Calculating IMT23 value based on Basic OD
                        'nooflldrivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                        'paiddriversi' => $PAToPaidDriver_SumInsured,
                        'noofemployees' => '0',
                        'IsPreviousClaim' => $previous_policy_type == 'Third-party' ? '0' : $IsPreviousClaim,
                        'previousdiscount' => $previous_policy_type == 'Third-party' ? '0' : $requestData->previous_ncb,
                        'prepolstartdate' => ($businesstype == 'New Business' || $prev_policy_end_date == 'New') ? '' : Carbon::createFromFormat('d-m-Y', $prepolstartdate)->format('d/m/Y'),
                        'prepolicyenddate' => ($businesstype == 'New Business' || $prev_policy_end_date == 'New') ? '' : Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d/m/Y'),
                        'IsZeroDepth' => $productData->zero_dep == 0 ? '1' : '0',# need zd addon age
                        //'num_is_zero_dept' => '1', // Not getting premium using this tag
                        'electicalacc' => (($requestData->electrical_acessories_value != '') ? $requestData->electrical_acessories_value : '0'),
                        'nonelecticalacc' => (($requestData->nonelectrical_acessories_value != '') ? $requestData->nonelectrical_acessories_value : '0'),
                        'lpg_cngkit' => (($requestData->bifuel_kit_value != '') ? $requestData->bifuel_kit_value : '0'),
                        'txt_cust_type' => $requestData->vehicle_owner_type == 'I' ? 'I' : 'C',
                        'is_pa_cover_owner_driver' => $requestData->vehicle_owner_type == 'I' ? 1 : 0,
                        'NoOfFPP' => '0',
                        'NoOfNFPP' => '0',
                        'Cust_StateCode' => $rto_location->num_state_code,
                        'CustomerInspection' => '0',
                        'limitedownpremises' => '0',
                        'privateuse' => $requestData->gcv_carrier_type == 'PUBLIC' ? "0" : "1",
                        'IS_PVT_CARRIER' => $requestData->gcv_carrier_type == 'PUBLIC' ? "0" : "1",
                        'Bustype' => '',
                        'TPPD' => 'Y', // TPPD not available in comprehensive (available in only TP package) - 07-10-2021 - @Amit Gupta
                    ],
                ],
            ];
            if ($productData->premium_type_id == 4) {
                $model_config_premium[1]['CommercialVehicleInput']['manufacturingyear'] = date('Y', strtotime('01-' . $requestData->manufacture_year));
                $model_config_premium[1]['CommercialVehicleInput']['page_calling'] = '1';
                $model_config_premium[1]['CommercialVehicleInput']['num_is_rti'] = '0';
            }

            if(!$skip_second_call) {
                $get_response= getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_PREMIUM_URL'),
                    $model_config_premium, 'hdfc_ergo',
                    [
                        'root_tag' => 'PCVPremiumCalc',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Premium Calculation'. ($productData->zero_dep == 0 ? ' - Zero Dept.' : ''),
                        'requestMethod' => 'post',
                        'enquiryId' => $enquiryId,
                        'productName' => $productData->product_sub_type_name,
                        'transaction_type' => 'quote',
                    ]
                );
            }
            //
            $premium_data = $get_response['response'];
            if (preg_match('/Service Unavailable/i', $premium_data) || preg_match('/Could not allocate space for object/i', $premium_data)) {
                return camelCase(
                    [
                        'status' => false,
                        'webservice_id '=> $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Insurer not reachable',
                    ]
                );
            }
            if ($premium_data) {
                $premium_data = html_entity_decode($premium_data);
                $premium_data = XmlToArray::convert($premium_data);

                if (isset($premium_data['TXT_ERR_MSG']) && !empty($premium_data['TXT_ERR_MSG'])) {
                    return [
                        'status' => false,
                        'message' => $premium_data['TXT_ERR_MSG'],
                    ];
                } else if (!isset($premium_data['PREMIUMOUTPUT'])) {
                    return [
                        'status' => false,
                        'message' => 'Unable to parse the response. Please try again.',
                    ];
                } else if (isset($premium_data['PREMIUMOUTPUT']['PARENT'])) {
                    $premium_data = $premium_data['PREMIUMOUTPUT']['PARENT'][0];
                } else {
                    $premium_data = $premium_data['PREMIUMOUTPUT'];
                }

                $pa_paid_driver = $premium_data['NUM_PA_PAID_DRVR_PREM'];
                $liabilities = $premium_data['NUM_LL_PAID_DRIVER'];
                $tppd_discount = isset($premium_data['NUM_TPPD_AMT']) ? round($premium_data['NUM_TPPD_AMT']) : 0;
                $motor_electric_accessories_value = $premium_data['NUM_ELEC_ACC_PREM'];
                $motor_non_electric_accessories_value = $premium_data['NUM_NON_ELEC_ACC_PREM'];
                $final_tp_premium = $premium_data['NUM_TP_RATE'] + $liabilities + $premium_data['NUM_LPG_CNGKIT_TP_PREM'] + $pa_paid_driver;

                $total_own_damage = $premium_data['NUM_BASIC_OD_PREMIUM'];
                $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;

                $imt_23 = 0;
                $own_premises_od = 0;
                $own_premises_tp = 0; 
                //(int) $premium_data['NUM_INCLUSION_IMT23_AMT_OD'];
                if ($is_GCV) {
                    $basic_imt_23 = (int) $total_own_damage * (0.15);
                    $imt_23_electrical = (int) $requestData->electrical_acessories_value * (0.6 / 100); // 0.6%
                    $imt_23_non_elec = (int) $requestData->nonelectrical_acessories_value * (0.26 / 100); // 0.26%
                    $imt_23_bifuel_kit = (int) $requestData->bifuel_kit_value * (0.6 / 100); // 0.6%
                    $imt_23 = round($basic_imt_23 + $imt_23_electrical + $imt_23_non_elec + $imt_23_bifuel_kit);
                    $final_od_premium = $total_own_damage + $total_accessories_amount + $premium_data['NUM_LPG_CNGKIT_OD_PREM'];
                    $final_total_discount = round($final_od_premium * $requestData->applicable_ncb / 100);
                    $deduction_of_ncb = round($final_od_premium * $requestData->applicable_ncb / 100);
                } else {
                    if ($productData->product_sub_type_code == 'AUTO-RICKSHAW')
                    {
                        $basic_imt_23 = (int) $premium_data['NUM_INCLUSION_IMT23_AMT_OD'] ?? 0;
                        $imt_23_electrical = (int) $premium_data['NUM_INCLUS_IMT23_AMT_ELEC'] ?? 0; // 0.6%
                        $imt_23_non_elec = (int) $premium_data['NUM_INCLUS_IMT23_AMT_NELEC'] ?? 0; // 0.26%
                        $imt_23_bifuel_kit = (int) $premium_data['NUM_INCLUSION_IMT23_AMT_CNG'] ?? 0; // 0.6%
                        $imt_23 = round($basic_imt_23 + $imt_23_electrical + $imt_23_non_elec + $imt_23_bifuel_kit);
                    }

                    $final_od_premium = $total_own_damage + $total_accessories_amount + $premium_data['NUM_LPG_CNGKIT_OD_PREM'];
                    $deduction_of_ncb = round($final_od_premium * $requestData->applicable_ncb / 100);
                    $final_total_discount = $deduction_of_ncb;
                }

                $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);

                //Tax calculate
                if ($is_GCV) {
                    //GCV
                    $final_gst_amount = ($premium_data['NUM_TP_RATE'] * 0.12) + ($final_net_premium - $premium_data['NUM_TP_RATE']) * 0.18;
                } else {
                    //PCV
                    $final_gst_amount = round($final_net_premium * 0.18);
                }
                $final_payable_amount = $final_net_premium + $final_gst_amount;

                if($productData->zero_dep == 0) {
                    if ((int)$premium_data['NUM_ZERO_DEPT_PREM'] == 0) {
                        return camelCase(
                            [
                                'status' => false,
                                'msg' => 'Zero Dept. value is 0',
                                'zero_depreciation' => (int)$premium_data['NUM_ZERO_DEPT_PREM']
                            ]
                        );
                    }
                    $applicable_addons =  [
                        'in_built' => [
                            'zero_depreciation' => (int)$premium_data['NUM_ZERO_DEPT_PREM'],
                        ],
                        'additional' => [
                            'imt23' => $imt_23,
                        ],
                    ];
                    
                }else {
                    $applicable_addons =  [
                        'in_built' => [
                        ],
                        'additional' => [
                            'imt23' => $imt_23,
                            'zero_depreciation' => (int)$premium_data['NUM_ZERO_DEPT_PREM'],
                        ],
                    ];
                }
                
                $applicable_addons['additional_premium'] = array_sum($applicable_addons['additional']);
                $applicable_addons['in_built_premium'] = array_sum($applicable_addons['in_built']);
                $data_response = [
                    'status' => 200,
                    'msg' => 'Found',
                    'Data' => [
                        'idv' => $idv_amount,
                        'min_idv' => $idv_data['IDV_AMOUNT_VEH_MIN'],
                        'max_idv' => $idv_data['IDV_AMOUNT_VEH_MAX'],
                        'exshowroomprice' => $idv_data['EXSHOWROOMPRICE_VEH'],
                        'qdata' => null,
                        'tppd_discount' => $tppd_discount,
                        'pp_enddate' => $prev_policy_end_date,
                        'addonCover' => null,
                        'addon_cover_data_get' => '',
                        'rto_decline' => null,
                        'rto_decline_number' => null,
                        'mmv_decline' => null,
                        'mmv_decline_name' => null,
                        'policy_type' => 'Comprehensive',
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'voluntary_excess' => 0,
                        'version_id' => $get_mapping_mmv_details->version_id,
                        'selected_addon' => [],
                        'showroom_price' => 0,
                        'fuel_type' => $requestData->fuel_type,
                        'vehicle_idv' => 0,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
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
                            'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'predefine_series' => "",
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
                            'car_age' => $car_age,
                            'ic_vehicle_discount' => '',
                        ],
                        'basic_premium' => $premium_data['NUM_BASIC_OD_PREMIUM'],
                        'deduction_of_ncb' => $deduction_of_ncb,
                        'tppd_premium_amount' => $premium_data['NUM_TP_RATE'],
                        'motor_electric_accessories_value' => (int) $premium_data['NUM_ELEC_ACC_PREM'],
                        'motor_non_electric_accessories_value' => (int) $premium_data['NUM_NON_ELEC_ACC_PREM'],
                        /* 'motor_lpg_cng_kit_value' => (int) $premium_data['NUM_LPG_CNGKIT_OD_PREM'], */
                        'cover_unnamed_passenger_value' => 0,
                        'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                        'default_paid_driver' => (float) $liabilities,
                        'll_paid_driver_premium' => (float) $liabilities,
                        'll_paid_conductor_premium' => 0,
                        'll_paid_cleaner_premium' => 0,
                        'motor_additional_paid_driver' => $pa_paid_driver,
                        'compulsory_pa_own_driver' => (float) $premium_data['NUM_PA_COVER_OWNER_DRVR'],
                        'total_accessories_amount(net_od_premium)' => $premium_data['NUM_BASIC_OD_PREMIUM'],
                        'total_own_damage' => '',
                        /* 'cng_lpg_tp' => $premium_data['NUM_LPG_CNGKIT_TP_PREM'], */
                        'total_liability_premium' => '',
                        'net_premium' => $premium_data['NUM_TOTAL_PREMIUM'],
                        'service_tax_amount' => $premium_data['NUM_SERVICE_TAX'],
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => '',
                        'quotation_no' => '',
                        'premium_amount' => (int) $premium_data['NUM_TOTAL_PREMIUM'],
                        'antitheft_discount' => 0,
                        'final_od_premium' => $final_od_premium,
                        'final_tp_premium' => $final_tp_premium,
                        'final_total_discount' => $final_total_discount,
                        'final_net_premium' => $final_net_premium,
                        'final_gst_amount' => round($final_gst_amount),
                        'final_payable_amount' => round($final_payable_amount),
                        'service_data_responseerr_msg' => '',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => $is_breakin ? 'Break-in' : $businesstype,
                        'policyStartDate' => $is_breakin ? '' : $policy_start_date,
                        'policyEndDate' => $policy_end_date,
                        'ic_of' => $productData->company_id,
                        'vehicle_in_90_days' => $vehicle_in_90_days,
                        'get_policy_expiry_date' => null,
                        'get_changed_discount_quoteid' => 0,
                        'GeogExtension_ODPremium' => 0,
                        'GeogExtension_TPPremium' => 0,
                        'LimitedtoOwnPremises_OD' => round($own_premises_od),
                        'LimitedtoOwnPremises_TP' => round($own_premises_tp),
                        'vehicle_discount_detail' => [
                            'discount_id' => null,
                            'discount_rate' => null,
                        ],
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                        'policy_id' => $productData->policy_id,
                        'insurane_company_id' => $productData->company_id,
                        "max_addons_selection" => null,
                        'add_ons_data' => $applicable_addons,
                        'applicable_addons' => ['zeroDepreciation','imt23'],
                         /*[
                            'in_built' => [],
                            'additional' => [
                                'zero_depreciation' => (int)$premium_data['NUM_ZERO_DEPT_PREM'],
                                'road_side_assistance' => 0,
                                'imt23' => $imt_23,
                                'imt34' => 0,
                                'geographicExtension' => 0,
                            ],
                            'additional_premium' => array_sum([$premium_data['NUM_ZERO_DEPT_PREM'], $imt_23]),
                        ],
                        'applicable_addons' =>[], #$is_GCV ? ['zeroDepreciation'] : [], */
                    ],
                ];

                if ($imt_23 > 0)
                {
                    array_push($data_response['Data']['applicable_addons'], 'imt23');
                }
                if((int)$premium_data['NUM_ZERO_DEPT_PREM'] > 0 && $is_GCV)
                {
                    array_push($data_response['Data']['applicable_addons'], 'zeroDepreciation');
                }
                if(!empty($requestData->bifuel_kit_value))
                {
                    $data_response['Data']['motor_lpg_cng_kit_value'] = (int) $premium_data['NUM_LPG_CNGKIT_OD_PREM'];
                    $data_response['Data']['cng_lpg_tp'] = $premium_data['NUM_LPG_CNGKIT_TP_PREM'];
                }
            } else {
                $data_response = array(
                    'status' => false,
                    'msg' => 'Insurer not reachable',
                );
            }
        } else if ($tp_only) {
            $selected_addons = DB::table('selected_addons')
                ->where('user_product_journey_id', $enquiryId)
                ->first();

            $PAToPaidDriver_SumInsured = '0';
            $bifuel = $TPPD = 'N';
            if (!empty($selected_addons) && $selected_addons->additional_covers != '') {
                $additional_covers = json_decode($selected_addons->additional_covers);
                foreach ($additional_covers as $value) {
                    if ($value->name == 'PA cover for additional paid driver') {
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                    }

                    //GCV
                    if ($value->name == 'LL paid driver/conductor/cleaner' && isset($value->selectedLLpaidItmes[0])) {
                        if ($value->selectedLLpaidItmes[0] == 'DriverLL' && !empty($value->LLNumberDriver)) {
                            $nooflldrivers = $value->LLNumberDriver;
                        }
                    }
                    if ($value->name == 'PA paid driver/conductor/cleaner') {
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                    }
                }
            }

            if (!empty($selected_addons) && $selected_addons->accessories != '') {
                $accessories = json_decode($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if ($value->name == 'External Bi-Fuel Kit CNG/LPG') {
                        $bifuel = 'Y';
                    }
                }
            }
            if (!empty($selected_addons) && $selected_addons->discounts != '') {
                $discounts = json_decode($selected_addons->discounts);
                foreach ($discounts as $v) {
                    if ($v->name == 'TPPD Cover') {
                        $TPPD = 'Y';
                    }
                }
            }
            $productcode = $is_GCV ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_GCV_PRODUCT_CODE') : config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_PCV_PRODUCT_CODE');
            $model_config_premium = [
                'VehicleClassCode=' . $get_mapping_mmv_details->vehicle_class_code . '&str',
                [
                    'TPPremium' => [
                        'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_AGENT_CODE'),
                        //'ProductCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_PRODUCT_CODE'),
                        'ProductCode' => $productcode,
                        'vehicleModelCode' => $get_mapping_mmv_details->vehicle_model_code,
                        'Num_Of_Paid_Driver' => 0,
                        'Num_Of_SI_Paid_Driver' => $PAToPaidDriver_SumInsured,
                        'Num_Of_UnNamedPasgr' => 0,
                        'Num_Of_SI_UnNamedPasgr' => 0,
                        'Num_Of_NamedPasgr' => 0,
                        'Num_Of_SI_NamedPasgr' => 0,
                        'IB_LPG_CNGKit' => 'N',
                        'EX_LPG_CNGKit' => $bifuel,
                        'Num_Of_LL_Drivers' => isset($nooflldrivers) ? $nooflldrivers : 0,
                        'Num_Of_LL_Employee' => 0,
                        'Num_Of_Trailers' => 0,
                        'TPPD' => $TPPD,
                        'CustomerType' => $requestData->vehicle_owner_type == 'I' ? 'I' : 'O',
                        'UnNamed_Pillion' => 'N',
                        'SI_Unnamed_Pillion' => 0,
                        'Num_Eff_Driving_Lic' => $requestData->vehicle_owner_type == 'I' ? 'Y' : 'N',
                        'Distance_To_From' => 0,
                        'RTO_Location_Code' => $rto_location->txt_rto_location_code,
                        'Policy_Effective_From_Date' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                        'IS_PVT_CARRIER' => $requestData->gcv_carrier_type == 'PUBLIC' ? "0" : "1",
                    ],
                ],
            ];

            $get_response = getWsData(
                config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_PREMIUM_URL'),
                $model_config_premium, 'hdfc_ergo',
                [
                    'root_tag' => 'PCVPremiumCalc',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'TP Premium Calculation',
                    'requestMethod' => 'post',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_sub_type_name,
                    'transaction_type' => 'quote',
                ]
            );
            //
            $premium_data = $get_response['response'];

            if (preg_match('/Service Unavailable/i', $premium_data) || preg_match('/Could not allocate space for object/i', $premium_data)) {
                return camelCase(
                    [
                        'status' => false,
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'msg' => 'Insurer not reachable',
                    ]
                );
            }
            if ($premium_data) {
                $premium_data = html_entity_decode($premium_data);
                $premium_data = XmlToArray::convert($premium_data);

                if (!isset($premium_data['PREMIUMOUTPUT'])) {
                    return [
                        'status' => false,
                        'message' => 'Unable to parse the response. Please try again.',
                    ];
                }
                $premium_data = $premium_data['PREMIUMOUTPUT'];

                $lpg_cng_kit = 0;

                if (isset($premium_data['NUM_IB_LPG_CNGKIT']) && (int) $premium_data['NUM_IB_LPG_CNGKIT'] > 0)
                {
                    $lpg_cng_kit = (int) $premium_data['NUM_IB_LPG_CNGKIT'];
                }
                elseif (isset($premium_data['NUM_EX_LPG_CNGKIT']) && (int) $premium_data['NUM_EX_LPG_CNGKIT'] > 0)
                {
                    $lpg_cng_kit = (int) $premium_data['NUM_EX_LPG_CNGKIT'];
                }

                if (isset($premium_data['TXT_ERROR_MSG']) && !empty($premium_data['TXT_ERROR_MSG'])) {
                    return [
                        'status' => false,
                        'premium' => 0,
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'message' => $premium_data['TXT_ERROR_MSG'],
                    ];
                }

                $own_premises_od = 0;
                $own_premises_tp = 0; 
                $pa_paid_driver = $premium_data['NUM_PA_PAID_DRIVER'] ?? 0;
                $liabilities = $premium_data['NUM_NOOFLLDRIVERS'];
                $tppd_discount = round($premium_data['NUM_TPPD_AMT']) ?? 0;
                $final_tp_premium = $premium_data['NUM_TP_PREMIUM'] + $liabilities + $lpg_cng_kit + $pa_paid_driver;
                $final_total_discount = $tppd_discount;

                $final_od_premium = 0;
                $own_premises_od = $own_premises_tp = 0;
                $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
                $final_gst_amount = round($final_net_premium * 0.18);
                $final_payable_amount = $final_net_premium + $final_gst_amount;

                $data_response = [
                    'status' => 200,
                    'msg' => 'Found',
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'Data' => [
                        'idv' => 0,
                        'min_idv' => 0,
                        'max_idv' => 0,
                        'exshowroomprice' => $idv_data['EXSHOWROOMPRICE_VEH'],
                        'qdata' => null,
                        'tppd_discount' => $tppd_discount,
                        'pp_enddate' => $prev_policy_end_date,
                        'addonCover' => null,
                        'addon_cover_data_get' => '',
                        'rto_decline' => null,
                        'rto_decline_number' => null,
                        'mmv_decline' => null,
                        'mmv_decline_name' => null,
                        'policy_type' => 'Third Party',
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'voluntary_excess' => 0,
                        'version_id' => $get_mapping_mmv_details->version_id,
                        'selected_addon' => [],
                        'showroom_price' => 0,
                        'fuel_type' => $requestData->fuel_type,
                        'vehicle_idv' => 0,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
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
                            'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'predefine_series' => "",
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
                            'car_age' => $car_age,
                            'ic_vehicle_discount' => '',
                        ],
                        'basic_premium' => 0,
                        'deduction_of_ncb' => 0,
                        'tppd_premium_amount' => $premium_data['NUM_TP_PREMIUM'],
                        'motor_electric_accessories_value' => 0,
                        'motor_non_electric_accessories_value' => 0,
                        /* 'motor_lpg_cng_kit_value' => 0, */
                        'cover_unnamed_passenger_value' => 0,
                        'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                        'default_paid_driver' => $liabilities,
                        'll_paid_driver_premium' => $liabilities,
                        'll_paid_conductor_premium' => 0,
                        'll_paid_cleaner_premium' => 0,
                        'motor_additional_paid_driver' => $pa_paid_driver,
                        'compulsory_pa_own_driver' => (float) $premium_data['NUM_PA_OWNER_DRIVER'],
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' => '',
                        /* 'cng_lpg_tp' => $lpg_cng_kit, */
                        'total_liability_premium' => '',
                        'net_premium' => $premium_data['NUM_NET_PREMIUM'],
                        'service_tax_amount' => $premium_data['NUM_SERVICE_TAX'],
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => '',
                        'quotation_no' => '',
                        'premium_amount' => $premium_data['NUM_TOTAL_PREMIUM'],
                        'antitheft_discount' => 0,
                        'final_od_premium' => 0,
                        'final_tp_premium' => $final_tp_premium,
                        'final_total_discount' => $final_total_discount,
                        'final_net_premium' => $final_net_premium,
                        'final_gst_amount' => round($final_gst_amount),
                        'final_payable_amount' => round($final_payable_amount),
                        'service_data_responseerr_msg' => '',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => ($productData->premium_type_id == 7) ? 'Break-in' : (($is_breakin) ? 'Break-in' : $businesstype),
                        'policyStartDate' => $is_breakin ? '' : $policy_start_date,
                        'policyEndDate' => $policy_end_date,
                        'ic_of' => $productData->company_id,
                        'vehicle_in_90_days' => $vehicle_in_90_days,
                        'get_policy_expiry_date' => null,
                        'get_changed_discount_quoteid' => 0,
                        'GeogExtension_ODPremium' => 0,
                        'GeogExtension_TPPremium' => 0,
                        'LimitedtoOwnPremises_OD' => round($own_premises_od),
                        'LimitedtoOwnPremises_TP' => round($own_premises_tp),
                        'vehicle_discount_detail' => [
                            'discount_id' => null,
                            'discount_rate' => null,
                        ],
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                        'policy_id' => $productData->policy_id,
                        'insurane_company_id' => $productData->company_id,
                        "max_addons_selection" => null,
                        'add_ons_data' => [
                            'in_built' => [],
                            'additional' => [
                                'zero_depreciation' => 0,
                                'road_side_assistance' => 0,
                                'imt23' => 0,
                                'imt34' => 0,
                                'geographicExtension' => 0,
                            ],
                        ],
                        'applicable_addons'=>[],
                    ],
                ];
                if($bifuel == 'Y')
                {
                    $data_response['Data']['motor_lpg_cng_kit_value'] = 0;
                    $data_response['Data']['cng_lpg_tp'] = $lpg_cng_kit;
                }
            } else {
                $data_response = array(
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'msg' => 'Insurer not reachable',
                );
            }
        }
    } else {
        $data_response = array(
            'status' => false,
            'webservice_id'=>$get_response['webservice_id'],
            'table'=>$get_response['table'],
            'msg' => 'Insurer not reachable',
        );
    }
    return camelCase($data_response);
}

function getJsonQuote($enquiryId, $requestData, $productData)
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
    if (empty($requestData->rto_code)) {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO not available',
            'request' => [
                'message' => 'RTO not available',
                'rto_code' => $requestData->rto_code
            ]
        ]; 
    }

    $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');

    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ],
        ];          
    }

    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];        
    } elseif ($mmv->ic_version_code == 'DNE') {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];        
    }

    $parent_id = get_parent_code($productData->product_sub_type_id);

    $premium_type = DB::table('master_premium_type')
        ->where('id',$productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    // $mmv_data = [
    //     'manf_name' => $mmv->manufacturer,
    //     'model_name' => $mmv->vehicle_model,
    //     'version_name' => $mmv->txt_variant,
    //     'seating_capacity' => $mmv->seating_capacity,
    //     'carrying_capacity' => $mmv->carrying_capacity,
    //     'cubic_capacity' => $mmv->cubic_capacity,
    //     'fuel_type' =>  $mmv->txt_fuel,
    //     'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
    //     'vehicle_type' => $parent_id,
    //     'version_id' => $mmv->version_id,
    // ];

    $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();

    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO code does not exist',
            'request' => [
                'message' => 'RTO code does not exist',
                'rto_code' => $requestData->rto_code
            ]
        ];
    }

    $vehicle_class_code = [
        'TAXI' => [
            'vehicle_class_code' => 41,
            'vehicle_sub_class_code' => 1
        ],
        'AUTO-RICKSHAW' => [
            'vehicle_class_code' => 41,
            'vehicle_sub_class_code' => 5
        ],
        'ELECTRIC-RICKSHAW' => [
            'vehicle_class_code' => 41,
            'vehicle_sub_class_code' => 5
        ],
        'PICK UP/DELIVERY/REFRIGERATED VAN' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 2
        ],
        'DUMPER/TIPPER' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 3
        ],
        'TRUCK' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 7
        ],
        'TRACTOR' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 5
        ],
        'TANKER/BULKER' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 4#6
        ]
    ];

    // $rto_location = DB::table('hdfc_ergo_rto_master')
    //     ->where('txt_rto_location_desc', $rto_data->rto_name)
    //     ->where('num_vehicle_class_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_class_code'])
    //     ->where('num_vehicle_subclass_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_sub_class_code'])
    //     ->first();

    $rto_cities = explode('/',  $rto_data->rto_name);
    foreach ($rto_cities as $rto_city) 
    {
        $rto_city = strtoupper($rto_city);
        $rto_location = DB::table('hdfc_ergo_rto_master')
            ->where('txt_rto_location_desc', 'like', '%' . $rto_city . '%')
            ->where('num_vehicle_class_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_class_code'])
            ->where('num_vehicle_subclass_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_sub_class_code'])
            ->first();
        $rto_location = keysToLower($rto_location);
        if (!empty($rto_location)) 
        {
            break;
        }
    }

    if (empty($rto_location)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO details does not exist with insurance company',
            'request' => [ 
                'rto_code' => $requestData->rto_code,
                'message' => 'RTO details does not exist with insurance company',
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    if ($parent_id == 'PCV') {
        $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2313 : 2314;
    } elseif ($parent_id == 'GCV') {
        $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2317 : 2315;
    }
    else
    {
        $product_code = 2316;
    }

    $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
    $motor_manf_year = $motor_manf_year_arr[1];
    $motor_manf_date = '01-'.$requestData->manufacture_year;
    $current_date = Carbon::now()->format('Y-m-d');

    $car_age = 0;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    if ($interval->y >= 15)
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes are not available for vehicles having age greater than or equal to 15 years.'
        ];
    }

    // if (
    //     $productData->zero_dep == 0 &&
    //     in_array($parent_id, ['PCV', 'GCV']) &&
    //     $interval->y >= 5
    // ) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero depreciation is not available for vehicles having age more than 5 years'
    //     ];
    // }

    if ($productData->product_identifier == 'RSA' && $interval->y >= 15) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Road side assistance is not available for vehicles having age more than 15 years'
        ];
    }

    $isRsa = $productData->product_identifier == 'RSA' && in_array($parent_id, ['PCV']);

    // if ($productData->zero_dep == 0 && $interval->y >= 3)
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero depreciation is not available for vehicles having age more than 3 years'
    //     ];
    // }

    if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
        $is_vehicle_new = 'false';
        $policy_start_date = date('Y-m-d', strtotime('+1 day', ! is_null($requestData->previous_policy_expiry_date) && $requestData->previous_policy_expiry_date != 'New' ? strtotime($requestData->previous_policy_expiry_date) : time()));

        if ($requestData->business_type == 'breakin' && $tp_only) {
            $today = date('Y-m-d');
            $policy_start_date = date('Y-m-d', strtotime($today . ' + 1 days'));
        }

        if ($requestData->vehicle_registration_no != '') {
            $registration_number = getRegisterNumberWithHyphen($requestData->vehicle_registration_no);
        } else {
            $reg_no_3 = chr(rand(65, 90));
            $registration_number = $requestData->rto_code.'-'.$reg_no_3.$reg_no_3.'-1234';
        }
    } elseif ($requestData->business_type == 'newbusiness')  {
        $requestData->vehicle_register_date = date('d-m-Y');
        $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
        $registration_number = 'NEW';

        if ($date_difference > 0) {  
            return [
                'status' => false,
                'message' => 'Please Select Current Date for New Business',
                'request' => [
                    'message' => 'Please Select Current Date for New Business',
                    'business_type' => $requestData->business_type,
                    'vehicle_register_date' => $requestData->vehicle_register_date
                ]
            ];
        }

        $is_vehicle_new = 'true';
        $policy_start_date = Carbon::today()->format('Y-m-d');
        $previousNoClaimBonus = 'ZERO';

        if ($requestData->vehicle_registration_no == 'NEW') {
            $vehicle_registration_no  = str_replace("-", "", $requestData->rto_code) . "-NEW";
        } else {
            $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
        }
    }

    $transaction_id = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);

    if ($tp_only)
    {
        $cache_name = 'HDFC_ERGO_CV_JSON_TP_' . $product_code;
    }
    elseif ($productData->zero_dep == 0)
    {
        $cache_name = 'HDFC_ERGO_CV_JSON_ZERO_DEP_' . $product_code;
    }
    else
    {
        $cache_name = 'HDFC_ERGO_CV_JSON_BASIC_' . $product_code;
    }

    $get_response = getWsData(
        config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_AUTHENTICATE_URL'),
        [],
        'hdfc_ergo',
        [
            'section' => $productData->product_sub_type_code,
            'method' => 'Token Generation',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'quote',
            'product_code' => $product_code,
            'transaction_id' => $transaction_id,
            'headers' => [
                'Content-type' => 'application/json',
                'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                'PRODUCT_CODE' => $product_code,
                'TransactionID' => $transaction_id,
                'Accept' => 'application/json',
                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CREDENTIAL')
            ]
        ]
    );

    $token_data = $get_response['response'];
    if ($token_data) {
        $token_data = json_decode($token_data, TRUE);

        if (isset($token_data['StatusCode']) && $token_data['StatusCode'] == 200) {
            $idv_calculation_array = [
                'TransactionID' => $transaction_id,
                'Customer_Details' => [],
                'Policy_Details' => [],
                'Req_GCV' => [],
                'Req_MISD' => [],
                'Req_PCV' => [],
                'Payment_Details' => [],
                'IDV_DETAILS' => [
                    'ModelCode' => $mmv->vehicle_model_code,
                    'RTOCode' => $rto_location->txt_rto_location_code,
                    'Vehicle_Registration_Date' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'Policy_Start_Date' => date('d/m/Y', strtotime($policy_start_date))
                ]
            ];

            if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CALCULATE_IDV_URL'), $idv_calculation_array, 'hdfc_ergo', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'IDV Calculation',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'quote',
                    'requestMethod' => 'post',
                    'product_code' => $product_code,
                    'transaction_id' => $transaction_id,
                    'token' => $token_data['Authentication']['Token'],
                    'headers' => [
                        'Content-type' => 'application/json',
                        'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                        'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                        'PRODUCT_CODE' => $product_code,
                        'TransactionID' => $transaction_id,
                        'Accept' => 'application/json',
                        'Token' => $token_data['Authentication']['Token']
                    ]
                ]);
                $idv_data = $get_response['response'];
                $idv_data = json_decode($idv_data, TRUE);
            }

            $skip_second_call = false;
            if (isset($idv_data) && $idv_data || in_array($premium_type, ['third_party', 'third_party_breakin'])) {

                if (isset($idv_data['StatusCode']) && $idv_data['StatusCode'] == 200 || in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $vehicle_idv = $min_idv = $max_idv = $default_idv = 0;
                    // $min_idv = $idv_data['CalculatedIDV']['MIN_IDV_AMOUNT'];
                    // $max_idv = $idv_data['CalculatedIDV']['MAX_IDV_AMOUNT'];
                    // $vehicle_idv = $idv_data['CalculatedIDV']['IDV_AMOUNT'];

                    if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                        $vehicle_idv = $idv_data['CalculatedIDV']['IDV_AMOUNT'];
                        $min_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $idv_data['CalculatedIDV']['MIN_IDV_AMOUNT'];
                        $max_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $idv_data['CalculatedIDV']['MAX_IDV_AMOUNT'];
                        $default_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $idv_data['CalculatedIDV']['IDV_AMOUNT'];
                    }
                    if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y') {
                        if ($requestData->edit_idv >= $max_idv) {
                            $vehicle_idv = floor($max_idv);
                        } elseif ($requestData->edit_idv <= $min_idv) {
                            $vehicle_idv = floor($min_idv);
                        } else {
                            $vehicle_idv = floor($requestData->edit_idv);
                        }
                    } else {
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $data_idv['CalculatedIDV']['IDV_AMOUNT'] = $default_idv;
                                $skip_second_call = true;
                                $idv =  $default_idv;
                                break;
                            case 'min_idv':
                                $data_idv['CalculatedIDV']['MIN_IDV_AMOUNT'] = $min_idv;
                                $idv =  $min_idv;
                                break;
                            case 'max_idv':
                                $data_idv['CalculatedIDV']['MAX_IDV_AMOUNT'] = $max_idv;
                                $idv =  $max_idv;
                                break;
                            default:
                            $idv = $data_idv['CalculatedIDV']['IDV_AMOUNT'] = $default_idv;
                                $idv =  $min_idv;
                                break;
                        }
                        $vehicle_idv = $min_idv;
                    }
                    $business_types = [
                        'rollover' => 'Roll Over',
                        'newbusiness' => 'New Vehicle',
                        'breakin' => 'Breakin'
                    ];

                    $selected_addons = DB::table('selected_addons')
                        ->where('user_product_journey_id', $enquiryId)
                        ->first();

                    $is_electrical_accessories = NULL;
                    $is_non_electrical_accessories = NULL;
                    $external_kit_type = NULL;
                    $external_kit_value = 0;
                    $electrical_accessories_value = 0;
                    $non_electrical_accessories_value = 0;
                    $pa_paid_driver_sum_insured = 0;
                    $no_of_unnamed_passenger = 0;
                    $unnamed_passenger_sum_insured = 0;
                    $no_of_ll_paid_drivers = 0;
                    $no_of_ll_paid_conductors = 0;
                    $no_of_ll_paid_cleaners = 0;
                    $is_anti_theft = false;
                    $voluntary_excess_value = NULL;
                    $is_tppd_cover = 0;
                    $is_vehicle_limited_to_own_premises = 0;
                    $no_of_ll_paid = 0;

                    if ($selected_addons && !empty($selected_addons)) {
                        if ($selected_addons->accessories != NULL && $selected_addons->accessories != '') {
                            $accessories = json_decode($selected_addons->accessories, TRUE);

                            foreach ($accessories as $accessory) {
                                if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                                    $external_kit_type = 'CNG';
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
                                } elseif ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                                    $no_of_unnamed_passenger = $mmv->seating_capacity;
                                    $unnamed_passenger_sum_insured = $additional_cover['sumInsured'];
                                } elseif ($additional_cover['name'] == 'LL paid driver') {
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

                                    $no_of_ll_paid = $no_of_ll_paid_cleaners + $no_of_ll_paid_drivers + $no_of_ll_paid_conductors;                                    
                                } elseif ($additional_cover['name'] == 'PA paid driver/conductor/cleaner' && isset($additional_cover['sumInsured'])) {
                                    $pa_paid_driver_sum_insured = $additional_cover['sumInsured'];
                                }
                            }
                        }

                        if ($selected_addons->discounts != NULL && $selected_addons->discounts != '') {
                            $discounts = json_decode($selected_addons->discounts, TRUE);

                            foreach ($discounts as $discount) {
                                if ($discount['name'] == 'anti-theft device') {
                                    $is_anti_theft = true;
                                } elseif ($discount['name'] == 'voluntary_insurer_discounts') {
                                    $voluntary_excess_value = $discount['sumInsured'];
                                } elseif ($discount['name'] == 'TPPD Cover') {
                                    $is_tppd_cover = 1;
                                }
                                elseif ($discount['name'] == 'Vehicle Limited to Own Premises' && $parent_id != 'GCV') // #9062 [20-09-2022]
                                {
                                    $is_vehicle_limited_to_own_premises = 1;
                                }
                            }
                        }
                    }

                    $premium_calculation_request = [
                        'TransactionID' => $transaction_id,
                        'Customer_Details' => [
                            'Customer_Type' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Corporate'
                        ],
                        'Policy_Details' => [
                            'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                            'ProposalDate' => date('d/m/Y', time()),
                            'AgreementType' => NULL,
                            'FinancierCode' => NULL,
                            'BranchName' => NULL,
                            'PreviousPolicy_CorporateCustomerId_Mandatary' => NULL,
                            'PreviousPolicy_NCBPercentage' => $requestData->previous_ncb,
                            'PreviousPolicy_PolicyEndDate' => $requestData->business_type == 'newbusiness' ? NULL : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
                            'PreviousPolicy_PolicyNo' => NULL,
                            'PreviousPolicy_PolicyClaim' => $requestData->is_claim == 'N' ? 'NO' : 'YES',
                            'BusinessType_Mandatary' => $business_types[$requestData->business_type],
                            'VehicleModelCode' => $mmv->vehicle_model_code,
                            'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                            'YearOfManufacture' => $motor_manf_year,
                            'Registration_No' => $registration_number,
                            'EngineNumber' => 'dwdwad34343',
                            'ChassisNumber' => 'grgrgrg444',
                            'RTOLocationCode' => $rto_location->txt_rto_location_code,
                            'Vehicle_IDV'=> $vehicle_idv
                        ]
                    ];

                    if ($requestData->prev_short_term == '1') {
                        //if previous policy is short term, then pass pyp start date is for 3 month range
                        $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)
                            ->subMonth(3)
                            ->addDay(1)
                            ->format('Y-m-d');

                        $premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyStartDate'] = date(
                            'd/m/Y',
                            strtotime($prevPolyStartDate)
                        );
                    }

                    if ($requestData->previous_policy_type == 'Not sure' || $premium_type == 'third_party_breakin')
                    {
                        if ($requestData->previous_policy_type == 'Not sure')
                        {
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_NCBPercentage']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyEndDate']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyNo']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyClaim']);
                        }

                        $premium_calculation_request['Policy_Details']['BusinessType_Mandatary'] = 'Roll Over';
                    }

                    // if ($requestData->is_idv_changed == 'Y') {                       	
                    //     if ($requestData->edit_idv >= $max_idv) {
                    //         $premium_calculation_request['Policy_Details']['Vehicle_IDV'] = $max_idv;
                    //     } elseif ($requestData->edit_idv <= $min_idv) {
                    //         $premium_calculation_request['Policy_Details']['Vehicle_IDV'] = $min_idv;
                    //     } else {
                    //         $premium_calculation_request['Policy_Details']['Vehicle_IDV'] = $requestData->edit_idv;
                    //     }
                    // } else {
                    //     $premium_calculation_request['Policy_Details']['Vehicle_IDV'];
                    // }

                    if ($requestData->is_idv_changed == 'Y') {                       	
                        if ($requestData->edit_idv >= $max_idv) {
                            $vehicle_idv = $max_idv;
                        } elseif ($requestData->edit_idv <= $min_idv) {
                            $vehicle_idv = $min_idv;
                        } else {
                            $vehicle_idv = $requestData->edit_idv;
                        }
                    } else {
                        $vehicle_idv = $min_idv;
                    }
                    
                    if($premium_type != 'third_party' && $premium_type != 'third_party_breakin'){
                        $totalAcessoriesIDV = 0;
                        $totalAcessoriesIDV += (($requestData->electrical_acessories_value != '') ? (int)($requestData->electrical_acessories_value) : 0);
                        $totalAcessoriesIDV += (($requestData->nonelectrical_acessories_value != '') ? (int)($requestData->nonelectrical_acessories_value) : 0);
                        $totalAcessoriesIDV += (($requestData->bifuel_kit_value != '') ? (int)($requestData->bifuel_kit_value) : '0');
                        if($totalAcessoriesIDV > ((int)($vehicle_idv) * 30 / 100) )
                        {
                            return [
                                'status' => false,
                                'message' => 'Total of Accessories (Electriccal, Non Electriccal, LPG-CNG KIT) cannot be grater than 30% of the vehicle si',
                                'request' => [
                                    'message' => 'Total of Accessories (Electriccal, Non Electriccal, LPG-CNG KIT) cannot be grater than 30% of the vehicle si',
                                    'totalAcessoriesIDV' => $totalAcessoriesIDV,
                                    'idv_amount' => $vehicle_idv
                                ]
                            ];
                        }
                    }

                    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

                    $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id', $requestData->user_product_journey_id)
                        ->where('seller_type','P')
                        ->first();

                    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                    {
                        if($pos_data)
                        {
                            $premium_calculation_request['Req_POSP'] = [
                                'VC_EMAILID' => $pos_data->agent_email,
                                'VC_NAME' => $pos_data->agent_name,
                                'VC_unique_CODE' => '',
                                'VC_STATE' => '',
                                'VC_Pan_CARD' => $pos_data->pan_no,
                                'VC_ADHAAR_CARD' => $pos_data->aadhar_no,
                                'NUM_MOBILE_NO' => $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '',
                                "DAT_START_DATE" => '',
                                "DAT_END_DATE" => '',
                                "REGISTRATION_NO" => "",
                                "VC_INTERMEDIARY_CODE" => ''
                            ];
                        }

                        if (config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y')
                        {
                            $premium_calculation_request['Req_POSP'] = [
                                'VC_EMAILID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_EMAIL_ID'),
                                'VC_NAME' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_NAME'),
                                'VC_UNIQUE_CODE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE'),
                                'VC_STATE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_STATE'),
                                'VC_Pan_CARD' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_PAN_CARD'),
                                'VC_ADHAAR_CARD' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_ADHAAR_CARD'),
                                'NUM_MOBILE_NO' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_MOBILE_NO'),
                                "DAT_START_DATE" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_START_DATE'),
                                "DAT_END_DATE" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_END_DATE'),
                                "REGISTRATION_NO" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE'),
                                "VC_INTERMEDIARY_CODE" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_INTERMEDIARY_CODE')
                            ];
                        }
                    } 
                    elseif (config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y')
                    {
                        $premium_calculation_request['Req_POSP'] = [
                            'VC_EMAILID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_EMAIL_ID'),
                            'VC_NAME' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_NAME'),
                            'VC_UNIQUE_CODE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE'),
                            'VC_STATE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_STATE'),
                            'VC_Pan_CARD' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_PAN_CARD'),
                            'VC_ADHAAR_CARD' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_ADHAAR_CARD'),
                            'NUM_MOBILE_NO' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_MOBILE_NO'),
                            "DAT_START_DATE" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_START_DATE'),
                            "DAT_END_DATE" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_END_DATE'),
                            "REGISTRATION_NO" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE'),
                            "VC_INTERMEDIARY_CODE" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_INTERMEDIARY_CODE')
                        ];
                    }

                    $premium_calculation_request['Req_GCV'] = "";
                    $premium_calculation_request['Req_MISD'] = "";
                    $premium_calculation_request['Req_PCV'] = "";
                    $premium_calculation_request['Payment_Details'] ="";
                    $premium_calculation_request['IDV_DETAILS'] = "";

                    if($parent_id == 'GCV' && $no_of_ll_paid > $mmv->seating_capacity){
                        return  [   
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'Sum of the selected value with other related covers should not be greater than seating capacity.',
                            'request' => [
                                'message' => 'Sum of the selected value with other related covers should not be greater than seating capacity.',
                                'seating_capacity' => $mmv->seating_capacity,
                                'product_type' => get_parent_code($productData->product_sub_type_id)
                            ]
                        ];
                    }

                    if ($parent_id == 'PCV') {
                        $premium_calculation_request['Req_PCV'] = [
                            'POSP_CODE' => ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $pos_data) || config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE') : '',
                            'ExtensionCountryCode' => '0',
                            'ExtensionCountryName' => '',
                            'Effectivedrivinglicense' => $requestData->vehicle_owner_type == 'I' ? false : true,
                            'NumberOfDrivers' => $no_of_ll_paid_drivers,
                            'NumberOfEmployees' => '0',
                            'NoOfCleanerConductorCoolies' =>  $pa_paid_driver_sum_insured > 0 ? 1 : 0,
                            'BiFuelType' => $external_kit_type,
                            'BiFuel_Kit_Value' => $external_kit_value,
                            'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                            'Owner_Driver_Nominee_Name' => NULL,
                            'Owner_Driver_Nominee_Age' => NULL,
                            'Owner_Driver_Nominee_Relationship' => NULL,
                            'Owner_Driver_Appointee_Name' => NULL,
                            'Owner_Driver_Appointee_Relationship' => NULL,
                            'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                            'ElecticalAccessoryIDV' => $electrical_accessories_value,
                            'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                            'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                            'IsPrivateUseLoading' => 0,
                            'IsInclusionofIMT23' => 0,
                            'OtherLoadDiscRate' => 0,
                            'AntiTheftDiscFlag' => false,
                            'HandicapDiscFlag' => false,
                            'Voluntary_Excess_Discount' => $voluntary_excess_value,
                            'UnnamedPersonSI' => $unnamed_passenger_sum_insured,
                            // 'TPPDLimit' => $is_tppd_cover,as per #23856
                            'IsRTI_Cover' => 1,
                            'IsCOC_Cover' => 1,
                            'Bus_Type' => "",
                            'NoOfFPP' => 0,
                            'NoOfNFPP' => 0,
                            'IsCOC_Cover' => 0,
                            'IsTowing_Cover' => 0,
                            'Towing_Limit' => "",
                            'IsEngGearBox_Cover' => 0,
                            'IsNCBProtection_Cover' => 0,
                            'IsRTI_Cover' => 0,
                            'IsEA_Cover' => $isRsa ? 1 : 0,
                            'IsEAW_Cover' => 0
                        ];
                    } 
                    elseif ($parent_id == 'GCV')
                    {
                        $premium_calculation_request['Req_GCV'] = [
                            'POSP_CODE' => ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $pos_data) || config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE') : '',
                            'ExtensionCountryCode' => '0',
                            'ExtensionCountryName' => '',
                            'Effectivedrivinglicense' => $requestData->vehicle_owner_type == 'I' ? false : true,
                            'NumberOfDrivers' => $no_of_ll_paid > $mmv->seating_capacity ? $mmv->seating_capacity : $no_of_ll_paid,
                            'NumberOfEmployees' => '0',
                            'NoOfCleanerConductorCoolies' => $pa_paid_driver_sum_insured > 0 ? $mmv->seating_capacity : 0,
                            'BiFuelType' => $external_kit_type,
                            'BiFuel_Kit_Value' => $external_kit_value,
                            'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                            'Owner_Driver_Nominee_Name' => NULL,
                            'Owner_Driver_Nominee_Age' => NULL,
                            'Owner_Driver_Nominee_Relationship' => NULL,
                            'Owner_Driver_Appointee_Name' => NULL,
                            'Owner_Driver_Appointee_Relationship' => NULL,
                            'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                            'NoOfTrailers' => 0,
                            'TrailerChassisNo' => "",
                            'TrailerIDV' => 0,
                            'ElecticalAccessoryIDV' => $electrical_accessories_value,
                            'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                            'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                            'IsPrivateUseLoading' => 0,
                            'IsInclusionofIMT23' => 1,
                            'IsOverTurningLoading' => 0,
                            'OtherLoadDiscRate' => 0,
                            'AntiTheftDiscFlag' => false,
                            'HandicapDiscFlag' => false,
                            'PrivateCarrier' => $requestData->gcv_carrier_type == 'PRIVATE' ? true : false,
                            'Voluntary_Excess_Discount' => NULL,
                            // 'TPPDLimit' => $is_tppd_cover,as per #23856
                            'IsRTI_Cover' => 1,
                            'IsCOC_Cover' => 1,
                            'Bus_Type' => "",
                            'NoOfFPP' => 0,
                            'NoOfNFPP' => 0,
                            'IsCOC_Cover' => 0,
                            'IsTowing_Cover' => 0,
                            'Towing_Limit' => "",
                            'IsEngGearBox_Cover' => 0,
                            'IsNCBProtection_Cover' => 0,
                            'IsRTI_Cover' => 0,
                            'IsEA_Cover' => 0,
                            'IsEAW_Cover' => 0
                        ];
                    }
                    elseif ($parent_id == 'MISCELLANEOUS')
                    {
                        $premium_calculation_request['Req_MISD'] = [
                            'POSP_CODE' => [],
                            'ExtensionCountryCode' => '0',
                            'ExtensionCountryName' => '',
                            'Effectivedrivinglicense' => $requestData->vehicle_owner_type == 'I' ? false : true,
                            'NumberOfDrivers' => $no_of_ll_paid_drivers > $mmv->seating_capacity ? $mmv->seating_capacity : $no_of_ll_paid_drivers,
                            'NumberOfEmployees' => '0',
                            'NoOfCleanerConductorCoolies' =>  $pa_paid_driver_sum_insured > 0 ? $mmv->seating_capacity : 0,
                            'BiFuelType' => $external_kit_type,
                            'BiFuel_Kit_Value' => $external_kit_value,
                            'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                            'Owner_Driver_Nominee_Name' => NULL,
                            'Owner_Driver_Nominee_Age' => NULL,
                            'Owner_Driver_Nominee_Relationship' => NULL,
                            'Owner_Driver_Appointee_Name' => NULL,
                            'Owner_Driver_Appointee_Relationship' => NULL,
                            'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                            'NoOfTrailers' => 0,
                            'TrailerChassisNo' => "",
                            'TrailerIDV' => 0,
                            'ElecticalAccessoryIDV' => $electrical_accessories_value,
                            'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                            'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                            'IsPrivateUseLoading' => 0,
                            'IsInclusionofIMT23' => 1,
                            'IsOverTurningLoading' => 0,
                            'OtherLoadDiscRate' => 0,
                            'AntiTheftDiscFlag' => false,
                            'HandicapDiscFlag' => false,
                            'Voluntary_Excess_Discount' => NULL,
                            // 'TPPDLimit' => $is_tppd_cover,as per #23856
                            'IsRTI_Cover' => 1,
                            'IsCOC_Cover' => 1,
                            'Bus_Type' => "",
                            'NoOfFPP' => 0,
                            'NoOfNFPP' => 0,
                            'IsCOC_Cover' => 0,
                            'IsTowing_Cover' => 0,
                            'Towing_Limit' => "",
                            'IsEngGearBox_Cover' => 0,
                            'IsNCBProtection_Cover' => 0,
                            'IsRTI_Cover' => 0,
                            'IsEA_Cover' => 0,
                            'IsEAW_Cover' => 0
                        ];
                    }
                    // if(!$skip_second_call) {
                        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CALCULATE_PREMIUM_URL'), $premium_calculation_request, 'hdfc_ergo', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Premium Calculation',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'quote',
                            'requestMethod' => 'post',
                            'product_code' => $product_code,
                            'transaction_id' => $transaction_id,
                            'token' => $token_data['Authentication']['Token'],
                            'headers' => [
                                'Content-type' => 'application/json',
                                'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                                'PRODUCT_CODE' => $product_code,
                                'TransactionID' => $transaction_id,
                                'Accept' => 'application/json',
                                'Token' => $token_data['Authentication']['Token']
                            ]
                        ]);
                    // }

                    $premium_data = $get_response['response'];
                    if ($premium_data) {
                        $premium_data = json_decode($premium_data, TRUE);

                        if (isset($premium_data['StatusCode']) && $premium_data['StatusCode'] == 200) {
                            if ($parent_id == 'PCV')
                            {
                                $premium = $premium_data['Resp_PCV'];
                            }
                            else
                            {
                                $premium = $premium_data['Resp_GCV'];
                            }

                            $basic_od = $premium['Basic_OD_Premium'] + ($premium['HighTonnageLoading_Premium'] ?? 0);
                            $tppd = $premium['Basic_TP_Premium'];
                            $pa_owner = (float) $premium['PAOwnerDriver_Premium'];
                            $pa_unnamed = 0;
                            $pa_paid_driver = $premium['PAPaidDriverCleaCondCool_Premium'];
                            $electrical_accessories = $premium['Electical_Acc_Premium'];
                            $non_electrical_accessories = $premium['NonElectical_Acc_Premium'];
                            $roadSideAssistance = $premium['EA_premium'] ?? 0;
                            $zero_dep_amount = $premium['Vehicle_Base_ZD_Premium'];
                            $ncb_discount = $premium['NCBBonusDisc_Premium'];
                            $lpg_cng = $premium['BiFuel_Kit_OD_Premium'];
                            $lpg_cng_tp = isset($premium['BiFuel_Kit_TP_Premium']) && $premium['BiFuel_Kit_TP_Premium'] > 0 ? $premium['BiFuel_Kit_TP_Premium'] : (isset($premium['InBuilt_BiFuel_Kit_Premium']) && $premium['InBuilt_BiFuel_Kit_Premium'] > 0 ? $premium['InBuilt_BiFuel_Kit_Premium'] : 0);
                            $automobile_association = 0;
                            $anti_theft = $parent_id == 'PCV' ? $premium['AntiTheftDisc_Premium'] : 0;
                            $tppd_discount_amt = $premium['TPPD_premium'] ?? 0;
                            $other_addon_amount = 0;
                            $liabilities = 0;
                            $ll_paid_cleaner = $premium['NumberOfDrivers_Premium'];
                            $imt_23 = $parent_id == 'GCV' ? $premium['VB_InclusionofIMT23_Premium'] : 0;
                            $geog_extension_od = $premium['GeogExtension_ODPremium'] ?? 0;
                            $geog_extension_tp = $premium['GeogExtension_TPPremium'] ?? 0;
                            $own_premises_od = $premium['LimitedtoOwnPremises_OD_Premium'] ?? 0;
                            $own_premises_tp = $premium['LimitedtoOwnPremises_TP_Premium'] ?? 0;

                            $idv = $premium['IDV'];

                            $ic_vehicle_discount = 0;
                            $voluntary_excess = 0;
                            $other_discount = 0;

                            if ($electrical_accessories > 0) {
                                $zero_dep_amount += (int)$premium['Elec_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $premium['Elec_InclusionofIMT23_Premium'] : 0;
                            }
    
                            if ($non_electrical_accessories > 0) {
                                $zero_dep_amount += (int)$premium['NonElec_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $premium['NonElec_InclusionofIMT23_Premium'] : 0;
                            }
    
                            if ($lpg_cng > 0) {
                                $zero_dep_amount += (int)$premium['Bifuel_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $premium['BiFuel_InclusionofIMT23_Premium'] : 0;
                            }

                            $final_od_premium = $basic_od + $electrical_accessories + $non_electrical_accessories + $lpg_cng - $own_premises_od;
                            $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $ll_paid_cleaner - $own_premises_tp;

                            $ncb_discount = ($final_od_premium - $anti_theft) * ($requestData->applicable_ncb/100);

                            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt;

                            $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);

                            if ($parent_id == 'GCV') {
                                $final_gst_amount = round(($tppd * 0.12) + (($final_net_premium - $tppd) * 0.18));
                            } else {
                                $final_gst_amount = round($final_net_premium * 0.18);
                            }

                            $final_payable_amount = $final_net_premium + $final_gst_amount;

                            $applicable_addons = ['zeroDepreciation', 'imt23', 'roadSideAssistance'];

                            if ($parent_id == 'PCV') {
                                array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
                            }

                            if ($interval->y >= 3)
                            {
                                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                            }

                            $business_types = [
                                'rollover' => 'Rollover',
                                'newbusiness' => 'New Business',
                                'breakin' => 'Break-in'
                            ];

                            $data_response = [
                                'status' => true,
                                'msg' => 'Found',
                                'webservice_id'=> $get_response['webservice_id'],
                                'table'=> $get_response['table'],
                                'Data' => [
                                    'idv' => round($idv),
                                    'min_idv' => $min_idv != "" ? round($min_idv) : 0,
                                    'max_idv' => $max_idv != "" ? round($max_idv) : 0,
                                    'vehicle_idv' => round($idv),
                                    'qdata' => NULL,
                                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                                    'addonCover' => NULL,
                                    'addon_cover_data_get' => '',
                                    'rto_decline' => NULL,
                                    'rto_decline_number' => NULL,
                                    'mmv_decline' => NULL,
                                    'mmv_decline_name' => NULL,
                                    'policy_type' => $tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                                    'business_type' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type],
                                    'cover_type' => '1YC',
                                    'hypothecation' => '',
                                    'hypothecation_name' => '',
                                    'vehicle_registration_no' => $requestData->rto_code,
                                    'rto_no' => $requestData->rto_code,
                                    'version_id' => $requestData->version_id,
                                    'selected_addon' => [],
                                    'showroom_price' => 0,
                                    'fuel_type' => $requestData->fuel_type,
                                    'ncb_discount' => (int)$ncb_discount > 0 ? $requestData->applicable_ncb : 0,
                                    'tppd_discount' => $tppd_discount_amt,
                                    'company_name' => $productData->company_name,
                                    'company_logo' => url(config('constants.motorConstant.logos')).'/'.$productData->logo,
                                    'product_name' => $productData->product_sub_type_name,
                                    'mmv_detail' => $mmv,
                                    'master_policy_id' => [
                                        'policy_id' => $productData->policy_id,
                                        'policy_no' => $productData->policy_no,
                                        'policy_start_date' => $policy_start_date,
                                        'policy_end_date' => date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))),
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
                                    'motor_manf_date' => '01-'.$requestData->manufacture_year,
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
                                    'basic_premium' => $basic_od,
                                    'motor_electric_accessories_value' => $electrical_accessories,
                                    'motor_non_electric_accessories_value' => $non_electrical_accessories,
                                    'motor_lpg_cng_kit_value' => $lpg_cng,
                                    'total_accessories_amount(net_od_premium)' => $electrical_accessories + $non_electrical_accessories + $lpg_cng,
                                    'total_own_damage' => $final_od_premium,
                                    'tppd_premium_amount' => $tppd,
                                    'compulsory_pa_own_driver' => $pa_owner,  // Not added in Total TP Premium
                                    'cpa_allowed' => !empty($pa_owner),
                                    'GeogExtension_ODPremium' => round($geog_extension_od),
                                    'GeogExtension_TPPremium' => round($geog_extension_tp),
                                    'LimitedtoOwnPremises_OD' => round($own_premises_od),
                                    'LimitedtoOwnPremises_TP' => round($own_premises_tp),
                                    'cover_unnamed_passenger_value' => $pa_unnamed,
                                    'default_paid_driver' => $liabilities + $ll_paid_cleaner,
                                    'motor_additional_paid_driver' => $pa_paid_driver,
                                    'cng_lpg_tp' => $lpg_cng_tp,
                                    'seating_capacity' => $mmv->seating_capacity,
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
                                    'policyStartDate' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y', strtotime($policy_start_date)),
                                    'policyEndDate' => date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))),
                                    'ic_of' => $productData->company_id,
                                    'vehicle_in_90_days' => 'N',
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
                                    'add_ons_data' => [
                                        'in_built' => [
                                            'zero_depreciation' => (int)$zero_dep_amount
                                        ],
                                        'additional' => [
                                            'road_side_assistance' => $roadSideAssistance,
                                            'imt23' => $imt_23
                                        ]
                                    ],
                                    'final_od_premium' => $final_od_premium,
                                    'final_tp_premium' => $final_tp_premium,
                                    'final_total_discount' => $final_total_discount,
                                    'final_net_premium' => $final_net_premium,
                                    'final_gst_amount' => round($final_gst_amount),
                                    'final_payable_amount' => round($final_payable_amount),
                                    'applicable_addons' => $applicable_addons,
                                    'mmv_detail' => [
                                        'manf_name' => $mmv->manufacturer,
                                        'model_name' => $mmv->vehicle_model,
                                        'version_name' => $mmv->txt_variant,
                                        'fuel_type' => $mmv->txt_fuel,
                                        'seating_capacity' => $mmv->seating_capacity,
                                        'carrying_capacity' => $mmv->carrying_capacity,
                                        'cubic_capacity' => $mmv->cubic_capacity,
                                        'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
                                        'vehicle_type' => '',
                                    ]
                                ]
                            ];

                            if ((int) $zero_dep_amount == 0)
                            {
                                unset($data_response['Data']['add_ons_data']['in_built']['zero_depreciation']);
                            }

                            if ($own_premises_od == 0) {
                                unset($data_response['Data']['LimitedtoOwnPremises_OD']);
                            }

                            if ($own_premises_tp == 0) {
                                unset($data_response['Data']['LimitedtoOwnPremises_TP']);
                            }

                            if ($lpg_cng == 0) {
                                unset($data_response['Data']['motor_lpg_cng_kit_value']);
                            }

                            if ($lpg_cng_tp == 0) {
                                unset($data_response['Data']['cng_lpg_tp']);
                            }

                            if (in_array($premium_type, [
                                'short_term_3',
                                'short_term_6',
                                'short_term_3_breakin',
                                'short_term_6_breakin'
                            ])) {
                                $data_response['Data']['premium_type_code'] = $premium_type;
                            }

                            return camelCase($data_response);
                        } else {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => isset($premium_data['Error']) ? $premium_data['Error'] : 'Service Error'
                            ];
                        }
                    } else {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Something went wrong while calculating premium'
                        ];
                    }
                } else {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        
                        'message' => isset($idv_data['Error']) ? $idv_data['Error'] : 'Service Error'
                    ];
                }
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'message' => 'Something went wrong while calculating IDV'
                ];
            }
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => isset($token_data['Error']) ? $token_data['Error'] : 'Service Error'
            ];
        }
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Something went wrong while generating token'
        ];
    }
}
