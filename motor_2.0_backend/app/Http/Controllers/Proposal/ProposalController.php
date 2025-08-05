<?php

namespace App\Http\Controllers\Proposal;

use App\Models\QuoteLog;
use App\Models\JourneyStage;
use App\Models\UserProposal;
use App\Models\MasterCompany;
use App\Models\ProposalHash;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use App\Models\ProposalFields;
use Illuminate\Validation\Rule;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Extra\PosToPartnerUtility;
use Illuminate\Support\Facades\Http;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Proposal\Services\{
    SbiSubmitProposal,
    ackoSubmitProposal,
    goDigitSubmitProposal,
    shriramSubmitProposal,
    tataAigSubmitProposal,
    hdfcErgoSubmitProposal,
    orientalSubmitProposal,
    relianceSubmitProposal,
    iffco_tokioSubmitProposal,
    iciciLombardSubmitProposal,
    bajaj_allianzSubmitProposal,
    libertyVideoconSubmitProposal,
    magmaSubmitProposal,
    universalSompoSubmitProposal,
    royalSundaramSubmitProposal,
    chollamandalamSubmitProposal,
    NicSubmitProposal,
    UnitedIndiaSubmitProposal,
    futuregeneraliSubmitProposal,
    NewIndiaSubmitProposal
};

use App\Http\Controllers\Proposal\Services\V2\shriramgcvpcvSubmitProposal as ShriramSubmitProposalV2;
use App\Http\Controllers\Proposal\Services\V1\GCV\ShriramSubmitProposal as ShriramSubmitProposalV1GCV;
use App\Http\Controllers\Proposal\Services\V1\PCV\ShriramSubmitProposal as ShriramSubmitProposalV1PCV;
use App\Http\Controllers\Proposal\Services\V1\GCV\FutureGeneraliProposalSubmit as fgsubmitv1;
use App\Http\Controllers\Proposal\Services\Pcv\V2\GoDigitSubmitProposal as goDigitOneapiSubmitProposal;

