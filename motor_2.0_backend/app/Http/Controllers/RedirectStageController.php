<?php

namespace App\Http\Controllers;

use App\Models\CvJourneyStages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RedirectStageController extends Controller
{
    function redirect(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'enquiry_id' => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validation->errors(),
            ], 422);
        }
        $enquiry_id = is_numeric($request->enquiry_id) && strlen($request->enquiry_id) == 16 ? Str::substr($request->enquiry_id, 8): customDecrypt($request->enquiry_id);
        $cv_journey_stage = CvJourneyStages::where('user_product_journey_id', $enquiry_id)->first();
        if (in_array($cv_journey_stage['stage'], [ STAGE_NAMES["LEAD_GENERATION"], STAGE_NAMES["QUOTE"] ] ) ) {
            $url = $cv_journey_stage['quote_url'];
        } else {
            $url = $cv_journey_stage['proposal_url'];
        }
        if (empty($url)) {
            return response()->json([
                'status' => false,
                'data' => [
                    'status' => false,
                    'stage' => $cv_journey_stage['stage'] ?? '',
                    'urlLink' => "URL Not Found"
                ]
            ]);
        } else {

            return response()->json([
                'status' => true,
                'data' => [
                    'status' => true,
                    'stage' => $cv_journey_stage['stage'] ?? '',
                    'urlLink' => $url
                ]
            ]);
        }
    }
}
