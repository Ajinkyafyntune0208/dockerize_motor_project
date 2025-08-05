<?php

namespace App\Http\Controllers\Payment\Services\Car;
include_once app_path() . '/Helpers/CarWebServiceHelper.php';

use App\Http\Controllers\SyncPremiumDetail\Bike\HdfcErgoPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvBreakinStatus;
use App\Models\MasterPolicy;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use http\Env\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\SelectedAddons;
use App\Models\PaymentRequestResponse;
use App\Models\MasterPremiumType;
use App\Models\ProposalExtraFields;
use Carbon\Carbon;
use DateTime;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Support\Facades\Config;

class hdfcErgoPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse    
     * */
    public static function make($request)
    {
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->pluck('quote_id')
            ->first();

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        $enquiryId = customDecrypt($request->enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $requestData = getQuotation($enquiryId);
        //postinception for breakin case 
        if (
            in_array($premium_type, ['breakin', 'own_damage_breakin'])
            || ($requestData->previous_policy_type == 'Third-party'
            && !in_array($premium_type, ['third_party', 'third_party_breakin']))
        ) {
            $breakinDetails = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->first();
            if (!empty($breakinDetails) && $breakinDetails->breakin_status == STAGE_NAMES['INSPECTION_APPROVED']) {
                $response = self::postInspectionSubmit($user_proposal, $productData, $request);
                if (($response['status'] ?? false) != true) {
                    return response()->json([
                        'status' => false,
                        'msg' => $response['message'] ?? 'Something went wrong'
                    ]);
                }

                $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'Inspection is pending'
                ]);
            }
        }

        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y')
        {
            $productData = getProductDataByIc($request['policyId']);
            $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();

            $premium_type = DB::table('master_premium_type')
                ->where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

            if (in_array($premium_type, ['breakin', 'own_damage_breakin', 'short_term_3_breakin', 'short_term_6_breakin']) && $user_proposal->is_inspection_done == 'Y')
            {
                $is_zero_dep = 'NO';
                $is_roadside_assistance = 'NO';
                $is_key_replacement = 'NO';
                $is_engine_protector = 'NO';
                $is_ncb_protection = 'NO';
                $is_tyre_secure = 'NO';
                $is_consumable = 'NO';
                $is_return_to_invoice = 'NO';
                $is_loss_of_personal_belongings = 'NO';

                $selected_addons = SelectedAddons::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();

                if ($selected_addons)
                {
                    if ($selected_addons['applicable_addons'] != NULL && $selected_addons['applicable_addons'] != '')
                    {
                        foreach ($selected_addons['applicable_addons'] as $applicable_addon)
                        {
                            if ($applicable_addon['name'] == 'Zero Depreciation')
                            {
                                $is_zero_dep = 'YES';
                            }

                            if ($applicable_addon['name'] == 'Road Side Assistance')
                            {
                                $is_roadside_assistance = 'YES';
                            }

                            if ($applicable_addon['name'] == 'Key Replacement')
                            {
                                $is_key_replacement = 'YES';
                            }

                            if ($applicable_addon['name'] == 'Engine Protector')
                            {
                                $is_engine_protector = 'YES';
                            }

                            if ($applicable_addon['name'] == 'NCB Protection')
                            {
                                $is_ncb_protection = 'YES';
                            }

                            if ($applicable_addon['name'] == 'Tyre Secure')
                            {
                                $is_tyre_secure = 'YES';
                            }

                            if ($applicable_addon['name'] == 'Consumable')
                            {
                                $is_consumable = 'YES';
                            }

                            if ($applicable_addon['name'] == 'Return To Invoice')
                            {
                                $is_return_to_invoice = 'YES';
                            }

                            // if ($applicable_addon['name'] == 'Loss of Personal Belongings')
                            // {
                            //     $is_loss_of_personal_belongings = 'YES';
                            // }
                        }
                    }
                }

                $request_data = json_decode($user_proposal->additional_details_data, TRUE);
                $premium_request = $request_data['premium_request'];
                $proposal_request = $request_data['proposal_request'];

                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_POST_INSPECTION_PREMIUM_CALCULATION_URL'), $premium_request, 'hdfc_ergo', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Premium Calculation post Inspection',
                    'requestMethod' => 'post',
                    'enquiryId' => $user_proposal->user_product_journey_id,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                        'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                        'Content-Type' => 'application/json',
                        'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                        'Accept-Language' => 'en-US,en;q=0.5'
                    ]
                ]);
                $premium_response = $get_response['response'];

                if ($premium_response)
                {
                    $premium_response = json_decode($premium_response, TRUE);

                    if ($premium_response['Status'] == 200)
                    {
                        $basic_od = $premium_response['Data'][0]['BasicODPremium'] ?? 0;
                        $basic_tp = $premium_response['Data'][0]['BasicTPPremium'] ?? 0;
                        $electrical_accessories = 0;
                        $non_electrical_accessories = 0;
                        $lpg_cng_kit_od = 0;
                        $cpa = 0;
                        $unnamed_passenger = 0;
                        $ll_paid_driver = 0;
                        $pa_paid_driver = 0;
                        $zero_depreciation = 0;
                        $road_side_assistance = 0;
                        $ncb_protection = 0;
                        $engine_protection = 0;
                        $consumable = 0;
                        $key_replacement = 0;
                        $tyre_secure = 0;
                        $return_to_invoice = 0;
                        $loss_of_personal_belongings = 0;
                        $lpg_cng_kit_tp = $premium_response['Data'][0]['LpgCngKitTPPremium'] ?? 0;
                        $ncb_discount = $premium_response['Data'][0]['NewNcbDiscountAmount'] ?? 0;
                        $tppd_discount = $premium_response['Data'][0]['TppdDiscountAmount'] ?? 0;

                        if(isset($premium_response['Data'][0]['BuiltInLpgCngKitPremium']) && $premium_response['Data'][0]['BuiltInLpgCngKitPremium'] != 0.0)
                        {
                            $lpg_cng_kit_tp = $premium_response['Data'][0]['BuiltInLpgCngKitPremium'] ?? 0;
                        }

                        if (isset($premium_response['Data'][0]['AddOnCovers']))
                        {
                            foreach ($premium_response['Data'][0]['AddOnCovers'] as $addon_cover)
                            {
                                switch($addon_cover['CoverName'])
                                {
                                    case 'ElectricalAccessoriesIdv':
                                        $electrical_accessories = $addon_cover['CoverPremium'];
                                        break;
                                
                                    case 'NonelectricalAccessoriesIdv':
                                        $non_electrical_accessories = $addon_cover['CoverPremium'];
                                        break;
                                        
                                    case 'LpgCngKitIdvOD':
                                        $lpg_cng_kit_od = $addon_cover['CoverPremium'];
                                        break;

                                    case 'LpgCngKitIdvTP':
                                        $lpg_cng_kit_tp = $addon_cover['CoverPremium'];
                                        break;

                                    case 'PACoverOwnerDriver':
                                        $cpa = $addon_cover['CoverPremium'];
                                        break;

                                    case 'PACoverOwnerDriver3Year':
                                        if ($corporate_vehicles_quotes_request->business_type == 'newbusiness' && $corporate_vehicles_quotes_request->policy_type == 'comprehensive')
                                        {
                                            $cpa = $addon_cover['CoverPremium'];
                                        }
                                        break;

                                    case 'UnnamedPassenger':
                                        $unnamed_passenger = $addon_cover['CoverPremium'];
                                        break;

                                    case 'LLPaidDriver':
                                        $ll_paid_driver = $addon_cover['CoverPremium'];
                                        break;

                                    case 'PAPaidDriver':
                                        $pa_paid_driver = $addon_cover['CoverPremium'];
                                        break;

                                    case 'ZERODEP':
                                        $zero_depreciation = $is_zero_dep == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'EMERGASSIST':
                                        $road_side_assistance = $is_roadside_assistance == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'NCBPROT':
                                        $ncb_protection = $is_ncb_protection == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'ENGEBOX':
                                        $engine_protection = $is_engine_protector == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'COSTCONS':
                                        $consumable = $is_consumable == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'EMERGASSISTWIDER':
                                        $key_replacement = $is_key_replacement == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'TYRESECURE':
                                        $tyre_secure = $is_tyre_secure == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'RTI':
                                        $return_to_invoice = $is_return_to_invoice == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;
                                        
                                    case 'LOPB':
                                    case 'LOSSUSEDOWN':
                                        $loss_of_personal_belongings = $is_loss_of_personal_belongings == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    default:
                                        break;
                                }
                            }
                        }

                        $final_total_discount = $ncb_discount;
                        $total_od_amount = $basic_od - $final_total_discount;
                        $final_total_discount = $final_total_discount + $tppd_discount;
                        $total_tp_amount = $basic_tp + $ll_paid_driver + $lpg_cng_kit_tp + $pa_paid_driver + $cpa + $unnamed_passenger - $tppd_discount;
                        $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od + $zero_depreciation + $road_side_assistance + $ncb_protection + $consumable + $key_replacement + $tyre_secure + $engine_protection + $return_to_invoice + $loss_of_personal_belongings;

                        $final_net_premium = (int) $premium_response['Data'][0]['NetPremiumAmount'];
                        $service_tax = (int) $premium_response['Data'][0]['TaxAmount'];
                        $final_payable_amount = (int) $premium_response['Data'][0]['TotalPremiumAmount'];

                        $proposal_request['ProposalDetails']['NetPremiumAmount'] = $premium_response['Data'][0]['NetPremiumAmount'];
                        $proposal_request['ProposalDetails']['TaxAmount'] = $premium_response['Data'][0]['TaxAmount'];
                        $proposal_request['ProposalDetails']['TotalPremiumAmount'] = $premium_response['Data'][0]['TotalPremiumAmount'];

                        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_POST_INSPECTION_PROPOSAL_GENERATION_URL'), $proposal_request, 'hdfc_ergo', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Proposal Generation post Inspection',
                            'requestMethod' => 'post',
                            'enquiryId' => $user_proposal->user_product_journey_id,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'proposal',
                            'headers' => [
                                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                                'Content-Type' => 'application/json',
                                'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                                'Accept-Language' => 'en-US,en;q=0.5'
                            ]
                        ]);
                        $proposal_response = $get_response['response'];

                        if ($proposal_response)
                        {
                            $proposal_response = json_decode($proposal_response, TRUE);

                            if ($proposal_response['Status'] == 200)
                            {
                                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                                    ->where('user_proposal_id', $user_proposal->user_proposal_id)
                                    ->update([
                                        'policy_start_date' => date('d-m-Y', strtotime($proposal_response['Data']['NewPolicyStartDate'])),
                                        'policy_end_date' => date('d-m-Y', strtotime($proposal_response['Data']['NewPolicyEndDate'])),
                                        'proposal_no' => (int) $proposal_response['Data']['TransactionNo'],
                                        'customer_id' => (int) $proposal_response['Data']['QuoteNo'],
                                        'od_premium' => $total_od_amount,
                                        'tp_premium' => $total_tp_amount,
                                        'ncb_discount' => $ncb_discount, 
                                        'addon_premium' => $total_addon_amount,
                                        'cpa_premium' => $cpa,
                                        'total_discount' => $final_total_discount,
                                        'total_premium' => $final_net_premium,
                                        'service_tax_amount' => $service_tax,
                                        'final_payable_amount' => $final_payable_amount,
                                        'is_inspection_done' => 'Y'
                                    ]);

                                $user_proposal->proposal_no = (int) $proposal_response['Data']['TransactionNo'];
                            }
                            else
                            {
                                return response()->json([
                                    'status' => false,
                                    'msg' => 'An error occurred in post inspection proposal generation api.',
                                    'data'   => []
                                ]);
                            }
                        }
                        else
                        {
                            return response()->json([
                                'status' => false,
                                'msg' => 'An error occurred in post inspection proposal generation api.',
                                'data'   => []
                            ]);
                        }
                    }
                    else
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => 'An error occurred in post inspection premium calculation api.',
                            'data'   => []
                        ]);
                    }
                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'An error occurred in post inspection premium calculation api.',
                        'data'   => []
                    ]);
                }
            }

            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->update(['active' => 0]);

            $return_data = [
                'form_action' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_PAYMENT_REDIRECTION_URL'),
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'Trnsno' => $user_proposal->proposal_no,
                    'FeatureID' => 'S001',
                    'Checksum' => strtoupper(hash('sha512', config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY') . '|' . $user_proposal->proposal_no . '|' . config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN') . '|S001'))
                ]
            ];

            PaymentRequestResponse::insert([
                'quote_id' => $quote_log_id,
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'user_proposal_id' => $user_proposal->user_proposal_id,
                'ic_id' => $icId,
                'order_id' => $user_proposal->proposal_no,
                'proposal_no' => $user_proposal->proposal_no,
                'amount' => $user_proposal->final_payable_amount,
                'payment_url' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_PAYMENT_REDIRECTION_URL'),
                'return_url' => route('car.payment-confirm', ['hdfc_ergo']),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'xml_data' => json_encode($return_data),
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        else
        {
            $transaction_no = (int) (config('constants.IcConstants.hdfc_ergo.TRANSACTION_NO_SERIES_HDFC_ERGO_GIC_MOTOR').date('ymd').rand(10000, 99999));
            $str = 'TransactionNo=' . $transaction_no;
            $str .= '&TotalAmount=' . $user_proposal->final_payable_amount;
            $str .= '&AppID=' . config('constants.IcConstants.hdfc_ergo.APPID_PAYMENT_HDFC_ERGO_GIC_MOTOR');
            $str .= '&SubscriptionID=' . config('constants.IcConstants.hdfc_ergo.SubscriptionID_HDFC_ERGO_GIC_MOTOR');
            $str .= '&SuccessUrl=' . route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
            $str .= '&FailureUrl=' . route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]);
            $str .= '&Source=' . 'POST';
            $checksum_url = config('constants.IcConstants.hdfc_ergo.PAYMENT_CHECKSUM_LINK_HDFC_ERGO_GIC_MOTOR') . '?' . $str;
            $checksum = file_get_contents($checksum_url);

            $checksum_data = [
                'checksum_url' => $checksum_url,
                'checksum' => $checksum
            ];

            $check_sum_response = preg_replace('#</\w{1,10}:#', '</', preg_replace('#<\w{1,10}:#', '<', preg_replace('/ xsi[^=]*="[^"]*"/i', '$1', preg_replace('/ xmlns[^=]*="[^"]*"/i', '$1', preg_replace('/ xml:space[^=]*="[^"]*"/i', '$1', $checksum)))));
            $xmlString = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $check_sum_response);
            $checksum = strip_tags(trim($xmlString));
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->update(['active' => 0]);
            $payment_url = config('constants.IcConstants.hdfc_ergo.PAYMENT_GATEWAY_LINK_HDFC_ERGO_GIC_MOTOR');
            PaymentRequestResponse::insert([
                'quote_id' => $quote_log_id,
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'user_proposal_id' => $user_proposal->user_proposal_id,
                'ic_id' => $icId,
                'order_id' => $user_proposal->proposal_no,
                'amount' => $user_proposal->final_payable_amount,
                'payment_url' => $payment_url,
                'return_url' => route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'xml_data' => json_encode($checksum_data, JSON_UNESCAPED_SLASHES),
                'customer_id' => $transaction_no
            ]);

            $return_data = [
                'form_action' => $payment_url,
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'trnsno' => $transaction_no,//$user_proposal->user_product_journey_id,
                    'Amt' => $user_proposal->final_payable_amount,
                    'Appid' => config('constants.IcConstants.hdfc_ergo.APPID_PAYMENT_HDFC_ERGO_GIC_MOTOR'),
                    'Subid' => config('constants.IcConstants.hdfc_ergo.SubscriptionID_HDFC_ERGO_GIC_MOTOR'),
                    'Chksum' => $checksum,
                    'Src' => 'POST',
                    'Surl' => route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                    'Furl' => route('car.payment-confirm', ['hdfc_ergo', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                ],
            ];
        }

        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];

        updateJourneyStage($data);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y') {
            if (isset($request->hdmsg)) {
                PaymentRequestResponse::where('proposal_no', $request->hdmsg)
                    ->update([
                        'response' => $request->all()
                    ]);

                $proposal = UserProposal::where('proposal_no', $request->hdmsg)
                    ->first();

                if ($proposal) {
                    $policy_generation_request = [
                        'TransactionNo' => $request->hdmsg,
                        'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
                        'UniqueRequestID' => $proposal->unique_proposal_id
                    ];

                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_POLICY_GENERATION_URL'), $policy_generation_request, 'hdfc_ergo', [
                        'section' => $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
                        'method' => 'Policy Generation',
                        'requestMethod' => 'post',
                        'enquiryId' => $proposal->user_product_journey_id,
                        'productName' => $proposal->quote_log->master_policy->master_product->product_name,
                        'transaction_type' => 'proposal',
                        'headers' => [
                            'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                            'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                            'Content-Type' => 'application/json',
                            'User-Agent' => $request->userAgent(),
                            'Accept-Language' => 'en-US,en;q=0.5'
                        ]
                    ]);
                    $policy_generation_response = $get_response['response'];

                    if ($policy_generation_response) {
                        $policy_generation_response = json_decode($policy_generation_response, TRUE);

                        if ($policy_generation_response['Status'] == 200) {
                            if ($policy_generation_response['Data']['PaymentStatus'] == 'SPD' && $policy_generation_response['Data']['PolicyNumber'] != "null") {
                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);

                                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                                    ->where('active', 1)
                                    ->update([
                                        'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);

                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $proposal->user_proposal_id],
                                    [
                                        'policy_start_date' => $proposal->policy_start_date,
                                        'premium' => $proposal->final_payable_amount,
                                        'policy_number' => $policy_generation_response['Data']['PolicyNumber'],
                                        'Status' => 'SUCCESS',
                                        'created_on' => date('Y-m-d H:i:s')
                                    ]
                                );

                                $policy_document_request = [
                                    'AgentCd' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
                                    'PolicyNo' => $policy_generation_response['Data']['PolicyNumber']
                                ];

                                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_POLICY_DOCUMENT_DOWNLOAD_URL'), $policy_document_request, 'hdfc_ergo', [
                                    'section' => $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
                                    'method' => 'Policy Document Download',
                                    'requestMethod' => 'post',
                                    'enquiryId' => $proposal->user_product_journey_id,
                                    'productName' => $proposal->quote_log->master_policy->master_product->product_name,
                                    'transaction_type' => 'proposal',
                                    'headers' => [
                                        'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                                        'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                                        'Content-Type' => 'application/json',
                                        'User-Agent' => $request->userAgent(),
                                        'Accept-Language' => 'en-US,en;q=0.5'
                                    ]
                                ]);
                                $policy_document_response = $get_response['response'];

                                if ($policy_document_response) {
                                    $policy_document_response = json_decode($policy_document_response, TRUE);

                                    if (isset($policy_document_response['Status']) && $policy_document_response['Status'] == 200 && $policy_document_response['PdfBytes'] != NULL) {
                                        $pdfData = base64_decode($policy_document_response['PdfBytes']);
                                        if (!checkValidPDFData($pdfData)) {
                                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
                                        }
                                        Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) . '.pdf', $pdfData);

                                        $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) . '.pdf';

                                        updateJourneyStage([
                                            'user_product_journey_id' => $proposal->user_product_journey_id,
                                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                                        ]);

                                        PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                            ->update([
                                                'pdf_url' => $pdf_url
                                            ]);

                                        $enquiryId = $proposal->user_product_journey_id;
                                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                                    } elseif (isset($policy_document_response['status']) && $policy_document_response['status'] == 200 && $policy_document_response['pdfbytes'] != NULL) {
                                        $pdfData = base64_decode($policy_document_response['pdfbytes']);
                                        if (!checkValidPDFData($pdfData)) {
                                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
                                        }
                                        Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) . '.pdf', $pdfData);

                                        $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) . '.pdf';

                                        updateJourneyStage([
                                            'user_product_journey_id' => $proposal->user_product_journey_id,
                                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                                        ]);

                                        PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                            ->update([
                                                'pdf_url' => $pdf_url
                                            ]);

                                        $enquiryId = $proposal->user_product_journey_id;
                                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                                    }
                                }

                                $enquiryId = $proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                            } else {
                                updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                                ]);

                                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                                    ->where('active', 1)
                                    ->update([
                                        'status' => STAGE_NAMES['PAYMENT_FAILED'],
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                            }
                        }
                    }
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }
            }

            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        } else {
            if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_CAR') == 'Y') {
                $user_proposal = UserProposal::find($request->user_proposal_id);
                try {
                    $request_data = $request->all();
                    $requestData = getQuotation($user_proposal->user_product_journey_id);
                    $enquiryId = $user_proposal->user_product_journey_id;
                    $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->first();
                    $productData = getProductDataByIc($master_policy_id->master_policy_id);
                    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
                    if ($premium_type == 'third_party_breakin') {
                        $premium_type = 'third_party';
                    }
                    if ($premium_type == 'own_damage_breakin') {
                        $premium_type = 'own_damage';
                    }

                    //                $ProductCode = '2311';
                    //                if ($premium_type == "third_party") {
                    //                    $ProductCode = '2319';
                    //
                    //                }

                    $ProductCode = $user_proposal->product_code;

                    switch ($requestData->business_type) {

                        case 'rollover':
                            $business_type = 'Roll Over';
                            break;

                        case 'newbusiness':
                            $business_type = 'New Business';
                            break;

                        default:
                            $business_type = $requestData->business_type;
                            break;
                    }


                    PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                        ->where('active', 1)
                        ->update([
                            'response' => $request->hdnmsg,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    if ($request->hdnmsg != null) {
                        $response = explode('|', $request->hdnmsg);
                        $MerchantID = $response[0];
                        $TransactionNo = $response[1];
                        $TransctionRefNo = $response[1];
                        $BankReferenceNo = $response[3];
                        $TxnAmount = $response[4];
                        $BankCode = $response[5];
                        $IsSIOpted = $response[6];
                        $PaymentMode = $response[7];
                        $PG_Remarks = $response[8];
                        $PaymentStatus = $response[9];
                        $TransactionDate = $response[10];
                        $AppID = $response[11];
                        $Checksum = $response[12];
                        $status = $PaymentStatus == 'SPD' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'];

                        PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                            ->where('active', 1)
                            ->update([
                                'status' => $status,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => $status
                        ]);

                        if ($status == STAGE_NAMES['PAYMENT_FAILED']) {
                            $enquiryId = $user_proposal->user_product_journey_id;
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                        }
                        $proposal_array = json_decode($user_proposal->additional_details_data, true);
                        $transactionid = $proposal_array['TransactionID'];
                        $proposal_array['proposal_no'] = $user_proposal->proposal_no;

                        $proposal_array = [
                            'GoGreen' => false,
                            'IsReadyToWait' => null,
                            'PolicyCertifcateNo' => null,
                            'PolicyNo' => null,
                            'Inward_no' => null,
                            'Request_IP' => null,
                            'Customer_Details' => null,
                            'Policy_Details' => null,
                            'Req_GCV' => null,
                            'Req_MISD' => null,
                            'Req_PCV' => null,
                            'IDV_DETAILS' => null,
                            'Req_ExtendedWarranty' => null,
                            'Req_Policy_Document' => null,
                            'Req_PEE' => null,
                            'Req_TW' => null,
                            'Req_GHCIP' => null,
                            'Req_PolicyConfirmation' => null
                        ];

                        $additionData = [
                            'type' => 'gettoken',
                            'method' => 'tokenGeneration',
                            'section' => 'car',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name . " ($business_type)",
                            'transaction_type' => 'proposal',
                            'PRODUCT_CODE' => $ProductCode, // config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                            'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                            'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                            'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                        ];

                        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);
                        $token = $get_response['response'];
                        $token_data = json_decode($token, TRUE);
                        if (isset($token_data['Authentication']['Token'])) {
                            $additionData = [
                                'type' => 'PremiumCalculation',
                                'method' => 'Policy Generation',
                                'requestMethod' => 'post',
                                'section' => 'car',
                                'enquiryId' => $user_proposal->user_product_journey_id,
                                'productName' => $productData->product_name . " ($business_type)",
                                'TOKEN' => $token_data['Authentication']['Token'],
                                'transaction_type' => 'proposal',
                                'PRODUCT_CODE' => $ProductCode,
                                config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                                'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                                'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                            ];
                            $proposal_array['Proposal_no'] =  $user_proposal->proposal_no;
                            $proposal_array['TransactionID'] = $transactionid;
                            if (config('IC.HDFC_ERGO.CIS_DOCUMENT_ENABLE') == 'Y') {
                                $proposal_array['CIS_Flag'] = 'Y';
                            }
                            $proposal_array['Payment_Details'] = [
                                'GC_PaymentID' => null,
                                'BANK_NAME' => 'BIZDIRECT',
                                'BANK_BRANCH_NAME' => 'Andheri',
                                'Elixir_bank_code' => null,
                                'PAYMENT_MODE_CD' => 'EP',
                                'IsPolicyIssued' => "0",
                                'IsReserved' => 0,
                                'OTC_Transaction_No' => "",
                                'PAYER_TYPE' => 'DEALER',
                                'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount,
                                'INSTRUMENT_NUMBER' => $TransctionRefNo,
                                'PAYMENT_DATE' => date('d/m/Y'),
                            ];
                            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                            $getpremium = $get_response['response'];

                            //                print_r(json_encode([config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_PROPOSAL'), $additionData, $proposal_array, json_decode($getpremium)]));
                            //
                            $arr_proposal = json_decode($getpremium, true);
                            if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null) {
                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id],
                                    [
                                        'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                        'policy_start_date' => $user_proposal->policy_start_date,
                                        'premium' => $user_proposal->final_payable_amount,
                                        'created_on' => date('Y-m-d H:i:s')
                                    ]
                                );
                                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                                    ->where('user_proposal_id', $user_proposal->user_proposal_id)
                                    ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
                                $pdf_array = [
                                    'TransactionID' => $arr_proposal['TransactionID'],
                                    'Req_Policy_Document' => [
                                        'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                    ],
                                ];
                                PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                                    ->where('active', 1)
                                    ->update([
                                        'order_id' => $arr_proposal['TransactionID'],
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                                $additionData = [
                                    'type' => 'PdfGeneration',
                                    'method' => 'Pdf Generation',
                                    'requestMethod' => 'post',
                                    'section' => 'car',
                                    'productName' => $productData->product_name . " ($business_type)",
                                    'enquiryId' => $user_proposal->user_product_journey_id,
                                    'TOKEN' => $token_data['Authentication']['Token'],
                                    'transaction_type' => 'proposal',
                                    'PRODUCT_CODE' => $ProductCode,
                                    config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                                    'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),

                                ];
                                $policy_array = [
                                    "TransactionID" => $arr_proposal['TransactionID'],
                                    "Req_Policy_Document" => [
                                        "Policy_Number" => $arr_proposal['Policy_Details']['PolicyNumber']
                                    ]
                                ];
                                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
                                $pdf_data = $get_response['response'];

                                if ($pdf_data === null || $pdf_data == '') {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                }

                                $pdf_response = json_decode($pdf_data, TRUE);

                                if ($pdf_response === null || empty($pdf_response)) {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                }


                                if ($pdf_response['StatusCode'] == 200) {
                                    $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                                    ]);

                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $user_proposal->user_proposal_id],
                                        [
                                            'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                            'ic_pdf_url' => '',
                                            'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                                            'status' => STAGE_NAMES['POLICY_ISSUED']
                                        ]
                                    );

                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                } else {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);

                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $user_proposal->user_proposal_id],
                                        [
                                            'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                            'ic_pdf_url' => '',
                                            'pdf_url' => '',
                                            'status' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                        ]
                                    );
                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                }
                            } else {
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }
                        } else {
                            $enquiryId = $user_proposal->user_product_journey_id;
                            // return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
                            // return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                        }
                    } else {
                        $enquiryId = $user_proposal->user_product_journey_id;
                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                    }
                } catch (\Exception $e) {
                    $enquiryId = $user_proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                }
            } else {
                $user_proposal = UserProposal::find($request->user_proposal_id);
                try {
                    $request_data = $request->all();
                    $requestData = getQuotation($user_proposal->user_product_journey_id);
                    $enquiryId = $user_proposal->user_product_journey_id;
                    $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->first();
                    $productData = getProductDataByIc($master_policy_id->master_policy_id);
                    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
                    if ($premium_type == 'third_party_breakin') {
                        $premium_type = 'third_party';
                    }
                    if ($premium_type == 'own_damage_breakin') {
                        $premium_type = 'own_damage';
                    }

                    //                $ProductCode = '2311';
                    //                if ($premium_type == "third_party") {
                    //                    $ProductCode = '2319';
                    //
                    //                }

                    $ProductCode = $user_proposal->product_code;

                    switch ($requestData->business_type) {

                        case 'rollover':
                            $business_type = 'Roll Over';
                            break;

                        case 'newbusiness':
                            $business_type = 'New Business';
                            break;

                        default:
                            $business_type = $requestData->business_type;
                            break;
                    }


                    PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                        ->where('active', 1)
                        ->update([
                            'response' => $request->hdnmsg,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    if ($request->hdnmsg != null) {
                        $response = explode('|', $request->hdnmsg);
                        $MerchantID = $response[0];
                        $TransactionNo = $response[1];
                        $TransctionRefNo = $response[1];
                        $BankReferenceNo = $response[3];
                        $TxnAmount = $response[4];
                        $BankCode = $response[5];
                        $IsSIOpted = $response[6];
                        $PaymentMode = $response[7];
                        $PG_Remarks = $response[8];
                        $PaymentStatus = $response[9];
                        $TransactionDate = $response[10];
                        $AppID = $response[11];
                        $Checksum = $response[12];
                        $status = $PaymentStatus == 'SPD' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'];

                        PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                            ->where('active', 1)
                            ->update([
                                'status' => $status,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => $status
                        ]);

                        if ($status == STAGE_NAMES['PAYMENT_FAILED']) {
                            $enquiryId = $user_proposal->user_product_journey_id;
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                        }
                        $proposal_array = json_decode($user_proposal->additional_details_data, true);
                        $transactionid = $proposal_array['TransactionID'];

                        $additionData = [
                            'type' => 'gettoken',
                            'method' => 'tokenGeneration',
                            'section' => 'car',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name . " ($business_type)",
                            'transaction_type' => 'proposal',
                            'PRODUCT_CODE' => $ProductCode, // config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                            'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                            'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                            'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                        ];
                        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);
                        $token = $get_response['response'];
                        $token_data = json_decode($token, TRUE);
                        if (isset($token_data['Authentication']['Token'])) {
                            $additionData = [
                                'type' => 'PremiumCalculation',
                                'method' => 'Policy Generation',
                                'requestMethod' => 'post',
                                'section' => 'car',
                                'enquiryId' => $user_proposal->user_product_journey_id,
                                'productName' => $productData->product_name . " ($business_type)",
                                'TOKEN' => $token_data['Authentication']['Token'],
                                'transaction_type' => 'proposal',
                                'PRODUCT_CODE' => $ProductCode,
                                config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                                'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                                'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                            ];
                            $proposal_array['Proposal_no'] =  $user_proposal['proposal_no'];
                            $proposal_array['Payment_Details'] = [
                                'GC_PaymentID' => null,
                                'BANK_NAME' => 'BIZDIRECT',
                                'BANK_BRANCH_NAME' => 'Andheri',
                                'Elixir_bank_code' => null,
                                'PAYMENT_MODE_CD' => 'EP',
                                'IsPolicyIssued' => "0",
                                'IsReserved' => 0,
                                'OTC_Transaction_No' => "",
                                'PAYER_TYPE' => 'DEALER',
                                'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount,
                                'INSTRUMENT_NUMBER' => $TransctionRefNo,
                                'PAYMENT_DATE' => date('d/m/Y'),
                            ];
                            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                            $getpremium = $get_response['response'];

                            //                print_r(json_encode([config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_PROPOSAL'), $additionData, $proposal_array, json_decode($getpremium)]));
                            //
                            $arr_proposal = json_decode($getpremium, true);
                            if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null) {
                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal->user_proposal_id],
                                    [
                                        'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                        'policy_start_date' => $user_proposal->policy_start_date,
                                        'premium' => $user_proposal->final_payable_amount,
                                        'created_on' => date('Y-m-d H:i:s')
                                    ]
                                );
                                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                                    ->where('user_proposal_id', $user_proposal->user_proposal_id)
                                    ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
                                $pdf_array = [
                                    'TransactionID' => $arr_proposal['TransactionID'],
                                    'Req_Policy_Document' => [
                                        'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                    ],
                                ];
                                PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                                    ->where('active', 1)
                                    ->update([
                                        'order_id' => $arr_proposal['TransactionID'],
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                                $additionData = [
                                    'type' => 'PdfGeneration',
                                    'method' => 'Pdf Generation',
                                    'requestMethod' => 'post',
                                    'section' => 'car',
                                    'productName' => $productData->product_name . " ($business_type)",
                                    'enquiryId' => $user_proposal->user_product_journey_id,
                                    'TOKEN' => $token_data['Authentication']['Token'],
                                    'transaction_type' => 'proposal',
                                    'PRODUCT_CODE' => $ProductCode,
                                    config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                                    'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),

                                ];
                                $policy_array = [
                                    "TransactionID" => $arr_proposal['TransactionID'],
                                    "Req_Policy_Document" => [
                                        "Policy_Number" => $arr_proposal['Policy_Details']['PolicyNumber']
                                    ]
                                ];
                                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
                                $pdf_data = $get_response['response'];

                                if ($pdf_data === null || $pdf_data == '') {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                }

                                $pdf_response = json_decode($pdf_data, TRUE);

                                if ($pdf_response === null || empty($pdf_response)) {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                }


                                if ($pdf_response['StatusCode'] == 200) {
                                    $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                                    ]);

                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $user_proposal->user_proposal_id],
                                        [
                                            'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                            'ic_pdf_url' => '',
                                            'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                                            'status' => STAGE_NAMES['POLICY_ISSUED']
                                        ]
                                    );

                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));

                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                } else {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);

                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $user_proposal->user_proposal_id],
                                        [
                                            'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                                            'ic_pdf_url' => '',
                                            'pdf_url' => '',
                                            'status' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                        ]
                                    );
                                    $enquiryId = $user_proposal->user_product_journey_id;
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                }
                            } else {
                                $enquiryId = $user_proposal->user_product_journey_id;
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }
                        } else {
                            $enquiryId = $user_proposal->user_product_journey_id;
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                            // return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                        }
                    } else {
                        $enquiryId = $user_proposal->user_product_journey_id;
                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                    }
                } catch (\Exception $e) {
                    $enquiryId = $user_proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                }
            }
        }
    }

    public static function generatePdf($request)
    {
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y')
        {
            $user_product_journey_id = customDecrypt($request->enquiryId);

            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)
                            ->first();

            if ($proposal)
            {
                    $policy_details = DB::table('payment_request_response as prr')
                    ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'prr.user_proposal_id')
                    ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
                    ->where('prr.user_product_journey_id', $user_product_journey_id)
                    ->where(array('prr.active' => 1, 'prr.status' => STAGE_NAMES['PAYMENT_SUCCESS']))
                    ->select(
                        'up.user_proposal_id',
                        'up.user_proposal_id',
                        'up.proposal_no',
                        'up.unique_proposal_id',
                        'pd.policy_number',
                        'pd.pdf_url',
                        'pd.ic_pdf_url',
                        'prr.order_id'
                    )
                    ->first();

                    if(($policy_details->pdf_url ?? '') != '')
                    {
                        return response()->json([
                            'status' => true,
                            'msg' => 'success',
                            'data' => [
                                'policy_number' => $policy_details->policy_number,
                                'pdf_link' => file_url($policy_details->pdf_url)
                            ]
                        ]);
                    }
                    $payment_check_status = self::check_payment_status($user_product_journey_id, $proposal,$request);
                    if(!$payment_check_status['status'])
                    {
                        $pdf_response_data = [
                            'status' => false,
                            'msg'    => $payment_check_status['msg']
                        ];
                        return response()->json($pdf_response_data);
                    }

                
                $policy_generation_request = [
                    'TransactionNo' => $proposal->proposal_no,
                    'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
                    'UniqueRequestID' => $proposal->unique_proposal_id
                ];

                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_POLICY_GENERATION_URL'), $policy_generation_request, 'hdfc_ergo', [
                    'section' => $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
                    'method' => 'Policy Generation',
                    'requestMethod' => 'post',
                    'enquiryId' => $proposal->user_product_journey_id,
                    'productName' => $proposal->quote_log->master_policy->master_product->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                        'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                        'Content-Type' => 'application/json',
                        'User-Agent' => $request->userAgent(),
                        'Accept-Language' => 'en-US,en;q=0.5'
                    ]
                ]);
                $policy_generation_response = $get_response['response'];

                if ($policy_generation_response)
                {
                    $policy_generation_response = json_decode($policy_generation_response, TRUE);

                    if ($policy_generation_response['Status'] == 200)
                    {
                        if ($policy_generation_response['Data']['PaymentStatus'] == 'SPD' && $policy_generation_response['Data']['PolicyNumber'] != "null")
                        {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);

                            PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active', 1)
                                ->update([
                                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_start_date' => $proposal->policy_start_date,
                                    'premium' => $proposal->final_payable_amount,
                                    'policy_number' => $policy_generation_response['Data']['PolicyNumber'],
                                    'Status' => 'SUCCESS',
                                    'created_on' => date('Y-m-d H:i:s')
                                ]);

                            $policy_document_request = [
                                'AgentCd' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
                                'PolicyNo' => $policy_generation_response['Data']['PolicyNumber']
                            ];

                            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_POLICY_DOCUMENT_DOWNLOAD_URL'), $policy_document_request, 'hdfc_ergo', [
                                'section' => $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
                                'method' => 'Policy Document Download',
                                'requestMethod' => 'post',
                                'enquiryId' => $proposal->user_product_journey_id,
                                'productName' => $proposal->quote_log->master_policy->master_product->product_name,
                                'transaction_type' => 'proposal',
                                'headers' => [
                                    'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                                    'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                                    'Content-Type' => 'application/json',
                                    'User-Agent' => $request->userAgent(),
                                    'Accept-Language' => 'en-US,en;q=0.5'
                                ]
                            ]);
                            $policy_document_response = $get_response['response'];

                            if ($policy_document_response)
                            {
                                $policy_document_response = json_decode($policy_document_response, TRUE);

                                if (isset($policy_document_response['Status']) && $policy_document_response['Status'] == 200 && $policy_document_response['PdfBytes'] != NULL)
                                {
                                    $pdfData = base64_decode($policy_document_response['PdfBytes']);
                                    if(!checkValidPDFData($pdfData)) {
                                        return [
                                            'status' => false,
                                            'msg' => 'PDF generation Failed...! Not a valid PDF data.'
                                        ];
                                    }
                                    Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) . '.pdf', $pdfData);

                                    $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id). '.pdf';

                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                                    ]);

                                    PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                        ->update([
                                            'pdf_url' => $pdf_url
                                        ]);

                                    return response()->json([
                                        'status' => true,
                                        'msg' => 'success',
                                        'data' => [
                                            'policy_number' => $proposal->policy_details->policy_number,
                                            'pdf_link' => file_url($pdf_url)
                                        ]
                                    ]);
                                }
                                elseif (isset($policy_document_response['status']) && $policy_document_response['status'] == 200 && $policy_document_response['pdfbytes'] != NULL)
                                {
                                    $pdfData = base64_decode($policy_document_response['pdfbytes']);
                                    if(!checkValidPDFData($pdfData)) {
                                        return [
                                            'status' => false,
                                            'msg' => 'PDF generation Failed...! Not a valid PDF data.'
                                        ];
                                    }
                                    Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) . '.pdf', $pdfData);

                                    $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id). '.pdf';

                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                                    ]);

                                    PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                        ->update([
                                            'pdf_url' => $pdf_url
                                        ]);

                                    return response()->json([
                                        'status' => true,
                                        'msg' => 'success',
                                        'data' => [
                                            'policy_number' => $proposal->policy_details->policy_number,
                                            'pdf_link' => file_url($pdf_url)
                                        ]
                                    ]);
                                }
                                else
                                {
                                    return response()->json([
                                        'status' => false,
                                        'msg' => $policy_document_response['ErrMsg'] ?? 'An error occured while generating pdf'
                                    ]);
                                }
                            }
                            else
                            {
                                return response()->json([
                                    'status' => false,
                                    'msg' => 'An error occured while generating pdf'
                                ]);
                            }
                        }
                        else
                        {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['PAYMENT_FAILED']
                            ]);

                            PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('active', 1)
                                ->update([
                                    'status' => STAGE_NAMES['PAYMENT_FAILED'],
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);

                            return response()->json([
                                'status' => false,
                                'msg' => 'Payment is unsuccessful'
                            ]);
                        }
                    }
                    else
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => $policy_generation_response['Message']
                        ]);
                    }
                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'An error occured while generating policy'
                    ]);
                }
            }
            else
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Proposal Details not available'
                ]);
            }
        }
        else
        {
            if(config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_CAR') == 'Y')
            {
                return self::generatePdfNewFlow($request);
            }

            $enquiryId = customDecrypt($request->enquiryId);
            $policy_details = PaymentRequestResponse::leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'payment_request_response.user_proposal_id')
                ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'payment_request_response.user_product_journey_id')
                ->where('payment_request_response.user_product_journey_id', $enquiryId)
                ->where(array('payment_request_response.active' => 1, 'payment_request_response.status' => STAGE_NAMES['PAYMENT_SUCCESS']))
                ->select(
                    'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no', 'up.unique_proposal_id',
                    'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url', 'payment_request_response.order_id', 'payment_request_response.response'
                )
                ->first();
            if ($policy_details == null) {
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'Data Not Found',
                    'data' => []
                ];
                return response()->json($pdf_response_data);
            }
            $response = explode('|', $policy_details->response);
            $TransctionRefNo = $response[1];
            $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            $productData = getProductDataByIc($request->master_policy_id);
            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

        //            $ProductCode = '2311';
        //            if ($premium_type == "third_party") {
        //                $ProductCode = '2319';
        //            }
            $ProductCode = $user_proposal->product_code;
            //        $enquiryId=$enquiryId."1";
            $additionData = [
                'type' => 'gettoken',
                'method' => 'tokenGeneration',
                'section' => 'car',
                'enquiryId' => $enquiryId,
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode,// config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                'TRANSACTIONID' => $enquiryId,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
            ];

            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);
            $token = $get_response['response'];
            $token_data = json_decode($token, TRUE);
            //        return $token_data;
            if (!isset($token_data['Authentication']['Token'])) {
                return [
                    "status" => false,
                    'message' => "Token Generation Failed",
                    //                'Response' => $token_data,
                ];
            }
            $additionData = [
                'type' => 'PremiumCalculation',
                'method' => 'Policy Generation',
                'requestMethod' => 'post',
                'section' => 'car',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'TOKEN' => $token_data['Authentication']['Token'],
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                'TRANSACTIONID' => $enquiryId,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
            ];
            if($policy_details->policy_number=='') {

                $proposal_array = json_decode($user_proposal->additional_details_data, true);
                $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id',$user_proposal->user_product_journey_id)->get()->toArray();

                foreach($PaymentRequestResponse as $p_key => $p_value)
                {
                    if(empty($p_value['customer_id']))
                    {
                        continue;
                    }
                    $proposal_array['Payment_Details'] = [
                        'GC_PaymentID' => null,
                        'BANK_NAME' => 'BIZDIRECT',
                        'BANK_BRANCH_NAME' => 'Andheri',
                        'PAYMENT_MODE_CD' => 'EP',
                        'PAYER_TYPE' => 'DEALER',
                        'PAYMENT_AMOUNT' => $user_proposal->final_payable_amount,
                        'INSTRUMENT_NUMBER' => $p_value['customer_id'],
                        'PAYMENT_DATE' => date('d-m-Y', strtotime($p_value['updated_at'])),
                    ];
            
                    $additionData['method'] = 'Policy Generation '.$p_value['customer_id'];

                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                    $getpremium = $get_response['response'];
                    $arr_proposal = json_decode($getpremium, true);
                    if (isset($arr_proposal['StatusCode']) && $arr_proposal['StatusCode'] == 200 && !empty($arr_proposal['Policy_Details']['PolicyNumber'])) 
                    {
                        $arr_proposal['Error'] = null;
                        $arr_proposal['Warning'] = null;
                        break;
                    }

                    unset($proposal_array['Payment_Details']);
                }

                if (empty($arr_proposal['Policy_Details']['PolicyNumber'])) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Policy number not found'
                    ]);
                }
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                        'policy_start_date' => $user_proposal->policy_start_date,
                        'premium' => $user_proposal->final_payable_amount,
                        'created_on' => date('Y-m-d H:i:s')
                    ]
                );
                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('user_proposal_id', $user_proposal->user_proposal_id)
                    ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
                $pdf_array = [
                    'TransactionID' => $arr_proposal['TransactionID'],
                    'Req_Policy_Document' => [
                        'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                    ],
                ];
                $user_proposal->proposal_no = $arr_proposal['TransactionID'];
                $policy_details->policy_number=$arr_proposal['Policy_Details']['PolicyNumber'];
                PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'order_id' => $arr_proposal['TransactionID'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                $arr_proposal['StatusCode']=200;
                $arr_proposal['Error']=null;
                $arr_proposal['Warning']=null;
            }
            if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null && $arr_proposal['Warning'] == null) {

                $additionData = [
                    'type' => 'PdfGeneration',
                    'method' => 'Pdf Generation',
                    'requestMethod' => 'post',
                    'section' => 'car',
                    'enquiryId' => $user_proposal->user_product_journey_id,
                    'TOKEN' => $token_data['Authentication']['Token'],
                    'transaction_type' => 'proposal',
                    'PRODUCT_CODE' => $ProductCode, config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                    'TRANSACTIONID' => $enquiryId,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),

                ];
                $policy_array = [
                    "TransactionID" => $user_proposal->proposal_no,
                    "Req_Policy_Document" => [
                        "Policy_Number" => $policy_details->policy_number
                    ]
                ];
                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
                $pdf_data = $get_response['response'];
                $pdf_response = json_decode($pdf_data, TRUE);
                //Generate Policy PDF - Start

                if ($pdf_response['StatusCode'] == 200) {
                    $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);

                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $user_proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_details->policy_number,
                            'ic_pdf_url' => '',
                            'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                            'status' => 'SUCCESS'
                        ]
                    );
                    return response()->json([
                        'status' => true,
                        'msg' => 'PDF Generated Successfully',
                        'data' => [
                                    'policy_number' => $policy_details->policy_number,
                                    'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf')
                                ]
                    ]);
                } else {
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);

                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $user_proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_details->policy_number,
                            'ic_pdf_url' => '',
                            'pdf_url' => '',
                            'status' => 'SUCCESS'
                        ]
                    );
                    return response()->json([
                        'status' => false,
                        'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                        'data' => [
                                    'policy_number' => $policy_details->policy_number,
                                    'pdf_link'      => ''
                                ]
                    ]);
                }

            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'Policy Generation Failed',
                    'data' => []
                ]);
            }
        }
    }

    public static function check_payment_status($enquiry_id, $proposal,$request)
    {
        $payment_status_request = [
            'TransactionNo' => $proposal->proposal_no,
            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
            'Checksum' => strtoupper(hash('sha512', config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY') . '|' . $proposal->proposal_no . '|' . config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN') . '|S001'))
        ];

        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_PAYMENT_STATUS_CHECK_URL'), $payment_status_request, 'hdfc_ergo', [
            'section' => $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
            'method' => 'Payment Status check',
            'requestMethod' => 'post',
            'enquiryId' => $proposal->user_product_journey_id,
            'productName' => $proposal->quote_log->master_policy->master_product->product_name,
            'transaction_type' => 'proposal',
            'headers' => [
                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                'Content-Type' => 'application/json',
                'User-Agent' => $request->userAgent(),
                'Accept-Language' => 'en-US,en;q=0.5'
            ]
        ]);
        $payment_status_response = $get_response['response'];

        if ($payment_status_response)
        {
            $payment_status_response = json_decode($payment_status_response, TRUE);

            if (isset($payment_status_response['VENDOR_AUTH_STATUS']) && $payment_status_response['VENDOR_AUTH_STATUS'] == 'SPD')
            {

                $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)
                    ->first();
                $additional_details_data = json_encode($payment_status_response);
                UserProposal::where('user_product_journey_id', $enquiry_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'additional_details_data' => $additional_details_data,
                    ]);

                return [
                    'status'    => true
                ];
            }else
            {
                return [
                    'status' => false,
                    'msg' => $payment_status_response['Error Message'] ?? 'Unable to check the payment status. Please try after sometime.'
                ];

            }

        }else
        {
            return [
                'status' => false,
                'msg' => 'An error occured while generating policy'
            ];

        }
    }

    public static function generatePdfNewFlow($request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $policy_details = PaymentRequestResponse::leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'payment_request_response.user_proposal_id')
        ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'payment_request_response.user_product_journey_id')
        ->where('payment_request_response.user_product_journey_id', $enquiryId)
        ->where(array('payment_request_response.active' => 1))
        ->select(
            'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no', 'up.unique_proposal_id',
            'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url', 'payment_request_response.order_id', 'payment_request_response.response'
        )
        ->first();
        if ($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg' => 'Data Not Found',
                'data' => []
            ];
            return response()->json($pdf_response_data);
        }
        // $response = explode('|', $policy_details->response);
        // $TransctionRefNo = $response[1];
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $productData = getProductDataByIc($request->master_policy_id);
        // $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
        //     ->pluck('premium_type_code')
        //     ->first();

        //            $ProductCode = '2311';
        //            if ($premium_type == "third_party") {
        //                $ProductCode = '2319';
        //            }
        $ProductCode = $user_proposal->product_code;
        $additionData = [
            'type' => 'gettoken',
            'method' => 'tokenGeneration',
            'section' => 'car',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode,// config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
            'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
            'TRANSACTIONID' => $enquiryId,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
        ];

        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);
        $token = $get_response['response'];
        $token_data = json_decode($token, TRUE);
        if (!isset($token_data['Authentication']['Token']))
        {
            return [
                "status" => false,
                'message' => "Token Generation Failed"
            ];
        }
        $additionData = [
            'type' => 'PremiumCalculation',
            'method' => 'Policy Generation',
            'requestMethod' => 'post',
            'section' => 'car',
            'enquiryId' => $user_proposal->user_product_journey_id,
            'TOKEN' => $token_data['Authentication']['Token'],
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
            'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
            'TRANSACTIONID' => $enquiryId,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
        ];
        if($policy_details->policy_number == '')
        {
            $proposal_array = json_decode($user_proposal->additional_details_data, true);
            $PaymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id',$user_proposal->user_product_journey_id)->get()->toArray();
            $transactionid = $proposal_array['TransactionID'];
            $proposal_array = [
                'GoGreen' => false,
                'IsReadyToWait' => null,
                'PolicyCertifcateNo' => null,
                'PolicyNo' => null,
                'Inward_no' => null,
                'Request_IP' => null,
                'Customer_Details' => null,
                'Policy_Details' => null,
                'Req_GCV' => null,
                'Req_MISD' => null,
                'Req_PCV' => null,
                'IDV_DETAILS' => null,
                'Req_ExtendedWarranty' => null,
                'Req_Policy_Document' => null,
                'Req_PEE' => null,
                'Req_TW' => null,
                'Req_GHCIP' => null,
                'Req_PolicyConfirmation' => null
            ];
            $proposal_array['Proposal_no'] =  $user_proposal->proposal_no;
            $proposal_array['TransactionID'] = $transactionid;
            if(config('IC.HDFC_ERGO.CIS_DOCUMENT_ENABLE') == 'Y'){
                $proposal_array['CIS_Flag'] = 'Y';
            }
            $proposal_array['Payment_Details'] = [
                'GC_PaymentID'          => null,
                'BANK_NAME'             => 'BIZDIRECT',
                'BANK_BRANCH_NAME'      => 'Andheri',
                'Elixir_bank_code'      => null,
                'PAYMENT_MODE_CD'       => 'EP',
                'IsPolicyIssued'        => "0",
                'IsReserved'            => 0,
                'OTC_Transaction_No'    => "",
                'PAYER_TYPE'            => 'DEALER',
                'PAYMENT_AMOUNT'        => $user_proposal->final_payable_amount
            ];

            foreach($PaymentRequestResponse as $p_key => $p_value)
            {
                if(empty($p_value['customer_id']))
                {
                    continue;
                }
                $proposal_array['Payment_Details']['INSTRUMENT_NUMBER'] = $p_value['customer_id'];
                $proposal_array['Payment_Details']['PAYMENT_DATE'] = date('d/m/Y', strtotime($p_value['updated_at']));
                $additionData['method'] = 'Policy Generation - Re Hit';
                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_SUBMIT_PAYMENT_DETAILS'), $proposal_array, 'hdfc_ergo', $additionData);
                $getpremium = $get_response['response'];
                $arr_proposal = json_decode($getpremium, true);
                if (isset($arr_proposal['StatusCode']) && $arr_proposal['StatusCode'] == 200 && !empty($arr_proposal['Policy_Details']['PolicyNumber'])) 
                {
                    $arr_proposal['Error'] = null;
                    $arr_proposal['Warning'] = null;
                    break;
                }
            }
            if (empty($arr_proposal['Policy_Details']['PolicyNumber']))
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Policy number not found'
                ]);
            }
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal->user_proposal_id],
                [
                    'policy_number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                    'policy_start_date' => $user_proposal->policy_start_date,
                    'premium' => $user_proposal->final_payable_amount,
                    'created_on' => date('Y-m-d H:i:s')
                ]
            );
            UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update(['policy_no' => $arr_proposal['Policy_Details']['PolicyNumber']]);
            $pdf_array = [
                'TransactionID' => $arr_proposal['TransactionID'],
                'Req_Policy_Document' => [
                    'Policy_Number' => $arr_proposal['Policy_Details']['PolicyNumber'],
                ],
            ];
            $user_proposal->proposal_no = $arr_proposal['TransactionID'];
            $policy_details->policy_number=$arr_proposal['Policy_Details']['PolicyNumber'];
            PaymentRequestResponse::where('user_proposal_id', $request->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                    'order_id' => $arr_proposal['TransactionID'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        }
        else
        {
            $arr_proposal['StatusCode']=200;
            $arr_proposal['Error']=null;
            $arr_proposal['Warning']=null;
        }
            if ($arr_proposal['StatusCode'] == 200 && $arr_proposal['Error'] == null && $arr_proposal['Warning'] == null)
            {
                $additionData = [
                    'type' => 'PdfGeneration',
                    'method' => 'Pdf Generation',
                    'requestMethod' => 'post',
                    'section' => 'car',
                    'enquiryId' => $user_proposal->user_product_journey_id,
                    'TOKEN' => $token_data['Authentication']['Token'],
                    'transaction_type' => 'proposal',
                    'PRODUCT_CODE' => $ProductCode, config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                    'TRANSACTIONID' => $enquiryId,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),

                ];
                $policy_array = [
                    "TransactionID" => $user_proposal->proposal_no,
                    "Req_Policy_Document" => [
                        "Policy_Number" => $policy_details->policy_number
                    ]
                ];
                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD'), $policy_array, 'hdfc_ergo', $additionData);
                $pdf_data = $get_response['response'];
                $pdf_response = json_decode($pdf_data, TRUE);
                //Generate Policy PDF - Start

                if ($pdf_response['StatusCode'] == 200)
                {
                    $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf', base64_decode($pdf_response['Resp_Policy_Document']['PDF_BYTES']));
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);

                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $user_proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_details->policy_number,
                            'ic_pdf_url' => '',
                            'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf',
                            'status' => 'SUCCESS'
                        ]
                    );
                    return response()->json([
                        'status' => true,
                        'msg' => 'PDF Generated Successfully',
                        'data' => [
                                    'policy_number' => $policy_details->policy_number,
                                    'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '.pdf')
                                ]
                    ]);
                }
                else
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);

                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $user_proposal->user_proposal_id],
                        [
                            'policy_number' => $policy_details->policy_number,
                            'ic_pdf_url' => '',
                            'pdf_url' => '',
                            'status' => 'SUCCESS'
                        ]
                    );
                    return response()->json([
                        'status' => false,
                        'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                        'data' => [
                                    'policy_number' => $policy_details->policy_number,
                                    'pdf_link'      => ''
                                ]
                    ]);
                }

            }
            else
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Policy Generation Failed',
                    'data' => []
                ]);
            }
        }

    public static function postInspectionSubmit($user_proposal, $productData, $request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $requestData = getQuotation($enquiryId);
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $master_policy = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();
        $ProductCode = '2311';
        if ($premium_type == "third_party") {
            $ProductCode = '2319';
        }
        $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);

        switch ($requestData->business_type) {

            case 'rollover':
                $business_type = 'Roll Over';
                break;

            case 'newbusiness':
                $business_type = 'New Business';
                break;

            default:
                $business_type = $requestData->business_type;
                break;
        }
        $KeyReplacementYN = $InvReturnYN = $engine_protection = $LossOfPersonBelongYN = $LLtoPaidDriverYN = $PAPaidDriverConductorCleanerSI = $tyresecure = $LossOfPersonalBelonging_SI = 0;
        $policy_start_date = date('Y-m-d');
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));


        $additionData = [
            'type' => 'gettoken',
            'method' => 'tokenGeneration',
            'section' => 'car',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name . " ($business_type)",
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode,
            'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
            'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
            'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
        ];

        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);

        $token = $get_response['response'];
        $token_data = json_decode($token, TRUE);


        if (isset($token_data['Authentication']['Token'])) {

            $additionData = [
                'type' => 'PremiumCalculation',
                'method' => 'Proposal Submit', 
                'requestMethod' => 'post',
                'section' => 'car',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name . " ($business_type)",
                'TOKEN' => $token_data['Authentication']['Token'],
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                'TRANSACTIONID' => $transactionid, // config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
            ];


            if (!empty($user_proposal['additional_details_data'])) {
                $proposal_array = json_decode($user_proposal['additional_details_data']);
                $proposal_array->Req_PvtCar->BreakInStatus = "Recommended";
                $proposal_array->Policy_Details->PolicyStartDate = date('d/m/Y', time());
                $PreviousPolicy_CorporateCustomerId_Mandatary = DB::table('previous_insurer_lists AS a')
                ->where('a.name', 'ICICI Lombard General Insurance Co. Ltd.')
                ->where('a.company_alias', 'hdfc_ergo')
                ->pluck('code')
                ->first();
                $proposal_array->Policy_Details->PreviousPolicy_CorporateCustomerId_Mandatary = $PreviousPolicy_CorporateCustomerId_Mandatary;

            } else {
                return ([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'additional_details_data not found',
                ]);
            }
            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_CREATE_PROPOSAL'), $proposal_array, 'hdfc_ergo', $additionData);
            $proposal_submit_response = $get_response['response'];
            $proposal_submit_response = json_decode($proposal_submit_response, true);

            if ((isset($proposal_submit_response['StatusCode']) && $proposal_submit_response['StatusCode'] == '200')) {
                $proposal_data = $proposal_submit_response['Resp_PvtCar'];
                // $proposal->proposal_no = $proposal_submit_response['TransactionID'];
                // // $proposal->ic_vehicle_details = $vehicleDetails;
                // $proposal->save();
                $Nil_dep = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $age_discount = $lpg_cng_tp = $lpg_cng = $Bonus_Discount = $automobile_discount = $antitheft
                    = $basic_tp_premium = $electrical_accessories = $tppd_value =
                    $non_electrical_accessories = $ncb_protction = $ll_paid_driver =
                    $ncb_protection = $consumables_cover = $Nil_dep = $roadside_asst =
                    $key_replacement = $loss_of_personal_belongings = $eng_protector =
                    $rti = $incon_allow = $Basic_OD_Discount = $electrical_Discount =
                    $non_electrical_Discount = $lpg_od_premium_Discount = $tyre_secure = $GeogExtension_od = $GeogExtension_tp = $OwnPremises_OD = $OwnPremises_TP = $basic_od_premium = $legal_liability_to_employee = 0;

                if (!empty($proposal_data['PAOwnerDriver_Premium'])) {
                    $pa_owner_driver = $proposal_data['PAOwnerDriver_Premium'];
                }
                if (!empty($proposal_data['Vehicle_Base_ZD_Premium'])) {
                    $Nil_dep = $proposal_data['Vehicle_Base_ZD_Premium'];
                }

                if (!empty($proposal_data['GeogExtension_ODPremium'])) {
                    $GeogExtension_od = $proposal_data['GeogExtension_ODPremium'];
                }
                if (!empty($proposal_data['GeogExtension_TPPremium'])) {
                    $GeogExtension_tp = $proposal_data['GeogExtension_TPPremium'];
                }

                if (!empty($proposal_data['LimitedtoOwnPremises_OD_Premium'])) {
                    $OwnPremises_OD = $proposal_data['LimitedtoOwnPremises_OD_Premium'];
                }
                if (!empty($proposal_data['LimitedtoOwnPremises_TP_Premium'])) {
                    $OwnPremises_TP = $proposal_data['LimitedtoOwnPremises_TP_Premium'];
                }

                if (!empty($proposal_data['EA_premium'])) {
                    $roadside_asst = $proposal_data['EA_premium'];
                }
                if (!empty($proposal_data['LossOfPersonalBelongings_Premium'])) {
                    $loss_of_personal_belongings = $proposal_data['LossOfPersonalBelongings_Premium'];
                }
                if (!empty($proposal_data['Vehicle_Base_NCB_Premium'])) {
                    $ncb_protection = $proposal_data['Vehicle_Base_NCB_Premium'];
                }
                if (!empty($proposal_data['NCBBonusDisc_Premium'])) {
                    $ncb_discount = $proposal_data['NCBBonusDisc_Premium'];
                }
                if (!empty($proposal_data['Vehicle_Base_ENG_Premium'])) {
                    $eng_protector = $proposal_data['Vehicle_Base_ENG_Premium'];
                }
                if (!empty($proposal_data['Vehicle_Base_COC_Premium'])) {
                    $consumables_cover = $proposal_data['Vehicle_Base_COC_Premium'];
                }
                if (!empty($proposal_data['Vehicle_Base_RTI_Premium'])) {
                    $rti = $proposal_data['Vehicle_Base_RTI_Premium'];
                }
                if (!empty($proposal_data['EAW_premium'])) {
                    $key_replacement = $proposal_data['EAW_premium'];
                }
                if (!empty($proposal_data['UnnamedPerson_premium'])) {
                    $pa_unnamed = $proposal_data['UnnamedPerson_premium'];
                }
                if (!empty($proposal_data['Electical_Acc_Premium'])) {
                    $electrical_accessories = $proposal_data['Electical_Acc_Premium'];
                }
                if (!empty($proposal_data['NonElectical_Acc_Premium'])) {
                    $non_electrical_accessories = $proposal_data['NonElectical_Acc_Premium'];
                }
                if (!empty($proposal_data['BiFuel_Kit_OD_Premium'])) {
                    $lpg_cng = $proposal_data['BiFuel_Kit_OD_Premium'];
                }
                if (!empty($proposal_data['BiFuel_Kit_TP_Premium'])) {
                    $lpg_cng_tp = $proposal_data['BiFuel_Kit_TP_Premium'];
                }
                if (!empty($proposal_data['PAPaidDriver_Premium'])) {
                    $pa_paid_driver = $proposal_data['PAPaidDriver_Premium'];
                }
                if (!empty($proposal_data['PaidDriver_Premium'])) {
                    $ll_paid_driver = $proposal_data['PaidDriver_Premium'];
                }
                if (!empty($proposal_data['NumberOfEmployees_Premium'])) {
                    $legal_liability_to_employee = $proposal_data['NumberOfEmployees_Premium'];
                }
                if (!empty($proposal_data['VoluntartDisc_premium'])) {
                    $voluntary_excess = $proposal_data['VoluntartDisc_premium'];
                }
                if (!empty($proposal_data['Vehicle_Base_TySec_Premium'])) {
                    $tyre_secure = $proposal_data['Vehicle_Base_TySec_Premium'];
                }
                if (!empty($proposal_data['AntiTheftDisc_Premium'])) {
                    $anti_theft = $proposal_data['AntiTheftDisc_Premium'];
                }
                if (!empty($proposal_data['Net_Premium'])) {
                    $final_net_premium = $proposal_data['Net_Premium'];
                }
                if (!empty($proposal_data['Total_Premium'])) {
                    $final_payable_amount = $proposal_data['Total_Premium'];
                }
                if (!empty($proposal_data['Basic_OD_Premium'])) {
                    $basic_od_premium = $proposal_data['Basic_OD_Premium'];
                }
                if (!empty($proposal_data['Basic_TP_Premium'])) {
                    $basic_tp_premium = $proposal_data['Basic_TP_Premium'];
                }
                if (!empty($proposal_data['TPPD_premium'])) {
                    $tppd_value = $proposal_data['TPPD_premium'];
                }
                if (!empty($proposal_data['InBuilt_BiFuel_Kit_Premium'])) {
                    $lpg_cng_tp = ($proposal_data['InBuilt_BiFuel_Kit_Premium']);
                }
                if ($electrical_accessories > 0) {
                    $Nil_dep += (int)$proposal_data['Elec_ZD_Premium'];
                    $engine_protection += (int)$proposal_data['Elec_ENG_Premium'];
                    $ncb_protection += (int)$proposal_data['Elec_NCB_Premium'];
                    $consumables_cover += (int)$proposal_data['Elec_COC_Premium'];
                    $rti += (int)$proposal_data['Elec_RTI_Premium'];
                }
                if ($non_electrical_accessories > 0) {
                    $Nil_dep += (int)$proposal_data['NonElec_ZD_Premium'];
                    $engine_protection += (int)$proposal_data['NonElec_ENG_Premium'];
                    $ncb_protection += (int)$proposal_data['NonElec_NCB_Premium'];
                    $consumables_cover += (int)$proposal_data['NonElec_COC_Premium'];
                    $rti += (int)$proposal_data['NonElec_RTI_Premium'];
                }

                if ($lpg_cng > 0) {
                    $Nil_dep += (int)$proposal_data['Bifuel_ZD_Premium'];
                    $engine_protection += (int)$proposal_data['Bifuel_ENG_Premium'];
                    $ncb_protection += (int)$proposal_data['Bifuel_NCB_Premium'];
                    $consumables_cover += (int)$proposal_data['Bifuel_COC_Premium'];
                    $rti += (int)$proposal_data['Bifuel_RTI_Premium'];
                }

                HdfcErgoPremiumDetailController::saveV1PremiumDetails($get_response['webservice_id']);

                $addon_premium = $Nil_dep + $tyre_secure + $consumables_cover + $ncb_protection + $roadside_asst + $key_replacement + $loss_of_personal_belongings + $eng_protector + $rti;
                //        print_r([$proposal_data['Elec_ZD_Premium'], $proposal_data['NonElec_ZD_Premium'], $proposal_data['Bifuel_ZD_Premium']]);
                $tp_premium = ($basic_tp_premium + $pa_owner_driver + $ll_paid_driver + $legal_liability_to_employee + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp) - $tppd_value + $GeogExtension_tp + $OwnPremises_TP;
                //print_r([$basic_tp_premium,$pa_owner_driver,$ll_paid_driver,$pa_paid_driver,$pa_unnamed,$lpg_cng_tp,$tppd_value]);

                $od_premium = $basic_od_premium + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD;
                $final_total_discount = $ncb_discount;
                $total_od_amount = $od_premium - $final_total_discount;

                if ($proposal_submit_response['Policy_Details']['ProposalNumber'] == null) {
                    return ([
                        'status' => false,
                        'message' => "The proposal number cannot have a null value",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                    ]);
                }
                //GET CIS DOCUMENT API
                if (!empty($proposal_submit_response['Policy_Details']['ProposalNumber'])) {
                    $get_cis_document_array = [
                        'TransactionID' => $transactionid,
                        'Req_Policy_Document' => [
                            'Proposal_Number' => $proposal_submit_response['Policy_Details']['ProposalNumber'] ?? null,
                        ],
                    ];

                    $additionData['method'] = 'Get CIS Document';
                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_CREATE_CIS_DOCUMENT'), $get_cis_document_array, 'hdfc_ergo', $additionData);
                    $cis_doc_resp = json_decode($get_response['response']);
                    $pdfData = base64_decode($cis_doc_resp->Resp_Policy_Document->PDF_BYTES);
                    if (checkValidPDFData($pdfData)) {
                        Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '_cis' . '.pdf', $pdfData);

                        // $pdf_url = file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '_cis' . '.pdf');
                        ProposalExtraFields::updateOrCreate(
                            ['enquiry_id' => $enquiryId], 
                            ['cis_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($user_proposal->user_proposal_id) . '_cis' . '.pdf']      
                        );
                    } else {
                        return ([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'    => $cis_doc_resp->Error ?? 'CIS Document service Issue'
                        ]);
                    }
                }

                UserProposal::where('user_product_journey_id', $enquiryId)->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'final_payable_amount' => ($final_payable_amount),
                    'proposal_no' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                    'od_premium'            => $total_od_amount,
                    'tp_premium'            => $tp_premium,
                    'addon_premium'         => $addon_premium,
                    'cpa_premium'           => $pa_owner_driver,
                    'total_premium'         => ($final_net_premium),
                    'total_discount'        => ($ncb_discount + $Basic_OD_Discount + $electrical_Discount + $non_electrical_Discount + $lpg_od_premium_Discount + $tppd_value),
                    'service_tax_amount'    => ($proposal_data['Service_Tax']),
                ]);
                // UserProposal::where('user_product_journey_id', $enquiryId)
                //     ->where('user_proposal_id', $proposal->user_proposal_id)
                //     ->update([
                //         'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                //         'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                //         'proposal_no' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                //         'unique_proposal_id' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                //         'product_code'      => $ProductCode,
                //         'od_premium' => $total_od_amount,
                //         'business_type' => $BusinessType,
                //         'tp_premium' => $tp_premium,
                //         'addon_premium' => $addon_premium,
                //         'cpa_premium' => $pa_owner_driver,
                //         'applicable_ncb' => $requestData->applicable_ncb,
                //         'final_premium' => ($final_net_premium),
                //         'total_premium' => ($final_net_premium),
                //         'service_tax_amount' => ($proposal_data['Service_Tax']),
                //         'final_payable_amount' => ($final_payable_amount),
                //         'customer_id' => '',
                //         'ic_vehicle_details' => json_encode($vehicleDetails),
                //         'ncb_discount' => $ncb_discount,
                //         'total_discount' => ($ncb_discount + $Basic_OD_Discount + $electrical_Discount + $non_electrical_Discount + $lpg_od_premium_Discount + $tppd_value),
                //         'cpa_ins_comp' => $cPAInsComp,
                //         'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                //         'cpa_policy_no' => $cPAPolicyNo,
                //         'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                //         'cpa_sum_insured' => $cPASumInsured,
                //         'electrical_accessories' => $ElectricalaccessSI,
                //         'non_electrical_accessories' => $NonElectricalaccessSI,
                //         'additional_details_data' => json_encode($proposal_array),
                //         // 'is_breakin_case' => ($is_breakin) ? 'Y' : 'N',
                //     ]);


                $data['user_product_journey_id'] = $enquiryId;
                $data['ic_id'] = $master_policy;
                $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                $data['proposal_id'] = $proposal_submit_response['TransactionID'];
                updateJourneyStage($data);


                return ([
                    'status' => true,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Proposal Submitted Successfully!"
                ]);
            }
            else {
                return ([
                     'status' => false,
                     'webservice_id' => $get_response['webservice_id'],
                     'table' => $get_response['table'],
                     'message'    => $proposal_submit_response['Error'] ?? 'Insurer Not Found'
                 ]); 
             }
        } else {
            return ([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => "Token Service Issue"
            ]);
        }
    }
}
