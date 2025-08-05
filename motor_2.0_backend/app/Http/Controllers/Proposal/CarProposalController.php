<?php

namespace App\Http\Controllers\Proposal;
use App\Models\ProposalHash;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Proposal\Services\Car\V2\GoDigitSubmitProposal as GoDigitOneapiSubmitProposal;
use App\Http\Controllers\Proposal\Services\Car\V2\nicSubmitProposal as nicSubmitProposalV2;
use App\Http\Controllers\Proposal\Services\Car\V1\RelianceSubmitProposal as RELIANCE_V1;
use App\Http\Controllers\Proposal\Services\Car\V1\RoyalSundaramSubmitProposal as ROYAL_SUNDHARAM_V1;
use App\Http\Controllers\Proposal\Services\Car\V1\FutureGeneraliProposal as FutureGeneraliSubmitProposal;
use App\Http\Controllers\Proposal\Services\Car\V1\BajajAllianzSubmitProposal as BajajAllianzSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Car\V1\LibertyVideoconSubmitProposal as LibertyVideoconSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Renewal\Car\V2\tataAigSubmitProposal as tata_aig_v2_renewal;
use App\Http\Controllers\Proposal\Services\Car\{
    ackoSubmitProposal,
    bajaj_allianzSubmitProposal,
    bhartiAxaSubmitProposal,
    chollamandalamSubmitProposal,
    edelweissSubmitProposal,
    futureGeneraliProposal,
    goDigitSubmitProposal,
    hdfcErgoSubmitProposal,
    iciciLombardSubmitProposal,
    iffco_tokioSubmitProposal,
    kotakSubmitProposal,
    libertyVideoconSubmitProposal,
    magmaSubmitProposal,
    newIndiaSubmitProposal,
    NicSubmitProposal,
    orientalSubmitProposal,
    RahejaSubmitProposal,
    relianceSubmitProposal,
    royalSundaramSubmitProposal,
    sbiSubmitProposal,
    shriramSubmitProposal,
    tataAigSubmitProposal,
    tataAigV2SubmitProposal,
    UnitedIndiaSubmitProposal,
    sbiRenewalSubmitProposal,
    universalSompoSubmitProposal
};
use App\Http\Controllers\Proposal\Services\Car\V1\hdfcErgoSubmitProposal as hdfcErgoSubmitProposalv1;
use App\Http\Controllers\Proposal\Services\Car\V1\ShriramSubmitProposal as shriramSubmitProposalv1;
use App\Http\Controllers\Proposal\Services\Car\V1\EdelweissSubmitProposal as EdelweissSubmitProposalv1;
use App\Http\Controllers\Proposal\Services\Car\V1\ChollaMandalamSubmitProposal as ChollaSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Car\V1\IffcoTokioSubmitProposal as Iffco_tokioSubmitProposalV1;
use App\Http\Controllers\Proposal\Services\Renewal\Car\V1\hdfcErgoSubmitProposal as RenewalV1;
use App\Http\Controllers\Proposal\Services\Car\V2\FutureGeneraliProposal as FutureGenerali_SubmitProposal;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use App\Models\JourneyStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\CvBreakinStatus;
use App\Models\MasterCompany;
use App\Models\QuoteLog;
use App\Http\Controllers\Extra\PosToPartnerUtility;
use App\Models\ProposalExtraFields;
use App\Models\UserProductJourney;
use Carbon\Carbon;

