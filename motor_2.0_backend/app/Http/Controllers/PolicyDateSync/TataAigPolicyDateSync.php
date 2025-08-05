<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;

class TataAigPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Premium calculation - Proposal',
            getGenericMethodName('Premium calculation - Proposal', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['tata_aig_v2', 'tata_aig'])
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
                $response  = json_decode($log['response'], true);

                $response = $response['data'][0]['data'] ?? [];
                if (!empty($response['policy_start_date'] ?? $response['pol_start_date'])) {


                    $startDate = str_replace('/', '-', $response['policy_start_date'] ?? $response['pol_start_date']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $response['policy_end_date'] ?? $response['pol_end_date']);
                    $endDate = date('d-m-Y', strtotime($endDate));
                    
                    $tpEndDate = $endDate;

                    if ($data->policy_type != 'Short Term') {
                        if ($businessType == 'newbusiness') {
                            if (strtolower($data->policy_type) != 'third party') {
                                $endDate = date('d-m-Y', strtotime($startDate . ' +1 years -1 days'));
                            }
                        } else {
                            $endDate = date('d-m-Y', strtotime($startDate . ' +1 years -1 days'));
                        }
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
