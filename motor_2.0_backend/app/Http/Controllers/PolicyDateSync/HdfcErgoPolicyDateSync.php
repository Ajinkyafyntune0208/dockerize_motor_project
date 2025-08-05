<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;

class HdfcErgoPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Policy Generation',
            getGenericMethodName('Policy Generation', 'proposal')
        ];
        

        $logs = \App\Models\WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->where(function ($query) use ($methodList) {
                foreach (array_unique($methodList) as $method) {
                    $query->orWhere('method_name', 'like', '%' . $method . '%');
                }
            })
            ->whereIn('company', ['hdfc_ergo'])
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
                if (($response['StatusCode'] ?? '') == 200 && !empty($response['Policy_Details']['PolicyStartDate'])) {

                    $startDate = str_replace('/', '-', $response['Policy_Details']['PolicyStartDate']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $response['Policy_Details']['PolicyEndDate']);
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
