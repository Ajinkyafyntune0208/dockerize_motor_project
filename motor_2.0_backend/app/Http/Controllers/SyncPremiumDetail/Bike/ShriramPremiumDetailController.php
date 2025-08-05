<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\SelectedAddons;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class ShriramPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';
            $is_json = config('constants.motor.shriram.SHRIRAM_BIKE_JSON_REQUEST_TYPE') == 'JSON';
            if ($isRenewal) {
                return [
                    'status' => false,
                    'message' => 'Integration not yet done.',
                ];
                $methodList = [
                    'Proposal Submit',
                    'Premium Calculation',
                    getGenericMethodName('Proposal Submit', 'proposal'),
                    getGenericMethodName('Premium Calculation', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Proposal Submit',
                    'Premium Calculation',
                    getGenericMethodName('Proposal Submit', 'proposal'),
                    getGenericMethodName('Premium Calculation', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id', 'request')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'shriram'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                try {
                    $response = $is_json || $isRenewal ? json_decode($response, true) : XmlToArray::convert($response);
                } catch (\Throwable $th) {
                    $response = null;
                }

                if (!$is_json && !$isRenewal) {
                    $response = $response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'] ?? $response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'] ?? null;
                }

                if ((isset($response['ERROR_CODE']) && $response['ERROR_CODE'] == 0) ||
                    ($response['MessageResult']['Result'] ?? '') == 'Success' ||
                    ($response['ERROR_DESC'] ?? '') == 'Successful Completion'
                ) {
                    $webserviceId = $log['id'];
                    break;
                }
            }

            if (!empty($webserviceId)) {
                if ($isRenewal) {
                    return self::saveRenewalPremiumDetails($webserviceId);
                } elseif (!$is_json) {
                    return self::saveXmlPremiumDetails($webserviceId);
                } else {
                    return self::saveJsonPremiumDetails($webserviceId);
                }
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

    public static function saveXmlPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = XmlToArray::convert($logs->response);

            $requestData = getQuotation($enquiryId);
            $NonElectricalaccessSI = 0;

            $selected_addons = SelectedAddons::select('accessories')->where('user_product_journey_id', $enquiryId)->first();
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            foreach ($accessories as $value) {
                if (in_array('Non-Electrical Accessories', $value)) {
                    $NonElectricalaccessSI = $value['sumInsured'];
                }
            }


            $response = $response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'] ?? $response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'] ?? null;
            $coverDTList = $response['CoverDtlList']['CoverDtl'];
            $igst           = $anti_theft = $other_discount = $sgst = $cgst = $detariffic_discount =
                $rsapremium     = $pa_paid_driver = $zero_dep_amount =
                $ncb_discount   = $tppd = $final_tp_premium = $loading_amount =
                $final_od_premium = $final_net_premium =
                $final_payable_amount = $basic_od = $electrical_accessories =
                $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories =
                $pa_owner       = $voluntary_excess = $pa_unnamed = $key_rplc = $tppd_discount =
                $ll_paid_driver = $personal_belonging = $engine_protection = $consumables_cover = $return_to_invoice = $basic_tp_premium = 0;

            foreach ($coverDTList as $key => $value) {
                if (in_array($value['CoverDesc'], array('BASIC OD COVER', 'BASIC OD COVER - 1 YEAR'))) {
                    $basic_od = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['VOLUNTARY EXCESS DISCOUNT-IMT-22A', 'IMT22A-VOLUNTARY EXCESS DISCOUNT - 1 YEAR', 'IMT22A-VOLUNTARY EXCESS DISCOUNT'])) {
                    $voluntary_excess = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('OD TOTAL', 'OD TOTAL - 1 YEAR'))) {
                    $final_od_premium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('BASIC TP COVER', 'BASIC TP COVER - 1 YEAR', 'BASIC TP COVER - 2 YEAR', 'BASIC TP COVER - 3 YEAR', 'BASIC TP COVER - 4 YEAR', 'BASIC TP COVER - 5 YEAR'))) {
                    $basic_tp_premium += $value['Premium'];
                }
                if ($value['CoverDesc'] == 'TOTAL PREMIUM') {
                    $final_net_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'IGST') {
                    $igst = $igst + $value['Premium'];
                }

                if ($value['CoverDesc'] == 'SGST') {
                    $sgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'CGST') {
                    $cgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                    $ncb_discount = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD - 1 YEAR') {
                    $detariffic_discount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['UW LOADING-MIN PREMIUM', 'UW LOADING-BASIC OD - 1 YEAR'])) {
                    $loading_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['Nil Depreciation', 'Nil Depreciation Cover', 'Nil Depreciation - 1 YEAR', 'LOADING TW (Nil Depreciation Cover) - 1 YEAR'])) {
                    $zero_dep_amount += $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['INVOICE RETURN', 'INVOICE RETURN - 1 YEAR'])) {
                    $return_to_invoice = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Consumables Cover', 'Consumables Loading'])) {
                    $consumables_cover += $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Engine Protector Cover', 'Engine Protector Loading'])) {
                    $engine_protection += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES', 'GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES - 1 YEAR'])) {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['CNG/LPG-KIT-COVER-GR42', 'INBUILT CNG/LPG KIT'])) {
                    $lpg_cng = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER'))) {
                    $lpg_cng_tp = $value['Premium'];
                }

                /*if ($value['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                    $non_electrical_accessories = $value['Premium'];
                }*/

                if (in_array($value['CoverDesc'], ['PA-UN-NAMED-GR36B2', 'PA-UN-NAMED-GR36B2 - 1 YEAR', 'PA-UN-NAMED-GR36B2 - 2 YEAR', 'PA-UN-NAMED-GR36B2 - 3 YEAR', 'PA-UN-NAMED-GR36B2 - 4 YEAR', 'PA-UN-NAMED-GR36B2 - 5 YEAR'])) {
                    $pa_unnamed += $value['Premium'];
                }

                if ($value['CoverDesc'] == 'PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3') {
                    $pa_paid_driver = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER', 'GR36A-PA FOR OWNER DRIVER - 1 YEAR'])) {
                    $pa_owner = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28', 'LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28 - 1 YEAR', 'LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28 - 2 YEAR', 'LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28 - 3 YEAR', 'LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28 - 4 YEAR', 'LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28 - 5 YEAR'])) {
                    $ll_paid_driver += $value['Premium'];
                }

                if ($value['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                    $other_discount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'ANTI-THEFT DISCOUNT-GR-30'])) {
                    $anti_theft = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['ROAD SIDE ASSISTANCE', 'ROAD SIDE ASSISTANCE - 1 YEAR'])) {
                    $rsapremium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR39A-TPPD COVER', 'GR39A-TPPD COVER - 1 YEAR'])) {
                    //$tppd_discount = $value['Premium'];
                    $tppd_discount = ($requestData->business_type == 'newbusiness') ? ($value['Premium'] * 5) : $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['TP TOTAL', 'TP TOTAL - 1 YEAR', 'TP TOTAL - 2 YEAR', 'TP TOTAL - 3 YEAR', 'TP TOTAL - 4 YEAR', 'TP TOTAL - 5 YEAR'])) {
                    $final_tp_premium += $value['Premium'];
                }
            }

            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (string) round((($NonElectricalaccessSI * 3.283) / 100), 2);
                $basic_od = ($basic_od - $non_electrical_accessories);
            }

            if (empty($igst)) {
                $igst = $cgst + $sgst;
            }

            $final_tp_premium = $basic_tp_premium + $lpg_cng_tp + $pa_owner + $pa_paid_driver + $pa_unnamed + $ll_paid_driver - $tppd_discount;

            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
            $total_addon_amount = $zero_dep_amount + $return_to_invoice + $personal_belonging + $consumables_cover + $engine_protection + $key_rplc + $rsapremium;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => $loading_amount - $detariffic_discount,
                "final_od_premium" => $final_od_premium + $total_addon_amount,
                // TP Tags
                "basic_tp_premium" => $basic_tp_premium,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => $rsapremium,
                "imt_23" => 0,
                "consumable" => $consumables_cover,
                "key_replacement" => $key_rplc,
                "engine_protector" => $engine_protection,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => $return_to_invoice,
                "loss_of_personal_belongings" => $personal_belonging,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $igst,
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

    public static function saveJsonPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = json_decode($logs->response, true);
            $requestData = getQuotation($enquiryId);

            $igst           = $anti_theft = $other_discount = $cgst = $sgst = 0;
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 0;
            $ncb_discount   = $tppd = $final_tp_premium =  0;
            $final_od_premium = $final_net_premium = 0;
            $final_payable_amount = $basic_od = $electrical_accessories = 0;
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = $tppd_discount = 0;
            $pa_owner       = $voluntary_excess = $pa_unnamed =  0;
            $ll_paid_driver = $engine_protection = $consumables_cover = $return_to_invoice = $loading_amount = 0;
            $geog_Extension_TP_Premium = $geog_Extension_OD_Premium = $geo_ext_one = $geo_ext_two = 0;
            $Minimum_OD_Loading = $NilDepreciationLoading = $loadingAmount = 0;

            $quote_response = $response['GenerateProposalResult'];
            foreach ($quote_response['CoverDtlList'] as $key => $value) {

                $value['CoverDesc'] = trim($value['CoverDesc']);

                if (in_array($value['CoverDesc'], array(
                    'Basic OD Premium',
                    'Basic Premium - 1 Year',
                    'Basic OD Premium - 1 Year',
                    'Basic Premium - OD',
                    'Daily Expenses Reimbursement - OD',
                    'Basic Premium - 1 Year - OD'
                ))) {
                    $basic_od = $value['Premium'];
                    $od_key = $key;
                }
                if (in_array($value['CoverDesc'], [
                    'Voluntary excess/deductibles',
                    'Voluntary excess/deductibles - 1 Year',
                    'Voluntary excess/deductibles - 1 Year - OD',
                    'Voluntary excess/deductibles - OD'
                ])) {
                    $voluntary_excess = abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], array('OD Total'))) {
                    $final_od_premium = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'Basic Premium - TP',
                    'Basic TP Premium',
                    'Basic TP Premium - 1 Year',
                    'Basic Premium - 1 Year',
                    'Basic TP Premium - 2 Year',
                    'Basic TP Premium - 3 Year',
                    'Basic TP Premium - 4 Year',
                    'Basic TP Premium - 5 Year'
                ])) {
                    $tppd += $value['Premium'];
                }
                if ($value['CoverDesc'] == 'Total Premium') {
                    $final_net_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'IGST(18.00%)') {
                    $igst +=$value['Premium'];
                }

                if ($value['CoverDesc'] == 'SGST/UTGST(0.00%)' && in_array($value['CoverDesc'], [
                    'SGST/UTGST(0.00%)',
                    'SGST/UTGST(9.00%)'
                ])) {
                    $sgst += $value['Premium'];
                }

                if ($value['CoverDesc'] == 'CGST(0.00%)') {
                    $cgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'NCB Discount',
                    'NCB Discount  - OD',
                    'NCB Discount - OD'
                ))) {
                    $ncb_discount = abs($value['Premium']);
                }

                if ($value['CoverDesc'] == 'UW LOADING-MIN PREMIUM') {
                    $loading_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'Minimum OD Loading',
                    'Minimum OD Loading - OD'
                ))) {
                    $Minimum_OD_Loading = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'Nil Depreciation Cover',
                    'Nil Depreciation Cover - 1 Year',
                    'Nil Depreciation Cover - OD',
                    'Nil Depreciation Cover - 1 Year - OD'
                ])) {
                    $zero_dep_amount = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array(
                    'Return to Invoice',
                    'Return to Invoice - 1 Year',
                    'Return to Invoice - 1 Year - OD'
                ))) {
                    $return_to_invoice = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'Consumable',
                    'Consumable - 1 Year',
                    'Consumable - OD',
                    'Consumable - 1 Year - OD'
                ])) {
                    $consumables_cover = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'Engine Protector',
                    'Engine Protector - 1 Year',
                    'Engine Protector - OD',
                    'Engine Protector - 1 Year - OD'
                ])) {
                    $engine_protection = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'GR41-Cover For Electrical and Electronic Accessories',
                    'GR41-Cover For Electrical and Electronic Accessories - OD',
                    'GR41-Cover For Electrical and Electronic Accessories - 1 Year - OD',
                    'GR41-Cover For Electrical and Electronic Accessories - 1 Year'
                ])) {
                    $electrical_accessories += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'CNG/LPG-KIT-COVER-GR42',
                    'INBUILT CNG/LPG KIT'
                ))) {
                    $lpg_cng = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'CNG/LPG KIT - TP  COVER-GR-42',
                    'IN-BUILT CNG/LPG KIT - TP  COVER'
                ))) {
                    $lpg_cng_tp = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'Cover For Non Electrical Accessories',
                    'Cover For Non Electrical Accessories - OD',
                    'Cover For Non Electrical Accessories - 1 Year - OD',
                    'Cover For Non Electrical Accessories - 1 Year'
                ))) {
                    $non_electrical_accessories += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'GR36B2-PA Cover For Passengers (Un-Named Persons)',
                    'GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year',
                    'GR36B2-PA Cover For Passengers (Un-Named Persons) - TP'
                ])) {
                    $pa_unnamed = $value['Premium'];
                }

                if ($value['CoverDesc'] == '  ') {
                    $pa_paid_driver = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'GR36A-PA FOR OWNER DRIVER',
                    'GR36A-PA FOR OWNER DRIVER - 1 Year',
                    'GR36A-PA FOR OWNER DRIVER - TP'
                ])) {
                    $pa_owner = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'Legal Liability Coverages For Paid Driver',
                    'Legal Liability Coverages For Paid Driver - 1 Year',
                    'Legal Liability Coverages For Paid Driver - TP'
                ])) {
                    $ll_paid_driver = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'De-Tariff Discount',
                    'De-Tariff Discount - 1 Year',
                    'De-Tariff Discount - OD',
                    'De-Tariff Discount - 1 Year - OD'
                ])) {
                    $other_discount = abs($value['Premium']);
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover'])) {
                    $anti_theft = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array(
                    'Road Side Assistance',
                    'Road Side Assistance - 1 Year',
                    'Road Side Assistance - OD',
                    'Road Side Assistance - 1 Year - OD'
                ))) {
                    $rsapremium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array(
                    'GR39A-Limit The Third Party Property Damage Cover',
                    'GR39A-Limit The Third Party Property Damage Cover - TP'
                ))) {
                    $tppd_discount = abs(($requestData->business_type == 'newbusiness') ? ($value['Premium'] * 5) : $value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['TP Total'])) {
                    $final_tp_premium = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'GR4-Geographical Extension',
                    'GR4-Geographical Extension - 1 Year'
                ])) {
                    if ($geo_ext_one > 0) {
                        $geo_ext_two = $value['Premium'];
                    } else {
                        $geo_ext_one = $value['Premium'];
                    }
                }

                if (in_array($value['CoverDesc'], [
                    'Nil Depreciation Loading',
                    'Nil Depreciation Loading - 1 Year',
                    'Nil Depreciation Loading - OD',
                    'Nil Depreciation Loading - 1 Year - OD'
                ])) {
                    $NilDepreciationLoading = $value['Premium'];
                }

                // Basic TP
                if (in_array($value['CoverDesc'], ['Basic Premium - 1 Year - TP'])) {
                    $tppd = (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 2 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 3 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 4 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 5 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }

                // CPA
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 1 Year - TP'])) {
                    $pa_owner = (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 2 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 3 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 4 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 5 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }

                //TPPD Discount
                if (in_array($value['CoverDesc'], array('GR39A-Limit The Third Party Property Damage Cover', 'GR39A-Limit The Third Party Property Damage Cover - TP', 'GR39A-Limit The Third Party Property Damage Cover - 1 Year - TP'))) {
                    $tppd_discount = (float)(($requestData->business_type == 'newbusiness') ? ($value['Premium'] * 5) : $value['Premium']);
                }

                //LL paid driver
                if (in_array($value['CoverDesc'], ['Legal Liability Coverages For Paid Driver', 'Legal Liability Coverages For Paid Driver - 1 Year', 'Legal Liability Coverages For Paid Driver - TP', 'Legal Liability Coverages For Paid Driver - 1 Year - TP'])) {
                    $ll_paid_driver = (float)(($requestData->business_type == 'newbusiness') ? ($value['Premium'] * 5) : $value['Premium']);
                }

                //PA Passenger
                if (in_array($value['CoverDesc'], ['GR36B2-PA Cover For Passengers (Un-Named Persons)', 'GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year', 'GR36B2-PA Cover For Passengers (Un-Named Persons) - TP', 'GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year - TP'])) {
                    $pa_unnamed = (float)(($requestData->business_type == 'newbusiness') ? ($value['Premium'] * 5) : $value['Premium']);
                }

                // GEO Extension
                if (in_array($value['CoverDesc'], ['GR4-Geographical Extension', 'GR4-Geographical Extension - 1 Year', 'GR4-Geographical Extension - 1 Year - OD', 'GR4-Geographical Extension - 1 Year - OD'])) {
                    $geo_ext_one = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR4-Geographical Extension - 1 Year - TP'])) {
                    $geo_ext_two = (float)(($requestData->business_type == 'newbusiness') ? ($value['Premium'] * 5) : $value['Premium']);
                }


                if (in_array($value['CoverDesc'], ['GR4-Geographical Extension - OD'])) {
                    $geog_Extension_OD_Premium = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR4-Geographical Extension - TP'])) {
                    $geog_Extension_TP_Premium = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['UW Loading-Automatic'])) {
                    $loading_amount = $value['Premium'];
                }
            }

            if ($geo_ext_one > $geo_ext_two) {
                $geog_Extension_TP_Premium = $geo_ext_two;
                $geog_Extension_OD_Premium = $geo_ext_one;
            } else {
                $geog_Extension_OD_Premium = ($geo_ext_one <= 0) ? $geog_Extension_OD_Premium : $geo_ext_one;
                $geog_Extension_TP_Premium = ($geo_ext_two <= 0) ? $geog_Extension_TP_Premium : $geo_ext_two;
            }

            if ($requestData->business_type == 'newbusiness') {
                $geog_Extension_OD_Premium = ($geo_ext_one <= 0) ? $geog_Extension_OD_Premium : $geo_ext_one;
                $geog_Extension_TP_Premium = ($geo_ext_two <= 0) ? $geog_Extension_TP_Premium : $geo_ext_two;
            }
            // $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount;
            if ($Minimum_OD_Loading > 0) {
                $basic_od = round(($basic_od + $Minimum_OD_Loading), 2);
            }
            $zero_dep_amount+= $NilDepreciationLoading;
            // $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $geog_Extension_OD_Premium - $other_discount;
            // $final_od_premium += $total_addon_amount;
            // $total_addon_amount = $zero_dep_amount + $return_to_invoice + $consumables_cover + $engine_protection + $rsapremium;

            $igst = $final_payable_amount - $final_net_premium;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => $loading_amount,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => $rsapremium,
                "imt_23" => 0,
                "consumable" => $consumables_cover,
                "key_replacement" => 0,
                "engine_protector" => $engine_protection,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => $return_to_invoice,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => $geog_Extension_OD_Premium,
                "geo_extension_tppremium" => $geog_Extension_TP_Premium,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $igst,
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

    public static function saveRenewalPremiumDetails($webserviceId)
    {

    }
}
