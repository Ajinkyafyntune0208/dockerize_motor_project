<?php

namespace App\Http\Controllers\Proposal\Services\Car;

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

        $current_date = date('Y-m-d');
        $policy_start_date = date('Y-m-d', strtotime($quote_data->previous_policy_expiry_date.' + 1 days'));

        $policy_end_date = date('Y-m-d', strtotime($policy_start_date.' - 1 days + 1 year'));

        if (strtotime($quote_data->previous_policy_expiry_date) < strtotime($current_date)) {
            $policy_start_date = date('Y-m-d');
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

        // if(($requestData->business_type != "newbusiness") && ($product_data->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }

    	$productData = getProductDataByIc($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        
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
         } elseif ($get_mapping_mmv_details->seating_capacity > 7) {
             return [
                 'status' => false,
                 'msg' => 'Premium not available for vehicle with seating capacity greater than 7'
             ];
         }

         $mmv_data['ic_version_code'] = $get_mapping_mmv_details->ic_version_code;

        $rto_data = MasterRto::where('rto_code', $quote_data->rto_code)
            ->where('status', 'Active')
            ->first();

        if (empty($rto_data)) {
            return [
                'status' => false,
                'msg' => 'RTO code does not exist'
            ];
        }

        $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
        $motor_manf_year = $motor_manf_year_arr[1];
        $motor_manf_month = $motor_manf_year_arr[0];
        $selected_addons = SelectedAddons::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        $is_previous_policy_expired = strtotime($quote_data->previous_policy_expiry_date) < strtotime(date('Y-m-d'));

        $previous_policy_expiry_date = new \DateTime($quote_data->previous_policy_expiry_date);

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                        ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                        ->first();

        $premium_type = DB::table('master_premium_type')
                                        ->where('id', $product_data->premium_type_id)
                                        ->pluck('premium_type_code')
                                        ->first();
         $addon_array=[];
         $i=0;                               
        if(!empty($additional['compulsory_personal_accident']))
        {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) 
            {
                if (isset($data['name']) && $data['name']  == 'Compulsory Personal Accident') 
                {
                    $addon_array[$i++]['id'] = 'pa_owner_car';
                }
            }
        }
        
        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Zero Depreciation') {
                    $addon_array[$i++]['id'] = 'zero_depreciation_car';
                }
                if ($data['name'] == 'Engine Protector') {
                    $addon_array[$i++]['id'] = 'engine_protect_car';
                }
            }
        }

        if(!empty($additional['additional_covers']))
        {
            foreach ($additional['additional_covers'] as $key => $data)
            {
                if (isset($data['name']) && $data['name']  == 'LL paid driver')
                {
                    $addon_array[$i++]['id'] = 'legal_liability_car';
                }
            }
        }
    $new_addon_array=json_encode($addon_array,true);
    $json_addon = json_decode(stripslashes($new_addon_array));
        $vehicle_registration_no_array = explode('-', $proposal->vehicale_registration_number);
        $vehicle_registration_number = $vehicle_registration_no_array[0].$vehicle_registration_no_array[1].strtolower($vehicle_registration_no_array[2]).$vehicle_registration_no_array[3];

        $premium_calculation_request = [
            'product' => 'car',
            'user' => [
                'pincode' => $proposal->pincode,
                'is_corporate' => $quote_data->vehicle_owner_type == 'C' ? true : false,
                'name' => $proposal->first_name." ".$proposal->last_name,
                'email' => $proposal->email,
                'phone' => $proposal->mobile_number,
                'nominee' => [
                    'name' => $proposal->nominee_name,
                    'age' => (int)$proposal->nominee_age,
                    'relationship' => $proposal->nominee_relationship
                ]
            ],
            'vehicle' => [
                'variant_id' => (int) $mmv_data['ic_version_code'],
                'is_new' => false,
                'registration_month' => (int)date('m', strtotime($quote_data->vehicle_register_date)),
                'registration_year' => (int)date('Y', strtotime($quote_data->vehicle_register_date)),
                'manufacturing_year' => (int)$motor_manf_year,
                'rto_code' => $quote_data->rto_code,
                'registration_number' => $vehicle_registration_number,
                'engine_number' => $proposal->engine_number,
                'chassis_number' => $proposal->chassis_number,
                'is_external_cng_kit' => false,
                'previous_policy' => [
                    'is_expired' => $is_previous_policy_expired,
                    'expiry_date' => $previous_policy_expiry_date->format('Y-m-d\TH:i:sP'),
                    'is_claim' => $quote_data->is_claim == 'Y' ? true : false,
                    'number' => $proposal->previous_policy_number,
                    'insurer_name' => $proposal->previous_insurance_company,
                    'ncb' => $premium_type == 'third_party' ? 0 : (int)$quote_data->previous_ncb
                ],
                'registration_type' => 'private',
                'vehicle_type' => 'car'
            ],
            'policy' => [
                'plan_id' => $premium_type == 'third_party' ? 'car_tp':(($premium_type == "own_damage") ? 'car_od': 'car_comprehensive'),
                'tenure' => 1,
                'addons' =>$json_addon,
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
        if ($premium_type != 'third_party') {
            $premium_calculation_request['policy']['idv'] = $quote->idv;
        }

        if ($proposal->nominee_name == '') {
            unset($premium_calculation_request['user']['nominee']);
        }
        if($premium_type == 'third_party'){
            $premium_calculation_request['vehicle']['previous_policy']['is_claim'] = false;
        }
        if($premium_type == "own_damage")
        {
            $premium_calculation_request['vehicle']['registration_month'] = (int) $motor_manf_month;
            if((int)$is_previous_policy_expired == 0)
            {
                $premium_calculation_request['vehicle']['previous_policy']['od_expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
                $premium_calculation_request['vehicle']['previous_policy']['expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
            }else
            {
                $premium_calculation_request['vehicle']['previous_policy']['od_expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
                $premium_calculation_request['vehicle']['previous_policy']['expiry_date'] = null;
            }
        }
        $get_response = getWsData(config('constants.IcConstants.acko.ACKO_QUOTE_WEB_SERVICE_URL'), $premium_calculation_request, 'acko', [
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
                        'nominee' => [
                            'name' => $proposal->nominee_name,
                            'age' => (int)$proposal->nominee_age,
                            'relationship' => $proposal->nominee_relationship
                            ]
                        ],
                        'vehicle' => [
                            'variant_id' => (int) $mmv_data['ic_version_code'],
                            'is_new' => false,
                            'registration_month' => (int)date('m', strtotime($quote_data->vehicle_register_date)),
                            'registration_year' => (int)date('Y', strtotime($quote_data->vehicle_register_date)),
                            'manufacturing_year' => (int)$motor_manf_year,
                            'rto_code' => $quote_data->rto_code,
                            'engine_number' => $proposal->engine_number,
                            'chassis_number' => $proposal->chassis_number,
                            'is_external_cng_kit' => false,
                            'previous_policy' => [
                                'is_expired' => $is_previous_policy_expired,
                                'expiry_date' => $previous_policy_expiry_date->format('Y-m-d\TH:i:sP'),
                                'is_claim' => $quote_data->is_claim == 'Y' ? true : false,
                                'number' => $proposal->previous_policy_number,
                                'insurer_name' => $proposal->previous_insurance_company,
                                'ncb' => $premium_type == 'third_party' ? 0 : (int)$quote_data->previous_ncb
                            ],
                            'registration_type' => 'private',
                            'vehicle_type' => 'car'
                        ],
                        'policy' => [
                            'plan_id' => $premium_type == 'third_party' ? 'car_tp':(($premium_type == "own_damage") ? 'car_od': 'car_comprehensive'),
                            'tenure' => 1,
                            'addons' => $json_addon,
                            ]
                        ];
                        
                        if ($proposal->gst_number != "") {
                            $proposal_submit_request['user']['gst_info']['number'] = $proposal->gst_number;
                        }
                        
                        if ($proposal->is_vehicle_finance) {
                            $proposal_submit_request['vehicle']['hypothecation']['is_financed'] = true;
                            $proposal_submit_request['vehicle']['hypothecation']['name'] = $proposal->name_of_financer;
                            $proposal_submit_request['vehicle']['hypothecation']['finance_type'] = $proposal->financer_agreement_type;
                        }
            
                        if ($premium_type != 'third_party') {
                            $proposal_submit_request['policy']['idv'] = $quote_result['result']['policy']['idv']['calculated'];
                        }
                        
                        if ($proposal->nominee_name == '') {
                            unset($proposal_submit_request['user']['nominee']);
                        }
                        if($premium_type == 'third_party'){
                            $proposal_submit_request['vehicle']['previous_policy']['is_claim'] = false;
                        }
                        if($premium_type == "own_damage")
                        {
                            $proposal_submit_request['vehicle']['registration_month'] = (int) $motor_manf_month;
                            if((int)$is_previous_policy_expired == 0)
                            {
                                $proposal_submit_request['vehicle']['previous_policy']['od_expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
                                $proposal_submit_request['vehicle']['previous_policy']['expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
                            }else
                            {
                                $proposal_submit_request['vehicle']['previous_policy']['od_expiry_date'] = $previous_policy_expiry_date->format('Y-m-d\TH:i:sP');
                                $proposal_submit_request['vehicle']['previous_policy']['expiry_date'] = null;
                            }
                        }
                $get_response = getWsData(config('constants.IcConstants.acko.ACKO_PROPOSAL_WEB_SERVICE_URL'), $proposal_submit_request, 'acko', [
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
                        $motor_electric_accessories_value = 0;
                        $motor_non_electric_accessories_value = 0;

                        $paid_driver = 0;
                        $total_own_damage = 0;
                        $tppd_premium_amount = 0;
                        $ncbvalue = 0;
                        $ic_vehicle_discount = 0;
                        $antitheft_discount = 0;
                        $aai_discount = 0;
                        $tp_cng_car = 0;
                        $cpa_owner_driver = 0;
                        $zero_depreciation_car = 0;
                        $engine_protect_car = 0;
                        foreach ($proposal_submit_result['result']['policy']['covers'] as $responsekey => $responsevalue) {
                            if ($responsevalue['id'] == 'pa_owner_car') {
                                $cpa_owner_driver = ($responsevalue['premium']['net']['breakup'][0]['value']);
                            } elseif ($responsevalue['id'] == 'own_damage_basic_car') {
                                $total_own_damage = ($responsevalue['premium']['net']['breakup'][0]['value']);
                            } elseif ($responsevalue['id'] == 'third_party_car') {
                                $tppd_premium_amount = ($responsevalue['premium']['net']['breakup'][0]['value']);
                            }  elseif ($responsevalue["id"] == "tp_cng_car") {
                                $tp_cng_car = ($responsevalue['premium']['net']['breakup'][0]['value']);
                            } elseif ($responsevalue['id'] == 'zero_depreciation_car') {
                                $zero_depreciation_car = ($responsevalue['premium']['net']['breakup'][0]['value']);
                            }
                            elseif ($responsevalue['id'] == 'engine_protect_car') {
                                $engine_protect_car = ($responsevalue['premium']['net']['breakup'][0]['value']);
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

                        $final_od_premium = $total_own_damage + $motor_electric_accessories_value + $motor_non_electric_accessories_value;
                        $final_tp_premium = $tppd_premium_amount + ($proposal_submit_result['vPAForUnnamedPassengerPremium'] ?? 0) + $paid_driver + $tp_cng_car + $cpa_owner_driver;
                        $final_total_discount = $ncbvalue + $antitheft_discount + $aai_discount + $ic_vehicle_discount;
                        $addon_premium = $zero_depreciation_car + $engine_protect_car;
                        $final_net_premium = $proposal_submit_result['result']['policy']['premium']['net']['value'];
                        $final_gst_amount = ($proposal_submit_result['result']['policy']['premium']['gst']['value']);
                        $final_payable_amount  = $proposal_submit_result['result']['policy']['premium']['gross']['value'];
                        $vehicle_details = [
                            'manufacture_name' =>$get_mapping_mmv_details->vehicle_manufacturer,
                            'model_name' => $get_mapping_mmv_details->vehicle_model_name,
                            'version' => $get_mapping_mmv_details->variant,
                            'fuel_type' => $get_mapping_mmv_details->fuel_type,
                            'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                            'carrying_capacity' => $get_mapping_mmv_details->seating_capacity - 1,
                            'cubic_capacity' => $get_mapping_mmv_details->cubic_capacity,
                            'gross_vehicle_weight' => '',
                            'vehicle_type' => 'PRIVATE CAR'
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
                                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                'tp_insurance_company' =>!empty($proposal->tp_insurance_company) ? $proposal->tp_insurance_company :'',
                                'tp_insurance_number' =>!empty($proposal->tp_insurance_number) ? $proposal->tp_insurance_number :'',
                                'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :'',
                                'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :'',
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
