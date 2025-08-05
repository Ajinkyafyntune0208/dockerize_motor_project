<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class SbiPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Submit',
                    getGenericMethodName('Proposal Submit', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Proposal Submit',
                    getGenericMethodName('Proposal Submit', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'sbi'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (!empty($response['PolicyObject']['DuePremium'] ?? '')) {
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
            $request = json_decode($logs->request, true);
            $requestData = getQuotation($enquiryId);

            
            $is_anti_theft = ($request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['AntiTheftAlarmSystem'] ?? '');
            $tp_only = search_for_id_sbi('C101065', $request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0] ?? false;
            $is_tppd = false;
            if ($tp_only !== false) {
                $is_tppd = array_search('B00009', array_column($request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'], 'ProductElementCode'));
                $is_tppd = $is_tppd !== false;
            }


            $Own_Damage_Basic = 0;
            $Non_Electrical_Accessories = 0;
            $Electrical_Accessories = 0;
            $LPG_CNG_Cover = 0;
            $Inbuilt_LPG_CNG_Cover = 0;
            $Trailer_OD = 0;
            $Road_Side_Assistance = 0;
            $Add_Road_Side_Assistance = 0;
            $Third_Party_Bodily_Injury = 0;
            $Third_Party_Property_Damage = 0;
            $CNG_LPG_Liability = 0;
            $Trailer_TP = 0;
            $Legal_Liability_Employees = 0;
            $Legal_Liability_Paid_Drivers = 0;
            $Legal_Liability_Defence = 0;
            $PA_Owner_Driver = 0;
            $PA_Unnamed_Passenger = 0;
            $PA_Paid_Driver = 0;
            $EN_PA_Owner_Driver = 0;
            $EN_PA_Unnamed_Passenger = 0;
            $EN_PA_Paid_Driver = 0;
            $HCC_Owner_Driver = 0;
            $HCC_Unnamed_Passenger = 0;
            $HCC_Paid_Driver = 0;
            $Depreciation_Reimbursement = 0;
            $Return_to_Invoice = 0;
            $Protection_NCB = 0;
            $Key_Replacement_Cover = 0;
            $Inconvience_Allowance = 0;
            $Loss_Personal_Belongings = 0;
            $Engine_Guard = 0;
            $ncb_discount = 0;
            $Tyre_Guard = 0;
            $Consumables = 0;
            $anti_theft = 0;
            $OD_BasePremium = 0;
            $voluntary_excess = $arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['VolDeductDiscAmt'] ?? 0;
            $tppd_discount = ($is_tppd) ? (($requestData->business_type == 'newbusiness') ? 250 : 50) : 0;
            $array_cover = $arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];
            foreach ($array_cover as $key => $cover) {
                if ($cover['ProductElementCode'] == 'C101064') {
                    foreach ($cover['PolicyBenefitList'] as $key => $ODcover) {
                        if ($ODcover['ProductElementCode'] == 'B00002') {
                            $Own_Damage_Basic = $ODcover['GrossPremium'] + ($cover['LoadingAmount'] ?? 0);
                        } elseif ($ODcover['ProductElementCode'] == 'B00003') {
                            $Non_Electrical_Accessories = $ODcover['GrossPremium'];
                        } elseif ($ODcover['ProductElementCode'] == 'B00004') {
                            $Electrical_Accessories = $ODcover['GrossPremium'];
                        } elseif ($ODcover['ProductElementCode'] == 'B00005') {
                            $LPG_CNG_Cover = $ODcover['GrossPremium'];
                        } elseif ($ODcover['ProductElementCode'] == 'B00006') {
                            $Inbuilt_LPG_CNG_Cover = $ODcover['GrossPremium'];
                        }
                    }
                } elseif ($cover['ProductElementCode'] == 'C101069') {
                    $Road_Side_Assistance = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101065') {
                    foreach ($cover['PolicyBenefitList'] as $key => $LLTPcover) {
                        if ($LLTPcover['ProductElementCode'] == 'B00008') {
                            $Third_Party_Bodily_Injury = $LLTPcover['GrossPremium'];
                        } elseif ($LLTPcover['ProductElementCode'] == 'B00009') {
                            $Third_Party_Property_Damage = $LLTPcover['GrossPremium'];
                        } elseif ($LLTPcover['ProductElementCode'] == 'B00010') {
                            $CNG_LPG_Liability = $LLTPcover['GrossPremium'];
                        } elseif ($LLTPcover['ProductElementCode'] == 'B00013') {
                            $Legal_Liability_Paid_Drivers = $LLTPcover['GrossPremium'];
                        } elseif ($LLTPcover['ProductElementCode'] == 'B00012') {
                            $Legal_Liability_Employees = $LLTPcover['GrossPremium'];
                        }
                    }
                } elseif ($cover['ProductElementCode'] == 'C101066') {
                    foreach ($cover['PolicyBenefitList'] as $key => $PAcover) {
                        if ($PAcover['ProductElementCode'] == 'B00015') {
                            $PA_Owner_Driver = $PAcover['GrossPremium'];
                        } elseif ($PAcover['ProductElementCode'] == 'B00075') {
                            $PA_Unnamed_Passenger = $PAcover['GrossPremium'];
                        } elseif ($PAcover['ProductElementCode'] == 'B00027') {
                            $PA_Paid_Driver = $PAcover['GrossPremium'];
                        }
                    }
                } elseif ($cover['ProductElementCode'] == 'C101072') {
                    $Depreciation_Reimbursement = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101067') {
                    $Return_to_Invoice = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101068') {
                    $Protection_NCB = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101073') {
                    $Key_Replacement_Cover = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101075') {
                    $Loss_Personal_Belongings = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101108') {
                    $Engine_Guard = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101110') {
                    $Tyre_Guard = $cover['GrossPremium'];
                } elseif ($cover['ProductElementCode'] == 'C101111') {
                    $Consumables = $cover['GrossPremium'];
                }
            }


            $ncb_discount = $arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBDiscountAmt'] ?? 0;
            if ($is_anti_theft == '1') {
                $OD_BasePremium = $arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['OD_BasePremium'];
                $anti_theft = round(($arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['AntiTheftDiscAmt'] ?? 0), 2);

                if ($anti_theft > 500) {
                    $anti_theft = 500; #antitheft max amount is 500 only
                }
            }

            $final_od_premium = $Own_Damage_Basic + $LPG_CNG_Cover + $Electrical_Accessories + $Non_Electrical_Accessories + $Inbuilt_LPG_CNG_Cover;
            $final_tp_premium = $Third_Party_Bodily_Injury + $CNG_LPG_Liability + $Legal_Liability_Paid_Drivers +  $PA_Paid_Driver + $PA_Unnamed_Passenger + $PA_Owner_Driver;
            $addon_dis = $Depreciation_Reimbursement + $Road_Side_Assistance + $Return_to_Invoice + $Protection_NCB + $Key_Replacement_Cover + $Loss_Personal_Belongings + $Engine_Guard + $Consumables + $Tyre_Guard;

            $final_tp_premium = $final_tp_premium - $tppd_discount;
            $total_discount = round($ncb_discount, 2) + $voluntary_excess  + $anti_theft; # $tppd_discount
            $final_od_premium = $final_od_premium - $total_discount;
            $net_premium = round(($final_od_premium + $final_tp_premium + $addon_dis), 2);
            $final_gst_amount   = round(($net_premium * 0.18), 2);
            $final_payable_amount  = round(($arr_premium['PolicyObject']['DuePremium']), 2);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $Own_Damage_Basic,
                "loading_amount" => 0,
                "final_od_premium" => $final_od_premium + $addon_dis,
                // TP Tags
                "basic_tp_premium" => $Third_Party_Bodily_Injury,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $Electrical_Accessories,
                "non_electric_accessories_value" => $Non_Electrical_Accessories,
                "bifuel_od_premium" => $LPG_CNG_Cover + $Inbuilt_LPG_CNG_Cover,
                "bifuel_tp_premium" => $CNG_LPG_Liability,
                // Addons
                "compulsory_pa_own_driver" => $PA_Owner_Driver,
                "zero_depreciation" => $Depreciation_Reimbursement,
                "road_side_assistance" => $Road_Side_Assistance,
                "imt_23" => 0,
                "consumable" => $Consumables,
                "key_replacement" => $Key_Replacement_Cover,
                "engine_protector" => $Engine_Guard,
                "ncb_protection" => $Protection_NCB,
                "tyre_secure" => $Tyre_Guard,
                "return_to_invoice" => $Return_to_Invoice,
                "loss_of_personal_belongings" => $Loss_Personal_Belongings,
                "wind_shield" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $PA_Paid_Driver,
                "unnamed_passenger_pa_cover" => $PA_Unnamed_Passenger,
                "ll_paid_driver" => $Legal_Liability_Paid_Drivers,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "Legal_Liability_Employees" => $Legal_Liability_Employees,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $net_premium,
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
}
