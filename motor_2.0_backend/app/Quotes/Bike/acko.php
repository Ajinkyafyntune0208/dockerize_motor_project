<?php

use Illuminate\Support\Facades\DB;
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
    if ($requestData->policy_type == 'newbusiness') {
        return [
            'status' => false,
            'premium' => 0,
            'msg' => 'New Business Not Allowed.'
        ];
    }

    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
    $motor_manf_year = $motor_manf_year_arr[1];

    $current_date = date('Y-m-d');
    $policy_start_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date.' + 1 days'));
    $prev_policy_end_date = implode('', explode('-', date('Y-m-d', strtotime($requestData->previous_policy_expiry_date))));

    $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 1 year'));

    if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
        $policy_start_date = date('Y-m-d');
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

    $get_mapping_mmv_details = DB::table('ic_version_mapping AS icvm')
        ->leftJoin('cv_acko_model_master AS camm', 'icvm.ic_version_code', '=', 'camm.version_id')
        ->where('icvm.fyn_version_id', $requestData->version_id)
        ->where('icvm.ic_id', $productData->company_id)
        ->first();

    if (empty($get_mapping_mmv_details)) {
        return [
            'status' => false,
            'premium' => 0,
            'msg' => 'Vehicle does not exist with insurance company'
        ];
    } elseif ($get_mapping_mmv_details->seating_capacity > 6) {
        return [
            'status' => false,
            'premium' => 0,
            'msg' => 'Premium not available for vehicle with seating capacity greater than 6'
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
            'msg' => 'RTO code does not exist'
        ];
    }

    $is_previous_policy_expired = strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date);

    $previous_policy_expiry_date = new \DateTime($requestData->previous_policy_expiry_date);

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $quote_request_array = [
        'product' => 'car',
        'user' => [
            'is_corporate' => $requestData->vehicle_owner_type == 'C' ? true : false,
            'name' => $requestData->user_fname,
            'email' => $requestData->user_email,
            'phone' => $requestData->user_mobile
        ],
        'vehicle' => [
            'variant_id' => $mmv_data['version_id'],
            'is_new' => false,
            'registration_year' => (int)$motor_manf_year,
            'rto_code' => $requestData->rto_code,
            'is_external_cng_kit' => false,
            'previous_policy' => [
                'is_expired' => $is_previous_policy_expired,
                'expiry_date' => $previous_policy_expiry_date->format('Y-m-d\TH:i:sP'),
                'is_claim' => $requestData->is_claim == 'Y' ? true : false,
                'ncb' => $premium_type == 'third_party' ? 0 : (int)$requestData->previous_ncb
            ],
            'registration_type' => 'commercial'
        ],
        'policy' => [
            'plan_id' => $premium_type == 'third_party' ? 'pcv_third_party' : 'pcv_comprehensive',
            'tenure' => 1,
            'addons' => [
                [
                    'id' => 'pa_owner_pcv'
                ]
            ]
        ]
    ];

    if ($requestData->vehicle_owner_type == 'C') {
        unset($quote_request_array['policy']['addons']);
    }

    if ($requestData->edit_idv > 0 && $premium_type != 'third_party') {
        $quote_request_array['policy']['idv'] = $requestData->edit_idv;
    }

    $get_response = getWsData(config('constants.IcConstants.acko.ACKO_QUOTE_WEB_SERVICE_URL'), $quote_request_array, 'acko', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_sub_type_name,
            'transaction_type' => 'Quote'
        ]
    );

    $quote_result=$get_response['response'];
    if ($quote_result) {
        $result = json_decode($quote_result, TRUE);

        if (isset($result['success']) && $result['success']) {
            $motor_electric_accessories_value = 0;
            $motor_non_electric_accessories_value = 0;

            $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;

            $paid_driver = 0;
            $total_own_damage = 0;
            $tp_pcv = 0;
            $ncbvalue = 0;
            $insurer_discount = 0;
            $tp_cng_pcv = 0;
            $cpa_owner_driver = 0;

            foreach ($result['result']['plans'][0]['tenures'][0]['covers'] as $responsekey => $responsevalue) {
                if ($responsevalue["id"] == "pa_owner_pcv") {
                    $cpa_owner_driver = ($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "own_damage_pcv") {
                    $total_own_damage = ($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "tp_pcv") {
                    $tp_pcv = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }  elseif ($responsevalue["id"] == "tp_cng_pcv") {
                    $tp_cng_pcv = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }
            }

            if ($result['result']['plans'][0]['tenures'][0]['premium']['discount']['value'] > 0) {
                foreach ($result['result']['plans'][0]['tenures'][0]['premium']['discount']['breakup'] as $discountkey => $discountvalue) {
                    if ($discountvalue["id"] == "ncb") {
                        $ncbvalue = ($discountvalue['value']);
                    } elseif ($discountvalue["id"] == "insurer_discount") {
                        $insurer_discount = ($discountvalue['insurer_discount'] ?? 0);
                    }
                }
            }

            $aai_discount = 0;
            $antitheft_discount = 0;
            $ic_vehicle_discount = $insurer_discount;

            $final_od_premium = $total_own_damage + $total_accessories_amount;
            $final_tp_premium = $tp_pcv + ($result['vPAForUnnamedPassengerPremium'] ?? 0) + $paid_driver + $tp_cng_pcv;
            $final_total_discount = $ncbvalue + $antitheft_discount + $aai_discount + $ic_vehicle_discount;
            $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount;

            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'min_idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['min'],
                    'max_idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['max'],
                    'qdata' => NULL,
                    'pp_enddate' => $prev_policy_end_date,
                    'addonCover' => NULL,
                    'addon_cover_data_get' => '',
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => $premium_type == 'third_party' ? 'Third Party' : 'Comprehensive',
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => 0,
                    'version_id' => $get_mapping_mmv_details->version_id,
                    'selected_addon' => [],
                    'showroom_price' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'fuel_type' => $requestData->fuel_type,
                    'vehicle_idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
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
                        'ic_vehicle_discount' => ($insurer_discount) 
                    ],
                    'basic_premium' => $total_own_damage,
                    'deduction_of_ncb' => $result['result']['plans'][0]['tenures'][0]['premium']['discount']['breakup'][0]['value'] ?? 0,
                    'tppd_premium_amount' => $tp_pcv,
                    'motor_electric_accessories_value' => 0,
                    'motor_non_electric_accessories_value' => 0,
                    'motor_lpg_cng_kit_value' => '0',
                    'cover_unnamed_passenger_value' => $result['vPAForUnnamedPassengerPremium'] ?? 0,
                    'seating_capacity' => $mmv_data['seating_capacity'],
                    'default_paid_driver' => 0,
                    'motor_additional_paid_driver' => 0,
                    'compulsory_pa_own_driver' => $cpa_owner_driver,
                    'total_accessories_amount(net_od_premium)' => $total_accessories_amount,
                    'total_own_damage' => $final_od_premium,
                    'cng_lpg_tp' => $tp_cng_pcv,
                    'total_liability_premium' => $result['result']['plans'][0]['tenures'][0]['covers'][1]['premium']['gross']['value'] ?? 0,
                    'net_premium' => $result['result']['plans'][0]['tenures'][0]['premium']['net']['value'],
                    'service_tax_amount' => $result['result']['plans'][0]['tenures'][0]['premium']['gst']['value'],
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => $result['result']['plans'][0]['tenures'][0]['premium']['gross']['value'],
                    'antitheft_discount' => 0,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => $final_total_discount,
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => ($final_net_premium * 0.18),
                    'final_payable_amount' => ($final_net_premium + ($final_net_premium * 0.18)),
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
                            'road_side_assistance' => 0
                        ]
                    ]
                ]
            ];      

            if ($requestData->edit_idv == 0 && $requestData->edit_idv == '') {
                $data_response['Data']['vehicle_idv'] = $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['recommended'];
            }
        } else {
            $messages = '';

            if (isset($result['result']['field_errors'])) {
                foreach ($result['result']['field_errors'] as $field => $field_error) {
                    $messages = $messages.$field_error['msg'].'. ';
                }
            } else if (isset($result['result']['msg'])) {
                $messages = $result['result']['msg'];
            } else {
                $messages = 'Service Temporarily Unavailable';
            }

            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'premium' => 0,
                'msg' => $messages
            ];
        }
    } else {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'status' => false,
            'premium' => 0,
            'msg' => 'Insurer not reachable'
        ];
    }

    return camelCase($data_response);
}
