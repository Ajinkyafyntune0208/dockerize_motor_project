<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

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
            $type = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                try {
                    $premium_response = XmlToArray::convert($response);
                    $premium_response = $premium_response['env:Body']['m:calculateMotorPremiumSigResponse'];
                } catch (\Throwable $th) {
                    $premium_response = json_decode($response, true);
                }

                if (!empty($premium_response['premiumdetails']['finalpremium'])) {
                    $type = 'JSON';
                    $webserviceId = $log['id'];
                    break;
                }


                if (!empty($premium_response['premiumDetailsOut_out']['typ:finalPremium'])) {
                    $type = 'XML';
                    $webserviceId = $log['id'];
                    break;
                }
            }

            if (!empty($webserviceId)) {
                if ($type == 'XML') {
                    return self::savePremiumDetails($webserviceId);
                }
                return self::saveJsonPremiumDetails($webserviceId);
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

            $zero_dep_amount = $key_replacement = $basic_od = $tp_amount = $pa_owner =
                $non_electrical_accessories = $electrical_accessories = $pa_unnamed =
                $ll_paid_driver = $lpg_cng = $lpg_cng_tp = $other_discount = $ncb_discount = $engine_protector =
                $lossbaggage = $rsa = $accident_shield = $conynBenef = $consExps = $voluntary_deductible = $tppd = 0;

            $final_payable_amount = $service_response['premiumDetailsOut_out']['typ:finalPremium'];
            if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) {
                $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];

                if (isset($covers['od'])) {
                    $basic_od = ($covers['od']);
                } else {
                    foreach ($covers as $key => $cover) {
                        if (($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                            $zero_dep_amount = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'KEYS AND LOCKS REPLACEMENT COVER') {
                            $key_replacement = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                            $engine_protector = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') {
                            $basic_od = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                            $tp_amount = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                            $pa_owner = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                            $non_electrical_accessories = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                            $electrical_accessories = round($cover['typ:od'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                            $pa_unnamed = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'LL To Person For Operation/Maintenance(IMT.28/39)') {
                            $ll_paid_driver = round($cover['typ:act'], 2);
                        } elseif ($cover['typ:paramDesc'] === 'CNG / LPG Unit (IMT.25)') {
                            $lpg_cng = round($cover['typ:od'], 2);
                            $lpg_cng_tp = round($cover['typ:act'], 2);
                        } elseif (in_array($cover['typ:paramDesc'], ['Commercial Discount', 'Commercial Discount3'])) {
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
                        } elseif ($cover['typ:paramDesc'] === 'Voluntary Excess (IMT.22 A)') {
                            $voluntary_deductible = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                            $tppd = (abs($cover['typ:act']));
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
                'imt23' => 0
            ];

            $addon_premium = array_sum($all_addons);
            $ExtraPremiumForRejectedRTO = is_array($service_response['pDetariffObj_inout']['typ:extCol22']) ? 0 : $service_response['pDetariffObj_inout']['typ:extCol22'];


            $tppd = $tp_amount + $tppd;
            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories
                + $lpg_cng + $ExtraPremiumForRejectedRTO;
            $totalTP = $tppd + $pa_unnamed + $ll_paid_driver + $lpg_cng_tp + $pa_owner - abs($tppd);
            $total_od_discount = abs($ncb_discount) + abs($other_discount) + abs($voluntary_deductible);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $service_response['premiumDetailsOut_out']['typ:totalOdPremium'] ?? ($final_od_premium + $addon_premium - $total_od_discount),
                // TP Tags
                "basic_tp_premium" => $tp_amount,
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
                "anti_theft" => 0,
                "voluntary_excess" => $voluntary_deductible,
                "tppd_discount" => $tppd,
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
            $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];

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

    public static function saveJsonPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = $logs->response;

            $premium_response = json_decode($response, true);
            $basic_od = 0;
            $basic_tp = 0;
            $ncb_discount = 0;
            $cpa = 0;
            $pa_unnamed_passenger = 0;
            $ll_paid_driver = 0;
            $other_discount = 0;
            $non_electrical_accessories = 0;
            $electrical_accessories = 0;
            $lpg_cng_kit_od = 0;
            $lpg_cng_kit_tp = 0;
            $tppd_discount = 0;
            $imt_23 = 0;
            $geoOd = $geoTp = 0;
            $loadingAmount = 0;

            foreach ($premium_response['premiumsummerylist'] as $premium) {
                switch ($premium['paramref']) {
                    case 'OD': // Basic OD
                        $basic_od = $premium['od'];
                        break;

                    case 'ACT': // Basic TP
                        $basic_tp = $premium['act'];
                        break;

                    case 'PA_DFT': // CPA
                        $cpa = $premium['act'];
                        break;

                    case 'NELECACC': // Non-electrical Accessories
                        $non_electrical_accessories = $premium['od'];
                        break;

                    case 'ELECACC': // Electrical Accesssories
                        $electrical_accessories = $premium['od'];
                        break;

                    case 'PA': // PA for Unnamed Passenger
                        $pa_unnamed_passenger = $premium['act'];
                        break;

                    case 'CNG': // External LPG/CNG Kit
                        $lpg_cng_kit_od = $premium['od'];
                        $lpg_cng_kit_tp = $premium['act'];
                        break;
                    case 'GEOG':
                        $geoOd = $premium['od'];
                        $geoTp = $premium['act'];

                    case 'LLO': // LL Paid Driver
                        $ll_paid_driver = abs($premium['act']);
                        break;

                    case 'IMT23': // IMT-23
                        $imt_23 = abs($premium['od']);
                        break;

                    case 'TPPD_RES': // TPPD Discount
                        $tppd_discount = abs($premium['act']);
                        break;

                    case 'COMMDISC': // Other Discount
                        $other_discount += abs($premium['od']);
                        break;

                    case 'ADD_DISC': // Other Discount
                        $loadingAmount = abs($premium['od']);
                        break;

                    default:
                        break;
                }
            }

            $ncb_discount = $premium_response['premiumdetails']['ncbamt'] ?? $ncb_discount;
            if ($ncb_discount == 'null') {
                $ncb_discount = 0;
            }

            $all_addons = [
                'zero_depreciation' => 0,
                'road_side_assistance' => 0,
                'engine_protector' => 0,
                'ncb_protection' => 0,
                'key_replace' => 0,
                'consumables' => 0,
                'tyre_secure' => 0,
                'return_to_invoice' => 0,
                'lopb' => 0,
                'Accident_shield' => 0,
                'Conveyance_Benefit' => 0,
                'imt23' => $imt_23
            ];

            $addon_premium = array_sum($all_addons);

            $basic_tp = $basic_tp + $tppd_discount; //tppd is deducted from basic tp
            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories
                + $lpg_cng_kit_od + $geoOd + $loadingAmount;
            $totalTP = $basic_tp + $pa_unnamed_passenger + $ll_paid_driver + $lpg_cng_kit_tp + $cpa - abs($tppd_discount) + $geoTp;
            $total_od_discount = abs($ncb_discount) + abs($other_discount);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => $loadingAmount,
                "final_od_premium" => ($final_od_premium + $addon_premium - $total_od_discount),
                // TP Tags
                "basic_tp_premium" => $basic_tp,
                "final_tp_premium" => $totalTP,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng_kit_od,
                "bifuel_tp_premium" => $lpg_cng_kit_tp,
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
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $pa_unnamed_passenger,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => $geoOd,
                "geo_extension_tppremium" => $geoTp,
                // Discounts
                "anti_theft" => 0,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $premium_response['premiumdetails']['netpremium'],
                "service_tax_amount" => $premium_response['premiumdetails']['servicetax'],
                "final_payable_amount" => $premium_response['premiumdetails']['finalpremium'],
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
