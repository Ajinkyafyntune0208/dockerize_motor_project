<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use DateTime;
use Illuminate\Http\Request;

class HdfcErgoPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Premium Calculation',
                    'Fetch Policy Details',
                    getGenericMethodName('Premium Calculation', 'proposal'),
                    getGenericMethodName('Fetch Policy Details', 'proposal')
                ];
            } else {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal')
                ];
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

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (($response['Status'] ?? $response['StatusCode'] ?? '') == 200) {
                    $webserviceId = $log['id'];
                    break;
                }
            }

            if (!empty($webserviceId)) {
                if ($isRenewal) {
                    return self::saveRenewalPremiumDetails($webserviceId);
                } elseif (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y') {
                    return self::saveV2PremiumDetails($webserviceId);
                } else {
                    return self::saveV1PremiumDetails($webserviceId);
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

    public static function saveV1PremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = json_decode($logs->response, true);

            $premium_data = $response['Resp_PvtCar'];

            $Nil_dep = $pa_unnamed = $ncb_discount = $pa_paid_driver = $basic_od_premium = 
            $pa_owner_driver = $lpg_cng_tp = $lpg_cng  = $anti_theft
            = $basic_tp_premium = $electrical_accessories = $tppd_value =
            $non_electrical_accessories = $ll_paid_driver =
            $ncb_protection = $consumables_cover = $Nil_dep = $roadside_asst =
            $key_replacement = $loss_of_personal_belongings = $eng_protector =
            $rti  = $voluntary_excess = $tyre_secure = $GeogExtension_od =
            $GeogExtension_tp = $OwnPremises_OD = $OwnPremises_TP = $legal_liability_to_employee = 
            $batteryProtect = 0;

            if (!empty($premium_data['PAOwnerDriver_Premium'])) {
                $pa_owner_driver = round($premium_data['PAOwnerDriver_Premium'], 2);
            }
            if (!empty($premium_data['Vehicle_Base_ZD_Premium'])) {
                $Nil_dep = round($premium_data['Vehicle_Base_ZD_Premium'], 2);
            }

            if (!empty($premium_data['GeogExtension_ODPremium'])) {
                $GeogExtension_od = round($premium_data['GeogExtension_ODPremium'], 2);
            }
            if (!empty($premium_data['GeogExtension_TPPremium'])) {
                $GeogExtension_tp = round($premium_data['GeogExtension_TPPremium'], 2);
            }

            if (!empty($premium_data['LimitedtoOwnPremises_OD_Premium'])) {
                $OwnPremises_OD = round($premium_data['LimitedtoOwnPremises_OD_Premium'], 2);
            }
            if (!empty($premium_data['LimitedtoOwnPremises_TP_Premium'])) {
                $OwnPremises_TP = round($premium_data['LimitedtoOwnPremises_TP_Premium'], 2);
            }

            if (!empty($premium_data['EA_premium'])) {
                $roadside_asst = round($premium_data['EA_premium'], 2);
            }
            // if (!empty($premium_data['Loss_of_Use_Premium'])) {
            //     $loss_of_personal_belongings += round($premium_data['Loss_of_Use_Premium'], 2);
            // }

            if (!empty($premium_data['LossOfPersonalBelongings_Premium'])) {
                $loss_of_personal_belongings += round($premium_data['LossOfPersonalBelongings_Premium'], 2);
            }

            if (!empty($premium_data['Vehicle_Base_NCB_Premium'])) {
                $ncb_protection = round($premium_data['Vehicle_Base_NCB_Premium'], 2);
            }
            if (!empty($premium_data['NCBBonusDisc_Premium'])) {
                $ncb_discount = round($premium_data['NCBBonusDisc_Premium'], 2);
            }
            if (!empty($premium_data['Vehicle_Base_ENG_Premium'])) {
                $eng_protector = round($premium_data['Vehicle_Base_ENG_Premium'], 2);
            }
            if (!empty($premium_data['Vehicle_Base_COC_Premium'])) {
                $consumables_cover = round($premium_data['Vehicle_Base_COC_Premium'], 2);
            }
            if (!empty($premium_data['Vehicle_Base_RTI_Premium'])) {
                $rti = round($premium_data['Vehicle_Base_RTI_Premium'], 2);
            }
            if (!empty($premium_data['EAW_premium'])) {
                $key_replacement = round($premium_data['EAW_premium'], 2);
            }
            if (!empty($premium_data['UnnamedPerson_premium'])) {
                $pa_unnamed = round($premium_data['UnnamedPerson_premium'], 2);
            }
            if (!empty($premium_data['Electical_Acc_Premium'])) {
                $electrical_accessories = round($premium_data['Electical_Acc_Premium'], 2);
            }
            if (!empty($premium_data['NonElectical_Acc_Premium'])) {
                $non_electrical_accessories = round($premium_data['NonElectical_Acc_Premium'], 2);
            }
            if (!empty($premium_data['BiFuel_Kit_OD_Premium'])) {
                $lpg_cng = round($premium_data['BiFuel_Kit_OD_Premium'], 2);
            }
            if (!empty($premium_data['BiFuel_Kit_TP_Premium'])) {
                $lpg_cng_tp = round($premium_data['BiFuel_Kit_TP_Premium'], 2);
            }
            if (!empty($premium_data['PAPaidDriver_Premium'])) {
                $pa_paid_driver = round($premium_data['PAPaidDriver_Premium'], 2);
            }
            if (!empty($premium_data['PaidDriver_Premium'])) {
                $ll_paid_driver = round($premium_data['PaidDriver_Premium'], 2);
            }
            if (!empty($premium_data['VoluntartDisc_premium'])) {
                $voluntary_excess = round($premium_data['VoluntartDisc_premium'], 2);
            }
            if (!empty($premium_data['Vehicle_Base_TySec_Premium'])) {
                $tyre_secure = round($premium_data['Vehicle_Base_TySec_Premium'], 2);
            }
            if (!empty($premium_data['AntiTheftDisc_Premium'])) {
                $anti_theft = round($premium_data['AntiTheftDisc_Premium'], 2);
            }
            if (!empty($premium_data['Net_Premium'])) {
                $final_net_premium = round($premium_data['Net_Premium'], 2);
            }
            if (!empty($premium_data['Total_Premium'])) {
                $final_payable_amount = round($premium_data['Total_Premium'], 2);
            }
            if (!empty($premium_data['Basic_OD_Premium'])) {
                $basic_od_premium = round($premium_data['Basic_OD_Premium'], 2);
            }
            if (!empty($premium_data['Basic_TP_Premium'])) {
                $basic_tp_premium = round($premium_data['Basic_TP_Premium'], 2);
            }
            if (!empty($premium_data['TPPD_premium'])) {
                $tppd_value = round($premium_data['TPPD_premium'], 2);
            }
            if (!empty($premium_data['InBuilt_BiFuel_Kit_Premium'])) {
                $lpg_cng_tp = round($premium_data['InBuilt_BiFuel_Kit_Premium'], 2);
            }
            if(!empty($premium_data['NumberOfEmployees_Premium'])) {
                $legal_liability_to_employee = round($premium_data['NumberOfEmployees_Premium']);
            }
            if(!empty($premium_data['BatteryChargerAccessory_Premium'])) {
                $batteryProtect = round($premium_data['BatteryChargerAccessory_Premium']);
            }
            if ($electrical_accessories > 0) {
                $Nil_dep += (int)$premium_data['Elec_ZD_Premium'];
                $eng_protector += (int)$premium_data['Elec_ENG_Premium'];
                $ncb_protection += (int)$premium_data['Elec_NCB_Premium'];
                $consumables_cover += (int)$premium_data['Elec_COC_Premium'];
                $rti += (int)$premium_data['Elec_RTI_Premium'];
            }
            if ($non_electrical_accessories > 0) {
                $Nil_dep += (int)$premium_data['NonElec_ZD_Premium'];
                $eng_protector += (int)$premium_data['NonElec_ENG_Premium'];
                $ncb_protection += (int)$premium_data['NonElec_NCB_Premium'];
                $consumables_cover += (int)$premium_data['NonElec_COC_Premium'];
                $rti += (int)$premium_data['NonElec_RTI_Premium'];
            }

            if ($lpg_cng > 0) {
                $Nil_dep += (int)$premium_data['Bifuel_ZD_Premium'];
                $eng_protector += (int)$premium_data['Bifuel_ENG_Premium'];
                $ncb_protection += (int)$premium_data['Bifuel_NCB_Premium'];
                $consumables_cover += (int)$premium_data['Bifuel_COC_Premium'];
                $rti += (int)$premium_data['Bifuel_RTI_Premium'];
            }

            $requestData = getQuotation($enquiryId);
            if ($requestData->vehicle_owner_type == 'C') {
                $pa_owner_driver = 0;
            }

            $addon_premium = $Nil_dep + $tyre_secure + $consumables_cover + $ncb_protection + $roadside_asst + $key_replacement + $loss_of_personal_belongings + $eng_protector + $rti + $batteryProtect;
            $tp_premium = ($basic_tp_premium + $pa_owner_driver + $ll_paid_driver + $legal_liability_to_employee + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp) - $tppd_value + $GeogExtension_tp + $OwnPremises_TP;
            $od_premium = $premium_data['Basic_OD_Premium'] + $non_electrical_accessories + $electrical_accessories +
            $lpg_cng + $GeogExtension_od + $OwnPremises_OD - $ncb_discount;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od_premium,
                "loading_amount" => 0,
                "final_od_premium" => $od_premium + $addon_premium,
                // TP Tags
                "basic_tp_premium" => $basic_tp_premium,
                "final_tp_premium" => $tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner_driver,
                "zero_depreciation" => $Nil_dep,
                "road_side_assistance" => $roadside_asst,
                "imt_23" => 0,
                "consumable" => $consumables_cover,
                "key_replacement" => $key_replacement,
                "engine_protector" => $eng_protector,
                "ncb_protection" => $ncb_protection, // They don't provide
                "tyre_secure" => $tyre_secure,
                "return_to_invoice" => $rti,
                "loss_of_personal_belongings" => $loss_of_personal_belongings,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                "battery_protect" => $batteryProtect,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "ll_paid_employee" => $legal_liability_to_employee,
                "geo_extension_odpremium" => $GeogExtension_od,
                "geo_extension_tppremium" => $GeogExtension_tp,
                // Discounts
                "anti_theft" => $anti_theft, //They don't provide
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_value,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $premium_data['Service_Tax'],
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

    public static function saveV2PremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = json_decode($logs->response, true);
            $request = json_decode($logs->request, true);
            $requestData = getQuotation($enquiryId);

            $is_zero_dep = $request['AddOnCovers']['IsZeroDepCover'] ?? '';
            $is_ncb_protection = $request['AddOnCovers']['IsNoClaimBonusProtection'] ?? '';
            $is_key_replacement = $request['AddOnCovers']['IsEmergencyAssistanceWiderCover'] ?? '';
            $is_loss_of_personal_belongings = $request['AddOnCovers']['IsLossOfUse'] ?? '';
            $is_engine_protector = $request['AddOnCovers']['IsEngineAndGearboxProtectorCover'] ?? '';
            $is_consumable = $request['AddOnCovers']['IsCostOfConsumable'] ?? '';
            $is_return_to_invoice = $request['AddOnCovers']['IsReturntoInvoice'] ?? '';
            $is_tyre_secure = $request['AddOnCovers']['IsTyreSecureCover'] ?? '';
            $is_roadside_assistance = $request['AddOnCovers']['IsEmergencyAssistanceCover'] ?? '';
            $cpaYear = $request['AddOnCovers']['CpaYear'] ?? null;

            $basic_od = $response['Data'][0]['BasicODPremium'] ?? 0;
            $basic_tp = $response['Data'][0]['BasicTPPremium'] ?? 0;
            $electrical_accessories = 0;
            $non_electrical_accessories = 0;
            $lpg_cng_kit_od = 0;
            $cpa = 0;
            $unnamed_passenger = 0;
            $ll_paid_driver = 0;
            $legal_liability_to_employee = 0;
            $pa_paid_driver = 0;
            $zero_depreciation = 0;
            $road_side_assistance = 0;
            $ncb_protection = 0;
            $engine_protection = 0;
            $consumable = 0;
            $key_replacement = 0;
            $tyre_secure = 0;
            $return_to_invoice = 0;
            $loss_of_personal_belongings = 0;
            $lpg_cng_kit_tp = $response['Data'][0]['LpgCngKitTPPremium'] ?? 0;
            $ncb_discount = $response['Data'][0]['NewNcbDiscountAmount'] ?? 0;
            $tppd_discount = $response['Data'][0]['TppdDiscountAmount'] ?? 0;

            if (isset($response['Data'][0]['BuiltInLpgCngKitPremium']) && $response['Data'][0]['BuiltInLpgCngKitPremium'] != 0.0) {
                $lpg_cng_kit_tp = $response['Data'][0]['BuiltInLpgCngKitPremium'] ?? 0;
            }
            if (isset($response['Data'][0]['AddOnCovers'])) {
                foreach ($response['Data'][0]['AddOnCovers'] as $addon_cover) {
                    switch ($addon_cover['CoverName']) {
                        case 'ElectricalAccessoriesIdv':
                            $electrical_accessories = $addon_cover['CoverPremium'];
                            break;

                        case 'NonelectricalAccessoriesIdv':
                            $non_electrical_accessories = $addon_cover['CoverPremium'];
                            break;

                        case 'LpgCngKitIdvOD':
                            $lpg_cng_kit_od = $addon_cover['CoverPremium'];
                            break;

                        case 'LpgCngKitIdvTP':
                            $lpg_cng_kit_tp = $addon_cover['CoverPremium'];
                            break;

                        case 'PACoverOwnerDriver':
                            if (!empty($cpaYear)) {
                                $cpa = $addon_cover['CoverPremium'];
                            }
                            break;

                        case 'PACoverOwnerDriver3Year':
                            if (!empty($cpaYear) && $cpaYear == 3) {
                                $cpa = $addon_cover['CoverPremium'];
                            }
                            break;

                        case 'UnnamedPassenger':
                            $unnamed_passenger = $addon_cover['CoverPremium'];
                            break;

                        case 'LLPaidDriver':
                            $ll_paid_driver = $addon_cover['CoverPremium'];
                            break;

                        case 'LLEmployee':
                            $legal_liability_to_employee = $addon_cover['CoverPremium'];
                            break;

                        case 'PAPaidDriver':
                            $pa_paid_driver = $addon_cover['CoverPremium'];
                            break;

                        case 'ZERODEP':
                            $zero_depreciation = $is_zero_dep == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'EMERGASSIST':
                            $road_side_assistance = $is_roadside_assistance == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'NCBPROT':
                            $ncb_protection = $is_ncb_protection == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'ENGEBOX':
                            $engine_protection = $is_engine_protector == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'COSTCONS':
                            $consumable = $is_consumable == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'EMERGASSISTWIDER':
                            $key_replacement = $is_key_replacement == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'TYRESECURE':
                            $tyre_secure = $is_tyre_secure == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'RTI':
                            $return_to_invoice = $is_return_to_invoice == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'LOPB':
                        case 'LOSSUSEDOWN':
                            $loss_of_personal_belongings = $is_loss_of_personal_belongings == 'YES' ? $addon_cover['CoverPremium'] : 0;
                            break;
                        case 'UnnamedPassenger':
                            $unnamed_passenger = $addon_cover['CoverPremium'];
                            break;

                        case 'LLPaidDriver':
                            $ll_paid_driver = $addon_cover['CoverPremium'];
                            break;

                        case 'PAPaidDriver':
                            $pa_paid_driver = $addon_cover['CoverPremium'];
                            break;

                        default:
                            break;
                    }
                }
            }

            if ($requestData->vehicle_owner_type == 'C') {
                $cpa = 0;
            }

            $final_total_discount = $ncb_discount;
            $total_od_amount = $basic_od - $final_total_discount;
            $final_total_discount = $final_total_discount + $tppd_discount;
            $total_tp_amount = $basic_tp + $ll_paid_driver + $lpg_cng_kit_tp + $pa_paid_driver + $legal_liability_to_employee + $cpa + $unnamed_passenger - $tppd_discount;
            $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od + $zero_depreciation + $road_side_assistance + $ncb_protection + $consumable + $key_replacement + $tyre_secure + $engine_protection + $return_to_invoice + $loss_of_personal_belongings;

            $final_net_premium = (int) $response['Data'][0]['NetPremiumAmount'];
            $service_tax = (int) $response['Data'][0]['TaxAmount'];
            $final_payable_amount = (int) $response['Data'][0]['TotalPremiumAmount'];
            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $total_od_amount + $total_addon_amount,
                // TP Tags
                "basic_tp_premium" => $basic_tp,
                "final_tp_premium" => $total_tp_amount,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng_kit_od,
                "bifuel_tp_premium" => $lpg_cng_kit_tp,
                // Addons
                "compulsory_pa_own_driver" => $cpa,
                "zero_depreciation" => $zero_depreciation,
                "road_side_assistance" => $road_side_assistance,
                "imt_23" => 0,
                "consumable" => $consumable,
                "key_replacement" => $key_replacement,
                "engine_protector" => $engine_protection,
                "ncb_protection" => $ncb_protection, // They don't provide
                "tyre_secure" => $tyre_secure,
                "return_to_invoice" => $return_to_invoice,
                "loss_of_personal_belongings" => $loss_of_personal_belongings,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $unnamed_passenger,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_employee" => $legal_liability_to_employee,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0, //They don't provide
                "voluntary_excess" => 0, // They don't provide
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $service_tax,
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

    public static function saveRenewalPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $policy_data_response = json_decode($logs->response, true);
            $requestData = getQuotation($enquiryId);

            $all_data = $policy_data_response['Data'];
            $AddOnsOptedLastYear = explode(',', $all_data['AddOnsOptedLastYear']);
            $PrivateCarRenewalPremiumList = $all_data['PrivateCarRenewalPremiumList'][0];
            $AddOnCovers = $PrivateCarRenewalPremiumList['AddOnCovers'] ?? '';

            //OD Premium
            $basicOD = $PrivateCarRenewalPremiumList['BasicODPremium'] ?? 0;
            $ElectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['ElectricalAccessoriesPremium'] ?? 0;
            $NonelectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['NonelectricalAccessoriesPremium'] ?? 0;
            $LpgCngKitODPremium = $PrivateCarRenewalPremiumList['LpgCngKitODPremium'] ?? 0;

            $finalOd = $basicOD + $ElectricalAccessoriesPremium  + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;

            //TP Premium           
            $basic_tp = $PrivateCarRenewalPremiumList['BasicTPPremium'] ?? 0;
            $LLPaidDriversPremium = $PrivateCarRenewalPremiumList['LLPaidDriversPremium'] ?? 0;
            $UnnamedPassengerPremium = $PrivateCarRenewalPremiumList['UnnamedPassengerPremium'] ?? 0;
            $PAPaidDriverPremium = $PrivateCarRenewalPremiumList['PAPaidDriverPremium'] ?? 0;
            $PremiumNoOfLLPaidDrivers = $PrivateCarRenewalPremiumList['PremiumNoOfLLPaidDrivers'] ?? 0;
            $LpgCngKitTPPremium = $PrivateCarRenewalPremiumList['LpgCngKitTPPremium'] ?? 0;
            $PACoverOwnerDriverPremium = $PrivateCarRenewalPremiumList['PACoverOwnerDriverPremium'] ?? 0;
            $tppD_Discount = $PrivateCarRenewalPremiumList['TppdAmount'] ?? 0;

            $finalTp = $basic_tp + $LLPaidDriversPremium + $UnnamedPassengerPremium + $PAPaidDriverPremium + $PremiumNoOfLLPaidDrivers + $LpgCngKitTPPremium + $PACoverOwnerDriverPremium - $tppD_Discount;
            $NewNcbDiscountPercentage = $PrivateCarRenewalPremiumList['NewNcbDiscountPercentage'] ?? 0;
            //Discount 
            $NcbDiscountAmount = ($requestData->is_claim == 'Y' && $NewNcbDiscountPercentage == 0) ? 0 : ($PrivateCarRenewalPremiumList['NewNcbDiscountAmount'] ?? 0);
            $OtherDiscountAmount = $PrivateCarRenewalPremiumList['OtherDiscountAmount'] ?? 0;

            $zeroDepreciation           = 0;
            $engineProtect              = 0;
            $keyProtect                 = 0;
            $tyreProtect                = 0;
            $returnToInvoice            = 0;
            $lossOfPersonalBelongings   = 0;
            $roadSideAssistance         = 0;
            $consumables                = 0;
            $ncb_protection             = 0;

            
            if (is_array($AddOnCovers))  //LOPB cover is discontinued by hdfc
            {
                foreach ($AddOnCovers as $value) {
                    if (in_array($value['CoverName'], $AddOnsOptedLastYear)) {
                        if ($value['CoverName'] == 'ZERODEP') {
                            $zeroDepreciation = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'NCBPROT') {
                            $ncb_protection = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'ENGEBOX') {
                            $engineProtect = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'RTI') {
                            $returnToInvoice = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'COSTCONS') {
                            $consumables = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'EMERGASSIST') {
                            $roadSideAssistance = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'EMERGASSISTWIDER') {
                            $keyProtect = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'TYRESECURE') {
                            $tyreProtect = $value['CoverPremium'];
                        } else if ($value['CoverName'] == 'LOSSUSEDOWN') {
                            $lossOfPersonalBelongings = $value['CoverPremium'];
                        }
                    }
                }
            }

            $addons = $zeroDepreciation + $ncb_protection + $engineProtect + $returnToInvoice + $consumables + $roadSideAssistance + $keyProtect + $tyreProtect + $lossOfPersonalBelongings;
            //final calc
            $NetPremiumAmount = $finalOd + $finalTp + $addons - $NcbDiscountAmount;
            $NetPremiumAmount = $PrivateCarRenewalPremiumList['NetPremiumAmount'] ?? $NetPremiumAmount;
            $TaxAmount = round(($NetPremiumAmount * 0.18), 2);
            $TaxAmount = $PrivateCarRenewalPremiumList['TaxAmount'] ?? $TaxAmount;
            $TotalPremiumAmount = $NetPremiumAmount + $TaxAmount;
            $TotalPremiumAmount = $PrivateCarRenewalPremiumList['TotalPremiumAmount'] ?? $TotalPremiumAmount;
            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basicOD,
                "loading_amount" => 0,
                "final_od_premium" => $finalOd + $addons,
                // TP Tags
                "basic_tp_premium" => $basic_tp,
                "final_tp_premium" => $finalTp,
                // Accessories
                "electric_accessories_value" => $ElectricalAccessoriesPremium,
                "non_electric_accessories_value" => $NonelectricalAccessoriesPremium,
                "bifuel_od_premium" => $LpgCngKitODPremium,
                "bifuel_tp_premium" => $LpgCngKitTPPremium,
                // Addons
                "compulsory_pa_own_driver" => $PACoverOwnerDriverPremium,
                "zero_depreciation" => $zeroDepreciation,
                "road_side_assistance" => $roadSideAssistance,
                "imt_23" => 0,
                "consumable" => $consumables,
                "key_replacement" => $keyProtect,
                "engine_protector" => $engineProtect,
                "ncb_protection" => $ncb_protection,
                "tyre_secure" => $tyreProtect,
                "return_to_invoice" => $returnToInvoice,
                "loss_of_personal_belongings" => $lossOfPersonalBelongings,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $PAPaidDriverPremium,
                "unnamed_passenger_pa_cover" => $UnnamedPassengerPremium,
                "ll_paid_driver" => $LLPaidDriversPremium,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0, //They don't provide
                "voluntary_excess" => 0, // They don't provide
                "tppd_discount" => $tppD_Discount,
                "other_discount" => $OtherDiscountAmount,
                "ncb_discount_premium" => $NcbDiscountAmount,
                // Final tags
                "net_premium" => $NetPremiumAmount,
                "service_tax_amount" => $TaxAmount,
                "final_payable_amount" => $TotalPremiumAmount,
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
