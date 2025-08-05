<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

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
                    'Proposal Submit Renewal',
                    'Proposal Service',
                    getGenericMethodName('Proposal Service', 'proposal'),
                    getGenericMethodName('Proposal Service Renewal', 'proposal')
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

            $od_premium = $total_add_ons_premium = $tp_premium = $cpa_premium = $Auto_Mobile_Association_Discount = $AntiTheft_Discount = $No_Claim_Bonus_Discount = $Total_Discounts = $add_ons_premium = $Own_Damage_Basic = 0;
            $Third_Party_Basic_Sub_Coverage = $Non_Electrical_Accessories = $Electrical_Accessories = $Legal_Liability_to_Paid_Drivers = $PA_Unnamed_Passenger = $PA_Owner_Driver = 0;
            $Zero_Depreciation = $Engine_Protect = $Return_To_Invoice = $Key_Replacement = $Loss_of_Personal_Belongings = $Protection_of_NCB = $Basic_Road_Assistance = $Consumable_Cover = 0;
            $Tyre_Safeguard = $VoluntaryDeductibleDiscount = $No_Claim_Bonus_Discount = 0;

            if (isset($response['contractDetails'][0])) {
                foreach ($response['contractDetails'] as $sections) {
                    if ($sections['salesProductTemplateId'] == 'Own Damage Contract') {
                        $od_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                        $od_section_array = $sections['coveragePackage'];
                        if (isset($od_section_array['coverage']['subCoverage']['salesProductTemplateId'])) {
                            if ($od_section_array['coverage']['subCoverage']['salesProductTemplateId'] == 'Own Damage Basic') {
                                $Own_Damage_Basic = ($od_section_array['coverage']['subCoverage']['totalPremium']);
                            }
                        } else {
                            foreach (($od_section_array['coverage']['subCoverage'] ?? []) as $subCoverage) {
                                if ($subCoverage['salesProductTemplateId'] == 'Own Damage Basic') {
                                    $Own_Damage_Basic = ($subCoverage['totalPremium']);
                                }

                                if ($subCoverage['salesProductTemplateId'] == 'Non Electrical Accessories') {
                                    $Non_Electrical_Accessories = ($subCoverage['totalPremium']);
                                }

                                if ($subCoverage['salesProductTemplateId'] == 'Electrical Electronic Accessories') {
                                    $Electrical_Accessories = ($subCoverage['totalPremium']);
                                }
                            }
                        }

                        //Discount Section
                        if (!empty($od_section_array['coverage']['coverageSurchargesOrDiscounts'] ?? null)) {
                            $response_discount_array  = $od_section_array['coverage']['coverageSurchargesOrDiscounts'];

                            if (isset($response_discount_array['salesProductTemplateId'])) {
                                if ($response_discount_array['salesProductTemplateId'] == 'No Claim Bonus Discount') {
                                    $No_Claim_Bonus_Discount =  $response_discount_array['totalSurchargeandDiscounts'];
                                }
                                if ($response_discount_array['salesProductTemplateId'] == 'Voluntary Deductible Discount') {
                                    $VoluntaryDeductibleDiscount =  $response_discount_array['totalSurchargeandDiscounts'];
                                }
                            } else {
                                foreach ($od_section_array['coverage']['coverageSurchargesOrDiscounts'] as $subCoverage) {
                                    if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'No Claim Bonus Discount') {
                                        $No_Claim_Bonus_Discount =  $subCoverage['totalSurchargeandDiscounts'];
                                    }
                                    if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'AntiTheft Discount') {
                                        $AntiTheft_Discount =  $subCoverage['totalSurchargeandDiscounts'];
                                    }
                                    if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'Voluntary Deductible Discount') {
                                        $VoluntaryDeductibleDiscount = $subCoverage['totalSurchargeandDiscounts'];
                                    }
                                }
                            }
                        }
                    } else if ($sections['salesProductTemplateId'] == 'Addon Contract') {
                        $add_ons_premium = $sections['contractPremium']['contractPremiumBeforeTax'];
                        $add_ons_section_array = $sections['coveragePackage'];
                        if (isset($add_ons_section_array['coverage']['subCoverage'])) {
                            if (isset($add_ons_section_array['coverage']['subCoverage'][0])) {
                                foreach ($add_ons_section_array['coverage']['subCoverage'] as $subCoverage) {
                                    if ($subCoverage['salesProductTemplateId'] == 'Zero Depreciation') {
                                        $Zero_Depreciation = $subCoverage['totalPremium'];
                                    } else if ($subCoverage['salesProductTemplateId'] == 'Consumable Cover') {
                                        $Consumable_Cover = $subCoverage['totalPremium'];
                                    } else if ($subCoverage['salesProductTemplateId'] == 'Return To Invoice') {
                                        $Return_To_Invoice = $subCoverage['totalPremium'];
                                    }
                                }
                            } else {
                                $subCoverage = $add_ons_section_array['coverage']['subCoverage'];
                                if ($subCoverage['salesProductTemplateId'] == 'Zero Depreciation') {
                                    $Zero_Depreciation = $subCoverage['totalPremium'];
                                } else if ($subCoverage['salesProductTemplateId'] == 'Consumable Cover') {
                                    $Consumable_Cover = $subCoverage['totalPremium'];
                                } else if ($subCoverage['salesProductTemplateId'] == 'Return To Invoice') {
                                    $Return_To_Invoice = $subCoverage['totalPremium'];
                                }
                            }
                        }
                    } else if ($sections['salesProductTemplateId'] == 'Third Party Multiyear Contract') {
                        $TP_section_array = $sections['coveragePackage'];
                        if (isset($TP_section_array['coverage']['subCoverage'][0])) {
                            foreach (($TP_section_array['coverage']['subCoverage'] ?? []) as $subCoverage) {
                                if ($subCoverage['salesProductTemplateId'] == 'Third Party Basic Sub Coverage') {
                                    $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                } else if ($subCoverage['salesProductTemplateId'] == 'Legal Liability to Paid Drivers') {
                                    $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium'];
                                } else if ($subCoverage['salesProductTemplateId'] == 'PA Unnamed Passenger') {
                                    $PA_Unnamed_Passenger =  $subCoverage['totalPremium'];
                                }
                            }
                        } else {
                            $subCoverage = $TP_section_array['coverage']['subCoverage'];
                            if ($subCoverage['salesProductTemplateId'] == 'Third Party Basic Sub Coverage') {
                                $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                            } else if ($subCoverage['salesProductTemplateId'] == 'PA Unnamed Passenger') {
                                $PA_Unnamed_Passenger =  $subCoverage['totalPremium'];
                            }
                        }
                        $tp_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                    } else if ($sections['salesProductTemplateId'] == 'PA Compulsary Contract') {
                        //PA Owner Driver
                        $cpa_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                        $PA_Owner_Driver = $sections['coveragePackage']['coverage']['subCoverage']['totalPremium'] ?? $cpa_premium;
                    }
                }
            } else {
                $sections = $response['contractDetails'];
                if ($sections['salesProductTemplateId'] == 'Own Damage Contract') {
                    $Own_Damage_Basic =  $sections['contractPremium']['contractPremiumBeforeTax'];
                } else if ($sections['salesProductTemplateId'] == 'Addon Contract') {
                    $add_ons_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                } else if ($sections['salesProductTemplateId'] == 'Third Party Multiyear Contract') {
                    $Third_Party_Basic_Sub_Coverage =  $sections['contractPremium']['contractPremiumBeforeTax'];
                } else if ($sections['salesProductTemplateId'] == 'PA Compulsary Contract') {
                    //PA Owner Driver
                    $cpa_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                }
            }

            if (isset($response['contractDetails'][0])) {

                if (isset($response['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'][0])) {
                    foreach ($response['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'] as $sections) {
                        if ($sections['salesProductTemplateId'] == 'Voluntary Deductible Discount') {
                            $Total_Discounts = $Auto_Mobile_Association_Discount =  $sections['amount'];
                        } else if ($sections['salesProductTemplateId'] == 'No Claim Bonus Discount') {

                            $Total_Discounts += $No_Claim_Bonus_Discount =  $sections['amount'];
                        } else if ($sections['salesProductTemplateId'] == 'AntiTheft Discount') {

                            $Total_Discounts += $AntiTheft_Discount =  $sections['amount'];
                        }
                    }
                } else {
                    if (!empty($response['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'])) {
                        $section = $response['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'];

                        if ($section['salesProductTemplateId'] == 'Voluntary Deductible Discount') {

                            $Total_Discounts = $Auto_Mobile_Association_Discount =  $section['amount'];
                        } else  if ($section['salesProductTemplateId'] == 'No Claim Bonus Discount') {

                            $Total_Discounts += $No_Claim_Bonus_Discount =  $section['amount'];
                        } else if ($sections['salesProductTemplateId'] == 'AntiTheft Discount') {

                            $Total_Discounts += $AntiTheft_Discount =  $sections['amount'];
                        }
                    }
                }
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
                "loss_of_personal_belongings" => $Loss_of_Personal_Belongings,
            ];
            $addon_premium = array_sum($addons);

            $final_od_premium = ($response['premiumDetails']['totalODPremium'] ?? 0) + $addon_premium;
            $final_tp_premium = ($response['premiumDetails']['totalTPPremium'] ?? 0) + $cpa_premium;

            $net_premium = round(($final_od_premium + $final_tp_premium), 2);
            $final_gst_amount   = round(($net_premium * 0.18), 2);
            $final_payable_amount  = round(($net_premium + $final_gst_amount), 2);

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
                "bifuel_od_premium" => 0,
                "bifuel_tp_premium" => 0,
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
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $PA_Unnamed_Passenger,
                "ll_paid_driver" => $Legal_Liability_to_Paid_Drivers,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $AntiTheft_Discount,
                "voluntary_excess" => $VoluntaryDeductibleDiscount,
                "tppd_discount" => 0,
                "other_discount" => 0,
                "ncb_discount_premium" => $No_Claim_Bonus_Discount,
                // Final tags
                "net_premium" => $response['premiumDetails']['netTotalPremium'] ?? $net_premium,
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
