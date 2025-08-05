<?php

namespace App\Http\Controllers\Proposal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Extra\PosToPartnerUtility;
use App\Http\Controllers\Proposal\Services\Bike\ackoSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\bajaj_allianzSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\chollamandalamSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\edelweissSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\futureGeneraliProposal;
use App\Http\Controllers\Proposal\Services\Bike\goDigitSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\hdfcErgoSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\iciciLombardSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\iffco_tokioSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\kotakSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\libertyVideoconSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\magmaSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\newIndiaSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\NicSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\orientalSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\rahejaSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\relianceSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\royalSundaramSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\sbiSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\shriramSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\tataAigSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\UnitedIndiaSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\universalSompoSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\reliancerenewalSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\V1\hdfcErgoSubmitProposal as hdfcErgoSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Bike\V2\tataAigSubmitProposals;
use App\Http\Controllers\Proposal\Services\Bike\V1\ShriramSubmitProposal as ShriramSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Bike\V1\RoyalSundaramSubmitProposal as ROYAL_SUNDHARAM_V1_BIKE;
use App\Http\Controllers\Proposal\Services\Bike\V2\GoDigitSubmitProposal as GoDigitOneapiSubmitProposal;
use App\Http\Controllers\Proposal\Services\Bike\V1\RelianceSubmitProposal as RELIANCE_V1;
use App\Http\Controllers\Proposal\Services\Bike\V1\FutureGeneraliProposal as FutureGeneraliSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Bike\V1\EdelweissSubmitProposal as EdelweissSubmitProposalv1;
use App\Http\Controllers\Proposal\Services\Bike\V1\ChollaMandalamSubmitProposal as ChollaMandalamSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Bike\V1\BajajAllianzSubmitProposal as BajajAllianzSubmitProposalV1;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use App\Models\JourneyStage;
use App\Models\ProposalExtraFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\ProposalHash;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use Carbon\Carbon;
use App\Models\CvBreakinStatus;

class BikeProposalController extends Controller
{
    public function submit(Request $request)
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

        $enquiry_id = customDecrypt($request->userProductJourneyId);
        $user_product_journey = UserProductJourney::find($enquiry_id);
        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();

        if ((config('PROPOSAL_EXPIRED_IN_DAY_VALIDATION_ENABLE_BIKE') == 'Y') && $proposal->business_type != "breakin") {
            if (Carbon::parse($user_product_journey->created_on)->diffInHours(now()) > config('PROPOSAL_EXPIRED_IN_DAY_VALIDATION_BIKE')) {
                return response()->json([
                    'status' => true,
                    'msg' => 'Policy Creation Date is Older Than ' . config('PROPOSAL_EXPIRED_IN_DAY_VALIDATION_BIKE') . ' Days.',
                    'data' => [
                        'status' => 200,
                        'message' => 'Proposal Expired',
                        'policy_created_date' => $user_product_journey->created_on
                    ],
                ]);
            }
        }
        $USAGE_LIMIT_ALLOWED_SELLER = explode(',',trim(config('MOBILE_EMAIL_USAGE_LIMIT_VALIDATION_ALLOWED_SELLER')));
        $pos_data = CvAgentMapping::where('user_product_journey_id',$enquiry_id)
                            ->whereIn('seller_type',$USAGE_LIMIT_ALLOWED_SELLER)
                            ->exists();
        $get_pos_data = CvAgentMapping::where('user_product_journey_id', $enquiry_id)
        ->where('seller_type', 'P')
        ->first();  

        $get_partner_data = ProposalExtraFields::where('enquiry_id', $enquiry_id)->whereNotNull('original_agent_details')->first();

        $quote_log = QuoteLog::where('user_product_journey_id', $enquiry_id)->first();

