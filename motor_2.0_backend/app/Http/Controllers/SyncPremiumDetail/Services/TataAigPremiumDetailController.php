<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class TataAigPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {   
        $methodList = [
            'Premium calculation - Proposal',
            getGenericMethodName('Premium calculation - Proposal', 'proposal')
        ];

        $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
        ->where([
            'enquiry_id' => $enquiryId
        ])
        ->whereIn('company', ['tata_aig_v2', 'tata_aig'])
            ->whereIn('method_name', $methodList)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        $webserviceId = null;
        foreach ($logs as $log) {
            $response = $log['response'];
            $response = json_decode($response, true);
            if (
                !empty($response) &&
                !empty($response['data'][0]['data']['premium_break_up']['premium_value'] ?? null)
            ) {
                $webserviceId = $log['id'];
                break;
            }
        }

        if (!empty($webserviceId)) {
            return self::saveV2PremiumDetails($webserviceId);
        } else {
            return [
                'status' => false,
                'message' => 'Valid Proposal log not found',
            ];
        }
    }

    public static function saveV2PremiumDetails($webserviceId)
    {
        try {
            $logs = WebServiceRequestResponse::select(
                'response',
                'enquiry_id',
                'request',
                'created_at'
            )->find($webserviceId);

            $enquiryId = $logs->enquiry_id;

            $response = $logs->response;

            $response = json_decode($response, true);

            $polDetails = $response['data'][0]['pol_dlts'] ?? [];
            $premiumResponse = $response['data'][0]['data']['premium_break_up'];


            $odResponse = $premiumResponse['total_od_premium'] ?? [];
            $addonResponse = $premiumResponse['total_addOns'] ?? [];
            $tpResponse = $premiumResponse['total_tp_premium'] ?? [];
            $odDiscountResponse = $odResponse['discount_od'] ?? [];

            $netPremium   = $premiumResponse['net_premium'];
            $finalPayable = $premiumResponse['premium_value'];
            $taxAmount = $finalPayable - $netPremium;

            $basicOd = $odResponse['od']['basic_od'];
            
            $nonElectrical = (float) ($odResponse['od']['non_electrical_prem'] ?? 0);
            if (empty($nonElectrical) && !empty($polDetails['non_electrical_prem'])) {
                $nonElectrical = (float) $polDetails['non_electrical_prem'];
            }

            $electrical = (float) ($polDetails['electrical_prem'] ?? $odResponse['od']['electrical_prem'] ?? 0);
            if (empty($electrical) && !empty($polDetails['electrical_prem'])) {
                $electrical = (float) $polDetails['electrical_prem'];
            }

            $lpgOd = (float) ($odResponse['od']['cng_lpg_od_prem'] ?? 0);
            $geoOd = (float) ($odResponse['od']['geography_extension_od_prem'] ?? 0);


            $basicTp = (float) ($tpResponse['basic_tp'] ?? 0);
            $lpgTp = (float) ($tpResponse['cng_lpg_tp_prem'] ?? 0);
            $tppdDiscount = (float) ($tpResponse['tppd_prem'] ?? 0);
            if (empty($tppdDiscount) && !empty($polDetails['tppd_prem'])) {
                $tppdDiscount = (float) $polDetails['tppd_prem'];
            }

            $geoTp = (float) ($tpResponse['geography_extension_tp_prem'] ?? 0);

            $paUnnamed = (float) ($tpResponse['pa_unnamed_prem'] ?? 0);
            $llPaid = (float) ($tpResponse['ll_paid_drive_prem'] ?? 0);
            $papaid = (float) ($tpResponse['pa_paid_drive_prem'] ?? 0);
            $cpa = (float) ($tpResponse['cpa_prem'] ?? 0);

            $antitheft = (float) ($odDiscountResponse['atd_disc_prem'] ?? 0);
            $automoblie = (float) ($odDiscountResponse['aam_disc_prem'] ?? 0);
            $voluntaryDeductible = (float) ($odDiscountResponse['vd_disc_prem'] ?? 0);
            $ncb = (float) ($odDiscountResponse['ncb_prem'] ?? $polDetails['curr_ncb_perc'] ?? 0);

            $imt23 = (float) ($odResponse['loading_od']['cover_lapm_prem'] ?? 0);

            $zeroDep = $addonResponse['dep_reimburse_prem'] ?? 0;
            $rsa = $addonResponse['rsa_prem'] ?? 0;
            $ncbProtection = $addonResponse['ncb_protection_prem'] ?? 0;
            $engineSecure = $addonResponse['engine_secure_prem'] ?? 0;
            $tyreSecure = $addonResponse['tyre_secure_prem'] ?? 0;
            $rti = $addonResponse['return_invoice_prem'] ?? 0;
            $consumable = $addonResponse['consumbale_expense_prem'] ?? 0;
            $keyReplacement = $addonResponse['key_replace_prem'] ?? 0;
            $lopb = $addonResponse['personal_loss_prem'] ?? 0;
            $eme = $addonResponse['emergency_expense_prem'] ?? 0;

            if (!empty($odResponse['od']['net_od'])) {
                $finalOd = (float) $odResponse['od']['net_od'];
            } else {
                $finalOd = (float) ($odResponse['total_od'] ?? 0) + ($addonResponse['total_addon'] ?? 0);
            }
            $finalTp = (float) $tpResponse['total_tp'] ?? 0;



            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $basicOd,
                "loading_amount" => 0,
                "final_od_premium" => $finalOd,
                // TP Tags
                "basic_tp_premium" => $basicTp,
                "final_tp_premium" => $finalTp,
                // Accessories
                "electric_accessories_value" => $electrical,
                "non_electric_accessories_value" => $nonElectrical,
                "bifuel_od_premium" => $lpgOd,
                "bifuel_tp_premium" => $lpgTp,
                // Addons
                "compulsory_pa_own_driver" => $cpa,
                "zero_depreciation" => $zeroDep,
                "road_side_assistance" => $rsa,
                "imt_23" => $imt23,
                "consumable" => $consumable,
                "key_replacement" => $keyReplacement,
                "engine_protector" => $engineSecure,
                "ncb_protection" => $ncbProtection,
                "tyre_secure" => $tyreSecure,
                "return_to_invoice" => $rti,
                "loss_of_personal_belongings" => $lopb,
                "eme_cover" => $eme,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => $papaid,
                "unnamed_passenger_pa_cover" => $paUnnamed,
                "ll_paid_driver" => $llPaid,
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => $geoOd,
                "geo_extension_tppremium" => $geoTp,
                // Discounts
                "anti_theft" => $antitheft,
                "voluntary_excess" => $voluntaryDeductible,
                "tppd_discount" => $tppdDiscount,
                "other_discount" => 0,
                "ncb_discount_premium" => $ncb,
                // Final tags
                "net_premium" => $netPremium,
                "service_tax_amount" => $taxAmount,
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
