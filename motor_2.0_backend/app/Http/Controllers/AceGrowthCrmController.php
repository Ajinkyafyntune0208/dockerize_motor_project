<?php

namespace App\Http\Controllers;

use App\Models\UserProductJourney;
use Illuminate\Http\Request;

class AceGrowthCrmController extends Controller
{
  function aceGrowthApi($enquiryId, $mailData, $email, $cc_email, $channel_type, $event_name = "" ,$pdfData = "")
  {
    if (!empty($enquiryId)) {
      $userProductJourney = UserProductJourney::select('lead_id')->where('user_product_journey_id', $enquiryId)->first();
      if (!empty($userProductJourney) && !empty($userProductJourney["lead_id"])) {
        $alertable_id = $userProductJourney["lead_id"];
        if ($channel_type == "sms") {
          $channel_id = 2;
          $mobileNo = (strlen($email) === 12 && str_starts_with($email, '91')) ? substr($email, 2) : ((strlen($email) === 10) ? $email : "");
        } elseif ($channel_type == "whatsapp") {
          $channel_id = 3;
          $mobileNo = (strlen($email) === 12 && str_starts_with($email, '91')) ? substr($email, 2) : ((strlen($email) === 10) ? $email : "");
        } elseif ($channel_type == "email") {
          $channel_id = 1;
        }
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $email = $email;
        } else {
          $email = $email[0];
        }

        switch ($channel_type) {
          case 'email':
            $body = [
              "alertable_type" => "lead",
              "alertable_id" => $alertable_id,
              "channel_id" => $channel_id,  //1 - email, 2 - sms, 3 - whatsapp
              "source" => "external",
              "subject" => $mailData['title'] ?? '',
              "content" => $mailData['crm_content'] ?? '',
              "recipients" => [
                [
                  "email" => $email ?? '',
                  "recipient_type_id" => 1 //1 - to, 2 - cc, 3 - bcc
                ],

                // [
                //   "email" => $cc_email,
                //   "recipient_type_id" => 2
                // ]
              ]
            ];
            break;
          case 'whatsapp':
            $body = [
              "alertable_type" => "lead",
              "alertable_id" => $alertable_id,
              "channel_id" => $channel_id,  //1 - email, 2 - sms, 3 - whatsapp
              "source" => "external",
              "subject" => !empty($event_name) ? $event_name : "In whatsapp case subject is not applicable",
              "content" => $mailData,
              "recipients" => [
                [
                  "mobile" => $mobileNo
                ]
              ]
            ];
            break;
          case 'sms':
            $body = [
              "alertable_type" => "lead",
              "alertable_id" => $alertable_id,
              "channel_id" => $channel_id,  //1 - email, 2 - sms, 3 - whatsapp
              "source" => "external",
              "subject" => $event_name ?? "In Sms case subject is not applicable",
              "content" => $mailData['msg'],
              "recipients" => [
                [
                  "mobile" => $mobileNo
                ]
              ]
            ];
            break;
          default:
            break;
        }
        if (!empty($pdfData)) {
          $body[] =
            [
              "attachments" => [
                "type" => "pdf",
                "content" => $pdfData
              ]
            ];
        }

        $tokenResponse = httpRequest('ace-growth-crm-token');
        $token = $tokenResponse['response']['token'] ?? null;

        if (empty($token)) {
          return [
            "status" => false,
            "msg" => "Token not found for Ace Growth Crm"
          ];
        }

        $aceGrowthCrmResponse = httpRequest('ace-growth-crm', $body, headers:[
          'Authorization' => 'Bearer ' . $token,
        ]);
        if (!empty($aceGrowthCrmResponse) && $aceGrowthCrmResponse['status'] == 201) {
          $response = [
            "status" => true,
            "msg" => "Data stored in CrmGrowth successfully"
          ];
        } else {
          $response = [
            "status" => false,
            "msg" => $aceGrowthCrmResponse["response"] ?? "getting issue in a  CrmGrowth API"
          ];
        }
      } else {
        $response = [
          "status" => true,
          "msg" => "Ace Growth Crm Not Applicable"
        ];
      }
    } else {
      $response = [
        "status" => false,
        "msg" => "enquiry id not found"
      ];
    }

    return $response;
  }
}
