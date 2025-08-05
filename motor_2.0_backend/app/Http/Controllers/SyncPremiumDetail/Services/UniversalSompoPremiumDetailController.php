<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class UniversalSompoPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Generation',
                    getGenericMethodName('Proposal Generation', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Proposal Generation',
                    getGenericMethodName('Proposal Generation', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'universal_sompo'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = html_entity_decode($response);
                $response = XmlToArray::convert($response);
                $response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'] ?? [];
                if (!empty($response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value'] ?? null)) {
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

            $response = html_entity_decode($response->response);
            $response = XmlToArray::convert($response);
            $filter_response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];

            $total_OD_premium = $od_premium = $electrical_amount = $non_electrical_amount = $lpg_cng_amount = 0;
            $total_TP_premium = $tp_premium = $liability = $pa_owner =  $pa_unnamed = $pa_cover_driver = $lpg_cng_tp_amount = 0;
            $total_discount = $ncb_discount = $anti_theft_device_discount = $automobile_association_discount = $detariff_discount_amount = $voluntary_deductable_amount = $tppd_discount_amount = 0;

            $tppd_discount_amount = 0;

            $zero_dep_amount = 0;
            $rsa = 0;
            $key_replacement = 0;
            $eng_prot = $eng_prot_diesel = $eng_prot_petrol = 0;
            $consumable = 0;
            $tyre_secure = 0;
            $return_to_invoice = 0;
            $imt23_amount = 0;
            $discountData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['OtherDiscounts']['OtherDiscountGroup']['OtherDiscountGroupData'];

            foreach ($discountData as $key => $discount) {
                if ($discount['Description']['@attributes']['Value'] == 'Automobile Association discount') {
                    $automobile_association_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Antitheft device discount') {
                    $anti_theft_device_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Handicap discount') {
                    $handicap_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'De-tariff discount') {
                    $detariff_discount_value = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'No claim bonus') {
                    $ncb_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'TPPD Discount') {
                    $tppd_discount_amount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Voluntary deductable') {
                    $voluntary_deductable_amount = $discount['Premium']['@attributes']['Value'];
                }
            }

            // with GST
            $total_premium_amount       = $filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value'];
            //Without GST
            $net_premium_amount         = $filter_response['Root']['Product']['PremiumCalculation']['NetPremium']['@attributes']['Value'];
            $service_tax                = $filter_response['Root']['Product']['PremiumCalculation']['ServiceTax']['@attributes']['Value'];
            $detariff_discount_amount   = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Premium']['@attributes']['Value'];
            $discount_rate_final        = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Rate']['@attributes']['Value'];
            $ex_showroom_Price          = $filter_response['Root']['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'];

            $coversdata = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['CoverDetails']['Covers']['CoversData'];

            foreach ($coversdata as $key => $cover) {
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic OD') {
                    $od_premium = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic TP') {
                    $tp_premium = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'BUILTIN CNG KIT / LPG KIT OD') {
                    $builtin_lpg_cng_kit_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT OD') {
                    $lpg_cng_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT TP') {
                    $lpg_cng_tp_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'ELECTRICAL ACCESSORY OD') {
                    $electrical_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'FIBRE TANK - OD') {
                    $fibre_tank_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'NON ELECTRICAL ACCESSORY OD') {
                    $non_electrical_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Other OD') {
                    $other_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO OWNER DRIVER') {
                    $pa_owner = $cover['Premium']['@attributes']['Value'];
                }

                if ($cover['CoverGroups']['@attributes']['Value'] == 'UNNAMED PA COVER TO PASSENGERS') {
                    $pa_unnamed = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'LEGAL LIABILITY TO PAID DRIVER') {
                    $liability = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO PAID DRIVER') {
                    $pa_cover_driver = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'INCLUSION OF IMT23') {
                    $imt23_amount = (int)$cover['Premium']['@attributes']['Value'];
                }
            }

            $addonsData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['AddonCoverDetails']['AddonCovers']['AddonCoversData'];



            foreach ($addonsData as $key => $add_on_cover) {
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Road side Assistance') {
                    $rsa = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'COST OF CONSUMABLES') {
                    $consumable = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - DIESEL') {
                    $eng_prot_diesel = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - PETROL') {
                    $eng_prot_petrol = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'KEY REPLACEMENT') {
                    $key_replacement = $add_on_cover['Premium']['@attributes']['Value'];
                }

                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Nil Depreciation Waiver cover') {
                    $zero_dep_amount = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'RETURN TO INVOICE') {
                    $return_to_invoice = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'TYRE AND RIM SECURE') {
                    $tyre_secure = $add_on_cover['Premium']['@attributes']['Value'];
                }
            }

            $total_OD_premium = (int) $od_premium + (int)$electrical_amount + (int)$non_electrical_amount + (int)$lpg_cng_amount;
            $total_TP_premium = (int)$tp_premium + (int)$liability + (int)$pa_owner +  (int)$pa_unnamed + (int)$pa_cover_driver + (int)$lpg_cng_tp_amount;
            $total_discount = (int)$ncb_discount + (int)$anti_theft_device_discount + (int)$automobile_association_discount + (int)$detariff_discount_amount + (int)$voluntary_deductable_amount;

            $total_OD_premium -= $total_discount;
            $total_TP_premium -= $tppd_discount_amount;

            $eng_prot = $eng_prot_petrol + $eng_prot_diesel;
            
            $addons = [
                'zero_depreciation' => $zero_dep_amount,
                'road_side_assistance' => $rsa,
                "imt_23" => $imt23_amount,
                'consumable' => $consumable,
                'key_replacement' => $key_replacement,
                'engine_protector' => $eng_prot,
                'tyre_secure' => $tyre_secure,
                'return_to_invoice' => $return_to_invoice
            ];

            $total_addon_premium = array_sum($addons);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od_premium,
                "loading_amount" => 0,
                "final_od_premium" => $total_OD_premium + $total_addon_premium,
                // TP Tags
                "basic_tp_premium" => $tp_premium,
                "final_tp_premium" => $total_TP_premium,
                // Accessories
                "electric_accessories_value" => $electrical_amount,
                "non_electric_accessories_value" => $non_electrical_amount,
                "bifuel_od_premium" => $lpg_cng_amount,
                "bifuel_tp_premium" => $lpg_cng_tp_amount,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => $rsa,
                "imt_23" => $imt23_amount,
                "consumable" => $consumable,
                "key_replacement" => $key_replacement,
                "engine_protector" => $eng_prot,
                "ncb_protection" => 0,
                "tyre_secure" => $tyre_secure,
                "return_to_invoice" => $return_to_invoice,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_cover_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $liability,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft_device_discount,
                "voluntary_excess" => $voluntary_deductable_amount,
                "tppd_discount" => $tppd_discount_amount,
                "other_discount" => $detariff_discount_amount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $net_premium_amount,
                "service_tax_amount" => $service_tax,
                "final_payable_amount" => $total_premium_amount,
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
