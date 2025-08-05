<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Http\Request;
use App\Models\WebServiceRequestResponse;


class KotakPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';
            if ($isRenewal) {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal'),
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
                    'company' => 'kotak'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (!empty($response['ntotalpremium']) || !empty($response['nTotalPremium']) || !empty($response['vTotalPremium'])) {
                    $webserviceId = $log['id'];
                    break;
                }
            }

            if (!empty($webserviceId)) {
                if ($isRenewal) {
                    return self::saveRenewalPremiumDetails($webserviceId);
                }
                return self::savePremiumDetails($webserviceId);
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
            $response = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $response->enquiry_id;
            $response = json_decode($response->response, true);
            // tp calculation
            $paid_driver_tp = 0;
            $tp = $response['vBasicTPPremium'];
            $lpg_cng_tp = $response['vCngLpgKitPremiumTP'];
            $llpaiddriver = $response['vLegalLiabilityPaidDriverNo'];
            $paid_driver = $response['vPANoOfEmployeeforPaidDriverPremium'];
            $paid_driver = $response['vPaidDriverlegalliability'];
            $pa_unnamed = $response['vPAForUnnamedPassengerPremium'];
            $final_tp_premium = $tp + $lpg_cng_tp + $llpaiddriver  + $paid_driver + $pa_unnamed + $paid_driver_tp;

            // od calculation
            $od = ($response['vOwnDamagePremium']);
            $non_electrical_accessories = ($response['vNonElectronicSI']);
            $electrical_accessories = ($response['vElectronicSI']);
            $lpg_cng = ($response['vCngLpgKitPremium']);
            $final_od_premium = $od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;

            $addons = [
                "zero_depreciation" => $response['vDepreciationCover'] ?? 0,
                "road_side_assistance" => $response['vRSA'] ?? 0,
                "imt_23" => $response['imT23OD'] ?? 0,
                "consumable" => $response['vConsumableCover'] ?? 0,
                "key_replacement" => $response['nKeyReplacementPremium'] ?? 0,
                "engine_protector" => $response['vEngineProtect'] ?? 0,
                "ncb_protection" => $response['nNCBProtectPremium'] ?? 0,
                "tyre_secure" => $response['nTyreCoverPremium'] ?? 0,
                "return_to_invoice" => $response['vReturnToInvoice'] ?? 0,
                "loss_of_personal_belongings" => $response['nLossPersonalBelongingsPremium'] ?? 0,
                "eme_cover" => $response['emeCover'] ?? 0,
            ];

            $final_od_premium += array_sum($addons);
            $discount = [
                "anti_theft" => $response['vAntiTheftAmnt'] ?? 0,
                "voluntary_excess" => $response['vVoluntaryDeduction'] ?? 0,
                "tppd_discount" => $response['tppD_Discount'] ?? 0,
                "other_discount" => 0,
                "ncb_discount_premium" => $response['vNCB'] ?? 0,
            ];
            $final_od_premium -= array_sum($discount);


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $response['vOwnDamagePremium'] ?? 0,
                "loading_amount" => $response['breakinLoadingAmount'] ?? 0,
                "final_od_premium" => $response['vTotalOwnDamagePremium'] ?? $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $response['vBasicTPPremium'] ?? 0,
                "final_tp_premium" => $response['vTotalPremiumLiability'] ?? $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $response['vElectronicSI'] ?? 0,
                "non_electric_accessories_value" => $response['vNonElectronicSI'] ?? 0,
                "bifuel_od_premium" => $response['vCngLpgKitPremium'] ?? 0,
                "bifuel_tp_premium" => $response['vCngLpgKitPremiumTP'] ?? 0,
                // Addons
                "compulsory_pa_own_driver" => $response['vPACoverForOwnDriver'] ?? 0,
                "zero_depreciation" => $response['vDepreciationCover'] ?? 0,
                "road_side_assistance" => $response['vRSA'] ?? 0,
                "imt_23" => $response['imT23OD'] ?? 0,
                "consumable" => $response['vConsumableCover'] ?? 0,
                "key_replacement" => $response['nKeyReplacementPremium'] ?? 0,
                "engine_protector" => $response['vEngineProtect'] ?? 0,
                "ncb_protection" => $response['nNCBProtectPremium'] ?? 0,
                "tyre_secure" => $response['nTyreCoverPremium'] ?? 0,
                "return_to_invoice" => $response['vReturnToInvoice'] ?? 0,
                "loss_of_personal_belongings" => $response['nLossPersonalBelongingsPremium'] ?? 0,
                "eme_cover" => $response['emeCover'] ?? 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                //in case of tp premium comes in this tag  vPaidDriverlegalliability 
                "pa_additional_driver" => !empty($response['vPANoOfEmployeeforPaidDriverPremium']) ? $response['vPANoOfEmployeeforPaidDriverPremium'] : $response['vPaidDriverlegalliability'] ?? 0,
                "unnamed_passenger_pa_cover" => $response['vPAForUnnamedPassengerPremium'] ?? 0,
                "ll_paid_driver" => $response['vLegalLiabilityPaidDriverNo'] ?? 0,
                "ll_paid_employee" => $response['vLLEOPDCC'] ?? 0,
                "geo_extension_odpremium" => $response['vGeoODPrem'],
                "geo_extension_tppremium" => $response['vGeoTPPrem'],
                // Discounts
                "anti_theft" => $response['vAntiTheftAmnt'] ?? 0,
                "voluntary_excess" => $response['vVoluntaryDeduction'] ?? 0,
                "tppd_discount" => $response['tppD_Discount'] ?? 0,
                "other_discount" => 0,
                "ncb_discount_premium" => $response['vNCB'] ?? 0,
                // Final tags
                "net_premium" => $response['vNetPremium']  ?? 0,
                "service_tax_amount" => $response['vGSTAmount'] ?? 0,
                "final_payable_amount" => $response['vTotalPremium'] ?? 0,
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
            $response = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $response->enquiry_id;
            $response = json_decode($response->response, true);

            $tp =  $od = $rsa = $zero_dep = $consumable = $eng_protect = $rti = $tyre_secure =
            $electrical_accessories = $non_electrical_accessories = $lpg_cng = $lopb =
            $lpg_cng_tp = $pa_owner = $llpaiddriver = $pa_unnamed = $paid_driver = $paid_driver_tp = $eme_cover =
            $voluntary_deduction_zero_dep = $key_replacement = $ncb_protect = 0;
            $imt_23 = 0;

            if (isset($response['vBasicTPPremium'])) {
                $tp = ($response['vBasicTPPremium']);
            }
            if (isset($response['vPACoverForOwnDriver'])) {
                $pa_owner = ($response['vPACoverForOwnDriver']);
            }
            if (isset($response['vPAForUnnamedPassengerPremium'])) {
                $pa_unnamed = ($response['vPAForUnnamedPassengerPremium']);
            }
            if (isset($response['vCngLpgKitPremiumTP'])) {
                $lpg_cng_tp = ($response['vCngLpgKitPremiumTP']);
            }
            if (isset($response['vPANoOfEmployeeforPaidDriverPremium'])) {
                $paid_driver = ($response['vPANoOfEmployeeforPaidDriverPremium']);
            }

            if (isset($response['vPaidDriverlegalliability'])) {
                $paid_driver_tp = ($response['vPaidDriverlegalliability']);
            }

            if (isset($response['vLegalLiabilityPaidDriverNo'])) {
                $llpaiddriver = ($response['vLegalLiabilityPaidDriverNo']);
            }
            if (isset($response['vDepreciationCover'])) {
                $zero_dep = ($response['vDepreciationCover']);
            }
            if (isset($response['vRSA'])) {
                $rsa = ($response['vRSA']);
            }
            if (isset($response['nNCBProtectPremium'])) {
                $ncb_protect = ($response['nNCBProtectPremium']);
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
            if (isset($response['imT23OD'])) {
                $imt_23 = ($response['imT23OD']);
            }
            if (isset($response['nKeyReplacementPremium'])) {
                $key_replacement = ($response['nKeyReplacementPremium']);
            }
            if (isset($response['nTyreCoverPremium'])) {
                $tyre_secure = ($response['nTyreCoverPremium']);
            }
            if (isset($response['nLossPersonalBelongingsPremium'])) {
                $lopb = ($response['nLossPersonalBelongingsPremium']);
            }
            if (isset($response['emeCover'])) {
                $eme_cover = ($response['emeCover']);
            }
            if (isset($response['vElectronicSI'])) {
                $electrical_accessories = ($response['vElectronicSI']);
            }
            if (isset($response['vNonElectronicSI'])) {
                $non_electrical_accessories = ($response['vNonElectronicSI']);
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

            $final_tp_premium = $tp + $lpg_cng_tp + $llpaiddriver +  $paid_driver + $pa_unnamed + $pa_owner + $paid_driver_tp;
            $final_payable_amount = str_replace("INR ", "", $response['nTotalPremium']);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od,
                "loading_amount" => 0,
                "final_od_premium" => $response['vTotalOwnDamagePremium'] ?? 0,
                // TP Tags
                "basic_tp_premium" => $response['vBasicTPPremium'] ?? 0,
                "final_tp_premium" => $response['vTotalPremiumLiability'] ?? $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep,
                "road_side_assistance" => $rsa,
                "imt_23" => $imt_23,
                "consumable" => $consumable,
                "key_replacement" => $key_replacement,
                "engine_protector" => $eng_protect,
                "ncb_protection" => $ncb_protect,
                "tyre_secure" => $tyre_secure,
                "return_to_invoice" => $rti,
                "loss_of_personal_belongings" => $lopb,
                "eme_cover" => $eme_cover,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => empty($paid_driver) ? $paid_driver_tp : $paid_driver ,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $llpaiddriver,
                "geo_extension_odpremium" => $response['vGeoODPrem'] ?? 0,
                "geo_extension_tppremium" => $response['vGeoTPPrem'] ?? 0,
                // Discounts
                "anti_theft" => $response['vAntiTheftAmnt'] ?? 0,
                "voluntary_excess" => $voluntary_deduction_zero_dep,
                "tppd_discount" => $response['tppD_Discount'] ?? 0,
                "other_discount" => 0,
                "ncb_discount_premium" => $response['vNCB'] ?? 0,
                // Final tags
                "net_premium" => $response['vNetPremium']  ?? 0,
                "service_tax_amount" => $response['vGSTAmount'] ?? 0,
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
