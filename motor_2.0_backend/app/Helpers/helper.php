<?php

use App\Http\Controllers\AgentDiscountController;
use Carbon\Carbon;
use App\Models\Agents;
use App\Models\Gender;
use App\Models\QuoteLog;
use App\Models\CpaTenure;
use App\Mail\SendOtpEmail;
use Illuminate\Support\Str;
use App\Models\BajajCrmData;
use App\Models\JourneyStage;
use App\Models\MasterPolicy;
use App\Models\PolicySmsOtp;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Ixudra\Curl\Facades\Curl;
use App\Models\CvAgentMapping;
use App\Models\DiscountDomain;
use App\Models\SelectedAddons;
use App\Models\CvBreakinStatus;
use App\Models\CvJourneyStages;
use App\Models\IcErrorHandling;
use App\Models\MasterMotorAddon;
use App\Jobs\BajajCrmDataPushJob;
use App\Models\RcNumberBlockData;
use App\Models\HdfcErgoPosMapping;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Models\CKYCNotAFailureCases;
use App\Models\MasterProductSubType;
use Illuminate\Support\Facades\Http;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\IciciLombardPosMapping;
use Illuminate\Support\Facades\Schema;
use App\Models\CkycLogsRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\FastlanePreviousIcMapping;
use App\Models\WebServiceRequestResponse;
use App\Http\Controllers\CommonController;
use App\Models\QuoteServiceRequestResponse;
use App\Http\Controllers\LSQ\LeadController;
use App\Models\ThirdPartyApiRequestResponse;
use App\Http\Controllers\Mail\MailController;
use App\Http\Controllers\LSQ\ActivityController;
use App\Http\Controllers\ProposalReportController;
use App\Http\Controllers\LSQ\OpportunityController;
use App\Http\Controllers\Ckyc\CkycCommonController;
use App\Http\Controllers\IcConfig\IcConfigurationController;
use App\Jobs\DeleteOldRecordFromVisibilityTable;
use App\Models\JourneyStageLog;
use App\Models\PospUtility;
use App\Models\PospUtilityIcParameter;
use App\Models\PospUtilityImd;
use App\Models\QuoteVisibilityLogs;
use App\Models\UserJourneyActivity;
use App\Models\WebserviceRequestResponseDataOptionList;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

if(!function_exists('report_date')){

    function report_date($date1, $date2=NULL, $format='Y-m-d'){

        if($date1 && strtotime($date1)){
            return Carbon::parse($date1)->format($format);
        }

        if($date2 && strtotime($date2)){
            return Carbon::parse($date2)->format($format);
        }

        return NULL;
    }
}

if (!function_exists('linkDeliverystatus')) {
    function linkDeliverystatus($url, $enquiryId = null){
        $key = \Illuminate\Support\Str::uuid()->toString();
        $query = parse_url($url, PHP_URL_QUERY);
        if (!empty($query)) {
            $return_url = $url . '&' . http_build_query(['key' => $key]);
        }
        else{
            $return_url = $url . '?' . http_build_query(['key' => $key]);
        }
        \App\Models\LinkDeliverystatus::create([
            'url' => $url,
            'key' => $key,
            'user_product_journey_id' =>$enquiryId ,
            'status' => 'delivered',
        ]);
        return $return_url;
    }
}
if (!function_exists('discountEmailValidation')) {
    function discountEmailValidation($email)
    {
        $email = explode("@", $email);
        $end = end($email);
        if ($email_domain = DiscountDomain::where('domain', $end)->first()) {
            return [
                'status' => true,
                'msg' => $email_domain->domain . ' is Available for Discount'
            ];
        } else {
            return [
                'status' => false,
                'msg' => $email_domain->domain . ' is Not Available for Discount'
            ];
        }
    }
}

if (!function_exists('httpRequest')) {
    function httpRequest($name, $body = [], $attachment = [], $headers = [], $options = [], $save = true, $as_form = false)
    {
        $request = \App\Models\ThirdPartySetting::where('name', $name)->first();
        if(!empty(config('constants.http_proxy'))){
            $options = array_merge($options, [
                'proxy' => config('constants.http_proxy')
            ]);
        }
        $request_headers = array_merge($request->headers ?? [], $headers);
        $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders(array_merge($request->headers ?? [], $headers))->withOptions(array_merge($request->options ?? [], $options));
        if (!empty($attachment)) {
            foreach ($attachment as $key => $value) {
                $response = $response->attach($value[0], $value[1], $value[2]);
            }
        }
        $start_time = microtime(true) * 1000;
        if ($as_form) {
            $response = $response->asForm();
        }

        $url_encoded = '';

        if ( isset( $request->method ) && \Illuminate\Support\Str::upper($request->method) == 'POST' ) { 
            $response = $response->post($request->url, array_merge($request->body ?? [], $body));
        } else {                      
            $response = $response->get($request->url, array_merge($request->body ?? [], $body));
            
            $url_encoded = $response->handlerStats()['url'] ?? '' ;
            if(config('constants.motorConstant.SMS_FOLDER') == 'hero') {
            $url_encoded = str_replace('%20', '+', $url_encoded);
            }
        }
        $response_time = (microtime(true)* 1000) - $start_time . ' ms';
        $status = $response->status();
        $response_headers = $response->headers() ?? [];
        $url = !empty($url_encoded) ? $url_encoded : $request->url;
        $request = array_merge($request->body ?? [], $body);
        $response = $response->json() ?? $response->body();
        if($save){
        ThirdPartyApiRequestResponse::create([
            "name" => $name,
            "url" => $url,
            "request"=> $request,
            "response" => $response,
            "headers" => $request_headers,
            "response_headers" => $response_headers,
            "options" => $options,
            "response_time" => $response_time,
            "http_status" => $status
        ]);
    }
        return [
            'request' => $request,
            'status' => $status,
            'response' => $response,
            'response_headers' => $response_headers,
            'request_headers' => $request_headers,
            'url'=> $url
        ];
    }}

if (!function_exists('httpRequestNormal')) {
    function httpRequestNormal($name, $method = 'GET', $body = [], $attachment = [], $headers = [], $options = [] , $save = true, $as_form = false, $remove_proxy = false, $is_ckyc = false, $webservice=[])
    {
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        //Pass $remove_proxy=true for addming Proxy
        if(!empty(config('constants.http_proxy')) && $remove_proxy == false){
            $options = array_merge($options, [
                'proxy' => config('constants.http_proxy')
            ]);
        }
        if(isset(parse_url($name)['query']) && !empty(parse_url($name)['query'])){
            parse_str(parse_url($name)['query'], $query);
        }
        $body = array_merge($query ?? [], $body);
        $timeout = (int) config('HTTP_REQUEST_NORMAL_TIMEOUT', 45);
        
        if ($is_ckyc && config('IS_CKYC_WRAPPER_TOKEN_ENABLED') == 'Y') {
            $token = httpRequest('ckyc-wrapper-token', [
                'api_endpoint' => $name
            ], save:false)['response'];
            
            $headers['validation'] = $token['token'];
        }
        $response = \Illuminate\Support\Facades\Http::timeout($timeout)->withoutVerifying()->withHeaders($headers)->withOptions($options);
        if (!empty($attachment)) {
            foreach ($attachment as $key => $value) {
                $response = $response->attach($value[0], $value[1], $value[2]);
            }
        }
        
        $start_time = microtime(true) * 1000;
        if ($as_form) {
            $response = $response->asForm();
        }
        if (\Illuminate\Support\Str::upper($method) == 'POST') { 
            $response = $response->post($name, $body);
        } else {  
            try{
                $response = $response->get($name, $body);                
            } catch (Exception $ex) {
                return [                    
                    'status' => false,
                    'response' => NULL
                ];
            }
             
        }

        $end_time = microtime(true)* 1000;

        $response_time = $end_time - $start_time . ' ms';
        $status = $response->status();
        $response_headers = $response->headers() ?? [];
        $request = $body;
        $request_headers = $headers;
        $response = $response->json() ?? $response->body();
        if($save){
            if ($is_ckyc) {
                $ckyc_request = $body;

                if (isset($ckyc_request['documents']) && ! empty($ckyc_request['documents'])) {
                    foreach ($ckyc_request['documents'] as $key => $document) {
                        if ( ! empty($document['data'])) {
                            $ckyc_request['documents'][$key]['data'] = '<...base64 file data...>';
                        }
                    }
                }

                $verification_status = $response['data']['verification_status'] ?? false;
                $enquiry_id = $request['trace_id'];
                try {
                    $enquiry_id = customDecrypt($request['trace_id']);
                } catch (\Exception $e) {
                    // nothing to do here
                }
                $error_message = $response['data']['message'] ?? $response['message'] ?? null;
                $not_a_failure_cases = CKYCNotAFailureCases::where('message', $error_message)->first();
                CkycLogsRequestResponse::create([
                    'enquiry_id' => $enquiry_id,
                    'company_alias' => $request['company_alias'],
                    'mode' => $request['mode'],
                    'request' => json_encode($ckyc_request, JSON_UNESCAPED_SLASHES),
                    'response' => json_encode($response, JSON_UNESCAPED_SLASHES),
                    'headers' => json_encode($request_headers, JSON_UNESCAPED_SLASHES),
                    'endpoint_url' => $name,
                    'status' => ($verification_status == true) ? 'Success' : (!empty($not_a_failure_cases) ? 'not_a_failure' : 'Failed'),
                    'failure_message' => ($verification_status == true) ? null : $error_message,
                    'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                    'start_time' => date('Y-m-d H:i:s', $start_time / 1000),
                    'end_time' => date('Y-m-d H:i:s', $end_time / 1000),
                    // 'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2) . 's'
                    'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2)
                ]);
            } elseif (!empty($webservice)) {
	            $endTime = new DateTime(date('Y-m-d H:i:s'));
                $responseTime = $startTime->diff($endTime);
                $wsLogdata = [
                    'enquiry_id'     => $webservice['enquiryId'],
                    'product'       => $webservice['product'],
                    'section'       => $webservice['section'],
                    'method_name'   => $webservice['methodName'],
                    'company'       => $webservice['company'],
                    'method'        => $webservice['method'],
                    'transaction_type'    => $webservice['transactionType'],
                    'request'       => json_encode($body),
                    'response'      => json_encode($response),
                    'endpoint_url'  => $name,
                    'ip_address'    => request()->ip(),
                    'start_time'    => $startTime->format('Y-m-d H:i:s'),
                    'end_time'      => $endTime->format('Y-m-d H:i:s'),
                    // 'response_time'	=> $responseTime->format('%H:%i:%s'),
                    'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
                    'created_at'    => Carbon::now(),
                    'headers'       => json_encode($webservice['headers'])
                ];

				if($webservice['transactionType'] == 'quote') {
                    QuoteServiceRequestResponse::create($wsLogdata);
                } else {
                    WebServiceRequestResponse::create($wsLogdata);
                }
            } else {
                ThirdPartyApiRequestResponse::create([
                    "url" => $name,
                    "request"=> $body,
                    "response" => $response,
                    "headers" => $request_headers,
                    "response_headers" => $response_headers,
                    "options" => $options,
                    "response_time" => $response_time,
                    "http_status" => $status
                ]);
            }
        }
        return [
            'request' => $body,
            'status' => $status,
            'response' => $response,
            'response_headers' => $response_headers,
            'request_headers' => $headers,
            'url'=> $name
        ];
    }
}

if (!function_exists('file_url')) {
    function file_url($file)
    {
        if(config('filesystems.default') == 's3'){
            if (config('ENABLED_PRESIGNED_URL') == 'Y') {
                return createS3PresignedUrl($file);
            }
            return Storage::url($file) . '#';
        }
        return route('home', [\Illuminate\Support\Str::random(3) => customEncrypt($file, false)]);
    }
}

if (!function_exists('updateJourneyStage')) {
    function updateJourneyStage($data)
    {
        $journeyStage = JourneyStage::where('user_product_journey_id', $data['user_product_journey_id'])->first();
        if(isset($journeyStage->stage) && ($journeyStage->stage == STAGE_NAMES['POLICY_ISSUED']) && $data['stage'] != STAGE_NAMES['POLICY_CANCELLED']) 
        {
            return response()->json([
                "status" => true,
                "message" => 'Stage updated successfully!',
            ]);
        }

        // we don't need to update the journey stage to lower stages. - 22-01-2024
        $post_proposal_stages = array_map('strtolower', [ STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_APPROVED'], STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_REJECTED'], STAGE_NAMES['INSPECTION_REJECTED'], STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_CANCELLED']], );
        $pre_proposal_stages = array_map( 'strtolower', [ STAGE_NAMES['QUOTE'], STAGE_NAMES['PROPOSAL_DRAFTED'], STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['LEAD_GENERATION']] );
        $current_stage = strtolower($journeyStage?->stage ?? null);
        $incoming_stage = strtolower($data['stage']);

        if ( in_array( $current_stage, $post_proposal_stages ) && in_array( $incoming_stage, $pre_proposal_stages ) ) {
            return response()->json([
                'status' => false,
                'message' => 'It seems that there is a conflict while updating some data. Please refresh the page and try again.'
            ]);
        }
        
        JourneyStage::updateOrCreate(['user_product_journey_id' => $data['user_product_journey_id']], $data);

        $user_product_journey = UserProductJourney::find($data['user_product_journey_id']);

        if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y' && in_array($data['stage'], [ STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['POLICY_ISSUED']]))
        {
            $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

            if ($lsq_journey_id_mapping)
            {
                updateLsqOpportunity($data['user_product_journey_id']);
                createLsqActivity($data['user_product_journey_id']);
            }
        }

        $Calling = config('HANDSHAKE_API_CALLING');
        if($Calling == 'Y')
        {
            $ace_data = [
                'enquiryId' => $data['user_product_journey_id'],
                'stage'     => $data['stage']
            ];
            UpdateEnquiryStatusByIdAce($ace_data);
        }

        if (config('bajaj_crm_data_push') == "Y") {
            if( in_array( strtolower( $data['stage'] ), array_map('strtolower', [ STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['POLICY_ISSUED'] ] ) ) )
            {
                request()->merge(['userProductJourneyId' => customEncrypt($data['user_product_journey_id']) ]);
                $data["userProductJourneyId"] = customEncrypt($data['user_product_journey_id']);
                $data = (object)$data;
                bajajCrmDataUpdate($data);
            }
        }
        return response()->json([
            "status" => true,
            "message" => 'Stage updated successfully!',
        ]);
    }
}

if (!function_exists('customDecrypt')) {
    function customDecrypt($value, $type = true, $serialize = true, $ciper = 'aes-128-cbc')
    {
        if ($type) {
            if(config('enquiry_id_encryption') == 'Y'){
                return enquiryIdDecryption($value);
            }else {
                $date = Str::substr($value, 0, 8);
                if(!is_numeric($date)){
                    throw new \App\Exceptions\EnquiryIdDecryptionFailed('Enquiry ID Decryption Failed. Invalid enquiry ID - '.$value);
                }
                $date = Carbon::parse($date)->format('Y-m-d');
                $id = Str::substr($value, 8);
                $enquiry_id = cache()->remember($value, config('cache.expiration_time'), function() use($date, $id, $value) {
                    $id = UserProductJourney::whereDate('created_on', $date)->find($id);
                    if (empty($id)) {
                        throw new \App\Exceptions\EnquiryIdDecryptionFailed('Enquiry ID Decryption Failed. Invalid enquiry ID '.$value);
                    }
                    return $id;
                }); 
                abort_if(!$enquiry_id, 500);
                return $enquiry_id->user_product_journey_id;
            }
        } else {
            if (Str::startsWith($key = config('app.key'), $prefix = 'base64:')) {
                $key = base64_decode(Str::after($key, $prefix));
            }
            $tag = '';
            $iv = 'RTsQHVoJZtnPNGQZ'; //random_bytes(openssl_cipher_iv_length(strtolower($ciper)));
            $decrtypted_string =  openssl_decrypt($value, strtolower($ciper), $key, 0, $iv, $tag);
            return ($serialize ? unserialize($decrtypted_string) : $decrtypted_string);
        }
    }
}
if (!function_exists('customEncrypt')) {

    function customEncrypt($value, $type = true, $serialize = true, $ciper = 'aes-128-cbc')
    {
        if ($type) {
            if(config('enquiry_id_encryption') == 'Y'){
                return enquiryIdEncryption($value);
            }else{
                return cache()->remember($value, config('cache.expiration_time'), function () use ($value) {
                    $enquiry_id = UserProductJourney::select('created_on')->find($value);
                    if (empty($enquiry_id)) {
                        throw new \App\Exceptions\EnquiryIdEncryptionFailed('Enquiry ID Encryption Failed. Invalid enquiry Id.');
                    }
                    return \Carbon\Carbon::parse($enquiry_id->created_on)->format('Ymd') . sprintf('%08d', $value);
                });
            }
        } else {
            if (Str::startsWith($key = config('app.key'), $prefix = 'base64:')) {
                $key = base64_decode(Str::after($key, $prefix));
            }
            $tag = '';
            $iv = 'RTsQHVoJZtnPNGQZ'; //random_bytes(openssl_cipher_iv_length(strtolower($ciper)));
            return openssl_encrypt($serialize ? serialize($value) : $value, strtolower($ciper), $key, 0, $iv, $tag);

        }
    }
}

if (!function_exists('camelCase')) {
    function camelCase($payload)
    {
        $final_array = [];
        $is_quote_api = stripos(\Illuminate\Support\Facades\URL::current(), 'premiumCalculation') !== false;
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                if (is_array($value) || is_object($value)) {
                    $final_array[Str::camel($key)] = camelCase($value);
                } else if ($is_quote_api && is_numeric($value)) {
                    $final_array[Str::camel($key)] = (float) $value;
                } else {
                    $final_array[Str::camel($key)] = $value;
                }
                // $final_array[Str::camel($key)] = is_array($value) || is_object($value) ? camelCase($value) : $value;
            } else {
                if (is_array($value) || is_object($value)) {
                    $final_array[$key] = camelCase((array)$value);
                } else if ($is_quote_api && is_numeric($value)) {
                    $final_array[$key] = (float) $value;
                } else {
                    $final_array[$key] = $value;
                }
            }
        }

        return $final_array;
    }
}

if (!function_exists('snakeCase')) {
    function snakeCase($payload)
    {
        $final_array = [];
        foreach ($payload as $key => $value) {
            $final_array[Str::snake($key)] = $value;
        }
        return $final_array;
    }
}

if (!function_exists('generateToken')) {
    function generateToken()
    {
        return Str::random(50);
    }
}

if (!function_exists('getClientIpEnv')) {
    function getClientIpEnv()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
        } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        } elseif (getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
        } elseif (getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
}

if (!function_exists('shortUrl')) {
    function shortUrl($url)
    {
        if (empty($url)) {
            return [
                'status' => false,
                "message" => "url not found"
            ];
        }
        if (config('ENABLE_SHORT_URL') != 'Y') {
            $response['response']['short_url'] = $url;
            return $response;
        }
        switch (config('constants.motorConstant.SMS_FOLDER')) {
            case 'renewbuy':
                $shortUrl = httpRequest('renewbuyshortUrl', [
                    'url' => $url
                ], [], [], [], false, true);
                $shortUrl['response']['short_url'] = $shortUrl['response']['txtly'] ?? $url;
                break;

            case 'paytm':
                $shortUrl = httpRequest('paytmShortUrl', [
                    'encodedLongUrl' => $url
                ], [], [], [], true, false);
                $shortUrl['response']['short_url'] = $shortUrl['response']['shortUrl'] ?? $url;
                break;

            default:
                $shortUrl = httpRequest('shortUrl', [
                    'url' => $url
                ], [], [], [], [], false);
                break;
        }
        return $shortUrl;
    }
}

