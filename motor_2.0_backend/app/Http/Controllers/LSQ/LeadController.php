<?php

namespace App\Http\Controllers\LSQ;

use App\Http\Controllers\Controller;
use App\Models\LsqJourneyIdMapping;
use App\Models\UserProductJourney;
use Illuminate\Http\Request;

include_once app_path('/Helpers/CvWebServiceHelper.php'); 

class LeadController extends Controller
{
    public function create($enquiryId, $is_duplicate = FALSE)
    {
        $lead_request = [];

        $user_product_journey = UserProductJourney::find($enquiryId);

        $first_name = $user_product_journey->user_fname ?? 'Dummy Lead ';
        $last_name = $user_product_journey->user_lname ?? customEncrypt($enquiryId);
        $email = $user_product_journey->user_email ?? NULL;
        $phone = $user_product_journey->user_mobile ?? sprintf('%d%d', 9, rand(100000000, 999999999));

        $lead_request = [
            [
                'Attribute' => 'FirstName',
                'Value' => $first_name
            ],
            [
                'Attribute' => 'LastName',
                'Value' => $last_name
            ],
            [
                'Attribute' => 'EmailAddress',
                'Value' => $email
            ],
            [
                'Attribute' => 'Phone',
                'Value' => $phone
            ],
            [
                'Attribute' => 'mx_Is_Dummy_Lead',
                'Value' => is_null($user_product_journey->user_fname) || is_null($user_product_journey->user_lname) || is_null($user_product_journey->user_mobile) ? 'Yes' : 'No'
            ],
            [
                'Attribute' => 'SearchBy',
                'Value' => 'Phone'
            ],
            [
                'Attribute' => 'mx_Origin',
                'Value' => config('constants.LSQ.origin')
            ]
        ];

        $get_response = getWsData(
            config('constants.LSQ.LEAD_CAPTURE') . '?accessKey=' . config('constants.LSQ.ACCESS_KEY') . '&secretKey=' . config('constants.LSQ.SECRET_KEY'), $lead_request, 'LSQ', [
                'method' => 'Create Lead',
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'transaction_type' => 'quote',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        $lead_response = $get_response['response'];

        if ($lead_response)
        {
            $lead_response = json_decode($lead_response, TRUE);

            if (isset($lead_response['Status']) && $lead_response['Status'] == 'Success')
            {
                LsqJourneyIdMapping::updateOrCreate([
                    'enquiry_id' => $enquiryId
                ], [
                    'lead_id' => $lead_response['Message']['RelatedId'],
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'is_dummy_lead' => is_null($user_product_journey->user_fname) || is_null($user_product_journey->user_lname) || is_null($user_product_journey->user_mobile) ? 1 : 0,
                    'is_duplicate' => $is_duplicate ? 1 : 0
                ]);

                return [
                    'status' => TRUE,
                    'lead_id' => $lead_response['Message']['RelatedId']
                ];
            }
            else
            {
                return [
                    'status' => false,
                    'message' => $lead_response['ExceptionMessage'] ?? 'An error occured while creating lead'
                ];
            }
        }
        else
        {
            return [
                'status' => false,
                'message' => 'An error occured while creating lead'
            ];
        }
    }

    public function update($enquiryId)
    {
        $user_product_journey = UserProductJourney::find($enquiryId);
        $user_proposal = $user_product_journey->user_proposal;
        $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

        if (is_null($lsq_journey_id_mapping))
        {
            return [
                'status' => FALSE
            ];
        }

        if ($user_proposal->first_name != $lsq_journey_id_mapping->first_name || $user_proposal->last_name != $lsq_journey_id_mapping->last_name || $user_proposal->email != $lsq_journey_id_mapping->email || $user_proposal->mobile_number != $lsq_journey_id_mapping->phone)
        {
            $lead_request = [
                [
                    'Attribute' => 'FirstName',
                    'Value' => $user_proposal->first_name
                ],
                [
                    'Attribute' => 'LastName',
                    'Value' => $user_proposal->last_name
                ],
                [
                    'Attribute' => 'EmailAddress',
                    'Value' => $user_proposal->email
                ],
                [
                    'Attribute' => 'Phone',
                    'Value' => $user_proposal->mobile_number
                ],
                [
                    'Attribute' => 'mx_Is_Dummy_Lead',
                    'Value' => 'No'
                ],
                [
                    'Attribute' => 'mx_Origin',
                    'Value' => config('constants.LSQ.origin')
                ]
            ];

            $get_response = getWsData(
                config('constants.LSQ.LEAD_UPDATE') . '?accessKey=' . config('constants.LSQ.ACCESS_KEY') . '&secretKey=' . config('constants.LSQ.SECRET_KEY') . '&leadId=' . $lsq_journey_id_mapping->lead_id, $lead_request, 'LSQ', [
                    'method' => 'Update Lead',
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'transaction_type' => 'quote',
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ]
                ]
            );
            $lead_response = $get_response['response'];

            if ($lead_response)
            {
                $lead_response = json_decode($lead_response, TRUE);

                if (isset($lead_response['Status']) && $lead_response['Status'] == 'Success')
                {
                    LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                        ->update([
                            'first_name' => $user_proposal->first_name,
                            'last_name' => $user_proposal->last_name,
                            'email' => $user_proposal->email,
                            'phone' => $user_proposal->mobile_number,
                            'is_dummy_lead' => 0
                        ]);

                    return [
                        'status' => TRUE
                    ];
                }
                else
                {
                    return [
                        'status' => false,
                        'message' => $lead_response['ExceptionMessage'] ?? 'An error occured while creating lead'
                    ];
                }
            }
            else
            {
                return [
                    'status' => false,
                    'message' => 'An error occured while updating lead'
                ];
            }
        }
        else
        {
            return [
                'status' => FALSE
            ];
        }
    }

    public function retrieve($enquiryId)
    {
        $user_product_journey = UserProductJourney::find($enquiryId);
        $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
        $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;

        if ($user_product_journey->user_mobile == $lsq_journey_id_mapping->phone)
        {
            return [
                'status' => FALSE
            ];
        }

        $get_response = getWsData(
            config('constants.LSQ.LEAD_RETRIEVE') . '?accessKey=' . config('constants.LSQ.ACCESS_KEY') . '&secretKey=' . config('constants.LSQ.SECRET_KEY') . '&phone=' . $user_product_journey->user_mobile, [], 'LSQ', [
                'method' => 'Retrieve Lead',
                'enquiryId' => $enquiryId,
                'requestMethod' => 'get',
                'transaction_type' => 'quote',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        $lead_response = $get_response['response'];

        if ($lead_response)
        {
            $lead_response = json_decode($lead_response, TRUE);

            if (isset($lead_response[0]))
            {
                LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                    ->update([
                        'lead_id' => $lead_response[0]['ProspectID']
                    ]);

                if ($corporate_vehicles_quote_request && isset($corporate_vehicles_quote_request->vehicle_registration_no) && ! is_null($corporate_vehicles_quote_request->vehicle_registration_no))
                {
                    createLsqOpportunity($enquiryId, NULL, [
                        'rc_number' => $corporate_vehicles_quote_request->vehicle_registration_no,
                        'lead_updated' => TRUE
                    ]);
                }

                return [
                    'status' => TRUE,
                    'lead_id' => $lead_response[0]['ProspectID']
                ];
            }
            else
            {
                return [
                    'status' => FALSE,
                    'message' => 'An error occurred while retrieving lead'
                ];
            }
        }
        else
        {
            return [
                'status' => FALSE,
                'message' => 'An error occurred while retrieving lead'
            ];
        }
    }
}
