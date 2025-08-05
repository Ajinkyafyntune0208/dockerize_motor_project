<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Mtownsend\XmlToArray\XmlToArray;

class UniversalSompoPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Proposal Generation',
            getGenericMethodName('Proposal Generation', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('response', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['universal_sompo'])
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
                $response = XmlToArray::convert((string)$log['response']);
                $response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'] ?? [];

                if (is_string($response)) {
                    $response = XmlToArray::convert((string) $response);
                }
                $response = $response['Product']['GeneralProposal']['GeneralProposalGroup']['GeneralProposalInformation']['PolicyEffectiveDate'] ?? [];
                
                if (!empty($response['Fromdate']['@attributes']['Value'])) {

                    $startDate = str_replace('/', '-', $response['Fromdate']['@attributes']['Value']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $response['Todate']['@attributes']['Value']);
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