class CarProposalController extends Controller
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
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
        $user_product_journey = UserProductJourney::find($enquiry_id);

        if ((config('PROPOSAL_EXPIRED_IN_DAY_VALIDATION_ENABLE_CAR') == 'Y') && $user_proposal->business_type != "breakin") {
            if (Carbon::parse($user_product_journey->created_on)->diffInHours(now()) > config('PROPOSAL_EXPIRED_IN_HOUR_VALIDATION_CAR')) {
                return response()->json([
                    'status' => true,
                    'msg' => 'Policy Creation Date is Older Than ' . config('PROPOSAL_EXPIRED_IN_HOUR_VALIDATION_CAR') . ' Days.',
                    'data' => [
                        'status' => 200,
                        'message' => 'Proposal Expired',
                        'policy_created_date' => $user_product_journey->created_on
                    ],
                ]);
            }
        }
        if(!empty($user_proposal)){
            $cv_breakin_status = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->first();
            if(!empty($cv_breakin_status)){
                $ic_name = MasterCompany::where('company_id', $cv_breakin_status->ic_id)->select('company_name')->first();
                if((isset($cv_breakin_status->breakin_status) && (strtolower($cv_breakin_status->breakin_status) != STAGE_NAMES['INSPECTION_APPROVED'])) && (!in_array($request->companyAlias, ['tata_aig', 'tata_aig_v2']))){
                    return response()->json([
                        'status' => false,
                        'msg' => "Lead {$cv_breakin_status->breakin_number} is already generated with {$ic_name->company_name} for the same trace ID. Try with a fresh journey."
                    ]);
                }
            }   
        }
        
        if(isset($request->all()['declaredAddons']))
        {
            UserProposal::where('user_product_journey_id', $enquiry_id)
                ->update([
                    'previous_policy_addons_list' => $request->all()['declaredAddons']
                ]);            
        }

        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();

        $USAGE_LIMIT_ALLOWED_SELLER = explode(',',trim(config('MOBILE_EMAIL_USAGE_LIMIT_VALIDATION_ALLOWED_SELLER')));
        $pos_data = CvAgentMapping::where('user_product_journey_id',$enquiry_id)
                            ->whereIn('seller_type',$USAGE_LIMIT_ALLOWED_SELLER)
                            ->exists();
                            
        $get_pos_data = CvAgentMapping::where('user_product_journey_id', $enquiry_id)
        ->where('seller_type', 'P')
        ->first();   

        $quote_log = QuoteLog::where('user_product_journey_id', $enquiry_id)->first();

        $get_partner_data = ProposalExtraFields::where('enquiry_id', $enquiry_id)->whereNotNull('original_agent_details')->first();

        if (config('POS_TO_PARTNER_ALLOW_FIFTY_lAKH_IDV') == 'Y'  && config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' &&  $quote_log->idv >= 5000000 && $pos_data && !empty($get_pos_data)) {

            $PartnerDataUpdate = PosToPartnerUtility::posToPartnerFiftyLakhIdv($get_pos_data, $enquiry_id , false);

            if (!$PartnerDataUpdate['status']) {
                return response()->json([
                    'status' => $PartnerDataUpdate['status'],
                    'msg' => $PartnerDataUpdate['msg'],
                ]);
            }
        } elseif (config('POS_TO_PARTNER_ALLOW_FIFTY_lAKH_IDV') == 'Y'  && config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' &&  $quote_log->idv < 5000000 && !empty($get_partner_data)) {
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
           $isSectionMissmatched = isSectionMissmatched($request, 'car', $proposal->vehicale_registration_number);

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
                    if (config("ENABLE_GODIGIT_RENEWAL_API") === 'Y') {
                        if (config('IC.GODIGIT.V2.CAR.RENEWAL.ENABLE') == "Y") {
                            $data = GoDigitOneapiSubmitProposal::renewalSubmit($proposal, $request->all());
                        } else {
                            $data = goDigitSubmitProposal::renewalSubmit($proposal, $request->all());
                        }
                    } else {
                        $data = goDigitSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'acko':
                    $data = ackoSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'tata_aig':
                    if (config('IC.TATA.V2.CAR.RENEWAL.ENABLE') == 'Y') 
                    {
                        $data = tata_aig_v2_renewal::renewalSubmit($proposal, $request->all());

                    }
                    else{
                        $data = tataAigSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'kotak':
                    $data = kotakSubmitProposal::renewalSubmit($proposal, $request->all());
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
                    $isrenewal = true;
                    if (config('IC.HDFC_ERGO.V1.CAR.RENEWAL.ENABLE') == 'Y'){
                        $data = RenewalV1::renewalSubmit($proposal, $request->all());
                    } else {
                    $data = hdfcErgoSubmitProposal::renewalSubmit($proposal, $request->all());
                    }
                    break;
                case 'reliance':
                    if (config('IC.RELIANCE.V1.CAR.ENABLE') == 'Y') {
                        $data = RELIANCE_V1::submit($proposal, $request->all());
                    } else {
                        $data = relianceSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'future_generali':
                    $data = futureGeneraliProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'liberty_videocon':
                    if(config('IC.LIBERTY_VIDEOCON.V1.CAR.ENABLE') == 'Y'){
                        $data = LibertyVideoconSubmitProposalV1::renewalSubmit($proposal, $request->all());
                    } else {
                        $data = libertyVideoconSubmitProposal::renewalSubmit($proposal, $request->all());
                    }
                    break;
                case 'sbi':
                    $data = sbiRenewalSubmitProposal::renewalSubmit($proposal, $request->all());
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
                    if (config('IC.SHRIRAM.V1.CAR.ENABLE') == 'Y') { 

                        $data =  shriramSubmitProposalv1::submitV1($proposal, $request);
                    }
                    else {
                        $data = shriramSubmitProposal::submit($proposal, $request->all());
                    }                 
                    break;
                case 'godigit':
                    if (config('IC.GODIGIT.V2.CAR.ENABLE') == 'Y') {
                        $data = GoDigitOneapiSubmitProposal::submit($proposal, $request->all());
                    } else {
                        $data = goDigitSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'acko':
                    $data = ackoSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'liberty_videocon':
                    if(config('IC.LIBERTY_VIDEOCON.V1.CAR.ENABLE') == 'Y'){
                        $data = LibertyVideoconSubmitProposalV1::submit($proposal, $request->all());
                    } else {
                        $data = libertyVideoconSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'sbi':
                    $data = sbiSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'royal_sundaram':
                    if (config('IC.ROYAL_SUNDARAM.V1.CAR.ENABLE') == 'Y') {
                        $data = ROYAL_SUNDHARAM_V1::submit($proposal, $request);
                    } else {
                        $data = royalSundaramSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'icici_lombard':
                    $data = iciciLombardSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'hdfc_ergo':
                    if (config('IC.HDFC_ERGO.V1.CAR.ENABLE') == 'Y') {
                        $data = hdfcErgoSubmitProposalv1::submit($proposal, $request->all());
                    } else{
                        $data = hdfcErgoSubmitProposal::submit($proposal, $request->all());
                    }

                    break;
                case 'reliance':
                    if (config('IC.RELIANCE.V1.CAR.ENABLE') == 'Y') {
                        $data = RELIANCE_V1::submit($proposal, $request->all());
                    } else {
                        $data = relianceSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'tata_aig':
                    $data = tataAigSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'future_generali':
                    if(config("IC.FUTURE_GENERALI.V1.CAR.ENABLED") == 'Y')
                    {
                        $data = FutureGeneraliSubmitProposal::submit($proposal, $request->all());
                    }
                    elseif(config("IC.FUTURE_GENERALI.V2.CAR.ENABLED") == 'Y')
                    {
                        $data = FutureGenerali_SubmitProposal::submit($proposal, $request->all());
                    }
                    else
                    {
                        $data = futureGeneraliProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'bharti_axa':
                    $data = bhartiAxaSubmitProposal::submit($proposal, $request->all());
                    break;

                case 'cholla_mandalam':
                    if(config('IC.CHOLLA_MANDALAM.V1.CAR.ENABLED') == 'Y'){
                        $data = ChollaSubmitProposalV1::submit($proposal, $request->all());
                    }else{
                        $data = chollamandalamSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'kotak':
                    $data = kotakSubmitProposal::submit($proposal, $request->all());

                    break;
                case 'iffco_tokio':
                    if(config("IC.IFFCO_TOKIO.V1.CAR.ENABLE") == 'Y')
                    {
                        $data = Iffco_tokioSubmitProposalV1::submit($proposal, $request->all());
                    }
                    else{
                        $data = iffco_tokioSubmitProposal::submit($proposal, $request->all());
                    }
                   

                    break;
                case 'magma':
                    $data = magmaSubmitProposal::submit($proposal, $request->all());
                    break;

                case 'bajaj_allianz':
                    if(config('IC.BAJAJ_ALLIANZ.V1.CAR.ENABLE') == 'Y'){
                        $data = BajajAllianzSubmitProposalV1::submit($proposal, $request->all());
                    } else {
                        $data = bajaj_allianzSubmitProposal::submit($proposal, $request->all());
                    }
                    break;

                case 'universal_sompo':
                    $data = universalSompoSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'united_india':
                    $data = UnitedIndiaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'nic':
                    if (config('IC.NIC.V2.CAR.ENABLE') == 'Y') 
                    {
                        $data = nicSubmitProposalV2::submit($proposal, $request->all());
                    }else{
                        $data = NicSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'raheja':
                    $data = RahejaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'edelweiss':
                    if (config('IC.EDELWEISS.V1.CAR.ENABLE') == 'Y') 
                    { 
                        $data =  EdelweissSubmitProposalv1::submit($proposal, $request);
                    }
                    else 
                    {
                        $data = edelweissSubmitProposal::submit($proposal, $request->all());
                    }
                 
                    break;
                case 'new_india':
                    $data = newIndiaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'oriental':
                    $data = orientalSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'tata_aig_v2':
                    $data = tataAigV2SubmitProposal::submit($proposal, $request->all());
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
                $arr['message'] = getProposalCustomErrorMessage($d['msg'] ?? $d['message'] ?? '', $request->companyAlias, 'car');
                $data->setData($arr);
            }
        } else if (isset($data['status']) && !$data['status']) {
            $data['message'] = getProposalCustomErrorMessage($data['msg'] ?? $data['message'] ?? '', $request->companyAlias, 'car');
        }
        if(config('IS_FINSALL_ACTIVATED') == 'Y')
        {
            $allowed_ic = config('FINSALL_ALLOWED_IC');
            $allowed_ic_array = explode(',',$allowed_ic);
            if(in_array($request->companyAlias, $allowed_ic_array))
            {
                $data  = $data->getData();   
                $data->is_finsall_available = true;
                $data = response()->json($data);
            }
        }
        if(config("ENABLE_UPDATE_SIDECARD_DATA_AT_PROPOSAL") == 'Y')
        {
            $Sidecard_update_allowed_ic = config('CAR_SIDECARD_UPDATE_ALLOWED_IC');
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
                        [   'user_product_journey_id' => $proposal->user_product_journey_id,
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