if (!function_exists('sendSMS')) {
    function sendSMS($request, $name, $type)
    {
        $mailController = new \App\Http\Controllers\Mail\MailController;
        try {
            switch (config('constants.motorConstant.SMS_FOLDER')) {
                case 'compare-policy':
                    if ($type == "policyGeneratedSms") {
                        return httpRequest('sms', [
                            "message" => "Dear {$name}, Thank you for choosing Compare Policy. Your transaction with \"{$request->productName}\" is completed. Your policy number is {$request->policyNumber}, your policy details have been sent to your email, please go through that carefully and contact us at help@comparepolicy.com if required.",
                            "mobile" => $request->mobileNo,
                        ]);
                    } elseif ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    }
                    break;

                case 'abibl':

                    $messageData = [
                        'To' => $request->mobileNo,
                    ];
                    if ($type == "shareQuotes") {
                        $link = shortUrl($request->link)['response']['short_url'];
                        abiblPhoneUpdate($request->mobileNo, customDecrypt($request->enquiryId));
                        $messageData['message'] = "Hi,Thank you for placing your insurance inquiry. Here are the quotes for your motor insurance {$link} -Aditya Birla Insurance Brokers Ltd.";
                    } elseif ($type == "policyGeneratedSms") {
                        $messageData['message'] = "You just bought your Motor insurance policy {$request->policyNumber}. Please contact us in case of any concern-Aditya Birla Insurance Brokers Ltd.";
                    } elseif ($type == "shareProposal") {
                        abiblPhoneUpdate($request->mobileNo, customDecrypt($request->enquiryId));
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['message'] = "Hi,Thank you for reaching out. Here is the proposal form for the selected plan -{$link} -Aditya Birla Insurance Brokers";
                    } elseif ($type == "proposalCreated") {
                        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['message'] = "Hi,Please click {$link} to pay the premium towards your proposal No. {$proposal->proposal_no}. Link will expire at midnight-Aditya Birla Insurance Brokers";
                    } elseif ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    }
                    return httpRequest('sms', $messageData);
                    break;

                case 'gramcover':
                    $messageData = array(
                        'to' => $request->mobileNo
                    );
                    if ($type == "shareQuotes") {
                        $section = $request->section ?? "Vehicle";
                        $messageData['message'] = "Dear {$name}, Thank you for {$section} details. We'll get back to you with suitable quotes. - GramCover Insurance Brokers PVT LTD";
                    } elseif ($type == "policyGeneratedSms") {

                        $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $messageData['message'] = "Dear {$name}, Thank you for choosing GramCover Insurance Brokers PVT
                        LTD. Your transaction with {$request->productName} is completed. Your policy number is
                        {$policy_details->policy_details->policy_number}, your policy details have been sent to your email, please go
                        through that carefully and contact us at ops@gramcover.com if required
                        - GramCover Insurance Brokers PVT LTD";
                    } elseif ($type == "shareProposal") {
                        $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $messageData['message'] = "";
                    } elseif ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    } elseif ($type == "proposalCreated") {
                        $messageData['message'] = "You had received a proposal from GramCover. Please click the link {$request->link} to make payment. - GramCover Insurance Brokers Private Limited";
                    }
                    return httpRequest('sms', $messageData);
                    break;
                case 'sriyah':
                    if ($type == "shareQuotes") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $message = "Hi {$name}, Thankyou for placing your insurance inquiry at nammacover.com. Here is the quote for the selected plan. Click {$request->link} or call " . config('constants.brokerConstant.tollfree_number')  . " for clarification. - Sriyah Insurance Brokers";
                        // $message="Hi {$name}, Thankyou for placing your insurance inquiry at nammacover.com. Here is the quote comparison for the selected plans. Click {$request->link} or call ". config('constants.brokerConstant.tollfree_number')  . " for clarification. - Sriyah Insurance Brokers";
                        return httpRequest('sms', [
                            "to" => '91' . $request->mobileNo,
                            "text" => $message
                        ]);
                    }

                    if ($type == "proposalCreated" || $type == "shareProposal") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $message = "Hi {$name}, Your previous transaction for insurance policy purchase through nammacover.com has failed or is pending. Click on {$request->link} to complete your transaction. - Sriyah Insurance Brokers";

                        return httpRequest('sms', [
                            "to" => '91' . $request->mobileNo,
                            "text" => $message
                        ]);
                    }
                    if ($type == "policyGeneratedSms") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $message = "Congrats, Payment of INR {$proposal->final_payable_amount} for {$request->productName} insurance purchased through nammacover.com is successful. Your policy no is {$request->policyNumber}. To view your policy details, click {$request->link} or call " . config('constants.brokerConstant.tollfree_number') . " - Sriyah Insurance Brokers";
                        return httpRequest('sms', [
                            "to" => '91' . $request->mobileNo,
                            "text" => $message
                        ]);
                    }

                    if ($type === "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    }
                    break;
                case "ace":
                    if ($type == "shareQuotes") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        return httpRequest('sms', [
                            'send_to' => (int)$request->mobileNo,
                            "msg" => "Dear {$name},Thank you for placing your insurance enquiry. Here is the quote comparison for your vehicle car. Click {$request->link} or call " . config('constants.brokerConstant.tollfree_number') . " for clarification -ACE Insurance Brokers"
                        ]);
                    }

                    if ($type == "Aceleadsms") {
                        return httpRequest('sms', [
                            'send_to' => (int)$request->mobileNo,
                            "msg" => "Dear Customer, Opt-in to ACE Insurance Whatsapp to get your insurance policy and more! Click https://tinyurl.com/4z3uvs64/ez1ew. -ACE Insurance Brokers"
                        ]);
                    }

                    if ($type == "policyGeneratedSms") {
                       return MailController::ace_whatsapp($request);
                    }

                    if ($type == "proposalCreated") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        if ($proposal->owner_type == 'C') {
                            $name = $proposal->first_name;
                        }
                        return httpRequest('sms', [
                            'send_to' => (int)$request->mobileNo,
                            "msg" => "Dear {$name}, Please click {$request->link} to pay the premium for your {$proposal->vehicale_registration_number} Vehicle policy, Proposal No. {$proposal->proposal_no}. Your Total Payable Amount is INR {$proposal->final_payable_amount}.Important: This link will expire at " . today()->format('d-m-Y') . " 23:59.-ACE Insurance Brokers"
                        ]);
                    }

                    if ($type == "renewalSms") {
                      MailController::renewalSMS($request);
                      return true;
                    }
                    break;
                case "pinc":
                    $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                    if ($type == "shareQuotes") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $messagedata = [
                            'send_to' => (int)$request->mobileNo,
                            "msg" => "Hi {$name},\nThank you for placing your insurance inquiry at PINC Tree. You can view your selected quote here. Click {$request->link} or call {$tollFreeNumber} for clarification. - PINC Tree"
                        ];
                    }
                    if ($type == "shareProposal") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $messagedata = [
                            'send_to' => (int)$request->mobileNo,
                            "msg" => "Hi {$name},\nThank you for placing your insurance inquiry at PINC Tree. Here is the proposal form for the selected plan. Click {$request->link} or call {$tollFreeNumber} for clarification. - PINC Tree"
                        ];
                    }
                    if ($type == "proposalCreated") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $messagedata = [
                            'send_to' => (int)$request->mobileNo,
                            "msg" => "Dear {$name},\nPlease click {$request->link} to pay the premium for your Vehicle policy. Proposal No. {$proposal->proposal_no}. Your Total Payable Amount is INR {$proposal->final_payable_amount}.\nImportant: This link will expire at " . today()->format('d-m-Y') . " 23:59. - PINC Tree"
                        ];
                    }
                    if ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    }

                    if ($type == "inspectionIntimation") {
                        $appName = config("app.name");
                        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                        $messagedata = [
                            'send_to' => (int)$request->mobileNo,
                            "msg" => "Dear {$name}, Your Inspection request on PINC Tree with {$user_proposal->ic_name} for vehicle {$user_proposal->vehicale_registration_number} has been raised with ID/Reference ID {$inspectionData->breakin_number} on {$appName}."
                        ];
                    }
                    return (isset($messagedata) ? httpRequest('sms', $messagedata) : "No Type Found");
                    break;
                case "renewbuy":
                    $messageData = [
                        "direct_mobile" => [$request->mobileNo],
                    ];
                    if ($type == "shareQuotes") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $messageData['context'] = [
                            "customer_name" => $request->firstName . ' ' . $request->lastName,
                            'link' => $request->link
                        ];
                        $messageData['event'] = "EV_motor_share_quotes_complete_page";
                    }
                    if ($type == "comparepdf") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $messageData['context'] = [
                            "customer_name" => $request->firstName . ' ' . $request->lastName,
                            'link' => $request->link
                        ];
                        $messageData['event'] = "EV_motor_compare_plans";
                    }
                    if ($type == "shareProposal") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $user_propopsal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $messageData['context'] = [
                            "insurer_name" => $user_propopsal->ic_name ?? '',
                            "customer_name" => $user_propopsal->first_name ?? $request->firstName . ' ' . $user_propopsal->last_name,
                            'link' => $request->link,
                        ];
                        $messageData['event'] = "EV_motor_proposal_page_to_be_filled_by_customer";
                    }
                    if ($type == "proposalCreated") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $user_propopsal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        if($agent_details){
                            $messageData['context'] = [
                                "advisor_name" => $agent_details->agent_name ?? '',
                                "customer_name" => $request->firstName . ' ' . $request->lastName,
                                'link' => $request->link,
                            ];
                            $messageData['event'] = "EV_motor_proposal_review_with_payment_link_new";
                        }
                        else{
                            return false;
                        }
                    }
                    if ($type == "premiumBreakuppdf") {
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $messageData['context'] = [
                            "insurer_name" => "",
                            "customer_name" => $request->firstName . ' ' . $request->lastName,
                            'link' => $request->link,
                        ];
                        $messageData['event'] = "EV_motor_premium_pdf_attached";
                    }
                    if ($type == "inspectionIntimation") {
                        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                        $messageData['context'] = [
                            "insurer" =>  substr($user_proposal->ic_name, 0, 25),
                            "customer_name" => $request->firstName . ' ' . $request->lastName,
                            'time_stamp' => !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
                            'registration_number' => $user_proposal->vehicale_registration_number,
                            'reference_id' => $inspectionData->breakin_number
                        ];
                        $messageData['event'] = "EV_inspection_req_raised-_motor_insurance";
                    }
                    if ($type == "inspectionApproval") {
                        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                        $messageData['context'] = [
                            "insurer" =>  substr($user_proposal->ic_name, 0, 25),
                            "customer_name" => $request->firstName . ' ' . $request->lastName,
                            'payment_amount' => $user_proposal->final_payable_amount,
                            'registration_number' => $user_proposal->vehicale_registration_number,
                            'reference_id' => $inspectionData->breakin_number,
                            'link' => shortUrl($inspectionData->breakin_check_url)['response']['short_url'] ?? ""
                        ];
                        $messageData['event'] = "EV_inspection_req_accepted-_motor_insurance";
                    }
                    if ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    }
                    return httpRequest('sms', $messageData);
                    break;
                case  "epoch":
                    $messageData = [
                        "dest" => "91" . $request->mobileNo
                    ];
                    if ($type == "shareQuotes") {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['msg'] = "Hi {$request->firstName},\nThank you placing your Insurance Inquiry at www.policylo.com. Here is the Quote comparison for the selected plans. Click {$link} or call " . config('constants.brokerConstant.tollfree_number')  . " for clarification. Team PolicyLo";
                    }

                    if ($type == "shareProposal") {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['msg'] = "Hi {$request->firstName},\nThank you for placing your Insurance Inquiry at www.policylo.com. Here is the proposal for the selected plan. Click {$link} or call " . config('constants.brokerConstant.tollfree_number')  . " for clarification. Team PolicyLo";
                    }

                    if ($type == "proposalCreated") {
                        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['msg'] = "Dear {$request->firstName}\nPlease click {$link} to pay the premium for your {$product_code} Vehicle Policy. Proposal No.{$user_proposal->proposal_no}. Your total payable premium amount is INR {$user_proposal->final_payable_amount}. Important: This link will expire at " . today()->endOfDay()->format('Y-m-d H:i:s') . " - Team PolicyLo";
                    }

                    return httpRequest('sms', $messageData);
                    break;
                case 'spa' :
                    $messageData['to'] = '91' . $request->mobileNo;
                    if ($type == "shareQuotes") {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['message'] =  "Hi {$request->firstName}, Thankyou for placing your insurance inquiry at InsuringAll. Here is the quote for the selected plan. Click {$link} or call " . config('constants.brokerConstant.tollfree_number')  . " for clarification.- SPA Insurance";
                    }

                    if ($type == "shareProposal") {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['message'] = "Hi {$request->firstName}, Thankyou for placing your insurance inquiry at InsuringAll. Here is the proposal form for the selected plan. Click {$link} or call " . config('constants.brokerConstant.tollfree_number')  . " for clarification.- SPA Insurance";
                    }

                    if ($type == 'proposalCreated'){
                        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                        $url = shortUrl($request->link);
                        $request->link = $url['response']['short_url'];
                        $messageData['message'] = "Hi {$request->firstName}, Please click {$request->link} to pay the premium for your {$proposal->vehicale_registration_number} vehicle policy. Proposal No. {$proposal->proposal_no}. Your total payable amount is INR {$proposal->final_payable_amount}. Important: This link will expire at ". now()->endOfDay() ." - SPA Insurance";
                    }
                    if ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    }
                    return httpRequest('sms', $messageData);
                    break;
                case 'bajaj':
                    $messageData['to'] = $request->mobileNo;
                    $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                    $expiryTime = today()->endOfDay()->format('Y-m-d H:i:s');

                    $name = strlen($name) <= 20 ? trim($name) : ($request->firstName ?? "Customer");

                            if ($type == "shareQuotes") {
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['text'] = "Thanks {$name} for using Bajaj Capital Insurance. Compare plans: {$link} or call {$tollFreeNumber} for help. Bajaj Capital Insurance Ltd";     //Dear {$name},\nThank you for trusting www.bajajcapitalinsurance.com for your motor protection. Here are the quote plans comparison for your latest policy search for motor insurance for the selected plans. Click {$link} or call {$tollFreeNumber} for clarification.\n-Bajaj Capital Insurance Broking Limited"; Old template 19-09-2024 #28835
                            }

                            if ($type == "shareProposal") {
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['text'] = "Dear {$name},\nThank you for trusting www.bajajcapitalinsurance.com for your motor protection. Here is the proposal form for the selected insurance plan. Click {$link} or call {$tollFreeNumber} for clarification & more details.\n-Bajaj Capital Insurance Broking Limited";
                            }

                            if ($type == "proposalCreated") {
                                $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                                $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                                $link = shortUrl($request->link)['response']['short_url'];
                                if(config('constants.brokerConstant.old_template_for_bajaj') == "Y"){
                                    $messageData['text'] = "Pay your {$product_code} policy premium of INR {$user_proposal->final_payable_amount} for Proposal No. {$user_proposal->proposal_no}. Link expires {$expiryTime}. Bajaj Capital Insurance Broking Ltd.";    //"Dear {$name},\nPlease click {$link} to pay the premium for your {$product_code} Vehicle policy. Proposal No. {$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}.\nImportant: This link will expire at {$expiryTime}\n-Bajaj Capital Insurance Broking Limited"; Old Template 19-09-2024 #28835
                                }
                                else {
                            $messagedata = [
                                "sms" => [
                                    "ver" => "2.0",
                                    "dlr" => [
                                        "url" => ""
                                    ],
                                    "messages" => [
                                        [
                                            "udh" => "0",
                                            "coding" => 1,
                                            "text" => "Pay your {$product_code} policy premium of INR {$user_proposal->final_payable_amount} for Proposal No. {$user_proposal->proposal_no}. Link expires {$expiryTime}. Bajaj Capital Insurance Broking Ltd.",
                                            "id" => "1",
                                            "addresses" => [
                                                [
                                                    "from" => "BAJINS",
                                                    "to" => '91' . $request->mobileNo,
                                                    "seq" => "1741007",
                                                    "tag" => "sample tag"
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                            return httpRequest('bajaj_new_sms', $messagedata);
                                }
                            }

                            if ($type == "paymentFailure") {
                                $link = shortUrl($request->reInitiate)['response']['short_url'];
                                if(config('constants.brokerConstant.old_template_for_bajaj') == "Y"){
                                $messageData['text'] = "Dear {$name}.Payment for your proposal failed. Retry here: {$link}. Bajaj Capital Insurance Broking Ltd";    //OLD TEMEPLATE"Dear {$name},\nThe payment against the said proposal has failed. Kindly retry the payment by clicking on the {$link} Bajaj Capital Insurance Broking Limited"; Old template 19-09-2024 #28835
                                } else {
                            $messagedata = [
                                "sms" => [
                                    "ver" => "2.0",
                                    "dlr" => [
                                        "url" => ""
                                    ],
                                    "messages" => [
                                        [
                                            "udh" => "0",
                                            "coding" => 1,
                                            "text" => "Dear {$name}, Payment for your proposal failed. Retry here: {$link} Bajaj Capital Insurance Broking Ltd",
                                            "id" => "1",
                                            "addresses" => [
                                                [
                                                    "from" => "BAJINS",
                                                    "to" => '91' . $request->mobileNo,
                                                    "seq" => "1741007",
                                                    "tag" => "sample tag"
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                            return httpRequest('bajaj_new_sms', $messagedata);
                                }
                            }

                            if ($type == "comparepdf") {
                                $link = shortUrl($request->link)['response']['short_url'];
                        if (config('constants.brokerConstant.old_template_for_bajaj') == "Y") {
                            $messageData['text'] = "Dear {$name}\n\nThank you for trusting www.bajajcapitalinsurance.com for your motor protection. Click on {$link} to view full quote comparison of your selected plans Call 1800 212 123123 for any clarification. Bajaj Capital Insurance Broking Limited";
                        } else {
                            $messagedata = [
                                "sms" => [
                                    "ver" => "2.0",
                                    "dlr" => [
                                        "url" => ""
                                    ],
                                    "messages" => [
                                        [
                                            "udh" => "0",
                                            "coding" => 1,
                                            "text" => "Thanks {$name} for using Bajaj Capital Insurance. Compare plans: {$link} or call 1800 212 123123 for help. Bajaj Capital Insurance Ltd",
                                            "property" => 0,
                                            "id" => "1",
                                            "addresses" => [
                                                [
                                                    "from" => "BAJINS",
                                                    "to" => '91' . $request->mobileNo,
                                                    "seq" => "1741007",
                                                    "tag" => "sample tag"
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                            return httpRequest('bajaj_new_sms', $messagedata);
                        }
                            }

                            if ($type == "otpSms") {
                                return $mailController->sendProposalPageOtp($request);
                            }
                            $allow_sms_template_array = explode(',',config('ALLOW_SMS_TEMPLATE'));
                            if(!in_array($type,$allow_sms_template_array))
                            {
                                return 1;
                            }

                        return httpRequest('sms', $messageData);
                    break;
                case 'uib':
                        $messageData['number'] = $request->mobileNo;
                        if ($type == "shareQuotes") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['message'] = 'Hi '.$request->firstName.', Thanks for placing your inquiry. Here is the quote comparison. Click '.$link.' to view. - ' . config("app.name");
                            $messageData['templateid'] = 1307165993696742845;
                        }

                        if ($type == "shareProposal") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['message'] = 'Hi '.$request->firstName.', Thanks for placing your inquiry. Here is the proposal form. Click '.$link.' to view. - ' . config("app.name");
                            $messageData['templateid'] = 1307165993710643765;
                        }

                        if ($type == "proposalCreated") {
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['message'] = "Hi {$request->firstName}, Please click {$link} to pay the premium for {$product_code} vehicle policy. Proposal No. {$user_proposal->proposal_no}. Total Amt is INR {$user_proposal->final_payable_amount}. Link expires at " . today()->endOfDay()->format('Y-m-d H:i:s') . ".UIBINS";
                            $messageData['templateid'] = 1307165424961720034;
                        }

                        if ($type == "otpSms") {
                            return $mailController->sendProposalPageOtp($request);
                        }
                        return httpRequest('sms', $messageData);
                    break;
                case 'ss' :
                    $messageData['dest'] = $request->mobileNo;
                    if ($type == "shareQuotes") {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['msg'] = "Hi {$name},\nThankyou for placing your insurance inquiry at BIMA PLANNER. Here is the quote for the selected plan. Click {$link} or call ". config('constants.brokerConstant.tollfree_number')  ." for clarification.\n- SS Insurance Brokers";
                    }
                    if ($type == "shareProposal" || $type == "shareProposal" || $type == "sharePayment" || $type == "proposalCreated") {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['msg'] = "Hi {$name},\nThankyou for placing your insurance inquiry at BIMA PLANNER. Here is the proposal form for the selected plan. Click {$link} or call ". config('constants.brokerConstant.tollfree_number')  ." for clarification.\n- - SS Insurance Brokers";
                    }
                    if ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    }
                    return httpRequest('sms', $messageData);
                    break;
                    case 'sib':
                        $appName = config("app.name");
                        $tollFreeNumber = str_replace(" ", "", config('constants.brokerConstant.tollfree_number'));
                        $messageData['msisdn'] = $request->mobileNo;
                        $expiryTime = today()->endOfDay()->format('Y-m-d H:i:s');
                        
                        if ($type == "shareQuotes") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['message'] = "Hi {$request->firstName}, Thankyou for placing your inquiry at {$appName}. Here is the multiple quotes for the selected plans. Click {$link} or call {$tollFreeNumber} for clarification. - SIBINS";
                        }

                        if ($type == "shareProposal") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['message'] = "Hi {$request->firstName}, Thankyou for placing your inquiry at {$appName}. Here is the proposal form for the selected plan. Click {$link} or call {$tollFreeNumber} for clarification. - SIBINS";
                        }

                        if ($type == "proposalCreated") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                            $messageData['message'] = "Dear {$request->firstName}, Please click {$link} to pay the premium for your {$product_code} policy. Proposal No. {$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}. Important: This link will expire at {$expiryTime} - SIBINS";
                        }

                        if ($type == "otpSms") {
                            return $mailController->sendProposalPageOtp($request);
                        }

                        if ($type == "inspectionIntimation") {
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                            $short_ic = substr($user_proposal->ic_name, 0, 25);
                            $messageData['message'] = "Dear {$request->firstName} Your Inspection request with {$short_ic} for vehicle reg no {$user_proposal->vehicale_registration_number}. is raised with ID/Reference ID {$inspectionData->breakin_number} on {$appName} - SIBINS";
                        }

                    return httpRequest('sms', $messageData);
                    break;
                    case 'tmibasl':
                        $appName = config("app.name");
                        $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                        $messageData['To'] = "91" . $request->mobileNo;
                        $expiryTime = today()->endOfDay()->format('d/m/Y H:i');

                    if ($type == "shareQuotes") {
                        $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);

                        if (!in_array($product_code, ["PCV", "GCV"])) {
                            $messageData['Text'] =  "Dear {$request->firstName}, thank you for choosing Lifekaplan. Please click on the link to compare products for Motor Insurance. Click {$request->link}\nTeam Lifekaplan -TMIBASL";
                        }else{
                            $messageData['Text'] = "Dear {$request->firstName}, thank you for choosing Lifekaplan. Please click on the link to compare products for Commercial Vehicle Insurance. Click {$request->link}\nTeam Lifekaplan -TMIBASL";
                        }
                    }

                    if ($type == "shareProposal") {
                        $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);

                        if (!in_array($product_code, ["PCV", "GCV"])) {
                            $messageData['Text'] =  "Dear {$request->firstName}, thank you for choosing Lifekaplan. Please click on the link to share proposal for Motor Insurance. Click {$request->link}\nTeam Lifekaplan -TMIBASL";
                        }else{
                            $messageData['Text'] = "Dear {$request->firstName}, thank you for choosing Lifekaplan. Please click on the link to share proposal for Commercial Vehicle Insurance. Click {$request->link}\nTeam Lifekaplan -TMIBASL";
                        }
                    }

                        if ($type == "proposalCreated") {
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                            $messageData['message'] = "Dear {$request->firstName},\nPlease click {$request->link} to pay the premium for your {$product_code} Vehicle policy. Proposal No. {$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}.\nImportant: This link will expire at {$expiryTime} - TMIBASL";
                        }

                        if ($type == "otpSms") {
                            return $mailController->sendProposalPageOtp($request);
                        }

                        if ($type == "inspectionIntimation") {
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                            $inspectionRaisedDate = !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y');
                            $messageData['Text'] = "Dear {$request->firstName}, Your Inspection request with {$user_proposal->ic_name} for vehicle reg no {$user_proposal->vehicale_registration_number} is raised with ID/Reference ID {$inspectionData->breakin_number} on {$inspectionRaisedDate} -TMIBASL";
                        }

                        if($type === "premiumBreakuppdf"){
                            $messageData['Text'] = "Hi {$request->firstName},\nThank you for placing your insurance inquiry at www.lifekaplan.com.  Please click here to view the premium break-up for the selected quote. Click {$request->link} or call {$tollFreeNumber} for clarification.  - TMIBASL";
                        }

                    return httpRequest("sms", $messageData);
                    break;
                    case 'policy-era':
                        $appName = config("app.name");
                        $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                        $messageData['mobile'] = $request->mobileNo;
                        $expiryTime = today()->endOfDay()->format('d/m/Y H:i');
                        
                        if ($type === "shareQuotes") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['message'] = "Dear {$request->firstName},\nThankyou for placing your insurance inquiry at www.policyera.com. Here is the quote comparison for the selected plans. Click {$link} or call {$tollFreeNumber} for clarification\nPolicy Era Insurance Broking";
                        }

                        if ($type === "shareProposal") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['message'] = "Dear {$request->firstName},\nThankyou for placing your insurance inquiry at www.policyera.com. Here is the proposal form for the selected plan. Click {$link} or call {$tollFreeNumber} for clarification\nPolicy Era Insurance Broking";
                        }

                        if ($type === "proposalCreated") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                            $user_proposal->proposal_no = $user_proposal->proposal_no;
                            $messageData['message'] = "Dear {$request->firstName},\nPlease click {$link} to pay the premium for your {$product_code} Vehicle policy. Proposal No. {$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}.\nImportant: This link will expire at {$expiryTime}\nPolicy Era Insurance Broking";
                        }

                        if ($type === "otpSms") {
                            return $mailController->sendProposalPageOtp($request);
                        }


                    return httpRequest('sms', $messageData);
                    break;
                    case 'shree':
                        $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                        $messageData['ph'] = $request->mobileNo;
                        $expiryTime = today()->endOfDay()->format('d/m/Y H:i');
                        
                        if ($type == "shareQuotes") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['text'] = "Hi {$request->firstName}, Thank you for placing your insurance inquiry at shreeinsure.com. Here is the quote comparison for the the selected plans. Click {$link} or call {$tollFreeNumber} for clarification. - Lakshmishree";
                        }

                        if ($type === "shareProposal") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['text'] = "Hi {$request->firstName}, Thank you for placing your insurance inquiry at shreeinsure.com. Here is the proposal form for the selected plan. Click {$link} or call {$tollFreeNumber} for clarification. - Lakshmishree";
                        }

                        if ($type == "proposalCreated") {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                            $messageData['text'] = "Dear {$request->firstName}, please click {$link} to pay the premium for your {$product_code} Vehicle policy. Proposal No. {$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}. Important: This link will expire at {$expiryTime} - Lakshmishree";
                        }

                        if ($type == "otpSms") {
                            return $mailController->sendProposalPageOtp($request);
                        }

                        if ($type == "inspectionIntimation") {
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                            $inspectionRaisedDate = !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y');
                            $ic_name = substr($user_proposal->ic_name, 0, 25);
                            $messageData['text'] = "Dear {$request->firstName}, Your Inspection request with {$ic_name} for vehicle reg no {$user_proposal->vehicale_registration_number}. is raised with ID/Reference ID {$inspectionData->breakin_number} on {$inspectionRaisedDate}. Lakshmishree";
                        }
                    return httpRequest("sms", $messageData);
                    break;

                    case 'hero':
                        $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                        $incommingTreaceId = $request->enquiryId;
                        $fullTraceId = is_numeric($incommingTreaceId) && strlen($incommingTreaceId) == 16 ? Str::substr($incommingTreaceId, 8) : customDecrypt($incommingTreaceId);
                        $user_proposal = UserProposal::where('user_product_journey_id', $fullTraceId)->first();
                        $messageData['send_to'] = $request->mobileNo;
                        if ($type == "otpSms") {
                            return $mailController->sendProposalPageOtp($request);
                        } elseif ($type == 'shareQuotes') {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['msg'] = "Dear Customer,\nThank you for trusting us for your motor insurance. Click {$link} to view your selected quotes or call {$tollFreeNumber} for clarification.
                            \nTeam Hero Insurance Broking India";

                        } elseif ($type == 'comparepdf') {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $messageData['msg'] = "Dear Customer,
                            \nThank you for trusting us for your motor insurance. Click {$link} to view your selected quotes or call {$tollFreeNumber} for clarification.
                            \nTeam Hero Insurance Broking India";
                        } elseif ($type == 'proposalCreated') {
                            $link = shortUrl($request->link)['response']['short_url'];
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                            
                            $messageData['msg'] = "Dear Customer,\n
                            Please click {$link} to pay INR {$user_proposal->final_payable_amount} premium for your motor policy.\n
                            Team Hero Insurance Brokers";
                        
                        } elseif ($type == 'inspectionIntimation' && $user_proposal->ic_id == 30) {
                            $messageData = ['send_to' => $request->mobileNo,
                            'msg' => "Dear Customer, Please click {$request->link} to proceed for inspection of your vehicle with Cholla Mandalam. \nBreak-in ID No {$request->inspectionNo}. Follow insurer's instructions to finish the process.
                                                \nTeam HIBIPL",
                                        ];
                        } elseif ($type == 'policyGeneratedSms') {
                            $messageData['msg'] = "Dear {$name}, Thank you for choosing Compare Policy. Your transaction with \"{$request->productName}\" is completed. Your policy number is {$request->policyNumber}, your policy details have been sent to your email, please go through that carefully and contact us at help@comparepolicy.com if required.";
                        }
                        return httpRequest('sms', $messageData);
                        break;

                    case 'paytm':
                        $messageData['to'] = $request->mobileNo;
                        switch ($type) {
                            case 'otpSms':
                                return $mailController->sendProposalPageOtp($request);
                                break;
                            case 'shareQuotes':
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['text'] = "Hi {$request->firstName},
                                Thankyou for placing your insurance inquiry with Paytm Insurance. Here is the quote comparison for the selected plans. Click {$link} for clarification.
                                -PIBPL";
                                $messageData['template_id'] = '1107171324301721070';
                                break;

                            case 'shareProposal':
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['text'] = "Hi {$request->firstName},
                                Thankyou for placing your insurance inquiry with Paytm Insurance. Here is the proposal form for the selected plan. Click {$link} for clarification.
                                -PIBPL";
                                $messageData['template_id'] = '1107171324310319324';
                                break;

                            case 'proposalCreated':
                                $user_proposal = UserProposal::select('proposal_no', 'final_payable_amount')
                                ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                                ->first();
                                $product_code = get_parent_code(
                                    \App\Models\CorporateVehiclesQuotesRequest::select('product_id')
                                    ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                                    ->first()
                                    ->product_id
                                );
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['text'] = "Dear {$request->firstName},
                                    Please click {$link} to pay the premium for your {$product_code} Vehicle policy & Proposal No. {$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}.
                                    Important: This link will expire at " . today()->format('d-m-Y') . " 23:59
                                    -PIBPL";
                                $messageData['template_id'] = '1107171324313917649';
                                break;

                            case 'inspectionIntimation':
                                $user_proposal = UserProposal::select('user_proposal_id', 'ic_name', 'vehicale_registration_number')
                                ->where(
                                        'user_product_journey_id',
                                        customDecrypt($request->enquiryId)
                                    )
                                    ->first();
                                $inspectionData = CvBreakinStatus::select('created_at', 'breakin_number')
                                ->where("user_proposal_id", $user_proposal->user_proposal_id)
                                ->first();
                                $inspectionRaisedDate = !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y');
                                $ic_name = substr($user_proposal->ic_name, 0, 25);
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['text'] = "Dear  {$request->firstName}, Your Inspection request with {$ic_name} for vehicle reg no {$user_proposal->vehicale_registration_number}. is raised with ID/Reference ID {$inspectionData->breakin_number} on {$inspectionRaisedDate}.
                                    -PIBPL";
                                $messageData['template_id'] = '1107171324321510007';
                                break;

                            case 'inspectionApproval':
                                $user_proposal = UserProposal::select('ic_name', 'vehicale_registration_number', 'final_payable_amount')
                                ->where(
                                        'user_product_journey_id',
                                        customDecrypt($request->enquiryId)
                                    )
                                    ->first();
                                $ic_name = substr($user_proposal->ic_name, 0, 25);
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['text'] = "Dear {$request->firstName}, Your Inspection request with {$ic_name} for vehicle reg no. {$user_proposal->vehicale_registration_number}  is approved. Kindly click on the link {$link} for the payment of Rs. {$user_proposal->final_payable_amount}.
                                    -PIBPL";
                                $messageData['template_id'] = '1107171324325142488';
                                break;
                        }
                        return httpRequest('sms', $messageData);
                        break;
                        case 'WhiteHorse':
                            $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                            $brokerUrl = config('constants.brokerConstant.SMS_TEMPLATE_BROKER_URL');
                            $messageData['send_to'] = $request->mobileNo;
                            if ($type == "otpSms")
                            {
                                return $mailController->sendProposalPageOtp($request);
                            }
                            elseif ($type == 'shareQuotes')
                            {
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['msg'] = "Hi {$request->firstName}, Thankyou for placing your insurance inquiry at {$brokerUrl}. Here is the quote comparison for the selected plans. Click {$link} or call {$tollFreeNumber} for clarification. WHITE HORSE INSURANCE BROKER PRIVATE LIMITED";
                            }
                            elseif ($type == 'comparepdf')
                            {
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['msg'] = "Hi {$request->firstName}, Thankyou for placing your insurance inquiry at {$brokerUrl}. Here is the quote comparison for the selected plans. Click {$link} or call {$tollFreeNumber} for clarification. WHITE HORSE INSURANCE BROKER PRIVATE LIMITED";
                            }
                            elseif ($type == 'shareProposal')
                            {
                                $link = shortUrl($request->link)['response']['short_url'];
                                $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                                $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                                $messageData['msg'] = "Hi {$request->firstName}, Thankyou for placing your insurance inquiry at {$brokerUrl}. Here is the proposal form for the selected plan. Click {$link} or call {$tollFreeNumber} for clarification. WHITE HORSE INSURANCE BROKER PRIVATE LIMITED";
                            }
                            elseif ($type == 'proposalCreated')
                            {
                                $user_proposal = UserProposal::select('proposal_no', 'final_payable_amount')
                                ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                                ->first();
                                $product_code = get_parent_code(
                                    \App\Models\CorporateVehiclesQuotesRequest::select('product_id')
                                    ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                                    ->first()
                                    ->product_id
                                );
                                $link = shortUrl($request->link)['response']['short_url'];
                                $expiry_payment = today()->format('d-m-Y');
                                $messageData['msg'] = "Dear {$request->firstName}, Please click {$link} to pay the premium for your {$user_proposal->vehicale_registration_number} Vehicle policy. Proposal No.{$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}. Important: This link will expire at {$expiry_payment} - 12:00 AM - WHITE HORSE INSURANCE BROKER PRIVATE LIMITED";
                            }
                            elseif ($type == 'inspectionIntimation')
                            {
                                $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                                $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                                $inspectionRaisedDate = !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y');
                                $short_ic = substr($user_proposal->ic_name, 0, 25);
                                $messageData['msg'] = "Dear {$request->firstName}, Your Inspection request with {$short_ic} for vehicle reg no {$user_proposal->vehicale_registration_number}. is raised with ID/Reference ID {$inspectionData->breakin_number} on {$inspectionRaisedDate}. -WHITE HORSE INSURANCE BROKER PRIVATE LIMITED";
                            }
                            else if ($type == 'inspectionApproval')
                            {
                                $user_proposal = UserProposal::select('ic_name', 'vehicale_registration_number', 'final_payable_amount')
                                ->where(
                                        'user_product_journey_id',
                                        customDecrypt($request->enquiryId)
                                    )
                                    ->first();
                                $ic_name = substr($user_proposal->ic_name, 0, 25);
                                $link = shortUrl($request->link)['response']['short_url'];
                                $messageData['msg'] = "Dear {$request->firstName}, Your Inspection request with {$ic_name} for vehicle reg no. {$user_proposal->vehicale_registration_number} is approved. Kindly click on the link {$link} for the payment of Rs. {$user_proposal->final_payable_amount}. WHITE HORSE INSURANCE BROKER PRIVATE LIMITED";
                            }
                            return httpRequest('sms', $messageData);
                            break;
                case 'Atelier':
                    $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                    $brokerUrl = config('constants.brokerConstant.SMS_TEMPLATE_BROKER_URL');
                    $messageData['send_to'] = $request->mobileNo;
                    if ($type == "otpSms") {
                        return $mailController->sendProposalPageOtp($request);
                    } elseif (in_array($type, [
                        'shareQuotes',
                        'comparepdf'
                    ])) {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['msg'] = "Hi {$request->firstName}, Thank you for your insurance inquiry. Here is the quote comparison for the selected plans. Click {$link} or call {$tollFreeNumber} for clarification. Regards {$brokerUrl} -InstantBeema";
                    } elseif ($type == 'shareProposal') {
                        $link = shortUrl($request->link)['response']['short_url'];
                        $messageData['msg'] = "Hi {$request->firstName}, Thank you for your insurance inquiry. Here is the proposal form for the selected plan. Click {$link} or call {$tollFreeNumber} for clarification. Regards Instant Beema {$brokerUrl} -InstantBeema";
                    } elseif ($type == 'proposalCreated') {
                        $user_proposal = UserProposal::select('proposal_no', 'final_payable_amount')
                        ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->first();
                        $link = shortUrl($request->link)['response']['short_url'];
                        $expiry_payment = today()->format('d-m-Y');
                        $messageData['msg'] = "Dear {$request->firstName}, Please click {$link} to pay the premium for your {$user_proposal->vehicale_registration_number} Vehicle policy. Proposal No.{$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}. Important: This link will expire at {$expiry_payment} - 12:00 AM Regards Instant Beema {$brokerUrl} -InstantBeema";
                    }
                    return httpRequest('sms', $messageData);
                    break;
                case 'OneClick':
                    $shareEvents = ['comparepdf', 'shareQuotes'];
                    $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
                    if($type == "otpSms")
                    {
                        return $mailController->sendProposalPageOtp($request);
                    }
                    elseif ($type == 'shareProposal') {
                        // $link = shortUrl($request->link)['response']['short_url'];
                        // $link = substr($link, 8); // removw https://
                        $messageData['url'] = $request->link;
                        $messageData['receiver'] = $request->mobileNo;
                        $messageData['tempid'] = 1707174072456123628;
                        $messageData['sms'] = "Hi {$request->firstName}, Thank you for your insurance inquiry. Here is the proposal form for the selected plan. Click {{url}} or call {$tollFreeNumber} for clarification. Regards Team 1Clickpolicy(SIBL) SWASTIKA";
                    }
                    elseif ($type == 'proposalCreated') {
                        $user_proposal = UserProposal::select('proposal_no', 'final_payable_amount')
                        ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->first();
                        // $link = shortUrl($request->link)['response']['short_url'];
                        // $link = substr($link, 8); // removw https://
                        $messageData['url'] = $request->link;
                        $messageData['receiver'] = $request->mobileNo;
                        $messageData['tempid'] = 1707173839466328539;
                        $expiry_payment = today()->format('d-m-Y');
                        $messageData['sms'] = "Dear {$request->firstName}, Please click {{url}} to pay the premium for your {$user_proposal->vehicale_registration_number} Vehicle policy. Proposal No. {$user_proposal->proposal_no}. Your Total Payable Amount is INR {$user_proposal->final_payable_amount}. Important: This link will expire at {$expiry_payment} Regards Team 1Clickpolicy(SIBL) SWASTIKA";
                    }
                    elseif(in_array($type, $shareEvents))
                    {
                        // $link = shortUrl($request->link)['response']['short_url'];
                        // $link = substr($link, 8); // removw https://
                        $messageData['url'] = $request->link;
                        $messageData['receiver'] = $request->mobileNo;
                        $messageData['tempid'] = 1707173839476559515;
                        $messageData['sms'] = "Hi {$request->firstName}, Thank you for your insurance inquiry. Here is the quote comparison for the selected plans. Click {{url}} or call {$tollFreeNumber} for clarification. Regards Team 1Clickpolicy(SIBL) SWASTIKA";

                    }
                    return httpRequest('sms', $messageData);


            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}

if (!function_exists('sendOtpViaSmtp')) {
    function sendOtpViaSmtp($request, $user_proposal, $otp){
        $cc_email = config('constants.motorConstant.CC_EMAIL');
        $mailData = [
            'title' => "OTP for your {$request->productName} - " . config('app.name'),
            'name' => $user_proposal->first_name . ' ' . $user_proposal->last_name,
            'proposal_number' => $user_proposal->proposal_no,
            'otp' => $otp,
            'logo' => $request->logo
        ];
        \Illuminate\Support\Facades\Mail::to($request->emailId)->bcc($cc_email)->send(new \App\Mail\SendOtpEmail($mailData));

        $html_body = (new \App\Mail\SendOtpEmail($mailData))->render();

        if ($html_body) {
            \App\Models\MailLog::create([
                "email_id" => (is_array($request->emailId) ? json_encode($request->emailId) : $request->emailId),
                "mobile_no" => $request->mobileNo,
                "first_name" => $user_proposal->first_name,
                "last_name" => $user_proposal->last_name,
                "subject" => $mailData['title'] ?? "",
                "mail_body" => $html_body ?? "",
                "enquiryId" => $user_proposal->user_product_journey_id,
            ]);
        }

    }
}

if (!function_exists('aceWhatsapp')) {
    function aceWhatsapp($mobileNo)
    {
    return httpRequest('whatsapp', [
        'phone_number'=>"91".$mobileNo
    ]);
    }
}

if (!function_exists('getQuotation')) {
    function getQuotation($enquiryId)
    {
        $quoteData = DB::table('user_product_journey as upj')
            ->join('corporate_vehicles_quotes_request as cvqr', 'upj.user_product_journey_id', '=', 'cvqr.user_product_journey_id')
            ->where('upj.user_product_journey_id', $enquiryId)
            ->first();

        if (!empty($quoteData)) {
            return $quoteData;
        }

        return FALSE;
    }
}

if (!function_exists('getProductDataByIc')) {
    function getProductDataByIc($policyId)
    {
        $productData = DB::table('master_policy as mp')
            ->join('master_company as mc', 'mc.company_id', '=', 'mp.insurance_company_id')
            ->join('master_product_sub_type as mpst', 'mpst.product_sub_type_id', '=', 'mp.product_sub_type_id')
            ->leftjoin('master_product as mpo', 'mpo.master_policy_id', 'mp.policy_id')
            ->join('master_premium_type as mpt', 'mpt.id', '=', 'mp.premium_type_id')
            ->where('mp.policy_id', $policyId)
            ->select('mp.policy_id', 'mp.product_sub_type_id', 'mp.premium_type_id','mpst.status as product_sub_type_status', 'mp.zero_dep', 'mp.is_premium_online', 'mp.is_proposal_online', 'mp.is_payment_online', 'mc.company_id', 'mc.company_alias', 'mp.default_discount', 'mp.status', 'mc.company_name', 'mc.logo', 'mc.company_id', 'mpst.parent_id', 'mpst.product_sub_type_code', 'mpst.product_sub_type_name', 'mpo.product_name', 'mpo.product_identifier', 'mp.good_driver_discount','mpt.premium_type_code','mp.tenure')
            ->first();

        if (!empty($productData)) {
            $productData->policy_no = '';
            $productData->sum_insured = '';
            $productData->corp_client_id = '';
            return $productData;
        }

        return false;
    }
}

if (!function_exists('getGenderTypeMapping')) {
    function getGenderTypeMapping($gender, $field)
    {
        $getGender = DB::table('gender_mapping')
            ->where('gender', $gender)
            ->select($field)
            ->first();
        return isset($getGender->$field) ? $getGender->$field : "";
    }
}

if (!function_exists('getOccupationTypeMapping')) {
    function getOccupationTypeMapping($occupation, $field)
    {
        $getOccupation = DB::table('occupation_mapping')
            ->where('occupation', $occupation)
            ->select($field)
            ->first();
        return isset($getOccupation->$field) ? $getOccupation->$field : "";
    }
}

if (!function_exists('getMaritalStatusTypeMapping')) {
    function getMaritalStatusTypeMapping($maritalStatus, $field)
    {
        $getMaritalStatus = DB::table('marital_status_mapping')
            ->where('marital_status', $maritalStatus)
            ->select($field)
            ->first();
        return isset($getMaritalStatus->$field) ? $getMaritalStatus->$field : "";
    }
}

if (!function_exists('getCommonTppd')) {
    function getCommonTppd($request)
    {
        $vehicle_cc = $request['vehicle_cc'] ?? "";
        $policyStartDate = $request['policyStartDate'] ?? "";
        $applicable_year = $request['applicable_year'] ?? "1";
        $product_sub_type_id = $request['product_sub_type_id'] ?? "";

        if ($request['product_sub_type_id'] == 1 || $request['product_sub_type_id'] == 2) {
            $getCommonTppd = DB::table('motor_tppd')
                ->where('product_sub_type_id', $product_sub_type_id)
                ->where('applicable_year', $applicable_year)
                ->whereRaw($vehicle_cc . ' between min_cc and max_cc')
                ->whereRaw($policyStartDate . ' between effective_todate and effective_fromdate')
                ->select('*')
                ->get();
        } else if ($request['product_sub_type_id'] == 6) {
            $getCommonTppd = DB::table('pccv_tppd')
                ->where('product_sub_type_id', $product_sub_type_id)
                ->whereRaw($vehicle_cc . ' between cc_min and cc_max')
                ->whereRaw("'" . $policyStartDate . "' between effective_fromdate and effective_todate")
                ->select('*')
                ->get();

            $getCommonTppd = $getCommonTppd[0];
        }

        return $getCommonTppd;
    }
}

if (!function_exists('getMasterPolicy')) {
    function getMasterPolicy($policy_id, $policy_no, $corp_client_id, $ic_id, $product_sub_type_id)
    {
        $master_policy_id = DB::table('master_policy AS a')
            ->join('master_company AS c', 'a.insurance_company_id', '=', 'c.company_id')
            ->join('master_product_sub_type AS d', 'a.product_sub_type_id', '=', 'd.product_sub_type_id');

        if ($policy_id != '' && $policy_id != 0) {
            $master_policy_id = $master_policy_id->where('a.policy_id', $policy_id);
        }

        if ($policy_no != '' && $policy_no != 0) {
            $master_policy_id = $master_policy_id->where('a.policy_no', $policy_no);
        }

        if ($corp_client_id != '' && $corp_client_id != 0) {
            $master_policy_id = $master_policy_id->where('a.corp_client_id', $corp_client_id);
        }

        if ($ic_id != '' && $ic_id != 0) {
            $master_policy_id = $master_policy_id->where('a.insurance_company_id', $ic_id);
        }

        if ($product_sub_type_id != '' && $product_sub_type_id != 0) {
            $master_policy_id = $master_policy_id->where('a.product_sub_type_id', $product_sub_type_id);
        }

        $master_policy_id = $master_policy_id->select('a.policy_id', /* 'a.policy_start_date', 'a.policy_end_date', 'a.sum_insured',  'a.corp_client_id',*/ 'a.product_sub_type_id', 'a.insurance_company_id', 'a.status', 'c.company_name', 'c.logo', 'd.product_sub_type_name', 'a.default_discount AS flat_discount', /* 'a.predefine_series',  */'a.is_premium_online', 'a.is_proposal_online', 'a.is_payment_online');

        return $master_policy_id->get();
    }
}

if (!function_exists('getRelationshipMapping')) {
    function getRelationshipMapping($ownerDriverNomineeRelationship, $companyAlias)
    {
        $nominee_relationship = DB::table('relationship_mapping')
            ->where('nominee_relationship', $ownerDriverNomineeRelationship)
            ->select($companyAlias)
            ->first();

        return isset($nominee_relationship->$companyAlias) ? $nominee_relationship->$companyAlias : '';
    }
}

if (!function_exists('getPreviousInsurer')) {
    function getPreviousInsurer($previousInsurerCompany, $companyAlias)
    {
        $previous_insurer = DB::table('previous_insurer_mapping')
            ->where('previous_insurer', $previousInsurerCompany)
            ->select($companyAlias)
            ->first();

        return isset($previous_insurer->companyAlias) ? $previous_insurer->companyAlias : '';
    }
}

if (!function_exists('get_date_diff')) {
    /**
     * @param string $return like year, month or day
     * @param string $date1  should be in format of 'dd-mm-yyyy'
     * @param string $date2  should be in format of 'dd-mm-yyyy' and '' as default if blank
     *
     * @return mixed
     */
    function get_date_diff($return, $date1, $date2 = '')
    {
        $datetime1 = new DateTime($date1);
        $datetime2 = new DateTime($date2);
        $difference = $datetime1->diff($datetime2);
        $diff = false;
        switch (strtolower($return)) {
            case 'year':
                $diff = $difference->y;
                break;
            case 'month':
                $diff = ($difference->y * 12) + $difference->m;
                break;
            case 'day':
                $diff = $difference->days;
                break;
        }
        if ($datetime1 > $datetime2) {
            $diff = $diff * (-1);
        }

        return $diff;
    }
}

if (!function_exists('trim_array')) {
    function trim_array($arr)
    {
        $result = [];
        foreach ($arr as $key => $val) {
            $result[$key] = (is_array($val) ? trim_array($val) : trim($val));
        }

        return $result;
    }
}

if (!function_exists('getUUID')) {
    function getUUID($trace_id = null)
    {    
        try {
            $data = random_bytes(16);
        } catch (\Exception $e) {
            $data = openssl_random_pseudo_bytes(16);
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $generated_UUID = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        if(!empty($trace_id) && app()->environment() != 'live') {
            // Retrive correlation ID from DB table
            $user_data = \App\Models\UserJourneyAdditionalData::where('company_alias', 'icici_lombard')
                ->where('enquiry_id', $trace_id)
                ->first();
            if(empty($user_data)) {
                \App\Models\UserJourneyAdditionalData::create([
                    'company_alias' => 'icici_lombard',
                    'enquiry_id' => $trace_id,
                    'unique_value' => $generated_UUID
                ]);
            } else {
                $generated_UUID = $user_data->unique_value;
            }
        }
        return $generated_UUID;
    }
}

if (!function_exists('policyProductType')) {
    function policyProductType($policy_id = null)
    {
        return MasterPolicy::where('policy_id', $policy_id)
            ->join('master_product_sub_type', 'master_product_sub_type.product_sub_type_id', '=', 'master_policy.product_sub_type_id')
            ->first();
    }
}

if (!function_exists('get_mmv_details')) {
    function get_mmv_details($productData, $version_id, $ic_name, $gcv_carrier_type=NULL)
    {
        ini_set('memory_limit', '1024M');
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
        //$product_sub_type_id = [1, 2, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16];
        if (in_array($productData->product_sub_type_id, $product_sub_type_id)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }

            $product = [
                '1'  => 'motor',
                '2'  => 'bike',
                '5'  => 'pcv',
                '6'  => 'pcv',
                '7'  => 'pcv',
                '9'  => 'gcv',
                '10' => 'pcv',
                '11' => 'pcv',
                '12' => 'pcv',
                '13' => 'gcv',                
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
                '17' => 'misc',
                '18' => 'misc',
            ];
            $product = $product[$productData->product_sub_type_id];
            if (in_array($ic_name, explode(',', config(strtoupper($product) . '_ICS_USING_UAT_MMV')))) {
                $ic_name .= '_uat';
            }
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_model_version.json';
            //$version_data = json_decode(file_get_contents($file_name), true);
            $version_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $mmv_code = '';
            $no_of_wheels = '0';
            $fyntune_version =[];
            $category = $gcv_carrier_type ? ($gcv_carrier_type == 'PUBLIC' ? 'a1' : 'a2') : '';

          if (!empty($version_data)) {
                foreach ($version_data as $version) {
                    if ($version['version_id'] == $version_id) {
                        if (in_array($ic_name, ['iffco_tokio', 'reliance', 'tata_aig_v2']) && $product == 'gcv') {
                        if ($gcv_carrier_type == 'PUBLIC') {
                            switch ($ic_name) {
                                case 'iffco_tokio' :
                                    if (isset($version['mmv_iffco_a1_public']) && $version['mmv_iffco_a1_public'] != 'null' && $version['mmv_iffco_a1_public'] != null) {

                                        $mmv_code = $version['mmv_iffco_a1_public'];

                                    } elseif(isset($version['mmv_iffco_a2_private']) && $version['mmv_iffco_a2_private'] != 'null' && $version['mmv_iffco_a2_private'] != null) {

                                        $mmv_code = $version['mmv_iffco_a2_private'];
    
                                    }
                                    break;
                                case 'reliance' :
                                    if (isset($version['mmv_reliance_a1_public']) && $version['mmv_reliance_a1_public'] != 'null' && $version['mmv_reliance_a1_public'] != null) {

                                        $mmv_code = $version['mmv_reliance_a1_public'];

                                    } elseif (isset($version['mmv_reliance_a3_public']) && $version['mmv_reliance_a3_public'] != 'null' && $version['mmv_reliance_a3_public'] != null) {

                                        $mmv_code = $version['mmv_reliance_a3_public'];

                                    }
                                    break;
                                case 'tata_aig_v2' :
                                    if (!empty($version['mmv_tata_aig_v2_a1_public'] ?? '') && $version['mmv_tata_aig_v2_a1_public'] != 'null') {

                                        $mmv_code = $version['mmv_tata_aig_v2_a1_public'];

                                    } elseif (!empty($version['mmv_tata_aig_v2_a3_public'] ?? '') && $version['mmv_tata_aig_v2_a3_public'] != 'null') {

                                        $mmv_code = $version['mmv_tata_aig_v2_a3_public'];

                                    }
                                    break;
                            }
                        } else {
                            switch ($ic_name) {
                                case 'iffco_tokio' :
                                    if(isset($version['mmv_iffco_a2_private']) && $version['mmv_iffco_a2_private'] != 'null' && $version['mmv_iffco_a2_private'] != null) {
                                        $mmv_code = $version['mmv_iffco_a2_private'];
                                    }
                                    break;
                                case 'reliance' :
                                    if (isset($version['mmv_reliance_a2_private']) && $version['mmv_reliance_a2_private'] != 'null' && $version['mmv_reliance_a2_private'] != null) {

                                        $mmv_code = $version['mmv_reliance_a2_private'];

                                    } elseif (isset($version['mmv_reliance_a4_private']) && $version['mmv_reliance_a4_private'] != 'null' && $version['mmv_reliance_a4_private'] != null) {

                                        $mmv_code = $version['mmv_reliance_a4_private'];

                                    }
                                    break;
                                case 'tata_aig' :

                                    if (!empty($version['mmv_tata_aig_v2_a2_private'] ?? '') && $version['mmv_tata_aig_v2_a2_private'] != 'null')
                                    {

                                        $mmv_code = $version['mmv_tata_aig_v2_a2_private'];

                                    }
                                    elseif (!empty($version['mmv_tata_aig_v2_a4_private'] ?? '') && $version['mmv_tata_aig_v2_a4_private'] != 'null')
                                    {

                                        $mmv_code = $version['mmv_tata_aig_v2_a4_private'];

                                    }
                                    break;
                            }
                        }
                        
                            $fyntune_version = $version;
                            $no_of_wheels = isset($version['no_of_wheels']) ? $version['no_of_wheels'] : '0';
    
                            break;
                        } elseif (isset($version['mmv_' . $ic_name])) {
                            $mmv_code = $version['mmv_' . $ic_name];
                            $fyntune_version = $version;
                            $no_of_wheels = isset($version['no_of_wheels']) ? $version['no_of_wheels'] : '0';
                            if($ic_name == 'renewbuy'){
                                return [
                                    'status' => true,
                                    'data' => $mmv_code
                                ];
                            }
                            break;
                        } else {
                            return  [
                                'status' => false,
                                'message' => (strtoupper($ic_name) == 'CHOLLA_MANDALAM' ? 'CHOLA_MANDALAM' : strtoupper($ic_name)).' mapping does not exist with IC master'
                            ];
                        }
                    }
                }
            }
            
            if ($mmv_code == '') {
                return  [
                    'status' => false,
                    'message' => 'Vehicle Not Mapped'
                ];
            } else if ($mmv_code == 'DNE') {
                return  [
                    'status' => false,
                    'message' => 'Vehicle Does Not Exists'
                ];
            } else if (strtolower($mmv_code) == 'declined') {
                return  [
                    'status' => false,
                    'message' => 'The following vehicle is Declined / Blacklisted'
                ];
            } else {
                $product = $product == 'motor' ? '' : '_' . $product;
                $ic_version_file_name  = $path . $ic_name . $product . '_model_master.json';
                $ic_version_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($ic_version_file_name), true);
                if (isset($ic_version_data[$mmv_code])) {
                    $mmv_data = $ic_version_data[$mmv_code];
                    $mmv_data['ic_version_code'] = $mmv_code;
                    $mmv_data['no_of_wheels'] = $no_of_wheels;
                    $mmv_data['fyntune_version'] = $fyntune_version;
                    return  [
                        'status' => true,
                        'data' => $mmv_data
                    ];
                } else {
                    return  [
                        'status' => false,
                        'message' => (strtoupper($ic_name) == 'CHOLLA_MANDALAM' ? 'CHOLA_MANDALAM' : strtoupper($ic_name)).' Mapping Does Not Exists'
                    ];
                }
            }
        }
    }
}
if (!function_exists('get_fyntune_mmv_details')) {
    function get_fyntune_mmv_details($product_sub_type_id, $version_id, $gcv_carrier_type=NULL)
    {
        $product = [
            '1'  => 'motor',
            '2'  => 'bike',
            '5'  => 'pcv',
            '6'  => 'pcv',
            '9'  => 'gcv',
            '11' => 'pcv',
            '13' => 'gcv',
            '14' => 'gcv',
            '15' => 'gcv',
            '16' => 'gcv',
            '17' => 'misc',
            '18' => 'misc',
        ];
        $find_using_initials = false;
        // If the product subtype id is not known, then pass the 3 initial letters of the fyntune version code
        if (in_array($product_sub_type_id, ['CRP', 'BYK', 'PCV', 'GCV', 'MIS'])) {
            $find_using_initials = true;
            $sections = [
                // The below codes (1, 2, 11,..) are dummy, only to search in respective files
                'CRP' => '1',
                'BYK' => '2', 
                'PCV' => '11',
                'GCV' => '9',
                'MIS' => '17',
            ];
            $product_sub_type_id = $sections[$product_sub_type_id] ?? null;
        }
        if (in_array($product_sub_type_id, array_keys($product))) {
            $env = config('app.env');
            // if ($env == 'local') {
            //     $env_folder = 'uat';
            // } else if ($env == 'test') {
            //     $env_folder = 'production';
            // } else if ($env == 'live') {
            //     $env_folder = 'production';
            // }

            if ($env == 'local')
            $env_folder = 'uat';
            else if ($env == 'test')
            $env_folder = 'production';
            else if ($env == 'live')
            $env_folder = 'production';

            $product = $product[$product_sub_type_id];
            $path = 'mmv_masters/' . $env_folder . '/';

            $file_name  = $path . $product . '_model_version.json';
            //$version_data = json_decode(file_get_contents($file_name), true);
            $version_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $version = collect($version_data)->where('version_id', $version_id)->first();

            $model_file = $path . $product . '_model.json';
            $model_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($model_file), true);
            if(!empty($version['model_id']))
            $model = collect($model_data)->where('model_id', $version['model_id'])->first();
            $manuf_file = $path . $product . '_manufacturer.json';
            $manuf_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($manuf_file), true);
            if(!empty($model['manf_id']))
            $manf = collect($manuf_data)->where('manf_id', $model['manf_id'])->first();

            $mmv_data = [];
            if (!empty($version) && !empty($model) && !empty($manf)) {
                $mmv_data['version'] = $version;
                $mmv_data['model'] = $model;
                $mmv_data['manufacturer'] = $manf;
                if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy' && strtolower($mmv_data['manufacturer']['manf_name'] ?? '') == 'morris garages') {
                    return [
                        'status' => false,
                        'message' => 'Morris Garages vehicle are not allowed.',
                    ];
                }
            }
            $cv_sections = [
                "E-RICKSHAW" => "5",
                "AUTO RICKSHAW" => "5",
                "TAXI" => "6",
                "PICK UP/DELIVERY/REFRIGERATED VAN" => "9",
                "TRACTOR" => "15",
                "DUMPER/TIPPER" => "13",
                "TANKER/BULKER" => "16",
                "TRUCK" => "14",
                "PASSENGER-BUS" => "7",
                "SCHOOL-BUS" => "10",
                "ELECTRIC-RICKSHAW" => "11",
                "TEMPO-TRAVELLER" => "12",
                "AGRICULTURAL-TRACTOR" => "17",
                "MISCELLANEOUS-CLASS" => "18"
            ];
            $mmv_data['product_sub_type_id'] = null;
            if ($find_using_initials && !empty($mmv_data['manufacturer']['cv_type'])) {
                $mmv_data['product_sub_type_id'] = $cv_sections[$mmv_data['manufacturer']['cv_type']] ?? null;
            }
            // $mmv_data = [];
            // foreach ($version_data as $version) {
            //     if ($version['version_id'] == $version_id) {
            //         $mmv_data['version'] = $version;
            //         $model_file = $path . $product . '_model.json';
            //         $model_data = json_decode(file_get_contents($model_file), true);
            //         foreach ($model_data as $key => $value) {
            //             if ($value['model_id'] == $version['model_id']) {
            //                 $mmv_data['model'] = $value;
            //                 $manuf_file = $path . $product . '_manufacturer.json';
            //                 $manuf_data = json_decode(file_get_contents($manuf_file), true);
            //                 foreach ($manuf_data as $mk => $mv) {
            //                     if ($mv['manf_id'] == $value['manf_id']) {
            //                         $mmv_data['manufacturer'] = $mv;
            //                     }
            //                 }
            //             }
            //         }
            //     }
            // }

            if (empty($mmv_data)) {
                return [
                    'status' => false,
                    'data' => $mmv_data
                ];
            }else{
                return [
                    'status' => true,
                    'data' => $mmv_data
                ];
            }
            return  [
                'status' => false,
                'message' => 'Vehicle details not found.'
            ];
        }
        return  [
            'status' => false,
            'message' => 'Vehicle details not found.'
        ];
    }
}

