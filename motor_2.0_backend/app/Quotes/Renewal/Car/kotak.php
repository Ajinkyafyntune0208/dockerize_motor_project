<?php

use App\Models\UserProposal;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    $is_pos_flag = false;
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    //Removing age validation for all iC's  #31637 
    // if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin' || $premium_type == 'comprehensive' || $premium_type == 'breakin') {
    //     if ($requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party') {

    //         return  [
    //             'premium_amount' => 0,
    //             'status' => false,
    //             'message' => 'Quotes not available when previous policy type is not sure or third party',
    //         ];
    //     }
    // }
    $mmv = get_mmv_details($productData, $requestData->version_id, 'kotak');

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

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    $reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
    if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10) && strlen($reg_no[1]) < 2) {
        $permitAgency = $reg_no[0] . '0' . $reg_no[1];
    } else {
        $permitAgency = $reg_no[0] . '' . $reg_no[1];
    }

    $permitAgency = isBhSeries($permitAgency) ? $requestData->rto_code : $permitAgency;

    $rto_data = DB::table('kotak_rto_location')
        ->where('NUM_REGISTRATION_CODE', str_replace('-', '', $permitAgency))
        ->first();
    $rto_data = keysToLower($rto_data);
    if (!empty($rto_data) && strtoupper($rto_data->pvt_uw) == 'ACTIVE') {
        $tokenData = getKotakTokendetails('motor', $is_pos_flag);
        $token_req_array = [
            'vLoginEmailId' => $tokenData['vLoginEmailId'],
            'vPassword' => $tokenData['vPassword'],
        ];

        $data = cache()->remember(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR'), 10, function () use ($token_req_array, $tokenData, $enquiryId, $productData) {
            return getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR'), $token_req_array, 'kotak', [
                'Key' => $tokenData['vRanKey'],
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'kotak',
                'section' => $productData->product_sub_type_code,
                'method' => 'Token Generation',
                'transaction_type' => 'quote',
            ]);
        });

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $user_id = config('constants.IcConstants.kotak.KOTAK_MOTOR_USERID');

        if ($data['response']) {
            $token_response = json_decode($data['response'], true);
            if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Token Generation Success Success", "Success");
                $premium_req_array = [
                    "vPolicyNumber" => $user_proposal['previous_policy_number'],
                    "vLoginEmailId" => $user_id,
                    "bIsReCalculate"  => false,
                    "vRegistrationNumber"  => "",#$user_proposal['vehicale_registration_number'],
                    "vChassisNumber"  => $user_proposal['chassis_number'],
                    "vEngineNumber"  => $user_proposal['engine_number'],
                    "nFinalIDV"  => $user_proposal['idv'] ?? 0,
                    "nMarketMovement"  => -1,
                    "isRoadSideAssistance" => true
                ];
                $data = getWsData(config('constants.IcConstants.kotak.KOTAK_MOTOR_FETCH_POLICY_DETAILS_PREMIUM'), $premium_req_array, 'kotak', [
                    'token' => $token_response['vTokenCode'],
                    'headers' => [
                        'vTokenCode' => $token_response['vTokenCode']
                    ],
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'kotak',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Fetch Policy Details',
                    'transaction_type' => 'quote',
                ]);

                if ($data) {
                    $response = json_decode($data['response'], true);
                    if ($response['vErrorMessage'] == 'Success') {
                        if($response['objPrevInsurer']['vPrevPolicyType'] == "ComprehensivePolicy"){
                            $policyType = 'Comprehensive';
                        }
                        else if (in_array($response['objPrevInsurer']['vPrevPolicyType'], ["OD Only","1+3"]))
                        {
                            $policyType = 'Own Damage';
                        }
                        else if($response['objPrevInsurer']['vPrevPolicyType'] == "LiabilityOnlyPolicy")
                        {
                           $policyType = 'Third Party';
                        }
                        if (isset($policyType) && strtolower(str_replace(' ', '_', $policyType)) != $premium_type) {
                            return [
                                'status' => false,
                                'message' => 'Missmatched policy type',
                                'request' => [
                                    'message' => 'Missmatched policy type',
                                    'premium_type_code' => $premium_type,
                                    'policyType' => $policyType
                                ]
                            ];
                        }
                        $idv = ($response['vFinalIDV']);
                        $idv_min = (string) (0.9 * $response['vFinalIDV']);
                        $idv_max = (string) (1.15 * $response['vFinalIDV']);
                        $skip_second_call = false;
                        if ($requestData->is_idv_changed == 'Y') {
                            if ($requestData->edit_idv >= $idv_max) {
                                $premium_req_array['vFinalIDV'] = $idv_max;
                            } elseif ($requestData->edit_idv <= $idv_min) {
                                $premium_req_array['vFinalIDV'] = $idv_min;
                            } else {
                                $premium_req_array['vFinalIDV'] = $requestData->edit_idv;
                            }
                        } else {
                            #$premium_req_array['vFinalIDV'] = $idv_min;
                            //new idv code
                            $getIdvSetting = getCommonConfig('idv_settings');
                            switch ($getIdvSetting) {
                                case 'default':
                                    $premium_req_array['vFinalIDV'] = $idv;
                                    $skip_second_call = true;
                                    break;
                                case 'min_idv':
                                    $premium_req_array['vFinalIDV'] = $idv_min;
                                    break;
                                case 'max_idv':
                                    $premium_req_array['vFinalIDV'] = $idv_max;
                                    break;
                                default:
                                    $premium_req_array['vFinalIDV'] = $idv_min;
                                    break;
                            }
                        }

                        if ($data['response']) {
                            $response = json_decode($data['response'], true);
                            if ($response) {
                                $idv = ($response['vFinalIDV']);
                            } else {
                                return [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => isset($response['vErrorMsg']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $response['vErrorMsg']) : 'Error while processing request',
                                ];
                            }
                        } else {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Insurer not reachable',
                            ];
                        }
                        // }

                        $tp =  $od = $rsa = $zero_dep = $consumable = $eng_protect = $rti = $electrical_accessories = $non_electrical_accessories = $lpg_cng = $lpg_cng_tp = $pa_owner = $llpaiddriver = $pa_unnamed = $paid_driver = $voluntary_deduction_zero_dep = $lopb = $key_replacement =
                            $NCB = 0;
                        $geog_Extension_OD_Premium = 0;
                        $geog_Extension_TP_Premium = 0;

                        $is_pa_for_add_cover_selected = false;
                        $is_pa_unnamed_cover_selected = false;
                        $is_ll_paid_driver_selected = false;

                        $is_electrical_accessories = false;
                        $is_non_electrical_accessories = false;

                        if (isset($response['vBasicTPPremium'])) {
                            $tp = ($response['vBasicTPPremium']);
                        }
                        if (isset($response['vPACoverForOwnDriver'])) {
                            $pa_owner = ($response['vPACoverForOwnDriver']);
                        }
                        if (isset($response['vPAForUnnamedPassengerPremium'])) {
                            $pa_unnamed = ($response['vPAForUnnamedPassengerPremium']);
                            $is_pa_unnamed_cover_selected = true;
                        }
                        if (isset($response['vCngLpgKitPremiumTP'])) {
                            $lpg_cng_tp = ($response['vCngLpgKitPremiumTP']);
                        }
                        if (isset($response['vPANoOfEmployeeforPaidDriverPremium'])) {
                            $paid_driver = ($response['vPANoOfEmployeeforPaidDriverPremium']);
                            $is_pa_for_add_cover_selected = true;
                        }
                        if (isset($response['vLegalLiabilityPaidDriverNo'])) {
                            $llpaiddriver = ($response['vLegalLiabilityPaidDriverNo']);
                            $is_ll_paid_driver_selected = true;
                        }
                        if (isset($response['vDepreciationCover'])) {
                            $zero_dep = ($response['vDepreciationCover']);
                        }
                        if (isset($response['vRSA'])) {
                            $rsa = ($response['vRSA']);
                        }
                        if (isset($response['vEngineProtect'])) {
                            $eng_protect = ($response['vEngineProtect']);
                        }
                        if (isset($response['vConsumableCover'])) {
                            $consumable = ($response['vConsumableCover']);
                        }
                        if (isset($response['vReturnToInvoice'])) {
                            $rti = ($response['vReturnToInvoice']);
                        }
                        if (isset($response['nLossPersonalBelongingsPremium'])) {
                            $lopb = ($response['nLossPersonalBelongingsPremium']);
                        }
                        if (isset($response['nKeyReplacementPremium'])) {
                            $key_replacement = ($response['nKeyReplacementPremium']);
                        }
                        if (isset($response['vElectronicSI'])) {
                            $electrical_accessories = ($response['vElectronicSI']);
                            $is_electrical_accessories = true;
                        }
                        if (isset($response['vNonElectronicSI'])) {
                            $non_electrical_accessories = ($response['vNonElectronicSI']);
                            $is_non_electrical_accessories = false;
                        }
                        if (isset($response['vCngLpgKitPremium'])) {
                            $lpg_cng = ($response['vCngLpgKitPremium']);
                        }
                        if (isset($response['vVoluntaryDeductionDepWaiver'])) {
                            $voluntary_deduction_zero_dep = ($response['vVoluntaryDeductionDepWaiver']);
                        }
                        if (isset($response['vOwnDamagePremium'])) {
                            $od = ($response['vOwnDamagePremium']);
                        }
                        if (isset($response['vNCB'])) {
                            $NCB = ($response['vNCB']);
                        }

                        $allowed_quote = (($productData->zero_dep == 0) && ($zero_dep == 0)) ? false : true;
                        $applicable_addons = [];
                        $add_ons_data = [
                            'in_built' => [],
                            'additional' => [
                                'zero_depreciation' => (float)($zero_dep),
                                'road_side_assistance' => (float)$rsa,
                                'engine_protector' => (float)$eng_protect,
                                'ncb_protection' => 0,
                                'key_replacement' => (float)$key_replacement,
                                'consumables' => (float)$consumable,
                                'tyre_secure' => 0,
                                'return_to_invoice' => (float)($rti),
                                'loss_of_personal_belongings' => (float)$lopb,
                            ],
                            'other' => [],
                        ];

                        if ($allowed_quote) {
                            $add_ons = [];
                            $total_premium = ($response['vTotalPremium']);
                            $base_premium_amount = ($total_premium / (1 + (18.0 / 100)));


                            foreach ($add_ons_data as $add_on_key => $add_on_value) {
                                if (count($add_on_value) > 0) {
                                    foreach ($add_on_value as $add_on_value_key => $add_on_value_value) {
                                        if (is_numeric($add_on_value_value)) {
                                            if ($add_on_value_value != '0' && $add_on_value_value != 0) {
                                                $base_premium_amount -= $add_on_value_value;
                                                $value = $add_on_value_value;
                                            } else {
                                                $value = 0;
                                            }
                                        } else {
                                            $value = $add_on_value_value;
                                        }
                                        $add_ons[$add_on_key][$add_on_value_key] = $value;
                                    }
                                } else {
                                    $add_ons[$add_on_key] = $add_on_value;
                                }
                            }
                            // ($request_data['motor_car_owner_type'] == 'C' ? '0' : ($response['vPACoverForOwnDriver']));

                            $net_premium = $response['vNetPremium'];
                            $final_gst_amount = $response['vGSTAmount'];
                            array_walk_recursive($add_ons, function (&$item) {
                                if ($item == '' || $item == '0') {
                                    $item = 0;
                                }
                            });
                            $voluntary_deductible =  ($response['vVoluntaryDeduction'] ? $response['vVoluntaryDeduction'] : 0);
                            $other_discount = ($productData->zero_dep == 0 ? $voluntary_deduction_zero_dep : '0');
                            $applicable_ncb = ($response['vNCBPercentage'] ? $response['vNCBPercentage'] : 0);
                            $addon_premium = array_sum($add_ons_data['additional']);
                            //$od = $od - $addon_premium;
                            $base_premium_amount = ($base_premium_amount * (1 + (18.0 / 100)));
                            $final_od_premium = $od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
                            $final_tp_premium = $tp + $lpg_cng_tp + $llpaiddriver +  $paid_driver + $pa_unnamed;
                            $final_total_discount =  $NCB + $other_discount + $voluntary_deductible;
                            $policy_start_date = \Carbon\Carbon::createFromFormat('d/m/Y', $response['vPolicyStartDate'])->format('d-m-Y');
                            $policy_end_date = \Carbon\Carbon::createFromFormat('d/m/Y', $response['vPolicyEndDate'])->format('d-m-Y');

                            foreach (($add_ons['additional'] ?? []) as $key => $value) {
                                if (!empty($value)) {
                                    $add_ons['in_built'][$key] = $value;
                                }
                            }
                            $add_ons['additional'] = [];

                            $return_data = [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'status' => true,
                                'msg' => 'Found',
                                'Data' => [
                                    'isRenewal' => 'Y',
                                    'idv' => (int) $idv,
                                    'min_idv' => (int) $idv_min,
                                    'max_idv' => (int) $idv_max,
                                    'vehicle_idv' => $idv,
                                    'qdata' => null,
                                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                                    'addonCover' => null,
                                    'addon_cover_data_get' => '',
                                    'rto_decline' => null,
                                    'rto_decline_number' => null,
                                    'mmv_decline' => null,
                                    'mmv_decline_name' => null,
                                    'policy_type' => $premium_type == 'third_party' ? 'Third Party' : (($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                                    // 'business_type' => 'Rollover',
                                    'cover_type' => '1YC',
                                    'hypothecation' => '',
                                    'hypothecation_name' => '',
                                    'vehicle_registration_no' => $requestData->rto_code,
                                    'rto_no' => $requestData->rto_code,
                                    'version_id' => $requestData->version_id,
                                    'selected_addon' => [],
                                    'showroom_price' => 0,
                                    'fuel_type' => $requestData->fuel_type,
                                    'ncb_discount' => $applicable_ncb,
                                    'company_name' => $productData->company_name,
                                    'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                                    'product_name' => $productData->product_sub_type_name . ' ' . trim($productData->product_identifier),
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
                                        'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                                        'product_sub_type_name' => $productData->product_sub_type_name,
                                        'flat_discount' => $productData->default_discount,
                                        'predefine_series' => '',
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
                                        // 'car_age' => $car_age,
                                        'aai_discount' => 0,
                                        'ic_vehicle_discount' => floatval(abs($other_discount)),
                                    ],
                                    'ic_vehicle_discount' => floatval(abs($other_discount)),
                                    'basic_premium' => floatval($od),
                                    // 'motor_electric_accessories_value' => floatval($electrical_accessories),
                                    // 'motor_non_electric_accessories_value' => floatval($non_electrical_accessories),
                                    'motor_lpg_cng_kit_value' => floatval($lpg_cng),
                                    'total_accessories_amount(net_od_premium)' => floatval($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                                    'total_own_damage' => floatval($final_od_premium),
                                    'tppd_premium_amount' => floatval($tp),
                                    'compulsory_pa_own_driver' => floatval($pa_owner), // Not added in Total TP Premium
                                    'cpa_allowed' => ($pa_owner) > 0 ? true : false,
                                    // 'cover_unnamed_passenger_value' => $pa_unnamed,
                                    //'default_paid_driver' => $llpaiddriver,
                                    // 'motor_additional_paid_driver' => floatval($paid_driver),
                                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                                    'cng_lpg_tp' => floatval($lpg_cng_tp),
                                    'seating_capacity' => $mmv_data->seating_capacity,
                                    'deduction_of_ncb' => floatval(abs($NCB)),
                                    'antitheft_discount' => 0,
                                    'aai_discount' => 0,
                                    'voluntary_excess' => $voluntary_deductible,
                                    'other_discount' => floatval(abs($other_discount)),
                                    'total_liability_premium' => floatval($final_tp_premium),
                                    'net_premium' => floatval($net_premium),
                                    'service_tax_amount' => floatval($final_gst_amount),
                                    'service_tax' => 18,
                                    'total_discount_od' => 0,
                                    'add_on_premium_total' => 0,
                                    'addon_premium' => 0,
                                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                    'quotation_no' => '',
                                    'premium_amount' => floatval($total_premium),
                                    'service_data_responseerr_msg' => 'success',
                                    'user_id' => $requestData->user_id,
                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                    'user_product_journey_id' => $requestData->user_product_journey_id,
                                    'business_type' =>  $requestData->business_type == 'newbusiness' ?  'New Business' : $requestData->business_type,
                                    'service_err_code' => null,
                                    'service_err_msg' => null,
                                    'policyStartDate' => $policy_start_date,
                                    'policyEndDate' => $policy_end_date,
                                    'ic_of' => $productData->company_id,
                                    'vehicle_in_90_days' => NULL,
                                    'get_policy_expiry_date' => null,
                                    'get_changed_discount_quoteid' => 0,
                                    'vehicle_discount_detail' => [
                                        'discount_id' => null,
                                        'discount_rate' => null,
                                    ],
                                    'is_premium_online' => $productData->is_premium_online,
                                    'is_proposal_online' => $productData->is_proposal_online,
                                    'is_payment_online' => $productData->is_payment_online,
                                    'policy_id' => $productData->policy_id,
                                    'insurane_company_id' => $productData->company_id,
                                    'max_addons_selection' => null,
                                    'add_ons_data'      => $add_ons,
                                    'applicable_addons' => $applicable_addons,
                                    'final_od_premium'  => floatval($final_od_premium),
                                    'final_tp_premium'  => floatval($final_tp_premium),
                                    'final_total_discount' => floatval(abs($final_total_discount)),
                                    'final_net_premium' => floatval($total_premium),
                                    'final_gst_amount'  => floatval($final_gst_amount),
                                    'final_payable_amount' => floatval($total_premium),
                                    'mmv_detail'    => [
                                        'manf_name'     => $mmv_data->manufacturer,
                                        'model_name'    => $mmv_data->vehicle_model,
                                        'version_name'  => $mmv_data->txt_variant,
                                        'fuel_type'     => $mmv_data->txt_fuel,
                                        'seating_capacity' => $mmv_data->seating_capacity,
                                        'carrying_capacity' => $mmv_data->carrying_capacity,
                                        'cubic_capacity' => $mmv_data->cubic_capacity,
                                        'gross_vehicle_weight' => '',
                                        'vehicle_type'  => 'Private Car',
                                    ],
                                ],
                            ];

                            $included_additional = [
                                'included' =>[]
                            ];
                            if ($is_pa_for_add_cover_selected) {
                                $return_data['Data']['motor_additional_paid_driver'] = $paid_driver;
                                $included_additional['included'][] = 'motorAdditionalPaidDriver';
                            }
                            if ($is_pa_unnamed_cover_selected) {
                                $return_data['Data']['cover_unnamed_passenger_value'] = $pa_unnamed;
                                $included_additional['included'][] = 'coverUnnamedPassengerValue';
                            }
                            if ($is_ll_paid_driver_selected) {
                                $return_data['Data']['default_paid_driver'] = $llpaiddriver;
                                $included_additional['included'][] = 'defaultPaidDriver';
                            }
                            if ($is_electrical_accessories) {
                                $return_data['Data']['motor_electric_accessories_value'] = $electrical_accessories;
                                $included_additional['included'][] = 'motorElectricAccessoriesValue';
                            }
                            if ($is_non_electrical_accessories) {
                                $return_data['Data']['motor_non_electric_accessories_value'] = $non_electrical_accessories;
                                $included_additional['included'][] = 'motorNonElectricAccessoriesValue';
                            }
            
                        } else {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Zero dep is not allowed for this vehicle',
                            ];
                        }
                    } else {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => isset($response['vErrorMessage']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $response['vErrorMessage']) : 'Error while processing request',
                        ];
                    }
                } else {
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable',
                    ];
                }
            } else {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => (isset($token_response['vErrorMsg']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_response['vErrorMsg']) : 'Error while processing request'),
                ];
            }
        } else {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Insurer not reachable : ' . $data['vErrorMsg'],
            ];
        }
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => (!empty($rto_data) && strtoupper($rto_data->pvt_uw) == 'DECLINED' ? 'RTO Declined' : 'RTO not available'),
        ];
    }

            $return_data['Data']['included_additional'] = $included_additional;

    return camelCase($return_data);
}
