<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\PolicyDetails;
use Illuminate\Http\Request;

class PolicyReportController extends Controller
{

    public function getIncorrectPolicyDetails(Request $request)
    {
        $details = PolicyDetails::select('policy_number', 'user_product_journey_id', 'pdf_url', 'created_on', 'js.stage')
        ->join('cv_journey_stages as js', 'js.proposal_id', 'policy_details.proposal_id')
        ->when((isset($request->from) && isset($request->to)), function($query) {
            $query->whereBetween('created_on', [date('Y-m-d 00:00:00', strtotime(request()->from)), date('Y-m-d 00:00:00', strtotime(request()->to))]);
        })
        ->whereNotNull('policy_number')
        ->where('policy_number', '<>', '')
        ->whereNotIn('stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])
        ->limit($request->per_page ?? 2)
        ->get()->toArray();

        $newData = [];
        foreach($details as $detail) {
            $detail['enquiry_id'] = ($request->type == 'csv' ? '"' : '') . customEncrypt($detail['user_product_journey_id']);
            $detail['policy_number'] = ($request->type == 'csv' ? '"' : '') . $detail['policy_number'];
            unset($detail['user_product_journey_id']);
            $newData[] = $detail;
        }

        if($request->debug == 'Y') {
            $newData = array_merge($newData, \Illuminate\Support\Facades\DB::getQueryLog());
        }

        if($request->type == 'csv') {
            
            $fields[0] = array_keys($newData[0]);
            $fields = array_merge($fields, $newData);


            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($fields), now()->format('Y-m-d h:i:s') . ' Report.xls');
        }
        header("Content-Type: application/json");
        echo json_encode($newData);die();
        // return response()->json(array_values($data));
    }
}
