<?php

namespace App\Http\Controllers\Inspection\Service\Car;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\UserProposal;
use App\Models\JourneyStage;
use App\Models\PolicyDetails;
use App\Models\MasterProduct;

use App\Http\Controllers\Proposal\Services\Car\tataAigV2SubmitProposal as TATA_AIG;

include_once app_path().'/Helpers/CarWebServiceHelper.php';

class TATAAIGInspectionService
{

    public static function inspectionConfirm($request)
    {
        $breakinDetails = DB::table('cv_breakin_status')
        ->join('user_proposal', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
        ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->join('cv_journey_stages', 'cv_breakin_status.user_proposal_id', '=', 'cv_journey_stages.proposal_id')
        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
        ->select('cv_breakin_status.*','user_proposal.user_product_journey_id','user_proposal.proposal_no','user_proposal.final_payable_amount', 'quote_log.master_policy_id', 'cv_journey_stages.proposal_url', 'cv_breakin_status.breakin_number')
        ->first();

        if($breakinDetails)
        {
            $enquiryId = $breakinDetails->user_product_journey_id;
            $productData = getProductDataByIc($breakinDetails->master_policy_id);
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();
            $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

            if ($policy_details)
            {
                $status = false;
                $message = 'Policy has already been generated for this inspection number';
            }
            else
            {
                $breakinStatusResponse = json_decode($breakinDetails->breakin_response, TRUE);

                if (isset($breakinStatusResponse['data']['inspection_status']) && $breakinStatusResponse['data']['inspection_status'] == 'Approved') 
                {
                    $breakinStatusResponse = $breakinDetails->breakin_response;
                    $breakinStatusResponse = json_decode($breakinStatusResponse, true);
                }
                else
                {
                    $token_response = TATA_AIG::getToken($enquiryId, $productData);
                
                    if(!$token_response['status'])
                    {
                        $token_response['product_identifier'] = $masterProduct->product_identifier;
                        return $token_response;
                    }

                    $verifyInspectionRequest = [
                        'proposal_no' => $breakinDetails->proposal_no,
                        'ticket_no' => $request->inspectionNo,
                    ];

                    $additional_data = [
                        'enquiryId'         => $enquiryId,
                        'headers'           => [
                            'Content-Type'  => 'application/JSON',
                            'Authorization'  => 'Bearer '.$token_response['token'],
                            'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
                        ],
                        'requestMethod'     => 'post',
                        'requestType'       => 'json',
                        'section'           => $productData->product_sub_type_code,
                        'method'            => 'Verify Inspection - Proposal',
                        'transaction_type'  => 'proposal',
                        'productName'       => $productData->product_name,
                        'token'             => $token_response['token'],
                    ];

                    $url = config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_VERIFY_INSPECTION');

                    $get_response = getWsData($url, $verifyInspectionRequest, 'tata_aig_v2', $additional_data);
                    $data = $get_response['response'];

                    if($data && $data != '' && $data != null)
                    {
                        $data = json_decode($data, true);

                        if(!empty($data))
                        {
                            if(!isset($data['status']))
                            {
                                if(isset($data['message']))
                                {
                                    return response()->json([
                                        'status' => false,
                                        'msg'    => $data['message'],
                                        'product_identifier' => $masterProduct->product_identifier,
                                    ]);             
                                }

                                return response()->json([
                                    'status' => false,
                                    'msg'    => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction',
                                    'product_identifier' => $masterProduct->product_identifier,
                                ]);

                            }
                            if($data['status'] != 200)
                            {
                                if(!isset($data['message_txt']))
                                {
                                    return response()->json([
                                        'status' => false,
                                        'msg'    => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction',
                                        'product_identifier' => $masterProduct->product_identifier,
                                    ]);
                                }
                                return response()->json([
                                    'status' => false,
                                    'msg'    => $data['message_txt'],
                                    'product_identifier' => $masterProduct->product_identifier,
                                ]);
                            }
                        }
                        else{
                            return response()->json([
                                'status' => false,
                                'msg'    => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction'
                            ]);
                        }
                        
                        $breakinStatusResponse = $data;
                    }
                    else
                    {
                        return response()->json([
                            'status' => false,
                            'msg'    => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction'
                        ]);
                    }
                }
                if (isset($breakinStatusResponse['data']['inspection_status']) || isset($breakinStatusResponse['data'][0]['inspection_status']))
                {

                    if(isset($breakinStatusResponse['data'][0]))
                    {
                        $breakinStatusResponse['status'] = 200;
                        $breakinStatusResponse['data'] = $breakinStatusResponse['data'][0];
                        return self::checkInspectionStatus($breakinStatusResponse, $breakinDetails);
                    }
                    else
                    {
                        return self::checkInspectionStatus($breakinStatusResponse, $breakinDetails);
                    }
                } 
                else 
                {
                    $status = false;
                    $message = 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction';
                    return response()->json([
                        'status' => $status,
                        'msg'    => $message
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


    public static function checkInspectionStatus($serviceResponse, $breakinDetails)
    {
        if ($serviceResponse['data']['inspection_status'] == 'Approved') 
        {
            $update_data = [
                'breakin_response'      => $serviceResponse,
                'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                'updated_at'            => date('Y-m-d H:i:s'),
                'payment_url' =>  str_replace('quotes','proposal-page',$breakinDetails->proposal_url),
                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 2 day'))
            ]; 
            DB::table('cv_breakin_status')
            ->where('breakin_number', trim($breakinDetails->breakin_number))
            ->update($update_data);
            
            $breakin_make_time = strtotime('18:00:00');

            if ($breakin_make_time > time()) {
                $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
            } else {
                $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));
            }
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));

            $proposal = UserProposal::where('user_product_journey_id', $breakinDetails->user_product_journey_id)->first();

            $proposal_additional_details_data = json_decode($proposal->additional_details_data);

            if(isset($serviceResponse['data']['policy']['payment_id']))
            {
                $proposal_additional_details_data->tata_aig_v2->payment_id =  $serviceResponse['data']['policy']['payment_id'];
            }
            else
            {
                $proposal_additional_details_data->tata_aig_v2->payment_id =  $serviceResponse['data']['policy'][0]['payment_id'];
            }

            UserProposal::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
            ->update([
                'is_inspection_done' => 'Y',
                'additional_details_data' => json_encode($proposal_additional_details_data),
                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
            ]);

            $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_product_journey_id))
                ->first();

            $status = true;
            $message = 'Your Vehicle Inspection is Done By TATA AIG.';
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
                'breakin_response' => $serviceResponse,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $status = false;
            $message = 'Your Vehicle Inspection is Pending. Please try after some time';
            if($serviceResponse['data']['inspection_status'] == 'Pending')
            {
                    $message = 'Your Vehicle Inspection is Pending. Please try after some time';
            }
            else if($serviceResponse['data']['inspection_status'] == 'Rejected')
            {
                $message = 'Your Vehicle Inspection is Rejected';
                updateJourneyStage([
                    'user_product_journey_id' => $breakinDetails->user_product_journey_id,            
                    'stage' => STAGE_NAMES['INSPECTION_REJECTED']                                
                ]);

                $update_data = [
                    'breakin_response' => $serviceResponse,
                    'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                    'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }
            DB::table('cv_breakin_status')
                ->where('breakin_number', trim($breakinDetails->breakin_number))
                ->update($update_data);
        }
        return response()->json([
            'status' => $status,
            'msg'    => $message
        ]);
    }
}
