<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class IffcoTokioPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Proposal submission - Proposal',
            'Premium Calculation',
            getGenericMethodName('Proposal submission - Proposal', 'proposal'),
            getGenericMethodName('Premium Calculation', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('request', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['iffco_tokio'])
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
                $response = $log['request'];
                try {
                    $response = XmlToArray::convert((string) $response);
                } catch (\Throwable $th) {
                    $response = json_decode($response, true);
                }
                if (!empty($response['commercialVehicle']['inceptionDate'])) {

                    $startDate = date('d-m-Y', strtotime(str_replace('/', '-', $response['commercialVehicle']['inceptionDate'])));
                    $endDate = date('d-m-Y', strtotime(str_replace('/', '-', $response['commercialVehicle']['expirationDate'])));
                    
                    $returnData = [
                        'status' => true,
                        'data' => [
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'tp_start_date' => $startDate,
                            'tp_end_date' => $endDate
                        ]
                    ];
                    break;
                }elseif(!empty($response['soapenv:Body']['prem:getNewVehiclePremium']['policy']['inceptionDate'])){
                    $startDate = str_replace('/', '-', $response['soapenv:Body']['prem:getNewVehiclePremium']['policy']['inceptionDate']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $response['soapenv:Body']['prem:getNewVehiclePremium']['policy']['expiryDate']);
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
