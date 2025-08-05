<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GodigitPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Submit Renewal',
                    'Proposal Submit - Renewal',
                    'Proposal Submit',
                    getGenericMethodName('Proposal Submit Renewal', 'proposal'),
                    getGenericMethodName('Proposal Submit - Renewal', 'proposal')
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
            } else {
                return [
                    'status' => false,
                    'message' => 'Valid Proposal log not found',
                ];
            }

            // if (!empty($webserviceId)) {
            //     return self::savePremiumDetails($webserviceId);
            // } else {
            //     return [
            //         'status' => false,
            //         'message' => 'Valid Proposal log not found',
            //     ];
            // }
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

            $requestData = getQuotation($enquiryId);

            $contract = $response->contract ?? null;
            $od = $basicTp = $ncbDiscount = 0;
            $zeroDep = $rsa = $engineProtect = $tyreSecure = $rti = $consumables = $lopb = $keyReplacement = $ncb_protection = 0;
            $cng_lpg_tp = $llPadiEmployee = 0;
            $cover_pa_unnamed_passenger_premium = $cover_pa_paid_driver_premium = $cover_pa_owner_driver_premium = $llpaiddriver_premium = 0;

            $isLPG = $is_tppd = false;

            $isOwnDamage = $response->enquiryId == 'GODIGIT_QQ_PVT_CAR_SAOD_01' ? true : false;

            if (is_array($contract->coverages ?? null) && isset($contract->coverages[0]->coverType)) {

                foreach (($response->discounts->otherDiscounts ?? []) as $other_discounts) {
                    $other_discounts = json_decode(json_encode($other_discounts), true);
                    if ($other_discounts['discountType'] == 'NCB_DISCOUNT') {
                        $ncbDiscount = (int) str_replace('INR ', '', $other_discounts['discountAmount']);
                    }
                }
                foreach ($contract->coverages as $coverage) {
                    $coverage = json_decode(json_encode($coverage), true);
                    if ($coverage['coverType'] == 'THIRD_PARTY') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Property Damage') {
                                $basicTp = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'OWN_DAMAGE') {
                        $od = round((float) str_replace('INR ', '', $coverage['netPremium']), 2);
                    } elseif ($coverage['coverType'] == 'PA_OWNER') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Personal Accident' && $subcover['selection']) {
                                $cover_pa_owner_driver_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'ADDONS') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Breakdown Assistance') {
                                $rsa = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Tyre Protect') {
                                $tyreSecure = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Parts Depreciation Protect') {
                                $zeroDep = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Consumable cover') {
                                $consumables = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Engine and Gear Box Protect') {
                                $engineProtect = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Return to Invoice') {
                                $rti = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Rim Protect Cover') {
                                $ncb_protection = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Personal Belonging') {
                                $lopb = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Key and Lock Protect') {
                                $keyReplacement = (float) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'PA_UNNAMED') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'PA cover for Unnamed Passenger - IMT 16' && $subcover['selection']) {
                                $cover_pa_unnamed_passenger_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'PA cover for Paid Driver - IMT 17' && $subcover['selection']) {
                                $cover_pa_paid_driver_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'LEGAL_LIABILITY') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Legal Liability to Paid Driver - IMT 28' && $subcover['selection']) {
                                $llpaiddriver_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    }
                }

                $mmv = get_mmv_details(json_decode(json_encode(['product_sub_type_id' => 1])), $requestData->version_id,'godigit');
                $mmv = (object) array_change_key_case((array) $mmv['data'],CASE_LOWER);

                if (in_array($mmv->fuel_type, ['CNG', 'PETROL+CNG', 'DIESEL+CNG', 'LPG'])) {
                    $cng_lpg_tp = $isOwnDamage ? 0 : (($requestData->business_type == 'newbusiness') ? 180 : 60);
                    $basicTp = $basicTp - $cng_lpg_tp;
                }
            } else {
                foreach (($contract->coverages ?? []) as $key => $value) {
                    switch ($key) {
                        case 'thirdPartyLiability':
                            $is_tppd = $value->isTPPD ?? false;
                            $basicTp = !empty($value->netPremium ?? null) ? round(str_replace("INR ", "", $value->netPremium), 2) : 0;
                            break;
                        
                            case 'addons':
                                foreach ($value as $key => $addon) {
                                    if (isset($addon->selection) && $addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY' )) {
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
                                    if ($cover == "paidDriverLL" && $subcover->selection == 1) {
                                        $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    } elseif ($cover == 'employeesLL' && $subcover->selection) {
                                        $llPadiEmployee = round(str_replace("INR ", "", $subcover->netPremium), 2);
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

                $mmv = get_mmv_details(json_decode(json_encode(['product_sub_type_id' => 1])), $requestData->version_id, 'godigit');
                if (!$isLPG) {
                    $isLPG = !empty($mmv['data']) && in_array($mmv['data']['fuel_type'], ['CNG', 'LPG']);
                }
                if ($isLPG && !$isOwnDamage) {
                    $cng_lpg_tp = $requestData->business_type == 'newbusiness' ? 180 : 60;
                    $basicTp = $basicTp - $cng_lpg_tp;
                }
            }

            $netPremium  = str_replace("INR ", "", $response->netPremium);
            $finalPayable = str_replace("INR ", "", $response->grossPremium);
            $serviceTax = str_replace("INR ", "", $response->serviceTax->totalTax);

            $tppd_discount = $is_tppd ? ($requestData->business_type == 'newbusiness' ? 300 : 100) : 0;
            $final_total_discount = $ncbDiscount;
            $final_od_premium = $od;
            $addon_premium = $zeroDep + $rsa + $engineProtect + $tyreSecure + $rti + $consumables + $lopb + $keyReplacement + $ncb_protection;
            $final_tp_premium = $basicTp + $cng_lpg_tp + $llpaiddriver_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_owner_driver_premium + $llPadiEmployee;

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
                "ncb_protection" => $ncb_protection,
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
                "ll_paid_employee" => $llPadiEmployee,
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

    public static function saveOneApiPremiumDetails($webserviceId)
    {
        try {
            $response = WebServiceRequestResponse::select('response', 'enquiry_id')
            ->find($webserviceId);
            $enquiryId = $response->enquiry_id;
            $response = json_decode($response->response);

            $requestData = getQuotation($enquiryId);

            $contract = $response->contract ?? null;
            $od = $basicTp = $ncbDiscount = 0;
            $zeroDep = $rsa = $engineProtect = $tyreSecure = $rti = $consumables = $lopb = $keyReplacement = $ncb_protection = 0;
            $cng_lpg_tp = $llPadiEmployee = 0;
            $cover_pa_unnamed_passenger_premium = $cover_pa_paid_driver_premium = $cover_pa_owner_driver_premium = $llpaiddriver_premium = 0;

            $isLPG = $is_tppd = false;

            $isOwnDamage = $response->enquiryId == 'GODIGIT_QQ_PVT_CAR_SAOD_01' ? true : false;

            if (is_array($contract->coverages ?? null) && isset($contract->coverages[0]->coverType)) {

                foreach (($response->discounts->otherDiscounts ?? []) as $other_discounts) {
                    $other_discounts = json_decode(json_encode($other_discounts), true);
                    if ($other_discounts['discountType'] == 'NCB_DISCOUNT') {
                        $ncbDiscount = (int) str_replace('INR ', '', $other_discounts['discountAmount']);
                    }
                }
                foreach ($contract->coverages as $coverage) {
                    $coverage = json_decode(json_encode($coverage), true);
                    if ($coverage['coverType'] == 'THIRD_PARTY') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Property Damage') {
                                $basicTp = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'OWN_DAMAGE') {
                        $od = round((float) str_replace('INR ', '', $coverage['netPremium']), 2);
                    } elseif ($coverage['coverType'] == 'PA_OWNER') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Personal Accident' && $subcover['selection']) {
                                $cover_pa_owner_driver_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'ADDONS') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Breakdown Assistance') {
                                $rsa = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Tyre Protect') {
                                $tyreSecure = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Parts Depreciation Protect') {
                                $zeroDep = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Consumable cover') {
                                $consumables = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Engine and Gear Box Protect') {
                                $engineProtect = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Return to Invoice') {
                                $rti = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Rim Protect Cover') {
                                $ncb_protection = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Personal Belonging') {
                                $lopb = (float) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'Key and Lock Protect') {
                                $keyReplacement = (float) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'PA_UNNAMED') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'PA cover for Unnamed Passenger - IMT 16' && $subcover['selection']) {
                                $cover_pa_unnamed_passenger_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            } elseif ($subcover['name'] == 'PA cover for Paid Driver - IMT 17' && $subcover['selection']) {
                                $cover_pa_paid_driver_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    } elseif ($coverage['coverType'] == 'LEGAL_LIABILITY') {
                        foreach ($coverage['subCovers'] as $subcover) {
                            if ($subcover['name'] == 'Legal Liability to Paid Driver - IMT 28' && $subcover['selection']) {
                                $llpaiddriver_premium = (int) str_replace('INR ', '', $subcover['netPremium']);
                            }
                        }
                    }
                }

                $mmv = get_mmv_details(json_decode(json_encode(['product_sub_type_id' => 1])), $requestData->version_id,'godigit');
                $mmv = (object) array_change_key_case((array) $mmv['data'],CASE_LOWER);

                if (in_array($mmv->fuel_type, ['CNG', 'PETROL+CNG', 'DIESEL+CNG', 'LPG'])) {
                    $cng_lpg_tp = $isOwnDamage ? 0 : (($requestData->business_type == 'newbusiness') ? 180 : 60);
                    $basicTp = $basicTp - $cng_lpg_tp;
                }
            } else {
                foreach (($contract->coverages ?? []) as $key => $value) {
                    switch ($key) {
                        case 'thirdPartyLiability':
                            $is_tppd = $value->isTPPD ?? false;
                            $basicTp = !empty($value->netPremium ?? null) ? round(str_replace("INR ", "", $value->netPremium), 2) : 0;
                            break;
                        
                            case 'addons':
                                foreach ($value as $key => $addon) {
                                    if (isset($addon->selection) && $addon->selection == 'true' && in_array($addon->coverAvailability, [
                                        'AVAILABLE',
                                        'MANDATORY'
                                    ])) {
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
                                    if ($cover == "paidDriverLL" && $subcover->selection == 1) {
                                        $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium), 2);
                                    } elseif ($cover == 'employeesLL' && $subcover->selection) {
                                        $llPadiEmployee = round(str_replace("INR ", "", $subcover->netPremium), 2);
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

                $mmv = get_mmv_details(json_decode(json_encode(['product_sub_type_id' => 1])), $requestData->version_id, 'godigit');
                if (!$isLPG) {
                    $isLPG = !empty($mmv['data']) && in_array($mmv['data']['fuel_type'], ['CNG', 'LPG']);
                }
                if ($isLPG && !$isOwnDamage) {
                    $cng_lpg_tp = $requestData->business_type == 'newbusiness' ? 180 : 60;
                    $basicTp = $basicTp - $cng_lpg_tp;
                }
            }

            $netPremium  = str_replace("INR ", "", $response->netPremium);
            $finalPayable = str_replace("INR ", "", $response->grossPremium);
            $serviceTax = str_replace("INR ", "", $response->serviceTax->totalTax);

            $tppd_discount = $is_tppd ? ($requestData->business_type == 'newbusiness' ? 300 : 100) : 0;
            $final_total_discount = $ncbDiscount;
            $final_od_premium = $od;
            $addon_premium = $zeroDep + $rsa + $engineProtect + $tyreSecure + $rti + $consumables + $lopb + $keyReplacement + $ncb_protection;
            $final_tp_premium = $basicTp + $cng_lpg_tp + $llPadiEmployee + $llpaiddriver_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_owner_driver_premium;

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
                "ncb_protection" => $ncb_protection,
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
                "ll_paid_employee" => $llPadiEmployee,
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
