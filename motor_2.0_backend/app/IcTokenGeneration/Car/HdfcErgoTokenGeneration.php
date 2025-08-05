<?php

namespace App\IcTokenGeneration\Car;

use Illuminate\Http\Request;


class HdfcErgoTokenGeneration
{
    public function generateToken(Request $request){
        try {
            $token_url = config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR');
            $ProductCode = '2319';
            $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
            $SOURCE = config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR');
            $CHANNEL_ID = config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR');
            $TRANSACTIONID = $transactionid;
            $CREDENTIAL = config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR');
    
            $additionalData['headers'] = [
                'Content-type' => 'application/json',
                'PRODUCT_CODE' => $ProductCode,
                'SOURCE' => $SOURCE,
                'CHANNEL_ID' => $CHANNEL_ID,
                'TRANSACTIONID' => $TRANSACTIONID,
                'CREDENTIAL' => $CREDENTIAL
            ];
    
            $data = httpRequestNormal($token_url, 'GET', [], [], $additionalData['headers'], [], false, true);
            if(isset($data['response']['Authentication'])){
                $token_data= $data['response']['Authentication']['Token'];
                return response()->json([
                    'status' => true,
                    'token_data' => $token_data,
                    'message' =>'Token is Generated' 
                ]);
            } else {
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