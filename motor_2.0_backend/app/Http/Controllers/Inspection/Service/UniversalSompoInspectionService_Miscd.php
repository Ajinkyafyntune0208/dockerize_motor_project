<?php

namespace App\Http\Controllers\Inspection\Service;

use App\Models\CvBreakinStatus;
use App\Models\QuoteLog;
use App\Models\JourneyStage;
use App\Models\UserProposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
class UniversalSompoInspectionService_Miscd
{
    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::with('user_proposal')
            ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
            ->first();
        $user_proposal = $breakinDetails->user_proposal;
        $policy_details = $user_proposal->policy_details;

        if (empty($breakinDetails->user_proposal?->user_product_journey_id)) {
            return [
                'status' => false,
                'message' => 'Please enter correct Inspection Number'
            ];
        }
        if ($policy_details) {
            return response()->json([
                'status' => false,
                'message' => 'Policy has already been generated for this inspection number'
            ]);
        }
        $requestData = getQuotation($breakinDetails->user_proposal->user_product_journey_id);
        $policy_id = QuoteLog::where('user_product_journey_id', $requestData->user_product_journey_id)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policy_id);

        switch ($requestData->policy_type) {
            case 'comprehensive':
                $product_code = '2311';
                break;

            case 'third_party':
                $product_code = '2319';
                break;

            case 'own_damage':
                $product_code = '2398';
                break;
        }

        if ($breakinDetails) {
            $check_inspection_request = [
                'ReferenceNo' => $breakinDetails->breakin_number,
            ];
            $get_response = getWsData(config('IC.UNIVERSAL_SOMPO.BREAKIN_STATUS_CHECK_END_POINT_URL_MISCDV2'), $check_inspection_request, 'universal_sompo', [
                'requestMethod' => 'post',
                'enquiryId' => $breakinDetails->user_proposal->user_product_journey_id,
                'method' => 'Check Inspection',
                'section' => $productData->product_sub_type_code,
                'productName'   => $productData->product_sub_type_name,
                'transaction_type' => 'proposal',
            ]);

            $data = $get_response['response'];
            if ($data) {

                $response = json_decode($data, true);
                $status_array = array("case_completed", "approved", "accepted", "Fresh Case");
                if (isset($response['ServiceResult']) && $response['ServiceResult'] == "Success" && in_array(strtolower($response['OutputResult']['result']), $status_array)) {
                    UserProposal::where('user_product_journey_id', $requestData->user_product_journey_id)
                        ->update([
                            'is_inspection_done' => 'Y',
                        ]);

                    $m_current_date = Carbon::now();     
                    $end_date_ped = date('Y-m-d H:i:s', strtotime(' + 2 day'));
                    $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($m_current_date)));
                    CvBreakinStatus::where('user_proposal_id', $breakinDetails->user_proposal_id)
                        ->update([
                            'breakin_status'    => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                            'inspection_date'   => date('Y-m-d'),
                            // 'payment_url' => config('constants.motorConstant.CV_BREAKIN_PAYMENT_URL') . customEncrypt($breakinDetails->user_proposal->user_product_journey_id),
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'breakin_response'  => $response,
                            'payment_end_date'  => $end_date_ped,
                        ]);

                    $m_current_date = Carbon::now();
                    $breakinDetails->refresh();
                    $end_date_ped = $breakinDetails->payment_end_date;
                    $start_date_ped = Carbon::now();
                    //48 hrs expiry after approval
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
                            'policy_start_date' => date('d-m-Y', strtotime($start_date_ped)),
                            'policy_end_date' => $policy_end_date
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
                    $status = true;
                    $message = 'Your Vehicle Inspection is approved.';
                    $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                        ->first();
                    return response()->json([
                        'status' => $status,
                        'msg'    => $message,
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_proposal->user_product_journey_id),
                            'proposalNo' => $breakinDetails->user_proposal->proposal_no,
                            'totalPayableAmount' => $breakinDetails->user_proposal->final_payable_amount,
                            'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journey_stage->proposal_url)
                        ]
                    ]);
                } elseif (isset($response['ServiceResult']) && strtolower($response['OutputResult']['result']) == "rejected") {
                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                    ]);
                    $status = false;
                    $message = 'Your Vehicle Inspection is Rejected.';
                    return $return_data = [
                        'status' => $status,
                        'message' => $message
                    ];
                } elseif (empty($response['Serviceresult'])){
                    return [
                        'status' => false,
                        'message' => 'Invalid Response from IC.'
                    ];
                }
                else {
                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_PENDING']
                    ]);
                    $message = (!empty($response['ErrorText'])) ? $response['ErrorText'] : "Your inspection is in Pending. Kindly check after some time";
                    $status = false;
                    return $return_data = [
                        'status' => $status,
                        'message' => $message
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => 'Sorry, we are unable to process your request at the moment. Your inspection is Pending'
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Please enter correct Inspection Number'
            ];
        }
    }
}
