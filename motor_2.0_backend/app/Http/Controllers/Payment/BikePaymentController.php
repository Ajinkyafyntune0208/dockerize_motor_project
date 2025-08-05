<?php

namespace App\Http\Controllers\Payment;

use Illuminate\Http\Request;
use App\Models\PaymentResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Payment\Services\Bike\{
    sbiPaymentGateway,
    ackoPaymentGateway,
    kotakPaymentGateway,
    magmaPaymentGateway,
    rahejaPaymentGateway,
    goDigitPaymentGateway,
    shriramPaymentGateway,
    tataAigPaymentGateway,
    hdfcErgoPaymentGateway,
    newIndiaPaymentGateway,
    reliancePaymentGateway,
    bhartiAxaPaymentGateway,
    edelweissPaymentGateway,
    orientalPaymentGateway,
    iffco_tokioPaymentGateway,
    unitedIndiaPaymentGateway,
    iciciLombardPaymentGateway,
    bajaj_allianzPaymentGateway,
    royalSundaramPaymentGateway,
    chollaMandalamPaymentGateway,
    futureGeneraliPaymentGateway,
    universalSompoPaymentGateway,
    libertyVideoconPaymentGateway,
    UnitedIndiaPaymentGatewayBillDesk,
    UnitedIndiaPaymentGatewayRazorPay
};
use App\Http\Controllers\Payment\Services\Bike\V1\ShriramPaymentGateway as shriramPaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Bike\V1\hdfcErgoPaymentGateway as hdfcErgoPaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Bike\V2\GoDigitPaymentGateway as goDigitOneapiPaymentGateway; 
use App\Http\Controllers\Payment\Services\Bike\V2\tataAigPaymentGateway As tataaigpaymentv2;
use App\Http\Controllers\Payment\Services\Bike\V1\ReliancePaymentGateway as ReliancePaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Bike\V1\FutureGeneraliPaymentGateway As FutureGeneraliPaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Bike\V1\EdelweissPaymentGateway as EdelweissPaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Bike\V1\ChollaMandalamPaymentGateway as ChollaMandalamPaymentGatewayV1;
use App\Http\Controllers\Payment\Services\Bike\V1\BajajAllianzPaymentGateway as BajajAllianzPaymentGatewayV1;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use App\Models\MasterPolicy;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;
use App\Models\CvJourneyStages;
use App\Http\Controllers\RenewalController;
use App\Models\PaymentRequestResponse;
use Carbon\Carbon;
use App\Http\Controllers\Extra\DateChanger;

