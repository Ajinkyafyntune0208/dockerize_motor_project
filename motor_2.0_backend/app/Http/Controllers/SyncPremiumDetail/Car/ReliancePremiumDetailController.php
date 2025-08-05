<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class ReliancePremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $methodList = [
                'Proposal Creation',
                getGenericMethodName('Proposal Creation', 'proposal'),
                'Premium Calculation',
                getGenericMethodName('Premium Calculation', 'proposal'),
                'Proposal Creation for Post Inspection',
                getGenericMethodName('Proposal Creation for Post Inspection', 'proposal')
            ];
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Creation',
                    getGenericMethodName('Proposal Creation', 'proposal'),
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal'),
                    'Proposal Creation for Post Inspection',
                    getGenericMethodName('Proposal Creation for Post Inspection', 'proposal')
                ];
            }
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'reliance'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response);
                if (!empty($response) && ($response->MotorPolicy->status ?? '') == '1') {
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
            $response = json_decode($response->response);

            $proposal_resp = $response->MotorPolicy;
            unset($proposal_res_data);
            $basic_od = 0;
            $tppd = 0;
            $pa_owner = 0;
            $pa_unnamed = 0;
            $pa_paid_driver = 0;
            $electrical_accessories = 0;
            $non_electrical_accessories = 0;
            $zero_dep_amount = 0;
            $ncb_discount = 0;
            $lpg_cng = 0;
            $lpg_cng_tp = 0;
            $automobile_association = 0;
            $anti_theft = 0;
            $liabilities = 0;
            $voluntary_deductible = 0;
            $tppd_discount = 0;
            $other_addon_amount = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $RTIAddonPremium = '0';
            $inspection_charges = !empty((int) $proposal_resp->InspectionCharges) ? (int) $proposal_resp->InspectionCharges : 0;
            $basic_own_damage = 0;
            $liability_to_employee_premium = $rsaAddonPremium = 0;
            $in_built_addons = [];

            $cpa_premium = 0;
            $proposal_resp->lstPricingResponse = is_object($proposal_resp->lstPricingResponse) ? [$proposal_resp->lstPricingResponse] : $proposal_resp->lstPricingResponse;
            foreach ($proposal_resp->lstPricingResponse as $single) {
                if (isset($single->CoverageName)) {
                    if ($single->CoverageName == 'Basic OD') {
                        $basic_own_damage = $single->Premium + $inspection_charges;
                    } else if ($single->CoverageName == 'PA to Owner Driver') {
                        $cpa_premium = $single->Premium;
                    } elseif (($single->CoverageName == 'Nil Depreciation')) {
                        $zero_dep_amount = $single->Premium;
                    } elseif ($single->CoverageName == 'Bifuel Kit') {
                        $lpg_cng = $single->Premium;
                    } elseif ($single->CoverageName == 'Electrical Accessories') {
                        $electrical_accessories = $single->Premium;
                    } elseif ($single->CoverageName == 'Non Electrical Accessories') {
                        $non_electrical_accessories = $single->Premium;
                    } elseif ($single->CoverageName == 'NCB') {
                        $ncb_discount = abs((float) $single->Premium);
                    } elseif ($single->CoverageName == 'Total OD and Addon') {
                        $basic_od = abs((float) $single->Premium);
                    } elseif ($single->CoverageName == 'Secure Plus' || $single->CoverageName == 'Secure Premium') {
                        $other_addon_amount = abs((float) $single->Premium);
                        if ($single->CoverageName == 'Secure Premium') {
                            $in_built_addons = [
                                'zero_depreciation' => $other_addon_amount,
                                'engine_protector' => 0,
                                'consumable' => 0,
                                'key_replacement' => 0,
                                'tyre_secure' => 0,
                                'loss_of_personal_belongings' => 0,
                            ];
                        }
                        if ($single->CoverageName == 'Secure Plus') {
                            $in_built_addons = [
                                'zero_depreciation' => $other_addon_amount,
                                'engine_protector' => 0,
                                'consumable' => 0,
                                'key_replacement' => 0,
                                'loss_of_personal_belongings' => 0,
                            ];
                        }
                    } elseif ($single->CoverageName == 'Basic Liability') {
                        $tppd = round(abs((float) $single->Premium), 2);
                    } elseif ($single->CoverageName == 'PA to Unnamed Passenger') {
                        $pa_unnamed = $single->Premium;
                    } elseif ($single->CoverageName == 'PA to Paid Driver') {
                        $pa_paid_driver = $single->Premium;
                    } elseif ($single->CoverageName == 'Liability to Paid Driver') {
                        $liabilities = $single->Premium;
                    } elseif ($single->CoverageName == 'Bifuel Kit TP') {
                        $lpg_cng_tp = $single->Premium;
                    } elseif ($single->CoverageName == 'Automobile Association Membership') {
                        $automobile_association = round(abs($single->Premium), 2);
                    } elseif ($single->CoverageName == 'Anti-Theft Device') {
                        $anti_theft = abs($single->Premium);
                    } elseif ($single->CoverageName == 'Voluntary Deductible') {
                        $voluntary_deductible = abs($single->Premium);
                    } elseif ($single->CoverageName == 'TPPD') {
                        $tppd_discount = abs($single->Premium);
                    } elseif (in_array($single->CoverageName, ['Geographical Extension', 'Geo Extension']) && $single->CoverID == 5) {
                        $geog_Extension_OD_Premium = abs($single->Premium);
                    } elseif ($single->CoverageName == 'Geographical Extension' && ($single->CoverID =='6' || $single->CoverID == '403')) {
                        $geog_Extension_TP_Premium = round(abs($single->Premium), 2);
                    } elseif (in_array($single->CoverageName, ['Return to Invoice', 'Return to invoice'])) {
                        $RTIAddonPremium = round(abs($single->Premium ?? 0), 2);
                    }
                    elseif ($single->CoverageName == 'Liability to Employees') {
                        $liability_to_employee_premium = round(abs($single->Premium ?? 0), 2);
                    } elseif($single->CoverageName == 'Assistance cover- 24/7 RSA') {
                        $rsaAddonPremium = round(abs($single->Premium ?? 0), 2);
                    }
                }
            }
            $NetPremium = $proposal_resp->NetPremium;
            $total_tp_amount = $tppd + $liabilities + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $cpa_premium - $tppd_discount + $geog_Extension_TP_Premium + $liability_to_employee_premium;
            $final_payable_amount = $proposal_resp->FinalPremium;

            $final_od_premium = $proposal_resp->TotalOD ?? 0;
            
            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_own_damage,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $total_tp_amount,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $cpa_premium,
                "zero_depreciation" => $zero_dep_amount + $other_addon_amount, // $other_addon_amount is available in case of NB
                "road_side_assistance" => $rsaAddonPremium,
                "imt_23" => 0,
                "consumable" => 0,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => $RTIAddonPremium,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $liabilities,
                "ll_paid_employee" => $liability_to_employee_premium,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => $geog_Extension_OD_Premium,
                "geo_extension_tppremium" => $geog_Extension_TP_Premium,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_deductible,
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $NetPremium,
                "service_tax_amount" => $final_payable_amount - $NetPremium,
                "final_payable_amount" => $final_payable_amount,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? $value : 0;
            }, $updatePremiumDetails);

            foreach ($in_built_addons as $key => $value) {
                $in_built_addons[$key] = !empty($value) && is_numeric($value) ? $value : 0;
            }
            $updatePremiumDetails['in_built_addons'] = $in_built_addons;

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