if (!function_exists('car_age')) {
    function car_age($vehicle_register_date, $previous_policy_expiry_date, $type ='floor')
    {
        $date1 = new DateTime($vehicle_register_date);
        $date2 = new DateTime($previous_policy_expiry_date == 'New' ? date('Y-m-d') : $previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);#(($interval->y * 12) + $interval->m) + 1;
        $car_age = ($type == 'ceil') ? ceil($age / 12) : floor($age / 12);
        return (int) $car_age;
    }
}

if (!function_exists('car_age_intervals')) {
    function car_age_intervals($vehicle_register_date, $previous_policy_expiry_date)
    {
        $date1 = new DateTime($vehicle_register_date);
        $date2 = new DateTime($previous_policy_expiry_date == 'New' ? date('Y-m-d') : $previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = json_encode($interval);
        return $age;
    }
}

if (!function_exists('array_search_key')) {
    function array_search_key($needle_key, $array)
    {
        foreach ($array as $key => $value) {
            if ($key == $needle_key) {
                return $value;
            }
            if (is_array($value)) {
                if (($result = array_search_key($needle_key, $value)) !== false) {
                    return $result;
                }
            }
        }

        return false;
    }
}


if (!function_exists('getKotakTokendetails'))
{
   function getKotakTokendetails($section, $is_pos = false)
   {
        $user_id = $is_pos ? config('constants.IcConstants.kotak.KOTAK_'.strtoupper($section).'_POS_USERID') : config('constants.IcConstants.kotak.KOTAK_'.strtoupper($section).'_USERID');
        $password = $is_pos ? config('constants.IcConstants.kotak.KOTAK_'.strtoupper($section).'_POS_PASSWORD') : config('constants.IcConstants.kotak.KOTAK_'.strtoupper($section).'_PASSWORD');
        $encrypt_method = "AES-128-CBC";
        $data['vRanKey'] = (string)rand(1111111111111111,9999999999999999);
        $secret_key = $data['vRanKey'];
        $secret_iv = $data['vRanKey'];

        $id = openssl_encrypt($user_id, $encrypt_method, $secret_key, 0, $secret_iv);
        $data['vLoginEmailId'] = base64_encode($id);

        $pwd = openssl_encrypt($password, $encrypt_method, $secret_key, 0, $secret_iv);
        $data['vPassword'] = base64_encode($pwd);
        return $data;
    }
}

if (!function_exists('array_change_key_case_recursive'))
{
    function array_change_key_case_recursive($arr)
    {
        return array_map(function ($item) {
            if (is_array($item))
            {
                $item = array_change_key_case_recursive($item);
            }

            return $item;
        }, array_change_key_case($arr));
    }
}


if (!function_exists('voluntary_deductible_calculation'))
{
    function voluntary_deductible_calculation($od,$vol_amount,$section)
    {
        $voluntary_deductible = 0 ;
                switch ($section) {
                    case 'car':
                                if($vol_amount == 2500)
                                {
                                    if((($od * 20)/100)>750)
                                    {
                                        $voluntary_deductible = 750;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 20)/100);
                                    }
                                }
                                else if($vol_amount == 5000)
                                {

                                    if((($od * 25)/100)>1500)
                                    {
                                        $voluntary_deductible = 1500;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 25)/100);
                                    }
                                }
                                else if($vol_amount == 7500)
                                {
                                     if((($od * 30)/100)>2000)
                                    {
                                        $voluntary_deductible = 2000;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 30)/100);
                                    }
                                }
                                elseif($vol_amount == 15000)
                                {
                                     if((($od * 35)/100)>2500)
                                    {
                                        $voluntary_deductible = 2500;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 35)/100);
                                    }
                                }
                                else
                                {
                                    $voluntary_deductible = 0;
                                }
                        break;
                    default:
                                if($vol_amount == 500)
                                {
                                    if((($od * 5)/100)>50)
                                    {
                                        $voluntary_deductible = 50;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 5)/100);
                                    }
                                }
                                else if($vol_amount == 750)
                                {
                                    if((($od * 10)/100)>75)
                                    {
                                        $voluntary_deductible = 75;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 10)/100);
                                    }
                                }
                                else if($vol_amount == 1000)
                                {
                                     if((($od * 15)/100)>125)
                                    {
                                        $voluntary_deductible = 125;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 15)/100);
                                    }
                                }
                                elseif($vol_amount == 1500)
                                {
                                     if((($od * 20)/100)>200)
                                    {
                                        $voluntary_deductible = 200;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 20)/100);
                                    }
                                }
                                 elseif($vol_amount == 3000)
                                {
                                     if((($od * 25)/100)>250)
                                    {
                                        $voluntary_deductible = 250;
                                    }
                                    else
                                    {
                                        $voluntary_deductible = (($od * 25)/100);
                                    }
                                }
                                else
                                {
                                    $voluntary_deductible = 0;
                                }
                        break;
                }

                 return $voluntary_deductible;
    }
}

