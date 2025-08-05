<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Models\UserProposal;
use DateTime;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\ckycUploadDocuments;
include_once app_path().'/Helpers/CarWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/SbiCkycHelper.php';

class sbiRenewalSubmitProposal
{
    public static function renewalSubmit($proposal, $request)
    {
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $jsontoarray =  json_decode($quote->premium_json, true);
        $quotationNo = $jsontoarray['quotationNo'];
        $requestData = getQuotation($proposal->user_product_journey_id);
        // $enquiryId   = ($request['enquiryId']);
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'proposal_no' => $quotationNo ?? null,
                        ]);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'sbi');

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        //--OVD FLOW START--
        if ($proposal->is_ckyc_verified != 'Y') {
            $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            $get_doc_data = json_decode($document_upload_data->cky_doc_data ?? '', true);
            if (empty($get_doc_data) || empty($document_upload_data)) {
                return response()->json([
                    'data' => [
                        'message' => 'No documents found for CKYC Verification. Please upload any and try again.',
                        'verification_status' => false,
                    ],
                    'status' => false,
                    'message' => 'No documents found for CKYC Verification. Please upload any and try again.'
                ]);
            } else {
                try {
                    if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y' && $proposal->proposer_ckyc_details?->is_document_upload  == 'Y') {
                        $ckyc_doc_validation = ckycVerifications($proposal);
                        if ($ckyc_doc_validation['status'] != 'false' && $ckyc_doc_validation['message'] != 'File Upload successfully at both place') {
                            return [
                                'status' => false,
                                'message' => 'File Upload Unsuccessfully' . $ckyc_doc_validation['message']
                            ];
                        }
                    }
                } catch (Exception $e) {
                    \Illuminate\Support\Facades\Log::error('SBI KYC EXCEPTION trace_id=' . customEncrypt($proposal->user_product_journey_id), array($e));
                }
            }
        }
        //--OVD FLOW END--

        //--token generation-- 
        $data = cache()->remember('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN.CAR', 60 * 2.5, function () use ($enquiryId, $productData) {
            return getWsData(config('constants.IcConstants.sbi.SBI_RENEWAL_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
                'enquiryId' => $enquiryId,
                'requestMethod' => 'get',
                'productName'  => $productData->product_name,
                'company'  => 'sbi',
                'section' => $productData->product_sub_type_code,
                'method' => 'Generate Token',
                'transaction_type' => 'proposal'
            ]);
        });
        if ($data['response']) {
            $token_data = json_decode($data['response'], TRUE);


            $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            //renewal getQuote service 
            $policy_data = [
                [
                    "renewalQuoteRequestHeader" => [
                        "requestID" => $enquiryId,
                        "action" => "renewalQuote",
                        "channel" => "SBIGIC",
                        "transactionTimestamp" => date('d-M-Y-H:i:s')
                    ],
                    "renewalQuoteRequestBody" => [
                        "payload" => [
                            "policytype" => "Renewal",
                            "policyNumber" => $user_proposal['previous_policy_number'] ?? null,
                            "productCode" => "PMCAR001"
                        ]
                    ]
                ]
            ];

            $encrypt_req = [
                'data' => json_encode($policy_data),
                'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'development',
                'action' => 'encrypt'
            ];
            $encrpt_resp = httpRequest('sbi_encrypt', $encrypt_req, [], [], [], true, true)['response'];

            $encrpt_policy_data['DecryptedGCM'] = trim($encrpt_resp);
            if (isset($encrpt_resp)) {
                $get_response = getWsData(config('constants.IcConstants.sbi.SBI_RENEWAL_FETCH_END_POINT_URL'), $encrpt_policy_data, 'sbi', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Renewal Fetch Policy Details',
                    'requestMethod' => 'post',
                    'company'  => 'sbi',
                    'transaction_type' => 'proposal',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'authorization' => $token_data['access_token'] ?? $token_data['accessToken']
                ]);

                $data = $get_response['response'];
                $data = json_decode($get_response['response'], true);
                if (isset($data['EncryptedGCM'])) {
                    $decrypt_req = [
                        'data' => $data['EncryptedGCM'],
                        'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'development',
                        'action' => 'decrypt',
                        // 'file'  => 'true'
                    ];
                }
                $decrpt_resp = httpRequest('sbi_encrypt', $decrypt_req, [], [], [], true, true)['response'];
                $flag = $decrpt_resp['renewalQuoteResponseBody']['payload']['flag'] ?? '';
                $flag_description = [
                    '1' => 'Policy number/engine/chassis/registration number is null. Vaidation unsuccessful.',
                    '2' => 'mdm id/engine/chassis/registration number is null. Vaidation unsuccessful.',
                    '3' => 'Policy can be renewable.(i.e. effective date > =system date and <=30 dyas ( > =1 day <=30 days)). Vaidation successful',
                    '4' => 'Data not available/Referred to UW/Renewal quote error. Vaidation unsuccessful.',
                    '5' => 'Null data for the given policy/mdm id/engine/chassis/registration number details. Vaidation unsuccessful.',
                    '6' => 'Policy number/mdm id/engine/chassis/registration number details are invalid.Vaidation unsuccessful.',
                    '7' => 'ql exception/query error. Vaidation/class file execution unsuccessful',
                    '8' => 'future dated policies. (i.e. effective date > system date( > 30 days))',
                    '9' => 'Back dated policies. (i.e. effective date < = system date( < 1 day))',
                    '10' => 'Pending endorsement (maker/checker/UW)',
                    '11' => 'Policy already renewed',
                    '12' => 'Renewal notice not available',
                    '13' => 'Pending claim',
                    '14' => 'Total loss claim',
                    '15' => 'Rejected',
                    '16' => 'Multiple policy record',
                ];
                if ($flag === "3") {
                    // $transaction_id = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 16);
                    $start_date =  $decrpt_resp['renewalQuoteResponseBody']['payload']['startTime'];
                    $end_date =  $decrpt_resp['renewalQuoteResponseBody']['payload']['renewalDueDate'];
                    $final_amount =  $decrpt_resp['renewalQuoteResponseBody']['payload']['renewalPremiumAmount'];
                    $mobile =  $decrpt_resp['renewalQuoteResponseBody']['payload']['mobile'];
                    $email =  $decrpt_resp['renewalQuoteResponseBody']['payload']['email'] ?? null;

                    //other data
                    $today_date = date('d-m-Y h:i:s');
                    if (new DateTime($requestData->previous_policy_expiry_date . ' 23:59:59') > new DateTime($today_date)) {
                        $start_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                    }
                    if ($requestData->business_type == "breakin") {
                        $start_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                    }
                    $end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($start_date, '/', '-'))))));

                    UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'proposal_no' => $quotationNo ?? null,
                            'unique_proposal_id' => $quotationNo ?? null,
                            'policy_start_date' =>  $start_date,
                            'policy_end_date' =>  $end_date,
                            'final_payable_amount' => $final_amount ?? null,
                            'mobile_number' => $mobile,
                            'email' => $email,
                        ]);
                    updateJourneyStage([
                        'user_product_journey_id' => $enquiryId,
                        'ic_id' => $productData->company_id,
                        'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                        'proposal_id' => $proposal->user_proposal_id
                    ]);

                    return response()->json([
                        'status' => true,
                        'msg' => "Proposal Submitted Successfully!",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => [
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $enquiryId,
                            'proposalNo' => $quotationNo ?? null,
                            'finalPayableAmount' => $final_amount,
                        ]
                    ]);
                } else {
                    return [
                        'status' => false,
                        'flag' => $flag,
                        'message' => ($flag_description[$flag]) ?? 'Insurer not reachable'
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => 'Error in Encrytion'
                ];
            }
        } else {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status' => false,
                'message' => 'Token Generation Issue'
            ];
        }
    }
}
