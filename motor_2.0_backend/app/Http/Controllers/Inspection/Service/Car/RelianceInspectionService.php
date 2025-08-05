<?php

namespace App\Http\Controllers\Inspection\Service\Car;
use Illuminate\Support\Facades\DB;
use App\Models\CvBreakinStatus;
use App\Models\UserProposal;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class RelianceInspectionService
{
    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::where('breakin_number', trim($request->inspectionNo))->first();
        if (empty($breakinDetails)) {
            return response()->json([
                'status' => true,
                'msg' => 'Please check your inspection number'
            ]);
        }

        $userProposal = $breakinDetails->user_proposal;
        $quoteLog = $userProposal->quote_log;
        $journeyStage = $userProposal->user_product_journey->journey_stage;
        $policyDetails = $userProposal->policy_details;
        $productData = getProductDataByIc($quoteLog->master_policy_id);

        if (!empty($policyDetails)) {
            return response()->json([
                'status' => false,
                'message' => 'Policy has already been generated for this inspection number'
            ]);
        }
        elseif ($breakinDetails->breakin_status == STAGE_NAMES['INSPECTION_APPROVED']) {
            $policyStartDate = date('d-m-Y', time());
            $policyEndDate = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                
            UserProposal::where('user_product_journey_id', $userProposal->user_product_journey_id)
            ->update([
                    'is_inspection_done' => 'Y',
                    'policy_start_date' => $policyStartDate,
                    'policy_end_date' => $policyEndDate
                ]);
                
            return response()->json([
                'status' => true,
                'msg' => 'Your Vehicle Inspection is Done By Reliance.',
                'data'   => [
                    'enquiryId' => customEncrypt($userProposal->user_product_journey_id),
                    'proposalNo' => $userProposal->user_proposal_id,
                    'totalPayableAmount' => $userProposal->final_payable_amount,
                    'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journeyStage->proposal_url)
                ]
            ]);
        }

        $inspectionResult = json_decode($breakinDetails->breakin_response, TRUE);

        if (($inspectionResult['InspectionStatus'] ?? null) != 'Recommended') {
            $vehicaleRegistrationNumber = $userProposal->vehicale_registration_number;
            $corporateVehiclesQuotesRequest = $userProposal->corporate_vehicles_quotes_request;

            $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
                $vehicaleRegistrationNumber,
                $corporateVehiclesQuotesRequest->rto_code,
                $corporateVehiclesQuotesRequest->business_type == 'newbusiness'
            );

            if ($rcDetails['status']) {
                $vehicaleRegistrationNumber = $rcDetails['rcNumber'];
            }

            $leadDetails = [
                'LeadID' => $breakinDetails->breakin_number,
                'AgencyCode' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_AGENCY_CODE'),
                'VehicleRegNumber' => str_replace('-', '', $vehicaleRegistrationNumber)
            ];

            $get_response = getWsData(
                config('constants.IcConstants.reliance.RELIANCE_MOTOR_CAR_FETCH_LEAD_DETAILS_URL'),
                json_encode($leadDetails),
                'reliance',
                [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Lead Fetch Details',
                    'requestMethod' => 'post',
                    'enquiryId' => $userProposal->user_product_journey_id,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'Content-type' => 'application/json',
                        'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                    ]
                ]
            );
            $inspectionResult = $get_response['response'];

            CvBreakinStatus::where('breakin_number', $request->inspectionNo)
            ->update([
                'breakin_response' => $inspectionResult
            ]);
            
            $inspectionResult = json_decode($inspectionResult, true);
            
            if (($inspectionResult['InspectionStatus'] ?? null) == 'Recommended') {

                $policyStartDate = date('d-m-Y', time());
                $policyEndDate = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                
                UserProposal::where('user_product_journey_id', $userProposal->user_product_journey_id)
                ->update([
                    'is_inspection_done' => 'Y',
                    'policy_start_date' => $policyStartDate,
                    'policy_end_date' => $policyEndDate
                ]);

                CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                ->update([
                    'inspection_date' => date('Y-m-d'),
                    'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                    'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                    'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 2 day')),
                ]);

                updateJourneyStage([
                    'user_product_journey_id' => $userProposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                ]);

                return response()->json([
                    'status' => true,
                    'msg' => 'Your Vehicle Inspection is Done By Reliance.',
                    'data'   => [
                        'enquiryId' => customEncrypt($userProposal->user_product_journey_id),
                        'proposalNo' => $userProposal->user_proposal_id,                    
                        'totalPayableAmount' => $userProposal->final_payable_amount,
                        'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journeyStage->proposal_url)
                    ]
                ]);
            } else {
                $message = 'Your Inspection has not been recommended yet';
                $message = !empty($inspectionResult['ErrorMessage'] ?? null) ? $inspectionResult['ErrorMessage'] : (!empty($inspectionResult['Remark']) ? ($message . '. '.$inspectionResult['Remark']) : 'Your Inspection has not been recommended yet');
                
                if (($inspectionResult['InspectionStatus'] ?? null) == 'Not Recommended') {
                    updateJourneyStage([
                        'user_product_journey_id' => $userProposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_REJECTED'],
                    ]);
                    $message = 'Your Vehicle Inspection has been rejected,Reason : ' . $inspectionResult['Remark'] ?? '';
                } elseif (($inspectionResult['InspectionStatus'] ?? null) == 'Inspection Required') {
                    updateJourneyStage([
                        'user_product_journey_id' => $userProposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                    ]);

                    $message = 'Inspection Required : ' . $inspectionResult['Remark'] ?? '';
                } elseif (($inspectionResult['InspectionStatus'] ?? null) == 'Closed') {
                    updateJourneyStage([
                        'user_product_journey_id' => $userProposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_REJECTED'],
                    ]);

                    $message = 'Inspection has not completed in 48 hours and lead get closed : ' . $inspectionResult['Remark'] ?? '';
                }
                return response()->json([
                    'status' => false,
                    'message' => $message
                ]);
            }
        }

        if (($inspectionResult['InspectionStatus'] ?? null) == 'Recommended' && ($journeyStage->stage == STAGE_NAMES['INSPECTION_ACCEPTED'])) {
            $policyStartDate = date('d-m-Y', time());
            $policyEndDate = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                
            UserProposal::where('user_product_journey_id', $userProposal->user_product_journey_id)
            ->update([
                    'is_inspection_done' => 'Y',
                    'policy_start_date' => $policyStartDate,
                    'policy_end_date' => $policyEndDate
                ]);
                
            return response()->json([
                'status' => true,
                'msg' => 'Your Vehicle Inspection is Done By Reliance.',
                'data'   => [
                    'enquiryId' => customEncrypt($userProposal->user_product_journey_id),
                    'proposalNo' => $userProposal->user_proposal_id,
                    'totalPayableAmount' => $userProposal->final_payable_amount,
                    'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journeyStage->proposal_url)
                ]
            ]);

        } else {

            $message = !empty($inspectionResult['ErrorMessage'] ?? null) ? $inspectionResult['ErrorMessage'] : 'Your Inspection has not been recommended yet';

            return response()->json([
                'status' => false,
                'message' => $message
            ]);
        }
    }
}