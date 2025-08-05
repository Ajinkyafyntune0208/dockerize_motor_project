<?php

namespace App\Http\Controllers\WhatsApp;

use Nette\Utils\Strings;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\CvJourneyStages;
use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;

class WhatsAppController extends Controller
{

    public static function isWhatsAppDisabled(): bool
    {
        return config('constants.motorConstant.SMS_ENABLED') === "N";
    }

    public static function proposalData($enquiry_id)
    {
        return UserProposal::where('user_product_journey_id', customDecrypt($enquiry_id))->first();
    }

    public static function stageData($enquiry_id)
    {
        return CvJourneyStages::where('user_product_journey_id', customDecrypt($enquiry_id))->first();
    }

    public static function policyData($enquiry_id){
        return UserProposal::with('policy_details')->where('user_product_journey_id', customDecrypt($enquiry_id))->first();
    }

    public static function getProductCode($enquiry_id): string
    {
        $enquiry_id = (strlen($enquiry_id) < 16 ) ? customEncrypt($enquiry_id) : customDecrypt($enquiry_id);
        return ucfirst(strtolower(get_parent_code(CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
            ->first()->product_id)));
    }

    public static function getpolicyPdfUrl($enquiry_id): string
    {
        return UserProposal::with('policy_details')
        ->where('user_product_journey_id', $enquiry_id)
        ->first()->policy_details->pdf_url;
    }

    public static function smsgupshupOptin($number)
    {
        httpRequest('whatsapp', [
            'method' => 'OPT_IN',
            'channel' => 'WHATSAPP',
            'phone_number' => $number
        ]);
    }

    public static function renewal($request)
    {
        $isWhatsAppDisabled = self::isWhatsAppDisabled();
        if ($isWhatsAppDisabled) return false;

        if (config('constants.motorConstant.SMS_FOLDER') === 'ace') {
            self::aceRenewal($request);
        }

        if (config('constants.motorConstant.SMS_FOLDER') === 'abibl') {
            self::abiblRenewal($request);
        }
    }


    /* ACE BROKER */

    private static function aceRenewal($request): void
    {
        $proposal = self::proposalData($request->enquiry_id);
        $stage = self::stageData($request->enquiry_id);
        $product_code = self::getProductCode($request->enquiry_id);

        $name = trim(($proposal->first_name ?? "") . " " . ($proposal->last_name ?? ""));
        $link = $stage->proposal_url ?? $stage->quote_url ?? "";
        $url = shortUrl($link)['response']['short_url'];
        $expiryTime = today()->endOfDay()->format('d/m/Y H:i');

        httpRequest('whatsapp', [
            'method' => 'SendMessage',
            'msg_type' => 'HSM',
            'isHSM' => 'true',
            'isTemplate' => 'false',
            "linktracking" => 'true',
            'send_to' => $proposal->mobile_number,
            'msg' => "Dear {$name}, Please click {$url} to pay the premium for your {$product_code} Vehicle policy, Proposal No.{$proposal->proposal_no}. Your Total Payable Amount is INR {$proposal->final_payable_amount}. Important: This link will expire at {$expiryTime}.\nACE Insurance Brokers Private Limited, Registered Office- B-17 Ashadeep Building, 9 Hailey Road, New Delhi 110001, IRDAI License No. 246, Period 19.02.22 to 18.02.25, Category: Composite"
        ]);
    }

    /* ABIBL BROKER */

    private static function abiblRenewal($request): void
    {
        $proposal = self::proposalData($request->enquiry_id);
        $stage = self::stageData($request->enquiry_id);
        $product_code = self::getProductCode($request->enquiry_id);

        $link = $stage->proposal_url ?? $stage->quote_url ?? "";
        $url = shortUrl($link)['response']['short_url'];

        $data = [
            "send_to" => $proposal->mobile_number,
            "msg_type" => "HSM",
            "method" => "SENDMESSAGE",
            "format" => "json"
        ];

        $response = [];

        switch ($request->type) {
            case "t90":
                $response = self::abiblRenewalT90($data);
                break;
            case "t60":
                $response = self::abiblRenewalT60($data);
                break;
            case "t45":
                $response = self::abiblRenewalT45($data);
                break;
            case "t30":
                $response = self::abiblRenewalT30($data);
                break;
            case "t15":
                $response = self::abiblRenewalT15($data);
                break;
            case "t10":
                $response = self::abiblRenewalT10($data);
                break;
            case 't5':
                $response = self::abiblRenewalT5($data);
                break;
            case 't0':
                $response = self::abiblRenewalT0($data);
                break;
            case 'dropOff':
                $response = self::abiblRenewalDropOff($data);
                break;
            case 'policyIssued':
                $response = self::abiblRenewalPolicyIssued($data);
                break;
        }

        if (empty($response)) return;
        httpRequest('whats_app_two_way', $response);
    }

