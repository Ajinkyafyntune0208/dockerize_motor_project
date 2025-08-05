<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class LibertyVideoconPremiumDetailController extends Controller
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
                    'Proposal Submission',
                    getGenericMethodName('Proposal Submission', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'liberty_videocon'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (empty($response['ErrorText'] ?? null) && isset($response['TotalPremium'])) {
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

            $llpaiddriver_premium = round($response['LegalliabilityToPaidDriverValue'], 2);
            $cover_pa_owner_driver_premium = round($response['PAToOwnerDrivervalue'], 2);
            $cover_pa_paid_driver_premium = round($response['PatoPaidDrivervalue'], 2);
            $cover_pa_unnamed_passenger_premium = round($response['PAToUnnmaedPassengerValue'], 2);
            $voluntary_excess = round($response['VoluntaryExcessValue'], 2);
            $anti_theft = round($response['AntiTheftDiscountValue'], 2);
            $ic_vehicle_discount = round($response['Loading'], 2) + round(($response['Discount'] ?? 0), 2);
            $ncb_discount = round($response['DisplayNCBDiscountvalue'], 2);
            $od = round($response['BasicODPremium'], 2);
            $tppd = round($response['BasicTPPremium'], 2);
            $cng_lpg = round($response['FuelKitValueODpremium'], 2);
            $cng_lpg_tp = round($response['FuelKitValueTPpremium'], 2);
            $zero_depreciation = round($response['NilDepValue'], 2);
            $road_side_assistance = round($response['RoadAssistCoverValue'], 2);
            $engine_protection = round($response['EngineCoverValue'], 2);
            $return_to_invoice = round($response['GAPCoverValue'], 2);
            $consumables = round($response['ConsumableCoverValue'], 2);
            $key_protect = round($response['KeyLossCoverValue'], 2);
            $passenger_assist_cover = round($response['PassengerAssistCoverValue'], 2);
            $electrical_accessories_amt = round($response['ElectricalAccessoriesValue'], 2);
            $non_electrical_accessories_amt = round($response['NonElectricalAccessoriesValue'], 2);
            $ll_paid_employee = round(($response['LegalliabilityToEmployeeValue'] ?? 0), 2);

            $total_od_premium = round(($response['TotalODPremiumValue'] ?? 0), 2);
            $total_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $ll_paid_employee + $cover_pa_owner_driver_premium;
            $final_net_premium = round($response['NetPremium'], 2);
            $final_gst_amount = round($response['GST'], 2);
            $final_payable_amount  = $response['TotalPremium'];


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od,
                "loading_amount" => 0,
                "final_od_premium" => $total_od_premium,
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $total_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories_amt,
                "non_electric_accessories_value" => $non_electrical_accessories_amt,
                "bifuel_od_premium" => $cng_lpg,
                "bifuel_tp_premium" => $cng_lpg_tp,
                // Addons
                "compulsory_pa_own_driver" => $cover_pa_owner_driver_premium,
                "zero_depreciation" => $zero_depreciation,
                "road_side_assistance" => $road_side_assistance,
                "imt_23" => 0,
                "consumable" => $consumables,
                "key_replacement" => $key_protect,
                "engine_protector" => $engine_protection,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => $return_to_invoice,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => $passenger_assist_cover,
                // Covers
                "pa_additional_driver" => $cover_pa_paid_driver_premium,
                "unnamed_passenger_pa_cover" => $cover_pa_unnamed_passenger_premium,
                "ll_paid_driver" => $llpaiddriver_premium,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "ll_paid_employee" => $ll_paid_employee,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => 0,
                "other_discount" => $ic_vehicle_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_gst_amount,
                "final_payable_amount" => $final_payable_amount,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? $value : 0;
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
