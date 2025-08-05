<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Proposal\ProposalController;
use App\Models\CvAgentMapping;
use App\Models\JourneyStage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\ckycRequestResponse;
use App\Models\ckycUploadDocuments;
use App\Models\CkycVerificationTypes;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\UserProposal as ModelsUserProposal;
use App\Models\Gender;
use App\Models\ProposerCkycDetails;
use App\Models\ProposalHash;
use App\Models\WebServiceRequestResponse;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Proposal\Services\Car\newIndiaSubmitProposal;
use App\Http\Controllers\Proposal\Services\Car\tataAigV2SubmitProposal;
class CkycController extends Controller
{
    public $is_checked_using_reference_id = false;
    public $remove_proxy = true;

    public function ckycVerifications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => 'required',
            'mode' => 'required',
            'enquiryId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $this->remove_proxy = config('constants.REMOVE_PROXY_FOR_CKYC') != 'N' ? true : false;

        if ($request->mode == 'documents') {
            if ($request->companyAlias == 'bajaj_allianz' && config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') != 'Y') {
                return self::ckycBajajAllianze($request);
            }
          
            return self::ckycUploadDocuments($request);
        }

        //this block is used currently to store the files of RS AML
        if (!empty($request->file())) {
            if ($request->hasFile('form60')) {
                $file = $request->file('form60');
                $ext = $file->getClientOriginalExtension();
                $filename = $request->enquiryId . '.' . $ext;
                // $file->storeAs('ckyc_photos/' . $request->enquiryId.'/form60', $filename);
                ProposalController::storeCkycDocument(
                    $file,
                    'ckyc_photos/' . $request->enquiryId . '/form60',
                    $filename
                );
            } elseif ($request->hasFile('form49a')) {
                $file = $request->file('form49a');
                $ext = $file->getClientOriginalExtension();
                $filename = $request->enquiryId . '.' . $ext;
                // $file->storeAs('ckyc_photos/' . $request->enquiryId.'/form49a', $filename);
                ProposalController::storeCkycDocument(
                    $file,
                    'ckyc_photos/' . $request->enquiryId.'/form49a',
                    $filename
                );
            }
        }

        $enquiry_id = customDecrypt($request->enquiryId);
        $ckyc_verification_type = CkycVerificationTypes::where('company_alias', $request->companyAlias)->pluck('mode')->first();
        if(empty($ckyc_verification_type)) {
            return response()->json([
                'status' => false,
                'message' => 'Verification Type is not defined for this IC - ' . str_replace('_', ' ', $request->companyAlias),
                'data' => [
                    'message' => 'Verification Type is not defined for this IC - ' . str_replace('_', ' ', $request->companyAlias),
                    'verification_status' => false,
                ],
            ]);
        }
        $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_data = CvAgentMapping::where('user_product_journey_id', $enquiry_id)
        ->where('seller_type','P')
        ->first();

        $is_pos = 'false';
        if($is_pos_enabled && !empty($pos_data))
        {
            $is_pos = 'true';
        }

        $corporate_vehicles_quotes_request = $proposal->corporate_vehicles_quotes_request ?? '';
        $quote_log = $proposal->quote_log->premium_json;
        $user_product_journey = $proposal->user_product_journey;
        
        if($user_product_journey->product_sub_type_id == 1)
        {
            $sub_product = 'CAR';
        }
        else if($user_product_journey->product_sub_type_id == 2)
        {
           $sub_product = 'BIKE'; 
        }
        else
        {
            $sub_product = get_parent_code($user_product_journey->product_sub_type_id);
        }

        $proprietorshipCase = false;
        if (config('IS_PROPRIETORSHIP_CASE_ENABLED') == 'Y') {
            $proprietorshipFields = (json_decode(config('PROPRIOTERSHIP_AVAILABLE_ICS_FIELDS'), 1) ?? []);
            $proprietorshipCase = (isset($proprietorshipFields[$request->companyAlias]) && (($proposal->proposer_ckyc_details->organization_type ?? null) == 'Proprietorship')) ? true : false;
        }

        $company_alias = $request->companyAlias;
        if ($request->companyAlias == 'reliance') {
            $company_alias = 'reliance_general';
        } else if ($request->companyAlias == 'liberty_videocon') {
            $company_alias = 'liberty_general';
        }


        $request_data = [
            'company_alias' => $company_alias, //$request->companyAlias,
            'type' => $ckyc_verification_type,
            'mode' => $request->mode,
            'section' => 'motor',
            'trace_id' => customEncrypt($enquiry_id),
            'ckyc_number' => $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : null,
            'pan_no' => $proposal->ckyc_type == 'pan_card' ? $proposal->ckyc_type_value : null,
            'aadhar' => $proposal->ckyc_type == 'aadhar_card' ? $proposal->ckyc_type_value : null,
            'date_of_birth' => $proposal->dob,
            'tenant_id' => config('constants.CKYC_TENANT_ID')
        ];

        $customer_data = [];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        switch ($request->companyAlias) {
            case 'cholla_mandalam':
                $ckyc_meta_data=json_decode($proposal->ckyc_meta_data);
                $customer_data = [
                    'passport_no'=>$proposal->ckyc_type == 'passport' ? $proposal->ckyc_type_value : '',
                    'voter_id'=>$proposal->ckyc_type == 'voter_id' ? $proposal->ckyc_type_value : '',
                    'driving_license'=>$proposal->ckyc_type == 'driving_license' ? $proposal->ckyc_type_value : '',
                    'meta_data' => [
                        'ckyc_reference_id'=>$proposal->ckyc_reference_id,
                        'transaction_id'=>!empty($ckyc_meta_data) && !empty($ckyc_meta_data->transaction_id) ? $ckyc_meta_data->transaction_id : null,
                        'mobile_no'     => $proposal->mobile_number,
                        'email_id'      => $proposal->email,
                        'gender' => $proposal->gender ? $proposal->gender[0] : '',
                        'proposer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        'first_name' => $proposal->first_name,
                        'last_name' => $proposal->last_name
                    ]
                ];

                if (!$request_data['pan_no']) {
                    $customer_data['pan_no'] = '';
                    if ($request->mode == "pan_number_with_dob" && $proposal->pan_number) {
                        $customer_data['pan_no'] = $proposal->pan_number;
                    }
                }

                if (!$request_data['aadhar']) {
                    $customer_data['aadhar'] = '';
                }

                if (!$request_data['ckyc_number']) {
                    $customer_data['ckyc_number'] = '';
                }
                break;
            case 'hdfc_ergo':
                // If ckyc reference id exists, we will try to verify using ckyc_reference_id. If we get 'status' as true, then return, else we will try to verify with other modes 
                if ( ! empty($proposal->ckyc_reference_id) && ! $this->is_checked_using_reference_id) {
                    $request_data['mode'] = 'ckyc_reference_id';
                }

                if ( ! empty($corporate_vehicles_quotes_request) && $corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
                    if(!is_array($proposal->ckyc_meta_data)) {
                        $proposal->ckyc_meta_data = json_decode($proposal->ckyc_meta_data, 1);
                    }
                    $customer_data = [
                        'mode' => in_array($request_data['mode'], ['ckyc_number', 'pan_number_with_dob', 'cinNumber']) ? 'corporate_' . $request_data['mode'] : $request_data['mode'],
                        'cin' => $proposal->ckyc_type == 'cinNumber' ? $proposal->ckyc_type_value : null,
                        'meta_data' => [
                            'ent_type' => $proposal->proposer_ckyc_details->organization_type ?? null,
                            'customer_type' => 'C'
                        ]
                    ];
                    if(config('HDFC_CORPORATE_REFERENCE_ID_CHECK' == 'Y')) {
                        if(empty($proposal->ckyc_reference_id) && !($this->is_checked_using_reference_id) && !empty($proposal->ckyc_meta_data['txn_id'] ?? '')) {
                            $customer_data['mode'] = 'transaction_id';
                            $customer_data['meta_data']['txn_id'] = $proposal->ckyc_meta_data['txn_id'] ?? null;
                        } else if ($proposal->is_ckyc_verified == 'Y' && ($proposal->ckyc_reference_id)) {
                            $customer_data['mode'] = 'corporate_kyc_id';
                            $request_data['mode'] = 'corporate_kyc_id';
                            $customer_data['meta_data']['txn_id'] =  $proposal->ckyc_reference_id ?? null;
                            $customer_data['meta_data']['ckyc_reference_id'] =  $proposal->ckyc_reference_id ?? null;
                            $customer_data['meta_data']['kyc_id'] =  $proposal->ckyc_reference_id ?? null;
                        }
                    }
                }
                $customer_data['meta_data']['ckyc_reference_id'] = $proposal->ckyc_reference_id ?? null;

                break;

            case 'edelweiss':
                $ckycReqResdata = ckycRequestResponse::where([
                    'user_product_journey_id' => $enquiry_id,
                    'user_proposal_id' => $proposal->user_proposal_id
                ])->first();

                $kycresponse = (json_decode(($ckycReqResdata->kyc_response ?? '[]')));
                $KYC_Status = (int) ($kycresponse->KYC_Status ?? 0) == 1 ? 'Y' : 'N';

                if(!empty($KYC_Status) && $KYC_Status == 'Y')
                {
                    $ckyc_response_array = [
                        'verification_status' => true,
                        'ckyc_id' => ($kycresponse->ProposerCKYCNo ?? ''),
                        'name' => ($kycresponse->FirstName ?? ''). '' .($kycresponse->LastName ?? ''),
                        'ckyc_reference_id' => ($kycresponse->VISoF_KYC_Req_No ?? ''),
                        'meta_data' => [
                            "VISoF_KYC_Req_No"=> ($kycresponse->VISoF_KYC_Req_No ?? ''),
                            "IC_KYC_No"=>  ($kycresponse->IC_KYC_No ?? ''),
                        ],
                        'customer_details' => [
                            'dob' =>isset($kycresponse->DOB) && hasFullDate($kycresponse->DOB, 'm/d/Y') ? \DateTime::createFromFormat('m/d/Y', $kycresponse->DOB)->format('d-m-Y') : '',
                            'email' => ($kycresponse->Email ?? ''),
                            'fullName' => ($kycresponse->FirstName ?? ''). ' ' .($kycresponse->LastName ?? ''),
                            'mobileNumber' => ($kycresponse->MobileNo ?? ''),
                            'panNumber' => ($kycresponse->ProposerPAN ?? ''),
                        ]
                    ];

                    $proposal->is_ckyc_verified = 'Y';
                    $proposal->save();

                    return response()->json([
                        'data' => $ckyc_response_array,
                        'status' => true,
                        'message' => null
                    ]);
                }

                if ( ! empty($proposal->ckyc_reference_id) && ! $this->is_checked_using_reference_id) {
                    $request_data['mode'] = 'ckyc_reference_id';

                    $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);

                    $IC_KYC_No = $ckyc_meta_data['IC_KYC_No'];
                }

                $customer_data = [
                    'meta_data' => [
                        'VISoF_Program_Name' => config('constants.BROKER'),
                        'VISoF_KYC_Req_No' => (string) $proposal->ckyc_reference_id,
                        'proposer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        'first_name' => strtoupper($proposal->first_name),
                        'last_name' => strtoupper($proposal->last_name),
                        'mobile_no' => $proposal->mobile_number,
                        'zip_code' => (string) $proposal->pincode,
                        'email' => $proposal->email,
                        'IC_KYC_No' => $IC_KYC_No ?? null
                    ]
                ];

                if (empty($request_data['pan_no'])) {
                    $customer_data['pan_no'] = $proposal->pan_number;
                }
                break;

            case 'universal_sompo':
                // If ckyc reference id exists, we will try to verify using ckyc_reference_id. If we get 'status' as true, then return, else we will try to verify with other modes 
                if ( ! empty($proposal->ckyc_reference_id) && ! $this->is_checked_using_reference_id) {
                    $request_data['mode'] = 'ckyc_reference_id';
                }

                $date1 = new \DateTime($user_product_journey->created_on);
                $date2 = new \DateTime(date('Y-m-d'));
                $interval = $date1->diff($date2);

                $ckyc_reference_id = ! empty($proposal->ckyc_reference_id) && $interval->d < 15 ? $proposal->ckyc_reference_id : config('constants.BROKER') . '/' . $proposal->user_product_journey_id . '/' . substr(time(), -5);

                $customer_data = [
                    'meta_data' => [
                        "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        'ckyc_reference_id' => $ckyc_reference_id,
                        'customer_name' => implode(' ', [$proposal->first_name, $proposal->last_name]),
                        'gender' => (in_array(strtoupper($proposal->gender), ['M','MALE']) ? 'M' : 'F'),
                        'ip_address' => request()->ip() ?? $_SERVER['SERVER_ADDR']
                    ]
                ];

