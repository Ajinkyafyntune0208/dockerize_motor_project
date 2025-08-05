<?php

namespace App\Http\Controllers\Payment;

use App\Models\MasterPolicy;
use Illuminate\Http\Request;
use App\Models\PaymentResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Payment\Services\{
    SbiPaymentGateway,
    ackoPaymentGateway,
    goDigitPaymentGateway,
    shriramPaymentGateway,
    tataAigPaymentGateway,
    hdfcErgoPaymentGateway,
    orientalPaymentGateway,
    reliancePaymentGateway,
    iffco_tokioPaymentGateway,
    iciciLombardPaymentGateway,
    bajaj_allianzPaymentGateway,
    libertyVideoconPaymentGateway,
    magmaPaymentGateway,
    universalSompoPaymentGateway,
    royalSundaramPaymentGateway,
    tataAigV2PaymentGateway,
    chollaMandalamPaymentGateway,
    futureGeneraliPaymentGateway,
    unitedIndiaPaymentGateway,
    newIndiaPaymentGateway,
    UnitedIndiaPaymentGatewayBillDesk
};
use App\Http\Controllers\Payment\Services\Pcv\V2\GoDigitPaymentGateway as GoDigitOneapiPaymentGateway ;
use App\Http\Controllers\Payment\Services\V1\GCV\shriramPaymentGateway AS SHRIRAM_GCV;
use App\Http\Controllers\Payment\Services\V1\PCV\shriramPaymentGateway AS SHRIRAM_PCV;
use App\Http\Controllers\Payment\Services\V1\GCV\shriramPaymentGateway as GCVPaymentGateway; 
use App\Http\Controllers\Payment\Services\V1\GCV\FutureGeneraliPaymentGateway as FGPaymentGateway; 
use App\Http\Controllers\Payment\Services\V2\PCV\tataAigPaymentPcvGateway;
use App\Http\Controllers\Payment\Services\V1\HdfcErgoPaymentGateway AS  HDFC_ERGO_V1;
use App\Http\Controllers\Payment\Services\V1\ReliancePaymentGateway AS  RELIANCE_V1;
use App\Http\Controllers\OnePay\OnePayController;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use App\Models\CvAgentMapping;
use App\Http\Controllers\RenewalController;
use App\Models\PaymentRequestResponse;
use Carbon\Carbon;
use App\Http\Controllers\Extra\DateChanger;
use App\Http\Controllers\Payment\Services\V2\PCV\shriramV2PaymentGateway;


class PaymentController extends Controller
{
    public function makePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userProductJourneyId' => ['required'],
            'policyId' => ['required'],
            'companyAlias' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        //new array for update date details
        $origional_request['policyId'] = $request->policyId;
        $origional_request['enquiryId'] = $request->enquiryId;
        $origional_request['userProductJourneyId'] = $request->userProductJourneyId;
        $origional_request['segment'] = 'CV';
        //End new array for update date details

        $enquiry_id = customDecrypt($request->userProductJourneyId);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
        if(strlen($user_proposal->vehicale_registration_number) >= 8)
        {
            $data['registration_no'] = $user_proposal->vehicale_registration_number;
            $isPolicyIssued = RenewalController::getExistingPolicy($data);
            if($isPolicyIssued)
            {
                $return_data = RenewalController::validatePolicyStartDateInterval($isPolicyIssued);
                if(!$return_data['status'])
                {
                   return $return_data;
                }
            }
        }
        $CorporateVehiclesQuotesRequest = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
            ->get()
            ->first();
        
