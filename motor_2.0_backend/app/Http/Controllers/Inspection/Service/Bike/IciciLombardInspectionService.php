<?php

namespace App\Http\Controllers\Inspection\Service\Bike;

use App\Models\PolicyDetails;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;

class IciciLombardInspectionService
{
    public static function inspectionConfirm($request)
    {

        if (config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_BREAKIN_STATUS_API_CHECK_ENABLE_BIKE') == 'Y') {
            $breakinDetails = UserProposal::where('cv_breakin_status.breakin_number', '=', $request->inspectionNo)
            ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('cv_breakin_status', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
            ->join('cv_journey_stages', 'cv_breakin_status.user_proposal_id', '=', 'cv_journey_stages.proposal_id')
            ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount', 'quote_log.master_policy_id', 'cv_journey_stages.proposal_url','user_proposal.prev_policy_expiry_date')
            ->first();

            $productData = getProductDataByIc($breakinDetails['master_policy_id']);
            if ($breakinDetails) {
                $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();
                if ($policy_details) {
                    return response()->json([
                        'status' => false,
                        'msg'    => 'Policy has already been generated for this inspection number'
                    ]);
                }
                
                if($breakinDetails['breakin_status_final'] == STAGE_NAMES['INSPECTION_APPROVED'])
                {
                    return response()->json([
                        'status' => true,
                        'msg'    => 'Your Vehicle Inspection is Done By Icici Lombard.',
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                            'proposalNo' => $breakinDetails->proposal_no,
                            'totalPayableAmount' => $breakinDetails->final_payable_amount,
                            'proposalUrl' =>  str_replace('quotes', 'proposal-page', $breakinDetails->proposal_url)
                        ]
                    ]);					
                }

                $additionData = [
                    'requestMethod' => 'post',
                    'type' => 'tokenGeneration',
                    'section' => 'bike',
                    'enquiryId' => $breakinDetails->user_product_journey_id,
                    'transaction_type' => 'proposal',
                    'productName'  => 'icici_lombard',
                ];

                $response = iciciLombardBreakInStatusApi($breakinDetails, $additionData);

                if ($response['status'] == false) {
                    return response()->json([
                        'status' => false,
                        'msg' => $response['message']
                    ]);
                }

                $data = $response['data'];
                if($data['statusMessage']!= "Success" || !isset($data['breakinStatus'])) {
                    return response()->json([
                        'status' => false,
                        'msg' => $data['message']
                    ]);
                }

                if (in_array($data['breakinStatus'],['Recommended'])) {

                    $brknId=(string)$data['breakinId'];

                    $get_response = iciciLombardBreakInClearStatusApi( $data, $breakinDetails, $additionData);

                    $inspectionFinalStatusResponse = $get_response['response'];
                    $inspectionFinalStatusResponse = json_decode($inspectionFinalStatusResponse, true);

                    if (isset($inspectionFinalStatusResponse['vehicleInspectionStatus']) && $inspectionFinalStatusResponse['vehicleInspectionStatus'] == 'PASS') {
                        DB::table('cv_breakin_status')
                            ->where('breakin_number', $brknId)
                            ->where('ic_id', 40)
                            ->update([
                                'inspection_date' => date('Y-m-d'),
                                'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED'],
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);

                        updateJourneyStage([
                            'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                        ]);

                        $journey_payload = DB::table('cv_journey_stages')->where('proposal_id', $breakinDetails->user_proposal_id)
                            ->first();

                        $policyStartDate = date('d-m-Y');
                        $policyEndDate =  date('d-m-Y', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                        $breakinCreationDate = !empty($data["inspectionDate"]) ? $data["inspectionDate"] : (!empty($data["breakinCreationDate"]) ? $data["breakinCreationDate"] : date('d-m-Y'));
                        $paymentEndDate =  date('Y-m-d H:i:s', strtotime('+9 day', strtotime($breakinCreationDate)));

                        if ($productData->premium_type_code == 'comprehensive'){
                            $policyStartDate = date('d-m-Y', strtotime('+1 day', strtotime($breakinDetails->prev_policy_expiry_date)));
                            $policyEndDate =  date('d-m-Y', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                        }
                        DB::table('cv_breakin_status')
                            ->where('breakin_number', $brknId)
                            ->where('ic_id', 40)
                            ->update([
                                'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                                'payment_url' => str_replace('quotes', 'proposal-page', $journey_payload->proposal_url),
                                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                'updated_at' => date('Y-m-d H:i:s'),
                                'payment_end_date' => $paymentEndDate
                            ]);

                        UserProposal::where('user_product_journey_id', $breakinDetails->user_product_journey_id)
                            ->update([
                                'is_inspection_done' => 'Y',
                                'policy_start_date' => $policyStartDate,
                                'policy_end_date' =>  $policyEndDate
                            ]);

                        return response()->json([
                            'status' => true,
                            'msg'    => 'Your Vehicle Inspection is Done By Icici Lombard.',
                            'data'   => [
                                'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                                'proposalNo' => $breakinDetails->proposal_no,
                                'totalPayableAmount' => $breakinDetails->final_payable_amount,
                                'proposalUrl' =>  str_replace('quotes', 'proposal-page', $breakinDetails->proposal_url)
                            ]
                        ]);
                    }else if(
                        (isset($inspectionFinalStatusResponse['vehicleInspectionStatus']) && $inspectionFinalStatusResponse['vehicleInspectionStatus'] == 'FAIL')
                        &&
                        (isset($inspectionFinalStatusResponse['message']) && trim($inspectionFinalStatusResponse['message']) == config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_BREAKIN_FAIL_MESSAGE'))
                    )
                    {
                        if($breakinDetails['breakin_status_final'] == STAGE_NAMES['INSPECTION_APPROVED'] || $breakinDetails['breakin_status'] == STAGE_NAMES['INSPECTION_APPROVED'])
                        {
                            $policyStartDate = date('d-m-Y', time());
                            $policyEndDate = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                                
                            UserProposal::where('user_product_journey_id', $breakinDetails->user_product_journey_id)
                            ->update([
                                    'is_inspection_done' => 'Y',
                                    'policy_start_date' => $policyStartDate,
                                    'policy_end_date' => $policyEndDate
                                ]);
                                
                            return response()->json([
                                'status' => true,
                                'msg'    => 'Your Vehicle Inspection is Done By Icici Lombard.',
                                'data'   => [
                                    'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                                    'proposalNo' => $breakinDetails->proposal_no,
                                    'totalPayableAmount' => $breakinDetails->final_payable_amount,
                                    'proposalUrl' =>  str_replace('quotes', 'proposal-page', $breakinDetails->proposal_url)
                                ]
                            ]);					
                        }

                        DB::table('cv_breakin_status')
                        ->where('breakin_number', $brknId)
                        ->where('ic_id', 40)
                        ->update([
                            'inspection_date' => date('Y-m-d'),
                            'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                    ]);

                    $journey_payload = DB::table('cv_journey_stages')->where('proposal_id', $breakinDetails->user_proposal_id)
                        ->first();
                    $policyStartDate = date('d-m-Y');
                    $policyEndDate =  date('d-m-Y', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                    $breakinCreationDate = !empty($data["inspectionDate"]) ? $data["inspectionDate"] : (!empty($data["breakinCreationDate"]) ? $data["breakinCreationDate"] : date('d-m-Y'));
                    $paymentEndDate =  date('Y-m-d H:i:s', strtotime('+9 day', strtotime($breakinCreationDate)));

                    DB::table('cv_breakin_status')
                        ->where('breakin_number', $brknId)
                        ->where('ic_id', 40)
                        ->update([
                            'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                            'payment_url' => str_replace('quotes', 'proposal-page', $journey_payload->proposal_url),
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'payment_end_date' => $paymentEndDate
                        ]);

                    UserProposal::where('user_product_journey_id', $breakinDetails->user_product_journey_id)
                        ->update([
                            'is_inspection_done' => 'Y',
                            'policy_start_date' => $policyStartDate,
                            'policy_end_date' =>  $policyEndDate
                        ]);

                    return response()->json([
                        'status' => true,
                        'msg'    => 'Your Vehicle Inspection is Done By Icici Lombard.',
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                            'proposalNo' => $breakinDetails->proposal_no,
                            'totalPayableAmount' => $breakinDetails->final_payable_amount,
                            'proposalUrl' =>  str_replace('quotes', 'proposal-page', $breakinDetails->proposal_url)
                        ]
                    ]);


                    }elseif (isset($inspectionFinalStatusResponse['vehicleInspectionStatus']) && $inspectionFinalStatusResponse['vehicleInspectionStatus'] == 'FAIL') {
                        $update_data = [
                            'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                            'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                            'updated_at' => date('Y-m-d H:i:s'),
                            'inspection_date' => date('Y-m-d'),
                        ];


                        DB::table('cv_breakin_status')
                            ->where('breakin_number', $brknId)
                            ->where('ic_id', 40)
                            ->update($update_data);
                        updateJourneyStage([
                            'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                        ]);

                        return response()->json([
                            'status' => false,
                            'msg' => $brknId . ' is rejected from IC'
                        ]);
                    }
                } elseif (in_array($data['breakinStatus'],['Rejected','Closed'])) {
                    $update_data = [
                        'breakin_response' => $data,
                        'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                        'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                        'updated_at' => date('Y-m-d H:i:s'),
                        'inspection_date' => date('Y-m-d'),
                    ];

                    DB::table('cv_breakin_status')
                    ->where('breakin_number', trim($request->inspectionNo))
                    ->update($update_data);

                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                    ]);

                    return response()->json([
                        'status' => true,
                        'msg' => 'Your Inspection is ' . $data['breakinStatus']
                    ]);
                } else {
                    return response()->json([
                        'status'=>true,
                        'msg'=>'Your Inspection is Pending'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'no data found'
                ]);
            }
        } else {
            $breakinDetails = UserProposal::where('cv_breakin_status.breakin_number', '=', $request->inspectionNo)
                ->join('cv_breakin_status', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
                ->first();

            if ($breakinDetails) {

                if ($breakinDetails->breakin_status == STAGE_NAMES['INSPECTION_APPROVED'] && $breakinDetails->breakin_status_final == STAGE_NAMES['INSPECTION_APPROVED']) {

                    UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update(['is_inspection_done' => 'Y']);

                    $journey_payload = DB::table('cv_journey_stages')->where('proposal_id', $breakinDetails->user_proposal_id)
                        ->first();

                    return response()->json([
                        'status' => true,
                        'msg' => 'Vehicle Inspection is Done By ICICI Lombard!',
                        'data' => [
                            'proposalNo' => $breakinDetails->proposal_no,
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                            'totalPayableAmount' => $breakinDetails->final_payable_amount,
                            'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journey_payload->proposal_url)
                        ]
                    ]);
                } else if ($breakinDetails->breakin_status == STAGE_NAMES['INSPECTION_REJECTED']) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Vehicle Inspection is Rejected by ICICI Lombard.'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Vehicle Inspection is Pending. Please try after some time'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'No breakin details found!'
                ]);
            }
        }
        

    }
}
