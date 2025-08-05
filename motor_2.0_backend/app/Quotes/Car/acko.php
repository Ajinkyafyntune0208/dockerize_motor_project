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
                'message' => 'New Business Not Allowed.',
                'policy_type' => $requestData->policy_type
            ]
        ];
    }

    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
    $motor_manf_year = $motor_manf_year_arr[1];
    $motor_manf_month = $motor_manf_year_arr[0];

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
    $mmv_data = get_mmv_details($productData,$requestData->version_id, $productData->company_alias);

    if($mmv_data['status'] == 1)
    {
      $mmv_data = $mmv_data['data'];
    }
    else
    {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv_data['message'],
            'request' => [
                'mmv' => $mmv_data
            ]
        ];
    }

    $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv_data,CASE_LOWER);
 $mmv_details=[];
 $mmv_details['manf_name'] = $get_mapping_mmv_details->vehicle_manufacturer;
 $mmv_details['model_name'] = $get_mapping_mmv_details->vehicle_model_name;
 $mmv_details['version_name'] = $get_mapping_mmv_details->variant;
 $mmv_details['seating_capacity'] = $get_mapping_mmv_details->seating_capacity;
 $mmv_details['cubic_capacity'] = $get_mapping_mmv_details->cubic_capacity;
 $mmv_details['fuel_type'] = $get_mapping_mmv_details->fuel_type;
 $mmv_details['version_id'] = $get_mapping_mmv_details->ic_version_code;

     if (empty($get_mapping_mmv_details)) {
         return [
             'status' => false,
             'premium' => 0,
             'msg' => 'Vehicle does not exist with insurance company',
            'request' => [
                'message' => 'Vehicle does not exist with insurance company',
                'mmv_data' => $mmv_data
            ]
         ];
     } elseif ($get_mapping_mmv_details->seating_capacity > 6) {
         return [
             'status' => false,
             'premium' => 0,
             'msg' => 'Premium not available for vehicle with seating capacity greater than 6',
            'request' => [
                'message' => 'Premium not available for vehicle with seating capacity greater than 6.',
                'mmv_data' => $mmv_data
            ]
         ];
     }
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
                'message' => 'RTO code does not exist',
                'rto_code' => $requestData->rto_code
            ]
        ];
    }
    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
        ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
        ->first();
 
    $is_previous_policy_expired = strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date);

    $previous_policy_expiry_date = new \DateTime($requestData->previous_policy_expiry_date);

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        $i=0;
        $addon_array=[];
      if($premium_type !='third_party')
      { 
        if($requestData->vehicle_owner_type == 'C')
        {
            $addon_array[$i++]['id'] = 'zero_depreciation_car';
            $addon_array[$i++]['id'] = 'engine_protect_car';
        }
        else{
            $addon_array[$i++]['id'] = 'pa_owner_car';
            $addon_array[$i++]['id'] = 'zero_depreciation_car';
            $addon_array[$i++]['id'] = 'engine_protect_car';
        }
    }
    else
    {
        $addon_array[$i++]['id'] = 'pa_owner_car';
    } 
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $data) {
                if ($data['name'] == 'LL paid driver') {
                    $addon_array[$i++]['id'] = 'legal_liability_car';
                }
            }
        }
        $new_addon_array=json_encode($addon_array,true);
        $json_addon = json_decode(stripslashes($new_addon_array));
        $applicable_addons = [
            'zeroDepreciation', 'engineProtector'
        ];
   
        $version_id = str_replace('"', '', $mmv_data['ic_version_code']);

        $vehicle_registration = explode('-', $requestData->vehicle_registration_no ?? $requestData->rto_code.'-'.substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 2).'-'.substr(str_shuffle('1234567890'), 1, 4));

        $vehicle_registration_no = $vehicle_registration[0].$vehicle_registration[1].$vehicle_registration[2].$vehicle_registration[3];
    $quote_request_array = [
        'product' => 'car',
        'user' => [
            'is_corporate' => $requestData->vehicle_owner_type == 'C' ? true : false,
            'name' => $requestData->user_fname,
            'email' => $requestData->user_email,
            'phone' => $requestData->user_mobile
        ],
        'vehicle' => [
            'variant_id' => (int)$mmv_data['ic_version_code'],
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
            'registration_type' => 'private'
        ],
        'policy' => [
            'plan_id' => $premium_type == 'third_party' ? 'car_tp':(($premium_type == "own_damage") ? 'car_od': 'car_comprehensive'),
            'tenure' => 1,
            'addons' => $json_addon
        ]
    ];
    if($premium_type == 'third_party'){
        $quote_request_array['vehicle']['previous_policy']['is_claim'] = false;
    }
    if($premium_type == "own_damage")
    {
        $quote_request_array['vehicle']['registration_month'] = (int) $motor_manf_month;
        if((int)$is_previous_policy_expired == 0)
        {
            $quote_request_array['vehicle']['previous_policy']['od_expiry_date'] = date('Y-m-d\TH:i:sP');
            $quote_request_array['vehicle']['previous_policy']['expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
        }else
        {
            $quote_request_array['vehicle']['previous_policy']['od_expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
            $quote_request_array['vehicle']['previous_policy']['expiry_date'] = null;
        }
    }
    $quote_result = getWsData(config('constants.IcConstants.acko.ACKO_QUOTE_WEB_SERVICE_URL'), $quote_request_array, 'acko', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'Quote'
        ]
    );
    if ($quote_result['response']) {
        $result = json_decode($quote_result['response'], TRUE);
        if (isset($result['success']) && $result['success']) {
            update_quote_web_servicerequestresponse($quote_result['table'], $quote_result['webservice_id'], "Premium Calculation Success", "Success" );

            $vehicle_idv = ($result['result']['policy']['idv']['calculated']);
            $min_idv = ($result['result']['policy']['idv']['min']);
            $max_idv = ($result['result']['policy']['idv']['max']);
            $default_idv = ($result['result']['policy']['idv']['calculated']);

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
            $quote_result = getWsData(config('constants.IcConstants.acko.ACKO_QUOTE_WEB_SERVICE_URL'), $quote_request_array, 'acko', [
                'section' => $productData->product_sub_type_code,
                'method' => 'Premium Recalculation',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name,
                'transaction_type' => 'Quote'
            ]
        );

        if ($quote_result['response']) {
            $result = json_decode($quote_result['response'], TRUE);

            if (isset($result['success']) && $result['success']) {
                $vehicle_idv = ($result['result']['policy']['idv']['calculated']);
                $default_idv = ($result['result']['policy']['idv']['calculated']);
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
                    'webservice_id' => $quote_result['webservice_id'],
                    'table' => $quote_result['table'],
                    'status' => false,
                    'premium' => 0,
                    'msg' => $messages
                ];
            }
        } else {
            return [
                'webservice_id' => $quote_result['webservice_id'],
                'table' => $quote_result['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Insurer not reachable'
            ];
        }
            $motor_electric_accessories_value = 0;
            $motor_non_electric_accessories_value = 0;

            $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;

            $paid_driver = 0;
            $total_own_damage = 0;
            $tp_car = 0;
            $ncbvalue = 0;
            $insurer_discount = 0;
            $tp_cng_car = 0;
            $cpa_owner_driver = 0;
            $zero_depreciation_car=0;
            $engine_protect_car=0;
            $consumables = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            foreach ($result['result']['plans'][0]['tenures'][0]['covers'] as $responsekey => $responsevalue) {
                if ($responsevalue["id"] == "pa_owner_car") {
                    $cpa_owner_driver = ($responsevalue['premium']['net']['breakup'][0]['value']);
                } elseif ($responsevalue["id"] == "own_damage_basic_car") {
                    $total_own_damage = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }elseif ($responsevalue["id"] == "zero_depreciation_car") {
                    $zero_depreciation_car = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }
                elseif ($responsevalue["id"] == "engine_protect_car") {
                    $engine_protect_car = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }
                 elseif ($responsevalue["id"] == "third_party_car") {
                    $tp_car = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }  elseif ($responsevalue["id"] == "third_party_cng_car") {
                    $tp_cng_car = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }elseif ($responsevalue["id"] == "consumables_car") {
                    $consumables = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }elseif ($responsevalue["id"] == "legal_liability_car") {
                    $paid_driver = ($responsevalue['premium']['net']['breakup'][0]['value']);
                }
            }

            if ($result['result']['plans'][0]['tenures'][0]['premium']['discount']['value'] > 0) {
                foreach ($result['result']['plans'][0]['tenures'][0]['premium']['discount']['breakup'] as $discountkey => $discountvalue) {
                    if ($discountvalue["id"] == "ncb") {
                        $ncbvalue = ($discountvalue['value']);
                    } elseif ($discountvalue["id"] == "insurer_discount") {
                        $insurer_discount = ($discountvalue['value'] ?? 0);
                    }
                }
            }


            $aai_discount = 0;
            $antitheft_discount = 0;
            $ic_vehicle_discount = $insurer_discount;
            $final_od_premium = $total_own_damage + $total_accessories_amount;
            $final_tp_premium = $tp_car + ($result['vPAForUnnamedPassengerPremium'] ?? 0) + $paid_driver + $tp_cng_car;
            $final_total_discount = $ncbvalue + $antitheft_discount + $aai_discount + $ic_vehicle_discount;
            $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount + $consumables;

            $data_response = [
                'webservice_id' => $quote_result['webservice_id'],
                'table' => $quote_result['table'],
                'status' => true,
                'msg' => 'Found',
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
                    'policy_type' => $premium_type == 'third_party' ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => 0,
                    'version_id' => $get_mapping_mmv_details->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'fuel_type' => $requestData->fuel_type,
                    'vehicle_idv' => $premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'ncb_discount' => $result['result']['policy']['new_ncb'] ?? 0,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => $mmv_details,
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
                    'ic_vehicle_discount' => ($insurer_discount),
                    'basic_premium' => $total_own_damage,
                    'deduction_of_ncb' => $ncbvalue,
                    'tppd_premium_amount' => $tp_car,
                    'motor_electric_accessories_value' => 0,
                    'motor_non_electric_accessories_value' => 0,
                    'motor_lpg_cng_kit_value' => 0,
                    'cover_unnamed_passenger_value' => $result['vPAForUnnamedPassengerPremium'] ?? 0,
                    'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                    'default_paid_driver' => $paid_driver,
                    'motor_additional_paid_driver' => 0,
                    'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
                    'compulsory_pa_own_driver' => $cpa_owner_driver,
                    'total_accessories_amount(net_od_premium)' => $total_accessories_amount,
                    'total_own_damage' => $final_od_premium,
                    'cng_lpg_tp' => $tp_cng_car,
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
                    'applicable_addons' =>$applicable_addons,
                    'add_ons_data' =>   [
                        'in_built'   => [
                            'consumables' =>$consumables,
                        ],
                        'additional' => [
                            'zero_depreciation' => $zero_depreciation_car,
                            'road_side_assistance' => 0,
                            'engineProtector'      => $engine_protect_car
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
                'webservice_id' => $quote_result['webservice_id'],
                'table' => $quote_result['table'],
                'status' => false,
                'premium' => 0,
                'msg' => $messages
            ];
        }
    } else {
        return [
            'webservice_id' => $quote_result['webservice_id'],
            'table' => $quote_result['table'],
            'status' => false,
            'premium' => 0,
            'msg' => 'Insurer not reachable'
        ];
    }

    return camelCase($data_response);
}
