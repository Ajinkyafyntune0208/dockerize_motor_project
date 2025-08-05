<?php

namespace App\Http\Controllers;

use App\Models\CvJourneyStages;
use App\Models\PolicyDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DuplicateDataChangeController extends Controller
{


    function updateStageAndDeleteDuplicates(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'policy_no' => ['required', 'not_in:"null"']
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => $validator->errors(),
                ], 400);
            }

            $policyNumber = $request->policy_no;
            $stage = "Policy Issued";

            $sql = "
                SELECT id FROM (
                    SELECT b.id, ROW_NUMBER() OVER (PARTITION BY a.policy_number ORDER BY b.id) AS rn
                    FROM policy_details AS a
                    INNER JOIN cv_journey_stages AS b ON a.proposal_id = b.proposal_id
                    WHERE a.policy_number = ? AND b.stage = ?
                ) AS RankedRecords WHERE rn > 1
            ";

            $duplicateIds = DB::select($sql, [$policyNumber, $stage]);

            if (empty($duplicateIds)) {
                return response()->json([
                    "status" => false,
                    "message" => "No duplicate records found to update or delete."
                ], 200);
            }

            $ids = array_column($duplicateIds, 'id');

            CvJourneyStages::whereIn('id', $ids)
                ->update(['stage' => 'Lead Generation']);

            $enquiry_id = CvJourneyStages::whereIn('id', $ids)->get()->pluck('user_product_journey_id');
            foreach ($enquiry_id as $key => $value) {

                \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                    'enquiryId' => $enquiry_id,
                ]), $enquiry_id, false);
            }
            PolicyDetails::whereIn('proposal_id', function ($query) use ($ids) {
                $query->select('proposal_id')
                    ->from('cv_journey_stages')
                    ->whereIn('id', $ids);
            })->delete();

            return response()->json([
                "status" => true,
                "message" => count($ids) . " records updated in cv_journey_stages and deleted from policy_details."
            ]);
        } catch (Exception $e) {
            Log::error("Error in updateStageAndDeleteDuplicates: " . $e->getMessage(), [
                'request' => $request->all()
            ]);

            return response()->json([
                "status" => false,
                "message" => "An error occurred while updating and deleting records. Please try again.",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
