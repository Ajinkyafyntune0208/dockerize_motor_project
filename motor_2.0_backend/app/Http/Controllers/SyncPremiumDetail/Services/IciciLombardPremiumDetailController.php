<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class IciciLombardPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $methodList = [
                'proposalService',
                getGenericMethodName('proposalService', 'proposal')
            ];
    
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId,
                'company' => 'icici_lombard'
            ])
            ->whereIn('method_name', $methodList)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    
            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (!empty($response['premiumDetails']['finalPremium'])) {
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
            $riskDetails = $response['riskDetails'] ?? [];
            
            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $riskDetails['basicOD'] ?? 0,
                "loading_amount" => 0,
                "final_od_premium" => $response['premiumDetails']['totalOwnDamagePremium'] ?? 0,
                // TP Tags
                "basic_tp_premium" => $riskDetails['basicTP'],
                "final_tp_premium" => $response['premiumDetails']['totalLiabilityPremium'] ?? 0,
                // Accessories
                "electric_accessories_value" => $riskDetails['electricalAccessories'] ?? 0,
                "non_electric_accessories_value" => $riskDetails['nonElectricalAccessories'] ?? 0,
                "bifuel_od_premium" => $riskDetails['biFuelKitOD'] ?? 0,
                "bifuel_tp_premium" => $riskDetails['biFuelKitTP'] ?? 0,
                // Addons
                "compulsory_pa_own_driver" => $riskDetails['paCoverForOwnerDriver'] ?? 0,
                "zero_depreciation" => $riskDetails['zeroDepreciation'] ?? 0,
                "road_side_assistance" => $riskDetails['roadSideAssistance'] ?? 0,
                "imt_23" => $riskDetails['imT23OD'] ?? 0,
                "consumable" => $riskDetails['consumables'] ?? 0,
                "key_replacement" => $riskDetails['keyProtect'] ?? 0,
                "engine_protector" => $riskDetails['engineProtect'] ?? 0,
                "ncb_protection" => 0,
                "tyre_secure" => $riskDetails['tyreProtect'] ?? 0,
                "return_to_invoice" => $riskDetails['returnToInvoice'] ?? 0,
                "loss_of_personal_belongings" => $riskDetails['lossOfPersonalBelongings'] ?? 0,
                "eme_cover" => $riskDetails['emeCover'] ?? 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $riskDetails['paCoverForUnNamedPassenger'] ?? 0,
                "ll_paid_driver" => $riskDetails['paidDriver'] ?? 0,
                "ll_paid_conductor" => $riskDetails['legalLiabilityforCCC'] ?? 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $riskDetails['antiTheftDiscount'] ?? 0,
                "voluntary_excess" => $riskDetails['voluntaryDiscount'] ?? 0,
                "tppd_discount" => $riskDetails['tppD_Discount'] ?? 0,
                "other_discount" => 0,
                "ncb_discount_premium" => $riskDetails['bonusDiscount'] ?? 0,
                // Final tags
                "net_premium" => $response['premiumDetails']['packagePremium'] ?? $response['premiumDetails']['totalLiabilityPremium'] ?? 0,
                "service_tax_amount" => $response['premiumDetails']['totalTax'] ?? 0,
                "final_payable_amount" => $response['premiumDetails']['finalPremium'] ?? 0,
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
