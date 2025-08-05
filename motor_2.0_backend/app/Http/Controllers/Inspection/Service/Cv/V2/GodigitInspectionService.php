<?php

namespace App\Http\Controllers\Inspection\Service\Cv\V2;

use App\Models\CvAgentMapping;
use Illuminate\Support\Facades\DB;
use App\Models\PolicyDetails;
use App\Models\JourneyStage;
include_once app_path().'/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/GoDigitHelper.php';

class GoDigitInspectionService
{
    public static function oneApiInspectionConfirm($request)
    {
        $breakinDetails = DB::table('cv_breakin_status')
        ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
        ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->join('cv_journey_stages', 'cv_breakin_status.user_proposal_id', '=', 'cv_journey_stages.proposal_id')
        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
        ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount', 'quote_log.master_policy_id', 'cv_journey_stages.proposal_url')
        ->first();
        $product = getProductDataByIc($breakinDetails->master_policy_id);
        $access_token_resp = getToken($breakinDetails->user_product_journey_id, $product, 'proposal', $request->business_type);
        $access_token = ($access_token_resp['token']);

        if($breakinDetails)
        {
            $integrationId = config("IC.GODIGIT.V2.CV.CHECK_BREAKIN_INTEGRATIONID");
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();

            $posData = CvAgentMapping::where([
                'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                'seller_type' => 'P'
            ])
            ->first();
            if (!empty($posData)) {

                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $posData->agent_id,
                    'productSubTypeId' => $product->product_sub_type_id,
                    'ic_integration_type' => $product->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $integrationId = $credentials['data']['authorization_key'];
                }
            }

            if ($policy_details)
            {
                $status = false;
                $message = 'Policy has already been generated for this inspection number';
            }
            else
            {
                    $breakin_status_response = json_decode($breakinDetails->breakin_response, TRUE);

                    if (isset($breakin_status_response['policystatus']) && in_array($breakin_status_response['policystatus'], ['PRE_INSPECTION_APPROVED', 'INCOMPLETE'])) 
                    {
                        $data = $breakinDetails->breakin_response;
                    }
                    else
                    {
                        $policy_status = [
                            "motorMotorPolicystatussearchApi" => [
                                "queryParam" => [
                                    'policyNumber' => $breakinDetails->proposal_no,
                                ],
                            ]
                        ];
                        if(config('IC.GODIGIT.V2.CV.REMOVE_BREAKIN_GODIGIT_IDENTIFIER') == 'Y'){
                            $policy_status = $policy_status['motorMotorPolicystatussearchApi'];
                        }
                        $url = config('IC.GODIGIT.V2.CV.END_POINT_URL');
                        $get_response = getWsData($url,
                                $policy_status,
                                'godigit',
                                [
                                    'enquiryId'         => $breakinDetails->user_product_journey_id,
                                    'requestMethod'     => 'post',
                                    'section'           => 'CV',
                                    'productName'       => $product->product_name,//$product->product_sub_type_name,
                                    'company'           => 'godigit',
                                    'authorization'     => $access_token,
                                    'integrationId'     => $integrationId,
                                    'method'            => 'Check Breakin Status',
                                    'transaction_type'  => 'proposal'
                                ]
                        );
                        $data = $get_response['response'];
                    }
                    $response = json_decode($data, TRUE);
                    if(!is_array($response)) {
                        return response()->json([
                            'status' => false,
                            'msg'    => 'GoDigit API is not working as expected.'
                        ]);
                    }
                    $proposal_resp_array = array_change_key_case($response, CASE_LOWER);
    
               if (isset($proposal_resp_array['policystatus']))
                {
                  
                    if (in_array($proposal_resp_array['policystatus'], ['PRE_INSPECTION_APPROVED', 'INCOMPLETE']))
                    {
                        
                        $update_data = [
                            'breakin_response'      => $data,
                            'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                            'updated_at'            => date('Y-m-d H:i:s'),
                            'payment_url' =>  str_replace('quotes','proposal-page',$breakinDetails->proposal_url),
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 2 day')),
                            'inspection_date'       => date('Y-m-d'),
                        ]; 
                        DB::table('cv_breakin_status')
                        ->where('breakin_number', trim($request->inspectionNo))
                        ->update($update_data);
                        
                        $breakin_make_time = strtotime('18:00:00');

                        // if ($breakin_make_time > time()) {
                            $policy_start_date = date('Y-m-d'/*, strtotime('+1 day', time())*/);
                        // } else {
                        //     $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));
                        // }
                        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                        DB::table('user_proposal')
                        ->where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update([
                            'is_inspection_done' => 'Y',
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                        ]);

                        $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                            ->first();

                        $status = true;
                        $message = 'Your Vehicle Inspection is Done By Godigit.';
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
                                'proposalUrl' =>  str_replace('quotes','proposal-page',$journey_stage->proposal_url)
                            ]
                        ]);

                    } 
                    else 
                    {
                        $update_data = [
                            'breakin_response' => $data,
                            'updated_at' => date('Y-m-d H:i:s')
                        ];

                        $status = false;
                        $message = 'Your Vehicle Inspection is Pending. Please try after some time';
                        if($proposal_resp_array['policystatus'] == 'SELF_INSPECTION_PENDING')
                        {
                                $message = 'Your Vehicle Inspection is Pending. Please try after some time';
                        }
                        else if($proposal_resp_array['policystatus'] == 'PRE_INSPECTION_DECLINED' || $proposal_resp_array['policystatus'] == 'DECLINED')
                        {
                            $message = 'PRE INSPECTION DECLINED';
                            updateJourneyStage([
                                'user_product_journey_id' => $breakinDetails->user_product_journey_id,                               
                                'stage' => STAGE_NAMES['INSPECTION_REJECTED']                                
                            ]);

                            $update_data = [
                                'breakin_response' => $data,
                                'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                                'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                        }
                        else if($proposal_resp_array['policyStatus'] == 'ASSESSMENT_PENDING')
                        {
                                $message = 'ASSESSMENT PENDING';
                        }

                        DB::table('cv_breakin_status')
                            ->where('breakin_number', trim($request->inspectionNo))
                            ->update($update_data);
                    }
                } 
                else 
                {
                    $status = false;
                    $message = 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction';
                }
            }
            return response()->json([
                'status' => $status,
                'msg'    => $message
            ]);
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