        if (config('POS_TO_PARTNER_ALLOW_FIFTY_lAKH_IDV') == 'Y'  && config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' &&  $quote_log->idv >= 5000000 && $pos_data && !empty($get_pos_data)) {

            $PartnerDataUpdate = PosToPartnerUtility::posToPartnerFiftyLakhIdv($get_pos_data, $enquiry_id , false);

            if (!$PartnerDataUpdate['status']) {
                return response()->json([
                    'status' => $PartnerDataUpdate['status'],
                    'msg' => $PartnerDataUpdate['msg'],
                ]);
            }   
        }   elseif (config('POS_TO_PARTNER_ALLOW_FIFTY_lAKH_IDV') == 'Y'  && config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' &&  $quote_log->idv < 5000000 && !empty($get_partner_data)) {
            $PartnerDataUpdate = PosToPartnerUtility::parentToPosConversion($get_partner_data, $enquiry_id);

            if (!$PartnerDataUpdate) {
                return response()->json([
                    'status' => false,
                    'msg' => "Partner Deails not Found.",
                ]);
            }
        }                
        $return_msg_usage_limit_validation_errors = [];
        if(config('MOBILE_NUMBER_USAGE_LIMIT_VALIDATION_ENABLE') == 'Y' && $pos_data) 
        {            
            $mobile_request = new \Illuminate\Http\Request();
            $mobile_request->merge(['mobile_number' => $proposal->mobile_number]);
            $validateNumberStatus = \App\Http\Controllers\GenericController::agentMobileValidator($mobile_request);            
        
            if(!$validateNumberStatus['status'])
            {
                $return_msg_usage_limit_validation_errors['mobile'] = $validateNumberStatus['message'];
                // return response()->json([
                //     'status' => false,
                //     'msg' => $validateNumberStatus['message'],
                // ]);
            }
        }

        if(config('EMAIL_USAGE_LIMIT_VALIDATION_ENABLE') == 'Y' && $pos_data) 
        {
            $email_request = new \Illuminate\Http\Request();
            $email_request->merge(['email_id' => $proposal->email]); 
            $validateEmailStatus = \App\Http\Controllers\GenericController::agentEmailValidator($email_request);
            if(!$validateEmailStatus['status'])
            {
                $return_msg_usage_limit_validation_errors['email'] = $validateEmailStatus['message'];
                // return response()->json([
                //     'status' => false,
                //     'msg' => $validateEmailStatus['message'],
                // ]);
            }
        }

        if(isset($return_msg_usage_limit_validation_errors['email']) && isset($return_msg_usage_limit_validation_errors['mobile']))
        {
            return response()->json([
                'status' => false,
                'msg' => str_replace("mobile number","mobile number and email",$return_msg_usage_limit_validation_errors['mobile'])
            ]);
        }
        else if(isset($return_msg_usage_limit_validation_errors['email']) || isset($return_msg_usage_limit_validation_errors['mobile']))
        {
            return response()->json([
                'status' => false,
                'msg' => $return_msg_usage_limit_validation_errors['mobile'] ?? $return_msg_usage_limit_validation_errors['email']
            ]);
        }

        if (config('ENGINE_AND_CHASSIS_USAGE_LIMIT_VALIDATION_ENABLE') === 'Y') {
           
            $business_type =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)->pluck('business_type');
            $engine_and_chassis_request = new \Illuminate\Http\Request();
            $engine_and_chassis_request->merge([
                'engine_number' => $proposal->engine_number,
                'chassis_number' => $proposal->chassis_number,
                'business_type' => $business_type,
                'enquiry_id' => $enquiry_id,            
            ]);
            