    private static function abiblRenewalT90(array $data): array
    {
        $data['msg'] = "Financial Risks may be closer than they appear.Insure Your Drives with Motor Insurance #ChanceNahiInsuranceLo Click here to secure your car instantly.";
        return $data;
    }

    private static function abiblRenewalT60(array $data): array
    {
        $data['msg'] = "Buying Car Insurance through ABIBL is easy, convenient & secure. We have all the ingredients from quick policy issuance to seamless claim settlement process. We're here to help you every step of the way. Don't wait, renew now!";
        return $data;
    }

    private static function abiblRenewalT45(array $data): array
    {
        $data['msg'] =  "Don't wait for the traffic cops to remind you that... MOTOR INSURANCE IS IMPERATIVE #ChanceNahiInsuranceLo Click here to secure your car instantly.";
        return $data;
    }

    private static function abiblRenewalT30(array $data): array
    {
        $data['msg'] =  "Our customers love to Renew their vehicle insurance on time to avoid unnecessary hassles. Here's nudging you to renew your policy without hitting the snooze button! Click here for instant policy issuance.";
        return $data;
    }

    private static function abiblRenewalT15(array $data): array
    {
        $data['msg'] =  "Motor insurance for your car will expire on {Var} #ChanceNahiInsuranceLo Don't wait, renew your policy with ABIBL as your trusted broker. Click here to secure your car instantly";
        return $data;
    }

    private static function abiblRenewalT10(array $data): array
    {
        $data['msg'] =  "The ABIBL motor service and claims team is passionate about supporting customers, don't miss this opportunity to protect your vehicle and experience a sense of security. #ChanceNahiInsuranceLo Click here to secure your car instantly";
        return $data;
    }

    private static function abiblRenewalT5(array $data): array
    {
        $data['msg'] = "We've you covered. Motor insurance for your vehicle will expire on {var}\nRenew your insurance policy to avoid unnecessary hassles and stay secured because #ChanceNahiInsuranceLo";
        return $data;
    }

    private static function abiblRenewalT0(array $data): array
    {
        $data['msg'] = "You love your Vehicle and we love to insure it. While your insurance policy is up for renewal today, we want to continue to take the first step to protect your vehicle. Click here to secure your car instantly.";
        return $data;
    }

    private static function abiblRenewalDropOff(array $data): array
    {
        $data['msg'] = "We noticed you took longer to renew, don't miss this opportunity to protect your vehicle and experience a sense of security. #ChanceNahiInsuranceLo Click here to reach our executives";
        return $data;
    }

    private static function abiblRenewalPolicyIssued(array $data): array
    {
        $data['msg'] = "We are pleased to inform you that your motor insurance policy has been issued and is now active. Your policy documents can be accessed from the link provided below. We are committed to providing you with the best possible service. Thank you for choosing us as your insurance partner.";
        return $data;
    }

    public static function notification($request)
    {
        switch (config('constants.motorConstant.SMS_FOLDER')) {
            case 'policy-era':
                $response = self::policyera_whatsapp($request);
                break;
            default:
                return;
        }

        return $response;
    }

    private static function policyera_whatsapp($request)
    {
        
        $name = $request->firstName  ??  $request->name;
        $link = shortUrl($request->link)['response']['short_url'];

        $data = [
            'type' => 'template',
            'to' => $request->to,
            'content' => [
                'language' => 'en',
                'ttl' => 'P1D'
            ]
        ];

        if($request->type === 'shareProposal'){
            $vehicleType = self::getProductCode($request->enquiryId);
            $data['content']['template_name'] = 'proposal_share';
            $data['content']['params'] = [$name, $vehicleType, $link];
        }

        if($request->type === 'comparepdf'){
            $data['content']['template_name'] = 'quote_compare';
            $data['content']['params'] = [$name, $link];
        }

        if($request->type === 'shareQuotes'){
            $data['content']['template_name'] = 'quote_share';
            $data['content']['params'] = [$name, $link];
        }

        if($request->type === 'paymentSuccess'){
            $policy_details = self::policyData($request->enquiryId);
            $data['content']['template_name'] = 'payment_success';
            $data['content']['params'] = [$name, $policy_details->final_payable_amount, $policy_details->policy_details->policy_number, $policy_details->policy_details->pdf_url];
        }

        $response = httpRequest('whatsapp', $data);

        if ($response) {
            return response()->json([
                'status' => true,
                'msg' => 'WhatsApp message send Successfully...!'
            ]);
        }

        return response()->json([
            'status' => true,
            'msg' => 'Something Went Wrong'
        ]);
    }
}