if (!function_exists('get_parent_code')) {
    function get_parent_code($product_sub_type_id)
    { 
        // return MasterProductSubType::with('parent')->where('product_sub_type_id', $product_sub_type_id)->first()->parent->product_sub_type_code;  
        $all_parent_data = DB::table('master_product_sub_type as mpst')
                        ->where('mpst.parent_id', 0)
                        ->get()
                        ->toArray();
        $all_parent_ids = array_column($all_parent_data, 'product_sub_type_id');
        if(in_array($product_sub_type_id,$all_parent_ids))
        {
            $parentId = $product_sub_type_id;
        }
        else
        {
            $parentId = DB::table('master_product_sub_type as mpst')
                ->where('mpst.product_sub_type_id', $product_sub_type_id)
                ->pluck('parent_id')
                ->first();
        }
        return $parentCode = DB::table('master_product_sub_type as mpst')
            ->where('mpst.product_sub_type_id', $parentId)
            ->pluck('product_sub_type_code')
            ->first();
    }
}

if (!function_exists('get_usp')) {
    function get_usp($parent_code,$company_alias)
    {
        $table_name = strtolower(trim($parent_code)).'_usp';
        return DB::table($table_name)
        ->where('ic_alias', trim($company_alias))
        ->select('usp_desc')
        ->get();
    }
}

if (!function_exists('search_for_id_sbi')) {
    function search_for_id_sbi($search_value, $array)
    {
        foreach ($array as $key => $value) {
            $output = array();

            array_push($output, $key);

            if (is_array($value) && count($value)) {
                foreach ($value as $key1 => $value1) {
                    if ($value1 == $search_value) {
                        array_push($output, $key1);
                        return $output;
                    }
                }
            } elseif ($value == $search_value) {
                return $output;
            }
        }
    }
}

if (!function_exists('async_http_post_form_data'))
{
	function async_http_post_form_data($url, $data, $additionalData)
	{
		$curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        ));
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        $response = curl_exec($curl);
        $endTime = new DateTime(date('Y-m-d H:i:s'));
        $responseTime = $startTime->diff($endTime);
        $wsLogdata = [
            'enquiry_id'     => $additionalData['enquiryId'],
            'product'        => (isset($additionalData['productName']))?$additionalData['productName']:'',
            'section'        => 'CAR',
            'method_name'    => 'Break id generation',
            'company'        => 'kotak',
            'method'         => 'post',
            'transaction_type'    => 'proposal',
            'request'       => json_encode($data),
            'response'      => $response,
            'endpoint_url'  => $url,
            'ip_address'    => request()->ip(),
            'start_time'    => $startTime->format('Y-m-d H:i:s'),
            'end_time'      => $endTime->format('Y-m-d H:i:s'),
            // 'response_time'	=> $responseTime->format('%Y-%m-%d %H:%i:%s'),
            'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'    => Carbon::now()
        ];

        DB::table('webservice_request_response_data')->insert($wsLogdata);
		WebserviceRequestResponseDataOptionList::firstOrCreate([
			'company' =>'kotak',
			'section' => 'CAR',
			'method_name' => 'Break id generation',
		]);
        curl_close($curl);
        return $response;
	}
}

function async_http_post($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_exec($ch);
    curl_close($ch);
}

if (!function_exists('create_request_for_inspection_through_live_check'))
{
    function create_request_for_inspection_through_live_check($inspection_data)
    {
             $inspection_request_array = [
                "appId" => $inspection_data['appId'],
                "companyId"=> $inspection_data['companyId'],
                "branchId"=> $inspection_data['branchId'],
                "refId"=> $inspection_data['refId'],
                "name" =>  $inspection_data['fullname'],
                "email" =>  $inspection_data['email_addr'],
                "mobileNumber" =>  $inspection_data['mobile_no'],
                "address" => $inspection_data['address'],
                "regNumber" =>  $inspection_data['regNumber'],
                "vehicleCategory" => $inspection_data['Vehicle_category'] ?? 'car',
                "vehicleSubCategory" => "",
                "make" => $inspection_data['make'],
                "brand" => $inspection_data['brand'],
                "modelYear" => "",
                "fuelType" => "",
                "city" => "",
                "odometer" => '',
                "regType" => "",

        ];
        if(!empty($inspection_data['appUserId']))
        {
            $inspection_request_array['appUserId'] = $inspection_data['appUserId'] ;
        }
        $additional_data = [
            'requestMethod' => 'post',
            'enquiryId' => $inspection_data['enquiryId'],
            'method' => 'Live check - Inspection request generation',
            'section' => 'car',
            'transaction_type' => 'proposal',
        ];

        $get_response = getWsData(config('constants.IcConstants.future_generali.END_POINT_URL_NEW_INSPECTION'), $inspection_request_array, 'inspection_request', $additional_data);
        $inspection_response_array = $get_response['response'];

        return $inspection_response_array;

    }
}

if (!function_exists('create_webservice')) {
    function create_webservice($additionalData)
    {
        try {
            $startTime = new DateTime(date('Y-m-d H:i:s'));
            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $responseTime = $endTime->diff($startTime);

            $response = is_array($additionalData['response']) ? json_encode($additionalData['response']) : $additionalData['response'];
            $wsLogdata = [
                'enquiry_id'          => $additionalData['enquiryId'],
                'product'             => $additionalData['productName'],
                'section'             => $additionalData['section'],
                'method_name'         => $additionalData['method'],
                'company'             => $additionalData['companyAlias'],
                'method'              => null,
                'transaction_type'    => $additionalData['transaction_type'],
                'request'             => json_encode($additionalData['request']),
                'response'            => $response,
                'endpoint_url'        => 'Internal Service',
                'ip_address'          => request()->ip(),
                'start_time'          => $startTime->format('Y-m-d H:i:s'),
                'end_time'            => $endTime->format('Y-m-d H:i:s'),
                // 'response_time'	      => $responseTime->format('%H:%i:%s'),
                'response_time'	      => $endTime->getTimestamp() - $startTime->getTimestamp(),
                'created_at'          => now()
            ];

            if ($additionalData['method'] == 'Proposal') {
                $data = WebServiceRequestResponse::create($wsLogdata);
                $table = 'webservice_request_response_data';
            } else {
                $data = QuoteServiceRequestResponse::create($wsLogdata);
                $table = 'quote_webservice_request_response_data';
                insertIntoQuoteVisibilityLogs($data['id'], strtoupper($additionalData['section'] ?? ''), $additionalData['master_policy_id'] ?? null);
            }
            WebserviceRequestResponseDataOptionList::firstOrCreate([
                'company' =>$additionalData['companyAlias'],
                'section' => $additionalData['section'],
                'method_name' => $additionalData['method'],
            ]);
            return [
                'webservice_id' => $data['id'],
                'table' => $table,
                'status' => true,
                'msg' =>  empty($response) ? 'Log Entry Success..!' : $response,
            ];
        } catch (\Exception $e) {
            return [
                'status' => true,
                'msg' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('remove_xml_namespace'))
{
    function remove_xml_namespace($data)
    {
        return preg_replace('#</\w{1,10}:#', '</', preg_replace('#<\w{1,10}:#', '<', preg_replace('/ xsi[^=]*="[^"]*"/i', '$1', preg_replace('/ xmlns[^=]*="[^"]*"/i', '$1',preg_replace('/ xml:space[^=]*="[^"]*"/i', '$1', $data)))));

    }
}
if (!function_exists('generate_random_number'))
{
	function generate_random_number($num, $text = '', $numlen = 5)
	{
		return $text . '-' . str_repeat('0', $numlen - strlen($num)) . $num;
	}
}
if (!function_exists('array_search_key'))
{
    function array_search_key($needle_key, $array)
    {
        foreach ($array as $key => $value)
        {
            if ($key == $needle_key)
            {
                return $value;
            }
            if (is_array($value))
            {
                if (($result = array_search_key($needle_key, $value)) !== false)
                {
                    return $result;
                }
            }
        }

        return false;
    }
}

if (!function_exists('get_ic_min_max')) {
    function get_ic_min_max($idv=0,$minpercentage=0,$maxpercentage=0,$min=0,$max=0,$changed_idv=0){
        $idvdata=0;
        $data= new stdClass();
        $minval=0;
        $maxval=0;

        if($idv!=0 && $minpercentage!=0 &&  $maxpercentage!=0){

            if (is_float($minpercentage)==1 && is_float($maxpercentage)==1)
            {
                $minval=$idv*$minpercentage;
                $maxval=$idv*$maxpercentage;
            } else {
            $minpercentage=$idv*$minpercentage/100;
            $maxpercentage=$idv*$maxpercentage/100;
            $minval=$idv-$minpercentage;
            $maxval=$idv+$maxpercentage;
            }

            $data->min_idv=(int)$minval;
            $data->max_idv=(int)$maxval;

        } else if($min!=0 && $max!=0)
        {
            $minval=$min;
            $maxval=$max;
            $data->min_idv=(int)$minval;
            $data->max_idv=(int)$maxval;
        } else {
            $data->min_idv=$minval;
            $data->max_idv=$maxval;
        }


        if($minval!=0 && $maxval!=0 && $changed_idv!=0){


            if ($changed_idv >= $maxval) {
                 $idvdata=$maxval;
               } elseif ($changed_idv <=$minval) {
                $idvdata=$minval;
            } else {

                $idvdata=$changed_idv;
             }



        }

        if($idvdata!=0){
            $data->idv=(int)$idvdata;
        } else {
            $data->idv=$idvdata;
        }

        return $data;


    }
}

if (!function_exists('UpdateEnquiryStatusByIdAce'))
{
    function UpdateEnquiryStatusByIdAce($ace_data)
    {
        $UserProductJourneyData  = \App\Models\UserProductJourney::select('api_token','lead_id', 'lead_source')
        ->where('user_product_journey_id',$ace_data['enquiryId'])
                ->first();
        if($UserProductJourneyData['lead_id'] == null)
        {
            return true;
        }

        $status_data = [
            'enquiryId'     =>  $UserProductJourneyData['lead_id'],//33279,
            'status'        => $ace_data['stage'],
            'policyNumber'  => '',
            'policyPDFURL'  => ''
        ];
        // $report = httpRequestNormal(url('api/proposalReportsByLeadId'), 'POST', [
        //     'leadId'     => $UserProductJourneyData['lead_id'],
        //     'user_product_journey_id' => $ace_data['enquiryId']
        // ], [], [] ,[], [] , false);
        // $data = $report['response']['data'] ?? [];
        $proposalReportController = new ProposalReportController;
        $payload = [
            'leadId'     => $UserProductJourneyData['lead_id'],
            'user_product_journey_id' => $ace_data['enquiryId']
            ];
        $customRequest = new Request($payload);
        $getVehicleDetails = $proposalReportController->proposalReportsByLeadId($customRequest);
        $data = $getVehicleDetails->getOriginalContent()["data"] ?? [];
        $push_data = [];
        foreach($data  as $key => $value){
            if ($key != 'cover_amount')
            //$push_data[$key] = (string) $value;
            $push_data[$key] = is_array($value) ? json_encode($value) : (string) $value;
        }
        
        $data = $push_data;
        if (empty($data))
            return;
        
        // if($data['company_name'] == '' || $data['company_alias'] == ''){
        //     $status_data['stage'] = STAGE_NAMES['LEAD_GENERATION'];
        //     $data['transaction_stage'] = STAGE_NAMES['LEAD_GENERATION'];
        // }

        
        //if($data['data']['company_name'] == '' || $data['data']['company_alias'] == '')
        // if($ace_data['stage'] == STAGE_NAMES['LEAD_GENERATION'])
        // {
        //     return true;
        // }
        // if($data['trace_id'] != "" && $data['proposer_name'] != "" && $data['proposer_mobile'] != "" && $data['proposer_emailid'] != "" && $data['vehicle_registration_number'] != "" && $data['vehicle_make'] != "" && $data['vehicle_model'] != "" && $data['vehicle_version'] != "" && $data['vehicle_cubic_capacity'] != "" && $data['vehicle_fuel_type'] != "" && $data['policy_type'] != "" && $data['vehicle_registration_date'] != "" && $data['previous_policy_expiry_date'] != "" && $data['vehicle_manufacture_year'] != "" && $data['first_name'] != "" && $data['last_name'] != "" && $data['owner_type'] != "" && $data['business_type'] != "" && $data['prev_policy_type'] != "" && $data['section'] != "" && $data['product_type'] != "" && $data['sub_product_type'] != "" && $data['transaction_stage'] != "" && $data['lead_id'] != "")
        // {
        //     return;
        // }
        // $data['previous_ncb'] = (string) $data['previous_ncb'];
        // $data['ncb_percentage'] = (string) $data['ncb_percentage'];

        $status_data=array_merge($status_data, $data ?? []);
        if(isset($status_data['transaction_stage'])){
            $status_data['status'] = $status_data['transaction_stage'];
            unset($status_data['stage']);    
        }
        $status_data_check = $status_data;
        unset($status_data_check['lastupdated_time']);
        $status_data_md5 = md5(json_encode($status_data_check));
        // $UserName = config('ACE_HANDSHAKE_API_USER_NAME');
        // $Password = config('ACE_HANDSHAKE_API_PASSWORD');
        // $url =  config('ACE_UAPDATE_ENQUIRY_STATUS_BY_ID_URL')."?UserName=".$UserName."&Password=".$Password."&token=".$UserProductJourneyData['api_token'];

        //push data to ace crm
        if ($UserProductJourneyData['lead_source'] == 'ACE CRM') {
            $UserTokenDataExists  = \App\Models\UserTokenRequestResponse::where('user_product_journey_id', $ace_data['enquiryId'])
                ->where('request_checksum', $status_data_md5)->exists();
            if (!$UserTokenDataExists) {
                $url =  config('ACE_UAPDATE_ENQUIRY_STATUS_BY_ID_URL');
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->post(url($url), $status_data)->json();
                
                \App\Models\UserTokenRequestResponse::create([
                    'user_product_journey_id' => $ace_data['enquiryId'],
                    'request' => json_encode($status_data),
                    'response' => json_encode($response),
                    'request_checksum' => $status_data_md5,
                    'url' => $url
                ]);
            }
        }

        //push data to ft crm
        if (
            $UserProductJourneyData['lead_source'] == 'FT-CRM' &&
            config('constants.brokerConstant.ENABLE_CRM_LEAD_UPDATE_DATA_PUSH') == 'Y'
        ) {
            \App\Http\Controllers\CrmDataPushController::CrmDataPush($status_data);
        }
    }
}

if (!function_exists('UpdateEnquiryStatusByIdAceLead')) {
    function UpdateEnquiryStatusByIdAceLead(Request $request, $returnData, $enquiry_id)
    {
        $generatedUUID = Str::uuid();
        $requestData = [
            "enquiryId" => $generatedUUID,
            "status" => STAGE_NAMES['LEAD_GENERATION'],
            "stage" => STAGE_NAMES['LEAD_GENERATION'],
            "trace_id" => $enquiry_id,
            "proposer_name" => $request->first_name . " " . $request->last_name,
            "proposer_mobile" => $request->mobile_no,
            "proposer_emailid" => $request->email_id,
            "first_name" => $request->first_name,
            "last_name" => $request->last_name,
            "section" => $request->segment,
            "lead_id" => $generatedUUID
        ];
        if(is_numeric($enquiry_id) && strlen($enquiry_id) == 16)
        {
            $decrypt_enquiry_id = customDecrypt($enquiry_id);
        }
        UserProductJourney::where('user_product_journey_id', $decrypt_enquiry_id)->update(['lead_id' => $generatedUUID]);
        $url = config("constants.IcConstants.AMOGA_CRM_UPDATE_URL");
        $report = httpRequestNormal(url($url), 'POST', $requestData, [], [], [], [], false);
        $data = $report['response'] ?? [];
        \App\Models\UserTokenRequestResponse::create([
            'user_product_journey_id' => $returnData['new_user_product_journey_id'],
            'request' => json_encode($requestData),
            'response' => json_encode($data),
            'url' => $url
        ]);
    }
}

if (!function_exists('IciciPosRegistration'))
{
    function IciciPosRegistration($pos)
    {
        if(trim($pos['pan_no'])!== '' && trim($pos['aadhar_no']) !== '' && $pos['gender'] !== '')
            {
                $location_data = DB::table('icici_illocation_master')->where('city_name',$pos['city'])->first();
                $additionData = [
                    'requestMethod'     => 'post',
                    'type'              => 'tokenGeneration',
                    'productName'       => 'ICICI POS REGISTRATION',
                    'section'           => 'ICICI POS REGISTRATION',
                    'enquiryId'         => $pos['agent_id'],
                    'transaction_type'  => 'ICICI POS REGISTRATION'
                ];
                include_once app_path() . '/Helpers/CarWebServiceHelper.php';
                $tokenParam = [
                    'grant_type'    => 'password',
                    'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
                    'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
                    'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
                    'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
                    'scope'         => 'esbgeneric',
                ];
                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), 
                        http_build_query($tokenParam), 
                        'icici_lombard', 
                        $additionData);
                $token = json_decode($get_response['response'], true);
                if(isset($token['access_token']))
                {                    
                    $uid = getUUID();
                    $submit_pos_certificate = [
                        "IRDALicNo"             => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR'),
                        "CertificateNo"         => $pos['unique_number'],
                        "StartDate"             => date('d-m-Y'),
                        "EndDate"               => "31-12-2025",
                        "PanNumber"             => $pos['pan_no'],
                        "CertificateUserName"   => $pos['agent_name'],
                        "Gender"                => $pos['gender'],
                        "AadhaarNo"             => $pos['aadhar_no'],
                        "correlationID"         => $uid
                    ];
                   
                    $additionalData = [
                        'requestMethod'     => 'post',
                        'type'              => 'Submit POS Certificate',
                        'section'           => 'ICICI POS REGISTRATION',
                        'productName'       => 'ICICI POS REGISTRATION',
                        'token'             => $token['access_token'],
                        'enquiryId'         => $pos['agent_id'],
                        'transaction_type'  => 'ICICI POS REGISTRATION'
                    ];
                    $get_response = getWsData(
                        config('SUBMIT_POS_CERTIFICATE_ICICI_LOMBARD'), $submit_pos_certificate, 'icici_lombard',
                        $additionalData
                    );
                    $data_response = json_decode($get_response['response'], true);
                    if(isset($data_response["status"]))
                    {
                        $name = explode(' ',$pos['agent_name']);
                        $create_im_broker = [
                            "correlationID"         => $uid, 
                            "PanNumber"             => $pos['pan_no'],
                            "LicenseNo"             => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR'),
                            "IlLocation"            => config('IlLocation') == '' ? $location_data->il_location_name :  config('IlLocation'),//"MUMBAI - ANDHERI TELI GALI", 
                            "CertificateNo"         => $pos['unique_number'], 
                            "FirstName"             => (isset($name[0]) ? $name[0]: " "), 
                            "MiddleName"            => (($pos['father_name'] == '' )? ' ' : $pos['father_name']), 
                            "LastName"              => ((isset($name[1]) && $name[1] != '')  ? $name[1]: $name[0]),  
                            "FatherHusbandName"     => (($pos['father_name'] == '')? ' ' : $pos['father_name']), 
                            "DateOfBirth"           => date("d/m/Y", strtotime($pos['date_of_birth'])),
                            "Gender"                => $pos['gender'] == 'M' ? 'MALE' : 'FEMALE', 
                            "Mobile"                => $pos['mobile'],  
                            "EmailId"               => $pos['email'],  
                            "ContactPersonMobile"   => $pos['mobile'],  
                            "ContactPersonEmailId"  => $pos['email'], 
                            "Address"               => $pos['address'],  
                            "State"                 => $pos['state'],
                            "City"                  => $pos['city'],
                            "Country"               => "India",  
                            "PostalCode"            => $pos['pincode'], 
                        ];
                        unset($additionalData);
                        $additionalData = [
                            'requestMethod'     => 'post',
                            'type'              => 'Create IM Broker Child',
                            'section'           => 'ICICI POS REGISTRATION',
                            'productName'       => 'ICICI POS REGISTRATION',
                            'token'             => $token['access_token'],
                            'enquiryId'         => $pos['agent_id'],
                            'transaction_type'  => 'ICICI POS REGISTRATION'
                        ];

                        $get_response = getWsData(
                            config('CREATE_IMBROKER_POSCHILD_ICICI_LOMBARD'), $create_im_broker, 'icici_lombard', 
                            $additionalData
                        );	
                        $data_response_im =  json_decode($get_response['response'],true); 
                        if(isset($data_response_im[0]))
                        {
                            $data_response_im = $data_response_im[0];
                        }
                        if(isset($data_response_im["status"]) && strtolower($data_response_im["status"]) == "success")
                        {
                            IciciLombardPosMapping::updateorCreate(
                                [ 'agent_id' => $pos['agent_id'] ],
                                [
                                    'im_id'         => $data_response_im["imid"],
                                    'request'       => json_encode($create_im_broker),
                                    'response'      => json_encode($data_response_im["statusDesc"]),
                                    'status'        => $data_response_im["status"],
                                    'updated_at'    => date("Y-m-d H:i:s")
                                ]
                            );

                            return [
                                'status' => true,
                                'data' => json_encode($data_response_im)
                            ];
                        }
                        else
                        {
                            IciciLombardPosMapping::updateorCreate(
                                [ 'agent_id' => $pos['agent_id'] ],
                                [
                                    'im_id'         => $data_response_im["imid"],
                                    'request'       => json_encode($create_im_broker),
                                    'response'      => json_encode($data_response_im["statusDesc"]),
                                    'status'        => $data_response_im["status"],
                                    'updated_at'    => date("Y-m-d H:i:s")
                                ]
                            );

                            return [
                                'status' => false,
                                'data' => json_encode($data_response_im)
                            ];
                        }
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'data'      => json_encode($data_response)
                        ];
                    }                        
                }
                else
                {
                    $error['error_message'] = "Issue in Token Generation Service";
                    return [
                        'status' => false,
                        'data'      => $error
                    ];
                }               
            }
            else
            {
                $error['error_message'] = "Data Should not be NULL";
                return [
                    'status' => false,
                    'data'      => $error
                ];                
            }
    }
}
if (!function_exists('PosRegistrationHdfc')) {
    function PosRegistrationHdfc($pos)
    {
        if($pos['aadhar_no'] != '' && $pos['pan_no'] != '' && $pos['state'] != '')
            {
                    $root = [
                        "tem:DAT_END_DATE" => "31-12-2025",
                        "tem:NUM_MOBILE_NO" => $pos['mobile'],
                        "tem:VC_ADHAAR_CARD" => $pos['aadhar_no'],
                        "tem:VC_BRANCH_CODE" => 'mumbai',//str_replace("-", " ", trim($pos['branch_office'])),
                        "tem:VC_EMAILID" => $pos['email'],
                        "tem:VC_INTERMEDIARY_CODE" => '21048379',//21048379
                        "tem:VC_LANDLINE" => ($pos['phone_no'] != '') ? str_replace("`", "", $pos['phone_no']) : '02267523996',
                        "tem:VC_LOCATION" => $pos['state'],
                        "tem:VC_NAME" => $pos['agent_name'],
                        "tem:VC_PAN_CARD" => $pos['pan_no'],
                        "tem:VC_REG_NO" => $pos["unique_number"],
                        "tem:VC_STATE" => $pos['state'],
                        "tem:VC_TYPE" => "POSP",
                        "tem:VC_UNIQUE_CODE" => $pos["unique_number"]
                    ];

                    include_once app_path() . '/Helpers/CarWebServiceHelper.php';
                    //url =https://hepgw.hdfcergo.com/PospCreation/PospService.asmx
                    $get_response = getWsData('https://hepgw.hdfcergo.com/PospCreation/PospService.asmx',$root, 'hdfc_ergo',
                    [
                        'root_tag'  => 'tem:objposp',
                        'soap_action' => 'MapPospPortal',
                        'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body><tem:MapPospPortal>#replace</tem:MapPospPortal></soapenv:Body></soapenv:Envelope>',
                        'enquiryId' => $pos['agent_id'],
                        'requestMethod' =>'post',
                        'productName'  => 'HDFC POS Registration',
                        'company'  => 'hdfc',
                        'section' => 'HDFC POS Registration',
                        'method' =>'HDFC POS Registration',
                        'transaction_type'  => 'ICICI POS REGISTRATION'
                    ]);
                    $output = $get_response['response'];
                     
                    if($output)
                    {
                        $response = XmlToArray::convert((string)remove_xml_namespace($output));
						
                        $response = strtolower($response['Body']['MapPospPortalResponse']['MapPospPortalResult']);
                        /* print_r($response); */
						
                        if($response == "success" || (substr_count($response, 'posp mapping already available for code') > 0))
                        {
                            HdfcErgoPosMapping::updateorCreate(
                                [ 'agent_id' => $pos['agent_id'] ],
                                [
                                    'user_name'     => $pos["user_name"],
                                    'request'       => $root,
                                    'response'      => json_encode($response),
                                    'status'        => $pos["status"],
                                    'updated_at'    => date("Y-m-d H:i:s")
                                ]
                            ); 

                            return[
                                'status' => true,
                            ];
                        }else
                        {
                            return[
                                'status' => false,
                            ];
                        }
						
                    }else
                    {
                        return[
                            'status' => false,
                        ];
                    }

                    unset($root, $output, $response);
            }else
            {
                return[
                    'status' => false,
                ];
            }
    }
}

// if (!function_exists('GetCpaTenure')) 
// {
//     function GetCpaTenure($company_alias,$product_sub_type_id)
//     {
//         $parent_code = get_parent_code($product_sub_type_id);
//         $CpaTenure = CpaTenure::where('company_alias','=',$company_alias)
//                         ->where('product_type','=',$parent_code)
//                         ->where('status','=',1)
//                         ->first();
//             return $CpaTenure;
//     }
// }

if (!function_exists('getFirstAndLastName')) 
{
    function getFirstAndLastName(String $full_name){
        $name = explode(' ', $full_name);
        $lastname = ((count($name) - 1 > 0) ? $name[count($name) - 1] : "");
        if (isset($name[count($name) - 1]) && (count($name) - 1 > 0)) {
            unset($name[count($name) - 1]);
        }
        $firstname = implode(' ', $name);
        return [
           $firstname,
           $lastname,
        ];
    }
}
if (!function_exists('getPreviousIcMapping')) 
{
    function getPreviousIcMapping(String $ic_name) {
        return FastlanePreviousIcMapping::with('masterCompany')->whereRaw('? LIKE CONCAT("%", identifier, "%")', $ic_name)->first();
    }
}
if (!function_exists('get_fyntune_mmv_code')) {
    function get_fyntune_mmv_code($request_product_sub_type_id, $version_id, $ic_name, $gcv_carrier_type=NULL)
    {
        ini_set('memory_limit', '1024M');
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
        //$product_sub_type_id = [1, 2, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16];
        if (in_array($request_product_sub_type_id, $product_sub_type_id)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }

            $product = [
                '1'  => 'motor',
                '2'  => 'bike',
                '5'  => 'pcv',
                '6'  => 'pcv',
                '9'  => 'gcv',
                '11' => 'pcv',
                '13' => 'gcv',
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
            ];
            $product = $product[$request_product_sub_type_id];
            
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_model_version.json';
            //$version_data = json_decode(file_get_contents($file_name), true);
            $version_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $return_data = [
                'status' => false,
                'message' => strtoupper($ic_name) . ' mapping does not exist with IC master'
            ];
            foreach ($version_data as $key => $value) 
            {                
                if($value['mmv_' . $ic_name] == $version_id)
                {
                   return [
                       'status' => true,
                       'data'   =>  $value                          
                   ];
                }              
            }
            return $return_data;
        }
    }
}

