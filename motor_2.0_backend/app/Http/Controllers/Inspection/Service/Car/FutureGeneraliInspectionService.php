<?php

namespace App\Http\Controllers\Inspection\Service\Car;

use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;

include_once app_path().'/Helpers/CarWebServiceHelper.php';
class FutureGeneraliInspectionService
{

    public static function updateBreakinStatus($request) 
    {

        $body = file_get_contents('php://input');
        $InspectionData = json_decode($body);
        $InspectionData = json_decode(json_encode($InspectionData), true);
        
        if(isset($_SERVER['PHP_AUTH_USER']))
        {
            if((($_SERVER['PHP_AUTH_USER'] == config('constants.IcConstants.future_generali.BROKER_USER_FOR_NEW_INSPECTION')) && ($_SERVER['PHP_AUTH_PW'] == config('constants.IcConstants.future_generali.BROKER_PASS_FOR_NEW_INSPECTION'))))
            {
               
       
                $InspectionStatus = trim($InspectionData['Status']);
                $Status_Message = $InspectionData['Message'];
                $IC_Inspection_ID = $InspectionData['IC_Inspection_ID'];
                $refID = $InspectionData['reference_id'];
                $approval_date = $InspectionData['inspection_approval_date'];
                $return_data['status_code'] = '200';
                $return_data['status'] = 'true';
                $return_data['Status_Message'] = 'Your inspection no ' . $IC_Inspection_ID . ' is approved on ' . $approval_date . ' with reference id provided:- ' . $refID;
                

                $breakinDetails = DB::table('cv_breakin_status')
                        ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
                        ->where('cv_breakin_status.breakin_number', '=', trim($refID))
                        ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount')
                        ->first();
                if($breakinDetails)
                {
                    if ($InspectionStatus == 'Approved') 
                    {

                        //updating cv_breakin_status
                        $update_data = [
                            'breakin_response'      => $InspectionData,
                            'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_id'            => $IC_Inspection_ID,
                            'updated_at'            => date('Y-m-d H:i:s'),
                            'payment_url' => config('constants.motorConstant.CAR_BREAKIN_PAYMENT_URL').customEncrypt($breakinDetails->user_product_journey_id),
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 1 day'))

                        ]; 
                        DB::table('cv_breakin_status')
                        ->where('breakin_number', trim($refID))
                        ->update($update_data);

                        //updating user_proposal
                        $policy_start_date = date('d/m/Y', strtotime("+1 day")); 
                        $policy_end_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
                        $proposal_date = date('Y-m-d H:i:s');
                        $status_data = 
                        [
                                    'is_inspection_done' => 'Y',
                                    'policy_start_date'                 => str_replace('/','-',$policy_start_date),
                                    'policy_end_date'                   => str_replace('/','-',$policy_end_date),
                                    'proposal_date'                     => $proposal_date
                                ];
                        UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update($status_data);


                        $status = true;
                        $message = 'Your Vehicle Inspection is Done By Future Generali(Live_chek).';
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
                                'totalPayableAmount' => $breakinDetails->final_payable_amount
                            ]
                        ]);
                        
                           
                            
                    } 
                    else 
                    {

                      //updating cv_breakin_status
                        $update_data = [
                            'breakin_response'      => $InspectionData,
                            'breakin_status'        => STAGE_NAMES['INSPECTION_REJECTED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_REJECTED'],
                            'updated_at'            => date('Y-m-d H:i:s'),
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            
                        ]; 
                        DB::table('cv_breakin_status')
                        ->where('breakin_number', trim($refID))
                        ->update($update_data);

                        //updating user_proposal
                        $policy_start_date = date('d/m/Y', strtotime("+1 day")); 
                        $policy_end_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
                        $proposal_date = date('Y-m-d H:i:s');
                        $status_data = 
                        [
                                    'is_inspection_done' => 'Y',
                                    'policy_start_date'                 => str_replace('/','-',$policy_start_date),
                                    'policy_end_date'                   => str_replace('/','-',$policy_end_date),
                                    'proposal_date'                     => $proposal_date
                                ];
                        UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                        ->update($status_data);


                        $status = false;
                        $message = 'Your Vehicle Inspection is rejected By Future Generali(Live_chek).';
                        updateJourneyStage([
                                    'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                        ]);
                        return response()->json([
                            'status' => $status,
                            'msg'    => $message,
                            'data'   => [
                                'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                                'proposalNo' => $breakinDetails->proposal_no,                    
                                'totalPayableAmount' => $breakinDetails->final_payable_amount
                            ]
                        ]);

                    }

                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Please Check Your Inspection Reference ID'
                    ]);    
                }
                    
            }
            else
            {
                header("WWW-Authenticate: Basic realm=\"Private Area\"");
                header("HTTP/1.0 401 Unauthorized");
                print_r(json_encode("You are not authorized user"));
                exit;
            }
                
        }
        else
        {
            header("WWW-Authenticate: Basic realm=\"Private Area\"");
            header("HTTP/1.0 401 Unauthorized");
            print_r(json_encode("Please Enter Authentication Details"));
            exit;
        }
        
    }
    public static function inspectionConfirm($request)
    {
        $breakinDetails = DB::table('cv_breakin_status')
                        ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
                        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
                        ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount','user_proposal.user_proposal_id')
                        ->first();
        
        if($breakinDetails)
        {
        
           $check_policy = DB::table('policy_details')
                        ->where('proposal_id', '=', trim($breakinDetails->user_proposal_id))
                        ->first();
            if($check_policy)
            {
                if($check_policy->policy_number != '' && $check_policy->policy_number != 'NULL')
               {
                    return response()->json([
                            'status' => false,
                            'msg' => 'Payment is already done and generated policy number is ' . $check_policy->policy_number,
                       ]);
               }

            }
           else
           {
               if($breakinDetails->breakin_id == '' &&  $breakinDetails->breakin_status_final == '')
               {
                   return response()->json([
                        'status' => false,
                        'msg' => 'Your vehicle inspection is pending'
                   ]);
               }
               if($breakinDetails->breakin_id != '' && $breakinDetails->breakin_status_final ==  STAGE_NAMES['INSPECTION_APPROVED'])
               {

                    return response()->json([
                        'status' => true,
                        'msg'    => 'Your vehicle inspection is approved by Future Generali',
                        'data'   => [
                            'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                            'proposalNo' => $breakinDetails->proposal_no,                    
                            'totalPayableAmount' => $breakinDetails->final_payable_amount
                        ]
                    ]);

                    
               }
               elseif($breakinDetails->breakin_status_final ==  STAGE_NAMES['INSPECTION_REJECTED'])
               {
                  return response()->json([
                        'status' => false,
                        'msg' => 'Your vehicle inspection is rejected by Future Generali'
                   ]);
               }
           }
           
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
