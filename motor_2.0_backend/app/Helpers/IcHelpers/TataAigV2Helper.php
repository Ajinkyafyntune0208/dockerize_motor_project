<?php

use App\Models\UserProposal;
use App\Http\Controllers\CkycController;
use Illuminate\Http\Request;

class TataAigV2Helper
{
    public static function getToken($enquiryId, $productData, $transaction_type = 'quote', $type = 'renewal')
    {

        $tokenrequest  = [
            "grant_type" => config('TATA_AIG_GRANT_TYPE'), 
            "scope" => config('TATA_AIG_SCOPE'), 
            "client_id" => config('TATA_AIG_CLIENT_ID') ,
            "client_secret" => config('TATA_AIG_CLIENT_SECRET')
        ];
       

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig_v2',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'token generation',
            'transaction_type'  => $transaction_type,
            'type'              => $type,
            'headers' => [
                'Content-Type'   => "application/x-www-form-urlencoded",
                "Connection" => "Keep-Alive",
                // 'Authorization' =>  'Basic ' . base64_encode("$webUserId:$password"),
                'Accept'        => "application/json",
            ]

        ];
        

        $tokenservice = getWsData(config('constants.IcConstants.tataaig.TATA_AIG_CV_MOTOR_TOKEN_GENERATION_URL'), $tokenrequest, 'tata_aig', $additional_data);
        $tokenserviceresponse  = $tokenservice['response'];
        $tokenservicejson = json_decode($tokenserviceresponse);
        if (empty($tokenservicejson) || !isset($tokenservicejson->access_token)) {
            return [
                'status' => false,
                'message' => 'Getting error in token service '
            ];
        } else {
            return [
                'status' => true,
                'token' => $tokenservicejson->access_token
            ];
        }
    }

    
   

    
}



