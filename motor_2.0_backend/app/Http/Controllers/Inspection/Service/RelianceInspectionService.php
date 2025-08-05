<?php

namespace App\Http\Controllers\Inspection\Service;
use Illuminate\Support\Facades\DB;
use App\Models\JourneyStage;
use App\Models\PolicyDetails;
use App\Models\CvBreakinStatus;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use App\Models\UserProposal;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

class RelianceInspectionService
{
    public static function wimwisureInspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::with([
            'user_proposal',
            'user_proposal.quote_log',
            'user_proposal.journey_stage',
            'user_proposal.corporate_vehicles_quotes_request'
        ])
        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
        ->first();

        if (!empty($breakinDetails))
        {
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();

            if ($policy_details)
            {
                return response()->json([
                    'status' => false,
                    'message' => 'Policy has already been generated for this inspection number'
                ]);
            }
            else
            {

                $quoteLog = QuoteLog::where('user_product_journey_id', $breakinDetails->user_proposal->user_product_journey_id)->first();
                if (!empty($quoteLog) && !empty($quoteLog->premium_json['waiverExpiry'])) {
                    $premiumJson = $quoteLog->premium_json; 
                    $waiverDate = new \DateTime($premiumJson['waiverExpiry']);
                    $today = new \DateTime(date('d-m-Y'));
                
                        if ($waiverDate < $today) {
                            $premiumJson['isInspectionWaivedOff'] = false; 
                            $quoteLog->premium_json = $premiumJson; 
                            $quoteLog->save();
                        }
              
                }  
                $inspection_result = json_decode($breakinDetails->breakin_response, true);

                if ( ! empty($inspection_result))
                {
                    $inspection_result = array_change_key_case($inspection_result, CASE_LOWER);
                }

                if ( ! isset($inspection_result['remarks']) || (isset($inspection_result['remarks']) && $inspection_result['remarks'] != 'APPROVED'))
                {
                    $request->api_key = config('constants.wimwisure.API_KEY_RELIANCE');
                    $inspection = new WimwisureBreakinController();
                    $inspection_result = $inspection->WimwisureCheckInspection($request);
                }

                if (isset($inspection_result['remarks']) && $inspection_result['remarks'] == 'APPROVED')
                {
                    $productData = getProductDataByIc($breakinDetails->user_proposal->quote_log->master_policy_id);

                    $parent_id = get_parent_code($productData->product_sub_type_id);

                    $premium_type = DB::table('master_premium_type')
                        ->where('id',$productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();

                    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

                    $mmv = get_mmv_details(
                        $productData,
                        $breakinDetails->user_proposal->corporate_vehicles_quotes_request->version_id,
                        'reliance',
                        $parent_id == 'GCV' ? $breakinDetails->user_proposal->corporate_vehicles_quotes_request->gcv_carrier_type : NULL
                    );

                    if ($mmv['status'] == 1)
                    {
                        $mmv = $mmv['data'];
                    }
                    else
                    {
                        return  response()->json([
                            'premium_amount' => '0',
                            'status' => false,
                            'message' => $mmv['message']
                        ]);
                    }

                    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

                    $corres_address_data = DB::table('reliance_pincode_state_city_master')
                        ->where('pincode', $breakinDetails->user_proposal->pincode)
                        ->first();

                    $lead_array = [
                        'LEADDESCRIPTION' => [
                            'LEADID' => trim($request->inspectionNo),
                            'CUSTOMERFNAME' => $breakinDetails->user_proposal->first_name,
                            'CUSTOMERMNAME' => '',
                            'CUSTOMERLNAME' => $breakinDetails->user_proposal->last_name,
                            'CUSTOMERADDRESS1' => $breakinDetails->user_proposal->address_line1,
                            'CUSTOMERADDRESS2' => $breakinDetails->user_proposal->address_line2,
                            'CUSTOMERADDRESS3' => $breakinDetails->user_proposal->address_line3,
                            'CUSTOMERCONTACTNO' => $breakinDetails->user_proposal->mobile_number,
                            'CUSTOMERTELEPHONENO' => '',
                            'CUSTOMEREMAILID' => $breakinDetails->user_proposal->email,
                            'VEHICLEREGNO' => $breakinDetails->vehicale_registration_number,
                            'MAKE' => $mmv_data->make_id_pk,
                            'MAKEVALUE' => $mmv_data->make_name,
                            'MODEL' => $mmv_data->model_id_pk,
                            'SEATING_CAPACITY' => $mmv_data->seating_capacity,
                            'MODELVALUE' => $mmv_data->variance,
                            'ENGINENO' => $breakinDetails->user_proposal->engine_number,
                            'CHASSISNO' => $breakinDetails->user_proposal->chassis_number,
                            'STATE' => $corres_address_data->state_id_pk,
                            'STATEVALUE' => $corres_address_data->state_name,
                            'DISTRICT' => $corres_address_data->district_id_pk,
                            'DISTRICTVALUE' => $corres_address_data->district_name,
                            'CITY' => $corres_address_data->city_or_village_id_pk,
                            'CITYVALUE' => $corres_address_data->city_or_village_name,
                            'PINCODE' => $breakinDetails->user_proposal->pincode,
                            'VECH_INSP_ADDRESS' => $breakinDetails->user_proposal->address_line1,
                            'VECH_INSP_ADDRESS2' => $breakinDetails->user_proposal->address_line2,
                            'VECH_INSP_ADDRESS3' => $breakinDetails->user_proposal->address_line3,
                            'REMARK' => '',
                            'LEADTYPE' => 'F',
                            'STATE_VEH' => $corres_address_data->state_id_pk,
                            'STATEVALUE_VEH' => $corres_address_data->state_name,
                            'DISTRICT_VEH' => $corres_address_data->district_id_pk,
                            'DISTRICTVALUE_VEH' => $corres_address_data->district_name,
                            'CITY_VEH' => $corres_address_data->city_or_village_id_pk,
                            'CITYVALUE_VEH' => $corres_address_data->city_or_village_name,
                            'PINCODE_VEH' => $breakinDetails->user_proposal->pincode,
                            'STATUSCODE' => 2,//$status_code_array[$lead_status],
                            'DIST_WITHINCORPORATIONRANGE' => '',
                            'DIST_OUTSIDECORPORATIONRANGE' => '',
                            'LEADUPDATEDBY' => 'reliance',
                            'INSPECTIONDONEBYAGENCYDATETIME' => date('Y-m-d H:i:s.B'),
                            'AGENCYREFNO' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_AGENCYREFNO'),
                            'AGENCY_CODE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_AGENCY_CODE')
                        ]
                    ];

                    $lead_array = ArrayToXML::convert($lead_array, 'LEAD');
                    $lead_array = preg_replace("/<\\?xml .*\\?>/i", '', $lead_array);

                    $root = [
                        'rootElementName' => 'soapenv:Envelope',
                        '_attributes' => [
                            'xmlns:soapenv' => 'http://schemas.xmlsoap.org/soap/envelope/',
                            'xmlns:tem' => 'http://tempuri.org/'
                        ]
                    ];
            
                    $request_array = [
                        'soapenv:Header' => [
                            'tem:UserCredential' => [
                                'tem:UserID' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_USERID'),
                                'tem:UserPassword' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_USERPASSWORD')
                            ]
                        ],
                        'soapenv:Body' => [
                            'tem:UpdateLead' => [
                                'tem:xmlstring' => [
                                    '_cdata' => $lead_array
                                ]
                            ]
                        ]
                    ];

                    $lead_array = ArrayToXml::convert($request_array, $root, false);

                    $headers = ((config('constants.IcConstants.reliance.IS_WIMWISURE_RELIANCE_ENABLED') == 'Y') && config('constants.IcConstants.reliance.IS_WIMWISURE_RELIANCE_ENABLED_SHORT_TERM') != 'Y') ? [
                        'Content-type' => 'text/xml'
                    ] : [
                        'Content-type' => 'text/xml',
                        'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.RELIANCE_CV_OCP_APIM_SUBSCRIPTION_KEY_FOR_INSERT_LEAD'),
                        'SOAPAction' => config('constants.IcConstants.reliance.RELIANCE_CV_SOAP_ACTION_FOR_UPDATE_LEAD')
                    ];

                    $headers['Ocp-Apim-Subscription-Key'] = config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY');

                    $get_response = getWsData(config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_LEAD_UPDATE'),
                        $lead_array, 'reliance', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Lead Updation',
                            'requestMethod' => 'post',
                            'enquiryId' => $breakinDetails->user_proposal->user_product_journey_id,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'proposal',
                            'headers' => $headers
                        ]
                    );
                    $lead_res_data = $get_response['response'];

                    if ($lead_res_data)
                    {
                        $lead_response = XmlToArray::convert($lead_res_data);

                        if (isset($lead_response['soap:Body']['UpdateLeadResponse']['UpdateLeadResult']) && ($lead_response['soap:Body']['UpdateLeadResponse']['UpdateLeadResult'] == '1 | Record Updated Successfully' || $lead_response['soap:Body']['UpdateLeadResponse']['UpdateLeadResult'] == 'Lead Number status is Allready Recommended'))
                        {
                            $policy_start_date = date('d-m-Y', time());

                            if ( in_array($premium_type, ['short_term_3', 'short_term_3_breakin']))
                            {
                                $policy_end_date = date('d-m-Y', strtotime('+3 month -1 day', strtotime($policy_start_date)));
                            }
                            elseif ( in_array($premium_type, ['short_term_6', 'short_term_6_breakin']))
                            {
                                $policy_end_date = date('d-m-Y', strtotime('+6 month -1 day', strtotime($policy_start_date)));
                            }
                            else
                            {
                                $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                            }

                            UserProposal::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                                ->update([
                                    'is_inspection_done' => 'Y',
                                    'policy_start_date' => $policy_start_date,
                                    'policy_end_date' => $policy_end_date
                                ]);

                            return response()->json([
                                'status' => true,
                                'msg' => 'Your Vehicle Inspection is Done By Reliance.',
                                'data'   => [
                                    'enquiryId' => customEncrypt($breakinDetails->user_proposal->user_product_journey_id),
                                    'proposalNo' => $breakinDetails->user_proposal_id,
                                    'totalPayableAmount' => $breakinDetails->user_proposal->final_payable_amount,
                                    'proposalUrl' =>  str_replace('quotes','proposal-page', $breakinDetails->user_proposal->journey_stage->proposal_url)
                                ]
                            ]);
                        }
                        else
                        {
                            CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))   
                                ->update([
                                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC']
                                ]);

                            return response()->json([
                                'status' => false,
                                'message' => $lead_response['soap:Body']['UpdateLeadResponse']['UpdateLeadResult']
                            ]);
                        }
                    }
                    else
                    {
                        CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))   
                            ->update([
                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC']
                            ]);

                        return response()->json([
                            'status' => true,
                            'msg' => 'Error occurred in lead updation service'
                        ]);
                    }
                }
                else
                {
                    $status = false;

                    if (isset($inspection_result['remarks']) && $inspection_result['remarks'] == 'REJECTED')
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,                               
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED'],                             
                        ]);

                        $message = 'Your Vehicle Inspection has been rejected';
                    }
                    else
                    {
                        $message = 'Your Vehicle Inspection status is ' . $inspection_result['remarks'];
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

    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))->first();

        if ($breakinDetails)
        {
            $user_proposal = $breakinDetails->user_proposal;
            $quote_log = $user_proposal->quote_log;
            $journey_stage = $user_proposal->user_product_journey->journey_stage;
            $policy_details = $user_proposal->policy_details;

            $productData = getProductDataByIc($quote_log->master_policy_id);

            if ($policy_details)
            {
                return response()->json([
                    'status' => false,
                    'message' => 'Policy has already been generated for this inspection number'
                ]);
            } elseif ($breakinDetails->breakin_status == STAGE_NAMES['INSPECTION_APPROVED']) {
                $premium_type = DB::table('master_premium_type')
                ->where('id', $productData->premium_type_id)
                    ->pluck('premium_type_code')
                    ->first();

                $policy_start_date = date('d-m-Y', time());

                if (in_array($premium_type, ['short_term_3', 'short_term_3_breakin'])) {
                    $policy_end_date = date('d-m-Y', strtotime('+3 month -1 day', strtotime($policy_start_date)));
                } elseif (in_array($premium_type, ['short_term_6', 'short_term_6_breakin'])) {
                    $policy_end_date = date('d-m-Y', strtotime('+6 month -1 day', strtotime($policy_start_date)));
                } else {
                    $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                }

                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->update([
                        'is_inspection_done' => 'Y',
                        'policy_start_date' => $policy_start_date,
                        'policy_end_date' => $policy_end_date
                    ]);
                return response()->json([
                    'status' => true,
                    'msg' => 'Your Vehicle Inspection is Done By Reliance.',
                    'data'   => [
                        'enquiryId' => customEncrypt($user_proposal->user_product_journey_id),
                        'proposalNo' => $user_proposal->user_proposal_id,
                        'totalPayableAmount' => $user_proposal->final_payable_amount,
                        'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journey_stage->proposal_url)
                    ]
                ]);
            }
            else
            {
                $inspection_result = json_decode($breakinDetails->breakin_response, TRUE);

                if ( ! isset($inspection_result['InspectionStatus']) || (isset($inspection_result['InspectionStatus']) && $inspection_result['InspectionStatus'] != 'Recommended'))
                {
                    $corporateVehiclesQuotesRequest = $user_proposal->corporate_vehicles_quotes_request;
                    $vehicaleRegistrationNumber = $user_proposal->vehicale_registration_number;

                    $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
                        $vehicaleRegistrationNumber,
                        $corporateVehiclesQuotesRequest->rto_code,
                        $corporateVehiclesQuotesRequest->business_type == 'newbusiness'
                    );

                    if ($rcDetails['status']) {
                        $vehicaleRegistrationNumber = $rcDetails['rcNumber'];
                    }

                    // $vehicaleRegistrationNumber = explode('-', $vehicaleRegistrationNumber); 
                    // if ($vehicaleRegistrationNumber[0] == 'DL') { 
                    //     $registration_no = RtoCodeWithOrWithoutZero($vehicaleRegistrationNumber[0].$vehicaleRegistrationNumber[1],true);
                    //     $vehicaleRegistrationNumber = $registration_no.'-'.$vehicaleRegistrationNumber[2].'-'.$vehicaleRegistrationNumber[3]; 
                    // } else {
                    //     $vehicaleRegistrationNumber = $user_proposal->vehicale_registration_number;
                    // }

                    $lead_details = [
                        'LeadID' => $breakinDetails->breakin_number,
                        'AgencyCode' => $user_proposal->inspection_type == 'Manual' ? config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_AGENCY_CODE_MANUAL') : config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_AGENCY_CODE'),
                        'VehicleRegNumber' => str_replace('-', '', $vehicaleRegistrationNumber)
                    ];

                    $get_response = getWsData(config('constants.IcConstants.reliance.RELIANCE_MOTOR_FETCH_LEAD_DETAILS_URL'), $lead_details, 'reliance', [
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Lead Fetch Details',
                        'requestMethod' => 'post',
                        'enquiryId' => $user_proposal->user_product_journey_id,
                        'productName' => $productData->product_name,
                        'transaction_type' => 'proposal',
                        'headers' => [
                            'Content-type' => 'application/json',
                            'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                        ]
                    ]);
                    $inspection_result = $get_response['response'];

                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'breakin_response' => $inspection_result
                        ]);

                    $inspection_result = json_decode($inspection_result, TRUE);
                }

                if (isset($inspection_result['InspectionStatus']) && $inspection_result['InspectionStatus'] == 'Recommended')
                {
                    $premium_type = DB::table('master_premium_type')
                        ->where('id',$productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();

                    $policy_start_date = date('d-m-Y', time());

                    if ( in_array($premium_type, ['short_term_3', 'short_term_3_breakin']))
                    {
                        $policy_end_date = date('d-m-Y', strtotime('+3 month -1 day', strtotime($policy_start_date)));
                    }
                    elseif ( in_array($premium_type, ['short_term_6', 'short_term_6_breakin']))
                    {
                        $policy_end_date = date('d-m-Y', strtotime('+6 month -1 day', strtotime($policy_start_date)));
                    }
                    else
                    {
                        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                    }

                    UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->update([
                            'is_inspection_done' => 'Y',
                            'policy_start_date' => $policy_start_date,
                            'policy_end_date' => $policy_end_date
                        ]);

                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                    ->update([
                        'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                        'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                        'updated_at'            => date('Y-m-d H:i:s'),
                        //'payment_url'           =>  str_replace('quotes','proposal-page',$breakinDetails->proposal_url),
                        'breakin_check_url'     => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                        'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 2 day')),
                        'inspection_date'       => date('Y-m-d'),
                    ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                    ]);

                    return response()->json([
                        'status' => true,
                        'msg' => 'Your Vehicle Inspection is Done By Reliance.',
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
                    $status = false;

                    if (isset($inspection_result['InspectionStatus']) && $inspection_result['InspectionStatus'] == 'Not Recommended')
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,                               
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED'],                             
                        ]);

                        $message = 'Your Vehicle Inspection has been rejected,Reason : ' . $inspection_result['Remark'] ?? '';
                    }
                    elseif(isset($inspection_result['InspectionStatus']) && $inspection_result['InspectionStatus'] == 'Inspection Required')
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        ]);

                        $message = 'Inspection Required : ' . $inspection_result['Remark'] ?? '';
        
                    } 
                    elseif (isset($inspection_result['InspectionStatus']) && $inspection_result['InspectionStatus'] == 'Closed') 
                    {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED'],
                        ]);

                        $message = 'Inspection has not completed in 48 hours and lead get closed : ' . $inspection_result['Remark'] ?? '';
                    }
                    else
                    {
                        $message = isset($inspection_result['ErrorMessage']) && ! empty($inspection_result['ErrorMessage']) ? $inspection_result['ErrorMessage'] : 'Your Inspection has not been recommended yet';
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