<?php

namespace App\Quotes\FetchRenewalData\Car;

use DateTime;
use App\Models\PreviousInsurerList;
use Illuminate\Support\Facades\Storage;

class sbi
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';

        $data = cache()->remember('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN.CAR', 60 * 2.5, function () use ($enquiryId) {
            return getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
                'enquiryId' => $enquiryId,
                'requestMethod' => 'get',
                'productName'  => 'token generation',
                'company'  => 'sbi',
                'section' => 'car',
                'method' => 'Generate Token',
                'transaction_type' => 'quote'
            ]);
        });

        if ($data['response']) {
            $token_data = json_decode($data['response'], TRUE);
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
                            "policyNumber" => $value['previous_policy_number'],
                            "productCode" => "PMCAR001"
                        ]
                    ]
                ]
            ];
            $encrypt_req = [
                'data' => json_encode($policy_data),
                'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                'action' => 'encrypt'
            ];
            $encrpt_resp = httpRequest('sbi_encrypt', $encrypt_req, [], [], [], true, true)['response'];
            if (isset($encrpt_resp)) {

                $encrpt_policy_data['DecryptedGCM'] = trim($encrpt_resp);
                $get_response = getWsData(config('constants.IcConstants.sbi.SBI_RENEWAL_FETCH_END_POINT_URL'),  $encrpt_policy_data, 'sbi', [
                    'section' => 'car',
                    'method' => 'Renewal Fetch Policy Details',
                    'requestMethod' => 'post',
                    'company'  => 'sbi',
                    'enquiryId' => $enquiryId,
                    'productName' => $value['product_name'] . " Renewal Data Migration",
                    'transaction_type' => 'quote',
                    'authorization' => $token_data['access_token'] ?? $token_data['accessToken'],
                ]);
                $data = $get_response['response'];
                $data = json_decode($get_response['response'], true);
                if (isset($data['EncryptedGCM'])) {
                    $decrypt_req = [
                        'data' => $data['EncryptedGCM'],
                        'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'local',
                        'action' => 'decrypt',
                        // 'file'  => 'true'
                    ];
                }
                $decrpt_resp = httpRequest('sbi_encrypt', $decrypt_req, [], [], [], true, true)['response'];
                $flag = $decrpt_resp['renewalQuoteResponseBody']['payload']['flag'];
                if ($flag === "3") {
                    $value['idv'] = round($decrpt_resp['renewalQuoteResponseBody']['payload']['sumInsured']);
                    $value['full_name'] = $decrpt_resp['renewalQuoteResponseBody']['payload']['customerName'];
                    $value['email_address'] = $decrpt_resp['renewalQuoteResponseBody']['payload']['email'];
                    $value['mobile_no'] = $decrpt_resp['renewalQuoteResponseBody']['payload']['mobile'];

                    // return true;
                }
            }
        }
        return false;
    }

    public static function getFyntuneVersionId(&$value, $code)
    {
        return true;
    }
}
