<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Proposal\Services\Bike\iffco_tokioSubmitProposal;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class IffcoTokioPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $methodList = [
                'Premium Calculation',
                getGenericMethodName('Premium Calculation', 'proposal')
            ];
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Premium Calculation',
                    getGenericMethodName('Premium Calculation', 'proposal')
                ];
            }
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
            $requestData = getQuotation($enquiryId);
            foreach ($logs as $log) {
                $response = $log['response'];
                $request = $log['request'];
                try {
                    $response = XmlToArray::convert((string)$response);
                    $request = XmlToArray::convert((string)$request);
                } catch (\Throwable $th) {
                    continue;
                }
                $isZeroDep = 'N';
                if ($requestData->business_type == 'newbusiness') {
                    $premium_data = $response['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn'] ?? [];
                    $coverages = $request['soapenv:Body']['prem:getNewVehiclePremium']['policy']['vehicle']['vehicleCoverage']['item'] ?? [];
                    $zeroDep = array_search('Depreciation Waiver', array_column($coverages, 'coverageId'));
                    if ($zeroDep !== false) {
                        $isZeroDep = $coverages[$zeroDep]['sumInsured'] ?? 'N';
                    }
                    $premium_data = ($isZeroDep == 'Y') ? ($premium_data[1] ?? []) : ($premium_data[0] ?? []);
                    if (!empty($premium_data['premiumPayable'] ?? null)) {
                        $webserviceId = $log['id'];
                        break;
                    }
                } else {
                    $coverages = $request['soapenv:Body']['getMotorPremium']['policy']['vehicle']['vehicleCoverage']['item'] ?? [];
                    $zeroDep = array_search('Depreciation Waiver', array_column($coverages, 'coverageId'));
                    if ($zeroDep !== false) {
                        $isZeroDep = $coverages[$zeroDep]['sumInsured'] ?? 'N';
                    }
                    $ns = ($isZeroDep == 'Y') ? 'ns2:' : 'ns1:';
                    $premium_data = $response['soapenv:Body']['getMotorPremiumResponse'][$ns . 'getMotorPremiumReturn'] ?? [];
                    if (!empty($premium_data[$ns . 'premiumPayable'] ?? null)) {
                        $webserviceId = $log['id'];
                        break;
                    }
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
            $log = WebServiceRequestResponse::select('response', 'enquiry_id', 'request')->find($webserviceId);
            $enquiryId = $log->enquiry_id;
            $response = XmlToArray::convert((string)$log->response);
            $request = XmlToArray::convert((string)$log->request);
            $requestData = getQuotation($enquiryId);

            $isZeroDep = 'N';
            $ns = '';
            if ($requestData->business_type == 'newbusiness') {
                $premium_data = $response['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn'] ?? [];
                $coverages = $request['soapenv:Body']['prem:getNewVehiclePremium']['policy']['vehicle']['vehicleCoverage']['item'] ?? [];
                $zeroDep = array_search('Depreciation Waiver', array_column($coverages, 'coverageId'));
                if ($zeroDep !== false) {
                    $isZeroDep = $coverages[$zeroDep]['sumInsured'] ?? 'N';
                }
                $premium_data = ($isZeroDep == 'Y') ? ($premium_data[1] ?? []) : ($premium_data[0] ?? []);
            } else {
                $coverages = $request['soapenv:Body']['getMotorPremium']['policy']['vehicle']['vehicleCoverage']['item'] ?? [];
                $zeroDep = array_search('Depreciation Waiver', array_column($coverages, 'coverageId'));
                if ($zeroDep !== false) {
                    $isZeroDep = $coverages[$zeroDep]['sumInsured'] ?? 'N';
                }
                $ns = ($isZeroDep == 'Y') ? 'ns2:' : 'ns1:';
                $premium_data = $response['soapenv:Body']['getMotorPremiumResponse'][$ns . 'getMotorPremiumReturn'] ?? [];
            }

            $ncb_amount = 0;
            $pa_unnamed = 0;
            $voluntary_excess = 0;
            $aai_discount = 0;
            $anti_theft = 0;
            $electric_accessories = 0;
            $non_electric_accessories = 0;
            $cng_od_internal = 0;
            $cng_tp_internal = 0;
            $cng_od_premium = 0;
            $cng_tp_premium = 0;
            $tppd_discount = 0;
            $pa_owner_driver = 0;
            $legal_liability_paid_driver = 0;
            $dep_value  = 0;
            $towing = 0;
            $addon_total = 0;
            $consumable_value = 0;
            $ncb_protection_value = 0;
            $voluntary_deductible_od_premium = $voluntary_deductible_tp_premium = 0;
            $od_premium = $tp_premium = 0;

            if ($requestData->business_type == 'newbusiness') {
                $premium_data = $response;
                $premium_data = $premium_data['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn'];
                $premium_data = ($isZeroDep == 'Y') ? $premium_data[1] : $premium_data[0];

                $coveragepremiumdetail = $premium_data['inscoverageResponse']['coverageResponse']['coverageResponse'];

                foreach ($coveragepremiumdetail as $k => $v) {
                    $coverage_name = $v['coverageCode'];
                    if ($coverage_name == 'IDV Basic') {
                        $prm = self::calculateNBPremium(($v));
                        $od_premium = round($prm['od'], 2);
                        $tp_premium = round($prm['tp'], 2);
                    } else if ($coverage_name == 'PA Owner / Driver') {
                        $prm = self::calculateNBPremium(($v));
                        $pa_owner_driver = $prm['tp'];
                    } else if ($coverage_name == 'Depreciation Waiver') {
                        $prm = self::calculateNBPremium(($v));
                        $dep_value = round($prm['od'], 2);
                    } else if ($coverage_name == 'Towing & Related') {
                        $prm = self::calculateNBPremium(($v));
                        $towing = round($prm['od'], 2);
                    } else if ($coverage_name == 'PA to Passenger') {
                        $prm = self::calculateNBPremium(($v));
                        $pa_unnamed = $prm['tp'];
                    } else if ($coverage_name == 'Voluntary Excess') {
                        $prm = self::calculateNBPremium(($v));
                        $voluntary_excess = abs($prm['od'] + $prm['tp']);
                        $voluntary_deductible_od_premium = abs($prm['od']);
                        $voluntary_deductible_tp_premium = abs($prm['tp']);
                    } else if ($coverage_name == "TPPD") {
                        $prm = self::calculateNBPremium(($v));
                        $tppd_discount = abs($prm['tp']);
                    } else if ($coverage_name == 'Legal Liability to Driver') {
                        $prm = self::calculateNBPremium(($v));
                        $legal_liability_paid_driver = $prm['tp'];
                    } else if ($coverage_name == 'Electrical Accessories') {
                        $prm = self::calculateNBPremium(($v));
                        $electric_accessories = $prm['od'];
                    } else if ($coverage_name == 'Cost of Accessories') {
                        $prm = self::calculateNBPremium(($v));
                        $non_electric_accessories = $prm['od'];
                    } else if ($coverage_name == 'CNG Kit') {
                        $prm = self::calculateNBPremium(($v));
                        $cng_od_premium = $prm['od'];
                        $cng_tp_premium = $prm['tp'];
                    } else if ($coverage_name == 'AAI Discount') {
                        $prm = self::calculateNBPremium(($v));
                        $aai_discount = abs($prm['od']);
                    } else if ($coverage_name == 'Anti-Theft') {
                        $prm = self::calculateNBPremium(($v));
                        $anti_theft = abs($prm['od']);
                    } else if ($coverage_name == 'CNG Kit Company Fit') {
                        $prm = self::calculateNBPremium(($v));
                        $cng_od_internal = $prm['od'];
                        $cng_tp_internal = $prm['tp'];
                    } else if ($coverage_name == 'Consumable') {
                        $prm = self::calculateNBPremium(($v));
                        $consumable_value = round($prm['od'], 2);
                    } else if ($coverage_name == 'NCB Protection') {
                        $prm = self::calculateNBPremium(($v));
                        $ncb_protection_value = round($prm['od'], 2);
                    }
                }
            } else {
                $coveragePremiumDetail = $premium_data[$ns . 'coveragePremiumDetail'] ?? [];
                foreach ($coveragePremiumDetail as $v) {
                    $coverageName = $v[$ns . 'coverageName'];

                    if (is_array($v[$ns . 'odPremium'])) {
                        $v[$ns . 'odPremium'] = (!empty($v[$ns . 'odPremium']['@value']) ? $v[$ns . 'odPremium']['@value'] : '0');
                    }

                    if (is_array($v[$ns . 'tpPremium'])) {
                        $v[$ns . 'tpPremium'] = (!empty($v[$ns . 'tpPremium']['@value']) ? $v[$ns . 'tpPremium']['@value'] : '0');
                    }

                    if ($coverageName == 'IDV Basic') {
                        $od_premium = $v[$ns . 'odPremium'];
                        $tp_premium = $v[$ns . 'tpPremium'];
                    } elseif ($coverageName == 'No Claim Bonus') {
                        $ncb_amount = abs($v[$ns . 'odPremium']);
                    } elseif ($coverageName == 'PA Owner / Driver') {
                        $pa_owner_driver = $v[$ns . 'tpPremium'];
                    } elseif ($coverageName == 'Depreciation Waiver') {
                        $dep_value = $v[$ns . 'coveragePremium'];
                    } elseif ($coverageName == 'Towing & Related') {
                        $towing = $v[$ns . 'coveragePremium'];
                    } elseif ($coverageName == 'PA to Passenger') {
                        $pa_unnamed = $v[$ns . 'tpPremium'];
                    } elseif ($coverageName == 'Voluntary Excess') {
                        $voluntary_excess = abs($v[$ns . 'odPremium'] + $v[$ns . 'tpPremium']);
                        $voluntary_deductible_od_premium = abs($v[$ns . 'odPremium']);
                        $voluntary_deductible_tp_premium = abs($v[$ns . 'tpPremium']);
                    } elseif ($coverageName == "TPPD") {
                        $tppd_discount = intval($v[$ns . 'tpPremium']) == 1 ? 0 : abs($v[$ns . 'tpPremium']);
                    } elseif ($coverageName == 'Legal Liability to Driver') {
                        $legal_liability_paid_driver = (abs($v[$ns . 'tpPremium']));
                    } elseif ($coverageName == 'Electrical Accessories') {
                        $electric_accessories = ($v[$ns . 'odPremium']);
                    } elseif ($coverageName == 'Cost of Accessories') {
                        $non_electric_accessories = ($v[$ns . 'odPremium']);
                    } elseif ($coverageName == 'CNG Kit') {
                        $cng_od_premium += ($v[$ns . 'odPremium']);
                        $cng_tp_premium += ($v[$ns . 'tpPremium']);
                    } elseif ($coverageName == 'AAI Discount') {
                        $aai_discount = abs($v[$ns . 'odPremium']);
                    } elseif ($coverageName == 'Anti-Theft') {
                        $anti_theft = abs($v[$ns . 'odPremium']);
                    } elseif ($coverageName == 'CNG Kit Company Fit') {
                        $cng_od_premium += ($v[$ns . 'odPremium']);
                        $cng_tp_premium += ($v[$ns . 'tpPremium']);
                    } elseif ($coverageName == 'Consumable') {
                        $consumable_value = $v[$ns . 'coveragePremium'];
                    } elseif ($coverageName == 'NCB Protection') {
                        //On UAT Getting premium in 'coveragePremium' tag but in production getting in 'odPremium' Tag - @Amit
                        if (!is_array($v[$ns . 'coveragePremium'])) {
                            $ncb_protection_value = ($v[$ns . 'coveragePremium']);
                        } elseif (!is_array($v[$ns . 'odPremium'])) {
                            $ncb_protection_value = ($v[$ns . 'odPremium']);
                        }
                        //$ncb_protection_value = $v[$ns.'coveragePremium'];
                    }
                }
            }

            $total_od_premium = $premium_data[$ns . 'totalODPremium'];
            $total_tp_premium = $premium_data[$ns . 'totalTPPremium'];
            $addon_total = $dep_value + $towing + $consumable_value + $ncb_protection_value;
            $voluntary_excess = $voluntary_deductible_od_premium + $voluntary_deductible_tp_premium;
            $discount_amount = abs($premium_data[$ns . 'discountLoadingAmt']);
            $service_tax = $premium_data[$ns . 'serviceTax'] ?? $premium_data[$ns . 'gstAmount'] ?? 0;
            $od_discount_amt = $premium_data[$ns . 'discountLoadingAmt'];
            $od_discount_loading = $premium_data[$ns . 'discountLoading'];
            $od_sum_dis_loading = $premium_data[$ns . 'totalODPremium'];
            $pa_unnamed = intval($pa_unnamed) == 1 ? 0 : $pa_unnamed;
            $ncb_amount = intval($ncb_amount) == 1 ? 0 : $ncb_amount;
            $pa_owner_driver = intval($pa_owner_driver) == 1 ? 0 : $pa_owner_driver;
            $total_discount_amount = abs($ncb_amount) + abs($discount_amount) + abs($tppd_discount) + abs($voluntary_excess) + abs($anti_theft);

            $finalPremium = $premium_data[$ns . 'premiumPayable'];
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
                "imt_23" => 0,
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

    public static function calculateNBPremium($cov)
    {
        return iffco_tokioSubmitProposal::calculateNBPremium($cov);
    }
}