                // Note: CKYC verification is not available using CKYC number for corporate cases
                if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') { 
                    $customer_data['cin'] = $proposal->ckyc_type == 'cinNumber' ? $proposal->ckyc_type_value : '';
                    $customer_data['gst_no'] = $proposal->ckyc_type == 'gstNumber' ? $proposal->ckyc_type_value : '';
                }
                break;

            case 'bajaj_allianz':
                $customer_data = [
                    'pan_no' => $proposal->pan_number,
                    'passport_no' => $proposal->ckyc_type == 'passport' ? $proposal->ckyc_type_value : '',
                    'voter_id' => $proposal->ckyc_type == 'voter_id' ? $proposal->ckyc_type_value : '',
                    'driving_license' => $proposal->ckyc_type == 'driving_license' ? $proposal->ckyc_type_value : '',
                    'name' => implode(' ', [$proposal->first_name, $proposal->last_name]),
                    'meta_data' => [
                        "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type == "I" ? "I" : "O",
                        "transaction_id" => $proposal->proposal_no,
                        "user_id" => $request->user_id,
                        "location_code" => config('constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR'),
                        "product_code" => $request->product_code
                    ]
                ];
                if($proposal->ckyc_type == 'gstNumber'){
                    $customer_data['gst_no'] = $proposal->ckyc_type == 'gstNumber' ? $proposal->ckyc_type_value : '';
                }

                if ($proposal->ckyc_type == 'aadhar_card') {
                    $customer_data['aadhar'] = substr($proposal->ckyc_type_value, -4);
                    $customer_data['name'] = implode(' ', [$proposal->first_name, $proposal->last_name]);
                    $customer_data['gender'] = (in_array(strtoupper($proposal->gender), ['MALE', 'M']) ? "M" : "F");
                }
                break;

            case 'icici_lombard':
                switch ($request->mode) {
                    case 'ckyc_number':
                        $customer_data = [
                            'ckyc_number' => $proposal->ckyc_type_value
                        ];
                        break;
                    case 'aadhar_with_dob':
                    case 'aadhar':
                        $customer_data = [
                            "name" => trim($proposal->first_name . ' ' . $proposal->last_name),
                            "gender" => $proposal->gender == 'MALE' ? "M" : "F",
                            "aadhar" => $proposal->ckyc_type_value
                        ];
                        break;
                    case 'otp':
                        $customer_data = [
                            "name" => trim($proposal->first_name . ' ' . $proposal->last_name),
                            "gender" => $proposal->gender == 'MALE' ? "M" : "F",
                            "aadhar" => $proposal->ckyc_type_value,
                            "otp_id" => $request->otpId,
                            "otp" => $request->otp,
                        ];
                        break;
                    case 'driving_license':
                        $customer_data = [
                            'driving_license' => $proposal->ckyc_type_value
                        ];
                        break;
                    case 'voter_id':
                        $customer_data = [
                            'mode' => 'voter_card',
                            'voter_id' => $proposal->ckyc_type_value
                        ];
                        break;
                    case 'passport':
                        $customer_data = [
                            'passport' => $proposal->ckyc_type_value
                        ];
                        break;
                    case 'cinNumber':
                        $customer_data = [
                            'cin' => $proposal->ckyc_type_value
                        ];
                        break;
                    default:

                        break;
                }
                $customer_data['meta_data']['correlation_id'] = getUUID($enquiry_id);
ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'unique_proposal_id' => $customer_data['meta_data']['correlation_id'],
                                    ]);
                $headers = [
                    'Accept' => 'application/json'
                ];
                break;
            case 'godigit':
                $response = [];
                $response['response']['data']['verification_status'] = true;
                $response['response']['data']['ckyc_reference_id'] = null;
                $response['response']['data']['meta_data'] = null;

                return json_encode($response['response'], JSON_UNESCAPED_SLASHES);
                break;

            case 'royal_sundaram':

                if(config('ENABLE_ROYAL_SUNDARAM_CKYC_ON_FIRST_CARD') == 'Y' && ($proposal->is_ckyc_verified ?? 'N') == 'Y')
                {
                    $customer_details = ((!empty(json_decode($proposal->ckyc_meta_data, true)) && isset(json_decode($proposal->ckyc_meta_data, true)['customer_details'])) ? (json_decode($proposal->ckyc_meta_data, true)['customer_details']) : []);

                    $array = [
                        'verification_status' => true,
                        'ckyc_id' => $proposal->ckyc_number,
                        'name' => $proposal->fullName,
                        'message' => "",
                        'meta_data' =>  (!empty(json_decode($proposal->ckyc_meta_data, true)) ? json_decode($proposal->ckyc_meta_data, true) : []),
                        'customer_details' => $customer_details
                    ];
                    return response()->json([
                        'data' => $array,
                        'status' => true,
                        'message' => null
                    ]);
                }


                $uniqueId = $quote_log['quoteId'];
                if(!empty($uniqueId)) {
                    $corporate_vehicles_quotes_request = $proposal->corporate_vehicles_quotes_request;

                    $quoteIdRequestData = [
                        'company_alias' => 'royal_sundaram',
                        'type' => $ckyc_verification_type,
                        'mode' => 'fetch_api',
                        'section' => 'motor',
                        'trace_id' => customEncrypt($enquiry_id),
                        'meta_data' => [
                            'uniqueId' => $uniqueId,
                            'owner_type' => $corporate_vehicles_quotes_request->vehicle_owner_type
                        ]
                    ];

                    $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $quoteIdRequestData, [], [
                        'Content-Type' => 'application/json'
                    ], [], true, false, $this->remove_proxy, true);
                    if ($response['status'] == 200){
                        if(isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status']) {
                            self::royalSundaramUpdateVerificationData($response, $proposal);

                            $response['response']['status'] = $response['response']['data']['verification_status'] ?? false;
                            $response['response']['message'] = isset($response['response']['data']['message']) ? $response['response']['data']['message'] : null;
                            $response['response']['data']['customer_details']['dob'] = str_replace('/', '-', $response['response']['data']['customer_details']['dob']);
                                            
                            return response()->json($response['response']);exit;
                        }else if (($response['response']['data']['meta_data']['kyc_status'] ?? '') == 'pending') {
                            return response()->json($response['response']);exit;
                        }
                    } else {
                        if(!empty($response['response']['exception'] ?? '') == 'App\Exceptions\TenantCredentialsNotFoundException') {
                            $err_message = 'Credentials not configured for this IC : ' . $response['response']['message'];
                            return response()->json([
                                'status' => false,
                                'message' => $err_message,
                                'data' => [
                                    'message' => $err_message,
                                    'verification_status' => false,
                                ],
                            ]);
                        }
                        $response['response']['status'] = false;
                        $response['response']['message'] = 'Something went wrong';
                        return response()->json($response['response']);exit;
                    }
                }



                $customer_data = [
                    'meta_data' => [
                        'app_name' => 'D2C',
                        "customer_type" =>  $proposal->owner_type,
                        "unique_id" => $request->unique_quote_id ?? $quote_log['quoteId'],
                    ]
                ];
                if (empty($request_data['pan_no'])) {
                    if ($request->mode == "pan_number_with_dob" && !empty($proposal->pan_number)) {
                        $request_data['pan_no'] = $proposal->pan_number;
                    }
                }
                if (in_array($proposal->ckyc_type, ['ckyc_number', 'driving_license', 'passport', 'voter_id'])) {
                    $customer_data['pan_no'] = $proposal->pan_number;
                }
                break;

            case 'future_generali':
                if ( ! empty($proposal->ckyc_reference_id) && ! $this->is_checked_using_reference_id) {
                    $request_data['mode'] = 'validate_ckyc_reference_id';
                }

                $customer_data = [
                    "name"          => trim($proposal->first_name . ' ' . $proposal->last_name),
                    "mobile_no"     => $proposal->mobile_number,
                    "email_id"      => $proposal->email,
                    "date_of_birth" => $proposal->dob,
                    "gender" => $proposal->gender,
                    'meta_data' => [
                        'proposal_id' => $proposal->ckyc_reference_id,
                        'otp' => $request->otp ?? ''  #fg otp ckyc changes
                    ]
                ];

                $headers = [
                    'Accept' => 'application/json'
                ];
                break;

            case 'reliance':
                if(!empty($proposal->is_ckyc_verified) && $proposal->is_ckyc_verified == 'Y' && !empty($proposal->ckyc_number))
                {
                    $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true) ?? [];
                    $ckyc_meta_data["verification_status"] = true;

                    return response()->json([
                        'data' => $ckyc_meta_data,
                        'status' => true,
                        'message' => null
                    ]);
                }

                if ($proposal->ckyc_type == 'cinNumber') {
                    $request_data['mode'] = 'cin_number';
                    $request_data['cin'] = $proposal->ckyc_type_value;
                }

                //Proprietorship Changes
                if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') { 
                    $request_data['gst_no'] = $proposal->ckyc_type == 'gstNumber' ? $proposal->ckyc_type_value : '';
                    $request_data['udyam']  = $proposal->ckyc_type == 'udyam' ? $proposal->ckyc_type_value : '';
                    $request_data['udyog']  = $proposal->ckyc_type == 'udyog' ? $proposal->ckyc_type_value : '';
                    $request_data['passportFileNumber'] = $proposal->ckyc_type == 'passportFileNumber' ? $proposal->ckyc_type_value : '';
                }

                $gender = '';
                if (($proposal->owner_type ? $proposal->owner_type : '') == 'I') {
                    $gender = $proposal->gender ? $proposal->gender : '';
                    if(in_array(strtoupper($gender), ['M', 'MALE', 1])) {
                        $gender = 'M';
                    } else {
                        $gender = 'F';
                    }
                }

                $customer_data = [
                    'name' => trim(implode(' ', [$proposal->first_name, $proposal->last_name])),
                    'mobile_no' => $proposal->mobile_number,
                    'email_id' => $proposal->email,
                    'meta_data' => [
                        'gender' => $gender,
                        'owner_type' => $proposal->owner_type ? $proposal->owner_type : '',
                    ]
                ];
                $headers = [
                    'Accept' => 'application/json'
                ];
                break;

            case 'kotak':
                $ckyc_extras = json_decode($proposal->ckyc_extras, true) ?? [];
                // If ckyc reference id exists, we will try to verify using ckyc_reference_id. If we get 'status' as true, then return, else we will try to verify with other modes 
                // if ($request_data['mode'] = 'ckyc_reference_id' && $ckyc_extras['vTokenID']) {

                //     if (isset($ckyc_extras['vTokenID']) && ! empty($ckyc_extras['vTokenID'])) {
                //         $request_data['mode'] = 'ckyc_reference_id';
                //     }
                // }

                $customer_data = [
                    'meta_data' => [
                        "first_name"    => trim($proposal->first_name),
                        "gender"        => $proposal->gender,
                        "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        "user_id"       => config('constants.IcConstants.kotak.KOTAK_BIKE_USERID'),
                        "password"      => config('constants.IcConstants.kotak.KOTAK_BIKE_PASSWORD'),
                        'mobile_no'     => $proposal->mobile_number,
                        'email_id'      => $proposal->email,
                        'ckyc_reference_id' => $request->quoteId,
                        'token_id'      => $ckyc_extras['vTokenID'] ?? null,
                        'pincode' => $proposal->pincode,
                        'status_check' => config('KOTAK_KYC_REFERENCE_ID_STATUS_CHECK')
                    ]
                ];
                if($corporate_vehicles_quotes_request->vehicle_owner_type == 'I'){
                    $full_name = implode(' ', [trim($proposal->first_name), trim($proposal->last_name)]);
                    $parts = explode(' ', trim($full_name));

                    $refirstname = reset($parts);
                    unset($parts[0]);

                    $relastname = empty(end($parts)) ? '' : end($parts);

                    if (isset($parts[count($parts)]) && count($parts) >= 1) unset($parts[count($parts)]);

                    $customer_data['meta_data']['first_name'] = $refirstname;
                    $customer_data['meta_data']['middle_name'] = implode(' ', $parts);
                    $customer_data['meta_data']['last_name'] = $relastname;
                }
                else{
                    $customer_data['meta_data']['first_name'] = trim($proposal->first_name);
                    $customer_data['meta_data']['last_name'] = trim($proposal->last_name);
                }
                break;

            case 'liberty_videocon':
                if(!empty($proposal->is_ckyc_verified) && $proposal->is_ckyc_verified == 'Y')
                {
                    $customer_details = ((!empty(json_decode($proposal->ckyc_meta_data, true)) && isset(json_decode($proposal->ckyc_meta_data, true)['customer_details'])) ? (json_decode($proposal->ckyc_meta_data, true)['customer_details']) : []);

                    $array = [
                        'verification_status' => true,
                        'ckyc_id' => $proposal->ckyc_number,
                        'name' => $proposal->fullName,
                        'message' => "",
                        'meta_data' =>  (!empty(json_decode($proposal->ckyc_meta_data, true)) ? json_decode($proposal->ckyc_meta_data, true) : []),
                        'customer_details' => $customer_details
                    ];
                    return response()->json([
                        'data' => $array,
                        'status' => true,
                        'message' => null
                    ]);
                    return $array;
                }

                $gender = ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I' ? (in_array(strtoupper($proposal->gender), ['M','MALE']) ? 'M' : 'F') : '');

                $customer_data = [
                    'name' => $proposal->first_name . ' ' . $proposal->last_name,
                    'mobile_no' => $proposal->mobile_number,
                    'email_id' => $proposal->email,
                    'gender' => $gender,
                    'meta_data' => [
                        'gender' => $gender,
                        'is_pos' => $is_pos,
                        'customer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                    ]
                ];
                $headers = [
                    'Accept' => 'application/json'
                ];

                break;

            case 'sbi':
                if (config('TESTING_PAN_WITH_DOB_CASE_TEMP') == 'Y') {
                    $response['data'] = [
                        "verification_status" => true,
                        "ckyc_id" => "60025668726538",
                        "name" => "SUMEET DILIP MAL",
                        "redirection_url" => null,
                        "redirection_url_via_form" => null,
                        "message" => null,
                        "old_message" => null,
                        "meta_data" => [],
                        "ckyc_reference_id" => "REN20240510070510",
                        "otp_id" => null,
                        "newTab" => false,
                        "customer_details" => [
                            "addressLine1" => "S O DILIP MAL BUILDING NO 4 A ROOM NO 405",
                            "addressLine2" => "KRISHNA CO OP HSG SOC LALLUBHAI COMPOUND",
                            "addressLine3" => "MANKHURD WEST SHIVAJI NAGAR S O MUMBAI",
                            "pincode" => "400043",
                            "dob" => "09-05-2000",
                            "gender" => "M",
                            "requestId" => "REN20240510070510",
                            "mode" => "pan_number_with_dob",
                            "documentId" => "GMRPM8438R",
                            "fullName" => "SUMEET DILIP MAL",
                            "genderName" => "Male",
                        ],
                        "disabled_field" => null,
                        "accessToken" => false,
                        "show_document_fields" => false,
                    ];
        
                    // Return JSON response
                    return response()->json($response);
                }
                $additional_details = json_decode($proposal->additional_details, true);
                $CKYCUniqueId = $additional_details['CKYCUniqueId'] ?? NULL;
                $request_data['company_alias'] = 'sbi_general';
                $customer_data = [
                    'name'      => trim($proposal->first_name . ' ' . $proposal->last_name),
                    'mobile_no' => $proposal->mobile_number,
                    'email_id'  => $proposal->email,
                    'gender'    =>  $proposal->gender,
                    'meta_data' => [
                        'pincode' => $proposal->pincode,                        
                        'sub_product' => $sub_product,
                        'source'  => strtoupper(config('constants.motorConstant.SMS_FOLDER')),
                        'first_name' => $proposal->first_name,
                        'last_name' => $proposal->last_name,
                        'is_renewal' => $proposal->corporate_vehicles_quotes_request->is_renewal == 'Y' &&  $proposal->corporate_vehicles_quotes_request->rollover_renewal != 'Y',
                        'proposal_no' => $proposal->proposal_no,
                        'CKYCUniqueId'=> $CKYCUniqueId                  #need to pass ckycuniqueId in requestId of A99Request CKYC req
                        ]
                ];
                $headers = [
                    'Accept' => 'application/json'
                ];

                break;

            case 'iffco_tokio':
                $customer_data = [
                    'passport_no' => $proposal->ckyc_type == 'passport' ? $proposal->ckyc_type_value : '',
                    'voter_id' => $proposal->ckyc_type == 'voter_id' ? $proposal->ckyc_type_value : '',
                    'driving_license' => $proposal->ckyc_type == 'driving_license' ? $proposal->ckyc_type_value : '',
                    'nrega_job_card' => $proposal->ckyc_type == 'nrega_job_card' ? $proposal->ckyc_type_value : '',
                    'national_population_register_letter' => $proposal->ckyc_type == 'national_population_register_letter' ? $proposal->ckyc_type_value : '',
                    'registration_certificate' => $proposal->ckyc_type == 'registration_certificate' ? $proposal->ckyc_type_value : '',
                    'certificate_of_incorporation' => $proposal->ckyc_type == 'certificate_of_incorporation' ? $proposal->ckyc_type_value : '',
                    'meta_data' => [
                        "first_name" => $proposal->first_name,
                        "middle_name" => "",
                        "last_name" => $proposal->last_name,
                        "gender" => $proposal->gender,
                        'ckyc_reference_id' => $proposal->ckyc_reference_id,
                        'customer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        "mobile" => $proposal->mobile_number
                    ]
                ];

                break;

            case 'magma':
                $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);
                $gender = ($corporate_vehicles_quotes_request->vehicle_owner_type == "I" ? ($proposal->gender ? $proposal->gender : '') : "MALE");


                $customer_data = [
                    'passport_no' => $proposal->ckyc_type == 'passport' ? $proposal->ckyc_type_value : '',
                    'voter_id' => $proposal->ckyc_type == 'voter_id' ? $proposal->ckyc_type_value : '',
                    'driving_license' => $proposal->ckyc_type == 'driving_license' ? $proposal->ckyc_type_value : '',
                    'cin' => $proposal->ckyc_type == 'cinNumber' ? $proposal->ckyc_type_value : '',
                    'meta_data' => [
                        'ckyc_reference_id' => $proposal->ckyc_reference_id,
                        'mobile_no' => $proposal->mobile_number,
                        'email_id' => $proposal->email,
                        'gender' => $gender,
                        'proposer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        'first_name' => $proposal->first_name,
                        'last_name' => $proposal->last_name
                    ]
                ];
                if($corporate_vehicles_quotes_request->vehicle_owner_type == 'C'){
                    $customer_place = $proposal->proposer_ckyc_details->meta_data;
                    $customer_data['meta_data']['incorporation_place'] = $proposal->city ?? null;
                }else{
                    $customer_data['meta_data']['relation_type'] = $proposal->proposer_ckyc_details->relationship_type ?? '';
                    $customer_data['meta_data']['related_person_name'] = $proposal->proposer_ckyc_details->related_person_name ?? '';
                }
                if($request->mode == 'voter_card') {
                    $request_data['mode'] = 'voter_id';
                    $customer_data['voter_id'] = $proposal->ckyc_type_value;
                }

                if ($request->mode == 'otp') {
                    $customer_data['meta_data']['otp'] = $request->otp;
                    $customer_data['meta_data']['client_id'] = $ckyc_meta_data['otp_id'];
                }
                break;
            
            case 'tata_aig':
                if (!empty($proposal->ckyc_meta_data)) {
                    $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);
                }

                if (config('constants.IcConstants.tata_aig_v2.IS_NEW_CKYC_FLOW_ENABLED_FOR_TATA_AIG_V2') != 'Y') {
                    $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $enquiry_id)->first();
                    $get_doc_data = json_decode($document_upload_data->cky_doc_data, true);

                    if(empty($get_doc_data)) {
                        return response()->json([
                            'data' => [
                                'message' => 'No documents found for CKYC Verification. Please upload any and try again.',
                                'verification_status' => false,
                            ],
                            'status' => false,
                            'message' => 'No documents found for CKYC Verification. Please upload any and try again.'
                        ]);
                    }

                    $poi_type = $get_doc_data['proof_of_identity']['poi_identity'];
                    if($poi_type == 'cinNumber' && empty($proposal->ckyc_reference_id))
                    {
                        return response()->json([
                            'data' => [
                                'message' => 'Kindly Try With PAN Number Before CIN',
                                'verification_status' => false,
                            ],
                            'status' => false,
                            'message' => 'Kindly Try With PAN Number Before CIN'
                        ]);
                    }
                    if ( ! in_array($poi_type, ['panNumber', 'cinNumber'])) {
                        return response()->json([
                            'data' => [
                                'message' => 'Proof of Identity must be Pan Number or CIN number.',
                                'verification_status' => false,
                            ],
                            'status' => false,
                            'message' => 'Proof of Identity must be Pan Number or CIN number.'
                        ]);
                    }

                    $poa_type = $get_doc_data['proof_of_address']['poa_identity'];

                    if(empty($proposal->proposal_no)) {
                        return response()->json([
                            'data' => [
                                'message' => 'Proposal Number not found. Please submit the proposal again.',
                                'verification_status' => false,
                            ],
                            'status' => false,
                            'message' => 'Proposal Number not found. Please submit the proposal again.'
                        ]);
                    }

                    $customer_data = [
                        "mode" => $request->mode == "otp" ? "otp" : "pan_number",
                        "gender" => $proposal->gender,
                        "ckyc_number" => $proposal->ckyc_type == "ckyc_number" ? $proposal->ckyc_type_value : null,
                        "pan_no" => $poi_type == "panNumber" ? $get_doc_data["proof_of_identity"]["poi_" . $poi_type] : null,
                        "aadhar" => $poa_type == "aadharNumber" ? $get_doc_data["proof_of_address"]["poa_" . $poa_type] : null,
                        "passport_number" => $poa_type == "passportNumber" ? $get_doc_data["proof_of_address"]["poa_" . $poa_type] : null,
                        "voter_id" => $poa_type == "voterId" ? $get_doc_data["proof_of_address"]["poa_" . $poa_type] : null,
                        "driving_license" => $poa_type == "drivingLicense" ? $get_doc_data["proof_of_address"]["poa_" . $poa_type] : null,
                        "cin_number" => $poi_type == "cinNumber" ? $get_doc_data["proof_of_identity"]["poi_" . $poi_type] : null,
                        "meta_data" => [
                            "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type,
                            "proposal_no" => $proposal->proposal_no,
                            "req_id" => $ckyc_meta_data["req_id"] ?? null,
                            "client_id" => $ckyc_meta_data["client_id"] ?? null,
                            "customer_name" => $proposal->first_name . " " . $proposal->last_name ,
                            "otp" => $request->otp ?? null,
                            "ic_version_type" => 'V1',
                            "cin_number" => $poi_type == "cinNumber" ? $get_doc_data["proof_of_identity"]["poi_" . $poi_type] : null,
                            "ckyc_reference_id" => $proposal->ckyc_reference_id
                        ]
                    ];
                    $customer_data['mode'] = $poi_type == "cinNumber" ? 'cinNumber' : $customer_data['mode'];
                   
                    $user_journey_type = UserProductJourney::with([
                        'sub_product'
                    ])
                    ->where('user_product_journey_id', $enquiry_id)
                    ->first();
                  
                    if($user_journey_type->product_sub_type_id == 1 || $user_journey_type->product_sub_type_id == 2  ) {
                        $product_code = config('constants.IcConstants.tata_aig.PRODUCT_ID');
                        if(config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED') == "Y") {
                            $product_code = '3184';
                            $customer_data['meta_data']['ic_version_type'] = 'V2';
                        }
                    } elseif (in_array($user_journey_type->sub_product?->parent_product_sub_type_id, [8])) {
                        $product_code = config('constants.IcConstants.tata_aig_pcv.PRODUCT_ID');
                        if (config('TATA_AIG_V2_PCV_FLOW') == "Y") {
                            $product_code = '3188';
                            $customer_data['meta_data']['ic_version_type'] = 'V2';
                        }
                    } else if($user_journey_type->product_sub_type_id == 2) {
                        $customer_data['meta_data']['ic_version_type'] = 'V2';
                        $product_code = config('constants.IcConstants.tata_aig.bike.PRODUCT_CODE');
                    } else {
                        $product_code = '3124';
                    }
                    $customer_data['meta_data']['token'] = config('constants.IcConstants.tata_aig.TOKEN');
                    $customer_data['meta_data']['product_code'] = $product_code;
                    $aadharCard = $pancard = $passport = $gst_number = $voterCard = $drivingLicense = '';
                    $doc_list = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . customEncrypt($enquiry_id));
                    if($request->mode != "otp") {
                        if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . customEncrypt($enquiry_id))) {
                            if (!isset($doc_list[0]) && empty($doc_list[0])) {
                                return response()->json([
                                    'data' => [
                                        'message' => 'Please upload document to complete proposal.',
                                        'verification_status' => false,
                                    ],
                                    'status' => false,
                                    'message' => 'Please upload document to complete proposal.'
                                ]);
                            } else {
                                // $file = \Illuminate\Support\Facades\Storage::get($doc_list[0]);
                                $file = ProposalController::getCkycDocument($doc_list[0]);
                                switch ($document_upload_data->doc_name) {
                                    case 'pan_card':
                                        $pancard = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode($file);
                                        break;
                                    case 'aadhar_card':
                                        $aadharCard = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                                        break;
                                    case 'gst_doc':
                                        $gst_number = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                                        break;
                                    case 'passport':
                                        $passport = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                                        break;
                                    case 'voter_card':
                                        $voterCard = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                                        break;
                                    case 'driving_license':
                                        $drivingLicense = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                                        break;
                                    default :
                                        break;
                                }
                            }
                        } else {
                        /* return response()->json([
                            'data' => [
                                'message' => 'Please upload Documents For CKYC Verifications.',
                                'verification_status' => false,
                            ],
                            'status' => false,
                            'message' => 'Please upload Documents For CKYC Verifications.'
                        ]); */
                        }
                        $customer_data['documents'] = [
                            [ 'type' => 'pan_card', 'data' => $pancard ],
                            [ 'type' => 'aadhar_card', 'data' => $aadharCard ],
                            [ 'type' => 'passport', 'data' => $passport ],
                            [ 'type' => 'gst_number', 'data' => $gst_number ],
                            [ 'type' => 'voter_id', 'data' => $voterCard ],
                            [ 'type' => 'driving_licence', 'data' => $drivingLicense ],
                        ];
                    }
                } else {
                    $additionalDetails = json_decode($proposal->additional_details, true) ?? [];

                    $ckycUnavailable = ($corporate_vehicles_quotes_request->vehicle_owner_type == "C" && (($additionalDetails['owner']['isCinPresent'] ?? false) == 'NO'));

                    //if mode is pan then do not call ckyc for cin 
                    if ($request->mode == 'pan_number') {
                        $ckycUnavailable = false;
                    }

                    $mode = ($request->mode == "otp" ? "otp" : (($request->mode == 'cin_number' || $ckycUnavailable) ? 'cin_number' : "pan_number"));

                    $poi_type = '';
                    if(config('TATA_CIN_NUMBER_FROM_POI') == 'Y') {
                        $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $enquiry_id)->first();
                        $get_doc_data = json_decode($document_upload_data->cky_doc_data, true);
                        $poi_type = $get_doc_data['proof_of_identity']['poi_identity'] ?? '';
                        if(!empty($proposal->ckyc_reference_id) && str_contains($proposal->ckyc_reference_id, 'pan_') && $poi_type = 'cinNumber') {
                            $mode = 'cinNumber';
                        }
                    }

                    $customer_data = [
                        "mode" => $mode,
                        "gender" => $proposal->gender,
                        "ckyc_number" => $proposal->ckyc_type == "ckyc_number" ? $proposal->ckyc_type_value : null,
                        "pan_no" => $proposal->pan_number,
                        "aadhar" => $proposal->ckyc_type == "aadhar_card" ? $proposal->ckyc_type_value : null,
                        "passport_no" => $proposal->ckyc_type == "passport" ? $proposal->ckyc_type_value : null,
                        "voter_id" => $proposal->ckyc_type == "voter_id" ? $proposal->ckyc_type_value : null,
                        "driving_license" => $proposal->ckyc_type == "driving_license" ? $proposal->ckyc_type_value : null,
                        "cin" => $mode == 'cinNumber' ? $proposal->ckyc_type_value : null,
                        "meta_data" => [
                            "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type,
                            "proposal_no" => $proposal->proposal_no,
                            "req_id" => $ckyc_meta_data["req_id"] ?? null,
                            "client_id" => $ckyc_meta_data["client_id"] ?? null,
                            "customer_name" => $proposal->first_name . " " . $proposal->last_name ,
                            "otp" => $request->otp ?? null,
                            "ic_version_type" => 'V1',
                            "ckyc_reference_id" => $proposal->ckyc_reference_id,
                            "ckycUnavailable" => $ckycUnavailable,
                            "cin_number" => $mode == 'cinNumber' ? $proposal->ckyc_type_value : null,
                        ]
                    ];

                    $product_sub_type_id = $proposal->user_product_journey->product_sub_type_id;

                    if ( ! empty($proposal->ckyc_meta_data)) {
                        $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);
                    }

                    if(in_array($product_sub_type_id, [1, 9,13,14,15])){
                        $product_code = config('constants.IcConstants.tata_aig.PRODUCT_ID');

                        if (config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED') == "Y") {
                            $product_code = '3184';
                            $customer_data['meta_data']['ic_version_type'] = 'V2';
                        }
                    } elseif (in_array($proposal->user_product_journey->sub_product?->parent_product_sub_type_id, [8]) && config('TATA_AIG_V2_PCV_FLOW') == 'Y' ) {
                            $product_code = '3188';
                            $customer_data['meta_data']['ic_version_type'] = 'V2';
                        
                    } elseif ($product_sub_type_id == 2) {
                        $product_code = config('constants.IcConstants.tata_aig.bike.PRODUCT_CODE');
                        $customer_data['meta_data']['ic_version_type'] = 'V2';
                    } else {
                        $product_code = '3124';
                    }

                    $customer_data['meta_data']['token'] = config('constants.IcConstants.tata_aig.TOKEN');
                    $customer_data['meta_data']['product_code'] = $product_code;

                    if(($request->is_form60 ?? false) === true) {
                        $form60_document_file = '';
                        $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $enquiry_id)->first();
                        $get_doc_data = json_decode($document_upload_data->cky_doc_data, true);
                        $doc_list = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . customEncrypt($enquiry_id));

                        $uploadForm60DocumentMessage = [
                            'data' => [
                                'message' => 'Please upload Form60 Document For CKYC Verifications.',
                                'verification_status' => false,
                            ],
                            'status' => false,
                            'message' => 'Please upload Form60 Document For CKYC Verifications.',
                        ];

                        if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . customEncrypt($enquiry_id))) {
                            if (!isset($doc_list[0]) && empty($doc_list[0])) {
                                return response()->json($uploadForm60DocumentMessage);
                            } else {
                                // $file = \Illuminate\Support\Facades\Storage::get($doc_list[0]);
                                $file = ProposalController::getCkycDocument($doc_list[0]);
                                if ($document_upload_data->doc_name == 'form60') {
                                    $form60_document_file = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                                } else {
                                    return response()->json($uploadForm60DocumentMessage);
                                }
                            }
                        } else {
                            return response()->json($uploadForm60DocumentMessage);
                        }
                        $customer_data['mode'] = 'form60';
                        $customer_data['documents'] = [
                            [ 'type' => 'form60', 'data' => $form60_document_file ],
                        ];
                    }
                }
                break;
                
            case 'united_india':
                $customer_data = [];
                break;

            case 'shriram':
                $customer_data = [
                    'driving_license' => $proposal->ckyc_type == 'driving_license' ? $proposal->ckyc_type_value : null,
                    'voter_id' => $proposal->ckyc_type == 'voter_id' ? $proposal->ckyc_type_value : null,
                    'passport' => $proposal->ckyc_type == 'passport' ? $proposal->ckyc_type_value : null,
                    'national_population_register_letter' => $proposal->ckyc_type == 'national_population_register_letter' ? $proposal->ckyc_type_value : null,
                    'meta_data' => [
                        'policy_number' => $proposal->proposal_no ?? null
                    ]
                ];
                break;

            case 'new_india':
                $customer_data = [
                    'meta_data' => [
                        'policy_holder_code' => $request->policyHolderCode ?? null,
                        'transaction_id' => $proposal->ckyc_reference_id,
                        'name' => implode(' ', array_filter([$proposal->first_name, $proposal->last_name])),
                        'gender' => $proposal->gender
                    ]
                ];
                break;
            
            case 'oriental':
                if (isset($request['HyperKycResult']['status'])) {

                    $start_time =  $end_time = microtime(true) * 1000;

                    $message = $request['HyperKycResult']['message'] ?? '';
                    if ((($request['HyperKycResult']['status'] ?? '') == 'auto_approved')) {
                        $message = 'success';
                    } else if (($request['HyperKycResult']['status'] ?? '') == 'auto_declined') {
                        $message = ($request['HyperKycResult']['details']['declineReason'] ?? 'CKYC Verification Failed');
                    }

                    $log = [
                        'request' => json_encode([], JSON_UNESCAPED_SLASHES),
                        'response' => json_encode($request['HyperKycResult'], JSON_UNESCAPED_SLASHES),
                        'headers' => json_encode([], JSON_UNESCAPED_SLASHES),
                        'endpoint_url' => '',
                        'status' => true,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'messgae' => '',
                        'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2),
                    ];
                    $ckyc_common_controller =  new \App\Http\Controllers\Ckyc\CkycCommonController();
                    $ckyc_common_controller->orientalSaveCkyclog(customDecrypt($request->enquiryId), 'ckyc', $log);

                    $data_update = [
                        'is_ckyc_verified' => $request['HyperKycResult']['status'] == 'auto_approved' ? 'Y' : 'N',
                        'ckyc_reference_id' => $request['HyperKycResult']['transactionId'],
                        'ckyc_meta_data' => $request['HyperKycResult'],
                        'ckyc_type' => !empty($request['HyperKycResult']['details']['pan']) ? 'pan_card' : $request['HyperKycResult']['details']['kycMode'],
                        'ckyc_type_value' => !empty($request['HyperKycResult']['details']['pan']) ? $request['HyperKycResult']['details']['pan'] : $request['HyperKycResult']['details']['idNumber'],
                    ];
                    $ckycNo = $request['HyperKycResult']['details']['ckycNo'] ?? '';
                    if(!empty($ckycNo) && !in_array(strtoupper($ckycNo), ['NA', 'NULL'])) {
                        $data_update['ckyc_number'] = $ckycNo;
                    }

                    ModelsUserProposal::where([
                        'user_product_journey_id' => customDecrypt($request->enquiryId)])
                        ->update(
                     $data_update);
                    
                    $address = '';
                    $panNumber = $proposal->pan_number;
                    if ( ! empty($request['HyperKycResult']['details']['permAddress'])) {
                        $address = $request['HyperKycResult']['details']['permAddress'];
                    } elseif ( ! empty($request['HyperKycResult']['details']['permanentAddress'])) {
                        $address = $request['HyperKycResult']['details']['permanentAddress'];
                    }

                    if ( ! empty($request['HyperKycResult']['details']['pan'])) {
                        $panNumber = $request['HyperKycResult']['details']['pan'];
                    } elseif ( ! empty($request['HyperKycResult']['details']['individualPAN'])) {
                        $panNumber = $request['HyperKycResult']['details']['individualPAN'];
                    }

                    if ($request['HyperKycResult']['status'] == 'auto_approved') {
                   
                        $address = '';
                        $panNumber = $proposal->pan_number;
                        if ( ! empty($request['HyperKycResult']['details']['permAddress'])) {
                            $address = $request['HyperKycResult']['details']['permAddress'];
                        } elseif ( ! empty($request['HyperKycResult']['details']['permanentAddress'])) {
                            $address = $request['HyperKycResult']['details']['permanentAddress'];
                        }
    
                        if ( ! empty($request['HyperKycResult']['details']['pan'])) {
                            $panNumber = $request['HyperKycResult']['details']['pan'];
                        } elseif ( ! empty($request['HyperKycResult']['details']['individualPAN'])) {
                            $panNumber = $request['HyperKycResult']['details']['individualPAN'];
                        }
                        if ((config("CHECK_PROPOSAL_HASH_ENABLE") == "Y" && $request->companyAlias == 'oriental') ) {
                            ProposalHash::create(
                                [   'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'user_proposal_id' => $proposal->user_proposal_id,
                                    'additional_details_data' => is_array($proposal->additional_details) ?  json_encode($proposal->additional_details) : $proposal->additional_details,
                                    'hash' => $request->proposalHash ?? null,  
                                ]
                            );
                        }
    
                        return response()->json([
                            'status' => true,
                            'message' => '',
                            'data' => [
                                'verification_status' => true,
                                'ckyc_id' => '',
                                'name' => '',
                                "customer_details" => array_filter([
                                    "fullName" => $request['HyperKycResult']['details']['insuredName'] ?? '',
                                    "dob" => str_replace('/', '-',$request['HyperKycResult']['details']['dob']) ?? '',
                                    "pincode" => $request['HyperKycResult']['details']['pincode'] ?? '',
                                    "address" => $address,
                                    "panNumber" => $panNumber
                                ]),
                            ],
                        ]);
                    } else if (isset($request['HyperKycResult']['status']) && $request['HyperKycResult']['status'] == 'auto_declined') {
    
                        return response()->json([
                            'status' => false,
                            'message' => $request['HyperKycResult']['details']['declineReason'] ?? 'CKYC Verification Failed',
                            'data' => [
                                'verification_status' => false,
                                'message' => $request['HyperKycResult']['details']['declineReason'] ?? 'CKYC Verification Failed',
                                'ckyc_id' => '',
                                'name' => '',
                                "customer_details" => []
                            ],
                        ]);
                    }
                }
                if(($request['isCkycVerified'] ?? false) === true){
                    $start_time =  $end_time = microtime(true) * 1000;
                    $log = [
                        'request' => json_encode([], JSON_UNESCAPED_SLASHES),
                        'response' => json_encode($request['HyperKycResult'], JSON_UNESCAPED_SLASHES),
                        'headers' => json_encode([], JSON_UNESCAPED_SLASHES),
                        'endpoint_url' => '',
                        'status' => true,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'messgae' => '',
                        'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2),
                    ];
                    $ckyc_common_controller =  new \App\Http\Controllers\Ckyc\CkycCommonController();
                    $ckyc_common_controller->orientalSaveCkyclog(customDecrypt($request->enquiryId), 'ckyc', $log);
                   
                    self::orientalProposalSave($request , $proposal);

                     return response()->json([
                        'status' => true,
                        'message' => '',
                        'data' => [
                            'kyc_status' => true,
                            'verification_status' => true,
                            'hidePopup' => true,
                            'redirection_url' => null,
                        ],
                    ]);
                }
                $customer_data = [];
                break;
                case 'nic':
                    if (($proposal->is_ckyc_verified ?? 'N') === 'Y' && !empty($proposal->ckyc_meta_data) && $proposal->ckyc_type == "aadhar_card") {
                        $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true) ?? []; 
                        if (($ckyc_meta_data['JourneyType'] ?? '') == 'ekyc') {
                            $array = [
                                'verification_status' => true,
                                'name' => trim($proposal->first_name . ' ' . $proposal->last_name),
                                'ckyc_reference_id' => $ckyc_meta_data['merchantId'] ?? null,
                                'ckyc_id' => "",
                                'message' => "",
                                'meta_data' => [
                                    'merchantId' => $ckyc_meta_data['merchantId'] ?? null,
                                    'JourneyType' => $ckyc_meta_data['JourneyType'] ?? null,
                                    'signzyAppId' => $ckyc_meta_data['signzyAppId'] ?? null,
                                ],
                                'customer_details' => [
                                    'fullName' => $ckyc_meta_data['name'] ?? null,
                                    'merchantId' => $ckyc_meta_data['merchantId'] ?? null,
                                    'JourneyType' => $ckyc_meta_data['JourneyType'] ?? null
                                ]
                            ];
                            return response()->json([
                                'data' => $array,
                                'status' => true,
                                'message' => null
                            ]);
                        }
                    }elseif(!empty($proposal->ckyc_meta_data)){
                        $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true) ?? []; 
                        if (($ckyc_meta_data['status'] ?? '') != 'success') {
                            $proposal->ckyc_meta_data = null;
                            $proposal->save();                            
                            return response()->json([
                                'status' => false,
                                'message' => $ckyc_meta_data['responseMessage'] ?? 'CKYC verification failed',
                                'data' => [
                                    'verification_status' => false,
                                    'message' => $ckyc_meta_data['responseMessage'] ?? 'CKYC verification failed',
                                    'ckyc_id' => '',
                                    'name' => '',
                                    "customer_details" => []
                                ],
                            ]);
                        }
                    }
                    $ckyc_meta_data = json_decode($proposal->ckyc_meta_data);
                    $customer_data = [
                        'passport_no' => $proposal->ckyc_type == 'passport' ? $proposal->ckyc_type_value : '',
                        'voter_id' => $proposal->ckyc_type == 'voter_id' ? $proposal->ckyc_type_value : '',
                        'driving_license' => $proposal->ckyc_type == 'driving_license' ? $proposal->ckyc_type_value : '',
                        'meta_data' => [
                            'ckyc_reference_id' => $proposal->ckyc_reference_id,
                            'transaction_id' => !empty($ckyc_meta_data) && !empty($ckyc_meta_data->transaction_id) ? $ckyc_meta_data->transaction_id : null,
                            'mobile_no'     => $proposal->mobile_number,
                            'email_id'      => $proposal->email,
                            'gender' => $proposal->gender ? $proposal->gender[0] : '',
                            'proposer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                            "full_name" => implode(' ', [$proposal->first_name, $proposal->last_name]),
                        ]
                    ];

                    if (!$request_data['pan_no']) {
                        $customer_data['pan_no'] = '';
                        if ($request->mode == "pan_number_with_dob" && !empty($proposal->pan_number)) {
                            $customer_data['pan_no'] = $proposal->pan_number;
                        }
                    }
    
                    if (!$request_data['aadhar']) {
                        $customer_data['aadhar'] = $proposal->ckyc_type_value;
                    }
    
                    if ($request->mode == "gstNumber" && !empty($proposal->gst_number)) {
                        $customer_data['gst_no'] = $proposal->gst_number;
                    }
                    break;
       
            default:
                return [];
                break;
        }

        $customer_data['meta_data']['is_corporate_case'] = $corporate_vehicles_quotes_request->vehicle_owner_type == 'C' ? true : false;

        if($proprietorshipCase) $customer_data['meta_data']['is_corporate_case'] = false;

        $request_data = array_merge($request_data, $customer_data);
        $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], $headers, [], true, false, $this->remove_proxy, true);
        if ($response['status'] == 200) {

            if ((!isset($response['response']['data']['message']) || empty($response['response']['data']['message'])) && !($response['response']['data']['verification_status'] ?? false)) {
                $response['response']['data']['message'] = 'CKYC verification failed';
            }

            $response['response']['status'] = $response['response']['data']['verification_status'] ?? false;
            $response['response']['message'] = isset($response['response']['data']['message']) ? $response['response']['data']['message'] : null;

            $pendingCase = in_array(($response['response']['data']['message'] ?? ''), ['Your previous CKYC request is currently being processed. Kindly wait for some time to receive the updated status.', 'Your CKYC is pending for further processing and approval, you are kindly requested to wait for some time and try again.']);

            if((! $response['response']['status']) && in_array($request->companyAlias, ['hdfc_ergo']) &&  $pendingCase) {
                return response()->json($response['response']);exit;
            }

            $data_update = [
                'is_ckyc_verified' => isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status'] ? 'Y' : 'N',
                'ckyc_number' => isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status'] ? $response['response']['data']['ckyc_id'] : null,
                'ckyc_reference_id' => isset($response['response']['data']['ckyc_reference_id']) ? $response['response']['data']['ckyc_reference_id'] : null,
                'ckyc_meta_data' => isset($response['response']['data']['meta_data']) && !empty($response['response']['data']['meta_data']) ? $response['response']['data']['meta_data'] : null
            ];
            if($request->companyAlias == 'tata_aig' && $request->mode == 'cinNumber')
            {
                unset($data_update['ckyc_reference_id']);
            }
            if ($request->companyAlias == 'royal_sundaram' && isset($response['response']['data']['customer_details']['dob'])) {
                $response['response']['data']['customer_details']['dob'] = str_replace('/', '-', $response['response']['data']['customer_details']['dob']);
            }
            if((! $response['response']['status']) && in_array($request->companyAlias, ['sbi']) && app()->environment() != 'local') {
                $response['response']['message'] = 'CKYC verification failed. Try other method';
            }

            /* if ($data_update['is_ckyc_verified'] == 'Y' && $request->companyAlias == 'iffco_tokio' && isset($response['response']['data']['meta_data']['ckyc_status']) && $response['response']['data']['meta_data']['ckyc_status'] != 'CKYCSuccess') {
                $data_update['is_ckyc_verified'] = 'N';
            } */

            ModelsUserProposal::updateOrCreate([
                'user_product_journey_id' => customDecrypt($request->enquiryId)
            ], $data_update);
            // Need to update the CKYC status for RB
            event(new \App\Events\CKYCInitiated($enquiry_id));

            if ($response['response']['data']['verification_status']) {
                $response['response']['ckyc_verified_using'] = $request_data['mode'];
            }

            if (in_array($request->companyAlias, ['universal_sompo', 'hdfc_ergo', 'future_generali', 'edelweiss']) && ! $response['response']['status'] && ! $this->is_checked_using_reference_id && ($request_data['mode'] == 'ckyc_reference_id' || $request_data['mode'] == 'validate_ckyc_reference_id')) {
                $this->is_checked_using_reference_id = true;
                return $this->ckycVerifications(new Request($request->all()));
            }

            if ($request->companyAlias == 'future_generali') {
                unset($response['response']['data']['customer_details']['address']);
                unset($response['response']['data']['customer_details']['pincode']);
            }


            if($request->companyAlias == 'tata_aig' && $request->mode == 'otp') {
                if(($response['response']['data']['verification_status'] ?? false)) {
                    $breakin_details = $proposal->breakin_status;
                    $proposal_additional_details_data = json_decode($proposal->additional_details_data, true);
                    if(($proposal_additional_details_data['is_breakin_case'] ?? '') == 'Y' && !empty($proposal_additional_details_data['ticket_number'] ?? ''))
                    {
                        tataAigV2SubmitProposal::createTataBreakindata($proposal, $proposal_additional_details_data['ticket_number']);
                        $proposal->is_breakin_case = 'Y';
                        $breakin_details = (object)['breakin_number' => $proposal_additional_details_data['ticket_number']];
                    }
                    $response['response'] =  [
                        'status' => true,
                        'msg' => 'OTP Verified..',
                        'data' => [
                            'verification_status' => true,
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal->user_product_journey_id,
                            'proposalNo' => $proposal->proposal_no,
                            'finalPayableAmount' => $proposal->final_payable_amount,
                            'is_breakin' => $proposal->is_breakin_case,
                            'isBreakinCase' => $proposal->is_breakin_case,
                            'inspection_number' => $breakin_details->breakin_number ?? '',
                            'kyc_verified_using' => $request_data['mode']
                        ],
                    ];
                } else {
                    $response['response']['message'] = isset($response['response']['data']['message']) ? $response['response']['data']['message'] : "OTP Verification failed.";
                }
            }
            /* if($request->companyAlias == 'tata_aig' && $request->mode == 'pan_number' && $corporate_vehicles_quotes_request->vehicle_owner_type == 'C' && $response['response']['data']['verification_status'] == false) 
            {
                //handle response
                if(!(strpos($response['response']['data']['message'], 'Insurer not reachable') !== false || strpos($response['response']['data']['message'], '.php') !== false || strpos($response['response']['data']['message'], 'Server down') !== false || strpos($response['response']['data']['message'], 'Details not matching. Please enter again') !== false))
                {
                    $response['response']['data']['message'] = $response['response']['data']['message'].'.Try With CIN Number in Case of Company.';
                }
            } */

            if ($request->companyAlias == 'iffco_tokio') {
                unset($response['response']['data']['customer_details']['address']);
                unset($response['response']['data']['customer_details']['pincode']);
            }
        } else {
            if(!empty($response['response']['exception'] ?? '') == 'App\Exceptions\TenantCredentialsNotFoundException') {
                $err_message = 'Credentials not configured for this IC : ' . $response['response']['message'];
                return response()->json([
                    'status' => false,
                    'message' => $err_message,
                    'data' => [
                        'message' => $err_message,
                        'verification_status' => false,
                    ],
                ]);
            }
            $response['response']['status'] = false;
            $response['response']['message'] = 'Something went wrong';
        }

        $response = $this->setGenderInApi($response,$request);

        if (config('IS_PROPRIETORSHIP_CASE_ENABLED') == 'Y' && $proprietorshipCase) {
            $proprietorshipFields = (json_decode(config('PROPRIOTERSHIP_AVAILABLE_ICS_FIELDS'), 1) ?? []);
            if (isset($proprietorshipFields[$request->companyAlias])) {
                if (!empty($proprietorshipFields[$request->companyAlias])) {
                    $customer_details = $response['response']['data']['customer_details'];
                    if (isset($request_data['mode']) && $request_data['mode'] == 'pan_number_with_dob') {
                        foreach ($customer_details as $key => $value) {
                            if (!in_array($key, $proprietorshipFields[$request->companyAlias])) unset($response['response']['data']['customer_details'][$key]);
                        }
                    }
                } else {
                    $response['response']['data']['customer_details'] = [];
                }
            }
        }
        
        if($request->companyAlias == 'sbi')
        {
            if(isset($response['response']['data']['customer_details']['name']))
            {
               $response['response']['data']['customer_details']['fullName'] = $response['response']['data']['customer_details']['name']; 
               unset($response['response']['data']['customer_details']['name']);
            }

            if($corporate_vehicles_quotes_request->vehicle_owner_type == 'C' && !empty($response['response']['data']['customer_details']['fullName'])) {
                $response['response']['data']['customer_details']['firstName'] = $response['response']['data']['customer_details']['fullName'];
                unset($response['response']['data']['customer_details']['fullName']);
            }
            
            if(isset($response['response']['data']['customer_details']['gender']) && in_array(strtoupper($response['response']['data']['customer_details']['gender']),['M','F']))
            {
                $response['response']['data']['customer_details']['genderName'] = Gender::where('company_alias', $request->companyAlias)
                                                    ->where('gender_code', $response['response']['data']['customer_details']['gender'])
                                                    ->pluck('gender')
                                                    ->first();
            }
            if(isset($response['response']['data']['customer_details']))
            {
                $response['response']['data']['customer_details'] = camelCase($response['response']['data']['customer_details']);
            }

            if (!empty($response['response']['data']['customer_details']['dob'] ?? null)) {
                $response['response']['data']['customer_details']['dob'] = date("d-m-Y", strtotime($response['response']['data']['customer_details']['dob']));
            }
            unset($response['response']['data']['customer_details']['city']);
            unset($response['response']['data']['customer_details']['state']);
            unset($response['response']['data']['customer_details']['district']);
                        
            $update_data['ckyc_meta_data'] = json_encode($response['response']['data']['customer_details']);
            ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->update($update_data);    
            return response()->json(($response['response']));
        }       

        if($request->companyAlias == 'reliance' && (isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status']))
        {
            $additional_details = json_decode($proposal->additional_details, true);

            if (!empty($response['response']['data']['customer_details']['address'] ?? '')){
                $additional_details['owner']['address'] = $response['response']['data']['customer_details']['address'];
                $additional_details['owner']['addressLine1'] = $response['response']['data']['customer_details']['address'];
            }

            if (!empty($response['response']['data']['customer_details']['pincode'] ?? '')){
                $additional_details['owner']['pincode'] = $response['response']['data']['customer_details']['pincode'];
            }

            if (!empty($response['response']['data']['customer_details']['dob'] ?? '') && !in_array($response['response']['data']['customer_details']['dob'],[' ','null','NULL','[]','--'])){
                $additional_details['owner']['dob'] = date("d-m-Y" ,strtotime(str_replace('/', '-', $response['response']['data']['customer_details']['dob'])));
            }

            if (!empty($response['response']['data']['customer_details']['email'] ?? '')){ 
                $additional_details['owner']['email'] = $response['response']['data']['customer_details']['email'];
            }

            if (!empty($response['response']['data']['customer_details']['fullName'] ?? '')){
                $additional_details['owner']['fullName'] = $response['response']['data']['customer_details']['fullName'];
                $additional_details['owner']['firstName'] = $response['response']['data']['customer_details']['fullName'];
            }
            elseif ( ! empty($response['response']['data']['customer_details']['firstName'] ?? '')) {
                $additional_details['owner']['fullName'] = $response['response']['data']['customer_details']['firstName'];
                $additional_details['owner']['firstName'] = $response['response']['data']['customer_details']['firstName'];
            }

            if (!empty($response['response']['data']['customer_details']['mobileNumber'] ?? '')){
                $additional_details['owner']['mobileNumber'] = $response['response']['data']['customer_details']['mobileNumber'];
            }

            if (!empty($response['response']['data']['customer_details']['panNumber'] ?? '')){
                $additional_details['owner']['panNumber'] = $response['response']['data']['customer_details']['panNumber'];
            }

            if (!empty($response['response']['data']['customer_details']['ckycNumber'] ?? '')){
                $additional_details['owner']['ckycNumber'] = $response['response']['data']['customer_details']['ckycNumber'];
            }

            $update_data['additional_details'] = json_encode($additional_details);
            $update_data['ckyc_meta_data'] = json_encode($response['response']['data']);
            ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->update($update_data);    
        }
        
        if($request->companyAlias == 'cholla_mandalam')
        {
            if (!empty($response['response']['data']['customer_details']['fullName']))
            {
                $fullName = explode(' ',$response['response']['data']['customer_details']['fullName']);
                if(in_array($fullName[0],['Mr']))
                {
                    $name = removeSalutation($response['response']['data']['customer_details']['fullName']);
                    $response['response']['data']['customer_details']['fullName'] =  trim($name);
                    $response['response']['data']['name'] =  trim($name);
                    $response['response']['data']['name_without_modified'] =  $response['response']['data']['customer_details']['fullName'];
                }
            }            
        }

        if($request->companyAlias == 'universal_sompo') {
            if(!empty($response['response']['data']['customer_details']['address_line_1'])) {
                $mappedAddressArray = array_map(function ($a) {
                    return trim($a);
                }, [
                    ($response['response']['data']['customer_details']['address_line_1'] ?? ''),
                    ($response['response']['data']['customer_details']['address_line_2'] ?? ''),
                    ($response['response']['data']['customer_details']['address_line_3'] ?? ''),
                ]);
            
                $address  = implode(' ', array_filter($mappedAddressArray));
                if(!empty($address)) {
                    unset($response['response']['data']['customer_details']['address_line_1']);
                    unset($response['response']['data']['customer_details']['address_line_2']);
                    unset($response['response']['data']['customer_details']['address_line_3']);
                }
                $response['response']['data']['customer_details']['addressLine1'] = $address;
            }
        }
        if($request->companyAlias == 'kotak') {
            if(!empty($response['response']['data']['meta_data']['TokenId'])) {
                $updated_proposal['ckyc_extras'] = json_encode([
                    'vTokenID' => $response['response']['data']['meta_data']['TokenId']
                ]);

            }
            if (isset($updated_proposal) && ! empty($updated_proposal)) {
                ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                    ->update($updated_proposal);
            }
        }

        foreach ($response['response']['data']['customer_details'] as $key => $value) {
            if (empty($value)) {
                unset($response['response']['data']['customer_details'][$key]);
            }
        }

        if($request->companyAlias == 'hdfc_ergo') {
            if(in_array($request_data['mode'], ['ckyc_reference_id', 'corporate_kyc_id', 'transaction_id'])) {
                if(!($response['response']['data']['verification_status'] ?? false) && empty($response['response']['data']['redirection_url'])) {
                    return $this->ckycVerifications(new Request($request->all()));
                }
            }
        }
        return response()->json($response['response']);
    }

    public function ckycResponse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trace_id' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ]);
        }

        $this->remove_proxy = config('constants.REMOVE_PROXY_FOR_CKYC') != 'N' ? true : false;

        $request->company_alias = (($request->company_alias == 'reliance_general') ? 'reliance' : $request->company_alias);
        $request->company_alias = (($request->company_alias == 'liberty_general') ? 'liberty_videocon' : $request->company_alias);

        $request->trace_id = explode('?', $request->trace_id)[0];

        $enquiry_id = customDecrypt($request->trace_id);

        $journey_stage = JourneyStage::where('user_product_journey_id', $enquiry_id)->first();
        $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();

        if ($journey_stage) {
            switch ($request->company_alias) {
                case 'hdfc_ergo':
                    $updated_proposal['ckyc_reference_id'] = $request->kycId;

                    if (isset($request->success) && $request->success) {
                        $ckyc_verification_type = CkycVerificationTypes::where('company_alias', 'hdfc_ergo')->pluck('mode')->first();

                        $updated_proposal['is_ckyc_verified'] = 'Y';

                        $request_data = [
                            'company_alias' => 'hdfc_ergo',
                            'type' => $ckyc_verification_type,
                            'mode' => 'kyc_id',
                            'section' => 'motor',
                            'trace_id' => $request->trace_id,
                            'meta_data' => [
                                'kyc_id' => $request->kycId,
                                'customer_type' => $proposal->corporate_vehicles_quotes_request->vehicle_owner_type
                            ]
                        ];

                        $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
                            'Content-Type' => 'application/json'
                        ], [], true, false, $this->remove_proxy, true);

                        if ($response['status'] == 200) {
                            if ($response['response']['data']['verification_status']) {
                                $updated_proposal_data = self::saveCkycResponseInProposal($request, $response, $proposal);
                                $updated_proposal = array_merge($updated_proposal, $updated_proposal_data);
                            }
                        }

                    }

                    ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                        ->update($updated_proposal);
                    break;

                case 'edelweiss':
                    break;

                case 'future_generali':
                    $ckyc_verification_type = CkycVerificationTypes::where('company_alias', 'future_generali')->pluck('mode')->first();

                    $request_data = [
                        'company_alias' => 'future_generali',
                        'type' => $ckyc_verification_type,
                        'mode' => 'proposal_id',
                        'section' => 'motor',
                        'trace_id' => $request->trace_id,
                        'meta_data' => [
                            'proposal_id' => $proposal->ckyc_reference_id
                        ]
                    ];

                    $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
                        'Content-Type' => 'application/json'
                    ], [], true, false, $this->remove_proxy, true);

                    if ($response['status'] == 200) {
                        if ($response['response']['data']['verification_status']) {
                            $updated_proposal['is_ckyc_verified'] = 'Y';

                            $updated_proposal_data = self::saveCkycResponseInProposal($request, $response, $proposal);
                            $updated_proposal = array_merge($updated_proposal, $updated_proposal_data);

                            ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                            ->update($updated_proposal);
                        }/*  else {
                            ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                                ->update([
                                    'ckyc_verification_failure_popup_to_be_shown' => 'Y'
                                ]);
                        } */
                    }
                    break;

                case 'kotak':
                    if (isset($request->vTokenID) && ! empty($request->vTokenID)) {
                        $ckyc_verification_type = CkycVerificationTypes::where('company_alias', 'kotak')->pluck('mode')->first();

                        $request_data = [
                            'company_alias' => 'kotak',
                            'type' => $ckyc_verification_type,
                            'mode' => 'token_id',
                            'section' => 'motor',
                            'trace_id' => $request->trace_id,
                            'meta_data' => [
                                'user_id' => config('constants.IcConstants.kotak.KOTAK_BIKE_USERID'),
                                'password' => config('constants.IcConstants.kotak.KOTAK_BIKE_PASSWORD'),
                                'token_id' => $request->vTokenID,
                                'partner_request_id' => $proposal->ckyc_reference_id,
                                'status_check' => config('KOTAK_KYC_REFERENCE_ID_STATUS_CHECK')
                            ]
                        ];

                        $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
                            'Content-Type' => 'application/json'
                        ], [], true, false, $this->remove_proxy, true);

                        if ($response['status'] == 200) {
                            if ($response['response']['data']['verification_status']) {
                                $updated_proposal = self::saveCkycResponseInProposal($request, $response, $proposal);
                                $updated_proposal['is_ckyc_verified'] = 'Y';
                            }
                        }

                        $updated_proposal['ckyc_extras'] = json_encode([
                            'vTokenID' => $request->vTokenID
                        ]);

                        if (isset($updated_proposal) && ! empty($updated_proposal)) {
                            ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                                ->update($updated_proposal);
                        }
                    }
                    break;
    
                case 'liberty_videocon':
                    $KYC_Status = ($request->KYC_Status ?? '');

                    $ckyc_reference_id = ((in_array($KYC_Status, ['1','3'])) ? ($request->IC_KYC_No ?? '') : '');
                    $ProposerCKYCNo = (in_array($KYC_Status, ['1','3']) ? ($request->ProposerCKYCNo ?? '') : '');

                    $update_data = [
                        'ckyc_reference_id' => $ckyc_reference_id,
                        'ckyc_number' => $ProposerCKYCNo,
                        'is_ckyc_verified' => (in_array($KYC_Status, ['1','3']) ? 'Y' : 'N'),
                    ];

                    $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();
                    $additional_details = json_decode($proposal->additional_details, true);

                    if(in_array($KYC_Status, ['1','3'])){
                        $fullName       = trim($request->FirstName.' '.$request->MiddleName.' '.$request->LastName);
                        $genderName     = ($request->Gender == 'M' ? 'Male' : 'Female');
                        $ownerType      = ((($request->ProposerType ?? '') == 'C') ? 'C' : 'I');
                        $dob            = ((($request->DOB ?? '') != '') ? (date("Y-m-d" ,strtotime($request->DOB))) : '');
                        $panNumber      = ((($request->ProposerPAN ?? '') != '') ? ($request->ProposerPAN) : '');
                        $aadharNumber   = ((($request->ProposerAadhaarNumber ?? '') != '') ? ($request->ProposerAadhaarNumber) : '');

                        $ckyc_meta_data = [
                            'ckyc_meta_data'=>[
                                'IC_KYC_No'             => $ckyc_reference_id,
                                'Aggregator_KYC_Req_No' => ($request->Aggregator_KYC_Req_No ?? ''),
                                'IC_response'           => $request->all()
                            ]
                        ];

                        if($fullName && !empty($fullName)){
                            if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
                                $additional_details['owner']['fullName'] = $fullName;
                                
                                $additional_details['owner']['firstName'] = CommonController::parseFullName($fullName)['firstName'];
                                $additional_details['owner']['lastName'] = CommonController::parseFullName($fullName)['lastName'];

                                $update_data['first_name'] = $additional_details['owner']['firstName'];
                                $update_data['last_name'] = $additional_details['owner']['lastName'];
                                
                                $customer_details['fullName'] = $fullName;
                                $customer_details['firstName'] = $additional_details['owner']['firstName'];
                                $customer_details['lastName'] = $additional_details['owner']['lastName'];

                            } else {
                                $additional_details['owner']['firstName'] = $fullName;
                                $customer_details['firstName'] = $fullName;
                            }
                        } if ($request->MobileNo && !empty($request->MobileNo)){
                            $additional_details['owner']['mobileNumber'] = $request->MobileNo;
                            $customer_details['mobileNumber'] = $request->MobileNo;
                        } if ($request->Email && !empty($request->Email)){
                            $additional_details['owner']['email'] = $request->Email;
                            $customer_details['email'] = $request->Email;
                        } if ($dob && !empty($dob)){
                            $additional_details['owner']['dob'] = $dob;
                            $customer_details['dob'] = $dob;
                        } if ($genderName && !empty($genderName)){
                            $additional_details['owner']['genderName'] = $genderName;
                            $customer_details['genderName'] = $genderName;
                        } if ($ownerType && !empty($ownerType)){
                            $additional_details['owner']['ownerType'] = $ownerType;
                            $customer_details['ownerType'] = $ownerType;
                        } if ($panNumber && !empty($panNumber)){
                            $additional_details['owner']['panNumber'] = $panNumber;
                            $customer_details['panNumber'] = $panNumber;
                            $update_data = array_merge($update_data, ['pan_number' => $panNumber]);
                        } if ($aadharNumber && !empty($aadharNumber)){
                            $additional_details['owner']['aadharNumber'] = $aadharNumber;
                            $customer_details['aadharNumber'] = $aadharNumber;
                        }

                        $ckyc_meta_data['ckyc_meta_data'] = array_merge($ckyc_meta_data['ckyc_meta_data'], ['customer_details' => $customer_details]);

                        $update_data = array_merge($update_data, $ckyc_meta_data);

                        $update_data['additional_details'] = json_encode($additional_details);
                    }

                    ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                        ->update($update_data);
                    break;

                case 'royal_sundaram':
                    $ckyc_verification_type = CkycVerificationTypes::where('company_alias', 'royal_sundaram')->pluck('mode')->first();
                    $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();
                    $corporate_vehicles_quotes_request = $proposal->corporate_vehicles_quotes_request;

                    $quote_log = $proposal->quote_log->premium_json;
                    $request_data = [
                        'company_alias' => 'royal_sundaram',
                        'type' => $ckyc_verification_type,
                        'mode' => 'fetch_api',
                        'section' => 'motor',
                        'trace_id' => $request->trace_id,
                        'meta_data' => [
                            'uniqueId' => $quote_log['quoteId'],
                            'owner_type' => $corporate_vehicles_quotes_request->vehicle_owner_type
                        ]
                    ];

                    $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
                        'Content-Type' => 'application/json'
                    ], [], true, false, $this->remove_proxy, true);
                    if ($response['status'] == 200 && isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status']) {
                        self::royalSundaramUpdateVerificationData($response, $proposal);
                    }
                    break;

                case 'reliance':
                    $kyc_verified = ($request->kyc_verified ?? '');
            
                    $proposal_id = (($kyc_verified == 'true') ? ($request->proposal_id ?? '') : '');
                    $ckyc_number = (($kyc_verified == 'true') ? ($request->ckyc_number ?? '') : '');
            
                    $update_data = [
                        'ckyc_reference_id' => $proposal_id,
                        'ckyc_number' => $ckyc_number,
                        'is_ckyc_verified' => (($kyc_verified == 'true') ? 'Y' : 'N'),
                    ];
            
                    $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();
                    $additional_details = json_decode($proposal->additional_details, true);
            
                    if($kyc_verified == 'true'){
                        $fullName       = trim($request->registered_name);
                        $genderName     = (($request->gender == 'M') ? 'Male' : (($request->gender == 'F') ? 'Female' : null));
                        $dob            = ((($request->dob ?? '') != '' && !in_array($request->dob,[' ','null','NULL','[]','--'])) ? (date('d-m-Y' ,strtotime(str_replace('/', '-', $request->dob)))) : '');
                        $panNumber      = ((($request->pan_no ?? '') != '') ? ($request->pan_no) : (((($request->id_type ?? '') == 'PAN') && (($request->id_num ?? '') != '')) ? ($request->id_num) : ''));
                        $aadharNumber   = (((($request->id_type ?? '') == 'AADHAAR') && (($request->id_num ?? '') != '')) ? ($request->id_num) : '');
                        //Proprietorship
                        $gstNumber      = (($request->id_type ?? '') == 'GST' && !empty($request->id_num)) ? $request->id_num : '';
            
                        $address = ((($request->corr_address_line1 ?? '') != '') ? $request->corr_address_line1 : '') . ((($request->corr_address_line2 ?? '') != '') ? $request->corr_address_line2 : '');
            
                        $pincode = ((($request->corr_address_pincode ?? '') != '') ? $request->corr_address_pincode : '');

                        $proprietorshipCase = false;

                        $canUpdateInDb = ["address", "pincode", "dob", "email", "fullName", "mobileNumber", "genderName"];

                        if (config('IS_PROPRIETORSHIP_CASE_ENABLED') == 'Y') {
                            $proprietorshipFields = (json_decode(config('PROPRIOTERSHIP_AVAILABLE_ICS_FIELDS'), 1) ?? []);
                            $proprietorshipCase = (isset($proprietorshipFields[$request->company_alias]) && (($proposal->proposer_ckyc_details->organization_type ?? null) == 'Proprietorship')) ? true : false;
                        }
                        if (config('IS_PROPRIETORSHIP_CASE_ENABLED') == 'Y' && $proprietorshipCase) {
                            $proprietorshipFields = (json_decode(config('PROPRIOTERSHIP_AVAILABLE_ICS_FIELDS'), 1) ?? []);
                            if (isset($proprietorshipFields[$request->company_alias])) {
                                if (!empty($proprietorshipFields[$request->company_alias])) {
                                    foreach ($canUpdateInDb as $value) {
                                        if (!in_array($value, $proprietorshipFields[$request->company_alias])) {
                                            if (array_search($value, $canUpdateInDb) !== false) {
                                                array_splice($canUpdateInDb, array_search($value, $canUpdateInDb), 1);
                                            }
                                        }
                                    }
                                } else {
                                    $canUpdateInDb = [];
                                }
                            }
                        }
            
                        // NAME, EMAIL, MOBILE, DOB, GENDER, PAN
            
                        $ckyc_meta_data = [
                            'ckyc_meta_data'=>[]
                        ];

                        $updated_proposal = [];

                        $first_name = null;
                        $last_name = null;

                        if ($fullName && !empty($fullName) && in_array('fullName', $canUpdateInDb)) {
                            if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
                                $additional_details['owner']['fullName'] = $fullName;
                                $customer_details['fullName'] = $fullName;

                                $name = explode(' ', $fullName);
                                $first_name = $name[0];
                                $last_name = null;

                                if (count($name) > 1) {
                                    if (count($name) > 2) {
                                        $fname_array = $name;
                                        unset($fname_array[count($fname_array) - 1]);
                                        $first_name = implode(' ', $fname_array);
                                        $last_name = $name[count($name) - 1];
                                    } else {
                                        $last_name = $name[1];
                                    }
                                }

                                if (count($name) > 1) {
                                    if (count($name) > 2) {
                                        $fname_array = $name;
                                        unset($fname_array[count($fname_array) - 1]);
                                        $first_name = implode(' ', $fname_array);
                                        $last_name = $name[count($name) - 1];
                                    } else {
                                        $last_name = $name[1];
                                    }
                                }
                                
                                $updated_proposal['first_name'] = $customer_details['firstName'] = $additional_details['owner']['firstName'] = $first_name;
                                $updated_proposal['last_name'] = $customer_details['lastName'] = $additional_details['owner']['lastName'] = $last_name;
                            } else {
                                $additional_details['owner']['firstName'] = $fullName;
                                $customer_details['firstName'] = $fullName;
                                $updated_proposal['first_name'] = $fullName;
                                $additional_details['owner']['lastName'] = $customer_details['lastName'] = $updated_proposal['last_name'] = null;
                            }
                        }
                        if($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'C' && $request->id_type != 'PAN'){
                            $additional_details['owner']['fullName'] = $fullName;
                            $customer_details['fullName'] = $fullName;
                            
                            $updated_proposal['first_name'] = $customer_details['firstName'] = $additional_details['owner']['firstName'] = $fullName;
                            $updated_proposal['last_name'] = $customer_details['lastName'] = $additional_details['owner']['lastName'] = '';
                        }
                        if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I' && $request->mobile && !empty($request->mobile) && in_array('mobileNumber', $canUpdateInDb)) {
                            $additional_details['owner']['mobileNumber'] = $request->mobile;
                            $customer_details['mobileNumber'] = $request->mobile;
                            $updated_proposal['mobile_number'] = $request->mobile;
                        }
                        if ($request->email && !empty($request->email) && in_array('email', $canUpdateInDb)) {
                            $additional_details['owner']['email'] = $request->email;
                            $customer_details['email'] = $request->email;
                            $updated_proposal['email'] = $request->email;
                        }
                        if ($dob && !empty($dob) && $proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'C' && $request->id_type != 'PAN')
                        {
                            $additional_details['owner']['dob'] = $dob;
                            $customer_details['dob'] = $dob;
                            $updated_proposal['dob'] = $dob;
                        }
                        if ($dob && !empty($dob) && in_array('dob', $canUpdateInDb)) {
                            $additional_details['owner']['dob'] = $dob;
                            $customer_details['dob'] = $dob;
                            $updated_proposal['dob'] = $dob;
                        }
                        if ($genderName && !empty($genderName) && in_array('genderName', $canUpdateInDb)) {
                            $additional_details['owner']['genderName'] = $genderName;
                            $customer_details['genderName'] = $genderName;

                            if (!empty($genderName)) {
                                $updated_proposal['gender'] = $request->gender;
                                $updated_proposal['gender_name'] = $genderName;
                            }
                        }
                        if ($panNumber && !empty($panNumber)){
                            $panNumber = strtoupper($panNumber);
                            if (preg_match("/^([a-zA-Z]){5}([0-9]){4}([a-zA-Z]){1}?$/", ($panNumber)))
                            {
                                $additional_details['owner']['panNumber'] = $panNumber;
                                $customer_details['panNumber'] = $panNumber;
                                $update_data = array_merge($update_data, ['pan_number' => $panNumber]);

                                $updated_proposal['pan_number'] = $panNumber;
                            }
                        }
                        // if ($panNumber && !empty($panNumber)){
                        //     $additional_details['owner']['panNumber'] = $panNumber;
                        //     $customer_details['panNumber'] = $panNumber;
                        //     $update_data = array_merge($update_data, ['pan_number' => $panNumber]);
                        // }
                        if ($aadharNumber && !empty($aadharNumber)){
                            $additional_details['owner']['aadharNumber'] = $aadharNumber;
                            $customer_details['aadharNumber'] = $aadharNumber;
                        }
                        if ($address && !empty($address)){
                            $additional_details['owner']['address'] = $address;
                            $additional_details['owner']['addressLine1'] = $address;
                            $customer_details['address'] = $address;
                            $customer_details['addressLine1'] = $address;
                            $updated_proposal['address_line1'] = $address;
                        }
                        if ($pincode && !empty($pincode)){
                            $additional_details['owner']['pincode'] = $pincode;
                            $customer_details['pincode'] = $pincode;
                            $updated_proposal['pincode'] = $pincode;
                        }
                        if (($request->id_type ?? '') == 'UDYOG') {
                            $idNum      = explode('-', $request->id_num);

                            if ($idNum[0] == 'UDYAM') {
                                $udyam = array_slice($idNum, 1);
                                $udyam = implode('-', $udyam);

                                if (!empty($udyam)) {
                                    $additional_details['owner']['identity'] = 'udyam';
                                    $additional_details['owner']['udyam'] = $udyam;
                                    $customer_details['udyam'] = $udyam;

                                    $updated_proposal['ckyc_type_value'] = $udyam;
                                    $updated_proposal['ckyc_type'] = 'udyam';
                                }
                            }else{  
                                $udyog = $request->id_num;
                                if(!empty($udyog)){
                                    $additional_details['owner']['identity'] = 'udyog';
                                    $additional_details['owner']['udyog'] = $udyog;
                                    $customer_details['udyog'] = $udyog;

                                    $updated_proposal['ckyc_type_value'] = $udyog;
                                    $updated_proposal['ckyc_type'] = 'udyog';
                                }
                            }
                        }
                        if (!empty($gstNumber)) {
                            $additional_details['owner']['identity'] = 'gstNumber';
                            $additional_details['owner']['gstNumber'] = $gstNumber;
                            $customer_details['gstNumber'] = $gstNumber;
                            $update_data = array_merge($update_data, ['gst_number' => $gstNumber]);

                            $updated_proposal['ckyc_type_value'] = $gstNumber;
                            $updated_proposal['gst_number'] = $gstNumber;
                            $updated_proposal['ckyc_type'] = 'gstNumber';
                        }
            
                        $ckyc_meta_data = [
                            'ckyc_meta_data'=>[
                                "name" => $fullName ?? null,
                                "newTab" => false,
                                "otp_id" => null,
                                "ckyc_id" => $ckyc_number ?? null,
                                "message" => null,
                                "meta_data" => [],
                                "accessToken" => false,
                                "disabled_field" => null,
                                "redirection_url" => null,
                                "ckyc_reference_id" => null,
                                "verification_status" => true,
                                "show_document_fields" => false,
                                "redirection_url_via_form" => null
                            ]
                        ];

                        if($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'C'){
                            $ckyc_meta_data['ckyc_meta_data']['ckyc_mode'] = $request->id_num;
                        }

                        $ckyc_meta_data['ckyc_meta_data'] = array_merge($ckyc_meta_data['ckyc_meta_data'], ['customer_details' => $customer_details]);
            
                        $ckyc_meta_data['ckyc_meta_data'] = array_merge($ckyc_meta_data['ckyc_meta_data'], ['request' => $request->All()]);
                        
                        $update_data = array_merge($update_data, $ckyc_meta_data, $updated_proposal);
            
                        $update_data['additional_details'] = json_encode($additional_details);
                    }

                    ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->update($update_data);

                break;

                case 'cholla_mandalam':
                    if (isset($request->status) && $request->status == 'auto_approved') {
                        $ckyc_meta_data = null;

                        if ( ! empty($proposal->ckyc_meta_data)) {
                            $ckyc_meta_data = json_decode($proposal->ckyc_meta_data, true);
                        }

                        $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', [
                            'company_alias' => 'cholla_mandalam',
                            'type' => 'redirection',
                            'mode' => 'transaction_id',
                            'section' => 'motor',
                            'trace_id' => $request->trace_id,
                            'meta_data' => [
                                'ckyc_reference_id' => $proposal->ckyc_reference_id,
                                'transaction_id' => isset($ckyc_meta_data['transaction_id']) ? $ckyc_meta_data['transaction_id'] : null
                            ]
                        ], [], [
                            'Content-Type' => 'application/json'
                        ], [], true, false, $this->remove_proxy, true);

                        if ($response['status'] == 200) {
                            if ($response['response']['data']['verification_status']) {
                                $updated_proposal = self::saveCkycResponseInProposal($request, $response, $proposal);
                                ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                                    ->update($updated_proposal);
                            }
                        }
                    }
                    break;

                    case 'new_india':
                        if (isset($request->status) && $request->status == 'auto_approved')
                        {
                            $proposal->refresh();
                            $required_reqest['enquiryId'] = $request->trace_id;
                            $policyHoldercode = json_decode($proposal->ckyc_meta_data,true)['policy_holder_code'];
                            newIndiaSubmitProposal::ckycVerifications($proposal, $required_reqest, $policyHoldercode, $required_reqest['enquiryId']);
                        }
                        break;
                    case 'nic':
                        self::nicCKYCVerification($proposal, $request);
                        break;

                default:
                    # code...
                    break;
            }

            return redirect($journey_stage->proposal_url);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Data does not exists'
            ]);
        }
    }

    public function ckycResponseAPi(Request $request)
    {
        $response = $request->all();

        switch ($request->ic_alias) {
            case 'edelweiss':
                if (empty($response)) {
                    return response()->json([
                        'Acknowledgment' => 0,
                        'RejectionMsg' => 'Request is null'
                    ]);
                }

                if ($response['KYC_Status'] == 0) {
                    return response()->json([
                        'Acknowledgment' => 1,
                        'RejectionMsg' => ''
                    ]);
                }

                $proposal = ModelsUserProposal::where('ckyc_reference_id', $response['VISoF_KYC_Req_No'])->first();

                if ($proposal) {
                    $user_product_journey_id = $proposal->user_product_journey_id;

                    ckycRequestResponse::updateOrCreate([
                        'user_product_journey_id' => $user_product_journey_id
                    ], [
                        'user_proposal_id' => $proposal->user_proposal_id,
                        'company_alias' => 'edelweiss',
                        'kyc_search_data' => $response['VISoF_KYC_Req_No'],
                        'kyc_response' => json_encode($response, JSON_UNESCAPED_SLASHES),
                        'kyc_status' => in_array($response['KYC_Status'], [1, 0]) ? $response['KYC_Status'] : 0
                    ]);


                    // $user_proposal['ckyc_number'] = $response['ProposerCKYCNo'] ?? $proposal->ckyc_number;
                    $user_proposal['is_ckyc_verified'] = (int) $response['KYC_Status'] == 1 ? 'Y' : 'N';
                    $user_proposal['ckyc_meta_data'] = [
                        'VISoF_KYC_Req_No' => $response['VISoF_KYC_Req_No'],
                        'IC_KYC_No' => $response['IC_KYC_No']
                    ];

                    ModelsUserProposal::where(['user_product_journey_id' => $user_product_journey_id])
                        ->update($user_proposal);

                    $user_proposal['first_name'] = $response['FirstName'] . (isset($response['MiddleName']) && ! empty($response['MiddleName']) ? ' ' . $response['MiddleName'] : '');
                    $user_proposal['last_name'] = $response['LastName'];
                    $user_proposal['email'] = $response['Email'] ?? $proposal->email;
                    $user_proposal['mobile_number'] = $response['MobileNo'];

                    $user_proposal['pan_number'] = $response['ProposerPAN'] ?? $proposal->pan_number;

                    $additional_details = json_decode($proposal->additional_details, true);

                    $checkDate = isset($response['DOB']) && !empty($response['DOB']) ? $response['DOB'] : (isset($response['DOI']) && !empty($response['DOI']) ? $response['DOI'] : '');

                    if (!empty($checkDate) && hasFullDate($checkDate, "m/d/Y")) {
                        $user_proposal['dob'] = \DateTime::createFromFormat('m/d/Y', ($checkDate))->format('d-m-Y');
                        $additional_details['owner']['dob'] = $user_proposal['dob'];
                    }

                    $additional_details['owner']['firstName'] = $user_proposal['first_name'];
                    $additional_details['owner']['lastName'] = $user_proposal['last_name'];
                    $additional_details['owner']['fullName'] = $user_proposal['first_name'] . ' ' . $user_proposal['last_name'];
                    $additional_details['owner']['mobileNumber'] = $user_proposal['mobile_number'];
                    $additional_details['owner']['email'] = $user_proposal['email'];
                    $additional_details['owner']['panNumber'] = $user_proposal['pan_number'];
                    // $additional_details['owner']['ckycNumber'] = $proposal->ckyc_number;

                    $user_proposal['additional_details'] = json_encode($additional_details);

                    

                    ModelsUserProposal::where(['user_product_journey_id' => $user_product_journey_id])
                        ->update($user_proposal);

                    return response()->json([
                        'Acknowledgment' => 1,
                        'RejectionMsg' => ''
                    ]);
                }

                return response()->json([
                    'Acknowledgment' => 0,
                    'RejectionMsg' => 'Something went wrong'
                ]);
                break;
            
                case 'united_india':
                    if (empty($response)) {
                        return response()->json([
                            'Acknowledgment' => 0,
                            'RejectionMsg' => 'Request is null'
                        ]);
                    }
                    
                    $proposal = ModelsUserProposal::where('ckyc_reference_id', $response['transactionId'])->first();
                    
                    if ($proposal) {
                        $user_product_journey_id = $proposal->user_product_journey_id;
                        
                        ckycRequestResponse::updateOrCreate([
                            'user_product_journey_id' => $user_product_journey_id
                        ], [
                            'user_proposal_id' => $proposal->user_proposal_id,
                            'company_alias' => 'united_india',
                            'kyc_search_data' => $response['transactionId'],
                            'kyc_response' => json_encode($response),
                            'kyc_status' => in_array($response['applicationStatus'],['auto_approved','manually_approved']) ? '1' : '0'
                        ]);
                       
                        $user_proposal['is_ckyc_verified'] = (int) in_array($response['applicationStatus'],['auto_approved','manually_approved']) ? 'Y' : 'N';
                        
                        ModelsUserProposal::where(['user_product_journey_id' => $user_product_journey_id])
                            ->update($user_proposal);
    
                        $ckyResponse =  self::ckycVerifications(new Request([
                            'companyAlias' => 'united_india',
                            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                            // 'enquiryId' => customEncrypt('35136'),
                            'mode' => 'ckyc'
                        ]));
    
                        $ckyc_result =  $ckyResponse->getOriginalContent();
                        
                        if (isset($ckyc_result['data']['verification_status']) && $ckyc_result['data']['verification_status']) 
                        {
                            $name = explode(' ', $response['data']['customer_details']['name']);
    
                            $first_name = $name[0];
                            $last_name = null;
    
                            if (count($name) > 1) {
                                if (count($name) > 2) {
                                    unset($name[0]);
    
                                    $last_name = implode(' ', $name);
                                } else {
                                    $last_name = $name[1];
                                }
                            }
    
                            $ckyc_response['data']['customer_details'] = [
                                'name' => $name ?? null,
                                'mobile' => null,
                                'dob' => $response['data']['customer_details']['dob'] ?? null,
                                'address' => $response['data']['customer_details']['address'] ?? null, // $ckyc_response['data']['customer_details']['address'],
                                'pincode' => $response['data']['customer_details']['pincode'] ?? null, // $ckyc_response['data']['customer_details']['pincode'],
                                'email' => null,
                                'pan_no' => null,
                                'ckyc' => $response['data']['ckyc_id'] ?? null
                            ];
    
                            $enquiry_id = customDecrypt($request->trace_id);
    
                            $updated_proposal_data = self::saveCkycResponseInProposal(
                                new Request([
                                    'company_alias' => 'united_india',
                                    'trace_id' => $request['enquiryId']
                                ]), $ckyc_response, $proposal);
                            
                                ModelsUserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->update($updated_proposal_data);
    
                            /* return response()->json([
                                'Acknowledgment' => 1,
                                'RejectionMsg' => ''
                            ]); */
                        }
                        
                        /* return response()->json([
                            'Acknowledgment' => 1,
                            'RejectionMsg' => ''
                        ]); */
                    }
    
                    return response()->json([
                        'Acknowledgment' => 0,
                        'RejectionMsg' => 'Something went wrong'
                    ]);
                    break;

            default:
                break;
        }
    }

    public static function ckycUploadDocuments(Request $request)
    {
        ini_set('max_execution_time', 600);

        $validator = Validator::make($request->all(), [
            'companyAlias' => 'required',
            'mode' => 'required',
            'enquiryId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if (in_array($request->companyAlias, ['icici_lombard', 'magma', 'iffco_tokio', 'nic'])) {
            $documents = ['poi_panCard', 'poi_aadharCard', 'poa_aadharCard', 'poi_gst_certificate', 'poa_gst_certificate', 'poa_voterCard', 'poa_voter_card', 'poi_voter_card', 'poa_drivingLicense', 'poa_driving_license', 'poi_driving_license', 'poi_eiaNumber', 'poa_eiaNumber', 'poi_passport_image', 'poa_passport_image',//];
                          'poi_aadharCard_back', 'poi_aadharCard_front','poi_voterCard_back', 'poi_voter_card_back','poi_voter_card_front','poi_drivingLicense_back','poi_driving_license_back','poi_driving_license_front','poi_passport_image_front','poi_passport_image_back' ];
            $max_file_size = [
                'icici_lombard' => 10240,
                'magma' => 2048,
                'iffco_tokio' => 512,
                'nic' =>102400
            ];
            $doc_type = [
                'poi_panCard' => 'poi',
                'poi_aadharCard' => 'poi',
                'poi_gst_certificate' => 'poi',
                'poi_voter_card' => 'poi',
                'poi_driving_license' => 'poi',
                'poi_eiaNumber' => 'poi',
                'poi_passport_image' => 'poi',
                'poa_aadharCard' => 'poa',
                'poa_gst_certificate' => 'poa',
                'poa_voterCard' => 'poa',
                'poa_voter_card' => 'poa',
                'poa_drivingLicense' => 'poa',
                'poa_driving_license' => 'poa',
                'poa_eiaNumber' => 'poa',
                'poa_passport_image' => 'poa',
                'poi_aadharCard_back'=> 'back', 
                'poi_aadharCard_front'=> 'front',
                'poi_voterCard_back'=> 'back', 
                'poi_voter_card_back'=> 'back',
                'poi_voter_card_front'=> 'front',
                'poi_drivingLicense_back'=> 'back',
                'poi_driving_license_back'=> 'back',
                'poi_driving_license_front'=> 'front',
                'poi_passport_image_front'=> 'front',
                'poi_passport_image_back'=> 'back'
            ];

            for ($i=0; $i < count($documents); $i++) { 
                if ($request->hasFile($documents[$i])) {
                    $file_validation_rules[$documents[$i]] = 'max:' . $max_file_size[$request->companyAlias];
                    // $file = $request->file('form60');
                    $file = $request->file($documents[$i]);
                    $ext = $file->getClientOriginalExtension();
                    $filename = ($request->userProductJourneyId ?? ($request->enquiryId ?? 'id-'.time())).'.'.$ext;

                    $file_path = 'ckyc_photos/' . ($request->userProductJourneyId ?? ($request->enquiryId ?? 'id-'.time())). '/' . $doc_type[$documents[$i]];

                    if (in_array($request->companyAlias, ['shriram', 'sbi'])) {
                        if (\Illuminate\Support\Facades\Storage::exists($file_path.'/'.$filename)) {
                            \Illuminate\Support\Facades\Storage::delete($file_path.'/'.$filename);
                        }
                    }

                    ProposalController::storeCkycDocument(
                        $file,
                        $file_path,
                        $filename
                    );
                    // $file->storeAs($file_path, $filename);
                }
            }

            if (isset($file_validation_rules) && ! empty($file_validation_rules)) {
                $file_validation = Validator::make($request->all(), $file_validation_rules);

                if ($file_validation->fails()) {
                    return response()->json([
                        "status" => false,
                        "message" => $file_validation->errors()
                    ]);
                }
            }
        }

        $enquiry_id = customDecrypt($request->enquiryId);
        $ckyc_verification_type = CkycVerificationTypes::where('company_alias', $request->companyAlias)->pluck('mode')->first();
        $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();
        $document_upload_data = $proposal->ckyc_upload_documents;
        $get_doc_data = json_decode($document_upload_data->cky_doc_data, true);
        $corporate_vehicles_quotes_request = $proposal->corporate_vehicles_quotes_request ?? '';
        $quote_log = $proposal->quote_log->premium_json;
        $ocr_premium = $proposal->quote_log->final_premium_amount ?? null;
        $aadharCard = $pancard = $passportNumber = $gst_number = $voterCard = $cinDocument = $drivingLicense = $gst_doc = $nregaJobCard = $nationalPopulationRegisterLetter = $certificateOfIncorporation = $registrationCertificate = '';
        #condition for bajaj allianze
        $poi_type = '';
        $poa_type = '';
        $poi_document_file = '';
        $poa_document_file = '';
        $photo_document_file = '';

        if (in_array($request->companyAlias, ['bajaj_allianz', 'tata_aig'])) {
            $doc_list = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . customEncrypt($enquiry_id));

            if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . customEncrypt($enquiry_id))) {
                if (!isset($doc_list[0]) && empty($doc_list[0])) {
                    return response()->json([
                        'status' => false,
                        'premium' => '0',
                        'message' => 'Please upload document to complete proposal.'
                    ]);
                } else {
                    // $file = \Illuminate\Support\Facades\Storage::get($doc_list[0]);
                    $file = ProposalController::getCkycDocument($doc_list[0]);
                    switch ($document_upload_data->doc_name) {
                        case 'pan_card':
                            $pancard = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode($file);
                            break;
                        case 'aadhar_card':
                            $aadharCard = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                            break;
                        case 'gst_doc':
                            $gst_number = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                            break;

                        case 'passport':
                            $passportNumber = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                            break;
                        case 'voter_card':
                            $voterCard = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                            break;
                        case 'driving_license':
                            $drivingLicense = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                            break;
                        case 'cin_number':
                            $cinDocument = 'data:@file/' . $document_upload_data->doc_type . ';base64,' . base64_encode(($file));
                            break;

                        default :
                            break;
                    }
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Please upload Documents For CKYC Verifications.'
                ]);
            }
        } elseif (in_array($request->companyAlias, ['shriram', 'sbi'])) {
            if (Storage::exists('ckyc_photos/' . $request->enquiryId)) {
                $document_list = Storage::allFiles('ckyc_photos/' . $request->enquiryId);

                $documents_data = json_decode($document_upload_data['cky_doc_data'], true);

                if ( ! empty($document_list)) {
                    for ($i = 0; $i < count($document_list); $i++) {
                        $path_array = explode('/', $document_list[$i]);

                        # for retrieving documents .ext manually
                        $extension = pathinfo($document_list[$i], PATHINFO_EXTENSION);

                        $file_extension = match ($extension) { 
                            'jpg' => 'image/jpg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'pdf' => 'application/pdf',
                        };
                        
                        # $file_extension = Storage::mimeType($document_list[$i]);

                        if ($path_array[2] == 'poi') {
                            $poi_document_file = base64_encode(ProposalController::getCkycDocument($document_list[$i]));

                            // dd($poi_document_file, $documents_data['proof_of_identity']['poi_identity']);

                            switch ($documents_data['proof_of_identity']['poi_identity']) {
                                case 'panNumber':
                                    $poi_type = 'pan_card';
                                    $pancard = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'aadharNumber':
                                    $poi_type = 'aadhar_card';
                                    $aadharCard = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'drivingLicense':
                                    $poi_type = 'driving_licence';
                                    $drivingLicense = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'voterId':
                                    $poi_type = 'voter_id';
                                    $voterCard = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'passportNumber':
                                    $poi_type = 'passport';
                                    $passportNumber = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'nationalPopulationRegisterLetter':
                                    $poi_type = 'national_population_register_letter';
                                    $nationalPopulationRegisterLetter = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'registrationCertificate':
                                    $poi_type = 'registration_certificate';
                                    $registrationCertificate = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'certificateOfIncorporation':
                                case 'cretificateOfIncorporaion':
                                    $poi_type = 'certificate_of_incorporation';
                                    $certificateOfIncorporation = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                case 'cinNumber':
                                    $poi_type = 'cin_number';
                                    $cinDocument = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poi_document_file : $poi_document_file;
                                    break;

                                default:
                                    return response()->json([
                                        'status' => false,
                                        'message' => 'POI provided is not available'
                                    ]);
                                    break;
                            }
                        } elseif ($path_array[2] == 'poa') {
                            // $poa_document_file = base64_encode(Storage::get($document_list[$i]));
                            $poa_document_file = base64_encode(ProposalController::getCkycDocument($document_list[$i]));

                            switch ($documents_data['proof_of_address']['poa_identity']) {
                                case 'aadharNumber':
                                    $poa_type = 'aadhar_card';
                                    $aadharCard = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'drivingLicense':
                                    $poa_type = 'driving_licence';
                                    $drivingLicense = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'voterId':
                                    $poa_type = 'voter_id';
                                    $voterCard = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'passportNumber':
                                    $poa_type = 'passport';
                                    $passportNumber = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'nationalPopulationRegisterLetter':
                                    $poa_type = 'national_population_register_letter';
                                    $nationalPopulationRegisterLetter = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'registrationCertificate':
                                    $poa_type = 'registration_certificate';
                                    $registrationCertificate = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'certificateOfIncorporation':
                                case 'cretificateOfIncorporaion':
                                    $poa_type = 'certificate_of_incorporation';
                                    $certificateOfIncorporation = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'gstNumber':
                                    $poa_type = 'gst_number';
                                    $gst_number = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                case 'nregaJobCard':
                                    $poa_type = 'NREGA';
                                    $nregaJobCard = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $poa_document_file : $poa_document_file;
                                    break;

                                default:
                                    return response()->json([
                                        'status' => false,
                                        'message' => 'POA provided is not available'
                                    ]);
                                    break;
                            }
                        } elseif ($path_array[2] == 'photos' && ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I')) {
                            // $photo_document = base64_encode(Storage::get($document_list[$i]));
                            $photo_document = base64_encode(ProposalController::getCkycDocument($document_list[$i]));
                            $photo_document_file = $request->companyAlias == 'sbi' ? 'data:' . $file_extension . ';base64,' . $photo_document : $photo_document;
                        }
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Document list not found'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Documents not found'
                ]);
            }
        } else {
            $documentType=[];

            if (!empty($request->file())) {
                if (!empty($request->hasFile('poi_panCard'))) {
                    $documentType['poi']='pan_card';
                    $file = $request->file('poi_panCard');
                    $ext = $file->getClientOriginalExtension();
                    //Storage::disk('local')->put('example.' . $ext, file_get_contents($request->file('poi_panCard')));
                    $pancard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_panCard')));
                    $poi_type = 'pan_card';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poa_panCard'))) {
                    $documentType['poa']='pan_card';
                    $file = $request->file('poa_panCard');
                    $ext = $file->getClientOriginalExtension();
                    //Storage::disk('local')->put('example.' . $ext, file_get_contents($request->file('poa_panCard')));
                    $pancard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_panCard')));

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poi_aadharCard'))) {
                    $documentType['poi']='aadhaar_card';
                    $file = $request->file('poi_aadharCard');
                    $ext = $file->getClientOriginalExtension();
                    $aadharCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_aadharCard')));
                    $poi_type = 'aadhar_card';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);                 
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poa_aadharCard'))) {
                    $documentType['poa']='aadhaar_card';
                    $file = $request->file('poa_aadharCard');
                    $ext = $file->getClientOriginalExtension();
                    $aadharCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_aadharCard')));
                    $poa_type = 'aadhar_card';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->file('poi_gst_certificate'))) {
                    $documentType['poi']='gst_number';
                    $file = $request->file('poi_gst_certificate');
                    $ext = $file->getClientOriginalExtension();
                    //Storage::disk('local')->put('example.' . $ext, file_get_contents($request->file('poi_gst_certificate')));
                    $gst_number = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_gst_certificate')));
                    $poi_type = 'gst_certificate';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->file('poa_gst_certificate'))) {
                    $documentType['poa']='gst_number';
                    $file = $request->file('poa_gst_certificate');
                    $ext = $file->getClientOriginalExtension();
                    //Storage::disk('local')->put('example.' . $ext, file_get_contents($request->file('poa_gst_certificate')));
                    $gst_number = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_gst_certificate')));
                    $poa_type = 'gst_certificate';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->file('poa_voterCard'))) {
                    $documentType['poa']='voter_id';
                    $file = $request->file('poa_voterCard');
                    $ext = $file->getClientOriginalExtension();

                    $voterCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_voterCard')));
                    $poa_type = 'voter_id';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->file('poa_voter_card'))) {
                    $documentType['poa']='voter_id';
                    $file = $request->file('poa_voter_card');
                    $ext = $file->getClientOriginalExtension();

                    $voterCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_voter_card')));
                    $poa_type = 'voter_id';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }



                if (!empty($request->file('poi_voter_card'))) {
                    $documentType['poi']='voter_id';
                    $file = $request->file('poi_voter_card');
                    $ext = $file->getClientOriginalExtension();

                    $voterCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_voter_card')));
                    $poi_type = 'voter_id';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                
                //poa voter id
                if (!empty($request->file('poa_voter_id'))) {
                    $documentType['poa']='voter_id';
                    $file = $request->file('poa_voter_id');
                    $ext = $file->getClientOriginalExtension();

                    $voterCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_voter_id')));
                    $poi_type = 'voter_id';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->file('poa_drivingLicense'))) {
                    $documentType['poa']='driving';
                    $file = $request->file('poa_drivingLicense');
                    $ext = $file->getClientOriginalExtension();

                    $drivingLicense = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_drivingLicense')));
                    $poa_type = 'driving_licence';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->file('poa_driving_license'))) {
                    $documentType['poa']='driving';
                    $file = $request->file('poa_driving_license');
                    $ext = $file->getClientOriginalExtension();

                    $drivingLicense = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_driving_license')));
                    $poa_type = 'driving_licence';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->file('poi_driving_license'))) {
                    $documentType['poi']='driving';
                    $file = $request->file('poi_driving_license');
                    $ext = $file->getClientOriginalExtension();

                    $drivingLicense = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_driving_license')));
                    $poi_type = 'driving_licence';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
                if (!empty($request->hasFile('poi_eiaNumber'))) {
                    $documentType['poi']='eia_number';
                    $file = $request->file('poi_eiaNumber');
                    $ext = $file->getClientOriginalExtension();
                    $eiaNumber = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_eiaNumber')));
                    $poi_type = 'eia_number';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poa_eiaNumber'))) {
                    $documentType['poa']='eia_number';
                    $file = $request->file('poa_eiaNumber');
                    $ext = $file->getClientOriginalExtension();
                    $eiaNumber = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_eiaNumber')));
                    $poa_type = 'eia_number';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poi_passport_image'))) {
                    $documentType['poi']='passport';
                    $file = $request->file('poi_passport_image');
                    $ext = $file->getClientOriginalExtension();
                    $passportNumber = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_passport_image')));
                    $poi_type = 'passport';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poa_passport_image'))) {
                    $documentType['poa']='passport';
                    $file = $request->file('poa_passport_image');
                    $ext = $file->getClientOriginalExtension();
                    $passportNumber = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_passport_image')));
                    $poa_type = 'passport';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('poi_form_60_image'))) {
                    $documentType['poi'] = 'form_60';
                    $file = $request->file('poi_form_60_image');
                    $ext = $file->getClientOriginalExtension();
                    $form60 = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_form_60_image')));
                    $poi_type = 'form_60';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('poi_nrega_job_card_image'))) {
                    $documentType['poi'] = 'nrega_job_card';
                    $file = $request->file('poi_nrega_job_card_image');
                    $ext = $file->getClientOriginalExtension();
                    $nregaJobCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_nrega_job_card_image')));
                    $poi_type = 'nrega_job_card';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('poa_nrega_job_card_image'))) {
                    $documentType['poa'] = 'nrega_job_card';
                    $file = $request->file('poa_nrega_job_card_image');
                    $ext = $file->getClientOriginalExtension();
                    $nregaJobCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_nrega_job_card_image')));
                    $poa_type = 'nrega_job_card';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('poi_national_population_register_letter_image'))) {
                    $documentType['poi'] = 'national_population_register_letter';
                    $file = $request->file('poi_national_population_register_letter_image');
                    $ext = $file->getClientOriginalExtension();
                    $nationalPopulationRegisterLetter = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_national_population_register_letter_image')));
                    $poi_type = 'national_population_register_letter';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('poa_national_population_register_letter_image'))) {
                    $documentType['poa'] = 'national_population_register_letter';
                    $file = $request->file('poa_national_population_register_letter_image');
                    $ext = $file->getClientOriginalExtension();
                    $nationalPopulationRegisterLetter = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_national_population_register_letter_image')));
                    $poa_type = 'national_population_register_letter';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('poa_registration_certificate_image'))) {
                    $documentType['poa'] = 'registration_certificate';
                    $file = $request->file('poa_registration_certificate_image');
                    $ext = $file->getClientOriginalExtension();
                    $registrationCertificate = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_registration_certificate_image')));
                    $poa_type = 'registration_certificate';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('poa_certificate_of_incorporation_image'))) {
                    $documentType['poa'] = 'certificate_of_incorporation';
                    $file = $request->file('poa_certificate_of_incorporation_image');
                    $ext = $file->getClientOriginalExtension();
                    $certificateOfIncorporation = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poa_certificate_of_incorporation_image')));
                    $poa_type = 'certificate_of_incorporation';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if(!empty($request->hasFile('poi_cinNumber'))) {
                    $documentType['poa'] = 'cin_number';
                    $file = $request->poi_cinNumber;
                    $ext = $file->getClientOriginalExtension();
                    $cinDocument = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->poi_cinNumber));
                    $poa_type = 'cinNumber';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if ( ! empty($request->hasFile('photo'))) {
                    $file = $request->file('photo');
                    $ext = $file->getClientOriginalExtension();
                    $photo_document_file = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('photo')));
                }
                //-----x-------for front and back page document upload----------x------ //
                if (!empty($request->hasFile('poi_aadharCard_front'))) {
                    $documentType['poi']='aadhaar_card';
                    $file = $request->file('poi_aadharCard_front');
                    $ext = $file->getClientOriginalExtension();
                    //$aadharCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_aadharCard')));
                    $aadharCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
                    $poi_type = 'aadhar_card';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);                 
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poi_aadharCard_back'))) {
                    $documentType['poi_back']='aadhaar_card';
                    $file = $request->file('poi_aadharCard_back');
                    $ext = $file->getClientOriginalExtension();

                    $aadharCard_back = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
                    $poi_type = 'aadhar_card';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);                 
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->file('poi_driving_license_front'))) {
                    $documentType['poi']='driving';
                    $file = $request->file('poi_driving_license_front');
                    $ext = $file->getClientOriginalExtension();

                    $drivingLicense = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_driving_license_front')));
                    $poi_type = 'driving_licence';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->file('poi_driving_license_back'))) {
                    $documentType['poi_back']='driving';
                    $file = $request->file('poi_driving_license_back');
                    $ext = $file->getClientOriginalExtension();

                    $drivingLicense_back = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_driving_license_back')));
                    $poi_type = 'driving_licence';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poi_passport_image_front'))) {
                    $documentType['poi']='passport';
                    $file = $request->file('poi_passport_image_front');
                    $ext = $file->getClientOriginalExtension();
                    $passportNumber = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_passport_image_front')));
                    $poi_type = 'passport';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->hasFile('poi_passport_image_back'))) {
                    $documentType['poi_back']='passport';
                    $file = $request->file('poi_passport_image_back');
                    $ext = $file->getClientOriginalExtension();
                    $passportNumber_back = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_passport_image_back')));
                    $poi_type = 'passport';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->file('poi_voter_id_front'))) {
                    $documentType['poi']='voter_id';
                    $file = $request->file('poi_voter_id_front');
                    $ext = $file->getClientOriginalExtension();

                    $voterCard = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_voter_id_front')));
                    $poi_type = 'voter_id';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }

                if (!empty($request->file('poi_voter_id_back'))) {
                    $documentType['poi_back']='voter_id';
                    $file = $request->file('poi_voter_id_back');
                    $ext = $file->getClientOriginalExtension();

                    $voterCard_back = 'data:@file/' . $ext . ';base64,' . base64_encode(file_get_contents($request->file('poi_voter_id_back')));
                    $poi_type = 'voter_id';

                    $checkAllowedFileExtentions = self::checkAllowedFileExtentions($ext, $request->companyAlias);
                    if(!$checkAllowedFileExtentions['status']) {
                        return response()->json($checkAllowedFileExtentions);
                    }
                }
            }
        }

        $pan_no = $adhar_no = $voter_id = $gst_no = $poa_driving_licence = $poa_passport = $cinDocumentNo = null;
        if (!empty($get_doc_data['proof_of_identity']['poi_identity'])) {
            $pan_no = $get_doc_data['proof_of_identity']['poi_panNumber'];
            $adhar_no = $get_doc_data['proof_of_identity']['poi_aadharNumber'];
            $voter_id = $get_doc_data['proof_of_identity']['poi_voterId'] ?? '';
            $gst_no = $get_doc_data['proof_of_identity']['poi_gstNumber'] ?? '';
            $poa_driving_licence = $get_doc_data['proof_of_identity']['poi_driving_license'] ?? '';
            $nrega_job_card = $get_doc_data['proof_of_identity']['poi_nrega_job_card'] ?? '';
            $cinDocumentNo = $get_doc_data['proof_of_identity']['poi_cinNumber'] ?? '';
        }
        if (!empty($get_doc_data['proof_of_address']['poa_identity'])) {

            $pan_no = !empty($pan_no) ? $pan_no : $get_doc_data['proof_of_address']['poa_panNumber'];
            $adhar_no = !empty($adhar_no) ? $adhar_no : $get_doc_data['proof_of_address']['poa_aadharNumber'];
            $voter_id = !empty($voter_id) ? $voter_id : $get_doc_data['proof_of_address']['poa_voterId'] ?? '';
            $gst_no = !empty($gst_no) ? $gst_no : $get_doc_data['proof_of_address']['poa_gstNumber'] ?? '';
            $poa_driving_licence = $get_doc_data['proof_of_address']['poa_drivingLicense'] ?? '';
            $poa_passport = $get_doc_data['proof_of_address']['poa_passportNumber'] ?? '';
            $nrega_job_card = $get_doc_data['proof_of_address']['poa_nrega_job_card'] ?? '';
            $national_population_register_letter = $get_doc_data['proof_of_address']['poa_nationalPopulationRegisterLetter'] ?? '';
            $registration_certificate = $get_doc_data['proof_of_address']['poa_registrationCertificate'] ?? '';
            $certificate_of_incorporation = $get_doc_data['proof_of_address']['poa_certificateOfIncorporation'] ?? '';
        }

        $request_data = [
            'company_alias' => $request->companyAlias,
            'type' => $ckyc_verification_type,
            'mode' => 'documents',
            'section' => 'motor',
            'trace_id' => customEncrypt($enquiry_id),
            'ckyc_number' => $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : null,
            'pan_no' => $pan_no,
            'aadhar' => $adhar_no,
            'voter_id' => $voter_id, // these filed should we passed in metedata
            'gst_no' => $gst_no,
            'tenant_id' => config('constants.CKYC_TENANT_ID'),
            'passport_no' => $poa_passport,
            'driving_license' => $poa_driving_licence,
            'nrega_job_card' => $nrega_job_card ?? '',
            'national_population_register_letter' => $national_population_register_letter ?? '',
            'registration_certificate' => $registration_certificate ?? '',
            'certificate_of_incorporation' => $certificate_of_incorporation ?? '',
            'date_of_birth' => $proposal->dob,
            'documents' => [
                [
                    'type' => 'pan_card',
                    'data' => $pancard
                ],
                [
                    'type' => 'aadhar_card',
                    'data' => $aadharCard
                ],
                [
                    'type' => 'passport',
                    'data' => $passportNumber
                ],
                [
                    'type' => 'gst_number',
                    'data' => $gst_number
                ],
                [
                    'type' => 'voter_id',
                    'data' => $voterCard
                ],
                [
                    'type' => 'driving_licence',
                    'data' => $drivingLicense
                ],
                [
                    'type' => 'nrega_job_card',
                    'data' => $nregaJobCard
                ],
                [
                    'type' => 'national_population_register_letter',
                    'data' => $nationalPopulationRegisterLetter
                ],
                [
                    'type' => 'certificate_of_incorporation',
                    'data' => $certificateOfIncorporation
                ],
                [
                    'type' => 'registration_certificate',
                    'data' => $registrationCertificate
                ],
                [
                    'type' => 'photo',
                    'data' => $photo_document_file
                ],
                [
                    'type' => 'cin_number',
                    'data' => $cinDocument ?? ''
                ]
            ]
        ];

        // dd($request_data['documents']);

        $customer_data = [];

        switch ($request->companyAlias) {
            case 'bajaj_allianz':
                $customer_data = [
                    'aadhar' => substr($adhar_no, -4),
                    'meta_data' => [
                        "transaction_id" => $proposal->proposal_no,
                        "user_id" => $request->user_id,
                        "full_name" => implode(' ', [$proposal->first_name, $proposal->last_name]),
                        "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type == "I" ? "I" : "O",
                        "premium" => $ocr_premium,
                        "trigger_old_document_flow" => (!empty($request->trigger_old_document_flow) && $request->trigger_old_document_flow == 'Y') ? 'Y' : 'N'
                    ]
                ];
                break;

            case 'icici_lombard':
                $validator = \Illuminate\Support\Facades\Validator::make(['email' => $proposal->email], [
                    'email' => 'required|email:rfc,dns',
                ]);
            
                if ($validator->fails()) {
                    return response()->json([
                        "status" => false,
                        "message" => $validator->errors(),
                    ]);
                }
                $customer_data = [
                    "email_id" => $proposal->email,
                    "mobile_no" =>  '91' . $proposal->mobile_number,
                    "date_of_birth" => $proposal->dob,
                    "name" => trim($proposal->first_name . ' ' . $proposal->last_name),
                    "gender" => $proposal->gender == 'MALE' ? "M" : "F",
                    'documents' => [],
                    'meta_data' => [
                        'customer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        'correlation_id' => getUUID($enquiry_id),
                        'accepted_for_manual_qc' => (config('constants.IcConstants.icici_lombard.PROCEED_JOURNEY_WHEN_ACCEPTED_FOR_MANUAL_QC') == 'Y')
                    ]
                ];

                if(app()->environment() == 'local') {
                    $customer_data['meta_data']['correlation_id'] = ($proposal->unique_proposal_id ?? ($correlationID = getUUID($enquiry_id)));

                    if(empty($proposal->unique_proposal_id)){
                        ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'unique_proposal_id' => $correlationID,
                            ]);
                    }
                }


                switch($documentType['poa'])
                {
                    case 'aadhaar_card':
                        array_push($customer_data['documents'],[
                            'type'=>'aadhaar_card',
                            'document_category'=>'poa_document',
                            'data'=>$aadharCard
                        ]);
                        break;

                    case 'passport':
                        array_push($customer_data['documents'],[
                            'type'=>'passport',
                            'document_category'=>'poa_document',
                            'data'=>$passportNumber
                        ]);
                        break;

                    case 'voter_id':
                        array_push($customer_data['documents'],[
                            'type'=>'voter_id',
                            'document_category'=>'poa_document',
                            'data'=>$voterCard
                        ]);
                        break;
                    case 'driving':
                            array_push($customer_data['documents'],[
                                'type'=>'driving',
                                'document_category'=>'poa_document',
                                'data'=>$drivingLicense
                            ]);
                        break;
                    case 'gst_number':
                        array_push($customer_data['documents'],[
                            'type'=>'gst_number',
                            'document_category'=>'poa_document',
                            'data'=>$gst_number
                        ]);
                        break;
                        
                }

                switch($documentType['poi'])
                {
                    case 'pan_card':
                        array_push($customer_data['documents'],[
                            'type'=>'pan_card',
                            'document_category'=>'poi_document',
                            'data'=>$pancard
                        ]);
                        break;

                    case 'aadhaar_card':
                        array_push($customer_data['documents'],[
                            'type'=>'aadhaar_card',
                            'document_category'=>'poi_document',
                            'data'=>$aadharCard
                        ]);
                        break;

                    case 'passport':
                        array_push($customer_data['documents'],[
                            'type'=>'passport',
                            'document_category'=>'poi_document',
                            'data'=>$passportNumber
                        ]);
                        break;

                    case 'voter_id':
                        array_push($customer_data['documents'],[
                            'type'=>'voter_id',
                            'document_category'=>'poi_document',
                            'data'=>$voterCard
                        ]);
                        break;
                    case 'driving':
                        array_push($customer_data['documents'],[
                            'type'=>'driving',
                            'document_category'=>'poi_document',
                            'data'=>$drivingLicense
                        ]);
                        break;

                    case 'gst_number':
                        array_push($customer_data['documents'],[
                            'type'=>'gst_number',
                            'document_category'=>'poi_document',
                            'data'=>$gst_number
                        ]);
                        break;
                        
                }

                break;

            case 'iffco_tokio':
                if (empty($proposal->first_name) && empty($proposal->last_name)) {
                    return [
                        'verification_status' => false,
                        'ckyc_id' => null,
                        'name' =>  '',
                        'ckyc_reference_id' =>  '',
                        'message' => 'First name and Last Name mendatory',
                    ];
                }
                $salutation = ($proposal->gender == 'M') ? 'Mr.' : (($proposal->marital_status == 'Single') ? 'Miss' : 'Mrs.');

                $related_person_prefix = '';
                $relationship_type = '';

                if ($proposal->proposer_ckyc_details?->relationship_type == 'fatherName') {
                    $relationship_type = 'FATHER';
                    $related_person_prefix = 'MR';
                } elseif ($proposal->proposer_ckyc_details?->relationship_type == 'motherName') {
                    $relationship_type = 'MOTHER';
                    $related_person_prefix = 'MRS';
                } elseif ($proposal->proposer_ckyc_details?->relationship_type == 'spouseName') {
                    $relationship_type = 'SPOUSE';
                    $related_person_prefix = $proposal->gender == 'M' ? 'MRS' : 'MR';
                }

                $customer_data = [
                    'meta_data' => [
                        "prefix"                     => $salutation,
                        "first_name"                  => !empty($proposal->first_name) ? $proposal->first_name : '',
                        "middle_name"                 => "",
                        "last_name"                   => !empty($proposal->last_name) ? $proposal->last_name : '',
                        "related_person_prefix"        => $related_person_prefix,
                        "related_person_first_name"     => $proposal->proposer_ckyc_details->related_person_name ?? '',
                        "related_person_middle_name"    => "",
                        "related_person_last_name"      => "",
                        "gender"                     => !empty($proposal->gender) ? $proposal->gender : '',
                        "dateofBirth"                => !empty($proposal->dob) ? $proposal->dob :  '',
                        "address_line1"               => !empty($proposal->address_line1) ? $proposal->address_line1 : '',
                        "pincode"                    => !empty($proposal->pincode) ? $proposal->pincode : '',
                        "city"                       => !empty($proposal->city) ? $proposal->city : '',
                        "state"                      => !empty($proposal->state) ? $proposal->state : '',
                        "country"                    => "",
                        "district"                   => !empty($proposal->city) ? $proposal->city : '',
                        "correspondence_address_line1" => !empty($proposal->address_line1) ? $proposal->address_line1 : '',
                        "correspondence_pincode"      => !empty($proposal->pincode) ? $proposal->pincode : '',
                        "correspondence_city"         => !empty($proposal->city) ? $proposal->city : '',
                        "correspondence_state"        => !empty($proposal->state) ? $proposal->state : '',
                        "correspondence_country"      => "",
                        "correspondence_district"     => !empty($proposal->city) ? $proposal->city : '',
                        'voter_id' => $voter_id,
                        'gst_no' => $gst_no,
                        'driving_license' => $poa_driving_licence,
                        'customer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type,
                        "mobile" => $proposal->mobile_number,
                        'relationship_type' => $relationship_type,
                        'poi_type' => $poi_type,
                        'poa_type' => $poa_type
                    ]
                ];
                break;

            
            case 'tata_aig':
                $ckyc_meta_data = json_decode($proposal->ckyc_meta_data ?? '', true);

                $customer_data = [
                    'meta_data' => [
                        "pan_no"=> $proposal->pan_number,
                        'customer_name' => implode(' ', array_filter([$proposal->first_name, $proposal->last_name])),
                        'req_id' => $ckyc_meta_data['req_id'] ?? '',
                        'proposal_no' => $proposal->proposal_no,
                        "ic_version_type" => 'V1'
                    ]
                ];

                if (
                    in_array($proposal->user_product_journey->product_sub_type_id, [1, 9, 13, 14, 15]) &&
                    config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED') == "Y"
                ) {
                    $customer_data['meta_data']['ic_version_type'] = 'V2';
                } elseif (
                    in_array($proposal->user_product_journey->sub_product?->parent_product_sub_type_id, [8, 6]) &&
                    config('TATA_AIG_V2_PCV_FLOW') == 'Y'
                ) {
                    $customer_data['meta_data']['ic_version_type'] = 'V2';
                }
                break;

            case 'magma':
                $customer_data = [
                    'meta_data' => [
                        'proposer_type' => $corporate_vehicles_quotes_request->vehicle_owner_type
                    ]
                ];
                break;

            case 'shriram':
                $customer_data = [
                    'meta_data' => [
                        'policy_number' => $proposal->proposal_no ?? null,
                        'father_name' => $proposal->proposer_ckyc_details->related_person_name ?? '',
                        'marital_status' => $proposal->marital_status == 'Married' ? 'M' : 'U',
                        'poi_type' => $poi_type,
                        'poa_type' => $poa_type
                    ]
                ];
                break;

            case 'sbi':
                $poa_document_type = '';
                $poa_document_id = '';

                if ( ! empty($get_doc_data['proof_of_address']['poa_identity'])) {
                    if ($get_doc_data['proof_of_address']['poa_identity'] == 'aadharNumber') {
                        $poa_document_type = 'AadharCard';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_aadharNumber'];
                    } elseif ($get_doc_data['proof_of_address']['poa_identity'] == 'drivingLicense') {
                        $poa_document_type = 'DrivingLicence';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_drivingLicense'];
                    } elseif ($get_doc_data['proof_of_address']['poa_identity'] == 'voterId') {
                        $poa_document_type = 'VoterID';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_voterId'];
                    } elseif ($get_doc_data['proof_of_address']['poa_identity'] == 'passportNumber') {
                        $poa_document_type = 'Passport';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_passportNumber'];
                    } elseif ($get_doc_data['proof_of_address']['poa_identity'] == 'nregaJobCard') {
                        $poa_document_type = 'NREGA';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_nrega_job_card'];
                    } elseif ($get_doc_data['proof_of_address']['poa_identity'] == 'gstNumber') {
                        $poa_document_type = 'Others';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_gstNumber'];
                    } elseif ($get_doc_data['proof_of_address']['poa_identity'] == 'registrationCertificate') {
                        $poa_document_type = 'CompanyRegistrationNumber';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_registrationCertificate'];
                    } elseif (in_array($get_doc_data['proof_of_address']['poa_identity'], ['cretificateOfIncorporaion', 'certificateOfIncorporation', 'cinNumber'])) {
                        $poa_document_type = 'CIN';
                        $poa_document_id = $get_doc_data['proof_of_address']['poa_certificateOfIncorporation'];
                    }
                }

                if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'C' && ! empty($get_doc_data['proof_of_identity']['poi_identity'])) {
                    if ($get_doc_data['proof_of_identity']['poi_identity'] == 'panNumber') {
                        $poi_document_type = 'OVDIRP';
                        $poi_document_id = $get_doc_data['proof_of_identity']['poi_panNumber'];
                    } elseif ($get_doc_data['proof_of_identity']['poi_identity'] == 'registrationCertificate') {
                        $poi_document_type = 'CompanyRegistrationNumber';
                        $poi_document_id = $get_doc_data['proof_of_identity']['poi_registrationCertificate'];
                    } elseif (in_array($get_doc_data['proof_of_identity']['poi_identity'], ['cretificateOfIncorporaion', 'certificateOfIncorporation'])) {
                        $poi_document_type = 'CIN';
                        $poi_document_id = $get_doc_data['proof_of_identity']['poi_certificateOfIncorporation'];
                    } elseif (in_array($get_doc_data['proof_of_identity']['poi_identity'], ['cinNumber'])) {
                        $poi_document_type = 'CIN';
                        $poi_document_id = $get_doc_data['proof_of_identity']['poi_cinNumber'];
                    }
                }

                if ($proposal->user_product_journey->product_sub_type_id == 1) {
                    $sub_product = 'CAR';
                } else if($proposal->user_product_journey->product_sub_type_id == 2) {
                    $sub_product = 'BIKE'; 
                } else {
                    $sub_product = get_parent_code($proposal->user_product_journey->product_sub_type_id);
                }
                $additional_details = json_decode($proposal->additional_details, true);
                $CKYCUniqueId = $additional_details['CKYCUniqueId'] ?? NULL;
                $customer_data = [
                    'company_alias' => 'sbi_general',
                    'meta_data' => [
                        'proposal_no' => ($proposal->corporate_vehicles_quotes_request->is_renewal == 'Y' &&  $proposal->corporate_vehicles_quotes_request->rollover_renewal != 'Y') ? $proposal->proposal_no : $CKYCUniqueId,
                        'kyc_confirmation' => 'N',
                        'document_type' => 'pan_card',
                        'pan_number_with_dob' => 'failure',
                        'poi_document_pan' => $pan_no,
                        'poa_document' => $poa_document_type,
                        'poi_document' => $poi_document_type ?? '',
                        'customer_first_name' => $proposal->first_name,
                        'customer_last_name' => $proposal->last_name,
                        'relative_first_name' => $proposal->proposer_ckyc_details->related_person_name ?? '',
                        'relative_middle_name' => '',
                        'relative_last_name' => '',
                        'gender' => $proposal->gender,
                        'poa_document_unique_id' => $poa_document_id,
                        'sub_product' => $sub_product,
                        'customer_type' => $proposal->corporate_vehicles_quotes_request->vehicle_owner_type,
                        'source'  => config('constants.motorConstant.sbi.CHANNEL_SOURCE'),
                        'organization_type' => $proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'C' ? $proposal->proposer_ckyc_details->organization_type : ''
                    ]
                ];

                //Passing first name and last name as per git 34559
                if(!empty($proposal?->proposer_ckyc_details?->related_person_name)){
                    $name = getFirstAndLastName($proposal->proposer_ckyc_details->related_person_name);
                    $customer_data['meta_data']['relative_first_name'] = $name[0];
                    if(!empty($name[1])){
                        $customer_data['meta_data']['relative_last_name'] = $name[1];
                    }
                }

                if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
                    $customer_data['meta_data']['poi_document_type'] = $poi_document_type;
                    $customer_data['meta_data']['poi_document_id'] = $poi_document_id;
                }

                if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
                    $relationship_type_array = [
                        'fatherName' => 'father',
                        'motherName' => 'mother',
                        'spouseName' => 'spouse',
                    ];

                    $customer_data['meta_data']['relationship_type'] = $relationship_type_array[$proposal?->proposer_ckyc_details?->relationship_type] ?? '';
                }

                $request_data['documents'] = array_values(array_filter($request_data['documents'], function($document) {
                    return ! empty($document['data']);
                }));

                $temp = [];

                foreach($request_data['documents'] as $document) {
                    $document['document_category'] = ($document['type'] == $poa_type) ? 'poa_document' : 'poi_document';
                    $temp[] = $document;
                }

                $request_data['documents'] = $temp;

                unset($temp);

                break;
            case 'nic':
                    $validator = \Illuminate\Support\Facades\Validator::make(
                         ['email' => $proposal->email], 
                         ['email' => 'required|email:rfc,dns',]);
                
                    if ($validator->fails()) {
                        return response()->json([
                            "status"  => false,
                            "message" => $validator->errors(),
                        ]);
                    }
                    $customer_data = [
                        "email_id"      => $proposal->email,
                        "mobile_no"     => $proposal->mobile_number, //'91' . $proposal->mobile_number,
                        "date_of_birth" => $proposal->dob,
                        "name"          => trim($proposal->first_name . ' ' . $proposal->last_name),
                        "gender"        => $proposal->gender == 'MALE' ? "M" : "F",
                        'documents' => [],
                        'meta_data' => [
                            'customer_type'  => $corporate_vehicles_quotes_request->vehicle_owner_type,
                            'correlation_id' => getUUID($enquiry_id),
                            // 'accepted_for_manual_qc' => (config('constants.IcConstants.icici_lombard.PROCEED_JOURNEY_WHEN_ACCEPTED_FOR_MANUAL_QC') == 'Y')
                            ]
                        ];
                        
                    if(app()->environment() == 'local') {
                        $customer_data['meta_data']['correlation_id'] = ($proposal->unique_proposal_id ?? ($correlationID = getUUID($enquiry_id)));
    
                        if(empty($proposal->unique_proposal_id)){
                            ModelsUserProposal::where('user_product_journey_id', $enquiry_id)
                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'unique_proposal_id' => $correlationID,
                                ]);
                        }
                    }

                    switch($documentType['poi'])
                    {
                        case 'pan_card':
                            array_push($customer_data['documents'],[
                                'type'             =>'pan_card',
                                'document_category'=>'poi_document_front',
                                'data'             =>$pancard,
                            ]);
                            break;
    
                        case 'aadhaar_card':
                            array_push($customer_data['documents'],[
                                'type'             =>'aadhaar_card',
                                'document_category'=>'poi_document_front',//'poi_document',
                                'data'             =>$aadharCard,
                                'ovd_type'         =>'aadhaar'
                             ]);
                            break;
    
                        case 'passport':
                            array_push($customer_data['documents'],[
                                'type'             =>'passport',
                                'document_category'=>'poi_document_front',
                                'data'             =>$passportNumber,
                                'ovd_type'         =>'passport'
                            ]);
                            break;
    
                        case 'voter_id':
                            array_push($customer_data['documents'],[
                                'type'             =>'voter_id',
                                'document_category'=>'poi_document_front',
                                'data'             =>$voterCard,
                                'ovd_type'         =>'voterid'
                            ]);
                            break;
                        case 'driving':
                            array_push($customer_data['documents'],[
                                'type'             =>'driving',
                                'document_category'=>'poi_document_front',
                                'data'             =>$drivingLicense,
                                'ovd_type'         =>'drivingLicence'
                            ]);
                            break;
    
                        case 'gst_number':
                            array_push($customer_data['documents'],[
                                'type'             =>'gst_number',
                                'document_category'=>'poi_document_front',
                                'data'             =>$gst_number
                            ]);
                            break;
                            
                    }

                    switch($documentType['poi_back'])
                    {
                        case 'pan_card':
                            array_push($customer_data['documents'],[
                                'type'             =>'pan_card',
                                'document_category'=>'poi_document_back',
                                'data'             =>$pancard
                            ]);
                            break;
    
                        case 'aadhaar_card':
                            array_push($customer_data['documents'],[
                                'type'             =>'aadhaar_card',
                                'document_category'=>'poi_document_back',//'poi_document',
                                'data'             =>$aadharCard_back,
                                'ovd_type'         =>'aadhaar'
                            ]);
                            break;
    
                        case 'passport':
                            array_push($customer_data['documents'],[
                                'type'             =>'passport',
                                'document_category'=>'poi_document_back',
                                'data'             =>$passportNumber_back,
                                'ovd_type'         =>'passport'
                            ]);
                            break;
    
                        case 'voter_id':
                            array_push($customer_data['documents'],[
                                'type'             =>'voter_id',
                                'document_category'=>'poi_document_back',
                                'data'             =>$voterCard_back,
                                'ovd_type'         =>'voterid'
                            ]);
                            break;
                        case 'driving':
                            array_push($customer_data['documents'],[
                                'type'             =>'driving',
                                'document_category'=>'poi_document_back',
                                'data'             =>$drivingLicense_back,
                                'ovd_type'         =>'drivingLicence'
                            ]);
                            break;
    
                        case 'gst_number':
                            array_push($customer_data['documents'],[
                                'type'             =>'gst_number',
                                'document_category'=>'poi_document_back',
                                'data'             =>$gst_number
                            ]);
                            break;
                            
                    }

                    
            break;
            default:
                return [];
                break;
        }
        $request_data = array_merge($request_data, $customer_data);
        // $remove_proxy = true;//remove proxy for internal call
        $remove_proxy = config('constants.REMOVE_PROXY_FOR_CKYC') != 'N' ? true : false;
        $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
            'Accept' => 'application/json'
        ], [], true, false, $remove_proxy, true);

        if (isset( $response['status'] ) && $response['status'] == 200) {
            ModelsUserProposal::updateOrCreate([
                'user_product_journey_id' => customDecrypt($request->enquiryId)
            ], [
                'is_ckyc_verified' => (isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status']) ? 'Y' : 'N',
                'ckyc_reference_id' => isset($response['response']['data']['ckyc_reference_id']) ? $response['response']['data']['ckyc_reference_id'] : null,
                'ckyc_meta_data' => isset($response['response']['data']['meta_data']) && !empty($response['response']['data']['meta_data']) ? $response['response']['data']['meta_data'] : null
            ]);

            // Need to update the CKYC status for RB
            event(new \App\Events\CKYCInitiated($enquiry_id));

            if ($request->companyAlias == 'iffco_tokio' && isset($response['response']['data']['ckyc_reference_id']) && ! empty($response['response']['data']['ckyc_reference_id'])) {
                return (new self)->ckycVerifications(new Request([
                    'companyAlias' => 'iffco_tokio',
                    'mode' => 'ckyc_reference_id',
                    'enquiryId' => $request->enquiryId
                ]));
            }

            $response['response']['status'] = $response['response']['data']['verification_status'] ?? false;

            if ($response['response']['data']['verification_status']) {
                $response['response']['ckyc_verified_using'] = $request_data['mode'];
            }
        } else {

            if (is_string($response)) {
                $standardized_response = [
                    'status' => false,
                    'data' => [
                        'message' => $response
                    ]
                ];
                return response()->json($standardized_response);
            }

            if (!isset($response['response']) || !is_array($response['response'])) {
                $standardized_response = [
                    'status' => false,
                    'data' => serialize($response)
                ];
                return response()->json($standardized_response);
            }
            
            $response['response']['status'] = false;
        }
       
        return response()->json($response['response']);
    }

    public static function ckycBajajAllianze(Request $request)
    {
        $enquiry_id = customDecrypt($request->enquiryId);
        $ckyc_verification_type = CkycVerificationTypes::where('company_alias', $request->companyAlias)->pluck('mode')->first();
        $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();

        $corporate_vehicles_quotes_request = $proposal->corporate_vehicles_quotes_request ?? '';
        $quote_log = $proposal->quote_log->premium_json;

        $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $enquiry_id)->first();
        if(empty($document_upload_data))
        {
            return response()->json([
                "verification_status" => false,
                "message" => 'Unable to find uploaded documents, please upload documents again.',
            ]);
        }
        $get_doc_data = json_decode($document_upload_data->cky_doc_data, true);
        $poi_pan_no = $poi_adhar_no = $poi_voter_id = $poi_gst_no = null;
        $poa_pan_no = $poa_adhar_no = $poa_voter_id = $poa_gst_no = $poa_driving_licence = $poa_passport = null;


        if (!empty($get_doc_data['proof_of_identity']['poi_identity'])) {
            $poi_pan_no = $get_doc_data['proof_of_identity']['poi_panNumber'];
            $poi_adhar_no = $get_doc_data['proof_of_identity']['poi_aadharNumber'];
            $poi_voter_id = $get_doc_data['proof_of_identity']['poi_voterId'] ?? '';
            $poi_gst_no = $get_doc_data['proof_of_identity']['poi_gstNumber'] ?? '';
        }
        if (!empty($get_doc_data['proof_of_address']['poa_identity'])) {

            $poa_pan_no = $get_doc_data['proof_of_address']['poa_panNumber'];
            $poa_adhar_no = $get_doc_data['proof_of_address']['poa_aadharNumber'];
            $poa_voter_id = $get_doc_data['proof_of_address']['poa_voterId'] ?? '';
            $poa_gst_no = $get_doc_data['proof_of_address']['poa_gstNumber'] ?? '';
            $poa_driving_licence = $get_doc_data['proof_of_address']['poa_drivingLicense'] ?? '';
            $poa_passport = $get_doc_data['proof_of_address']['poa_passportNumber'] ?? '';
        }
        //validate with pan number
        if ($get_doc_data['proof_of_identity']['poi_identity'] == 'panNumber') {
            $request_data = [
                'company_alias' => $request->companyAlias,
                'type' => $ckyc_verification_type,
                'mode' => 'pan_number_with_dob',
                'section' => 'motor',
                'trace_id' => customEncrypt($enquiry_id),
                'ckyc_number' => $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : null,
                'pan_no' => !empty($poi_pan_no) ? $poi_pan_no : $poa_pan_no,
                'aadhar' => !empty($poi_adhar_no) ? $poi_adhar_no : $poa_adhar_no,
                'date_of_birth' => $proposal->dob,
                'tenant_id' => config('constants.CKYC_TENANT_ID'),
                'meta_data' => [
                    "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type == "I" ? "I" : "O",
                    "transaction_id" => $proposal->proposal_no,
                    "user_id" => $request->user_id,
                    "location_code" => config('constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR'),
                    "product_code" => $request->product_code
                ]
            ];
        }
        else {
            return response()->json([
                "verification_status" => false,
                "message" => "Please select Proof of Identity.",
            ]);
        }

        // $remove_proxy = true;//remove proxy for internal call
        $remove_proxy = config('constants.REMOVE_PROXY_FOR_CKYC') != 'N' ? true : false;
        $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
            'Content-Type' => 'application/json'
        ], [], true, false, $remove_proxy, true);

        //if poi failed or not found
        if ((isset($response['response']['data']['ckyc_id']) && !empty($response['response']['data']['ckyc_id']))) {
            ModelsUserProposal::updateOrCreate([
                'user_product_journey_id' => customDecrypt($request->enquiryId)
            ], [
                'is_ckyc_verified' => isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status'] ? 'Y' : 'N',
                'ckyc_number' => isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status'] ? $response['response']['data']['ckyc_id'] : null,
                'ckyc_reference_id' => isset($response['response']['data']['ckyc_reference_id']) ? $response['response']['data']['ckyc_reference_id'] : null,
                'ckyc_meta_data' => isset($response['response']['data']['meta_data']) && !empty($response['response']['data']['meta_data']) ? $response['response']['data']['meta_data'] : null
            ]);
            
            // Need to update the CKYC status for RB
            event(new \App\Events\CKYCInitiated($enquiry_id));
            
            $response['response']['status'] = true;
            $response['response']['ckyc_verified_using'] = $request_data['mode'];

            return response()->json($response['response']);
        }
      //if poi failed or not found terminate
        if (((isset($response['response']['data']['message']) && $response['response']['data']['message'] == 'POI failed') || (isset($response['response']['data']['meta_data']['poiStatus']) && in_array($response['response']['data']['meta_data']['poiStatus'], ['NOT_FOUND', 'NA', null, ''])))) {
            $response['response']['status'] = false;
            return response()->json($response['response']);
        }

        #this code for poa status check if not found then upload respective docs
        #created dynamic mode basis on user uploaded files 
        $mode = '';
        if ($get_doc_data['proof_of_address']['poa_identity'] == 'passportNumber')
        {
            $mode ='passport';
        }
        else if($get_doc_data['proof_of_address']['poa_identity'] == 'drivingLicense')
        {
            $mode ='driving_licence';
        }
        else if($get_doc_data['proof_of_address']['poa_identity'] == 'voterId')
        {
            $mode ='voter_card';

        }else if($get_doc_data['proof_of_address']['poa_identity'] == 'aadharNumber'){
           
            $mode ='aadhar';
        } elseif ($get_doc_data['proof_of_address']['poa_identity'] == 'gstNumber'){
            $mode ='gst_number';
        }
        #dynamic code mode end

        $request_data = [
            'company_alias' => $request->companyAlias,
            'type' => $ckyc_verification_type,
            'mode' => $mode, #dynamic except pan
            'section' => 'motor',
            'trace_id' => customEncrypt($enquiry_id),
            'ckyc_number' => $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : null,
            'pan_no' => !empty($poi_pan_no) ? $poi_pan_no : $poa_pan_no,
            'aadhar' => !empty($poi_adhar_no) ? $poi_adhar_no : $poa_adhar_no,
            'passport_no' => !empty($poi_adhar_no) ? $poi_adhar_no : $poa_adhar_no,
            'voter_id' => $poa_voter_id,
            'driving_license' => $poa_driving_licence,
            'passport_no' => $poa_passport,
            'gst_no' => $poa_gst_no,
            'date_of_birth' => $proposal->dob,
            'tenant_id' => config('constants.CKYC_TENANT_ID'),
            'meta_data' => [
                "customer_type" => $corporate_vehicles_quotes_request->vehicle_owner_type == "I" ? "I" : "O",
                "transaction_id" => $proposal->proposal_no, //$proposal->proposal_no,
                "user_id" => $request->user_id,
                "location_code" => config('constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR'),
                "product_code" => $request->product_code
            ]
        ];

        if ($mode == 'aadhar') {
            $request_data['name'] = implode(' ', [$proposal->first_name, $proposal->last_name]);
        }

        $remove_proxy = true;//remove proxy for internal call
        $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
            'Content-Type' => 'application/json'
        ], [], true, false, $remove_proxy, true);
        if (
            isset($response['response']['data']['meta_data']['poaStatus']) &&
            ($response['response']['data']['meta_data']['poaStatus'] == 'NOT_FOUND' ||
            $response['response']['data']['meta_data']['poaStatus'] == 'NA' ||
            $response['response']['data']['meta_data']['poaStatus'] == 'null')
        ) {
            return self::ckycUploadDocuments($request);

        } else {
            ModelsUserProposal::updateOrCreate([
                'user_product_journey_id' => customDecrypt($request->enquiryId)
            ], [
                'is_ckyc_verified' => isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status'] ? 'Y' : 'N',
                'ckyc_number' => isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status'] ? $response['response']['data']['ckyc_id'] : null,
                'ckyc_reference_id' => isset($response['response']['data']['ckyc_reference_id']) ? $response['response']['data']['ckyc_reference_id'] : null,
                'ckyc_meta_data' => isset($response['response']['data']['meta_data']) && !empty($response['response']['data']['meta_data']) ? $response['response']['data']['meta_data'] : null
            ]);
            $response['response']['status'] = true;
            $response['response']['ckyc_verified_using'] = $request_data['mode'];

            return response()->json($response['response']);
        }
    }

    public function saveCkycResponseInProposal($request, $response, $proposal)
    {
        $first_name = null;
        $last_name = null;

        if (isset($response['response']['data']['customer_details']['name']) && ! empty($response['response']['data']['customer_details']['name'])) {
            if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
                $name = explode(' ', $response['response']['data']['customer_details']['name']);

                $first_name = $name[0];
                $last_name = null;
        
                if (count($name) > 1) {
                    if (count($name) > 2) {
                        $fname_array = $name;
                        unset($fname_array[count($fname_array) - 1]);
                        $first_name = implode(' ', $fname_array);
                        $last_name = $name[count($name) - 1];
                    } else {
                        $last_name = $name[1];
                    }
                }
        
                $updated_proposal['first_name'] = $first_name;
                $updated_proposal['last_name'] = $last_name;
            } else {
                $updated_proposal['first_name'] = $response['response']['data']['customer_details']['name'];
                $updated_proposal['last_name'] = null;
            }
        }

        if (empty($first_name)) {
            $first_name = $proposal->first_name;
        }

        $updated_proposal['email'] = ! empty($response['response']['data']['customer_details']['email']) ? $response['response']['data']['customer_details']['email'] : $proposal->email;
        $updated_proposal['mobile_number'] = ! empty($response['response']['data']['customer_details']['mobile']) ? $response['response']['data']['customer_details']['mobile'] : $proposal->mobile_number;
        $updated_proposal['dob'] = ! empty($response['response']['data']['customer_details']['dob']) ? $response['response']['data']['customer_details']['dob'] : $proposal->dob;

        if ($request->company_alias != 'future_generali') {
            $updated_proposal['address_line1'] = ! empty($response['response']['data']['customer_details']['address']) ? $response['response']['data']['customer_details']['address'] : $proposal->address_line1;
            $updated_proposal['pincode'] = ! empty($response['response']['data']['customer_details']['pincode']) ? $response['response']['data']['customer_details']['pincode'] : $proposal->pincode;
        }

        $updated_proposal['pan_number'] = ! empty($response['response']['data']['customer_details']['pan_no']) ? strtoupper($response['response']['data']['customer_details']['pan_no']) : $proposal->pan_number;
        $updated_proposal['ckyc_number'] = ! empty($response['response']['data']['customer_details']['ckyc']) ? $response['response']['data']['customer_details']['ckyc'] : $proposal->ckyc_number;

        $additional_details = json_decode($proposal->additional_details, true);

        if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
            if ( ! empty($first_name) && ! empty($last_name)) {
                $additional_details['owner']['fullName'] = implode(' ', array_filter([$first_name, $last_name]));
            }

            $additional_details['owner']['firstName'] = $first_name ?? null;
            $additional_details['owner']['lastName'] = $last_name ?? null;
        } else {
            $additional_details['owner']['fullName'] = $response['response']['data']['customer_details']['name'] ?? null;
            $additional_details['owner']['firstName'] = $response['response']['data']['customer_details']['name'] ?? null;
            $additional_details['owner']['lastName'] = null;
        }

        $additional_details['owner']['dob'] = $updated_proposal['dob'];
        $additional_details['owner']['email'] = $updated_proposal['email'];
        $additional_details['owner']['mobileNumber'] = $updated_proposal['mobile_number'];
        $additional_details['owner']['panNumber'] = $updated_proposal['pan_number'];

        if ($request->company_alias != 'future_generali') {
            $additional_details['owner']['addressLine1'] = $updated_proposal['address_line1'];
            $additional_details['owner']['address'] = $updated_proposal['address_line1'];
            $additional_details['owner']['pincode'] = $updated_proposal['pincode'];
        }

        $additional_details['owner']['ckycNumber'] = $updated_proposal['ckyc_number'];

        if (in_array($request->company_alias, ['kotak', 'future_generali']) && isset($response['response']['data']['customer_details']['ckyc']) && ! empty($response['response']['data']['customer_details']['ckyc'])) {
            $updated_proposal['ckyc_type'] = ! empty($response['response']['data']['customer_details']['ckyc']) ? 'ckyc_number' : $proposal->ckyc_type;
            $updated_proposal['ckyc_type_value'] = ! empty($response['response']['data']['customer_details']['ckyc']) ? $response['response']['data']['customer_details']['ckyc'] : $proposal->ckyc_type_value;
            $additional_details['owner']['isckycPresent'] = 'YES';
        }

        if ($request->company_alias != 'future_generali') {
            if ( ! empty($response['response']['data']['customer_details']['pincode'])) {
                $common_controller = new CommonController;

                $address_response = $common_controller->getIcPincode(new Request([
                    'pincode' => $response['response']['data']['customer_details']['pincode'],
                    'companyAlias' => $request->company_alias,
                    'enquiryId' => $request->trace_id
                ]));

                $address_details = $address_response->getOriginalContent();

                if ($address_response->status() == 200 && $address_details['status']) {
                    $updated_proposal['state'] = $address_details['data']['state']['state_name'];
                    $additional_details['owner']['stateId'] = $address_details['data']['state']['state_id'];
                    $additional_details['owner']['state'] = $address_details['data']['state']['state_name'];
                    $updated_proposal['city'] = $address_details['data']['city'][0]['city_name'];
                    $additional_details['owner']['cityId'] = $address_details['data']['city'][0]['city_id'];
                    $additional_details['owner']['city'] = $address_details['data']['city'][0]['city_name'];
                }
            }
        }

        $updated_proposal['additional_details'] = json_encode($additional_details);

        return $updated_proposal;
    }

    public static function checkAllowedFileExtentions($ext, $companyAlias, $type = '')
    {
        if(!in_array(strtolower($ext),['pdf','jpeg','jpg','png']) && $companyAlias == 'icici_lombard') {
            return [
                'status' => false,
                'msg' => "Please upload document in PNG, JPEG, JPG or PDF format.",
            ];
        }
        if(!in_array(strtolower($ext),['pdf','jpeg','jpg','tif', 'tiff']) && $companyAlias == 'iffco_tokio') {
            return [
                'status' => false,
                'msg' => "Please upload document in PDF, JPEG, JPG, TIF or TIFF format.",
            ];
        }
        if(!in_array(strtolower($ext),['pdf','jpeg','jpg','tif', 'tiff']) && $companyAlias == 'nic') {
            return [
                'status' => false,
                'msg' => "Please upload document in PDF, JPEG, JPG, TIF or TIFF format.",
            ];
        }
        return ['status' => true];
    }

    public function royalSundaramUpdateVerificationData ($response, $proposal) {
        $updated_proposal['is_ckyc_verified'] = 'Y';
        $updated_proposal['first_name'] = $response['response']['data']['customer_details']['firstName'] ?? null;
        $updated_proposal['first_name'] .= ($response['response']['data']['customer_details']['middleName'] != null && $response['response']['data']['customer_details']['middleName'] != ' ') ? ' '.$response['response']['data']['customer_details']['middleName'] : null;
        $updated_proposal['last_name'] = trim($response['response']['data']['customer_details']['lastName']) ?? null;
        $updated_proposal['dob'] =  !empty($response['response']['data']['customer_details']['dob']) ?  $response['response']['data']['customer_details']['dob'] : $proposal->dob;
        $updated_proposal['ckyc_number'] = $response['response']['data']['ckyc_id'];
        /* $updated_proposal['mobile_number'] = !empty($response['response']['data']['customer_details']['mobile']) ? $response['response']['data']['customer_details']['mobile'] : $proposal->mobile_number;
        $updated_proposal['address_line1'] = !empty($response['response']['data']['customer_details']['address1']) ? $response['response']['data']['customer_details']['address1'] : $proposal->address_line1 ;
        $updated_proposal['pincode'] = !empty($response['response']['data']['customer_details']['pinCode']) ? $response['response']['data']['customer_details']['pinCode'] : $proposal->pincode;
        $updated_proposal['email'] = !empty($response['response']['data']['customer_details']['email']) ? $response['response']['data']['customer_details']['email'] : $proposal->email; */
        $updated_proposal['pan_number'] =  !empty($response['response']['data']['customer_details']['pan']) ? $response['response']['data']['customer_details']['pan'] : $proposal->pan_number ;

        // additional details
        $additional_details = json_decode($proposal->additional_details, true);

        $additional_details['owner']['fullName'] = implode(' ', array_filter([$updated_proposal['first_name'], $updated_proposal['last_name']]));
        $additional_details['owner']['firstName'] = $updated_proposal['first_name'];
        $additional_details['owner']['lastName'] = $updated_proposal['last_name'];
        $additional_details['owner']['dob'] = $updated_proposal['dob'];
        /* $additional_details['owner']['mobileNumber'] = $updated_proposal['mobile_number'];
        $additional_details['owner']['addressLine1'] = $updated_proposal['address_line1'];
        $additional_details['owner']['address'] = $updated_proposal['address_line1'];
        $additional_details['owner']['pincode'] = $updated_proposal['pincode']; */
        /* $additional_details['owner']['isckycPresent'] = 'YES';
        $additional_details['owner']['ckycNumber'] = $updated_proposal['ckyc_number']; */
        // $additional_details['owner']['email'] = $updated_proposal['email'];
        $additional_details['owner']['panNumber'] = $updated_proposal['pan_number'];
        
        /* $address_details = httpRequestNormal(url('/api/getPincode?pincode=' . $response['response']['data']['customer_details']['pinCode'] . '&companyAlias=royal_sundaram&enquiryId=' . $request->trace_id), 'GET', [], [], [
            'Content-Type' => 'application/json'
        ], [], false, false); */

        $common_controller = new CommonController;

        $per_address_details = [];

        if (! empty($response['response']['data']['customer_details']['pinCode'])) {
            $address_response = $common_controller->getIcPincode(new Request([
                'pincode' => $response['response']['data']['customer_details']['pinCode'],
                'companyAlias' => 'royal_sundaram',
                'enquiryId' => customEncrypt($proposal->user_product_journey_id)
            ]));

            if ($address_response->status() == 200) {
                $address_details = $address_response->getOriginalContent();

                if ($address_details['status']) {
                    $per_address_details['city'] = $address_details['data']['city'][0]['city_name'];
                    $per_address_details['pincode'] = $response['response']['data']['customer_details']['pinCode'];
                }
            }
        }

        $updated_proposal['additional_details'] = json_encode($additional_details);

        ModelsUserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
        ->update($updated_proposal);

        $permanent_address_details = array_filter([
            'permanent_address' => trim(implode('', [$response['response']['data']['customer_details']['address1'] ?? '', $response['response']['data']['customer_details']['address2'] ?? '', $response['response']['data']['customer_details']['address3'] ?? ''])),
            'city' => $per_address_details['city'] ?? null,
            'pincode' => $per_address_details['pincode'] ?? null
        ]);

        if ( ! empty($permanent_address_details)) {
            ProposerCkycDetails::updateOrCreate([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'user_proposal_id' => $proposal->user_proposal_id
            ], [
                'permanent_address' => json_encode($permanent_address_details, JSON_UNESCAPED_SLASHES)
            ]);
        }

        // if(isset($request->skip_proposal) && $request->skip_proposal == true)
        // {
        //     return response()->json([
        //         'status' => true
        //     ]);
        // }
    }

    public function setGenderInApi($response, $request)
    {
        $details = $response['response']['data']['customer_details'] ?? [];

        if (isset($details['genderName']) && !isset($details['gender'])) {

            $details['genderName'] = $this->getGenderAsPerValue($request, $details['genderName']);
            $details['gender'] = $this->getGenderAsPerValue($request, $details['genderName']);

        } else if (isset($details['gender']) && !isset($details['genderName'])) {

            $details['gender'] = $this->getGenderAsPerValue($request, $details['gender']);
            $details['genderName'] = $this->getGenderAsPerValue($request, $details['gender']);

        } else if (isset($details['genderName']) && isset($details['gender'])) {

            $details['genderName'] = $this->getGenderAsPerValue($request, $details['genderName']);
            $details['gender'] = $this->getGenderAsPerValue($request, $details['gender']);

        }
        $response['response']['data']['customer_details'] = $details;
        unset($details);
        return $response;
    }

    public function getGenderAsPerValue($request, $value) {
        $enquiry_id = customDecrypt($request->enquiryId);
        $proposal = ModelsUserProposal::where('user_product_journey_id', $enquiry_id)->first();
        $corporate_vehicles_quotes_request = $proposal->corporate_vehicles_quotes_request ?? '';

        if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I' && !empty($value)) {
            $value = in_array(strtoupper($value), ['M', 'MALE']) ? 'M' : 'F';
        } else {
            $value = null;
        }
        return $value;
    }
    public static function orientalProposalSave ($request, $proposal) {

        if(config('constants.oriental.ENABLE_CKYC_PROPOSAL_UPDATE') != 'Y'){
            return true;
        }
        $data_update = [];

        $additional_details = json_decode($proposal->additional_details , true);

        if (!empty($request['dob'])) {
            $data_update['dob'] = str_replace('/', '-',$request['dob']);
            $additional_details['owner']['dob'] = str_replace('/', '-',$request['dob']);
        }
        if (!empty($request['individualPAN'])) {
            $data_update['pan_number'] = $request['individualPAN'];
            $additional_details['owner']['panNumber'] = $request['individualPAN'];
        }

        if (!empty($request['insuredName'])) {
            if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {

                $name = explode(' ', removeSalutation($request['insuredName']));

                $first_name = $name[0];
                $last_name = null;
        
                if (count($name) > 1) {
                    if (count($name) > 2) {
                        $fname_array = $name;
                        unset($fname_array[count($fname_array) - 1]);
                        $first_name = implode(' ', $fname_array);
                        $last_name = $name[count($name) - 1];
                    } else {
                        $last_name = $name[1];
                    }
                }
                $additional_details['owner']['firstName'] = $first_name;
                $additional_details['owner']['lastName'] = $last_name;
                $additional_details['owner']['fullName'] = $first_name .' '. $last_name;

                $data_update['first_name'] = $first_name;
                $data_update['last_name'] = $last_name;
            } else {
                $data_update['first_name'] = $request['insuredName'];
                $data_update['last_name'] = null;
            }
        }
        $data_update["additional_details"] = json_encode($additional_details);
        $data_update['is_ckyc_verified'] = 'Y';
        ModelsUserProposal::where([
            'user_product_journey_id' => customDecrypt($request->enquiryId)])
            ->update($data_update);
        return true;
    }

    public static function nicCKYCVerification($proposal, $request) {
        $startTime = microtime(true);
        $request_data = [
            'name' => $proposal->first_name,
            'email' => $proposal->email,
            'mobile' =>  $proposal->mobile_number,
            'webAggregatorName' => config('IC.NIC.V2.CAR.NIC_WEB_AGGREGATOR'),
            'requestId' => $request['requestId'],
        ];
        
        $response = httpRequestNormal(config('IC.NIC.V2.CAR.NIC_CKYC_GET_AADHAR_DETAILS_VERIFICATION_URL'), 'POST', $request_data, [], [
            'Content-Type' => 'text/plain',
            'WWW-Authenticate' => 'Basic '.config('IC.NIC.V2.CAR.NIC_AUTH_TOKEN')
        ], [], true, false, true);
        
        $endTime = microtime(true);
        $responseTime = round($endTime - $startTime, 2);
        
        if ($response['response']['responseCode'] == 999) {
            $proposal->is_ckyc_verified = 'Y';
            $proposal->first_name = $response['response']['firstName'];
            $proposal->last_name = $response['response']['lastName'];
            $proposal->ckyc_meta_data = json_encode([
                'name' => $response['response']['firstName'].' '.$response['response']['lastName'],
                'merchantId' => $response['response']['merchantId'],
                'JourneyType' => $response['response']['journeyType'],
                'signzyAppId' => $response['response']['signzyAppId']
            ]);
            $proposal->save();
        } else {
            $proposal->is_ckyc_verified = 'N';
            $proposal->ckyc_meta_data = json_encode([
                'status' => $request['status'] ?? 'error',
                'responseMessage' => $response['response']['responseMessage'] ?? 'CKYC verification failed'
            ]);
            $proposal->save();
        }

        $store_data_encrypt = [
            'enquiry_id'        => $proposal->user_product_journey_id,
            'product'           => '',
            'section'           => 'motor',
            'method_name'       => 'EKYC – Digilocker Aadhar',
            'company'           => 'nic',
            'method'            => 'post',
            'transaction_type'  => 'proposal',
            'request'           => json_encode($response['request']),
            'response'          => json_encode($response['response']),
            'endpoint_url'      => $response['url'],
            'ip_address'        => request()->ip(),
            'start_time'        => Carbon::now(),
            'end_time'          => Carbon::now(),
            'response_time'     => $responseTime,
            'created_at'        => Carbon::now(),
            'headers'           => json_encode($response['request_headers']),
        ];
        WebServiceRequestResponse::create($store_data_encrypt);
    }

}
