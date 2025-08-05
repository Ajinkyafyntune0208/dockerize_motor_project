<?php

namespace App\Http\Controllers\QuotationProcess;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\CommonController;
use App\Models\QuoteStartProcess;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

// ini_set('memory_limit', '-1');
// ini_set('max_execution_time', 300);

class QuoteService extends Controller
{
    public function getProductCount(Request $request)
    {
        $common = new CommonController;
        $response = $common->getProductDetails($request);
        $response_content = json_decode($response->getContent(),true)['data'];
        $product_count = count($response_content['comprehensive']) + count($response_content['third_party']) + count($response_content['short_term']);
        if(is_numeric($request->enquiryId) && strlen($request->enquiryId) == 16)
        {
            $enquiryId = Str::substr($request->enquiryId, 8);
        }
        else
        {
            $enquiryId = customDecrypt($request->enquiryId,true);
        }

        $insert_data = [
            'enquiry_id'        => $enquiryId,
            'request_payload'   => json_encode($request->all()),
            'response'          => $response->getContent(),
            'section'           => $request->productSubTypeId
        ];
        $data = QuoteStartProcess::create($insert_data);
        // echo url('api/startProcess?enquiryId=').$request->enquiryId;
        // die;
        $short_term_quote = url('api/startProcess?enquiryId=').$request->enquiryId.'&quote_type=short_term';
        $basic_quote = url('api/startProcess?enquiryId=').$request->enquiryId.'&quote_type=basic';
        $zd_quote = url('api/startProcess?enquiryId=').$request->enquiryId.'&quote_type=zd';
        $tp_quote = url('api/startProcess?enquiryId=').$request->enquiryId.'&quote_type=tp';        
        async_http_post($short_term_quote);
        async_http_post($basic_quote);
        async_http_post($zd_quote);
        async_http_post($tp_quote);
        return response()->json([
            "status" => $product_count > 0 ? true : false,
            "data" => [
                "totalCount" => $product_count
            ]
        ]);
    }

