<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Mtownsend\XmlToArray\XmlToArray;

class FutureGeneraliPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Proposal submit',
            'Payment Proposal Generation',
            'Submit Proposal:CRT',
            'Generate Policy',
            getGenericMethodName('Proposal submit', 'proposal'),
            getGenericMethodName('Payment Proposal Generation', 'proposal'),
            getGenericMethodName('Submit Proposal:CRT', 'proposal'),
            getGenericMethodName('Generate Policy', 'proposal'),
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('request', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['future_generali'])
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
                $request = XmlToArray::convert((string)$log['request']);
                $request = $request['Body']['CreatePolicy']['XML'] ?? $request['Body']['CreateProposal']['XML'] ?? [];

                if (is_string($request)) {
                    $request = XmlToArray::convert((string) $request);
                }

                if (!empty($request['PolicyHeader']['PolicyStartDate'])) {

                    $startDate = str_replace('/', '-', $request['PolicyHeader']['PolicyStartDate']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $request['PolicyHeader']['PolicyEndDate']);
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
