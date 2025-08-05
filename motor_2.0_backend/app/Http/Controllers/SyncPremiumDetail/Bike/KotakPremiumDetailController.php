<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class KotakPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';
            if ($isRenewal) {
                return [
                    'status' => false,
                    'message' => 'Integration not yet done.',
                ];
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
                if (($response['verrormsg'] ?? '') == 'Success' && !empty($response['vnetpremium'])) {
                    $webserviceId = $log['id'];
                    break;
                }
            }

            if (!empty($webserviceId)) {
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
            $tp = $response['vBasicTPPremium'];
            $lpg_cng_tp = $response['vCngLpgKitPremiumTP'];
            $llpaiddriver = $response['vLegalLiabilityPaidDriverNo'];
            $paid_driver = $response['vPANoOfEmployeeforPaidDriverPremium'];
            $pa_unnamed = $response['vPAForUnnamedPassengerPremium'];
            $final_tp_premium = $tp + $lpg_cng_tp + $llpaiddriver  + $paid_driver + $pa_unnamed;

            // od calculation
            $od = ($response['vOwnDamagePremium']);
            $non_electrical_accessories = ($response['vNonElectronicSI']);
            $electrical_accessories = ($response['vElectronicSI']);
            $lpg_cng = ($response['vCngLpgKitPremium']);
            $final_od_premium = $od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $response['vOwnDamagePremium'] ?? 0,
                "loading_amount" => $response['breakinLoadingAmount'] ?? 0,
                "final_od_premium" => $final_od_premium ?? 0,
                // TP Tags
                "basic_tp_premium" => $response['vBasicTPPremium'] ?? 0,
                "final_tp_premium" => $response['vTotalPremiumLiability'] ?? 0,
                // Accessories
                "electric_accessories_value" => $response['vElectronicSI'] ?? 0,
                "non_electric_accessories_value" => $response['vNonElectronicSI'] ?? 0,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
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
                "pa_additional_driver" => $response['vPANoOfEmployeeforPaidDriverPremium'] ?? 0,
                "unnamed_passenger_pa_cover" => $response['vPAForUnnamedPassengerPremium'] ?? 0,
                "ll_paid_driver" => $response['vLegalLiabilityPaidDriverNo'] ?? 0,
                "geo_extension_odpremium" => $response['vGeoODPrem'],
                "geo_extension_tppremium" => $response['vGeoTPPrem'],
                // Discounts
                "anti_theft" => $response['vAntiTheftAmnt'] ?? 0,
                "voluntary_excess" => $response['vVoluntaryDeduction'] ?? 0,
                "tppd_discount" => $response['tppD_Discount'] ?? 0,
                "other_discount" => 0,
                "ncb_discount_premium" => $response['vNCB'] ?? 0,
                // Final tags
                "net_premium" => $response['vNetPremium'] ?? 0,
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
}
