<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class FutureGeneraliPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        try {
            $coporateVehcileData = CorporateVehiclesQuotesRequest::select('is_renewal', 'rollover_renewal')
            ->where('user_product_journey_id', $enquiryId)
            ->first();
            $isRenewal = ($coporateVehcileData->is_renewal ?? null) == 'Y' && $coporateVehcileData->rollover_renewal != 'Y';
            if (!$isRenewal) {
                $methodList = [
                    'Proposal submit',
                    getGenericMethodName('Proposal submit', 'proposal')
                ];
                $methodList = array_unique($methodList);
                $logs = WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
                ->where([
                    'enquiry_id' => $enquiryId,
                    'company' => 'future_generali'
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
                    if (($response['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy']['Status'] ??
                    $response['PremiumBreakup']['Root']['Policy']['Status'] ?? '') == 'Successful' || !empty($response['PremiumBreakup']['NewDataSet'])) {
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
            } else {
                return [
                    'status' => false,
                    'message' => 'Integration not yet done.',
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
            $response = $logs->response;

            $response = html_entity_decode($response);
            $response = XmlToArray::convert($response);

            $quote_output = $response['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];

            $od_premium = 0;
            $tp_premium = 0;
            $liability = 0;
            $pa_owner = 0;
            $pa_unnamed = 0;
            $lpg_cng_amount = 0;
            $lpg_cng_tp_amount = 0;
            $electrical_amount = 0;
            $non_electrical_amount = 0;
            $ncb_discount = 0;
            $discount_amount = 0;
            $discperc = 0;
            $zero_dep_amount = 0;
            $ncb_prot = 0;
            $rsa = $imt23 = $consumable = 0;
            $total_od = 0;
            $total_tp = 0;
            $service_tax_od = $tppdPremium = 0;
            $service_tax_tp = $legal_liability_to_employee =  0;

            foreach ($quote_output['NewDataSet']['Table1'] as  $cover) {
                $cover = array_map('trim', $cover);
                $value = $cover['BOValue'];

                if (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD')) {
                    $od_premium = $value;
                } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'TP')) {
                    $tp_premium = $value;
                } elseif (($cover['Code'] == 'LLDE') && ($cover['Type'] == 'TP')) {
                    $liability = $value;
                } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                    $pa_owner = $value;
                } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                    $pa_unnamed = $value;
                } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'OD')) {
                    $lpg_cng_amount = $value;
                } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'TP')) {
                    $lpg_cng_tp_amount = $value;
                } elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                    $electrical_amount = $value;
                } elseif (in_array($cover['Code'], ['NEA', 'NEAV']) && ($cover['Type'] == 'OD') && !empty($value)) {
                    $non_electrical_amount = $value;
                } elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                    $ncb_discount = abs($value);
                } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                    $discount_amount = round(str_replace('-', '', $value), 2);
                } elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                    $discperc = $value;
                } elseif (in_array($cover['Code'], ['ZDCNS', 'ZDCNE', 'ZDCNT', 'ZDCET', 'ZCETR', 'STZDP', 'ZODEP'])) {
                    $zero_dep_amount = $value;
                } elseif (in_array($cover['Code'], ['STRSA', 'RSPBK', 'RODSA'])) {
                    $rsa = $value;
                } elseif (in_array($cover['Code'], ['STNCB'])) {
                    $ncb_prot = $value;
                } elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'OD')) {
                    $service_tax_od = $value;
                } elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'TP')) {
                    $service_tax_tp = $value;
                } elseif (($cover['Code'] == 'Gross Premium') && ($cover['Type'] == 'OD')) {
                    $total_od = $value;
                } elseif (($cover['Code'] == 'Gross Premium') && ($cover['Type'] == 'TP')) {
                    $total_tp = $value;
                } elseif(($cover['Code'] == 'LLEE' && $cover['Type'] == 'TP')) {
                    $legal_liability_to_employee = $value;
                } elseif (($cover['Code'] == 'RTC') && ($cover['Type'] == 'TP') && !empty($value)) {
                    $tppdPremium = abs($value);
                } elseif (($cover['Code'] == 'IMT23') && ($cover['Type'] == 'OD') && !empty($value)) {
                    $imt23 = abs($value);
                } elseif (($cover['Code'] == '00005') && ($cover['Type'] == 'OD')) {
                    $consumable = $value;
                }
            }
            if ($discperc > 0) {
                $od_premium = $od_premium + $discount_amount;
                $discount_amount = 0;
            }
            $service_tax = $service_tax_od + $service_tax_tp;
            $net_premium = $total_od + $total_tp;
            $final_payable_premium = $net_premium + $service_tax;

            $updatePremiumDetails = [
                // OD Tags
                "basic_od_premium" => $od_premium,
                "loading_amount" => 0,
                "final_od_premium" => $total_od,
                // TP Tags
                "basic_tp_premium" => $tp_premium,
                "final_tp_premium" => $total_tp,
                // Accessories
                "electric_accessories_value" => $electrical_amount,
                "non_electric_accessories_value" => $non_electrical_amount,
                "bifuel_od_premium" => $lpg_cng_amount,
                "bifuel_tp_premium" => $lpg_cng_tp_amount,
                // Addons
                "compulsory_pa_own_driver" => $pa_owner,
                "zero_depreciation" => $zero_dep_amount,
                "road_side_assistance" => $rsa,
                "imt_23" => $imt23,
                "consumable" => $consumable,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => $ncb_prot,
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
                "ll_paid_driver" => $liability,
                "ll_paid_conductor" => 0,
                "ll_paid_employee" => $legal_liability_to_employee,
                "ll_paid_cleaner" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0,
                "voluntary_excess" => 0,
                "tppd_discount" => $tppdPremium,
                "other_discount" => $discount_amount,
                "ncb_discount_premium" => $ncb_discount,
                // Final tags
                "net_premium" => $net_premium,
                "service_tax_amount" => $service_tax,
                "final_payable_amount" => $final_payable_premium,
            ];

            $updatePremiumDetails = array_map(function ($value) {
                return !empty($value) && is_numeric($value) ? round(abs($value), 2) : 0;
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
