<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use App\Http\Controllers\Proposal\Services\Car\iffco_tokioSubmitProposal;
use Mtownsend\XmlToArray\XmlToArray;

class IffcoTokioPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        // $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
        // $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

        $methodList = [
            'Quote Calculation - Proposal',
            'Quote Calculation - Proposal Submit',
            getGenericMethodName('Quote Calculation - Proposal', 'proposal'),
            getGenericMethodName('Quote Calculation - Proposal Submit', 'proposal'),
        ];

        $methodList = array_unique($methodList);
        $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id', 'request')
        ->where([
            'enquiry_id' => $enquiryId,
            'company' => 'iffco_tokio'
        ])
        ->whereIn('method_name', $methodList)
        ->orderBy('id', 'desc')
        ->get()
        ->toArray();

        $webserviceId = null;
        $isXml = false;
        foreach ($logs as $log) {
            $response = $log['response'];
            try {
                $response = XmlToArray::convert((string) $response);
            } catch (\Throwable $th) {
                $response = json_decode($response, true);
            }
            if (!empty($response['premiumPayble'])) {
                $webserviceId = $log['id'];
                break;
            } elseif (
                !empty($response['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn']['premiumPayable'] ??
                    $response['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'][0]['premiumPayable'] ??
                    $response['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'][1]['premiumPayable'] ?? null)
            ) {
                $webserviceId = $log['id'];
                $isXml = true;
                break;
            }
        }

        if (!empty($webserviceId)) {
            if ($isXml) {
                return self::savePremiumDetails($webserviceId);
            }
            return self::saveShortTermPremiumDetails($webserviceId);
        } else {
            return [
                'status' => false,
                'message' => 'Valid Proposal log not found',
            ];
        }
    }

    public static function saveShortTermPremiumDetails($webserviceId)
    {
        try {
            $log = WebServiceRequestResponse::select('response', 'enquiry_id', 'request')->find($webserviceId);
            $enquiryId = $log->enquiry_id;
            $response = json_decode($log->response, true);
            $addOnPremium = ($response['nilDep'] ?? 0) + ($response['consumablePrem'] ?? 0);
            $finalOdPremium = $addOnPremium + ($response['odPremium'] ?? 0);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $response['basicOD'] ?? 0,
                "loading_amount" => 0,
                "final_od_premium" => $finalOdPremium,
                // TP Tags
                "basic_tp_premium" => $response['basicTP'] ?? 0,
                "final_tp_premium" => $response['tpPremium'] ?? 0,
                // Accessories
                "electric_accessories_value" => $response['electricalOD'] ?? 0,
                "non_electric_accessories_value" => 0,
                "bifuel_od_premium" => $response['cngOD'] ?? 0,
                "bifuel_tp_premium" => $response['cngTP'] ?? 0,
                // Addons
                "compulsory_pa_own_driver" => $response['paOwnerDriverTP'] ?? 0,
                "zero_depreciation" => $response['nilDep'] ?? 0,
                "road_side_assistance" => 0,
                "imt_23" => 0,
                "consumable" => $response['consumablePrem'] ?? 0,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $response['paPassengerTP'] ?? 0,
                "ll_paid_driver" => $response['llDriverTP'] ?? 0,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "ll_paid_employee" => $response['llToEmp'] ?? 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => abs($response['antiTheftDisc'] ?? 0),
                "voluntary_excess" => abs($response['voluntaryExcessDisc'] ?? 0),
                "tppd_discount" => abs($response['tppdDiscount'] ?? 0),
                "other_discount" => abs($response['premiumDiscount'] ?? 0),
                "ncb_discount_premium" => abs($response['ncb'] ?? 0),
                // Final tags
                "net_premium" => $response['grossPremiumAfterDiscount'] ?? 0,
                "service_tax_amount" => $response['serviceTax'] ?? 0,
                "final_payable_amount" => $response['premiumPayble'] ?? 0,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? abs(round($value, 2)) : 0;
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

    public static function savePremiumDetails($webserviceId)
    {
        try {
            $log = WebServiceRequestResponse::select('response', 'enquiry_id', 'request')->find($webserviceId);
            $enquiryId = $log->enquiry_id;
            $response = XmlToArray::convert((string)$log->response);
            $request = XmlToArray::convert((string)$log->request);

            $premium_data = $response['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'] ?? [];
            $coverages = $request['soapenv:Body']['getMotorPremium']['policy']['vehicle']['vehicleCoverage']['item'] ?? [];
            $zeroDep = array_search('Depreciation Waiver', array_column($coverages, 'coverageId'));
            $isZeroDep = 'N';
            if ($zeroDep !== false) {
                $isZeroDep = $coverages[$zeroDep]['sumInsured'] ?? 'N';
            }
            $premium_data = ($isZeroDep == 'Y') ? ($premium_data[1] ?? []) : ($premium_data[0] ?? []);

            $ncb_amount = 0;
            $pa_unnamed = 0;
            $voluntary_excess = 0;
            $anti_theft = 0;
            $electric_accessories = 0;
            $non_electric_accessories = 0;
            $cng_od_premium = 0;
            $cng_tp_premium = 0;
            $tppd_discount = 0;
            $pa_owner_driver = 0;
            $legal_liability_paid_driver = 0;
            $dep_value  = 0;
            $towing = 0;
            $consumable_value = 0;
            $ncb_protection_value = 0;
            $voluntary_deductible_od_premium = $voluntary_deductible_tp_premium = 0;
            $od_premium = $tp_premium = $imt23 = 0;

            $coveragePremiumDetail = $premium_data['coveragePremiumDetail'] ?? [];

                foreach ($coveragePremiumDetail as $v) {
                    $coverageName = $v['coverageName'];

                    if (is_array($v['odPremium'])) {
                        $v['odPremium'] = (!empty($v['odPremium']['@value']) ? $v['odPremium']['@value'] : '0');
                    }

                    if (is_array($v['tpPremium'])) {
                        $v['tpPremium'] = (!empty($v['tpPremium']['@value']) ? $v['tpPremium']['@value'] : '0');
                    }

                    if ($coverageName == 'IDV Basic') {
                        $od_premium = $v['odPremium'];
                        $tp_premium = $v['tpPremium'];
                    } elseif ($coverageName == 'No Claim Bonus') {
                        $ncb_amount = abs($v['odPremium']);
                    } elseif ($coverageName == 'PA Owner / Driver') {
                        $pa_owner_driver = $v['tpPremium'];
                    } elseif ($coverageName == 'Depreciation Waiver') {
                        $dep_value = $v['coveragePremium'];
                    } elseif ($coverageName == 'Towing & Related') {
                        $towing = $v['coveragePremium'];
                    } elseif ($coverageName == 'PA to Passenger') {
                        $pa_unnamed = $v['tpPremium'];
                    } elseif ($coverageName == 'Voluntary Excess') {
                        $voluntary_excess = abs($v['odPremium'] + $v['tpPremium']);
                        $voluntary_deductible_od_premium = abs($v['odPremium']);
                        $voluntary_deductible_tp_premium = abs($v['tpPremium']);
                    } elseif ($coverageName == "TPPD") {
                        $tppd_discount = intval($v['tpPremium']) == 1 ? 0 : abs($v['tpPremium']);
                    } elseif ($coverageName == 'Legal Liability to Driver') {
                        $legal_liability_paid_driver = (abs($v['tpPremium']));
                    } elseif ($coverageName == 'Electrical Accessories') {
                        $electric_accessories = ($v['odPremium']);
                    } elseif ($coverageName == 'Cost of Accessories') {
                        $non_electric_accessories = ($v['odPremium']);
                    } elseif ($coverageName == 'CNG Kit') {
                        $cng_od_premium += ($v['odPremium']);
                        $cng_tp_premium += ($v['tpPremium']);
                    } elseif ($coverageName == 'AAI Discount') {
                        $aai_discount = ($v['odPremium']);
                    } elseif ($coverageName == 'Anti-Theft') {
                        $anti_theft = abs($v['odPremium']);
                    } elseif ($coverageName == 'CNG Kit Company Fit') {
                        $cng_od_premium += ($v['odPremium']);
                        $cng_tp_premium += ($v['tpPremium']);
                    } elseif ($coverageName == 'Consumable') {
                        $consumable_value = $v['coveragePremium'];
                    } elseif ($coverageName == 'NCB Protection') {
                        if (!is_array($v['coveragePremium'])) {
                            $ncb_protection_value = ($v['coveragePremium']);
                        } elseif (!is_array($v['odPremium'])) {
                            $ncb_protection_value = ($v['odPremium']);
                        }
                    } elseif ($coverageName == 'IMT 23'){
                        $imt23 = $v['odPremium'];
                    }
                }

            $total_od_premium = $premium_data['totalODPremium'];
            $total_tp_premium = $premium_data['totalTPPremium'];
            $voluntary_excess = $voluntary_deductible_od_premium + $voluntary_deductible_tp_premium;
            $discount_amount = abs($premium_data['discountLoadingAmt']);
            $service_tax = $premium_data['serviceTax'] ?? $premium_data['gstAmount'] ?? 0;
            $pa_unnamed = intval($pa_unnamed) == 1 ? 0 : $pa_unnamed;
            $ncb_amount = intval($ncb_amount) == 1 ? 0 : $ncb_amount;
            $pa_owner_driver = intval($pa_owner_driver) == 1 ? 0 : $pa_owner_driver;

            $finalPremium = $premium_data['premiumPayable'];
            $od_premium = $od_premium + $discount_amount;
            $total_addon_amount = $dep_value + $towing + $consumable_value + $ncb_protection_value;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od_premium,
                "loading_amount" => 0,
                "final_od_premium" => $total_od_premium + $total_addon_amount,
                // TP Tags
                "basic_tp_premium" => $tp_premium,
                "final_tp_premium" => $total_tp_premium,
                // Accessories
                "electric_accessories_value" => $electric_accessories,
                "non_electric_accessories_value" => $non_electric_accessories,
                "bifuel_od_premium" => $cng_od_premium,
                "bifuel_tp_premium" => $cng_tp_premium,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner_driver,
                "zero_depreciation" => $dep_value,
                "road_side_assistance" => $towing,
                "imt_23" => $imt23,
                "consumable" => $consumable_value,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => $ncb_protection_value,
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $legal_liability_paid_driver,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $discount_amount,
                "ncb_discount_premium" => $ncb_amount,
                // Final tags
                "net_premium" => $finalPremium - $service_tax,
                "service_tax_amount" => $service_tax,
                "final_payable_amount" => $finalPremium,
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

    public static function calculateNBPremium($cov)
    {
        return iffco_tokioSubmitProposal::calculateNBPremium($cov);
    }
}
