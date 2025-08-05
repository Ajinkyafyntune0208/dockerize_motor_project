<?php

namespace App\Http\Controllers\Inspection\Service;

use App\Models\JourneyStage;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use App\Models\CvBreakinStatus;
use App\Models\UserProposal;

use App\Http\Controllers\Inspection\Service\Cv\V2\GodigitInspectionService as oneapi;
use App\Models\CvAgentMapping;
use App\Models\QuoteLog;

include_once app_path().'/Helpers/CvWebServiceHelper.php';
class GoDigitInspectionService
{
    public static function inspectionConfirm($request)
    {
        if (config('IC.GODIGIT.V2.CV.ENABLE') == 'Y')
        return  oneapi::oneApiInspectionConfirm($request);
    
        $breakinDetails = CvBreakinStatus::with([
            'user_proposal',
            'user_proposal.quote_log',
            'user_proposal.journey_stage'
        ])
        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
        ->first();
        if(!empty($breakinDetails))
        {
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();

            if ($policy_details)
            {
                $status = false;
                $message = 'Policy has already been generated for this inspection number';
            }
            else
            {
                $breakin_status_response = json_decode($breakinDetails->breakin_response, true);

                if (isset($breakin_status_response['policyStatus']) && in_array($breakin_status_response['policyStatus'], ['PRE_INSPECTION_APPROVED', 'INCOMPLETE']))
                {
                    $data = $breakinDetails->breakin_response;
                }
                else
                {

                    $webUserId = config("constants.IcConstants.godigit.GODIGIT_WEB_USER_ID");
                    $password = config("constants.IcConstants.godigit.GODIGIT_PASSWORD");

                    $posData = CvAgentMapping::where([
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                        'seller_type' => 'P'
                    ])
                    ->first();

                    if (!empty($posData)) {

                        $policyId = QuoteLog::where('user_product_journey_id', $breakinDetails->user_product_journey_id)
                        ->pluck('master_policy_id')
                        ->first();

                        $productData = getProductDataByIc($policyId);

                        $credentials = getPospImdMapping([
                            'sellerType' => 'P',
                            'sellerUserId' => $posData->agent_id,
                            'productSubTypeId' => $productData->product_sub_type_id,
                            'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                        ]);

                        if ($credentials['status'] ?? false) {
                            $webUserId = $credentials['data']['web_user_id'];
                            $password = $credentials['data']['password'];
                        }
                    }

                    $url = config('constants.IcConstants.godigit.GODIGIT_BREAKIN_STATUS').trim($request->inspectionNo);
                    $get_response = getWsData($url, $url, 'godigit', [
                            'enquiryId'         => $breakinDetails->user_proposal->user_product_journey_id,
                            'requestMethod'     => 'get',
                            'section'           => 'TAXI',
                            'productName'       => '',//$product->product_sub_type_name,
                            'company'           => 'godigit',
                            'method'            => 'Check Breakin Status',
                            'transaction_type'  => 'proposal',
                            'webUserId' => $webUserId,
                            'password' => $password
                        ]
                    );
                    $data = $get_response['response'];
                }
                $response = json_decode($data, TRUE);
                if(!is_array($response)) {
                    return response()->json([
                        'status' => false,
                        'msg'    => 'GoDigit API is not working as expected.'
                    ]);
                }

                $proposal_resp_array = array_change_key_case($response, CASE_LOWER);

                if (isset($proposal_resp_array['policystatus']))
                {
                    if (in_array($proposal_resp_array['policystatus'], ['PRE_INSPECTION_APPROVED', 'INCOMPLETE'])) 
                    {
                        $update_data = [
                            'breakin_response'      => $data,
                            'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                            'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                            'updated_at'            => date('Y-m-d H:i:s'),
                            'payment_url' =>  str_replace('quotes','proposal-page', $breakinDetails->user_proposal->journey_stage->proposal_url),
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 2 day')),
                            'inspection_date'       => date('Y-m-d'),
                        ]; 
                        DB::table('cv_breakin_status')
                        ->where('breakin_number', trim($request->inspectionNo))
                        ->update($update_data);

                        $productData = getProductDataByIc($breakinDetails->user_proposal->quote_log->master_policy_id);

                        $premium_type = DB::table('master_premium_type')
                            ->where('id', $productData->premium_type_id)
                            ->pluck('premium_type_code')
                            ->first();
                        
                        $breakin_make_time = strtotime('18:00:00');
                        $policy_start_date = date('Y-m-d');
                        // if ($breakin_make_time > time()) {
                        //     $policy_start_date = date('Y-m-d', strtotime('+1 day', time())); 
                        // } else {
                        //     $policy_start_date = date('Y-m-d', strtotime('+2 day', time())); 
                        // }

                        if (in_array($premium_type, ['short_term_3', 'short_term_3_breakin'])) {
                            $policy_end_date = date('Y-m-d', strtotime('+3 month -1 day', strtotime($policy_start_date)));
                        } elseif (in_array($premium_type, ['short_term_6', 'short_term_6_breakin'])) {
                            $policy_end_date = date('Y-m-d', strtotime('+6 month -2 day', strtotime($policy_start_date)));
                        } else {
                            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                        }

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
                                'proposalUrl' =>  str_replace('quotes','proposal-page', $journey_stage->proposal_url)
                            ]
                        ]);

                    } 
                    else 
                    {
                        $update_data = [
                            'breakin_response' => $data,
                            'updated_at' => date('Y-m-d H:i:s')
                        ];

                        $status = false;
                        $message = 'Your Vehicle Inspection is Pending. Please try after some time';
                        if($proposal_resp_array['policystatus'] == 'SELF_INSPECTION_PENDING')
                        {
                                $message = 'Your Vehicle Inspection is Pending. Please try after some time';
                        }
                        else if($proposal_resp_array['policystatus'] == 'PRE_INSPECTION_DECLINED')
                        {
                            $message = 'PRE INSPECTION DECLINED';
                            updateJourneyStage([
                                'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                            ]);

                            $update_data = [
                                'breakin_response' => $data,
                                'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                                'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                        }
                        else if($proposal_resp_array['policystatus'] == 'ASSESSMENT_PENDING')
                        {
                                $message = 'ASSESSMENT PENDING';
                        }

                        DB::table('cv_breakin_status')
                            ->where('breakin_number', trim($request->inspectionNo))
                            ->update($update_data);
                    }
                } 
                else 
                {
                    $status = false;
                    $message = 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction';
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

    public static function wimwisureInspectionConfirm($request)
    {

        $breakinDetails = CvBreakinStatus::with([
            'user_proposal',
            'user_proposal.quote_log',
            'user_proposal.journey_stage'
        ])
            ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
            ->first();

        if (!empty($breakinDetails))
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
                    $request->api_key = config('constants.wimwisure.API_KEY_GODIGIT');
                    $inspection = new WimwisureBreakinController();
                    $inspection_result = $inspection->WimwisureCheckInspection($request);
                }

                if (( ! isset($ic_breakin_response['status']) || (isset($ic_breakin_response['status']) && $ic_breakin_response['status'] != 'SelfSurvey-Approved')))
                {
                    $url = config('constants.IcConstants.godigit.GODIGIT_WIMWISURE_BREAKIN_STATUS') . trim($request->inspectionNo);

                    $get_response = getWsData($url, $url, 'godigit', [
                            'enquiryId'         => $breakinDetails->user_proposal->user_product_journey_id,
                            'requestMethod'     => 'get',
                            'section'           => 'TAXI',
                            'productName'       => '',//$product->product_sub_type_name,
                            'company'           => 'godigit',
                            'method'            => 'Check Wimwisure Breakin Status',
                            'transaction_type'  => 'proposal',
                            'authorization'     => config('constants.IcConstants.godigit.GODIGIT_WIMWISURE_BREAKIN_AUTHORIZATION'),
                            'headers'           => [
                                'Authorization' => config('constants.IcConstants.godigit.GODIGIT_WIMWISURE_BREAKIN_AUTHORIZATION')
                            ]
                        ]
                    );
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

                    $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
                    
                    $breakin_make_time = strtotime('18:00:00');

                    if ($breakin_make_time > time())
                    {
                        $policy_start_date = date('Y-m-d', strtotime('+1 day', time())); 
                    }
                    else
                    {
                        $policy_start_date = date('Y-m-d', strtotime('+2 day', time())); 
                    }

                    if (in_array($premium_type, ['short_term_3', 'short_term_3_breakin']))
                    {
                        $policy_end_date = date('Y-m-d', strtotime('+3 month -1 day', strtotime($policy_start_date)));
                    }
                    elseif (in_array($premium_type, ['short_term_6', 'short_term_6_breakin']))
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
