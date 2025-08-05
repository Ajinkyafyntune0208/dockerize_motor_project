<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Mtownsend\XmlToArray\XmlToArray;

class ShriramPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Proposal Submit',
            getGenericMethodName('Proposal Submit', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('request', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['shriram'])
            ->whereIn('method_name', array_unique($methodList))
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
                $request = json_decode($log['request'], true);

                if (empty($request)) {
                    $request = XmlToArray::convert((string) $log['request']);
                    $request = $request['soap:Body']['GenerateProposal'] ?? $request['soap:Body']['GenerateLTTwoWheelerProposal'] ?? [];
                }

                $request = $request['objPolicyEntryETT'] ?? $request['objGCCVProposalEntryETT'] ?? [];

                if (!empty($request['PolicyFromDt'])) {

                    $startDate = str_replace('/', '-', $request['PolicyFromDt']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $request['PolicyToDt']);
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
