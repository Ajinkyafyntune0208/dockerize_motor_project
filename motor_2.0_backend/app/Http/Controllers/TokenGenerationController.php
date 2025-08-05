<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TokenGenerationController extends Controller
{
    public function generateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_endpoint' => 'required|url',
        ]);

        if ($validator->fails())    {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Request.',
                'token' => null,
                'expires_in' => null,
            ], 403);
        }

        try {
            $validUser = false;
            $authorizationHeader = explode(' ', $request->header('Authorization'));
            if ($authorizationHeader[0] === 'Basic' && isset($authorizationHeader[1])) {
                $is_base64 = is_base64($authorizationHeader[1]);
                if ($is_base64) {
                    $decodedKey = base64_decode($authorizationHeader[1]);
                    if ($this->isValidEmailPasswordFormat($decodedKey)) {
                        $credentials = explode(':', $decodedKey);
                        $role = User::where('email', $credentials[0])->first()?->getRoleNames()->first();
                        $isWebServiceUser = 'webservice' === $role;
                        $credentials = [
                            'email' => $credentials[0],
                            'password' => $credentials[1]
                        ];
                        $validUser = Auth::attempt($credentials);
                        if ($validUser & $isWebServiceUser) {
                            return response()->json([
                                'status' => true,
                                'message' => 'Token Generated successfully',
                                'token' => $this->generateKey($request->api_endpoint),
                                'expires_in' => 3600,
                            ]);
                        }
                    }
                }
            }
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized User',
                'token' => null,
                'expires_in' => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'token' => null,
                'expires_in' => null,
            ]);
        }
    }
    public function dataEncrypt($text)
    {
        return base64_encode(openssl_encrypt($text, 'aes-256-cbc', '01234567890123456789012345678901', OPENSSL_RAW_DATA, '1234567890123412'));
    }
    public function randomSplit($inputString, $marker = "~:~")
    {
        $parts = preg_split('/(.{1,5})/', $inputString, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        return implode($marker, $parts);
    }

    public function randomEncrypt($inputString, $markerOpen = "{", $markerClose = "}")
    {
        $parts = explode("~:~", $inputString);
        $encryptedParts = array_map(function ($part) use ($markerOpen, $markerClose) {
            return (mt_rand(0, 1) == 0) ? "{$markerOpen}" . $this->dataEncrypt($part) . "{$markerClose}" : $part;
        }, $parts);
        return implode("~:~", $encryptedParts);
    }

    public function generateKey($fullPath)
    {
        $preSharedKey = "ijHjx4/alAwjLu1ftuwLF3g0w4pNORaol9GQ4Y0qYVM=";
        $splitString = $this->randomSplit($preSharedKey);
        $encryptedString = $this->randomEncrypt($splitString);

        $validity = date('d/m/Y H:i:s', strtotime('+60 minutes'));
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $encodedFullPath = urlencode($fullPath);

        $key = "{$encryptedString}|{$encodedFullPath}|{$validity}|{$userAgent}";

        return $this->dataEncrypt($key);
    }
    public function isValidEmailPasswordFormat($string)
    {
        $pattern = '/^\S+@\S+\.\S+:\S+$/';
        return preg_match($pattern, $string);
    }
}
