<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class HdfcErgoPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            $isJson = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_REQUEST_TYPE') == 'JSON';
            if ($isRenewal) {
                return [
                    'status' => false,
                    'message' => 'Integration not yet done.',
                ];
                // $methodList = [
                //     'Premium Calculation',
                //     'Fetch Policy Details',
                //     getGenericMethodName('Premium Calculation', 'proposal'),
                //     getGenericMethodName('Fetch Policy Details', 'proposal')
                // ];
            } else {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal')
                ];
                if ($isJson) {
                    $methodList = array_merge($methodList, [
                        'Proposal Generation',
                        getGenericMethodName('Proposal Generation', 'proposal'),
                    ]);
                }
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'hdfc_ergo'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $isMiscd = false;
            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (($response['Status'] ?? $response['StatusCode'] ?? '') == 200 &&
                    (!empty($response['Resp_PCV']['Total_Premium']) || !empty($response['Resp_GCV']['Total_Premium']))
                ) {
                    $webserviceId = $log['id'];
                    break;
                } else {
                    $response = $log['response'];
                    if (!preg_match('/Service Unavailable/i', $response)) {
                        try {
                            $response = html_entity_decode($response);
                            $response = XmlToArray::convert($response);
                        } catch (\Throwable $th) {
                            $response = null;
                        }
                        if (!empty($response) && empty($response['TXT_ERR_MSG']) && empty($response['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['TXT_ERROR_MSG'])) {
                            $isMiscd = !empty($response['PREMIUMOUTPUTOUTER']);
                            $webserviceId = $log['id'];
                            break;
                        }
                    }
                }
            }

            if (!empty($webserviceId)) {
                if ($isMiscd) {
                    return self::saveMiscPremiumDetails($webserviceId);
                }
                if ($isJson) {
                    return self::savePremiumDetails($webserviceId);
                } else {
                    return self::saveXmlPremiumDetails($webserviceId);
                }
            } else {
                return [
                    'status' => false,
                    'message' => 'Valid Proposal log not found',
                ];
            }
        } catch (\Throwable $th) {
            info($th);
            return [
                'status' => false,
                'message' => $th->getMessage(),
            ];
        }
    }

    public static function savePremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $request = $logs->request;
            $response = json_decode($logs->response, true);

            $request = json_decode($request, true);

            $isPCV = !empty($request['Req_PCV']);

            $premium = $isPCV ? $response['Resp_PCV'] : $response['Resp_GCV'];


            $basic_od = $premium['Basic_OD_Premium'] + ($premium['HighTonnageLoading_Premium'] ?? 0);
            $tppd = $premium['Basic_TP_Premium'];
            $pa_owner = $premium['PAOwnerDriver_Premium'];
            $pa_unnamed = 0;
            $pa_paid_driver = $premium['PAPaidDriverCleaCondCool_Premium'];
            $electrical_accessories = $premium['Electical_Acc_Premium'];
            $non_electrical_accessories = $premium['NonElectical_Acc_Premium'];
            $zero_dep_amount = $premium['Vehicle_Base_ZD_Premium'];
            $roadSideAssistance = $premium['EA_premium'] ?? 0;
            $ncb_discount = $premium['NCBBonusDisc_Premium'];
            $lpg_cng = $premium['BiFuel_Kit_OD_Premium'];
            $lpg_cng_tp = isset($premium['BiFuel_Kit_TP_Premium']) && $premium['BiFuel_Kit_TP_Premium'] > 0 ? $premium['BiFuel_Kit_TP_Premium'] : (isset($premium['InBuilt_BiFuel_Kit_Premium']) && $premium['InBuilt_BiFuel_Kit_Premium'] > 0 ? $premium['InBuilt_BiFuel_Kit_Premium'] : 0);
            $automobile_association = 0;
            $anti_theft = $isPCV ? $premium['AntiTheftDisc_Premium'] : 0;
            $tppd_discount_amt = $premium['TPPD_premium'] ?? 0;
            $other_addon_amount = 0;
            $liabilities = 0;
            $ll_paid_cleaner = $premium['NumberOfDrivers_Premium'];
            $imt_23 = !$isPCV ? $premium['VB_InclusionofIMT23_Premium'] : 0;
            $ic_vehicle_discount = 0;
            $voluntary_excess = 0;
            $other_discount = 0;
            $own_premises_od = $premium['LimitedtoOwnPremises_OD_Premium'] ?? 0;
            $own_premises_tp = $premium['LimitedtoOwnPremises_TP_Premium'] ?? 0;

            if ($electrical_accessories > 0) {
                $zero_dep_amount += (int)$premium['Elec_ZD_Premium'];
                $imt_23 += !$isPCV ? (int) $premium['Elec_InclusionofIMT23_Premium'] : 0;
            }

            if ($non_electrical_accessories > 0) {
                $zero_dep_amount += (int)$premium['NonElec_ZD_Premium'];
                $imt_23 += !$isPCV ? (int) $premium['NonElec_InclusionofIMT23_Premium'] : 0;
            }

            if ($lpg_cng > 0) {
                $zero_dep_amount += (int)$premium['Bifuel_ZD_Premium'];
                $imt_23 += !$isPCV ? (int) $premium['BiFuel_InclusionofIMT23_Premium'] : 0;
            }

            $requestData = getQuotation($enquiryId);
            if ($requestData->vehicle_owner_type == 'C') {
                $pa_owner = 0;
            }

            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt;
            $final_od_premium = $basic_od - $final_total_discount - ($premium['LimitedtoOwnPremises_OD_Premium'] ?? 0);
            $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $ll_paid_cleaner + $pa_owner - ($premium['LimitedtoOwnPremises_TP_Premium'] ?? 0);
            $total_addon_premium = $zero_dep_amount + $imt_23 + $electrical_accessories + $non_electrical_accessories + $lpg_cng;


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $total_addon_premium,
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => $roadSideAssistance,
                "imt_23" => $imt_23,
                "consumable" => 0,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $liabilities,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => $ll_paid_cleaner,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_discount_amt,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncb_discount,
                "limited_own_premises_od" => $own_premises_od,
                "limited_own_premises_tp" => $own_premises_tp,
                // Final tags
                "net_premium" => $premium['Net_Premium'],
                "service_tax_amount" => $premium['Service_Tax'],
                "final_payable_amount" => $premium['Total_Premium'],
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? round($value, 2) : 0;
            }, $updatePremiumDetails);

            savePremiumDetails($enquiryId, $updatePremiumDetails);

            return ['status' => true, 'message' => 'Premium details stored successfully'];
        } catch (\Throwable $th) {
            info($th);
            return [
                'status' => false,
                'message' => $th->getMessage(),
            ];
        }
    }

    public static function saveXmlPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = $logs->response;

            $premium_data = html_entity_decode($response);
            $premium_data = XmlToArray::convert($premium_data);

            if (isset($premium_data['PREMIUMOUTPUT']['PARENT'])) {
                $premium_data = $premium_data['PREMIUMOUTPUT']['PARENT'][0];
            } else {
                $premium_data = $premium_data['PREMIUMOUTPUT'];
            }

            $pa_paid_driver = $premium_data['NUM_PA_PAID_DRVR_PREM'];
            $liabilities = $premium_data['NUM_LL_PAID_DRIVER'];
            $tppd_discount = isset($premium_data['NUM_TPPD_AMT']) ? round($premium_data['NUM_TPPD_AMT'], 2) : 0;
            $motor_electric_accessories_value = $premium_data['NUM_ELEC_ACC_PREM'];
            $motor_non_electric_accessories_value = $premium_data['NUM_NON_ELEC_ACC_PREM'];
            $final_tp_premium = $premium_data['NUM_TP_RATE'] + $liabilities + $premium_data['NUM_LPG_CNGKIT_TP_PREM'] + $pa_paid_driver;

            $basic_od = $premium_data['NUM_BASIC_OD_PREMIUM'];
            $total_own_damage = $basic_od;
            $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;

            $zero_dep_amount = $premium_data['NUM_ZERO_DEPT_PREM'] ?? 0;
            $imt_23 = 0;
            $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
            $productData = getProductDataByIc($quoteLog->master_policy_id);
            $requestData = getQuotation($enquiryId);


            $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
            if ($is_GCV) {
                $basic_imt_23 = (int) $total_own_damage * (0.15);
                $imt_23_electrical = (int) $requestData->electrical_acessories_value * (0.6 / 100); // 0.6%
                $imt_23_non_elec = (int) $requestData->nonelectrical_acessories_value * (0.26 / 100); // 0.26%
                $imt_23_bifuel_kit = (int) $requestData->bifuel_kit_value * (0.6 / 100); // 0.6%
                $imt_23 = round(($basic_imt_23 + $imt_23_electrical + $imt_23_non_elec + $imt_23_bifuel_kit), 2);
                $final_od_premium = $total_own_damage + $total_accessories_amount + $premium_data['NUM_LPG_CNGKIT_OD_PREM'];
                $final_total_discount = round(($final_od_premium * $requestData->applicable_ncb / 100), 2);
                $deduction_of_ncb = round(($final_od_premium * $requestData->applicable_ncb / 100), 2);
            } else {
                if ($productData->product_sub_type_code == 'AUTO-RICKSHAW') {
                    $basic_imt_23 = (int) $premium_data['NUM_INCLUSION_IMT23_AMT_OD'] ?? 0;
                    $imt_23_electrical = (int) $premium_data['NUM_INCLUS_IMT23_AMT_ELEC'] ?? 0; // 0.6%
                    $imt_23_non_elec = (int) $premium_data['NUM_INCLUS_IMT23_AMT_NELEC'] ?? 0; // 0.26%
                    $imt_23_bifuel_kit = (int) $premium_data['NUM_INCLUSION_IMT23_AMT_CNG'] ?? 0; // 0.6%
                    $imt_23 = round(($basic_imt_23 + $imt_23_electrical + $imt_23_non_elec + $imt_23_bifuel_kit), 2);
                }

                $final_od_premium = $total_own_damage + $total_accessories_amount + $premium_data['NUM_LPG_CNGKIT_OD_PREM'];
                $deduction_of_ncb = round(($final_od_premium * $requestData->applicable_ncb / 100), 2);
                $final_total_discount = $deduction_of_ncb;
            }

            $total_addon_premium = $zero_dep_amount + $imt_23;

            $final_net_premium = $premium_data['NUM_NET_PREMIUM'];
            $final_gst_amount = $premium_data['NUM_SERVICE_TAX'];
            $final_payable_amount = $premium_data['NUM_TOTAL_PREMIUM'];


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $total_addon_premium,
                // TP Tags
                "basic_tp_premium" => $premium_data['NUM_TP_RATE'],
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $motor_electric_accessories_value,
                "non_electric_accessories_value" => $motor_non_electric_accessories_value,
                "bifuel_od_premium" => $premium_data['NUM_LPG_CNGKIT_OD_PREM'],
                "bifuel_tp_premium" => $premium_data['NUM_LPG_CNGKIT_TP_PREM'],
                // Addons
                "compulsory_pa_own_driver" => $premium_data['NUM_PA_OWNER_DRIVER'],
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => 0,
                "imt_23" => $imt_23,
                "consumable" => 0,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => 0,
                "ll_paid_driver" => $liabilities,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $deduction_of_ncb,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_gst_amount,
                "final_payable_amount" => $final_payable_amount,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? round($value, 2) : 0;
            }, $updatePremiumDetails);

            savePremiumDetails($enquiryId, $updatePremiumDetails);

            return ['status' => true, 'message' => 'Premium details stored successfully'];
        } catch (\Throwable $th) {
            info($th);
            return [
                'status' => false,
                'message' => $th->getMessage(),
            ];
        }
    }

    public static function saveMiscPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = $logs->response;

            $premium_data = html_entity_decode($response);
            $premium_data = XmlToArray::convert($premium_data);

            if (isset($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['PARENT'])) {
                $premium_data = $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['PARENT'][0];
            } else {
                $premium_data = $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT'];
            }

            $requestData = getQuotation($enquiryId);

            $pa_paid_driver = $premium_data['NUM_PA_PAID_DRIVER_PREM'];
            $liabilities = $premium_data['NUM_NOOFLLDRIVERS_PREM'];
            $tppd_discount = isset($premium_data['NUM_TPPD_AMT']) ? round($premium_data['NUM_TPPD_AMT'], 2) : 0;
            $motor_electric_accessories_value = $premium_data['NUM_ELECTRICAL_PREM'];
            $motor_non_electric_accessories_value = $premium_data['NUM_NON_ELECTRICAL_PREM'];
            $cpa = $premium_data['NUM_PA_OWNER_DRIVER_PREM'];
            $final_tp_premium = $premium_data['NUM_BASIC_TP_PREM'] + $liabilities + $pa_paid_driver + $cpa;

            $basic_od = $premium_data['NUM_BASIC_OD_PREM'];
            $total_own_damage = $basic_od;
            $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;

            $own_premises_od =  $premium_data['NUM_LIMITED_PREMISES_OD_PREM'];
            $own_premises_tp = $premium_data['NUM_LIMITED_PREMISES_TP_PREM'];
            $imt_23 = round($premium_data['NUM_INCLUSION_IMT23_PREM'], 2);
            $final_od_premium = $total_own_damage + $motor_electric_accessories_value + $motor_non_electric_accessories_value;
            $final_total_discount = (int)($final_od_premium * $requestData->applicable_ncb / 100);
            $deduction_of_ncb = (int)($final_od_premium * $requestData->applicable_ncb / 100);
            
            $final_net_premium = round(($final_od_premium + $final_tp_premium - $final_total_discount), 2);
            $total_addon_premium = $imt_23;

            //Tax calculate
            $final_gst_amount = round(($final_net_premium * 0.18), 2);
            $final_payable_amount = $final_net_premium + $final_gst_amount;


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $total_addon_premium,
                // TP Tags
                "basic_tp_premium" => $premium_data['NUM_BASIC_TP_PREM'],
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $motor_electric_accessories_value,
                "non_electric_accessories_value" => $motor_non_electric_accessories_value,
                "bifuel_od_premium" => 0,
                "bifuel_tp_premium" => 0,
                // Addons
                "compulsory_pa_own_driver" => $cpa,
                "zero_depreciation" => 0,
                "road_side_assistance" => 0,
                "imt_23" => $imt_23,
                "consumable" => 0,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => 0,
                "ll_paid_driver" => $liabilities,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $deduction_of_ncb,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_gst_amount,
                "final_payable_amount" => $final_payable_amount,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? round($value, 2) : 0;
            }, $updatePremiumDetails);

            savePremiumDetails($enquiryId, $updatePremiumDetails);

            return ['status' => true, 'message' => 'Premium details stored successfully'];
        } catch (\Throwable $th) {
            info($th);
            return [
                'status' => false,
                'message' => $th->getMessage(),
            ];
        }
    }
}
