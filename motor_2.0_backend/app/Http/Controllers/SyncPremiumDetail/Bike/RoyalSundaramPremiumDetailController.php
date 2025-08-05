<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\SelectedAddons;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class RoyalSundaramPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Fetch Policy Details',
                    getGenericMethodName('Fetch Policy Details', 'proposal')
                ];
            } else {
                $methodList = [
                    'Update Premium Calculation',
                    getGenericMethodName('Update Premium Calculation', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'royal_sundaram'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if ($isRenewal && ($response['statusCode'] ?? '') == 'S-0001') {
                    $webserviceId = $log['id'];
                    break;
                }
                if (!$isRenewal && isset($response['PREMIUMDETAILS']['DATA']['PREMIUM'])) {
                    $webserviceId = $log['id'];
                    break;
                }
            }

            if (!empty($webserviceId)) {
                if ($isRenewal) {
                    return self::saveRenewalPremiumDetails($webserviceId);
                }
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

            $TPPDCover = '';
            $additional = SelectedAddons::where('user_product_journey_id', $enquiryId)
                ->select('discounts', 'compulsory_personal_accident')
                ->first();

            $cpa_tenure = $requestData->business_type == 'newbusiness' ? 5 : 1;

            if (!empty($additional['discounts'])) {
                foreach ($additional['discounts'] as $data) {
                    if ($data['name'] == 'TPPD Cover') {
                        $TPPDCover = '6000';
                    }
                }
            }

            if ($requestData->vehicle_owner_type == 'I' && !empty($additional['compulsory_personal_accident'])) {
                foreach ($additional['compulsory_personal_accident'] as $data) {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                        $cpa_tenure = isset($data['tenure']) ? (string) $data['tenure'] : '1';
                    }
                }
            }

            $llpaiddriver_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS'], 2);
            $cover_pa_owner_driver_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER'], 2);
            $cover_pa_paid_driver_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER'], 2);
            $cover_pa_unnamed_passenger_premium = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS'], 2);
            $voluntary_excess = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE'], 2);
            $anti_theft = 0;
            $ic_vehicle_discount = 0;
            $ncb_discount = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS'] ?? 0), 2);
            $electrical_accessories_amt = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES'], 2);
            $non_electrical_accessories_amt = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES'] ?? 0), 2);
            $od = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES'], 2);
            $tppd = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD'], 2);
            $tppd_discount = 0;
            if (!empty($TPPDCover)) {
                $tppd_discount = 50 * $cpa_tenure;
                $tppd += $tppd_discount;
            }
            $cng_lpg = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT'], 2);
            $cng_lpg_tp = round($response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG'], 2);

            $zero_depreciation = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'], 2);
            $rsa = round(($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDEASSISSTANCE'] ?? 0), 2);
            $engine_protection = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR'], 2);
            $ncb_protection = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NCB_PROTECTOR'], 2);
            $key_replace = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['KEY_REPLACEMENT'], 2);
            $tyre_secure = 0;
            $return_to_invoice = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['INVOICE_PRICE_INSURANCE'], 2);
            $lopb = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['LOSS_OF_BAGGAGE'], 2);

            $loading_amount = round($response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['UNDERWRITER_LOADING'] ?? 0);
            $final_od_premium = $final_od_premium =  $response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TOTAL_OD_PREMIUM']; //$od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
            $final_tp_premium = $response['PREMIUMDETAILS']['DATA']['LIABILITY']['TOTAL_LIABILITY_PREMIUM']; //$tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium;
            $final_net_premium = round($response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM'], 2);
            $final_gst_amount = round(($response['PREMIUMDETAILS']['DATA']['PREMIUM'] - $response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM']), 2);
            $final_payable_amount = round($response['PREMIUMDETAILS']['DATA']['PREMIUM'], 2);


            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od,
                "loading_amount" => $loading_amount,
                "final_od_premium" => $final_od_premium,
                // TP Tags
                "basic_tp_premium" => $tppd,
                "final_tp_premium" => $final_tp_premium,
                // Accessories
                "electric_accessories_value" => $electrical_accessories_amt,
                "non_electric_accessories_value" => $non_electrical_accessories_amt,
                "bifuel_od_premium" => $cng_lpg,
                "bifuel_tp_premium" => $cng_lpg_tp,
                // Addons
                "compulsory_pa_own_driver" => $cover_pa_owner_driver_premium,
                "zero_depreciation" => $zero_depreciation,
                "road_side_assistance" => $rsa,
                "imt_23" => 0,
                "consumable" => 0,
                "key_replacement" => $key_replace,
                "engine_protector" => $engine_protection,
                "ncb_protection" => $ncb_protection,
                "tyre_secure" => $tyre_secure,
                "return_to_invoice" => $return_to_invoice,
                "loss_of_personal_belongings" => $lopb,
                "wind_shield" => 0,
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
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $voluntary_excess,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $ic_vehicle_discount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $final_net_premium,
                "service_tax_amount" => $final_gst_amount,
                "final_payable_amount" => $final_payable_amount,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? $value : 0;
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

    public static function saveRenewalPremiumDetails($webserviceId)
    {
        try {
            $response = WebServiceRequestResponse::select('response', 'enquiry_id')->find($webserviceId);
            $enquiryId = $response->enquiry_id;
            $response = json_decode($response->response, true);

            $VoluntaryDed = $VPC_OwnDamageCover = $VPC_TPBasicCover = $VPC_CompulsoryPA =
                $ServiceeTax = $AntiTheftDiscount = $VPC_ODBasicCover = $AutoAssociationMembership =
                $VPC_LiabilityCover = $NoCliamDiscount = $OwnPremisesDiscount = 0;

            $VPC_FiberGlass = $SpareCar = $AggravationCover = $DepreciationWaiver = $VPC_ElectAccessories = $VPC_PAPaidDriver =
                $EnhancedPAUnnamedPassengersCover = $TP_GeoExtension = $GeoExtension = $AdditionalTowingChargesCover =
                $WindShieldGlass = $LossofBaggage = $EnhancedPAPaidDriverCover = $VPC_PAUnnamed = $VPC_WLLDriver =
                $EnhancedPANamedPassengersCover = $KeyReplacementCover = $NonElectricalAccessories = $VPC_PANamedOCcupants = 0;
            $NCBProtectorCover = $TyreCoverClause = $InvoicePrice =  0;
            $electrical_accessories_amt = 0;
            $tppd_discount = 0;
            $non_electrical_accessories_amt = 0;
            $cng_lpg = 0;
            $cover_pa_unnamed_passenger_premium = 0;
            $llpaiddriver_premium = 0;
            $cover_pa_paid_driver_premium = 0;
            $cover_pa_owner_driver_premium = 0;
            $cng_lpg_tp = 0;
            $anti_theft = 0;
            $ic_vehicle_discount = 0;
            $total_discount = 0;
            $addon_premium = 0;

            foreach ($response['coverages'] as $coverages) {
                if ($coverages['name'] == 'VoluntaryDed') {
                    $total_discount += $VoluntaryDed = $coverages['premium'];
                } elseif (in_array($coverages['name'] , ['VPC_TPBasicCover' , 'VMC_LiabilityCover'])) {
                    $VPC_TPBasicCover = $coverages['premium'];
                } elseif (in_array($coverages['name'], ['VPC_CompulsoryPA', 'VMC_PAOwnerDriverCover'])) {
                    $cover_pa_owner_driver_premium = $coverages['premium'];
                } elseif ($coverages['name'] == 'AntiTheftDiscount') {
                    $total_discount += $anti_theft = $coverages['premium'];
                } elseif (in_array($coverages['name'] , ['VPC_OwnDamageCover', 'VMC_ODBasicCover'])) {
                    $VPC_OwnDamageCover = $coverages['premium'];
                } elseif ($coverages['name'] == 'AutoAssociationMembership') {
                    $total_discount += $AutoAssociationMembership = $coverages['premium'];
                } elseif ($coverages['name'] == 'NoCliamDiscount') {
                    $total_discount += $NoCliamDiscount = $coverages['premium'];
                } elseif ($coverages['name'] == 'OwnPremisesDiscount') {
                    $total_discount += $OwnPremisesDiscount = $coverages['premium'];
                } elseif (in_array($coverages['name'], ['DepreciationWaiverforTW', 'DepreciationWaiver'])) {
                    $addon_premium += $DepreciationWaiver = $coverages['premium'];
                } elseif (in_array($coverages['name'] , ['AggravationCover', 'EngineProtectorCover_TW'])) {
                    $addon_premium += $AggravationCover = $coverages['premium'];
                } elseif ($coverages['name'] == 'NCBProtectorCover') {
                    $addon_premium += $NCBProtectorCover = $coverages['premium'];
                } elseif ($coverages['name'] == 'InvoicePrice') {
                    $addon_premium += $InvoicePrice = $coverages['premium'];
                } elseif ($coverages['name'] == 'TyreCoverClause') {
                    $addon_premium += $TyreCoverClause = $coverages['premium'];
                } elseif (in_array($coverages['name'],['VPC_ElectAccessories', 'VMC_ElecAccessoriesCover'])) {
                    $addon_premium += $electrical_accessories_amt = $coverages['premium'];
                } elseif (in_array($coverages['name'], ['NonElectricalAccessories', 'VMC_NonElecAccessoriesCover'])) {
                    $addon_premium += $non_electrical_accessories_amt = $coverages['premium'];
                } elseif (in_array($coverages['name'], ['VPC_PAPaidDriver', 'VMC_LLPaidDriverCover'])) {
                    $llpaiddriver_premium = $coverages['premium'];
                } elseif ($coverages['name'] == 'GeoExtension') {
                    $addon_premium += $GeoExtension = $coverages['premium'];
                } elseif ($coverages['name'] == 'WindShieldGlass') {
                    $addon_premium += $WindShieldGlass = $coverages['premium'];
                } elseif ($coverages['name'] == 'LossofBaggage') {
                    $addon_premium += $LossofBaggage = $coverages['premium'];
                } elseif ($coverages['name'] == 'EnhancedPAPaidDriverCover') {
                    $cover_pa_paid_driver_premium = (int) $coverages['premium'];
                } elseif (in_array($coverages['name'], ['VPC_PAUnnamed','VMC_PAUnnamed'])) {
                    $cover_pa_unnamed_passenger_premium = $coverages['premium'];
                } elseif ($coverages['name'] == 'KeyReplacementCover') {
                    $addon_premium += $KeyReplacementCover = $coverages['premium'];
                }
            }
            $final_gst_amount = $response['serviceTax'];
            $final_net_premium = $response['netPremium'];
            $final_payable_amount = $response['renewalPremium'];

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $VPC_OwnDamageCover,
                "loading_amount" => 0,
                "final_od_premium" => $response['totalodpremium'] ?? 0,
                // TP Tags
                "basic_tp_premium" => $VPC_TPBasicCover,
                "final_tp_premium" => $response['totaltppremium'] ?? 0,
                // Accessories
                "electric_accessories_value" => $electrical_accessories_amt,
                "non_electric_accessories_value" => $non_electrical_accessories_amt,
                "bifuel_od_premium" => $cng_lpg,
                "bifuel_tp_premium" => $cng_lpg_tp,
                // Addons
                "compulsory_pa_own_driver" => $cover_pa_owner_driver_premium,
                "zero_depreciation" => $DepreciationWaiver,
                "road_side_assistance" => 0,
                "imt_23" => 0,
                "consumable" => 0,
                "key_replacement" => $KeyReplacementCover,
                "engine_protector" => $AggravationCover,
                "ncb_protection" => $NCBProtectorCover,
                "tyre_secure" => $TyreCoverClause,
                "return_to_invoice" => $InvoicePrice,
                "loss_of_personal_belongings" => $LossofBaggage,
                "wind_shield" => 0,
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
                "anti_theft" => $anti_theft,
                "voluntary_excess" => $VoluntaryDed,
                "tppd_discount" => $tppd_discount,
                "other_discount" => $ic_vehicle_discount,
                "ncb_discount_premium" => $NoCliamDiscount,
                // Final tags
                "net_premium" => $final_net_premium,
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