if (!function_exists('getAddress')) 
{
    function getAddress($address_data)
    {
        // Will have to replace more than one space so that below explode function work perfectly - @Amit - 10-08-2022
        $full_address = trim(preg_replace('/\s+/', ' ', $address_data['address']));
        $full_address = filter_var($full_address, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH);
        $address_1 = '';
        $address_2 = '';
        $address_3 = '';
        $address_4 = '';
        $address_1_limit = (int) $address_data['address_1_limit'];
        $address_2_limit = (int) $address_data['address_2_limit'];
        $address_3_limit = (int) ($address_data['address_3_limit'] ?? '');
        $address_4_limit = (int) ($address_data['address_4_limit'] ?? '');
        if(strlen($full_address) > $address_1_limit)
        {     
            //Address 1
            $address = explode(' ', $full_address);
            if (strlen($address[0] ?? '') > $address_1_limit) {
                if (strpos($address[0], ',') !== false) {
                    $address[0] = str_replace(',', ', ', $address[0]);
                    $address = implode(' ', $address);
                    $full_address = $address;
                    $address = explode(' ', $address);
                } else {
                    return [
                        'address_1' => trim($full_address),
                        'address_2' => '',
                        'address_3' => '',
                        'address_4' => '',
                    ];
                }
            }

            if (strlen($address[0] ?? '') > $address_1_limit) {
                return [
                    'address_1' => trim($full_address),
                    'address_2' => '',
                    'address_3' => '',
                    'address_4' => '',
                ];
            }
            foreach ($address as $key => $value) 
            {
                $temp_1 = trim($address_1.' '.$value);
                $temp_1=str_replace("  "," ",$temp_1);
                if(strlen($temp_1) <= $address_1_limit && strlen($address_1) <= $address_1_limit)
                {
                   $address_1 = $temp_1;
                }
                else
                {
                    break;
                }
            }
            //Address 2
            $remaing_address_1 = $remaing_address = str_replace(trim($address_1),' ',$full_address);
            unset($temp);
            if(strlen($remaing_address) > $address_2_limit)
            {
                $remaing_address = explode(' ', $remaing_address);
                foreach ($remaing_address as $key => $value) 
                {
                    $temp = trim($address_2.' '.$value);
                    $temp=str_replace("  "," ",$temp);
                    if(strlen($temp) <= $address_2_limit && strlen($address_2) <= $address_2_limit)
                    {
                       $address_2 = $temp;
                    }
                    else
                    {
                        break;
                    }
                }
                
            }
            else
            {
               $address_2 = $remaing_address;
            }
            unset($temp);
            $remaing_address_3 = str_replace(trim($address_2),' ',$remaing_address_1); 
            if((int) $address_3_limit > 0 && strlen($remaing_address_3) > (int) $address_3_limit)
            {
                $remaing_address_3_array = explode(' ', $remaing_address_3);
                foreach ($remaing_address_3_array as $key => $value) 
                {
                    $temp = trim($address_3.' '.$value);
                    $temp=str_replace("  "," ",$temp);
                    if(strlen($temp) <= (int) $address_3_limit && strlen($address_3) <= (int) $address_3_limit)
                    {
                       $address_3 = $temp;
                    }
                    else
                    {
                        break;
                    }
                }
                
            }
            else
            {
               $address_3 = $remaing_address_3;
            }
            unset($temp);
            //address 4
            $remaing_address_4 = str_replace(trim($address_3),' ',$remaing_address_3); 
            if((int) $address_4_limit > 0 && strlen($remaing_address_4) > (int) $address_4_limit)
            {
                $remaing_address_4_array = explode(' ', $remaing_address_4);
                foreach ($remaing_address_4_array as $key => $value) 
                {
                    $temp = trim($address_4.' '.$value);
                    $temp=str_replace("  "," ",$temp);
                    if(strlen($temp) <= (int) $address_4_limit && strlen($address_4) <= (int) $address_4_limit)
                    {
                       $address_4 = $temp;
                    }
                    else
                    {
                        break;
                    }
                }
                
            }
            else
            {
               $address_4 = $remaing_address_4;
            }
            
        }
        else
        {
            $address_1 = $full_address;            
        }
        
        return [
            'address_1' => trim($address_1),
            'address_2' => trim($address_2),
            'address_3' => trim($address_3),               
            'address_4' => trim($address_4),             
        ];
        
    }
}

if ( ! function_exists('createLsqLead'))
{
    function createLsqLead($enquiry_id, $is_duplicate = FALSE)
    {
        $lead = new LeadController;

        return $lead->create($enquiry_id, $is_duplicate);
    }
}

if ( ! function_exists('updateLsqLead'))
{
    function updateLsqLead($enquiry_id)
    {
        $lead = new LeadController;

        return $lead->update($enquiry_id);
    }
}

if ( ! function_exists('retrieveLsqLead'))
{
    function retrieveLsqLead($enquiry_id)
    {
        $lead = new LeadController;

        return $lead->retrieve($enquiry_id);
    }
}

if ( ! function_exists('createLsqOpportunity'))
{
    function createLsqOpportunity($enquiry_id, $custom_stage = NULL, $additional_data = [])
    {
        $opportunity = new OpportunityController;

        return $opportunity->create($enquiry_id, $custom_stage, $additional_data);
    }
}

if ( ! function_exists('updateLsqOpportunity'))
{
    function updateLsqOpportunity($enquiry_id, $message_type = NULL, $additional_data = [])
    {
        $opportunity = new OpportunityController;

        return $opportunity->update($enquiry_id, $message_type, $additional_data);
    }
}

if ( ! function_exists('retrieveLsqOpportunity'))
{
    function retrieveLsqOpportunity($enquiry_id, $rc_number = NULL, $is_updated = FALSE)
    {
        $opportunity = new OpportunityController;

        return $opportunity->retrieve($enquiry_id, $rc_number, $is_updated);
    }
}

if ( ! function_exists('createLsqActivity'))
{
    function createLsqActivity($enquiry_id, $create_lead_on = 'opportunity', $message_type = NULL, $additional_data = [])
    {
        $activity = new ActivityController;

        return $activity->create($enquiry_id, $create_lead_on, $message_type, $additional_data);
    }
}
if ( ! function_exists('getCustomErrorMessage'))
{
    function getCustomErrorMessage($message, $company_alias, $product_type)
    {
        $message = empty($message) ? 'Invalid Response From IC service' : (is_string($message) ? trim($message) : json_encode($message));
        // $insert_data = [
        //     'ic_error' => $message,
        //     'company_alias' => $company_alias,
        //     'section' => $product_type,
        //     'status' => 'N',
        // ];
        // insertIcCustomMessage($insert_data);
        if (config('IS_IC_ERROR_HANDLING_ENABLED') == 'Y') {
            $custom_error = IcErrorHandling::select('custom_error')
                ->where([
                    ['checksum', '=', checksum_encrypt($message)],
                    ['company_alias', '=', $company_alias],
                    ['section', '=', $product_type],
                    ['status', '=', 'Y'],
                ])->first();
        }
        return $custom_error->custom_error ?? $message;
    }

}

if ( ! function_exists('insertIcCustomMessage'))
{
    function insertIcCustomMessage($data)
    {
      return false; // we won't be inserting error msgs into table anymore.
      if(config('IS_IC_ERROR_HANDLING_ENABLED') == 'Y')
      {
        if (IcErrorHandling::where('ic_error', $data['ic_error'])
                ->where('company_alias', $data['company_alias'])
                ->where('section', $data['section'])->doesntExist()) {
            
                    IcErrorHandling::updateOrCreate($data,$data);
         }
      }  
        
    }
}

if (!function_exists('get_vehicle_code_by_mmv_code')) {
    function get_vehicle_code_by_mmv_code($product_sub_type_id, $ic_name, $mmv_code)
    {
        $product = [
            '1'  => 'motor',
            '2'  => 'bike',
            '8'  => 'pcv',
            '6'  => 'pcv',
            '9'  => 'gcv',
            '13' => 'gcv',
            '14' => 'gcv',
            '15' => 'gcv',
            '16' => 'gcv',
        ];
        if (in_array($product_sub_type_id, array_keys($product))) {
            $env = config('app.env');
            if ($env == 'local')
            $env_folder = 'uat';
            else if ($env == 'test')
            $env_folder = 'production';
            else if ($env == 'live')
            $env_folder = 'production';

            $product = $product[$product_sub_type_id];
            $path = 'mmv_masters/' . $env_folder . '/';

            $file_name  = $path . $product . '_model_version.json';
            //$version_data = json_decode(file_get_contents($file_name), true);
            $version_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $mmv_data = collect($version_data)->where('mmv_reliance', $mmv_code)->first();
            if (empty($mmv_data)) {
                return [
                    'status' => false,
                    'data' => $mmv_data
                ];
            }else{
                return [
                    'status' => true,
                    'data' => $mmv_data
                ];
            }
            return  [
                'status' => false,
                'message' => 'Vehicle details not found.'
            ];
        }
    }
}

if (!function_exists('cashlessGarageCount')) {
    function getCashlessGarageCount($company_alias, $product_sub_type_id)
    {
        $parent_code = strtolower(get_parent_code($product_sub_type_id));
        $table_name = $company_alias . '_' . $parent_code . '_cashless_garage';
        return cache()->remember($table_name, env('CACHE_EXPIRATION_TIME'), function () use ($table_name) {
            if (Schema::hasTable($table_name)) {
                return [
                    'status' => true,
                    'count'  => DB::table($table_name)->count(),
                ];
            } else {
                return [
                    'status' => false,
                    'count'  => null,
                ];
 
            }
        });
    }
}

if (!function_exists('bajajCrmDataUpdate')) {
    function bajajCrmDataUpdate($data)
    {
        $proposalReportController = new ProposalReportController();
        if(empty($data->userProductJourneyId)) return;
        $enquiry_id = customDecrypt($data->userProductJourneyId);
        $agent_detail = CvAgentMapping::where('user_product_journey_id', $enquiry_id)->first();

        if( !empty($agent_detail) && $agent_detail->seller_type != "" ) 
        {
            request()->merge(['enquiry_id' => $data->userProductJourneyId, "combined_seller_ids" => [ "E" => [], "P" => [ ], "U" => [], 'b2c' => [] ], 'seller_type' => $agent_detail->seller_type ]);
        } 
        else 
        {
            request()->merge(['enquiry_id' => $data->userProductJourneyId, "combined_seller_ids" => [ "E" => [], "P" => [ ], "U" => [], 'b2c' => [] ] ]);
        }


        # request()->merge(['enquiry_id' => $data->userProductJourneyId, "combined_seller_ids" => [ "E" => [], "P" => [ ], "U" => [], 'b2c' => [] ] ]);
        $proposal_report = json_decode($proposalReportController->proposalReports(request())->content(), true)['data'][0] ?? "";

        if(!empty($agent_detail)){
            if ($agent_detail->seller_type == 'P') {
                $rm_code = $agent_detail->pos_key_account_manager ?? "";
            } else {
                $rm_code = $agent_detail->user_id ?? "";
            }
        }
        // $stage = null;
        // $lead_id = UserProductJourney::find(customDecrypt($data->userProductJourneyId));

        /* BLOCK CRM JOURNEY UPDATE */
        
        if (config("BLOCK_CRM_JOURNEY") === "Y") {

            $blockedSellerType = [];

            if (!empty(config('BLOCKED_CRM_SELLER_TYPE'))) {
                $blockedSellerType = explode(',', config('BLOCKED_CRM_SELLER_TYPE'));
            }

            if (!empty($agent_detail)) {
                if(in_array("b2c", $blockedSellerType) && $agent_detail->seller_type == '' && !empty($agent_detail->user_id)){
                    return;
                }

                if(in_array("U", $blockedSellerType) && $agent_detail->seller_type == 'U' && !empty($agent_detail->user_id)){
                    return;
                }

                if(in_array($agent_detail->seller_type, $blockedSellerType)){
                    return;
                }
            } else {
                //if agent detail is empty that means its a b2c journey
                if (in_array('b2c', $blockedSellerType)) {
                    return;
                }
            }

        }

        if (config('ALLOW_WITH_LOGIN_LEAD_USER') === "Y" && !empty($proposal_report)) {
            if ($proposal_report["seller_type"] === "U") {
                $proposal_report["proposer_mobile"] = empty(trim($proposal_report["proposer_mobile"])) ? $proposal_report["seller_mobile"] : $proposal_report["proposer_mobile"];
                $proposal_report["proposer_name"] = empty(trim($proposal_report["proposer_name"])) ? $proposal_report["seller_name"] : $proposal_report["proposer_name"];
                $proposal_report["proposer_emailid"] = empty(trim($proposal_report["proposer_emailid"])) ? $proposal_report["seller_email"] : $proposal_report["proposer_emailid"];
            }
        }
        $user_producyt_journey_data = UserProductJourney::find($enquiry_id);
        if (!empty($proposal_report['proposer_mobile']) && !empty($proposal_report['proposer_emailid']) && !empty($proposal_report['proposer_name'])) {
            $campaign = $leadSource = "";
            $section = empty($proposal_report['section']) ? $data->section : $proposal_report['section'];
            if(Str::lower($section) == "car"){
                $product = 'Car Insurance';

                if($proposal_report["seller_type"] == "P"){
                    $campaign = "BCIBLB2B-Car-Insurance_POS";
                    $leadSource = "B2B Insurance Portal";
                }elseif($proposal_report["seller_type"] == "Partner"){
                    $campaign = "BCIBLB2B-Car-Insurance_Non_POS";
                    $leadSource = "B2B Insurance Portal";
                }elseif($proposal_report["seller_type"] == "E"){
                    $campaign = "BCIBLB2B-Car-Insurance_Employee";
                    $leadSource = "B2B Insurance Portal";
                }elseif($proposal_report["seller_type"] == "b2c" || $proposal_report["seller_type"] == "U"){
                    if (!empty($user_producyt_journey_data) && !empty($user_producyt_journey_data->campaign_id) && !empty($user_producyt_journey_data->lead_source)) {
                        $campaign = $user_producyt_journey_data->campaign_id;
                        $leadSource = $user_producyt_journey_data->lead_source;
                    } else {
                        $campaign = "BCIBLB2C-Car-Insurance";
                        $leadSource = "B2C Insurance Portal";
                    }
                }

            } elseif (Str::lower($section) == "bike") {
                $product = 'Bike Insurance';

                if($proposal_report["seller_type"] == "P"){
                    $campaign = "BCIBLB2B-Bike-Insurance_POS";
                    $leadSource = "B2B Insurance Portal";
                }elseif($proposal_report["seller_type"] == "Partner"){
                    $campaign = "BCIBLB2B-Bike-Insurance_Non_POS";
                    $leadSource = "B2B Insurance Portal";
                }elseif($proposal_report["seller_type"] == "E"){
                    $campaign = "BCIBLB2B-Bike-Insurance_Employee";
                    $leadSource = "B2B Insurance Portal";
                }elseif($proposal_report["seller_type"] == "b2c" || $proposal_report["seller_type"] == "U"){
                    if (!empty($user_producyt_journey_data) && !empty($user_producyt_journey_data->campaign_id) && !empty($user_producyt_journey_data->lead_source)) {
                        $campaign = $user_producyt_journey_data->campaign_id;
                        $leadSource = $user_producyt_journey_data->lead_source;
                    } else {
                        $campaign = "BCIBLB2C-Bike-Insurance";
                        $leadSource = "B2C Insurance Portal";
                    }
                }
            }
            else{
                return;
            }
            $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)->first();
            $subType = (isset($corporate_data->is_renewal) && $corporate_data->is_renewal == "Y") ? "Renewal" : "Fresh";
            $journeyCampaignData = \App\Models\JourneycampaignDetails::where('user_product_journey_id', $enquiry_id)->first();

            $input = [
                "mobile_no" => $proposal_report['proposer_mobile'],
                "campaign_name" => $campaign,
                "product" => $product ?? $data['section'],
                "sub_product" => $subType,
                "lead_source" => $leadSource,
                /* "marketingId" => $lead_id->lead_id, */
                'last_name' =>  $proposal_report['proposer_name'] ?? "",
                'email' => $proposal_report['proposer_emailid'] ?? "",
                'plan' => \Illuminate\Support\Str::lower($proposal_report['section']),
                'resume_link' => $data->proposalUrl ?? $data->quoteUrl ?? $proposal_report['proposal_url'],
                "rm_code" => $rm_code ?? '',
            ];
          
            $input['journey_status'] = "";
            if($proposal_report["transaction_stage"] === STAGE_NAMES['LEAD_GENERATION']){
                $quote_url = parse_url($proposal_report["quote_url"], PHP_URL_QUERY);
                $quote_url && parse_str($quote_url, $step);
                $input['journey_status'] = 'User_Details_Updated';

                if(isset($step['stepNo']) && in_array($step['stepNo'], [1,2,3,4,5,6]) && !empty($proposal_report['vehicle_registration_number'])){
                      $input['journey_status']  = 'Registration_Completed';
                }
                
                if(isset($step['stepNo']) && $step['stepNo'] == 7){
                      $input['journey_status']  = 'Quote_Redirected';
                }
            }
            
            if($proposal_report["transaction_stage"] === STAGE_NAMES['QUOTE']){
                $input['journey_status']  = "Quote_Selection_Process";
            }

            if($proposal_report["transaction_stage"] === STAGE_NAMES['PROPOSAL_DRAFTED']){
                $input['journey_status']  = "Quote_Selection_Completed";

                if($data['stage'] == 1){
                    $input['journey_status'] = 'Owners_Personal_Details_Added & Location_Details_Captured';
                }

                if($data['stage'] == 2){
                    $input['journey_status'] = 'Nominee_Details_Updated';
                }

                if($data['stage'] == 3){
                    $input['journey_status'] = 'Vehicle_Details_Updated';
                }

                if($data['stage'] == 4){
                    $input['journey_status'] = 'Previous_Policy_Details_Updated';
                }
            }

            if($proposal_report["transaction_stage"] === STAGE_NAMES['INSPECTION_PENDING']){
                $input['journey_status']  = 'Underwriting_Approval';
            }

            if($proposal_report["transaction_stage"] === STAGE_NAMES['PROPOSAL_ACCEPTED']){
                $input['journey_status']  = 'Proposal_Creation_Completed';
            }

            if($proposal_report["transaction_stage"] === STAGE_NAMES['PAYMENT_INITIATED']){
                $input['journey_status']  = 'Policy_Payment_Initiated';
            }

            if($proposal_report["transaction_stage"] === STAGE_NAMES['PAYMENT_FAILED']){
                  $input['journey_status']  = 'Payment_Failed';
            }

            if($proposal_report["transaction_stage"] === STAGE_NAMES['PAYMENT_SUCCESS']){
                  $input['journey_status']  = 'Payment_Succcess';
            }

            if($proposal_report["transaction_stage"] === STAGE_NAMES['POLICY_ISSUED']){
                  $input['journey_status']  = 'Policy_Issued';
            }
            if(isset($journeyCampaignData['utm_source']) && !empty($journeyCampaignData['utm_source'])){
                  $input['utm_source_c']  = $journeyCampaignData['utm_source'];
                  if( config('bcl.overwrite.lead_source_with_utm_source') == "Y" )
                  {
                    $input['lead_source']  = $journeyCampaignData['utm_source'];
                  }
            }
            if(isset($journeyCampaignData['utm_medium']) && !empty($journeyCampaignData['utm_medium'])){
                  $input['utm_medium_c']  = $journeyCampaignData['utm_medium'];
            }
            if(isset($journeyCampaignData['utm_campaign']) && !empty($journeyCampaignData['utm_campaign'])){
                  $input['utm_campaign_c']  = $journeyCampaignData['utm_campaign'];
            }
            /* Store Data in Database */
            BajajCrmData::create([
               'user_product_journey_id' => $enquiry_id,
               'payload' => $input,
               'status' => '0'
            ]);


        }
    }
}

if ( ! function_exists('ongridPreviousInsurerMapping'))
{
    function ongridPreviousInsurerMapping($ongrid_previous_insurer, $previous_insurer_data)
    {
        $previous_insurers = ['rahejaqbe','reliance','acko','bajajallianz','bhartiaxa','cholamandalam','dhfl','edelweiss','futuregenerali','godigit','hdfcergo','icicilombard','iffcotokio','kotakmahindra','liberty','magmahdi','national','royalsundaram','sbi','shriram','tataaig','newindia','oriental','unitedindia','universalsompo','navi','m/s.ggif','l&t','hdfcchubb', 'generalinsurancecorporationofindia','newindiaassurance','gujratgovernmentinsurancefund','exportcreditguaranteecorporationofindialtd','agricultureinsuranceco.ofindialtd.'];

        foreach ($previous_insurers as $previous_insurer)
        {
            if (strpos(str_replace(' ', '', strtolower($ongrid_previous_insurer)), $previous_insurer) !== FALSE)
            {
                foreach ($previous_insurer_data as $prev_insurer_data)
                {
                    if (strpos(str_replace(' ', '', strtolower($prev_insurer_data['name'])), $previous_insurer) !== FALSE)
                    {
                        return [
                            'code' => $prev_insurer_data['code'],
                            'name' => $prev_insurer_data['name']
                        ];
                    }
                }
            }
        }

        return NULL;
    }
}

if (!function_exists('getProposalCustomErrorMessage')) {
    function getProposalCustomErrorMessage($message, $company_alias, $product_type)
    {
        //return $message;
        // $message = is_array($message) ? json_encode() : $message;
        $message = empty($message) ? 'Invalid Response From IC service' : (is_string($message) ? $message : json_encode($message));
        $message = trim($message);
        // if (config('IS_PROPOSAL_IC_ERROR_HANDLING_ENABLED') == 'Y') {
        //     $insert_data = [
        //         'ic_error' => $message,
        //         'company_alias' => $company_alias,
        //         'section' => $product_type,
        //         'status' => 'N',
        //     ];
        //     insertProposalIcCustomMessage($insert_data);
        // }

        if (config('IS_PROPOSAL_IC_ERROR_HANDLING_ENABLED') == 'Y') {
            $custom_error = \App\Models\ProposalIcErrorHandling::select('custom_error')
                ->where([
                    ['checksum', '=', checksum_encrypt($message)],
                    ['company_alias', '=', $company_alias],
                    ['section', '=', $product_type],
                    ['status', '=', 'Y'],
                ])->first();
        }
        // return $message;
        return $custom_error->custom_error ?? $message;
    }
}
if ( ! function_exists('insertProposalIcCustomMessage'))
{
    function insertProposalIcCustomMessage($data)
    {
        //\App\Models\ProposalIcErrorHandling::create($data);
        if (\App\Models\ProposalIcErrorHandling::where('ic_error', $data['ic_error'])->where('company_alias', $data['company_alias'])->where('section', $data['section'])->doesntExist()) {
            \App\Models\ProposalIcErrorHandling::updateOrCreate($data,$data);
        }        
    }
}
if (!function_exists('get_value_without_imt')) {
    function get_value_without_imt($value)
    {
        return round($value * 100 / 115);
    }
}

if (!function_exists('getRegisterNumberWithHyphen')) {
    function getRegisterNumberWithHyphen($vehcile_number)
    {
        $vehcile_number = str_replace('-', '', $vehcile_number);
        $new_reg_num = [];
        foreach (str_split($vehcile_number) as $key => $value) 
        {
            if(count($new_reg_num) == 0)
            {
               $new_reg_num[] = $value;
            }
            else
            {
                if(is_numeric(end($new_reg_num)) !== is_numeric($value))
                {
                    $new_reg_num[] = '-';               
                }
                $new_reg_num[] = $value;                
            }
        }

        if (is_numeric($new_reg_num[3])  && $new_reg_num[4] == '-') {
            $new_reg_num = array_merge(array_slice($new_reg_num, 0, 3), array("0"), array_slice($new_reg_num, 3));
        }

        $totalHyphens = array_count_values($new_reg_num)['-'] ?? 0;
        if($totalHyphens == 1) {
            $new_reg_num = array_merge(array_slice($new_reg_num, 0, 5), array("-"), array_slice($new_reg_num, 5));
        };
        $regNo = formatRegistrationNo(implode('',$new_reg_num));
        return $regNo;
    }
}

//Adding zero if we don't get data from the DB for cases like DL-1-XX-XXXX
if (!function_exists('getRegisterNumberWithOrWithoutZero')) {
    function getRegisterNumberWithOrWithoutZero($vehicle_number, $withZero = true)
    {
        $vehicle_number = explode('-', $vehicle_number);
        if (count($vehicle_number) == 4) {
            if ($withZero) {
                $vehicle_number[1] = str_pad($vehicle_number[1], 2, '0', STR_PAD_LEFT);
            } else {
                $vehicle_number[1] = ltrim($vehicle_number[1], '0');
            }
            return implode('-', $vehicle_number);
        }
        return implode('-', $vehicle_number);
    }
}

if (!function_exists('generateRandomString')) {
    function generateRandomString($length) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

if (!function_exists('abiblPhoneUpdate')) {
    function abiblPhoneUpdate($phone, $id){
        $phone = is_array($phone) ? $phone[0] : $phone;
        if(!empty($id) && !empty($phone)){
            UserProductJourney::where('user_product_journey_id', $id)->update(['user_mobile' => $phone]);
        } 
    }
}

if (!function_exists('getProductSubTypeName')) {
    function getProductSubTypeName($policy_id){
        try{
            $product_sub_type_id = MasterPolicy::find($policy_id)->product_sub_type_code->product_sub_type_id;
            $master_product_sub_type_name = MasterPolicy::find($policy_id)->product_sub_type_code->product_sub_type_name;

            $gcv_array = [4,13,14,15,16,9];
            $pcv_array = [8,6,10,7,11,5,12];

            if(in_array(trim($product_sub_type_id), $gcv_array)){
               return "Commercial Vehicle Insurance-Goods Carrying Vehicle";
            }

            if(in_array(trim($product_sub_type_id), $pcv_array)){
                return "Commercial Vehicle Insurance-Passenger Carrying Vehicle";
            }

            return  $master_product_sub_type_name;
        }catch(Exception $e){
            return "";
        }
    }
}

if (!function_exists('getFilePathFrom_Url')) {
    function getFilePathFrom_Url($url)
    {
        parse_str($url, $output);
        return customDecrypt($output[array_key_first($output)], false);
    }
}


if (!function_exists('update_quote_web_servicerequestresponse;')) {
    function update_quote_web_servicerequestresponse($table, $id, $message, $status){

        if(!empty($id) && !empty($message)  && !empty($table)){
            if($table == "quote_webservice_request_response_data"){
                QuoteServiceRequestResponse::where('id', $id)
                    ->update([
                        'status' => $status,
                        'message' => $message
                        // 'responsible' => $data['response']
                ]);
                updateQuoteVisibilityLogs($id, $status, $message);
            }elseif($table == "webservice_request_response_data") {
                WebServiceRequestResponse::where('id', $id)
                    ->update([
                        'status' => $status,
                        'message' => $message
                        // 'responsible' => $data['response']
                ]);
            }
            
        }
    }
}        
if (!function_exists('premium_updation_proposal')) {
    function premium_updation_proposal($enquiryId, $proposal_premium = [], $addon_array = [])
    {
        if (config("ENABLE_UPDATE_SIDECARD_DATA_AT_PROPOSAL") == 'Y') {
            // get values from user proposal and update quote log
            $quote = QuoteLog::where(["user_product_journey_id" => $enquiryId])->first();
            $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            $cpa_amount = 0;
            $quote_data_change = false;
            $data_addon_change = false;
            $data_cpa_change = false;
            if (!empty($proposal_premium)) {
                foreach ($proposal_premium as $prop_key => $prop_value) {
                    if ($prop_key == 'cpa_amount') {
                        $cpa_amount = $proposal_premium['cpa_amount'];
                    } else if ($prop_key == 'total_discount') {
                        if ($proposal_premium['total_discount'] > 0) {
                            $premium_json = $quote->premium_json;
                            $premium_json['finalTotalDiscount'] = $proposal_premium['total_discount'];
                            $quote->premium_json = $premium_json;
                            $quote_data_change = true;
                        }
                    } else {
                        $quote->{$prop_key} = $prop_value;
                        $quote_data_change = true;
                    }

                }
            } else {

                $basic_od = $user_proposal->od_premium ?? 0;
                $total_discount = $user_proposal->total_discount ?? 0;
                $total_od = round($basic_od + $total_discount);
                $quote->final_premium_amount = $user_proposal->final_payable_amount ?? 0;
                $quote->od_premium = $total_od;
                $quote->tp_premium = $user_proposal->tp_premium ?? 0;
                $quote->addon_premium = $user_proposal->addon_premium ?? 0;
                $quote->revised_ncb = $user_proposal->total_discount ?? 0;
                $quote->service_tax = $user_proposal->service_tax_amount ?? 0;
                $cpa_amount = $user_proposal->cpa_premium ?? 0;
                $premium_json = $quote->premium_json;
                $premium_json['finalTotalDiscount'] = $user_proposal->total_discount ?? 0;
                $quote->premium_json = $premium_json;
                $quote_data_change = true;
            }


            if ($quote_data_change) {
                $quote->save();
            }
            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
            $compulsory_personal_accident = $selected_addons->compulsory_personal_accident;


            $applicable_addons = $selected_addons->applicable_addons;

            if ($cpa_amount > 0) {
                $cpa_array = '[{"name":"Compulsory Personal Accident"}]';
                $data_cpa_change = true;
            } else {
                $cpa_array = '[{"reason":"I do not have a valid driving license."}]';
                $data_cpa_change = true;

            }

            if ($data_cpa_change) {
                // $cpa_selection = json_encode($cpa_array);
                selectedAddons::where(["user_product_journey_id" => $enquiryId])->update(['compulsory_personal_accident' => $cpa_array]);
            }

            if ($data_addon_change) {
                $applicable_addons_changed = json_encode($applicable_addons);
                selectedAddons::where(["user_product_journey_id" => $enquiryId])->update(['applicable_addons' => $applicable_addons_changed]);
            }
        }
        return true;
    }
}
//This function is used to create Redirection URL as per Agent 
//arguments ($enquiryId,$section=>BIKE,CAR,CV,$status=>SUCCESS/FAILURE)
if (!function_exists('paymentSuccessFailureCallbackUrl')) 
{
    function paymentSuccessFailureCallbackUrl($enquiryId,$section,$status)
    {
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$enquiryId)
            ->whereIn('seller_type',['E','P'])
            ->first();$base_url = NULL;
        if(isset($pos_data->seller_type) && in_array($pos_data->seller_type,['P','E']))
        {
            //config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL_AGENT')//car
            //config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL_AGENT')//car
            //config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL_AGENT')//CV
            $base_url = config('constants.motorConstant.'.$section.'_PAYMENT_'.$status.'_CALLBACK_URL_AGENT');
        }
        if($base_url == NULL)
        {
            //config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL')//car
            //config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL')//BIKE
            //config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL')//CV
           $base_url = config('constants.motorConstant.'.$section.'_PAYMENT_'.$status.'_CALLBACK_URL'); 
        }
        if(config('PAYMENTSUCCESSFAILURECALLBACKURL_SAMEAS_FRONTEND_ENABLE') == 'Y')
        {
            if(!empty($base_url))
            {
                
                $cv_joruney_stages = CvJourneyStages::where('user_product_journey_id',$enquiryId)
                                                    ->first();
               if(isset($cv_joruney_stages->proposal_url) &&  !empty($cv_joruney_stages->proposal_url))
               {
                $URL_CV = explode(strtolower('/'.$section),$cv_joruney_stages->proposal_url);
                $URL_PRESENT = parse_url($base_url);
                $base_url = $URL_CV[0].$URL_PRESENT['path'];
               }else if(isset($cv_joruney_stages->quote_url) &&  !empty($cv_joruney_stages->quote_url))
               {
                $URL_CV = explode(strtolower('/'.$section),$cv_joruney_stages->quote_url);
                $URL_PRESENT = parse_url($base_url);
                $base_url = $URL_CV[0].$URL_PRESENT['path'];
               }
                
            }
        }
        return $base_url.'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]);
    }
}

