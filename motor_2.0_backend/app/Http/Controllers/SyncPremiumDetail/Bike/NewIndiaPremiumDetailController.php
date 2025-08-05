<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class NewIndiaPremiumDetailController extends Controller
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
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id', 'request')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'new_india'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                try {
                    $response = XmlToArray::convert((string) remove_xml_namespace($response));
                } catch (\Throwable $th) {
                    $response = null;
                }

                if (!empty($response) && array_search_key('PRetCode', $response) == '0') {
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
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $logs->enquiry_id;

            $premium_resp = XmlToArray::convert((string) remove_xml_namespace($logs->response));
            $PRetCode = array_search_key('PRetCode', $premium_resp);

            $zero_dep_key = 0;
            $engine_protector_key = 0;
            $consumable_key = 0;
            $ncb_protecter_key = 0;
            $tyre_secure_key = 0;
            $personal_belongings_key = 0;
            $rsa_key = 0;
            $key_protect_key = 0;
            $rti_cover_key = 0;
            $pa_owner_driver = 0;
            $basic_tp_key = 0;
            $electrical_key = 0;
            $nonelectrical_key = 0;
            $basic_od_key = 0;
            $calculated_ncb = 0;
            $ll_paid_driver = 0;
            $pa_paid_driver = 0;
            $pa_unnamed_person = 0;
            $additional_od_prem_cnglpg = 0;
            $additional_tp_prem_cnglpg = 0;
            $anti_theft_discount_key = 0;
            $aai_discount_key = 0;
            $od_discount = 0;
            $total_od = 0;
            $total_tp = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $voluntary_Deductible_Discount = 0;
            $service_tax = 0;
            $calculated_premium = 0;
            $net_total_premium = 0;

            $requestData = getQuotation($enquiryId);

            $properties = array_search_key('properties', $premium_resp);

            $premium_resp = array_change_key_case_recursive($properties);

            unset($PRetCode, $properties);
            foreach ($premium_resp as $cover) {
                if ($cover['name'] === 'Additional Premium for Electrical fitting') {
                    $electrical_key = $cover['value'];
                } elseif ($cover['name'] === 'Additional Premium for Non-Electrical fitting') {
                    $nonelectrical_key = $cover['value'];
                } elseif ($cover['name'] === 'Basic OD Premium') {
                    $basic_od_key = $cover['value'];
                } elseif ($cover['name'] === 'IMT Rate Basic OD Premium') {
                    $basic_od_key = $cover['value'];
                } elseif ($cover['name'] === 'Basic TP Premium' && $requestData->business_type != 'newbusiness') {
                    $basic_tp_key = $cover['value'];
                } elseif (in_array($cover['name'], ['(#)Total TP Premium', '(#)Total TP Premium for 2nd Year', '(#)Total TP Premium for 3rd Year', '(#)Total TP Premium for 4th Year', '(#)Total TP Premium for 5th Year'])  && $requestData->business_type == 'newbusiness') {
                    $basic_tp_key += $cover['value'];
                } elseif ($cover['name'] === 'Calculated NCB Discount') {
                    $calculated_ncb = $cover['value'];
                } elseif ($cover['name'] === 'Calculated Voluntary Deductible Discount') {
                    $voluntary_Deductible_Discount = $cover['value'];
                } elseif ($cover['name'] === 'OD Premium Discount Amount') {
                    $od_discount = $cover['value'];
                } elseif ($cover['name'] === 'Compulsory PA Premium for Owner Driver') {
                    $pa_owner_driver = $cover['value'];
                } elseif ($cover['name'] === 'Net Total Premium') {
                    $net_total_premium = $cover['value'];
                } elseif ($cover['name'] === 'Legal Liability Premium for Paid Driver') {
                    $ll_paid_driver = $cover['value'];
                } elseif ($cover['name'] === 'PA premium for Paid Drivers And Others') {
                    $pa_paid_driver = $cover['value'];
                } elseif ($cover['name'] === 'PA premium for UnNamed/Hirer/Pillion Persons') {
                    $pa_unnamed_person = $cover['value'];
                } elseif ($cover['name'] == 'Additional OD Premium for CNG/LPG') {
                    $additional_od_prem_cnglpg = $cover['value'];
                } elseif ($cover['name'] == 'Additional TP Premium for CNG/LPG') {
                    $additional_tp_prem_cnglpg = $cover['value'];
                } elseif ($cover['name'] == 'Calculated Discount for Anti-Theft Devices') {
                    $anti_theft_discount_key = $cover['value'];
                } elseif ($cover['name'] == 'Calculated Discount for Membership of recognized Automobile Association') {
                    $aai_discount_key = $cover['value'];
                } elseif (($cover['name'] === 'Premium for nil depreciation cover')) {
                    $zero_dep_key = $cover['value'];
                } elseif ($cover['name'] == 'Engine Protect Cover Premium') {
                    $engine_protector_key = $cover['value'];
                } elseif ($cover['name'] == 'Consumable Items Cover Premium') {
                    $consumable_key = $cover['value'];
                } elseif ($cover['name'] == 'NCB Protection Cover Premium') {
                    $ncb_protecter_key = $cover['value'];
                } elseif ($cover['name'] == 'Tyre and Alloy Cover Premium') {
                    $tyre_secure_key = $cover['value'];
                } elseif ($cover['name'] == 'Personal Belongings Cover Premium') {
                    $personal_belongings_key = $cover['value'];
                } elseif ($cover['name'] == 'Additional Towing Charges Cover Premium') {
                    $rsa_key = $cover['value'];
                } elseif ($cover['name'] == 'Key Protect Cover Premium') {
                    $key_protect_key = $cover['value'];
                } elseif ($cover['name'] == 'Return to Invoice Cover Premium') {
                    $rti_cover_key = $cover['value'];
                } elseif ($cover['name'] == 'Loading for Extension of Geographical area') {
                    $geog_Extension_OD_Premium = $cover['value'];
                } elseif ($cover['name'] == 'Extension of Geographical Area Premium') {
                    $geog_Extension_TP_Premium = $cover['value'];
                } elseif ($cover['name'] === 'Service Tax') {
                    $service_tax = $cover['value'];
                } elseif ($cover['name'] === 'Calculated Premium') {
                    $calculated_premium = $cover['value'];
                } elseif ($cover['name'] === 'Net Total Premium') {
                    $net_total_premium = $cover['value'];
                }
            }

            $total_od = $basic_od_key + $electrical_key + $nonelectrical_key + $additional_od_prem_cnglpg + $geog_Extension_OD_Premium;

            $total_tp = $basic_tp_key + $ll_paid_driver + $pa_unnamed_person + $additional_tp_prem_cnglpg + $pa_paid_driver + $geog_Extension_TP_Premium;

            $addon_premium = $zero_dep_key + $rsa_key + $consumable_key + $key_protect_key +
                $engine_protector_key + $ncb_protecter_key + $tyre_secure_key + $rti_cover_key +
                $personal_belongings_key;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_od_key,
                "loading_amount" => 0,
                "final_od_premium" => $total_od + $addon_premium,
                // TP Tags
                "basic_tp_premium" => $basic_tp_key,
                "final_tp_premium" => $total_tp,
                // Accessories
                "electric_accessories_value" => $electrical_key,
                "non_electric_accessories_value" => $nonelectrical_key,
                "bifuel_od_premium" => $additional_od_prem_cnglpg,
                "bifuel_tp_premium" => $additional_tp_prem_cnglpg,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner_driver,
                "zero_depreciation" => $zero_dep_key,
                "road_side_assistance" => $rsa_key,
                "imt_23" => 0,
                "consumable" => $consumable_key,
                "key_replacement" => $key_protect_key,
                "engine_protector" => $engine_protector_key,
                "ncb_protection" => $ncb_protecter_key,
                "tyre_secure" => $tyre_secure_key,
                "return_to_invoice" => $rti_cover_key,
                "loss_of_personal_belongings" => $personal_belongings_key,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $pa_paid_driver,
                "unnamed_passenger_pa_cover" => $pa_unnamed_person,
                "ll_paid_driver" => $ll_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => $geog_Extension_OD_Premium,
                "geo_extension_tppremium" => $geog_Extension_TP_Premium,
                // Discounts
                "anti_theft" => $anti_theft_discount_key,
                "voluntary_excess" => $voluntary_Deductible_Discount,
                "tppd_discount" => 0,
                "other_discount" => $od_discount + $aai_discount_key,
                "ncb_discount_premium" => $calculated_ncb,
                // Final tags
                "net_premium" => $calculated_premium,
                "service_tax_amount" => $service_tax,
                "final_payable_amount" => $net_total_premium,
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
