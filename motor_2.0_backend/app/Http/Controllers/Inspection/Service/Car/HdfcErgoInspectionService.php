<?php
namespace App\Http\Controllers\Inspection\Service\Car;

use App\Models\CvBreakinStatus;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;

include_once app_path().'/Helpers/CarWebServiceHelper.php';

class HdfcErgoInspectionService {
    public static function v2InspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))->first();

        if ($breakinDetails)
        {
            $user_proposal = $breakinDetails->user_proposal;
            $quote_log = $user_proposal->quote_log;
            $journey_stage = $user_proposal->user_product_journey->journey_stage;
            $policy_details = $user_proposal->policy_details;
            $selected_addons = $user_proposal->selected_addons;
            $corporate_vehicles_quotes_request = $user_proposal->corporate_vehicles_quotes_request;

            $productData = getProductDataByIc($quote_log->master_policy_id);

            if ($policy_details)
            {
                return response()->json([
                    'status' => false,
                    'message' => 'Policy has already been generated for this inspection number'
                ]);
            }
            else
            {
                $inspection_result = json_decode($breakinDetails->breakin_response, TRUE);

                if ( ! isset($inspection_result['Data']['BreakInStatus']) || (isset($inspection_result['Data']['BreakInStatus']) && $inspection_result['Data']['BreakInStatus'] != 'Recommended'))
                {
                    $breakin_details_input = [
                        'QuoteNo' => 0,
                        'BreakinId' => $breakinDetails->breakin_number,
                        'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE')
                    ];

                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_GET_BREAKIN_DETAILS_URL'), $breakin_details_input, 'hdfc_ergo', [
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Get Breakin Details',
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
                    $breakin_details_output = $get_response['response'];

                    if ($breakin_details_output)
                    {
                        $inspection_result = json_decode($breakin_details_output, TRUE);

                        if (isset($inspection_result['Status']) && $inspection_result['Status'] == 200)
                        {
                            CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                                ->update([
                                    'breakin_response' => $breakin_details_output,
                                    'inspection_date' => date('Y-m-d', strtotime(str_replace('/', '-', $inspection_result['Data']['StatusUpdatedDateTime'])))
                                ]);
                        }
                        else
                        {
                            return response()->json([
                                'status' => false,
                                'msg' => 'An error occurred while fetching breakin details.',
                                'data'   => []
                            ]);
                        }
                    }
                    else
                    {
                        return response()->json([
                            'status' => FALSE,
                            'message' => 'Insurer Not Reachable'
                        ]);
                    }
                }

                if (isset($inspection_result['Data']['BreakInStatus']) && $inspection_result['Data']['BreakInStatus'] == 'Recommended')
                {
                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'payment_end_date' => date('Y-m-d', strtotime('+2 days', strtotime(str_replace('/', '-', $inspection_result['Data']['StatusUpdatedDateTime']))))
                        ]);

                    $post_inspection_breakin_details_input = [
                        'QuoteNo' => $user_proposal->customer_id,
                        'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE')
                    ];

                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_POST_INSPECTION_BREAKIN_DETAILS_URL'), $post_inspection_breakin_details_input, 'hdfc_ergo', [
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Post Inspection Breakin Details',
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
                    $post_inspection_breakin_details_output = $get_response['response'];

                    if ($post_inspection_breakin_details_output)
                    {
                        $post_inspection_breakin_details_output = json_decode($post_inspection_breakin_details_output, TRUE);

                        if ($post_inspection_breakin_details_output['Status'] == 200)
                        {
                            $request_data = json_decode($user_proposal->additional_details_data, TRUE);
                            $premium_request = $request_data['premium_request'];
                            $proposal_request = $request_data['proposal_request'];

                            /****************We need to pass values from 'Post Inspection Breakin Details' api response in 'Premium Calculation post Inspection' api response as suggested by IC*******************/
                            $premium_request['PreviousPolicyEndDate'] = $proposal_request['ProposalDetails']['PreviousPolicyEndDate'] = $post_inspection_breakin_details_output['Data']['ProposalDetail']['PreviousPolicyEndDate'];
                            $premium_request['PreviousInsurerId'] = $proposal_request['ProposalDetails']['PreviousInsurerCode'] = $post_inspection_breakin_details_output['Data']['ProposalDetail']['PreviousInsurerId'];
                            $premium_request['IsPreviousClaim'] = $proposal_request['ProposalDetails']['IsPreviousClaim'] = $post_inspection_breakin_details_output['Data']['VehicleDetail']['PreviousClaimTaken'];
                            $proposal_request['ProposalDetails']['PreviousPolicyNumber'] = $post_inspection_breakin_details_output['Data']['ProposalDetail']['PreviousPolicyNumber'];
                            /*******************************************************************************/

                            $additional_details_data = json_encode([
                                'premium_request' => $premium_request,
                                'proposal_request' => $proposal_request
                            ], JSON_UNESCAPED_SLASHES);

                            $is_zero_dep = 'NO';
                            $is_roadside_assistance = 'NO';
                            $is_key_replacement = 'NO';
                            $is_engine_protector = 'NO';
                            $is_ncb_protection = 'NO';
                            $is_tyre_secure = 'NO';
                            $is_consumable = 'NO';
                            $is_return_to_invoice = 'NO';
                            $is_loss_of_personal_belongings = 'NO';

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

                            $basic_od = $post_inspection_breakin_details_output['Data'][0]['BasicODPremium'] ?? 0;
                            $basic_tp = $post_inspection_breakin_details_output['Data'][0]['BasicTPPremium'] ?? 0;
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
                            $lpg_cng_kit_tp = $post_inspection_breakin_details_output['Data'][0]['LpgCngKitTPPremium'] ?? 0;
                            $ncb_discount = $post_inspection_breakin_details_output['Data'][0]['NewNcbDiscountAmount'] ?? 0;
                            $tppd_discount = $post_inspection_breakin_details_output['Data'][0]['TppdDiscountAmount'] ?? 0;

                            if(isset($post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['BuiltInLpgCngKitPremium']) && $post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['BuiltInLpgCngKitPremium'] != 0.0)
                            {
                                $lpg_cng_kit_tp = $post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['BuiltInLpgCngKitPremium'] ?? 0;
                            }

                            if (isset($post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['AddOnCovers']))
                            {
                                foreach ($post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['AddOnCovers'] as $addon_cover)
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

                            $final_net_premium = (int) $post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['NetPremiumAmount'];
                            $service_tax = (int) $post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['TaxAmount'];
                            $final_payable_amount = (int) $post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['TotalPremiumAmount'];

                            UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                                ->update([
                                    'additional_details_data' => $additional_details_data,
                                    'policy_start_date' => date('d-m-Y', strtotime($post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['NewPolicyStartDate'])),
                                    'policy_end_date' => date('d-m-Y', strtotime($post_inspection_breakin_details_output['Data']['ComprehensiveCalcResponse'][0]['NewPolicyEndDate'])),
                                    'od_premium' => $total_od_amount,
                                    'tp_premium' => round($total_tp_amount),
                                    'ncb_discount' => round($ncb_discount), 
                                    'addon_premium' => round($total_addon_amount),
                                    'cpa_premium' => round($cpa),
                                    'total_discount' => round($final_total_discount),
                                    'total_premium' => round($final_net_premium),
                                    'service_tax_amount' => round($service_tax),
                                    'final_payable_amount' => round($final_payable_amount),
                                    'is_inspection_done' => 'Y'
                                ]);

                            updateJourneyStage([
                                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                            ]);

                            CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)
                            ->update([
                                'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                                'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                                'inspection_date'       => date('Y-m-d'),
                            ]);

                            return response()->json([
                                'status' => true,
                                'msg' => 'Your Vehicle Inspection is Done By HDFC ERGO.',
                                'data'   => [
                                    'enquiryId' => customEncrypt($user_proposal->user_product_journey_id),
                                    'proposalNo' => $user_proposal->user_proposal_id,                    
                                    'totalPayableAmount' => $user_proposal->final_payable_amount,
                                    'proposalUrl' =>  str_replace('quotes','proposal-page',$journey_stage->proposal_url)
                                ]
                            ]);
                        }
                        else
                        {
                            return response()->json([
                                'status' => false,
                                'msg' => 'An error occurred while fetching post inspection breakin details.',
                                'data'   => []
                            ]);
                        }
                    }
                    else
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => 'An error occurred while fetching post inspection breakin details.',
                            'data'   => []
                        ]);
                    }
                }
                else
                {
                    $status = false;

                    if (isset($inspection_result['Data']['BreakInStatus']) && $inspection_result['Data']['BreakInStatus'] == 'Not Recommended')
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,                               
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED'],                             
                        ]);

                        $message = 'Your Vehicle Inspection has been rejected';
                    }
                    else
                    {
                        $message = isset($inspection_result['Data']['BreakInStatus']) && ! empty($inspection_result['Data']['BreakInStatus']) ? 'Your Inspection status is ' . $inspection_result['Data']['BreakInStatus'] : 'Your Inspection has not been recommended yet';
                    }

                    return response()->json([
                        'status' => $status,
                        'message' => $message
                    ]);
                }
            }
        }
        else
        {
            return response()->json([
                'status' => true,
                'msg' => 'Please check your inspection number'
            ]);
        }
    }
}