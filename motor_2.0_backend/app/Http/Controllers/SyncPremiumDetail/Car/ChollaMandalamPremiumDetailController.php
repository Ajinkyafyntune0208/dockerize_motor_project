<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;

class ChollaMandalamPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')->where('user_product_journey_id', $enquiryId)->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';

            if ($isRenewal) {
                $methodList = [
                    'Proposal Submition - Proposal',
                    getGenericMethodName('Proposal Submition - Proposal', 'proposal'),
                ];
            } else {
                $methodList = [
                    'Proposal Submition - Proposal',
                    getGenericMethodName('Proposal Submition - Proposal', 'proposal')
                ];
            }
            $methodList = array_unique($methodList);
            $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'cholla_mandalam'
                ])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();
            $webserviceId = null;
            foreach ($logs as $log) {
                $response = $log['response'];
                $response = json_decode($response, true);
                if (!empty($response['Data']['Total_Premium'] ?? null)) {
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

            $proposal_response = array_change_key_case_recursive($response);
            $proposal_response_data = $proposal_response['data'];
            $payment_id           = $proposal_response_data['payment_id'];
            $total_premium         = $proposal_response_data['total_premium'];
            $service_tax_total     = $proposal_response_data['gst'];
            $base_premium         = $proposal_response_data['net_premium'];

            $base_cover['od'] = $proposal_response_data['basic_own_damage_cng_elec_non_elec'];
            $base_cover['electrical'] = $proposal_response_data['electrical_accessory_prem'];
            $base_cover['non_electrical'] = $proposal_response_data['non_electrical_accessory_prem'];
            $base_cover['lpg_cng_od'] = $proposal_response_data['cng_lpg_own_damage'];

            $base_cover['tp'] = $proposal_response_data['basic_third_party_premium'];
            $base_cover['pa_owner'] = $proposal_response_data['personal_accident'];
            $base_cover['unnamed'] = $proposal_response_data['unnamed_passenger_cover'];
            $base_cover['paid_driver'] = '0';
            $base_cover['legal_liability'] = $proposal_response_data['legal_liability_to_paid_driver'];
            $base_cover['lpg_cng_tp'] = $proposal_response_data['cng_lpg_tp'];

            $base_cover['ncb'] = $proposal_response_data['no_claim_bonus'];
            $base_cover['automobile_association'] = '0';
            $base_cover['anti_theft'] = '0';
            $base_cover['other_discount'] = $proposal_response_data['dtd_discounts'] + $proposal_response_data['gst_discounts'];

            $addon['zero_dep'] = (($proposal_response_data['zero_depreciation'] == '0') ? 'NA' : $proposal_response_data['zero_depreciation']);
            $addon['key_replacement'] = (($proposal_response_data['key_replacement_cover'] == '0') ? 'NA' : $proposal_response_data['key_replacement_cover']);
            $addon['consumable'] = (($proposal_response_data['consumables_cover'] == '0') ? 'NA' : $proposal_response_data['consumables_cover']);
            $addon['loss_of_belongings'] = (($proposal_response_data['personal_belonging_cover'] == '0') ? 'NA' : $proposal_response_data['personal_belonging_cover']);
            $addon['rsa'] = (($proposal_response_data['rsa_cover'] == '0') ? 'NA' : $proposal_response_data['rsa_cover']);
            $addon['engine_protect']  = (($proposal_response_data['hydrostatic_lock_cover'] == '0') ? 'NA' : $proposal_response_data['hydrostatic_lock_cover']);
            $addon['tyre_secure'] = 'NA';
            $addon['return_to_invoice'] = 'NA';
            $addon['ncb_protect'] = 'NA';

            $total_od = $base_cover['od'] + $base_cover['electrical'] + $base_cover['non_electrical'] + $base_cover['lpg_cng_od'];
            $total_tp = $base_cover['tp'] + $base_cover['legal_liability'] + $base_cover['unnamed'] + $base_cover['lpg_cng_tp'] + $base_cover['pa_owner'];

            $total_discount = $base_cover['other_discount'] + $base_cover['automobile_association'] + $base_cover['anti_theft'] + $base_cover['ncb'];

            $total_premium_amount = $proposal_response_data['total_premium'];


            $base_cover['tp'] = $base_cover['tp']; // + $base_cover['legal_liability'];

            $addon_sum = (is_integer($addon['zero_dep']) ? $addon['zero_dep'] : 0)
                + (is_integer($addon['key_replacement']) ? $addon['key_replacement'] : 0)
                + (is_integer($addon['consumable']) ? $addon['consumable'] : 0)
                + (is_integer($addon['loss_of_belongings']) ? $addon['loss_of_belongings'] : 0)
                + (is_integer($addon['rsa']) ? $addon['rsa'] : 0)
                + (is_integer($addon['engine_protect']) ? $addon['engine_protect'] : 0)
                + (is_integer($addon['tyre_secure']) ? $addon['tyre_secure'] : 0)
                + (is_integer($addon['return_to_invoice']) ? $addon['return_to_invoice'] : 0)
                + (is_integer($addon['ncb_protect']) ? $addon['ncb_protect'] : 0);

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $base_cover['od'],
                "loading_amount" => $proposal_response['dtd_loading'] ?? 0,
                "final_od_premium" => $total_od - $total_discount + $addon_sum,
                // TP Tags
                "basic_tp_premium" => $base_cover['tp'],
                "final_tp_premium" => $total_tp,
                // Accessories
                "electric_accessories_value" => $base_cover['electrical'],
                "non_electric_accessories_value" => $base_cover['non_electrical'],
                "bifuel_od_premium" => $base_cover['lpg_cng_od'],
                "bifuel_tp_premium" => $base_cover['lpg_cng_tp'],
                // Addons
                "compulsory_pa_own_driver" => $base_cover['pa_owner'],
                "zero_depreciation" => $addon['zero_dep'],
                "road_side_assistance" => $addon['rsa'],
                "imt_23" => 0,
                "consumable" => $addon['consumable'],
                "key_replacement" => $addon['key_replacement'],
                "engine_protector" => $addon['engine_protect'],
                "ncb_protection" => 0, // They don't provide
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => $addon['loss_of_belongings'],
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,
                // Covers
                "pa_additional_driver" => 0,
                "unnamed_passenger_pa_cover" => $base_cover['unnamed'],
                "ll_paid_driver" => $base_cover['legal_liability'],
                "ll_paid_conductor" => 0,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => $base_cover['anti_theft'],
                "voluntary_excess" => 0, // They don't provide
                "tppd_discount" => 0,
                "other_discount" => $base_cover['other_discount'],
                "ncb_discount_premium" => $base_cover['ncb'],
                // Final tags
                "net_premium" => $base_premium,
                "service_tax_amount" => round($service_tax_total, 2),
                "final_payable_amount" => $proposal_response_data['total_premium'],
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
