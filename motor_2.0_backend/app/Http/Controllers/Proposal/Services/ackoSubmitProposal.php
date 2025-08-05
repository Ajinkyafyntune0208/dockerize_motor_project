<?php

namespace App\Http\Controllers\Proposal\Services;

use Config;
use DB;
use App\Models\UserProposal;
use App\Models\IcVersionMapping;
use App\Models\MasterRto;
use App\Models\SelectedAddons;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

class ackoSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));

        $product_data = getProductDataByIc($request['policyId']);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $product_data->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $current_date = date('Y-m-d');
        $policy_start_date = date('Y-m-d', strtotime($quote_data->previous_policy_expiry_date.' + 1 days'));

        if (strtotime($quote_data->previous_policy_expiry_date) < strtotime($current_date)) {
            $policy_start_date = date('Y-m-d');
        }

        $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 1 year'));

        if ($premium_type == 'short_term_3') {
            $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 3 month'));
        } elseif ($premium_type == 'short_term_6') {
            $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 6 month'));
        }

        $vehicle_in_90_days = 0;

        if (isset($policy_start_date)) {
            $vehicle_in_90_days = (strtotime(date('Y-m-d')) - strtotime($policy_start_date)) / (60*60*24);
            
            if ($vehicle_in_90_days > 90) {
                $quote_data->previous_ncb = 0;
            }
        }

        /* if (isset($proposal->ownership_changed) && $proposal->ownership_changed == 'Y') {
            $quote_data->previous_ncb = 0;
        } */