if (!function_exists('print_pre')) {
    function print_pre($str)
    {
        print_r("<pre>");
        print_r($str);
        print_r("<pre>");
    }
}
if (!function_exists('iciciLombardBreakInStatusApi')) {
    function iciciLombardBreakInStatusApi($breakinDetails, $additionalData) {
        if($additionalData['section'] == 'cv') {
            include_once app_path() . '/Helpers/CvWebServiceHelper.php';
        }else if($additionalData['section'] == 'bike') {
            include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
        }
        else {
            include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        }
        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
            'scope' => 'esbmotor',
        ];

        $token_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionalData);
        if(config('constants.motorConstant.SMS_FOLDER')=='OneClick')
        {
            $tokenParam = rawurldecode(http_build_query($tokenParam));
            $token_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), $tokenParam, 'icici_lombard', $additionalData);
        }
        $token_response = $token_response['response'];
        $token = json_decode($token_response, true);
        if (!isset($token['access_token'])) {
            return [
                'status' => false,
                'message' => "Insurer not reachable,Issue in Token Generation service"
            ];
        }
        $access_token = $token['access_token'];
        $corelationId = getUUID($breakinDetails->user_product_journey_id);
        $url=config('constants.IcConstants.icici_lombard.END_POINT_URL_GET_ICICI_LOMBARD_BREAKIN_STATUS_API');
        $data = getWsData($url, [
            'CorrelationId' => $corelationId,
            'breakinId' => $breakinDetails->breakin_number
        ], 'icici_lombard', [
            'enquiryId'         => $breakinDetails->user_product_journey_id,
            'token' => $access_token,
            'requestMethod'     => 'post',
            'section' => $additionalData['section'],
            'company'           => 'icici_lombard',
            'type'            => 'checkBreakinStatus',
            'transaction_type'  => 'proposal'
        ]);

        $data = $data['response'];
        $proposal_resp_array = json_decode($data, TRUE);

        if (isset($proposal_resp_array['status'])) {
            if (isset($proposal_resp_array['status']) == true) {
                $proposal_resp_array['token']=$access_token;
                return [
                    'status' => true,
                    'data' => $proposal_resp_array
                ];
            } else {
                return [
                    'status' => false,
                    'message' => $proposal_resp_array['message']
                ];
            }
        }
        else {
            return [
                'status' => false,
                'message' => 'insurer not reachable'
            ];
        }
    }
}
if (!function_exists('iciciLombardBreakInClearStatusApi')) {
    function iciciLombardBreakInClearStatusApi($data, $breakinDetails, $additionData)
    {
        $brknId=$data['breakinId'];
        $inspection_data = [
            'InspectionId' => $brknId,
            'ReferenceDate' => date('d/m/Y', strtotime($breakinDetails->created_at)),
            'CorrelationId' => $data['correlationId'],
            'InspectionStatus' => "OK",
            'ReferenceNo' => $breakinDetails->proposal_no
        ];

        $master_policy_id = QuoteLog::where('user_product_journey_id', $breakinDetails->user_product_journey_id)
            ->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);

        switch ($productData->product_sub_type_id) {
            case '1'://car
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');

                $product_code = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');

                $section = 'car';
                break;
            case '6': //taxi
                $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN');
                $product_code = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
                $section = 'taxi';
                break;

            case '2'; # FOR BIKE
            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_BREAKIN');
            $product_code = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
            $section = 'bike';
            break;
            
            case '9':
            case '13':
            case '14':
            case '14':
                $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN');
                $product_code = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
                $section = 'GCV Breakin';
                break;
        }

        $additionData['type'] = 'brekinInspectionStatus';
        $additionData['token'] = $data['token'];

        $is_pos = 'N';
        $is_icici_pos_disabled_renewbuy = config('constants.motorConstant.IS_ICICI_POS_DISABLED_RENEWBUY');
        $is_pos_enabled = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $breakinDetails->user_product_journey_id)
            ->where('user_proposal_id', $breakinDetails->user_proposal_id)
            ->where('seller_type', 'P')
            ->first();
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if ($pos_data) {
                $is_pos = 'Y';
                $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                $CertificateNumber = $pos_data->unique_number; #$pos_data->user_name;
                $PanCardNo = $pos_data->pan_no;
                $AadhaarNo = $pos_data->aadhar_no;
            }
            $ProductCode = $product_code;
            if($pos_testing_mode === 'Y'){
                $is_pos = 'Y';
                $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                $CertificateNumber = 'TMI0001';
                $PanCardNo = 'ABGTY8890Z';
                $AadhaarNo = '569278616999';
            }
        } elseif ($pos_testing_mode === 'Y') {
            $is_pos = 'Y';
            $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
            $CertificateNumber = 'TMI0001';
            $PanCardNo = 'ABGTY8890Z';
            $AadhaarNo = '569278616999';
            $ProductCode = $product_code;
        } else {
            $inspection_data['DealNo'] = $deal_id;
        }

        if ($is_pos == 'Y') {
            if (isset($inspection_data['DealNo'])) {
                unset($inspection_data['DealNo']);
            }
        } else {
            if (!isset($inspection_data['DealNo'])) {
                $inspection_data['DealNo'] = $deal_id;
            }
        }

        if ($is_pos == 'Y') {
            $pos_details = [
                'pos_details' => [
                    'IRDALicenceNumber' => $IRDALicenceNumber,
                    'CertificateNumber' => $CertificateNumber,
                    'PanCardNo'         => $PanCardNo,
                    'AadhaarNo'         => $AadhaarNo,
                    'ProductCode'       => $ProductCode
                ]
            ];
            $additionData = array_merge($additionData, $pos_details);
        }

        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLEAR_INSPECTION_STATUS'), $inspection_data, 'icici_lombard', $additionData);

        return $get_response;
    }
}

if (!function_exists('GetKycStatusGoDIgit')) 
{
    function GetKycStatusGoDIgit($user_product_journey_id,$proposal_no, $product_name,$user_proposal_id,$userProductJourneyId,$applicationiduse = false)
        {
            $proposal = UserProposal::where('user_product_journey_id',$user_product_journey_id)
            ->first();
            include_once app_path() . '/Helpers/CarWebServiceHelper.php';
            if($applicationiduse)
            {
                $KycVerfiyApi = config('constants.IcConstants.godigit.GODIGIT_KYC_VERIFICATION_API') . '?applicationId='.$proposal_no;
            }else
            {
                $KycVerfiyApi = config('constants.IcConstants.godigit.GODIGIT_KYC_VERIFICATION_API') . '?policyNumber='.$proposal_no;
            }
            $mode = $proposal->ckyc_type ?? 'NA';
            $mode_type =  strtoupper(str_replace("_"," ",$mode));
            $start_time = microtime(true) * 1000;
            $KycVerfiyApiResponse = getWsData($KycVerfiyApi,[],'godigit',
            [
                'enquiryId' => $user_product_journey_id,
                'requestMethod' =>'get',
                'section' => 'CAR',
                'productName'  => $product_name,
                'company'  => 'godigit',
                'method'   => 'Kyc Status',
                'transaction_type' => 'proposal',
                'webUserId' => config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID'),
                'password' => config('constants.IcConstants.godigit.GODIGIT_PASSWORD'),
            ]);
            $end_time = microtime(true)* 1000;
            $response_time = $end_time - $start_time . ' ms';
            $KycVerfiyApiResponseData = $KycVerfiyApiResponse['response'];
            $request = ["URL" => $KycVerfiyApi];
            if (!empty($KycVerfiyApiResponseData)) 
            {
                $KycVerfiyApiResponseDecoded = json_decode($KycVerfiyApiResponseData);
                $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
                $password  = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');
                $reqHeaders =[
                    'Content-type'  => 'application/json',
                    'Authorization: Basic '.base64_encode("$webUserId:$password"),
                    'Accept: application/json'
                ];
                // $KycVerfiyApiResponseDecoded->kycVerificationStatus = 'NOT_DONE';
                CkycCommonController::GodigitSaveCkyclog($user_product_journey_id,$KycVerfiyApi,$request,$KycVerfiyApiResponseDecoded,$reqHeaders,$end_time,$start_time);
               
                if (isset($KycVerfiyApiResponseDecoded->kycVerificationStatus) && in_array($KycVerfiyApiResponseDecoded->kycVerificationStatus, ['DONE','SKIP'])) 
                {
                    CkycLogsRequestResponse::create([
                        'enquiry_id' => $user_product_journey_id,
                        'company_alias' => 'godigit',
                        'mode' => $mode_type,
                        'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                        'response' => json_encode($KycVerfiyApiResponseDecoded, JSON_UNESCAPED_SLASHES),
                        'headers' => json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
                        'endpoint_url' => $KycVerfiyApi,
                        'status' => 'Success',
                        'failure_message' => null,
                        'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                        'start_time' => date('Y-m-d H:i:s', $start_time / 1000),
                        'end_time' => date('Y-m-d H:i:s', $end_time / 1000),
                        'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2)
                    ]);

                    $updateProposal = UserProposal::where('user_proposal_id' , $user_proposal_id)->first();

                    if (!empty($updateProposal)) {
                        $updateProposal->update([
                            'ckyc_reference_id' => $KycVerfiyApiResponseDecoded->referenceId ?? ''
                        ]);
                    }
                        
                    if(\Illuminate\Support\Facades\Storage::exists('ckyc_photos/'.$userProductJourneyId)) 
                    {
                        \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/'.$userProductJourneyId);
                    }

                    return [
                        'status' => true,
                        'message' => $KycVerfiyApiResponseDecoded->link,
                        'response' => $KycVerfiyApiResponseDecoded ?? ''
                    ];

                }else if (isset($KycVerfiyApiResponseDecoded->kycVerificationStatus) && (in_array($KycVerfiyApiResponseDecoded->kycVerificationStatus, ['FAILED','NOT_DONE','NA'] ) )) 
                {

                    CkycLogsRequestResponse::create([
                        'enquiry_id' => $user_product_journey_id,
                        'company_alias' => 'godigit',
                        'mode' => $mode_type,
                        'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                        'response' => json_encode($KycVerfiyApiResponseDecoded, JSON_UNESCAPED_SLASHES),
                        'headers' => json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
                        'endpoint_url' => $KycVerfiyApi,
                        'status' =>'not_a_failure',
                        'failure_message' => null,
                        'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                        'start_time' => date('Y-m-d H:i:s', $start_time / 1000),
                        'end_time' => date('Y-m-d H:i:s', $end_time / 1000),
                        'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2)
                    ]);

                    if(!empty(($KycVerfiyApiResponseDecoded->link)))
                    {
                        // return redirect($KycVerfiyApiResponseDecoded->link);\
                        return [
                            'status' => false,
                            'message' => $KycVerfiyApiResponseDecoded->link ?? '',
                            'response' => $KycVerfiyApiResponseDecoded ?? ''
                        ];
                    }else
                    {

                        $unique_proposal_id = UserProposal::select('unique_proposal_id')
                                      ->where('user_proposal_id',$user_proposal_id)
                                      ->first();
                        if(!empty($unique_proposal_id) && $applicationiduse == false)
                        {
                            return GetKycStatusGoDIgit($user_product_journey_id,$unique_proposal_id->unique_proposal_id, $product_name,$user_proposal_id,$userProductJourneyId,true);
                        }

                        return [
                            'status' => false,
                            'message' => $KycVerfiyApiResponseDecoded->link ?? '',
                            'response' => $KycVerfiyApiResponseDecoded ?? ''
                        ];
                    }
                    
                }else
                {
                    return [
                        'status' => false,
                        'message' => $KycVerfiyApiResponseDecoded->link ?? '',
                        'response' => $KycVerfiyApiResponseDecoded ?? ''
                    ];

                }
            }else
            {
                $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
                $password  = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');
                
                $reqHeaders =[
                    'Content-type'  => 'application/json',
                    'Authorization: Basic '.base64_encode("$webUserId:$password"),
                    'Accept: application/json'
                ];

                CkycLogsRequestResponse::create([
                    'enquiry_id' => $user_product_journey_id,
                    'company_alias' => 'godigit',
                    'mode' => $mode_type,
                    'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                    'response' => json_encode($KycVerfiyApiResponseData, JSON_UNESCAPED_SLASHES),
                    'headers' => json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
                    'endpoint_url' => $KycVerfiyApi,
                    'status' =>'Failed',
                    'failure_message' => "No Response from service",
                    'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                    'start_time' => date('Y-m-d H:i:s', $start_time / 1000),
                    'end_time' => date('Y-m-d H:i:s', $end_time / 1000),
                    'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2)
                ]);

                return [
                    'status' => false,
                    'message' => 'No response from KYC STATUS API.'
                ];

            }
        }
}

if (!function_exists('GetInspectionApplicableStatus')) {
    function GetInspectionApplicableStatus($premium_type_id, $company_id , $product_sub_type_id)
    {
        if (in_array($premium_type_id, [1, 4])) {
            $premium_type_id = 4;
        } else if (in_array($premium_type_id, [3, 6])) {
            $premium_type_id = 6;
        }else if (in_array($premium_type_id, [5, 9])) {
            $premium_type_id = 9;
        }else if (in_array($premium_type_id, [8, 10])) {
            $premium_type_id = 10;
        }
       return MasterPolicy::where('product_sub_type_id', $product_sub_type_id)
            ->where('insurance_company_id', $company_id)
            ->where('premium_type_id', $premium_type_id)
            ->where('status', 'Active')
            ->exists();
    }
}

if (!function_exists('checkProposalModified')) {
    function checkProposalModified($enquiryId, $lastUpdated)
    {

        try {
            $proposal = UserProposal::with([
                'quote_log'  => function ($query) {
                    $query->select('user_product_journey_id','updated_at');
                },
                'selected_addons'=>function ($query) {
                    $query->select('user_product_journey_id','updated_at');
                }
            ])
                ->where('user_product_journey_id', $enquiryId)
                ->select('user_proposal_id','user_product_journey_id','updated_at')
                ->first();

            if ($proposal && $proposal->updated_at) {
                $t1 = strtotime($proposal->quote_log->updated_at);
                $t2 = strtotime($proposal->selected_addons->updated_at);
                $t3 = strtotime($proposal->updated_at);

                if (($t2 > $t3) || ($t1 > $t3)) {

                    UserProposal::where('user_product_journey_id', $enquiryId)->update([]);

                    return [
                        'status' => false,
                        'message' => 'Proposal integrity check failed. You will be redirected to quote page.'
                    ];
                }

                if (($lastUpdated && $t3 != $lastUpdated)) {

                    UserProposal::where('user_product_journey_id', $enquiryId)->update([]);

                    return [
                        'status' => false,
                        'message' => 'Proposal integrity check failed.'
                    ];
                }
            }

            return [
                'status' => true
            ];
        } catch (\Throwable $th) {
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }
}
//Return Gender Name BY Gender Code
if (!function_exists('getGenderName')) {
    function getGenderName($company_alias,$gender_code)
    {
        return Gender::where('company_alias', $company_alias)
                ->where('gender_code', $gender_code)
                ->pluck('gender')
                ->first();     
    }
}
//Return Gender Code BY Gender Name
if (!function_exists('getGenderCode')) {
    function getGenderCode($company_alias,$gender_name)
    {
        return Gender::where('company_alias', $company_alias)
                ->where('gender', $gender_name)
                ->pluck('gender_code')
                ->first();     
    }
}

//Return FrontendUrl as per section and seller type
if (!function_exists('getFrontendUrl')) {
    function getFrontendUrl($section,$userProductJourneyId)
    {
        $CvAgentMapping = CvAgentMapping::where('user_product_journey_id',$userProductJourneyId)->first();

        $seller_type = $CvAgentMapping->seller_type ?? '';

        $frontendURLS = config('constants.motorConstant.FRONTEND_URLS');

        $frontend_url = '';

        if(!empty(json_decode(($frontendURLS ?? []), 1)[(!$seller_type == "") ? $seller_type : "DEFAULT"][$section] ?? ''))
        {
            $frontend_url = json_decode($frontendURLS, 1)[(!$seller_type == "") ? $seller_type : "DEFAULT"][$section];
            return $frontend_url;
        }
        return $frontend_url;
    }
}

//Return FrontendUrl as per section and seller type
if (!function_exists('isSectionMissmatched')) {
    function isSectionMissmatched($request, $section, $registrationNo)
    {
        if(config('proposalPage.vehicleValidation.enableIsCheckSectionMissmatched') != 'Y'){
            return [
                "status" => true,
            ];
        }
        $request->request->add([
            'registration_no'   => $registrationNo,
            'section'           => $section,
            'vehicleValidation' => 'Y',
            'action'            => 'sectionMismatch'
         ]);
  
        $common = new CommonController;
        $response = $common->getVehicleDetails($request);

        $response = is_array($response) ? $response : json_decode(json_encode($response->getData()),true);

        $block_journey = config('proposalPage.vehicleValidation.failureCase.blockJourney') == 'Y';

        $isVahanConfiguratorEnabled = config('vahanConfiguratorEnabled') == 'Y';

        if($isVahanConfiguratorEnabled && (($response['data']['status'] ?? 0) == 102 || ($response['data']['showErrorMsg'] ?? false))) {
            $message = 'Vahan Record not found. Block journey';
            if (!empty($response['data']['overrideMsg'])) {
                $message = $response['data']['overrideMsg'];
            }
            return [
                "status" => false,
                "message" => $message
            ];
        } elseif ($block_journey && (($response['data']['status'] ?? 0) == 102 || ($response['data']['showErrorMsg'] ?? false))) {
            return [
                "status" => false,
                "message" => 'Record not found. Block journey'
            ];
        }
        else
        {
            $enquiry_id = customDecrypt($request->userProductJourneyId);
            $id = UserProductJourney::find($enquiry_id)->product_sub_type_id ?? null;
            $parentId = strtolower(get_parent_code($id));
            if(isset($response['data']['ft_product_code']) && $response['data']['ft_product_code'] != $section){

                $frontend_url = config('constants.motorConstant.' . strtoupper($response['data']['ft_product_code']) . '_FRONTEND_URL');
                if(config('constants.motorConstant.DYNAMIC_FRONTEND_URLS') == 'Y')
                {
                    $frontend_url = getFrontendUrl($response['data']['ft_product_code'], $enquiry_id);
                }
                return [
                    "status" => false,
                    "message" => "You've entered the registration number of a ". ($response['data']['ft_product_code']),
                    "frontend_url" => $frontend_url,
                    "ft_product_code" => $response['data']['ft_product_code']
                ];
            }
            else if(isset($response['data']['sub_section']) && ($response['data']['sub_section'] ?? '') != $parentId)
            {
                $frontend_url = config('constants.motorConstant.CV_FRONTEND_URL');
                if(config('constants.motorConstant.DYNAMIC_FRONTEND_URLS') == 'Y'){
                    $frontend_url = getFrontendUrl('CV', $enquiry_id);
                }
                return [
                    "status" => false,
                    "message" => "You've entered the registration number of a ". ($response['data']['sub_section']),
                    "frontend_url" => $frontend_url,
                    "ft_product_code" => 'cv'
                ];
            }
            return [
                "status" => true,
                "data" => $response,
            ];
        }
    }
}

//check if pdf data is valid or not
if (!function_exists('checkValidPDFData')) {
    function checkValidPDFData($pdfData)
    {

        if((!strpos($pdfData, "%PDF") !== false) && (!strpos($pdfData, "%%EOF") !== false))
        {
            return false;
        }
        else
        {
            return true;
        }
    }
}

//check if pdf file is valid or not
if (!function_exists('checkValidPDFFile')) {
    function checkValidPDFFile($pdf_url)
    {
        $pdfData = httpRequestNormal($pdf_url, 'GET', [], [], [], [], false)['response'];

        return checkValidPDFData($pdfData);
    }
}

if (!function_exists('getFastLaneData')) {
    function getFastLaneData($cUrl = '', $additionalData = array()) {

        $global_timeout = (int) config("WEBSERVICE_TIMEOUT", 45);
        if (!empty(config("fastlane.WEBSERVICE_TIMEOUT"))) {
            $global_timeout = (int) config( "fastlane.WEBSERVICE_TIMEOUT", 45);
        }
        $global_with_timeout = (int) config("WEBSERVICE_WITH_TIMEOUT", 45);
        if (!empty(config("fastlane.WEBSERVICE_WITH_TIMEOUT"))) {
            $global_with_timeout = (int) config("fastlane.WEBSERVICE_WITH_TIMEOUT", 45);
        }
        $curl = Curl::to($cUrl)
            ->withHeader('Content-type: application/json')
            ->withHeader('Accept: application/json')
            ->withHeader('Authorization:Basic ' . base64_encode($additionalData['username'] . ':' . $additionalData['password']));
        $startTime = new DateTime(date('Y-m-d H:i:s'));

        if (!empty(config('constants.http_proxy'))) {
            $curl = $curl->withProxy(config('constants.http_proxy'));
        }
        $curl->withTimeout($global_with_timeout);
        $curl->withConnectTimeout($global_timeout);
        $curlResponse = $curl->get();
        $endTime = new DateTime(date('Y-m-d H:i:s'));
        $responseTime = $startTime->diff($endTime);
        $wsLogdata = [
            'enquiry_id'        => $additionalData['enquiryId'],
            'transaction_type'  => 'Fast Lane Service',
            'request'           => $additionalData['reg_no'],
            'response'          => $curlResponse,
            'endpoint_url'      => $cUrl,
            'ip_address'        => request()->ip(),
            'response_time'    => $responseTime->format('%H:%i:%s'),
            'created_at'        => Carbon::now(),
            'section'           => $additionalData['section'] ?? null
        ];
        $data = \App\Models\FastlaneRequestResponse::create($wsLogdata);
        return ['webservice_id' => $data->id ?? null, 'table' => 'fastlane_request_response', 'response' => $curlResponse];
    }
}

if (!function_exists('RtoCodeWithOrWithoutZero')) 
{
    function RtoCodeWithOrWithoutZero($rto_code,$with_zero = false) 
    {
        if (strpos($rto_code, '-') !== false) 
        {
            $rtoCode = explode('-',$rto_code);
            
        }else
        {
            $rtoCode = str_split($rto_code, 2);
        }

        if(isset($rtoCode[1]) && $rtoCode[1] <= 9 && strlen($rtoCode[1]) < 2 && $with_zero)
        {
            $rtoCode[1] = '0'.$rtoCode[1];
        }

        if (!$with_zero && intval($rtoCode[1]) <= 9) {
            $rtoCode[1] = str_replace('0','',$rtoCode[1]);
        }

        $returnRtoCode = $rtoCode[0]."-".$rtoCode[1];
  
        return strtoupper($returnRtoCode);

    }

}

if (!function_exists('duplicateVehicleRegistrationNumberPolicyCheck')) 
{
    function duplicateVehicleRegistrationNumberPolicyCheck($registrationNo, $enquiryId) {
        
        
        $result = UserProposal::select('user_proposal.policy_end_date')
        ->where('vehicale_registration_number',$registrationNo)
        ->whereNotIn('user_proposal.user_product_journey_id',[$enquiryId])
        ->join('cv_journey_stages','cv_journey_stages.user_product_journey_id','=','user_proposal.user_product_journey_id')
        ->whereIn('cv_journey_stages.stage',[ STAGE_NAMES['POLICY_ISSUED'],STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])
        ->get();
        
        $days=config('duplicateVehicleRegistrationNumber.daysCondition', 45);
        foreach($result as $r) {
            if ($r->policy_end_date && strtotime($r->policy_end_date) > strtotime(date('d-m-Y'))) {
                if (((new DateTime(date('d-m-Y')))->diff((new DateTime($r->policy_end_date))))->days > $days) {
                    return ['status' => false];
                }
            }
        }
        return ['status'=>true];
    }
}

if (!function_exists('removeSpecialCharactersFromString')) 
{
    function removeSpecialCharactersFromString($string, $space = false) 
    {
        $regex = "/[^a-zA-Z0-9]+/";

        if ($space) {
            $regex = "/[^a-zA-Z0-9 ]+/";
        }
        return preg_replace($regex, "", $string);
    }
}

if(!function_exists('checkRehitSourceStatusExist'))
{
    function checkRehitSourceStatusExist($user_proposal_id, $rehit_source)
    {
        if(!empty($user_proposal_id))
        {
            return PolicyDetails::where('proposal_id', $user_proposal_id)
                                ->where('rehit_source', $rehit_source)
                                ->exists();
        }
    }
}
if (!function_exists('getCommonConfig')) {
    function getCommonConfig($keyName, $default = null)
    {
        // Check if the cache is set for the required key value
        // If the cache is not set, then fetch the value from the DB and set it in cache.
        $cacheKeyName = request()->header('host') . '_common_config_' . trim($keyName);
        if (\Illuminate\Support\Facades\Cache::has($cacheKeyName)) {
            return \Illuminate\Support\Facades\Cache::get($cacheKeyName);
        } else {
            $value = \App\Models\CommonConfigurations::select('value')->where('key', $keyName)->first()?->value;
            if (!is_null($value)) {
                \Illuminate\Support\Facades\Cache::remember($cacheKeyName, 3600, function () use ($value){
                    return trim($value);
                });
            }
            // Note : If the $value variable is null, then also $default will be returned
            return $value ?? $default;
        }
        
    }
}

if (!function_exists('getMotorAddons')) {
    function getMotorAddons($masterPolicyId, $planName = '', $vehicleSegment = 'nonpremium', $vehicleMake = 'other_make')
    {
        $selectedColumns = array_diff(array_keys(MasterMotorAddon::first()->getAttributes()), explode(',', config('DYNAMIC_MASTER_MOTOR_ADDON_TABLE_COLUMN_NAMES_REMOVING') ?? ''));

        $policy = MasterPolicy::with(['master_product', 'product_sub_type_code', 'premium_type'])
            ->where('policy_id', $masterPolicyId)
            ->whereNotIn('premium_type_id', [2, 7])
            ->first();

        if (!$policy || empty($selectedColumns)) {
            return array_fill_keys($selectedColumns, '0 years');
        }

        $productType = $policy->product_sub_type_id;
        $companyId = $policy->insurance_company_id;

        $selectedAddons = MasterMotorAddon::where('company_id', $companyId)
            ->where(fn($query) =>  $query->where('plan_name', $planName)->orWhere('plan_name', '')->orWhere('plan_name', null))
            ->where('product_sub_type_id', $productType)
            ->where('vehicle_segment', $vehicleSegment)
            ->where('vehicle_make', $vehicleMake)
            ->first();

        if (!$selectedAddons) {
            return array_fill_keys($selectedColumns, '0 years');
        }

        $result = [];
        foreach ($selectedColumns as $column) {
            $value = $selectedAddons->$column;
            if (empty($value)) {
                $result[$column] = 0;
            } elseif (strpos($value, '-') !== false) {
                $ageValidation = '';
                $ages = explode('-', $value);
                foreach ($ages as $i => $age) {
                    $ageUnit = ($i === 0) ? ' years' : (($i === 1) ? ' months' : ' days');
                    $ageValidation .= $age . $ageUnit . (($i === count($ages) - 1) ? '' : '-');
                }
                $result[$column] = $ageValidation;
            } else {
                $result[$column] = $value . ' years';
            }
        }
        $result['included_addons'] = $selectedAddons->included;
        return $result;
    }
}

function getIndividualAddonsvalue(
    $addon_validation_array,
    $addon_name,
    $true_value,
    $false_value,
    $car_age_year,
    $car_age_months = 0,
    $car_age_days = 0,
    $consider_inbuilt_addons_true = false,
    $include_other_addons_in_plan = true
) {
    /* $addon_name to be from following

        road_side_assistance
        zero_depreciation
        key_replacement
        engine_protector
        ncb_protection
        consumable
        tyre_secure
        return_to_invoice
        loss_of_personal_belongings
        emergency_medical_expenses

    */
    // If any of the required parameters is empty, return the false value
    if (empty($addon_validation_array) || empty($addon_name) || empty($true_value) || !is_numeric($car_age_year)) {
        return $false_value;
    }

    $is_inbuilt_addon = false;
    $included_addons = [];
    if(!empty($addon_validation_array['included_addons']))
    {
        $included_addons = explode(',',$addon_validation_array['included_addons']) ?? [];
        $is_inbuilt_addon = in_array($addon_name,$included_addons) ? true : false ;

    }

    foreach ($addon_validation_array as $key => $value) {
        if ($key !== $addon_name) {
            continue;
        }
        
        preg_match_all('/\d+/', $value, $age_from_validation);
        $year_validation = $age_from_validation[0][0] ?? 0;
        $month_validation = $age_from_validation[0][1] ?? 0;
        $day_validation = $age_from_validation[0][2] ?? 0;
        
        if ($car_age_year <= $year_validation && $car_age_months <= $month_validation && $car_age_days <= $day_validation) {

            if(count($included_addons) > 0 && $include_other_addons_in_plan && !$is_inbuilt_addon)
            {
                return $false_value;
            }
            return $true_value;
        }else if($consider_inbuilt_addons_true && $is_inbuilt_addon)
        {
            return $true_value;
        }
    }
    
    return $false_value;
}

if (!function_exists('getApplicableAddons')) {
    function getApplicableAddons($old_applicable_addons, $addtional_addons , $inbuilt_addons)
    {
        #check addon premium amount in additional addon ,if it's zero remove from applicable addon
        $new_addon = [];
            foreach ($addtional_addons as $k => $v) {
                if ($v == 0) {
                    array_push($new_addon, $k);
                }
            }
            $new_applicable_addons = array_diff($old_applicable_addons,
                $new_addon
            );
            $new_array = array_merge($new_applicable_addons, array_keys($inbuilt_addons));
            return array_unique($new_array);
    }
}
if (!function_exists('isBhSeries')) {
    function isBhSeries($reg_no)
    {
        if (!empty($reg_no)) {
            $string = strtoupper(preg_replace('/-/', '', $reg_no));
            return preg_match('/^.{2}([BH]{2}).*$/', $string, $matches) ? true : false;
        }else{
            return false;
        }
    }
}
if (!function_exists('getIdvData')) {
    function getIdvData($exShowRoomPrice, $interval)
    {
        if ($interval->y < 1) {
            if ($interval->m < 6) {
                $age_interval = '0-0.5';
            } else {
                $age_interval = '0.5-1';
            }
        } else {
            $age_interval = $interval->y . '-' . ($interval->y + 1);
        }
        $idv_data = DB::table('united_idv_percentages')->where('age_interval', $age_interval)->first();
        if (!empty($idv_data)) {
            $idv = ceil($exShowRoomPrice * (100 - $idv_data->percentage) / 100);
            $data =  (object)['status' => true, 'idv' => $idv];
        } else {
            $data = (object) [
                'idv' => '0',
                'status' => false,
                'message' => 'Idv details are not available for given age',
                'request' => [
                    'age_interval' => $age_interval,
                    'interval' => $interval,
                    'exShowRoomPrice' => $exShowRoomPrice
                ]
            ];
        }
        return $data;
    }
}
if (!function_exists('getIdvData')) {
    function getIdvData($exShowRoomPrice, $interval)
    {

        for ($i = 1; $i < 10; $i++) {
            if ($interval->y > ($i) && $interval->y <= (($i + 1))) {
                $idv_data = DB::table('united_idv_percentages')->where('age_interval', $i . '-' . ($i + 1))->first();
                if (!empty($idv_data)) {
                    $idv = ceil($exShowRoomPrice * (100 - $idv_data->percentage) / 100);
                    return (object)['idv' => $idv];
                }

                break;
            }
        }
    }
}

if(!function_exists('isValidPolicyNumber')) //true if only alphanumaric and '-' and '/' are present with min length of 5
{
    function isValidPolicyNumber($value)
    {
        return (!(preg_match('/[^a-zA-Z0-9\/-]|-{2,}|\/{2,}/', $value)) && strlen($value)>=5);
    }
}

if (!function_exists('godigitPaymentStatusCheck')) {
    function godigitPaymentStatusCheck($user_product_journey_id, $policyid, $proposal_no)
    {
        /* $policyid= QuoteLog::where('user_product_journey_id',$user_product_journey_id)->pluck('master_policy_id')->first(); */
        $product = getProductDataByIc($policyid);

        $url = config('constants.IcConstants.godigit.GODIGIT_BREAKIN_STATUS') . trim($proposal_no);
        // sleep(5);
        if ($product->product_sub_type_code == 'CAR') {
            include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        } else if ($product->product_sub_type_code == 'BIKE') {
            include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
        } else {
            include_once app_path() . '/Helpers/CvWebServiceHelper.php';
        }

        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $user_product_journey_id,
            'seller_type' => 'P'
        ])
        ->first();
        $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
        $password = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');

        if (!empty($posData)) {
            
            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $product->product_sub_type_id,
                'ic_integration_type' => $product->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $webUserId = $credentials['data']['web_user_id'];
                $password = $credentials['data']['password'];
            }
        }

        $get_response = getWsData(
            $url,
            '',
            'godigit',
            [
                'enquiryId' => $user_product_journey_id,
                'requestMethod' => 'get',
                'section' => $product->product_sub_type_code,
                'productName' => $product->product_name,
                'company' => 'godigit',
                'method' => 'Check Policy Status',
                'transaction_type' => 'proposal',
                'webUserId' => $webUserId,
                'password' => $password,
            ]
        );
        $data = $get_response['response'];
        if ($data) {
            $policy_status_data = json_decode($data, TRUE);

            if (isset($policy_status_data['policyStatus']) && (in_array($policy_status_data['policyStatus'], ['EFFECTIVE', 'COMPLETE', 'UW_REFFERED']) ||  ($policy_status_data['policyStatus'] == 'INCOMPLETE' && in_array($policy_status_data['kycStatus']['paymentStatus'] ?? '', ['PAID', 'DONE'])))) {
                $return_data = [
                    'status' => true,
                    'msg' => $policy_status_data['policyStatus'],
                ];
            } elseif (isset($policy_status_data['policyStatus']) && ($policy_status_data['policyStatus'] == 'INCOMPLETE' || $policy_status_data['policyStatus'] == 'DECLINED')) {
                $return_data = [
                    'status' => false,
                    'msg' => $policy_status_data['policyStatus'],
                ];
            } else {
                $return_data = [
                    'status' => false,
                    'msg' => 'Error in service',
                ];
            }
        } else {

            $return_data = [
                'status' => false,
                'msg' => 'Error in service',
            ];
        }
        return $return_data;
    }
}

