<?php

namespace App\Http\Controllers;


use App\Models\CleverTapPushLog;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class cleverTapEvent extends Controller
{
     public static function pushData($policyUrl = null, $user_product_journey_id, $mobileNumber , $segment)
    {
        $client = new Client();
        $region    = 'in1';
        $mobileNumber = trim('+91' . $mobileNumber);
        $encodedMobile = urlencode($mobileNumber);

        $eventName = 'Hi_Portal_4W_Policy_Created';

        if ($segment == 'BIKE') {
            $eventName = 'Hi_Portal_2W_Policy_Created';
        } else if ($segment == 'GCV') {
            $eventName = 'Hi_Portal_GCV_Policy_Created';
        } else if ($segment == 'PCV') {
            $eventName = 'Hi_Portal_PCV_Policy_Created';
        }

        try {
            // $response = $client->request('GET', "https://{$region}.api.clevertap.com/1/profile.json?identity={$encodedMobile}", [
            //     'headers' => [
            //         'X-CleverTap-Account-Id' => config('clevertap.account_id'),
            //         'X-CleverTap-Passcode' => config('clevertap.passcode'),
            //         'Content-Type' => 'application/json',
            //     ]
            // ]);

            // $body = $response->getBody();
            // $data = json_decode($body, true);

            // if (isset($data['profile']['objectId'])) {
            //     $objectId = $data['profile']['objectId'];

            $url = "https://{$region}.api.clevertap.com/1/upload";

            $headers = [
                'Content-Type'             => 'application/json',
                'X-CleverTap-Account-Id'   => config('clevertap.account_id'),
                'X-CleverTap-Passcode'     => config('clevertap.passcode'),
            ];

            $body = [
                'd' => [
                    [
                        'identity'  => $mobileNumber, //$objectId,
                        'type'      => 'event',
                        'evtName'   => $eventName,
                        'evtData'   => [
                            'policy_url' => $policyUrl,
                            'generated_at' => now()->toDateTimeString(),
                        ],
                    ]
                ]
            ];

            $log = new CleverTapPushLog();
            $log->trace_id = $user_product_journey_id;
            $log->event_name = 'Policy PDF Generated';
            $log->payload = $body;
            $log->save();

            $response = $client->post($url, [
                'headers' => $headers,
                'json'    => $body,
            ]);

            $log->response = $response->getBody();
            $log->success = $response->getStatusCode() === 200;
            $log->save();
            // }
        } catch (\Exception $e) {

            Log::error('CleverTap Push Failed: ' . $e->getMessage());
        }
    }
}
