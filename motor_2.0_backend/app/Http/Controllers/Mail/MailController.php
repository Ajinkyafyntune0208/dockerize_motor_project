<?php

namespace App\Http\Controllers\Mail;

use App\Models\MailLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\WhatsApp\WhatsAppController;
use App\Mail\CallUs;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\PaymentSuccessEmail;
use App\Mail\ProposalShareEmail;
use App\Mail\FyntuneGarageMail;
use App\Mail\QuotesShareEmail;
use App\Models\PolicySmsOtp;
use App\Models\{CvBreakinStatus, UserProposal,
                PolicyDetails };
use Exception;
use App\Mail\PremiumBreakupMail;
use App\Mail\ProposalCreatedEmail;
use App\Mail\CompareMail;
use App\Mail\PaymentShareEmail;
use App\Mail\PolicyShareDashboard;
use App\Mail\SendOtpEmail;
use App\Models\CvAgentMapping;
use App\Models\QuoteLog;
use App\Models\SmsLog;
use App\Models\UserProductJourney;
use App\Models\MasterCompany;
use App\Models\WhatsappRequestResponse;
use Carbon\Carbon;
use CorporateVehiclesQuotesRequest;
use Facade\FlareClient\Api;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Mail\PaymentFailureEmail;
use App\Mail\NsdlAccountCreation;
use App\Models\CvJourneyStages;
use App\Models\LeadPageOtp;
use Doctrine\DBAL\Schema\View;
use App\Http\Controllers\AceGrowthCrmController;

class MailController extends Controller
{
    public function sendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "notificationType" => ['required', Rule::in('email', 'sms', 'all')],
            'enquiryId' => 'required',
            // 'emailId' => ['required_if:notificationType,email', 'nullable', 'email:rfc,dns'],
            'mobileNo' => ['required_if:notificationType,sms', 'nullable', 'numeric'],
            'type' => 'required|in:shareQuotes,shareProposal,sharePayment,paymentSuccess,proposalCreated,policyGeneratedSms,otpSms,Aceleadsms,pinsms,comparepdf,premiumBreakuppdf,inspectionIntimation,inspectionApproval,paymentFailure,renewalSms,cashlessGarage',
            'domain' => 'required',
            'logo' => 'nullable',
            'ic_logo'=>'nullable',
            'ic_name'=>'nullable',
            'quotes' => ['required_if:type,shareQuotes', 'array'],
            'link' => [Rule::requiredIf(function () use ($request) {
                return in_array($request->type, ['shareProposal', 'sharePayment']);
            })],
            'quotes.*.name' => ['required_if:type,shareQuotes'],
            'quotes.*.idv' => ['required_if:type,shareQuotes'],
            'quotes.*.logo' => ['required_if:type,shareQuotes'],
            'quotes.*.premium' => ['required_if:type,shareQuotes'],
            'quotes.*.action' => ['required_if:type,shareQuotes'],
            'policyNumber' => [Rule::requiredIf(function () use ($request) {
                return (($request->notificationType == "sms" && $request->type == "policyGeneratedSms"));
            })],
            'policyStartDate' => [Rule::requiredIf(function () use ($request) {
                return ($request->notificationType == "sms" && $request->type == "otpSms");
            })],
            'policyEndDate' => [Rule::requiredIf(function () use ($request) {
                return ($request->notificationType == "sms" && $request->type == "otpSms");
            })],
            'section' => ['nullable', 'string'],
            'registrationNumber' => [Rule::requiredIf(function () use ($request) {
                return ($request->notificationType == "sms" && $request->type == "otpSms");
            })],
            'applicableNcb' => [Rule::requiredIf(function () use ($request) {
                return ($request->notificationType == "sms" && $request->type == "otpSms");
            })],
            'premiumAmount' => [Rule::requiredIf(function () use ($request) {
                return ($request->notificationType == "sms" && $request->type == "otpSms");
            })],
            'productName' => [Rule::requiredIf(function () use ($request) {
                return (($request->notificationType == "email" && $request->type !== "shareQuotes")
                    || ($request->notificationType == "sms" && $request->type !== "shareQuotes"));
            })],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        try{
        if(config('constants.motorConstant.SMS_FOLDER') == 'hero' && $request->type!='otpSms' && $request->notificationType!="email" && $request->notificationType!="sms") {
            $this->whatsappNotification($request);
        } 
        elseif($request->notificationType == "all" && config('constants.motorConstant.SMS_FOLDER') != 'hero') {
            $this->whatsappNotification($request);
        }
        }
    catch(\Exception $e){
        Log::error($e->getMessage());
    }
        $email = $request->emailId;
        $enquiryId = $request->enquiryId;
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $name = $request->firstName . ' ' . $request->lastName;  
        if ($request->type == "cashlessGarage") {
            return self::sendCashlessGarageEmailAndSms($request);
        }
        if ($request->notificationType == "email" || $request->notificationType == "all")
         {
            try{
            if (config('constants.motorConstant.EMAIL_ENABLED') == "Y") {
                if (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                    self::renewbuyEmail($request);
                } else if (config('constants.motorConstant.SMS_FOLDER') == 'abibl') {
                    self::abiblEmail($request);
                } else if (config('constants.motorConstant.SMS_FOLDER') == 'spa') {
                    self::spaEmail($request);
                } else if (config('constants.motorConstant.SMS_FOLDER') == 'ss') {
                    self::ssEmail($request);
                } else if (config('constants.motorConstant.SMS_FOLDER') == 'sib') {
                    self::sibEmail($request);
                } else if (config('constants.motorConstant.SMS_FOLDER') == 'tmibasl') {
                    self::tmibaslEmail($request);
                } else {
                    $quote_data = DB::table('quote_log as ql')
                        ->where('ql.user_product_journey_id', $user_product_journey_id)
                        ->select('ql.quote_data')
                        ->first();
                    $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();

                    $cc_email = config('constants.motorConstant.CC_EMAIL');



                    switch ($request->type) {
                        case 'shareQuotes':
                            $mailData = [
                                'title' => "Best plans of {$request->section} insurance recommended - " . config('app.name'),
                                'url' => $request->domain . 'cv/quotes?enquiry_id=' . $enquiryId,
                                'name' => $name,
                                'quotes' => $request->quotes,
                                'section' => $request->section,
                                'logo' => $request->logo,
                                'quote_data' => json_decode(json_decode($quote_data->quote_data)),
                                'corporate_data' => $corporate_data,
                                'gstSelected' => $request->gstSelected
                            ];
                            
                            if(is_array($mailData['quotes']))
                            {
                                array_walk($mailData['quotes'], function (&$val, $key){
                                    $val['applicableAddonsList'] = $val['applicableAddons'] ?? [];
                                    $val['applicableAddonsList'] = implode(', ', array_column($val['applicableAddonsList'], 'name'));
                                });
                            }
                            
                            if (in_array(config('constants.motorConstant.SMS_FOLDER'), ['bajaj', 'hero'])) {
                                $mailData['title'] = "Best Insurance Plans for your Vehicle only on " . config('BROKER_WEBSITE');
                            } elseif (config('constants.motorConstant.SMS_FOLDER') == 'pinc'){
                                $mailData['title'] = 'Recommendation of insurance plans - PINC Tree';
                            }

                            $html_body = (new QuotesShareEmail($mailData))->render();
                            $mailData['crm_content'] = $html_body;
                            $this->ace_crm_api($user_product_journey_id, $mailData, $email, $cc_email, "email");
                            Mail::to($email)->bcc($cc_email)->send(new QuotesShareEmail($mailData));
                            break;
                        case 'shareProposal':
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                            $mailData = [
                                'title' => "Continue your proposal journey for {$request->productName} - " . config('app.name'),
                                'url' => $request->link,
                                'name' => $name,
                                'product_name' => $request->productName,
                                'link' => $request->link,
                                'logo' => $request->logo,
                                'ic_logo' => $request->ic_logo,
                                'product_code' => ucfirst(strtolower($product_code)) ?? ''
                            ];
                            if(config('constants.motorConstant.SMS_FOLDER') == 'bajaj'){
                                $mailData['title'] = "Continue your Proposal Journey for {$mailData['product_code']} Insurance";
                            }
                            if(config('constants.motorConstant.SMS_FOLDER') == 'pinc'){
                                $mailData['title'] = 'Continue your proposal journey';
                            }

                            $html_body = (new ProposalShareEmail($mailData))->render();
                            $mailData['crm_content'] = $html_body;
                            $this->ace_crm_api($user_product_journey_id, $mailData, $email, $cc_email, "email");
                            Mail::to($email)->bcc($cc_email)->send(new ProposalShareEmail($mailData));
                            break;
                        case 'sharePayment':
                        case 'proposalCreated':
                            $mailData = [
                                'name' => $name,
                                'product_name' => $request->productName,
                                'link' => $request->link,
                                'logo' => $request->logo,
                                'ic_logo' => $request->ic_logo,
                            ];
                            /*
                            if ($request->type == "sharePayment") {
                                $mailData['title'] = "Continue your payment for {$request->productName} - " . config('app.name');
                            } elseif ($request->type == "proposalCreated") {
                                $mailData['title'] = "{$request->productName} Proposal Created Successfully - " . config('app.name');
                            }
                            */

                            /* Changed Proposal Created Email Template to Share Payment Email */
                            $user_propopsal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                            $mailData['title'] = "Proposal Payment " . $user_propopsal->vehicale_registration_number;
                            $mailData['final_amount'] = $user_propopsal->final_payable_amount;
                            $mailData['proposal_no'] = $user_propopsal->proposal_no;
                            $mailData['policy_end_date'] = Carbon::parse($user_propopsal->policy_end_date)->format('d F Y');
                            $mailData['vehicale_registration_number'] = $user_propopsal->vehicale_registration_number;
                            $mailData['time_stamp'] = Carbon::now()->format('d/m/Y');
                                /* If CKYC Failed and no proposal data saved in db empty details going so handle with this condition*/
                            $html_body = (new PaymentShareEmail($mailData))->render();
                            $mailData['crm_content'] = $html_body;
                            $this->ace_crm_api($user_product_journey_id, $mailData, $email, $cc_email, "email");
                            if(!empty($mailData['final_amount']) && !empty($mailData['proposal_no'])){
                                Mail::to($email)->bcc($cc_email)->send(new PaymentShareEmail($mailData));
                                $html_body = (new PaymentShareEmail($mailData))->render();
                            }
                            break;
                        case 'paymentSuccess':
                            $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first()->product_id);
                            $mailData = [
                                'title' => "Payment Successful your policy has been issued for {$request->productName} - " . config('app.name'),
                                'name' => $name,
                                'policy_number' => $policy_details->policy_details->policy_number,
                                'proposal_number' => $policy_details->proposal_no,
                                'link' => $policy_details->policy_details->pdf_url,
                                'logo' => $request->logo,
                                'product_code' => $product_code
                            ];
                            if(config('constants.motorConstant.SMS_FOLDER') == 'bajaj'){
                                $mailData['title'] = "Congratulations! Your policy has been successfully booked!";
                            }
                            $html_body = (new PaymentSuccessEmail($mailData))->render();
                            $mailData['crm_content'] = $html_body;
                            $this->ace_crm_api($user_product_journey_id, $mailData, $email, $cc_email, "email");
                            Mail::to($email)->bcc($cc_email)->send(new PaymentSuccessEmail($mailData));
                            break;
                        case 'paymentFailure':
                            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first()->product_id);
                            $mailData = [
                                'title' => "Sorry! Your policy booking was unsuccessful!",
                                'name' => $name,
                                'logo' => $request->logo,
                                'link' => $request->reInitiate ?? '',
                                'product_code' => $product_code
                            ];
                            $html_body = (new PaymentFailureEmail($mailData))->render();
                            $mailData['crm_content'] = $html_body;
                            $this->ace_crm_api($user_product_journey_id, $mailData, $email, $cc_email, "email");

                            if(config('constants.motorConstant.SMS_FOLDER') == 'bajaj'){
                                Mail::to($email)->bcc($cc_email)->send(new PaymentFailureEmail($mailData));
                                $html_body = (new PaymentFailureEmail($mailData))->render();
                            }
                            break;
                        case 'inspectionIntimation':
                            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                            $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                            $mailData = [
                                'title' => "Inspection Request - " . config('app.name'),
                                'name' => $name,
                                "insurer" => $user_proposal->ic_name,
                                "reference_id" => $inspectionData->breakin_number,
                                'time_stamp' => !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
                                'registration_number' => $user_proposal->vehicale_registration_number,
                                'logo' => $request->logo
                            ];
                            $html_body = (new \App\Mail\InspectionIntimationEmail($mailData))->render();
                            $mailData['crm_content'] = $html_body;
                            $this->ace_crm_api($user_product_journey_id, $mailData, $email, $cc_email, "email");
                            Mail::to($email)->bcc($cc_email)->send(new \App\Mail\InspectionIntimationEmail($mailData));
                            $html_body = (new \App\Mail\InspectionIntimationEmail($mailData))->render();
                            break;
                    }
                }
            }
        }catch(\Exception $e){
            Log::error($e);
        }        
        }
        try{
        if ($request->notificationType == "sms" || $request->notificationType == "all" &&  config('constants.motorConstant.SMS_FOLDER')!= 'hero') {
            if (config('constants.motorConstant.SMS_ENABLED') == "Y") {
                $send_sms = sendSMS($request, $name, $request->type);
                $this->ace_crm_api($user_product_journey_id, $send_sms['request'] ?? [], $request->mobileNo, null, "sms", $request->type);
                SmsLog::create([
                    "user_product_journey_id" => $user_product_journey_id,
                    "mobile_no" => $request->mobileNo,
                    "request" => $send_sms['request'] ?? [],
                    "response" => $send_sms['response'] ?? [],
                ]);
                if($request->type === "otpSms"){
                    return response()->json($send_sms);
                }
                if (!$send_sms) {
                    return response()->json([
                        "status" => false,
                        "msg" => 'Something went wrong or OTP is incorrect'
                    ]);
                } else {
                    return response()->json([
                        "status" => true,
                        "msg" => 'SMS send successfully'
                    ]);
                }
            }
        }
        elseif ($request->notificationType == "sms" || $request->notificationType == "all" &&  config('constants.motorConstant.SMS_FOLDER')== 'hero') {
            if (config('constants.motorConstant.SMS_ENABLED') == "Y") {
                $send_sms = sendSMS($request, $name, $request->type);
                SmsLog::create([
                    "user_product_journey_id" => $user_product_journey_id,
                    "mobile_no" => $request->mobileNo,
                    "request" => $send_sms['request'] ?? [],
                    "response" => $send_sms['response'] ?? [],
                ]);
                if($request->type === "otpSms"){
                    $this->whatsappNotification($request);
                    return response()->json($send_sms);
                }
                if (!$send_sms) {
                    return response()->json([
                        "status" => false,
                        "msg" => 'Something went wrong or OTP is incorrect'
                    ]);
                } else {
                    return response()->json([
                        "status" => true,
                        "msg" => 'SMS send successfully'
                    ]);
                }
            }
        }
        
    } catch(\Exception $e){
        Log::error($e);
    }  
        if ($html_body ?? null) {
            MailLog::create([
                "email_id" => (is_array($request->emailId) ? json_encode($request->emailId) : $request->emailId),
                "mobile_no" => $request->mobileNo,
                "first_name" => $request->firstName,
                "last_name" => $request->lastName,
                "subject" => $mailData['title'] ?? "",
                "mail_body" => $html_body ?? "",
                "enquiryId" => $user_product_journey_id,
            ]);
        }

        return response()->json([
            "status" => true,
            "msg" => 'Email has been sent successfully!.'
        ]);
    }

    public static function renewbuyEmail($request)
    {
        $messageData = [
            "direct_to" => $request->emailId,
        ];
        switch ($request->type) {
            case 'shareQuotes':
                $user_product_journey_id = customDecrypt($request->enquiryId);
                $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();
                $messageData['event'] = 'EV_motor_share_quotes_complete_page_new';
                $messageData['context'] = [
                    "posp_name" => $request->firstName,
                    "quotes_link" => $request->link,
                    "customer_name" => $request->firstName . ' ' . $request->lastName,
                    // 'plans_image' => 'https://images-na.ssl-images-amazon.com/images/I/71DpSsbEtRL._AC_SX679_.jpg',
                    'html_table' => view('Email.' . config('constants.motorConstant.SMS_FOLDER') . '.ShareQuotesEmail', ['mailData' => ['quotes' => $request->quotes, "gstSelected" => $request->gstSelected, "corporate_data" => $corporate_data]])->render()
                ];
                break;
            case 'shareProposal':
            case 'sharePayment':
            case 'proposalCreated':
                $messageData['event'] = 'EV_motor_proposal_review_with_payment_link_new';
                $messageData['context'] = [
                    "proposal_link" => $request->link,
                    "customer_name" => $request->firstName . ' ' . $request->lastName,
                ];
                break;
            case 'paymentSuccess':
                $user_product_journey_id = customDecrypt($request->enquiryId);
                $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();
                $messageData['event'] = 'EV_policy_payment_success_and_policy_issued';

                $agent_details = CvAgentMapping::where('user_product_journey_id', $user_product_journey_id)->first();

                if ($agent_details) {
                    $cover_data = [
                        'name' => $policy_details->first_name,
                        'posp_name' => $agent_details->agent_name ?? null,
                        'posp_pan' => $agent_details->pan_no ?? null,
                        'posp_mobile' => $agent_details->agent_mobile ?? null,
                    ];
                    $view_name = 'renew_buy_pos_cover_letter';
                } else {
                    $cover_data = [
                        'name' => $policy_details->first_name,
                    ];
                    $view_name = 'renew_buy_non_pos_cover_letter';
                }
                /* Cove Letter Generate */
                $pdf = \PDF::loadView($view_name, compact('cover_data'))->output();
                $file_name = "Cover-Letters/{$request->enquiryId}/Cover-Letter.pdf";
                Storage::delete($file_name);
                Storage::put($file_name, $pdf);
                /* Cove Letter Generate */

                $messageData['context'] = [
                    "payment_amount" => $policy_details->final_payable_amount,
                    "customer_name" => $request->firstName . ' ' . $request->lastName,
                    "vehicle_name" => $request->productName,
                    "has_attachment" => $policy_details->policy_details->pdf_url,
                    "insurer" => $policy_details->ic_name,
                    "policy_number" => $policy_details->policy_details->policy_number,
                    "cover_letter" => Storage::url($file_name),
                ];
                $messageData['attachments'] = [
                    "policy_document" => $policy_details->policy_details->pdf_url,
                    "cover_letter" => Storage::url($file_name)
                ];
                break;
            case 'inspectionIntimation':
                $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                $messageData['event'] = 'EV_inspection_req_raised-_motor_insurance';
                $messageData['context'] = [
                    "customer_first_name" => $request->firstName,
                    "insurer" => substr($user_proposal->ic_name, 0, 25),
                    "reference_id" => $inspectionData->breakin_number,
                    'time_stamp' => !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
                    'registration_number' => $user_proposal->vehicale_registration_number
                ];
                break;
            case 'inspectionApproval':
                $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                $messageData['event'] = 'EV_inspection_req_accepted-_motor_insurance';
                $messageData['context'] = [
                    "customer_first_name" => $request->firstName,
                    "insurer" => substr($user_proposal->ic_name, 0, 25),
                    "payment_amount" => $user_proposal->final_payable_amount,
                    'link' => $inspectionData->breakin_check_url,
                    'registration_number' => $user_proposal->vehicale_registration_number
                ];
                break;
            default:
                return;
        }
        return httpRequest('sms', $messageData);
    }


    public static function sendCashlessGarageEmailAndSms ($request) {
        if (config('constants.motorConstant.EMAIL_ENABLED') == "Y") {
            if (config('constants.motorConstant.SMS_FOLDER') == 'fyntune') {
                return self::fyntuneEmail($request);
            }
        }
    }
    public static function fyntuneEmail(Request $request)
    {
        $messageData = [
            "direct_to" => $request->emailId,
        ];
           
            $name = $request->firstName. ' ' . $request->lastName  ??  $request->name;
            $company = MasterCompany::where('company_alias', $request->companyAlias)->first();
            $brokerName = config('constants.BROKER');
            $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
       
            if ($request->type == 'cashlessGarage') 
            {
                $mailData =[
                    'garageName'=>$request->garageName,
                    'garageMobileNo'=> $request->garageMobileNo, 
                    'garagePincode' =>$request->garagePincode,             
                    'garageAddress'=> $request->garageAddress, 
                    'name' =>$name, 
                    'company' =>$company->company_name,
                    'companycontact_no' =>$company->contact_no,
                    'brokerName' =>$brokerName,
                    'tollFreeNumber' =>$tollFreeNumber,

                ];

                $subData =  $brokerName.' - Cashless Garage Details';
            }
             Mail::to($messageData["direct_to"]) ->send( new FyntuneGarageMail($mailData,$subData));
             return response()->json([
                "status" => true,
                "msg" => 'Email has been sent successfully!.']);
      }



    public function sendSMS(Request $request)
    {
    }

    public function whatsappNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => ['required', 'numeric', 'digits:12'],
            'type' => ['required', 'in:driverDetails,shareQuotes,shareProposal,sharePayment,paymentSuccess,proposalCreated,premiumBreakuppdf,comparepdf,inspectionIntimation,inspectionApproval,otpSms,proposalOtp'],
            'enquiryId' => ['required'],
            // 'link' => ['required'],
            'link' => ['required_unless:type,inspectionIntimation,inspectionApproval'],
        ]);
     
        // if ($validator->fails()) {
        //     dd($validator->errors());
        //     return response()->json([
        //         "status" => false,
        //         "message" => $validator->errors(),
        //     ]);
        // }

        if (config('constants.motorConstant.WHATSAPP_ENABLED') === "N") {
            return response()->json([
                'status' => false,
                'msg' => 'The WhatsApp Module is Disabled'
            ]);
        } 
  
        switch (config('constants.motorConstant.SMS_FOLDER')) {
            case 'ace':
                $response = self::ace_whatsapp($request);
                break;
            case 'hero':
                $response = self::hero_whatsapp($request);
                break;
            case 'pinc':
                $response = self::pinc_whatsapp($request);
                break;
            case 'ola':
                $response = self::ola_whatsapp($request);
                break;
            case 'bajaj':
                $response = self::bajaj_whatsapp($request);
                break;
            case 'spa':
                $response = self::spa_whatsapp($request);
                break;
            case 'abibl':
                $response = self::abibl_whatsapp($request);
                break;
            case 'vcare':
            case 'Atelier':
            case 'WhiteHorse':
                $response = self::saas_whatsapp($request);
                break;
            case 'policy-era':
                $response = WhatsAppController::notification($request);
                break;
            default:
                return;
        }

        if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y') {
            $enquiryId = customDecrypt($request->enquiryId);

            if ((is_object($response) && isset($response->original['status']) && $response->original['status']) || (is_array($response) && isset($response['status']) && $response['status'])) {
                if (in_array($request->type, ['shareQuotes', 'shareProposal', 'proposalCreated'])) {
                    $user_product_journey = UserProductJourney::find($enquiryId);
                    $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

                    if ($lsq_journey_id_mapping) {
                        if (!is_null($lsq_journey_id_mapping->opportunity_id)) {
                            updateLsqOpportunity($enquiryId, $request->type);
                            createLsqActivity($enquiryId, 'opportunity', $request->type);
                        } else {
                            createLsqActivity($enquiryId, 'lead', $request->type);
                        }
                    }
                }
            }
        }

        return $response;
    }

    public static function saas_whatsapp($request)
    {
        if (strtolower(config('constants.motorConstant.WHATSAPP_ENABLED')) == "n") {
            return response()->json([
                'status' => false,
                'msg' => 'This Module is Disabled'
            ]);
        }
        elseif (!$request->filled('to'))
        {
            return response()->json([
                'status' => false,
                'msg' => 'Destination whatsapp number is missing'
            ]);
        }

        $body_data = [
            'destination' => $request->to
        ];

        switch ($request->type) {
            case 'shareQuotes':
            case 'comparepdf':
            case 'premiumBreakuppdf':
                $template_id = trim(config('constants.motorConstant.COMMUNICATION.QUOTE.WHATSAPP.TEMPLATE_ID'));
                if(empty($template_id))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Template ID is invalid'
                    ]);
                }
                else
                {
                    $fname = $request->firstName ?? $request->name;
                    $fname = trim($fname);
                    /*// This can be used later with url shortening
                    if($request->type == 'premiumBreakuppdf')
                    {
                        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $quote_url = $protocol . $_SERVER['HTTP_HOST'];
                        $quote_url = $quote_url . '/api/premiumBreakupPdfemail?download=false&data=' . urlencode($request->data);
                    }
                    else
                    {
                        $quote_url = $request->link;
                    }
                    //*/
                    $quote_url = $request->link;
                    $contact_no = config("constants.brokerConstant.tollfree_number");
    
                    $template_data = [];
                    $template_data['id'] = $template_id;
                    $template_data['params'] = [$fname, $quote_url, $contact_no];
    
                    $body_data['template'] = json_encode($template_data);

                    // Add any headers required here
                    $header_data = [
                        // 'cache-control' => 'no-cache'
                    ];

                    $response = httpRequest('whatsapp_enterprise', $body_data, [], $header_data, [], true, true);

                    \App\Models\WhatsappRequestResponse::create([
                        'ip' => request()->ip(),
                        'enquiry_id' => $request->enquiryId,
                        'request_id' => $response['response']['messageId'] ?? '',
                        'mobile_no' => $request->to ?? '',
                        'request' => $response['request'] ?? [],
                        'response' => $response['response'] ?? [],
                        'additional_data' => json_encode(array('status' => ($response['status'] ?? '')))
                    ]);

                    // Clean not required variables
                    unset($fname, $quote_url, $contact_no, $template_data, $header_data, $body_data);
                }
                break;
            case 'shareProposal':
                $template_id = trim(config('constants.motorConstant.COMMUNICATION.PROPOSAL.WHATSAPP.TEMPLATE_ID'));
                if(empty($template_id))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Template ID is invalid'
                    ]);
                }
                else
                {
                    $fname = $request->firstName ?? $request->name;
                    $fname = trim($fname);
                    $proposal_url = $request->link;
                    $contact_no = config("constants.brokerConstant.tollfree_number");

                    $template_data = [];
                    $template_data['id'] = $template_id;
                    $template_data['params'] = [$fname, $proposal_url, $contact_no];
    
                    $body_data['template'] = json_encode($template_data);

                    // Add any headers required here
                    $header_data = [
                        // 'cache-control' => 'no-cache'
                    ];

                    $response = httpRequest('whatsapp_enterprise', $body_data, [], $header_data, [], true, true);

                    \App\Models\WhatsappRequestResponse::create([
                        'ip' => request()->ip(),
                        'enquiry_id' => $request->enquiryId,
                        'request_id' => $response['response']['messageId'] ?? '',
                        'mobile_no' => $request->to ?? '',
                        'request' => $response['request'] ?? [],
                        'response' => $response['response'] ?? [],
                        'additional_data' => json_encode(array('status' => ($response['status'] ?? '')))
                    ]);

                    // Clean not required variables
                    unset($fname, $proposal_url, $contact_no, $template_data, $header_data, $body_data);
                }
                break;
            case 'proposalCreated':
                $template_id = trim(config('constants.motorConstant.COMMUNICATION.PAYMENT_LINK.WHATSAPP.TEMPLATE_ID'));
                if(empty($template_id))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Template ID is invalid'
                    ]);
                }
                else
                {
                    $fname = $request->firstName ?? $request->name;
                    $fname = trim($fname);
                    $proposal_url = $request->link;
                    $contact_no = config("constants.brokerConstant.tollfree_number");
                    $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                    $proposal_no = $proposal->proposal_no;
                    $premium_amount = $proposal->final_payable_amount;

                    $registration_no = $proposal?->vehicale_registration_number;
                    $registration_no = ((!empty($registration_no)) ? ( strtolower(trim($registration_no))=='new' ? 'new' : $registration_no) : 'new');
                    $proposal_expiry = today()->format('d-m-Y') . " 23:59";


                    $template_data = [];
                    $template_data['id'] = $template_id;
                    $template_data['params'] = [$fname, $proposal_url, $registration_no, $proposal_no, $premium_amount, $proposal_expiry];
    
                    $body_data['template'] = json_encode($template_data);

                    // Add any headers required here
                    $header_data = [
                        // 'cache-control' => 'no-cache'
                    ];

                    $response = httpRequest('whatsapp_enterprise', $body_data, [], $header_data, [], true, true);

                    \App\Models\WhatsappRequestResponse::create([
                        'ip' => request()->ip(),
                        'enquiry_id' => $request->enquiryId,
                        'request_id' => $response['response']['messageId'] ?? '',
                        'mobile_no' => $request->to ?? '',
                        'request' => $response['request'] ?? [],
                        'response' => $response['response'] ?? [],
                        'additional_data' => json_encode(array('status' => ($response['status'] ?? '')))
                    ]);

                    // Clean not required variables
                    unset($fname, $proposal, $proposal_url, $registration_no, $proposal_no, $premium_amount, $proposal_expiry, $contact_no, $template_data, $header_data, $body_data);
                }
                break;
            case 'inspectionIntimation':
                $template_id = trim(config('constants.motorConstant.COMMUNICATION.INSPECTION_INTIMATION.WHATSAPP.TEMPLATE_ID'));
                if(empty($template_id))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Template ID is invalid'
                    ]);
                }
                else
                {
                    // dd(customDecrypt($request->enquiryId));
                    $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                    // dd($user_proposal);
                    $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                    // dump($user_proposal->user_proposal_id);
                    // dd(count($inspectionData?->total() ?? []));
                    
                    if(empty($inspectionData))
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => 'Enquiry ID is not break-in journey or does not have break-in data'
                        ]);
                    }
                    else
                    {
                        // dd($inspectionData);
                        $fname = $request->firstName ?? $request->name ?? $user_proposal->first_name ?? 'Customer';
                        $fname = trim($fname);
                        $insurer = $user_proposal->ic_name;
                        $registration_no = $user_proposal->vehicale_registration_number;
                        $reference_id = $inspectionData->breakin_number;
                        $inspection_dt = !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y');
                    }

                    $template_data = [];
                    $template_data['id'] = $template_id;
                    $template_data['params'] = [$fname, $insurer, $registration_no, $reference_id, $inspection_dt];
    
                    $body_data['template'] = json_encode($template_data);

                    // Add any headers required here
                    $header_data = [
                        // 'cache-control' => 'no-cache'
                    ];

                    $response = httpRequest('whatsapp_enterprise', $body_data, [], $header_data, [], true, true);

                    \App\Models\WhatsappRequestResponse::create([
                        'ip' => request()->ip(),
                        'enquiry_id' => $request->enquiryId,
                        'request_id' => $response['response']['messageId'] ?? '',
                        'mobile_no' => $request->to ?? '',
                        'request' => $response['request'] ?? [],
                        'response' => $response['response'] ?? [],
                        'additional_data' => json_encode(array('status' => ($response['status'] ?? '')))
                    ]);

                    // Clean not required variables
                    unset($fname, $insurer, $registration_no, $reference_id, $inspection_dt, $template_data, $header_data, $body_data);
                }
                break;
            case 'inspectionApproval':
                $template_id = trim(config('constants.motorConstant.COMMUNICATION.INSPECTION_APPROVAL.WHATSAPP.TEMPLATE_ID'));
                if(empty($template_id))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Template ID is invalid'
                    ]);
                }
                else
                {
                    // dd(customDecrypt($request->enquiryId));
                    $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                    // dd($user_proposal);
                    $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                    // dump($user_proposal->user_proposal_id);
                    // dd(count($inspectionData?->total() ?? []));
                    
                    if(empty($inspectionData))
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => 'Enquiry ID is not break-in journey or does not have break-in data'
                        ]);
                    }
                    else
                    {
                        // dd($inspectionData);
                        $fname = $request->firstName ?? $request->name ?? $user_proposal->first_name ?? 'Customer';
                        $fname = trim($fname);
                        $insurer = $user_proposal->ic_name;
                        $registration_no = $user_proposal->vehicale_registration_number;
                        $payment_amount = $user_proposal->final_payable_amount;
                        $link = $inspectionData->breakin_check_url;
                    }

                    $template_data = [];
                    $template_data['id'] = $template_id;
                    $template_data['params'] = [$fname, $insurer, $registration_no, $link, $payment_amount];
    
                    $body_data['template'] = json_encode($template_data);

                    // Add any headers required here
                    $header_data = [
                        // 'cache-control' => 'no-cache'
                    ];

                    $response = httpRequest('whatsapp_enterprise', $body_data, [], $header_data, [], true, true);

                    \App\Models\WhatsappRequestResponse::create([
                        'ip' => request()->ip(),
                        'enquiry_id' => $request->enquiryId,
                        'request_id' => $response['response']['messageId'] ?? '',
                        'mobile_no' => $request->to ?? '',
                        'request' => $response['request'] ?? [],
                        'response' => $response['response'] ?? [],
                        'additional_data' => json_encode(array('status' => ($response['status'] ?? '')))
                    ]);

                    // Clean not required variables
                    unset($fname, $insurer, $registration_no, $payment_amount, $link, $template_data, $header_data, $body_data);
                }
                break;
            default:
                return response()->json([
                    'status' > false,
                    'msg' => 'Notification Type is Incorrect...!',
                ], 500);
                break;
        }

        if ($response) {
            return response()->json([
                'status' => true,
                'msg' => 'WhatsApp message send Successfully...!'
            ]);
        }
        else {
            return response()->json([
                'status' => true,
                'msg' => 'Something Went Wrong'
            ]);
        }
    }