if (!function_exists('changeRegNumberFormat')) { 
    #to convert regNo strings of bh series (22BH9780A, 22BH-9780-A, 22/BH/9780/A, 22BH/9780/A) to type '22-BH-9780-A'
    function changeRegNumberFormat($regNo)
    {
        if(strtoupper($regNo) == 'NEW') {
            return $regNo;
        }
        if(isBhSeries($regNo)) {
            $regNo = preg_replace('/[^A-Za-z0-9]/', '', $regNo);
            preg_match('/^(\d+)([A-Za-z]+)(\d+)([A-Za-z]+)$/', $regNo, $matches);
            $regNo = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . '-' . $matches[4];
            return $regNo;
        }
        return getRegisterNumberWithHyphen($regNo);
    }
}

if (!function_exists('hasFullDate')) {
    function hasFullDate($dateString, $format)
    {
        try {
            $date = \Carbon\Carbon::createFromFormat($format, $dateString);
            return $date && $date->format($format) == $dateString;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('checkValidRcNumber')) {
    function checkValidRcNumber($rcNumber) {

        $RcNumberBlockData = RcNumberBlockData::where('rc_number', str_replace("-", "", $rcNumber)) 
            ->where('status', 'deny')
            ->exists();
        return $RcNumberBlockData;
    }
}

if (!function_exists('isHTMLUrlValid'))
{
    function isHTMLUrlValid($url)
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get($url);

            $contentType = $response->getHeaderLine('content-type');
            if (strpos($contentType, 'text/html') !== false) {
                return true;
            }
        } catch (\Exception $e) {
            // Handle any errors that occurred during the request
        }

        return false;
    }
}

if(!function_exists('readHTMLResponse'))
{
    function readHTMLResponse($url)
    {
        $client = new \GuzzleHttp\Client();
    
        try {
            // Extract the base URL
            $parsedUrl = parse_url($url);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $response = $client->get($url);
    
            $htmlContent = $response->getBody()->getContents();
            // You now have the HTML content as a string in the $htmlContent variable
    
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true); // Disable libxml errors and warnings
            $dom->loadHTML($htmlContent);
    
            $data = []; // Array to store extracted data
    
            // Extract desired data from HTML using DOMDocument and DOMXPath
            // Here's an example of extracting all <a> tags and their href properties from the HTML
            $xpath = new \DOMXPath($dom);
            $aNodes = $xpath->query('//a');
            foreach ($aNodes as $aNode) {
                $elementData = [
                    'text' => $aNode->textContent,
                    'href' => $aNode->getAttribute('href'),
                    'href_params' => $aNode->getAttribute('href.params'),
                    'base_url' => $baseUrl 
                    // Add more properties as needed
                ];
                $data[] = $elementData;
            }
    
            // Return the data as JSON or array
            return response()->json($data); // or return $data; for array format
        } catch (\Exception $e) {
            // Handle any errors that occurred during the request
        }
    
        return null;
    }
}

if(!function_exists('additionalActionOnCovers'))
{
    function additionalActionOnCovers($user_product_journey_id, $data_response)
    {
        $quoteData = $data_response;
        $selected_addons = SelectedAddons::where('user_product_journey_id', $user_product_journey_id)->first();
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();
        $selected_accessories = [
                'motorElectricAccessoriesValue','motorNonElectricAccessoriesValue','motorLpgCngKitValue','cngLpgTp','defaultPaidDriver','motorAdditionalPaidDriver','coverUnnamedPassengerValue','antitheftDiscount','voluntaryExcess','tppdDiscount',
            ];
    
            $lpg_cng_fuel_kit = $non_electrical_accessories = $electrical_accessories = $ll_paid_driver = $motor_additional_paid_driver = $cover_unnamed_passenger_value = $anti_theft_discount = $voluntary_excess = $tppd_discount = $geog_extension_cover = false;

            foreach ($accessories as $key => $value) {
                if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
    
                    $lpg_cng_fuel_kit = true;
                
                } else if ($value['name'] == 'Non-Electrical Accessories') {
    
                    $non_electrical_accessories = true;
    
                } elseif ($value['name'] == 'Electrical Accessories') {

                    $electrical_accessories = true;
                }
            }
    
            foreach ($additional_covers as $key => $value) {
                
                if ($value['name'] == 'LL paid driver') {
    
                    $ll_paid_driver = true;
                
                } elseif ($value['name'] == 'PA cover for additional paid driver') {
    
                    $motor_additional_paid_driver = true;
    
                } elseif ($value['name'] == 'Unnamed Passenger PA Cover') {

                    $cover_unnamed_passenger_value = true;

                } elseif ($value['name'] == 'LL paid driver' || $value['name'] == 'LL paid driver/conductor/cleaner') {

                    $ll_paid_driver = true;

                } elseif ($value['name'] == 'PA cover for additional paid driver' || $value['name'] == 'PA paid driver/conductor/cleaner') {

                    $motor_additional_paid_driver = true;

                } elseif ($value['name'] == 'Geographical Extension') {

                    $geog_extension_cover = true;
                } 
                
            }

            foreach ($discounts as $key => $value) {
                
                if ($value['name'] == 'anti-theft device') {
    
                    $anti_theft_discount = true;
                
                } elseif ($value['name'] == 'voluntary_insurer_discounts') {
    
                    $voluntary_excess = true;
    
                } elseif ($value['name'] == 'TPPD Cover') {

                    $tppd_discount = true;
                }
            }
            $CNG_Inbuilt = strtoupper("CNG (Inbuilt)");
            $LPG_Inbuilt = strtoupper("LPG (Inbuilt)");
            if($lpg_cng_fuel_kit == false && !in_array(strtoupper($quoteData['data']['fuelType']), ['CNG', 'LPG', 'INBUILT',$CNG_Inbuilt,$LPG_Inbuilt])) {
                unset($quoteData['data']['motorLpgCngKitValue']);
                unset($quoteData['data']['cngLpgTp']);
            }

            if($non_electrical_accessories == false){

                unset($quoteData['data']['motorNonElectricAccessoriesValue']);
            }

            if($electrical_accessories == false) {

                unset($quoteData['data']['motorElectricAccessoriesValue']);
            }

            if ($ll_paid_driver == false && !in_array($quoteData['data']['masterPolicyId']['insuranceCompanyId'] , ['28','45'])) {

                unset($quoteData['data']['defaultPaidDriver']);
            }

            if ($motor_additional_paid_driver == false) {

                unset($quoteData['data']['motorAdditionalPaidDriver']);
            }

            if ($geog_extension_cover == false) {

                unset($quoteData['data']['GeogExtension_ODPremium']);
                unset($quoteData['data']['GeogExtension_TPPremium']);
            }

            if ($cover_unnamed_passenger_value == false && !in_array($quoteData['data']['masterPolicyId']['insuranceCompanyId'] , ['11','28'])) {

                unset($quoteData['data']['coverUnnamedPassengerValue']);
            }

            if ($anti_theft_discount == false) {

                unset($quoteData['data']['antitheftDiscount']);
            }

            if ($voluntary_excess == false) {

                unset($quoteData['data']['voluntaryExcess']);
            }

            if (($tppd_discount == false) && ($corporate_data->is_renewal != "Y")){

                unset($quoteData['data']['tppdDiscount']);
            }

        return $quoteData;
    }
}

if(!function_exists('JwtTokenDecode'))
{
    function JwtTokenDecode($token)
    {
        $tokenParts = explode(".", $token);  
        $tokenHeader = base64_decode($tokenParts[0]);
        $tokenPayload = base64_decode($tokenParts[1]);
        $jwtHeader = json_decode($tokenHeader);
        $jwtPayload = json_decode($tokenPayload);
        $exp_date = Carbon::parse($jwtPayload->exp)->format('Y-m-d');
        $start_date = Carbon::parse($jwtPayload->iat)->format('Y-m-d');
        $agent_id = $jwtPayload->sub;
        $jwtPayload->exp_date = $exp_date;
        $jwtPayload->start_date = $start_date;
        $jwtPayload->agent_id = $agent_id;
        
        if(!empty($jwtPayload->exp))
        {
            return [
                "status" => true,
                'token_data' => json_decode(json_encode($jwtPayload), true)
            ];
        }else
        {
            return [
                "status" => false,
                'token_data' => ''
            ];

        }
    }
}

if(!function_exists('GramcoverTokenExpiryCheck'))
{
    function GramcoverTokenExpiryCheck($enquiry_id)
    {
        $agent_data = CvAgentMapping::where('user_product_journey_id', $enquiry_id)
        ->whereNotNull('token')
        ->first();
        if(!empty($agent_data->token))
        {
            $t_data = JwtTokenDecode($agent_data->token);
            if($t_data['status'] == true && !empty($t_data['token_data']['exp']))
            {
                $now = strtotime('now');
                $t_time = $t_data['token_data']['exp'];
                if($t_time < $now)
                {
                    $redirection_link = config('USER_DASHBOARD_LINK');
                    if(strtoupper($agent_data->seller_type) == 'P')
                    {
                        $redirection_link = config('POS_DASHBOARD_LINK');

                    }else if(strtoupper($agent_data->seller_type) == 'E')
                    {
                        $redirection_link = config('EMPLOYEE_DASHBOARD_LINK');
                    }
                    return   [
                                'status' => false ,
                                'message' => config('GRAMCOVER_TOKEN_EXPIRED_MESSAGE') ?? 'Your token is expired please login again.',
                                'redirection_link' => $redirection_link,
                                'agent_data' => $agent_data->toArray()
                             ];
                }else
                {
                    return [ 'status' => true ];    
                }

            }else
            {
                return [ 'status' => true ]; 
            }                  
        }else
        {
            return [ 'status' => true ]; 
        }
    }
}

if(!function_exists('getDecryptedEnquiryId'))
{
    function getDecryptedEnquiryId($encrypted_id){
        $traceId = customDecrypt($encrypted_id);
        $enquiry_id = DB::table('user_product_journey')->select('created_on')->where('user_product_journey_id', $traceId)->first();
        return \Carbon\Carbon::parse($enquiry_id->created_on)->format('Ymd') . sprintf('%08d', $traceId);
    }
}

