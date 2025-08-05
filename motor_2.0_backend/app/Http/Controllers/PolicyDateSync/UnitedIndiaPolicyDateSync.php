<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;


class UnitedIndiaPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Premium Calculation',
            getGenericMethodName('Premium Calculation', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('request', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['united_india'])
                ->whereIn('method_name', $methodList)
                ->orderBy('id', 'desc')
                ->get();

        $businessType = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->pluck('business_type')
        ->first();

            $returnData = [
                'status' => false,
                'message' => 'Log Not found'
            ];
        foreach ($logs as $log) {
            try {
                $request  = XmlToArray::convert((string) $log->request);

                $request = $request['soapenv:Body']['ws:calculatePremium']['proposalXml'] ?? [];

                if (!is_array($request)) {
                    $request = XmlToArray::convert($request);
                }

                $requestData = $request['HEADER'];
                $startDate = $requestData['DAT_DATE_OF_ISSUE_OF_POLICY'];
                $endDate = $requestData['DAT_DATE_OF_EXPIRY_OF_POLICY'];

                if (!empty($startDate) && !empty($endDate)) {
                    $startDate = str_replace('/', '-', $startDate);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $endDate);
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
