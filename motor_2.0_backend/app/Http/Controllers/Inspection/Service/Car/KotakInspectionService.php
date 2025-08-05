<?php

namespace App\Http\Controllers\Inspection\Service\Car;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Quotes\Car\kotak;
use Illuminate\Support\Facades\DB;
use App\Models\JourneyStage;
use App\Models\CvBreakinStatus;
use App\Models\PolicyDetails;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use Carbon\Carbon;
use App\Models\UserProposal;
// request

class KotakInspectionService extends Controller
{
    //
    public static function wimwisureInspectionConfirm($request)
    {
        $breakinDetails = DB::table('cv_breakin_status')
        ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
        ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->join('cv_journey_stages', 'cv_breakin_status.user_proposal_id', '=', 'cv_journey_stages.proposal_id')
        ->join('corporate_vehicles_quotes_request', 'quote_log.user_product_journey_id', 'corporate_vehicles_quotes_request.user_product_journey_id')
        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
        ->select(
            'cv_breakin_status.*',
            'user_proposal.first_name',
            'user_proposal.last_name',
            'user_proposal.address_line1',
            'user_proposal.address_line2',
            'user_proposal.address_line3',
            'user_proposal.mobile_number',
            'user_proposal.email',
            'user_proposal.vehicale_registration_number',
            'user_proposal.engine_number',
            'user_proposal.chassis_number',
            'user_proposal.pincode',
            'user_proposal.user_product_journey_id',
            'user_proposal.proposal_no',
            'user_proposal.final_payable_amount',
            'user_proposal.is_inspection_done',
            'user_proposal.policy_start_date',
            'user_proposal.policy_end_date',
            'quote_log.master_policy_id',
            'corporate_vehicles_quotes_request.version_id',
            'corporate_vehicles_quotes_request.gcv_carrier_type',
            'cv_journey_stages.proposal_url'
        )
        ->first();
    $inspection_done=false;
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
            if ( ! isset($inspection_result['Remarks']) || (isset($inspection_result['Remarks']) && $inspection_result['Remarks'] != 'APPROVED'))
            {
                $request->api_key = config('constants.wimwisure.API_KEY_KOTAK');
                $inspection = new WimwisureBreakinController();
                $inspection_result = $inspection->WimwisureCheckInspection($request);
                if($inspection_result['Status'] == 'COMPLETED')
                {
                    $inspection_done=true;
                }
            }
            if (isset($inspection_result['Remarks']) && $inspection_result['Remarks'] == 'APPROVED' || $inspection_done==true) 
            {
                $m_policy_start_date = Carbon::parse($breakinDetails->policy_start_date);
                $m_payment_end_date = Carbon::parse($breakinDetails->payment_end_date);
                CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))
                ->update([
                    'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED'],
                    'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                    'payment_end_date' => empty($breakinDetails->payment_end_date) ? Carbon::today()->addDay(2)->toDateString() : $breakinDetails->payment_end_date,
                ]);
                    $m_current_date = Carbon::now();
                    if($m_current_date->gt($m_payment_end_date))
                    {
                        UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update([
                            'policy_start_date' => null,
                            'policy_end_date' => null,
                        ]);
                        $message = 'Your Vehicle Inspection Has Expired.';
                        return response()->json([
                            'status' => false,
                            'msg' => $message
                        ]);
                    }
                    if($m_policy_start_date->lte($m_payment_end_date))
                    {
                        $s_policy_start_date = Carbon::today()->format('d-m-Y');
                        $s_policy_end_date = carbon::now()->copy()->addYear()->format('d-m-Y');

                        UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update([
                            'policy_start_date' => $s_policy_start_date,
                            'policy_end_date' => $s_policy_end_date,
                        ]);
                    }
                    else
                    {
                        $message = 'Your Vehicle Inspection Has Expired Please Do Re-Inspection.';
                        return response()->json([
                            'status' => false,
                            'msg' => $message . ' Your Breakin Number is ' . $request->inspectionNo
                        ]);
                    }
                    
                if($breakinDetails->is_inspection_done == 'Y' && $m_policy_start_date->gt($m_payment_end_date))
                {
                    $message = 'Your Vehicle Inspection Has Expired Please Do Re-Inspection.';
                    return response()->json([
                        'status' => false,
                        'msg' => $message . ' Your Breakin Number is ' . $request->inspectionNo,
                        'payment_end_date' => $breakinDetails->payment_end_date,

                    ]);
                }
               UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                    ->update([
                        'is_inspection_done' => 'Y',
                    ]);

                $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                    ->first();

                $status = true;
                $message = 'Your Vehicle Inspection is Done By Kotak.';

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
            elseif (isset($inspection_result['Remarks']) && $inspection_result['Remarks'] == 'REJECTED')
                {
                    CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))   
                        ->update([
                            'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                            'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED']
                        ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,                               
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
                        'msg' => $breakinDetails->breakin_status ? 'Inspection status is ' . $breakinDetails->breakin_status : 'Insurer not reachable'
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
            'status' => true,
            'msg' => 'Please check your inspection number'
        ]);
    }
       ////////////////////////////////////////////// // 
      
    }
   
}
