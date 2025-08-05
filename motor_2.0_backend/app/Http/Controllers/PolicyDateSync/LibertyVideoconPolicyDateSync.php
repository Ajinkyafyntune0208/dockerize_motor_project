<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;

class LibertyVideoconPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Proposal Submission',
            getGenericMethodName('Proposal Submission', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['liberty_videocon'])
            ->whereIn('method_name', $methodList)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();


        $returnData = [
            'status' => false,
            'message' => 'Log Not found'
        ];

        $businessType = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->pluck('business_type')
        ->first();
        
        foreach ($logs as $log) {
            try {
                $response = json_decode($log['response'], true);
               
                if (!empty($response['PolicyStartDate'])) {

                    $startDate = str_replace('/', '-', $response['PolicyStartDate']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $response['PolicyEndDate']);
                    $endDate = date('d-m-Y', strtotime($endDate));
                    
                    $tpEndDate = $endDate;

                    if ($businessType == 'newbusiness' && in_array(strtolower($data->section_code), [
                        'car',
                        'bike'
                    ])) {
                        $period = strtolower($data->section_code) == 'car' ? 3 : 5;
                        $tpEndDate = date('d-m-Y', strtotime($startDate. " +{$period} years -1 days"));
                    }

                    $returnData = [
                        'status' => true,
                        'data' => [
                            'start_date' => date('d-m-Y', strtotime($startDate)),
                            'end_date' => date('d-m-Y', strtotime($endDate)),
                            
                            'tp_start_date' => date('d-m-Y', strtotime($startDate)),
                            'tp_end_date' => date('d-m-Y', strtotime($tpEndDate)),
                        ]
                    ];
                    break;
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        }
        return $returnData;
    }
}