            $utility = new \App\Http\Controllers\Extra\UtilityApi();
            $validateEngineAndChassisStatus = $utility->ChassisEngineCheck($engine_and_chassis_request);
            $data = $validateEngineAndChassisStatus->getData(true);
            if (isset($data['status']) && $data['status'] == false) 
            {
                return response()->json([
                    'status' => false,
                    'msg' => $data['message'],
                ]);
            }
        }

        $CorporateVehiclesQuotesRequest = CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))
            ->get()
            ->first();

        if ($CorporateVehiclesQuotesRequest->business_type != 'newbusiness' && !empty($proposal->vehicale_registration_number) && checkValidRcNumber($proposal->vehicale_registration_number)) {
            return response()->json([
                'status' => false,
                'msg' => 'This Rc Number Blocked On Portal',
            ]);
        }
       if(config('proposalPage.isVehicleValidation') == 'Y' && $CorporateVehiclesQuotesRequest->business_type != 'newbusiness')
       {
           $isSectionMissmatched = isSectionMissmatched($request, 'bike', $proposal->vehicale_registration_number);

           if(!$isSectionMissmatched['status'])
           {
               return response()->json($isSectionMissmatched);
           }
       }
       if(config('VALIDATE_MOBILE_TO_POS') == 'Y')
        {
            $posDetails =  CvAgentMapping::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
                    ->where('seller_type','P')
                    ->first();
            if(!empty($posDetails) && $posDetails->agent_mobile == $proposal->mobile_number)
            {
                return response()->json([
                    'status' => false,
                    'msg' => "Cutomer mobile should be different from POS",
                ]);
            }
        }
        $data = [
            'status' => false,
            'message' => "Something went wrong. Please try again after sometime.",
            'msg' => "Something went wrong. Please try again after sometime.",
        ];

        $journeyStage = JourneyStage::where('user_product_journey_id', $enquiry_id)->first();
        $master_policy = \App\Models\MasterPolicy::find($request->policyId);
        if (isset($journeyStage->stage) && $journeyStage->stage == STAGE_NAMES['PAYMENT_INITIATED']) {
            return response()->json([
                'status' => false,
                'message' => STAGE_NAMES['PAYMENT_INITIATED']
            ], 500);
        }
        else if(isset($journeyStage->stage) && ($journeyStage->stage == STAGE_NAMES['POLICY_ISSUED'] || $journeyStage->stage == STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] || strtolower($journeyStage->stage) == STAGE_NAMES['PAYMENT_SUCCESS'] || $journeyStage->stage == STAGE_NAMES['PAYMENT_INITIATED'])) {
            return response()->json([
                'status' => false,
                'message' => 'Transaction Already Completed',
                'data' => $journeyStage
            ], 500);
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiry_id)
        ->select('addons','compulsory_personal_accident', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
        ->first();

        $breakin_details  = CvBreakinStatus::where('user_proposal_id' , $proposal->user_proposal_id)
        ->exists();

        $sortedDetails = sortKeysAlphabetically(json_decode($proposal->additional_details));

        $hashData = collect($sortedDetails)->merge($proposal->ic_name)->merge($selected_addons->addons)->merge($selected_addons->compulsory_personal_accident)->merge($selected_addons->accessories)->merge($selected_addons->additional_covers)->merge($selected_addons->voluntary_insurer_discounts)->merge($selected_addons->discounts)->all();

        $kycHash = hash('sha256', json_encode($hashData));

        if((config("CHECK_PROPOSAL_HASH_ENABLE") == "Y"))
        {
            $checkProposalHash = \App\Http\Controllers\Proposal\ProposalController::checkProposalHash($kycHash, $proposal);

            if ($checkProposalHash['status']) {
                $responseArray = [
                    "status" => true,
                    "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "msg" => "Proposal Submited Successfully..!",
                    "data" => [
                        "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        "verification_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $proposal->user_product_journey_id,
                        'proposalNo' => $proposal->proposal_no,
                        'finalPayableAmount' => $proposal->final_payable_amount,
                        'is_breakin' => $breakin_details == true ? 'Y' : 'N' , //handing for brekin case BIKE
                        'inspection_number' =>$breakin_details == true ? $proposal->proposal_no : null, //handing for brekin case BIKE
                    ]
                ];
                $enable_hyperverse = config('ENABLE_HYPERVERGE_FOR_ORIENTAL') == "Y";
                if ($request->companyAlias == 'oriental' && $enable_hyperverse) {
                    $additional_details_data = json_decode($proposal->additional_details_data, true);
                    $token = $additional_details_data['access_token'] ?? null;
                    $clientId = $additional_details_data['clientId'] ?? null;
                    $clientSecret = $additional_details_data['clientSecret'] ?? null;

                    $responseArray['token'] = $token;
                    $responseArray['data']['token'] = $token;
                    $responseArray['data']['clientId'] = $clientId;
                    $responseArray['data']['clientSecret'] = $clientSecret;
                }
                 return response()->json($responseArray);
            }
        } else {
            $ckycStatus = \App\Http\Controllers\Ckyc\statusCheckController::checkStatus($proposal, $kycHash);
            
            if($ckycStatus['msg']){
                return response()->json($ckycStatus['data']);
            }
        }

        $fullName = json_decode($proposal->additional_details, true);
        if(config("ENABLE_NAME_VALIDATION") == 'Y'){
        if ($proposal->owner_type == 'I') {
        $is_name_validated = fullNameValidation($proposal->first_name, $proposal->last_name, $fullName['owner']['fullName']);
        if (!$is_name_validated) {
            return response()->json([
                'status' => false,
                'msg' => "Name MissMatch",
                'data' => [
                    'First Name' => $proposal->first_name,
                    'Last Name' => $proposal->last_name,
                    'Full Name' => $proposal->fullName
                ]
            ]);
        }
        }
    }

        $isrenewal = null;
        if ($request->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest['is_renewal'] == 'Y') {
            switch ($request->companyAlias) {
                case 'shriram':
                    $data = shriramSubmitProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'godigit':
                    if(config("ENABLE_GODIGIT_RENEWAL_API") === 'Y')
                    {
                        if (config('IC.GODIGIT.V2.BIKE.RENEWAL.ENABLE') == 'Y') {
                            $data = GoDigitOneapiSubmitProposal::renewalSubmitOneapi($proposal, $request->all());
                        } else {
                            $data = goDigitSubmitProposal::renewalSubmit($proposal, $request->all());
                        }
                    }else
                    {
                        $data = goDigitSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'tata_aig':
                    if(config('IC.TATA_AIG.V2.BIKE.ENABLE') == 'Y')
                    {
                        $data = tataAigSubmitProposals::submit($proposal, $request->all());
                    }
                    else 
                    {
                        $data = tataAigSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                // case 'acko':
                //     $data = ackoSubmitProposal::submit($proposal, $request->all());
                //     break;
                case 'kotak':
                    $data = kotakSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'royal_sundaram':
                    //$isrenewal = true;
                    $data = royalSundaramSubmitProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'iffco_tokio':
                    $data = iffco_tokioSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'icici_lombard':
                    $data = iciciLombardSubmitProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'hdfc_ergo':
                    $data = hdfcErgoSubmitProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'reliance':
                    $data = reliancerenewalSubmitProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'bajaj_allianz':
                    if (config("ENABLE_BAJAJ_ALLIANZ_RENEWAL_API") === 'Y')
                    {
                        $isrenewal = true;
                        if (config('IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE') == 'Y'){
                            $data = BajajAllianzSubmitProposalV1::renewalSubmit($proposal, $request->all());
                        } else {
                            $data = bajaj_allianzSubmitProposal::renewalSubmit($proposal, $request->all());
                        }
                    }
                    else
                    {
                        if (config('IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE') == 'Y'){
                            $data = BajajAllianzSubmitProposalV1::submit($proposal, $request->all());
                        } else {
                            $data = bajaj_allianzSubmitProposal::submit($proposal, $request->all());
                        }
                    }
                    break;
                case 'future_generali':
                    $data = futureGeneraliProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'liberty_videocon':
                    $data = libertyVideoconSubmitProposal::renewalSubmit($proposal, $request->all());
                    break;
                default:
                    $data = response()->json([
                        'status' => false,
                        'msg' => 'invalid company alias name',
                    ]);
            }
        } else {
            switch ($request->companyAlias) {
                case 'shriram':
                    if (config('IC.SHRIRAM.V1.BIKE.ENABLE') == 'Y') {
                        $data = ShriramSubmitProposalV1::submitV1JSON($proposal, $request);
                    }
                    else{
                        $data = shriramSubmitProposal::submit($proposal, $request->all());
                    }                  
                    break;
                case 'godigit':
                    if (config('IC.GODIGIT.V2.BIKE.ENABLE') == 'Y') {
                        $data = GoDigitOneapiSubmitProposal::oneApiSubmit($proposal, $request->all());
                    } else {
                        $data = goDigitSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                // case 'acko':
                //     $data = ackoSubmitProposal::submit($proposal, $request->all());
                //     break;
                case 'icici_lombard':
                    $data = iciciLombardSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'hdfc_ergo':
                    if (config('IC.HDFC_ERGO.V1.BIKE.ENABLE') == 'Y') {
                        $data = hdfcErgoSubmitProposalV1::submit($proposal, $request->all());
                    } else{
                        $data = hdfcErgoSubmitProposal::submit($proposal, $request->all());
                    }

                    break;
                case 'reliance':
                    if(config('IC.RELIANCE.V1.BIKE.ENABLE') == 'Y') {                        
                        $data = RELIANCE_V1::submit($proposal, $request->all());
                    } else {
                        $data = relianceSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'tata_aig':
                    if(config('IC.TATA_AIG.V2.BIKE.ENABLE') == 'Y')
                    {
                        $data = tataAigSubmitProposals::submit($proposal, $request->all());
                    }
                    else
                    {
                        $data = tataAigSubmitProposal::submit($proposal, $request->all());
                    }                   
                    break;
                case 'iffco_tokio':
                    $data = iffco_tokioSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'royal_sundaram':
                    if (config('IC.ROYAL_SUNDARAM.V1.BIKE.ENABLE') == 'Y') {
                        $data = ROYAL_SUNDHARAM_V1_BIKE::submit($proposal, $request);
                    } else {
                        $data = royalSundaramSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'liberty_videocon':
                    $data = libertyVideoconSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'magma':
                    $data = magmaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'kotak':
                    $data = kotakSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'cholla_mandalam':
                    if(config('IC.CHOLLA_MANDALAM.V1.BIKE.ENABLED') == 'Y'){
                        $data = ChollaMandalamSubmitProposalV1::submit($proposal, $request->all());
                    }else{
                        $data = chollamandalamSubmitProposal::submit($proposal, $request->all());
                    }   
                    break;
                case 'universal_sompo':
                    $data = universalSompoSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'edelweiss':
                    if (config('IC.EDELWEISS.V1.BIKE.ENABLE') == 'Y') 
                    { 
                        $data =  EdelweissSubmitProposalv1::submit($proposal, $request);
                    }
                    else{
                        $data = edelweissSubmitProposal::submit($proposal, $request->all());
                    }
                  
                    break;
                case 'raheja':
                    $data = rahejaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'future_generali':
                    if(config('IC.FUTURE_GENERALI.V1.BIKE.ENABLED') == 'Y')
                    {
                        $data = FutureGeneraliSubmitProposalV1::submit($proposal, $request->all());
                    }
                    else
                    {
                        $data = futureGeneraliProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'bajaj_allianz':
                    if (config('IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE') == 'Y'){
                        $data = BajajAllianzSubmitProposalV1::submit($proposal, $request->all());
                    } else {
                        $data = bajaj_allianzSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'united_india':
                    $data = UnitedIndiaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'nic':
                    $data = NicSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'new_india':
                    $data = newIndiaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'oriental':
                    $data = orientalSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'sbi':
                    $data = sbiSubmitProposal::submit($proposal, $request->all());
                    break;
                default:
                    $data = response()->json([
                        'status' => false,
                        'msg' => 'invalid company alias name',
                    ]);
            }
        }
        $submitResponse = $data;
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            $submitResponse = $data->original;
            $d = $data->original;
            if (isset($d['status']) && !$d['status']) {
                $arr = json_decode($data->content(), true);
                $arr['message'] = getProposalCustomErrorMessage($d['msg'] ?? $d['message'] ?? '', $request->companyAlias, 'bike');
                $data->setData($arr);
            }
        } 
        else if(is_object($data))
		{
			$submitResponse = $data = json_decode(json_encode($data),true);
			if(isset($data['status']) && !$data['status'])
			{
				$data['message'] = getProposalCustomErrorMessage($data['msg'] ?? $data['message'] ?? '', $request->companyAlias, 'bike');
			} 
		}
       else if (isset($data['status']) && !$data['status']) {
            $data['message'] = getProposalCustomErrorMessage($data['msg'] ?? $data['message'] ?? '', $request->companyAlias, 'bike');
        }

        if(config("ENABLE_UPDATE_SIDECARD_DATA_AT_PROPOSAL") == 'Y')
        {
            $Sidecard_update_allowed_ic = config('BIKE_SIDECARD_UPDATE_ALLOWED_IC');
            $Sidecard_update_allowed_ic = explode(',',$Sidecard_update_allowed_ic);
            if(in_array($request->companyAlias,$Sidecard_update_allowed_ic))
            {
                try {
                    if ($data instanceof \Illuminate\Http\JsonResponse) {
                        $d = $data->original;
                        if(isset($d['status']) && $d['status']) {
                            premium_updation_proposal($enquiry_id);
                        }
                    } else if (isset($data['status']) && $data['status']) {
                        premium_updation_proposal($enquiry_id);
                    }
                }catch(\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('SIDECARD updation failed for trace '. $enquiry_id .' : ' .$e->getMessage());
                    // noting to write here....
                };

            }
        }

        if (isset($submitResponse['status']) && ($submitResponse['status'] == 'true' || $submitResponse['status'] === true)) {
            $webservice_id = $submitResponse['webservice_id'] ?? $submitResponse['webserviceId'] ?? null;
            $message = $submitResponse['message'] ?? $submitResponse['msg'];

            if (isset($webservice_id)) {
                update_quote_web_servicerequestresponse($submitResponse['table'], $webservice_id, $message, "Success");
            }
        } else if (isset($submitResponse['status']) && ($submitResponse['status'] == 'false' ||  $submitResponse['status'] === false)) {
            $webservice_id = $submitResponse['webservice_id'] ?? $submitResponse['webserviceId'] ?? null;
            $message = $submitResponse['message'] ?? $submitResponse['msg'];

            if (isset($webservice_id)) {
                update_quote_web_servicerequestresponse($submitResponse['table'], $webservice_id, $message, "Failed");
            }
        }
        if ((is_object($data) && isset($data->original['status']) && $data->original['status']) || (is_array($data) && isset($data['status']) && $data['status'])) {
            if (config("CHECK_PROPOSAL_HASH_ENABLE") == "Y") {
                $hash_ic_list = config("constants.PROPOSAL_HASH_ALLOWED_ICS", null);
                $hash_ic_list = explode(',', $hash_ic_list);
                if (
                    !empty($hash_ic_list) &&
                    in_array($request->companyAlias, $hash_ic_list)
                ) {
                    ProposalHash::create(
                        [
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'user_proposal_id' => $proposal->user_proposal_id,
                            'additional_details_data' => is_array($proposal->additional_details) ?  json_encode($proposal->additional_details) : $proposal->additional_details,
                            'hash' => $kycHash ?? null,
                        ]
                    );
                }
            }
        }

        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->select('is_ckyc_verified')->first();
        $kyc_status = $proposal->is_ckyc_verified == 'Y' ? true : false;
        if(in_array($request->companyAlias, explode(',',config('ICS_ALLOWED_FOR_PAYMENT_WITHOUT_CKYC'))))
        {
           $kyc_status = true;
        }
        $verification_status = $hide_ckyc_popup = false;
        if(in_array($request->companyAlias, explode(',',config('ICS_VERIFY_CKYC_HIDE_POPUP'))))
        {
           $hide_ckyc_popup = true;
        }
        if(in_array($request->companyAlias, explode(',',config('ICS_VERIFY_CKYC_BYPASS_STATUS'))))
        {
            $verification_status = true;
        }
        if($isrenewal == true){
            $kyc_status = true;
            $hide_ckyc_popup = true;
        }
        try {
            if ($data instanceof \Illuminate\Http\JsonResponse) {
                $data = $data->original;

                $data['data']['kyc_status'] = $kyc_status;
                $data['data']['hidePopup'] = $hide_ckyc_popup;

            } else if (isset($data['status'])) {
                $data['data']['kyc_status'] = $kyc_status;
                $data['data']['hidePopup'] = $hide_ckyc_popup;
            }
        } catch (\Exception $e) {
            $data['data']['kyc_status'] = $kyc_status;
            $data['data']['hidePopup'] = $hide_ckyc_popup;
            \Illuminate\Support\Facades\Log::error('Error while checking CKYC status' . $enquiry_id . ' : ' . $e->getMessage());
            // noting to write here....
        };
        if($isrenewal == true){
            $data['data']['verification_status'] = true;
        }
        $proposalNo = UserProposal::where('user_product_journey_id', $enquiry_id)->select('proposal_no')->first();
        if(isset($data['status']) && $data['status'] == true && !isset($data['data']['proposalNo']) && empty($data['data']['proposalNo'])){
            $data['data']['proposalNo'] = $proposalNo->proposal_no;
        }
        event(new \App\Events\ProposalSubmitted($enquiry_id));

        return $data;
    }
}
