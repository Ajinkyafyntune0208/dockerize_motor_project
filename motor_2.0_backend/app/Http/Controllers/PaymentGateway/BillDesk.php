<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\WebServiceRequestResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DateTime;
use Carbon\Carbon;

class BillDesk extends Controller
{
    public static function CreateOrder($order_id, $request, $proposal , $url, $sharedSecretKey , $merchant_id , $return_url)
    {
        try {

            $productData = getProductDataByIc($request['policyId']);
            $enquiryId  = customDecrypt($request->enquiryId);
            $trace_id  = time() . rand(111, 9999);
            $order_date = (new DateTime())->format('Y-m-d\TH:i:sP');

            // Prepare headers
            $headers = [
                'Content-type'  => 'application/jose',
                'Accept'        => 'application/jose',
                'BD-Traceid'    => $trace_id,
                'BD-Timestamp'  => $trace_id,
                'alg'           => config('IC.BILL_DESK_API.V2.ALG'),
                'clientid'      => config('IC.BILL_DESK_API.V2.CLIENTID')
            ];

            // Prepare payload
            $payload = [
                'mercid'        => $merchant_id,
                'orderid'       => $order_id,
                'amount'        => $proposal->final_payable_amount,
                'order_date'    => $order_date,
                'currency'      => '356',
                'ru'            => $return_url,
                'additional_info' => array_fill_keys(
                    [
                        'additional_info1',
                        'additional_info2',
                        'additional_info3',
                        'additional_info4',
                        'additional_info5',
                        'additional_info6',
                        'additional_info7',
                        'additional_info8',
                        'additional_info9',
                        'additional_info10'
                    ],
                    'NA'
                ),
                'itemcode' => 'DIRECT',
                'device' => [
                    'init_channel'               => 'internet',
                    'ip'                         => request()->ip(),
                    'user_agent'                 => $_SERVER['HTTP_USER_AGENT'],
                    'accept_header'              => 'text/html',
                    'browser_language'           => 'en-US',
                    'browser_javascript_enabled' => false
                ]
            ];

            $headerJson = json_encode($headers);
            $payloadJson = json_encode($payload);

            $startTime = new DateTime(date('Y-m-d H:i:s'));
            $endTime = new DateTime(date('Y-m-d H:i:s'));



            $headerBase64Url = rtrim(strtr(base64_encode($headerJson), '+/', '-_'), '=');
            $payloadBase64Url = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');

            $dataToSign = $headerBase64Url . '.' . $payloadBase64Url;
            $signature = hash_hmac('sha256', $dataToSign, $sharedSecretKey, true);
            $signatureBase64Url = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');



            $jws = $headerBase64Url . '.' . $payloadBase64Url . '.' . $signatureBase64Url; //request payload



            $store_data = [
                'enquiry_id'        => $enquiryId,
                'product'           => $productData->product_name,
                'section'           => $productData->product_sub_type_id == 1 ? 'CAR' : ($productData->product_sub_type_id == 2 ? 'BIKE' : 'CV'),
                'method_name'       => 'OrderId Creation - Step-1 (JSON)',
                'company'           => 'united_india',
                'method'            => 'post',
                'transaction_type'  => 'proposal',
                'request'           => $payloadJson,
                'response'          => 'JSON LOADED',
                'endpoint_url'      => $url,
                'ip_address'        => request()->ip(),
                'start_time'        => $startTime->format('Y-m-d H:i:s'),
                'end_time'          => $endTime->format('Y-m-d H:i:s'),
                'response_time'        => $endTime->getTimestamp() - $startTime->getTimestamp(),
                'created_at'        => Carbon::now(),
                'headers'           => $headerJson
            ];

            WebServiceRequestResponse::create($store_data);

            $additionalData = [
                'method' => 'Order Id Creation',
                'company' => 'united_india',
                'method_name' => 'OrderId Creation - Step-2 (Encryption)',
                'headers' => $headers,
                'requestMethod' => 'post',
                'transaction_type' => 'proposal',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name,
                'section' => $productData->product_sub_type_id == 1 ? 'CAR' : ($productData->product_sub_type_id == 2 ? 'BIKE' : 'CV'),
            ];

            if ($productData->product_sub_type_id == 1) {
                
                include_once app_path() . '/Helpers/CarWebServiceHelper.php';
                $create_order_response = getWsData($url, $jws, 'billdesk', $additionalData);
            } elseif ($productData->product_sub_type_id == 2) {

                include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
                $create_order_response = getWsData($url, $jws, 'billdesk', $additionalData);
            } else {

                include_once app_path() . '/Helpers/CvWebServiceHelper.php';
                $create_order_response = getWsData($url, $jws, 'billdesk', $additionalData);
            }
            
            $responseBody = $create_order_response['response'];

            if (isset($create_order_response['status_code']) && $create_order_response['status_code'] == 200 && isset($responseBody)) {

                return response()->json([
                    'status'           => true,
                    'msg'              => 'Payment Redirectional Successfully',
                    'data'             => $responseBody,
                ]);
            }

            return response()->json([
                'status' => false,
                'msg'    => "Error in generating order ID",
                'data'   => null,
            ]);
        } catch (\Exception $e) {
            // Handle exceptions gracefully
            return response()->json([
                'status' => false,
                'msg'    => "An error occurred: " . $e->getMessage(),
                'data'   => null,
            ]);
        }
    }

