<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CommunicationEmail\notificationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunicationEmailController extends Controller
{
    public static function sendCommunicationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $response = [
                'status'  => false,
                'email_status'  => false,
                'email_message'  => '',
                'sms_status'    => false,
                'sms_message'    => '',
                'whatsapp_status'    => false,
                'whatsapp_message'    => '',
            ];

            if (!empty($request->email)) {

                $emailResponse = notificationType::sendEmail($request);
                $emailData     = json_decode($emailResponse->getContent(), true);

                if (!empty($emailData['status'])) {
                    $response['email_status'] = true;
                    $response['email_message'] = 'Email sent successfully';
                }else{
                    $response['email_message'] = 'Email Failed';
                }
            }

            if (!empty($request->sms)) {

                $smsResponse = notificationType::sendSMS($request);
                $smsData     = json_decode($smsResponse->getContent(), true);

                if (!empty($smsData['status'])) {
                    $response['sms_status'] = true;
                    $response['sms_message'] = 'SMS sent successfully';

                }else{
                    $response['sms_message'] = 'SMS Failed';
                }
            }

            if (!empty($request->whatsapp)) {

                $whatsappResponse = notificationType::sendWhatsAPP($request);
                $whatsappData     = json_decode($whatsappResponse->getContent(), true);

                if (!empty($whatsappData['status'])) {
                    $response['whatsapp_status'] = true;
                    $response['whatsapp_message'] = 'WhatsApp sent successfully';
              
                }else{

                    $response['whatsapp_message'] = 'WhatsApp Failed';
                }
            }
            $response['status'] = $response['email_status'] || $response['sms_status'] || $response['whatsapp_status'];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while triggering communication.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
