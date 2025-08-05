<?php
include_once app_path().'/Helpers/CvWebServiceHelper.php';
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;

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
    if ($requestData->policy_type == 'newbusiness') {
        return [
            'status' => false,
            'premium' => 0,
            'msg' => 'New Business Not Allowed.',
            'request' => [
                'policy_type' => $requestData->policy_type,
                'message' => 'Quotes Not Allowed For New Business',
                'requestData' => $requestData
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
    $motor_manf_year = $motor_manf_year_arr[1];

    $current_date = date('Y-m-d');
    $policy_start_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date.' + 1 days'));
    $prev_policy_end_date = implode('', explode('-', date('Y-m-d', strtotime($requestData->previous_policy_expiry_date))));

    if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
        $policy_start_date = date('Y-m-d');
    }

    $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 1 year'));

    if ($premium_type == 'short_term_3') {
        $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 3 month'));
    } elseif ($premium_type == 'short_term_6') {
        $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 6 month'));
    }

    $motor_manf_date = '01-'.$requestData->manufacture_year;

    $car_age = 0;
    $car_age = ((date('Y', strtotime($current_date)) - date('Y', strtotime($motor_manf_date))) * 12) + (date('m', strtotime($current_date)) - date('m', strtotime($motor_manf_date)));

    $car_age = $car_age < 0 ? 0 : $car_age;

    $vehicle_in_90_days = 0;

    if (isset($policy_start_date)) {
        $vehicle_in_90_days = (strtotime(date('Y-m-d')) - strtotime($policy_start_date)) / (60*60*24);
        
        if ($vehicle_in_90_days > 90) {
            $requestData->previous_ncb = 0;
        }
    }

