<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class BajajAllianzPremiumDetailController extends Controller
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
            } else {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id', 'request')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'bajaj_allianz'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                try {
                    $response = XmlToArray::convert($response);
                    $response = $response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'] ?? $response['env:Body']['m:calculateMotorPremiumSigResponse'] ?? null;
                } catch (\Throwable $th) {
                    $response = null;
                }

                if (!empty($response) && !empty($response['premiumDetailsOut_out']['typ:finalPremium'])) {
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
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = $logs->response;

            $response = XmlToArray::convert($response);
            $service_response = $response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'] ?? $response['env:Body']['m:calculateMotorPremiumSigResponse'] ?? null;

            $zero_dep_amount = $key_replacement = $basic_od = $tppd = $pa_owner = $non_electrical_accessories = $electrical_accessories = $pa_unnamed = $ll_paid_driver = $lpg_cng = $lpg_cng_tp = $other_discount = $ncb_discount = $engine_protector = $lossbaggage = $rsa = $accident_shield = $conynBenef = $consExps = $voluntary_deductible = 0;
            $restricted_tppd = 0;
            $antitheft_discount_amount = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $uw_loading_amount = 0;

            $final_payable_amount = $service_response['premiumDetailsOut_out']['typ:finalPremium'];

            $loadingAmount = $service_response['premiumDetailsOut_out']['typ:addLoadPrem'] ?? 0;
            if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) {
                $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];

                if (isset($covers['od'])) {
                    $basic_od = ($covers['od']);
                } else if (!isset($covers[0])) {
                    $coverName = $covers['typ:paramDesc'];
                    if ($coverName == 'Basic Third Party Liability') {
                        $tppd = $covers['typ:act'];
                    }

                    if (in_array($coverName, ['Basic Own Damage', 'Basic Own Damage 1'])) {
                        $basic_od = round($covers['typ:od'], 2);
                    }
                } else {
                    foreach ($covers as $key => $cover) {
                        if (!isset($cover['typ:paramDesc'])) {
                            continue;
                        }
                        if (($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                            $zero_dep_amount = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'KEYS AND LOCKS REPLACEMENT COVER') {
                            $key_replacement = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                            $engine_protector = round($cover['typ:od'], 2);
                        }
                        // elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') 
                        elseif (in_array($cover['typ:paramDesc'], ['Basic Own Damage', 'Basic Own Damage 1'])) {
                            $basic_od = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                            $tppd = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                            $pa_owner = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                            $non_electrical_accessories = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                            $electrical_accessories = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                            $pa_unnamed = round($cover['typ:act'], 2);
                        } elseif (in_array($cover['typ:paramDesc'], ['LL To Person For Operation/Maintenance(IMT.28/39)', '19LL To Person For Operation/Maintenance(IMT.28/39)'])) {
                            $ll_paid_driver = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'CNG / LPG Unit (IMT.25)') {
                            $lpg_cng = round($cover['typ:od'], 2);
                            $lpg_cng_tp = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Commercial Discount') {
                            $other_discount = round(abs($cover['typ:od']), 2);
                        } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                            $ncb_discount = round(abs($cover['typ:od']), 2);
                        } elseif ($cover['typ:paramDesc'] === 'Personal Baggage Cover') {
                            $lossbaggage = round(abs($cover['typ:od']), 2);
                        } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                            $rsa = round(abs($cover['typ:od']), 2);
                        } elseif ($cover['typ:paramDesc'] === 'Accident Sheild') {
                            $accident_shield = round(abs($cover['typ:od']), 2);
                        } elseif ($cover['typ:paramDesc'] === 'Conveyance Benefit') {
                            $conynBenef = round(abs($cover['typ:od']), 2);
                        } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                            $consExps = round(abs($cover['typ:od']), 2);
                        } elseif (in_array($cover['typ:paramDesc'], ['Voluntary Excess (IMT.22 A)', '6Voluntary Excess (IMT.22 A)'])) {
                            $voluntary_deductible = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                            $restricted_tppd = round($cover['typ:act'], 2);
                        } elseif (in_array($cover['typ:paramDesc'], ['10Anti-Theft Device (IMT.10)', 'Anti-Theft Device (IMT.10)'])) {
                            $antitheft_discount_amount = round(abs($cover['typ:od']), 2);
                        } elseif (in_array($cover['typ:paramDesc'], ['CHDH Additional Discount/Loading', 'CHDH Additional Discount/Loading '])) {
                            $uw_loading_amount = round(abs($cover['typ:od']), 2);
                        }
                    }
                }
            }

            $all_addons = [
                'zero_depreciation' => $zero_dep_amount,
                'road_side_assistance' => $rsa,
                'engine_protector' => $engine_protector,
                'ncb_protection' => 0, // Bajaj doesn't provide NCB
                'key_replace' => $key_replacement,
                'consumables' => $consExps,
                'tyre_secure' => 0, // Bajaj doesn't provide Tyre Secure
                'return_to_invoice' => 0, // Bajaj doesn't provide RTI
                'lopb' => $lossbaggage,
                'Accident_shield' => $accident_shield,
                'Conveyance_Benefit' => $conynBenef,
            ];

            $uw_loading_amount+= $loadingAmount;

            $addon_premium = array_sum($all_addons);
            $ExtraPremiumForRejectedRTO = is_array($service_response['pDetariffObj_inout']['typ:extCol22']) ? 0 : $service_response['pDetariffObj_inout']['typ:extCol22'];


            $tppd = $tppd + $restricted_tppd;
            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories
                + $lpg_cng + $ExtraPremiumForRejectedRTO;
            $totalTP = $tppd + $pa_unnamed + $ll_paid_driver + $lpg_cng_tp + $pa_owner - abs($restricted_tppd);
            $total_od_discount = abs($ncb_discount) + abs($other_discount) + abs($voluntary_deductible)  + abs($antitheft_discount_amount);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => $uw_loading_amount + $ExtraPremiumForRejectedRTO,
                "final_od_premium" => ($service_response['premiumDetailsOut_out']['typ:totalOdPremium'] + $ExtraPremiumForRejectedRTO) ?? ($final_od_premium + $addon_premium - $total_od_discount),
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $totalTP,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => $rsa,
                "imt_23" => 0,
                "consumable" => $consExps,
                "key_replacement" => $key_replacement,
                "engine_protector" => $engine_protector,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => $lossbaggage,
                "eme_cover" => 0,
                "accident_shield" => $accident_shield,
                "conveyance_benefit" => $conynBenef,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $antitheft_discount_amount,
                "voluntary_excess" => $voluntary_deductible,
                "tppd_discount" => $restricted_tppd,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $service_response['premiumDetailsOut_out']['typ:netPremium'],
                "service_tax_amount" => $service_response['premiumDetailsOut_out']['typ:serviceTax'],
                "final_payable_amount" => $final_payable_amount,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? round(abs($value), 2) : 0;
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
