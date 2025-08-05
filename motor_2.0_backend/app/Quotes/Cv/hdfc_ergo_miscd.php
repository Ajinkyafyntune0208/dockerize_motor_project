<?php

use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MotorIdv;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getMiscQuote($enquiryId, $requestData, $productData)
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


    $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');

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
    $mmv_data['model_name'] = $get_mapping_mmv_details->vehiclemodel;
    $mmv_data['version_name'] = $get_mapping_mmv_details->txt_variant;
    $mmv_data['seating_capacity'] = $get_mapping_mmv_details->seatingcapacity;
    $mmv_data['cubic_capacity'] = $get_mapping_mmv_details->cubiccapacity;
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

    $state_name = DB::table('miscd_hdfc_ergo_state_master')->where('state_id', $rto_data->state_id)->first();
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
        $rto_location = DB::table('miscd_hdfc_ergo_rto_location')
        ->where('Txt_Rto_Location_desc', 'like', '%' . $rto_city . '%')
        ->where('Num_Vehicle_Subclass_Code', '=', $get_mapping_mmv_details->num_vehicle_subclass_code)
        ->where('Num_Vehicle_Class_code', '=', $get_mapping_mmv_details->vehicleclasscode)
        ->first();

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

    $tp_only = in_array($productData->premium_type_id, [2, 7]);
    $policy_expiry_date = $requestData->previous_policy_expiry_date;
    $is_breakin = false;
    if ($requestData->business_type == 'newbusiness') {
        $businesstype = 'NEW BUSINESS';
        $policy_start_date = date('d-m-Y');
        $IsPreviousClaim = '0';
        $prepolstartdate = '01-01-1900';
        $prepolicyenddate = '01-01-1900';
    } else {
        $businesstype = 'ROLL OVER';
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
    if ($previous_policy_type == 'Third-party' && $businesstype == 'ROLL OVER' && !$tp_only) {
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
    $nooflldrivers = 0;
    if (!empty($selected_addons) && $selected_addons->additional_covers != null && $selected_addons->additional_covers != '') {
        $additional_covers = json_decode($selected_addons->additional_covers);
        foreach ($additional_covers as $value) {

            if ($value->name == 'PA cover for additional paid driver') {
                $pa_paid_driver = '1';
                $PAToPaidDriver_SumInsured = $value->sumInsured;
            }
            if ($value->name == 'LL paid driver') {
                $nooflldrivers = '1';
            }

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
    if($nooflldrivers > $get_mapping_mmv_details->seatingcapacity){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'No. of LL paid driver should not be more than Seating Capacity.',
            'request' => [
                'message' => 'No. of LL paid driver should not be more than Seating Capacity.',
                'requestData' => $get_mapping_mmv_details->seatingcapacity
            ]
        ];
    }
    if (!empty($selected_addons) && $selected_addons->discounts != null && $selected_addons->discounts != '') {
        $discounts = json_decode($selected_addons->discounts);
        foreach ($discounts as $data) {
            if ($data->name == 'TPPD Cover') {
                $tppd_cover = 6000;
            }
            if ($data->name == 'Vehicle Limited to Own Premises') {
                $own_premises = '1';
            }
        }
    }
    if (!empty($selected_addons) && $selected_addons->addons != null && $selected_addons->addons != '') {
        $addons = json_decode($selected_addons->addons);   
        foreach ($addons as $value) {
            if ($value->name == 'IMT - 23') {
                $imt23 = '1';
            }
        }
    }
    $ElectricalaccessSI  = 0;
    $NonElectricalaccessSI = 0;
    if (!empty($selected_addons) && $selected_addons->accessories != null && $selected_addons->accessories != '') {
        $accessories = json_decode($selected_addons->accessories);
        foreach ($accessories as $value) {
            if ($value->name == 'Electrical Accessories') {
                $Electricalaccess = 1;
                $ElectricalaccessSI = $value->sumInsured;
            }
            if ($value->name == 'Non-Electrical Accessories') {
                $NonElectricalaccess = 1;
                $NonElectricalaccessSI = $value->sumInsured;
            }

            if ($value->name == 'External Bi-Fuel Kit CNG/LPG') {
                $externalCNGKIT = 'LPG';
                $externalCNGKITSI = $value->sumInsured;
            }
        }
    }
    $model_config_idv = [
        'str',
        [
            'IDV' => [
                'agent_cd' => config('constants.IcConstants.hdfc_ergo.HDFC_MISCD_AGENT_CODE'),
                'policy_start_date' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                'vehicle_class_cd' => $get_mapping_mmv_details->vehicleclasscode,
                'vehicle_subclass_cd' => $get_mapping_mmv_details->num_vehicle_subclass_code,
                'RTOLocationCode' => $rto_location->Txt_Rto_Location_code,
                'vehiclemodelcode' => $get_mapping_mmv_details->vehiclemodelcode,
                'manufacturer_code' => $get_mapping_mmv_details->manufacturercode,
                'purchaseregndate' => Carbon::createFromFormat('d-m-Y', $first_reg_date)->format('d/m/Y'),
                'manufacturingyear' => $manufacturingyear,
                'prev_policy_end_date' => (strtoupper($prev_policy_end_date) == 'NEW') || empty($prev_policy_end_date) ? '' : Carbon::createFromFormat('d-m-Y', $prev_policy_end_date)->format('d/m/Y'),
                'typeofbusiness' => $businesstype,
            ],
        ],
    ];

    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TRACTOR_IDV_URL'), $model_config_idv, 'hdfc_ergo',
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
    if (preg_match('/Service Unavailable/i', $idv_data) || preg_match('/network-related or instance-specific error/i', $idv_data) || preg_match('/Could not allocate space for object/i', $idv_data)) {
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
        $idv = $idv_data['idv_amount'];
        $idv_min = $idv_data['idv_amount_min'];
        $idv_max = $idv_data['idv_amount_max'];

        if ($requestData->is_idv_changed == 'Y') {
            if ($requestData->edit_idv >= $idv_max) {
                $idv_amount = floor($idv_max);
            } elseif ($requestData->edit_idv <= $idv_min) {
                $idv_amount = floor($idv_min);
            } else {
                $idv_amount = floor($requestData->edit_idv);
            }
        } else {
            $getIdvSetting = getCommonConfig('idv_settings');   
            switch ($getIdvSetting) {
                case 'default':
                    $idv_data['idv_amount'] = $idv;
                    $skip_second_call = true;
                    $idv_amount = $idv;
                    break;
                case 'min_idv':
                    $idv_data['idv_amount_min'] = $idv_min;
                    $idv_amount = $idv_min;
                    break;
                case 'max_idv':
                    $idv_data['idv_amount_max'] = $idv_data;
                    $idv_amount = $idv_data;

                    break;
                default:
                    $idv_data['idv_amount_min'] = $idv_min;
                    $idv_amount = $idv_min;
                    break;
            }
            // $idv_amount = $idv_data['idv_amount_min'];
        }

        $is_pa_cover_owner_driver = 0;
        if (!empty($selected_addons && $selected_addons->compulsory_personal_accident != '')) {
            $compulsory_personal_accident = json_decode($selected_addons->compulsory_personal_accident);
            foreach ($compulsory_personal_accident as $value) {
                if (isset($value->name) && ($value->name == 'Compulsory Personal Accident')) {
                    if ($requestData->vehicle_owner_type == 'I') {
                        $is_pa_cover_owner_driver = 1;
                    }
                }
            }
        }
        if ($productData->premium_type_id == 1 || $productData->premium_type_id == 4) {
            $model_config_premium = [
                'VehicleClassCode=' . $get_mapping_mmv_details->vehicleclasscode . '&str',
                [
                    'CSCTractorPremiumCalc' => [
                        'agent_cd' => config('constants.IcConstants.hdfc_ergo.HDFC_MISCD_AGENT_CODE'),
                        'typeofbusiness' => $businesstype,
                        'intermediaryid' => '201764376894',
                        'vehiclemakeidv' => $idv_amount,
                        'vehiclemodelcode' => $get_mapping_mmv_details->vehiclemodelcode,
                        'rtolocationcode' => $rto_location->Txt_Rto_Location_code,
                        'customertype' => $requestData->vehicle_owner_type == 'I' ? "I" : "O",
                        'policystartdate' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                        'policyenddate' => $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d/m/Y'),
                        'purchaseregndate' => Carbon::createFromFormat('d-m-Y', $first_reg_date)->format('d/m/Y'),
                        'numofpaiddriver' => isset($pa_paid_driver) ? $pa_paid_driver : '0',
                        'numsipaiddriver' => $PAToPaidDriver_SumInsured,
                        'numnonelectricalidv' => isset($NonElectricalaccessSI) ? $NonElectricalaccessSI : '0',
                        'numelectricalidv' => isset($ElectricalaccessSI) ? $ElectricalaccessSI : '0',
                        'nooflldrivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                        'nooftrailers' => '0',
                        'numtrailersidv' => '0',
                        'previousdiscount' => $previous_policy_type == 'Third-party' ? '0' : $requestData->previous_ncb,
                        'limitedownpremises' =>'0',
                        'privateuse' => '0',
                        'inclusionimt23' => isset($imt23) ? $imt23 : '0',
                        'previousisclaim' => $IsPreviousClaim,
                        'prev_pol_end_dt' => ($businesstype == 'NEW BUSINESS' || $prepolicyenddate == 'New') ? '01/01/1900' : Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d/m/Y'),
                        'effective_driving_lic' => $is_pa_cover_owner_driver,
                        'lpg_cngkit' => '0',
                        'source_request_type' => 'ONLINE',
                        'customer_state_code' => '0',
                        'i_gcpospcode' => '',
                        'is_gstin_applicable' => '0',
                        'misc_code' => '0'
                    ]
                ],
            ];
            $skip_second_call = false;
            if(!$skip_second_call) {
                $get_response= getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TRACTOR_PREMIUM_URL'),
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
                if (isset($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['TXT_ERROR_MSG']) && !empty($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['TXT_ERROR_MSG'])) {
                    return [
                        'status' => false,
                        'message' => $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['TXT_ERROR_MSG'],
                    ];
                } else if (!isset($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT'])) {
                    return [
                        'status' => false,
                        'message' => 'Unable to parse the response. Please try again.',
                    ];
                }else if (isset($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['PARENT'])) {
                    $premium_data = $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['PARENT'][0];
                } else {
                    $premium_data = $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT'];
                }

                $pa_paid_driver = $premium_data['NUM_PA_PAID_DRIVER_PREM'];
                $liabilities = $premium_data['NUM_NOOFLLDRIVERS_PREM'];
                $tppd_discount = isset($premium_data['NUM_TPPD_AMT']) ? round($premium_data['NUM_TPPD_AMT']) : 0;
                $motor_electric_accessories_value = $premium_data['NUM_ELECTRICAL_PREM'];
                $motor_non_electric_accessories_value = $premium_data['NUM_NON_ELECTRICAL_PREM'];
                $final_tp_premium = $premium_data['NUM_BASIC_TP_PREM'] + $liabilities + $pa_paid_driver;

                $total_own_damage = $premium_data['NUM_BASIC_OD_PREM'];
                $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;

                $own_premises_od =  $premium_data['NUM_LIMITED_PREMISES_OD_PREM'];
                $own_premises_tp = $premium_data['NUM_LIMITED_PREMISES_TP_PREM'];                
                $imt_23 = round($premium_data['NUM_INCLUSION_IMT23_PREM']);
                $final_od_premium = $total_own_damage + $motor_electric_accessories_value + $motor_non_electric_accessories_value;
                $final_total_discount = (int)($final_od_premium * $requestData->applicable_ncb / 100);
                $deduction_of_ncb = (int)($final_od_premium * $requestData->applicable_ncb / 100);   
                
                $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);

                //Tax calculate
                $final_gst_amount = round($final_net_premium * 0.18);
                $final_payable_amount = $final_net_premium + $final_gst_amount;

                $applicable_addons =  [
                    'in_built' => [
                    ],
                    'additional' => [
                        'imt23' => $imt_23,
                        // 'zero_depreciation' => 0,
                    ],
                ];
                    
                
                
                $applicable_addons['additional_premium'] = array_sum($applicable_addons['additional']);
                $applicable_addons['in_built_premium'] = array_sum($applicable_addons['in_built']);
                $data_response = [
                    'status' => 200,
                    'msg' => 'Found',
                    'Data' => [
                        'idv' => $idv_amount,
                        'min_idv' => $idv_data['idv_amount_min'],
                        'max_idv' => $idv_data['idv_amount_max'],
                        'exshowroomprice' => $idv_data['exshowroomPrice'],
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
                        'version_id' => $get_mapping_mmv_details->srno,
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
                        'basic_premium' => $premium_data['NUM_BASIC_OD_PREM'],
                        'deduction_of_ncb' => $deduction_of_ncb,
                        'tppd_premium_amount' => $premium_data['NUM_BASIC_TP_PREM'],
                        'motor_electric_accessories_value' => (int) $premium_data['NUM_ELECTRICAL_PREM'],
                        'motor_non_electric_accessories_value' => (int) $premium_data['NUM_NON_ELECTRICAL_PREM'],
                        'motor_lpg_cng_kit_value' => 0,
                        'cover_unnamed_passenger_value' => 0,
                        'seating_capacity' => $get_mapping_mmv_details->seatingcapacity,
                        'default_paid_driver' => (float) $liabilities,
                        'll_paid_driver_premium' => (float) $liabilities,
                        'll_paid_conductor_premium' => 0,
                        'll_paid_cleaner_premium' => 0,
                        'motor_additional_paid_driver' => $pa_paid_driver,
                        'compulsory_pa_own_driver' => (float) $premium_data['NUM_PA_OWNER_DRIVER_PREM'],
                        'total_accessories_amount(net_od_premium)' => $premium_data['NUM_BASIC_OD_PREM'],
                        'total_own_damage' => '',
                        'cng_lpg_tp' => 0,
                        'total_liability_premium' => '',
                        'net_premium' => $premium_data['NUM_TOTAL_PREMIUM'],
                        'service_tax_amount' => $premium_data['NUM_SERVICE_TAX_PREM'],
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
                        'applicable_addons' => [],
                    ],
                ];

                if ($imt_23 > 0)
                {
                    array_push($data_response['Data']['applicable_addons'], 'imt23');
                }
                // if(!empty($requestData->bifuel_kit_value))
                // {
                //     $data_response['Data']['motor_lpg_cng_kit_value'] = (int) $premium_data['CNGLPGODAMOUNT'];
                //     $data_response['Data']['cng_lpg_tp'] = $premium_data['CNGLPGTPAMOUNT'];
                // }
            } else {
                $data_response = array(
                    'status' => false,
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