    public static function checkPaymentStaus($order_id, $merchant_id , $sharedSecretKey , $status_url , $enquiryId , $productData)
    {
        $oder_time = time() . rand(111, 9999);

        $startTime = new DateTime(date('Y-m-d H:i:s'));
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $headers = [
            'Content-type'  => 'application/jose',
            'Accept'        => 'application/jose',
            'BD-Traceid'    => $oder_time,
            'BD-Timestamp'  => $oder_time,
            'alg'           => config('IC.BILL_DESK_API.V2.ALG'),
            'clientid'      => config('IC.BILL_DESK_API.V2.CLIENTID')
        ];

        $payload = [
            'mercid'        => $merchant_id,
            'orderid'       => $order_id,
        ];

        $headerJson = json_encode($headers);
        $payloadJson = json_encode($payload);

        $store_data = [
            'enquiry_id'        => $enquiryId,
            'product'           => $productData->product_name,
            'section'           => $productData->product_sub_type_id == 1 ? 'CAR' : ($productData->product_sub_type_id == 2 ? 'BIKE' : 'CV'),
            'method_name'       => 'Check Payment Status - Step-1 (JSON)',
            'company'           => 'united_india',
            'method'            => 'post',
            'transaction_type'  => 'proposal',
            'request'           => $payloadJson,
            'response'          => 'JSON lOADED',
            'endpoint_url'      => $status_url,
            'ip_address'        => request()->ip(),
            'start_time'        => $startTime->format('Y-m-d H:i:s'),
            'end_time'          => $endTime->format('Y-m-d H:i:s'),
            'response_time'        => $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'        => Carbon::now(),
            'headers'           => $headerJson
        ];

        WebServiceRequestResponse::create($store_data);

        $headerBase64Url = rtrim(strtr(base64_encode($headerJson), '+/', '-_'), '=');
        $payloadBase64Url = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');

        $dataToSign = $headerBase64Url . '.' . $payloadBase64Url;
        $signature = hash_hmac('sha256', $dataToSign, $sharedSecretKey, true);
        $signatureBase64Url = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $jws = $headerBase64Url . '.' . $payloadBase64Url . '.' . $signatureBase64Url;

        $additionalData = [
            'method' => 'Check Order Id Status',
            'company' => 'united_india',
            'method_name' => 'Check Payment Status - Step-2 (Encryption)',
            'headers' => $headers,
            'requestMethod' => 'post',
            'transaction_type' => 'proposal',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'section' => $productData->product_sub_type_id == 1 ? 'CAR' : ($productData->product_sub_type_id == 2 ? 'BIKE' : 'CV'),
        ];   

        if ($productData->product_sub_type_id == 1) {
                
            include_once app_path() . '/Helpers/CarWebServiceHelper.php';
            $create_order_response = getWsData($status_url, $jws, 'billdesk', $additionalData);
        } elseif ($productData->product_sub_type_id == 2) {

            include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
            $create_order_response = getWsData($status_url, $jws, 'billdesk', $additionalData);
        } else {

            include_once app_path() . '/Helpers/CvWebServiceHelper.php';
            $create_order_response = getWsData($status_url, $jws, 'billdesk', $additionalData);
        }

        $responseBody = $create_order_response['response'];

        if (isset($create_order_response['status_code']) && $create_order_response['status_code'] == 200 && isset($responseBody)) {
            $check_status = self::checkTransactionStaus($responseBody , $sharedSecretKey , $enquiryId , $productData , $status_url );
            if ($check_status) {
                return response()->json([
                    'status' => false,
                    'msg'    => "Seems..! Payment Already Done for this Transaction",
                ]);
            }
        }
        
        return response()->json([
            'status' => true,
            'msg'    => "Transaction not found",
        ]);
    }

    public static function checkTransactionStaus($response , $sharedSecretKey , $enquiryId , $productData , $status_url )
    {
        $decoded = JWT::decode($response, new Key($sharedSecretKey, 'HS256'));
        $decoded = json_decode(json_encode($decoded), TRUE);

        $startTime = new DateTime(date('Y-m-d H:i:s'));
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $store_data = [
            'enquiry_id'        => $enquiryId,
            'product'           => $productData->product_name,
            'section'           => $productData->product_sub_type_id == 1 ? 'CAR' : ($productData->product_sub_type_id == 2 ? 'BIKE' : 'CV'),
            'method_name'       => 'Check Payment Status - Step-3 (Decrypted Response)',
            'company'           => 'united_india',
            'method'            => 'post',
            'transaction_type'  => 'proposal',
            'request'           => 'JSON lOADED',
            'response'          => $decoded,
            'endpoint_url'      => $status_url,
            'ip_address'        => request()->ip(),
            'start_time'        => $startTime->format('Y-m-d H:i:s'),
            'end_time'          => $endTime->format('Y-m-d H:i:s'),
            'response_time'        => $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'        => Carbon::now(),
            'headers'           => NULL
        ];

        WebServiceRequestResponse::create($store_data);

        if ($decoded['auth_status'] == "0300") {
            return true;
        } elseif ($decoded['auth_status'] == "0002") {
            return true;
        }
        return false;
    }

    public static function jwtDecode($response , $sharedSecretKey)
    {
        $data = JWT::decode($response, new Key($sharedSecretKey, 'HS256'));
        return $data;
    }
}