//        $get_mapping_mmv_details = IcVersionMapping::leftJoin('cv_acko_model_master AS camm', 'ic_version_code', '=', 'camm.version_id')
//            ->where('fyn_version_id', $quote_data->version_id)
//            ->where('ic_id', $product_data->company_id)
//            ->first();
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
    	$requestData = getQuotation($enquiryId);
    	$productData = getProductDataByIc($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        
        $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
        $motor_manf_year = $motor_manf_year_arr[1];

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
                'message' => $mmv['message']
            ];          
        }
        $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($get_mapping_mmv_details)) {
            return [
                'status' => false,
                'msg' => 'Vehicle does not exist with insurance company'
            ];
        }

        $mmv_data['version_id'] = $get_mapping_mmv_details->version_id;

        $rto_data = MasterRto::where('rto_code', $quote_data->rto_code)
            ->where('status', 'Active')
            ->first();

        if (empty($rto_data)) {
            return [
                'status' => false,
                'msg' => 'RTO code does not exist'
            ];
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

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
                    'msg' => 'External CNG kit value should not be greater than 50000'
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

        $is_previous_policy_expired = strtotime($quote_data->previous_policy_expiry_date) < strtotime(date('Y-m-d'));

        $previous_policy_expiry_date = new \DateTime($quote_data->previous_policy_expiry_date);

        $proposal->vehicale_registration_number = str_replace('--', '-', $proposal->vehicale_registration_number);
        $vehicle_registration_no_array = explode('-', $proposal->vehicale_registration_number);

        $vehicle_registration_number = $vehicle_registration_no_array[0] . $vehicle_registration_no_array[1] . strtolower($vehicle_registration_no_array[2]) . (isset($vehicle_registration_no_array[3]) ? $vehicle_registration_no_array[3] : '');

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

        $is_imt_23 = false;

        if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '')
        {
            foreach ($selected_addons->applicable_addons as $applicable_addon)
            {
                if ($applicable_addon['name'] == 'IMT - 23')
                {
                    $is_imt_23 = true;
                }
            }
        }

        $premium_calculation_request = [
            'product' => 'car',
            'user' => [
                'pincode' => $proposal->pincode,
                'is_corporate' => $quote_data->vehicle_owner_type == 'C' ? true : false,
                'name' => $proposal->first_name." ".$proposal->last_name,
                'email' => $proposal->email,
                'phone' => $proposal->mobile_number
            ],
            'vehicle' => [
                'variant_id' => (int) $mmv_data['version_id'],
                'is_new' => false,
                'registration_month' => (int)date('m', strtotime($quote_data->vehicle_register_date)),
                'registration_year' => (int)date('Y', strtotime($quote_data->vehicle_register_date)),
                'manufacturing_year' => (int)$motor_manf_year,
                'rto_code' => $quote_data->rto_code,
                'registration_number' => $vehicle_registration_number,
                'engine_number' => $proposal->engine_number,
                'chassis_number' => $proposal->chassis_number,
                'is_external_cng_kit' => $is_external_cng_kit,
                'cng_kit_value' => $cng_kit_value,
                'previous_policy' => [
                    'is_expired' => $is_previous_policy_expired,
                    'expiry_date' => $previous_policy_expiry_date->format('Y-m-d\TH:i:sP'),
                    'is_claim' => $quote_data->is_claim == 'Y' ? true : false,
                    'number' => $proposal->previous_policy_number,
                    'insurer_name' => $proposal->previous_insurance_company,
                    'ncb' => $premium_type == 'third_party' ? 0 : (int)$quote_data->previous_ncb,
                    'plan_id' =>  $requestData->previous_policy_type == 'Third-party' ? 'pcv_third_party' : 'pcv_comprehensive'
                ],
                'registration_type' => 'commercial',
                'vehicle_type' => 'car'
            ],
            'policy' => [
                'plan_id' => $premium_type == 'third_party' ? 'pcv_third_party' : 'pcv_comprehensive',
                'tenure' => 1,
                'addons' => []
            ]
        ];

        if ($proposal->gst_number != "") {
            $premium_calculation_request['user']['gst_info']['number'] = $proposal->gst_number;
        }

        if ($proposal->is_vehicle_finance) {
            $premium_calculation_request['vehicle']['hypothecation']['is_financed'] = true;
            $premium_calculation_request['vehicle']['hypothecation']['name'] = $proposal->name_of_financer;
            $premium_calculation_request['vehicle']['hypothecation']['finance_type'] = $proposal->financer_agreement_type;
        }

        if (isset($selected_addons->compulsory_personal_accident[0]['name']) && $quote_data->vehicle_owner_type == 'I') {
            array_push($premium_calculation_request['policy']['addons'], [
                'id' => 'pa_owner_pcv'
            ]);

            $premium_calculation_request['user']['nominee'] = [
                'name' => $proposal->nominee_name,
                'age' => (int) $proposal->nominee_age,
                'relationship' => $proposal->nominee_relationship
            ];
        }

        if (!$is_external_cng_kit) {
            unset($premium_calculation_request['vehicle']['cng_kit_value']);
        }

        if ($premium_type != 'third_party') {
            $premium_calculation_request['policy']['idv'] = $quote->idv;
        }

        if ($proposal->nominee_name == '') {
            unset($premium_calculation_request['user']['nominee']);
        }

        if ($unnamed_passenger) {
            array_push($premium_calculation_request['policy']['addons'], [
                'id' => 'unnamed_passenger_pcv',
                'sum_insured' => $unnamed_passenger_sum_insured
            ]);
        }
    
        if ($ll_paid_driver) {
            array_push($premium_calculation_request['policy']['addons'], [
                'id' => 'imt_40_pcv',
                'sum_insured' => $ll_paid_driver_sum_insured
            ]);
        }

        if ($electrical_accessories) {
            array_push($premium_calculation_request['policy']['addons'], [
                'id' => 'electrical_accessories_pcv',
                'sum_insured' => $electrical_accessories_value
            ]);
        }

        if ($is_imt_23)
        {
            array_push($premium_calculation_request['policy']['addons'], [
                'id' => 'imt_23_pcv'
            ]);
        }
    
        if ($premium_type == 'short_term_3') {
            $premium_calculation_request['policy']['tenure'] = 3;
            $premium_calculation_request['policy']['tenure_unit'] = 'MONTH';
        } elseif ($premium_type == 'short_term_6') {
            $premium_calculation_request['policy']['tenure'] = 6;
            $premium_calculation_request['policy']['tenure_unit'] = 'MONTH';
        }
    
        $get_response = getWsData(Config::get('constants.IcConstants.acko.ACKO_QUOTE_WEB_SERVICE_URL'), $premium_calculation_request, 'acko', [
                'section' => $product_data->product_sub_type_code,
                'method' => 'Premium Calculation',
                'requestMethod' => 'post',
                'enquiryId' => customDecrypt($request['userProductJourneyId']),
                'productName' => $product_data->product_name,
                'transaction_type' => 'Proposal'
            ]
        );
        $quote_response = $get_response['response'];

        if ($quote_response) {
            $quote_result = json_decode($quote_response, TRUE);

            if (isset($quote_result['success']) && $quote_result['success']) {
                $proposal_submit_request = [
                    'product' => 'car',
                    'user' => [
                        'pincode' => $proposal->pincode,
                        'is_corporate' => $quote_data->vehicle_owner_type == 'C' ? true : false,
                        'name' => $proposal->first_name." ".$proposal->last_name,
                        'email' => $proposal->email,
                        'phone' => $proposal->mobile_number,
                        'communication_address' => $proposal->address_line1.", ".$proposal->address_line2.", ".$proposal->address_line3.", ".$proposal->city.", ".$proposal->pincode
                    ],
                    'vehicle' => [
                        'variant_id' => (int) $mmv_data['version_id'],
                        'is_new' => false,
                        'registration_month' => (int)date('m', strtotime($quote_data->vehicle_register_date)),
                        'registration_year' => (int)date('Y', strtotime($quote_data->vehicle_register_date)),
                        'manufacturing_year' => (int)$motor_manf_year,
                        'rto_code' => $quote_data->rto_code,
                        'registration_number' => $vehicle_registration_number,
                        'engine_number' => $proposal->engine_number,
                        'chassis_number' => $proposal->chassis_number,
                        'is_external_cng_kit' => $is_external_cng_kit,
                        'cng_kit_value' => $cng_kit_value,
                        'previous_policy' => [
                            'is_expired' => $is_previous_policy_expired,
                            'expiry_date' => $previous_policy_expiry_date->format('Y-m-d\TH:i:sP'),
                            'is_claim' => $quote_data->is_claim == 'Y' ? true : false,
                            'number' => $proposal->previous_policy_number,
                            'insurer_name' => $proposal->previous_insurance_company,
                            'ncb' => $premium_type == 'third_party' ? 0 : (int)$quote_data->previous_ncb,
                            'plan_id' =>  $requestData->previous_policy_type == 'Third-party' ? 'pcv_third_party' : 'pcv_comprehensive'
                        ],
                        'registration_type' => 'commercial',
                        'vehicle_type' => 'car'
                    ],
                    'policy' => [
                        'plan_id' => $premium_type == 'third_party' ? 'pcv_third_party' : 'pcv_comprehensive',
                        'tenure' => 1,
                        'addons' => []
                    ]
                ];

                if ($proposal->gst_number != "") {
                    $proposal_submit_request['user']['gst_info']['number'] = $proposal->gst_number;
                }

                if ($proposal->pan_number != "") {
                    $proposal_submit_request['user']['pan_number'] = $proposal->pan_number;
                }

                if ($proposal->is_vehicle_finance) {
                    $proposal_submit_request['vehicle']['hypothecation']['is_financed'] = true;
                    $proposal_submit_request['vehicle']['hypothecation']['name'] = $proposal->name_of_financer;
                    $proposal_submit_request['vehicle']['hypothecation']['finance_type'] = $proposal->financer_agreement_type;
                }

                if (isset($selected_addons->compulsory_personal_accident[0]['name']) && $quote_data->vehicle_owner_type == 'I') {
                    array_push($proposal_submit_request['policy']['addons'], [
                        'id' => 'pa_owner_pcv'
                    ]);

                    $proposal_submit_request['user']['nominee'] = [
                        'name' => $proposal->nominee_name,
                        'age' => (int) $proposal->nominee_age,
                        'relationship' => $proposal->nominee_relationship
                    ];
                }

                if (!$is_external_cng_kit) {
                    unset($proposal_submit_request['vehicle']['cng_kit_value']);
                }

                if ($premium_type != 'third_party') {
                    $proposal_submit_request['policy']['idv'] = $quote_result['result']['policy']['idv']['calculated'];
                }

                if ($proposal->nominee_name == '') {
                    unset($proposal_submit_request['user']['nominee']);
                }

                if ($unnamed_passenger) {
                    array_push($proposal_submit_request['policy']['addons'], [
                        'id' => 'unnamed_passenger_pcv',
                        'sum_insured' => $unnamed_passenger_sum_insured
                    ]);
                }
            
                if ($ll_paid_driver) {
                    array_push($proposal_submit_request['policy']['addons'], [
                        'id' => 'imt_40_pcv',
                        'sum_insured' => $ll_paid_driver_sum_insured
                    ]);
                }

                if ($electrical_accessories) {
                    array_push($proposal_submit_request['policy']['addons'], [
                        'id' => 'electrical_accessories_pcv',
                        'sum_insured' => $electrical_accessories_value
                    ]);
                }

                if ($is_imt_23)
                {
                    array_push($proposal_submit_request['policy']['addons'], [
                        'id' => 'imt_23_pcv'
                    ]);
                }
            
                if ($premium_type == 'short_term_3') {
                    $proposal_submit_request['policy']['tenure'] = 3;
                    $proposal_submit_request['policy']['tenure_unit'] = 'MONTH';
                } elseif ($premium_type == 'short_term_6') {
                    $proposal_submit_request['policy']['tenure'] = 6;
                    $proposal_submit_request['policy']['tenure_unit'] = 'MONTH';
                }

                $get_response = getWsData(Config::get('constants.IcConstants.acko.ACKO_PROPOSAL_WEB_SERVICE_URL'), $proposal_submit_request, 'acko', [
                        'section' => $product_data->product_sub_type_code,
                        'method' => 'Proposal Generation',
                        'requestMethod' => 'post',
                        'enquiryId' => customDecrypt($request['userProductJourneyId']),
                        'productName' => $product_data->product_name,
                        'transaction_type' => 'Proposal'
                    ]
                );
                $proposal_submit_response = $get_response['response'];

                if ($proposal_submit_response) {
                    $proposal_submit_result = json_decode($proposal_submit_response, TRUE);

                    if (isset($proposal_submit_result['success']) && $proposal_submit_result['success']) {
                        $imt_23 = 0;

                        $motor_electric_accessories_value = 0;
                        $motor_non_electric_accessories_value = 0;

                        $paid_driver = 0;
                        $total_own_damage = 0;
                        $tppd_premium_amount = 0;
                        $ncbvalue = 0;
                        $ic_vehicle_discount = 0;
                        $antitheft_discount = 0;
                        $aai_discount = 0;
                        $tp_cng_pcv = 0;
                        $cpa_owner_driver = 0;
                        $unnamed_passenger_cover = 0;
                        $ll_paid_driver_cover = 0;
                        $addon_premium = 0;

                        foreach ($proposal_submit_result['result']['policy']['covers'] as $responsekey => $responsevalue) {
                            if ($responsevalue['id'] == 'pa_owner_pcv') {
                                $cpa_owner_driver = round($responsevalue['premium']['net']['breakup'][0]['value']);
                            } elseif ($responsevalue['id'] == 'own_damage_pcv') {
                                $total_own_damage = round($responsevalue['premium']['net']['breakup'][0]['value']);
                            } elseif ($responsevalue['id'] == 'tp_pcv') {
                                $tppd_premium_amount = round($responsevalue['premium']['net']['breakup'][0]['value']);
                            }  elseif ($responsevalue["id"] == "tp_cng_pcv") {
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

                        if ($proposal_submit_result['result']['policy']['premium']['discount']['value'] > 0) {
                            foreach ($proposal_submit_result['result']['policy']['premium']['discount']['breakup'] as $discountkey => $discountvalue) {
                                if ($discountvalue['id'] == 'ncb') {
                                    $ncbvalue = $discountvalue['value'];
                                } elseif ($discountvalue['id'] == 'insurer_discount') {
                                    $ic_vehicle_discount = $discountvalue['value'];
                                }
                            }
                        }

                        $final_total_discount = $ncbvalue + $antitheft_discount + $aai_discount + $ic_vehicle_discount;
                        $final_od_premium = $total_own_damage - $final_total_discount;
                        $final_tp_premium = $tppd_premium_amount + $unnamed_passenger_cover + $paid_driver + $tp_cng_pcv + $cpa_owner_driver + $ll_paid_driver_cover;
                        $addon_premium = $motor_electric_accessories_value + $motor_non_electric_accessories_value + $imt_23;
                        $final_net_premium = $proposal_submit_result['result']['policy']['premium']['net']['value'];
                        $final_gst_amount = round($proposal_submit_result['result']['policy']['premium']['gst']['value']);
                        $final_payable_amount  = $proposal_submit_result['result']['policy']['premium']['gross']['value'];

                        $vehicle_details = [
                            'manufacture_name' => $get_mapping_mmv_details->make,
                            'model_name' => $get_mapping_mmv_details->model,
                            'version' => $get_mapping_mmv_details->version,
                            'fuel_type' => $get_mapping_mmv_details->fuel_type,
                            'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                            'carrying_capacity' => $get_mapping_mmv_details->seating_capacity - 1,
                            'cubic_capacity' => $get_mapping_mmv_details->cubic_capacity,
                            'gross_vehicle_weight' => '',
                            'vehicle_type' => 'PCV'
                        ];

                        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'od_premium' => $final_od_premium,
                                'tp_premium' => $final_tp_premium,
                                'ncb_discount' => $ncbvalue,
                                'addon_premium' => $addon_premium,
                                'total_premium' => $final_net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'final_payable_amount' => $final_payable_amount,
                                'cpa_premium' => $cpa_owner_driver,
                                'total_discount' => $final_total_discount,
                                'ic_vehicle_details' => json_encode($vehicle_details),
                                'proposal_no' => $proposal_submit_result['result']['id'],
                                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                            ]);

                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $product_data->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id
                        ]);

                        return [
                            'status' => true,
                            'msg' => 'Proposal submitted successfully',
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'proposalNo' => $proposal_submit_result['result']['id'],
                                'odPremium' => $final_od_premium,
                                'tpPremium' => $final_tp_premium,
                                'ncbDiscount' => $ncbvalue,
                                'totalPremium' => $final_net_premium,
                                'serviceTaxAmount' => $final_gst_amount,
                                'finalPayableAmount' => $final_payable_amount
                            ]
                        ];                       
                    } else {
                        $messages = '';

                        if (isset($proposal_submit_result['result']['field_errors'])) {
                            foreach ($proposal_submit_result['result']['field_errors'] as $field => $field_error) {
                                $messages = $messages.$field_error['msg'].'. ';
                            }
                        } else if (isset($proposal_submit_result['result']['msg'])) {
                            $messages = $proposal_submit_result['result']['msg'];
                        } else {
                            $messages = 'Service Temporarily Unavailable';
                        }

                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => $messages
                        ];
                    }
                } else {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Insurer not reachable'
                    ];
                }
            } else {
                $messages = '';

                if (isset($quote_result['result']['field_errors'])) {
                    foreach ($quote_result['result']['field_errors'] as $field => $field_error) {
                        $messages = $messages.$field_error['msg'].'. ';
                    }
                } else if (isset($quote_result['result']['msg'])) {
                    $messages = $quote_result['result']['msg'];
                } else {
                    $messages = 'Service Temporarily Unavailable';
                }

                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => $messages
                ];
            }
        } else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Insurer not reachable'
            ];
        }
    }
}
