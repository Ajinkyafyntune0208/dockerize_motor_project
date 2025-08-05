<?php

namespace App\IcTokenGeneration\Car;

use Illuminate\Http\Request;

class TataAigTokenGeneration
{
    public function generateToken(Request $request, $requestData, $cUrl = '')
    {
        $tokenUrl = config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_TOKEN');
        $tokenParam = [
            'grant_type'    => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_GRANT_TYPE'),
            'scope'         => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_SCOPE'),
            'client_id'     => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_SECRET'),
        ];

        $additionalData['headers'] = [
            'Accept' =>  'application/json'
        ];

        $data = httpRequestNormal($tokenUrl, 'POST', $tokenParam, [], $additionalData['headers'], [], false, true);

        if ($data && is_array($data) && isset($data['response'])) {
            if (isset($data['response']['access_token'])) {
                return response()->json([
                    'status' => true,
                    'token_data' => $data,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid token response",
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => "Invalid token response",
            ]);
        }
    }
}
