<?php

namespace App\Http\Controllers\sbi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ixudra\Curl\Facades\Curl;

class SbiApiRequestController extends Controller
{
    // constant

    const TOKEN_URL = 'https://devapi.sbigeneral.in/cld/v1/token';
    const X_IBM_Client_Id = 'f7ddba7b-f392-4b0b-869e-5019dd98c620';
    const X_IBM_Client_Secret = 'mY5lU4mK4oG1dE7jO2qR1xA4mQ7sA2iC1sU4dJ3tP7dJ7cU0kF';
    const END_POINT_URL_CV_QUICK_QUOTE = 'https://devapi.sbigeneral.in/cld/v1/quickquote/CMVPC01';
    const END_POINT_URL_CV_FULL_QUOTE = 'https://devapi.sbigeneral.in/cld/v1/fullquote/CMVPC01';
    const END_POINT_URL_GCV_FULL_QUOTE = 'https://devapi.sbigeneral.in/cld/v1/fullquote/CMVGC01';

    public function tokenGeneration()
    {
        $curl = Curl::to(Self::TOKEN_URL)
                ->withHeader('Content-type: application/json')
                ->withHeader('X-IBM-Client-Id: '. Self::X_IBM_Client_Id)
                ->withHeader('X-IBM-Client-Secret: '. Self::X_IBM_Client_Secret);
        $curlResponse = $curl->get();

        return $curlResponse;

    }
    public function tokenGenerationMotor()
    {
        $curl = Curl::to('https://api.sbigeneral.in/cld/v1/token')
                ->withHeader('Content-type: application/json')
                ->withHeader('X-IBM-Client-Id: '. '2cb51666-1d44-491c-83e4-03e00650c477')
                ->withHeader('X-IBM-Client-Secret: '. 'J0bP1tP3gT1pI7wI5sP8kP3mX5rK4yG2pP3dJ5tR1xP6jG4aS5');
        $curlResponse = $curl->get();

        return $curlResponse;

    }

    public function fullQuote(Request $request)
    {
        $curl = Curl::to(Self::TOKEN_URL)
                ->withHeader('Content-type: application/json')
                ->withHeader('X-IBM-Client-Id: '. Self::X_IBM_Client_Id)
                ->withHeader('X-IBM-Client-Secret: '. Self::X_IBM_Client_Secret);
        $curlResponse = $curl->get();

        if(is_array(json_decode($curlResponse, true)))
        {
            $request = json_encode($request->all());
            $token = json_decode($curlResponse, true);

            $curl = Curl::to(Self::END_POINT_URL_CV_FULL_QUOTE)
            ->withHeader('Content-type: application/json')
            ->withHeader('X-IBM-Client-Id: '. Self::X_IBM_Client_Id)
            ->withHeader('X-IBM-Client-Secret: '. Self::X_IBM_Client_Secret)
            ->withHeader('Authorization: Bearer '. $token['access_token'])
            ->withHeader('Content-Length: '.strlen($request))
            ->withData($request);

            $curlResponse = $curl->post();

            Log::info('PCV Request =>'.$request);
            Log::info('PCV Response =>'.$curlResponse);
        }


        return $curlResponse;
    }

    public function gcvfullQuote(Request $request)
    {
        $curl = Curl::to(Self::TOKEN_URL)
                ->withHeader('Content-type: application/json')
                ->withHeader('X-IBM-Client-Id: '. Self::X_IBM_Client_Id)
                ->withHeader('X-IBM-Client-Secret: '. Self::X_IBM_Client_Secret);
        $curlResponse = $curl->get();

        if(is_array(json_decode($curlResponse, true)))
        {
            $request = json_encode($request->all());
            $token = json_decode($curlResponse, true);

            $curl = Curl::to(Self::END_POINT_URL_GCV_FULL_QUOTE)
            ->withHeader('Content-type: application/json')
            ->withHeader('X-IBM-Client-Id: '. Self::X_IBM_Client_Id)
            ->withHeader('X-IBM-Client-Secret: '. Self::X_IBM_Client_Secret)
            ->withHeader('Authorization: Bearer '. $token['access_token'])
            ->withHeader('Content-Length: '.strlen($request))
            ->withData($request);

            $curlResponse = $curl->post();

            Log::info('GCV Request =>'.$request);
            Log::info('GCV Response =>'.$curlResponse);

        }



        return $curlResponse;
    }

    public function policyGeneration($policy_number)
    {
        $curl = Curl::to(Self::TOKEN_URL)
        ->withHeader('Content-type: application/json')
        ->withHeader('X-IBM-Client-Id: '. Self::X_IBM_Client_Id)
        ->withHeader('X-IBM-Client-Secret: '. Self::X_IBM_Client_Secret);
        $curlResponse = $curl->get();

        if(is_array(json_decode($curlResponse, true)))
        {
            $token = json_decode($curlResponse, true);

            $curl = Curl::to('https://devapi.sbigeneral.in/customers/v1/policies/documents?policyNumber=' . $policy_number . '')
            ->withHeader('Content-type: application/json')
            ->withHeader('X-IBM-Client-Id: '. Self::X_IBM_Client_Id)
            ->withHeader('X-IBM-Client-Secret: '. Self::X_IBM_Client_Secret)
            ->withHeader('Authorization: Bearer '. $token['access_token']);

            $curlResponse = $curl->get();
        }

        return $curlResponse;

    }

    public function newPolicyGeneration($policy_number)
    {
        $curl = Curl::to('https://devapi.sbigeneral.in/v1/tokens')
        ->withHeader('Content-type: application/json')
        ->withHeader('X-IBM-Client-Id: '. '08e9c64bf82247c97639733335cae869')
        ->withHeader('X-IBM-Client-Secret: '. '96b28412afa9d441f981349a0f12539f');
        $curlResponse = $curl->get();

        if(is_array(json_decode($curlResponse, true)))
        {
            $requestData = [
                "RequestHeader" => [
                    "requestID" => mt_rand(100000, 999999),
                    "action" => "getPDF",
                    "channel" => "SBIG",
                    "transactionTimestamp" => date('d-M-Y-H:i:s')
                ],
                "RequestBody" => [
                    "PolicyNumber" => $policy_number,
                    "Regeneration" => "N",
                    "SourceSystem" => "Saral",
                    "IntermediateCode" => null,
                    "ProductName" => "",
                    "Offline" => "N"
                ]
            ];

            $cRequest = json_encode($requestData);

            $token = json_decode($curlResponse, true);

            $curl = Curl::to('https://devapi.sbigeneral.in/customers/v1/getpdf')
            ->withHeader('Content-type: application/json')
            ->withHeader('X-IBM-Client-Id: '. '08e9c64bf82247c97639733335cae869')
            ->withHeader('X-IBM-Client-Secret: '. '96b28412afa9d441f981349a0f12539f')
            ->withHeader('Authorization: Bearer '. $token['accessToken'])
            ->withData($cRequest);

            Log::info('Request =>'. $cRequest);
            $curlResponse = $curl->post();
            Log::info('Response =>'. $curlResponse);
        }

        return $curlResponse;
    }

}