class BikePaymentController extends Controller
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
        $origional_request['segment'] = 'BIKE';
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
        $CorporateVehiclesQuotesRequest = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
            ->get()
            ->first();
        if(config('proposalPage.isVehicleValidation') == 'Y' && $CorporateVehiclesQuotesRequest->business_type != 'newbusiness')
        {
            $isSectionMissmatched = isSectionMissmatched($request, 'bike', $user_proposal->vehicale_registration_number);
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

        if ($user_proposal->is_ckyc_verified != 'Y' && !in_array($request->companyAlias, explode(',',config('ICS_ALLOWED_FOR_PAYMENT_WITHOUT_CKYC')))) {
            return response()->json([
                'status' => false,
                'msg' => 'It seems your CKYC verification is not complete.'
            ]);
        }

        // All three IC ID should be same in-order to avoid any payment mismatch in another IC : @Amit - 01-11-2022
        //if(env('APP_ENV') == 'local') {
        //Condition to be run on all environments - 09-11-2022
        if(true) {
            $master_policy = MasterPolicy::find($request->policyId);
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
                if ( config('IC.SHRIRAM.V1.BIKE.ENABLE') == 'Y') {
                    $data = shriramPaymentGatewayV1::make($request);
                } 
                else {
                    $data = shriramPaymentGateway::make($request);
                }
              
                break;
            case 'godigit':
                if ($request->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest['is_renewal'] == 'Y' && config('IC.GODIGIT.V2.BIKE.RENEWAL.ENABLE')) {
                    $data = goDigitOneapiPaymentGateway::make($request);
                } elseif (config('IC.GODIGIT.V2.BIKE.ENABLE') == 'Y') {
                    $data = goDigitOneapiPaymentGateway::make($request);
                } else {
                    $data = goDigitPaymentGateway::make($request);
                }
                break;
            // case 'acko':
            //     $data = ackoPaymentGateway::make($request);
            //     break;
            case 'liberty_videocon':
                $data = libertyVideoconPaymentGateway::make($request);
                break;
            case 'royal_sundaram':
                $data = royalSundaramPaymentGateway::make($request);
                break;
            case 'icici_lombard':
                $data = iciciLombardPaymentGateway::make($request);
                break;
            case 'hdfc_ergo':
                if(config('IC.HDFC_ERGO.V1.BIKE.ENABLE') == 'Y'){
                    $data = hdfcErgoPaymentGatewayV1::make($request);
                } else {
                    $data = hdfcErgoPaymentGateway::make($request);
                }
                break;

            case 'reliance':
            if(config('IC.RELIANCE.V1.BIKE.ENABLE') == 'Y'){
                $data = ReliancePaymentGatewayV1::make($request);
            } else {
                $data = reliancePaymentGateway::make($request);
            }
            break;
            case 'future_generali':
                if(config('IC.FUTURE_GENERALI.V1.BIKE.ENABLED') == 'Y')
                {
                    $data = FutureGeneraliPaymentGatewayV1::make($request);
                }
                else
                {
                    $data = futureGeneraliPaymentGateway::make($request);
                }
            break;
            case 'tata_aig':
                if(config('IC.TATA_AIG.V2.BIKE.ENABLE') == 'Y')
                {
                    $data = tataaigpaymentv2::make($request);
                }
                else{
                    $data = tataAigPaymentGateway::make($request);
                }
               
            break;
            // case 'bharti_axa':
            //     $data = bhartiAxaPaymentGateway::make($request);
            // break;
            case 'iffco_tokio':
                $data = iffco_tokioPaymentGateway::make($request);
            break;
            case 'kotak':
                $data = kotakPaymentGateway::make($request);
                break;
                case 'magma':
                    $data = magmaPaymentGateway::make($request);
                break;
            case 'cholla_mandalam':
                if(config('IC.CHOLLA_MANDALAM.V1.BIKE.ENABLED') == 'Y'){
                    $data = ChollaMandalamPaymentGatewayV1::make($request);
                }else{
                    $data = chollaMandalamPaymentGateway::make($request);
                }
                break;
            case 'universal_sompo':
                $data = universalSompoPaymentGateway::make($request);
                break;

            case 'edelweiss':
                if (config('IC.EDELWEISS.V1.BIKE.ENABLE') == 'Y') {
     
                    $data = EdelweissPaymentGatewayV1::make($request);
                } 
                else{
                    $data = edelweissPaymentGateway::make($request);
                }
             
                break;
            case 'raheja':
                $data = rahejaPaymentGateway::make($request);
            break;    

            case 'bajaj_allianz':
                if (config('IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE') == 'Y'){
                    $data = BajajAllianzPaymentGatewayV1::make($request);
                } else {
                    $data = bajaj_allianzPaymentGateway::make($request);
                }
            break;

            case 'united_india':
                if(config('IC.UNITED_INDIA.BIKE.BILLDESK.ENABLE') == 'Y') {
                    $data =  UnitedIndiaPaymentGatewayBillDesk::make($request);
                }elseif(config('IC.UNITED_INDIA.BIKE.RAZOR_PAY.ENABLE') == 'Y'){
                    $data = UnitedIndiaPaymentGatewayRazorPay::make($request);
                } else {
                    $data =  unitedIndiaPaymentGateway::make($request);
                }
            break;

            case 'new_india':
                $data = newIndiaPaymentGateway::make($request);
            break;
            case 'oriental':
                $data = orientalPaymentGateway::make($request);
            break;
            case 'sbi':
                $data = sbiPaymentGateway::make($request);
            break;

            default:
                $data = response()->json([
                    'status' => false,
                    'msg' => 'invalid company alias name'
                ]);
            break;
        }

        $enquiryId = customDecrypt($request->enquiryId);
        event(new \App\Events\PaymentInitiated($enquiryId));
        Datechanger::Datechange($origional_request);
        return $data;
    }

    public function confirm(Request $request, $ic_name)
    {
        $requestType =  app()->runningInConsole() ? 'SCHEDULER' : 'WEB';

        PaymentResponse::create([
            'company_alias' => $ic_name,
            'section' => 'bike',
            'response' => json_encode( [ 'icResponse' => $request->all(), 'mode' => $requestType ,'headers' => $request->header()] )
        ]);

        addPolicyGenerationDelay($ic_name);
        
        switch ($ic_name) {
            case 'shriram':
                if ( config('IC.SHRIRAM.V1.BIKE.ENABLE') == 'Y') {
                    return shriramPaymentGatewayV1::confirm($request);
                } 
                else {
                    return shriramPaymentGateway::confirm($request);
                }
              
                break;
            case 'godigit':
                if ($request->is_renewal == 'Y' && config('IC.GODIGIT.V2.BIKE.RENEWAL.ENABLE')) {
                    return goDigitOneapiPaymentGateway::confirm($request);
                } elseif (config('IC.GODIGIT.V2.BIKE.ENABLE') == 'Y') {
                    return goDigitOneapiPaymentGateway::confirm($request);
                } else {
                    return goDigitPaymentGateway::confirm($request);
                }
                break;
            // case 'acko':
            //     return ackoPaymentGateway::confirm($request);
            //     break;
            case 'liberty_videocon':
                return libertyVideoconPaymentGateway::confirm($request);
                break;
            case 'royal_sundaram':
                return royalSundaramPaymentGateway::confirm($request);
                break;
            case 'icici_lombard':
                return iciciLombardPaymentGateway::confirm($request);
                break;
            case 'hdfc_ergo':
                if(config('IC.HDFC_ERGO.V1.BIKE.ENABLE') == 'Y'){
                    return hdfcErgoPaymentGatewayV1::confirm($request);
                } else {
                    return hdfcErgoPaymentGateway::confirm($request);
                }
                break;

            case 'reliance':
                if(config('IC.RELIANCE.V1.BIKE.ENABLE') == 'Y'){
                    return ReliancePaymentGatewayV1::confirm($request);
                } else {
                    return reliancePaymentGateway::confirm($request);
                }
            break;
            case 'future_generali':
                return futureGeneraliPaymentGateway::confirm($request);
            break;
            case 'tata_aig':
                if(config('IC.TATA_AIG.V2.BIKE.ENABLE') == 'Y')
                {
                    return tataaigpaymentv2::confirm($request);
                }
                else {
                    return tataAigPaymentGateway::confirm($request);
                }
               
            break;
            // case 'bharti_axa':
            //     return bhartiAxaPaymentGateway::confirm($request);
            // break;
            case 'iffco_tokio':
                return iffco_tokioPaymentGateway::confirm($request);
            break;
            case 'kotak':
                return kotakPaymentGateway::confirm($request);
            break;
            case 'magma':
                return magmaPaymentGateway::confirm($request);
            break;
            case 'cholla_mandalam':
                if(config('IC.CHOLLA_MANDALAM.V1.BIKE.ENABLED') == 'Y'){
                    return ChollaMandalamPaymentGatewayV1::confirm($request);
                }else{
                    return chollaMandalamPaymentGateway::confirm($request);
                }
                break;
            case 'universal_sompo':
                return universalSompoPaymentGateway::confirm($request);
                break;
            case 'edelweiss':
                if (config('IC.EDELWEISS.V1.BIKE.ENABLE') == 'Y') {
     
                    return EdelweissPaymentGatewayV1::confirm($request);
                } 
                else{
                    return edelweissPaymentGateway::confirm($request);
                }
               
            break;
            case 'raheja':
                return rahejaPaymentGateway::make($request);
            break;
            case 'bajaj_allianz':
                if (config('IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE') == 'Y'){
                    return BajajAllianzPaymentGatewayV1::confirm($request);
                } else {
                    return bajaj_allianzPaymentGateway::confirm($request);
                }
            break;
            case 'united_india':
                if(config('IC.UNITED_INDIA.BIKE.BILLDESK.ENABLE') == 'Y') {
                    return  UnitedIndiaPaymentGatewayBillDesk::confirm($request);
                }elseif(config('IC.UNITED_INDIA.BIKE.RAZOR_PAY.ENABLE') == 'Y'){
                    return UnitedIndiaPaymentGatewayRazorPay::confirm($request);
                } else {
                    return  unitedIndiaPaymentGateway::confirm($request);
                }
            break;
            
            case 'new_india':
                return newIndiaPaymentGateway::confirm($request);
            break;
            case 'oriental':
                return orientalPaymentGateway::confirm($request);
            break;
            case 'sbi':
                return sbiPaymentGateway::confirm($request);
            break;

            default:
                return response()->json([
                    'status' => false,
                    'msg' => 'invalid company alias name'
                ]);
            break;
        }

    }
}
