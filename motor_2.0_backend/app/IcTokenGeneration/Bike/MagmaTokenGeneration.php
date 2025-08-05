<?php

namespace App\IcTokenGeneration\Bike;

use Illuminate\Http\Request;

class MagmaTokenGeneration
{
    public function generateToken(Request $request, $requestData)
    {
        try{
            $request_headers = $request->request_headers;
            $tokenParam = [
                'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
                'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
                'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
                'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
            ];

            $additionalData['headers'] = [
                'Content-type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0'
            ];

            $request_headers = explode(PHP_EOL, $request_headers);

            $headers = [];
            foreach ($request_headers as $line) {
                list($key, $value) = explode(':', $line);
                $headers[$key] = $value;
            }

            $data = httpRequestNormal(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), 'POST', $tokenParam, [], $additionalData['headers'], [], false, true, []);
            if(isset($data['response']['access_token'])){
                $headers['Authorization'] = "Bearer ".$data['response']['access_token'];

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
