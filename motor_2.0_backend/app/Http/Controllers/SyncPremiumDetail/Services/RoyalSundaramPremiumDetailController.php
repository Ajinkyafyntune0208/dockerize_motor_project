<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\SelectedAddons;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class RoyalSundaramPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')
        ->where('user_product_journey_id', $enquiryId)
            ->first();
        $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

        if (!$isRenewal) {
            $methodList = [
                'Update Premium Calculation',
                getGenericMethodName('Update Premium Calculation', 'proposal')
            ];

            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'royal_sundaram'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);

                if (isset($response['PREMIUMDETAILS']['DATA']['PREMIUM'])) {
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
        }
        return [
            'status' => false,
            'message' => 'Integration not yet done.',
        ];
    }

    public static function savePremiumDetails($webserviceId)
    {
        try {
            $response = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $response->enquiry_id;
            $response = json_decode($response->response, true);
            $requestData = getQuotation($enquiryId);

            $TPPDCover = '';
            $additional = SelectedAddons::where('user_product_journey_id', $enquiryId)
                ->select('discounts')
                ->first();
            if (!empty($additional['discounts'])) {
                foreach ($additional['discounts'] as $data) {
                    if ($data['name'] == 'TPPD Cover') {
                        $TPPDCover = '6000';
                    }
                }
            }

            $llpaiddriver_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS'], 2);
            $ll_paid_employee = round(($response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_EMPLOYESES'] ?? 0), 2);
            $ll_paid_conductor = round(($response['PREMIUMDETAILS']['DATA']['LIABILITY']['LLDriverConductorCleaner'] ?? 0), 2);
            $cover_pa_owner_driver_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER'], 2);
            $cover_pa_paid_driver_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER'], 2);
            $cover_pa_unnamed_passenger_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS'], 2);
            $voluntary_excess = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE'], 2);
            $anti_theft = 0;
            $ic_vehicle_discount = 0;
            $ncb_discount = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS'] ?? 0), 2);
            $electrical_accessories_amt = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES'] ?? 0), 2);
            $non_electrical_accessories_amt = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES'] ?? 0), 2);
            $od = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES'], 2);
            $tppd = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD'], 2);
            $tppd_discount = 0;

            if (!empty($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW'])) {
                $od += round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW'], 2);
            }

            if (!empty($TPPDCover)) {
                $tppd_discount = 100 * ($requestData->business_type == 'newbusiness' ? 3 : 1);
                $tppd += $tppd_discount;
            }
            $cng_lpg = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT'], 2);
            $cng_lpg_tp = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG'], 2);

            $zero_depreciation = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'] ?? 0), 2);
            $wind_shield = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['WIND_SHIELD_GLASS'] ?? 0), 2);
            $rsa = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDE_ASSISTANCE_COVER'] ?? 0), 2);
            $engine_protection = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR'] ?? 0), 2);
            $ncb_protection = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NCB_PROTECTOR'] ?? 0), 2);
            $key_replace = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['KEY_REPLACEMENT'] ?? 0), 2);
            $tyre_secure = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYRE_COVER'] ?? 0), 2);
            $return_to_invoice = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VEHICLE_REPLACEMENT_COVER'] ?? 0), 2);
            $lopb = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['LOSS_OF_BAGGAGE'] ?? 0), 2);
            $consumable_cover = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['CONSUMABLE_COVER'] ?? 0), 2);
            $imt23 = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYREMUDGUARD'] ?? 0);

            $geogOd = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['OD_GEO_EXTENSION'] ?? 0), 2);
            $geogTp = round(($response['PREMIUMDETAILS']['DATA']['LIABILITY']['TP_GEO_EXTENSION'] ?? 0), 2);

            $final_od_premium =  $response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TOTAL_OD_PREMIUM'];
            $final_tp_premium =  $response['PREMIUMDETAILS']['DATA']['LIABILITY']['TOTAL_LIABILITY_PREMIUM'];
            $final_net_premium = round($response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM'], 2);
            $final_gst_amount = round(($response['PREMIUMDETAILS']['DATA']['PREMIUM'] - $response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM']), 2);
            $final_payable_amount = round($response['PREMIUMDETAILS']['DATA']['PREMIUM'], 2);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories_amt,
                "non_electric_accessories_value" => $non_electrical_accessories_amt,
                "bifuel_od_premium" => $cng_lpg,
                "bifuel_tp_premium" => $cng_lpg_tp,
                // Addons
                "compulsory_pa_own_driver" => $cover_pa_owner_driver_premium,
                "zero_depreciation" => $zero_depreciation,
                "road_side_assistance" => $rsa,
                "imt_23" => $imt23,
                "consumable" => $consumable_cover,
                "key_replacement" => $key_replace,
                "engine_protector" => $engine_protection,
                "ncb_protection" => $ncb_protection,
                "tyre_secure" => $tyre_secure,
                "return_to_invoice" => $return_to_invoice,
                "loss_of_personal_belongings" => $lopb,
                "wind_shield" => $wind_shield,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $cover_pa_paid_driver_premium,
                "unnamed_passenger_pa_cover" => $cover_pa_unnamed_passenger_premium,
                "ll_paid_driver" => $llpaiddriver_premium,
                "ll_paid_conductor" => $ll_paid_conductor,
                "ll_paid_cleaner" => 0,
                "ll_paid_employee" => $ll_paid_employee,
                "geo_extension_odpremium" => $geogOd,
                "geo_extension_tppremium" => $geogTp,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_discount,
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
            return [
                'status' => false,
                'message' => $th->getMessage(),
            ];
        }
    }
}
