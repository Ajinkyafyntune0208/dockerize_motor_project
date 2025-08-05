<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class OrientalPremiumDetailController extends Controller
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
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'oriental'
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
                } catch (\Throwable $th) {
                    $response = null;
                }
                if (!empty($response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult']['ANNUAL_PREMIUM'])) {
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

            $response = XmlToArray::convert($response->response);

            $quote_res_array = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];

            $final_tp_premium = $final_od_premium = $final_net_premium = $final_payable_amount = $final_total_discount = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $res_array = [
                'ANNUAL_PREMIUM' => $quote_res_array['ANNUAL_PREMIUM'],
                'NCB_AMOUNT' => $quote_res_array['NCB_AMOUNT'],
                'SERVICE_TAX' => $quote_res_array['SERVICE_TAX'],
                'ZERO_DEP' => '0',
                'IMT_23' => 0,
                'ZERO_DEP_DISC' => '0',
                'PA_OWNER' => '0',
                'ELEC' => '0',
                'LL_PAID_DRIVER' => '0',
                'CNG' => '0',
                'CNG_TP' => '0',
                'VOL_ACC_DIS' => '0',
                'RTI' => '0',
                'ENG_PRCT' => '0',
                'DISC' => '0',
                'FIB_TANK' => '0',
                'NCB_DIS' => '0',
                'ANTI_THEFT' => '0',
                'AUTOMOBILE_ASSO' => '0',
                'UNNAMED_PASSENGER' => '0',
                'KEYREPLACEMENT' => '0',
                'CONSUMABLES' => '0',
                'LOSSPER_BELONG' => '0',
                'NO_CLAIM_BONUS' => '0',
                'OTHER_FUEL1' => '0',
                'OTHER_FUEL2' => '0',
                'IDV' => '0',
                'PA_PAID_DRIVER' => '0',
                'OD_PREMIUM' => '0',
                'TPPD' => '0',
                'TP_PREMIUM' => '0',
                'EMI_PROTECTION' => '0'
            ];

            $GeogExtension_od = 0;
            $GeogExtension_tp = 0;

            $flex_01 =  (!empty($quote_res_array['FLEX_02_OUT'])) ?  ($quote_res_array['FLEX_01_OUT'] . $quote_res_array['FLEX_02_OUT']) : $quote_res_array['FLEX_01_OUT'];
            $flex = explode(",", $flex_01);
            foreach ($flex as $val) {
                $cover = explode("~", $val);
                if ($cover[0] == "MOT-CVR-149") {

                    $res_array['ZERO_DEP'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-006") {
                    $GeogExtension_od = $cover[1];
                }
                if ($cover[0] == 'MOT-CVR-051')
                {
                    $GeogExtension_tp = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-010") {

                    $res_array['PA_OWNER'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-002") {

                    $res_array['ELEC'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-015") {

                    $res_array['LL_PAID_DRIVER'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-003") {

                    $res_array['CNG'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-008") {

                    $res_array['CNG_TP'] = $cover[1];
                }
                if ($cover[0] == "MOT-DIS-004") {

                    $res_array['VOL_ACC_DIS'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-070") {

                    $res_array['RTI'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-EPC") {

                    $res_array['ENG_PRCT'] = $cover[1];
                }
                if ($cover[0] == "MOT-DLR-IMT") {

                    $res_array['DISC'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-005") {

                    $res_array['FIB_TANK'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-001") {

                    $res_array['OD_PREMIUM'] = $cover[1];
                    $res_array['IDV'] = $cover[2];
                }
                if ($cover[0] == "MOT-CVR-007") {

                    $res_array['TP_PREMIUM'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-012") {

                    $res_array['UNNAMED_PASSENGER'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-154") {

                    $res_array['KEYREPLACEMENT'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-155") {

                    $res_array['CONSUMABLES'] = $cover[1];
                }
                if ($cover[0] == "MOT-DIS-013" && $cover[1] !== '0') {
                    $res_array['NCB_DIS'] = $cover[1];
                } elseif ($cover[0] == "MOT-DIS-310") {
                    $res_array['NCB_DIS'] = $cover[1];
                }
                if ($cover[0] == "MOT-DIS-002") {

                    $res_array['ANTI_THEFT'] = $cover[1];
                }
                if ($cover[0] == "MOT-DIS-005") {

                    $res_array['AUTOMOBILE_ASSO'] = $cover[1];
                }
                if ($cover[0] == "MOT-DIS-ACN") {

                    $res_array['ZERO_DEP_DISC'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-152") {

                    $res_array['LOSSPER_BELONG'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-053") {

                    $res_array['OTHER_FUEL1'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-058") {

                    $res_array['OTHER_FUEL2'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-013") {

                    $res_array['PA_PAID_DRIVER'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-019") {

                    $res_array['TPPD'] = $cover[1];
                }
                if ($cover[0] == 'MOT-LOD-007') {
                    $res_array['IMT_23'] = (int)$cover[1];
                }
                if ($cover[0] == 'MOT-CVR-157') {
                    $res_array['EMI_PROTECTION'] = (int)$cover[1];
                }
            }

            $final_payable_amount = round($res_array['ANNUAL_PREMIUM'], 2);

            $final_tp_premium = round(($res_array['TP_PREMIUM'] ?? 0), 2) + round(($res_array['LL_PAID_DRIVER'] ?? 0), 2) +
            round($res_array['PA_PAID_DRIVER'], 2) + round($res_array['UNNAMED_PASSENGER'], 2) + round($res_array['CNG_TP'], 2) +
            $res_array['OTHER_FUEL2'] + $res_array['PA_OWNER'] + $GeogExtension_tp;
            $final_tp_premium -= $res_array['TPPD'];

            $final_od_premium = round(($res_array['OD_PREMIUM'] ?? 0), 2) + $res_array['ELEC'] + $res_array['CNG'] + 
            $res_array['OTHER_FUEL1'] + $GeogExtension_od;

            $total_addon = round(($res_array['ZERO_DEP'] - $res_array['ZERO_DEP_DISC']), 2) + $res_array['CONSUMABLES'] + $res_array['KEYREPLACEMENT'] +
                $res_array['ENG_PRCT'] +  $res_array['RTI'] + $res_array['LOSSPER_BELONG'] + $res_array['IMT_23'] + $res_array['EMI_PROTECTION'];

            $total_od_discount = $res_array['NCB_AMOUNT'] + $res_array['DISC'] + $res_array['VOL_ACC_DIS'] + $res_array['ANTI_THEFT'];

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $res_array['OD_PREMIUM'],
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $total_addon - $total_od_discount,
                // TP Tags
                "basic_tp_premium" => $res_array['TP_PREMIUM'] ?? 0,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $res_array['ELEC'],
                "non_electric_accessories_value" => 0,
                "bifuel_od_premium" => $res_array['CNG'] + $res_array['OTHER_FUEL1'],
                "bifuel_tp_premium" => $res_array['CNG_TP'] + $res_array['OTHER_FUEL2'],
                // Addons
                "compulsory_pa_own_driver" => $res_array['PA_OWNER'],
                "zero_depreciation" => round(($res_array['ZERO_DEP'] - $res_array['ZERO_DEP_DISC']), 2),
                "road_side_assistance" => 0,
                "imt_23" => $res_array['IMT_23'],
                "consumable" => $res_array['CONSUMABLES'],
                "key_replacement" => $res_array['KEYREPLACEMENT'],
                "engine_protector" => $res_array['ENG_PRCT'],
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => $res_array['RTI'],
                "loss_of_personal_belongings" => $res_array['LOSSPER_BELONG'],
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $res_array['PA_PAID_DRIVER'],
                "unnamed_passenger_pa_cover" => $res_array['UNNAMED_PASSENGER'],
                "ll_paid_driver" => $res_array['LL_PAID_DRIVER'],
                "geo_extension_odpremium" => $GeogExtension_od,
                "geo_extension_tppremium" => $GeogExtension_tp,
                // Discounts
                "anti_theft" => $res_array['ANTI_THEFT'],
                "voluntary_excess" => $res_array['VOL_ACC_DIS'],
                "tppd_discount" => $res_array['TPPD'],
                "other_discount" => $res_array['DISC'],
                "ncb_discount_premium" => $res_array['NCB_AMOUNT'],
                // Final tags
                "net_premium" => $final_payable_amount - $res_array['SERVICE_TAX'],
                "service_tax_amount" => $res_array['SERVICE_TAX'],
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
