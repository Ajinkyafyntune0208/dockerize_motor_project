<?php

namespace App\IcTokenGeneration\Bike;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KotakTokenGeneration
{
    public function generateToken(Request $request, $requestData)
    {
        try{
            $request_headers = $request->request_headers;
            $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');
            $tokenData = getKotakTokendetails('bike',$is_pos);
            $token_req_array = [
                'vLoginEmailId' => $tokenData['vLoginEmailId'],
                'vPassword' => $tokenData['vPassword'],
            ];

            $additionalData['headers'] = [
                'Content-Type' => 'application/json',
                'vRanKey' => $tokenData['vRanKey']
            ];

            $request_headers = explode(PHP_EOL, $request_headers);

            $headers = [];
            foreach ($request_headers as $line) {
                list($key, $value) = explode(':', $line);
                $headers[$key] = $value;
            }

            $data = httpRequestNormal(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE'), 'POST', $token_req_array, [], $additionalData['headers'], [], false, []);
            if(isset($data['response']['vTokenCode'])){
                $headers['vTokenCode'] = $data['response']['vTokenCode'];

                $request_headers = '';
                foreach($headers as $key => $value) {
                    $request_headers .=$key.":".$value.PHP_EOL;
                }

                return response()->json([
                    'status' => true,
                    'token_data' => $request_headers,
                    'message' =>'Token is Generated'
                ]);
            }else{
                return response()->json([
                    'status' => false,
                    'message' =>'Token Generation Issue'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' =>'An error occurred during token generation: ' . $e->getMessage()
            ]);
        }
    }
}
