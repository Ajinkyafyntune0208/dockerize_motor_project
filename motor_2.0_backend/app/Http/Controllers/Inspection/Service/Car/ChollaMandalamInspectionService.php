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

class ChollaMandalamInspectionService extends Controller
{
    public static function inspectionConfirm($request)
    {
        //take record from cv_breakin_status by using ref number which you will get from $request
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

            // NEW TOKEN SERVICE FOR CHECKING STATUS
            $token_array = [
                'grant_type' => 'client_credentials'
            ];
            $token_breakin = getWsData(config('IC.CHOLLA_MANDALAM.V1.BREAKIN.TOKEN'), $token_array, 'cholla_mandalam', [
                'requestMethod' => 'post',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic' . ' ' . config('IC.CHOLLA_MANDALAM.V1.BREAKIN.AUTH')
                ],
                'enquiryId' => $user_proposal->user_product_journey_id,
                'method' => 'Token Generation',
                'productName' => $productData->product_name,
                'section' => 'car',
                'transaction_type' => 'proposal',
                'type'          => 'Breakin Token'
            ]);
            $token_response = json_decode($token_breakin['response']);
            $actoken = $token_response->access_token;
            if ($policy_details) {
                return response()->json([
                    'status' => false,
                    'message' => 'Policy has already been generated for this inspection number'
                ]);
            } else {
                //generate request for checking inspection for that reference id
                $inspection_result = json_decode($breakinDetails->breakin_response, TRUE);
                if (!isset($inspection_result['STATUS']) || (isset($inspection_result['STATUS']) && $inspection_result['STATUS'] != 'APPROVED')) {
                    $vehicaleRegistrationNumber = $user_proposal->vehicale_registration_number;
                    $vehregno = str_replace('-', '/', $vehicaleRegistrationNumber);
                    //call check inspection status service
                    $end_point_url = config('constants.IcConstants.cholla_madalam.INITIATE_APPROVAL') . '?uniqueid=' . $user_proposal->unique_quote . '&ReferenceNumber=' . $breakinDetails->breakin_number . '&RegistrationNumber=' . $vehregno;
                    $get_response = getWsData($end_point_url, '', 'cholla_mandalam', [
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Check-Inspection-Status',
                        'Authorization' => $actoken,
                        'requestMethod' => 'get',
                        'enquiryId' => $user_proposal->user_product_journey_id,
                        'productName' => $productData->product_name,
                        'type' => 'request',
                        'transaction_type' => 'proposal',
                    ]);
                    //response
                    $inspection_result = json_decode($get_response['response']);
                    $inspection_result = (array)$inspection_result[0];
                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'breakin_response' => $inspection_result
                        ]);
                }
                //response -> approved
                if (isset($inspection_result['STATUS']) && ($inspection_result['STATUS'] == 'APPROVED' || $inspection_result['STATUS'] == 'APPROVED BY HEAD OF INSPECTION')) {
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
                    $end_date_ped = date('Y-m-d H:i:s', strtotime(' + 1 day'));
                    if($diff_ped->diffInDays($user_proposal->prev_policy_expiry_date) > 30){
                        $end_date_ped = date('Y-m-d H:i:s', strtotime('+4 day'));
                    }
                    //cv_breakin_status -> payment_end_date -> 24 hrs    
                    CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                        ->update([
                            'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                            'updated_at'            => date('Y-m-d H:i:s'),
                            //'payment_url'           =>  str_replace('quotes','proposal-page',$breakinDetails->proposal_url),
                            'breakin_check_url'     => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'payment_end_date'      => $end_date_ped,
                            'inspection_date'       => date('Y-m-d'),
                        ]);
                    $m_current_date = Carbon::now();
                    $breakinDetails->refresh();
                    $end_date_ped = $breakinDetails->payment_end_date;
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
                    return response()->json([
                        'status' => true,
                        'msg' => 'Your Vehicle Inspection is Done By Cholla mandalam MS General Insurance Co. Ltd. .',
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
                    if (isset($inspection_result[0]['STATUS']) && $inspection_result['STATUS'] == 'REJECTED') {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_REJECTED'],
                        ]);

                        $message = 'Your Vehicle Inspection has been rejected, Reason : ' . $inspection_result['RejectReason'] ?? $inspection_result['Remarks'];
                    } elseif (isset($inspection_result['STATUS']) && $inspection_result['STATUS'] == 'PENDING') {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        ]);

                        $message = 'Inspection Required : ' . $inspection_result['Remarks'] ?? '';
                    } elseif (isset($inspection_result['STATUS']) && $inspection_result['STATUS'] == 'INCOMPLETED') {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => 'Inspection Incomplete',
                        ]);

                        $message = 'Inspection incomplete' . $inspection_result['Remarks'] ?? '';
                    } else {
                        $message = isset($inspection_result['Remarks']) && !empty($inspection_result['Remarks']) ? $inspection_result['RejectReason'] : 'Your Inspection has not been recommended yet';
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
