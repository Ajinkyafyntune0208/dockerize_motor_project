<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class GodigitPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Submit - Renewal',
                    'Renewal Proposal Service',
                    getGenericMethodName('Renewal Proposal Service', 'proposal'),
                    getGenericMethodName('Proposal Submit - Renewal', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Proposal Submit',
                    getGenericMethodName('Proposal Submit', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id', 'request')
            ->where([
                'enquiry_id' => $enquiryId,
                'company' => 'godigit'
            ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $isOneApi = false;
            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $request = $log['request'];
                
                $response = json_decode($response, true);
                $request = json_decode($request, true);
                if (isset($request['motorCreateQuote'])) {
                    if (isset($response['grossPremium']) && ($response['error']['errorCode'] ?? '1') == '0') {
                        $webserviceId = $log['id'];
                        $isOneApi = true;
                        break;
                    }
                } else {
                    if (isset($response['grossPremium']) && ($response['error']['errorCode'] ?? '1') == '0') {
                        $webserviceId = $log['id'];
                        break;
                    }
                }
            }
            
            if (!empty($webserviceId)) {
                if ($isOneApi) {
                    return self::saveOneApiPremiumDetails($webserviceId);
                } else {
                    return self::savePremiumDetails($webserviceId);
                }
            }else{
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
            $response = json_decode($response->response);

            $contract = $response->contract ?? null;
            $od = $basicTp = $ncbDiscount = 0;
            $zeroDep = $rsa = $engineProtect = $tyreSecure = $rti = $consumables = $lopb = $keyReplacement = 0;
            $cng_lpg_tp = 0;
            $cover_pa_unnamed_passenger_premium = $cover_pa_paid_driver_premium = $cover_pa_owner_driver_premium = $llpaiddriver_premium = 0;

            $isLPG = $is_tppd = false;

            $isOwnDamage = $response->enquiryId == 'GODIGIT_QQ_TWO_WHEELER_SAOD_01' ? true : false;

            foreach (($contract->coverages ?? []) as $key => $value) {
                switch ($key) {
                    case 'thirdPartyLiability':
                        $is_tppd = $value->isTPPD ?? false;
                        $basicTp = !empty($value->netPremium ?? null) ? round(str_replace("INR ", "", $value->netPremium), 2) : 0;
                        break;
                    
                        case 'addons':
                            foreach ($value as $key => $addon) {
                                if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY' )) {
                                    switch ($key) {
                                        case 'partsDepreciation':
                                            $zeroDep = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'roadSideAssistance':
                                            $rsa = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'engineProtection':
                                            $engineProtect = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'tyreProtection':
                                            $tyreSecure = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'returnToInvoice':
                                            $rti = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'consumables':
                                            $consumables = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'personalBelonging':
                                            $lopb = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'keyAndLockProtect':
                                            $keyReplacement = str_replace('INR ', '', $addon->netPremium);
                                            break;
                                    }
                                }
                            }
                            break;
    
                        case 'ownDamage':
                            if (isset($value->netPremium)) {
                                 $od = str_replace("INR ", "", $value->netPremium);
                                 foreach ($value->discount->discounts as $key => $type) {
                                     if ($type->discountType == "NCB_DISCOUNT") {
                                         $ncbDiscount = str_replace("INR ", "", $type->discountAmount);
                                     }
                                 }
                            } 
                            break;
    
                        case 'legalLiability' :
                            foreach ($value as $cover => $subcover) {
                                if ($cover == "paidDriverLL") {
                                    if ($subcover->selection == 1) {
                                        $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    }
                                }
                            }
                            break;
                    
                        case 'personalAccident':
                            if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium))) {
                                $cover_pa_owner_driver_premium = round(str_replace("INR ", "", $value->netPremium), 2);
                            } 
                            break;
    
                        case 'accessories' :
                            foreach ($value as $cover => $subcover) {
                                if ($cover == "cng" && ($subcover->selection ?? false)) {
                                   $isLPG = true;
                                }
                            }
                            break;
    
                        case 'unnamedPA':
                            foreach ($value as $cover => $subcover) {
                                if (isset($subcover->selection) && $subcover->selection == 1 && isset($subcover->netPremium)) {
                                    if ($cover == 'unnamedPaidDriver') {
                                        $cover_pa_paid_driver_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    }

                                    if ($cover == 'unnamedPax') {
                                        $cover_pa_unnamed_passenger_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    }
                                }
                            }
                            break;
                }
            }

            $requestData = getQuotation($enquiryId);
            $mmv = get_mmv_details(json_decode(json_encode(['product_sub_type_id' => 2])), $requestData->version_id, 'godigit');
            if (!$isLPG) {
                $isLPG = !empty($mmv['data']) && in_array($mmv['data']['fuel_type'], ['CNG', 'LPG']);
            }

            if ($isLPG && !$isOwnDamage) {
                $cng_lpg_tp = 60;
                $basicTp = $basicTp - $cng_lpg_tp;
            }

            $tppd_discount = $is_tppd ? ($requestData->business_type == 'newbusiness' ? 250 : 50) : 0;

            $netPremium  = str_replace("INR ", "", $response->netPremium);
            $finalPayable = str_replace("INR ", "", $response->grossPremium);
            $serviceTax = str_replace("INR ", "", $response->serviceTax->totalTax);

            $final_total_discount = $ncbDiscount;
            $final_od_premium = $od;
            $addon_premium = $zeroDep + $rsa + $engineProtect + $tyreSecure + $rti + $consumables + $lopb + $keyReplacement;
            $final_tp_premium = $basicTp + $cng_lpg_tp + $llpaiddriver_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_owner_driver_premium;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium - $final_total_discount + $addon_premium,
                // TP Tags
                "basic_tp_premium" => $basicTp + $tppd_discount,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => 0, // Value is included in OD premium, no separate tag provided
                "non_electric_accessories_value" => 0, // Value is included in OD premium, no separate tag provided
                "bifuel_od_premium" => 0, // Value is included in OD premium, no separate tag provided
                "bifuel_tp_premium" => $cng_lpg_tp,
                // Addons
                "compulsory_pa_own_driver" => $cover_pa_owner_driver_premium,
                "zero_depreciation" => $zeroDep,
                "road_side_assistance" => $rsa,
                "imt_23" => 0,
                "consumable" => $consumables,
                "key_replacement" => $keyReplacement,
                "engine_protector" => $engineProtect,
                "ncb_protection" => 0, // They don't provide
                "tyre_secure" => $tyreSecure,
                "return_to_invoice" => $rti,
                "loss_of_personal_belongings" => $lopb,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $cover_pa_paid_driver_premium,
                "unnamed_passenger_pa_cover" => $cover_pa_unnamed_passenger_premium,
                "ll_paid_driver" => $llpaiddriver_premium,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0, //They don't provide
                "voluntary_excess" => 0, // They don't provide
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncbDiscount,
                // Final tags
                "net_premium" => $netPremium,
                "service_tax_amount" => $serviceTax,
                "final_payable_amount" => $finalPayable,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? round(abs($value), 2) : 0;
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

    public static function saveOneApiPremiumDetails($webserviceId)
    {
        try {
            $response = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $response->enquiry_id;
            $response = json_decode($response->response);

            $contract = $response->contract ?? null;
            $od = $basicTp = $ncbDiscount = 0;
            $zeroDep = $rsa = $engineProtect = $tyreSecure = $rti = $consumables = $lopb = $keyReplacement = 0;
            $cng_lpg_tp = 0;
            $cover_pa_unnamed_passenger_premium = $cover_pa_paid_driver_premium = $cover_pa_owner_driver_premium = $llpaiddriver_premium = 0;

            $isLPG = $is_tppd = false;

            $isOwnDamage = $response->enquiryId == 'GODIGIT_QQ_TWO_WHEELER_SAOD_01' ? true : false;

            foreach (($contract->coverages ?? []) as $key => $value) {
                switch ($key) {
                    case 'thirdPartyLiability':
                        $is_tppd = $value->isTPPD ?? false;
                        $basicTp = !empty($value->netPremium ?? null) ? round(str_replace("INR ", "", $value->netPremium), 2) : 0;
                        break;
                    
                        case 'addons':
                            foreach ($value as $key => $addon) {
                                if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY' )) {
                                    switch ($key) {
                                        case 'partsDepreciation':
                                            $zeroDep = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'roadSideAssistance':
                                            $rsa = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'engineProtection':
                                            $engineProtect = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'tyreProtection':
                                            $tyreSecure = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'returnToInvoice':
                                            $rti = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'consumables':
                                            $consumables = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'personalBelonging':
                                            $lopb = str_replace('INR ', '', $addon->netPremium);
                                            break;
    
                                        case 'keyAndLockProtect':
                                            $keyReplacement = str_replace('INR ', '', $addon->netPremium);
                                            break;
                                    }
                                }
                            }
                            break;
    
                        case 'ownDamage':
                            if (isset($value->netPremium)) {
                                 $od = str_replace("INR ", "", $value->netPremium);
                                 foreach ($value->discount->discounts as $key => $type) {
                                     if ($type->discountType == "NCB_DISCOUNT") {
                                         $ncbDiscount = str_replace("INR ", "", $type->discountAmount);
                                     }
                                 }
                            } 
                            break;
    
                        case 'legalLiability' :
                            foreach ($value as $cover => $subcover) {
                                if ($cover == "paidDriverLL") {
                                    if ($subcover->selection == 1) {
                                        $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    }
                                }
                            }
                            break;
                    
                        case 'personalAccident':
                            if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium))) {
                                $cover_pa_owner_driver_premium = round(str_replace("INR ", "", $value->netPremium), 2);
                            } 
                            break;
    
                        case 'accessories' :
                            foreach ($value as $cover => $subcover) {
                                if ($cover == "cng" && ($subcover->selection ?? false)) {
                                   $isLPG = true;
                                }
                            }
                            break;
    
                        case 'unnamedPA':
                            foreach ($value as $cover => $subcover) {
                                if (isset($subcover->selection) && $subcover->selection == 1 && isset($subcover->netPremium)) {
                                    if ($cover == 'unnamedPaidDriver') {
                                        $cover_pa_paid_driver_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    }

                                    if ($cover == 'unnamedPax') {
                                        $cover_pa_unnamed_passenger_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    }
                                }
                            }
                            break;
                }
            }

            $requestData = getQuotation($enquiryId);
            $mmv = get_mmv_details(json_decode(json_encode(['product_sub_type_id' => 2])), $requestData->version_id, 'godigit');
            if (!$isLPG) {
                $isLPG = !empty($mmv['data']) && in_array($mmv['data']['fuel_type'], ['CNG', 'LPG']);
            }

            if ($isLPG && !$isOwnDamage) {
                $cng_lpg_tp = 60;
                $basicTp = $basicTp - $cng_lpg_tp;
            }

            $tppd_discount = $is_tppd ? ($requestData->business_type == 'newbusiness' ? 250 : 50) : 0;

            $netPremium  = str_replace("INR ", "", $response->netPremium);
            $finalPayable = str_replace("INR ", "", $response->grossPremium);
            $serviceTax = str_replace("INR ", "", $response->serviceTax->totalTax);

            $final_total_discount = $ncbDiscount;
            $final_od_premium = $od;
            $addon_premium = $zeroDep + $rsa + $engineProtect + $tyreSecure + $rti + $consumables + $lopb + $keyReplacement;
            $final_tp_premium = $basicTp + $cng_lpg_tp + $llpaiddriver_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_owner_driver_premium;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium - $final_total_discount + $addon_premium,
                // TP Tags
                "basic_tp_premium" => $basicTp + $tppd_discount,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => 0, // Value is included in OD premium, no separate tag provided
                "non_electric_accessories_value" => 0, // Value is included in OD premium, no separate tag provided
                "bifuel_od_premium" => 0, // Value is included in OD premium, no separate tag provided
                "bifuel_tp_premium" => $cng_lpg_tp,
                // Addons
                "compulsory_pa_own_driver" => $cover_pa_owner_driver_premium,
                "zero_depreciation" => $zeroDep,
                "road_side_assistance" => $rsa,
                "imt_23" => 0,
                "consumable" => $consumables,
                "key_replacement" => $keyReplacement,
                "engine_protector" => $engineProtect,
                "ncb_protection" => 0, // They don't provide
                "tyre_secure" => $tyreSecure,
                "return_to_invoice" => $rti,
                "loss_of_personal_belongings" => $lopb,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $cover_pa_paid_driver_premium,
                "unnamed_passenger_pa_cover" => $cover_pa_unnamed_passenger_premium,
                "ll_paid_driver" => $llpaiddriver_premium,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0, //They don't provide
                "voluntary_excess" => 0, // They don't provide
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncbDiscount,
                // Final tags
                "net_premium" => $netPremium,
                "service_tax_amount" => $serviceTax,
                "final_payable_amount" => $finalPayable,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? round(abs($value), 2) : 0;
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
