<?php
namespace App\IcTokenGeneration\Car;
use Illuminate\Http\Request;

class GodigitTokenGeneration{
    public function generateToken(Request $request, $requestData){
        try{
            $request_headers = $request->request_headers;
            $token_param  = [
                "username" => config('constants.IcConstants.godigit.oneapi.ONEAPI_WEB_USER_ID'),
                "password" => config('constants.IcConstants.godigit.oneapi.ONEAPI_PASSWORD')
            ];

            $webUserId = config('constants.IcConstants.godigit.oneapi.ONEAPI_WEB_USER_ID');
            $password  = config('constants.IcConstants.godigit.oneapi.ONEAPI_PASSWORD'); 

            $additionalData['headers'] = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$webUserId:$password"),
                'Accept' => 'application/json'
            ];

            $request_headers = explode(PHP_EOL, $request_headers);

            $headers = [];
            foreach($request_headers as $line){
                list($key, $value) = explode(':', $line);
                $headers[$key] = $value;
            }

            $data = httpRequestNormal(config('constants.IcConstants.oneapi.ONEAPI_BIKE_MOTOR_TOKEN_GENERATION_URL'), 'POST', $token_param, [], $additionalData['headers'], [], false, []);

            if(isset($data['response']['access_token'])){
                $headers['Authorization'] = 'Bearer '.$data['response']['access_token'];

                $request_headers = '';
                foreach($headers as $key=> $value){
                    $request_headers .=$key.":".$value.PHP_EOL;
                }

                return response()->json([
                    'status' => true,
                    'token_data' => $request_headers,
                    'message'=> 'Token is Generated'
                ]);
            }else{
                return response()->json([
                    'status' => false,
                    'message' => 'Token Generation Issue'
                ]);
            }
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'An error occurred during token generation: '.$e->getMessage()
            ]);
        }       
    }
}