<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class KotakDecryptionController extends Controller
{
    public function index(Request $request)
    { 
        $data = array();
        $encryptedText = '';

        if ($request->isMethod('post')) {
            ob_start();
            $secretKey = config('constant.IcContant.kotak_decryption_secret_key');
            $key = mb_convert_encoding($secretKey, 'UTF-8', 'UTF-8');

            // Decode Base64 encrypted text
            $encryptedText = mb_convert_encoding($request->all()['encodedId'], 'UTF-8', 'UTF-8');
            $encryptedData = base64_decode($encryptedText);
    
            if ($encryptedData === false)
            {
                return "Decryption failed: Invalid Base64 input.";
            }
    
            // Extract the IV from the first 16 bytes
            $iv = mb_strcut($encryptedData, 0, 16, '8bit');
            $encryptedPayload = mb_strcut($encryptedData, 16, null, '8bit');
    
            // Check if IV and encrypted payload are valid
            if ($iv === false || $encryptedPayload === false)
            {
                return "Decryption failed: Invalid encrypted data format.";
            }
    
            // Decrypt the payload
            try {
                $decryptedData = openssl_decrypt(
                    $encryptedPayload,
                    'aes-256-cbc', // AES with 256-bit key and CBC mode
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv
                );
                $data = $decryptedData;
                if ($decryptedData === false) {
                    $data = openssl_error_string();
                }
                // $data = $decryptedData;
            } catch (Exception $e) {
                // Handle any decryption errors
                return redirect()->back()->with([
                    'status' => "Decryption failed: " . $e->getMessage(),
                    'class' => 'danger',
                ]);
            }
        }

        return view('admin_lte.encrypt-decrypt.kotakindex',compact('data'));
    }
}
