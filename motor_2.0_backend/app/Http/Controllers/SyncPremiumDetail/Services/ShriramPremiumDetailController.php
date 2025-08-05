<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
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
                    $response = json_decode($response, true);
                    if (empty($response)) {
                        $response = XmlToArray::convert($response);
                    }
                } catch (\Throwable $th) {
                    $response = null;
                }
                if (($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE'] ?? '1') == '0') {
                    $webserviceId = $log['id'];
                    return self::savePcvXmlPremiumDetails($webserviceId);
                }

                if (($response['MessageResult']['Result'] ?? '') == 'Success') {
                    if (isset($response['GenerateGCCVProposalResult'])) {
                        $webserviceId = $log['id'];
                        return self::saveGcvJsonPremiumDetails($webserviceId);
                    } else {
                        $webserviceId = $log['id'];
                        return self::savePcvJsonPremiumDetails($webserviceId);
                    }
                }

                if (
                    isset($response['soap:Body']) &&
                    ($response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_CODE'] ?? '1') == '0'
                ) {
                    $webserviceId = $log['id'];
                    return self::saveGcvPremiumDetails($webserviceId);
                }
            }

            return [
                'status' => false,
                'message' => 'Valid Proposal log not found',
            ];
        } catch (\Throwable $th) {
            info($th);
            return [
                'status' => false,
                'message' => $th->getMessage(),
            ];
        }
    }

    public static function saveGcvPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = XmlToArray::convert($logs->response);

            $imt_23 = $igst = $anti_theft = $other_discount = $rsapremium =
                $pa_paid_driver = $zero_dep_amount = $ncb_discount = $tppd =
                $final_tp_premium = $final_od_premium = $final_net_premium =
                $igst = $final_payable_amount = $basic_od  = $electrical_accessories =
                $lpg_cng_tp = $lpg_cng = $non_electrical_accessories = $pa_owner =
                $ll_paid_driver = $tppd_discount = 0;

            $result = $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult'];
            foreach ($result['CoverDtlList']['CoverDtl'] as $key => $value) {
                if ($value['CoverDesc'] == 'BASIC OD COVER') {
                    $basic_od = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'IMT23-COVERAGE FOR IMT 21 EXCLUSIONS') {
                    $imt_23 = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES'])) {
                    $electrical_accessories = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR42--CNG-KIT-COVER'])) {
                    $lpg_cng += $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR42--CNG KIT - TP  COVER', 'In Built CNG/LPG Kit TP Cover', 'In Built CNG Kit TP Cover'])) {
                    $lpg_cng_tp += $value['Premium'];
                }

                if ($value['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                    $pa_owner = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'Legal Liability Coverages For Paid Driver') {
                    $pa_paid_driver = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                    $other_discount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'GR30-Anti Theft Discount Cover - OD'])) {
                    $anti_theft = abs($value['Premium']);
                }

                if ($value['CoverDesc'] == 'ROAD SIDE ASSISTANCE') {
                    $rsapremium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                    $ncb_discount = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['LL TO PAID DRIVER', 'LL TO PAID CLEANER', 'LL TO PAID CONDUCTOR'])) {
                    $ll_paid_driver = $ll_paid_driver + $value['Premium'];
                }
                if ($value['CoverDesc'] == 'BASIC TP COVER') {
                    $tppd = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'TP TOTAL') {
                    $final_tp_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'OD TOTAL') {
                    $final_od_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'IGST') {
                    $igst = $igst + $value['Premium'];
                }

                if ($value['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'GR39A-TPPD COVER') {
                    $tppd_discount = (float)($value['Premium']);
                }

                if ($value['CoverDesc'] == 'TOTAL PREMIUM') {
                    $final_net_premium = (float)($value['Premium']);
                }
            }
            $NonElectricalaccessSI = 0;

            $selected_addons = SelectedAddons::select('accessories')->where('user_product_journey_id', $enquiryId)->first();
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            foreach ($accessories as $value) {
                if (in_array('Non-Electrical Accessories', $value)) {
                    $NonElectricalaccessSI = $value['sumInsured'];
                }
            }

            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (float) (($NonElectricalaccessSI * 3.283) / 100);
                $basic_od = ($basic_od - $non_electrical_accessories);
            }

            $temp_od = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
            $imt_23 = ($temp_od * 0.15);

            $final_gst_amount = isset($igst) ? $igst : 0;

            $total_addon_amount = $zero_dep_amount + $rsapremium + $imt_23;
            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $total_addon_amount,
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
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => 0,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_gst_amount,
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

    public static function savePcvJsonPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = json_decode($logs->response, true);

            $final_payable_amount = $final_net_premium =
            $final_od_premium = $final_tp_premium = $basic_tp =
            $ncb_discount = $rsapremium = $anti_theft =
            $other_discount = $zero_dep_amount = $pa_paid_driver =
            $pa_owner = $non_electrical_accessories = $lpg_cng_tp =
            $lpg_cng = $electrical_accessories = $basic_od =
            $rsapremium = $geoextensionod = $geoextensiontp =
            $tppd_discount = $limited_to_own_premises = $pa_unnamed =
            $ll_paid_driver = $motorProtection = $llPaidCleaner = 
            $llPaidConductor = $imt23 = 0;

            foreach ($response['GeneratePCCVProposalResult']['CoverDtlList'] as $key => $value) {
                $value['CoverDesc'] = trim($value['CoverDesc']);
                $value['CoverDesc'] = str_replace('  ', ' ', $value['CoverDesc']);

                if (in_array($value['CoverDesc'], [
                    'Road Side Assistance',
                    'Road Side Assistance - OD'
                ])) {
                    $rsapremium = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Basic Premium - OD',
                    'Basic OD Premium'
                ])) {
                    $basic_od = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR41-Cover For Electrical and Electronic Accessories - OD',
                    'GR41-Cover For Electrical and Electronic Accessories'
                ])) {
                    $electrical_accessories = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'InBuilt CNG Cover - OD'
                ])) {
                    $lpg_cng += $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'InBuilt CNG Cover - TP',
                    'InBuilt CNG Cover - TP'
                ])) {
                    $lpg_cng_tp += $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'InBuilt CNG Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover'
                ])) {
                    if ($value['Premium'] == 60) {
                        $lpg_cng_tp += $value['Premium'];
                    } else {
                        $lpg_cng += $value['Premium'];
                    }
                } elseif ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - OD') {
                    $lpg_cng += $value['Premium'];
                } elseif ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - TP') {
                    $lpg_cng_tp += $value['Premium'];
                } elseif ($value['CoverDesc'] == 'GR4-Geographical Extension - OD') {
                    $geoextensionod = $value['Premium'];
                } elseif ($value['CoverDesc'] == 'GR4-Geographical Extension - TP') {
                    $geoextensiontp = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Cover For Non Electrical Accessories - OD',
                    'Cover For Non Electrical Accessories'
                ])) {
                    $non_electrical_accessories = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR36A-PA FOR OWNER DRIVER - TP',
                    'GR36A-PA FOR OWNER DRIVER'
                ])) {
                    $pa_owner = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Legal Liability Coverages For Paid Driver - TP',
                    'Legal Liability Coverages For Paid Driver',
                    'LL TO PAID DRIVER'
                ])) {
                    $ll_paid_driver = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR39A-Limit The Third Party Property Damage Cover - TP',
                    'GR39A-Limit The Third Party Property Damage Cover'
                ])) {
                    $tppd_discount = abs($value['Premium']);
                } elseif ($value['CoverDesc'] == 'GR35-Cover For Limited To Own Premises') {
                    $limited_to_own_premises = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Nil Depreciation Cover - OD',
                    'Nil Depreciation Loading - OD',
                    'Nil Depreciation Cover'
                ])) {
                    $zero_dep_amount += $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'De-Tariff Discount - OD',
                    'De-Tariff Discount'
                ])) {
                    $other_discount = abs($value['Premium']);
                } elseif (in_array($value['CoverDesc'], ['NCB Discount'])) {
                    $ncb_discount = abs($value['Premium']);
                } elseif (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'GR30-Anti Theft Discount Cover - OD'])) {
                    $anti_theft = abs($value['Premium']);
                } elseif (in_array($value['CoverDesc'], [
                    'Basic Premium - TP',
                    'Basic TP Premium'
                ])) {
                    $basic_tp = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR36B3-PA-Paid Driver, Conductor,Cleaner - TP',
                    'GR36B3-PA-Paid Driver, Conductor,Cleaner'
                ])) {
                    $pa_paid_driver = $value['Premium'];
                } elseif ($value['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = $value['Premium'];
                } elseif ($value['CoverDesc'] == 'Total Premium') {
                    $final_net_premium = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], ['OD Total'])) {
                    $final_od_premium = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], ['TP Total'])) {
                    $final_tp_premium = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'Motor Protection',
                    'Motor Protection - OD'
                ])) {
                    $motorProtection = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'LL TO PAID CLEANER',
                ])) {
                    $llPaidCleaner = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'LL TO PAID CONDUCTOR',
                ])) {
                    $llPaidConductor = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'Cover for lamps tyres / tubes mudguards bonnet /side parts bumpers headlights and paintwork of damaged portion only (IMT-23)',
                ])) {
                    $imt23 = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'GR4-Geographical Extension',
                ])) {
                    $premium = round($value['Premium'], 2);
                    if ($premium == 100) {
                        $geoextensiontp += $premium;
                    } else {
                        $geoextensionod += $premium;
                    }
                }
            }

            $final_discount = $ncb_discount + $other_discount;
            $total_addon_amount = $zero_dep_amount + $rsapremium + $imt23;
            if (empty($final_od_premium)) {
                $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $geoextensionod - $final_discount + $total_addon_amount;
            }
            
            if (empty($final_tp_premium)) {
                $final_tp_premium = $basic_tp + $lpg_cng_tp + $pa_unnamed + $pa_paid_driver + $ll_paid_driver + $geoextensiontp + $llPaidCleaner + $llPaidConductor;
            }
            $final_gst_amount = $final_payable_amount - $final_net_premium;


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $basic_tp,
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
                "imt_23" => $imt23,
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
                "motor_protection" => $motorProtection,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => $llPaidConductor,
                "ll_paid_cleaner" => $llPaidCleaner,
                "geo_extension_odpremium" => $geoextensionod,
                "geo_extension_tppremium" => $geoextensiontp,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_gst_amount,
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

    public static function saveGcvJsonPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = json_decode($logs->response, true);

            $final_payable_amount = $final_net_premium =
            $final_od_premium = $final_tp_premium = $basic_tp =
            $ncb_discount = $rsapremium = $anti_theft =
            $other_discount = $zero_dep_amount = $pa_paid_driver =
            $pa_owner = $non_electrical_accessories = $lpg_cng_tp =
            $lpg_cng = $electrical_accessories = $basic_od =
            $rsapremium = $geoextensionod = $geoextensiontp =
            $tppd_discount = $limited_to_own_premises = $pa_unnamed =
            $ll_paid_driver = $motorProtection = $llPaidCleaner = 
            $llPaidConductor = $imt23 = $loadingAmount = $addtowprem = 0;

            foreach ($response['GenerateGCCVProposalResult']['CoverDtlList'] as  $value) {
                $value['CoverDesc'] = trim($value['CoverDesc']);
                $value['CoverDesc'] = str_replace('  ', ' ', $value['CoverDesc']);

                if (in_array($value['CoverDesc'], [
                    'Road Side Assistance',
                    'Road Side Assistance - OD'
                ])) {
                    $rsapremium = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Basic Premium - OD',
                    'Basic OD Premium'
                ])) {
                    $basic_od = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR41-Cover For Electrical and Electronic Accessories - OD',
                    'GR41-Cover For Electrical and Electronic Accessories'
                ])) {
                    $electrical_accessories = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'InBuilt CNG Cover - OD'
                ])) {
                    $lpg_cng += $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'InBuilt CNG Cover - TP',
                    'InBuilt CNG Cover - TP'
                ])) {
                    $lpg_cng_tp += $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'InBuilt CNG Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover'
                ])) {
                    if ($value['Premium'] == 60) {
                        $lpg_cng_tp += $value['Premium'];
                    } else {
                        $lpg_cng += $value['Premium'];
                    }
                } elseif ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - OD') {
                    $lpg_cng += $value['Premium'];
                } elseif ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - TP') {
                    $lpg_cng_tp += $value['Premium'];
                } elseif ($value['CoverDesc'] == 'GR4-Geographical Extension - OD') {
                    $geoextensionod = $value['Premium'];
                } elseif ($value['CoverDesc'] == 'GR4-Geographical Extension - TP') {
                    $geoextensiontp = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Cover For Non Electrical Accessories - OD',
                    'Cover For Non Electrical Accessories'
                ])) {
                    $non_electrical_accessories = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR36A-PA FOR OWNER DRIVER - TP',
                    'GR36A-PA FOR OWNER DRIVER'
                ])) {
                    $pa_owner = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Legal Liability Coverages For Paid Driver - TP',
                    'Legal Liability Coverages For Paid Driver',
                    'LL TO PAID DRIVER'
                ])) {
                    $ll_paid_driver = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR39A-Limit The Third Party Property Damage Cover - TP',
                    'GR39A-Limit The Third Party Property Damage Cover'
                ])) {
                    $tppd_discount = abs($value['Premium']);
                } elseif ($value['CoverDesc'] == 'GR35-Cover For Limited To Own Premises') {
                    $limited_to_own_premises = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'Nil Depreciation Cover - OD',
                    'Nil Depreciation Loading - OD',
                    'Nil Depreciation Cover'
                ])) {
                    $zero_dep_amount += $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'De-Tariff Discount - OD',
                    'De-Tariff Discount',
                    'Special Discount',
                    'Special Discount - OD'
                ])) {
                    $other_discount += abs($value['Premium']);
                } elseif (in_array($value['CoverDesc'], ['NCB Discount'])) {
                    $ncb_discount = abs($value['Premium']);
                } elseif (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'GR30-Anti Theft Discount Cover - OD'])) {
                    $anti_theft = abs($value['Premium']);
                } elseif (in_array($value['CoverDesc'], [
                    'Basic Premium - TP',
                    'Basic TP Premium'
                ])) {
                    $basic_tp = $value['Premium'];
                } elseif (in_array($value['CoverDesc'], [
                    'GR36B3-PA-Paid Driver, Conductor,Cleaner - TP',
                    'GR36B3-PA-Paid Driver, Conductor,Cleaner'
                ])) {
                    $pa_paid_driver = $value['Premium'];
                } elseif ($value['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = $value['Premium'];
                } elseif ($value['CoverDesc'] == 'Total Premium') {
                    $final_net_premium = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], ['OD Total'])) {
                    $final_od_premium = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], ['TP Total'])) {
                    $final_tp_premium = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'Motor Protection',
                    'Motor Protection - OD'
                ])) {
                    $motorProtection = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'LL TO PAID CLEANER',
                ])) {
                    $llPaidCleaner = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'LL TO PAID CONDUCTOR',
                ])) {
                    $llPaidConductor = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'Cover for lamps tyres / tubes mudguards bonnet /side parts bumpers headlights and paintwork of damaged portion only (IMT-23)',
                ])) {
                    $imt23 = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'GR4-Geographical Extension',
                ])) {
                    $premium = round($value['Premium'], 2);
                    if ($premium == 100) {
                        $geoextensiontp += $premium;
                    } else {
                        $geoextensionod += $premium;
                    }
                } elseif (in_array($value['CoverDesc'], [
                    'Minimum OD Loading',
                ])) {
                    $loadingAmount = round($value['Premium'], 2);
                } elseif (in_array($value['CoverDesc'], [
                    'Towing',
                    'Towing - OD'
                ])){
                    $addtowprem = $value['Premium'];
                }
            }

            $final_discount = $ncb_discount + $other_discount;
            $total_addon_amount = $zero_dep_amount + $rsapremium + $imt23;
            if (empty($final_od_premium)) {
                $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $geoextensionod - $final_discount + $total_addon_amount + $loadingAmount;
            }
            
            if (empty($final_tp_premium)) {
                $final_tp_premium = $basic_tp + $lpg_cng_tp + $pa_unnamed + $pa_paid_driver + $ll_paid_driver + $geoextensiontp + $llPaidCleaner + $llPaidConductor;
            }
            $final_gst_amount = $final_payable_amount - $final_net_premium;


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => $loadingAmount,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $basic_tp,
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
                "imt_23" => $imt23,
                'additional_towing' => $addtowprem,
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
                "motor_protection" => $motorProtection,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => $llPaidConductor,
                "ll_paid_cleaner" => $llPaidCleaner,
                "geo_extension_odpremium" => $geoextensionod,
                "geo_extension_tppremium" => $geoextensiontp,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_gst_amount,
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

    public static function savePcvXmlPremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $response = XmlToArray::convert($logs->response);

            $final_payable_amount = $cgst = $sgst = $igst =
                $final_net_premium = $final_od_premium = $final_tp_premium =
                $tppd = $ncb_discount = $rsapremium = $anti_theft =
                $other_discount = $zero_dep_amount = $pa_paid_driver = $pa_owner =
                $ll_paid_driver = $non_electrical_accessories = $lpg_cng_tp =
                $lpg_cng = $electrical_accessories = $basic_od =
                $voluntary_excess_discount = $rsapremium = $pa_unnamed =
                $tp_discount = 0;


            foreach ($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['CoverDtlList']['CoverDtl'] as $key => $cover) {
                if ($cover['CoverDesc'] == 'BASIC OD COVER') {
                    $basic_od = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'VOLUNTARY EXCESS DISCOUNT-IMT-22A') {
                    $voluntary_excess_discount = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'OD TOTAL') {
                    $final_od_premium = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'BASIC TP COVER') {
                    $tppd = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'TP TOTAL') {
                    $final_tp_premium = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'TOTAL PREMIUM') {
                    $final_net_premium = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'IGST') {
                    $igst = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'CGST') {
                    $cgst = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'SGST') {
                    $sgst = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                    $ncb_discount = $cover['Premium'];
                }
                if (in_array($cover['CoverDesc'], array('Nil Depreciation', 'Nil Depreciation Cover'))) {
                    $zero_dep_amount = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES') {
                    $electrical_accessories = $cover['Premium'];
                }
                if (in_array($cover['CoverDesc'], array('GR42--CNG-KIT-COVER', 'CNG/LPG-KIT-COVER-GR42', 'INBUILT CNG/LPG KIT', 'In Built CNG Kit', 'In Built CNG/LPG Kit'))) {
                    $lpg_cng = $cover['Premium'];
                }
                if (in_array($cover['CoverDesc'], ['GR42--CNG/LPG KIT - TP  COVER', 'GR42--CNG KIT - TP  COVER', 'CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER', 'IN-BUILT CNG KIT - TP  COVER', 'IN-BUILT CNG TP COVER'])) {
                    $lpg_cng_tp = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'GR36B2--PA-UN-NAMED') {
                    $pa_unnamed = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3') {
                    $pa_paid_driver = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                    $pa_owner = $cover['Premium']; // CPA
                }
                if ($cover['CoverDesc'] == 'LL TO PAID DRIVER') {
                    $ll_paid_driver = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'TPPD COVER') {
                    $tp_discount = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                    $other_discount = $cover['Premium'];
                }
                if ($cover['CoverDesc'] == 'ROAD SIDE ASSISTANCE') {
                    $rsapremium = $cover['Premium'];
                }

                if ($cover['CoverDesc'] == 'TOTAL PREMIUM') {
                    $final_net_premium = round($cover['Premium'], 2);
                }
                if ($cover['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = round($cover['Premium'], 2);
                }
            }

            $NonElectricalaccessSI = 0;

            $selected_addons = SelectedAddons::select('accessories')->where('user_product_journey_id', $enquiryId)->first();
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            foreach ($accessories as $value) {
                if (in_array('Non-Electrical Accessories', $value)) {
                    $NonElectricalaccessSI = $value['sumInsured'];
                }
            }

            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (string) round((($NonElectricalaccessSI * 3.283) / 100), 2);
                $basic_od = ($basic_od - $non_electrical_accessories);
            }

            $final_tp_premium = $tppd + $ll_paid_driver + $lpg_cng_tp + $pa_paid_driver;

            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
            $total_addon_amount = $zero_dep_amount + $rsapremium;

            if (empty($igst)) {
                $igst = $cgst + $sgst;
            }

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $total_addon_amount,
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
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess_discount,
                "tppd_discount" => $tp_discount,
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
}
