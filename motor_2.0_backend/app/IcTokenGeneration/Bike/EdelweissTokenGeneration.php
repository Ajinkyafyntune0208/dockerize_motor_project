<?php

namespace App\IcTokenGeneration\Bike;

use Illuminate\Http\Request;

class EdelweissTokenGeneration
{
    public function generateToken(Request $request, $requestData)
    {
        try{
            $request_headers = $request->request_headers;

            $webUserId = config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME');
            $password  = config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD');

            $additionalData['headers'] = [
                'webUserId' => $webUserId,
                'password'  => $password,
                'Content-type'  => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic '.base64_encode("$webUserId:$password")
            ];

            $request_headers = explode(PHP_EOL, $request_headers);

            $headers = [];
            foreach ($request_headers as $line) {
                list($key, $value) = explode(':', $line);
                $headers[$key] = $value;
            }

            $data = httpRequestNormal(config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION'), 'POST', [], [], $additionalData['headers'], [], false, []);
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
