<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

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
            $is_json = config('constants.motor.shriram.SHRIRAM_CAR_JSON_REQUEST_TYPE') == 'JSON';
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
                    $response = $response['soap:Body']['GenerateLTPvtCarProposalResponse']['GenerateLTPvtCarProposalResult'] ?? $response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'] ?? null;
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


            $response = $response['soap:Body']['GenerateLTPvtCarProposalResponse']['GenerateLTPvtCarProposalResult'] ?? $response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'] ?? null;
            $coverDTList = $response['CoverDtlList']['CoverDtl'];
            $igst           = $anti_theft = $other_discount = $sgst = $cgst = 
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 
            $ncb_discount   = $tppd = $final_tp_premium = 
            $final_od_premium = $final_net_premium =
            $final_payable_amount = $basic_od = $electrical_accessories = 
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = 
            $pa_owner       = $voluntary_excess = $pa_unnamed = $key_rplc = $tppd_discount =
            $ll_paid_driver = $personal_belonging = $engine_protection = $consumables_cover = $return_to_invoice = $basic_tp_premium = 0;

            foreach($coverDTList as $key => $value){
                if ( in_array($value['CoverDesc'], array('BASIC OD COVER', 'BASIC OD COVER - 1 YEAR')) ) {
                    $basic_od = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['VOLUNTARY EXCESS DISCOUNT-IMT-22A','VOLUNTARY EXCESS DISCOUNT-IMT-22A - 1 YEAR'])) {
                    $voluntary_excess = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'OD TOTAL') {
                    $final_od_premium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('BASIC TP COVER', 'BASIC TP COVER - 1 YEAR','BASIC TP COVER - 2 YEAR','BASIC TP COVER - 3 YEAR')) ) {
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

                if ( in_array($value['CoverDesc'], array('Nil Depreciation', 'Nil Depreciation Cover','Nil Depreciation - 1 YEAR')) ) {
                    $zero_dep_amount = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES', 'GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES - 1 YEAR'))) {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG-KIT-COVER-GR42', 'INBUILT CNG/LPG KIT','GR42--CNG-KIT-COVER - 1 YEAR', 'INBUILT CNG/LPG KIT - 1 YEAR'))) {
                    $lpg_cng = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER','CNG/LPG KIT - TP  COVER-GR-42 - 1 YEAR'))) {
                    $lpg_cng_tp = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }

                /*if ($value['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                    $non_electrical_accessories = $value['Premium'];
                }*/
                if (in_array($value['CoverDesc'], ['PA-UN-NAMED-GR36B2', 'PA-UN-NAMED-GR36B2 - 1 YEAR'])) {
                    $pa_unnamed = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3','PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3 - 1 YEAR'])) {
                    $pa_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER', 'GR36A-PA FOR OWNER DRIVER - 1 YEAR'])) {
                    $pa_owner = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28','LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28 - 1 YEAR'])) {
                    $ll_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], ['TP TOTAL', 'TP TOTAL - 1 YEAR', 'TP TOTAL - 2 YEAR', 'TP TOTAL - 3 YEAR']) ) {
                    $final_tp_premium += $value['Premium'];
                    //$final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 3)+ $pa_owner +$pa_unnamed +$pa_paid_driver+$ll_paid_driver+$lpg_cng_tp): $final_tp_premium;
                }

                if (in_array($value['CoverDesc'], ['DETARIFF DISCOUNT ON BASIC OD', 'DETARIFF DISCOUNT ON BASIC OD - 1 YEAR'])) {
                    $other_discount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'ANTI-THEFT DISCOUNT-GR-30'])) {
                    $anti_theft = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('ROAD SIDE ASSISTANCE', 'ROAD SIDE ASSISTANCE - 1 YEAR')) ) {
                    $rsapremium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('KEY REPLACEMENT', 'KEY REPLACEMENT - 1 YEAR')) ) {
                    $key_rplc = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['Engine Protector Cover', 'Engine Protector Loading'])) {
                    $engine_protection += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['Consumables Cover', 'Consumables Loading'])) {
                    $consumables_cover += $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('LOSS OF PERSONAL BELONGINGSE', 'LOSS OF PERSONAL BELONGINGS - 1 YEAR', 'LOSS OF PERSONAL BELONGINGS')) ) {
                    $personal_belonging = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('INVOICE RETURN', 'INVOICE RETURN - 1 YEAR')) ) {
                    $return_to_invoice = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR39A-TPPD COVER', 'GR39A-TPPD COVER - 1 YEAR', 'GR39A-TPPD COVER - 2 YEAR', 'GR39A-TPPD COVER - 3 YEAR'])) {
                    $tppd_discount += $value['Premium'];
                }
            }

            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (string) round((($NonElectricalaccessSI * 3.283 ) / 100), 2);
                $basic_od = ($basic_od - $non_electrical_accessories);
            }

            $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount;

            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
            $total_addon_amount = $zero_dep_amount + $return_to_invoice + $personal_belonging + $consumables_cover + $engine_protection + $key_rplc + $rsapremium;

            if (empty($igst)) {
                $igst = $cgst + $sgst;
            }

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
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

            $igst           = $anti_theft = $other_discount = $sgst = $cgst = 
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 
            $ncb_discount   = $tppd = $final_tp_premium = 
            $final_od_premium = $final_net_premium =
            $final_payable_amount = $basic_od = $electrical_accessories = 
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = 
            $pa_owner       = $voluntary_excess = $pa_unnamed = $key_rplc = $tppd_discount =
            $ll_paid_driver = $personal_belonging = $engine_protection = $consumables_cover = $return_to_invoice = $basic_tp_premium = $imt29amt = 
            $geog_Extension_TP_Premium = $geog_Extension_OD_Premium = $geo_ext_one = $geo_ext_two = 0;
            $zero_dep_loading = $engine_protection_loading = $consumable_loading=  0;
            $final_od_premium = $loadingAmount = 0;

            $quote_response = $response['GenerateProposalResult'];
            foreach ($quote_response['CoverDtlList'] as $key => $value) {

                $value['CoverDesc'] = trim($value['CoverDesc']);

                if (in_array($value['CoverDesc'], [
                    'Minimum OD Loading'
                ])) {
                    $loadingAmount += abs($value['Premium']);
                }

                if (in_array($value['CoverDesc'], [
                    'Basic OD Premium',
                    'Basic OD Premium - 1 Year',
                    'Basic Premium - 1 Year',
                    'Basic Premium - OD',
                    'Daily Expenses Reimbursement - OD',
                    'Basic Premium - 1 Year - OD'
                ])) {
                    $basic_od = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'Voluntary excess/deductibles',
                    'Voluntary excess/deductibles - 1 Year',
                    'Voluntary excess/deductibles - 1 Year - OD',
                    'Voluntary excess/deductibles - OD'
                ])) {
                    $voluntary_excess = abs($value['Premium']);
                }
                if ($value['CoverDesc'] == 'OD Total') {
                    $final_od_premium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array(
                    'Basic TP Premium',
                    'Basic TP Premium - 1 Year',
                    'Basic TP Premium - 2 Year',
                    'Basic TP Premium - 3 Year',
                    'Basic Premium - 1 Year - TP',
                    'Basic Premium - TP',
                    'Basic Premium - 2 Year - TP',
                    'Basic Premium - 3 Year - TP'
                ))) {
                    $basic_tp_premium += $value['Premium'];
                }
                
                //End basic tp for NB 
                
                if ($value['CoverDesc'] == 'Total Premium') {
                    $final_net_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'IGST(18.00%)') {
                    $igst = $igst + $value['Premium'];
                }

                if ($value['CoverDesc'] == 'SGST/UTGST(0.00%)') {
                    $sgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'CGST(0.00%)') {
                    $cgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'NCB Discount',
                    'NCB Discount ',
                    'NCB Discount  - OD',
                    'NCB Discount - OD'
                ])) {
                    $ncb_discount = abs($value['Premium']);
                }

                if (in_array($value['CoverDesc'], [
                    'Depreciation Deduction Waiver (Nil Depreciation) - 1 Year',
                    'Depreciation Deduction Waiver (Nil Depreciation)',
                    'Depreciation Deduction Waiver (Nil Depreciation) - OD',
                    'Depreciation Deduction Waiver (Nil Depreciation) - 1 Year - OD'
                ])) {
                    $zero_dep_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'GR41-Cover For Electrical and Electronic Accessories - 1 Year',
                    'GR41-Cover For Electrical and Electronic Accessories',
                    'GR41-Cover For Electrical and Electronic Accessories - OD',
                    'GR41-Cover For Electrical and Electronic Accessories - 1 Year - OD'
                ))) {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'GR42-Outbuilt CNG/LPG-Kit-Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 3 Year',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - OD',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year - OD',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 2 Year',
                    'InBuilt CNG Cover',
                    'InBuilt  CNG  Cover',
                    'InBuilt CNG Cover - OD',
                    'InBuilt  CNG  Cover - OD'
                ))) {
                    if ($value['Premium'] != 60) {
                        $lpg_cng += $value['Premium'];
                    } else {
                        $lpg_cng_tp += $value['Premium'];
                    }
                }

                if (in_array($value['CoverDesc'], array(
                    'CNG/LPG KIT - TP  COVER-GR-42',
                    'IN-BUILT CNG/LPG KIT - TP  COVER',
                    'CNG/LPG KIT - TP  COVER-GR-42 - 1 YEAR',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year - TP',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - TP',
                    'InBuilt CNG Cover - TP',
                    'InBuilt  CNG  Cover - TP',
                ))) {
                    $lpg_cng_tp += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'Cover For Non Electrical Accessories - 1 Year',
                    'Cover For Non Electrical Accessories',
                    'Cover For Non Electrical Accessories - OD',
                    'Cover For Non Electrical Accessories - 1 Year - OD'
                ))) {
                    $non_electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'GR36B2-PA Cover For Passengers (Un-Named Persons)',
                    'GR36B2-PA Cover For Passengers (Un-Named Persons) - TP',
                    'GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year - TP',
                    'GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year',
                    'GR36B2-PA Cover For Passengers (Un-Named Persons) - 2 Year',
                    'GR36B2-PA Cover For Passengers (Un-Named Persons) - 3 Year',
                ])) {
                    $pa_unnamed += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3',
                    'PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3 - 1 YEAR'
                ])) {
                    $pa_paid_driver = ($requestData->business_type == 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'GR36A-PA FOR OWNER DRIVER',
                    'GR36A-PA FOR OWNER DRIVER - 1 YEAR',
                    'GR36A-PA FOR OWNER DRIVER - 1 Year',
                    'GR36A-PA FOR OWNER DRIVER - TP',
                    'GR36A-PA FOR OWNER DRIVER - 1 Year - TP',
                    'GR36A-PA FOR OWNER DRIVER - 2 Year',
                    'GR36A-PA FOR OWNER DRIVER - 3 Year'
                ])) {
                    $pa_owner += $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'Legal Liability Coverages For Paid Driver',
                    'Legal Liability Coverages For Paid Driver - TP',
                    'Legal Liability Coverages For Paid Driver - 1 Year - TP',
                    'Legal Liability Coverages For Paid Driver - 1 Year',
                    'Legal Liability Coverages For Paid Driver - 2 Year',
                    'Legal Liability Coverages For Paid Driver - 3 Year',
                ])) {
                    $ll_paid_driver += $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['TP Total', 'TP Total'])) {
                    $final_tp_premium += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'De-Tariff Discount - 1 Year',
                    'De-Tariff Discount',
                    'De-Tariff Discount - OD',
                    'De-Tariff Discount - 1 Year - OD'
                ])) {
                    $other_discount = abs($value['Premium']);
                }

                if (in_array($value['CoverDesc'], [

                    'GR30-Anti Theft Discount Cover', 
                    'ANTI-THEFT DISCOUNT-GR-30', 
                    'GR30-Anti Theft Discount Cover - 1 Year - OD','GR30-Anti Theft Discount Cover - OD',
                    'GR30-Anti Theft Discount Cover - 1 Year'
                ])) {
                    $anti_theft = abs($value['Premium']);
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
                    'KEY REPLACEMENT',
                    'KEY REPLACEMENT - 1 YEAR',
                    'Key Replacement',
                    'Key Replacement - 1 Year',
                    'Key Replacement - OD',
                    'Key Replacement - 1 Year - OD'
                ))) {
                    $key_rplc = $value['Premium'];
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
                    'Consumable',
                    'Consumable - 1 Year',
                    'Consumable - OD',
                    'Consumable - 1 Year - OD'
                ])) {
                    $consumables_cover += $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array(
                    'Personal Belonging',
                    'Personal Belonging - 1 Year',
                    'Personal Belonging - OD',
                    'Personal Belonging - 1 Year - OD'
                ))) {
                    $personal_belonging = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'INVOICE RETURN',
                    'INVOICE RETURN - 1 YEAR',
                    'Return to Invoice - 1 Year',
                    'Return to Invoice - 1 Year - OD'
                ))) {
                    $return_to_invoice = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'GR39A-Limit The Third Party Property Damage Cover',
                    'GR39A-Limit The Third Party Property Damage Cover - 1 Year',
                    'GR39A-Limit The Third Party Property Damage Cover - 2 Year',
                    'GR39A-Limit The Third Party Property Damage Cover - 3 Year',
                    'GR39A-Limit The Third Party Property Damage Cover - TP',
                    'GR39A-Limit The Third Party Property Damage Cover - 2 Year - TP',
                    'GR39A-Limit The Third Party Property Damage Cover - 1 Year - TP'
                ])) {
                    $tppd_discount += abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], [
                    'Nil Depreciation Loading',
                    'Nil Depreciation Loading - 1 Year',
                    'Nil Depreciation Loading - OD',
                    'Nil Depreciation Loading - 1 Year - OD'
                ])) {
                    $zero_dep_loading += abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], [
                    'Engine Protector Loading',
                    'Engine Protector Loading - OD'
                ])) {
                    $engine_protection = abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], [
                    'Consumable Loading',
                    'Consumable Loading - OD'
                ])) {
                    $consumables_cover += abs($value['Premium']);
                }

                // GEO Extension
                if (in_array($value['CoverDesc'], [
                    'GR4-Geographical Extension',
                    'GR4-Geographical Extension - 1 Year',
                    'GR4-Geographical Extension - 2 Year',
                    'GR4-Geographical Extension - 3 Year',
                    'GR4-Geographical Extension - 1 Year - OD',
                    'GR4-Geographical Extension - OD',

                    'GR4-Geographical Extension - 1 Year - TP',
                    'GR4-Geographical Extension - TP',
                    'GR4-Geographical Extension - 2 Year - TP',
                    'GR4-Geographical Extension - 3 Year - TP'
                ])) {
                    if ($value['Premium'] == 400) {
                        $geog_Extension_OD_Premium += $value['Premium'];
                    } else {
                        $geog_Extension_TP_Premium += $value['Premium'];
                    }
                }
                if (in_array($value['CoverDesc'], [
                    'Legal Liability To Employees',
                    'Legal Liability To Employees - 1 Year - TP',
                    'Legal Liability To Employees - 2 Year - TP',
                    'Legal Liability To Employees - 3 Year - TP',
                    'Legal Liability To Employees - 1 Year',
                    'Legal Liability To Employees - 2 Year',
                    'Legal Liability To Employees - 3 Year',
                    'Legal Liability To Employees - TP'])) {
                    $imt29amt += $value['Premium'];
                }
            }

            $zero_dep_amount += $zero_dep_loading;
            $igst = $final_payable_amount - $final_net_premium;
            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => $loadingAmount,
                "final_od_premium" => $final_od_premium,
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
                "ll_paid_employee" => $imt29amt,
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