    public function startProcess(Request $request)
    {
        if(is_numeric($request->enquiryId) && strlen($request->enquiryId) == 16)
        {
            $enquiryId = Str::substr($request->enquiryId, 8);
        }
        else
        {
            $enquiryId = customDecrypt($request->enquiryId,true);
        }

        $quote_type = $request->quote_type;
        $QuoteStartProcess = QuoteStartProcess::where('enquiry_id', $enquiryId)->latest()->first();
        if($QuoteStartProcess->section == 1)
        {
            $base_url = url('api/car/premiumCalculation').'/';
        }
        else if ($QuoteStartProcess->section == 2)
        {
            $base_url = url('api/bike/premiumCalculation').'/';
        }
        else
        {
            $base_url = url('api/premiumCalculation').'/';
        }
        $pool_data = [];

        $quote_policy_data = json_decode($QuoteStartProcess->response,true)['data'];
        $request_enquiryId = $request->enquiryId;
        // $responses = Http::pool(function (Pool $pool) use ($quote_policy_data, $base_url,$request_enquiryId,$quote_type) 
        // {
        //     $requests = [];
        //     if($quote_type == 'short_term')
        //     {
        //         foreach($quote_policy_data['short_term'] as $key => $quote)
        //         {
        //             $url = $base_url.$quote['companyAlias'];
        //             $payload_request = [
        //                 "enquiryId" => $request_enquiryId,
        //                 "policyId"  => $quote['policyId']
        //             ];
        //             $requests[] = $pool->post($url, $payload_request);
        //         }
        //     }

        //     if($quote_type == 'basic' || $quote_type == 'zd')
        //     {
        //         foreach($quote_policy_data['comprehensive'] as $key => $quote)
        //         {
        //             if($quote_type == 'zd' && $quote['zeroDep'] == 0)
        //             {
        //                 $url = $base_url.$quote['companyAlias'];
        //                 $payload_request = [
        //                     "enquiryId" => $request_enquiryId,
        //                     "policyId"  => $quote['policyId']
        //                 ];
        //                 $requests[] = $pool->post($url, $payload_request);
                        
        //             }
        //             else if( $quote_type == 'basic' && $quote['zeroDep'] == 1)
        //             {
        //                 $url = $base_url.$quote['companyAlias'];
        //                 $payload_request = [
        //                     "enquiryId" => $request_enquiryId,
        //                     "policyId"  => $quote['policyId']
        //                 ];
        //                 $requests[] = $pool->post($url, $payload_request);
        //             }                    
        //         }
        //     }
            
        //     if($quote_type == 'tp')
        //     {
        //         foreach($quote_policy_data['third_party'] as $key => $quote)
        //         {
        //             $url = $base_url.$quote['companyAlias'];
        //             $payload_request = [
        //                 "enquiryId" => $request_enquiryId,
        //                 "policyId"  => $quote['policyId']
        //             ];
        //             $requests[] = $pool->post($url, $payload_request);
        //         }
        //     }  
        //     return $requests;
        // });

        // $responses = Http::pool(function (Pool $pool) use ($quote_policy_data, $base_url, $request_enquiryId, $quote_type) 
        // {    
        //     // Handling short_term quotes
        //     if ($quote_type === 'short_term') 
        //     {
        //         foreach ($quote_policy_data['short_term'] as $quote) 
        //         {
        //             $pool->post($base_url . $quote['companyAlias'], 
        //             [
        //                 'enquiryId' => $request_enquiryId,
        //                 'policyId' => $quote['policyId']
        //             ]);
        //         }
        //     }
        
        //     // Handling basic and zd quotes
        //     if (in_array($quote_type, ['basic', 'zd'])) 
        //     {
        //         foreach ($quote_policy_data['comprehensive'] as $quote) 
        //         {
        //             if ($quote_type === 'zd' && $quote['zeroDep'] == 0) 
        //             {
        //                 $pool->post($base_url . $quote['companyAlias'], 
        //                 [
        //                     'enquiryId' => $request_enquiryId,
        //                     'policyId' => $quote['policyId']
        //                 ]);
        //             }
        //             else if ($quote_type === 'basic' && $quote['zeroDep'] == 1) 
        //             {
        //                 $pool->post($base_url . $quote['companyAlias'], 
        //                 [
        //                     'enquiryId' => $request_enquiryId,
        //                     'policyId' => $quote['policyId']                    
        //                 ]);
        //             }
        //         }
        //     }
        
        //     // Handling third party (tp) quotes
        //     if ($quote_type === 'tp') 
        //     {
        //         foreach ($quote_policy_data['third_party'] as $quote) 
        //         {
        //             $pool->post($base_url . $quote['companyAlias'], 
        //             [
        //                 'enquiryId' => $request_enquiryId,
        //                 'policyId' => $quote['policyId']                        
        //             ]);
        //         }
        //     }
        // });    
        $requests = [];
        // Handling short_term quotes
        if ($quote_type === 'short_term') 
        {
            foreach ($quote_policy_data['short_term'] as $quote) 
            {
                $requests[] = [
                    'url' => $base_url . $quote['companyAlias'], 
                    'data' => [
                    'enquiryId' => $request_enquiryId,
                    'policyId' => $quote['policyId']
                ]];
            }
        }
    
        // Handling basic and zd quotes
        if (in_array($quote_type, ['basic', 'zd'])) 
        {
            foreach ($quote_policy_data['comprehensive'] as $quote) 
            {
                if ($quote_type === 'zd' && $quote['zeroDep'] == 0) 
                {
                    $requests[] = [
                        'url' => $base_url . $quote['companyAlias'], 
                        'data' => [
                        'enquiryId' => $request_enquiryId,
                        'policyId' => $quote['policyId']
                    ]];
                }
                else if ($quote_type === 'basic' && $quote['zeroDep'] == 1) 
                {
                    $requests[] = [
                        'url' => $base_url . $quote['companyAlias'], 
                        'data' => [
                        'enquiryId' => $request_enquiryId,
                        'policyId' => $quote['policyId']
                    ]];
                }
            }
        }
    
        // Handling third party (tp) quotes
        if ($quote_type === 'tp') 
        {
            foreach ($quote_policy_data['third_party'] as $quote) 
            {
                $requests[] = [
                    'url' => $base_url . $quote['companyAlias'], 
                    'data' => [
                    'enquiryId' => $request_enquiryId,
                    'policyId' => $quote['policyId']
                ]];
            }
        }

        $responses = Http::pool(function (Pool $pool) use ($requests) 
        {
			$poolRequests = [];
			foreach ($requests as $request) 
            {
				$poolRequests[] = $pool->post($request['url'], $request['data']);
			}
			return $poolRequests;
		});
    }
}