//    $get_mapping_mmv_details = DB::table('ic_version_mapping AS icvm')
//        ->leftJoin('cv_acko_model_master AS camm', 'icvm.ic_version_code', '=', 'camm.version_id')
//        ->where('icvm.fyn_version_id', $requestData->version_id)
//        ->where('icvm.ic_id', $productData->company_id)
//        ->first();
    $mmv = get_mmv_details($productData,$requestData->version_id,'acko');
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
    
    $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($get_mapping_mmv_details)) {
        return [
            'status' => false,
            'premium' => 0,
            'msg' => 'Vehicle does not exist with insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle does not exist with insurance company',
            ]
        ];
    }

    $mmv_data['manf_name'] = $get_mapping_mmv_details->make;
    $mmv_data['model_name'] = $get_mapping_mmv_details->model;
    $mmv_data['version_name'] = $get_mapping_mmv_details->version; 
    $mmv_data['seating_capacity'] = $get_mapping_mmv_details->seating_capacity; 
    $mmv_data['cubic_capacity'] = $get_mapping_mmv_details->cubic_capacity; 
    $mmv_data['fuel_type'] = $get_mapping_mmv_details->fuel_type;
    $mmv_data['version_id'] = $get_mapping_mmv_details->version_id;

    $rto_data = DB::table('master_rto')
        ->where('rto_code', $requestData->rto_code)
        ->where('status', 'Active')
        ->first();

    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => 0,
            'msg' => 'RTO code does not exist',
            'request' => [
                'rto_data' => $rto_data,
                'message' => 'RTO code does not exist',
                'rto_no' => $requestData->rto_code
            ]
        ];
    }

    $is_previous_policy_expired = strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date);

    $previous_policy_expiry_date = new \DateTime($requestData->previous_policy_expiry_date);

    if ($requestData->vehicle_registration_no == NULL)
    {
        $reg_no_3 = chr(rand(65,90));
        $vehicle_registration_number = str_replace('-', '', $requestData->rto_code) . strtolower($reg_no_3 . $reg_no_3) . '1234';
    }
    else
    {
        $requestData->vehicle_registration_no = str_replace('--', '-', $requestData->vehicle_registration_no);
        $vehicle_registration_no_array = explode('-', $requestData->vehicle_registration_no);
        $vehicle_registration_number = $vehicle_registration_no_array[0] . $vehicle_registration_no_array[1] . strtolower($vehicle_registration_no_array[2]) . (isset($vehicle_registration_no_array[3]) ? $vehicle_registration_no_array[3] : '');
    }

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)
        ->first();

    $is_external_cng_kit = false;
    $cng_kit_value = 0;
    $electrical_accessories = false;
    $electrical_accessories_value = 0;

    if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
        foreach ($selected_addons->accessories as $accessory) {
            if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                $is_external_cng_kit = true;
                $cng_kit_value = (int)$accessory['sumInsured'];
            }

            if ($accessory['name'] == 'Electrical Accessories') {
                $electrical_accessories = true;
                $electrical_accessories_value = $accessory['sumInsured'];
            }
        }
    }

    $cng_kit_values = [0, 10000, 20000, 30000, 40000, 50000];

    if ($is_external_cng_kit) {
        if ($cng_kit_value > 50000) {
            return [
                'status' => false,
                'msg' => 'External CNG kit value should not be greater than 50000',
                'request' => [
                    'message' => 'External CNG kit value should not be greater than 50000',
                    'cng_kit_value' => $cng_kit_value
                ]
            ];
        } else {
            if (!in_array($cng_kit_value, [10000, 20000, 30000, 40000, 50000])) {
                foreach ($cng_kit_values as $key => $cng_value) {
                    if ($cng_kit_value > $cng_value && $cng_kit_value < $cng_kit_values[$key + 1]) {
                        if ($cng_kit_value < ($cng_value + $cng_kit_values[$key + 1])/2) {
                            $cng_kit_value = $cng_value;
                        } else {
                            $cng_kit_value = $cng_kit_values[$key + 1];
                        }
                    }
                }
            }
        }
    }

    $unnamed_passenger = false;
    $ll_paid_driver = false;
    $unnamed_passenger_sum_insured = 0;
    $ll_paid_driver_sum_insured = 0;

    if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
        foreach ($selected_addons->additional_covers as $additional_cover) {
            if ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                $unnamed_passenger = true;
                $unnamed_passenger_sum_insured = $additional_cover['sumInsured'];
            }

            if ($additional_cover['name'] == 'LL paid driver') {
                $ll_paid_driver = true;
                $ll_paid_driver_sum_insured = $additional_cover['sumInsured'];
            }
        }
    }

    $quote_request_array = [
        'product' => 'car',
        'user' => [
            'is_corporate' => $requestData->vehicle_owner_type == 'C' ? true : false,
            'name' => null,
            'email' => null,
            'phone' => null
        ],
        'vehicle' => [
            'variant_id' => (int) $mmv_data['version_id'],
            'is_new' => false,
            'registration_month' => (int)date('m', strtotime($requestData->vehicle_register_date)),
            'registration_year' => (int)date('Y', strtotime($requestData->vehicle_register_date)),
            'manufacturing_year' => (int)$motor_manf_year,
            'rto_code' => $requestData->rto_code,
            'registration_number' => $vehicle_registration_number,
            'is_external_cng_kit' => $is_external_cng_kit,
            'cng_kit_value' => $cng_kit_value,
            'previous_policy' => [
                'is_expired' => $is_previous_policy_expired,
                'expiry_date' => $previous_policy_expiry_date->format('Y-m-d\TH:i:sP'),
                'is_claim' => $requestData->is_claim == 'Y' ? true : false,
                'ncb' => $premium_type == 'third_party' ? 0 : (int)$requestData->previous_ncb,
                'plan_id' =>  $requestData->previous_policy_type == 'Third-party' ? 'pcv_third_party' : 'pcv_comprehensive'
            ],
            'registration_type' => 'commercial'
        ],
        'policy' => [
            'plan_id' => $premium_type == 'third_party' ? 'pcv_third_party' : 'pcv_comprehensive',
            'tenure' => 1,
            'addons' => []
        ]
    ];

    if ($requestData->vehicle_owner_type == 'I') {
        array_push($quote_request_array['policy']['addons'], [
            'id' => 'pa_owner_pcv'
        ]);
    }

    if (!$is_external_cng_kit) {
        unset($quote_request_array['vehicle']['cng_kit_value']);
    }

    if ($unnamed_passenger) {
        array_push($quote_request_array['policy']['addons'], [
            'id' => 'unnamed_passenger_pcv',
            'sum_insured' => $unnamed_passenger_sum_insured
        ]);
    }

    if ($ll_paid_driver) {
        array_push($quote_request_array['policy']['addons'], [
            'id' => 'imt_40_pcv',
            'sum_insured' => $ll_paid_driver_sum_insured
        ]);
    }

    if ($electrical_accessories) {
        array_push($quote_request_array['policy']['addons'], [
            'id' => 'electrical_accessories_pcv',
            'sum_insured' => $electrical_accessories_value
        ]);
    }

    if ($mmv_data['seating_capacity'] > 6 && $premium_type != 'third_party')
    {
        array_push($quote_request_array['policy']['addons'], [
            'id' => 'imt_23_pcv'
        ]);
    }

    if ($premium_type == 'short_term_3') {
        $quote_request_array['policy']['tenure'] = 3;
        $quote_request_array['policy']['tenure_unit'] = 'MONTH';
    } elseif ($premium_type == 'short_term_6') {
        $quote_request_array['policy']['tenure'] = 6;
        $quote_request_array['policy']['tenure_unit'] = 'MONTH';
    }

    $get_response = getWsData(config('constants.IcConstants.acko.ACKO_QUOTE_WEB_SERVICE_URL'), $quote_request_array, 'acko', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'quote'
        ]
    );

    $quote_result = $get_response['response'];
    if ($quote_result) {
        $result = json_decode($quote_result, TRUE);

        if (isset($result['success']) && $result['success']) {
            $vehicle_idv = round($result['result']['policy']['idv']['calculated']);
            $min_idv = ceil($result['result']['policy']['idv']['min']);
            $max_idv = floor($result['result']['policy']['idv']['max']);
            $default_idv = round($result['result']['policy']['idv']['calculated']);

            if ($premium_type != 'third_party') {
                if ($requestData->is_idv_changed == 'Y') {                       	
                    if ($requestData->edit_idv >= $max_idv) {
                        $quote_request_array['policy']['idv'] = $max_idv;
                    } elseif ($requestData->edit_idv <= $min_idv) {
                        $quote_request_array['policy']['idv'] = $min_idv;
                    } else {
                        $quote_request_array['policy']['idv'] = $requestData->edit_idv;
                    }
                } else {
                    $quote_request_array['policy']['idv'] = $min_idv;
                }
            }

            $get_response = getWsData(config('constants.IcConstants.acko.ACKO_QUOTE_WEB_SERVICE_URL'), $quote_request_array, 'acko', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Premium Recalculation',
                    'requestMethod' => 'post',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'quote'
                ]
            );

            $quote_result = $get_response['response'];
            if ($quote_result) {
                $result = json_decode($quote_result, TRUE);

                if (isset($result['success']) && $result['success']) {
                    $vehicle_idv = round($result['result']['policy']['idv']['calculated']);
                    $default_idv = round($result['result']['policy']['idv']['calculated']);
                } else {
                    $messages = '';

                    if (isset($result['result']['field_errors'])) {
                        foreach ($result['result']['field_errors'] as $field => $field_error) {
                            $messages = $messages.$field_error['msg'].'. ';
                        }
                    } else if (isset($result['result']['code'])) {
                        $messages = $result['result']['code'];
                    } else {
                        $messages = 'Service Temporarily Unavailable';
                    }

                    $data_response = [
                        'status' => false,
                        'premium' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => $messages
                    ];
                }
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Insurer not reachable'
                ];
            }

            $imt_23 = 0;

            $motor_electric_accessories_value = 0;
            $motor_non_electric_accessories_value = 0;

            $paid_driver = 0;
            $total_own_damage = 0;
            $tp_pcv = 0;
            $ncbvalue = 0;
            $insurer_discount = 0;
            $tp_cng_pcv = 0;
            $cpa_owner_driver = 0;
            $unnamed_passenger_cover = 0;
            $ll_paid_driver_cover = 0;

            foreach ($result['result']['plans'][0]['tenures'][0]['covers'] as $responsekey => $responsevalue) {
                if ($responsevalue["id"] == "pa_owner_pcv") {
                    $cpa_owner_driver = round($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "own_damage_pcv") {
                    $total_own_damage = round($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "tp_pcv") {
                    $tp_pcv = round($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "tp_cng_pcv") {
                    $tp_cng_pcv = round($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "unnamed_passenger_pcv") {
                    $unnamed_passenger_cover = round($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "imt_40_pcv") {
                    $ll_paid_driver_cover = round($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "electrical_accessories_pcv") {
                    $motor_electric_accessories_value = round($responsevalue['premium']['net']['breakup'][0]['value']);
                }
                elseif ($responsevalue["id"] == "imt_23_pcv")
                {
                    $imt_23 = round($responsevalue['premium']['net']['breakup'][0]['value']);
                }
            }

            if ($result['result']['plans'][0]['tenures'][0]['premium']['discount']['value'] > 0) {
                foreach ($result['result']['plans'][0]['tenures'][0]['premium']['discount']['breakup'] as $discountkey => $discountvalue) {
                    if ($discountvalue["id"] == "ncb") {
                        $ncbvalue = round($discountvalue['value']);
                    } elseif ($discountvalue["id"] == "insurer_discount") {
                        $insurer_discount = round($discountvalue['insurer_discount'] ?? 0);
                    }
                }
            }

            $aai_discount = 0;
            $antitheft_discount = 0;
            $ic_vehicle_discount = $insurer_discount;
            
            $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;

            // $ncbvalue = $total_own_damage * ($requestData->applicable_ncb / 100);

            $final_od_premium = $total_own_damage + $total_accessories_amount;
            $final_tp_premium = $tp_pcv + $unnamed_passenger_cover + $paid_driver + $tp_cng_pcv + $ll_paid_driver_cover;
            $final_total_discount = $ncbvalue + $antitheft_discount + $aai_discount + $ic_vehicle_discount;
            $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount;

            $service_tax_amount = $final_net_premium * 0.18;

            $final_payable_amount = $final_net_premium + $service_tax_amount;

            $applicable_addons = ['imt23'];

            if ($mmv_data['seating_capacity'] <= 6)
            {
                array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
            }

            $data_response = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Data' => [
                    'idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'min_idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['min'],
                    'max_idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['max'],
                    'default_idv' => $premium_type == 'third_party' ? 0 : $default_idv,
                    'qdata' => NULL,
                    'pp_enddate' => $prev_policy_end_date,
                    'addonCover' => NULL,
                    'addon_cover_data_get' => '',
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => $premium_type == 'third_party' ? 'Third Party' : ($premium_type == 'short_term_3' || $premium_type == 'short_term_6' ? 'Short Term' : 'Comprehensive'),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => 0,
                    'version_id' => $get_mapping_mmv_details->version_id,
                    'selected_addon' => [],
                    'showroom_price' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'fuel_type' => $requestData->fuel_type,
                    'vehicle_idv' => $premium_type == 'third_party' ? 0 : $vehicle_idv,
                    'ncb_discount' => $result['result']['policy']['new_ncb'] ?? 0,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
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
                        'car_age' => $car_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => round($insurer_discount) 
                    ],
                    'basic_premium' => $total_own_damage,
                    'deduction_of_ncb' => $ncbvalue,
                    'tppd_premium_amount' => $tp_pcv,
                    'motor_electric_accessories_value' => $motor_electric_accessories_value,
                    'motor_non_electric_accessories_value' => 0,
                    'motor_lpg_cng_kit_value' => 0,
                    'cover_unnamed_passenger_value' => $unnamed_passenger_cover,
                    'seating_capacity' => $mmv_data['seating_capacity'],
                    'default_paid_driver' => $ll_paid_driver_cover,
                    'll_paid_driver_premium'    => $ll_paid_driver_cover,
                    'll_paid_conductor_premium' => 0,
                    'll_paid_cleaner_premium'   => 0,
                    'motor_additional_paid_driver' => 0,
                    'compulsory_pa_own_driver' => $cpa_owner_driver,
                    'total_accessories_amount(net_od_premium)' => $total_accessories_amount,
                    'total_own_damage' => $final_od_premium,
                    'cng_lpg_tp' => $tp_cng_pcv,
                    'total_liability_premium' => $premium_type == 'short_term_3' || $premium_type == 'short_term_6' ? $final_tp_premium : ($result['result']['plans'][0]['tenures'][0]['covers'][1]['premium']['gross']['value'] ?? 0),
                    'net_premium' => $premium_type == 'short_term_3' || $premium_type == 'short_term_6' ? $final_total_discount : $result['result']['plans'][0]['tenures'][0]['premium']['net']['value'],
                    'service_tax_amount' => $premium_type == 'short_term_3' || $premium_type == 'short_term_6' ? $service_tax_amount : $result['result']['plans'][0]['tenures'][0]['premium']['gst']['value'],
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => $premium_type == 'short_term_3' || $premium_type == 'short_term_6' ? $final_payable_amount : $result['result']['plans'][0]['tenures'][0]['premium']['gross']['value'],
                    'antitheft_discount' => 0,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => $final_total_discount,
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => round($final_net_premium * 0.18),
                    'final_payable_amount' => round($final_net_premium + ($final_net_premium * 0.18)),
                    'service_data_responseerr_msg' => $result['success'],
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => 'Rollover',
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
                    "max_addons_selection"=> NULL,
                    'add_ons_data' =>   [
                        'in_built'   => [],
                        'additional' => [
                            'zero_depreciation' => 0,
                            'road_side_assistance' => 0,
                            'imt_23' => $imt_23
                        ],
                        'in_built_premium' => 0,
                        'additional_premium' => 0,
                        'other_premium' => 0
                    ],
                    'applicable_addons' => $applicable_addons
                ]
            ];      

            if ($requestData->edit_idv == 0 && $requestData->edit_idv == '') {
                $data_response['Data']['vehicle_idv'] = $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['recommended'];
            }

            if ($premium_type == 'short_term_3' || $premium_type == 'short_term_6') {
                $data_response['Data']['premiumTypeCode'] = $premium_type;
            }
        } else {
            $messages = '';

            if (isset($result['result']['field_errors'])) {
                foreach ($result['result']['field_errors'] as $field => $field_error) {
                    $messages = $messages.$field_error['msg'].'. ';
                }
            } else if (isset($result['result']['code'])) {
                $messages = $result['result']['code'];
            } else {
                $messages = 'Service Temporarily Unavailable';
            }

            $data_response = [
                'status' => false,
                'premium' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $messages
            ];
        }
    } else {
        return [
            'status' => false,
            'premium' => 0,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'msg' => 'Insurer not reachable'
        ];
    }

    return camelCase($data_response);
}
