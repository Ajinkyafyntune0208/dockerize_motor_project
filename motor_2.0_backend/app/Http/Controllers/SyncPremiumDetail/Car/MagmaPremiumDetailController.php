<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;

class MagmaPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Premium Calculation',
                    'Fetch Policy Details',
                    getGenericMethodName('Premium Calculation', 'proposal'),
                    getGenericMethodName('Fetch Policy Details', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal'),
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'magma',
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (($response['ServiceResult'] ?? '') == 'Success') {
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
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'request', 'created_at')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;
            $arr_premium = json_decode($logs->response, true);

            $basic_tp_premium = $basic_od_premium = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $lpg_od_premium = $cng_od_premium = $lpg_tp_premium = $cng_tp_premium = $antitheft = $tppd_discount = $voluntary_excess_discount = $roadside_asst_premium = $zero_dep_premium = $ncb_protection_premium = $eng_protector_premium = $return_to_invoice_premium = $incon_allow_premium = $key_replacement_premium = $loss_of_personal_belongings_premium = $other_discount = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $consumable_premium = $legal_liability_to_employee = 0;
            $lpg_discount = $electrical_discount = $non_electrical_discount = 0;
            $tyreSecure = 0;
            $add_array = $arr_premium['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'];

            foreach ($add_array as $add1) {
                $coverName = str_replace([' ', '-'], '', $add1['AddOnCoverType']);
                if (in_array($coverName, ["BasicOD"])) {
                    $basic_od_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["BasicTP"])) {
                    $basic_tp_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["LLtoPaidDriverIMT28"])) {
                    $liabilities = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["PAOwnerDriver", "PAOwnerCover"])) {
                    $pa_owner_driver = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["BasicRoadsideAssistance", "RoadSideAssistance"])) {
                    $roadside_asst_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["ZeroDepreciation"])) {
                    $zero_dep_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["NCBProtection"])) {
                    $ncb_protection_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["EngineProtector"])) {
                    $eng_protector_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["ReturntoInvoice", "ReturnToInvoice"])) {
                    $return_to_invoice_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["InconvenienceAllowance"])) {
                    $incon_allow_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["KeyReplacement"])) {
                    $key_replacement_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["LossOfPersonalBelongings", "LossOfPerBelongings"])) {
                    $loss_of_personal_belongings_premium = (float) ($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["Consumables"])) {
                    $consumable_premium = (float)($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["EmployeeOfInsured"])) {
                    $legal_liability_to_employee = (float)($add1['AddOnCoverTypePremium']);
                } elseif (in_array($coverName, ["TyreGuard"])) {
                    $tyreSecure = (float)($add1['AddOnCoverTypePremium']);
                }
            }
            if (isset($arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'])) {
                $optionadd_array = $arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'];

                foreach ($optionadd_array as $add) {
                    $cover_name = !empty($add['OptionalAddOnCoverName']) ? $add['OptionalAddOnCoverName'] : ($add['OptionalAddOnCoversName'] ?? null);
                    $cover_premium = !empty($add['OptionalAddOnCoverPremium']) ? $add['OptionalAddOnCoverPremium'] : ($add['AddOnCoverTotalPremium'] ?? 0);

                    if (empty($cover_name) || empty($cover_premium)) {
                        continue;
                    }

                    $cover_name = str_replace([' ', '-'], '', $cover_name);
                    $cover_premium = (float) $cover_premium;

                    if ($cover_name == "LLPaidDriverCleanerTP") {
                        $liabilities = $cover_premium;
                    } elseif (in_array($cover_name, ['ElectricalorElectronicAccessories', 'Electrical'])) {
                        $electrical = $cover_premium;
                    } elseif (in_array($cover_name, ["NonElectricalAccessories", "NonElectrical", "NonElectricalAccessories"])) {
                        $non_electrical = $cover_premium;
                    } elseif (in_array($cover_name, ["PersonalAccidentCoverUnnamed", "UnnamedPACover"])) {
                        $pa_unnamed = $cover_premium;
                    } elseif (in_array($cover_name, ["PAPaidDrivers,CleanersandConductors", "PAPaidDriver"])) {
                        $pa_paid_driver = $cover_premium;
                    } elseif (in_array($cover_name, ["LPGKitOD", "CNGKitOD", "ExternalCNGkitOD", "InbuiltCNGkitOD"])) {
                        $lpg_od_premium = $cover_premium;
                    } elseif (in_array($cover_name, ["LPGKitTP", "CNGKitTP", "ExternalCNGkitTP", "InbuiltCNGkitTP"])) {
                        $lpg_tp_premium = $cover_premium;
                    } elseif (in_array($cover_name, ["GeographicalExtensionOD"])) {
                        $geog_Extension_OD_Premium = $cover_premium;
                    } elseif (in_array($cover_name, ["GeographicalExtensionTP"])) {
                        $geog_Extension_TP_Premium = $cover_premium;
                    }
                }
            }

            if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Discount'])) {
                $discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Discount'];

                foreach ($discount_array as $discount) {
                    $discount['DiscountType'] = str_replace([' ', '-'], '', $discount['DiscountType']);
                    if (in_array($discount['DiscountType'], ['AntiTheftDeviceOD', 'ApprovedAntiTheftDeviceDetariffDiscount'])) {
                        $antitheft = (float) ($discount['DiscountTypeAmount']);
                    } elseif ($discount['DiscountType'] == "AutomobileAssociationDiscount") {
                        $automobile_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["BonusDiscount", "BonusDiscountOD"])) {
                        $ncb_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["BasicODDetariffDiscount"])) {
                        $other_discount += (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["ElectricalorElectronicAccessoriesDetariffDiscountonElecricalAccessories", "ElecricalDetariffDiscount"])) {
                        $electrical_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["NonElectricalAccessoriesDetariffDiscount", "NonElecricalDetariffDiscount"])) {
                        $non_electrical_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif ($discount['DiscountType'] == "LPGKitODDetariffDiscountonCNGorLPGKit") {
                        $other_discount += (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["VoluntaryExcessDiscount", "VoluntaryExcessDiscountOD"])) {
                        $voluntary_excess_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["BasicTPTPPDDiscount", "TPPDDiscount"])) {
                        $tppd_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif ($discount['DiscountType'] == "DetariffDiscount") {
                        $other_discount += (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ['CNGKitODDetariffDiscountonCNGorLPGKit', 'ExternalCNGkitDetariffDiscount'])) {
                        $lpg_discount = (float) $discount['DiscountTypeAmount'];
                    }
                }
            }
            if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Loading'])) {
                $loadin_discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Loading'];
                foreach ($loadin_discount_array as $loading) {
                    if ($loading['LoadingType'] == 'Built in CNG - OD loading - OD') {
                        $lpg_od_premium = (float) $loading['LoadingTypeAmount'];
                    } elseif ($loading['LoadingType'] == 'Built in CNG-TP Loading-TP') {
                        $lpg_tp_premium = (float) $loading['LoadingTypeAmount'];
                    }
                }
            }

            $electrical -= $electrical_discount;
            $non_electrical -= $non_electrical_discount;
            $lpg_od_premium -= $lpg_discount;

            $final_net_premium = round($arr_premium['OutputResult']['PremiumBreakUp']['NetPremium'], 2);
            $final_payable_amount = round($arr_premium['OutputResult']['PremiumBreakUp']['TotalPremium'], 2);
            
            $final_total_discount = $ncb_discount + $antitheft + $voluntary_excess_discount + $other_discount;
            $final_od_premium = $basic_od_premium - $final_total_discount + $geog_Extension_OD_Premium;
            $final_tp_premium = $basic_tp_premium + $liabilities + $pa_unnamed + $pa_owner_driver + $lpg_tp_premium + $pa_paid_driver + $geog_Extension_TP_Premium - $tppd_discount + $legal_liability_to_employee;
            $final_addon_amount = $zero_dep_premium + $return_to_invoice_premium + $roadside_asst_premium + $ncb_protection_premium + $eng_protector_premium + $key_replacement_premium + $loss_of_personal_belongings_premium + $consumable_premium + $tyreSecure;
            $final_addon_amount += $electrical + $non_electrical + $lpg_od_premium + $cng_od_premium;
            $final_od_premium = $final_od_premium + $final_addon_amount;

            $final_od_premium = round(($arr_premium['OutputResult']['PremiumBreakUp']['NetODPremium'] ?? $final_od_premium), 2);
            $final_tp_premium = round(($arr_premium['OutputResult']['PremiumBreakUp']['NetTPPremium'] ?? $final_tp_premium), 2);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od_premium,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $basic_tp_premium,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical,
                "non_electric_accessories_value" => $non_electrical,
                "bifuel_od_premium" => $lpg_od_premium + $cng_od_premium,
                "bifuel_tp_premium" => $lpg_tp_premium + $cng_tp_premium,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner_driver,
                "zero_depreciation" => $zero_dep_premium,
                "road_side_assistance" => $roadside_asst_premium,
                "imt_23" => 0,
                "consumable" => $consumable_premium,
                "key_replacement" => $key_replacement_premium,
                "engine_protector" => $eng_protector_premium,
                "ncb_protection" => $ncb_protection_premium,
                "tyre_secure" => $tyreSecure,
                "return_to_invoice" => $return_to_invoice_premium,
                "loss_of_personal_belongings" => $loss_of_personal_belongings_premium,
                "wind_shield" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $liabilities,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "ll_paid_employee" => $legal_liability_to_employee,
                "geo_extension_odpremium" => $geog_Extension_OD_Premium,
                "geo_extension_tppremium" => $geog_Extension_TP_Premium,
                // Discounts
                "anti_theft" => $antitheft,
                "voluntary_excess" => $voluntary_excess_discount,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $other_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_payable_amount - $final_net_premium,
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
