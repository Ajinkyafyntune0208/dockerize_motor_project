<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class ReliancePremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $methodList = [
                'Proposal Creation',
                getGenericMethodName('Proposal Creation', 'proposal')
            ];
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Creation',
                    getGenericMethodName('Proposal Creation', 'proposal')
                ];
            }
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'reliance'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response);
                if (!empty($response) && ($response->MotorPolicy->status ?? '') == '1') {
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
            $response = json_decode($response->response);

            $motorPolicy = $response->MotorPolicy;

            $motorPolicy->lstPricingResponse = is_object($motorPolicy->lstPricingResponse) ? [$motorPolicy->lstPricingResponse] : $motorPolicy->lstPricingResponse;
            $inspection_charges = !empty((int) $motorPolicy->InspectionCharges) ? (int) $motorPolicy->InspectionCharges : 0;

            $basic_od = 0;
            $tppd = 0;
            $pa_owner = 0;
            $pa_unnamed = 0;
            $pa_paid_driver = 0;
            $electrical_accessories = 0;
            $non_electrical_accessories = 0;
            $zero_dep_amount = 0;
            $ncb_discount = 0;
            $lpg_cng = 0;
            $lpg_cng_tp = 0;
            $automobile_association = 0;
            $anti_theft = 0;
            $liabilities = 0;
            $voluntary_deductible = 0;
            $tppd_discount = 0;
            $other_discount = 0;
            $idv = $motorPolicy->IDV;
            $RTIAddonPremium = 0;
            $basic_own_damage = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $rsa = 0;
            foreach ($motorPolicy->lstPricingResponse as $k => $v) {
                $value = round(trim(str_replace('-', '', (int) ($v->Premium ?? 0))), 2);
                if ($v->CoverageName == 'Basic OD') {
                    $basic_own_damage = $v->Premium + $inspection_charges;
                } elseif ($v->CoverageName == 'Total OD and Addon') {
                    $basic_od = $value;
                } elseif (($v->CoverageName == 'Nil Depreciation')) {
                    $zero_dep_amount = $value;
                } elseif ($v->CoverageName == 'Bifuel Kit') {
                    $lpg_cng = $value;
                } elseif ($v->CoverageName == 'Electrical Accessories') {
                    $electrical_accessories = $value;
                } elseif ($v->CoverageName == 'Non Electrical Accessories') {
                    $non_electrical_accessories = $value;
                } elseif ($v->CoverageName == 'NCB') {
                    $ncb_discount = $value;
                } elseif ($v->CoverageName == 'Basic Liability') {
                    $tppd = round(abs((int) $value), 2);
                } elseif ($v->CoverageName == 'PA to Unnamed Passenger') {
                    $pa_unnamed = $value;
                } elseif ($v->CoverageName == 'PA to Owner Driver') {
                    $pa_owner = $value;
                } elseif ($v->CoverageName == 'PA to Paid Driver') {
                    $pa_paid_driver = $value;
                } elseif ($v->CoverageName == 'Liability to Paid Driver') {
                    $liabilities = $value;
                } elseif ($v->CoverageName == 'Bifuel Kit TP') {
                    $lpg_cng_tp = $value;
                } elseif ($v->CoverageName == 'Automobile Association Membership') {
                    $automobile_association = round(abs($value), 2);
                } elseif ($v->CoverageName == 'Anti-Theft Device') {
                    $anti_theft = abs($value);
                } elseif ($v->CoverageName == 'Voluntary Deductible') {
                    $voluntary_deductible = abs($value);
                } elseif ($v->CoverageName == 'TPPD') {
                    $tppd_discount = abs($value);
                } elseif ($v->CoverageName == 'OD Discount') {
                    $other_discount = abs($value);
                } elseif (in_array($v->CoverageName, ['Return to Invoice', 'Return to invoice'])) {
                    $RTIAddonPremium = $value;
                } elseif (in_array($v->CoverageName, ['Assistance Cover (Two Wheeler Shield)'])) {
                    $rsa = $value;
                }
                unset($value);
            }

            $service_tax = 0;
            foreach ($motorPolicy->LstTaxComponentDetails as $k => $v) {
                if ($k == 'TaxComponent') {
                    if (is_array($v)) {
                        foreach ($v as $taxComponent) {
                            $service_tax += (int) $taxComponent->Amount;
                        }
                    } else {
                        $service_tax += (int) $v->Amount;
                    }
                }
            }

            $discountForLoadingCalc = $ncb_discount + $anti_theft + $voluntary_deductible + $other_discount;
            $checkValue = $basic_own_damage + $other_discount;
            $checkvalue = round(($checkValue - $discountForLoadingCalc), 2);
            $loadingAmount = 0;

            //non tp case
            if(!empty($basic_own_damage) && $checkvalue < 100 && $motorPolicy->TotalODPremium <= 100) {
                $loadingAmount = 100 - $checkvalue;
            }

            $NetPremium = $motorPolicy->NetPremium;
            $final_payable_amount = $motorPolicy->FinalPremium;
            $total_tp_amount = $tppd + $liabilities + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $pa_owner - $tppd_discount;


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basic_own_damage,
                "loading_amount" => $loadingAmount,
                "final_od_premium" => $basic_od,
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $total_tp_amount,
                // Accessories
                "electric_accessories_value" => $electrical_accessories,
                "non_electric_accessories_value" => $non_electrical_accessories,
                "bifuel_od_premium" => $lpg_cng,
                "bifuel_tp_premium" => $lpg_cng_tp,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => $rsa,
                "imt_23" => 0,
                "consumable" => 0,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => $RTIAddonPremium,
                "loss_of_personal_belongings" => 0,
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
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_deductible,
                "tppd_discount" => $tppd_discount,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $NetPremium,
                "service_tax_amount" => $final_payable_amount - $NetPremium,
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
