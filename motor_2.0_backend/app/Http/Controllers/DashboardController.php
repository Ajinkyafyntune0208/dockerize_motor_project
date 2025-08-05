<?php

namespace App\Http\Controllers;

use App\Models\CvAgentMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\JourneyStage;

class DashboardController extends Controller
{
    public static function encryptToken($token)
    {
        if (config('constants.motorConstant.ENCRYPT_DASHBOARD_TOKEN') != 'Y') {
            return $token;
        }
        //Decrypt the token using dashboard keys
        $decryptedToken = self::decryptData($token);

        //again encrypt the token, and let dashboard decrypt it
        return self::encryptData($decryptedToken);
    }
    public static function decryptData($data)
    {
        try {
            $encryptMethod = "AES-256-CBC";

            $secretKey = config('constants.motorConstant.DASHBOARD_ENCRYPTION_TOKEN_SECRET_KEY');
            $secretIv = hash('sha256', hash('sha256', $secretKey));

            $key = hash('sha256', $secretKey);
            $iv = substr(hash('sha256', $secretIv), 0, 16);

            $data = openssl_decrypt(base64_decode($data), $encryptMethod, $key, 0, $iv);
            
        } catch (\Throwable $th) {
            Log::error($th);
        }

        return $data;
    }

    public static function encryptData($data)
    {
        $encryptMethod = "AES-256-CBC";
        
        $secretKey = config('constants.motorConstant.MOTOR_ENCRYPTION_TOKEN_SECRET_KEY');
        $secretIv = hash('sha256', hash('sha256', $secretKey));
        $key = hash('sha256', $secretKey);
        $iv = substr(hash('sha256', $secretIv), 0, 16);

        $data = openssl_encrypt($data, $encryptMethod, $key, 0, $iv);
        $data = base64_encode($data);

        return $data;
    }

    public static function updateAgentDetils($data, $enquiry_id)
    {
        $update_agent_details = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'), 'POST', ['token' => $data])['response'];

        if (! isset($update_agent_details['data']['seller_name']) || $update_agent_details['data']['seller_name'] == NULL) {
            return response()->json([
                "status" => false,
                "msg" => 'Invalid Token...!',
            ]);
        }

        $agent_details = $update_agent_details['data'];

        $update_data =  [
            "seller_type" => $agent_details['seller_type'] ?? null,
            "user_name" => $agent_details['user_name'],
            "agent_name" => $agent_details['seller_name'] ?? null,
            "agent_email" => $agent_details['email'] ?? null,
            "agent_mobile" => $agent_details['mobile'] ?? null
        ];

        CvAgentMapping::updateOrCreate(['user_product_journey_id' => $enquiry_id], $update_data);
    }
}