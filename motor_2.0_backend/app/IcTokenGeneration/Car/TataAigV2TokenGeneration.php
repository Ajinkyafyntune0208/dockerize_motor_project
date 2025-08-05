<?php
namespace App\IcTokenGeneration\Car;
use Illuminate\Http\Request;

class TataAigV2TokenGeneration{
    public function generateToken(Request $request, $requestData){
        try{
            $request_headers = $request->request_headers;
            $token_param = [
                'grant_type'    => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_GRANT_TYPE'),
                'scope'         => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_SCOPE'),
                'client_id'     => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_ID'),
                'client_secret' => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_SECRET')
            ];

            $request_headers = explode(PHP_EOL, $request_headers);
            $headers = [];
            foreach($request_headers as $line){
                list($key, $value) = explode(':', $line);
                $headers[$key] = $value;
            }

            $data = httpRequestNormal(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_TOKEN'), 'POST', $token_param, [], [], [], false, true);
            if(isset($data['response'])){
                $headers['Authorization'] = 'Bearer '.$data['response']['access_token'];

                $request_headers = '';
                foreach($headers as $key => $value){
                    $request_headers .=$key.":".$value.PHP_EOL;
                }

                return response()->json([
                    'status' => true,
                    'token_data' => $request_headers,
                    'message' => 'Token is Generated'
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
                'message' => 'An error occurred during token generation: '. $e->getMessage()
            ]);
        }
    }
}