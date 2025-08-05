<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

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
                'Premium Calculation',
                getGenericMethodName('Premium Calculation', 'proposal'),
                'Proposal Creation',
                getGenericMethodName('Proposal Creation', 'proposal')
            ];
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal'),
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal'),
                    'Proposal Creation',
                    getGenericMethodName('Proposal Creation', 'proposal')
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

            $response = $response->MotorPolicy;

            $od = 0;
            $tp_liability = 0;
            $ll_paid_driver = 0;
            $ll_paid_cleaner = 0;
            $electrical_accessories = 0;
            $non_electrical_accessories = 0;
            $external_lpg_cng = 0;
            $external_lpg_cng_tp = 0;
            $pa_to_paid_driver = 0;
            $cpa_premium = 0;
            $ncb_discount = 0;
            $automobile_association = 0;
            $anti_theft = 0;
            $tppd_discount_amt = 0;
            $ic_vehicle_discount = 0;
            $voluntary_excess = 0;
            $other_discount = 0;
            $imt_23 = 0;
            $nil_depreciation = 0;
            $GeogExtension_od = 0;
            $GeogExtension_tp = 0;
            $total_od_addon = 0;
            $pa_unnamed = 0;
            if (is_array($response->lstPricingResponse)) {
                foreach ($response->lstPricingResponse as $single) {
                    if (isset($single->CoverageName) && $single->CoverageName == 'PA to Owner Driver') {
                        $cpa_premium = $single->Premium;
                    } elseif (isset($single->CoverageName) && $single->CoverageName == 'NCB') {
                        $ncb_discount = round(abs($single->Premium), 2);
                    } elseif ($single->CoverageName == 'Automobile Association Membership') {
                        $automobile_association = round(abs($single->Premium), 2);
                    } elseif ($single->CoverageName == 'Anti-Theft Device') {
                        $anti_theft = round(abs($single->Premium), 2);
                    } elseif ($single->CoverageName == 'TPPD') {
                        $tppd_discount_amt = round(abs($single->Premium), 2);
                    }
                    elseif ($single->CoverageName == 'Basic OD')
                    {
                        $od = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Basic Liability')
                    {
                        $tp_liability = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Liability to Paid Driver')
                    {
                        $ll_paid_driver = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Liability to Cleaner')
                    {
                        $ll_paid_cleaner = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Electrical Accessories')
                    {
                        $electrical_accessories = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Non Electrical Accessories')
                    {
                        $non_electrical_accessories = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Bifuel Kit')
                    {
                        $external_lpg_cng = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Bifuel Kit TP')
                    {
                        $external_lpg_cng_tp = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'PA to Paid Driver')
                    {
                        $pa_to_paid_driver = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'IMT 23(Lamp/ tyre tube/ Headlight etc )')
                    {
                        $imt_23 = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Nil Depreciation')
                    {
                        $nil_depreciation = round($single->Premium, 2);
                    } elseif (in_array($single->CoverageName, ['Geographical Extension', 'Geo Extension']) && $single->CoverID == '5') {
                        $GeogExtension_od = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Geographical Extension' && ($single->CoverID =='6' || $single->CoverID == '403'))
                    {
                        $GeogExtension_tp = round($single->Premium, 2);
                    }
                    elseif ($single->CoverageName == 'Total OD and Addon')
                    {
                        $total_od_addon = abs( (int) $single->Premium);
                    } elseif ($single->CoverageName == 'PA to Unnamed Passenger') {
                        $pa_unnamed = abs( (int) $single->Premium);
                    }
                }
            } else {
                $tp_liability = $response->lstPricingResponse->Premium;
            }

            $NetPremium = $response->NetPremium;
            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount;
            $total_od_amount = $od + $GeogExtension_od + $external_lpg_cng + $electrical_accessories + $non_electrical_accessories - $final_total_discount;
            $total_tp_amount = $tp_liability + $ll_paid_driver + $ll_paid_cleaner + $external_lpg_cng_tp + $pa_to_paid_driver + $cpa_premium - $tppd_discount_amt + $GeogExtension_tp;
            $total_addon_amount =   $imt_23 + $nil_depreciation;
            $final_payable_amount = $response->FinalPremium;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od,
                "loading_amount" => 0,
                "final_od_premium" => $total_od_amount + $total_addon_amount,
                // TP Tags
                "basic_tp_premium" => $tp_liability,
                "final_tp_premium" => $total_tp_amount,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $external_lpg_cng,
                "bifuel_tp_premium" => $external_lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $cpa_premium,
                "zero_depreciation" => $nil_depreciation,
                "road_side_assistance" => 0, //not applicable
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
                "pa_additional_driver" => $pa_to_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => $ll_paid_cleaner,
                "geo_extension_odpremium" => $GeogExtension_od,
                "geo_extension_tppremium" => $GeogExtension_tp,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount_amt,
                "other_discount" => $automobile_association,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $NetPremium,
                "service_tax_amount" => $final_payable_amount - $NetPremium,
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