if(!function_exists('StoreCommunicationLogs'))
{
    function StoreCommunicationLogs($service_type,$user_product_journey_id,$request,$response,$status,$communication_module = 'OTHER',$day = '1',$old_user_product_journey_id = 0,$prev_policy_end_date = null, $queue_number = null)
    {
        if($queue_number && $queue_number > 0 && Schema::hasTable('communication_logs_' . $queue_number)) {
            $save_status = DB::table('communication_logs_' . $queue_number)->insert(
                [
                    'user_product_journey_id' => $user_product_journey_id,
                    'service_type' => $service_type,
                    'request' => $request,
                    'response' => $response,
                    'communication_module' => $communication_module,
                    'status' => $status,
                    'days' => $day,
                    'old_user_product_journey_id' => $old_user_product_journey_id,
                    'prev_policy_end_end' => $prev_policy_end_date,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        } else {
            $save_status = \App\Models\CommunicationLogs::Create(
                [
                    'user_product_journey_id' => $user_product_journey_id,
                    'service_type' => $service_type,
                    'request' => $request,
                    'response' => $response,
                    'communication_module' => $communication_module,
                    'status' => $status,
                    'days' => $day,
                    'old_user_product_journey_id' => $old_user_product_journey_id,
                    'prev_policy_end_end' => $prev_policy_end_date,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
        return [
            'status' => $save_status ? true : false
        ];
    }   
}

if(!function_exists('createPresignedUrl'))
{
    function createS3PresignedUrl($fileKey)
    {
        $filename = "Policy-Document.pdf";
        if (preg_match('/([^\/]+\.pdf)$/', $fileKey, $matches)) {
            $filename = $matches[1];
        }

        $presignedUrl = \Illuminate\Support\Facades\Storage::disk("s3")->temporaryUrl(
            $fileKey, 
            now('UTC')->addDay(config('DATE_INTERVAL_FOR_S3_BUCKET', 7)),
            [
                'ResponseContentDisposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
        return $presignedUrl;
    }
}

if(!function_exists('getGenericMethodName'))
{
    function getGenericMethodName(String $dirtyMethodName, String $transactionType) {
        if ($transactionType == 'proposal') {
            $proposalMethods = config('webservicemethods.proposalAllMethods');
            return $proposalMethods[$dirtyMethodName] ?? $dirtyMethodName;
        } else if ($transactionType == 'quote') {
            $quoteMethods = config('webservicemethods.quoteAllMethods');
            return $quoteMethods[$dirtyMethodName] ?? $dirtyMethodName;
        }
        return $dirtyMethodName;
    }
}

if(!function_exists('idVehiclePartialBuild'))
{
    function getVehiclePartialBuild($version_id) 
    {
        $is_partial_build = false;
        $env_folder = config('app.env') == 'local' ? 'uat' : 'production';
        $path = 'mmv_masters/' . $env_folder . '/';
        $file_name  = $path.'gcv_model_version.json';
        $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
        $data = collect($data)[$version_id];
        if(in_array($data['vehicle_built_up'],['PARTIALLY BUILT','Partial Built','Partial Built Vehicle']))
        {
           $is_partial_build = true;
        }
        return [
            'is_partial_build' => $is_partial_build,
            'vehicle_build_type' => $data['vehicle_built_up']
        ];
    }
}

if(!function_exists('mergeArrays')) {
    function mergeArrays(&$result, $arrayToMerge) {
        foreach ($arrayToMerge as $key => $value) {
            if (is_array($value)) {
                if (!isset($result[$key])) {
                    $result[$key] = [];
                }
                mergeArrays($result[$key], $value);
            } else {
                if (is_numeric($value)) {
                    if (!isset($result[$key])) {
                        $result[$key] = 0;
                    }
                    $result[$key] += $value;
                }
            }
        }
    }
}

function generateHourlyDateRanges($from,$to)
{
    $timeRanges = collect();

    $current = $from->copy();

    while ($current < $to && $current < (now()->subHour())) {
        $timeRanges->push(['from' => $current->copy(), 'to' => $current->copy()->endOfHour()]);
        $current->addHour();
    }

    return $timeRanges;
}

function insertIntoQuoteVisibilityLogs($quoteData, $section = null, $masterPolicyId = null) {
    // switch for enable/disable the new logic.
    if (config('visibility.disableRevisedQuotes') == 'Y') {
        return;
    }
    if (empty($masterPolicyId)) {
        return;
    }
    // $quoteWebserviceRecord = QuoteServiceRequestResponse::find($quoteId);
    $allowedMasterPolicyIds = explode(',', config('quote.visibility.allowedMasterPolicyIds'));
    if (empty($allowedMasterPolicyIds)) {
        return;
    }
    $consider_for_visibility_report =  MasterPolicy::where('policy_id', $masterPolicyId)->first()->consider_for_visibility_report;
    // Do the insertion, if the master policy id matches with the request's policyId
    if (in_array($masterPolicyId, $allowedMasterPolicyIds)) {
        QuoteVisibilityLogs::insert([
            'enquiry_id' => $quoteData['enquiry_id'],
            'message' => $quoteData['message'] ?? null,
            'master_policy_id' => $masterPolicyId,
            'quote_webservice_id' => $quoteData['id'],
            'product' => $quoteData['product'],
            'method_name' => $quoteData['method_name'],
            'company' => $quoteData['company'],
            'section' => $section,
            'response_time' => $quoteData['response_time'],
            'transaction_type' => $quoteData['transaction_type'],
            'status' => $quoteData['status'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } elseif ($consider_for_visibility_report === 1) {
        QuoteVisibilityLogs::insert([
            'enquiry_id' => $quoteData['enquiry_id'],
            'message' => $quoteData['message'] ?? null,
            'master_policy_id' => $masterPolicyId,
            'quote_webservice_id' => $quoteData['id'],
            'product' => $quoteData['product'],
            'method_name' => $quoteData['method_name'],
            'company' => $quoteData['company'],
            'section' => $section,
            'response_time' => $quoteData['response_time'],
            'transaction_type' => $quoteData['transaction_type'],
            'status' => $quoteData['status'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function updateQuoteVisibilityLogs($id, $status, $message) {
    // switch for enable/disable the new logic.
    if (config('visibility.disableRevisedQuotes') == 'Y') {
        return;
    }
    $allowedMasterPolicyIds = explode(',', config('quote.visibility.allowedMasterPolicyIds'));
    if (empty($allowedMasterPolicyIds)) {
        return;
    }
    $not_a_failure_cache_name = 'visibility.notAFailure.errorMessages';
    $naf_cache = \Illuminate\Support\Facades\Cache::get($not_a_failure_cache_name);
    if(empty($naf_cache)) {
        // It will be cached for 2 hours.
        $naf_error_messages = cache()->remember($not_a_failure_cache_name, 60 * 60 * 2, function () {
            return \App\Models\QuoteVisibilityNotAFailureErrorMessages::where('status', 'Active')->select('error_message')->get();
        });
    } else {
        $naf_error_messages = $naf_cache;
    }
    if ($naf_error_messages->isNotEmpty() && $status == 'Failed') {
        $naf_error_messages->each(function($value) use (&$status, $message) {
            $msg = $value->error_message;
            if (Str::contains($message, $msg)) {
                $status = 'not_a_failure';
                return false;
            }
        });
    }
    // Update the record with status and message
    $getRecord = QuoteVisibilityLogs::where('quote_webservice_id', $id)->select('enquiry_id', 'master_policy_id', 'created_at')->limit(1);
    $record = $getRecord->get()->first();
    $getRecord->update([
        'status' => $status,
        'message' => $message,
        'updated_at' => now()
    ]);
    if(empty($record)) {
        return;
    }

    // Delete old record if master_policy_id exist for older rows.
    $toBeDeletedIds = QuoteVisibilityLogs::where('quote_webservice_id', '<', $id)
        ->where('enquiry_id', $record->enquiry_id)
        ->where('master_policy_id', $record->master_policy_id)
        ->whereBetween('created_at', [Carbon::parse($record->created_at)->startOfDay(), Carbon::parse($record->created_at)->endOfDay()])
        ->pluck('id')->toArray();
    if (!empty($toBeDeletedIds)) {
        DeleteOldRecordFromVisibilityTable::dispatch($toBeDeletedIds)
            ->onQueue( ( !empty( env('VISIBILITY_REPORT_QUEUE') ) ? env('VISIBILITY_REPORT_QUEUE') : "default" ) )
            ->delay(
                now()->addMinutes(config('visibility.deleteOldRecords.delay', 10))
            );
    }
}


if(!function_exists('generateUserActivityToken')) {
    function generateUserActivityToken($enquiryId)
    {
        UserJourneyActivity::updateOrCreate([
            'user_product_journey_id' => $enquiryId
        ],[
            'st_token' => getUUID(),
            'ls_token' => getUUID()
        ]);
    }
}

if(!function_exists('formatRegistrationNo')) {
    function formatRegistrationNo($regNo)
    {
        $regArray = explode('-', $regNo);
        if(count($regArray) == 4) {
            return implode('-', $regArray);
        }
        if(count($regArray) == 3) {
            if(is_numeric($regArray[2])) {
                $regArray[3] = $regArray[2];
                $regArray[2] = '';
            }
            return implode('-', $regArray);
        }
        return implode('-', $regArray);
    }
}

if(!function_exists('splitAndFormatRegistrationNumber')) {
    function splitAndFormatRegistrationNumber($regNo)
    {
        $regNo = str_replace('--', '-', $regNo);
        $regArray = explode('-', $regNo);
        if(count($regArray) == 4) {
            return implode('-', $regArray);
        }
        if(count($regArray) == 3) {
            if(is_numeric($regArray[2]) && strlen($regArray[2]) == 4) {
                $regArray[3] = substr($regArray[2], 2, 2);
                $regArray[2] = substr($regArray[2], 0, 2);
            }
            return implode('-', $regArray);
        }
        return implode('-', $regArray);
    }
}

function enquiryIdEncryption($id) {
    if (empty($id) || strlen((string)$id) == 0) {
        throw new \App\Exceptions\EnquiryIdEncryptionFailed('Enquiry ID Encryption Failed. Invalid parameter.');
    }
    try {
        $list = [ 0 => 'AGS', 1 => 'JSH', 2 => 'IDK', 3 => 'KLS', 4 => 'IEU', 5 => 'OPA', 6 => 'ABQ', 7 => 'MLS', 8 => 'BCV', 9 => 'OQP' ];
        $splittedTraceId = collect(str_split($id));
        $temp = [];
        $splittedTraceId->each(function ($value) use ($list, &$temp) {
            $temp[] = $list[(int) $value];
        });
        $newTraceId = implode('-', $temp);
        // Encypt new trace id with rot 13
        $newTraceId = str_rot13($newTraceId);
        // Now base64 encode that string
        return base64_encode($newTraceId);
    } catch (\Exception $e) {
        throw new \App\Exceptions\EnquiryIdEncryptionFailed('Enquiry ID Encryption Failed.');
    }
}

function enquiryIdDecryption($data) {
    if (empty($data) || strlen((string)$data) == 0) {
        throw new \App\Exceptions\EnquiryIdDecryptionFailed('Enquiry ID Decryption Failed. Invalid parameter - '.$data);
    }
    try {
        // Decode the encode string
        $base64Decoded = base64_decode($data);
        // Decode the rot 13 string
        $rotDecoded = str_rot13($base64Decoded);
        // Replace the manual characters
        $list = [ 'AGS' => 0,'JSH' => 1,'IDK' => 2,'KLS' => 3,'IEU' => 4,'OPA' => 5,'ABQ' => 6,'MLS' => 7,'BCV' => 8,'OQP' => 9 ];
        
        $splittedCharacters = collect(explode('-', $rotDecoded));
        $temp = [];
        $splittedCharacters->each(function ($value) use ($list, &$temp) {
            $temp[] = $list[$value];
        });
        return implode('', $temp);
    } catch (\Exception $e) {
        throw new \App\Exceptions\EnquiryIdDecryptionFailed('Enquiry ID Decryption Failed - '.$data);
    }   
}

function getDecryptedNumericEnquiryId($value) {
    $date = Str::substr($value, 0, 8);
    $date = Carbon::parse($date)->format('Y-m-d');
    $id = Str::substr($value, 8);
    $enquiry_id = cache()->remember($value, config('cache.expiration_time'), function() use($date, $id) {
        return DB::table('user_product_journey')->whereDate('created_on', $date)->where('user_product_journey_id', $id)->first(); //->user_product_journey_id;
    }); 
    abort_if(!$enquiry_id, 500);
    return $enquiry_id->user_product_journey_id;
}

if(!function_exists('calculateAgentDiscount')) {
    function calculateAgentDiscount($enquiryId, $companyAlias, $vehcileType) {
        return AgentDiscountController::getIcDiscount($enquiryId, $companyAlias, $vehcileType);
    }
}

if (!function_exists('removeSalutation')) {
    function removeSalutation($name)
    {
        $name = explode(' ', $name);

        if (!empty($name)) {
            if (in_array($name[0], ['MR', 'MRS', 'MISS', 'MS', 'Mr', 'Mrs', 'Miss', 'Ms'])) {
                array_shift($name);
            }

            return implode(' ', $name);
        }

        return null;
    }
}

if(!function_exists('acceptBothEncryptDecryptTraceId')){
    function acceptBothEncryptDecryptTraceId($enquiry_id){
        if (config('enquiry_id_encryption') == 'Y') {
            try {
                $enquiryId = null;
                if (strlen($enquiry_id) == 16 && config('enquiry_id_encryption') == 'Y' && (int)$enquiry_id) {
                    $new_enquiryId = \Illuminate\Support\Str::substr($enquiry_id, 8);
                    $enquiryId = customDecrypt(customEncrypt($new_enquiryId));
                } else if ($enquiry_id) {
                    $enquiryId = customDecrypt($enquiry_id);
                }
            } catch (\Throwable $th) {
                return redirect()->back()->withInput()->with('error', "Invalid enquiry id");
            }
        } else {
            if (is_numeric($enquiry_id)) {
                $enquiryId = customDecrypt($enquiry_id);
            } else if ($enquiry_id) {
                $enquiryId = enquiryIdDecryption($enquiry_id);
            }
        } 
        return $enquiryId;
    }
}

if (!function_exists('createCvAgentMappingEntryForAgent')) {
    function createCvAgentMappingEntryForAgent($data)
    {
        // if(!(app()->environment() == 'local')) return ['status' => false, 'message' => 'action not allowed', 'show_error' => true];

        if(empty($data['user_product_journey_id'] ?? '')) {
            return [
                'status' => false,
                'message' => 'enquiryId should not be empty',
                'overrideMsg' => 'enquiryId should not be empty',
                'show_error' => true
            ];
        }
 
        if(!empty(($data['agent_id'] ?? '')) && !empty(($data['seller_type'] ?? ''))) {
            $agentData = CvAgentMapping::where('user_product_journey_id', $data['user_product_journey_id'])->whereNotNull('seller_type')->where('seller_type', '!=', '')
            ->first();

            if(!empty($agentData)) {
                if($agentData->agent_id == $data['agent_id']) {
                    return ['status' => true, 'message' => 'Journey already exist for same agent'];
                } else {

                    if (config('constants.brokerConstants.DISABLE_AGENT_VALIDATION') == 'Y') {
                        return [
                            'status' => true
                        ];
                    }
                    return [
                        'status' => false,
                        'message' => 'This journey belongs to some another agent',
                        'overrideMsg'=> 'This journey belongs to some another agent',
                        'show_error' => true
                    ];
                }
            } else {
                //CvAgentMapping::create($data);
                CvAgentMapping::updateOrCreate(
                    ['user_product_journey_id' => $data['user_product_journey_id']],
                    $data
                );
                return ['status' => true, 'message' => 'Data Inserted'];
            }
        } else {
            $agentData = CvAgentMapping::where('user_product_journey_id', $data['user_product_journey_id'])->whereNull('seller_type')->orWhere('seller_type', '=', '')
            ->first();

            if(!empty($agentData)) {
                return ['status' => true, 'message' => 'Journey already exist'];
            } else {
                // CvAgentMapping::create($data);
                CvAgentMapping::updateOrCreate(
                    ['user_product_journey_id' => $data['user_product_journey_id']],
                    $data
                );
                return ['status' => true, 'message' => 'Data Inserted'];

            }
        }
    }
}

function pushTraceIdInLogs($explicit_trace_id = null) {
    $possible_trace_ids = collect([
        'userProductJourneyId',
        'enquiry_id',
        'enquiryId',
        'trace_id',
        'traceId',
    ]);
    $front_end_trace_id = null;
    $possible_trace_ids->each(function ($v) use (&$front_end_trace_id) {
        if (request()->has($v)) {
            $front_end_trace_id = request()->{$v};
        }
    });

    if ($explicit_trace_id) {
        \Illuminate\Support\Facades\Log::withContext([
            'debugTraceId' => $explicit_trace_id
        ]);
    }else if ($front_end_trace_id) {
        \Illuminate\Support\Facades\Log::withContext([
            'debugTraceId' => $front_end_trace_id
        ]);
    }
}

function setCommonConfigInCache(): void
{
    if(Schema::hasTable('common_configurations'))
    {
        $common_configs = \Illuminate\Support\Facades\Cache::remember(request()->header('host') . '_common_configurations_all', 3600, function () {
            return \App\Models\CommonConfigurations::get(['key', 'value']);
        });

        foreach ($common_configs as $key => $value) {
            \Illuminate\Support\Facades\Cache::remember(request()->header('host') . '_common_config_' . trim($value->key), 3600, function () use ($value) {
                return trim($value->value);
            });
        }
    }
}

function lanninsportCode ($request){
         
    $headers = $request->header();
    // $lannincode = $headers['lanninsport'][0];
    $lannincode = empty($headers) ? null :  $headers['lanninsport'][0];

    return $lannincode;
}

if (!function_exists('godigitRtoCode')) {
    function godigitRtoCode($rtoCode)
    {
        $rtoCode = RtoCodeWithOrWithoutZero($rtoCode, false);
        $rtoCode = explode('-', $rtoCode);
        if (($rtoCode[0] ?? '') == 'DL' && isset($rtoCode[1]) && !is_numeric($rtoCode[1]) && strlen($rtoCode[1]) > 1) {
            $rtoCode[1] = is_numeric(substr($rtoCode[1], 0, 2)) ? substr($rtoCode[1], 0, 2) : (is_numeric(substr($rtoCode[1], 0, 1)) ? '0' . substr($rtoCode[1], 0, 1) : $rtoCode[1]);
        } else {
            if (isset($rtoCode[1]) && is_numeric($rtoCode[1]) && strlen($rtoCode[1]) == 1) {
                $rtoCode[1] = '0' . $rtoCode[1];
            }
        }
        return implode('-', $rtoCode);
    }
}

if (!function_exists('savePremiumDetails')) {
    function savePremiumDetails($enquiry_id, $premium_details) {
        $otherData = [];
        $file = debug_backtrace();
        if (isset($file[0])) {
            $file = $file[0];
            unset($file['args']);
            $otherData['file'] = $file;
        }

        $od_premium = $premium_details['basic_od_premium'] + $premium_details['electric_accessories_value'] +
        $premium_details['non_electric_accessories_value'] + $premium_details['bifuel_od_premium'] +
        $premium_details['geo_extension_odpremium'] - ($premium_details['limited_own_premises_od'] ?? 0);

        $updateData = [
            'details' =>  [
                // OD Tags
                "basic_od_premium" => $premium_details['basic_od_premium'],
                'od_premium' => $od_premium,
                "loading_amount" => $premium_details['loading_amount'],
                "final_od_premium" => $premium_details['final_od_premium'],
                // TP Tags
                "basic_tp_premium" => $premium_details['basic_tp_premium'],
                "final_tp_premium" => $premium_details['final_tp_premium'],
                // Accessories
                "electric_accessories_value" => $premium_details['electric_accessories_value'],
                "non_electric_accessories_value" => $premium_details['non_electric_accessories_value'],
                "bifuel_od_premium" => $premium_details['bifuel_od_premium'],
                "bifuel_tp_premium" => $premium_details['bifuel_tp_premium'],
                // Addons
                "compulsory_pa_own_driver" => $premium_details['compulsory_pa_own_driver'],
                "zero_depreciation" => $premium_details['zero_depreciation'],
                "road_side_assistance" => $premium_details['road_side_assistance'],
                "imt_23" => $premium_details['imt_23'],
                "consumable" => $premium_details['consumable'],
                "key_replacement" => $premium_details['key_replacement'],
                "engine_protector" => $premium_details['engine_protector'],
                "ncb_protection" => $premium_details['ncb_protection'],
                "tyre_secure" => $premium_details['tyre_secure'],
                "return_to_invoice" => $premium_details['return_to_invoice'],
                "loss_of_personal_belongings" => $premium_details['loss_of_personal_belongings'],
                "eme_cover" => $premium_details['eme_cover'],
                "wind_shield" => $premium_details['wind_shield'] ?? 0,
                "accident_shield" => $premium_details['accident_shield'],
                "conveyance_benefit" => $premium_details['conveyance_benefit'],
                "passenger_assist_cover" => $premium_details['passenger_assist_cover'],
                "motor_protection" => $premium_details['motor_protection'] ?? 0,
                "battery_protect" => $premium_details['battery_protect'] ?? 0,
                "additional_towing" => $premium_details['additional_towing'] ?? 0,
                // Covers
                "pa_additional_driver" => $premium_details['pa_additional_driver'],
                "unnamed_passenger_pa_cover" => $premium_details['unnamed_passenger_pa_cover'],
                "ll_paid_driver" => $premium_details['ll_paid_driver'],
                "ll_paid_employee" => $premium_details['ll_paid_employee'] ?? 0,
                "ll_paid_conductor" => $premium_details['ll_paid_conductor'] ?? 0,
                "ll_paid_cleaner" => $premium_details['ll_paid_cleaner'] ?? 0,
                "geo_extension_odpremium" => $premium_details['geo_extension_odpremium'],
                "geo_extension_tppremium" => $premium_details['geo_extension_tppremium'],
                "in_built_addons" => $premium_details['in_built_addons'] ?? [],
                // Discounts
                "anti_theft" => $premium_details['anti_theft'],
                "voluntary_excess" => $premium_details['voluntary_excess'],
                "tppd_discount" => $premium_details['tppd_discount'],
                "other_discount" => $premium_details['other_discount'],
                "ncb_discount_premium" => $premium_details['ncb_discount_premium'],
                "limited_own_premises_od" => $premium_details['limited_own_premises_od'] ?? 0,
                "limited_own_premises_tp" => $premium_details['limited_own_premises_tp'] ?? 0,
                // Final tags
                "net_premium" => $premium_details['net_premium'],
                "service_tax_amount" => $premium_details['service_tax_amount'],
                "final_payable_amount" => $premium_details['final_payable_amount'],

                //other data
                "others" => $otherData
            ]
        ];

        \App\Models\PremiumDetails::updateOrCreate(
            ['user_product_journey_id' => $enquiry_id],
            $updateData
        );
    }
}

if (!function_exists('checkTransactionStageValidity')) {
    function checkTransactionStageValidity($enquiryId)
    {
        $incompleteStages = [
            STAGE_NAMES['INSPECTION_ACCEPTED'],
            STAGE_NAMES['INSPECTION_PENDING'],
            STAGE_NAMES['INSPECTION_REJECTED'],

            STAGE_NAMES['PAYMENT_INITIATED'],
            STAGE_NAMES['PAYMENT_FAILED'],
            STAGE_NAMES['PAYMENT_SUCCESS'],

            STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            STAGE_NAMES['POLICY_ISSUED'],

            STAGE_NAMES['POLICY_CANCELLED']
        ];

        $journeyStageData = \App\Models\JourneyStage::where('user_product_journey_id', $enquiryId)
        ->whereIn('stage', $incompleteStages)
        ->first();

        if (!empty($journeyStageData)) {
            return [
                'status' => false,
                'msg' => 'This Transaction Already Completed',
                'data' => $journeyStageData
            ];
        } else {
            return [
                'status' => true,
                'msg' => 'Transaction is incomplete',
                'data' => $journeyStageData
            ];
        }
    }
}

if (!function_exists('get_web_service_data_via_checksum'))
{
    function get_web_service_data_via_checksum($enquiryId,$company,$checksum_data,$section)
    {
        //$minute = config('VALIDATION_FOR_CHECK_QUOTE_LOGS_DATA_IN_MINUTE',60);
        $minute = config('IC.CACHE.QUOTE.GLOBAL.TIME_IN_MINUTES',600);
        if(empty($minute))
        {
            $minute = 600;
        }

        if(config('IC.CACHE.QUOTE.'.strtoupper($company).'.STATUS') == 'Y' && !empty(config('IC.CACHE.QUOTE.'.strtoupper($company).'.TIME_IN_MINUTES')))
        {
            $minute =  config('IC.CACHE.QUOTE.'.strtoupper($company).'.TIME_IN_MINUTES');
        }

        $where = [
            'enquiry_id' => $enquiryId,
            'company'    => $company,
            'checksum'   => $checksum_data
        ];

        $web_service_data = QuoteServiceRequestResponse::where($where)
        ->where('created_at','>=',Carbon::now()->subMinute($minute))
        ->where('created_at','>',now()->startOfDay())
        //->where('status','Success')
        ->orderBy('id', 'DESC')
        ->get()->first();
        //dd($web_service_data);
        if(!empty($web_service_data))
        {
            $return_data =
            [
                'found'         => true,
                'webservice_id' => $web_service_data->id,
                'table'         => 'quote_webservice_request_response_data',
                'response'      => $web_service_data->response,
                'status'        => (strtolower($web_service_data->status) == 'success'),
                'message'       => $web_service_data->message
            ];
        }
        else
        {
            $return_data =
            [
                'found'         => false,
                'status'        => false
            ];
        }
        return $return_data;
    }
}

if (!function_exists('checksum_encrypt'))
{
    function checksum_encrypt($checksum)
    {
        return md5(json_encode($checksum));
    }
}

if (!function_exists('showLoadingAmount'))
{
    function showLoadingAmount($company_alias)
    {
        if (config('constants.ENABLE_LOADING_AMOUNT_LOGIC')) {
            return getCommonConfig('loadingConfig.ic.'.$company_alias, 'N') == 'Y';
        }
        return false;
    }
}

if (!function_exists('sortKeysAlphabetically')) {
    function sortKeysAlphabetically($data) {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            return $data;
        }

        $keys = array_keys($data);
        sort($keys, SORT_STRING | SORT_FLAG_CASE);

        $sorted = [];
        foreach ($keys as $key) {
            $value = $data[$key];
            if (is_array($value) || is_object($value)) {
                $sorted[$key] = sortKeysAlphabetically($value);
            } else {
                $sorted[$key] = $value;
            }
        }

        return $sorted;
    }
}

function is_base64($string){
    if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) return false;
    $decoded = base64_decode($string, true);
    if(false === $decoded) return false;
    if(base64_encode($decoded) != $string) return false;
    return true;
}
function removingExtraHyphen($string) {
    return str_replace("--", "-", $string);
}
function keysToLower($data)
{
    if($data instanceof \Illuminate\Support\Collection){
        $data = $data->map(function ($item){
            return keysToLower($item);
        });
        return $data;
    }
    if (is_object($data) && !is_array($data)) {
        $data = (array)$data;
        foreach ($data as $key => $value) {
            $new_data[strtolower($key)] = $data[$key];
            unset($data[$key]);
        }
        return (object)$new_data;
    }
}
function fullNameValidation($first_name, $last_name, $full_name)
{
    return str_replace(' ' , '' , strtoupper($first_name . $last_name)) == str_replace(' ' , '', strtoupper($full_name));
}

if (!function_exists('storeJourneyStageLog')) {
    function storeJourneyStageLog($userProductJourney_id, $previousStage, $currentStage)
    {
        if (empty($previousStage) && empty($currentStage)) {
            return false;
        }

        try {
            JourneyStageLog::create([
                'user_product_journey_id' => $userProductJourney_id,
                'previous_stage' => $previousStage,
                'current_stage' => $currentStage,
            ]);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error('Error creating JourneyStageLog: ', ['exception' => $th]);
        }
    }
}

if(!function_exists('FG_bodymaster'))
{
    function FG_bodymaster($body_type)
    {
        $master_body_type = [
             'AGRICULTURAL TRACTORS' => 'AGTR',
             'AMBULANCE' => 'AMBU',
             'ARTICULITED TRAILER' => 'ARTI',
             'BACKHOE LOADER' => 'BAHL',
             'BIKE' => 'BIKE',
             'BINDER' => 'BIND',
             'BITUMEN' => 'BITU',
             'BACKHOE LOADER' => 'BLDE',
             'BOREWELL DRILLING' => 'BOMD',
             'BOOM LIFT' => 'BOML',
             'BOOM PUMP' => 'BOMP',
             'BULLDOZER' => 'BULD',
             'BULKER' => 'BULK',
             'BULLDOZER' => 'BULL',
             'BUS' => 'BUSE',
             'BUS' => 'BUST',
             'Cabriolet/Convertable' => 'CABR',
             'CAMPER VAN' => 'CAMP',
             'CAMPER VAN' => 'CAMV',
             'CANTER' => 'CANT',
             'CASHVAN' => 'CASH',
             'CLOSED BODY' => 'CLOB',
             'CONTAINER' => 'CNTR',
             'COMPACTOR' => 'COMP',
             'CONCRETE SPAYER' => 'CONS',
             'CONCRETE PUMP' => 'COPM',
             'CORPORATE BUS' => 'CORB',
             'Coupe' => 'COUP',
             'CRANE' => 'CRAN',
             'CRAWLER TRACTOR' => 'CRAT',
             'CRAWLER' => 'CRAW',
             'DELIVERY VAN' => 'DELV',
             'DELIVERY VAN' => 'DEYV',
             'DIESEL BROWSER' => 'DIBW',
             'BORWELL DRILING' => 'DRIL',
             'DRIVING SCH BUS' => 'DRVB',
             'DRIVING SCHOOL BUS' => 'DSCB',
             'DUMPER' => 'DUMP',
             'EARTH MOVING EQUIPMENT' => 'EMEQ',
             'Estate' => 'ESTA',
             'EXCAVATOR' => 'EXCA',
             'EXCAVATOR LOADER' => 'EXCL',
             'EXCAVATOR' => 'EXCV',
             'FIRE ENGINE' => 'FIRE',
             'FORKLIFT' => 'FORK',
             'FORKLIFT' => 'FORL',
             'FORKLIFT TRUCK' => 'FORT',
             'FIRE ENGINE' => 'FR E',
             'TRUCK' => 'FREN',
             'GARBAGE TIPPER' => 'GART',
             'GOODS CARRIER' => 'GDSC',
             'GENERATOR VAN' => 'GENV',
             'GOODS CARRIER' => 'GODC',
             'GOLFCART' => 'GOLF',
             'GRADERS' => 'GRAD',
             'HACKNEY CARRIAGE' => 'HACA',
             'HARVESTER' => 'HARV',
             'Hatchback' => 'HATC',
             'HATCHBACK' => 'HCBK',
             'HY CRAWLER DRIL' => 'HCWD',
             'HEARSE VAN' => 'HEAR',
             'HEARSE VAN' => 'HEVA',
             'HY EXCAVATOR' => 'HEXV',
             'HIGH END' => 'HIGE',
             'HIGHLIFTER' => 'HILI',
             'HY MOBILE CRANE' => 'HMBC',
             'HY TRUCK CRANE' => 'HTRC',
             'HYDRAULIC CRAWLER DRILL' => 'HYCD',
             'HYDRAULIC EXCAVATOR' => 'HYEX',
             'HYDRAULIC MOBILE CRANE' => 'HYMC',
             'HYDRAULIC TRUCK CRANE' => 'HYTC',
             'JCB' => 'JCBB',
             '320 STR1' => 'JCBS',
             'JEEP' => 'JEEP',
             'LIFT' => 'LIFT',
             'LINE PUMP' => 'LINP',
             'LOAD CARRIER' => 'LOAC',
             'LOADER' => 'LOAD',
             'LOAD CARRIER' => 'LODC',
             'LUV' => 'LUVV',
             'MASTIC MIXER' => 'MAMI',
             'MICROVAN' => 'MICR',
             'MINI BUS' => 'MINB',
             'MISCELLANEOUS' => 'MISC',
             'TRANSIT MIXER' => 'MIXE',
             'MOTORCYCLE' => 'MOTC',
             'MOTOR GRADER' => 'MOTG',
             'STREET SWEEPER MACHINE' => 'MPVS',
             'MUV' => 'MUVV',
             'NAVIGATOR' => 'NAVI',
             'NOTCHBACK' => 'NCBK',
             'SCAB-PSAW STR3' => 'OBTS',
             'OFF HIGHWAY TRUCK' => 'OFHT',
             'OIL TANKER' => 'OILT',
             'OPEN BODY' => 'OPNB',
             'PASC' => 'PASC',
             'PASSENGER' => 'PASS',
             'PAVER' => 'PAVE',
             'PAVER FINISHER' => 'PAVF',
             'PICKUP-TRUCK' => 'PCKU',
             'PICK-UP' => 'PICK',
             'PICKUP-TRUCK' => 'PICT',
             'PICK UP' => 'PICU',
             'PICK UP VAN' => 'PICV',
             'POWER TILLER' => 'POWT',
             'PULLER' => 'PULL',
             'REACHSTACKER(CONTAINER LOADER)' => 'REAC',
             'REACH STACKER' => 'REAS',
             'Recovery VAN' => 'RECV',
             'REFRIGERATR VAN' => 'REFV',
             'REACH STACKER' => 'REHS',
             'RICKSHAW' => 'RICK',
             'RICKSHAW Pickup' => 'RICP',
             'ROAD ROLLER' => 'ROAD',
             'ROAD ROLLER' => 'ROAR',
             'ROLLER' => 'ROLL',
             'Saloon' => 'SALO',
             'SCHOOL BUS' => 'SCHB',
             'SCOOTER' => 'SCOO',
             'SCOOTY' => 'SCOT',
             'SCHOOL VAN' => 'SCVN',
             'Sedan' => 'SEDN',
             'SOIL TEST VAN' => 'SLTE',
             'Saloon' => 'SOLO',
             'SOIL TESTING VAN' => 'SOTV',
             'REACH STACKER' => 'STAC',
             'STATION WAGON' => 'STAW',
             'SUV' => 'SUVV',
             'SWEEPER' => 'SWEE',
             'TANDEM COMPACTOR' => 'TANC',
             'TANKER' => 'TANK',
             'TAXI' => 'TAXI',
             'TANDM COMPACTOR' => 'TCMP',
             'All purpose 4-wheel drives.' => 'TERR',
             'THRESHAR' => 'THRE',
             'POWER TILLER' => 'TILL',
             'TIPPER' => 'TIPP',
             'TOWING VAN' => 'TOWV',
             'TRACK TRACTOR' => 'TRAC',
             'TRAILER' => 'TRAI',
             'TRACTOR LOADER' => 'TRAL',
             'TRANSIT MIXER' => 'TRAM',
             'TRACK TRACTOR' => 'TRAT',
             'TROLLY attachment' => 'TROA',
             'TRUCK' => 'TRUC',
             'TWO WHEELER' => 'TWWH',
             'TYRE ROLLER' => 'TYRR',
             'ULTRA END' => 'ULTE',
             '10.90 VANITY VAN' => 'VANI',
             'VAN' => 'VANN',
             'VIB COMPACTOR' => 'VCMP',
             'VIBRATORY COMPACTOR' => 'VIBC',
             'VIBRATORY SOIL COMPACT' => 'VISC',
             'WALKING TRACKTOR' => 'WALT',
             'WHEEL LOADER' => 'WHLL',
             'WHEEL LOADER' => 'WLOA'
        ];
        if(array_key_exists($body_type, $master_body_type))
        {
            return $master_body_type[$body_type];
        }
        else
        {
            return false;
        }
    }
}

if (!function_exists('getPospImdMapping')) {
    function getPospImdMapping($data)
    {
        try {
            if (config('constants.motorConstant.ENABLE_POS_IMD_LOGIC') != 'Y') {
                return [
                    'status' => false
                ];
            }

            $sellerType = 'B2C';
            $sellerUserId = 0;
            if (!empty($data['sellerType'])) {
                $sellerTypeList = [
                    'P' => 'POSP',
                    'MISP' => 'MISP',
                    'B2C' => 'B2C',
                    'E' => 'EMPLOYEE',
                    'PARTNER' => 'PARTNER'
                ];
                $sellerUserId = $data['sellerUserId'];
                $sellerType = $sellerTypeList[strtoupper($data['sellerType'])];
            }

            $utilityId = PospUtility::where([
                'seller_type' => $sellerType,
                'seller_user_id' => $sellerUserId
            ])
                ->pluck('utility_id')
                ->first();

            if (!empty($utilityId)) {
                $imdData = PospUtilityIcParameter::where([
                    'utility_id' => $utilityId,
                    'segment_id' => $data['productSubTypeId'],
                    'ic_integration_type' => $data['icIntegrationType'],
                ])
                    ->pluck('imd_id')
                    ->first();

                if (!empty($imdData)) {
                    $creds = PospUtilityImd::where('imd_id', $imdData)
                        ->pluck('imd_fields_data')
                        ->first();
                    $creds = !is_array($creds) ? json_decode($creds, true) : $creds;

                    if (!empty($creds)) {
                        return [
                            'status' => true,
                            'data' => $creds
                        ];
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error(json_encode($data) . $th);
        }

        return ['status' => false];
    }
}

if (!function_exists('getPremCalFormula')) {
    function getPremCalFormula($productData, &$data, $request)
    {
        try {
            return IcConfigurationController::getFormulas($productData, $data, $request);
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }
}

if (!function_exists('addPolicyGenerationDelay')) {
    function addPolicyGenerationDelay($companyAlias)
    {
        //This function will add delay of configured seconds before calling pdf service
        try {
            $enabled = getCommonConfig('policyWaitingTime.activation', 'N') == 'Y';
            if ($enabled) {
                $delay = getCommonConfig('policyWaitingTime.ic.'.$companyAlias, 0);
                if (!empty($delay) && is_numeric($delay)) {
                    $delay = (float) $delay;
                    sleep($delay);
                }
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }
}

if (!function_exists('getPossiblevehicleNumberSeries')) {
    function getPossiblevehicleNumberSeries($reg)
    {
        if (empty($reg)) {
            return $reg;
        }

        $reg = strtoupper($reg);


        $list = [
            getRegisterNumberWithHyphen($reg),
            getRegisterNumberWithOrWithoutZero($reg, true),
            getRegisterNumberWithOrWithoutZero($reg, false),
            $reg
        ];


        //to convert DL-1-ZB-9833 to DL-1Z-B-9833
        $reg = explode('-', $reg);
        if (!empty($reg[1]) && !empty($reg[2])) {
            if (is_numeric($reg[1]) && $reg[1] < 10 && strlen($reg[2]) > 1) {
                $r = substr($reg[2], 0, 1);
                $reg[1] = $reg[1] . $r;
                $reg[2] = substr($reg[2], 1);
            }
        }
        $reg = implode('-', $reg);

        array_push($list, $reg);

        return array_values(array_unique($list));
    }
}

if (!function_exists('parseVehicleNumber')) {
    function parseVehicleNumber($vehicle_registration_no)
    {
        $vehicle_registration_no = str_replace('--', '-', $vehicle_registration_no);

        $veh_reg_no = explode('-', $vehicle_registration_no);

        $vehicle_register_no = [];
        if (count($veh_reg_no) === 3) {
            // Case like KL-81-5174 → needs to be split into KL-81-51-74
            $vehicle_register_no[0] = $veh_reg_no[0];
            $vehicle_register_no[1] = $veh_reg_no[1];
            $vehicleNumber = $veh_reg_no[2];
            $vehicle_register_no[2] = substr($vehicleNumber, 0, 2);
            $vehicle_register_no[3] = substr($vehicleNumber, 2);
        } else {
            $vehicle_register_no = $veh_reg_no;
        }

        return $vehicle_register_no;
    }
}