public static function hero_whatsapp($request)
{
  
    // if (strtolower(config('constants.motorConstant.WHATSAPP_ENABLED')) == "n") {
    //     return response()->json([
    //         'status' => false,
    //         'msg' => 'This Module is Disabled'
    //     ]);
    // }
    $userid = config('IC_HERO_WHATSAPP_API_ENABLE.USER_ID');
    $password = config('IC_HERO_WHATSAPP_API_ENABLE.PASSWORD');
    if($request['type']!="inspectionApproval")
    {
    if (!$request->to && !$request->mobileNo) {
        return response()->json([
            'status' => false,
            'msg' => 'Destination WhatsApp number is missing'
        ]);
    }
    }

    if (isset($request->type, $request->notificationType) && $request->type == 'paymentSuccess' && $request->notificationType == 'whatsapp') 
    {
        $request->enquiryId = is_numeric($request->enquiryId) && strlen($request->enquiryId) == 16 ? Str::substr($request->enquiryId, 8): customDecrypt($request->enquiryId);
        $proposal = UserProposal::where('user_product_journey_id', ($request->enquiryId))->first();
        $policydt = PolicyDetails::where('proposal_id', $proposal->user_proposal_id)->first();
        $recipientNumber = $request->to ? $request->to : $request->mobileNo;
        $name = $request->firstName . ' ' . $request->lastName ?? 'Customer';
        $icName = $proposal->ic_name;
        $input_data['msg'] = "Dear {$name},\n\nCongratulations! Your {$icName} insurance policy has been successfully renewed.\n\nPlease find your policy copy attached for your reference.\n\nThank you for choosing us!";
        $pdf_string = $policydt->pdf_url;
        $input_data['mediaUrl'] = $pdf_string;

        $response = httpRequest('whatsapp_enterprise', [
            'userid' => config('IC_HERO_WHATSAPP_API_ENABLE.USER_ID_POLICY_PDF'), //2000199923
            'password' =>  config('IC_HERO_WHATSAPP_API_ENABLE.PASSWORD_POLICY_PDF'), //MuTjsEBn
            'v' => 1.1,
            'format' => 'json',
            'msg_type' => 'DOCUMENT',
            'method' => "SENDMEDIAMESSAGE",
            'media_url' =>  $input_data['mediaUrl'] . "&policy-doc.pdf",
            'isTemplate' => "true",
            'send_to' => $recipientNumber,
            'caption' => $input_data['msg'],
            'footer' => "Hero Insurance",
            'filename' => "Policy-Copy"
        ]);

        \App\Models\WhatsappRequestResponse::create([
            'ip' => request()->ip(),
            'enquiry_id' => $request->enquiryId ?? $request['enquiryId'],
            'request_id' => $response['response']['messageId'] ?? '',
            'mobile_no' => $request->to,
            'request' => $response['request'] ?? [],
            'response' => $response['response'] ?? [],
            'additional_data' => json_encode(['status' => $response['status'] ?? ''])
        ]);

        if ($response['status'] ?? false) {
            return response()->json([
                'status' => true,
                'msg' => 'WhatsApp message sent successfully!'
            ]);
    }
    }

    if($request['type']=="inspectionApproval")
    {
        $recipientNumber=$request['to'];
        $user_proposal = UserProposal::where('user_product_journey_id', $request['enquiryId'])->first();
        $inspectionData = CvJourneyStages::where("user_product_journey_id", $user_proposal->user_product_journey_id)->first();
        $payment_url = shortUrl($inspectionData->proposal_url)['response']['short_url'] ;
        $proposal_expiry = today()->format('d-M-Y');
        $message ="Dear Customer, \n\nYour Inspection request with https://heroinsurance.com for vehicle reg no. {$user_proposal->vehicale_registration_number} is approved. Kindly click on the link {$payment_url} for the payment of Rs. INR {$user_proposal->final_payable_amount}.";

    }
    else{
        $recipientNumber = $request->filled('to') ? $request->to : $request->mobileNo;
        $name = $request->firstName . ' ' . $request->lastName ?? 'Customer';
        $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
        $userid = config('IC_HERO_WHATSAPP_API_ENABLE.USER_ID');
        $password = config('IC_HERO_WHATSAPP_API_ENABLE.PASSWORD');
        $enquiryId =  customDecrypt($request->enquiryId);

        switch ($request->type ?? $request->otpType) {
            case 'shareQuotes':
            case 'comparepdf':
            case 'premiumBreakuppdf':
            $url = shortUrl($request->link);
            $link = $url['response']['short_url'];
            $message = "Hi {$name},\n\nThank you for placing your insurance inquiry at https://www.heroinsurance.com/. \n\nHere is the quote comparison for the selected plans. \n\nClick {$link} or call {$tollFreeNumber} for clarification.";
            break;
        case 'shareProposal':
            $url = shortUrl($request->link);
            $link = $url['response']['short_url'];
            $message = "Hi {$name},\n\nThank you for placing your insurance inquiry at https://www.heroinsurance.com/. \n\nHere is the proposal form for the selected plan. \n\nClick {$link} or call {$tollFreeNumber} for clarification.";
            break;

        case 'proposalCreated':
            $url = shortUrl($request->link);
            $link = $url['response']['short_url'];
            $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            $user_product_journey_id = $request->proposalData['userProductJourneyId'];
            $quote_log = QuoteLog::where('user_product_journey_id', $user_product_journey_id)->pluck('quote_data')->toArray();
            $quote_data = json_decode($quote_log[0], true);
            $manfacture_name = $quote_data['manfacture_name'];
            $model_name = $quote_data['model_name'];
            $version_name =$quote_data['version_name'];
    
            $vehicle_name = $manfacture_name. ' ' . $model_name. ' ' .$version_name;
            $proposal_expiry = today()->format('d-M-Y') . " 00:00Hr";
            $message = "Dear {$name},\n\nPlease click {$link} to pay the premium for your {$vehicle_name} Vehicle policy. Proposal No. {$proposal->proposal_no}. \n\nYour Total Payable Amount is INR {$proposal->final_payable_amount}.\n\nImportant: This link will expire at {$proposal_expiry}.";
            break;

        case 'inspectionIntimation':
            $url = shortUrl($request->link);
            $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
            $proposal_expiry = today()->format('d-M-Y');
            $message = "Dear {$name}, \n\nYour Inspection request with {$user_proposal->ic_name} for vehicle reg no {$user_proposal->vehicale_registration_number} is raised with ID/Reference ID {$inspectionData->breakin_number} on {$proposal_expiry}." ;
            
            break;

        case 'otpSms':
        case 'proposalOtp':
            $otp = PolicySmsOtp::where('enquiryId', $enquiryId)
            ->where('is_expired', 0)
            ->pluck('otp')
            ->first();
            if (empty($otp) && env('APP_ENV') == 'local') {
                $otp = '1234';
            }
            $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            $proposal_expiry_to = today()->format('d-M-Y');
            $proposal_expiry_from = today()->addDay()->format('d-M-Y');
            $message ="{$otp} is OTP for {$user_proposal->ic_name} insurance proposal no. {$user_proposal->proposal_no} from Hero Insurance for {$user_proposal->ic_name}. \n\nPeriod {$proposal_expiry_to}- {$proposal_expiry_from}. {$request->applicableNcb}% NCB. Premium Rs {$request->premiumAmount}.";
            break;

        default:
            return response()->json([
                'status' => false,
                'msg' => 'Invalid request type'
            ]);
    }
    }

        $response = httpRequest('whatsapp_enterprise', [
            'userid' => $userid,
            'password' => $password,
            'send_to' => $recipientNumber,
            'v' => 1.1,
            'format' => 'json',
            'msg_type' => 'TEXT',
            'method' => 'SENDMESSAGE',
            'msg' => $message,
            'isTemplate' => 'true',
            'footer' => 'Hero Insurance'
        ]);


        \App\Models\WhatsappRequestResponse::create([
            'ip' => request()->ip(),
            'enquiry_id' => $request->enquiryId ??$request['enquiryId'],
            'request_id' => $response['response']['messageId'] ?? '',
            'mobile_no' => $recipientNumber,
            'request' => $response['request'] ?? [],
            'response' => $response['response'] ?? [],
            'additional_data' => json_encode(['status' => $response['status'] ?? ''])
        ]);

    if ($response['status'] ?? false) {
        return response()->json([
            'status' => true,
            'msg' => 'WhatsApp message sent successfully!'
        ]);
    } else {
        return response()->json([
            'status' => false,
            'msg' => 'Something went wrong'
        ]);
    }
}


    public static function pinc_whatsapp($request)
    {
        if (config('constants.motorConstant.SMS_ENABLED') == "N") {
            return response()->json([
                'status' => false,
                'msg' => 'This Module is disabled'
            ]);
        }
        httpRequest('whatsapp', [
            'method' => 'OPT_IN',
            'channel' => 'WHATSAPP',
            'phone_number' => $request->to
        ]);
        $tollFreeNumber = config('constants.brokerConstant.tollfree_number');
        if ($request->type == 'shareQuotes') {
            $link = shortUrl($request->link)['response']['short_url'];
            $name = trim($request->firstName . " ". ($request->lastName ?? ""));
            $response = httpRequest('whatsapp', [
                'send_to' => $request->to,
                'isTemplate'=> "false",
                'footer' => '',
                "msg" => "Hi {$name},\nThank you for placing your insurance inquiry at PINC Tree. You can view your selection here. Click {$link} or call {$tollFreeNumber}  for clarification."
            ]);
        }
        if ($request->type == 'shareProposal') {
            $name = trim($request->firstName . " ". ($request->lastName ?? ""));
            $response = httpRequest('whatsapp', [
                'send_to' => $request->to,
                'isTemplate'=> "false",
                'footer' => '',
                "msg" => "Hi {$name},\nThank you for placing your insurance inquiry at PINC Tree. Here is the proposal form for the selected plan. Click {$request->link} or call " . config('constants.brokerConstant.tollfree_number') . " for clarification."
            ]);
        }
        if ($request->type == 'proposalCreated') {
            $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
            $response = httpRequest('whatsapp', [
                'send_to' => $request->to,
                "msg" => "Dear {$request->firstName} {$request->lastName},\nPlease click {$request->link} to pay the premium for your Vehicle policy. Proposal No. {$proposal->proposal_no}. Your Total Payable Amount is INR {$proposal->final_payable_amount}.\nImportant: This link will expire at " . today()->format('d-m-Y') . " 23:59"
            ]);
        }
        if ($request->type == 'paymentSuccess') {
            $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
            $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
            $response = httpRequest('whatsapp', [
                'send_to' => $request->to,
                "msg" => "Congrats,\nPayment of INR {$proposal->final_payable_amount} for car insurance purchased through PINC Tree is successful. Your policy no is {$policy_details->policy_details->policy_number}. To view your policy details, click {$policy_details->policy_details->pdf_url} or call " . config('constants.brokerConstant.tollfree_number') . ""
            ]);
        }

        \App\Models\WhatsappRequestResponse::create([
            'ip' => request()->ip(),
            'enquiry_id' => $request->enquiryId,
            'request_id' => $response['response']['response']['id'],
            'mobile_no' => $response['response']['response']['phone'],
            'request' => $response['request'],
            'response' => $response['response'],
        ]);
        return $response;
    }

    public static function ace_whatsapp($request)
    {   
        try{
            //Triggered from sendEmail via sendSMS (helper file) 
            httpRequest('whatsapp', [
                'method' => 'OPT_IN',
                'channel' => 'WHATSAPP',
                'phone_number' => $request->to ?? $request->mobileNo
            ]);
            //preparing variables for easy integration in template
            $quote= QuoteLog::where(["user_product_journey_id" => customDecrypt($request->enquiryId)])->first();
            $quote_dt=json_decode($quote['quote_data'], TRUE);
            $name = $request->firstName ?? $request->name;
            $regdate = $request->reg_date ?? $quote_dt['vehicle_register_date'] ?? 'Not Mentioned';
            $prevexpdate = $request->previos_policy_expiry_date ?? $quote_dt['previous_policy_expiry_date'] ?? 'Not Mentioned';
            $vhmn = $request->manfacture_name ?? $quote_dt['manfacture_name'] ??  'Not Mentioned';
            $vhmodel = $request->vehicle_model ?? $quote_dt['model_name'] ??  '';
            $vhversion = $request->version_name ?? $quote_dt['version_name'] ??  '';
            $vhregno = $request->vehicle_registration_no ?? $quote_dt['vehicle_registration_no'] ??  'Not Mentioned';
            $rto = $request->rto ?? $quote_dt['rto_code'] ?? 'Not Mentioned';
            $vhname = $vhmn." ".$vhmodel." ".$vhversion;
            $input_data = [
                'send_to' => $request->to,
            ];

            //Templates are highly sensetive for case and spaces.
            // It is advised to use "\n" for breaking line in template as per the template recieved
            if ($request->type == 'shareQuotes') {

                $url = shortUrl($request->link);
                $request->link = $url['response']['short_url'];
                $input_data['msg'] ="Dear {$name}\nPlease click on below link to see comparison of your vehicle Insurance based on following details\n{$request->link} \nRegistration Date: {$regdate}\nPrevious Expiry Date: {$prevexpdate}\nVehicle model:  {$vhname}\nRto: {$rto}\nFor any assistance, please feel free to connect us at 8750881122 or drop an e-mail at operations@aceinsurance.com\n\nACE Insurance Brokers Private Limited\nRegistered Office- B-17 Ashadeep Building, 9 Hailey Road,\nNew Delhi –110 001 \nIRDAI License No. : 246 Period -19.02.22 to 18.02.25 Category: Composite:";        
                $enquiryId = customDecrypt($request->enquiryId);
                self::ace_crm_api($enquiryId, $input_data['msg'], $request->to, '', "whatsapp", "shareQuotes");  

                $response = httpRequest('whatsapp_post', [
                    'method' => 'SendMessage',
                    'msg_type' => 'TEXT',
                    'isHSM' => "True",
                    'msg' =>$input_data['msg'],
                    'send_to' => $request->to
                ]);

                $response['response']['response']['phone'] = $request->to;
            }
            if ($request->type == 'comparepdf') {

                $input_data['msg'] = 
                "Dear {$name}\nPlease find attached comparison of your vehicle Insurance based on following details.\nRegistration Date: {$regdate}\nPrevious Expiry Date: {$prevexpdate}\nVehicle model: {$vhmodel}\nRto: {$rto}\nFor any assistance, please feel free to connect us at 8750881122 or drop an e-mail at operations@aceinsurance.com\n\nACE Insurance Brokers Private Limited\nRegistered Office- B-17 Ashadeep Building, 9 Hailey Road,\nNew Delhi –110 001 \nIRDAI License No. : 246 Period -19.02.22 to 18.02.25 Category: Composite";        
                
                $pdf = \PDF::loadView('comparepdf', ['data' => json_decode($request->data, true)]);
                $pdf_string = $pdf->output();
                $file_name = "WA/{$request->enquiryId}_ComparedPolicy.pdf";
                // Storage::delete($file_name);
                Storage::put($file_name, $pdf_string);
                $pdf_string = file_url($file_name);

                $enquiryId = customDecrypt($request->enquiryId);
                $pdfData = base64_encode($request->data);
                self::ace_crm_api($enquiryId, $input_data['msg'], $request->to, '', "whatsapp", "comparepdf", $pdfData); 

                $input_data['mediaUrl'] = $pdf_string;
                $response = httpRequest('whatsapp_post', [
                    'method' => 'SendMediaMessage',
                    'msg_type' => 'DOCUMENT',
                    'media_url' =>  $input_data['mediaUrl'],
                    'isHSM' => "true",
                    'filename' => "ComparedPolicy",
                    'caption' =>  $input_data['msg'],
                    'send_to' => $request->to,
                ]);

                $response['response']['response']['phone'] = $request->to;
            }
            if ($request->type == 'premiumBreakuppdf') {

                $input_data['msg'] = 
                "Dear {$name}\nThank You!\nPlease find attached premium breakup of your vehicle insurance based on the following details.\nRegistration Date : {$regdate}\nPrevious Expiry Date: {$prevexpdate}\nVehicle model: {$vhname}\nVehicle Registration No: {$vhregno}\nFor any assistance, please feel free to connect us at 8750881122 or drop an e-mail at operations@aceinsurance.com\n\nACE Insurance Brokers Private Limited\nRegistered Office- B-17 Ashadeep Building, 9 Hailey Road,\nNew Delhi –110001 \nIRDAI License No. : 246 Period -19.02.22 to 18.02.25 Category: Composite";
                
                $pdf = \PDF::loadView('demo-copy', ['data' => json_decode($request->data, true)]);
                $pdf_string = $pdf->output();
                $file_name = "WA/{$request->enquiryId}_PremiumBreakup.pdf";
                // Storage::delete($file_name);
                Storage::put($file_name, $pdf_string);
                $pdf_string = file_url($file_name);

                $enquiryId = customDecrypt($request->enquiryId);
                $pdfData = base64_encode($request->data);
                self::ace_crm_api($enquiryId, $input_data['msg'], $request->to, '', "whatsapp","premiumBreakuppdf", $pdfData); 

                $input_data['mediaUrl'] = $pdf_string;
                
                $response = httpRequest('whatsapp_post', [
                    'method' => 'SendMediaMessage',
                    'msg_type' => 'DOCUMENT',
                    'media_url' =>  $input_data['mediaUrl'],
                    'isHSM' => "true",
                    'filename' => "Premium-Breakup",
                    'caption' =>  $input_data['msg'],
                    'send_to' => $request->to
        
                ]);  
            } 

            if ($request->type == 'policyGeneratedSms') {

                $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();       
                $propvhregno =$proposal['vehicale_registration_number'] ?? "Not Mentioned";
                $policydt = PolicyDetails::where ('proposal_id',$proposal->user_proposal_id)->first();
                $policyno =$request->policyNumber ?? $policydt->policy_number ?? $proposal['policy_no'] ?? "Not Generated";
                $request->to = $request->mobileNo  ;
                $input_data['msg'] = "Dear {$name}, Thank you for showing interest in buying policy through ACE Insurance. A soft copy of your Policy No. {$policyno} for vehicle registration number {$propvhregno} attached for your reference. You can also get in touch with our helpline number 8750881122 or write back to us on operations@aceinsurance.com \nACE Insurance Brokers Private Limited, Registered Office- B-17 Ashadeep Building, 9 Hailey Road, New Delhi 110001, IRDAI License No. 246, Period 19.02.22 to 18.02.25, Category: Composite";
                $pdf_string = $policydt->pdf_url;
                $input_data['mediaUrl'] = $pdf_string;

                $enquiryId = customDecrypt($request->enquiryId);
                self::ace_crm_api($enquiryId, $input_data['msg'], $request->to, '', "whatsapp", "policyGeneratedSms"); 
               
                $response = httpRequest('whatsapp_post', [ 
                    'method' => 'SendMediaMessage',
                    'msg_type' => 'DOCUMENT',
                    'media_url' =>  $input_data['mediaUrl'],
                    'filename' => "Policy-copy",
                    'caption' =>  $input_data['msg'],
                    'send_to' => $request->to,
                ]);      
            } 

            if ($request->type == 'proposalCreated') {
            
                $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $proposal->vehicale_registration_number = preg_replace('/-/', '', $proposal->vehicale_registration_number);
                $url = shortUrl($request->link);
                $request->link = $url['response']['short_url'];

                $enquiryId = customDecrypt($request->enquiryId);
                $pdfData = base64_encode($request->data);
                $input_data['msg'] = "Dear {$request->firstName} {$request->lastName}\n\nThank you for showing interest in buying policy through ACE Insurance Brokers. This mail is in reference to your conversation with our Authorized Verifier / Personnel for {$proposal->vehicale_registration_number}.\n \nYou can {$request->link} to pay the premium using your Debit Card, Credit Card or Net Banking option.\n\nFor any further queries you may write back to us on operations@aceinsurance.com\n\nThank you,\nACE Insurance Brokers Private Limited";
                self::ace_crm_api($enquiryId, $input_data['msg'], $request->to, '', "whatsapp", "proposalCreated", $pdfData); 

                $response = httpRequest('whatsapp', [
                    'method' => 'SendMessage',
                    'msg_type' => 'HSM',
                    'isHSM' => "True",
                    'isTemplate' => "true",
                    "linktracking" => TRUE,
                    'msg' => "Dear {$request->firstName} {$request->lastName}\n\nThank you for showing interest in buying policy through ACE Insurance Brokers. This mail is in reference to your conversation with our Authorized Verifier / Personnel for {$proposal->vehicale_registration_number}.\n \nYou can {$request->link} to pay the premium using your Debit Card, Credit Card or Net Banking option.\n\nFor any further queries you may write back to us on operations@aceinsurance.com\n\nThank you,\nACE Insurance Brokers Private Limited",
                    'send_to' => $request->to
                ]);
            }
            
            \App\Models\WhatsappRequestResponse::create([
                'ip' => request()->ip(),
                'enquiry_id' => $request->enquiryId,
                'request_id' => $response['response']['response']['id'] ?? null,
                'mobile_no' => $response['response']['response']['phone'] ?? $request->to ?? null,
                'request' => $response['request'] ?? null,
                'response' => $response['response'] ?? null,
            ]);

            if (isset($response['response']['response']['status']) && $response['response']['response']['status'] == 'success') {
                return [
                    'status' => true,
                    'msg' => 'WhatsApp message send Successfully...!'
                ];
            } else {
                return[
                    'status' => false,
                    'msg' => 'Something went wrong while sending WhatsApp message...!'
                ];
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
        
    }

    public static function bajaj_whatsapp($request)
    {
        cache()->remember('whatsapp_OPT_IN_' . $request->to, 60 * 60 * 24, function () use ($request) {
            return httpRequest('whatsapp', [
                'method' => 'OPT_IN',
                'channel' => 'WHATSAPP',
                'phone_number' => $request->to
            ]);
        });

        $name = $request->firstName ?? $request->name;
        $input_data = [
            'send_to' => $request->to
        ];
        if (config('old_template') != "Y") {
            if ($request->type == 'shareQuotes' || $request->type == 'premiumBreakuppdf') {
                $input_data[ "msg" ] = "Hi {$name},\n\nThank you for placing your insurance inquiry at www.bajajcapitalinsurance.com. Here is the quote comparison for the selected plans. Click {$request->link} or call " . config("constants.brokerConstant.tollfree_number") . " for clarification.\n\n" . config('app.name');
            }
            if ($request->type == 'shareProposal') {
                $input_data[ "msg" ] = "Hi {$request->firstName},\n\nThankyou for placing your insurance inquiry at www.bajajcapitalinsurance.com. Here is the proposal form for the selected plan. Click {$request->link} or call " . config('constants.brokerConstant.tollfree_number') . " for clarification.\n\n" . config('app.name');
            }
            if ($request->type == 'proposalCreated') {
                $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $input_data[ "msg" ] = "Dear {$request->firstName},\n\nPlease click {$request->link} to pay the premium for your   Vehicle policy. Proposal No.{$proposal->proposal_no}. Your Total Payable Amount is INR {$proposal->final_payable_amount}.\nImportant: This link will expire at " . today()->format('d-m-Y') . " 23:59" . ".\n\n" . config('app.name');
            }

        } else {
            if ($request->type == 'shareQuotes' || $request->type == 'premiumBreakuppdf') {
                $input_data[ "msg" ] = "Dear {$name},\n\nThank you for placing your insurance inquiry. Click {$request->link} for viewing your selected quote(s). Call ". config("constants.brokerConstant.tollfree_number") ." for any clarification.\n\nTeam Bajaj Capital Insurance Broking Limited";
            }
            if ($request->type == 'shareProposal') {
                $input_data[ "msg" ] = "Dear {$name},\n\nThank you for placing your insurance inquiry. Click {$request->link} to view the proposal form for the selected plan. Call ". config("constants.brokerConstant.tollfree_number") ." for any clarification. \n\nTeam Bajaj Capital Insurance Broking Limited";
            }
            if ($request->type == 'proposalCreated') {
                // $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $input_data[ "msg" ] = "Dear {$name},\n\nThank you for placing your insurance inquiry. Click {$request->link} to view the proposal form for the selected plan. Call ". config("constants.brokerConstant.tollfree_number") ." for any clarification. \n\nTeam Bajaj Capital Insurance Broking Limited";
            }
        }

        if ($request->type === 'comparepdf') {
            $request->merge(["download" => false]);
            $uuid = Str::uuid()->toString();
            $insertData = DB::table('comapare_pdf_data')->insert([
                'data' => request()->data,
                'uuid' => $uuid,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $input_data[ "send_to" ] = $request->to;
            $input_data[ "msg_type" ] = "DOCUMENT";
            $input_data[ "method" ] = "SENDMEDIAMESSAGE";
            $input_data[ "template_name" ] = "media_template_motor_testing";
            $input_data[ 'filename' ] = "Quote Comparison";
            $input_data[ "caption" ] = "Hi {$name},\n\nThank you for placing your insurance inquiry at www.bajajcapitalinsurance.com. Here is the quote comparison for the selected plans. Call 1800 212 123123 for clarification.\n\nBajaj Capital Insurance Broking";
            $input_data[ 'media_url' ] = url('api/pdf-create?key=') . $uuid;
            
        }

        $response = httpRequest('whatsapp', $input_data);

        \App\Models\WhatsappRequestResponse::create([
            'ip' => request()->ip(),
            'enquiry_id' => $request->enquiryId,
            'request_id' => $response['response']['response']['id'],
            'mobile_no' => $response['response']['response']['phone'],
            'request' => $response['request'],
            'response' => $response['response'],
        ]);
    }

    public static function ola_whatsapp($request)
    {
        switch ($request->type) {
            case 'shareQuotes':
                $pdf = \PDF::loadView('pdf', ['data' => $request->quotes]);
                // $pdf_name = 'quote-pdf/' . \Illuminate\Support\Str::random() . time() . '.pdf';
                // Storage::put($pdf_name, );

                $inputs = [
                    ($request->firstName ?? " ") . " " . ($request->lastName ?? " "),
                    // /* Storage::url */file_url($pdf_name),
                    '*' . config('constants.brokerConstant.tollfree_number') . '*',
                ];

                $inputs = '"' . implode('","', $inputs) . '"';
                $response = httpRequest('whatsapp', [
                    "to" => $request->to,
                    "type" => "mediatemplate",
                    "template_name" => "share_quote_single_variable_salutation1",
                    "params" => $inputs,
                ], [
                    ['media', $pdf->output(), \Illuminate\Support\Str::random(5) . "$request->enquiryId.pdf"]
                ]);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);
                self::whatsappNotificationDash($request->to, $response, $request->enquiryId);

                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                break;
            case 'driverDetails':
                $inputs = [
                    $request->firstName . ' ' . $request->lastName,
                    '*' . config('constants.brokerConstant.tollfree_number') . '*',
                ];
                $inputs = '"' . implode('","', $inputs) . '"';
                $response = httpRequest('whatsapp', [
                    "to" => $request->to,
                    "type" => "template",
                    "template_name" => "driver_document",
                    "params" => $inputs,
                ]);

                self::whatsappNotificationDash($request->to, $response, $request->enquiryId);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);
                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                break;

            case 'shareProposal':
                $proposal = \App\Models\UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->additonal_data;
                $pdf = \PDF::loadView('proposalpdf', compact('proposal'));
                $pdf = $pdf->stream('Proposal Policy.pdf');

                $inputs = [
                    $request->firstName . ' ' . $request->lastName,
                    '*' . config('constants.brokerConstant.tollfree_number') . '*',
                ];
                $inputs = '"' . implode('","', $inputs) . '"';
                $response = httpRequest('whatsapp', [
                    "to" => $request->to,
                    "type" => "template",
                    "template_name" => "proposal_form",
                    "params" => $inputs,
                ]);
                self::whatsappNotificationDash($request->to, $response, $request->enquiryId);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);

                $response = httpRequest(
                    'whatsapp',
                    [
                        "to" => $request->to,
                        "type" => "media",
                    ],
                    [
                        ['media', $pdf, 'Policy Proposal.pdf']
                    ]
                );
                // self::whatsappNotificationDash($request->to, $response, $request->enquiryId);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);
                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                $response = false;
                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                break;

            case 'proposalCreated':
                $inputs = [
                    $request->firstName . ' ' . $request->lastName,
                    $request->link,
                    '*' . config('constants.brokerConstant.tollfree_number') . '*',
                ];
                $inputs = '"' . implode('","', $inputs) . '"';
                $response = httpRequest('whatsapp', [
                    "to" => $request->to,
                    "type" => "template",
                    "template_name" => "purchase_link_1",
                    "params" => $inputs,
                ]);
                self::whatsappNotificationDash($request->to, $response, $request->enquiryId);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);
                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                break;


            case 'paymentSuccess':
                $inputs = [
                    $request->firstName . ' ' . $request->lastName,
                    '*' . config('constants.brokerConstant.tollfree_number') . '*',
                ];
                $inputs = '"' . implode('","', $inputs) . '"';
                $user_product_journey = \App\Models\UserProductJourney::with('user_proposal.policy_details')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $response = httpRequest(
                    'whatsapp',
                    [
                        "to" => $request->to,
                        "type" => "mediatemplate",
                        "template_name" => "policy_document1",
                        "params" => $inputs,
                    ],
                    [
                        [
                            'media', file_get_contents($user_product_journey->user_proposal->policy_details->pdf_url), 'Policy Pdf.pdf'
                        ]
                    ]
                );
                self::whatsappNotificationDash($request->to, $response, $request->enquiryId);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);
                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                break;

            case 'premiumBreakuppdf':
                // return json_decode($request->data, true);
                $pdf = \PDF::loadView('demo-copy', ['data' => json_decode($request->data, true), 'is_pdf' => true]);
                $inputs = [
                    ($request->firstName ?? " ") . " " . ($request->lastName ?? " "),
                    // /* Storage::url */file_url($pdf_name),
                    '*' . config('constants.brokerConstant.tollfree_number') . '*',
                ];

                $inputs = '"' . implode('","', $inputs) . '"';
                return $response = httpRequest('whatsapp', [
                    "to" => $request->to,
                    "type" => "mediatemplate",
                    "template_name" => "share_quote_single_variable_salutation1",
                    "params" => $inputs,
                ], [
                    ['media', $pdf->output(), \Illuminate\Support\Str::random(5) . "$request->enquiryId.pdf"]
                ]);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);
                self::whatsappNotificationDash($request->to, $response, $request->enquiryId);

                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                break;
            case 'comparepdf':
                $pdf = \PDF::loadView('comparepdf', ['data' => json_decode($request->data, true)]);
                $inputs = [
                    ($request->firstName ?? " ") . " " . ($request->lastName ?? " "),
                    // /* Storage::url */file_url($pdf_name),
                    '*' . config('constants.brokerConstant.tollfree_number') . '*',
                ];

                $inputs = '"' . implode('","', $inputs) . '"';
                return $response = httpRequest('whatsapp', [
                    "to" => $request->to,
                    "type" => "mediatemplate",
                    "template_name" => "share_quote_single_variable_salutation1",
                    "params" => $inputs,
                ], [
                    ['media', $pdf->output(), \Illuminate\Support\Str::random(5) . "$request->enquiryId.pdf"]
                ]);
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request->enquiryId,
                    'request_id' => $response['response']['data'][0]['message_id'],
                    'mobile_no' => $response['response']['data'][0]['recipient'],
                    'request' => $response['request'],
                    'response' => $response['response'],
                ]);
                self::whatsappNotificationDash($request->to, $response, $request->enquiryId);

                if ($response) {
                    return response()->json([
                        'status' => true,
                        'msg' => 'Whats App message send Successfully...!'
                    ]);
                }
                break;
            default:
                return response()->json([
                    'status' > false,
                    'msg' => 'Notification Type is wrong ...!',
                ], 500);
                break;
        }
    }

    public static function spa_whatsapp($request)
    {

        $name = $request->firstName  ??  $request->name;

        $whatsappData = [
            "fullPhoneNumber" => $request->to,
            "template" => ["languageCode" => "en"]
        ];

        if ($request->type == 'shareQuotes') {
            $link = shortUrl($request->link)['response']['short_url'];
            $vehicleType = ucfirst(strtolower(get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id))) ?? '';
            $whatsappData["template"]["name"] = "motor_web_portal_share_quote_template_1s";
            $whatsappData["template"]["bodyValues"] = [$name, $vehicleType, $link];
        }

        if ($request->type === 'shareProposal') {
            $link = shortUrl($request->link)['response']['short_url'];
            $whatsappData["template"]["name"] = "motor_web_portal_share_proposal_template_w4";
            $whatsappData["template"]["bodyValues"] = [$name, $link];
        }
        
        if ($request->type === 'paymentSuccess') {
            $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
            $whatsappData["template"]["name"] = "motor_web_portal_policy_success_template";
            $whatsappData["template"]["fileName"] = "Policy Document";
            $whatsappData["template"]["bodyValues"] = [$name];
            $whatsappData["template"]["headerValues"] = [$policy_details->policy_details->pdf_url];
        }

        if($request->type === 'comparepdf'){

            $request->merge(["download" => false]);
            $uuid = Str::uuid()->toString();
            $insertData = DB::table('comapare_pdf_data')->insert([
                'data' => request()->data,
                'uuid' => $uuid,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $link = url('api/pdf-create?key=') . $uuid;
            
            $whatsappData["template"]["name"] = "_motor_web_portal_quote_compare_template";
            $whatsappData["template"]["fileName"] = "Compare PDF";
            $whatsappData["template"]["bodyValues"] = [$name];
            $whatsappData["template"]["headerValues"] = [$link];
        }

        if ($request->type === 'proposalCreated') {
            $link = shortUrl($request->link)['response']['short_url'];
            $whatsappData["template"]["name"] = "motor_web_portal_payment_template";
            $whatsappData["template"]["bodyValues"] = [$name, $link];
        }

        $response = httpRequest('whatsapp', $whatsappData);

        \App\Models\WhatsappRequestResponse::create([
            'ip' => request()->ip(),
            'enquiry_id' => $request->enquiryId,
            'request_id' => $response['response']['id'] ?? "",
            'mobile_no' => $response['request']['fullPhoneNumber'] ?? "",
            'request' => $response['request'],
            'response' => $response['response'],
        ]);

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

    public static function abibl_whatsapp($request)
    {

        if (config('constants.motorConstant.WHATSAPP_ENABLED') == "N") {
            return response()->json([
                'status' => false,
                'msg' => 'This Module is Disabled'
            ]);
        }

        cache()->remember('whatsapp_OPT_IN_' . $request->to, 60 * 60 * 24, function () use ($request) {
            return httpRequest('whats_app_two_way', [
                'method' => 'OPT_IN',
                'channel' => 'WHATSAPP',
                'phone_number' => $request->to
            ]);
        });

        $name = $request->firstName  ??  $request->name;

        $whatsappData = [
            "send_to" => $request->to,
            "msg_type" => "TEXT",
            "method" => "SENDMESSAGE",
            "format" => "json",
            "isTemplate" => "true",
            "footer" => "Aditya Birla Insurance Brokers Limited",
        ];

        if ($request->type == 'shareQuotes') {
            $link = shortUrl($request->link)['response']['short_url'];
            $whatsappData["msg"] = "Dear {$name},\n\nGreetings from Aditya Birla!\n\nThank you for your interest in our motor insurance offerings. We're glad to assist you in finding the right coverage for your needs. Please take a look at the quote shared below and let us know in case you need any assistance. We're always happy to help you!";
        }

        if ($request->type == 'shareProposal') {
            $link = shortUrl($request->link)['response']['short_url'];
            $whatsappData["msg"] = "Hi {$name}, You love your Vehicle and we love to insure it.\nWhile your insurance policy is up for renewal, we want to continue to take the first step to protect your vehicle. Click {$link} to secure your car instantly.";
        }

        if ($request->type == 'proposalCreated') {
            $link = shortUrl($request->link)['response']['short_url'];
            $whatsappData["msg"] = "Dear {{$name}},\n\nThank you for issuing your motor insurance through us. We're happy to assist you in protecting your vehicle. To proceed with the policy, please click {$link} to make the payment. Please let us know if you have any questions or concerns, we're always here to help.";
        }

        if ($request->type == 'paymentSuccess') {

            $templates = \App\Models\RenewalNotificationTemplates::where('type','whatsapp')
            ->where('status','Active')
            ->where('notification_type','success')
            ->get()->toArray();
            foreach($templates as $t_value)
            {

                    $url = paymentSuccessFailureCallbackUrl(customDecrypt($request->enquiryId),'CAR','SUCCESS');;
                    $type = strtoupper($t_value['type']);
                    $store_logs = false;
                    $whatsappData = '';
                    switch ($type) 
                    {
                        case 'WHATSAPP':

                        $whatsappData = [
                                    "send_to"   => $request->to,
                                    "msg_type"  => $t_value['media_type'],
                                    "method"    =>  $t_value['method'],
                                    "isTemplate" => "true",
                                    "msg" => $t_value['template'],
                                    "media_url" => $t_value['media_path'] ?? '',
                                    "cta_button_url" => $url,
                                    "footer" => "Aditya Birla Insurance Brokers Limited",
                                ];

                        if(!empty($t_value['footer']))
                        {
                            unset($whatsappData['footer']);
                            $whatsappData['footer'] = $t_value['footer']; 
                        }

                        if(empty($t_value['media_url']))
                        {
                            unset($whatsappData['media_url']); 
                        }

                        if(!empty($t_value['variables_in_template']))
                        {
                            $whatsappData['msg'] = self::response_build();
                        }
                    }

            }
        }

        $response = httpRequest('whats_app_two_way', $whatsappData);

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


    public function verifySMSOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => ['required'],
            'otp' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        // If the Application Environment is Local i.e. UAT,
        // then allow the user to pass OTP as 1234 and  consider it as a valid OTP - @Amit 26-03-2022
        if ($request->otp == '1234' && env('APP_ENV') == 'local') {
            return response()->json([
                'status' => true,
                'msg' => 'valid OTP'
            ]);
        }

        $otpValidTime = config("LEAD_OTP_VALID_TIME") ?? 10;

        $is_valid_otp = PolicySmsOtp::where([
            "enquiryId" => customDecrypt($request->enquiryId),
            'is_expired' => 0,
            'otp' => $request->otp
        ])->whereBetween('updated_at', [now()->subMinutes($otpValidTime), now()])->latest('id')->first();

        if (!$is_valid_otp) {
            return response()->json([
                'status' => false,
                'msg' => 'invalid OTP'
            ]);
        } else {
            self::updateProposalOtpStatus($request);
            return response()->json([
                'status' => true,
                'msg' => 'valid OTP'
            ]);
        }
    }

    public function updateProposalOtpStatus($request)
    {
        return PolicySmsOtp::where([
            "enquiryId" => customDecrypt($request->enquiryId)
        ])->update(['is_expired' => 1]);
    }

    public function premiumBreakupMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|string',
            // 'email' => 'required|email',
            'enquiryId' => 'required',
            'vehicle_model' => 'required',
            'rto' => 'required',
            'name' => 'required',
            'previos_policy_expiry_date' => 'required',
            'fuel_type' => 'required',
            'productName' => 'required',
            'link' => 'required',
            'logo' => 'required',
            'reg_date' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        if (isset($request->notificationType) && $request->notificationType == "all") {
            $this->whatsappNotification($request);
        }
        try {
            if (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                $messageData = [
                    "direct_to" => $request->email,
                ];
                $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $comparepdf_url = $protocol . $_SERVER['HTTP_HOST'];
                $comparepdf_url = $comparepdf_url . '/api/premiumBreakupPdfemail?download=false&data=' . urlencode($request->data);
                $url = shortUrl($comparepdf_url);
                $url = $url['response']['short_url'];
                //  $messageData['event']='EV_motor_compare_plans';
                $messageData['event'] = 'EV_motor_premium_pdf_attached';
                $messageData['context'] = [
                    "customer_name" => $request->name,
                    "premium_breakuppdf" => $url,
                    "insurer_name" => json_decode($request->data, true)['ic_name'],
                    //  "comparepremium_link"=>$url,                     
                ];
                    httpRequest('sms', $messageData);
                return response()->json([
                    "status" => true,
                    "msg" => 'Premium Breakup Mail has been sent successfully!.'
                ]);
            } else {
                $enquiryId = customDecrypt($request->enquiryId);
                if (config('constants.motorConstant.SMS_FOLDER') == 'abibl' || config('pdf_from_third_party_wrapper') == "Y") {
                    self::abiblEmailUpdate($request->email, $enquiryId);
                    $response = httpRequest('pdf-api', [
                        'html' => view('demo-copy', ['data' => json_decode($request->data, true)])->render(),
                        'name' => date('Y-m-d H:i:s') . ' PremiumBreakup.pdf',
                        'download' => 'false',
                    ], [], [], [], false);

                    $pdf_string = base64_decode($response['response']['response']);
                } else {
                    $pdf = \PDF::loadView('demo-copy', ['data' => json_decode($request->data, true)]);
                    $pdf_string = $pdf->output();
                }
                $data = QuoteLog::select('quote_data', 'product_sub_type_id')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $quote_data = json_decode($data, true);
                $premium_data = json_decode($request->data, true) ?? '';
                $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                $mailData = [
                    'title' => config('constants.motorConstant.SMS_FOLDER') == 'bajaj' ? "Quote details" : "Premium Breakup of your {$request->productName} Insurance by " . config('app.name'),
                    'name' => $request->name,
                    'link' => $request->link,
                    'logo' => $request->logo,
                    'productName' => $request->productName,
                    "vehicle_model" => $request->vehicle_model,
                    "rto" => $request->rto,
                    "previos_policy_expiry_date" => $request->previos_policy_expiry_date,
                    "fuel_type" => $request->fuel_type,
                    "reg_date" => $request->reg_date,
                    'pdf' => $pdf_string,
                    'quote_data'=>  $quote_data,
                    'premium_data' => $premium_data,
                    'product_code' => $product_code ?? ''
                ];
                if(config('constants.motorConstant.SMS_FOLDER') == 'bajaj'){
                    $mailData['title'] = "Premium Breakup of your selected Motor Insurance policy only on " . config('BROKER_WEBSITE');
                }
                if(config('constants.motorConstant.SMS_FOLDER') == 'pinc'){
                    $mailData['title'] = 'Best plan of Vehicle Insurance by PINC Tree';
                }
                if (isset($request->regNumber)) {
                    $mailData["regNumber"] = $request->regNumber;
                }
                if (config('constants.motorConstant.SMS_FOLDER') == 'abibl') {
                    $input_data = [
                        "content" => [
                            "from" => [
                                "name" => config('mail.from.name'),
                                "email" => config('mail.from.address')
                            ],
                            "subject" => $mailData['title'],
                            "html" => (new PremiumBreakupMail($mailData))->render(),
                            "attachments" => [
                                [
                                    "name" => "Premium Breakup.pdf",
                                    "type" => "application/pdf",
                                    "data" => base64_encode($pdf_string)
                                ]
                            ]
                        ],
                        "recipients" => [
                            [
                                "address" => $request->email[0]
                            ]
                        ]
                    ];
                    httpRequest('abibl_email', $input_data);
                    return response()->json([
                        "status" => true,
                        "msg" => 'Premium Breakup Mail has been sent successfully!.'
                    ]);
                }
                if (config('constants.motorConstant.SMS_FOLDER') == 'ss') {
                    $input = [
                        "message" => [
                            "custRef" => "123",
                            "text" => "",
                            "fromEmail" => "ankit@bimaplanner.com",
                            "fromName" => config('app.name'),
                            "replyTo" => "ankit@bimaplanner.com",
                            "recipient" => $request->email[0],
                        ]
                    ];
                    $html_body = (new PremiumBreakupMail($mailData))->render();

                    $input['message']["subject"] = $mailData['title'];
                    $input['message']["html"] = $html_body;
                    httpRequest('email', $input);
                    return response()->json([
                        "status" => true,
                        "msg" => 'Premium Breakup Mail has been sent successfully!.'
                    ]);
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'sib') {

                    $email = "";
                    if (is_array($request->email)) {
                        $email = $request->email[0];
                    } else {
                        $email = $request->email;
                    }

                    $input = [
                        "message" => [
                            "from_email" => config('mail.from.address'),
                            "from_name" =>  config('mail.from.name') ??  config('app.name'),
                            "subject" =>  $mailData['title'],
                            "html" => (new PremiumBreakupMail($mailData))->render(),
                            "to" => ["email" => $email, "name" => $request->name, "type" => "to"],
                            "attachments" => [
                                [
                                    "type" => "application/pdf",
                                    "name" => "Premium Breakup.pdf",
                                    "content" => base64_encode($pdf_string)
                                ]
                            ],
                        ]
                    ];

                    httpRequest('email', $input);
                    return response()->json([
                        "status" => true,
                        "msg" => 'Premium Breakup Mail has been sent successfully!.'
                    ]);
                }

                if (config('constants.motorConstant.SMS_FOLDER') == 'tmibasl') {

                    $email = is_array($request->email) ? $request->email[0] : $request->email;
                  
                    $input = [
                        'personalizations' => [
                            [
                                'to' => [
                                    [
                                        'email' => $email
                                    ]
                                ]
                            ]
                        ],
                        'from' => ['email' => config("constants.brokerConstant.support_email"), 'name' => config("app.name")],
                        'subject' => "Know Premium of Your {$request->productName}",
                        'content' => [[
                            'type' => 'html',
                            'value' => (new PremiumBreakupMail($mailData))->render(),
                        ]],
                        "attachments" => [[
                           "name" => "{$mailData['productName']} Insurance Premium Breakup.pdf",
                           "content" =>  base64_encode($pdf_string)
                        ]]
                    ];

                    httpRequest('email', $input);
                    return response()->json([
                        "status" => true,
                        "msg" => 'Premium Breakup has been sent successfully!.'
                    ]);
                }

                Mail::to($request->email)->send(new PremiumBreakupMail($mailData));
                $html_body = (new PremiumBreakupMail($mailData))->render();
                return response()->json([
                    "status" => true,
                    "msg" => 'Premium Breakup Mail has been sent successfully!.'
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => 'Premium Breakup Mail Has Been Failed...!',
                'data' => $e->getMessage()
            ]);
        }
    }

    public function comapareEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|string',
            // 'email' => 'required|email',
            'enquiryId' => 'required',
            'vehicle_model' => 'required',
            'rto' => 'required',
            'name' => 'required',
            'previos_policy_expiry_date' => 'required',
            'fuel_type' => 'required',
            'productName' => 'required',
            'link' => 'required',
            'logo' => 'required',
            'reg_date' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        if (isset($request->notificationType) && $request->notificationType == "all") {
            $this->whatsappNotification($request);
            sendSMS($request, $request->name, $request->type);
        }
        try {
            if (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                $messageData = [
                    "direct_to" => $request->email,
                ];

                $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $comparepdf_url = $protocol . $_SERVER['HTTP_HOST'];
                // $comparepdf_url = $comparepdf_url . '/api/policyComparePdf?data=' . urlencode($request->data);
                $comparepdf_url = $comparepdf_url . '/api/policyComparePdf?data=' . base64_encode($request->data);
                $comparepdf_url = $comparepdf_url . '&download=false&Compare_page_email=true';
                $url = shortUrl($comparepdf_url);
                $url = $url['response']['short_url'];
                $messageData['event'] = 'EV_motor_compare_plans';
                $messageData['context'] = [
                    "customer_name" => $request->name,
                    "comparepremium_link" => $url,
                ];
                httpRequest('sms', $messageData);
                return response()->json([
                    "status" => true,
                    "msg" => 'Compare Email has been sent successfully!.'
                ]);
            } else {
                $enquiryId = customDecrypt($request->enquiryId);
                if (config('constants.motorConstant.SMS_FOLDER') == 'abibl' || config('pdf_from_third_party_wrapper') == "Y") {
                    self::abiblEmailUpdate($request->email, $enquiryId);
                    # The Email Id Will Reflect on Compare PDF.
                    $data = json_decode($request->data, true); 
                    $data['customer_email'] = is_array($request->email) ? $request->email[0] : $request->email;
                    
                    $response = httpRequest('pdf-api', [
                        'html' => view('comparepdf', ['data' => $data])->render(),
                        'name' => date('Y-m-d H:i:s') . ' PremiumBreakup.pdf',
                        'download' => 'false'
                    ], [], [], [], false);
                    $pdf_string = base64_decode($response['response']['response']);
                } else {
                    $pdf = \PDF::loadView('comparepdf', ['data' => json_decode($request->data, true)]);
                    $pdf_string = $pdf->output();
                }
                $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id);
                $data = QuoteLog::select('quote_data')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $quote_data = json_decode($data, true);
                $mailData = [
                    'title' => "Best plans of  {$request->productName} Insurance by " . config('app.name'),
                    'name' => $request->name,
                    'link' => $request->link,
                    'logo' => $request->logo,
                    'productName' => $request->productName,
                    "vehicle_model" => $request->vehicle_model,
                    "rto" => $request->rto,
                    "previos_policy_expiry_date" => (isset($request->previos_policy_expiry_date) && $request->previos_policy_expiry_date === "New") ? 'NA' : ($request->previos_policy_expiry_date ?? ''),
                    "fuel_type" => $request->fuel_type,
                    "reg_date" => $request->reg_date,
                    'pdf' => $pdf_string,
                    'product_code' => ucfirst(strtolower($product_code)) ?? '',
                    'quote_data' => $quote_data
                ];
                if (config('constants.motorConstant.SMS_FOLDER') == 'bajaj') {
                    $mailData['title'] = "Best plans of {$mailData['product_code']} Insurance - {$request->productName} only on  " . config('BROKER_WEBSITE');
                }
                if(config('constants.motorConstant.SMS_FOLDER') == 'pinc'){
                    $mailData['title'] = 'Best plan of Vehicle Insurance by PINC Tree';
                }
                if (config('constants.motorConstant.SMS_FOLDER') == 'abibl') {
                    $mailData['plan_type'] = json_decode($request->data)->vehicle_insurance_type ?? "";
                }
                if (config('constants.motorConstant.SMS_FOLDER') == 'spa') {
                    $mailData['title'] = "Compare {$request->productName} Plans | " . config('app.name');
                }
                if (config('constants.motorConstant.SMS_FOLDER') == 'tmibasl') {
                    $email = is_array($request->email) ? $request->email[0] : $request->email;
                    $input = [
                        'personalizations' => [
                            [
                                'to' => [
                                    [
                                        'email' => $email
                                    ]
                                ]
                            ]
                        ],
                        'from' => ['email' => config("constants.brokerConstant.support_email"), 'name' => config("app.name")],
                        'subject' => "Compare and Choose The Right Vehicle Insurance Cover With  " . config('app.name'),
                        'content' => [[
                            'type' => 'html',
                            'value' => (new CompareMail($mailData))->render(),
                        ]],
                        "attachments" => [[
                           "content" =>  base64_encode($pdf_string),
                           "name" => "{$mailData['productName']} Insurance Compare.pdf"
                        ]]
                    ];

                    httpRequest('email', $input);
                    return response()->json([
                        "status" => true,
                        "msg" => 'Compare Email has been sent successfully!.'
                    ]);
                }
                if (config('constants.motorConstant.SMS_FOLDER') == 'abibl') {
                    $input_data = [
                        "content" => [
                            "from" => [
                                "name" => config('mail.from.name'),
                                "email" => config('mail.from.address')
                            ],
                            "html" => (new CompareMail($mailData))->render(),
                            "subject" => $mailData['title'],
                            "attachments" => [
                                [
                                    "name" => "{$mailData['productName']} Insurance Compare.pdf",
                                    "type" => "application/pdf",
                                    "data" => base64_encode($pdf_string)
                                ]
                            ]
                        ],
                        "recipients" => [
                            [
                                "address" => $request->email[0]
                            ]
                        ]
                    ];
                    httpRequest('abibl_email', $input_data);
                    return response()->json([
                        "status" => true,
                        "msg" => 'Compare Email has been sent successfully!.'
                    ]);
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'sib') {

                    $email = "";
                    if (is_array($request->email)) {
                        $email = $request->email[0];
                    } else {
                        $email = $request->email;
                    }
                    
                    $input = [
                        "message" => [
                            "from_email" => config('mail.from.address'),
                            "from_name" =>  config('mail.from.name') ??  config('app.name'),
                            "subject" =>  $mailData['title'],
                            "html" => (new CompareMail($mailData))->render(),
                            "to" => ["email" => $email, "name" => $request->name, "type" => "to"],
                            "attachments" => [
                                [
                                    "type" => "application/pdf",
                                    "name" => "{$mailData['productName']} Insurance Compare.pdf",
                                    "content" => base64_encode($pdf_string)
                                ]
                            ],
                        ]
                    ];

                    httpRequest('email', $input);
                    return response()->json([
                        "status" => true,
                        "msg" => 'Compare Email has been sent successfully!.'
                    ]);
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'hero') {
                    $mailData['title'] = "Best plans of {$mailData['product_code']} Insurance - {$request->productName} only on  " . config('BROKER_WEBSITE');
                }
                
                Mail::to($request->email)->send(new CompareMail($mailData));
                $html_body = (new CompareMail($mailData))->render();
                return response()->json([
                    "status" => true,
                    "msg" => 'Compare Email has been sent successfully!.'
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => 'Compare Email Mail Has Been Failed...!',
                'data' => $e->getMessage()
            ]);
        }
    }

    public function whatsapphistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiry_id' => 'required',
            'mobile_no' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        try {

            $user_product_journey = \App\Models\UserProductJourney::with('user_proposal', 'user_proposal.policy_details')->where('user_product_journey_id', customDecrypt($request->enquiry_id))->first();

            $start_date = \Carbon\Carbon::parse($user_product_journey->created_on);

            $end_date = \Carbon\Carbon::parse($user_product_journey->user_proposal->policy_details->created_on ?? now())->addMinutes(15);

            $records = WhatsappRequestResponse::where('mobile_no', '91' . $request->mobile_no)->whereBetween('created_at', [$start_date, $end_date])->get();

            $data = [];
            $i = 0;
            foreach ($records as $key => $value) {
                $i1 = $i++;
                if (isset($value->request['to'])) {
                    $data[$i1]['mobile_no'] = $value->request['to'];
                } elseif (isset($value->request['from'])) {
                    $data[$i1]['mobile_no'] = $value->request['from'];
                } elseif (isset($value->request['mobile'])) {
                    $data[$i1]['mobile_no'] = $value->request['mobile'] ?? 'null';
                } else {
                    $data[$i1]['mobile_no'] = $value->request;
                }

                if (isset($value->request['to']) && isset($value->request['from'])) {
                    $data[$i1]['method'] = 'send';
                } elseif (!isset($value->request['to']) && isset($value->request['from'])) {
                    $data[$i1]['method'] = 'recieved';
                } elseif (isset($value->request['status'])) {
                    $data[$i1]['method'] = 'status';
                }

                $data[$i1]['type'] = isset($value->request['type']) ? $value->request['type'] : "";
                $data[$i1]['created_at'] = $value->created_at;
                if (isset($value->request['type']) && ($value->request['type'] == 'template' || $value->request['type'] == 'mediatemplate')) {
                    $data[$i1]['body'] = 'Template Name :- ' . $value->request['template_name'];
                } elseif (isset($value->request['type']) && $value->request['type'] == 'text') {
                    $data[$i1]['body'] = $value->request['body'];
                } elseif (isset($value->request['type']) && $value->request['type'] == 'document') {
                    $data[$i1]['body'] = $value->request['media_url'];
                } elseif (isset($value->request['type']) && $value->request['type'] == 'image') {
                    $data[$i1]['body'] = $value->request['media_url'];
                } elseif (isset($value->request['status'])) {
                    $data[$i1]['body'] = $value->request['message'] ?? '';
                }
            }
            return response()->json([
                'status' => true,
                'msg' => 'Whatsapp History for Mobile No. ' . '91' . $request->mobile_no,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine(),
            ]);
        }
    }

    public function whatsappNotificationNew(Request $request)
    {
        \App\Models\WhatsappRequestResponse::create([
            'ip' => request()->ip(),
            'request_id' => request()->id,
            'mobile_no' => request()->mobile,
            'request' => request()->all(),
        ]);
        if (isset($request->status))
            return true;
        $mobile_no_response = \App\Models\WhatsappRequestResponse::where('mobile_no', request()->mobile)->whereNotNull('enquiry_id')->latest()->first();

        $mobile_no = \Illuminate\Support\Str::substr($mobile_no_response->mobile_no, 2, 10);
        $user_product_journey = UserProductJourney::with(['agent_details'])->where('user_mobile', $mobile_no)->orWhere('user_product_journey_id', customDecrypt($mobile_no_response->enquiry_id))->orderBy('user_product_journey_id', 'DESC')->first();

        if ($request->type == 'text') {
            $body = $request->body;
        } else if ($request->type == 'document') {
            $body = $request->media_url;
        } else if ($request->type == 'image') {
            $body = $request->media_url;
        } else if ($request->type == 'video') {
            $body = $request->media_url;
        }
        $response = httpRequest('whatsapp_notification_dashboard_push', [
            'enquiry_id' => $user_product_journey->journey_id ?? '',
            'customer_name' => ($user_product_journey->user_fname ?? '') . ' ' . ($user_product_journey->user_lname ?? ''),
            'message_type' => $request->type,
            'message_body' => $body ?? null,
            'method' => 'recieved',
            'mobile_no' => $mobile_no,
            'seller_id' => isset($user_product_journey->agent_details[0]->agent_id) ? $user_product_journey->agent_details[0]->agent_id : null,
            'seller_type' => isset($user_product_journey->agent_details[0]->seller_type) ? $user_product_journey->agent_details[0]->seller_type : null,
            'created_on' => now(),
        ]);
    }

    static public function whatsappNotificationDash($to, $response, $enquiryId = null)
    {
        $mobile_no = \Illuminate\Support\Str::substr($to, 2, 10);
        $user_product_journey = UserProductJourney::with(['agent_details'])->find(customDecrypt($enquiryId));
        return $response = httpRequest('whatsapp_notification_dashboard_push', [
            'enquiry_id' => $enquiryId,
            'customer_name' => $user_product_journey->user_fname . ' ' . $user_product_journey->user_lname,
            'message_type' => $response["request"]['type'] ?? null,
            'message_body' => "Template Name: " . $response["request"]['template_name'] ?? null,
            'method' => 'send',
            'mobile_no' => $mobile_no,
            'seller_id' => isset($user_product_journey->agent_details[0]->agent_id) ? $user_product_journey->agent_details[0]->agent_id : null,
            'seller_type' => isset($user_product_journey->agent_details[0]->seller_type) ? $user_product_journey->agent_details[0]->seller_type : null,
            'created_on' => now()
        ]);
    }

    public function policyShareDashboard(Request $request)
    {

        Mail::to($request->to)->cc($request->cc)->bcc($request->bcc)->send(new PolicyShareDashboard($request->all()));
        return response()->json([
            'status' => true,
            'msg' => 'Email Send Successfully...!',
        ], 200);
    }

    public function callUs(Request $request)
    {
        $request->title = $request["title"] = "Request for Assistance: Car Insurance";

        if (config('constants.motorConstant.SMS_FOLDER') == 'sib') {
            self::sibCallUs($request);
            return response()->json([
                'status' => true,
                'msg' => 'Email has been send successfull...!'
            ], 200);
        }

        Mail::to(config('constants.brokerConstant.support_email'))->send(new CallUs($request->all()));
        return response()->json([
            'status' => true,
            'msg' => 'Email has been send successfull...!'
        ], 200);
    }

    static public function abiblEmail($request)
    {
        $email = $request->emailId;
        $enquiryId = $request->enquiryId;
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $name = $request->firstName . ' ' . $request->lastName;
        $quote_data = DB::table('quote_log as ql')
            ->where('ql.user_product_journey_id', $user_product_journey_id)
            ->select('ql.quote_data')
            ->first();
        $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();

        $cc_email = config('constants.motorConstant.CC_EMAIL');

        switch ($request->type) {
            case 'shareQuotes':
                self::abiblEmailUpdate($email, $user_product_journey_id);
                $mailData = [
                    'title' => "Best plans of {$request->section} insurance recommended - " . config('app.name'),
                    'url' => $request->domain . 'cv/quotes?enquiry_id=' . $enquiryId,
                    'name' => $name,
                    'quotes' => $request->quotes,
                    'section' => $request->section,
                    'logo' => $request->logo,
                    'quote_data' => json_decode(json_decode($quote_data->quote_data)),
                    'corporate_data' => $corporate_data,
                    'gstSelected' => $request->gstSelected
                ];
                $html_body = (new QuotesShareEmail($mailData))->render();
                break;
            case 'shareProposal':
                self::abiblEmailUpdate($email, $user_product_journey_id);
                $mailData = [
                    'title' => "Continue your proposal journey for {$request->productName} - " . config('app.name'),
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];
                $html_body = (new ProposalShareEmail($mailData))->render();
                break;
            case 'sharePayment':
            case 'proposalCreated':
                $mailData = [
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];
                if ($request->type == "sharePayment") {
                    $mailData['title'] = "Continue your payment for {$request->productName} - " . config('app.name');
                } elseif ($request->type == "proposalCreated") {
                    $mailData['title'] = "{$request->productName} Proposal Created Successfully - " . config('app.name');
                }

                $user_propopsal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData['title'] = "Proposal Payment " . $user_propopsal->vehicale_registration_number;
                $mailData['final_amount'] = $user_propopsal->final_payable_amount;
                $mailData['proposal_no'] = $user_propopsal->proposal_no;
                $mailData['policy_end_date'] = Carbon::parse($user_propopsal->policy_end_date)->format('d F Y');
                $mailData['vehicale_registration_number'] = $user_propopsal->vehicale_registration_number;

                $html_body = (new ProposalCreatedEmail($mailData))->render();
                break;
            case 'paymentSuccess':
                $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData = [
                    'title' => "Payment Successful your policy has been issued for {$request->productName} - " . config('app.name'),
                    'name' => $name,
                    'policy_number' => $policy_details->policy_details->policy_number,
                    'proposal_number' => $policy_details->proposal_no,
                    'link' => $policy_details->policy_details->pdf_url,
                    'policy_start_date' => Carbon::parse($policy_details->policy_start_date)->format('d F Y'),
                    'policy_end_date' => Carbon::parse($policy_details->policy_end_date)->format('d F Y'),
                    'final_payable_amount' => $policy_details->final_payable_amount,
                    'product_name' => $request->productName,
                    'logo' => $request->logo
                ];
                $html_body = (new PaymentSuccessEmail($mailData))->render();

                if (!empty($mailData['link'])) {
                    $attachements = [
                        [
                            "name" => "Policy Document.pdf",
                            "type" => "application/pdf",
                            //"data" => base64_encode(file_get_contents($mailData['link']))
                            "data" => base64_encode(\Illuminate\Support\Facades\Storage::get(getFilePathFrom_Url($mailData['link'])))
                        ]
                    ];
                }

                break;
            default:
                return;
        }

        $input_data = [
            "content" => [
                "from" =>  [
                    "name" => config('mail.from.name'),
                    "email" => config('mail.from.address')
                ],
                "subject" => $mailData['title'],
                "html" => $html_body,
            ],
            "recipients" => [
                [
                    "address" => is_array($email) ? $email[0] : $email
                ]
            ]
        ];
        if (isset($attachements)) {
            $input_data['content']['attachments'] = $attachements;
        }

        if ($html_body ?? null) {
            MailLog::create([
                "email_id" => (is_array($request->emailId) ? json_encode($request->emailId) : $request->emailId),
                "mobile_no" => $request->mobileNo,
                "first_name" => $request->firstName,
                "last_name" => $request->lastName,
                "subject" => $mailData['title'] ?? "",
                "mail_body" => $html_body ?? "",
                "enquiryId" => $user_product_journey_id,
            ]);
        }
        return httpRequest('abibl_email', $input_data);
    }

    static private function abiblEmailUpdate($email, $id)
    {
        $email = is_array($email) ? $email[0] : $email;
        if (!empty($id) && !empty($email)) {
            UserProductJourney::where('user_product_journey_id', $id)->update(['user_email' => $email]);
        }
    }

    public function spaEmail($request)
    {
        $email = $request->emailId;
        $enquiryId = $request->enquiryId;
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $name = $request->firstName . ' ' . $request->lastName;

        $quote_data = DB::table('quote_log as ql')
            ->where('ql.user_product_journey_id', $user_product_journey_id)
            ->select('ql.quote_data')
            ->first();
        $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();

        $cc_email = config('constants.motorConstant.CC_EMAIL');

        switch ($request->type) {
            case 'shareQuotes':

                $mailData = [
                    'title' => "Quotes prepared for you {$request->productName} | " . config('app.name'),
                    'url' => $request->domain . 'cv/quotes?enquiry_id=' . $enquiryId,
                    'name' => $name,
                    'quotes' => $request->quotes,
                    'section' => $request->productName,
                    'logo' => $request->logo,
                    'quote_data' => json_decode(json_decode($quote_data->quote_data)),
                    'corporate_data' => $corporate_data,
                    'gstSelected' => $request->gstSelected
                ];
                $html_body = (new QuotesShareEmail($mailData))->render();

                Mail::to($email)->bcc($cc_email)->send(new QuotesShareEmail($mailData));
                break;
            case 'shareProposal':
                $mailData = [
                    'title' => "Complete your {$request->productName} Purchase | " . config('app.name'),
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];
                Mail::to($email)->bcc($cc_email)->send(new ProposalShareEmail($mailData));
                $html_body = (new ProposalShareEmail($mailData))->render();
                break;
            case 'sharePayment':
            case 'proposalCreated':
                $mailData = [
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];
                // if ($request->type == "sharePayment") {
                //     $mailData['title'] = "Continue your payment for {$request->productName} - " . config('app.name');
                // } elseif ($request->type == "proposalCreated") {
                //     $mailData['title'] = "{$request->productName} Proposal Created Successfully - " . config('app.name');
                // }


                $user_propopsal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData['title'] = "Policy Payment Link for " . $user_propopsal->vehicale_registration_number . " | " . config('app.name');
                $mailData['final_amount'] = $user_propopsal->final_payable_amount;
                $mailData['proposal_no'] = $user_propopsal->proposal_no;
                $mailData['policy_end_date'] = Carbon::parse($user_propopsal->policy_end_date)->format('d F Y');
                $mailData['vehicale_registration_number'] = $user_propopsal->vehicale_registration_number;

                Mail::to($email)->bcc($cc_email)->send(new ProposalCreatedEmail($mailData));
                $html_body = (new ProposalCreatedEmail($mailData))->render();
                break;
            case 'paymentSuccess':
                $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData = [
                    'title' => "Payment Successful your policy has been issued for {$request->productName} - " . config('app.name'),
                    'name' => $name,
                    'policy_number' => $policy_details->policy_details->policy_number,
                    'proposal_number' => $policy_details->proposal_no,
                    'link' =>  $policy_details->policy_details->pdf_url,
                    'logo' => $request->logo
                ];
                Mail::to($email)->bcc($cc_email)->send(new PaymentSuccessEmail($mailData));
                $html_body = (new PaymentSuccessEmail($mailData))->render();
                break;
        }
    }

    public function ssEmail($request)
    {
        $email = "";

        if (is_array($request->emailId)) {
            $email = $request->emailId[0];
        } else {
            $email = $request->emailId;
        }

        $enquiryId = $request->enquiryId;
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $name = $request->firstName . ' ' . $request->lastName;

        $quote_data = DB::table('quote_log')
            ->where('user_product_journey_id', $user_product_journey_id)
            ->select('quote_data')
            ->first();
        $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();

        $cc_email = config('constants.motorConstant.CC_EMAIL');
        $input = [
            "message" => [
                "custRef" => "123",
                "text" => "",
                "fromEmail" => "ankit@bimaplanner.com",
                "fromName" => config('app.name'),
                "replyTo" => "ankit@bimaplanner.com",
                "recipient" => $email,
            ]
        ];
        switch ($request->type) {
            case 'shareQuotes':

                $mailData = [
                    'title' => "Quotes prepared for you {$request->productName} | " . config('app.name'),
                    'url' => $request->domain . 'cv/quotes?enquiry_id=' . $enquiryId,
                    'name' => $name,
                    'quotes' => $request->quotes,
                    'section' => $request->productName,
                    'logo' => $request->logo,
                    'quote_data' => json_decode(json_decode($quote_data->quote_data)),
                    'corporate_data' => $corporate_data,
                    'gstSelected' => $request->gstSelected
                ];
                $html_body = (new QuotesShareEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["recipient"] = $email;
                $input['message']["html"] = $html_body;

                break;
            case 'shareProposal':
                $mailData = [
                    'title' => "Complete your {$request->productName} Purchase | " . config('app.name'),
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];

                $html_body = (new ProposalShareEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["recipient"] = $email;
                $input['message']["html"] = $html_body;
                break;
            case 'sharePayment':
            case 'proposalCreated':
                $mailData = [
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];
                if ($request->type == "sharePayment") {
                    $mailData['title'] = "Continue your payment for {$request->productName} - " . config('app.name');
                } elseif ($request->type == "proposalCreated") {
                    $mailData['title'] = "{$request->productName} Proposal Created Successfully - " . config('app.name');
                }


                $user_propopsal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData['title'] = "Policy Payment Link for " . $user_propopsal->vehicale_registration_number . " | " . config('app.name');
                $mailData['final_amount'] = $user_propopsal->final_payable_amount;
                $mailData['proposal_no'] = $user_propopsal->proposal_no;
                $mailData['policy_end_date'] = Carbon::parse($user_propopsal->policy_end_date)->format('d F Y');
                $mailData['vehicale_registration_number'] = $user_propopsal->vehicale_registration_number;

                $html_body = (new ProposalCreatedEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["recipient"] = $email;
                $input['message']["html"] = $html_body;
                break;
            case 'paymentSuccess':
                $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData = [
                    'title' => "Payment Successful your policy has been issued for {$request->productName} - " . config('app.name'),
                    'name' => $name,
                    'policy_number' => $policy_details->policy_details->policy_number,
                    'proposal_number' => $policy_details->proposal_no,
                    'link' =>  $policy_details->policy_details->pdf_url,
                    'logo' => $request->logo
                ];

                $html_body = (new PaymentSuccessEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["recipient"] = $email;
                $input['message']["html"] = $html_body;
                break;
        }
        return httpRequest('email', $input);
    }

    public function sibEmail($request)
    {

        $email = "";

        if (is_array($request->emailId)) {
            $email = $request->emailId[0];
        } else {
            $email = $request->emailId;
        }

        $enquiryId = $request->enquiryId;
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $name = $request->firstName . ' ' . $request->lastName;

        $quote_data = DB::table('quote_log')
            ->where('user_product_journey_id', $user_product_journey_id)
            ->select('quote_data')
            ->first();

        $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();

        $input = [
            "message" => [
                "from_email" => config('mail.from.address'),
                "from_name" =>  config('mail.from.name') ??  config('app.name')
            ]
        ];

        switch ($request->type) {
            case 'shareQuotes':

                $mailData = [
                    'title' => "Quotes prepared for you {$request->productName} | " . config('app.name'),
                    'url' => $request->domain . 'cv/quotes?enquiry_id=' . $enquiryId,
                    'name' => $name,
                    'quotes' => $request->quotes,
                    'section' => $request->productName,
                    'logo' => $request->logo,
                    'quote_data' => json_decode(json_decode($quote_data->quote_data)),
                    'corporate_data' => $corporate_data,
                    'gstSelected' => $request->gstSelected
                ];

                $html_body = (new QuotesShareEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["to"] = ["email" => $email, "name" => $name, "type" => "to"];
                $input['message']["html"] = $html_body;

                break;
            case 'shareProposal':
                $mailData = [
                    'title' => "Complete your {$request->productName} Purchase | " . config('app.name'),
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];

                $html_body = (new ProposalShareEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["to"] = ["email" => $email, "name" => $name, "type" => "to"];
                $input['message']["html"] = $html_body;
                break;
            case 'sharePayment':
            case 'proposalCreated':
                $mailData = [
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];

                if ($request->type == "sharePayment") {
                    $mailData['title'] = "Continue your payment for {$request->productName} - " . config('app.name');
                } elseif ($request->type == "proposalCreated") {
                    $mailData['title'] = "{$request->productName} Proposal Created Successfully - " . config('app.name');
                }

                $user_propopsal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData['title'] = "Policy Payment Link for " . $user_propopsal->vehicale_registration_number . " | " . config('app.name');
                $mailData['final_amount'] = $user_propopsal->final_payable_amount;
                $mailData['proposal_no'] = $user_propopsal->proposal_no;
                $mailData['policy_end_date'] = Carbon::parse($user_propopsal->policy_end_date)->format('d F Y');
                $mailData['vehicale_registration_number'] = $user_propopsal->vehicale_registration_number;

                $html_body = (new ProposalCreatedEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["to"] = ["email" => $email, "name" => $name, "type" => "to"];
                $input['message']["html"] = $html_body;
                break;
            case 'inspectionIntimation':
                $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                $mailData = [
                    'title' => "Inspection Request Raised - " . config('app.name'),
                    'name' => $name,
                    "insurer" => $user_proposal->ic_name,
                    "reference_id" => $inspectionData->breakin_number,
                    'time_stamp' => !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
                    'registration_number' => $user_proposal->vehicale_registration_number,
                    'logo' => $request->logo ?? asset("images/broker-logos/sib.png")
                ];
                $html_body = (new \App\Mail\InspectionIntimationEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input['message']["to"] = ["email" => $email, "name" => $name, "type" => "to"];
                $input['message']["html"] = $html_body;
                break;
            case 'paymentSuccess':

                $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();

                $mailData = [
                    'title' => "Payment Successful your policy has been issued for {$request->productName} - " . config('app.name'),
                    'name' => $name,
                    'policy_number' => $policy_details->policy_details->policy_number,
                    'proposal_number' => $policy_details->proposal_no,
                    'link' =>  $policy_details->policy_details->pdf_url,
                    'logo' => $request->logo
                ];

                $html_body = (new PaymentSuccessEmail($mailData))->render();

                if (!empty($mailData['link'])) {
                    $input['message']["attachments"] = [
                        [
                            "type" => "application/pdf",
                            "name" => "Policy Document.pdf",
                            "content" => base64_encode(httpRequestNormal($mailData['link'], 'GET', [], [], [], [], false)['response'])
                        ]
                    ];
                }

                $input['message']["subject"] = $mailData['title'];
                $input['message']["to"] = ["email" => $email, "name" => $name, "type" => "to"];
                $input['message']["html"] = $html_body;
                break;
        }

        return httpRequest('email', $input);
    }

    public static function sibCallUs($request)
    {
        $email = config('constants.brokerConstant.callus_email');

        $input = [
            "message" => [
                "from_email" => config('mail.from.address'),
                "from_name" =>  config('mail.from.name') ??  config('app.name')
            ]
        ];

        $mailData = [
            'title' => "Request for Assistance | " . config('app.name'),
            'contactName' => $request->contactName,
            'contactNo' => $request->contactNo,
            'email' => $request->email,
            'logo' => $request->logo ?? asset("images/broker-logos/sib.png")
        ];

        $html_body = (new CallUs($mailData))->render();
        $input['message']["subject"] = $mailData['title'];
        $input['message']["to"] = ["email" => $email, "name" => config('app.name'), "type" => "to"];
        $input['message']["html"] = $html_body;

        return httpRequest('email', $input);
    }

    public function tmibaslEmail($request)
    {

        $email = is_array($request->emailId) ? $request->emailId[0] : $request->emailId;
        $enquiryId = $request->enquiryId;
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $name = $request->firstName . ' ' . $request->lastName;

        $quote_data = DB::table('quote_log')
            ->where('user_product_journey_id', $user_product_journey_id)
            ->select('quote_data')
            ->first();

        $corporate_data = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_product_journey_id)->first();

        $input = [
            'personalizations' => [
                [
                    'to' => [
                        [
                            'email' => $email
                        ]
                    ]
                ]
            ],
            'from' => ['email' => config("constants.brokerConstant.support_email"), 'name' => config("app.name")],
            'subject' => "",
            'content' => [[
                'type' => 'html'
            ]]
        ];

        switch ($request->type) {
            case 'shareQuotes':
                $mailData = [
                    'title' => "Choose The Right {$request->productName} Cover With - " . config('app.name'),
                    'url' => $request->domain . 'cv/quotes?enquiry_id=' . $enquiryId,
                    'name' => $name,
                    'quotes' => $request->quotes,
                    'section' => $request->productName,
                    'logo' => $request->logo,
                    'quote_data' => json_decode(json_decode($quote_data->quote_data)),
                    'corporate_data' => $corporate_data,
                    'productName' =>  $request->productName,
                    'gstSelected' => $request->gstSelected
                ];

                $html_body = (new QuotesShareEmail($mailData))->render();
                $input["subject"] = $mailData['title'];
                $input['content'][0]["value"] = $html_body;
                break;
            case 'shareProposal':
                $mailData = [
                    "title" => "Secure Your Vehicle In Few Clicks",
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];

                $html_body = (new ProposalShareEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input["subject"] = $mailData['title'];
                $input['content'][0]["value"] = $html_body;
                break;
            case 'sharePayment':
            case 'proposalCreated':
                $mailData = [
                    'name' => $name,
                    'product_name' => $request->productName,
                    'link' => $request->link,
                    'logo' => $request->logo
                ];

                $user_propopsal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                $mailData['vehicale_registration_number'] = $user_propopsal->vehicale_registration_number;
                $mailData['title'] = "{$request->productName} Payment {$mailData['vehicale_registration_number']}";
                $mailData['final_amount'] = $user_propopsal->final_payable_amount;
                $mailData['proposal_no'] = $user_propopsal->proposal_no;
                $mailData['policy_end_date'] = Carbon::parse($user_propopsal->policy_end_date)->format('d F Y');

                if ($user_propopsal->ic_id == "43") {
                    $mailData['proposal_no'] =  $user_propopsal->unique_quote ?? "";
                }

                $html_body = (new PaymentShareEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input["subject"] = $mailData['title'];
                $input['content'][0]["value"] = $html_body;
                break;
            case 'inspectionIntimation':
                $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();
                $mailData = [
                    'title' => "Inspection Request - " . config('app.name'),
                    'name' => $name,
                    "insurer" => $user_proposal->ic_name,
                    "reference_id" => $inspectionData->breakin_number,
                    'time_stamp' => !empty($inspectionData->created_at) ? Carbon::parse($inspectionData->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
                    'registration_number' => $user_proposal->vehicale_registration_number,
                    'logo' => $request->logo
                ];
                $html_body = (new \App\Mail\InspectionIntimationEmail($mailData))->render();
                $input["subject"] = $mailData['title'];
                $input['content'][0]["value"] = $html_body;
                break;
            case 'paymentSuccess':

                # NSDL Account Creation Mail
                /*
                Commenting beacause of Duplicate Code.
                $emailData = [
                    'title' => "Open E-Insurance Account for your Policies",
                    'name' => $name,
                    'logo' => $request->logo,
                    'nsdlUrl' => $request->nsdlUrl ?? ''
                ];

                $emailBody = (new NsdlAccountCreation($emailData))->render();
                $input['message']["subject"] = $emailData['title'];
                $input["subject"] = $emailData['title'];
                $input['content'][0]["value"] = $emailBody;

                httpRequest('email', $input);
                unset($input['message']["subject"], $input["subject"], $input['content'][0]["value"]);
                */

                # Policy Issued Email
                $policy_details = UserProposal::with('policy_details')->where('user_product_journey_id', $user_product_journey_id)->first();

                $mailData = [
                    'title' => "Payment Successful your policy has been issued for {$request->productName} - " . config('app.name'),
                    'name' => $name,
                    'policy_number' => $policy_details->policy_details->policy_number,
                    'proposal_number' => $policy_details->proposal_no,
                    'link' =>  $policy_details->policy_details->pdf_url,
                    'logo' => $request->logo,
                    'nsdlUrl' => $request->nsdlUrl ?? ''
                ];

                if ($policy_details->ic_id == "43") {
                    $mailData['proposal_number'] =  $policy_details->unique_quote ?? "";
                }

                if (!empty($mailData['link'])) {
                    $input["attachments"] = [
                        [
                            "name" => "Policy Document.pdf",
                            "content" => base64_encode(\Illuminate\Support\Facades\Storage::get(getFilePathFrom_Url($mailData['link'])))
                        ]
                    ];
                }
                $mailData['link'] = null;
                $html_body = (new PaymentSuccessEmail($mailData))->render();
                $input['message']["subject"] = $mailData['title'];
                $input["subject"] = $mailData['title'];
                $input['content'][0]["value"] = $html_body;
                break;
        }

        return httpRequest('email', $input);
    }
    public static function renewbuyInspectionApprovalNotify($enquiryId)
    {

        $user_proposal = \App\Models\UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();

        # SMS
        $messageData['direct_mobile'] = $user_proposal->mobile_number;
        $messageData['context'] = [
            "insurer" =>  substr($user_proposal->ic_name, 0, 25),
            "customer_name" => $user_proposal->first_name . ' ' . $user_proposal->last_name,
            'payment_amount' => $user_proposal->final_payable_amount,
            'registration_number' => $user_proposal->vehicale_registration_number,
            'reference_id' => $inspectionData->breakin_number,
            'link' => shortUrl($inspectionData->payment_url)['response']['short_url'] ?? ""
        ];
        $messageData['event'] = "EV_inspection_req_accepted-_motor_insurance";
        httpRequest('sms', $messageData);

        # EMAIL
        $mailData['event'] = 'EV_inspection_req_accepted-_motor_insurance';
        $mailData["direct_to"] = $user_proposal->email;
        $mailData['context'] = [
            "customer_first_name" => $user_proposal->first_name,
            "insurer" => substr($user_proposal->ic_name, 0, 25),
            "payment_amount" => $user_proposal->final_payable_amount,
            'link' => shortUrl($inspectionData->payment_url)['response']['short_url'] ?? "",
            'registration_number' => $user_proposal->vehicale_registration_number
        ];
        httpRequest('sms', $mailData);
    }

    public static function tmibaslInspectionApprovalNotify($enquiryId)
    {

        $user_proposal = \App\Models\UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();

        # Email
        $mailData = [
            'title' => "Inspection Approval - " . config('app.name'),
            'name' => $user_proposal->first_name,
            "insurer" => $user_proposal->ic_name,
            "payment_amount" => $user_proposal->final_payable_amount,
            "payment_url" => shortUrl($inspectionData->payment_url)['response']['short_url'] ?? "",
            'registration_number' => $user_proposal->vehicale_registration_number,
            'logo' => asset("images/broker-logos/tata.gif")
        ];

        $input = [
            'personalizations' => [
                [
                    'to' => [
                        [
                            'email' => $user_proposal->email
                        ]
                    ]
                ]
            ],
            'from' => ['email' => config("constants.brokerConstant.support_email"), 'name' => config("app.name")],
            'subject' => "Inspection Approval",
            'content' => [[
                'type' => 'html',
                'value' => (new \App\Mail\InspectionApprovalEmail($mailData))->render(),
            ]]
        ];

        httpRequest('email', $input);

        # SMS
        $short_ic = substr($user_proposal->ic_name, 0, 25);
        $payment_url = shortUrl($inspectionData->payment_url)['response']['short_url'] ?? "";
        $messageData['To'] = "91" . $user_proposal->mobile_number;
        $messageData['Text'] = "Dear {$user_proposal->first_name}, Your Inspection request with {$short_ic} for vehicle reg no. {$user_proposal->vehicale_registration_number} is approved. Kindly click on the link {$payment_url} for the payment of Rs. {$user_proposal->final_payable_amount}. -TMIBASL";
        httpRequest('sms', $input);
    }

    public static function pincInspectionApprovalNotify($enquiryId)
    {

        $user_proposal = \App\Models\UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();

        # Email
        $mailData = [
            'title' => "Inspection Approval - " . config('app.name'),
            'name' => $user_proposal->first_name,
            "insurer" => $user_proposal->ic_name,
            "payment_amount" => $user_proposal->final_payable_amount,
            "payment_url" => shortUrl($inspectionData->payment_url)['response']['short_url'] ?? "",
            'registration_number' => $user_proposal->vehicale_registration_number,
            'logo' => asset("images/broker-logos/pinc.png")
        ];

        $cc_email = config('constants.motorConstant.CC_EMAIL');
        Mail::to($user_proposal->email)->bcc($cc_email)->send(new \App\Mail\InspectionApprovalEmail($mailData));
        $html_body = (new \App\Mail\InspectionApprovalEmail($mailData))->render();

        # Store Logs
        self::storeInspectionApprovalLogs($user_proposal, $html_body, $enquiryId);

        # SMS
        $short_ic = substr($user_proposal->ic_name, 0, 25);
        $payment_url = shortUrl($inspectionData->payment_url)['response']['short_url'] ?? "";

        $messagedata = [
            'send_to' => $user_proposal->mobile_number,
            "msg" => "Dear {$user_proposal->first_name}, Your Inspection request on PINC Tree with {$short_ic} for vehicle {$user_proposal->vehicale_registration_number} is approved. Kindly click on the link {$payment_url} to make payment of ₹{$user_proposal->final_payable_amount}."
        ];

        httpRequest('sms', $messagedata);
    }

    public static function shreeInspectionApprovalNotify($enquiryId)
    {
        $user_proposal = \App\Models\UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();

        $short_ic = substr($user_proposal->ic_name, 0, 25);
        $payment_url = shortUrl($inspectionData->payment_url)['response']['short_url'] ?? "";
        $messagedata = [
            'ph' => $user_proposal->mobile_number,
            "text" => "Dear {$user_proposal->first_name}, Your Inspection request with {$short_ic} for vehicle reg no. {$user_proposal->vehicale_registration_number} is approved. Kindly click on the link {$payment_url} for the payment of Rs. {$user_proposal->final_payable_amount}. Lakshmishree"
        ];

        httpRequest('sms', $messagedata);
    }

    public static function sibInspectionApprovalNotify($enquiryId)
    {
        $user_proposal = \App\Models\UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();

        $short_ic = substr($user_proposal->ic_name, 0, 25);
        $payment_url = shortUrl($inspectionData->payment_url)['response']['short_url'] ?? "";
        $messagedata = [
            'msisdn' => $user_proposal->mobile_number,
            "message" => "Dear {$user_proposal->first_name} Your Inspection request with {$short_ic} for vehicle reg no. {$user_proposal->vehicale_registration_number} is approved. Kindly click on the {$payment_url} for the details. - SIBINS"
        ];

        httpRequest('sms', $messagedata);

        # Email

        $input = [
            "message" => [
                "from_email" => config('mail.from.address'),
                "from_name" =>  config('mail.from.name') ??  config('app.name')
            ]
        ];

        $user_proposal = \App\Models\UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $inspectionData = \App\Models\CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();

        $mailData = [
            'title' => "Inspection Request has been Approved - " . config('app.name'),
            'name' => $user_proposal->first_name,
            "insurer" => $user_proposal->ic_name,
            "payment_amount" => $user_proposal->final_payable_amount,
            "payment_url" => $inspectionData->payment_url ?? "",
            'registration_number' => $user_proposal->vehicale_registration_number ?? '',
            'logo' => asset("images/broker-logos/sib.png")
        ];

        $html_body = (new \App\Mail\InspectionApprovalEmail($mailData))->render();
        $input['message']["subject"] = $mailData['title'];
        $input['message']["to"] = ["email" => $user_proposal->email, "name" => $user_proposal->first_name, "type" => "to"];
        $input['message']["html"] = $html_body;
        httpRequest('email', $input);
    }

    private static function storeInspectionApprovalLogs($user_proposal, $html_body, $enquiryId)
    {
        if ($html_body ?? null) {
            MailLog::create([
                "email_id" => $user_proposal->email,
                "mobile_no" => $user_proposal->mobile_number,
                "first_name" => $user_proposal->first_name,
                "last_name" => $user_proposal->first_name,
                "subject" => "Inspection Approval - " . config('app.name'),
                "mail_body" => $html_body ?? "",
                "enquiryId" => $enquiryId,
            ]);
        }
    }

    public static function TmibaslNsdlShareLink($nsdl_response, $request)
    {

        $mailData = [
            'title' => "Open E-Insurance Account for your Policies",
            'name' => $request->first_name,
            'nsdlUrl' => $nsdl_response["link"] ?? $nsdl_response?->link ?? "",
            'logo' => $request->logo ?? asset("images/broker-logos/tata.gif") ?? ""
        ];

        $input = [
            'personalizations' => [
                [
                    'to' => [
                        [
                            'email' =>   $request->email
                        ]
                    ]
                ]
            ],
            'from' => ['email' => config("constants.brokerConstant.support_email"), 'name' => config("app.name")],
            'subject' => $mailData["title"],
            'content' => [[
                'type' => 'html',
                'value' => (new \App\Mail\NsdlAccountCreation($mailData))->render(),
            ]]
        ];

        httpRequest('email', $input);

        $messageData['To'] = "91" . $request->contact;
        $messageData['Text'] = "Click on the link to open E Insurance Account for your Insurance Policies " . ($nsdl_response["link"] ?? $nsdl_response?->link ?? ""). " -TMIBASL";
        httpRequest('sms', $messageData);
    }


    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => ['required_if:otpType,proposalOtp'],
            'emailId' => ['required', 'email:rfc,dns'],
            'mobileNo' => ['required', 'numeric'],
            'otpType' => 'required|in:leadOtp,proposalOtp',
            'domain' => 'required',
            'logo' => 'required',
            'firstName' => 'required',
            'lastName' => 'nullable',
            'policyStartDate' => ['required_if:otpType,proposalOtp'],
            'policyEndDate' => ['required_if:otpType,proposalOtp'],
            'applicableNcb' => ['required_if:otpType,proposalOtp'],
            'premiumAmount' => ['required_if:otpType,proposalOtp'] 
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        
        $mailController = new MailController;

        if ($request->otpType === "leadOtp") {

            if (config("B2C_OTP_REQUIRED") !== "Y") {
                return response()->json([
                    "status" => false,
                    "message" => "OTP Configuration is not Enabled.",
                ]);
            }

            $allowedSellerType = [];

            if (!empty(config('ALLOWED_OTP_SELLER_TYPE'))) {
                $allowedSellerType = explode(',', config('ALLOWED_OTP_SELLER_TYPE'));
            }

            if ((in_array('b2c', $allowedSellerType) && empty($request->tokenResp))
                || (isset($request->tokenResp) && in_array($request->tokenResp['seller_type'], $allowedSellerType))
            ) {
                /* Lead Page Send-Resend Functionality */
                return self::sendLeadOtp($request);
            }
        }

        if (config('constants.motorConstant.SMS_FOLDER') == 'hero') {
            $otpResponse = self::sendProposalPageOtp($request);
            $this->whatsappNotification($request, $otpResponse);
            return response()->json($otpResponse);
        }
        /* Proposal Page Send-Resend Functionality */
        return response()->json(self::sendProposalPageOtp($request));
    }

    public function sendLeadOtp($request)
    {

        $resendOtpTimeLimit = config("LEAD_OTP_RESEND_TIME") ?? 2;
        $otpDigit = config("LEAD_OTP_DIGIT") ?? 4;
        $otpValidTime = config("LEAD_OTP_VALID_TIME") ?? 10;
        $resendOtpMaxAttemt = config("LEAD_OTP_RESEND_MAX_ATTEMPT") ?? 3;

        /* Check Weather OTP Sent and Present Between OTP Timeout Time */
        $isOtpPresent = LeadPageOtp::where([
            "mobile_number" => $request->mobileNo,
            'email' => $request->emailId,
        ])->whereBetween('updated_at', [now()->subMinutes($resendOtpTimeLimit), now()])->first();

        $dbCount = $isOtpPresent?->count ?? 0;
        if ($dbCount == $resendOtpMaxAttemt) {
            return response()->json(
                [
                    "status" => false,
                    "message" => "You have exhausted your attempts."
                ],
                403
            );
        }

        /* If Present Wait for OTP Timeout */

        if ($isOtpPresent) {
            $diffInSeconds = (($resendOtpTimeLimit * 60) - (Carbon::parse($isOtpPresent->updated_at)->diffInSeconds(Carbon::now())));
            return response()->json([
                    "status" => false,
                    "message" => "OTP is Already Sent Wait for {$diffInSeconds} Seconds."
                ]);
        }

        $otp = rand(str_repeat(1, $otpDigit), str_repeat(9, $otpDigit));

        $leadOtps = LeadPageOtp::where([
            'mobile_number' => $request->mobileNo,
            'email' => $request->emailId
        ])->latest('id')->first();

        $count = isset($leadOtps->count) ? ($leadOtps->count + 1) : 1;

        $saveOtps = LeadPageOtp::updateOrCreate(
            [
                'mobile_number' => $request->mobileNo,
                'email' => $request->emailId
            ],
            [
                "mobile_number" => $request->mobileNo,
                'email' => $request->emailId,
                'otp' => $otp,
                'count' => $count,
                'is_expired' => 0
            ]
        );

        if (config('constants.motorConstant.EMAIL_ENABLED') == "Y" && (config('constants.motorConstant.MAIL_OTP_REQUIRED') == 'Y')) {
            $cc_email = config('constants.motorConstant.CC_EMAIL');
            $mailData = [
                'title' => "OTP for your journey - " . config('app.name'),
                'name' => $request->fullName ?? 'Customer',
                'otp' => $otp,
                'logo' => $request->logo,
                'isOtpRequired' => 'Y',
            ];
            $html_body = (new \App\Mail\SendOtpEmail($mailData))->render();
            \Illuminate\Support\Facades\Mail::to($request->emailId)->bcc($cc_email)->send(new \App\Mail\SendOtpEmail($mailData));
        }

        if (config('constants.motorConstant.SMS_ENABLED') == "Y") {
            if (config('constants.motorConstant.SMS_FOLDER') == 'bajaj') {

                if(config('constants.motorConstant.old.otpsms.flow') == 'Y')
                {
                    $messageData['to'] = $request->mobileNo;
                    $messageData['text'] = "{$otp} is your OTP for verification on Bajaj Capital Insurance Platform. It can be used only once and is valid for 10 minutes. Thank you.";     
                    httpRequest('sms', $messageData);
                }
                else
                {
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
                                        "text" => "{$otp} is your OTP for verification on Bajaj Capital Insurance Platform. It can be used only once and is valid for 10 minutes. Thank you.",
                                        "property" => 0,
                                        "id" => "1",
                                        "addresses" => [
                                            [
                                                "from" => "BAJINS",
                                                "to" => '91' . $request->mobileNo,
                                                "seq" => "1747673",
                                                "tag" => "sample tag"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    httpRequest('bajaj_new_sms', $messagedata);
                }
            }
            if (config('constants.motorConstant.SMS_FOLDER') == 'policy-era') {
                $messageData['mobile'] = $request->mobileNo;
                $messageData['message'] = "OTP Login {$otp} is the OTP to login on Policy Era. Please do not share your OTP with anyone.\nPolicy Era Insurance Broking.";     
                httpRequest('sms', $messageData);
            }

            if (config('constants.motorConstant.SMS_FOLDER') == 'hero') {
                $messageData['send_to'] = $request->mobileNo;
                $messageData['msg'] = "{$otp} is your OTP for verification on Hero Insurance Broking Platform. It can be used only once and is valid for 10 minutes. Thank you.";
                httpRequest('sms', $messageData);
            }
        }

        if($saveOtps){
            return response()->json([
                "status" => true,
                "message" => "OTP is Sent Successfully..",
                "data" => [
                    "mailOtpRequired" => config('constants.motorConstant.MAIL_OTP_REQUIRED') ?? 'N',
                    "resendOtpTimeLimit" => ($resendOtpTimeLimit * 60),
                    "otpValidTime" => ($otpValidTime * 60),
                    "otpDigit" => $otpDigit,
                    "resendOtpMaxAttemt" => $resendOtpMaxAttemt
                ]
            ]);
        }

        return response()->json([
            "status" => false,
            "message" => "Something Went Wrong!"
        ]);
       
    }

    public function verifyLeadOtp($request)
    {
        $otpValidTime = config("LEAD_OTP_VALID_TIME") ?? 10;
        
        $isOtpVerified = LeadPageOtp::where([
            "mobile_number" => $request->mobileNo,
            'email' => $request->emailId,
            'is_expired' => 0,
            'otp' => $request->otp
        ])->whereBetween('updated_at', [now()->subMinutes($otpValidTime), now()])->latest('id')->first();


        if ($isOtpVerified == false) {
            return false;
        }

        self::updateLeadOtpStatus($request);
        return true;
    }
    
    public function updateLeadOtpStatus($request)
    {
        return LeadPageOtp::where([
            "mobile_number" => $request->mobileNo,
            'email' => $request->emailId
        ])->update(['is_expired' => 1]);
    }

    /* Proposal PAGE OTP FUNCTIONS */
    public function sendProposalPageOtp($request)
    {
        try {

            $resendOtpTimeLimit = config("LEAD_OTP_RESEND_TIME") ?? 2;
            $otpDigit = config("LEAD_OTP_DIGIT") ?? 4;
            $otpValidTime = config("LEAD_OTP_VALID_TIME") ?? 10;
            $resendOtpMaxAttemt = config("LEAD_OTP_RESEND_MAX_ATTEMPT") ?? 3;

            /* Check Weather OTP Sent and Present Between OTP Timeout Time */

            $isOtpPresent = PolicySmsOtp::where([
                "enquiryId" => customDecrypt($request->enquiryId),
            ])->whereBetween('updated_at', [now()->subMinutes($resendOtpTimeLimit), now()])->first();


            $dbCount = $isOtpPresent?->count ?? 0;
            if ($dbCount == $resendOtpMaxAttemt) {
                return
                [
                    "status" => false,
                    "message" => "You have exhausted your attempts."
                ];
            }

            /* If Present Wait for OTP Timeout */

            if ($isOtpPresent) {
                $diffInSeconds = (($resendOtpTimeLimit * 60) - (Carbon::parse($isOtpPresent->updated_at)->diffInSeconds(Carbon::now())));

                return [
                    "status" => false,
                    "message" => "OTP is Already Sent, Wait for {$diffInSeconds} Seconds."
                ];
            }

            $otp = rand(
                str_repeat(
                    1,
                    $otpDigit
                ),
                str_repeat(
                    9,
                    $otpDigit
                )
            );

            $proposalOtps = PolicySmsOtp::where([
                    'enquiryId' => customDecrypt($request->enquiryId),
                ])->first();


            $count = isset($proposalOtps->count) ? ($proposalOtps->count + 1) : 1;

            $saveOtps = PolicySmsOtp::updateOrCreate(
                [
                    'enquiryId' => customDecrypt($request->enquiryId)
                ],
                [
                    'otp' => $otp,
                    'count' =>  $count,
                    'is_expired' => 0
                ]
            );

            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
            $ic_name = substr($user_proposal->ic_name, 0, 25) ?? "";
            $product_code = get_parent_code(\App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->product_id ?? '');
            $ncb = empty($user_proposal->applicable_ncb) ? "0" : $user_proposal->applicable_ncb;
            $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
            $inspectionData = CvBreakinStatus::with("user_proposal")->where("user_proposal_id", $user_proposal->user_proposal_id)->first();

            if (config('constants.motorConstant.EMAIL_ENABLED') === "Y") {

                $cc_email = config('constants.motorConstant.CC_EMAIL');

                $mailData = [
                    'title' => "OTP for your {$request->productName} - " . config('app.name'),
                    'name' => $user_proposal->first_name . ' ' . $user_proposal->last_name,
                    'proposal_number' => $user_proposal->proposal_no,
                    'otp' => $otp,
                    'logo' => $request->logo,
                    'ic_logo'=>$request->ic_logo ?? '',
                    'ic_name'=>$request->ic_name ?? '',
                    'make_model' => ($user_proposal->ic_vehicle_details['manufacture_name'] ?? '') . ' - ' . ($user_proposal->ic_vehicle_details['model_name'] ?? '') . ' - ' .  ($user_proposal->ic_vehicle_details['version'] ?? ''),
                    'vehicle_manf_year' => $user_proposal->vehicle_manf_year ?? '',
                    'applicable_ncb' => $user_proposal->applicable_ncb ?? '',
                    'final_payable_amount' => $user_proposal->final_payable_amount ?? '',
                    'product_code' => $product_code ?? '',
                    'vehicale_registration_number' => $user_proposal->vehicale_registration_number ?? '',
                    'ncb' =>  $ncb ?? '',
                    'ic_name' => $user_proposal->ic_name ?? '',
                    'policy_start_date' => $user_proposal->policy_start_date ?? '',
                    'policy_end_date' => $user_proposal->policy_end_date ?? ''
                ];

                if(config('constants.motorConstant.SMS_FOLDER')  === 'abibl'){
                    $mailData['is_breakin_case'] = in_array(QuoteLog::with('master_policy')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->master_policy->premium_type_id, [4, 6, 7]) ? 'Y' :  'N';
                }

                $html_body = "";
                if(config('constants.motorConstant.SMS_FOLDER')=='WhiteHorse')
                {
                    $mailData[ 'breakin_id']= $inspectionData->breakin_number ?? '';
                    $mailData['isBreakin'] = isset($inspectionData->breakin_number) ? 'Y':'N';
                    $date = explode('-',$mailData['vehicle_manf_year']);
                    $mailData['vehicle_manf_year'] = $date[1];
                    if (view()->exists('Email.'. config('constants.motorConstant.SMS_FOLDER') .'.otpMailLiberty')){
                        $html_body = (new \App\Mail\SendOtpEmail($mailData))->render();
                    }
                }
                else if (view()->exists('Email.'. config('constants.motorConstant.SMS_FOLDER') .'.otpMail')){
                    $html_body = (new \App\Mail\SendOtpEmail($mailData))->render();
                }

                if (config('constants.motorConstant.SMS_FOLDER')  === 'abibl') {
                    $input_data = [
                        "content" => [
                            "from" =>  [
                                "name" => config('mail.from.name'),
                                "email" => config('mail.from.address')
                            ],
                            "subject" => $mailData['title'],
                            "html" => $html_body,
                        ],
                        "recipients" => [
                            [
                                "address" => $request->emailId
                            ]
                        ]
                    ];

                    httpRequest(
                        'abibl_email',
                        $input_data
                    );
                } elseif (config('constants.motorConstant.SMS_FOLDER')  === 'renewbuy') {

                    $policy_type = QuoteLog::with('master_policy.premium_type')
                    ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                    ->first(['user_product_journey_id', 'master_policy_id'])
                    ->master_policy->premium_type->premium_type;

                    $mailData = array();

                    $mailData = [
                        "direct_to" => is_array($request->emailId) ? $request->emailId : [$request->emailId]
                    ];

                    $mailData['event'] = 'EV_motor_new_otp';
                    $mailData['context'] = [
                        'proposal_no' => $user_proposal->proposal_no,
                        'customer_name' => $user_proposal->first_name . ' ' . $user_proposal->last_name,
                        'insurer_name' => $user_proposal->ic_name,
                        'policy_start_date' => $user_proposal->policy_start_date,
                        'policy_end_date' => $user_proposal->policy_end_date,
                        'otp' => $otp,
                        'policy_type' =>  $policy_type,
                        'premium_payable' => $user_proposal->final_payable_amount
                    ];

                    httpRequest('sms', $mailData);
                } elseif (config('constants.motorConstant.SMS_FOLDER')  === 'ss') {

                    $email = is_array($request->emailId) ? $request->emailId[0] : $request->emailId;

                    $input = [
                        "message" => [
                            "custRef" => "123",
                            "text" => "",
                            "fromEmail" => "ankit@bimaplanner.com",
                            "fromName" => config('app.name'),
                            "replyTo" => "ankit@bimaplanner.com",
                            "recipient" => $email,
                        ]
                    ];

                    $html_body = (new \App\Mail\SendOtpEmail($mailData))->render();
                    $input['message']["subject"] = $mailData['title'];
                    $input['message']["recipient"] = $email;
                    $input['message']["html"] = $html_body;

                    httpRequest('email', $input);
                } elseif (config('constants.motorConstant.SMS_FOLDER')  === 'tmibasl') {

                    $email = is_array($request->emailId) ? $request->emailId[0] : $request->emailId;
                    $input = [
                        'personalizations' => [
                            [
                                'to' => [
                                    [
                                        'email' => $email
                                    ]
                                ]
                            ]
                        ],
                        'from' => ['email' => config("constants.brokerConstant.support_email"), 'name' => config("app.name")],
                        'subject' => $mailData['title'],
                        'content' => [[
                            'type' => 'html',
                            'value' => $html_body,
                        ]]
                    ];

                    httpRequest('email', $input);
                } else {
                    if(config('constants.motorConstant.SMS_FOLDER')  !== 'shree'){
                        \Illuminate\Support\Facades\Mail::to($request->emailId)->bcc($cc_email)->send(new \App\Mail\SendOtpEmail($mailData));
                    }
                }
            }

            if (config('constants.motorConstant.SMS_ENABLED') === "Y") {

                if (config('constants.motorConstant.SMS_FOLDER') === 'compare-policy') {
                    $messagedata = [
                        "message" => "{$otp} is OTP for {$request->section} insurance proposal no. {$request->proposalNumber} from {$request->productName} for {$request->registrationNumber}.Period {$request->policyStartDate}-{$request->policyEndDate}.{$request->applicableNcb} NCB.Premium Rs{$request->premiumAmount}-Comparepolicy",
                        "mobile" => $request->mobileNo,
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'sriyah') {
                    $messagedata = [
                        'to' => '91' . $request->mobileNo,
                        "text" => "One Time Password {$otp} for login to nammacover.com. Please do not share your OTP with anyone. - Sriyah Insurance Brokers"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'pinc') {
                    $messagedata = [
                        'send_to' => (int)$request->mobileNo,
                        "msg" => "Dear Customer, {$otp} is your one time password (otp). Please enter the otp to proceed. - PINC Tree"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'spa') {
                    $messagedata = [
                        'to' => '91' . $request->mobileNo,
                        "message" => "{$otp} is OTP for motor insursnce insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb} NCB. Premium Rs {$user_proposal->final_payable_amount} - SPA Insurance"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'bajaj') {
                    $messagedata = [
                        'to' => '91' . $request->mobileNo,
                        "text" => "OTP {$otp} for {$product_code} insurance Proposal No. {$user_proposal->proposal_no}. Period: {$user_proposal->policy_start_date} to {$user_proposal->policy_end_date}. Premium: Rs {$user_proposal->final_payable_amount}. {$ncb} NCB. Bajaj Capital Insurance Ltd",     //"{$otp} is OTP for {$product_code} insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb} NCB. Premium Rs {$user_proposal->final_payable_amount} - Bajaj Capital Insurance Broking Limited" OLD OTP Template 19-09-2024 #28835
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'uib') {
                    $messagedata = [
                        'number' => $request->mobileNo,
                        "message" => $otp . ' is OTP for ' . $product_code . ' proposal no. ' . $user_proposal->proposal_no . ' from ' . $ic_name . ' for ' . $user_proposal->vehicale_registration_number . '. Period ' . $user_proposal->policy_start_date . '-' . $user_proposal->policy_end_date . '. ' . $ncb . '% NCB. Premium Rs ' . $user_proposal->final_payable_amount . '.UIBINS',
                        "templateid" => 1307165424967918936
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'policy-era') {
                    $messagedata = [
                        'mobile' => $request->mobileNo,
                        "message" => "{$otp} is OTP for {$product_code} insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. Premium Rs {$user_proposal->final_payable_amount}\nPolicy Era Insurance Broking"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'shree') {
                    $messagedata = [
                        'ph' => $request->mobileNo,
                        "text" => "Dear Client {$otp} is OTP for {$product_code} insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. Premium Rs {$user_proposal->final_payable_amount} - Lakshmishree"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'sib') {
                    $messagedata = [
                        'msisdn' => $request->mobileNo,
                        "message" => "Dear {$request->firstName}, {$otp} is OTP for {$request->productName} proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. - SIBINS"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'abibl') {
                    $messagedata = [
                        'To' => $request->mobileNo,
                        'message' => "{$otp} is OTP for motor insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for the period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}.NCB {$ncb}%, Premium Rs {$user_proposal->final_payable_amount}- Aditya Birla Insurance Brokers Ltd."
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'gramcover') {
                    $messagedata =  [
                        "to" => $request->mobileNo,
                        "api_key" => "Ad65f002058f76163382d7bc6e3019c34",
                        'message' => "{$otp} is OTP for {$request->productName} insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->registrationNumber}. Period {$request->policyStartDate}-{$request->policyEndDate}. {$request->applicableNcb} NCB. Premium Rs {$request->premiumAmount} -GramCover Insurance Brokers PVT LTD"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'tmibasl') {
                    $messagedata =  [
                        "To" => "91" . $request->mobileNo,
                        'message' => "{$otp} is OTP for {$product_code} insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. Premium Rs {$user_proposal->final_payable_amount}  - TMIBASL"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'ss') {
                    $messagedata =  [
                        "dest" => $request->mobileNo,
                        'msg' => "{$otp} is OTP for {$product_code} insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb} NCB. Premium Rs {$user_proposal->final_payable_amount} \n- SS Insurance Brokers"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'renewbuy') {
                    $messagedata = ["direct_mobile" => [$request->mobileNo]];
                    if (
                        $user_proposal->ic_name == 'Liberty General Insurance' && (env('APP_ENV') == 'local')
                    ) {
                        $messagedata['context'] = [
                            "otp" => $otp,
                            "mmv" => trim($user_proposal->ic_vehicle_details['manufacture_name']) . '-' . trim($user_proposal->ic_vehicle_details['model_name']) . '-' . trim($user_proposal->ic_vehicle_details['version']),
                            "reg_year" => $user_proposal->vehicle_manf_year,
                            "customer_name" => $user_proposal->first_name . ' ' . $user_proposal->last_name,
                            "new_ncb" => $user_proposal->applicable_ncb,
                            "premium" => $user_proposal->final_payable_amount,
                            "break_in" => in_array(QuoteLog::with('master_policy')->where('user_product_journey_id', customDecrypt($request->enquiryId))->first()->master_policy->premium_type_id, [4, 6, 7]) ? 'Y' :  'N',
                        ];
                        $messagedata['event'] = "EV_motor_liberty_explicit";
                    } else {
                        $messagedata['context'] = [
                            "otp" => $otp,
                            "proposer_name" => $user_proposal->first_name . ' ' . $user_proposal->last_name,
                        ];
                        $messagedata['event'] = "EV_motor_new_otp";
                    }
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'hero') {
                    $messagedata = [
                        'send_to' => $request->mobileNo,
                        'msg' => "{$otp} is OTP to proceed for the payment of INR {$user_proposal->final_payable_amount} for your motor insurance policy.\n
                        Team Hero Insurance Broking India"
                    ];
                }

                if (config('constants.motorConstant.SMS_FOLDER') === 'paytm') {
                    $messagedata = [
                        'template_id' => '1107171324317872704',
                        'to' => $request->mobileNo,
                        'text' => "{$otp} is OTP for {$product_code} insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. Premium Rs {$user_proposal->final_payable_amount}
                        -PIBPL"
                    ];
                }
                else if(config('constants.motorConstant.SMS_FOLDER') === 'WhiteHorse')
                {
                    $messagedata = [
                        'send_to' => $request->mobileNo,
                        'msg' => "{$otp} is OTP for Motor insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. Premium Rs {$user_proposal->final_payable_amount} - WHITE HORSE INSURANCE BROKER PRIVATE LIMITED."
                    ];
                } elseif (config('constants.motorConstant.SMS_FOLDER') == 'Atelier') {
                    $brokerUrl = config('constants.brokerConstant.SMS_TEMPLATE_BROKER_URL');
                    $messagedata = [
                        'send_to' => $request->mobileNo,
                        'msg' => "{$otp} is OTP for Motor insurance proposal no. {$user_proposal->proposal_no} from {$ic_name} for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. Premium Rs {$user_proposal->final_payable_amount} Regards Instant Beema {$brokerUrl} -InstantBeema."
                    ];
                } elseif (config('constants.motorConstant.SMS_FOLDER') == 'OneClick')
                {
                    $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                    $messagedata = [
                        'tempid' => 1707173856530371481,
                        'receiver' => $request->mobileNo,
                        'sms' => "is OTP for {$otp} insurance proposal no. {$user_proposal->proposal_no} from 1Clickpolicy(SIBL) for {$user_proposal->vehicale_registration_number}. Period {$user_proposal->policy_start_date}-{$user_proposal->policy_end_date}. {$ncb}% NCB. Premium Rs {$user_proposal->final_payable_amount} Regards Team 1Clickpolicy(SWASTIKA) SWASTIKA"
                    ];
                }
                httpRequest('sms', $messagedata);
            }

            if ($saveOtps) {
                return [
                    "status" => true,
                    "message" => "OTP is Sent Successfully..",
                    "data" => [
                        "resendOtpTimeLimit" => ($resendOtpTimeLimit * 60),
                        "otpValidTime" => ($otpValidTime * 60),
                        "otpDigit" => $otpDigit,
                        "resendOtpMaxAttemt" => $resendOtpMaxAttemt
                    ]
                ];
            }

            return [
                "status" => false,
                "message" => "Something Went Wrong!"
            ];

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
        } catch (\Exception $e) {
            return [
                "status" => false,
                "message" => "Something Went Wrong!",
                /* "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine() */
            ];
        }
    }

    public function shortUrlService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ]);
        } 

        $shortUrl = shortUrl($request->url)['response']['short_url'] ?? "";

        if (!empty($shortUrl)) {
            return response()->json([
                'status' => true,
                'data' => [
                    "url" => $shortUrl
                ]
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => "Something Went Wrong!"
        ]);

    }



    public static function renewalSMS($request): void
    {
        if (config('constants.motorConstant.SMS_FOLDER') === 'ace') {
            self::aceRenewalSMS($request);
        }
    }

    public static function aceRenewalSMS($request): void
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
        $stage = CvJourneyStages::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();

        $name = trim(($proposal->first_name ?? "") . " " . ($proposal->last_name ?? ""));
        $link = $stage->proposal_url ?? $stage->quote_url ?? "";
        $url = shortUrl($link)['response']['short_url'];
           
        httpRequest('sms', [
            'send_to' => (int)$request->mobileNo,
            "msg" => "Dear {$name}, Please click {$url} to pay the premium for your {$proposal->vehicale_registration_number} Vehicle policy, Proposal No. {$proposal->proposal_no}. Your Total Payable Amount is INR {$proposal->final_payable_amount}.Important: This link will expire at " . today()->format('d-m-Y') . " 23:59.-ACE Insurance Brokers"
        ]);
    }

    public static function fyntunSms($request): void
    {
        if (config('constants.motorConstant.SMS_FOLDER') == 'fyntune') {
            self::fyntuneSMS($request);
        }
    }

    public static function fyntuneSMS($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();

        $name = trim(($proposal->first_name ?? "") . " " . ($proposal->last_name ?? ""));
        $customer_contact = $proposal->mobile_number;
        httpRequest('sms', [
            'send_to' => (int)$customer_contact,
            "msg" => "Hello {$name}, sharing with you the details of Cashless Garage for your convenience:\n\n
                Garage Name: {$request->garageName}\n\n
                Location: {$request->pincode}\n\n
                Contact Number: {$request->mobileNo}"
        ]);


    }

    public function response_build()
    {
        // this function needs to be implmented for variables
    }
    public static function ace_crm_api($user_product_journey_id, $mailData, $email, $cc_email, $channel_type, $event_name="", $pdf_data ="")
    {
        if (config('constants.motorConstant.SMS_FOLDER') == 'ace' && config("constants.motorConstant.GROWTH_CRM_ENABLE") == "Y") {
            $controller = new AceGrowthCrmController();
            $aceGrowthApiResponse = $controller->aceGrowthApi($user_product_journey_id, $mailData, $email, $cc_email, $channel_type,$event_name, $pdf_data);
            if ($aceGrowthApiResponse["status"] == false) {
                return response()->json([
                    "status" => false,
                    "msg" => $aceGrowthApiResponse['msg'] ?? "Getting issue in a Growthcrm API"
                ]);
            }
        }
    }
    
}
