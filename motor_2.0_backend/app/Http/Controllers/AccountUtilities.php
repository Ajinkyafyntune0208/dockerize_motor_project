<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterCompany;
use App\Models\UserProposal;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\Http;

class AccountUtilities extends Controller
{
    function getBankDetails(Request $request)
    {
        $payload = [];
        $enquiry_id = customDecrypt($request->userProductJourneyId);
        $user_product_journey = UserProductJourney::where('user_product_journey_id', $enquiry_id)->first();
        
        //Get IC Name:
        $quoteLog = QuoteLog::where('user_product_journey_id', $enquiry_id)->first();
        $company_alias = MasterCompany::where('company_id', $quoteLog->ic_id)
            ->select('company_alias')
            ->first();

        //Section - CAR, BIKE, CV
        $section = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
            ->join('master_product_sub_type', 'master_product_sub_type.product_sub_type_id', '=', 'corporate_vehicles_quotes_request.product_id')
            ->select('product_sub_type_code', 'parent_id')
            ->first();
        $section = \Illuminate\Support\Str::ucfirst($section->product_sub_type_code);
        strtoupper($section);

        //Request
        $payload = $request->validate([
            'ifsc' => ['nullable'],
        ]);

        //WebServiceHelpers for getWs
        if ($section == 'CAR') {
            include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        } elseif ($section == 'BIKE') {
            include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
        } else {
            include_once app_path() . '/Helpers/CvWebServiceHelper.php';
        }

        //IC wise Bank fetch flow:
        if (!empty($company_alias)) {
            switch ($company_alias->company_alias) {
                case 'sbi':
                    if(empty($request->ifsc)){
                        return response()->json([
                            'status' => false,
                            'message' => 'IFSC Number not found.'
                        ]);
                    }
                    //Client ID
                    $X_IBM_CLIENT_ID = ($section == 'CAR') ? config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID') : (($section == 'BIKE') ? config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_BIKE') : (($section == 'PCV') ? config('constants.IcConstants.sbi.X_IBM_Client_Id_PCV') :
                        config('constants.IcConstants.sbi.CV_X_IBM_Client_Id')));
                    $X_IBM_CLIENT_SECRET = ($section == 'CAR') ? config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET') : (($section == 'BIKE') ? config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_BIKE') : (($section == 'PCV') ? config('constants.IcConstants.sbi.X_IBM_Client_Secret_PCV') :
                        config('constants.IcConstants.sbi.X_IBM_Client_Secret')));

                    //Client ID and Client secret is passed in additional data for CV
                    if($section != 'CAR' || $section != 'BIKE'){
                        $additionalData = [
                            'enquiryId' => $enquiry_id,
                            'requestMethod' => 'get',
                            'company'  => 'sbi',
                            'section' => $section,
                            'method' => 'Token Generation',
                            'transaction_type' => 'proposal',
                            'client_id' => $X_IBM_CLIENT_ID,
                            'client_secret' => $X_IBM_CLIENT_SECRET
                        ];
                    }else{
                       $additionalData = [
                            'enquiryId' => $enquiry_id,
                            'requestMethod' => 'get',
                            'company'  => 'sbi',
                            'section' => $section,
                            'method' => 'Get Token',
                            'transaction_type' => 'proposal'
                       ];
                    }
                        
                    $get_response = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', $additionalData);
                    $data = $get_response['response'];
                    $token_data = json_decode($data, TRUE);
                    if (empty($token_data)) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Token service issue.'
                        ]);
                    }
                    
                    //Bank fetch API encryption and decrytion
                    $bankfetch_array = [
                        'fetchBankDetailsRequest' => [
                            'ifscCode' => $request->ifsc,
                        ]
                    ];

                    //Encryption
                    $encryptReq = [
                        'data' => json_encode($bankfetch_array),
                        'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'development',
                        'client' => config('constants.motorConstant.SMS_FOLDER'),
                        'action' => 'encryption'
                    ];
                    $encrypt_resp = httpRequest('bank_fetch_encrypt', $encryptReq, [], [], [], true, true)['response'];
                    $encrypt_bank_req['ciphertext'] = trim($encrypt_resp);
                    if (isset($encrypt_bank_req)) {
                        if($section != 'CAR' || $section != 'BIKE'){
                            $additionalData = [
                                'section' => $section,
                                'method' => 'Bank Fetch Service',
                                'requestMethod' => 'post',
                                'company'  => 'sbi',
                                'transaction_type' => 'proposal',
                                'enquiryId' => $enquiry_id,
                                'authorization' => $token_data['access_token'],
                                'client_id' => $X_IBM_CLIENT_ID,
                                'client_secret' => $X_IBM_CLIENT_SECRET
                            ];
                        }else{
                            $additionalData = [
                                'section' => $section,
                                'method' => 'Bank Fetch Service',
                                'requestMethod' => 'post',
                                'company'  => 'sbi',
                                'transaction_type' => 'proposal',
                                'enquiryId' => $enquiry_id,
                                'authorization' => $token_data['access_token']
                            ];
                        }
                        $get_response = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_MOTOR_BANKFETCH'), $encrypt_bank_req, 'sbi', $additionalData);
                        $data = $get_response['response'];

                        $decryption_data = json_decode($data, true);
                        if (empty($decryption_data["ciphertext"])) {
                            return response()->json([
                                'status' => false,
                                'message' => 'Bank Fetch API Issue'
                            ]);
                        }

                        //Decryption
                        $decryptReq = [
                            'data' => $decryption_data['ciphertext'],
                            'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'development',
                            'client' => config('constants.motorConstant.SMS_FOLDER'),
                            'action' => 'decryption'
                        ];

                        $decryption_resp = httpRequest('bank_fetch_encrypt', $decryptReq, [], [], [], true, true)['response'];

                        if (!isset($decryption_resp)) {
                            return response()->json([
                                'status' => false,
                                'message' => 'Error in Decryption.'
                            ]);
                        }

                        $response = $decryption_resp['fetchBankDetailsResponse'];
                        if(empty($response) || empty($response['ifscCode'])){
                            return response()->json([
                                'status' => false,
                                'message' => 'Invalid IFSC Code.'
                            ]);
                        }
                        $result = [
                            'ifsc'        => $response['ifscCode'],
                            'bank_code'   => $response['bankCode'],
                            'bank_name'   => $response['bankName'],
                            'branch_code' => $response['branchCode'],
                            'bank_branch' => $response['bankBranch']
                        ];
                        return response()->json([
                            'status' => true,
                            'data' => $result,
                            'message' => 'Data Found'
                        ]);
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => 'Error in Encryption.'
                        ]);
                    }
                    break;

                default:
                    return response()->json([
                        'status' => false,
                        'message' => 'No Bank details/data found'
                    ]);
                    break;
            }

        }
    }
}