use App\Http\Controllers\Proposal\Services\V2\PCV\tataAigSubmitProposals;
use App\Http\Controllers\Proposal\Services\V1\hdfcErgoSubmitProposals AS HDFC_ERGO_V1;
use App\Http\Controllers\Proposal\Services\V1\RelianceSubmitProposal AS RELIANCE_V1;
use App\Models\ckycUploadDocuments;
use App\Models\CvBreakinStatus;
use App\Models\ProposalExtraFields;
use App\Models\ProposerCkycDetails;
use Exception;
use Illuminate\Support\Facades\Storage;
use App\Models\SelectedAddons;
use Carbon\Carbon;
class ProposalController extends Controller
{
    public function save(Request $request)
    {   //start
        $payload=[];
        $enquiry_id = customDecrypt($request->userProductJourneyId);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
        $quoteLog = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
        if(!empty($user_proposal)){
            $cv_breakin_status = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->first();
            if(!empty($cv_breakin_status)){
                $ic_name = MasterCompany::where('company_id', $cv_breakin_status->ic_id)->select('company_name')->first();
                if((isset($cv_breakin_status->breakin_status) && (strtolower($cv_breakin_status->breakin_status) != STAGE_NAMES['INSPECTION_APPROVED'])) && $quoteLog['ic_id'] != "24"){
                    return response()->json([
                        'status' => false,
                        'msg' => "Lead {$cv_breakin_status->breakin_number} is already generated with {$ic_name->company_name} for the same trace ID. Try with a fresh journey."
                    ]);
                }
            }
        }
        
        $journeyStage = JourneyStage::where('user_product_journey_id', $enquiry_id)->first();
        if (isset($journeyStage->stage) && (in_array(strtolower($journeyStage->stage), [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['PAYMENT_RECEIVED'], STAGE_NAMES['PAYMENT_FAILED']]))) {
            return response()->json([
                'status' => false,
                'message' => 'Editing this proposal is not allowed as payment transaction has been initiated or under process.',
                'data' => $journeyStage
            ], 500);
        }

        if (isset($request->icId)) {
            $finsallAvailability = 'N';
            $masterCompany = MasterCompany::where('company_id', $request->icId)->first();
            if (config('constants.finsall.IS_FINSALL_ACTIVATED') == 'Y') {
                $allowed_ic = config('constants.finsall.FINSALL_ALLOWED_IC');
                $allowed_ic_array = explode(',', $allowed_ic);
                if (in_array($masterCompany->company_alias, $allowed_ic_array)) {
                    $finsallAvailability = 'Y';
                }
                UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
                    ->update([
                        'is_finsall_available' => $finsallAvailability
                    ]);
            }
        }
        
        if(isset($request->clearDocuments) && !empty($request->clearDocuments) && ($request->clearDocuments)) {
            ProposerCkycDetails::updateOrCreate(
                ['user_product_journey_id' => $enquiry_id],
                ['is_document_upload' => 'N']
            );
        }

        $JourneyStage_data = JourneyStage::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();
        $url = $JourneyStage_data->quote_url;
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
        $token = $query['token'] ?? '';
        if ($token != '') {
            unset($agentDetail);
            $agentDetail =  CvAgentMapping::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
                ->count();
            if ($agentDetail == 0) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Agent Details is Missing'
                ]);
            }
        }

        $requestData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)->first();

        if ($requestData->business_type != 'newbusiness' && !empty($user_proposal->vehicale_registration_number) && checkValidRcNumber($user_proposal->vehicale_registration_number) && $request->stage === "3") {
            return response()->json([
                'status' => false,
                'msg' => 'This Rc Number Blocked On Portal',
            ]);
        }

        if(is_null($requestData->policy_type) || is_null($requestData->business_type))
        {
            return [
                'status' => false,
                'message' => 'Oops! Some detials are missing or updated. You will be redirected to input page.'
            ];     
        }
        if(config('VALIDATE_MOBILE_TO_POS') == 'Y')
        {
            $posDetails =  CvAgentMapping::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
                    ->where('seller_type','P')
                    ->first();
            if(!empty($posDetails) && $posDetails->agent_mobile == $request->mobileNumber)
            {
                return response()->json([
                    'status' => false,
                    'msg' => "Cutomer mobile should be different from POS",
                ]);
            }
        }
        $quoteData = QuoteLog::where('user_product_journey_id', $enquiry_id)->select('ic_id', 'product_sub_type_id')->first();
        $company_alias = DB::table('master_company')
            ->where('company_id', $quoteData->ic_id)
            ->select('company_alias')
            ->first();
        $section = DB::table('corporate_vehicles_quotes_request')
            ->where('user_product_journey_id', $enquiry_id)
            ->join('master_product_sub_type', 'master_product_sub_type.product_sub_type_id', '=', 'corporate_vehicles_quotes_request.product_id')
            ->select('product_sub_type_code', 'parent_id')
            ->first();
        $parentId = $section->parent_id;
        $section = \Illuminate\Support\Str::ucfirst($section->product_sub_type_code);
        $amlEnabled = false;
        $result = ProposalFields::where([
            "company_alias" => $company_alias->company_alias,
            "section" => $section,
            "owner_type" => $requestData['vehicle_owner_type']
        ])->select('fields')->first();
        if ($request->stage === "0") {
            $rules = [
                "userProductJourneyId" => ['required'],
                "cpaInsComp" => ['nullable'],
                'cpaPolicyFmDt' => ['nullable'],
                'cpaPolicyNo' => ['nullable'],
                'cpaPolicyToDt' => ['nullable'],
                'cpaSumInsured' => ['nullable'],
            ];
            $validator = Validator::make($request->all(), $rules);
           
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()]);
            }
            $payload = $request->validate($rules);
        }
        if ($request->stage === "1") {

            if($request->mobileNumber == '7620444846')
            {
                return response()->json([
                    'status' => false,
                    'msg' => "Kindly use own mobile number",
                ]);
            }
            else if($request->panNumber == 'BINPJ6746A')
            {
                return response()->json([
                    'status' => false,
                    'msg' => "Kindly use own pan number",
                ]);
            }
            $stage_1 = [
                "userProductJourneyId" => ['required'],
                "ownerType" => ['required', Rule::in(['I', 'C'])],
                "title" => ['nullable'],
                "firstName" => ['required_if:ownerType,I', 'string'],
                "lastName" => ['nullable'],
                "mobileNumber" => ['required'],
                //"email" => ['required', 'email:rfc,dns'],
                "email" => ( ( config( 'email.dns.validation.enabled' ) == "Y" ) ? ['nullable', 'email:rfc,dns'] : ['nullable', 'email:rfc'] ),
                "officeEmail" => ['nullable'],
                "panNumber" => ['nullable', 'regex:/[A-Z]{3}[ABCFGHLJPTF]{1}[A-Z]{1}[0-9]{4}[A-Z]{1}/'],
                "gstNumber" => ['nullable'],
                "addressLine1" => ['required'],
                "addressLine2" => ['nullable'],
                "addressLine3" => ['nullable'],
                "pincode" => ['required', 'max:6', 'min:6'],
                "state" => ['required'],
                "stateId" => ['required'],
                "city" => ['required'],
                "cityId" => ['required'],
                "street" => ['nullable'],
                "additionalDetails" => ['required'],
                "businessType" => ['required'],
                "productType" => ['required'],
                "icName" => ['required'],
                "icId" => ['required'],
                "idv" => ['required'],
            ];
            $stage_1["maritalStatus"] = ['nullable'];
            $stage_1["occupation"] = ['nullable'];
            $stage_1["occupationName"] = ['nullable'];
            $stage_1["gender"] = ['nullable'];
            $stage_1["genderName"] = ['nullable'];
            $stage_1["dob"] = ['nullable'];
            $stage_1["inspectionType"] = ['nullable'];
            $stage_1["isCkycDetailsRejected"] = ['nullable'];

            if (in_array($company_alias->company_alias,['iffco_tokio', 'magma']) && (isset($request->fatherName) || isset($request->spouseName)) && ($request->fatherName || $request->spouseName) && (in_array(strtolower($request->fatherName),['not applicable', 'na', 'null', 'not available', '.']) || in_array(strtolower($request->spouseName),['not applicable', 'na', 'null', 'not available', '.']))) {
                return response()->json([
                    'status' => false,
                    'msg' => "Please Enter Valid data in Relative Name.",
                ]);
            }

            if (!empty($request->proprietorName)) {
                $proposer_ckyc_details_data['related_person_name'] = $request->proprietorName;
                $proposer_ckyc_details_data['relationship_type'] = 'proprietorName';
                ProposerCkycDetails::updateOrCreate([
                    'user_product_journey_id' => $enquiry_id
                ], $proposer_ckyc_details_data);            
            }

            if (!empty($request->spouseName)) {
                $proposer_ckyc_details_data['related_person_name'] = $request->spouseName;
                $proposer_ckyc_details_data['relationship_type'] = $request->relationType ?? 'spouseName';
                ProposerCkycDetails::updateOrCreate([
                    'user_product_journey_id' => $enquiry_id
                ], $proposer_ckyc_details_data);            
            }

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $stage_1["ckycNumber"] = ['nullable'];
                $stage_1["ckycType"] = ['nullable'];
                $stage_1["ckycTypeValue"] = ['nullable'];

                $ckyc_data = [
                    'proof_of_identity' => [
                        'poi_identity' => $request->poi_identity ?? '',
                        'poi_panNumber' => $request->poi_panNumber ?? '',
                        'poi_aadharNumber' => $request->poi_aadharNumber ?? '',
                        'poi_gstNumber' => $request->poi_gstNumber ?? '',
                        'poi_passportNumber' => $request->poi_passportNumber ?? '',
                        'poi_drivingLicense' => $request->poi_drivingLicense ?? '',
                        'poi_voterId' => $request->poi_voterId ?? '',
                        'poi_cinNumber' => $request->poi_cinNumber ?? '',
                        'poi_nationalPopulationRegisterLetter' => $request->poi_nationalPopulationRegisterLetter ?? '',
                        'poi_registrationCertificate' => $request->poi_registrationCertificate ?? '',
                        'poi_certificateOfIncorporation' => $request->poi_cretificateOfIncorporaion ?? '',
                        'poi_nrega_job_card' => $request->poi_nregaJobCard ?? '',
                    ],
                    'proof_of_address' => [
                        'poa_identity' => $request->poa_identity ?? '',
                        'poa_panNumber' => $request->poa_panNumber ?? '',
                        'poa_aadharNumber' => $request->poa_aadharNumber ?? '',
                        'poa_gstNumber' => $request->poa_gstNumber ?? '',
                        'poa_passportNumber' => $request->poa_passportNumber ?? '',
                        'poa_voterId' => $request->poa_voterId ?? '',
                        'poa_drivingLicense' => $request->poa_drivingLicense ?? '',
                        'poa_nationalPopulationRegisterLetter' => $request->poa_nationalPopulationRegisterLetter ?? '',
                        'poa_registrationCertificate' => $request->poa_registrationCertificate ?? '',
                        'poa_certificateOfIncorporation' => $request->poa_cretificateOfIncorporaion ?? '',
                        'poa_nrega_job_card' => $request->poa_nregaJobCard ?? '',
                    ]
                ];
                ckycUploadDocuments::updateOrCreate(
                    ['user_product_journey_id' => $enquiry_id],
                    [
                        'cky_doc_data' => json_encode($ckyc_data, JSON_UNESCAPED_SLASHES)
                    ]
                );
            }

            UserProductJourney::where('user_product_journey_id', $enquiry_id)
                ->update([
                    'user_fname'  => $request->firstName.' '.$request->lastName,
                    'user_email'  => $request->email,
                    'user_mobile' => $request->mobileNumber
                ]);

            if (!empty($result) && in_array('maritalStatus', json_decode($result->fields,TRUE))) {
                $stage_1["maritalStatus"] = ['required_if:ownerType,I'];
            }
            if (!empty($result) && in_array('occupation', json_decode($result->fields,TRUE))) {
                $stage_1["occupation"] = ['required_if:ownerType,I'];
                $stage_1["occupationName"] = ['required_if:ownerType,I'];
            }
            if (!empty($result) && in_array('gender', json_decode($result->fields,TRUE))) {
                $stage_1["gender"] = ['required_if:ownerType,I'];
                $stage_1["genderName"] = ['required_if:ownerType,I'];
            }
            if (!empty($result) && in_array('dob', json_decode($result->fields,TRUE))) {
                $stage_1["dob"] = ['required_if:ownerType,I'];
            }
            if (!empty($result) && in_array('email', json_decode($result->fields,TRUE))) {
                $stage_1["email"] = ['required', 'email:rfc'];
            }

            if (!empty($user_proposal) && ($user_proposal->is_ckyc_verified == 'Y') && ($company_alias->company_alias == 'sbi') && (app()->environment() == 'local')) {
                $ckycMetaData = ($user_proposal->ckyc_meta_data) ?? [];
                $ckycAddress = trim(trim($ckycMetaData->addressLine1 ?? '') . (' '. trim($ckycMetaData->addressLine2 ?? '')) . (' '. trim($ckycMetaData->addressLine3 ?? '')));
                if(!empty($ckycAddress) && ($ckycAddress != $request->addressLine1)) {
                    UserProposal::where('user_product_journey_id', $enquiry_id)->update(['is_ckyc_verified' => 'N']);

                    return response()->json([
                        'status' => false,
                        'msg' => "Changes in address will require Re-Verification.",
                        'request' => [
                            'new_address' => $request->addressLine1,
                            'old_address' => $ckycAddress,
                            'is_address_missmatched' => ($ckycAddress != $request->addressLine1)
                        ]
                    ]);
                }
            }

            if($company_alias->company_alias == 'iffco_tokio') {
                $stage_1["panNumber"] = ['nullable', 'regex:/[A-Z]{5}[0-9]{4}[A-Z]{1}/'];
            }

            if(in_array(strtolower($request->ckycType), ['ckyc', 'ckyc_number'])) {
                $stage_1["ckycTypeValue"] = ['required', 'digits_between:14,14'];
            } else if(in_array(strtolower($request->ckycType), ['pan_card', 'pan_number_with_dob'])) {
                $stage_1["ckycTypeValue"] = ['required', 'regex:/[A-Z]{3}[ABCFGHLJPTF]{1}[A-Z]{1}[0-9]{4}[A-Z]{1}/'];

                if($company_alias->company_alias == 'iffco_tokio') {
                    $stage_1["ckycTypeValue"] = ['required', 'regex:/[A-Z]{5}[0-9]{4}[A-Z]{1}/'];
                }

            } else if(strtolower($request->ckycType) == 'aadhar_card') { 
                $stage_1["ckycTypeValue"] = ['required', 'digits_between:12,12'];
            } else if(in_array(strtolower($request->ckycType), ['voter_card', 'voter_id'])) {
                $stage_1["ckycTypeValue"] =  ['required', 'regex:/^[A-Z]{3}[0-9]{7}$/'];
            } else if(in_array(strtolower($request->ckycType), ['passport'])) {
                $stage_1["ckycTypeValue"] = ['required', 'regex:/^[A-Z]{1}[0-9]{7}$/'];
            } else if(in_array(strtolower($request->ckycType), ['gstnumber'])) {
                $stage_1["ckycTypeValue"] = ['required', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}[Z]{1}[0-9A-Z]{1}$/'];
            } else if(in_array(strtolower($request->ckycType), ['driving_license'])) {
                $stage_1["ckycTypeValue"] = ['required'];
            } else if(in_array(strtolower($request->ckycType), ['cinnumber']) && ($request->isCinPresent != 'NO')) {
                $stage_1["ckycTypeValue"] =  ['required', 'regex:/^[LU]{1}[0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/'];
            }
            
            $rules = $stage_1;
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()]);
            }
            $payload= $request->validate($rules);
            $user = \App\Models\Users::updateorCreate(
                ['mobile_no' => $request->mobileNumber],
                [
                    'mobile_no' => $request->mobileNumber,
                    'email' => $request->email,
                    'first_name' => $request->firstName,
                    'last_name' => $request->lastName,
                ]
            );

            $UserData = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id', $enquiry_id)
                // ->where('seller_type', 'U')
                ->whereNull('user_id')
                ->first();

            if (config('constants.motorConstant.IS_USER_ENABLED') == "Y" && $request->firstName != null) {

                // NEW CODE START
                if ($token == '') {
                    //without token code
                    if (!empty($UserData)) {
                        // registered user
                        if ($UserData->agent_mobile ==  $request->mobileNumber) {
                            // same mobile no as on input

                        } else {

                            // diff mobile no as on input
                            $user_creation_data = [
                                'mobile_no' => $request->mobileNumber,
                                'email' => $request->email,
                                'first_name' => $request->firstName,
                                'last_name' => $request->lastName,                    
                            ];

                            if (config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true') {
                                $user_data = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                            } else {
                                $user_data = HTTP::withoutVerifying()->asForm()->withOptions(['proxy' => config('constants.http_proxy')])->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                            }

                            if (isset($user_data['user_id'])) {
                                $user->update(['user_id' => $user_data['user_id']]);
                                CvAgentMapping::updateorCreate(
                                    [
                                        'user_product_journey_id' => $enquiry_id,
                                        // 'seller_type' => /* 'U' */null,
                                    ],
                                    [
                                        'user_product_journey_id' => $enquiry_id,
                                        // 'seller_type' => 'U',
                                        // 'agent_name' => $request->firstName.' '.$request->lastName,
                                        'user_id' => $user_data['user_id'],
                                        // 'agent_mobile' => $request->mobileNumber,
                                        // 'agent_email' => $request->email,
                                        'stage'         => "quote"
                                    ]
                                );
                                if (config('constants.motorConstant.USER_CREATION_MAIL') == "Y") {
                                    if (config('constants.motorConstant.SMS_FOLDER') == 'abibl') {

                                        $name = $request->firstName . ' ' . $request->lastName;
                                        $email = is_array($request->email) ? $request->email[0] : $request->email;
                                        $mailData = ['name' => $name, 'subject' => "Account Successfully Created"];

                                        $html_body = (new \App\Mail\UserCreationMail($mailData))->render();

                                        $input_data = [
                                            "content" => [
                                                "from" =>  [
                                                    "name" => config('mail.from.name'),
                                                    "email" => config('mail.from.address')
                                                ],
                                                "subject" => $mailData['subject'],
                                                "html" => $html_body,
                                            ],
                                            "recipients" => [
                                                [
                                                    "address" => $email
                                                ]
                                            ]
                                        ];
                                        httpRequest('abibl_email', $input_data);
                                    } elseif(config('constants.motorConstant.SMS_FOLDER') == 'sriyah') {
                                        $mailData = [
                                            'logo' => $request->logo ?? "",
                                            'subject' => "Customer Login in nammacover.com"
                                        ];
                                        \Illuminate\Support\Facades\Mail::to($request->email)->send(new \App\Mail\UserCreationMail($mailData));
                                    }
                                }
                            }
                        }
                    } else {
                        // not registered user

                        $user_creation_data = [
                            'mobile_no' => $request->mobileNumber,
                            'email' => $request->email,
                            'first_name' => $request->firstName,
                            'last_name' => $request->lastName,                    
                        ];

                        if (config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true') {
                            $user_data = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                        } else {
                            $user_data = HTTP::withoutVerifying()->asForm()->withOptions(['proxy' => config('constants.http_proxy')])->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                        }

                        if (isset($user_data['user_id'])) {
                            $user->update(['user_id' => $user_data['user_id']]);
                            CvAgentMapping::updateorCreate(
                                [
                                    'user_product_journey_id' => $enquiry_id,
                                    // 'seller_type' => 'U',
                                ],
                                [
                                    'user_product_journey_id' => $enquiry_id,
                                    // 'seller_type' => 'U',
                                    // 'agent_name' => $request->firstName.' '.$request->lastName,
                                    'user_id' => $user_data['user_id'],
                                    // 'agent_mobile' => $request->mobileNumber,
                                    // 'agent_email' => $request->email,
                                    'stage'         => "quote"
                                ]
                            );
                            if (config('constants.motorConstant.USER_CREATION_MAIL') == "Y") {
                                if (config('constants.motorConstant.SMS_FOLDER') == 'abibl') {

                                    $name = $request->firstName . ' ' . $request->lastName;
                                    $email = is_array($request->email) ? $request->email[0] : $request->email;
                                    $mailData = ['name' => $name, 'subject' => "Account Successfully Created"];

                                    $html_body = (new \App\Mail\UserCreationMail($mailData))->render();

                                    $input_data = [
                                        "content" => [
                                            "from" =>  [
                                                "name" => config('mail.from.name'),
                                                "email" => config('mail.from.address')
                                            ],
                                            "subject" => $mailData['subject'],
                                            "html" => $html_body,
                                        ],
                                        "recipients" => [
                                            [
                                                "address" => $email
                                            ]
                                        ]
                                    ];
                                    httpRequest('abibl_email', $input_data);
                                } elseif(config('constants.motorConstant.SMS_FOLDER') == 'sriyah') {
                                    $mailData = [
                                        'logo' => $request->logo ?? "",
                                        'subject' => "Customer Login in nammacover.com"
                                    ];
                                    \Illuminate\Support\Facades\Mail::to($request->email)->send(new \App\Mail\UserCreationMail($mailData));
                                }
                            }
                        }
                    }
                } else {
                    //token code
                    $user_product_journey = UserProductJourney::find($enquiry_id);
                    if ($user_product_journey->lead_id == null || ($request->mobileNumber != $user_product_journey->user_mobile)) {
                        /*
                        $token_data = Http::withoutVerifying()->post(config('constants.motorConstant.TOKEN_VALIDATE_URL'), ['token' => $token, "skip_validation" => "Y"])->json();
                        */

                    if(config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == "true"){
                        $token_data = Http::withoutVerifying()->post(config('constants.motorConstant.TOKEN_VALIDATE_URL'), ['token' => $token, "skip_validation" => "Y"])->json();
                    } else {
                        $token_data = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'),'POST', ['token' => $token, "skip_validation" => "Y"])['response'] ?? "";
                    }

                        \App\Models\UserTokenRequestResponse::create([
                            'user_type' => /* base64_decode( */ $token_data['data']['seller_type'] ?? null,
                            'request' => json_encode(['token' => $token, "skip_validation" => "Y"]),
                            'response' => json_encode($token_data),
                        ]);

                        if (isset($token_data['status']) && $token_data['status'] == "true") {

                            $token_data = $token_data['data'];
                            // if token is there and its not user then create user with prop data 
                            if (isset($token_data['seller_type']) && $token_data['seller_type'] != 'U') {
                                //check for user exist or not
                                if (!empty($UserData)) {
                                    //user exist and mobile is not same
                                    if ($UserData->agent_mobile !=  $request->mobileNumber) {

                                        $user_creation_data = [
                                            'mobile_no' => $request->mobileNumber,
                                            'email' => $request->email,
                                            'first_name' => $request->firstName,
                                            'last_name' => $request->lastName,                    
                                        ];
                
                                        if (config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true') {
                                            $user_data = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                                        } else {
                                            $user_data = HTTP::withoutVerifying()->asForm()->withOptions(['proxy' => config('constants.http_proxy')])->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                                        }

                                        if (isset($user_data['user_id'])) {
                                            $user->update(['user_id' => $user_data['user_id']]);
                                            CvAgentMapping::updateorCreate(
                                                [
                                                    'user_product_journey_id' => $enquiry_id,
                                                    // 'seller_type' => 'U',
                                                ],
                                                [
                                                    'user_product_journey_id' => $enquiry_id,
                                                    // 'seller_type' => 'U',
                                                    // 'agent_name' => $request->firstName.' '.$request->lastName,
                                                    'user_id' => $user_data['user_id'],
                                                    // 'agent_mobile' => $request->mobileNumber,
                                                    // 'agent_email' => $request->email,
                                                    'stage'         => "quote"
                                                ]
                                            );
                                        }
                                    }
                                } else {
                                    // user not exist
                                    $user_creation_data = [
                                        'mobile_no' => $request->mobileNumber,
                                        'email' => $request->email,
                                        'first_name' => $request->firstName,
                                        'last_name' => $request->lastName,                    
                                    ];
            
                                    if (config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true') {
                                        $user_data = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                                    } else {
                                        $user_data = HTTP::withoutVerifying()->asForm()->withOptions(['proxy' => config('constants.http_proxy')])->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), $user_creation_data)->json();
                                    }

                                    if (isset($user_data['user_id'])) {
                                        $user->update(['user_id' => $user_data['user_id']]);
                                        CvAgentMapping::updateorCreate(
                                            [
                                                'user_product_journey_id' => $enquiry_id,
                                                // 'seller_type' => 'U',
                                            ],
                                            [
                                                'user_product_journey_id' => $enquiry_id,
                                                // 'seller_type' => 'U',
                                                // 'agent_name' => $request->firstName.' '.$request->lastName,
                                                'user_id' => $user_data['user_id'],
                                                // 'agent_mobile' => $request->mobileNumber,
                                                // 'agent_email' => $request->email,
                                                'stage'         => "quote"
                                            ]
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
                // NEW CODE END

                /* OLD CODE COMMENTED START
                if(isset($UserData->seller_type) && $UserData->seller_type != 'U')
                {
                    $user_data = HTTP::asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), [
                        'mobile_no' => $request->mobileNumber,
                        'email' => $request->email,
                        'first_name' => $request->firstName,
                        'last_name' => $request->lastName,
                    ])->json();
                    $user->update(['user_id' => $user_data['user_id']]);
                }else
                {
                    if(empty($UserData))
                    {
                        $user_data = HTTP::asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'), [
                            'mobile_no' => $request->mobileNumber,
                            'email' => $request->email,
                            'first_name' => $request->firstName,
                            'last_name' => $request->lastName,
                        ])->json();

                        if(isset($user_data['user_id']))
                        {
                            $user->update(['user_id' => $user_data['user_id']]);
                            CvAgentMapping::updateorCreate(
                                [
                                    'user_product_journey_id' => $enquiry_id,
                                ],
                                [
                                'user_product_journey_id' => $enquiry_id,
                                'seller_type' => 'U',
                                'agent_name' => $request->firstName.' '.$request->lastName,
                                'agent_id' => $user_data['user_id'],
                                'agent_mobile' => $request->mobileNumber,
                                'agent_email' => $request->email,
                                'stage'         => "quote"
                            ]);   
                        }
                    }else
                    {
                        $user_data['user_id'] = $UserData->agent_id;
                    }
                }
                OLD CODE COMMENTED END */
                // if($user_data['user_id'] != $UserData->agent_id)
                // {
                //     CvAgentMapping::updateorCreate(
                //             [
                //                 'user_product_journey_id' => $enquiry_id,
                //                 'seller_type' => 'U',
                //             ],
                //         [
                //         'user_product_journey_id' => $enquiry_id,
                //         'seller_type' => 'U',
                //         'agent_name' => $request->firstName.' '.$request->lastName,
                //         'agent_id' => $user_data['user_id'],
                //         'agent_mobile' => $request->mobileNumber,
                //         'agent_email' => $request->email,
                //         'stage'         => "quote"
                //     ]);
                // }
            }
            //    $cv_agent_mapping_payload = $request->validate([
            //        'sellerType' => ['nullable', 'in:E,P'],
            //        'agentId' => [Rule::requiredIf(function () use ($request) {
            //            return in_array($request->sellerType, ['E', 'P']);
            //        })],
            //        'agentName' => [Rule::requiredIf(function () use ($request) {
            //            return in_array($request->sellerType, ['E', 'P']);
            //        })],
            //        'agentMobile' => [Rule::requiredIf(function () use ($request) {
            //            return in_array($request->sellerType, ['E', 'P']);
            //        })],
            //        'agentEmail' => [Rule::requiredIf(function () use ($request) {
            //            return in_array($request->sellerType, ['E', 'P']);
            //        }), 'email'],
            //    ]);

            //if(config('POS_VALIDATE_MOBILE_ENABLED') == 'Y') 

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
                $mobile_request->merge(['mobile_number' => $request->mobileNumber]); 
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
            
            //if(config('POS_VALIDATE_EMAIL_ENABLED') == 'Y') 
            if(config('EMAIL_USAGE_LIMIT_VALIDATION_ENABLE') == 'Y' && $pos_data)
            {  
                $email_request = new \Illuminate\Http\Request();
                $email_request->merge(['email_id' => $request->email]); 
                $validateEmailStatus = \App\Http\Controllers\GenericController::agentEmailValidator($email_request);
                //dd($validateEmailStatus);
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
        }

        if ($request->stage == "2") {
            $payload = $request->validate([
                "userProductJourneyId" => ['required'],
                'nomineeName' => ['nullable'],
                'nomineeAge' => ['nullable'],
                'nomineeDob' => ['nullable'],
                'nomineeRelationship' => ['nullable'],
                "additionalDetails" => ['required']
            ]);
        }

        if ($request->stage == "3") {
            $arr = [];
            if (!empty($result) && in_array('hypothecationCity', json_decode($result->fields,true))) {

                $arr["financerLocation"] = [Rule::requiredIf($request->isVehicleFinance)];
                $arr["hypothecationCity"] = [Rule::requiredIf($request->isVehicleFinance)];
            } else {
                $arr["hypothecationCity"] = ['nullable'];
                $arr["financerLocation"] = ['nullable'];
            }
            // if($request->isVehicleFinance){
            //     $arr['fullNameFinance'] = [Rule::requiredIf($request->isVehicleFinance)];
            // }
            if(isset($request->hazardousType) && $request->hazardousType == 'Hazardous')
            {
                return response()->json([
                    'status' => false,
                    'msg' => "Insurance of policies for Hazardous goods is prohibitted.",
                ]);
            }
            if(isset($request->fullNameFinance))
            {
               $arr["fullNameFinance"] = ['nullable']; 
            }
            $payload = $request->validate(array_merge($arr, [
                "userProductJourneyId" => ['required'],
                "vehicaleRegistrationNumber" => [
                    function ($attribute, $value, $fail) {
                        if ($value != "NEW") {
                            $value_array = explode('-', $value);
                            if ($value_array[0] == null && $value_array[1] == null && ($value_array[2] == null || $value_array[2] == '') && $value_array[3] == null) {
                                $fail($attribute . ' is invalid.');
                            }
                            // if (!preg_match("/^[A-Z]{2}[-\s][0-9]{1,2}[-\s][A-Z0-9]{0,3}[-\s][0-9]{3,4}$/i", $value) && !preg_match("/^[A-Z]{2}[-\s][0-9]{1,2}[-\s][0-9]{3,4}$/i", $value)) {
                            //     $fail($attribute . ' is invalid.');
                            // }
                        }
                    }
                ],
                "rtoLocation" => ['required'],
                "engineNumber" => ['required'],
                "vehicleManfYear" => ['required'],
                "chassisNumber" => ['required'],
                "vehicleColor" => ['nullable'],
                "isValidPuc" => ['nullable'],
                "isVehicleFinance" => ['required', 'boolean'],
                "isCarRegistrationAddressSame" => ['required', 'boolean'],
                "carRegistrationAddress1" => [Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "carRegistrationAddress2" => [Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "carRegistrationAddress3" => ['nullable'], //[Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "carRegistrationPincode" => [Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "carRegistrationState" => [Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "carRegistrationStateId" => [Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "carRegistrationCity" => [Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "carRegistrationCityId" => [Rule::requiredIf(!$request->isCarRegistrationAddressSame)],
                "nameOfFinancer" => [Rule::requiredIf($request->isVehicleFinance)],
                "financerAgreementType" => [Rule::requiredIf($request->isVehicleFinance)],
                "additionalDetails" => ['required'],
                "carOwnership" => ['nullable'],
                "policyOwner" => ['nullable'],
                "vehicleCategory" => [Rule::requiredIf(function () use ($quoteData) {
                    return $quoteData->ic_id == 20 && $quoteData->product_sub_type_id == 6;
                }), 'numeric'],
                "vehicleUsageType" => [Rule::requiredIf(function () use ($quoteData) {
                    return $quoteData->ic_id == 20 && $quoteData->product_sub_type_id == 6;
                }), 'numeric'],
                "pucNo" => ['nullable'],
                "pucExpiry" => ['nullable'],
                "inspectionType" => ['nullable'],
            ]));
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)->update([
                'manufacture_year' => $payload['vehicleManfYear'],
                'vehicle_registration_no' => $payload['vehicaleRegistrationNumber']
            ]);
            $payload['vehicaleRegistrationNumber'] = removingExtraHyphen($payload['vehicaleRegistrationNumber']);
        }

        if ($request->stage == "4") {
            $rules = [
                "userProductJourneyId" => ['required'],
                "InsuranceCompanyName" => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->business_type != 'newbusiness' && $requestData->previous_policy_type != 'Not sure';
                })],
                "previousInsuranceCompany" => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->business_type != 'newbusiness' && $requestData->previous_policy_type != 'Not sure';
                })],
                "prevPolicyExpiryDate" => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->business_type != 'newbusiness' && $requestData->previous_policy_type != 'Not sure';
                })],
                "previousPolicyNumber" => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->business_type != 'newbusiness' && $requestData->previous_policy_type != 'Not sure';
                })],
                // "previousInsurerAddress" => ['required'],
                // "previousInsurerPin" => ['required'],
                "previousPolicyStartDate" => ['nullable'],
                "additionalDetails" => ['required'],
                'tpStartDate' => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->policy_type == 'own_damage';
                })],
                'tpEndDate' => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->policy_type == 'own_damage';
                })],
                'tpInsuranceCompany' => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->policy_type == 'own_damage';
                })],
                'tpInsuranceCompanyName' => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->policy_type == 'own_damage';
                })],
                'tpInsuranceNumber' => [Rule::requiredIf(function () use ($requestData) {
                    return $requestData->policy_type == 'own_damage';
                })],
                "previousPolicyType" => ['nullable'],
                "applicableNcb" => ['nullable'],
                "previousNcb" => ['nullable'],
                "isClaim" => ['nullable'],
                "previousPolicyAddonsList" => ['nullable'],
            ];
            if (
                !empty($request->tpStartDate) && !empty($request->tpEndDate) && $requestData->policy_type == 'own_damage' &&
                isset($quoteData->product_sub_type_id) &&
                in_array($quoteData->product_sub_type_id, [1, 2], true)
            ) {
                $tpStartDate = Carbon::parse($request->tpStartDate);
                $tpEndDate = Carbon::parse($request->tpEndDate);
                $expectedStartDate = $tpEndDate->addDay()->subYears($quoteData->product_sub_type_id == 1 ? 3 : 5);
                if (!$tpStartDate->equalTo($expectedStartDate)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid TP Start Date or TP End Date.'
                    ]);
                }
            }
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()]);
            }
            $payload=$request->validate($rules);
        }
        $journeyStage = JourneyStage::where('user_product_journey_id', $enquiry_id)->first();
        if (isset($journeyStage->stage) && $journeyStage->stage == STAGE_NAMES['PAYMENT_INITIATED']) {
            return response()->json([
                'status' => false,
                'message' => STAGE_NAMES['PAYMENT_INITIATED']
            ], 500);
        } else if (isset($journeyStage->stage) && ($journeyStage->stage == STAGE_NAMES['POLICY_ISSUED'] || $journeyStage->stage == STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] || strtolower($journeyStage->stage) == STAGE_NAMES['PAYMENT_SUCCESS'] || $journeyStage->stage == STAGE_NAMES['PAYMENT_INITIATED'])) {
            return response()->json([
                'status' => false,
                'message' => 'Transaction Already Completed',
                'data' => $journeyStage
            ], 500);
        }

        if(isset($request->step) && !empty($request->step) && $request->step == 5)
        {
            $isAmlDocSubmitted = false;
            if($request->companyAlias == 'shriram' || ($request->companyAlias == 'bajaj_allianz' && config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') != 'Y')) {
                UserProposal::where('user_product_journey_id',$enquiry_id)->update(['ckyc_type'=>$request->mode]);
            }

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $proposer_ckyc_details_data = [];

                if ($request->fatherName) {
                    $proposer_ckyc_details_data['related_person_name'] = $request->fatherName;
                    $proposer_ckyc_details_data['relationship_type'] = $request->relationType ?? 'fatherName';
                }

                if ($request->step == '5') {
                    $proposer_ckyc_details_data['is_document_upload'] = ! empty($request->file()) && $request->mode == 'documents' ? 'Y' : 'N';
                }

                if ($user_proposal?->user_proposal_id) {
                    $proposer_ckyc_details_data['user_proposal_id'] = $user_proposal->user_proposal_id;
                }

                if ( ! empty($proposer_ckyc_details_data)) {
                    ProposerCkycDetails::updateOrCreate([
                        'user_product_journey_id' => $enquiry_id
                    ], $proposer_ckyc_details_data);
                }
            }

            #$payload['user_product_journey_id'] = customDecrypt($request->userProductJourneyId);
            if (!empty($request->file())) {
                if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                    if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $request->userProductJourneyId)) {
                        \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/' . $request->userProductJourneyId);
                    }
                }

                $checkICDocumentExists = self::checkICDocumentExists($request, $user_proposal);

                if(!($checkICDocumentExists['status'])) {
                    return response()->json($checkICDocumentExists);
                }

                if ($request->hasFile('panFile')) {
                    $isAmlDocSubmitted = true;
                    $file_name = 'pan_card';
                    $file = $request->file('panFile');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;
                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (!in_array(strtolower($ext),['pdf','png']) && $request->companyAlias == 'shriram') {
                        return response()->json([
                            'status' => false,
                            'msg' => "Please upload pan card in PDF or PNG format.",
                        ]);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/pan_document',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/pan_document', $filename);
                }

                if ($request->hasFile('poi_panCard')) {
                    $isAmlDocSubmitted = true;
                    $file = $request->file('poi_panCard');
                    $file_name = 'pan_card';
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }

                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload pan card in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poi',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poi', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }
    
                if ($request->hasFile('poi_aadharCard')) {
                    $file_name = 'aadhar_card';
                    $file = $request->file('poi_aadharCard');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload aadhar card in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poi',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poi', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }
    
                if ($request->hasFile('poi_gst_certificate')) {
                    $file_name = 'gst_doc';
                    $file = $request->file('poi_gst_certificate');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload gst certificate in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poi',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poi', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
    
                }
                if ($request->hasFile('poa_panCard')) {
                    $isAmlDocSubmitted = true;
                    $file_name = 'pan_card';
                    $file = $request->file('poa_panCard');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload pan card in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }
    
                // if ($request->hasFile('poa_aadharCard')) {
                //     $file_name = 'aadhar_card';
                //     $file = $request->file('poa_aadharCard');
                //     $ext = $file->getClientOriginalExtension();
                //     $filename = $request->userProductJourneyId.'.'.$ext;

                //     $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                //     if(!$checkAllowedFileExtentions['status'])
                //     {
                //         return response()->json($checkAllowedFileExtentions);
                //     }
                //     if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                //         if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                //             return response()->json([
                //                 'status' => false,
                //                 'msg' => "Please upload aadhar card in PDF, PNG or XLSX format.",
                //             ]);
                //         }
                //         self::storeCkycDocument(
                //             $file,
                //             'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                //             $filename
                //         );
                //         // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                //     } else {
                //         self::storeCkycDocument(
                //             $file,
                //             'ckyc_photos/' . $request->userProductJourneyId,
                //             $filename
                //         );
                //         // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                //     }
    
                // }
                if ($request->hasFile('poi_gst_certificate')) {
                    $file_name = 'gst_doc';
                    $file = $request->file('poi_gst_certificate');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload gst certificate in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poi',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poi', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }
                if ($request->hasFile('poa_gst_certificate')) {
                    $file_name = 'gst_doc';
                    $file = $request->file('poa_gst_certificate');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload gst certificate in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }
                if ($request->hasFile('poa_passport_image')) {
                    $file_name = 'passport';
                    $file = $request->file('poa_passport_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload passport in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }

                if ($request->hasFile('poi_passport_image')) {
                    $file_name = 'passport';
                    $file = $request->file('poi_passport_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }

                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if ( ! in_array(strtolower($ext), ['pdf', 'png', 'xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload passport in PDF, PNG or XLSX format.",
                            ]);
                        }

                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                            $filename
                        );

                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poi', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }

                if ($request->hasFile('poa_voter_card')) {
                    $file_name = 'voter_card';
                    $file = $request->file('poa_voter_card');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload voter card in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
    
                }

                if ($request->hasFile('poa_voter_id')) {
                    $file_name = 'voter_card';
                    $file = $request->file('poa_voter_id');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload voter card in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }

                if ($request->hasFile('poi_voter_card') || $request->hasFile('poi_voter_id')) {
                    $file_name = 'voter_card';
                    if ($request->hasFile('poi_voter_card')) {
                        $file = $request->file('poi_voter_card');
                    } else {
                        $file = $request->file('poi_voter_id');
                    }
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }

                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if ( ! in_array(strtolower($ext), ['pdf', 'png', 'xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload voter card in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                            $filename
                        );

                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poi', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }

                if ($request->hasFile('poa_driving_license')) {
                    $file_name = 'driving_license';
                    $file = $request->file('poa_driving_license');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload driving license in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
    
                }

                if ($request->hasFile('poi_cinNumber')) {
                    $file_name = 'cin_number';
                    $file = $request->file('poi_cinNumber');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                }

                if ($request->hasFile('poi_driving_license')) {
                    $file_name = 'driving_license';
                    $file = $request->file('poi_driving_license');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }

                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if ( ! in_array(strtolower($ext), ['pdf', 'png', 'xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload driving license in PDF, PNG or XLSX format.",
                            ]);
                        }

                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poi', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }

                if ($request->hasFile('poi_nrega_job_card_image')) {
                    $file_name = 'nrega_job_card';
                    $file = $request->file('poi_nrega_job_card_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poi', $filename);
                }

                if ($request->hasFile('poa_nrega_job_card_image')) {
                    $file_name = 'nrega_job_card';
                    $file = $request->file('poa_nrega_job_card_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poa',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poa', $filename);
                }

                if ($request->hasFile('poi_national_population_register_letter_image')) {
                    $file_name = 'national_population_register_letter';
                    $file = $request->file('poi_national_population_register_letter_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poi', $filename);
                }

                if ($request->hasFile('poa_national_population_register_letter_image')) {
                    $file_name = 'national_population_register_letter';
                    $file = $request->file('poa_national_population_register_letter_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poa',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poa', $filename);
                }

                if ($request->hasFile('poa_registration_certificate_image')) {
                    $file_name = 'registration_certificate';
                    $file = $request->file('poa_registration_certificate_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poa',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poa', $filename);
                }

                if ($request->hasFile('poa_certificate_of_incorporation_image')) {
                    $file_name = 'certificate_of_incorporation';
                    $file = $request->file('poa_certificate_of_incorporation_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poa',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poa', $filename);
                }

                if ($request->hasFile('poi_certificate_of_incorporation_image')) {
                    $file_name = 'certificate_of_incorporation';
                    $file = $request->file('poi_certificate_of_incorporation_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poi', $filename);
                }

                if ($request->hasFile('poi_registration_certificate_image')) {
                    $file_name = 'registration_certificate';
                    $file = $request->file('poi_registration_certificate_image');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId . '.' . $ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId . '/poi',
                        $filename
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/poi', $filename);
                }

                if (in_array($request->companyAlias, ['shriram', 'royal_sundaram', 'tata_aig']) && $request->hasFile('form60')) {
                    $isAmlDocSubmitted = true;
                    $form60 = $request->file('form60');
                    $ext = $form60->getClientOriginalExtension();

                    
                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias, 'form60');
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }

                    $file = $request->file('form60');
                    $file_name = $request->userProductJourneyId.'.'.$ext;
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId.'/form60',
                        $file_name
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/form60', $file_name);
                    $file_name = 'form60';
                }

                #for updating pos_aadhare after form60 for TATA
                if ($request->hasFile('poa_aadharCard')) {
                    $file_name = 'aadhar_card';
                    $file = $request->file('poa_aadharCard');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $request->userProductJourneyId.'.'.$ext;

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (!in_array(strtolower($ext),['pdf','png','xlsx']) && $request->companyAlias == 'shriram') {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload aadhar card in PDF, PNG or XLSX format.",
                            ]);
                        }
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId.'/poa',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/poa', $filename);
                    } else {
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId, $filename);
                    }
                }

                if (in_array($request->companyAlias, ['royal_sundaram']) && $request->hasFile('form49a')) {
                    $isAmlDocSubmitted = true;
                    $form49a = $request->file('form49a');
                    $ext = $form49a->getClientOriginalExtension();

                    
                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias, 'form49a');
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }

                    $file = $request->file('form49a');
                    $file_name = $request->userProductJourneyId.'.'.$ext;
                    self::storeCkycDocument(
                        $file,
                        'ckyc_photos/' . $request->userProductJourneyId.'/form49a',
                        $file_name
                    );
                    // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/form49a', $file_name);
                }
                if($request->hasFile('photo'))
                {
                    $photo = $request->file('photo');
                    $ext = $photo->getClientOriginalExtension();
                    $file_name = 'photo';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if ($request->companyAlias == 'shriram') {
                        if (in_array(strtolower($ext),['pdf','png','xlsx'])) {
                            $file = $request->file('photo');
                            $filename = $request->userProductJourneyId.'.'.$ext;
                            self::storeCkycDocument(
                                $file,
                                'ckyc_photos/' . $request->userProductJourneyId.'/photos',
                                $filename
                            );
                            // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId.'/photos', $filename);
                        } else {
                            return response()->json([
                                'status' => false,
                                'msg' => "Please upload photograph in PDF, PNG or XLSX format.",
                            ]);
                        }

                    } elseif ($request->companyAlias == 'sbi') {
                        $file = $request->file('photo');
                        $filename = $request->userProductJourneyId . '.' . $ext;
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/' . $request->userProductJourneyId . '/photos',
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/' . $request->userProductJourneyId . '/photos', $filename);

                    } elseif (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp']))
                    {
                        $file = $request->file('photo');
                        $filename = $request->userProductJourneyId.'.'.$ext;
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/'.$request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/'.$request->userProductJourneyId,$filename);

                        // \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/'.$request->userProductJourneyId); // for deletcing directory

                    }else
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => "Please upload photograph in JPG or BMP format.",
                        ]);

                    }


                }
                elseif($request->hasFile('form60') && !in_array($request->companyAlias, ['royal_sundaram', 'tata_aig']))
                {
                    $form60 = $request->file('form60');
                    $ext = $form60->getClientOriginalExtension();

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status'])
                    {
                        return response()->json($checkAllowedFileExtentions);
                    }
                    if(in_array(strtolower($ext),['jpg','jpeg','bmp']))
                    {
                        $file = $request->file('form60');
                        $filename = $request->userProductJourneyId.'.'.$ext;
                        self::storeCkycDocument(
                            $file,
                            'ckyc_photos/'.$request->userProductJourneyId,
                            $filename
                        );
                        // $file->storeAs('ckyc_photos/'.$request->userProductJourneyId,$filename);

                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal saved successfully",
                        ]);

                        // \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/'.$request->userProductJourneyId); // for deletcing directory

                    }else
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => "Please upload photograph in JPG or BMP format.",
                        ]);

                    }


                }

                sleep(1);
                if(!isset($file_name))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => "File not found",
                    ]);
                }
                ckycUploadDocuments::updateOrCreate(
                    ['user_product_journey_id' => $enquiry_id],
                    [
                        'doc_name' => $file_name,
                        'doc_type' => $ext
                    ]
                );
                if (config('SHRIRAM_AML_ENABLED') == 'Y') {
                    switch(strtoupper($section)) {
                        case 'CAR' :
                            $amlEnabled = config('constants.motor.shriram.SHRIRAM_CAR_JSON_REQUEST_TYPE') == 'JSON';
                            break;
                        case 'BIKE' :
                            $amlEnabled = config('constants.motor.shriram.SHRIRAM_BIKE_JSON_REQUEST_TYPE') == 'JSON';
                            break;
                        case 'GCV' :
                            $amlEnabled = false;
                            break;
            
                        default :
                            if ($parentId != 4) {
                                $amlEnabled = config('constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE') == 'JSON';
                            }
                    }
                }

                if ($amlEnabled && in_array($request->companyAlias, ['shriram']) && !$isAmlDocSubmitted) {
                    return response()->json([
                        'status' => false,
                        'msg' => "Please upload Form 60 or Pan Card",
                    ]);
                }
                // if (in_array($request->companyAlias, ['godigit'])){
  
                //     UserProposal::where('user_product_journey_id', $enquiry_id)->update(['is_ckyc_verified' => 'N']);
                // }

                return response()->json([
                    'status' => true,
                    'msg' => "Proposal saved successfully",
                ]);
            }
            return response()->json([
                'status' => true,
                'msg' => "Step 5 : No data to save the form",
            ]);
        }
        
        if (empty($payload)) {
            return response()->json([
                'status' => false,
                'msg' => "No data to save the form",
            ]);
        }
        $proposal_present = UserProposal::select('proposal_stage')
            ->where('user_product_journey_id', $enquiry_id)
            ->first();


        if ($proposal_present && !empty($proposal_present->proposal_stage)) {
            $proposal_stage = json_decode($proposal_present->proposal_stage, true);
        } else {
            $proposal_stage = [
                'ownerDetails' => false,
                'nomineeDetails' => false,
                'vehicleDetails' => false,
                'previousPolicyDetails' => false,
            ];
        }
        if (!empty($request->stage)) {
            switch ($request->stage) {
                case '1':
                    $proposal_stage['ownerDetails'] = true;
                    break;
                case '2':
                    $proposal_stage['nomineeDetails'] = true;
                    break;
                case '3':
                    $proposal_stage['vehicleDetails'] = true;
                    break;
                case '4':
                    $proposal_stage['previousPolicyDetails'] = true;
                    break;
            }
        }

        foreach ($payload as $key => $payloadData) {
            if (gettype($payloadData) == 'string') {
                $payload[$key] = str_replace('', '', $payloadData);
                $payload[$key] = preg_replace('~\x{00AD}~u', '', $payloadData);
            }

            if (gettype($payloadData) == 'boolean') {
                $payload[$key] = $payloadData ? '1' : '0';
            }
        }

        //store the pprevious policy start date in db
        if (!empty($payload['previousPolicyStartDate'])) {
            $payload['prevPolicyStartDate'] = $payload['previousPolicyStartDate'];
        }

        unset($payload['previousPolicyStartDate']);

        $payload = snakeCase($payload);
        $payload['proposal_stage'] = json_encode($proposal_stage);
        $payload['additional_details'] = json_encode($request->additionalDetails);
        $payload['user_product_journey_id'] = $enquiry_id;
        // if (!empty($user_proposal->vehicale_registration_number)) {
        //     $payload['vehicale_registration_number'] = removingExtraHyphen($user_proposal->vehicale_registration_number);
        // } 

        array_walk_recursive($payload, function (&$payload) {
            $payload = (strip_tags($payload));
        });

        if ($request->ownerType == 'C' && $request->stage == '1') {
            if (!isset($payload['last_name']) || empty($payload['last_name'])) {
                $payload['last_name'] = NULL;
            }
        }
        // if (in_array($company_alias->company_alias, ['godigit'])){
        //         $payload['is_ckyc_verified'] = 'N';
        // }
        $is_saved = UserProposal::updateOrCreate(['user_product_journey_id' => $payload['user_product_journey_id']], $payload);
        if ($user_proposal) {
            $user_proposal = $user_proposal->refresh();
        }
        if(empty($user_proposal->engine_number) && $request->stage == "3")
        {
            return response()->json([
                'status' => false,
                'message' => 'Please enter the Engine Number',
            ]);
        }
        if(empty($user_proposal->chassis_number) && $request->stage == "3")
        {
            return response()->json([
                'status' => false,
                'message' => 'Please enter the Chassis Number',
            ]);
        }
        if (!empty($is_saved) && config('ENGINE_AND_CHASSIS_USAGE_LIMIT_VALIDATION_ENABLE') === 'Y'  && $request->stage == "3") {

            $business_type =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)->pluck('business_type');
            $engine_and_chassis_request = new \Illuminate\Http\Request();
            $engine_and_chassis_request->merge([
                'engine_number' => $user_proposal->engine_number,
                'chassis_number' => $user_proposal->chassis_number,
                'business_type' => $business_type,
                'enquiry_id' => $enquiry_id,
            ]);
            $utility = new \App\Http\Controllers\Extra\UtilityApi();
            $validateEngineAndChassisStatus = $utility->ChassisEngineCheck($engine_and_chassis_request);
            $data = $validateEngineAndChassisStatus->getData(true);

            if (isset($data['status']) && $data['status'] == false) {
                return response()->json([
                    'status' => false,
                    'msg' => $data['message'],
                ]);
            }
        }
        $proposal_url = JourneyStage::where(['user_product_journey_id' => $payload['user_product_journey_id']])->first()->proposal_url;

        if ($request->ownerType == 'C' && $request->stage == '1' && ! empty($request->organizationType)) {
            ProposerCkycDetails::updateOrCreate([
                'user_product_journey_id' => $payload['user_product_journey_id']
            ], [
                'user_proposal_id' => $is_saved->user_proposal_id ?? null,
                'organization_type' => $request->organizationType
            ]);
        }
        //If user changes previous policy on proposal page then on quote page that IC should not be visible - #8325
        if ($request->has('previousInsuranceCompany') && $request->stage == '4') {
            $fyntune_ic = getPreviousIcMapping($request->previousInsuranceCompany);
            if ($fyntune_ic && !empty($fyntune_ic->masterCompany->company_name ?? '')) {
                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $payload['user_product_journey_id'])->update([
                    'previous_insurer' => $fyntune_ic->masterCompany->company_name,
                    'previous_insurer_code' => $fyntune_ic->company_alias
                ]);
            }
        }

        if (config('TOKEN_VALIDATE_PROPOSAL_LEVEL') == 'Y') {
            if (\Illuminate\Support\Str::contains($proposal_url, 'token') && config('constants.motorConstant.SMS_FOLDER') != 'gramcover') {
                parse_str($proposal_url, $output);
                $token = $output['token'];
                /*
                $token_data = Http::withoutVerifying()->post(config('constants.motorConstant.TOKEN_VALIDATE_URL'), ['token' => $token, "skip_validation" => "Y"])->json();
                */

            if(config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == "true"){
                $token_data = Http::withoutVerifying()->post(config('constants.motorConstant.TOKEN_VALIDATE_URL'), ['token' => $token, "skip_validation" => "Y"])->json();
            } else {
                $token_data = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'),'POST', ['token' => $token, "skip_validation" => "Y"])['response'] ?? "";
            }

                \App\Models\UserTokenRequestResponse::create([
                    'user_type' => /* base64_decode( */ $token_data['data']['seller_type'] ?? null,
                    'request' => json_encode(['token' => $token, "skip_validation" => "Y"]),
                    'response' => json_encode($token_data),
                ]);

                if (isset($token_data['status']) && $token_data['status'] == "true") {
                    $token_data = $token_data['data'];
                    CvAgentMapping::updateOrCreate([/* 'seller_type' => $token_data['seller_type'],  */'user_product_journey_id' => $payload['user_product_journey_id']], [
                        'seller_type' => $token_data['seller_type'] ?? null,
                        'agent_id' => $token_data['seller_id'] ?? null,
                        'user_name' => $token_data['user_name'] ?? null,
                        'agent_name' => $token_data['seller_name'] ?? null,
                        'agent_mobile' => $token_data['mobile'] ?? null,
                        'agent_email' => $token_data['email'] ?? null,
                        'aadhar_no' => $token_data['aadhar_no'] ?? null,
                        'pan_no' =>  $token_data['pan_no'] ?? null,
                        'category' =>  $token_data['category'] ?? null,
                    ]);
                } else {
                    $token_data['status'] = $token_data['status'] == 'true' ? true : false;
                    $token_data['msg'] = isset($token_data['message']) ? $token_data['message'] : null;
                    return response()->json($token_data);
                }
            }
        }

        if ($request->stage == '1') {
            CvAgentMapping::where('user_product_journey_id', $payload['user_product_journey_id'])
                ->update(
                    [
                        'ic_name' => $request->icName,
                        'ic_id' => $request->icId,
                        'user_proposal_id' => $is_saved->user_proposal_id,
                        'stage' => 'proposal'
                    ]
                );

            $user_product_journey = UserProductJourney::find($payload['user_product_journey_id']);
            $old_mobile_number = '';

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y') {
                $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

                $old_mobile_number = $lsq_journey_id_mapping ? $lsq_journey_id_mapping->phone : '';
            }

            UserProductJourney::where('user_product_journey_id', $payload['user_product_journey_id'])->update([
                'user_fname'  => $request->firstName,
                'user_lname'  => $request->lastName,
                'user_email'  => $request->email,
                'user_mobile' => $request->mobileNumber
            ]);

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y') {
                if ($lsq_journey_id_mapping) {
                    retrieveLsqLead($payload['user_product_journey_id']);
                    updateLsqLead($payload['user_product_journey_id']);

                    if ($old_mobile_number != $request->mobileNumber) {
                        createLsqActivity($payload['user_product_journey_id'], (!is_null($lsq_journey_id_mapping->opportunity_id) ? 'opportunity' : 'lead'), 'Mobile Number Changed', [
                            'old_mobile_number' => $user_product_journey->user_mobile,
                            'new_mobile_number' => $request->mobileNumber
                        ]);
                    }
                }
            }
        }

        if ($request->stage == 3) {
            $user_product_journey = UserProductJourney::find($payload['user_product_journey_id']);
            $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y') {
                $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

                if ($corporate_vehicles_quote_request->business_type != 'newbusiness' && $lsq_journey_id_mapping) {
                    if ($lsq_journey_id_mapping && is_null($lsq_journey_id_mapping->opportunity_id)) {
                        createLsqOpportunity($payload['user_product_journey_id'], NULL, [
                            'rc_number' => $request->vehicaleRegistrationNumber
                        ]);
                    } else {
                        if ($lsq_journey_id_mapping->rc_number != $request->vehicaleRegistrationNumber) {
                            updateLsqOpportunity($payload['user_product_journey_id'], NULL, [
                                'rc_number' => $request->vehicaleRegistrationNumber
                            ]);

                            if (!is_null($lsq_journey_id_mapping->rc_number)) {
                                createLsqActivity($payload['user_product_journey_id'], NULL, 'RC Changed', [
                                    'old_rc_number' => $lsq_journey_id_mapping->rc_number,
                                    'new_rc_number' => $request->vehicaleRegistrationNumber
                                ]);
                            }
                        }
                    }
                }
            }
            $enable_rc_verification = config('NEW_INDIA_RC_VERIFICATION_ENABLE') == 'N' ? false : true;
            if($company_alias->company_alias == 'new_india'
            && $enable_rc_verification && !empty($request->vehicaleRegistrationNumber)
            && strtoupper($request->vehicaleRegistrationNumber != 'NEW'))
            {
                include_once app_path().'/Helpers/IcHelpers/NewIndiaHelper.php';
                $vahan_data = [
                    'reg_no'        => $request->vehicaleRegistrationNumber,
                    'enquiry_id'    => $enquiry_id,
                    'section'       => $section
                ];
                $RcVerification = RcVerification((object) $vahan_data);
                if(!$RcVerification['status'])
                {
                    return response()->json($RcVerification);
                }
            }
        }

        if ($request->stage == '4') {

            if (isset($request->additionalDetails['prepolicy']['applicableNcb']) && $request->additionalDetails['prepolicy']['applicableNcb'] != NULL) {
                $previousNcb = $request->additionalDetails['prepolicy']['previousNcb'];
                $applicableNcb = $request->additionalDetails['prepolicy']['applicableNcb'];
                $isClaim = $request->additionalDetails['prepolicy']['isClaim'];
                if ($isClaim == "Y") {
                    $applicableNcb = 0;
                }
                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $payload['user_product_journey_id'])->update([
                    'previous_ncb'    => $previousNcb,
                    'applicable_ncb'  => $applicableNcb,
                    'is_claim'        => $isClaim,
                ]);
            }
        }

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $proposer_ckyc_details_data = [];

            if ($request->fatherName) {
                $proposer_ckyc_details_data['related_person_name'] = $request->fatherName;
                $proposer_ckyc_details_data['relationship_type'] = $request->relationType ?? 'fatherName';
            }

            if ($request->step == '5') {
                $proposer_ckyc_details_data['is_document_upload'] = ! empty($request->file()) && $request->mode == 'documents' ? 'Y' : 'N';
            }

            if ($is_saved?->user_proposal_id) {
                $proposer_ckyc_details_data['user_proposal_id'] = $is_saved->user_proposal_id;
            }

            if ( ! empty($proposer_ckyc_details_data)) {
                ProposerCkycDetails::updateOrCreate([
                    'user_product_journey_id' => $enquiry_id
                ], $proposer_ckyc_details_data);
            }

            if ($request->step == '5' && $request->mode != 'documents' && $request->companyAlias == 'bajaj_allianz' && config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y') {
                UserProposal::where('user_product_journey_id', $enquiry_id)-> update([
                    'ckyc_type' => $request->mode
                ]);
            }
        }

        if ($is_saved && config('bajaj_crm_data_push') == "Y") {
            bajajCrmDataUpdate($request);
        }
        if ($request->stage == "1") {
            $data['user_product_journey_id'] = $enquiry_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_DRAFTED'];
            updateJourneyStage($data);
        }

        if ($is_saved) {
            event(new \App\Events\ProposalSaved($enquiry_id, $request->stage));
        }
        list($status, $msg) = $is_saved
            ? [true, "Proposal saved successfully"]
            : [false, "Something went wrong please try again"];

        return response()->json([
            'status' => $status,
            'msg' => $msg,
        ]);
    }

    public function submit(Request $request)
    {
        $request->validate([
            'userProductJourneyId' => ['required'],
            'policyId' => ['required'],
            'companyAlias' => ['required'],
        ]);
        $enquiry_id = customDecrypt($request->userProductJourneyId);

        $agentController = new \App\Http\Controllers\AgentValidateController($enquiry_id);
        $isEvPOS = $agentController->isEvPOS();

        if ($isEvPOS) {

            $isEvProductSubType = $agentController->isEvProductSubType();
            $isEvFuelType = $agentController->isEvFuelType();

            if (!$isEvProductSubType || !$isEvFuelType) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product Sub Type Not Allowed Or Fuel Type Mismatch',
                ]);
            }
        }
        if(config('JOURNEY_AGENT_VALIDATION') == 'Y') {
            $agentCheck = $agentController->agentValidation($request);
            if(!($agentCheck['status'] ?? true)) {
                return response()->json($agentCheck);
            }
        }

        $journeyStage = JourneyStage::where('user_product_journey_id', $enquiry_id)->first();
        $master_policy = \App\Models\MasterPolicy::find($request->policyId);
        if (isset($journeyStage->stage) && $journeyStage->stage == STAGE_NAMES['PAYMENT_INITIATED']) {
            return response()->json([
                'status' => false,
                'message' => STAGE_NAMES['PAYMENT_INITIATED']
            ], 500);
        } else if (isset($journeyStage->stage) && ($journeyStage->stage == STAGE_NAMES['POLICY_ISSUED'] || $journeyStage->stage == STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] || strtolower($journeyStage->stage) == STAGE_NAMES['PAYMENT_SUCCESS'] || $journeyStage->stage == STAGE_NAMES['PAYMENT_INITIATED'])) {
            return response()->json([
                'status' => false,
                'message' => 'Transaction Already Completed',
                'data' => $journeyStage
            ], 500);
        }
        $user_product_journey = UserProductJourney::find($enquiry_id);
        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();

        if ((config('PROPOSAL_EXPIRED_IN_DAY_VALIDATION_ENABLE_CV') == 'Y' ) && $proposal->business_type != "breakin") {
            if (Carbon::parse($user_product_journey->created_on)->diffInHours(now()) > config('PROPOSAL_EXPIRED_IN_HOUR_VALIDATION_CV')) {
                return response()->json([
                    'status' => true,
                    'msg' => 'Policy Creation Date is Older Than ' . config('PROPOSAL_EXPIRED_IN_HOUR_VALIDATION_CV') . ' Days.',
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

        $CorporateVehiclesQuotesRequest = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
            ->get()
            ->first();

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

        if ($CorporateVehiclesQuotesRequest->business_type != 'newbusiness'  && !empty($proposal->vehicale_registration_number) && checkValidRcNumber($proposal->vehicale_registration_number)) {
            return response()->json([
                'status' => false,
                'msg' => 'This Rc Number Blocked On Portal',
            ]);
        }
        if(config('proposalPage.isVehicleValidation') == 'Y' && $CorporateVehiclesQuotesRequest->business_type != 'newbusiness')
        {
            $isSectionMissmatched = isSectionMissmatched($request, 'cv', $proposal->vehicale_registration_number);
    
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

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiry_id)
        ->select('addons','compulsory_personal_accident', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
        ->first();

        $sortedDetails = sortKeysAlphabetically(json_decode($proposal->additional_details));

        $hashData = collect($sortedDetails)->merge($proposal->ic_name)->merge($selected_addons->addons)->merge($selected_addons->compulsory_personal_accident)->merge($selected_addons->accessories)->merge($selected_addons->additional_covers)->merge($selected_addons->voluntary_insurer_discounts)->merge($selected_addons->discounts)->all();

        $kycHash = hash('sha256', json_encode($hashData));

        if((config("CHECK_PROPOSAL_HASH_ENABLE") == "Y"))
        {
            $checkProposalHash = self::checkProposalHash($kycHash, $proposal);

            if ($checkProposalHash['status']) {
                $responseArray = [
                    "status" => true,
                    "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "msg" => "Proposal Submited Successfully..!",
                    "data" => [
                        "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        'proposalId' => $proposal->user_proposal_id,
                        "verification_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
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
        if ($request->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest['is_renewal'] == 'Y') {
            switch ($request->companyAlias) {
                case 'shriram':
                    $data = shriramSubmitProposal::renewalSubmit($proposal, $request->all());
                    break;
                case 'godigit':
                    $data = goDigitSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'acko':
                    $data = ackoSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'tata_aig':
                    $data = tataAigSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'iffco_tokio':
                    $data = iffco_tokioSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'reliance':
                    $data = relianceSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'royal_sundaram':
                    $data = royalSundaramSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'icici_lombard':
                    $data = iciciLombardSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'nic':
                        $data = NicSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'united_india':
                    $data = UnitedIndiaSubmitProposal::submit($proposal, $request->all());
                break;
                case 'new_india':
                    $data = NewIndiaSubmitProposal::submit($proposal, $request->all());
                break;
                default:
                    $data = response()->json([
                        'status' => false,
                        'msg' => 'invalid company alias name'
                    ]);
            }
        } else {
            if ($master_policy->is_proposal_online === 'No') {
                switch ($request->companyAlias) {
                    case 'shriram':
                        $data = shriramSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'godigit':
                        if ((config('IC.GODIGIT.V2.CV.ENABLE') == 'Y')) {
                            $data = goDigitOneapiSubmitProposal::oneApisubmit($proposal, $request->all());
                        } else {
                            $data = goDigitSubmitProposal::submit($proposal, $request->all());
                        }
                        $data = goDigitSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'acko':
                        $data = ackoSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'icici_lombard':
                        $data = iciciLombardSubmitProposal::offlineSubmit($proposal, $request->all());
                        break;
                    case 'hdfc_ergo':
                        if(config('IC.HDFC_ERGO.V1.CV.ENABLED') == 'Y'){
                            $data = HDFC_ERGO_V1::submit($proposal, $request->all());
                        } else {
                            $data = hdfcErgoSubmitProposal::submit($proposal, $request->all());
                        }
                        break;
                    case 'reliance':
                        if(config('IC.RELIANCE.V1.CV.ENABLE') == 'Y'){
                            $data = RELIANCE_V1::submit($proposal, $request->all());
                        } else {
                            $data = relianceSubmitProposal::submit($proposal, $request->all());
                        }
                        break;
                    case 'iffco_tokio':
                        $data = iffco_tokioSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'oriental':
                        $data = orientalSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'sbi':
                        $data = SbiSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'bajaj_allianz':
                        $data = bajaj_allianzSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'tata_aig':
                        $data = tataAigSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'liberty_videocon':
                        $data = libertyVideoconSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'royal_sundaram':
                        $data = royalSundaramSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'cholla_mandalam':
                        $data = chollamandalamSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'nic':
                            $data = NicSubmitProposal::submit($proposal, $request->all());
                        break;
                    case 'united_india':
                        $data = UnitedIndiaSubmitProposal::submit($proposal, $request->all());
                    break;
                    case 'new_india':
                        $data = NewIndiaSubmitProposal::submit($proposal, $request->all());
                    break;
                    default:
                        $data = response()->json([
                            'status' => false,
                            'msg' => 'invalid company alias name'
                        ]);
                }
            }
            switch ($request->companyAlias) {
                case 'shriram':
                    if(config('IC.constant.SHRIRAM_GCV_PCV_JSON_V2_ENABLED') == 'Y'){
                        $shriramSubmitProposalV2 = new ShriramSubmitProposalV2();
                        $data = $shriramSubmitProposalV2->submitV2($proposal, $request);
                    }
                    elseif (policyProductType($request['policyId'])->parent_id == 4  &&  config('IC.SHRIRAM.V1.GCV.ENABLE') == 'Y') {
          
                        $shriramSubmitProposalV1GCV = new ShriramSubmitProposalV1GCV();
                        $data = $shriramSubmitProposalV1GCV->submitV1Gcv($proposal, $request);
                     } 
                     elseif(config('IC.SHRIRAM.V1.PCV.ENABLE') == 'Y')
                     {
                         $shriramSubmitProposalV1PCV = new ShriramSubmitProposalV1PCV();
                         $data = $shriramSubmitProposalV1PCV->submitV1Pcv($proposal, $request);
                     }
                     else 
                     {
                        $data = shriramSubmitProposal::submit($proposal, $request->all());
                     }
                  
                    break;
                case 'godigit':
                    $data = goDigitSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'acko':
                    $data = ackoSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'icici_lombard':
                    $data = iciciLombardSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'hdfc_ergo':
                    if(config('IC.HDFC_ERGO.V1.CV.ENABLED') == 'Y'){
                        $data = HDFC_ERGO_V1::submit($proposal, $request->all());
                    } else {
                        $data = hdfcErgoSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'reliance':
                    if(config('IC.RELIANCE.V1.CV.ENABLE') == 'Y'){
                        $data = RELIANCE_V1::submit($proposal, $request->all());
                    } else {
                        $data = relianceSubmitProposal::submit($proposal, $request->all());
                    }
                    break;
                case 'iffco_tokio':
                    $data = iffco_tokioSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'oriental':
                    $data = orientalSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'sbi':
                    $data = SbiSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'bajaj_allianz':
                    $data = bajaj_allianzSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'united_india':
                    $data = UnitedIndiaSubmitProposal::submit($proposal, $request->all());
                break;
                    case 'future_generali':
                        if(config("IC.FUTURE_GENERALI.V1.GCV.ENABLED") == 'Y')
                        {
                            $data = fgsubmitv1::submit($proposal,$request->all());
                        }
                        else
                        {
                            $data = futuregeneraliSubmitProposal::submit($proposal, $request->all());
                        }
                        break;
                case 'tata_aig':

             if( config('IC.TATA_AIG.V2.PCV.ENABLE') == 'Y' && in_array(policyProductType($request->policyId)->parent_id , [8]) ) {
            $data = tataAigSubmitProposals::submitPcv($proposal, $request);
                } else {
            $data = tataAigSubmitProposal::submit($proposal, $request->all());
                 }

                    break;
                case 'liberty_videocon':
                    $data = libertyVideoconSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'royal_sundaram':
                    $data = royalSundaramSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'universal_sompo':
                    $data = universalSompoSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'magma':
                    $data = magmaSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'cholla_mandalam':
                    $data = chollamandalamSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'nic':
                        $data = NicSubmitProposal::submit($proposal, $request->all());
                    break;
                case 'united_india':
                    $data = UnitedIndiaSubmitProposal::submit($proposal, $request->all());
                break;
                case 'new_india':
                    $data = NewIndiaSubmitProposal::submit($proposal, $request->all());
                break;
                default:
                    $data = response()->json([
                        'status' => false,
                        'msg' => 'invalid company alias name'
                    ]);
            }
        }


        if ($data && config('constants.LSQ.IS_LSQ_ENABLED') == 'Y') {
            $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

            if (((is_object($data) && isset($data->original['status']) && $data->original['status']) || (is_array($data) && isset($data['status']) && $data['status'])) && $lsq_journey_id_mapping) {
                updateLsqOpportunity($enquiry_id);
                createLsqActivity($enquiry_id);
            }
        }

        if (config('IS_FINSALL_ACTIVATED') == 'Y') {
            $allowed_ic = config('FINSALL_ALLOWED_IC');
            $allowed_ic_array = explode(',', $allowed_ic);
            if (in_array($request->companyAlias, $allowed_ic_array)) {
                $data  = $data->getData();
                $data->is_finsall_available = true;
                $data = response()->json($data);
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

        if ($data) {
            if ((isset($quoteData['status']) && $quoteData['status'] != 'true') && isset($quoteData['request'])) {

                $requestData = getQuotation($enquiry_id);
                $productData = getProductDataByIc($request->policyId);

                $data = create_webservice([
                    'enquiryId' => $enquiry_id,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'Internal Service Error',
                    'section' => 'CV',
                    'method' => 'Proposal',
                    'companyAlias' => $productData->company_alias,
                    'request' => ['request' => $quoteData['request'], 'version_id' => $requestData->version_id],
                    'response' => (isset($quoteData['response']) ? $quoteData['response'] : (isset($quoteData['message']) ? $quoteData['message'] : $quoteData['msg'])),
                ]);

                $webservice_id = $data['webservice_id'] ?? $data['webserviceId'];
                $message = $data['message'] ?? $data['msg'];

                if (isset($webservice_id)) {
                    update_quote_web_servicerequestresponse($data['table'], $webservice_id, $message, "Failed");
                }
            } else if (isset($quoteData['status']) && ($quoteData['status'] == 'true' || $quoteData['status'] === true)) {

                $webservice_id = $quoteData['webservice_id'] ?? $quoteData['webserviceId'];
                $message = $quoteData['message'] ?? $quoteData['msg'];

                if (isset($webservice_id)) {
                    update_quote_web_servicerequestresponse($quoteData['table'], $webservice_id, $message, $quoteData['status'] == true ? "Success" : "Failed");
                }
            } else if (isset($quoteData['status']) && ($quoteData['status'] == 'false' ||  $quoteData['status'] === false)) {
                $webservice_id = $quoteData['webservice_id'] ?? $quoteData['webserviceId'];
                $message = $quoteData['message'] ?? $quoteData['msg'];

                if (isset($webservice_id)) {
                    update_quote_web_servicerequestresponse($quoteData['table'], $webservice_id, $message, $quoteData['status'] == true ? "Success" : "Failed");
                }
            }
        }

        if ($data instanceof \Illuminate\Http\JsonResponse) {
            $d = $data->original;
            if (isset($d['status']) && !$d['status']) {
                $arr = json_decode($data->content(), true);
                $arr['message'] = getProposalCustomErrorMessage($d['msg'] ?? $d['message'] ?? '', $request->companyAlias, 'cv');
                $data->setData($arr);
            }
        } else if (isset($data['status']) && !$data['status']) {
            $data['message'] = getProposalCustomErrorMessage($data['msg'] ?? $data['message'] ?? '', $request->companyAlias, 'cv');
        }

        if (config("ENABLE_UPDATE_SIDECARD_DATA_AT_PROPOSAL") == 'Y') {
            $Sidecard_update_allowed_ic = config('CV_SIDECARD_UPDATE_ALLOWED_IC');
            $Sidecard_update_allowed_ic = explode(',', $Sidecard_update_allowed_ic);
            if (in_array($request->companyAlias, $Sidecard_update_allowed_ic)) {
                try {
                    if ($data instanceof \Illuminate\Http\JsonResponse) {
                        $d = $data->original;
                        if (isset($d['status']) && $d['status']) {
                            premium_updation_proposal($enquiry_id);
                        }
                    } else if (isset($data['status']) && $data['status']) {
                        premium_updation_proposal($enquiry_id);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('SIDECARD updation failed for trace ' . $enquiry_id . ' : ' . $e->getMessage());
                    // noting to write here....
                };
            }
        }

        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->select('is_ckyc_verified')->first();
        $kyc_status = $proposal->is_ckyc_verified == 'Y' ? true : false;

        if(in_array($request->companyAlias, explode(',',config('ICS_ALLOWED_FOR_PAYMENT_WITHOUT_CKYC'))))
        {
           $kyc_status = true; 
        }
        $hide_ckyc_popup = false;
        \Illuminate\Support\Facades\Log::info( 'CKYC Response after Proposal Submit : '.json_encode( $data ) );
        // $verification_status = is_array($data) && isset($data['data']['verification_status']) && $data['data']['verification_status'] == true ? true : false;
        if(in_array($request->companyAlias, explode(',',config('ICS_VERIFY_CKYC_HIDE_POPUP'))))
        {
           $hide_ckyc_popup = true; 
        }
        if(in_array($request->companyAlias, explode(',',config('ICS_VERIFY_CKYC_BYPASS_STATUS'))))
        {
            $verification_status = true;
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
        $proposalNo = UserProposal::where('user_product_journey_id', $enquiry_id)->select('proposal_no')->first();
        if(isset($data['status']) && $data['status'] == true && !isset($data['data']['proposalNo']) && empty($data['data']['proposalNo'])){
            $data['data']['proposalNo'] = $proposalNo->proposal_no;
        }
        event(new \App\Events\ProposalSubmitted($enquiry_id));
        
        return $data;
    }

    public static function checkAllowedFileExtentions($ext, $companyAlias, $type = '')
    {
        if(!in_array(strtolower($ext),['pdf','jpeg','gif','bmp','xls','xlsx','doc','docx']) && $companyAlias == 'bajaj_allianz')
        {
            return [
                'status' => false,
                'msg' => "Please upload document in PDF, JPEG, GIF, BMP, XLS, XLSX, DOC or DOCX format.",
            ];
        }
        if(!in_array(strtolower($ext),['jpg', 'jpeg', 'png', 'pdf']) && $companyAlias == 'icici')
        {
            return [
                'status' => false,
                'msg' => "Please upload document in JPG, JPEG, PNG or PDF format.",
            ];
        }
        
        if(!in_array(strtolower($ext), ['jpg', 'png', 'pdf', 'gif', 'bmp', 'doc', 'docx']) && $companyAlias == 'royal_sundaram' && $type == 'form60') {
            return [
                'status' => false,
                'msg' => "Please upload form60 in JPG, PNG, PDF, GIF, BMP, DOC or DOCX format.",
            ];
        }
        
        if(!in_array(strtolower($ext), ['pdf','png','jpg']) && $companyAlias == 'shriram' && $type == 'form60') {
            return [
                'status' => false,
                'msg' => "Please upload form60 in PDF, PNG or jpg format.",
            ];
        }
        
        if(!in_array(strtolower($ext), ['pdf', 'jpg', 'jpeg', 'tif', 'tiff']) && $companyAlias == 'iffco_tokio') {
            return [
                'status' => false,
                'msg' => "Please upload document in JPG, JPEG, PDF, TIF or TIFF format.",
            ];
        }
        return ['status' => true];
    }

    public static function checkProposalHash($proposalHash, $proposal)
    {   
        // if (!empty($proposalHash) && ProposalHash::where(['user_product_journey_id'=> $proposal->user_product_journey_id, 'hash' => $proposalHash])->exists()) {
        //     return ['status' => true];
        // }

        if (!empty($proposalHash) && ProposalHash::where(['user_product_journey_id' => $proposal->user_product_journey_id, 'hash' => $proposalHash])->latest('created_at')->first()) {
            return ['status' => true];
        }

        // UserProposal
        return ['status' => false];
    }

    public static function checkICDocumentExists($request, $proposal)
    {
        $isPOAPresent = $isPOIPresent = $isPHOTOPresent = false;

        $kycDocTypes = [
            'isPOIPresent' => [
                'poi_panCard',
                'poi_aadharCard',
                'poi_gst_certificate',
                'poi_gst_certificate',
                'poi_passport_image',
                'poi_voter_card',
                'poi_cinNumber',
                'poi_driving_license',
                'poi_nrega_job_card_image',
                'poi_national_population_register_letter_image',
            ],
            'isPOAPresent' => [
                'poa_panCard',
                'poa_aadharCard',
                'poa_gst_certificate',
                'poa_passport_image',
                'poa_voter_card',
                'poa_driving_license',
                'poa_nrega_job_card_image',
                'poa_national_population_register_letter_image',
                'poa_registration_certificate_image',
                'poa_certificate_of_incorporation_image',
            ],
            'isPHOTOPresent' => [
                'photo'
            ]
        ];

        foreach ($kycDocTypes as $type => $documents) {
            foreach ($documents as $docName) {
                if ($request->hasFile($docName)) {
                    ${$type} = true;
                    break;
                }
            }
        }

        if ($request->companyAlias == 'iffco_tokio' && (!($isPOAPresent && $isPOIPresent && $isPHOTOPresent))) {
            return [
                'status' => false,
                'message' => trim((!$isPOAPresent ? 'POA is required' : '') . (!$isPOIPresent ? 'POI is required' : '') . (!$isPHOTOPresent ? 'PHOTOGRAPH is required' : ''))
            ];
        }
        return [
            'status' => true
        ];
    }

    public static function storeCkycDocument($file, $path, $fileName)
    {
        $storagePath = $path . '/'. $fileName;
        try {
            include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';

            $fileContent = file_get_contents($file->getPathname());
            $fileContent = encryptData($fileContent);

            Storage::put($storagePath, $fileContent);
            
        } catch (\Throwable $th) {
            throw new Exception ($th);
        }
    }


    public static function getCkycDocument($path)
    {
        $fileContent = false;
        try {
            $fileContent = Storage::get($path);
            include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';

            $fileContent = decryptData($fileContent);
        } catch (\Throwable $th) {
            info($th);
        }
        return $fileContent;
    }
}