<?php

namespace App\Http\Controllers;

use App\Models\QuoteLog;
use App\Models\MasterRto;
use App\Models\JourneyStage;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use Illuminate\Validation\Rule;
use App\Models\LeadGenerationLogs;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Ixudra\Curl\Facades\Curl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\CorporateVehiclesQuotesRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Extra\UtilityApi;

class LeadController extends Controller
{
    public function getleads(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'segment' => ['required', Rule::in(['CAR', 'BIKE','CV'])],
            'source' => ['required']
        ]);

        $segmentList = [
            1 => 'car',
            2 => 'bike',
        ];

        if(!empty($request->xutm))
        {
            $request->request->add(['token' => $request->xutm]);
        }

        $isTokenEmpty = empty($request->token);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }

        if (!$isTokenEmpty) {

            $commonController = new CommonController;
            $tokenData = $commonController->tokenValidate($request);

            if ($tokenData instanceof \Illuminate\Http\JsonResponse) {
                $tokenData = json_decode($tokenData->getContent(), true);
            }

            if (isset($tokenData['status']) && $tokenData['status'] == false) {
                return response()->json($tokenData);
            }
        }
        $old_journey_id = NULL;
        $request_array = $request->all();
        $request->product_type = $request->segment;

        $user_fname     =   $request->first_name;
        $user_lname     =   $request->last_name;
        $user_email     =   $request->email_id;
        $user_mobile    =   $request->mobile_no;

        $policy_details = NULL;
        if($request->get_lead_url == 'Y')
        {
            $request->reg_no = NULL;
            $request->policy_no = NULL;
        }
        $is_new_vehicle = strtoupper($request->reg_no) == 'NEW' ? true : false;
        if($is_new_vehicle)
        {
            $request->reg_no = NULL;
        }

        if(!empty($request->reg_no) || !empty($request->policy_no))
        {
            $policy_details = UserProposal::join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
            ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->when(strlen($request->reg_no) > 6 , function($query){
                return $query->where('user_proposal.vehicale_registration_number','=', request()->reg_no);                
            }, function($query){
                return $query->where('pd.policy_number','=', request()->policy_no);
            })->where('s.stage','=',STAGE_NAMES['POLICY_ISSUED'])
            ->select('upj.*','user_proposal.*','pd.policy_number','pd.created_on as transaction_date')
            ->orderBy('pd.created_on', 'DESC')
            ->first();
        }
        //Creating new enquiry id for hero if policy is issued even once
        if (!empty($request['createNewEnquiryId']) && $request['createNewEnquiryId'] === true) {
            $policy_details = false;
        }

        if($policy_details)
        {
            $user_fname     =   $policy_details->first_name;
            $user_lname     =   $policy_details->last_name;
            $user_email     =   $policy_details->email;
            $user_mobile    =   $policy_details->mobile_number;
            $old_journey_id = $policy_details->user_product_journey_id;
            $renewal_journey = UserProductJourney::where('old_journey_id', $policy_details->user_product_journey_id)
                ->orderBy('user_product_journey_id', 'desc')
                ->get()
                ->first();
            if(!empty($renewal_journey) && $renewal_journey->status !== 'Inactive')
            {
                $renewal_product_journey_id = $renewal_journey->user_product_journey_id;
                if(isset($tokenData['data']) && !$isTokenEmpty){
                    self::saveRenewalTokenAgent($tokenData['data'], $renewal_journey->user_product_journey_id, $request->token);
                }
                $JourneyStage_data = JourneyStage::where('user_product_journey_id', $renewal_product_journey_id)->first();
                if (in_array($JourneyStage_data->stage,[ STAGE_NAMES['LEAD_GENERATION'],STAGE_NAMES['QUOTE']]))
                {
                    $return_url = $JourneyStage_data->quote_url;
                } 
                else 
                {
                    $return_url = $JourneyStage_data->proposal_url;
                }
                $return_data = [
                    'status'    => true,
                    'redirection_url' => $return_url,
                    'new_user_product_journey_id' => $renewal_product_journey_id,
                    'traceId' => getDecryptedEnquiryId(customEncrypt($renewal_product_journey_id))
                ];
                if(isset($request_array['redirection']) && $request_array['redirection'] == 'N')
                {
                    return $return_data;
                }
                return redirect($return_url);
            }
        }
        $sub_product_type = [
            'CAR'   => 1,
            'BIKE'  => 2
        ];
        if($request->segment == 'CV')
        {
           $product_sub_type_id =  $policy_details->product_sub_type_id ?? NULL;
        }
        else
        {
            $sub_product_type = [
                'CAR'   => 1,
                'BIKE'  => 2
            ];
            $product_sub_type_id = $sub_product_type[$request->segment];            
        }

        if(!empty($renewal_journey))
        {
            $UserProductJourney = $renewal_journey;
        }
        else
        {
            $UserProductJourney = UserProductJourney::create([
                'product_sub_type_id'   => $product_sub_type_id,
                'user_fname'            => $user_fname,
                'user_lname'            => $user_lname,
                'user_email'            => $user_email,
                'user_mobile'           => $user_mobile,
                'lead_source'           => $request->source,
                'lead_stage_id'         => 2,
                'lead_id' => empty($request->lead_id) ? Str::uuid()->toString() : $request->lead_id,
                'old_journey_id'        => $old_journey_id
            ]);
        }

        if(isset($tokenData['data']) && !$isTokenEmpty){
            self::saveRenewalTokenAgent($tokenData['data'], $UserProductJourney->user_product_journey_id, $request->token);
        }

        #for genrating user token
        if (config('constants.motorConstant.SMS_FOLDER') == "ace" && config('constants.motorConstant.EB_LEAD_GENERATION_ENABLE') == "Y" &&  $request->source == "eb-platform") {

            $token_generate = httpRequest('dashboard-user-token-generation');
            $get_token = $token_generate['response']['token'] ?? NULL;
        }     
        
        $enquiry_id = customEncrypt($UserProductJourney->user_product_journey_id);
        $query_parameters = [
            'enquiry_id'        => $enquiry_id,
            'registration_no'   => $request->reg_no,
            'source'            => $request->source,
            'token'      =>  !$isTokenEmpty ? $request->token : NULL,     
            'xutm'      =>  $get_token ?? NULL,   
        ];

        $segment = $segmentList[$UserProductJourney->product_sub_type_id] ?? 'cv';
        $frontend_url = config('constants.motorConstant.' . strtoupper($segment) . '_FRONTEND_URL');
        $redirect_page = '/registration?';
        updateJourneyStage([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id,
            'stage'                     => STAGE_NAMES['LEAD_GENERATION'],
            'proposal_url'              => $frontend_url.$redirect_page. "enquiry_id=" . $enquiry_id,
            'quote_url'                 => $frontend_url.$redirect_page. "enquiry_id=" . $enquiry_id
        ]);

        $return_url = $frontend_url . $redirect_page . http_build_query($query_parameters);
        $quoteDataJson['vehicle_registration_no'] = $corporateVehiclesQuotesRequestData['vehicle_registration_no'] = $request->reg_no;
        $quoteDataJson['remove_header_footer'] = $corporateVehiclesQuotesRequestData['remove_header_footer'] = $request->remove_header_footer;
        CorporateVehiclesQuotesRequest::updateOrCreate(['user_product_journey_id' => $UserProductJourney->user_product_journey_id], $corporateVehiclesQuotesRequestData);
        $QuoteLogData['quote_data'] = json_encode($quoteDataJson);
        QuoteLog::updateOrCreate(['user_product_journey_id' => $UserProductJourney->user_product_journey_id], $QuoteLogData);
        
        $payload = [
           'enquiryId'         => $enquiry_id,
           'registration_no'   => 'NULL',
           'productSubType'    => $product_sub_type_id,//$sub_product_type[$request->product_type],
           'section'           => strtolower($request->product_type),
           'is_renewal'        => 'Y',
        ];
