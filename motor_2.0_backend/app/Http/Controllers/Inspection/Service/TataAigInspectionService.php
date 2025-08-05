<?php

namespace App\Http\Controllers\Inspection\Service;

use TataAigV2Helper;
use App\Models\JourneyStage;

use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Proposal\Services\Car\tataAigV2SubmitProposal as TATA_AIG;
use App\Models\CvBreakinStatus;

include_once app_path() . '/Helpers/IcHelpers/TataAigV2Helper.php';
include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class TataAigInspectionService
{

    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::with([
            'user_proposal',
            'user_proposal.quote_log',
            'user_proposal.journey_stage'
        ])
            ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
            // ->latest('created_at')
            ->first();

        if (!empty($breakinDetails)) {
            $enquiryId = $breakinDetails->user_proposal->user_product_journey_id;
            $productData = getProductDataByIc($breakinDetails->user_proposal->quote_log->master_policy_id);
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal_id)->first();
            $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
                ->first();

            if ($policy_details) {
                $status = false;
                $message = 'Policy has already been generated for this inspection number';
            } else {
                $breakinStatusResponse = json_decode($breakinDetails->breakin_response, true);

                if (isset($breakinStatusResponse['data']['inspection_status']) && $breakinStatusResponse['data']['inspection_status'] == 'Approved') {
                    $breakinStatusResponse = $breakinDetails->breakin_response;
                    $breakinStatusResponse = json_decode($breakinStatusResponse, true);
                } else {
                    $token_response = TataAigV2Helper::getToken($enquiryId, $productData);

                    if (!$token_response['status']) {
                        return $token_response;
                    }

                    $verifyInspectionRequest = [
                        'proposal_no' => $breakinDetails->user_proposal->proposal_no,
                        'ticket_no' => $request->inspectionNo,
                    ];

                    $additional_data = [
                        'enquiryId'         => $enquiryId,
                        'headers'           => [
                            'Content-Type'  => 'application/JSON',
                            'Authorization'  => 'Bearer ' . $token_response['token'],
                            'x-api-key'      => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
                        ],
                        'requestMethod'     => 'post',
                        'requestType'       => 'json',
                        'section'           => $productData->product_sub_type_code,
                        'method'            => 'Verify Inspection - Proposal',
                        'transaction_type'  => 'proposal',
                        'productName'       => $productData->product_name,
                        'token'             => $token_response['token'],
                    ];

                    $url = config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_PCV_END_POINT_URL_VERIFY_INSPECTION');

                    $get_response = getWsData($url, $verifyInspectionRequest, 'tata_aig_v2', $additional_data);
                    $data = $get_response['response'];

                    if ($data && $data != '' && $data != null) {
                        $data = json_decode($data, true);

                        if (!empty($data)) {
                            if (!isset($data['status'])) {
                                if (isset($data['message'])) {
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
                            if ($data['status'] != 200) {
                                if (!isset($data['message_txt'])) {
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
                        } else {
                            return response()->json([
                                'status' => false,
                                'msg'    => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction'
                            ]);
                        }

                        $breakinStatusResponse = $data;
                    } else {
                        return response()->json([
                            'status' => false,
                            'msg'    => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction'
                        ]);
                    }
                }
            }
            if (isset($breakinStatusResponse['data']['inspection_status']) || isset($breakinStatusResponse['data'][0]['inspection_status'])) {

                if (isset($breakinStatusResponse['data'][0])) {
                    $breakinStatusResponse['status'] = 200;
                    $breakinStatusResponse['data'] = $breakinStatusResponse['data'][0];
                    return self::checkInspectionStatus($breakinStatusResponse, $breakinDetails);
                } else {
                    return self::checkInspectionStatus($breakinStatusResponse, $breakinDetails);
                }
            } else {
                $status = false;
                $message = 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction';
                return response()->json([
                    'status' => $status,
                    'msg'    => $message
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Please Check Your Inspection Number'
            ]);
        }
    }


    public static function checkInspectionStatus($serviceResponse, $breakinDetails)
    {
        if ($serviceResponse['data']['inspection_status'] == 'Approved') {
            $update_data = [
                'breakin_response'      => $serviceResponse,
                'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                'updated_at'            => date('Y-m-d H:i:s'),
                'payment_url' =>  str_replace('quotes', 'proposal-page', $breakinDetails->user_proposal->journey_stage->proposal_url),
                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                'payment_end_date'      => date('Y-m-d 23:59:59', strtotime($serviceResponse["data"]["policy"][0]["pol_start_date"]))
            ];
            DB::table('cv_breakin_status')
                ->where('breakin_number', trim($breakinDetails->breakin_number))
                ->update($update_data);

            $proposal = UserProposal::where('user_product_journey_id', $breakinDetails->user_proposal->user_product_journey_id)->first();

            $proposal_additional_details_data = json_decode($proposal->additional_details_data);
            $policy_start_date = $serviceResponse["data"]["policy"][0]["pol_start_date"] ?? "";
            $policy_end_date = date('Y-m-d', strtotime('+3 month -1 day', strtotime($policy_start_date)));

            if($proposal['business_type']== 'breakin' && $proposal['product_type']== 'comprehensive')
            {              
                $policy_start_date = $serviceResponse["data"]["policy"][0]["pol_start_date"] ?? "";
                $policy_end_date = date('Y-m-d', strtotime('+1 Year -1 day', strtotime($policy_start_date)));
            } 
            
            if (isset($serviceResponse['data']['policy']['payment_id'])) {
                $proposal_additional_details_data->tata_aig_v2->payment_id =  $serviceResponse['data']['policy']['payment_id'];
            } else {
                $proposal_additional_details_data->tata_aig_v2->payment_id =  $serviceResponse['data']['policy'][0]['payment_id'];
            }
            if ((strtotime($policy_start_date . "23:59:59")) < time() ) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Inception expired'
                ]);
            }
            UserProposal::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                ->update([
                    'is_inspection_done' => 'Y',
                    'additional_details_data' => json_encode($proposal_additional_details_data),
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                ]);

            $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                ->first();

            $status = true;
            $message = 'Your Vehicle Inspection is Done By TATA AIG.';
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
                    'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journey_stage->proposal_url)
                ]
            ]);
        } else {
            $update_data = [
                'breakin_response' => $serviceResponse,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $status = false;
            $message = 'Your Vehicle Inspection is Pending. Please try after some time';
            if ($serviceResponse['data']['inspection_status'] == 'Pending') {
                $message = 'Your Vehicle Inspection is Pending. Please try after some time';
            } else if ($serviceResponse['data']['inspection_status'] == 'Rejected') {
                $message = 'Your Vehicle Inspection is Rejected';
                updateJourneyStage([
                    'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                ]);

                $update_data = [
                    'breakin_response' => $serviceResponse,
                    'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                    'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }
            CvBreakinStatus::where('breakin_number', trim($breakinDetails->breakin_number))
            ->update($update_data);
        }
        return response()->json([
            'status' => $status,
            'msg'    => $message
        ]);
    }
}
