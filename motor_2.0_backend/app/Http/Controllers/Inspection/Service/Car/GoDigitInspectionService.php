<?php

namespace App\Http\Controllers\Inspection\Service\Car;
use Illuminate\Support\Facades\DB;
use App\Models\PolicyDetails;
use App\Models\JourneyStage;
include_once app_path().'/Helpers/CarWebServiceHelper.php';
use App\Http\Controllers\Inspection\Service\Car\V2\GoDigitInspectionService as oneapi;
use App\Models\CvAgentMapping;
use App\Models\UserProposal;

class GoDigitInspectionService
{
    
    public static function inspectionConfirm($request)
    {
        if (config('IC.GODIGIT.V2.CAR.ENABLE') == 'Y')
        return  oneapi::oneApiInspectionConfirm($request);

        $breakinDetails = DB::table('cv_breakin_status')
                        ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
                        ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                        ->join('cv_journey_stages', 'cv_breakin_status.user_proposal_id', '=', 'cv_journey_stages.proposal_id')
                        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
                        ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount', 'quote_log.master_policy_id', 'cv_journey_stages.proposal_url')
                        ->first();
        $product = getProductDataByIc($breakinDetails->master_policy_id);
        if($breakinDetails)
        {
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();

            if ($policy_details)
            {
                $status = false;
                $message = 'Policy has already been generated for this inspection number';
            }
            else
            {
                    $breakin_status_response = json_decode($breakinDetails->breakin_response, TRUE);

                    if (isset($breakin_status_response['policyStatus']) && in_array($breakin_status_response['policyStatus'], ['PRE_INSPECTION_APPROVED', 'INCOMPLETE'])) 
                    {
                        $data = $breakinDetails->breakin_response;
                    }
                    else
                    {
                        $webUserId = config("constants.IcConstants.godigit.GODIGIT_WEB_USER_ID");
                        $password = config("constants.IcConstants.godigit.GODIGIT_PASSWORD");

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
                                $webUserId = $credentials['data']['web_user_id'];
                                $password = $credentials['data']['password'];
                            }
                        }
                    
                        $url = config('constants.IcConstants.godigit.GODIGIT_BREAKIN_STATUS').trim($request->inspectionNo);
                        $get_response = getWsData($url,
                                $url,
                                'godigit',
                                [
                                    'enquiryId'         => $breakinDetails->user_product_journey_id,
                                    'requestMethod'     => 'get',
                                    'section'           => 'CAR',
                                    'productName'       => $product->product_name,//$product->product_sub_type_name,
                                    'company'           => 'godigit',
                                    'method'            => 'Check Breakin Status',
                                    'transaction_type'  => 'proposal',
                                    'password' => $password,
                                    'webUserId' => $webUserId,
                                ]
                        );
                        $data = $get_response['response'];
                    }
                $decoded_data = json_decode($data, true);
                $proposal_resp_array = is_array($decoded_data) ? array_change_key_case($decoded_data, CASE_LOWER) : [];
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

                        if ($breakin_make_time > time()) {
                            $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
                        } else {
                            $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));
                        }
                        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));

                        $proposal_update_data = [
                            'is_inspection_done' => 'Y',
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                        ];

                        //for gdd product do not update policy start and end date
                        //policy start date will be previous policy expiry date + 1
                        if ($product->good_driver_discount == 'Yes') {
                            unset($proposal_update_data['policy_start_date'], $proposal_update_data['policy_end_date']);
                        }

                        UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update($proposal_update_data);

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
                        else if($proposal_resp_array['policystatus'] == 'ASSESSMENT_PENDING')
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
