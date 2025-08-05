<?php

namespace App\Http\Controllers;

use App\Models\CrmDataPush;
use App\Models\CvJourneyStages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CrmDataPushController extends Controller
{
    public function crmDataProcess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required_if:type,push',
            'type' => 'required|in:push,process'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        if ($request->type == 'push') {
            return $this->push($request);
        }

        return $this->process();
    }

    public function push(Request $request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $userProductJourney = \App\Models\UserProductJourney::where('user_product_journey_id', $enquiryId)
            ->first();

        if (empty($userProductJourney)) {
            return response()->json([
                'status' => false,
                'message' => 'Enquiry Id not present'
            ], 400);
        }

        if (empty($userProductJourney->lead_id)) {
            return response()->json([
                'status' => false,
                'message' => 'Lead Id not present'
            ], 400);
        }

        $report = new ProposalReportController();
        $newRequest = new Request([
            'leadId' => $userProductJourney->lead_id,
            'user_product_journey_id' => $enquiryId
        ]);
        $report = $report->proposalReportsByLeadId($newRequest);
        if ($report instanceof \Illuminate\Http\JsonResponse) {
            $report = json_decode($report->getContent(), true);
        }

        $report = $report['data'] ?? [];

        if (empty($report)) {
            return response()->json([
                'status' => false,
                'message' => 'no data found'
            ]);
        }

        foreach($report  as $key => $value){
            if ($key != 'cover_amount') {
                $report[$key] = (string) $value;
            }
        }

        $stage = CvJourneyStages::where('user_product_journey_id', $enquiryId)->pluck('stage')->first();

        $statusData = [
            'enquiryId' =>  $userProductJourney->lead_id,
            'status' => $stage,
            'policyNumber' => '',
            'policyPDFURL' => ''
        ];

        $report = array_merge($statusData, $report);

        if(!empty($report['transaction_stage'])){
            $report['status'] = $report['transaction_stage'];
            unset($report['stage']);
        }


        CrmDataPush::updateOrCreate([
            'user_product_journey_id' => $enquiryId
        ], [
            'payload' => $report,
            'stage' => $stage,
            'enquiry_id' => $request->enquiryId,
            'lead_id' => $userProductJourney->lead_id
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Data pushed to data base successfully'
        ], 200);
    }

    public function process()
    {
        $data = CrmDataPush::pluck('payload', 'lead_id')->toArray();

        if (empty($data)) {
            return response()->json([
                'status' => false,
                'message' => 'no data to process'
            ], 400);
        }

        $folder = 'ace_crm_uploads';
        
        if (Storage::exists($folder)) {
            Storage::deleteDirectory($folder);
        }

        $fileName = 'ace-crm-data-'.time().'.json';

        $filePath = $folder.'/'.$fileName;

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));

        return response()->json([
            'status' => true,
            'message' => 'Json file read to download',
            'fileUrl' => file_url($filePath)
        ]);

    }

    public static function CrmDataPush($data)
    {
        try {
            httpRequest(
                'crm-lead-update-api',
                $data
            );
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }


}
