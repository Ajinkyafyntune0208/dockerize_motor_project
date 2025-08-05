<?php

namespace App\Http\Controllers\Inspection\Service;

use App\Models\CvBreakinStatus;
use App\Models\QuoteLog;
use App\Models\JourneyStage;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Inspection\Service\UniversalSompoInspectionService_Miscd;

include_once app_path().'/Helpers/CvWebServiceHelper.php';
class UniversalSompoInspectionService
{
    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::with('user_proposal')
        ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
        ->first();

        if (empty($breakinDetails->user_proposal?->user_product_journey_id)) {
            return [
                'status' => false,
                'message' => 'Please enter correct Inspection Number'
            ];
        }

        $requestData = getQuotation($breakinDetails->user_proposal->user_product_journey_id);
        $policy_id = QuoteLog::where('user_product_journey_id', $requestData->user_product_journey_id)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policy_id);

        //Miscd Blaze integration breakin new file.
        $is_MISC = policyProductType($policy_id)->parent_id;
        if($is_MISC == 3 && config('IC.UNIVERSAL_SOMPO.V2.MISCD.ENABLED') == 'Y'){
            return UniversalSompoInspectionService_Miscd::inspectionConfirm($request);
        }

        switch ($requestData->policy_type) 
        {
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

        if($breakinDetails)
        {
            $check_inspection_request = [
                'Authentication' => [
                    'WACode' =>  config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_MOTOR'),
                    'WAAppCode' => config('constants.IcConstants.universal_sompo.AUTH_APPCODE_SOMPO_MOTOR'),
                    'ProductCode' => $product_code
                ],
                'ReferenceId' => $breakinDetails->breakin_number,
                'Name' => $breakinDetails->user_proposal->first_name . ' ' . $breakinDetails->user_proposal->last_name
            ];

            $get_response = getWsData(config('constants.IcConstants.universal_sompo.BREAKIN_STATUS_CHECK_END_POINT_URL_UNIVERSAL_SOMPO_CV'), $check_inspection_request, 'universal_sompo', [
                'requestMethod' => 'post',
                'enquiryId' => $breakinDetails->user_proposal->user_product_journey_id,
                'method' => 'Check Inspection',
                'section' => $productData->product_sub_type_code,
                'productName'   => $productData->product_sub_type_name,
                'transaction_type' => 'proposal',
            ]);
            $data = $get_response['response'];

            if($data)
            {
                $response = json_decode($data, true);
                $status_array = array("company-approved", "approved", "accepted");

                if(isset($response['Status']) && $response['StatusCode'] == 200 && in_array(strtolower($response['Status']), $status_array))
                {
                    UserProposal::where('user_product_journey_id', $requestData->user_product_journey_id)
                                ->update([
                                    'is_inspection_done' => 'Y',
                                ]);

                    DB::table('cv_breakin_status')->where('user_proposal_id', $breakinDetails->user_proposal_id)
                    ->update([
                        'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED'],
                        'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                        'inspection_date' => date('Y-m-d'),
                        'payment_url' => config('constants.motorConstant.CV_BREAKIN_PAYMENT_URL').customEncrypt($breakinDetails->user_proposal->user_product_journey_id),
                        'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL')
                    ]);

                    updateJourneyStage([
                                    'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                        ]);
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
                            'proposalUrl' =>  str_replace('quotes','proposal-page', $journey_stage->proposal_url)
                        ]
                    ]);
                }
                elseif (isset($response['Status']) && ($response['StatusCode'] != 200 || strtolower($response['Status']) == "rejected"))
                {
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
                }
                else
                {
                    updateJourneyStage([
                                    'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_PENDING']
                        ]);
                    $message = (strtolower($response['ErrorMessage']) == "ok") ? "Your inspection is in Pending. Kindly check after some time" : $response['ErrorMessage'];
                                $status = false;
                                return $return_data = [
                                    'status' => $status,
                                    'message' => $message
                                ];

                }

            }
            else
            {
                return [
                    'status' => false,
                    'message' => 'Sorry, we are unable to process your request at the moment. Your inspection is Pending'
                ];
            }


        }
        else{
            return [
                'status' => false,
                'message' => 'Please enter correct Inspection Number'
            ];
        }

    }
}
