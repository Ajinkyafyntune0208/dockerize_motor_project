<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('encrypt-decrypt.list')){
            abort(403, 'Unauthorized action.');
        }  

        $data = array();

        if ($request->isMethod('post')) {
            $rules = [
                'normalText' => ['required_if:type,encryption', 'string'],
                'encryptedText' => ['required_if:type,decryption', 'string'],
                'normalId' => ['required_if:type,encode'],
                'encodedId' => ['required_if:type,decode'],
                'type' => ['required','in:encode,decode,encryption,decryption,piiEncrypt,piiDecrypt'],
                'piiEncryptText' =>['required_if:type, piiEncrypt'],
                'piiDecryptText' =>['required_if:type, piiDecrypt'],
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
                } elseif ($request->type === "piiEncrypt") {
                    include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
                    $data['piiResponse'] = encryptPiData($request->piiEncryptText);
                } elseif ($request->type === "piiDecrypt") {
                    include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
                    $data['piiResponse'] = decryptPiData($request->piiDecryptText);
                }

            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Sorry, Something Wents Wrong !',
                    'class' => 'danger',
                ]);
            }
        }

        return view('security.index', ['data' => $data]);
    }
}
