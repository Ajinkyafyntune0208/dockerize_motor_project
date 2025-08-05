<?php

namespace App\Http\Controllers\PolicyDateSync;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Http\Request;
use Mtownsend\XmlToArray\XmlToArray;

class OrientalPolicyDateSync extends Controller
{
    public function syncDetails($enquiryId, $data)
    {
        $methodList = [
            'Proposal Submit',
            'Premium Calculation',
            getGenericMethodName('Proposal Submit', 'proposal'),
            getGenericMethodName('Premium Calculation', 'proposal')
        ];

        $logs = \App\Models\WebServiceRequestResponse::select('request', 'enquiry_id', 'id')
            ->where([
                'enquiry_id' => $enquiryId
            ])
            ->whereIn('company', ['oriental'])
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
                $response = XmlToArray::convert((string) $response);
                if (!empty($response['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['START_DATE'])) {
                    $startDate = str_replace('/', '-', $response['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['START_DATE']);
                    $startDate = date('d-m-Y', strtotime($startDate));

                    $endDate = str_replace('/', '-', $response['soap:Body']['GetQuoteMotor']['objGetQuoteMotorETT']['END_DATE']);
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