        if ($CorporateVehiclesQuotesRequest->business_type != 'newbusiness' && !empty($user_proposal->vehicale_registration_number) && checkValidRcNumber($user_proposal->vehicale_registration_number)) {
            return response()->json([
                'status' => false,
                'msg' => 'This Rc Number Blocked On Portal',
            ]);
        }
        if(config('proposalPage.isVehicleValidation') == 'Y' && $CorporateVehiclesQuotesRequest->business_type != 'newbusiness')
        {
            $isSectionMissmatched = isSectionMissmatched($request, 'cv', $user_proposal->vehicale_registration_number);
            if(!$isSectionMissmatched['status'])
            {
                return response()->json($isSectionMissmatched);
            }
        }
        if(!empty($user_proposal->policy_start_date ?? '')) {
            if(\Carbon\Carbon::parse($user_proposal->policy_start_date) < today()) {
                return response()->json([
                    'status' => false,
                    'msg' => 'This proposal is expired with insurance policy start date as ' . $user_proposal->policy_start_date .'. Please initiate a new proposal.',
                    'policyStartDate' => $user_proposal->policy_start_date
                ]);
            }
        } else if(empty($user_proposal->policy_start_date)) {
            return response()->json([
                'status' => false,
                'msg' => 'Something went wrong. Please submit the proposal form again.',
                'policyStartDate' => $user_proposal->policy_start_date
            ]);
        }
        if(config('JOURNEY_AGENT_VALIDATION') == 'Y') {
            $agentController = new \App\Http\Controllers\AgentValidateController($enquiry_id);
            $agentCheck = $agentController->agentValidation($request);
            if(!($agentCheck['status'] ?? true)) {
                return response()->json($agentCheck);
            }
        }

        // All three IC ID should be same in-order to avoid any payment mismatch in another IC : @Amit - 01-11-2022
        $master_policy = MasterPolicy::find($request->policyId); // master_policy move above as it was giving error for offlineMake check

        if ($user_proposal->is_ckyc_verified != 'Y' && !in_array($request->companyAlias, explode(',',config('ICS_ALLOWED_FOR_PAYMENT_WITHOUT_CKYC')))) {
            return response()->json([
                'status' => false,
                'message' => 'It seems your CKYC verification is not complete.'
            ]);
        }

