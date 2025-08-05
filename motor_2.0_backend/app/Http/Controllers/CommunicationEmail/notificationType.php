<?php

namespace App\Http\Controllers\CommunicationEmail;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mail\MailController;
use App\Models\UserProposal;
use App\Mail\PaymentSuccessEmail;
use App\Models\MasterProductSubType;
use Illuminate\Support\Facades\Mail;
use App\Models\SmsLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class notificationType extends Controller
{
    //
    public static function sendEmail($request)
    {
        set_time_limit(60);
        $user_product_journey_id = is_numeric($request['enquiryId']) && strlen($request['enquiryId']) == 16 ? Str::substr($request['enquiryId'], 8): customDecrypt($request['enquiryId']);


        $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();

        if (config('constants.motorConstant.SMS_FOLDER') == 'hero') {

            $logo = asset("images/vehicle/hero_care.png");
            
        } else {

            $logo_path = "images/broker-logos/" . config('constants.motorConstant.SMS_FOLDER') . ".png";
            $logo = File::exists($logo_path) ? asset($logo_path) : asset("images/broker-logos/fyntune.png");
        }

        if (!$policy_details || !$policy_details->policy_details) {

            return response()->json([
                "status" => false,
                "msg" => 'Policy details not found'
            ], 404);

        }

        try {

            $name = $policy_details->first_name . ' ' . $policy_details->last_name;

            $product_id = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first()->product_id ?? null;
            $product_name = MasterProductSubType::where('product_sub_type_id', $product_id)->first()->product_sub_type_name ?? null;

            $product_code = get_parent_code($product_id);
            $cc_email = config('constants.motorConstant.CC_EMAIL');

            $mailData = [
                'title' => "Payment Successful your policy has been issued for {$product_name} - " . config('app.name'),
                'name' => $name,
                'policy_number' => $policy_details->policy_details->policy_number,
                'proposal_number' => $policy_details->proposal_no,
                'link' => $policy_details->policy_details->pdf_url,
                'logo' => $logo,
                'product_code' => $product_code
            ];

            Mail::to($policy_details->email)->bcc($cc_email)->send(new PaymentSuccessEmail($mailData));

            return response()->json([
                "status" => true,
                "msg" => 'Email sent successfully'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                "status" => false,
                "msg" => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }


    public static function sendSMS($request)
    {
        $user_product_journey_id = is_numeric($request['enquiryId']) && strlen($request['enquiryId']) == 16 ? Str::substr($request['enquiryId'], 8): customDecrypt($request['enquiryId']);

        $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();

        if (!$policy_details || !$policy_details->policy_details) {

            return response()->json([
                "status" => false,
                "msg" => 'Policy details not found'
            ], 404);
        }


        $product_id = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first()->product_id ?? null;
        $product_name = MasterProductSubType::where('product_sub_type_id', $product_id)->first()->product_sub_type_name ?? null;

        $name = $policy_details->first_name . ' ' . $policy_details->last_name;

        $request['productName'] = $product_name;
        $request['policyNumber'] = $policy_details->policy_details->policy_number;
        $request['mobileNo'] = $policy_details->mobile_number;

        $send_sms = sendSMS((object)$request->all(), $name, 'policyGeneratedSms');

        SmsLog::create([
            "user_product_journey_id" => $user_product_journey_id,
            "mobile_no" => $request->mobileNo,
            "request" => $send_sms['request'] ?? [],
            "response" => $send_sms['response'] ?? [],
        ]);

        return response()->json([
            "status" => true,
            "msg" => 'SMS send successfully'
        ], 200);
    }

    public static function sendWhatsAPP($request)
    {
        $user_product_journey_id = is_numeric($request['enquiryId']) && strlen($request['enquiryId']) == 16 ? Str::substr($request['enquiryId'], 8): customDecrypt($request['enquiryId']);

        $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();

        if (!$policy_details || !$policy_details->policy_details) {

            return response()->json([
                "status" => false,
                "msg" => 'Policy details not found'
            ], 404);
        }

        $request['to'] = $policy_details->mobile_number;
        $request['type'] = 'paymentSuccess';
        $request['notificationType'] = 'whatsapp';
        $request['link'] = $policy_details->policy_details->pdf_url;
        $request['mobileNo'] = $policy_details->mobile_number;
        $request['firstName'] = $policy_details->first_name;
        $request['lastName'] = $policy_details->last_name;


        $whatAppFunction = new MailController();

        $response =  $whatAppFunction->whatsappNotification($request);
        $response     = json_decode($response->getContent(), true);

        if ($response['status']) {

            return response()->json([
                "status" => true,
            ], 200);

        } else {

            return response()->json([
                "status" => false,
            ]);
        }
    }
}