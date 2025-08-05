<?php

namespace App\IcTokenGeneration\Cv;

use Illuminate\Http\Request;

class ChollaMandalamTokenGeneration
{
    public function generateToken(Request $request, $requestData)
    {
        try{
            $request_headers = $request->request_headers;
            $token_param = [
                "grant_type"                => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_GRANT_TYPE'),
                "username"                  => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_USERNAME'),
                "password"                  => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_PASSWORD'),
            ];

            $additionalData['headers'] = [
                'Authorization' => 'Basic ' . config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_API_KEY')
            ];
            $request_headers = explode(PHP_EOL, $request_headers);

            $headers = [];
            foreach ($request_headers as $line) {
                list($key, $value) = explode(':', $line);
                $headers[$key] = $value;
            }

            $data = httpRequestNormal(config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_CV_TOKEN'), 'POST', $token_param, [], $additionalData['headers'], [], false, true, []);
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
