<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

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

            $basic_tp_premium = $basic_od_premium = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $lpg_od_premium = $cng_od_premium = $lpg_tp_premium = $cng_tp_premium = $antitheft = $tppd_discount = $voluntary_excess_discount = $consumables = $roadside_asst_premium = $zero_dep_premium = $ncb_protection_premium = $eng_protector_premium = $return_to_invoice_premium = $key_replacement_premium = $loss_of_personal_belongings_premium = $other_discount = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $electrical_discount = $non_electrical_discount = 0;
            $add_array = $arr_premium['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'];

            foreach ($add_array as $add1) {
                if (in_array($add1['AddOnCoverType'], ["Basic - OD", "Basic OD"])) {
                    $basic_od_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Basic - TP", "Basic TP"])) {
                    $basic_tp_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["LL to Paid Driver IMT 28"])) {
                    $liabilities = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["PA Owner Driver", "PAOwnerCover"])) {
                    $pa_owner_driver = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Basic Roadside Assistance", "RoadSideAssistance"])) {
                    $roadside_asst_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Zero Depreciation", "ZeroDepreciation"])) {
                    $zero_dep_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["NCB Protection", "NCBProtection"])) {
                    $ncb_protection_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Engine Protector", "EngineProtector"])) {
                    $eng_protector_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Return to Invoice", "ReturnToInvoice"])) {
                    $return_to_invoice_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Inconvenience Allowance", "InconvenienceAllowance"])) {
                    $incon_allow_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Key Replacement", "KeyReplacement"])) {
                    $key_replacement_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Loss Of Personal Belongings", "LossOfPerBelongings"])) {
                    $loss_of_personal_belongings_premium = (float) ($add1['AddOnCoverTypePremium']);
                }

                if (in_array($add1['AddOnCoverType'], ["Consumables"])) {
                    $consumables = (float) ($add1['AddOnCoverTypePremium']);
                }
            }

            if (isset($arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'])) {
                $optionadd_array = $arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'];

                foreach ($optionadd_array as $add) {
                    if ($add['OptionalAddOnCoverName'] == "LLPaidDriverCleaner-TP" && ($add['OptionalAddOnCoverPremium'] > 0)) {
                        $liabilities = (float) ($add['OptionalAddOnCoverPremium']);
                    }
                    if (in_array($add['OptionalAddOnCoversName'], ['Electrical or Electronic Accessories', 'Electrical'])) {
                        $electrical = (float) (($add['AddOnCoverTotalPremium'] > 0) ? $add['AddOnCoverTotalPremium'] : $add['OptionalAddOnCoverPremium']);
                    } elseif (in_array($add['OptionalAddOnCoversName'], ["Non-Electrical Accessories", "NonElectrical", "Non Electrical Accessories"])) {
                        $non_electrical = (float) (($add['AddOnCoverTotalPremium'] > 0) ? $add['AddOnCoverTotalPremium'] : $add['OptionalAddOnCoverPremium']);
                    } elseif (in_array($add['OptionalAddOnCoversName'], ["Personal Accident Cover-Unnamed", "UnnamedPACover", "Personal accident cover Unnamed"])) {
                        $pa_unnamed = (float) ($add['AddOnCoverTotalPremium'] > 0) ? $add['AddOnCoverTotalPremium'] : $add['OptionalAddOnCoverPremium'];
                    } elseif (in_array($add['OptionalAddOnCoversName'], ["PA Paid Drivers, Cleaners and Conductors", "PAPaidDriver"])) {
                        $pa_paid_driver = (float) ($add['AddOnCoverTotalPremium'] > 0 ? $add['AddOnCoverTotalPremium'] : $add['OptionalAddOnCoverPremium']);
                    } elseif (in_array($add['OptionalAddOnCoversName'], ["LPG Kit-OD"])) {
                        $lpg_od_premium = (float) ($add['AddOnCoverTotalPremium']);
                    } elseif (in_array($add['OptionalAddOnCoversName'], ["LPG Kit-TP"])) {
                        $lpg_tp_premium = (float) ($add['AddOnCoverTotalPremium']);
                    } elseif (in_array($add['OptionalAddOnCoversName'], ["Geographical Extension - OD", "Geographical Extension OD", "GeographicalExtension-OD"])) {
                        $geog_Extension_OD_Premium = (float) ($add['AddOnCoverTotalPremium']);
                    } elseif (in_array($add['OptionalAddOnCoversName'], ["Geographical Extension - TP", "Geographical Extension TP", "GeographicalExtension-TP"])) {
                        $geog_Extension_TP_Premium = (float) ($add['AddOnCoverTotalPremium'] > 0 ? $add['AddOnCoverTotalPremium'] : $add['OptionalAddOnCoverPremium']);
                    }
                }
            }

            if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Discount'])) {
                $discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Discount'];

                foreach ($discount_array as $discount) {
                    if (in_array($discount['DiscountType'], ['Anti-Theft Device - OD', 'ApprovedAntiTheftDevice-Detariff Discount'])) {
                        $antitheft = (float) ($discount['DiscountTypeAmount']);
                    } elseif ($discount['DiscountType'] == "Automobile Association Discount") {
                        $automobile_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif ( in_array($discount['DiscountType'], ['Bonus Discount', 'Bonus Discount - OD', 'No Claim Bonus Discount'])) {
                        $ncb_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["Basic - OD - Detariff Discount", "Basic OD-Detariff Discount"])) {
                        $other_discount += (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["Electrical or Electronic Accessories - Detariff Discount on Elecrical Accessories", "Elecrical-Detariff Discount"])) {
                        $electrical_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["Non-Electrical Accessories - Detariff Discount", "NonElecrical-Detariff Discount", "Non Electrical Accessories - Detariff Discount"])) {
                        $non_electrical_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif ($discount['DiscountType'] == "LPG Kit-OD - Detariff Discount on CNG or LPG Kit") {
                        $other_discount += (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["Voluntary Excess Discount", "Voluntary Excess Discount-OD"])) {
                        $voluntary_excess_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["Basic - TP - TPPD Discount", "TPPDDiscount", "Basic TP - TPPD Discount"])) {
                        $tppd_discount = (float) ($discount['DiscountTypeAmount']);
                    } elseif (in_array($discount['DiscountType'], ["Detariff Discount", "Basic OD - Detariff Discount"])) {
                        $other_discount += (float) ($discount['DiscountTypeAmount']);
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

            $final_net_premium = round($arr_premium['OutputResult']['PremiumBreakUp']['NetPremium'], 2);
            $final_payable_amount = round($arr_premium['OutputResult']['PremiumBreakUp']['TotalPremium'], 2);

            $final_total_discount = $ncb_discount + $antitheft + $voluntary_excess_discount + $other_discount;
            $final_od_premium = $basic_od_premium - $final_total_discount + $geog_Extension_OD_Premium;
            $final_tp_premium = $basic_tp_premium + $liabilities + $pa_unnamed + $pa_owner_driver + $lpg_tp_premium + $pa_paid_driver + $geog_Extension_TP_Premium + $cng_tp_premium - $tppd_discount;
            $final_addon_amount = $zero_dep_premium + $return_to_invoice_premium + $roadside_asst_premium + $ncb_protection_premium + $eng_protector_premium + $key_replacement_premium + $loss_of_personal_belongings_premium + $consumables;
            $final_addon_amount += $electrical + $non_electrical + $lpg_od_premium + $cng_od_premium;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od_premium,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $final_addon_amount,
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
                "consumable" => $consumables,
                "key_replacement" => $key_replacement_premium,
                "engine_protector" => $eng_protector_premium,
                "ncb_protection" => $ncb_protection_premium,
                "tyre_secure" => 0,
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
