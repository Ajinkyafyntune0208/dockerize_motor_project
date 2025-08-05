<?php

namespace App\Http\Controllers\LSQ;

use App\Http\Controllers\Controller;
use App\Models\LsqActivities;
use App\Models\LsqJourneyIdMapping;
use App\Models\UserProductJourney;
use Illuminate\Http\Request;

include_once app_path('/Helpers/CvWebServiceHelper.php');

class ActivityController extends Controller
{
    public function create($enquiryId, $create_lead_on = 'opportunity', $message_type = NULL, $additional_data = [])
    {
        $user_product_journey = UserProductJourney::find($enquiryId);
        $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
        $journey_stage = $user_product_journey->journey_stage;
        $quote_log = $user_product_journey->quote_log;
        $agent_details = $user_product_journey->agent_details;
        $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
        $user_proposal = $user_product_journey->user_proposal;
        $breakin_status = ! is_null($user_proposal) ? $user_proposal->breakin_status : NULL;
        $addons = $user_product_journey->addons;

        $message_schema_name = '';
        $lsq_substage = NULL;

        if (is_null($lsq_journey_id_mapping))
        {
            return [
                'status' => FALSE
            ];
        }

        if (is_null($lsq_journey_id_mapping->lsq_stage))
        {
            $activity_event = 200;
            $mx_custom_3 = 'Lead Page Submitted';

            if ($lsq_journey_id_mapping->is_duplicate == 1 || ($corporate_vehicles_quote_request && $corporate_vehicles_quote_request->journey_type == 'driver-app' && is_null($message_type)))
            {
                $activity_event = 205;
                $mx_custom_3 = STAGE_NAMES['QUOTE'];
                $message_schema_name = 'Quote Seen';
            }

            if ($message_type == 'RC Submitted')
            {
                $activity_event = 201;
                $mx_custom_3 = 'Lead Page Submitted';
                $message_schema_name = 'RC Submitted';
                $lsq_substage = $message_schema_name;
            }
            elseif ($message_type == 'MMV Form Submitted')
            {
                $activity_event = 204;
                $message_schema_name = 'MMV Form Submitted';
                $lsq_substage = $message_schema_name;
            }
            elseif ($message_type == 'Embedded Link Shared')
            {
                $activity_event = 219;
                $message_schema_name = 'Embedded Link Shared';
                $lsq_substage = $message_schema_name;
            }
        }
        else 
        {
            if ($journey_stage->stage == $lsq_journey_id_mapping->lsq_stage && ! in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED']]) && ! in_array($message_type, ['RC Submitted', 'MMV Form Submitted', 'shareQuotes', 'shareProposal', 'Mobile Number Changed', 'RC Changed', 'proposalCreated', 'Proposal Seen', 'Embedded Link Shared']))
            {
                return [
                    'status' => FALSE
                ];
            }

            if ( ! is_null($message_type))
            {
                $mx_custom_3 = 'Lead Page Submitted';

                if ($message_type == 'RC Submitted')
                {
                    $activity_event = 201;
                    $message_schema_name = 'RC Submitted';
                    $lsq_substage = $message_schema_name;
                }
                elseif ($message_type == 'MMV Form Submitted')
                {
                    $activity_event = 204;
                    $message_schema_name = 'MMV Form Submitted';
                    $lsq_substage = $message_schema_name;
                }
                elseif ($message_type == 'shareQuotes')
                {
                    $activity_event = 202;
                    $message_schema_name = 'Quote Shared';
                    $mx_custom_3 = STAGE_NAMES['QUOTE'];
                }
                elseif ($message_type == 'Embedded Link Shared')
                {
                    $activity_event = 219;
                    $message_schema_name = 'Embedded Link Shared';
                    $lsq_substage = $message_schema_name;
                }
                elseif ($message_type == 'shareProposal')
                {
                    $activity_event = 206;
                    $message_schema_name = 'Proposal Shared';
                    $mx_custom_3 = STAGE_NAMES['PROPOSAL_DRAFTED'];
                }
                elseif ($message_type == 'Proposal Seen')
                {
                    $activity_event = 207;
                    $message_schema_name = 'Proposal Seen';
                    $mx_custom_3 = STAGE_NAMES['PROPOSAL_DRAFTED'];   
                }
                elseif ($message_type == 'Mobile Number Changed')
                {
                    $activity_event = 216;
                    $message_schema_name = 'Proposal Seen';
                    $mx_custom_3 = STAGE_NAMES['PROPOSAL_DRAFTED'];
                }
                elseif ($message_type == 'RC Changed')
                {
                    $activity_event = 217;
                    $message_schema_name = $journey_stage->stage == STAGE_NAMES['QUOTE'] ? 'RC Submitted' : 'Proposal Seen';
                    $mx_custom_3 = $journey_stage->stage == STAGE_NAMES['QUOTE'] ? 'Lead Page Submitted' : STAGE_NAMES['PROPOSAL_DRAFTED'];
                    $lsq_substage = 'RC Submitted';
                }
                elseif ($message_type == 'proposalCreated')
                {
                    $activity_event = 209;
                    $message_schema_name = 'Payment Link Sent';
                    $mx_custom_3 = $journey_stage->stage;
                }
            }
            else
            {            
                if ($journey_stage->stage == STAGE_NAMES['QUOTE'])
                {
                    $activity_event = 205;
                    $mx_custom_3 = STAGE_NAMES['QUOTE'];
                    $message_schema_name = 'Quote Seen';
                }
                elseif ($journey_stage->stage == STAGE_NAMES['PROPOSAL_DRAFTED'])
                {
                    $activity_event = 207;
                    $mx_custom_3 = STAGE_NAMES['PROPOSAL_DRAFTED'];
                    $message_schema_name = 'Proposal Seen';
                }
                elseif ($journey_stage->stage == STAGE_NAMES['PROPOSAL_ACCEPTED'])
                {
                    $activity_event = 208;
                    $mx_custom_3 = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                }
                elseif (in_array($journey_stage->stage, [ STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_REJECTED']]))
                {
                    $activity_event = 208;
                    $message_schema_name = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                    $mx_custom_3 = $journey_stage->stage;
                }
                elseif ($journey_stage->stage == STAGE_NAMES['PAYMENT_INITIATED'])
                {
                    $activity_event = 210;
                    $mx_custom_3 = STAGE_NAMES['PAYMENT_INITIATED'];
                }
                elseif ($journey_stage->stage == STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'])
                {
                    $activity_event = 211;
                    $mx_custom_3 = 'Payment Completed';
                    $message_schema_name = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                }
                elseif ($journey_stage->stage == STAGE_NAMES['PAYMENT_FAILED'])
                {
                    $activity_event = 212;
                    $mx_custom_3 = STAGE_NAMES['PAYMENT_FAILED'];
                }
                elseif ($journey_stage->stage == STAGE_NAMES['POLICY_ISSUED'])
                {
                    $activity_event = 213;
                    $mx_custom_3 = STAGE_NAMES['POLICY_ISSUED'];
                }
                else
                {
                    return [
                        'status' => FALSE
                    ];
                }
            }
        }

        if (isset($quote_log->master_policy->premium_type) && ! is_null($quote_log->master_policy->premium_type)) 
        {
            switch ($quote_log->master_policy->premium_type->premium_type_code)
            {
                case 'third_party':
                case 'third_party_breakin':
                    $policy_type = 'TP Only';
                    break;

                case 'own_damage':
                case 'own_damage_breakin':
                    $policy_type = 'OD Only';
                    break;

                default:
                    $policy_type = 'Comprehensive';
            }

            switch ($quote_log->master_policy->premium_type->premium_type_code)
            {
                case 'short_term_3':
                case 'short_term_3_breakin':
                    $policy_tenure = '3 months';
                    break;

                case 'short_term_6':
                case 'short_term_6_breakin':
                    $policy_tenure = '6 months';
                    break;

                default:
                    $policy_tenure = '12 months';
                    break;
            }
        }

        $activity_request = [
            'RelatedProspectId' => $lsq_journey_id_mapping->lead_id,
            'RelatedOpportunityId' => $lsq_journey_id_mapping->opportunity_id,
            'ActivityEvent' => $activity_event,
            'ActivityDateTime' => date('Y-m-d H:i:s', strtotime('-5 hours -30 minutes', time())),
            'Fields' => [
                [
                    'SchemaName' => $mx_custom_3 == 'Lead Page Submitted' && ! in_array($message_type, ['MMV Form Submitted', 'RC Changed']) ? 'mx_Custom_1' : 'mx_Custom_3', // Stage
                    'Value' => $message_schema_name != '' ? $message_schema_name : $mx_custom_3
                ]
            ]
        ];

        $source = 'B2C';

        if ( ! is_null($agent_details) && isset($agent_details[count($agent_details) - 1]->seller_type) && ! in_array($message_type, ['Embedded Link Shared']))
        {
            if ($agent_details[count($agent_details) - 1]->seller_type == 'P')
            {
                array_push($activity_request['Fields'], [
                    'SchemaName' => $mx_custom_3 == 'Lead Page Submitted' && ! in_array($message_type, ['MMV Form Submitted', 'RC Changed']) ? 'mx_Custom_2' : (in_array($message_type, ['Mobile Number Changed', 'RC Changed']) ? 'mx_Custom_5' : 'mx_Custom_1'), // POS ID
                    'Value' => $agent_details[count($agent_details) - 1]->agent_id
                ],
                [
                    'SchemaName' => $mx_custom_3 == 'Lead Page Submitted' && ! in_array($message_type, ['MMV Form Submitted', 'RC Changed']) ? 'mx_Custom_3' : (in_array($message_type, ['Mobile Number Changed', 'RC Changed']) ? 'mx_Custom_6' : 'mx_Custom_2'), // POS Name
                    'Value' => $agent_details[count($agent_details) - 1]->agent_name
                ]);
            }

            if ($agent_details[count($agent_details) - 1]->agent_name == 'driver_app')
            {
                $source = 'Driver App';
            }
            elseif ($agent_details[count($agent_details) - 1]->agent_name == 'embedded_admin')
            {
                $source = 'Embedded Admin';
            }
            elseif ($agent_details[count($agent_details) - 1]->agent_name == 'embedded_scrub')
            {
                $source = 'Embedded Scrub';
            }
            elseif ($agent_details[count($agent_details) - 1]->seller_type == 'E')
            {
                $source = 'Employee';
            }
            elseif ($agent_details[count($agent_details) - 1]->seller_type == 'P')
            {
                $source = 'POS';
            }
        }

        if (isset($journey_stage->stage) && ! in_array($journey_stage->stage, [ STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['POLICY_ISSUED']]) && $mx_custom_3 != 'Lead Page Submitted' && ! in_array($message_type, ['Mobile Number Changed', 'RC Changed', 'Embedded Link Shared']))
        {
            if ($message_type != 'proposalCreated')
            {
                array_push($activity_request['Fields'], [
                    'SchemaName' => 'mx_Custom_4', // Previous NCB
                    'Value' => ! is_null($corporate_vehicles_quote_request->previous_ncb) ? $corporate_vehicles_quote_request->previous_ncb . '%' : NULL
                ],
                [
                    'SchemaName' => 'mx_Custom_5', // Current NCB
                    'Value' => ! is_null($corporate_vehicles_quote_request->applicable_ncb) ? $corporate_vehicles_quote_request->applicable_ncb . '%' : NULL
                ],
                [
                    'SchemaName' => 'mx_Custom_6', // Claim Status
                    'Value' => $corporate_vehicles_quote_request->is_claim == 'Y' ? 'Yes' : 'No'
                ]);
            }
        }

        if (in_array($mx_custom_3, ['Quote Shared', STAGE_NAMES['QUOTE'], 'Proposal Shared']))
        {
            array_push($activity_request['Fields'], [
                'SchemaName' => 'mx_Custom_20', // Source
                'Value' => $source
            ]);
        }
        elseif ($message_schema_name == 'MMV Form Submitted')
        {
            array_push($activity_request['Fields'], [
                'SchemaName' => 'mx_Custom_19', // Source
                'Value' => $source
            ]);
        }
        elseif (in_array($mx_custom_3, [ STAGE_NAMES['PROPOSAL_DRAFTED'], STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_REJECTED']]) && ! in_array($message_type, ['Mobile Number Changed', 'RC Changed']))
        {
            array_push($activity_request['Fields'], [
                'SchemaName' => 'mx_Custom_22', // Source
                'Value' => $source
            ]);
        }
        else
        {
            if ( ! in_array($message_type, ['Embedded Link Shared']))
            {
                array_push($activity_request['Fields'], [
                    'SchemaName' => 'mx_Custom_4', // Source
                    'Value' => $source
                ]);
            }
        }

        if (in_array($message_type, ['Mobile Number Changed', 'RC Changed']))
        {
            array_push($activity_request['Fields'], [
                'SchemaName' => 'mx_Custom_1',
                'Value' => $message_type == 'Mobile Number Changed' ? $additional_data['old_mobile_number'] : $additional_data['old_rc_number']
            ],
            [
                'SchemaName' => 'mx_Custom_2',
                'Value' => $message_type == 'Mobile Number Changed' ? $additional_data['new_mobile_number'] : $additional_data['new_rc_number']
            ]);
        }

        if ((isset($journey_stage->stage) && $journey_stage->stage == STAGE_NAMES['QUOTE'] && is_null($message_type)) || $message_type == 'shareQuotes')
        {
            array_push($activity_request['Fields'], [
                'SchemaName' => 'mx_Custom_13', // Enquiry ID
                'Value' => customEncrypt($enquiryId)
            ],
            [
                'SchemaName' => 'mx_Custom_14', // Enquiry Link
                'Value' => str_replace('vehicle-details', 'quotes', $journey_stage->quote_url)
            ],
            [
                'SchemaName' => 'mx_Custom_17', // Quote ID
                'Value' => customEncrypt($enquiryId)
            ],
            [
                'SchemaName' => 'mx_Custom_18', // Quote Link
                'Value' => str_replace('vehicle-details', 'quotes', $journey_stage->quote_url)
            ]);
        }

        if ((isset($journey_stage->stage) && in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_DRAFTED'], STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_REJECTED']]) && ! in_array($message_type, ['Mobile Number Changed', 'RC Changed', 'Embedded Link Shared'])) || in_array($message_type, ['shareProposal', 'proposalCreated']))
        {
            $cpa = 0;

            if ($addons)
            {
                if (isset($addons[count($addons) - 1]['compulsory_personal_accident'][0]['name']))
                {
                    $cpa = $quote_log->premium_json['compulsoryPaOwnDriver'];
                }
            }

            $od_premium = 0;
            $tp_premium = 0;
            $addon_premium = 0;

            $electrical_accessories = isset($quote_log->premium_json['motorElectricAccessoriesValue']) && ! is_null($quote_log->premium_json['motorElectricAccessoriesValue']) ? (int) $quote_log->premium_json['motorElectricAccessoriesValue'] : 0;
            $non_electrical_accessories = isset($quote_log->premium_json['motorNonElectricAccessoriesValue']) && ! is_null($quote_log->premium_json['motorNonElectricAccessoriesValue']) ? (int) $quote_log->premium_json['motorNonElectricAccessoriesValue'] : 0;
            $lpg_cng_kit_od = isset($quote_log->premium_json['motorLpgCngKitValue']) && ! is_null($quote_log->premium_json['motorLpgCngKitValue']) ? (int) $quote_log->premium_json['motorLpgCngKitValue'] : 0;
            $ncb = isset($quote_log->revised_ncb) && ! is_null($quote_log->revised_ncb) ? (int) $quote_log->revised_ncb : 0;
            $anit_theft = isset($quote_log->premium_json['antitheftDiscount']) && ! is_null($quote_log->premium_json['antitheftDiscount']) ? (int) $quote_log->premium_json['antitheftDiscount'] : 0;
            $voluntary_excess = isset($quote_log->premium_json['voluntaryExcess']) && ! is_null($quote_log->premium_json['voluntaryExcess']) ? (int) $quote_log->premium_json['voluntaryExcess'] : 0;
            $tppd_discount = isset($quote_log->premium_json['tppdDiscount']) && ! is_null($quote_log->premium_json['tppdDiscount']) ? (int) $quote_log->premium_json['tppdDiscount'] : 0;
            $ic_vehicle_discount = isset($quote_log->premium_json['icVehicleDiscount']) && ! is_null($quote_log->premium_json['icVehicleDiscount']) ? (int) $quote_log->premium_json['icVehicleDiscount'] : 0;


            if ($journey_stage->stage == STAGE_NAMES['PROPOSAL_DRAFTED'] || $message_type == 'Proposal Seen')
            {
                if ($quote_log->od_premium != 0 && ! is_null($quote_log->od_premium))
                {
                    $od_premium = $quote_log->od_premium - $electrical_accessories - $non_electrical_accessories - $lpg_cng_kit_od - $ncb - $anit_theft - $voluntary_excess - $ic_vehicle_discount;
                }

                if ($quote_log->tp_premium != 0 && ! is_null($quote_log->tp_premium))
                {
                    $tp_premium = $quote_log->tp_premium - $tppd_discount;
                }

                if ($quote_log->addon_premium != 0 && ! is_null($quote_log->addon_premium))
                {
                    $addon_premium = $quote_log->addon_premium + $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od;
                }
            }

            array_push($activity_request['Fields'], [
                'SchemaName' => 'mx_Custom_7', // IC Name
                'Value' => in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING']]) ? $user_proposal->ic_name : $quote_log->premium_json['companyName']
            ],
            [
                'SchemaName' => 'mx_Custom_8', // OD Premium
                'Value' => in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING']]) ? $user_proposal->od_premium : $od_premium
            ],
            [
                'SchemaName' => 'mx_Custom_9', // TP Premium
                'Value' => in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING']]) ? $user_proposal->tp_premium : $tp_premium
            ],
            [
                'SchemaName' => 'mx_Custom_10', // CPA Premium
                'Value' => in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING']]) ? $user_proposal->cpa_premium : $cpa
            ],
            [
                'SchemaName' => 'mx_Custom_11', // Add-on Premium
                'Value' => in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING']]) ? $user_proposal->addon_premium : $addon_premium
            ],
            [
                'SchemaName' => 'mx_Custom_12', // Total Premium
                'Value' => in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING']]) ? $user_proposal->final_payable_amount : $quote_log->final_premium_amount
            ],
            [
                'SchemaName' => 'mx_Custom_13', // Enquiry ID
                'Value' => customEncrypt($enquiryId)
            ],
            [
                'SchemaName' => 'mx_Custom_14', // Enquiry Link
                'Value' => $journey_stage->proposal_url
            ],
            [
                'SchemaName' => 'mx_Custom_15', // Policy Type
                'Value' => $policy_type
            ],
            [
                'SchemaName' => 'mx_Custom_16', // Policy Tenure
                'Value' => $policy_tenure
            ],
            [
                'SchemaName' => 'mx_Custom_17', // Quote ID
                'Value' => customEncrypt($enquiryId)
            ],
            [
                'SchemaName' => 'mx_Custom_18', // Quote Link
                'Value' => $journey_stage->proposal_url
            ]);

            if (in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING']]))
            {
                array_push($activity_request['Fields'], [
                    'SchemaName' => 'mx_Custom_19', // Proposal ID
                    'Value' => $user_proposal->proposal_no
                ]);
            }

            if (in_array($journey_stage->stage, [ STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_REJECTED']]) && $breakin_status && ! is_null($breakin_status->breakin_number))
            {
                array_push($activity_request['Fields'], [
                    'SchemaName' => 'mx_Custom_20', // Inspection ID
                    'Value' => $breakin_status->breakin_number
                ],
                [
                    'SchemaName' => 'mx_Custom_21', // Inspection Status
                    'Value' => $journey_stage->stage == STAGE_NAMES['INSPECTION_ACCEPTED'] ? 'Approved' : ($journey_stage->stage == STAGE_NAMES['INSPECTION_REJECTED'] ? 'Rejected' : 'Sent for Inspection')
                ]);
            }
        }

        if ($message_type == 'Embedded Link Shared' && isset($additional_data['url']) && isset($additional_data['destination']))
        {
            array_push($activity_request['Fields'], [
                'SchemaName' => 'mx_Custom_2', // Journey URL
                'Value' => $additional_data['url']
            ], [
                'SchemaName' => 'mx_Custom_3', // Date and Time
                'Value' => date('Y-m-d H:i:s')
            ], [
                'SchemaName' => 'mx_Custom_4', // Mobile no.
                'Value' => $additional_data['destination']
            ]);
        }

        if ($create_lead_on == 'lead')
        {
            unset($activity_request['RelatedOpportunityId']);
        }

        $get_response = getWsData(
            config('constants.LSQ.ACTIVITY_CREATE') . '?accessKey=' . config('constants.LSQ.ACCESS_KEY') . '&secretKey=' . config('constants.LSQ.SECRET_KEY'), $activity_request, 'LSQ', [
                'method' => 'Create Activity',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'transaction_type' => 'quote',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        $activity_response = $get_response['response'];

        if ($activity_response)
        {
            $activity_response = json_decode($activity_response, TRUE);

            if (isset($activity_response['Status']) && $activity_response['Status'] == 'Success')
            {
                LsqActivities::create([
                    'enquiry_id' => $enquiryId,
                    'lead_id' => $lsq_journey_id_mapping->lead_id,
                    'stage' => $mx_custom_3,
                    'activity_id' => $activity_response['Message']['Id']
                ]);

                LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                    ->update([
                        'lsq_stage' => $mx_custom_3,
                        'is_duplicate' => 0,
                        'lsq_substage' => $lsq_substage,
                        'is_quote_page_visited' => $mx_custom_3 == STAGE_NAMES['QUOTE'] || $message_type == 'Proposal Seen' || $lsq_journey_id_mapping->is_quote_page_visited ==  1 ? 1 : 0
                    ]);

                return [
                    'status' => TRUE,
                    'message' => 'Activity Created'
                ];
            }
            else
            {
                return [
                    'status' => FALSE,
                    'message' => $activity_response['ExceptionMessage'] ?? 'An error occurred while creating activity'
                ];
            }
        }
        else
        {
            return [
                'status' => FALSE,
                'message' => 'An error occurred while creating activity'
            ];
        }
    }
}
