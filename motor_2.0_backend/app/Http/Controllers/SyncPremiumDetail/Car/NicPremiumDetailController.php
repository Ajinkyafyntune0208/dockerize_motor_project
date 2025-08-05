<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;

class NicPremiumDetailController extends Controller
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
                    'Proposal Submit',
                    getGenericMethodName('Proposal Submit', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Proposal Submition - Proposal',
                    getGenericMethodName('Proposal Submition - Proposal', 'proposal')
                ];
            }
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId,
                'company' => 'nic'
            ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (!empty($response) && !empty($response['finalPremium'])) {
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
            $corporateVehicleData = CorporateVehiclesQuotesRequest::select('business_type')->where('user_product_journey_id', $enquiryId)->first();
            $is_breakin = !empty($corporateVehicleData) && strpos($corporateVehicleData->business_type, 'breakin') !== false;
            $response = json_decode($response->response, true);

            $cover_codes = [
                'C0003009' => 'return_to_invoice',
                'C0003015' => 'rsa',
                'C0003003' => 'zero_dep',
                'C0003010' => 'engine_protect',
                'C0003012' => 'key_replace',
                'C0003011' => 'consumable',
                'C0003014' => 'Loss_of_belonging',
                'C0001004' => 'tyre_secure',
                'C0003017' => 'ncb_protection',
                // 'C0003006' => 'own_damage',
                'B00817' => 'legal_liability_to_employee',
                'B00818'   => 'legal_liability_driver_cleaner',
                'B00823'   => 'third_party_basic',
                'B00811'   => 'compulsory_pa',
                'B00813'   => 'electrical_accessories',
                'B00814'   => 'non_electrical_accessories',
                'B00815'   => 'cng_kit',
                'B00812'   => 'own_damage_basic',
                'B00821'   => 'optional_pa_paid_driver_cleaner',
                'B00822'   => 'optional_unnamed_persons',
            ];

            $covers = [
                'return_to_invoice'             => 0,
                'rsa'                           => 0,
                'zero_dep'                      => 0,
                'engine_protect'                => 0,
                'key_replace'                   => 0,
                'consumable'                    => 0,
                'Loss_of_belonging'             => 0,
                'tyre_secure'                   => 0,
                'ncb_protection'                => 0,
                // 'own_damage'                    => 0,
                'compulsory_pa'                 => 0,
                'electrical_accessories'        => 0,
                'non_electrical_accessories'    => 0,
                'cng_kit'                       => 0,
                'own_damage_basic'              => 0,
                'legal_liability_driver_cleaner' => 0,
                'third_party_basic'              => 0,
                'optional_pa_paid_driver_cleaner' => 0,
                'optional_unnamed_persons'      => 0,
                'legal_liability_to_employee'   => 0
            ];

            foreach ($response['PolicyObject']['PolicyLobList'] as $lob) {
                foreach ($lob['PolicyRiskList'] as $risk) {
                    foreach ($risk['PolicyCoverageList'] as $coverage) {
                        $productElementCode = $coverage['ProductElementCode'];
                        if (isset($cover_codes[$productElementCode])) {
                            $key = $cover_codes[$productElementCode];
                            $covers[$key] = $coverage['GrossPremium'];
                        }
                        if (isset($coverage['PolicyBenefitList'])) {
                            foreach ($coverage['PolicyBenefitList'] as $benefit) {
                                $benefitCode = $benefit['ProductElementCode'];
                                if (isset($cover_codes[$benefitCode])) {
                                    $key = $cover_codes[$benefitCode];
                                    $covers[$key] = $benefit['GrossPremium'];
                                }
                            }
                        }
                    }
                }
            }
            $covers['ncb'] = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['NCBAmount'] ?? 0;
            $AADiscountAmount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['AADiscountAmount'] ?? 0;
            $index = $is_breakin ? 0 : 1;
            $lpg_cng_tp_amount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index]['CNGLPGKitLiabilityPremium'] ?? 0;
            $total_od_premium           =  $covers['own_damage_basic'] + $covers['electrical_accessories'] + $covers['non_electrical_accessories'] + $covers['cng_kit'];

            $total_add_ons_premium      = $covers['rsa'] + $covers['return_to_invoice'] + $covers['zero_dep'] + $covers['engine_protect'] + $covers['key_replace'] + $covers['consumable'] + $covers['Loss_of_belonging'] + $covers['tyre_secure'] + $covers['ncb_protection'];

            $total_tp_premium           = $covers['third_party_basic'] + $lpg_cng_tp_amount + $covers['legal_liability_driver_cleaner'] + $covers['optional_unnamed_persons'] + $covers['optional_pa_paid_driver_cleaner'] + $covers['compulsory_pa'] + $covers['legal_liability_to_employee'];

            $total_discount_premium     = $covers['ncb'] + $AADiscountAmount;
            $basePremium = $total_od_premium + $total_tp_premium +  $total_add_ons_premium - $total_discount_premium;

            $totalTax = $basePremium * 0.18;
            $final_premium = $basePremium + $totalTax;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $covers['own_damage_basic'] ?? 0,
                "loading_amount" => 0,
                "final_od_premium" => $total_od_premium ?? 0,
                // TP Tags
                "basic_tp_premium" => $covers['third_party_basic'],
                "final_tp_premium" => $total_tp_premium ?? 0,
                // Accessories
                "electric_accessories_value" => $covers['electrical_accessories'] ?? 0,
                "non_electric_accessories_value" => $covers['non_electrical_accessories'] ?? 0,
                "bifuel_od_premium" => $covers['cng_kit'] ?? 0,
                "bifuel_tp_premium" => $lpg_cng_tp_amount ?? 0,
                // Addons
                "compulsory_pa_own_driver" => $covers['compulsory_pa'] ?? 0,
                "zero_depreciation" => $covers['zero_dep'] ?? 0,
                "road_side_assistance" => $covers['rsa'] ?? 0,
                "imt_23" => 0,
                "consumable" => $covers['consumable'] ?? 0,
                "key_replacement" => $covers['key_replace'] ?? 0,
                "engine_protector" => $covers['engine_protect'] ?? 0,
                "ncb_protection" => $covers['ncb_protection'] ?? 0,
                "tyre_secure" => $covers['tyre_secure'] ?? 0,
                "return_to_invoice" => $covers['return_to_invoice'] ?? 0,
                "loss_of_personal_belongings" => $covers['Loss_of_belonging'] ?? 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $covers['optional_pa_paid_driver_cleaner'],
                "unnamed_passenger_pa_cover" => $covers['optional_unnamed_persons'] ?? 0,
                "ll_paid_driver" => $covers['legal_liability_driver_cleaner'] ?? 0,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "ll_paid_employee" => $covers['legal_liability_to_employee'] ?? 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0,
                "voluntary_excess" => 0,
                "tppd_discount" => 0,
                "other_discount" => 0,
                "ncb_discount_premium" => $covers['ncb'] ?? 0,
                // Final tags
                "net_premium" => $basePremium ?? 0,
                "service_tax_amount" => $totalTax ?? 0,
                "final_payable_amount" => round($final_premium) ?? 0,
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
