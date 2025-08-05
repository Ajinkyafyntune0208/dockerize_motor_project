<?php

namespace App\Http\Controllers\Inspection\Service\Car;

use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path().'/Helpers/CarWebServiceHelper.php';
class libertyVideoconInspectionService
{

    public static function updateBreakinStatus(Request $request)
    {
        $payload = $request->all();

        if(isset($payload['LeadId']) && $payload['LeadId'] != '' && isset($payload['RelatedInfo']) && $payload['RelatedInfo'] != '')
        {
            $LeadId = $payload['LeadId'];

            $breakinDetails = DB::table('cv_breakin_status')
                ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
                ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->where('cv_breakin_status.breakin_number', '=', trim($payload['LeadId']))
                ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount', 'quote_log.master_policy_id')
                ->first();

            if($breakinDetails){
                if($payload['RelatedInfo'] == 'Recommended')
                {
                    $update_data = [
                        'breakin_response'      => json_encode($payload),
                        'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                        'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                        'updated_at'            => date('Y-m-d H:i:s'),
                        'payment_url' => config('constants.motorConstant.CAR_BREAKIN_PAYMENT_URL').customEncrypt($breakinDetails->user_product_journey_id),
                        'breakin_check_url'     => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                        'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' +3 day'))

                    ]; 
                    DB::table('cv_breakin_status')
                    ->where('breakin_number', $payload['LeadId'])
                    ->update($update_data);

                    $policy_start_date = date('Y-m-d');
                    $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));

                    UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                    ->update([
                        'is_inspection_done' => 'Y',
                        'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                        'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                    ]);
                    
                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                    ]);
                    return response()->json([
                        'status' => true,
                        'msg'    => 'Your Vehicle Inspection is Done By Liberty General Insurance.',
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id)
                        ]
                    ]);
                }
                else
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,                               
                        'stage' => STAGE_NAMES['INSPECTION_REJECTED']                                
                    ]);

                    DB::table('cv_breakin_status')
                        ->where('breakin_number', $payload['LeadId'])
                        ->update([
                        'breakin_response' => json_encode($payload),
                        'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                        'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    return response()->json([
                        'status' => false,
                        'msg'    => STAGE_NAMES['INSPECTION_REJECTED'],
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                        ]
                    ]);
                }
            }
            else
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Please Check Your Inspection Number'
                ]);
            }

        }
        else
        {
            return response()->json([
                'data' => [],
                'status' => false,
                'message' => 'incomplete data'
            ]);
        }            
    }            
        


    public static function inspectionConfirm($request)
    {
        $breakinDetails = DB::table('cv_breakin_status')
            ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
            ->join('cv_journey_stages', 'cv_breakin_status.user_proposal_id', '=', 'cv_journey_stages.proposal_id')
            ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
            ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount','user_proposal.user_proposal_id', 'cv_journey_stages.proposal_url', 'quote_log.master_policy_id')
            ->first();
        
        if($breakinDetails)
        {
        
           $check_policy = DB::table('policy_details')
                        ->where('proposal_id', '=', trim($breakinDetails->user_proposal_id))
                        ->first();
            if($check_policy)
            {
                if($check_policy->policy_number != '' && $check_policy->policy_number != 'NULL')
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Payment is already done and generated policy number is ' . $check_policy->policy_number,
                    ]);
                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Payment is already done and generated policy number is Not Generated',
                    ]);
                }
            }
            else
            {
                if($breakinDetails->breakin_id == '' &&  $breakinDetails->breakin_status_final == '')
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Your vehicle inspection is pending'
                    ]);
                }
                if($breakinDetails->breakin_id != '' && $breakinDetails->breakin_status_final ==  STAGE_NAMES['INSPECTION_APPROVED'])
                {
                    return response()->json([
                        'status' => true,
                        'msg'    => 'Your vehicle inspection is approved by Liberty General Insurance',
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                            'proposalNo' => $breakinDetails->proposal_no,                    
                            'totalPayableAmount' => $breakinDetails->final_payable_amount,
                            'breakinDetails' => $breakinDetails,
                            'check_policy' => $check_policy,
                            'proposalUrl' => $breakinDetails->payment_url,
                        ]
                    ]);
                }
                elseif($breakinDetails->breakin_status_final ==  STAGE_NAMES['INSPECTION_REJECTED'])
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Your vehicle inspection is rejected by Liberty General Insurance'
                    ]);
                }
                elseif($breakinDetails->breakin_id != '' && $breakinDetails->breakin_status_final ==  STAGE_NAMES['PENDING_FROM_IC'])
                {

                    $lead_request = [
                        "UserID"            => config('constants.IcConstants.liberty_videocon.breakin.PI_USER_ID'),
                        "Passwd"            => config('constants.IcConstants.liberty_videocon.breakin.PI_PASSWORD'),
                        "IntimatorName"     => config('constants.IcConstants.liberty_videocon.breakin.PI_USER_ID'),
                        "IntimatorPhone"    => '',
                        "IntimatorEmailId"  => config('constants.IcConstants.liberty_videocon.CAR_EMAIL'),
                        "AgencyID"          => config('constants.IcConstants.liberty_videocon.breakin.AgencyID'),//'tp123',//$proposal_details['agency_name'],
                        "BranchID"          => config('constants.IcConstants.liberty_videocon.breakin.BranchID'),//'1199',//$proposal_details['branch_id'],
                    ];
                    
                    $container = '
                        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                        <soap:Body>
                            <GetProposal xmlns="http://www.claimlook.com/">
                            <LeadId>'.$breakinDetails->breakin_id.'</LeadId>
                            <PraposalId></PraposalId>
                            </GetProposal>
                        </soap:Body>
                        </soap:Envelope>
                    ';

                    $additional_data = [
                        'enquiryId'         => $breakinDetails->user_product_journey_id,
                        'section'           => 'CAR',
                        'requestMethod' => 'post',
                        'company'       => 'liberty_videocon',
                        'method'        => 'Get Proposal',
                        'transaction_type' => 'proposal',
                        'root_tag'      => 'json',
                        'SOAPAction'   => 'http://www.claimlook.com/GetProposal',
                        'content_type'  => 'text/xml;',
                        'container'     => $container,
                    ];

                    $get_response = getWsData(
                        config('constants.IcConstants.liberty_videocon.breakin.END_POINT_URL_PI_LEAD_ID_CREATE'),
                        $lead_request,
                        'liberty_videocon',
                        $additional_data
                    );
                    
                    $lead_create_response = $get_response['response'];
                    if(!$lead_create_response || $lead_create_response == ''){
                        return [
                            'status'    => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium'   => '0',
                            'message'   => 'insurer Not Reachable',
                            'LeadID'    => '',
                            'data'      => []
                        ];
                    }
            
                    // $lead_response = html_entity_decode($lead_create_response);
                    $lead_response = XmlToArray::convert($lead_create_response);
                    $lead_response = json_decode($lead_response['soap:Body']['GetProposalResponse']['GetProposalResult'], true)[0];
                    $lead_response = json_decode($lead_response['Details']);
                    
                    if(strtolower($lead_response->Lead_Status) == 'final status - recommended'){
                        $update_data = [
                            'breakin_response'      => json_encode($lead_response),
                            'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                            'updated_at'            => date('Y-m-d H:i:s'),
                            'payment_url' =>  str_replace('quotes','proposal-page',$breakinDetails->proposal_url),
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 2 day')),
                            'inspection_date'       => date('Y-m-d'),
                        ]; 
                        DB::table('cv_breakin_status')
                        ->where('breakin_number', $breakinDetails->breakin_number)
                        ->update($update_data);

                        $productData = getProductDataByIc($breakinDetails->master_policy_id);

                        $premium_type = DB::table('master_premium_type')
                            ->where('id', $productData->premium_type_id)
                            ->pluck('premium_type_code')
                            ->first();

                        $updateData = [
                            'is_inspection_done' => 'Y'
                        ];

                        if (in_array($premium_type, ['breakin', 'own_damage_breakin'])) {
                            $policy_start_date = date('d-m-Y');
                            $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                            $updateData = [
                                'is_inspection_done' => 'Y',
                                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                            ];
                        }

                        UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update($updateData);

                        // $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                            // ->first();

                        $status = true;
                        $message = 'Your Vehicle Inspection is Done By Liberty General Insurance.';
                        updateJourneyStage([
                                    'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                        ]);
                        return response()->json([
                            'status' => $status,
                            'msg'    => $message,
                            'data'   => [
                                'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                                'proposalNo' => $breakinDetails->proposal_no,                    
                                'totalPayableAmount' => $breakinDetails->final_payable_amount,
                                'proposalUrl' =>  str_replace('quotes','proposal-page',$breakinDetails->proposal_url)
                            ]
                        ]);

                    }else if(in_array(strtolower($lead_response->Lead_Status), ["final status - non-recommended","final status - not recommended"])){
                        $message = 'Your vehicle inspection is rejected by Liberty General Insurance';
                        updateJourneyStage([
                            'user_product_journey_id' => $breakinDetails->user_product_journey_id,                               
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED']                                
                        ]);

                        $update_data = [
                            'breakin_response' => json_encode($lead_response),
                            'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                            'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ];

                        DB::table('cv_breakin_status')
                            ->where('breakin_number', trim($request->inspectionNo))
                            ->update($update_data);

                        return response()->json([
                            'status' => false,
                            'msg' => $message
                        ]);

                    }else{
                        return response()->json([
                            'status' => false,
                            'msg' => 'Your vehicle inspection is pending'
                        ]);
                    }
                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Your vehicle inspection is pending'
                    ]);
                }
            }
           
        }
        else
        {
            return response()->json([
                'status' => false,
                'msg' => 'Please Check Your Inspection Number'
            ]);
        }
    }
}