        //if(env('APP_ENV') == 'local') {
        //Condition to be run on all environments - 09-11-2022
        if(true) {
            $quote_log = QuoteLog::where('user_product_journey_id', $enquiry_id)->first();
            
            $are_all_3_same = ($user_proposal->ic_id == $quote_log->ic_id) &&
                ($user_proposal->ic_id == $master_policy->insurance_company_id) &&
                ($quote_log->ic_id == $master_policy->insurance_company_id);
            if(!$are_all_3_same) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Proposal has been already initiated with another Insurance company.
                              Please start over fresh journey in case you want to change the insurance company.',
                    'data' => [
                        'master_policy_ic_id' => $master_policy->insurance_company_id,
                        'user_proposal_ic_id' => $user_proposal->ic_id,
                        'quote_log_ic_id' => $quote_log->ic_id
                    ],
                ]);
            }
        }
        if ($master_policy->is_payment_online == "No") {
            $method = 'offlineMake';
        }
        else{
            $method = 'make';   
        }

        if((config('constants.motorConstant.SMS_FOLDER') == "gramcover") && (config('GRAMCOVER_ENABLE_TOKEN_EXPIRY_CHECK') == "Y"))
        {
             $token_data  = GramcoverTokenExpiryCheck($enquiry_id);
             
             if($token_data['status'] == false)
             {
                $agent_id  = $token_data['agent_data']['agent_id'];

                $agent_data = CvAgentMapping::where('agent_id',$agent_id)->whereNull('source')->orderBy('id','desc')->first();

                if(!empty($agent_data))
                {
                    $request_data = [
                        "seller_type" => "P",
                        "seller_id" => $agent_data->agent_id,
                        "user_product_journey_id" =>  $enquiry_id 
                    ];
                    $response = httpRequestNormal(config('DASHBOARD_GET_AGENT_TOKEN'), 'POST', $request_data, [], [
                        'Content-Type' => 'application/json'
                    ], [], true, false);

                    $response_api = $response['response'] ?? [];

                    if(!empty($response_api))
                    {
                        $token_api = $response_api['data']['remote_token'] ?? '';
                        if(!empty($token_api))
                        {
                            $t_data = JwtTokenDecode($token_api);
                            if($t_data['status'] == true && !empty($t_data['token_data']['exp']))
                            {
                                $now = strtotime('now');
                                $t_time = $t_data['token_data']['exp'];
                                if($t_time > $now)
                                {
                                        $all_agent_id = CvAgentMapping::select('agent_id')->where('user_product_journey_id',$enquiry_id)->pluck('agent_id')->toArray();
                
                                        CvAgentMapping::whereHas('journeyStage', function ($query) {
                                            $query->whereNotIn('stage', [ STAGE_NAMES['POLICY_ISSUED']]);
                                        })->whereIn('agent_id',$all_agent_id)
                                        ->update(['token' => $token_api]);
                                }else
                                {
                                    return response()->json($token_data);
                                }
                            }else
                            {
                                return response()->json($token_data);
                            }
                        }else
                        {
                            return response()->json($token_data);
                        }

                    }else
                    {
                        return response()->json($token_data);
                    }
                }else
                {
                    return response()->json($token_data);
                }
            }
        }

        $last_payment_details =  PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                                ->latest()->first();
        if(!empty($last_payment_details))
        {
            $start = new Carbon($last_payment_details->created_at);
            $end = Carbon::now();
            $waitingTimeInSeconds = (int) config('WAIT_TIME_FOR_PAYMENT_IN_SECONDS', 300);
            $timeDifferenceInSeconds = $end->diffInSeconds($start);
            $remainingTime = $waitingTimeInSeconds - $timeDifferenceInSeconds;
            if ($remainingTime > 0)
            {
                $minutes = intdiv($remainingTime, 60);
                $seconds = $remainingTime % 60;
                $msg = 'Payment already initiated. Kindly try after ';
                $msg .= $minutes > 0 ? "$minutes minute(s) and $seconds second(s)" : "$seconds second(s)";
                return response()->json([
                    'status'                        => false,
                    'remaining_time_in_seconds'     => $remainingTime,
                    'msg'                           => $msg
                ]);
            }
        }

        switch ($request->companyAlias) {
            case 'shriram':
                if(config('IC.constant.SHRIRAM_GCV_PCV_JSON_V2_ENABLED') == 'Y'){
                    $shriramv2 = new shriramV2PaymentGateway();
                    $response = $shriramv2::make($request);
                }
                elseif(config('IC.SHRIRAM.V1.GCV.ENABLE') == 'Y' && in_array(policyProductType($request->policyId)->parent_id , [4]))
                        {
                            // $response =  SHRIRAM_GCV::makeV1Gcv($request);
                            $shriramgcvpayment = new GCVPaymentGateway();
                            $response =  $shriramgcvpayment->makeV1Gcv($request);
                           
                        }
                        elseif((config('IC.SHRIRAM.V1.PCV.ENABLE') == 'Y') && in_array(policyProductType($request->policyId)->parent_id , [8])) {
                            $response = SHRIRAM_PCV::make($request);
                        } 
                        else {
                            $response = shriramPaymentGateway::make($request);
                            
                        }
                        break; 
            case 'godigit':
                if ((config('IC.GODIGIT.V2.CV.ENABLE') == 'Y')) {
                    $response = GoDigitOneapiPaymentGateway::make($request);
                } else {
                    $response = goDigitPaymentGateway::make($request);
                }
                break;
            case 'acko':
                $response = ackoPaymentGateway::make($request);
                break;
            case 'icici_lombard':
                $response = iciciLombardPaymentGateway::$method($request);
                break;
            case 'hdfc_ergo':
                if(config('IC.HDFC_ERGO.V1.CV.ENABLED') == 'Y'){
                    $response = HDFC_ERGO_V1::make($request);
                }else{
                    $response = hdfcErgoPaymentGateway::make($request);
                }
                break;
            break;
            case 'reliance':
                if(config('IC.RELIANCE.V1.CV.ENABLE') == 'Y'){
                    $response = RELIANCE_V1::make($request);
                }else{
                    $response = reliancePaymentGateway::make($request);
                }
             break;
            case 'iffco_tokio':
                $response = iffco_tokioPaymentGateway::make($request);
             break;
            case 'oriental':
                $response = orientalPaymentGateway::make($request);
            break;
            case 'bajaj_allianz':
                $response = bajaj_allianzPaymentGateway::make($request);
            break;
            case 'sbi':
                $response = SbiPaymentGateway::make($request);
            break;
            case 'tata_aig':
                if( config('IC.TATA_AIG.V2.PCV.ENABLE') == 'Y' && in_array(policyProductType($request->policyId)->parent_id , [8])) 
                {
                    $response = tataAigPaymentPcvGateway::make($request);
                }
                else
                {
                    $response = tataAigPaymentGateway::make($request);
                }
            break;
            case 'liberty_videocon':
                $response = libertyVideoconPaymentGateway::make($request);
            break;
            case 'royal_sundaram':
                $response = royalSundaramPaymentGateway::make($request);
            break;
            case 'universal_sompo':
                $response = universalSompoPaymentGateway::make($request);
            break;
            case 'magma':
                $response = magmaPaymentGateway::make($request);
                break;
            case 'cholla_mandalam':
                $response = chollaMandalamPaymentGateway::make($request);
                break;
            case 'future_generali':
                if(config('IC.FUTURE_GENERALI.V1.GCV.ENABLED') == 'Y')
                {
                    $response = FGPaymentGateway::make($request);
                }
                else
                {
                    $response = futureGeneraliPaymentGateway::make($request);
                }
                break;
            case 'united_india':
                if(config('IC.UNITED_INDIA.CV.BILLDESK.ENABLE') == 'Y') {
                    $response =  UnitedIndiaPaymentGatewayBillDesk::make($request);
                } else {
                    $response =  unitedIndiaPaymentGateway::make($request);
                }
                break;
            case 'new_india':
                $response = newIndiaPaymentGateway::make($request);
                break;
            default:
                $response = response()->json([
                    'status' => false,
                    'msg' => 'invalid company alias name'
                ]);
        }

        if ($response)
        {
            if ((is_object($response) && isset($response->original['status']) && $response->original['status']) || (is_array($response) && isset($response['status']) && $response['status']))
            {
                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $user_product_journey = UserProductJourney::find($enquiry_id);
                    $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

                    if ($lsq_journey_id_mapping)
                    {
                        updateLsqOpportunity($enquiry_id);
                        createLsqActivity($enquiry_id);
                    }
                }
            }
        }
        
        try {
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $d = $response->original;
                if(isset($d['status']) && $d['status']) {
                    event(new \App\Events\PaymentInitiated($enquiry_id));
                }
            } else if (isset($response['status']) && $response['status']) {
                event(new \App\Events\PaymentInitiated($enquiry_id));
            }
        }catch(\Exception $e) {
            // noting to write here....
        }
        Datechanger::Datechange($origional_request);
        return $response;
    }

    public function confirm(Request $request, $ic_name)
    {
        $requestType =  app()->runningInConsole() ? 'SCHEDULER' : 'WEB';

        PaymentResponse::create([
            'company_alias' => $ic_name,
            'section' => 'cv',
            'response' => json_encode( [ 'icResponse' => $request->all(), 'mode' => $requestType ,'headers' => $request->header()] )
        ]);
        
        $method = 'confirm';
        if (isset($request->policy_id)) {
            $master_policy = MasterPolicy::find($request->policy_id);
            if ($master_policy->is_payment_online == "No") {
                $method = 'offlineConfirm';
            }
            else{
                $method = 'confirm';
            }            
        }

        addPolicyGenerationDelay($ic_name);

        switch ($ic_name) {
            case 'shriram':           
                $user_proposal = UserProposal::find($request->user_proposal_id); 
                $enquiry_id = $user_proposal['user_product_journey_id'];
                $user_proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
                $quote = QuoteLog::where('user_product_journey_id',$enquiry_id)->first();    
                $quote_data = json_decode($quote->quote_data, true);
                $productData = getProductDataByIc($quote['master_policy_id']); 
                $data =  $productData->product_sub_type_id;
                if(config('IC.constant.SHRIRAM_GCV_PCV_JSON_V2_ENABLED') == 'Y'){
                    $shriramv2 = new shriramV2PaymentGateway();
                    return $shriramv2::confirm($request);
                }
        elseif (config('IC.SHRIRAM.V1.GCV.ENABLE') == 'Y' && get_parent_code($data)== 'GCV') {        
            $shriramgcvpayment = new GCVPaymentGateway(); 
            return $shriramgcvpayment->JSONGcvConfirm($request);
        }
      
        elseif (config('IC.SHRIRAM.V1.PCV.ENABLE') == 'Y' && get_parent_code($data) == 'PCV' ) {
          
            return SHRIRAM_PCV::JSONConfirm($request);
        }
     
        else {
            return shriramPaymentGateway::confirm($request);
        }
               
                break;
            case 'godigit':
                if ((config('IC.GODIGIT.V2.CV.ENABLE') == 'Y')) {
                    return GoDigitOneapiPaymentGateway::confirm($request);
                } else {
                    return goDigitPaymentGateway::confirm($request);
                }
                break;
            case 'acko':
                return ackoPaymentGateway::confirm($request);
                break;
            case 'icici_lombard':
                return iciciLombardPaymentGateway::$method($request);
                break;
            case 'hdfc_ergo':
                if(config('IC.HDFC_ERGO.V1.CV.ENABLED') == 'Y'){
                    return HDFC_ERGO_V1::confirm($request);
                } else {
                    return hdfcErgoPaymentGateway::confirm($request);
                }
                break;
            case 'reliance':
                if(config('IC.RELIANCE.V1.CV.ENABLE') == 'Y'){
                    return RELIANCE_V1::confirm($request);
                }else{
                    return reliancePaymentGateway::confirm($request);
                }
                break;
            case 'iffco_tokio':
                return iffco_tokioPaymentGateway::confirm($request);
             break;
            case 'oriental':
                return orientalPaymentGateway::confirm($request);
                break;
            case 'bajaj_allianz':
                return bajaj_allianzPaymentGateway::confirm($request);
                break;
            case 'sbi':
                return SbiPaymentGateway::confirm($request);
                break;
            case 'tata_aig':
                return tataAigPaymentGateway::confirm($request);
                break;
            case 'tata_aig_v2':
                if( config('IC.TATA_AIG.V2.PCV.ENABLE') == 'Y' ) 
                {
                    return tataAigPaymentPcvGateway::confirm($request);
                }  
                else
                {
                    return tataAigV2PaymentGateway::confirm($request);
                }                
                break;
            case 'liberty_videocon':
                return libertyVideoconPaymentGateway::confirm($request);
            break;
            case 'royal_sundaram':
                return royalSundaramPaymentGateway::confirm($request);
            break;
            case 'universal_sompo':
                return universalSompoPaymentGateway::confirm($request);
            break;
            case 'magma':
                return magmaPaymentGateway::confirm($request);
                break;
            case 'cholla_mandalam':
                return chollaMandalamPaymentGateway::confirm($request);
                break;
            case 'future_generali':
                if(config('IC.FUTURE_GENERALI.V1.GCV.ENABLED') == 'Y')
                {
                    return  FGPaymentGateway::confirm($request);
                }

                return futureGeneraliPaymentGateway::confirm($request);
                break;
            case 'united_india':
                if(config('IC.UNITED_INDIA.CV.BILLDESK.ENABLE') == 'Y') {
                    return  UnitedIndiaPaymentGatewayBillDesk::confirm($request);
                } else {
                    return  unitedIndiaPaymentGateway::confirm($request);
                }
                break;
            case 'new_india':
                return newIndiaPaymentGateway::make($request);
                break;
            default:
                return response()->json([
                    'status' => false,
                    'msg' => 'invalid company alias name'
                ]);
        }

    }
}