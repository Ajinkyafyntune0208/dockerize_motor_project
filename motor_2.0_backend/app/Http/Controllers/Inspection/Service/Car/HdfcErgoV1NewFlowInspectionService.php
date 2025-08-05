<?php

namespace App\Http\Controllers\Inspection\Service\Car;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JourneyStage;
use App\Models\CvBreakinStatus;
use App\Models\PolicyDetails;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use Carbon\Carbon;
use App\Models\UserProposal;
use DateTime;

class HdfcErgoV1NewFlowInspectionService extends Controller
{
    public static function V1NewFlowInspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))->first();
        if ($breakinDetails) {
            $user_proposal = $breakinDetails->user_proposal;
            $quote_log = $user_proposal->quote_log;
            $journey_stage = $user_proposal->user_product_journey->journey_stage;
            $policy_details = $user_proposal->policy_details;

            $productData = getProductDataByIc($quote_log->master_policy_id);
            $enquiryId   = $user_proposal->user_product_journey_id;
            $request_data['enquiryId'] = $enquiryId;
            $request_data['productName'] = $productData->product_name;
            $request_data['section'] = 'car';

            
            if ($policy_details) {
                return response()->json([
                    'status' => false,
                    'message' => 'Policy has already been generated for this inspection number'
                ]);
            } else {
                //generate request for checking inspection for that reference id
                $inspection_result = json_decode($breakinDetails->breakin_response, TRUE);
                if (!isset($inspection_result->BrkinStatus) || (isset($inspection_result->BrkinStatus) && $inspection_result->BrkinStatus != 'APPROVED')) {
                    $vehicaleRegistrationNumber = $user_proposal->vehicale_registration_number;
                    $vehregno = str_replace('-', '/', $vehicaleRegistrationNumber);
                    //call check inspection status service
                    $additionData = [
                        'type' => 'CheckInspection',
                        'method' => 'Check-Inspection-Status',
                        'requestMethod' => 'post',
                        'section' => 'car',
                        'enquiryId' => $enquiryId,
                        'productName' => $productData->product_name,
                        'transaction_type' => 'proposal',
                    ];
                    $inspectionNo = json_decode($request->inspectionNo);
                    $breakin_request = [
                        "BreakinId" => $inspectionNo
                    ];
                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CHECK_INSPECTION_STATUS'), $breakin_request, 'hdfc_ergo', $additionData);
                    //response
                    $inspection_result = ($get_response['response']);
                    // $inspection_result = (array)$inspection_result[0];
                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'breakin_response' => $inspection_result
                        ]);
                    $inspection_result = json_decode($get_response['response']);

                }
                //response -> approved
                if (isset($inspection_result->BrkinStatus) && ($inspection_result->BrkinStatus == 'Recommended')) {
                    $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();

                    $policy_start_date = date('d-m-Y', time());
                    $policy_end_date = '';
                    $diff_ped = Carbon::now();
                    if($diff_ped->diffInDays($user_proposal->prev_policy_expiry_date) > 30){
                        $policy_start_date = Carbon::createFromFormat('d-m-Y', $policy_start_date)->addDays(3)->format('d-m-Y H:i:s');
                    }
                    else if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                        $policy_start_date = Carbon::createFromFormat('d-m-Y', $policy_start_date)->addDays(1)->format('d-m-Y');
                        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                    } else {
                        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                    }
                    $m_current_date = Carbon::now();
                    $new_end_date_ped = $inspection_result->BrkinCreateDate;
                    $date = new DateTime($new_end_date_ped);
                    $date->modify('+7 days');
                    $end_date_ped = $date->format('Y-m-d H:i:s');
                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                            'updated_at'            => date('Y-m-d H:i:s'),
                            'breakin_response'      => json_encode($inspection_result),
                            'breakin_check_url'     => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'payment_end_date'      => $end_date_ped,
                            'inspection_date'       => date('Y-m-d'),
                        ]);
                    $breakinDetails->refresh();
                    $start_date_ped = Carbon::parse($user_proposal->policy_start_date);
                    if($m_current_date->gt($end_date_ped)){
                        UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->update([
                            'is_inspection_done' => 'N',
                            'policy_start_date' => null,
                            'policy_end_date' => null
                        ]);
                        $message = 'Your Vehicle Inspection Has Expired.';
                        return response()->json([
                            'status' => false,
                            'msg' => $message
                        ]);
                    }
                    if($start_date_ped->lte($end_date_ped)){
                        UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->update([
                            'is_inspection_done' => 'Y',
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => $policy_end_date,
                            
                        ]);
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                        ]);
                    }
                    else{
                        $message = 'Your Vehicle Inspection Has Expired Please Do Re-Inspection.';
                        return response()->json([
                            'status' => false,
                            'msg' => $message . ' Your Breakin Number is ' . $breakinDetails->breakin_number
                        ]);
                    }
                    $proposal_array = json_decode($user_proposal['additional_details_data']);
                    $proposal_array->Req_PvtCar->BreakinInspectionDate = !empty($inspection_result->BrkinInspDate)
                    ? Carbon::parse($inspection_result->BrkinInspDate)->format('d/m/y')
                        : null;
                    $updated_additional_details_data = json_encode($proposal_array);
                    $user_proposal->additional_details_data = $updated_additional_details_data;
                    $user_proposal->save();

                    return response()->json([
                        'status' => true,
                        'msg' => 'Your Vehicle Inspection is Done By HDFC ERGO General Insurance .',
                        'data'   => [
                            'enquiryId' => customEncrypt($user_proposal->user_product_journey_id),
                            'proposalNo' => $user_proposal->user_proposal_id,
                            'totalPayableAmount' => $user_proposal->final_payable_amount,
                            'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journey_stage->proposal_url)
                        ]
                    ]);
                } else {
                    //response->rejected
                    $status = false;
                    if (isset($inspection_result->BrkinStatus) && $inspection_result->BrkinStatus == 'Not Recommended') {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED'],
                        ]);

                        $message = 'Your Vehicle Inspection has been rejected, Reason : ' . "Photos not received in proper format,Doc are not uploded or seems like fraud";
                    } elseif (isset($inspection_result->BrkinStatus) && $inspection_result->BrkinStatus == 'Case not done') {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        ]);

                        $message = 'Inspection incomplete : ' . "one of the doc is pending from customer end so the case has been saved,after the doc will received case approved";
                        // } elseif (isset($inspection_result->BrkinStatus) && $inspection_result->BrkinStatus == 'INCOMPLETED') {
                        //     updateJourneyStage([
                        //         'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        //         'stage' => 'Inspection Incomplete',
                        //     ]);

                        //     $message = 'Inspection incomplete' . $inspection_result['Remarks'] ?? '';
                    } 
                    else {
                        $message = isset($inspection_result->BrkinStatus) && !empty($inspection_result->BrkinStatus) ? ($inspection_result->InspectionReason ?? 'Your Inspection has not been recommended yet') : 'Your Inspection has not been recommended yet';
                    }
                    //return response
                    return response()->json([
                        'status' => $status,
                        'message' => $message
                    ]);
                }
            }
        }
        else{
            return response()->json([
                'status' => true,
                'msg' => 'Please check your inspection number'
            ]);
        }
    }
}
