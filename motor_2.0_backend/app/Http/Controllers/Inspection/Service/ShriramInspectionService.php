<?php

namespace App\Http\Controllers\Inspection\Service;

use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use App\Models\CvBreakinStatus;
use App\Models\JourneyStage;
use App\Models\MasterPremiumType;
use App\Models\PolicyDetails;
use App\Models\UserProposal;

class ShriramInspectionService
{
    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))->first();

        if ($breakinDetails)
        {
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();

            if ($policy_details)
            {
                $status = false;
                $message = 'Policy has already been generated for this inspection number';
            }
            else
            {
                $inspection_result = json_decode($breakinDetails->breakin_response, TRUE);
                $ic_breakin_response = json_decode($breakinDetails->ic_breakin_response, TRUE);

                if ( ! isset($inspection_result['Remarks']) || (isset($inspection_result['Remarks']) && $inspection_result['Remarks'] != 'APPROVED'))
                {
                    $request->api_key = config('constants.wimwisure.API_KEY_SHRIRAM');
                    $inspection = new WimwisureBreakinController();
                    $inspection_result = $inspection->WimwisureCheckInspection($request);
                }

                if (( ! isset($ic_breakin_response['status']) || (isset($ic_breakin_response['status']) && $ic_breakin_response['status'] != 'SelfSurvey-Approved')))
                {
                    $ic_breakin_request = [
                        'preInsId' => $request->inspectionNo
                    ];

                    $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_BREAKIN_CHECK_URL'), $ic_breakin_request, 'shriram', [
                        'enquiryId' => customDecrypt($request['userProductJourneyId']),
                        'headers' => [
                            'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                            'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ],
                        'requestMethod' => 'post',
                        'requestType' => 'json',
                        'section' => 'Taxi',
                        'method' => 'Check Wimwisure Breakin Status',
                        'transaction_type' => 'proposal',
                    ]);
                    $ic_breakin_response = $get_response['response'];

                    if ($ic_breakin_response)
                    {
                        CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))   
                            ->update([
                                'ic_breakin_response' => $ic_breakin_response
                            ]);

                        $ic_breakin_response = json_decode($ic_breakin_response, TRUE);
                    }
                    else
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => 'Insurer not reachable'
                        ]);
                    }
                }

                if (isset($ic_breakin_response['status']) && $ic_breakin_response['status'] == 'SelfSurvey-Approved')
                {
                    $productData = getProductDataByIc($breakinDetails->user_proposal->quote_log->master_policy_id);

                    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();

                    $policy_start_date = date('Y-m-d', time());

                    if ($premium_type == 'short_term_3')
                    {
                        $policy_end_date = date('Y-m-d', strtotime('+3 month -1 day', strtotime($policy_start_date)));
                    }
                    elseif ($premium_type == 'short_term_6')
                    {
                        $policy_end_date = date('Y-m-d', strtotime('+6 month -2 day', strtotime($policy_start_date)));
                    }
                    else
                    {
                        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                    }

                    CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))
                        ->update([
                            'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED']
                        ]);

                    UserProposal::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                        ->update([
                            'is_inspection_done' => 'Y',
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                        ]);

                    $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                        ->first();

                    $status = true;
                    $message = 'Your Vehicle Inspection is Done By Godigit.';

                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                    ]);

                    return response()->json([
                        'status' => $status,
                        'msg'    => $message,
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_proposal->user_product_journey_id),
                            'proposalNo' => $breakinDetails->user_proposal->proposal_no,                    
                            'totalPayableAmount' => $breakinDetails->user_proposal->final_payable_amount,
                            'proposalUrl' =>  str_replace('quotes','proposal-page',$journey_stage->proposal_url)
                        ]
                    ]);
                }
                elseif (isset($ic_breakin_response['status']) && $ic_breakin_response['status'] == 'SelfSurvey-Rejected')
                {
                    CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))   
                        ->update([
                            'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                            'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED']
                        ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,                               
                        'stage' => STAGE_NAMES['INSPECTION_REJECTED'],                             
                    ]);

                    $status = false;
                    $message = 'Your Vehicle Inspection has been rejected';
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
                        'msg' => $ic_breakin_response['status'] ? 'Inspection status is ' . $ic_breakin_response['status'] : 'Insurer not reachable'
                    ]);
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
