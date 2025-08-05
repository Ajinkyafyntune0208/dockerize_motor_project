<?php

namespace App\Http\Controllers\Inspection\Service\Car;
use App\Models\JourneyStage;
use App\Models\UserProposal;
use App\Models\CvBreakinStatus;
use App\Http\Controllers\CommonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
include_once app_path().'/Helpers/CarWebServiceHelper.php';

class RoyalSundaramInspectionService {
    public static function inspectionConfirm($request)
    {
        $status = false;
        $message = "No data against this Inpection ID";
        $breakinDetails = DB::table('cv_breakin_status')
            ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
            ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
            ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount')
            ->first();

        if (!empty($breakinDetails)) {
            $status = true;
            $message = $breakinDetails->breakin_status;
        }
        $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
        ->first();

        if($breakinDetails->breakin_status_final != STAGE_NAMES['INSPECTION_APPROVED'])
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Inspection is pending from ic end'
            ]);
        }
        $payment_end_datetime = strtotime($breakinDetails->payment_end_date);
        /*if( time() <= $payment_end_datetime)
        {
            $redirect_url = $journey_stage->proposal_url;
        }
        else
        {
            $redirect_url = $journey_stage->quote_url;
            
            UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
            ->update([
                        'is_inspection_done' => 'N',
                        'is_breakin_case' => null
            ]);


           
                $old_enquiry = customEncrypt($breakinDetails->user_product_journey_id);
               
                $common_controller = new CommonController;
                $request->merge(["enquiryId"=>$old_enquiry]);
                $data_response = $common_controller->createDuplicateJourney($request);
                $respData = $data_response->getData();
                // $data_response_decode = json_decode($data_response);
              
                if(isset($respData->status) && $respData->status)
                {
                    $new_enquiry_id = $respData->data->enquiryId;
                    $old_url = $journey_stage->quote_url; 
                    $new_url =  str_replace($old_enquiry,$new_enquiry_id,$old_url); // new uerl with new enquiery id
                    $redirect_url = $new_url;
                }else
                {
                    return response()->json([
                        'status' => false,
                        'msg'    => 'There is an internal issue'
                    ]);
                }
 
        }*/
        $redirect_url =  str_replace('quotes','proposal-page',$journey_stage->proposal_url);
        $policy_start_date = date('Y-m-d');
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        
        UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
        ->update([
            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
        ]);

        return response()->json([
                        'status' => $status,
                        'msg'    => $message,
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                            'proposalNo' => $breakinDetails->proposal_no,                    
                            'totalPayableAmount' => $breakinDetails->final_payable_amount,
                            'proposalUrl' =>  $redirect_url
                        ]
                    ]);
    }

    public static function updateBreakinStatus($request) {
        $status = false;
        $message = "Quote ID or VIR Status missing";

        if (!empty($request->Quote_id) && !empty($request->VIRStatus)) {
            $user_proposal = UserProposal::where('proposal_no', $request->Quote_id)->first();
            $status = true;
            $message = STAGE_NAMES['INSPECTION_PENDING'];
            $payment = '';
            
            if (strtolower($request->VIRStatus) == 'recommended') {
                $message = STAGE_NAMES['INSPECTION_APPROVED'];
                $payment = config('constants.motorConstant.CAR_BREAKIN_PAYMENT_URL') . customEncrypt($user_proposal->user_product_journey_id);
                UserProposal::where('user_proposal_id' , $user_proposal->user_proposal_id)
                ->update([
                            'is_inspection_done' => 'Y'
                        ]);
                
            }

            $updateInspectionData = [
                'breakin_status' => $message,
                'breakin_status_final' => $message,
                'breakin_response' => json_encode($request->input()),
                'payment_url' => $payment,
                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                'payment_end_date'   => date('Y-m-d H:i:s', strtotime('24 hours')),
            ];
            if ($message == STAGE_NAMES['INSPECTION_APPROVED']) {
                $updateInspectionData ['inspection_date'] = date('Y-m-d');
            }
            CvBreakinStatus::where('user_proposal_id' , $user_proposal->user_proposal_id)
            ->update($updateInspectionData);
        }

        return response()->json([
            'status' => $status,
            'msg' => $message
        ]);
    }
}