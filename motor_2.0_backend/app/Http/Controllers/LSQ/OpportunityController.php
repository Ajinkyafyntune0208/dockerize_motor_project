<?php

namespace App\Http\Controllers\LSQ;

use App\Http\Controllers\Controller;
use App\Models\LsqJourneyIdMapping;
use App\Models\UserProductJourney;
use Illuminate\Http\Request;

include_once app_path('/Helpers/CvWebServiceHelper.php');

class OpportunityController extends Controller
{
    public function create($enquiryId, $custom_stage = NULL, $additional_data = [])
    {
        $user_product_journey = UserProductJourney::find($enquiryId);
        $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
        $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
        $agent_details = $user_product_journey->agent_details;
        $journey_stage = $user_product_journey->journey_stage;
        $user_proposal = $user_product_journey->user_proposal;

        if (is_null($corporate_vehicles_quote_request))
        {
            return [
                'status' => FALSE,
                'message' => 'Journey data not found'
            ];
        }

        if ($corporate_vehicles_quote_request->business_type != 'newbusiness' && (( ! is_null($additional_data) && isset($additional_data['rc_number'])) || ! is_null($corporate_vehicles_quote_request->vehicle_registration_no)))
        {
            $rc_number = str_replace('--', '-', (isset($additional_data['rc_number']) ? $additional_data['rc_number'] : $corporate_vehicles_quote_request->vehicle_registration_no));

            // Check if opportunity is already present for the provided rc number
            // $additional_data['lead_updated'] is used here to check for existing opportunity of newly fetched lead
            $opportunity = retrieveLsqOpportunity($enquiryId, $rc_number, $additional_data['lead_updated'] ?? FALSE);

            if ( ! $opportunity['status'])
            {
                // If opportunity is present then just update it
                return updateLsqOpportunity($enquiryId, $custom_stage, [
                    'rc_number' => $additional_data['rc_number'],
                    'lead_updated' => $additional_data['lead_updated'] ?? FALSE
                ]);
            }

            // Otherwise create new one
        }

        $opportunity_stage = '';
 
        if ($custom_stage == 'RC Submitted')
        {
            $opportunity_stage = 'RC Submitted';
        }
        elseif ($journey_stage->stage == STAGE_NAMES['QUOTE'])
        {
            $opportunity_stage = 'Quote Seen';
        }
        elseif ($journey_stage->stage == STAGE_NAMES['PROPOSAL_DRAFTED'] || $additional_data['lsq_stage'] == 'Proposal Seen')
        {
            $opportunity_stage = 'Proposal Seen';
        }

        $opportunity_request = [
            'LeadDetails' => [
                [
                    'Attribute' => 'Phone',
                    'Value' => $user_product_journey->user_mobile ?? $lsq_journey_id_mapping->phone
                ],
                [
                    'Attribute' => 'SearchBy',
                    'Value' => 'Phone'
                ]
            ],
            'Opportunity' => [
                'OpportunityEventCode' => 12000,
                'Fields' => [
                    [
                        'SchemaName' => 'mx_Custom_1', //Opportunity Name
                        'Value' => 'Opportunity'
                    ],
                    [
                        'SchemaName' => 'mx_Custom_2', //Opportunity Stage
                        'Value' => $opportunity_stage
                    ],
                    [
                        'SchemaName' => 'mx_Custom_13', // Sale Type
                        'Value' => 'Fresh'
                    ],
                    [
                        'SchemaName' => 'Status', // Status
                        'Value' => 'Open'
                    ],
                    [
                        'SchemaName' => 'mx_Custom_11', // Origin
                        'Value' => config('constants.LSQ.origin')
                    ]
                ]
            ]
        ];

        if ($custom_stage != 'RC Submitted')
        {
            if ($corporate_vehicles_quote_request->business_type != 'newbusiness' && $corporate_vehicles_quote_request->previous_policy_expiry_date != '')
            {
                array_push($opportunity_request['Opportunity']['Fields'], [
                    'SchemaName' => 'mx_Custom_18', // Old Policy Expiry Date
                    'Value' => $corporate_vehicles_quote_request->previous_policy_expiry_date != '' || is_null($corporate_vehicles_quote_request->previous_policy_expiry_date) ? date('Y-m-d', strtotime($corporate_vehicles_quote_request->previous_policy_expiry_date)) : NULL
                ]);
            }
        }
        else
        {
            if ( ! is_null($user_product_journey->user_fname))
            {
                array_push($opportunity_request['Opportunity']['Fields'], [
                    'SchemaName' => 'mx_Custom_12', // Owner Name
                    'Value' => $user_product_journey->user_fname . ' ' . $user_product_journey->user_lname
                ]);
            }
        }

        if ($corporate_vehicles_quote_request->business_type != 'newbusiness' && (( ! is_null($additional_data) && isset($additional_data['rc_number'])) || ! is_null($corporate_vehicles_quote_request->vehicle_registration_no)))
        {
            array_push($opportunity_request['Opportunity']['Fields'], [
                'SchemaName' => 'mx_Custom_4', //RC Number
                'Value' => isset($additional_data['rc_number']) ? str_replace('-', '', $additional_data['rc_number']) : str_replace('-', '', $corporate_vehicles_quote_request->vehicle_registration_no)
            ]);
        }

        if (isset($agent_details[count($agent_details) - 1]->seller_type) && $agent_details[count($agent_details) - 1]->seller_type == 'P')
        {
            array_push($opportunity_request['Opportunity']['Fields'], [
                'SchemaName' => 'mx_Custom_36', // POS ID
                'Value' => $agent_details[count($agent_details) - 1]->agent_id
            ],
            [
                'SchemaName' => 'mx_Custom_37', // POS Name
                'Value' => $agent_details[count($agent_details) - 1]->agent_name
            ]);
        }

        $get_response = getWsData(
            config('constants.LSQ.OPPORTUNITY_CAPTURE') . '?accessKey=' . config('constants.LSQ.ACCESS_KEY') . '&secretKey=' . config('constants.LSQ.SECRET_KEY'), $opportunity_request, 'LSQ', [
                'method' => 'Create Opportunity',
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'transaction_type' => 'quote',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        $opportunity_response = $get_response['response'];

        if ($opportunity_response)
        {
            $opportunity_response = json_decode($opportunity_response, TRUE);

            if (isset($opportunity_response['CreatedOpportunityId']) && ! is_null($opportunity_response['CreatedOpportunityId']))
            {
                LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                    ->update([
                        'opportunity_id' => $opportunity_response['CreatedOpportunityId'],
                        'rc_number' => $corporate_vehicles_quote_request->business_type != 'newbusiness' ? ( ! is_null($additional_data) && isset($additional_data['rc_number']) ? $additional_data['rc_number'] : $corporate_vehicles_quote_request->vehicle_registration_no) : NULL
                    ]);

                return [
                    'status' => TRUE,
                    'opportunity_id' => $opportunity_response['CreatedOpportunityId']
                ];
            }
            else
            {
                return [
                    'status' => FALSE,
                    'message' => $opportunity_response['ExceptionMessage'] ?? 'An error occured while creating opportunity'
                ];
            }
        }
        else
        {
            return [
                'status' => FALSE,
                'message' => 'An error occured while creating opportunity'
            ];
        }
    }

    public function update($enquiryId, $message_type = NULL, $additional_data = [])
    {
        $user_product_journey = UserProductJourney::find($enquiryId);
        $journey_stage = $user_product_journey->journey_stage;
        $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
        $user_proposal = $user_product_journey->user_proposal;
        $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
        $quote_log = $user_product_journey->quote_log;
        $agent_details = $user_product_journey->agent_details;

        if ($corporate_vehicles_quote_request->business_type != 'newbusiness' && ! isset($additional_data['rc_number']) && ( ! isset($corporate_vehicles_quote_request->vehicle_registration_no) || is_null($corporate_vehicles_quote_request->vehicle_registration_no)) && ( ! isset($user_proposal->vehicale_registration_number) || is_null($user_proposal->vehicale_registration_number)))
        {
            LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                ->update([
                    'opportunity_id' => NULL,
                    'rc_number' => NULL
                ]);

            return [
                'status' => FALSE
            ];
        }

        if (is_null($lsq_journey_id_mapping) || ($journey_stage->stage == $lsq_journey_id_mapping->lsq_stage && ( ! in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_ACCEPTED']]) && ( ! isset($additional_data['lead_updated']) || (isset($additional_data['lead_updated']) && ! $additional_data['lead_updated'])))))
        {
            return [
                'status' => FALSE
            ];
        }

        if ($message_type == 'RC Submitted')
        {
            $opportunity_stage = 'RC Submitted';
        }
        elseif ($message_type == 'MMV Form Submitted')
        {
            $opportunity_stage = 'MMV Form Submitted';
        }
        elseif ($journey_stage->stage == STAGE_NAMES['QUOTE'])
        {
            $opportunity_stage = 'Quote Seen';
        }
        elseif ($message_type == 'shareQuotes')
        {
            $opportunity_stage = 'Quote Shared';
        }
        elseif ($journey_stage->stage == STAGE_NAMES['PROPOSAL_DRAFTED'])
        {
            $opportunity_stage = 'Proposal Seen';
        }
        elseif ($message_type == 'shareProposal')
        {
            $opportunity_stage = 'Proposal Shared';
        }
        elseif ($journey_stage->stage == STAGE_NAMES['PROPOSAL_ACCEPTED'])
        {
            $opportunity_stage = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        }
        elseif ($message_type == 'proposalCreated')
        {
            $opportunity_stage = 'Payment Link Sent';
        }
        elseif ($journey_stage->stage == STAGE_NAMES['INSPECTION_PENDING'])
        {
            $opportunity_stage = STAGE_NAMES['INSPECTION_PENDING'];
        }
        elseif ($journey_stage->stage == STAGE_NAMES['INSPECTION_ACCEPTED'])
        {
            $opportunity_stage = STAGE_NAMES['INSPECTION_ACCEPTED'];
        }
        elseif ($journey_stage->stage == STAGE_NAMES['INSPECTION_REJECTED'])
        {
            $opportunity_stage = STAGE_NAMES['INSPECTION_REJECTED'];
        }
        elseif ($journey_stage->stage == STAGE_NAMES['PAYMENT_INITIATED'])
        {
            $opportunity_stage = STAGE_NAMES['PAYMENT_INITIATED'];
        }
        elseif ($journey_stage->stage == STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'])
        {
            $opportunity_stage = 'Payment Completed';
        }
        elseif ($journey_stage->stage == STAGE_NAMES['PAYMENT_FAILED'])
        {
            $opportunity_stage = STAGE_NAMES['PAYMENT_FAILED'];
        }
        elseif ($journey_stage->stage == STAGE_NAMES['POLICY_ISSUED'])
        {
            $opportunity_stage = STAGE_NAMES['POLICY_ISSUED'];
        }
        else
        {
            return [
                'status' => FALSE
            ];
        }

        $opportunity_request = [
            'ProspectOpportunityId' => $lsq_journey_id_mapping->opportunity_id,
            'Fields' => [
                [
                    'SchemaName' => 'mx_Custom_2', //Opportunity Stage
                    'Value' => $opportunity_stage
                ],
                [
                    'SchemaName' => 'mx_Custom_11', // Origin
                    'Value' => config('constants.LSQ.origin')
                ]
            ]
        ];

        if ( ! in_array($journey_stage->stage, [ STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['POLICY_ISSUED']]))
        {
            array_push($opportunity_request['Fields'], [
                'SchemaName' => 'mx_Custom_13', // Sale Type
                'Value' => 'Fresh'
            ]);

            if ( ! is_null($user_product_journey->user_fname) && (in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_DRAFTED'], STAGE_NAMES['PROPOSAL_ACCEPTED']]) || $message_type == 'shareProposal'))
            {
                array_push($opportunity_request['Fields'], [
                    'SchemaName' => 'mx_Custom_12', // Owner Name
                    'Value' => $journey_stage->stage == STAGE_NAMES['PROPOSAL_ACCEPTED'] ? $user_proposal->first_name . ' ' . $user_proposal->last_name : $user_product_journey->user_fname . ' ' . $user_product_journey->user_lname
                ]);
            }
        }

        if ($corporate_vehicles_quote_request->business_type != 'newbusiness')
        {
            $registration_number = '';

            if (isset($additional_data['rc_number']))
            {
                $registration_number = $additional_data['rc_number'];
            }
            elseif (isset($corporate_vehicles_quote_request->vehicle_registration_no) && ! is_null($corporate_vehicles_quote_request->vehicle_registration_no) && in_array($journey_stage->stage, [ STAGE_NAMES['QUOTE'], STAGE_NAMES['PROPOSAL_DRAFTED']]))
            {
                $registration_number = $corporate_vehicles_quote_request->vehicle_registration_no;
            }
            else
            {
                $registration_number = $user_proposal->vehicale_registration_number ?? '';
            }

            if ($registration_number != '')
            {
                array_push($opportunity_request['Fields'], [
                    'SchemaName' => 'mx_Custom_4', //RC Number
                    'Value' => str_replace('-', '', $registration_number)
                ]);
            }

            if ($message_type != 'MMV Form Submitted')
            {
                array_push($opportunity_request['Fields'], [
                    'SchemaName' => 'mx_Custom_18', // Old Policy Expiry Date
                    'Value' => is_null($corporate_vehicles_quote_request->previous_policy_expiry_date) ? NULL : date('Y-m-d', strtotime($corporate_vehicles_quote_request->previous_policy_expiry_date))
                ]);
            }
        }

        if ($journey_stage->stage == STAGE_NAMES['PROPOSAL_DRAFTED'] || $journey_stage->stage == STAGE_NAMES['PROPOSAL_ACCEPTED'])
        {
            array_push($opportunity_request['Fields'], [
                'SchemaName' => 'mx_Custom_33', // Policy Start Date
                'Value' => $journey_stage->stage == STAGE_NAMES['PROPOSAL_ACCEPTED'] ? date('Y-m-d', strtotime($user_proposal->policy_start_date)) : date('Y-m-d', strtotime($quote_log->premium_json['policyStartDate']))
            ]);
        }

        if ($journey_stage->stage == STAGE_NAMES['PROPOSAL_ACCEPTED'])
        {
            array_push($opportunity_request['Fields'], [
                'SchemaName' => 'mx_Custom_24', // New Policy End Date
                'Value' => date('Y-m-d', strtotime($user_proposal->policy_end_date))
            ],
            [
                'SchemaName' => 'mx_Custom_34', // Pincode
                'Value' => $user_proposal->pincode 
            ],
            [
                'SchemaName' => 'mx_Custom_35', // City
                'Value' => $user_proposal->city
            ]);
        }

        if (isset($agent_details->seller_type) && $agent_details->seller_type == 'P')
        {
            array_push($opportunity_request['Fields'], [
                'SchemaName' => 'mx_Custom_36', // POS ID
                'Value' => $agent_details->agent_id
            ],
            [
                'SchemaName' => 'mx_Custom_37', // POS Name
                'Value' => $agent_details->agent_name
            ]);
        }

        if ($journey_stage->stage == STAGE_NAMES['POLICY_ISSUED'])
        {
            array_push($opportunity_request['Fields'], [
                'SchemaName' => 'mx_Custom_24', // New Policy End Date
                'Value' => date('Y-m-d', strtotime($user_proposal->policy_end_date))
            ],
            [
                'SchemaName' => 'Status', // Status
                'Value' => 'Won'
            ]);
        }

        $get_response = getWsData(
            config('constants.LSQ.OPPORTUNITY_UPDATE') . '?accessKey=' . config('constants.LSQ.ACCESS_KEY') . '&secretKey=' . config('constants.LSQ.SECRET_KEY'), $opportunity_request, 'LSQ', [
                'method' => 'Update Opportunity',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'transaction_type' => 'quote',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        $opportunity_response = $get_response['response'];

        if ($opportunity_response)
        {
            $opportunity_response = json_decode($opportunity_response, TRUE);

            if (isset($opportunity_response['Status']) && $opportunity_response['Status'] == 'Success')
            {
                LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                    ->update([
                        'rc_number' => $corporate_vehicles_quote_request->business_type != 'newbusiness' ? ( ! is_null($additional_data) && isset($additional_data['rc_number']) ? $additional_data['rc_number'] : $corporate_vehicles_quote_request->vehicle_registration_no) : NULL
                    ]);

                return [
                    'status' => TRUE,
                    'message' => 'Opportunity data updated'
                ];
            }
            else
            {
                return [
                    'status' => FALSE,
                    'message' => $opportunity_response['ExceptionMessage'] ?? 'An error occurred while updating opportunity'
                ];
            }
        }
        else
        {
            return [
                'status' => FALSE,
                'message' => 'An error occurred while updating opportunity'
            ];
        }
    }

    public function retrieve($enquiryId, $rc_number = NULL, $lead_updated = FALSE)
    {
        $user_product_journey = UserProductJourney::find($enquiryId);
        $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

        if ( ! $lsq_journey_id_mapping || ($lsq_journey_id_mapping && ! is_null($lsq_journey_id_mapping->opportunity_id ) && ! $lead_updated))
        {
            return [
                'status' => FALSE
            ];
        }

        $opportunity_request = [
            'Columns' => [
                'Include_CSV' => 'Status,mx_Custom_4'
            ],
            'Sorting' => [
                'ColumnName' => 'Status',
                'Direction' => '1'
            ]
        ];

        $get_response = getWsData(
            config('constants.LSQ.OPPORTUNITY_RETRIEVE') . '?accessKey=' . config('constants.LSQ.ACCESS_KEY') . '&secretKey=' . config('constants.LSQ.SECRET_KEY') . '&leadId=' . $lsq_journey_id_mapping->lead_id . '&opportunityType=12000', $opportunity_request, 'LSQ', [
                'method' => 'Retrive Opportunity',
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'transaction_type' => 'quote',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        $opportunity_response = $get_response['response'];

        if ($opportunity_response)
        {
            $opportunity_response = json_decode($opportunity_response, TRUE);

            if (isset($opportunity_response['RecordCount']) && $opportunity_response['RecordCount'] > 0)
            {
                $status = TRUE;

                $rc_number = str_replace('-', '', $rc_number);

                foreach ($opportunity_response['List'] as $opportunity_list)
                {
                    $opportunity_list['mx_Custom_4'] = str_replace('-', '', $opportunity_list['mx_Custom_4']);

                    if ($opportunity_list['Status'] == 'Open' && $opportunity_list['mx_Custom_4'] == $rc_number)
                    {
                        $status = FALSE;

                        LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                            ->update([
                                'opportunity_id' => $opportunity_list['OpportunityId']
                            ]);

                        continue;
                    }
                }

                return [
                    'status' => $status
                ];
            }
            else
            {
                return [
                    'status' => TRUE  
                ];
            }
        }
        else
        {
            return [
                'status' => TRUE  
            ];
        }
    }
}