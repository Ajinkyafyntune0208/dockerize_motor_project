<?php
namespace App\IcTokenGeneration\Car;
use Illuminate\Http\Request;

class SbiTokenGeneration
{
    public function generateToken(Request $request, $requestData)
    {
        try{
            $request_headers = $request->request_headers;

            $additionalData['headers'] = [
                'Content-Type' => 'application/json',
                'X-IBM-Client-Id' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID'),
                'X-IBM-Client-Secret' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET'),
                'Accept'  => 'application/json'
            ];

            $request_headers = explode(PHP_EOL, $request_headers);
            $headers = [];
            foreach($request_headers as $line){
                list($key, $value) = explode(':', $line);
                $headers[$key] = $value;
            }

            $data = httpRequestNormal(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), 'GET', [], [], $additionalData['headers'], [],false, []);
            if(isset($data['response']['access_token'])){
                $headers['Authorization'] = "Bearer ".$data['response']['access_token'];

                $request_headers = '';
                foreach($headers as $key => $value){
                    $request_headers .=$key.":".$value.PHP_EOL;
                }

                return response()->json([
                    'status' => true,
                    'token_data' => $request_headers,
                    'message' =>'Token is Generated'
                ]);
            }else{
                return response([
                    'status' => false,
                    'message' => 'Token Generation Issue'
                ]);

            }

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'An error occurred during token generation: ' . $e->getMessage()
            ]);
        }
    }
}