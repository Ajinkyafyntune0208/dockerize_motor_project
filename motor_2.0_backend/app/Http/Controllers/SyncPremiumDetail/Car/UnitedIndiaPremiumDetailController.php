<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class UnitedIndiaPremiumDetailController extends Controller
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
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id', 'request')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'united_india'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                try {
                    $response = html_entity_decode($response);
                    $response = XmlToArray::convert($response);
                } catch (\Throwable $th) {
                    $response = null;
                }
                $response = $response['S:Body']['ns2:saveProposalResponse']['return']['ROOT']['HEADER'] ?? null;
                if (!empty($response['CUR_FINAL_TOTAL_PREMIUM'])) {
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

            $response = html_entity_decode($logs->response);
            $response = XmlToArray::convert($response);
            $response = $response['S:Body']['ns2:saveProposalResponse']['return']['ROOT']['HEADER'] ?? null;
            $worksheet = $response['TXT_PRODUCT_USERDATA']['WorkSheet'] ?? [];

            $total_tp_premium       = 0;
            $pa_unnamed             = 0;
            $electrical_amount      = 0;
            $non_electrical_amount  = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;

            $base_cover = [
                'od_premium'            =>  0,
                'tp_premium'            =>  0,
                'pa_owner'              =>  0,
                'liability'             =>  0,
                'eng_prot'              =>  0,
                'return_to_invoice'     =>  0,
                'zero_dep_amount'       =>  0,
                'medical_expense'       =>  0,
                'consumable'            =>  0,
                'key_replacement'       =>  0,
                'tyre_secure'           =>  0,
                'rsa' => 0,
                'battery_protect' => 0
            ];
            $base_cover_codes = [
                'od_premium'        =>  'Basic - OD',
                'tp_premium'        =>  'Basic - TP',
                'pa_owner'          =>  'PA Owner Driver',
                'liability'         =>  'LL to Paid Driver IMT 28',
                'eng_prot'          =>  'Engine and Gearbox Protection Platinum AddOn Cover',
                'return_to_invoice' =>  'Return To Invoice',
                'zero_dep_amount'   =>  'Nil Depreciation Without Excess',
                'medical_expense'   =>  'Medical Expenses',
                'consumable'        =>  'Consumables Cover',
                'key_replacement'   =>  'Loss Of Key Cover',
                'tyre_secure'       =>  'Tyre And Rim Protector Cover',
                'rsa' =>  'Road Side Assistance',
                'battery_protect' =>  'EV Protect'
            ];
            $base_cover_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];

            $discount_codes = [
                'bonus_discount'            =>  'Bonus Discount',
                'anti_theft_discount'       =>  'Anti-Theft Device - OD',
                'automobile_association'    =>  'Automobile Association Discount',
                'voluntary'                 =>  'Voluntary Excess Discount',
                'tppd'                      =>  'TPPD Discount',
                'detariff'                  =>  'Detariff Discount',
                'built_in_cng_od' => 'Built in CNG - OD loading - OD',
                'built_in_cng_tp' => 'Built in CNG-TP Loading-TP',
            ];
            $match_arr = [
                'name'  => 'PropLoadingDiscount_Description',
                'value' => 'PropLoadingDiscount_CalculatedAmount',
            ];
            $discount = [
                'bonus_discount'            =>  0,
                'anti_theft_discount'       =>  0,
                'automobile_association'    =>  0,
                'voluntary'                 =>  0,
                'tppd'                      =>  0,
                'detariff'                  =>  0,
                'built_in_cng_od' => 0,
                'built_in_cng_tp' => 0,
            ];



            $cng_codes = [
                'lpg_cng_tp_amount'     =>  'CNG Kit-TP',
                'lpg_cng_amount'        =>  'CNG Kit-OD',
            ];
            $cng_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];
            $cng = [
                'lpg_cng_tp_amount'     =>  0,
                'lpg_cng_amount'        =>  0,
            ];

            // $loading_amount = 0;

            $tppd = 0;
            $detariff_discount = 0;
            if (isset($worksheet['PropRisks_Col']['Risks'][0])) {
                foreach ($worksheet['PropRisks_Col']['Risks'] as $risk_key => $risk_value) {
                    if (is_array($risk_value) && isset($risk_value['PropRisks_VehicleSIComponent'])) {
                        if ($risk_value['PropRisks_VehicleSIComponent'] == 'Vehicle Base Value') {
                            $base_cover = self::getAddonValues($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);

                            if (!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0])) {
                                $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                } else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD') {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                                $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                            } else {
                                foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {
                                    if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {
                                        if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                            if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                                if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                    $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        }
                                    } elseif (
                                        in_array($v['PropCoverDetails_CoverGroups'], [
                                            'Basic OD',
                                            'Basic - OD'
                                        ]) && !empty($v['PropCoverDetails_LoadingDiscount_Col']) &&
                                        !isset($v['PropCoverDetails_LoadingDiscount_Col'][0])
                                    ) {
                                        if (isset($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0])) {
                                            foreach ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'] as $disc) {
                                                if (in_array($disc['PropLoadingDiscount_Description'], [
                                                    'Detariff Discount',
                                                    'Women Discount'
                                                ])) {
                                                    $detariff_discount += $disc['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        } elseif ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                            $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }
                                }
                            }
                        }
                        if ($risk_value['PropRisks_VehicleSIComponent'] == 'CNG') {
                            $cng = self::getAddonValues($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                        }
                        if ($risk_value['PropRisks_VehicleSIComponent'] == 'Unnamed PA Cover') {
                            $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_VehicleSIComponent'] == 'Electrical Accessories') {
                            $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_VehicleSIComponent'] == 'Non-Electrical Accessories') {
                            $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                        }
                    }
                }
            } else {
                $risk_value = $worksheet['PropRisks_Col']['Risks'];
                if (is_array($risk_value) && isset($risk_value['PropRisks_VehicleSIComponent'])) {
                    if ($risk_value['PropRisks_VehicleSIComponent'] == 'Vehicle Base Value') {
                        $base_cover = self::getAddonValues($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);

                        if (!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0])) {
                            $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                            if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {
                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                        if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                            $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }
                                }
                            } else if ($v['PropCoverDetails_CoverGroups'] == 'Basic - OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic OD') {
                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                        if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                            $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }
                                }
                            }
                        } else {
                            foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                } else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD') {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                                $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($risk_value['PropRisks_VehicleSIComponent'] == 'CNG') {
                        $cng = self::getAddonValues($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                    }
                    if ($risk_value['PropRisks_VehicleSIComponent'] == 'Unnamed PA Cover') {
                        $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_VehicleSIComponent'] == 'Electrical Accessories') {
                        $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_VehicleSIComponent'] == 'Non-Electrical Accessories') {
                        $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                    }
                }
            }
            if (is_array($worksheet['PropLoadingDiscount_Col']) && !empty($worksheet['PropLoadingDiscount_Col'])) {
                $discount = self::getAddonValues($worksheet['PropLoadingDiscount_Col']['LoadingDiscount'], $discount_codes, $match_arr, $discount);
            }
            $discount['detariff'] = $detariff_discount;
            $discount['tppd'] = $tppd;

            $base_cover['eng_prot'] + $base_cover['tyre_secure'] + $base_cover['return_to_invoice'];
            $total_tp_premium           = $base_cover['tp_premium'] + $base_cover['liability'] + $cng['lpg_cng_tp_amount'] + $pa_unnamed + $base_cover['pa_owner'];
            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $base_cover['od_premium'] ?? 0,
                "loading_amount" => 0,
                "final_od_premium" => $response['CUR_NET_OD_PREMIUM'],
                // TP Tags
                "basic_tp_premium" => $base_cover['tp_premium'] ?? 0,
                "final_tp_premium" => $response['CUR_NET_TP_PREMIUM'] ?? $total_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_amount,
                "non_electric_accessories_value" => $non_electrical_amount,
                "bifuel_od_premium" => $cng['lpg_cng_amount'] + $discount['built_in_cng_od'],
                "bifuel_tp_premium" => $cng['lpg_cng_tp_amount'] + $discount['built_in_cng_tp'],
                // Addons
                "compulsory_pa_own_driver" => $base_cover['pa_owner'] ?? 0,
                "zero_depreciation" => $base_cover['zero_dep_amount'],
                "road_side_assistance" => $base_cover['rsa'] ?? 0,
                "imt_23" => 0,
                "consumable" => $base_cover['consumable'],
                "key_replacement" => $base_cover['key_replacement'],
                "engine_protector" => $base_cover['eng_prot'],
                "ncb_protection" => 0,
                "tyre_secure" => $base_cover['tyre_secure'],
                "return_to_invoice" => $base_cover['return_to_invoice'],
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                "battery_protect" => $base_cover['battery_protect'] ?? 0,
                // Covers
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $pa_unnamed,
                "ll_paid_driver" => $base_cover['liability'],
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => $geog_Extension_OD_Premium,
                "geo_extension_tppremium" => $geog_Extension_TP_Premium,
                // Discounts
                "anti_theft" => $discount['anti_theft_discount'],
                "voluntary_excess" => $discount['voluntary'],
                "tppd_discount" => $discount['tppd'],
                "other_discount" => $discount['detariff'] + $discount['automobile_association'],
                "ncb_discount_premium" => $discount['bonus_discount'] ?? 0,
                // Final tags
                "net_premium" => $response['CUR_NET_FINAL_PREMIUM'] ?? 0,
                "service_tax_amount" => $response['CUR_FINAL_SERVICE_TAX'],
                "final_payable_amount" => $response['CUR_FINAL_TOTAL_PREMIUM'],
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

    public static function getAddonValues($value_arr, $cover_codes, $match_arr, $covers)
    {
        if (!isset($value_arr[0])) {
            $value = $value_arr;
            foreach ($cover_codes as $k => $v) {
                if ($value[$match_arr['name']] == $v) {
                    $covers[$k] = round($value[$match_arr['value']]);
                }
            }
        } else {
            foreach ($value_arr as $value) {
                foreach ($cover_codes as $k => $v) {
                    if ($value[$match_arr['name']] == $v) {
                        $covers[$k] = round($value[$match_arr['value']]);
                    }
                }
            }
        }
        return $covers;
    }
}
