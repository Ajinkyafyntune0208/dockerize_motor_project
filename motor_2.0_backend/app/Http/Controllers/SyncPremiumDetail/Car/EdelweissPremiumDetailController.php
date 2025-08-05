<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class EdelweissPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Service',
                    getGenericMethodName('Proposal Service', 'proposal')
                ];
            } else {
                $methodList = [
                    'Proposal Service',
                    getGenericMethodName('Proposal Service', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'edelweiss'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (isset($response['premiumDetails']['grossTotalPremium'])) {
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
            $response = json_decode($response->response, true);

            $requestData = getQuotation($enquiryId);
            $new_business       = (($requestData->business_type == 'newbusiness') ? true : false);
            $Own_Damage_Basic = $Third_Party_Basic_Sub_Coverage = 0;
            $Electrical_Accessories = $Non_Electrical_Accessories = 0;
            $CNG_LPG_Kit_Own_Damage = $CNG_LPG_Kit_Liability = 0;
            $PA_Owner_Driver = $motor_additional_paid_driver = 0;
            $Legal_Liability_to_Paid_Drivers = $PA_Unnamed_Passenger = 0;
            $Return_To_Invoice = $Tyre_Safeguard = $Zero_Depreciation = $Basic_Road_Assistance = 0;
            $Consumable_Cover = $Key_Replacement = $Engine_Protect = $Protection_of_NCB = 0;
            $Loss_of_Personal_Belongings = $Waiver_of_Policy = 0;

            $Total_Discounts = $total_add_ons_premium = $total_TP_Amount = $llPaidEmployee = 0;

            $AntiTheft_Discount = $tppd_discount = $No_Claim_Bonus_Discount = $Auto_Mobile_Association_Discount = 0;

            if (isset($response['contractDetails'][0])) {
                foreach ($response['contractDetails'] as $sections) {
                    $templateid = $sections['salesProductTemplateId'];

                    switch ($templateid) {
                        case 'MOCNMF00': //od Section
                            $od_section_array = $sections;
                            if (isset($od_section_array['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId'])) {
                                if ($od_section_array['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF00') {
                                    $Own_Damage_Basic =  ($od_section_array['coveragePackage']['coverage']['subCoverage']['totalPremium']);
                                }
                            } else {
                                foreach ($od_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) {
                                    if ($subCoverage['salesProductTemplateId'] == 'MOSCMF00') {
                                        $Own_Damage_Basic =  ($subCoverage['totalPremium']);
                                    } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF01') {
                                        $Non_Electrical_Accessories =  ($subCoverage['totalPremium']);
                                    } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF02') {
                                        $Electrical_Accessories =  ($subCoverage['totalPremium']);
                                    } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF03') {
                                        $CNG_LPG_Kit_Own_Damage =  ($subCoverage['totalPremium']);
                                    }
                                }
                            }

                            //Discount Section  

                            if (isset($od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'])) {
                                $response_discount_array  = $od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'];

                                if (isset($response_discount_array['salesProductTemplateId'])) {
                                    if ($response_discount_array['salesProductTemplateId'] == 'MOSDMFB1') {
                                        $Total_Discounts += $Auto_Mobile_Association_Discount =  $response_discount_array['amount'];
                                    } elseif ($response_discount_array['salesProductTemplateId'] == 'MOSDMFB2') {
                                        $Total_Discounts += $AntiTheft_Discount =  $response_discount_array['amount'];
                                    } elseif ($response_discount_array['salesProductTemplateId'] == 'MOSDMFB7') {
                                        $Total_Discounts += $No_Claim_Bonus_Discount =  $response_discount_array['amount'];
                                    }
                                } else {
                                    foreach ($od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'] as $subCoverage) {
                                        if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB1') {
                                            $Total_Discounts += $Auto_Mobile_Association_Discount =  $subCoverage['amount'];
                                        } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB2') {
                                            $Total_Discounts += $AntiTheft_Discount =  $subCoverage['amount'];
                                        } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB7') {
                                            $Total_Discounts += $No_Claim_Bonus_Discount =  $subCoverage['amount'];
                                        }
                                    }
                                }
                            }

                            break;

                        case 'MOCNMF01': //addon Section
                            $add_ons_section_array = $sections;
                            if (isset($add_ons_section_array['coveragePackage']['coverage']['subCoverage'][0])) {
                                foreach ($add_ons_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) {
                                    if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF06') {
                                        $total_add_ons_premium += $Tyre_Safeguard =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF07') {
                                        $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF08') {
                                        $total_add_ons_premium += $Engine_Protect =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF09') {
                                        $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF10') {
                                        $total_add_ons_premium += $Key_Replacement =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF11') {
                                        $total_add_ons_premium += $Loss_of_Personal_Belongings =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF12') {
                                        $total_add_ons_premium += $Protection_of_NCB =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF13') {
                                        $total_add_ons_premium += $Basic_Road_Assistance =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF15') {
                                        $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium'];
                                    } elseif (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF16') {
                                        $Waiver_of_Policy =  $subCoverage['totalPremium'];
                                    }
                                }
                            } else {
                                $subCoverage = $add_ons_section_array['coveragePackage']['coverage']['subCoverage'];
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF06') {
                                    $total_add_ons_premium += $Tyre_Safeguard =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF07') {
                                    $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF08') {
                                    $total_add_ons_premium += $Engine_Protect =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF09') {
                                    $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF10') {
                                    $total_add_ons_premium += $Key_Replacement =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF11') {
                                    $total_add_ons_premium += $Loss_of_Personal_Belongings =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF12') {
                                    $total_add_ons_premium += $Protection_of_NCB =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF13') {
                                    $total_add_ons_premium += $Basic_Road_Assistance =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF15') {
                                    $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium'];
                                }
                                if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF16') {
                                    $Waiver_of_Policy =  $subCoverage['totalPremium'];
                                }
                            }
                            break;

                        case 'MOCNMF02': //Third Party Section
                            $TP_section_array = $sections;
                            if (isset($sections['coveragePackage']['coverage']['subCoverage'][0])) {
                                foreach ($TP_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) {
                                    if ($subCoverage['salesProductTemplateId'] == 'MOSCMF25') {
                                        $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                    } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF17') {
                                        $total_TP_Amount += $CNG_LPG_Kit_Liability =  $subCoverage['totalPremium'];
                                    } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF20') {
                                        $total_TP_Amount += $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium'];
                                    } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF24') {
                                        $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium'];
                                    } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF27') {
                                        $total_TP_Amount += $motor_additional_paid_driver =  $subCoverage['totalPremium'];
                                    } elseif($subCoverage['salesProductTemplateId'] == 'MOSCMF19') {
                                        $total_TP_Amount += $llPaidEmployee =  $subCoverage['totalPremium']; 
                                    }
                                }
                            } else {
                                $subCoverage = $sections['coveragePackage']['coverage']['subCoverage'];
                                if ($subCoverage['salesProductTemplateId'] == 'MOSCMF25') {
                                    $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF17') {
                                    $total_TP_Amount += $CNG_LPG_Kit_Liability =  $subCoverage['totalPremium'];
                                } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF20') {
                                    $total_TP_Amount += $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium'];
                                } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF24') {
                                    $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium'];
                                } elseif ($subCoverage['salesProductTemplateId'] == 'MOSCMF27') {
                                    $total_TP_Amount += $motor_additional_paid_driver =  $subCoverage['totalPremium'];
                                }
                            }
                            if (isset($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'])) {
                                if (isset($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'][0])) {
                                    foreach ($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'] as $subCoverageDiscount) {
                                        if ($subCoverageDiscount['salesProductTemplateId'] == 'MOSDMFB9') {
                                            $Total_Discounts += $tppd_discount =  ($new_business ? $subCoverageDiscount['totalSurchargeandDiscounts'] : $subCoverageDiscount['amount']);
                                        }
                                    }
                                } else {

                                    if ($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']['salesProductTemplateId'] == 'MOSDMFB9') {
                                        $Total_Discounts += $tppd_discount =  ($new_business ? $TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']['totalSurchargeandDiscounts'] : $TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']['amount']);
                                    }
                                }
                            }
                            break;

                        case 'MOCNMF03': //PA Owner Driver
                            if (isset($sections['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId']) && $sections['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF26') {
                                $PA_Owner_Driver = $sections['coveragePackage']['coverage']['subCoverage']['totalPremium'];
                            }
                            break;
                    }
                }
            } else {
                $sections = $response['contractDetails'];
                if ($sections['salesProductTemplateId'] == 'MOCNMF00') {
                    $Own_Damage_Basic =  $sections['contractPremium']['contractPremiumBeforeTax'];
                } elseif ($sections['salesProductTemplateId'] == 'MOCNMF01') {
                    $total_add_ons_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                } elseif ($sections['salesProductTemplateId'] == 'MOCNMF02') {
                    $Third_Party_Basic_Sub_Coverage =  $sections['contractPremium']['contractPremiumBeforeTax'];
                } elseif ($sections['salesProductTemplateId'] == 'MOCNMF03') {
                    //PA Owner Driver
                    $PA_Owner_Driver =  $sections['contractPremium']['contractPremiumBeforeTax'];
                }
            }

            if (($response['contractDetails'][0]['coveragePackage']['coverage']['subCoverage'][1]['salesProductTemplateId'] ?? '') == 'MOSCMF04') {
                $CNG_LPG_Kit_Own_Damage += $response['contractDetails'][0]['coveragePackage']['coverage']['subCoverage'][1]['totalPremium'] ?? 0;
            }

            $addons = [
                "zero_depreciation" => $Zero_Depreciation,
                "road_side_assistance" => $Basic_Road_Assistance,
                "imt_23" => 0,
                "consumable" => $Consumable_Cover,
                "key_replacement" => $Key_Replacement,
                "engine_protector" => $Engine_Protect,
                "ncb_protection" => $Protection_of_NCB,
                "tyre_secure" => $Tyre_Safeguard,
                "return_to_invoice" => $Return_To_Invoice,
                "loss_of_personal_belongings" => $Loss_of_Personal_Belongings
            ];
            $addon_premium = array_sum($addons);
            
            $final_od_premium = ($response['premiumDetails']['totalODPremium'] ?? 0) + $addon_premium;
            $final_tp_premium = ($response['premiumDetails']['totalTPPremium'] ?? 0) + $PA_Owner_Driver;

            $final_net_premium = $final_od_premium + $final_tp_premium;
            $final_gst_amount   = $final_net_premium * 0.18;
            $final_payable_amount  = round(($final_net_premium + $final_gst_amount), 2);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $Own_Damage_Basic,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $Third_Party_Basic_Sub_Coverage,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $Electrical_Accessories,
                "non_electric_accessories_value" => $Non_Electrical_Accessories,
                "bifuel_od_premium" => $CNG_LPG_Kit_Own_Damage,
                "bifuel_tp_premium" => $CNG_LPG_Kit_Liability,
                // Addons
                "compulsory_pa_own_driver" => $PA_Owner_Driver,
                "zero_depreciation" => $Zero_Depreciation,
                "road_side_assistance" => $Basic_Road_Assistance,
                "imt_23" => 0,
                "consumable" => $Consumable_Cover,
                "key_replacement" => $Key_Replacement,
                "engine_protector" => $Engine_Protect,
                "ncb_protection" => $Protection_of_NCB,
                "tyre_secure" => $Tyre_Safeguard,
                "return_to_invoice" => $Return_To_Invoice,
                "loss_of_personal_belongings" => $Loss_of_Personal_Belongings,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $motor_additional_paid_driver,
                "unnamed_passenger_pa_cover" => $PA_Unnamed_Passenger,
                "ll_paid_driver" => $Legal_Liability_to_Paid_Drivers,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "ll_paid_employee" => $llPaidEmployee,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $AntiTheft_Discount,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $No_Claim_Bonus_Discount,
                // Final tags
                "net_premium" => $response['premiumDetails']['netTotalPremium'] ?? $final_net_premium,
                "service_tax_amount" => $response['premiumDetails']['gst'] ?? $final_gst_amount,
                "final_payable_amount" => $response['premiumDetails']['grossTotalPremium'] ?? $final_payable_amount,
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
