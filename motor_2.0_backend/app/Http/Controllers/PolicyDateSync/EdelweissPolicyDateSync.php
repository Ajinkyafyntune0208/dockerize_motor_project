<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Http\Request;

class EdelweissPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Proposal Service',
            getGenericMethodName('Proposal Service', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('request', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['edelweiss'])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();

        $businessType = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->pluck('business_type')
        ->first();        
                
            $returnData = [
                'status' => false,
                'message' => 'Log Not found'
            ];
        foreach ($logs as $log) {
            try {
                $response  = json_decode($log['request'], true);
                if (!empty($response['policyStartDate'])) {

                    $response['policyStartDate'] = str_replace('/', '-', $response['policyStartDate']);
                    $response['policyStartDate'] = date('d-m-Y', strtotime($response['policyStartDate']));

                    $response['policyEndDay'] = str_replace('/', '-', $response['policyEndDay']);
                    $response['policyEndDay'] = date('d-m-Y', strtotime($response['policyEndDay']));

                    $tpEndDate = $response['policyEndDay'];

                    if ($data->policy_type != 'Short Term') {
                        if ($businessType == 'newbusiness') {
                            if (strtolower($data->policy_type) != 'third party') {
                                $response['policyEndDay'] = date('d-m-Y', strtotime($response['policyStartDate'] . ' +1 years -1 days'));
                            }
                        } else {
                            $response['policyEndDay'] = date('d-m-Y', strtotime($response['policyStartDate'] . ' +1 years -1 days'));
                        }
                    }

                    $returnData = [
                        'status' => true,
                        'data' => [
                            'start_date' => date('d-m-Y', strtotime($response['policyStartDate'])),
                            'end_date' => date('d-m-Y', strtotime($response['policyEndDay'])),

                            'tp_start_date' => date('d-m-Y', strtotime($response['policyStartDate'])),
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
