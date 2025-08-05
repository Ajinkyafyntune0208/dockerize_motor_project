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

class ShriramInspectionService extends Controller
{
    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))->first();
        if (!empty($breakinDetails)){
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
                $inspection_result = json_decode($breakinDetails->breakin_response, TRUE);
                if (!isset($inspection_result->BrkinStatus) || (isset($inspection_result->BrkinStatus) && $inspection_result->BrkinStatus != 'APPROVED')) {
                    $vehicaleRegistrationNumber = $user_proposal->vehicale_registration_number;
                    $additionData = [
                        'type' => 'CheckInspection',
                        'method' => 'Check-Inspection-Status',
                        'requestMethod' => 'post',
                        'requestType' => 'json',
                        'section' => 'car',
                        'enquiryId' => $enquiryId,
                        'productName' => $productData->product_name,
                        'transaction_type' => 'proposal',
                        'headers' => [
                            'Content-type' => 'application/json',
                            'Accept' => 'application/json',
                            'username' => config('constant.IcConstant.shriram.CHECK_INSPECTION_USERNAME'),
                            'password' => config('constant.IcConstant.shriram.CHECK_INSPECTION_PASSWORD')
                        ]
                    ];
                    $inspectionNo = json_decode($request->inspectionNo);
                    $breakin_request = [
                        "RequestId" => $inspectionNo,
                        "Userpartyid" => config('constant.SHRIRAM_BREAKIN_USERPARTYID'),
                        "REG_NO" => $user_proposal->vehicale_registration_number,
                        "UserId" => config('constant.SHRIRAM_BREAKIN_USERPARTYID')
                    ];
                    $get_response = getWsData(config('constants.IcConstants.SHRIRAM_CHECK_INSPECTION_STATUS'), $breakin_request, 'shriram', $additionData);
                    $inspection_result = ($get_response['response']);
                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'breakin_response' => $inspection_result
                        ]);
                    $inspection_result = json_decode($get_response['response']);
                }
                //response -> approved
                //$inspection_result->StatusName = 'Survey Report Approved';
                if (isset($inspection_result->StatusName) && (in_array($inspection_result->StatusName,['Survey Report Approved','Intimation Approved','Review Report Confirmed']))) {
                    $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();

                        $policy_start_date = date('Y-m-d H:i:s'/*, strtotime(' + 2 day')*/);
                        $policy_end_date = date('Y-m-d H:i:s', strtotime(' + 1 year + 1 day'));
                    // $diff_ped = Carbon::now();
                    // $previousNcb = json_decode($user_proposal->quote_log->quote_data,true)['previous_ncb'];
                    // if($diff_ped->diffInDays(json_decode($user_proposal->quote_log->quote_data,true)['previous_policy_expiry_date']) > 90){
                    //     $policy_start_date = Carbon::createFromFormat('d-m-Y', $policy_start_date)->addDays(2)->format('d-M-Y H:i:s');
                    //     $previousNcb = 0;
                    // }
                    $m_current_date = date('Y-m-d H:i:s');
                    $payment_end_date = date('Y-m-d H:i:s', strtotime(' + 1 day'));
                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                            'updated_at'            => date('Y-m-d H:i:s'),
                            'payment_url'           =>  str_replace('quotes','proposal-page',$breakinDetails->proposal_url),
                            'breakin_check_url'     => config('constants.IcConstants.SHRIRAM_CHECK_INSPECTION_STATUS'),
                            'payment_end_date'      => $payment_end_date,
                            'inspection_date'       => date('Y-m-d H:i:s'),
                        ]);
                        $m_current_date = date('Y-m-d H:i:s');
                    $breakinDetails->refresh();
                    $payment_end_date = $breakinDetails->payment_end_date;
                    $payment_end_date = Carbon::parse($payment_end_date);
                    // $policy_start_date_after_payment = Carbon::parse($user_proposal->policy_start_date);
                    if($m_current_date  > ($payment_end_date)){
                        UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->update([
                            'is_inspection_done' => 'N',
                            'policy_start_date' => null,
                            'policy_end_date' => null,

                        ]);
                        $message = 'Your Vehicle Inspection Has Expired.';
                        return response()->json([
                            'status' => false,
                            'msg' => $message
                        ]);
                    }
                    if($m_current_date <= ($payment_end_date)){
                        UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->update([
                            'is_inspection_done' => 'Y',
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => $policy_end_date,
                            // 'previous_ncb' => $previousNcb
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
                    return response()->json([
                        'status' => true,
                        'msg' => 'Your Vehicle Inspection is Done By Shriram.',
                        'data'   => [
                            'enquiryId' => customEncrypt($user_proposal->user_product_journey_id),
                            'proposalNo' => $user_proposal->user_proposal_id,
                            'totalPayableAmount' => $user_proposal->final_payable_amount,
                            'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journey_stage->proposal_url)
                        ]
                    ]);
                } else {
                    $status = false;
                    if (isset($inspection_result->StatusName) && (in_array($inspection_result->StatusName,['Cancel By HO','Intimation Canceled By SGI','Intimation Rejected','Survey Report Rejected','Intimation canceled by HO','Intimation Canceled By PI Agency']))) {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED'],
                        ]);
                         $message = "Your Inspection has not been Approved by Shriram";
                    } elseif (isset($inspection_result->StatusName) && $inspection_result->StatusName == 'Report Pending') {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        ]);
                        $message = "User Document upload is Pending, Please upload the Documents";
                    } elseif (isset($inspection_result->StatusName) && (in_array($inspection_result->StatusName,['Report Submitted','Intimation Approval Required','Survey On Hold','Request Assign to Surveyor']))) {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        ]);
                        $message = "Your Document Uploaded Successfully, Waiting for the Inspection Approval";
                    }
                    else {
                        $message = isset($inspection_result->StatusName) && !empty($inspection_result->StatusName) ? ($inspection_result->InspectionReason ?? 'Your Inspection has not been recommended yet') : 'Your Inspection has not been recommended yet';
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
