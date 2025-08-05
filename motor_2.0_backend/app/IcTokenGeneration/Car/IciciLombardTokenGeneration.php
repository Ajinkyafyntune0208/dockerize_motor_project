<?php

namespace App\IcTokenGeneration\Car;

use Illuminate\Http\Request;

class IciciLombardTokenGeneration
{
    public function generateToken(Request $request, $requestData, $cUrl = '')
    {
        try{
        $tokenUrl = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL');
        $request_headers = $request->request_headers;
        if (empty($tokenUrl)) {
            return response()->json([
                'status' => false,
                'message' => 'Token URL is not configured.',
            ]);
        }

        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
            'scope' => 'esbmotor',
        ];

        $request_headers = explode(PHP_EOL, $request_headers);
        $headers = [];
        foreach ($request_headers as $line) {
            list($key, $value) = explode(':', $line);
            $headers[$key] = $value;
        }

        $additionalData['headers'] = [
            'Accept' => 'application/json',
        ];

        $data = httpRequestNormal($tokenUrl, 'POST', $tokenParam, [], $additionalData['headers'], [], false, true);

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