//        print_r($payload);
//        die;
        if(strtoupper($request->reg_no) == 'NEW')
        {
            $request->reg_no = '';
        }

        if (!empty($request->reg_no)) 
        {
            $payload['registration_no'] = getRegisterNumberWithHyphen(str_replace("-", "", $request->reg_no));
            $payload['vendor_rc']       = $request->reg_no;
        } 
        else if(!empty($request->policy_no))
        {
            $payload['isPolicyNumber'] = 'Y';
            $payload['policyNumber'] = $request->policy_no; 
        }
        if(!empty($request->skip) && $request->skip == 'Y')
        {
            $payload['skip'] = 'Y';
        }
        $oldRequest = $request->all();
        if(empty($request->reg_no) && empty($request->policy_no))
        {
            $getVehicleDetails = [];
        }
        else
        {
            $common = new CommonController;
            $customRequest = new Request($payload);
            // $getVehicleDetails = $common->getVehicleDetails(request()->replace($payload));
            $getVehicleDetails = $common->getVehicleDetails($customRequest);
        }
//        if(is_array($getVehicleDetails) && isset($getVehicleDetails['status']) && $getVehicleDetails['status'] == false)
//        {
//            //return json_encode($getVehicleDetails); 
//        }            
        $getVehicleDetails = is_array($getVehicleDetails) ? $getVehicleDetails : json_decode($getVehicleDetails->content(), TRUE);
        
        if(is_array($getVehicleDetails) && isset($getVehicleDetails['status']) && $getVehicleDetails['status'] == false && isset($getVehicleDetails['msg']) && strpos($getVehicleDetails['msg'], 'Policy already issued with Policy Number') !== false)
        {
            UserProductJourney::where('user_product_journey_id',$UserProductJourney->user_product_journey_id)
            ->update(['status' => 'Inactive']);
            sleep(1);
            return $getVehicleDetails;
        }
        if(isset($getVehicleDetails['data']['status']) && $getVehicleDetails['data']['status'] == 100)
        {
            if(($getVehicleDetails['data']['ft_product_code'] ?? '') == 'cv' && strtolower($payload['section']) == 'cv')
            {
                UserProductJourney::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
                ->update(['product_sub_type_id' => $getVehicleDetails['data']['additional_details']['product_sub_type_id']]);
            }
            UserProductJourney::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
                ->update(['lead_stage_id' => 2]);
            
            $UserProductJourney = UserProductJourney::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
            ->first();


            $segment = $segmentList[$UserProductJourney->product_sub_type_id] ?? 'cv';

            $frontend_url = config('constants.motorConstant.' . strtoupper($segment) . '_FRONTEND_URL');

            $return_url = $quote_url = $frontend_url.'/quotes?'.http_build_query($query_parameters);
            $proposal_url = $frontend_url.'/quotes?'.http_build_query($query_parameters);
            updateJourneyStage([
                'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                'stage'         => STAGE_NAMES['QUOTE'],
                'proposal_url'  => $proposal_url,
                'quote_url'     => $quote_url
            ]);
        }

        if($request->get_lead_url == 'Y')
        {
            $return_url = $quote_url = $frontend_url.'/lead-page?'.http_build_query($query_parameters);
            $proposal_url = $frontend_url.'/lead-page?'.http_build_query($query_parameters);
            updateJourneyStage([
                'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                'stage'         => STAGE_NAMES['LEAD_GENERATION'],
                'proposal_url'  => $proposal_url,
                'quote_url'     => $quote_url
            ]);
        }

        $parsed_url = parse_url($return_url);
        parse_str($parsed_url['query'], $query_params);
        $source = $query_params['source'];
        $return_data = [
            'status'    => true,
            'redirection_url' => $return_url,
            'new_user_product_journey_id' => customDecrypt($enquiry_id),
            'source' => $source,
            'traceId' => getDecryptedEnquiryId($enquiry_id)
        ];
        if (
            isset($getVehicleDetails['msg']) &&
            (
                str_starts_with($getVehicleDetails['msg'], 'Mismatched vehicle type') ||
                str_starts_with($getVehicleDetails['msg'], 'Mismtached vehicle type')
            )
        ) {
            $return_data = [
                'status'     => false,
                //'error_msg'  => strtoupper($getVehicleDetails['data']['ft_product_code']).' and request made for '.strtoupper($request->product_type),
                'error_msg'  => 'The vehicle information shows a mismatch - the Vahan record indicates a '.strtoupper($getVehicleDetails['data']['ft_product_code']).', while the request pertains to a '.strtoupper($request->product_type).'.',
                // 'redirection_url' => $return_url,
                'new_user_product_journey_id' => customDecrypt($enquiry_id),
                'trace_id'  => $enquiry_id,
                'source' => $source,
                'traceId' => getDecryptedEnquiryId($enquiry_id),
                'product_code_vahan'    => $getVehicleDetails['data']['ft_product_code']
            ];

            UserProductJourney::where('user_product_journey_id',$UserProductJourney->user_product_journey_id)
                ->update(['status' => 'Inactive']);
        }
        $agent_exits = CvAgentMapping::where('user_product_journey_id', $return_data['new_user_product_journey_id'])->exists();
        if(!$agent_exits)
        {
            $data = [
                'user_product_journey_id'   => $return_data['new_user_product_journey_id']
            ];
            if($isTokenEmpty)
            {
                $data['seller_type'] = 'b2c';
            }
            CvAgentMapping::create($data);
        }
        $product_id_null = CorporateVehiclesQuotesRequest::where('user_product_journey_id',$return_data['new_user_product_journey_id'])->whereNull('product_id')->exists();
        if($product_id_null)
        {
            $data = [
                'product_id'   => $product_sub_type_id ?? 6
            ];
            CorporateVehiclesQuotesRequest::where('user_product_journey_id',$return_data['new_user_product_journey_id'])->update($data);
        }
        if (config('constants.motorConstant.SMS_FOLDER') == "ace" && config('constants.motorConstant.EB_LEAD_GENERATION_ENABLE') == "Y" &&  $return_data["source"] == "eb-platform") {
            UpdateEnquiryStatusByIdAceLead($request, $return_data, $enquiry_id);

            $return_data['complete_trace_id'] = $enquiry_id;

            if(!empty($get_token ?? NULL )){
                DashboardController::updateAgentDetils($get_token , $UserProductJourney->user_product_journey_id);
            }

            LeadGenerationLogs::insert(
                [
                    'enquiry_id'    => customDecrypt($enquiry_id),
                    'request'       => json_encode($request->all()),
                    'response'      => json_encode($return_data),
                    'method'        => 'Generate Lead-Id',
                    'step'          => 1,
                    'created_at'    => Carbon::now(),
                    'updated_at'    => Carbon::now(),
                ]
            );
        }
        if(isset($request_array['redirection']) && $request_array['redirection'] == 'N')
        {
            return $return_data;
        }
        return redirect($return_url);
    }
    
    public function journeyRedirection(Request $request)
    {
        $commonController = new CommonController;
        $origional_request = clone $request;
//        dd($request);
//        "user_details" => array:9 [
//        "seller_type" => "U"
//        "seller_username" => "8805685311"
//        "lob" => "Car"
//        "first_name" => "Amit"
//        "last_name" => "Patil"
//        "email_id" => "amit.p@fyntune.com"
//        "gender" => "MALE"
//        "source" => "TML"
//        "return_url" => ""
//      ]
//      "additional_info" => array:15 [
//        "chassis_number" => "MTLOU7680973565"
//        "engine_number" => "DGT897686K98633"
//        "vehicle_type" => "NEW/RENEW"
//        "variant_id" => "2"
//        "model_id" => "1"
//        "manf_id" => "1"
//        "fuel_type" => "PETROL"
//        "registration_date" => "2022-04-28"
//        "existing_policy_expiry_date" => "2023-04-28"
//        "previous_ncb" => "25"
//        "claim_status" => "N"
//        "registration_number" => "MH-46-BV-6002"
//        "owner_type" => "IND"
//        "rto_number" => "MH-46"
//        "policy_source" => "TML"
//      ]
//      "token" => "224c81b6-32ed-427a-8db3-f336baaa8dfb"
//      "user_token_id" => 54
        $user_details = $request->user_details;
        $additional_info = $request->additional_info;
        $segment = $user_details['lob'];
        //$registration_no = $additional_info['registration_number'] ?? NULL;
        $sub_product_type = [
            'CAR'   => 1,
            'BIKE'  => 2
        ];
        
        if($segment == 'cv')
        {
           $product_sub_type_id = NULL;
        }
        else
        {
            $product_sub_type_id = $sub_product_type[strtoupper($segment)];            
        }
        if(!empty($additional_info['registration_number']))
        {
            $additional_info['registration_number'] = getRegisterNumberWithHyphen(str_replace("-", "", $additional_info['registration_number']));
        }
        
        //$additional_info['chassis_number'] = 'MBHCZFB3SNE262661';
        //$additional_info['chassis_number'] = NULL;
        $filterDataWithChassis = !empty($additional_info['chassis_number']) ? true : false;
        $chassis_number = $additional_info['chassis_number'] ?? NULL;
        //$additional_info['registration_number'] = 'MH-01-BZ-7634';
        $registration_no = $additional_info['registration_number'] ?? NULL;
        //$registration_no = 'MH-15-HU-7708';
        //check data in db
       
        $policy_details = UserProposal::join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            //->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            //->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
            ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->when($filterDataWithChassis , function($query) use ($chassis_number){
                return $query->where('user_proposal.chassis_number','=', $chassis_number);
            }, function($query) use ($registration_no){
                return $query->where('user_proposal.vehicale_registration_number','=', $registration_no);
            })
            //->where('s.stage','=',STAGE_NAMES['POLICY_ISSUED'])
            ->select('user_proposal.user_proposal_id','user_proposal.user_product_journey_id','user_proposal.policy_start_date','user_proposal.policy_end_date','user_proposal.chassis_number','user_proposal.chassis_number','user_proposal.vehicale_registration_number',
                    's.stage','s.proposal_url','s.quote_url')
            ->orderBy('user_proposal.user_product_journey_id', 'DESC')
            ->first();
//           dd($policy_details);
        //$policy_details = false;
        if($policy_details)
        {
            if(in_array($policy_details->stage, [ STAGE_NAMES['POLICY_ISSUED'],STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]))
            {
                $data = $commonController->checkValidPolicyViaStartDate($policy_details);
                if(!$data['status'])
                {
                    //return $data;                    
                    $return_url = config('ERROR_PAGE_FRONTEND_URL'). base64_encode(json_encode(['msg' => $data['msg']]));
                    return redirect($return_url);
                }
            }
            else if(in_array($policy_details->stage, [ STAGE_NAMES['LEAD_GENERATION'],STAGE_NAMES['QUOTE']]))
            {
                $return_url = $policy_details->quote_url;
                return redirect($return_url);
            }
            else
            {
                $return_url = $policy_details->proposal_url;
                return redirect($return_url);
            }
        }
        //Create New Journey
        $UserProductJourney = UserProductJourney::create([
            'product_sub_type_id'   => $product_sub_type_id,
            'user_fname'            => $user_details['first_name'].' '.$user_details['last_name'],
            'user_lname'            => NULL,
            'user_email'            => $user_details['email_id'],
            'user_mobile'           => $user_details['seller_username'],
            'lead_source'           => $user_details['source'],
            'lead_stage_id'         => 2
        ]);
        $enquiry_id = customEncrypt($UserProductJourney->user_product_journey_id);
        QuoteLog::create([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id
        ]);
        CorporateVehiclesQuotesRequest::create([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id,
            'vehicle_registration_no'   => $registration_no
        ]);
        JourneyStage::create([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id,
            'stage'                     => STAGE_NAMES['LEAD_GENERATION']
        ]);
        UserProposal::create([
            'user_product_journey_id'       => $UserProductJourney->user_product_journey_id,
            'vehicale_registration_number'  => $registration_no,
            'chassis_number'                => $chassis_number
        ]);
        SelectedAddons::create([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id
        ]);
        self::saveRenewalTokenAgent($user_details, $UserProductJourney->user_product_journey_id, $request->token);
        $frontend_url = config('constants.motorConstant.' . strtoupper($segment) . '_FRONTEND_URL');
        //$frontend_url = 'https://car-bike-dev.fynity.in/car';
        $query_parameters = [
            'enquiry_id'        => $enquiry_id,
            'token'             => $request->token,
            'registration_no'   => $registration_no,
            'source'            => $user_details['source']        
        ];
        if(empty($registration_no))
        {
            unset($query_parameters['registration_no']);
        }
        updateJourneyStage([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id,
            'stage'                     => STAGE_NAMES['LEAD_GENERATION'],
            'proposal_url'              => $frontend_url . "/proposal-page?enquiry_id=" . $enquiry_id,
            'quote_url'                 => $frontend_url . "/quotes?enquiry_id=" . $enquiry_id,
        ]);
        //dd($additional_info);
        if($additional_info['vehicle_type'] == 'RENEW' && $registration_no != NULL)
        {
            $payload = [
                'enquiryId'         => $enquiry_id,
                'registration_no'   => getRegisterNumberWithHyphen(str_replace("-", "", $registration_no)),
                'productSubType'    => $product_sub_type_id,//$sub_product_type[$request->product_type],
                'section'           => strtolower($segment),
                'is_renewal'        => 'Y',
                'vendor_rc'         => $registration_no
            ];
            $getVehicleDetails = $commonController->getVehicleDetails(request()->replace($payload));
            $getVehicleDetails = is_array($getVehicleDetails) ? $getVehicleDetails : json_decode($getVehicleDetails->content(), TRUE);
            if(isset($getVehicleDetails['data']['status']) && $getVehicleDetails['data']['status'] == 100)
            {
                UserProductJourney::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
                ->update([
                    'lead_stage_id' 		=> 2,
                    'product_sub_type_id' 	=> $getVehicleDetails['data']['additional_details']['productSubTypeId']
                ]);
                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
                ->update([
                    'product_id' => $getVehicleDetails['data']['additional_details']['productSubTypeId']
                ]);

                $return_url = $quote_url = $frontend_url.'/quotes?'.http_build_query($query_parameters);
                $proposal_url = $frontend_url.'/proposal-page?'.http_build_query($query_parameters);
                updateJourneyStage([
                    'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                    'stage'         => STAGE_NAMES['QUOTE'],
                    'proposal_url'  => $proposal_url,
                    'quote_url'     => $quote_url
                ]);
                return redirect($quote_url);
            }
        }

        $vehicle_register_date = NULL;
        $manufacture_year_month = NULL;
        $manufacture_year = NULL;
        $manfacture_id = NULL;
        $manfacture_name = NULL;
        $model = NULL;
        $model_name = NULL;
        $version_name = NULL;
        $version_id = NULL;
        if(!empty($additional_info['registration_date']))
        {
           $vehicle_register_date = date("d-m-Y", strtotime($additional_info['registration_date']));
           $manufacture_year_month = explode('-',$vehicle_register_date);
           $manufacture_year = $manufacture_year_month[1].'-'.$manufacture_year_month[2];
        }
        $previousPolicyExpiryDate = '';
        if(!empty($additional_info['existing_policy_expiry_date']))
        {
           $previousPolicyExpiryDate = date("d-m-Y", strtotime($additional_info['existing_policy_expiry_date'])); 
        }
        
        if($additional_info['vehicle_type'] == 'NEW')
        {
            $business_type = 'newbusiness';
            $policy_type = 'comprehensive';
            $vehicleRegistrationNo = !empty($additional_info['registration_number']) ? $additional_info['registration_number'] : 'NEW';
            $applicable_ncb = 0;
            $previous_ncb = 0;
            $journeyWithoutRegno = 0;
            $previousPolicyExpiryDate = 'New';
            $previousInsurer = 'NEW';
            $previousInsurerCode = 'NEW';
            $previousPolicyType  = 'NEW';
        }
        else
        {
            $business_type = 'rollover';
            $policy_type = 'comprehensive';
            $vehicleRegistrationNo = $additional_info['registration_number'];
            $applicable_ncb = 0;
            $previous_ncb = 0;
            $journeyWithoutRegno = 0;
            //$previousPolicyExpiryDate = $previousPolicyExpiryDate;//$additional_info['existing_policy_expiry_date'] !== NULL ? date("d-m-Y", strtotime($additional_info['existing_policy_expiry_date'])) : NULL;
            $previousInsurer = NULL;
            $previousInsurerCode = NULL;
            $previousPolicyType  = 'Comprehensive';
        }   
        // if(!empty($additional_info['variant_id']))
        // {
        //     $variant_id = $additional_info['variant_id'];
        //     $MmvController  = new MmvController();
        //     $variant_data = $MmvController->getVersionDetails($product_sub_type_id,$variant_id);
        //     $mmv_details = get_fyntune_mmv_details($product_sub_type_id,$variant_data['data']['version_id']);

        //     $version_id = $mmv_details['data']['version']['version_id'] ?? NULL;
        //     $manfacture_id = $mmv_details['data']['manufacturer']['manf_id'] ?? NULL;
        //     $manfacture_name = $mmv_details['data']['manufacturer']['manf_name'] ?? NULL;
        //     $model = $mmv_details['data']['version']['model_id'] ?? NULL;
        //     $model_name = $mmv_details['data']['model']['model_name'] ?? NULL;
        //     $version_name = $mmv_details['data']['version']['version_name'] ?? NULL;
        //     $fuel_type = $mmv_details['data']['version']['fuel_type'] ?? NULL;
        // }        
        
        $rto_code = $additional_info['rto_number'] ?? NULL;
        $vehicle_owner_type = 'I';//$additional_info['owner_type'] == 'IND' ? 'I' : 'C';        
        $rto_name = MasterRto::where('rto_code', $rto_code)->value('rto_name');
        $claim_status = $additional_info['claim_status'] ?? 'N';
        
        // dd($mmv_details);
        $corporate_vehicles_quote_request = [
            'version_id'                    =>  $version_id ?? NULL,
            'product_id'                    =>  $product_sub_type_id,
            'policy_type'                   =>  $policy_type,
            'business_type'                 =>  $business_type,
            'vehicle_registration_no'       =>  $vehicleRegistrationNo,
            'vehicle_register_date'         =>  $vehicle_register_date,
            'previous_policy_expiry_date'   =>  $previousPolicyExpiryDate,
            'previous_policy_type'          =>  $previousPolicyType,
            'previous_insurer'              =>  $previousInsurer,
            'previous_insurer_code'         =>  $previousInsurerCode,
            'fuel_type'                     =>  $fuel_type ?? NULL,
            'manufacture_year'              =>  $manufacture_year,
            'rto_code'                      =>  $rto_code,
            'rto_city'                      =>  $rto_name,
            'vehicle_owner_type'            =>  $vehicle_owner_type,
            'journey_without_regno'         =>  (strtoupper($vehicleRegistrationNo) == 'NEW' ||  $vehicleRegistrationNo == null ) ? 'Y' : 'N',
            'is_claim'                      =>  $claim_status,
            'is_popup_shown'                =>  'N',
            'is_idv_changed'                =>  'N',
            'ownership_changed'             =>  'N',
            'is_ncb_verified'               =>  'N'
        ];
        //dd($corporate_vehicles_quote_request);
        CorporateVehiclesQuotesRequest::updateOrCreate(
            ['user_product_journey_id' => $UserProductJourney->user_product_journey_id],
            $corporate_vehicles_quote_request
        );
        
        $quoteDataJson = $corporate_vehicles_quote_request;
        $quoteDataJson['product_sub_type_id'] = $product_sub_type_id;
        $quoteDataJson['manfacture_id'] = $manfacture_id;
        $quoteDataJson['manfacture_name'] = $manfacture_name;
        $quoteDataJson['model'] = $model;
        $quoteDataJson['model_name'] = $model_name;
        $quoteDataJson['version_name'] = $version_name;
        $QuoteLogData['quote_data'] = json_encode($quoteDataJson);
        QuoteLog::updateOrCreate(['user_product_journey_id' => $UserProductJourney->user_product_journey_id], $QuoteLogData);
        
        //IF journey is Rollover and Reg. Number is blank
        if($additional_info['vehicle_type'] !== 'NEW' && $vehicleRegistrationNo == NULL)
        { 
            //Lead page
            $redirect_page = '/registration?';
            $stage = STAGE_NAMES['LEAD_GENERATION'];
        }
        else if($version_id == NULL)
        {
            //Lead page
            $redirect_page = in_array($product_sub_type_id,[1,2]) ? '/vehicle-details?' : '/vehicle-type?';
            $stage = STAGE_NAMES['LEAD_GENERATION'];
        }
        else if($rto_code == NULL || $product_sub_type_id == NULL)
        {
            //Lead page
            $redirect_page = '/registration?';
            $stage = STAGE_NAMES['LEAD_GENERATION'];
        }
        else
        {
            //Quote page
            $redirect_page = '/quotes?';
            $stage = STAGE_NAMES['QUOTE'];
        }
        $proposal_url = $quote_url = $return_url = $frontend_url . $redirect_page . http_build_query($query_parameters);        
        updateJourneyStage([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id,
            'stage'                     => $stage,
            'proposal_url'              => $proposal_url,
            'quote_url'                 => $quote_url
        ]);
//        echo $return_url;
//        die;
        return redirect($return_url);
    }

    public static function saveRenewalTokenAgent($tokenData, $id, $token)
    {
        if (empty($tokenData) || empty($id)) return;

        CvAgentMapping::updateOrCreate([ 'user_product_journey_id' => $id ], [
            'seller_type'   => $tokenData['seller_type'] ?? null,
            'agent_id'      => $tokenData['seller_id'] ?? null,
            'user_name'     => isset($tokenData['user_name']) ? $tokenData['user_name'] : null,
            'agent_name'    => $tokenData['seller_name'] ?? null,
            'agent_mobile'  => $tokenData['mobile'] ?? null,
            'agent_email'   => $tokenData['email'] ?? null,
            'unique_number' => $tokenData['unique_number'] ?? null,
            'aadhar_no'     => $tokenData['aadhar_no'] ?? null,
            'pan_no'        => $tokenData['pan_no'] ?? null,
            'stage'         => "quote",
            "category"      => $tokenData['category'] ?? null,
            "relation_sbi" => $tokenData['relation_sbi'] ?? null,
            "relation_tata_aig" => (isset($tokenData['relation_tata_aig']) ? ($tokenData['relation_tata_aig'] ?? null) : ''),
            'token'=> $token,
            'branch_code'=> (isset($tokenData['branch_code']) ? ($tokenData['branch_code'] ?? null) : ''),
            'user_id'=> (isset($tokenData['user_id']) ? ($tokenData['user_id'] ?? null) : ''),
            'pos_key_account_manager' => $tokenData['pos_key_account_manager'] ?? null,
            "agent_business_type" => $tokenData['business_type'] ?? null,
            "agent_business_code" => $tokenData['business_code'] ?? null,
        ]);

        /* In Seller Type U Agent Id is User Id */

        // if (isset($token_resp['seller_type']) && $token_resp['seller_type'] === "U") {
        //     CvAgentMapping::updateOrCreate(
        //         [
        //             'user_product_journey_id' => $id,
        //         ],
        //         [
        //             'user_id' => $tokenData['agent_id'] ?? $tokenData['user_id']
        //         ]
        //     );
        // }

    }

    public function qrLeadGeneration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'segment' => ['required', Rule::in(['CAR', 'BIKE','CV'])],
            'source' => ['required']
            // 'token' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $pos_code = $request->pos_code;

        $cUrl = config('DASHBOARD_VALIDATE_USER');

        $pos_request_data = [
            'pos' => [
                'seller_code' => [$pos_code],
            ],
        ];
        $response = Curl::to($cUrl)
        ->withData(json_encode($pos_request_data))
        ->post();
        
        $response_decoded = json_decode($response);
        $pos_data = (array) $response_decoded->pos->bajaj->data;

        $get_leads_data =  $this->getleads($request);

        $enquiry_id     =  $get_leads_data['new_user_product_journey_id']; 
        
        CvAgentMapping::updateOrCreate([ 'user_product_journey_id' => $enquiry_id ],[
            'seller_type'   => $pos_data['seller_type'] ?? null,
            'agent_id'      => $pos_data['seller_id'] ?? null,
            'user_name'     => isset($pos_data['user_name']) ? $pos_data['user_name'] : null,
            'agent_name'    => $pos_data['seller_name'] ?? null,
            'agent_mobile'  => $pos_data['mobile'] ?? null,
            'agent_email'   => $pos_data['email'] ?? null,
            'aadhar_no'     => $pos_data['aadhar_no'] ?? null,
            'pan_no'        => strtoupper($pos_data['pan_no']) ?? null,
            'stage'         => "quote",
            'branch_name'=> (isset($pos_data['branch_name']) ? ($pos_data['branch_name'] ?? null) : ''),
            "region_name" => $pos_data['region_name'] ?? null,
            "zone_name" => $pos_data['zone_name'] ?? null,
            "channel_name" => $pos_data['channel'] ?? null,
        ]);

        return $get_leads_data;
    }


    public function hiblleadgeneration(Request $request)
    {
        $main_payload = $request->all();
        $log_id = [];
        $log_id[] = $log_inserted_id = LeadGenerationLogs::insertGetId(
            [
                'request'       => json_encode($request->all()),
                'method'        => 'payload received',
                'step'          => 1,
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ]
        );

        //Skipping and creating new enquiry id every time #34384
        $newEnquiryIdGeneration = config('NEW_ENQUIRY_ID_GENERATION_ENABLE', 'Y');
        $request->merge(['createNewEnquiryId' => false]);
        
        if($newEnquiryIdGeneration == 'Y'){
           $request['createNewEnquiryId'] = true;
        }

        $is_new_vehcile = strtoupper($request->reg_no) == 'NEW' ? true : false;
        $journey_without_regno = empty($request->reg_no) ? true : false;
        if(!empty($request->traceId))
        {
            if(is_numeric($request->traceId) && strlen($request->traceId) == 16)
            {
                $request_enquiryId = ltrim(Str::substr($request->traceId, 8), 0);
            }
            else if (ctype_alnum($request->traceId) && !is_numeric($request->traceId))
            {
                $request_enquiryId = customDecrypt($request->traceId,true);
            }
            else
            {
                return response()->json([
                    "status" => false,
                    "message" => "Invalid Trace id ".$request->traceId
                ]);
            }

            $JourneyStageData = JourneyStage::where('user_product_journey_id', $request_enquiryId)
                                ->get(['stage','quote_url','proposal_url'])->first();
            if (empty($JourneyStageData)) 
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Data Not Found...!',
                ]);
            }
            else
            {
                if(in_array($JourneyStageData->stage,[ STAGE_NAMES['LEAD_GENERATION'],STAGE_NAMES['QUOTE']]))
                {
                    $return_url = $JourneyStageData->quote_url;
                }
                else
                {
                    $return_url = $JourneyStageData->proposal_url;
                }

                $return_data = [
                    "status"            =>  true,
                    "redirection_url"   =>  $return_url,
                    "stage"             =>  $JourneyStageData->stage,
                    "stage_code"        =>  STAGE_CODE[array_flip(STAGE_NAMES)[$JourneyStageData->stage]],
                    "traceId"           =>  $request->traceId,
                    "method"            =>  "traceid" 
                ];
                return response()->json($return_data);
            }
        }
        //convert reg_no with hyper before validate
        if(!empty($request->reg_no) && !preg_match('/-/', $request->reg_no) && !$is_new_vehcile)
        {
            $request->merge([
                'reg_no' => str_replace('--', '-', getRegisterNumberWithHyphen($request->reg_no)),
            ]);
        }

        $regNo = strtoupper($request->input('reg_no'));
        $validator = Validator::make($request->all(), [
            'segment' => ['required', Rule::in(['CAR', 'BIKE','CV'])],
            'source' => ['required'],
            //'reg_no' => ['nullable','regex:/\b(\d{2}-[A-Z]{2}-\d{4})|([A-Z]{2}-\d{2}-\d{4})|([A-Z]{2}-\d{1,2}-[A-Z]{1,3}-\d{1,4})|([A-Z]{2}-\d{2}-\w{1}-\d{4})\b/']
        ]);

        if (!is_null($regNo) && $regNo !== 'NEW')
        {
            $rules['reg_no'] = [
                'nullable',
                'regex:/\b(\d{2}-[A-Z]{2}-\d{4})|([A-Z]{2}-\d{2}-\d{4})|([A-Z]{2}-\d{1,2}-[A-Z]{1,3}-\d{1,4})|([A-Z]{2}-\d{2}-\w{1}-\d{4})\b/'
            ];
        }
        else
        {
            $rules['reg_no'] = ['nullable']; // No regex
        }

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        //$dasboard_get_seller_details_api = 'https://uatdashboard.heroinsurance.com//api/getSellerData';
        $dasboard_get_seller_details_api = config('DASHBOARD.BASE_URL').config('DASHBOARD.GET_SELLER_DETAILS');
        $pos_request_data = [         
            'seller_type' => $request['seller_details']['seller_type'],
            'seller_username' => $request['seller_details']['seller_username'],
        ];
        $response = Curl::to($dasboard_get_seller_details_api)
            ->withData(json_encode($pos_request_data)) 
            ->post();

        $log_id[] = $pos_inserted_id = LeadGenerationLogs::insertGetId(
            [
                'request'       => json_encode($pos_request_data),
                'response'      => ($response),
                'method'        => 'get seller details',
                'step'          => 2,
                'url'           => $dasboard_get_seller_details_api,
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ]
        );
        $response_decoded = json_decode($response,true);
        $pos_data = isset($response_decoded['status']) && $response_decoded['status'] == 'true' ? $response_decoded['data'] : [];
        //User Create
        $segment             =   strtoupper($request->segment);
        if (config('constants.motorConstant.IS_USER_ENABLED') == "Y" && config('constants.motorConstant.BROKER_USER_CREATION_API') != "") 
        {        
            $user_fname      =   $request->first_name?? NULL;
            $user_lname      =   $request->last_name ?? NULL;
            $user_email      =   $request->email_id ?? NULL;
            $user_mobile     =   $request->mobile_no ?? NULL;
            $registration_no =   $request->registration_number ?? NULL;
            $source          =   $request->source;
            $segment         =   strtoupper($request->segment);
            $old_journey_id  =   NULL;

            $user_creation_data = [
                'first_name'    => $user_fname,
                'last_name'     => $user_lname,
                'mobile_no'     => $user_mobile,
                'email'         => $user_email,                 
            ];
            $user_creation_url = config('constants.motorConstant.BROKER_USER_CREATION_API');
            if(config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true')
            {
                $user_data = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'),$user_creation_data)->json();
            } 
            else 
            {
                $user_data = HTTP::withoutVerifying()->asForm()->withOptions([ 'proxy' => config('constants.http_proxy') ])->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'),$user_creation_data)->json();
            }
            $user_creation_data_insert = [
                'mobile_no'         => $user_creation_data['mobile_no'],
                'request'           => json_encode($user_creation_data),
                'response'          => is_array($user_data) ? json_encode($user_data) : $user_data,
                'url'               => $user_creation_url,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ];
            DB::table('user_creation_request_response')->insert($user_creation_data_insert);
            $user_creation_data_insert = [
                'request'       => json_encode($user_creation_data),
                'response'      => is_array($user_data) ? json_encode($user_data) : $user_data,
                'method'        => 'user creation',
                'step'          => 3,
                'url'           => $user_creation_url,
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ];
            $log_id[] = $user_inserted_id = LeadGenerationLogs::insertGetId($user_creation_data_insert); 
            if(!empty($user_data['user_id']))
            {
                $user = \App\Models\Users::updateorCreate(
                    ['mobile_no' => $user_mobile],
                    [
                        'first_name' => $user_fname,
                        'last_name'  => $user_lname,
                        'mobile_no'  => $user_mobile,
                        'email'      => $user_email,
                        'user_id'    => $user_data['user_id']
                    ]
                );
            }
        }
        $get_leads_data =  $this->getleads($request);
        $enquiry_id     =  $get_leads_data['new_user_product_journey_id'];
        if (config('MAP_LEAD_SOURCE') == 'Y' && !empty($request->reg_no) && !empty($enquiry_id)) {
            $days = config('NO_OF_DAYS_FOR_LEAD_SOURCE', 7);
            $leadSourceUpdated = UtilityApi::leadSoruceMapping($request->reg_no, $days, $enquiry_id);
            if ($leadSourceUpdated['status']) {
                UserProductJourney::where('user_product_journey_id', $enquiry_id)
                    ->update([
                        'lead_source' => $leadSourceUpdated['lead_source'] ?? NULL
                    ]);
            }
        }
        $seller_type = 'b2c';
        if(!empty($pos_data['seller_type']))
        {
            $seller_type = $pos_data['seller_type']; 
        }
        else if(!empty($user_data['user_id']))
        {
            $seller_type = 'U';
        }
        CvAgentMapping::updateOrCreate([ 'user_product_journey_id' => $enquiry_id ],[
            'seller_type'       => $seller_type,
            'agent_id'          => $pos_data['seller_id'] ?? null,
            'user_name'         => isset($pos_data['user_name']) ? $pos_data['user_name'] : null,
            'agent_name'        => $pos_data['seller_name'] ?? null,
            'agent_mobile'      => $pos_data['mobile'] ?? null,
            'agent_email'       => $pos_data['email'] ?? null,
            'aadhar_no'         => $pos_data['aadhar_no'] ?? null,
            'pan_no'            => isset($pos_data['pan_no']) ? strtoupper($pos_data['pan_no']) : null,
            'stage'             => "quote",
            'branch_name'       => (isset($pos_data['branch_name']) ? ($pos_data['branch_name'] ?? null) : ''),
            "region_name"       => $pos_data['region_name'] ?? null,
            "zone_name"         => $pos_data['zone_name'] ?? null,
            "channel_name"      => $pos_data['channel'] ?? null,
            "user_id"           => $user_data['user_id'] ?? null
        ]);
        $final_insert = [
            'request'       => json_encode($main_payload),
            'response'      => is_array($get_leads_data) ? json_encode($get_leads_data) : $get_leads_data,
            'method'        => 'response sent',
            'step'          => 4,
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ];
        $log_id[] = $user_inserted_id = LeadGenerationLogs::insertGetId($final_insert);
        LeadGenerationLogs::whereIn('id', $log_id)->update(['enquiry_id' => $enquiry_id]);
        if (config('MAP_LEAD_SOURCE') == 'Y') {
            $days = config('NO_OF_DAYS_FOR_LEAD_SOURCE', 7);
            $utmSourceUpdated = UtilityApi::leadSoruceMapping($request->reg_no, $days, $enquiry_id);

            if(!empty($utmSourceUpdated['utm_source'])){
                $main_payload['utm']['utm_source'] = $utmSourceUpdated['utm_source']['broker_utm_source'] ?? null;
                $main_payload['utm']['utm_media'] = $utmSourceUpdated['utm_source']['broker_utm_media'] ?? null;
                $main_payload['utm']['utm_campaign'] = $utmSourceUpdated['utm_source']['broker_utm_campaign'] ?? null;
                LeadGenerationLogs::where(['enquiry_id' => $enquiry_id])->update(['request' => json_encode($main_payload)]);
            }
        }
        if(!$get_leads_data['status'])
        {
            if(isset($get_leads_data['redirection_url']))
            {
                unset($get_leads_data['redirection_url']);
            }
        }

        if($get_leads_data['status'])
        {
            $get_leads_data['stage']   = JourneyStage::where('user_product_journey_id', $enquiry_id)->pluck('stage')->first();
            $get_leads_data['stage_code']= STAGE_CODE[array_flip(STAGE_NAMES)[$get_leads_data['stage']]];
            $get_leads_data['traceId'] = getDecryptedEnquiryId(customEncrypt($enquiry_id));
        }
        if(config('enquiry_id_encryption') == 'Y')
        {
            $get_leads_data['traceId_encrypted'] = customEncrypt($enquiry_id);
        }
        if(isset($get_leads_data['new_user_product_journey_id']))
        {
            unset($get_leads_data['new_user_product_journey_id']);
        }
        $get_leads_data['method'] = 'fresh';
        if($is_new_vehcile)
        {
            $corporate_data = [
                'business_type'           => 'newbusiness',
                'policy_type'             => 'comprehensive',
                'journey_without_regno'   => 'Y',
                'vehicle_registration_no' => 'NEW'
                //
            ];
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)->update($corporate_data);
            if(isset($get_leads_data['redirection_url']))
            {
                $get_leads_data['redirection_url'] = ($segment == 'CV') ? str_replace('/registration', '/vehicle-type', $get_leads_data['redirection_url']) : str_replace('/registration', '/vehicle-details', $get_leads_data['redirection_url']);
                JourneyStage::where('user_product_journey_id', $enquiry_id)->update([
                    'quote_url' => $get_leads_data['redirection_url']
                ]);
            }
        }
        else if($journey_without_regno)
        {
            $corporate_data = [
                'journey_without_regno' => 'Y'
            ];
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)->update($corporate_data);
            if(isset($get_leads_data['redirection_url']))
            {
                $get_leads_data['redirection_url'] = ($segment == 'CV') ? str_replace('/registration', '/vehicle-type', $get_leads_data['redirection_url']) : str_replace('/registration', '/vehicle-details', $get_leads_data['redirection_url']);
                JourneyStage::where('user_product_journey_id', $enquiry_id)->update([
                    'quote_url' => $get_leads_data['redirection_url']
                ]);
            }
        }
        $get_leads_data['resume_journey'] = NULL;
        if(!empty($get_leads_data['redirection_url']))
        {
            $get_leads_data['resume_journey'] = preg_replace('/(car|bike|cv)\/(registration|vehicle-type|vehicle-details|quotes|proposal-page)/', 'resume-journey', $get_leads_data['redirection_url']);
        }
        return $get_leads_data;
    }
    public function leadGeneration(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'vehicle_segment' => ['required', Rule::in(['car', 'bike','cv'])],
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }
        
        $fullName        =   explode(" ", $request->name) ?? NULL;
        $user_fname      =   implode(" ", array_slice($fullName, 0 , -1)) ?? NULL;
        $user_lname      =   end($fullName) ?? NULL;
        $user_email      =   $request->email ?? NULL;
        $user_mobile     =   $request->mobile ?? NULL;
        $registration_no =   $request->registration_number ?? NULL;
        $source          =   "BCL";
        $segment         =   strtoupper($request->vehicle_segment);
        $old_journey_id  =   NULL;
        $request_array   =   $request->all();

        // Customer Creation
        if (config('constants.motorConstant.IS_USER_ENABLED') == "Y" && $user_mobile != null) 
        {
            $user = \App\Models\Users::updateorCreate(
                ['mobile_no' => $user_mobile],
                [
                    'mobile_no'  => $user_mobile,
                    'email'      => $user_email,
                    'first_name' => $user_fname,
                    'last_name'  => $user_lname,
                ]
            );

            $user_creation_url = config('constants.motorConstant.BROKER_USER_CREATION_API');
            $user_creation_data = [
                'first_name' => $user_fname,
                'last_name'  => $user_lname,
                'mobile_no'  => $user_mobile,
                'email'      => $user_email                    
            ];
            if(config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true')
            {
                $user_data = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'),$user_creation_data)->json();
            } 
            else 
            {
                $user_data = HTTP::withoutVerifying()->asForm()->withOptions([ 'proxy' => config('constants.http_proxy') ])->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'),$user_creation_data)->json();
            }
            
            $insert_vahan_data = [
                'mobile_no'         => $user_creation_data['mobile_no'],
                'request'           => json_encode($user_creation_data),
                'response'          => is_array($user_data) ? json_encode($user_data) : $user_data,
                'url'               => $user_creation_url,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ];            
            DB::table('user_creation_request_response')->insert($insert_vahan_data);   
            
            $error_msg = '';
            if($user_data['status'] == 'false')
            {
                foreach ($user_data['data'] as $key => $value) {
                    $key = str_replace('_', ' ', $key);
                    $error_msg .= ucwords($key ." : ".$value."\n");
                }

                return response()->json([
                    'status' => false,
                    'msg'    => $error_msg,
                ]);
            } 
            else
            {
                $user->update(['user_id' => $user_data['user_id']]);
                CvAgentMapping::updateorCreate([
                    'user_id' => $user_data['user_id'],
                ]);
            }
        }

        // if(!empty($registration_no))
        // {
        //     // fetch policy data by either regestration number or policy number
        //     $policy_details = UserProposal::join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        //     ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        //     ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        //     ->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
        //     ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        //     ->when(strlen($registration_no) > 6 , function($query, $registration_no){
        //         return $query->where('user_proposal.vehicale_registration_number','=', $registration_no);                
        //     }, function($query, $policy_no){
        //         return $query->where('pd.policy_number','=', $policy_no);
        //     })->where('s.stage','=',STAGE_NAMES['POLICY_ISSUED'])
        //     ->select('upj.*','user_proposal.*','pd.policy_number','pd.created_on as transaction_date')
        //     ->orderBy('pd.created_on', 'DESC')
        //     ->first();

        //     if($policy_details)
        //     {
        //         $user_fname     =   $policy_details->first_name;
        //         $user_lname     =   $policy_details->last_name;
        //         $user_email     =   $policy_details->email;
        //         $user_mobile    =   $policy_details->mobile_number;
        //         $old_journey_id =   $policy_details->user_product_journey_id;
            
        //         $renewal_journey = UserProductJourney::where('old_journey_id', $policy_details->user_product_journey_id)
        //             ->orderBy('user_product_journey_id', 'desc')
        //             ->get()
        //             ->first();
            
        //         if(!empty($renewal_journey) && $renewal_journey->status !== 'Inactive')
        //         {
        //             $renewal_product_journey_id = $renewal_journey->user_product_journey_id;
                
        //             $JourneyStage_data = JourneyStage::where('user_product_journey_id', $renewal_product_journey_id)->first();
                
        //             if (in_array($JourneyStage_data->stage,[ STAGE_NAMES['LEAD_GENERATION'],STAGE_NAMES['QUOTE']]))
        //             {
        //                 $return_url = $JourneyStage_data->quote_url;
        //             } 
        //             else 
        //             {
        //                 $return_url = $JourneyStage_data->proposal_url;
        //             }
                
        //             $return_data = [
        //                 'status'    => true,
        //                 'redirection_url' => $return_url,
        //                 'new_user_product_journey_id' => $renewal_product_journey_id
        //             ];

        //             LeadGenerationLogs::create([
        //                 'enquiry_id' => customDecrypt($renewal_product_journey_id),
        //                 'request'    => json_encode($request_array, JSON_PRETTY_PRINT),
        //                 'response'   => json_encode($return_data, JSON_PRETTY_PRINT),
        //             ]);
                
        //             if(isset($request_array['redirection']) && $request_array['redirection'] == 'N')
        //             {
        //                 return $return_data;
        //             }
                
        //             return redirect($return_url);
        //         }
        //     }
        // }
        
        $sub_product_type = [
            'CAR'   => 1,
            'BIKE'  => 2
        ];

        if($segment == 'CV')
        {
           $product_sub_type_id = $policy_details->product_sub_type_id ?? NULL;
        }
        else
        {
            $product_sub_type_id = $sub_product_type[$segment];
        }

        if(!empty($renewal_journey))
        {
            $UserProductJourney = $renewal_journey;
        }
        else
        {
            $UserProductJourney = UserProductJourney::create([
                'product_sub_type_id'   => $product_sub_type_id,
                'user_fname'            => $user_fname,
                'user_lname'            => $user_lname,
                'user_email'            => $user_email,
                'user_mobile'           => $user_mobile,
                'lead_source'           => $source,
                'lead_stage_id'         => 2,
                'old_journey_id'        => $old_journey_id
            ]);
        }

        $enquiry_id = customEncrypt($UserProductJourney->user_product_journey_id);

        $query_parameters = [
            'enquiry_id'        => $enquiry_id,
            'registration_no'   => $registration_no,
            'source'            => $source,
        ];

        $frontend_url = config('constants.motorConstant.' . $segment . '_FRONTEND_URL');

        updateJourneyStage([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id,
            'stage'                     => STAGE_NAMES['LEAD_GENERATION'],
            'proposal_url'              => $frontend_url . "/proposal-page?enquiry_id=" . $enquiry_id,
            'quote_url'                 => $frontend_url . "/quotes?enquiry_id=" . $enquiry_id,
        ]);

        //Lead page
        $redirect_page = '/registration?';
        $return_url = $frontend_url . $redirect_page . http_build_query($query_parameters);

        if($registration_no != NULL)
        {
            //Lead page - MMV
            $redirect_page = in_array($product_sub_type_id,[1,2]) ? '/vehicle-details?' : '/vehicle-type?';
            $return_url = $frontend_url . $redirect_page . http_build_query($query_parameters);
        }
        
        $quoteDataJson['vehicle_registration_no'] = $corporateVehiclesQuotesRequestData['vehicle_registration_no'] = $registration_no;
        $quoteDataJson['remove_header_footer'] = $corporateVehiclesQuotesRequestData['remove_header_footer'] = $request->remove_header_footer;
        CorporateVehiclesQuotesRequest::updateOrCreate(['user_product_journey_id' => $UserProductJourney->user_product_journey_id], $corporateVehiclesQuotesRequestData);

        $QuoteLogData['quote_data'] = json_encode($quoteDataJson);
        QuoteLog::updateOrCreate(['user_product_journey_id' => $UserProductJourney->user_product_journey_id], $QuoteLogData);

        $payload = [
            'enquiryId'         => $enquiry_id,
            'registration_no'   => 'NULL',
            'productSubType'    => $product_sub_type_id,
            'section'           => strtolower($segment),
            'is_renewal'        => 'Y',
        ];
        
        if(strtoupper($registration_no) == 'NEW')
        {
            $registration_no = '';
        }

        if (!empty($registration_no)) 
        {
            $payload['registration_no'] = getRegisterNumberWithHyphen(str_replace("-", "", $registration_no));
            $payload['vendor_rc']       = $registration_no;
        }

        // Vahan call
        $common = new CommonController;
        $getVehicleDetails = $common->getVehicleDetails(request()->replace($payload));
        $getVehicleDetails = is_array($getVehicleDetails) ? $getVehicleDetails : json_decode($getVehicleDetails->content(), TRUE);

        if(is_array($getVehicleDetails) && $getVehicleDetails['status'] == false && isset($getVehicleDetails['msg']))
        {
            if(strpos($getVehicleDetails['msg'], 'Policy already issued with Policy Number') !== false)
            { 
                UserProductJourney::where('user_product_journey_id',$UserProductJourney->user_product_journey_id)
                ->update(['status' => 'Inactive']);
                sleep(1);
                return $getVehicleDetails;
            }
        }

        if(isset($getVehicleDetails['data']['status']) && $getVehicleDetails['data']['status'] == 100)
        {
            UserProductJourney::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
                ->update(['lead_stage_id' => 2]);
     
            $return_url = $quote_url = $frontend_url.'/quotes?'.http_build_query($query_parameters);
            $proposal_url = $frontend_url.'/proposal-page?'.http_build_query($query_parameters);
     
            updateJourneyStage([
                'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                'stage'         => STAGE_NAMES['QUOTE'],
                'proposal_url'  => $proposal_url,
                'quote_url'     => $quote_url
            ]);
        }

        $return_data = [
            'status'    => true,
            'redirection_url' => $return_url,
            'new_user_product_journey_id' => customDecrypt($enquiry_id)
        ];

        LeadGenerationLogs::create([
            'enquiry_id' => customDecrypt($enquiry_id),
            'request'    => json_encode($request_array, JSON_PRETTY_PRINT),
            'response'   => json_encode($return_data, JSON_PRETTY_PRINT),
        ]);

        if(isset($request_array['redirection']) && $request_array['redirection'] == 'N')
        {
            return $return_data;
        }
        return redirect($return_url);
    }
}
