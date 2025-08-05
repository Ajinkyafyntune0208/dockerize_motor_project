<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('encryption_decryption.list')){
            abort(403, 'Unauthorized action.');
        }  

        $data = array();

        if ($request->isMethod('post')) {

            $rules = [
                'normalText' => ['required_if:type,encryption', 'string'],
                'encryptedText' => ['required_if:type,decryption', 'string'],
                'normalId' => ['required_if:type,encode'],
                'encodedId' => ['required_if:type,decode'],
                'type' => ['required','in:encode,decode,encryption,decryption,piiEncrypt,piiDecrypt,payloadEncrypt,payloadDecrypt'],
                'piiEncryptText' =>['required_if:type, piiEncrypt'],
                'piiDecryptText' =>['required_if:type, piiDecrypt'],

                'payloadEncryptText' =>['required_if:type, payloadEncrypt'],
                'payloadDecryptText' =>['required_if:type, payloadDecrypt'],
            ];
            $validator = Validator::make($request->all(), $rules);
            if($validator->fails()){
                return redirect()->back()->withErrors($validator)->withInput();
            }

            try {
                if ($request->type === "encryption") {
                    $data['response'] = customEncrypt(trim($request->normalText), false);
                } elseif ($request->type === "decryption") {
                    $data['response'] =  customDecrypt(trim($request->encryptedText), false);
                    $data['response']  = is_string($data['response']) && is_array(json_decode($data['response'], true)) ? json_encode(json_decode($data['response'], true), JSON_PRETTY_PRINT) : $data['response'];
                }elseif($request->type === "encode"){
                    $data['responseEnquiry'] = customEncrypt(trim($request->normalId));
                }elseif($request->type === "decode"){
                    $data['responseEnquiry'] =  customDecrypt(trim($request->encodedId));
                }elseif ($request->type === "piiEncrypt") {
                    include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
                    $data['piiResponse'] = encryptPiData($request->piiEncryptText);
                } elseif ($request->type === "piiDecrypt") {
                    include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
                    $data['piiResponse'] = decryptPiData($request->piiDecryptText);
                }
                elseif ($request->type === "payloadEncrypt") {
                    $data['payloadResponse'] =  base64_encode(openssl_encrypt($request->payloadEncryptText, 'aes-256-cbc', '01234567890123456789012345678901', OPENSSL_RAW_DATA, '1234567890123412'));
                } elseif ($request->type === "payloadDecrypt") {
                    $data['payloadResponse'] = openssl_decrypt(base64_decode($request->payloadDecryptText), 'aes-256-cbc', '01234567890123456789012345678901', OPENSSL_RAW_DATA, '1234567890123412');
                }

            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Sorry, Something Wents Wrong !',
                    'class' => 'danger',
                ]);
            }
        }

        return view('admin_lte.encrypt-decrypt.index', ['data' => $data]);
    }
}
