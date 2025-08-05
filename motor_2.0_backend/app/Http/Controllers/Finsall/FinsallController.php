<?php

namespace App\Http\Controllers\Finsall;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\QuoteLog;
use App\Models\Finsall\FinsallDataTable;
use App\Models\Finsall\FinsallEntityType;
use App\Models\Finsall\FinsallPolicyDeatail;
use App\Models\Finsall\FinsallExternalEntity;
use App\Models\Finsall\FinsallTransactionData;

use App\Models\PolicyDetails;
use App\Models\PaymentResponse;
use App\Models\PaymentRequestResponse;

use Exception;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Finsall\RelianceFinsallController;
use App\Http\Controllers\Payment\Services\hdfcErgoPaymentGateway;
use App\Http\Controllers\Payment\Services\V1\HdfcErgoPaymentGateway AS  HDFC_ERGO_V1;
use App\Models\CvAgentMapping;
use App\Models\MasterProductSubType;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class FinsallController extends Controller
{
    static function saveOrUpdateBankSelector(Request $request)
    {
        try {
            $validateData = $request->validate([
                'enquiryId' => ['required'],
                'panNumber' => ['required'],
            ]);

            try{
                $enquiryId = customDecrypt($request->enquiryId);
            }
            catch(Exception $e)
            {
                return response()->json([
                    'status' => false,
                    'message' => 'enquiryId Not Found',
                    'error' => $e->getMessage(),
                    // 'data' => []
                ]);
            }

            $proposal = UserProposal::where([
                'user_product_journey_id' => $enquiryId
            ])->first();

            $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->first();

            $proposal->pan_number = $request->panNumber;

            $finsall_ic_data = DB::table('finsall_ic_mapping as fim')
            ->join('master_company as mc', 'mc.company_name', '=', 'fim.ic_name')
            ->where('mc.company_id', $proposal->ic_id)
            ->select('mc.*', 'fim.finsall_ic_name as finsall_ic_name')
            ->first();

            if($quote_log_data->product_sub_type_id == 6 && $finsall_ic_data->company_alias == 'reliance')
            {
                $proposal_data = RelianceFinsallController::submitProposal($proposal, $finsall_ic_data, $enquiryId);

                if(!$proposal_data['status'])
                {
                    return  response()->json($proposal_data);
                }

                if($proposal_data['status'])
                {
                    $proposal->proposal_no = $proposal_data['data']['proposal_no'];

                    UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)->update([
                        'proposal_no' => $proposal_data['data']['proposal_no']
                    ]);
                }

            }

            $proposal->save();

            $FinsallEntityType = FinsallEntityType::where('entity_name', $finsall_ic_data->finsall_ic_name)
            ->first();

            $entityTypeId = [
                1 => '1',
                2 => '2',
                8 => '16',
                4 => '15'
            ];

            $sectionType = [
                1 => 'car',
                2 => 'bike',
                8 => 'pcv',
                4 => 'gcv'
            ];

            $parent_code = MasterProductSubType::where('product_sub_type_id', $quote_log_data->product_sub_type_id)
            ->pluck('parent_product_sub_type_id')
            ->first();

            if (empty($parent_code)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Finsall is not integrated for this vehicle'
                ]);
            }

            $externalEntityTypeId = $entityTypeId[$parent_code] ?? '';

            $address_data = [
                'address' => $proposal->address_line1,
                'address_1_limit'   => 100,
                'address_2_limit'   => 100
            ];
            $getAddress = getAddress($address_data);

            if(empty($getAddress['address_2'])) {
                $getAddress['address_2'] = $proposal->state;
            }
            
            $userRequestData = [
                'mobileNo' => $proposal->mobile_number,
                'alternateMobileNo' => $proposal->mobile_number,
                'emailId' => $proposal->email,
                'firstName' => $proposal->first_name,
                'lastName' => $proposal->last_name,
                'addressLine1' => $getAddress['address_1'],//$proposal->address_line1,
                'addressLine2' => $getAddress['address_2'],//$proposal->address_line2,
                'state' => $proposal->state,
                'city' => $proposal->city,
                'pinCode' => (string)($proposal->pincode),
                'pan' => $request->panNumber,
            ];
            if(empty($proposal->last_name ?? ''))
            {
                $userRequestData['lastName'] = '.';
            }

            $salutation = '';
            if($proposal->owner_type == 'I')
            {
                if(!empty($proposal->dob ?? ''))
                {
                    $userRequestData['dob'] = (string)(strtotime($proposal->dob).'000');
                }
                if(!empty($proposal->gender_name ?? ''))
                {
                    $Gender = 'Not Applicable';
                    if(in_array(strtolower($proposal->gender_name),['m','male']))
                    {
                        $salutation = 'Mr.';
                        $Gender = 'Male';
                    }else if (in_array(strtolower($proposal->gender_name),['f','female']))
                    {
                        $salutation = 'Ms.';
                        $Gender = 'Female';
                    }
                    $userRequestData['gender'] = $Gender;
                    // $userRequestData['gender'] = $proposal->gender_name;
                }
                if(!empty($proposal->marital_status ?? ''))
                {
                    if (!empty($salutation)) {
                        $salutation = $salutation == 'Ms.' ? 'Mrs.' : $salutation; 
                    }
                    $userRequestData['maritalStatus'] = $proposal->marital_status;
                }
            } else {
                $salutation = 'Ms.';
            }
            $userRequestData['title'] = $salutation;
            //as per finsall annual income should be greater than 3 lakh
            $userRequestData['annualIncome'] = config('constants.finsall.user.ANNUAL_INCOME', '350000');

            $configMode = ($request->isEmi ?? false) ? 'emi' : 'full_payment';
            $companyAlias = $finsall_ic_data->company_alias;

            $logged_in_unique_identifier_id = config("constants.finsall.{$companyAlias}.{$configMode}.UNIQUE_IDENTIFIER");
            $logged_in_user_id = config("constants.finsall.{$companyAlias}.{$configMode}.USER_ID");
            $client_id = config("constants.finsall.{$companyAlias}.{$configMode}.CLIENT_ID");
            $client_key = config("constants.finsall.{$companyAlias}.{$configMode}.CLIENT_KEY");

            $version = config('constants.finsall.VERSION');
            $roles = config('constants.finsall.ROLES');

            $auth_token = config("constants.finsall.{$companyAlias}.{$configMode}.AUTH_TOKEN");
            $auth_username = config("constants.finsall.{$companyAlias}.{$configMode}.AUTH_USERNAME");
            $externalEntityNameId = config("constants.finsall.{$companyAlias}.{$configMode}.EXTERNAL_ENTITY_NAME_ID");

            $mode = ($request->isEmi ?? false) ? 'EMI' : 'FULL_PAYMENT';

            $agentData = CvAgentMapping::where('user_product_journey_id', $proposal->user_product_journey_id)->first();

            $saveOrUpdateBankSelectorRequest = [
                'serviceName' => 'BankSelectorService',
                'serviceMethod' => 'saveOrUpdateBankSelector',

                'user' => $userRequestData,
                'external_entity_name' => [
                    'externalEntityNameId' => $externalEntityNameId, //$request->externalEntityNameId,
                ],
                'external_entity_type' => [
                    'externalEntityTypeId' => $externalEntityTypeId,//$request->externalEntityTypeId,
                ],

                "policyName" => "Car Loan",
                "policyRefNumber" => $proposal->proposal_no,

                // Both are expiry dates
                "policyExpiryDate" => (string)(strtotime($proposal->policy_end_date).'000'),
                "policyRenewalDate" => (string)(strtotime($proposal->policy_end_date).'000'),

                "executiveMobileNo" => $agentData->agent_mobile ?? null,
                "executiveEmailId" => $agentData->agent_email ?? null,
                "referenceField1" => $request->enquiryId,
                "referenceField2" => (string)($proposal->user_proposal_id),
                "referenceField3" => $proposal->vehicle_registration_number ?? '0',

                "loggedInUniqueIdentifierId" => $logged_in_unique_identifier_id,
                "loggedInUserId" => $logged_in_user_id,
                "clientId" => $client_id,
                "clientKey" => $client_key,
                "version" => $version,
                "roles" => $roles, //'executive'
            ];

            if(!empty((round($proposal->final_payable_amount) - round($proposal->tp_premium * 1.18))))
            {
                $saveOrUpdateBankSelectorRequest['otherPremium'] = (string)(round($proposal->final_payable_amount) - round($proposal->tp_premium * 1.18));
                
                $saveOrUpdateBankSelectorRequest['thirdPartyPremium'] = (string)(round($proposal->tp_premium * 1.18));
            }
            else{
                $saveOrUpdateBankSelectorRequest['otherPremium'] = (string)(round($proposal->final_payable_amount));
                $saveOrUpdateBankSelectorRequest['thirdPartyPremium'] = (string)(round(0));
            }

            $get_response = getWsData(
                config('constants.finsall.FINSALL_SERVICE_URL'),
                $saveOrUpdateBankSelectorRequest,
                'finsall',
                [
                    'headers' => [
                        'authentication-token' => $auth_token,
                        'Content-Type' => 'application/json',
                        'authentication-username' => $auth_username
                    ],
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'section' => 'finsall',
                    'method' => 'saveOrUpdateBankSelector',
                    'transaction_type' => 'proposal',
                ]
            );
            $saveOrUpdateBankSelectorResponse = $get_response['response'];

            FinsallTransactionData::updateOrCreate(
                [
                    'enquiry_id' => $enquiryId,
                    'proposal_no' => $proposal->proposal_no,
                ],
                [
                    'section' => $sectionType[$parent_code],
                    'company_allias' => $finsall_ic_data->company_alias,
                    'other_premium' => (string)(round($proposal->final_payable_amount) - round($proposal->tp_premium * 1.18)),
                    'third_party_premium' => (string)(round($proposal->tp_premium * 1.18)),
                ]
            );

            if ($saveOrUpdateBankSelectorResponse) {
                $saveOrUpdateBankSelectorResponse2 = $saveOrUpdateBankSelectorResponse;
                $saveOrUpdateBankSelectorResponse = json_decode($saveOrUpdateBankSelectorResponse);

                if(empty($saveOrUpdateBankSelectorResponse)) {
                    $service_issue = (str_contains($saveOrUpdateBankSelectorResponse2, 'Service Temporarily Unavailable') || str_contains($saveOrUpdateBankSelectorResponse2, 'Service Unavailable'));

                    $errorMessage = 'Invalid response from Finsall service';

                    if($service_issue) {
                        $errorMessage = 'Finsall service is temporarily unavailable';
                    }

                    return response()->json([
                        'status' => false,
                        'message' => $errorMessage,
                        // 'data' => []
                    ]);
                }

                if (!isset($saveOrUpdateBankSelectorResponse->errorMessage)) {


                    FinsallDataTable::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'method_name' => 'saveOrUpdateBankSelector'
                        ],
                        [
                            'status' => true,
                            'message' => 'success',
                            'data' => json_encode($saveOrUpdateBankSelectorResponse)
                        ]
                    );

                    PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                        ->where('ic_id', $proposal->icId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update(['active' => 0]);

                    PaymentRequestResponse::create([
                        'quote_id' => $quote_log_data->quote_id,
                        'user_product_journey_id' => $enquiryId,
                        'user_proposal_id' => $proposal->user_proposal_id,
                        'ic_id' => $proposal->icId,
                        'order_id' => $proposal->proposal_no,
                        'proposal_no' => $proposal->proposal_no,
                        'amount' => $proposal->final_payable_amount,
                        'payment_url' => $saveOrUpdateBankSelectorResponse->redirectionLink,
                        'return_url' => route('cv.payment-confirm', [$finsall_ic_data->company_alias]),
                        'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                        'lead_source' => 'finsall',
                        'active' => 1
                    ]);

                    FinsallTransactionData::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'proposal_no' => $proposal->proposal_no,
                        ],
                        [
                            'status' => STAGE_NAMES['PAYMENT_INITIATED']
                        ]
                    );

                    FinsallPolicyDeatail::updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiryId,
                        ],
                        [
                            'section' => $sectionType[$parent_code],
                            'company_allias' => $finsall_ic_data->company_alias,
                            'proposal_no' => $proposal->proposal_no,
                            'status' => 'Redirected to Finsall',
                            'mode' => $mode
                        ]
                    );

                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['PAYMENT_INITIATED']
                    ]);

                    return response()->json([
                        'status' => true,
                        'message' => 'success',
                        'enquiry_id' => $enquiryId,
                        'method_name' => 'saveOrUpdateBankSelector',
                        'data' => $saveOrUpdateBankSelectorResponse
                    ]);
                } else {
                    FinsallDataTable::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'method_name' => 'saveOrUpdateBankSelector'
                        ],
                        [
                            'status' => false,
                            'message' => $saveOrUpdateBankSelectorResponse->errorMessage,
                            'data' => json_encode([])
                        ]
                    );
                    return response()->json([
                        'status' => false,
                        'message' => $saveOrUpdateBankSelectorResponse->errorMessage,
                        'enquiry_id' => $enquiryId,
                        'method_name' => 'saveOrUpdateBankSelector',
                        'saveOrUpdateBankSelectorResponse' => $saveOrUpdateBankSelectorResponse,
                        'saveOrUpdateBankSelectorRequest' => $saveOrUpdateBankSelectorRequest,
                        'proposal' => $proposal,
                        // 'data' => [],
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to connect to Finsall',
                    // 'data' => []
                ]);
            }
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }
    }

    public static function getExternalEntityType()
    {
        try {
            $externalEntityTypeRequest = [
                "serviceName" => "FinsAllService",
                "serviceMethod" => "getExternalEntityType",
                "clientId" => config('constants.finsall.reliance.emi.CLIENT_ID'),
                "clientKey" => config('constants.finsall.reliance.emi.CLIENT_KEY'),
                "version" => config('constants.finsall.VERSION'),
                "roles" => config('constants.finsall.ROLES'),
                "loggedInUniqueIdentifierId" => config('constants.finsall.reliance.emi.UNIQUE_IDENTIFIER'),
                "loggedInUserId" => config('constants.finsall.reliance.emi.USER_ID')
            ];

            $get_response = getWsData(
                config('constants.finsall.FINSALL_SERVICE_URL'),
                $externalEntityTypeRequest,
                'finsall',
                [
                    'headers' => [
                        'authentication-token' => config('constants.finsall.reliance.emi.AUTH_TOKEN'),
                        'Content-Type' => 'application/json',
                        'authentication-username' => config('constants.finsall.reliance.emi.AUTH_USERNAME')
                    ],
                    'enquiryId' => 0,
                    'requestMethod' => 'post',
                    'section' => 'finsall',
                    'method' => 'getExternalEntityType',
                    'transaction_type' => 'proposal',
                ]
            );
            $externalEntityTypeResponse = $get_response['response'];

            if ($externalEntityTypeResponse) {
                $externalEntityTypeResponse = json_decode($externalEntityTypeResponse);

                if (!isset($externalEntityTypeResponse->errorMessage)) {
                    $arr = [];
                    foreach ($externalEntityTypeResponse->external_entity_type as $key => $value) {
                        $data['externalEntityTypeId'] = $value->externalEntityTypeId;
                        $data['entityType'] = $value->entityType;
                        $data['icon'] = $value->icon;
                        $data['type'] = $value->insurance_type->type;
                        $data['insuranceTypeId'] = $value->insurance_type->insuranceTypeId;

                        foreach ($value->insurance_type->insurance_type_and_root as $insurance_type) {
                            $data['insuranceTypeRootId'] = $insurance_type->insurance_type_root->insuranceTypeRootId;
                            $data['name'] = $insurance_type->insurance_type_root->name;

                            $arr[] = $data;

                            FinsallExternalEntity::where(
                                [
                                    'external_entity_type_id' => $data['externalEntityTypeId'],
                                    'insurance_type_id' => $data['insuranceTypeId'],
                                    'insurance_type_root_id' => $data['insuranceTypeRootId'],
                                ]
                            )->update(
                                [
                                    'status' => 0,
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]
                            );

                            FinsallExternalEntity::insert(
                                [
                                    'external_entity_type_id' => $data['externalEntityTypeId'],
                                    'insurance_type_id' => $data['insuranceTypeId'],
                                    'insurance_type_root_id' => $data['insuranceTypeRootId'],
                                    'entity_type' => $data['insuranceTypeRootId'],
                                    'icon' => $data['icon'],
                                    'type' => $data['type'],
                                    'name' => $data['name'],
                                    'status' => 1,
                                    'created_at' => date('Y-m-d H:i:s')
                                ]
                            );
                        }
                    }

                    return [
                        'status' => false,
                        'message' => 'Finsall external entity data updated successfully',
                        // 'data' => $arr
                    ];
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => $externalEntityTypeResponse->errorMessage,
                        // 'data' => []
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to connect to Finsall',
                    // 'data' => []
                ]);
            }
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }
    }

    
    public static function getEntityTypeAndNameById(Request $request)
    {
        try {
            $getEntityTypeRequest = [
                "serviceName" => "FinsAllService",
                "serviceMethod" => "getEntityTypeAndNameById",

                "genericIdentifiers" => [
                    $request->ExternalEntityId
                ],

                "clientId" => config('constants.finsall.reliance.emi.CLIENT_ID'),
                "clientKey" => config('constants.finsall.reliance.emi.CLIENT_KEY'),
                "version" => config('constants.finsall.VERSION'),
                "roles" => config('constants.finsall.ROLES'),
                "loggedInUniqueIdentifierId" => config('constants.finsall.reliance.emi.UNIQUE_IDENTIFIER'),
                "loggedInUserId" => config('constants.finsall.reliance.emi.USER_ID')
            ];

            $get_response = getWsData(
                config('constants.finsall.FINSALL_SERVICE_URL'),
                $getEntityTypeRequest,
                'finsall',
                [
                    'headers' => [
                        'authentication-token' => config('constants.finsall.reliance.emi.AUTH_TOKEN'),
                        'Content-Type' => 'application/json',
                        'authentication-username' => config('constants.finsall.reliance.emi.AUTH_USERNAME')
                    ],
                    'enquiryId' => 0,
                    'requestMethod' => 'post',
                    'section' => 'finsall',
                    'method' => 'getEntityTypeAndNameById',
                    'transaction_type' => 'proposal',
                ]
            );
            $getEntityTypeResponse = $get_response['response'];

            if ($getEntityTypeResponse) {
                $getEntityTypeResponse = json_decode($getEntityTypeResponse);

                if (!isset($getEntityTypeResponse->errorMessage)) {
                    $arr = [];
                    foreach ($getEntityTypeResponse->external_entity_name->external_entity_name_list as $key => $value) {

                        $data['externalEntityNameId'] = $value->externalEntityNameId;
                        $data['entityName'] = $value->entityName;
                        $data['icon'] = $value->icon;
                        $data['website'] = $value->website;
                        $data['telephone'] = $value->telephone;

                        $arr[] = $data;
                        
                        FinsallEntityType::where('external_entity_name_id' , $data['externalEntityNameId'])
                        ->update([
                            'status' => 0,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                        FinsallEntityType::insert([
                            'external_entity_name_id' => $data['externalEntityNameId'],
                            'entity_name' => $data['entityName'],
                            'icon' => $data['icon'],
                            'website' => $data['website'],
                            'telephone' => $data['telephone'],
                            'status' => 1,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    return response()->json(camelCase([
                        'status' => true,
                        'message' => 'success',
                        'data' => $arr
                    ]));
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => $getEntityTypeResponse->errorMessage,
                        // 'data' => []
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to connect to Finsall',
                    // 'data' => []
                ]);
            }
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }
    }


    public function paymentConfirm(Request $request)
    {
        $requestType =  app()->runningInConsole() ? 'SCHEDULER' : 'WEB';

        PaymentResponse::create([
            'company_alias' => "finsall",
            'section' => 'cv',
            'response' => json_encode( [ 'icResponse' => $request->all(), 'mode' => $requestType ] )
        ]);
        
        $PaymentRequestData = PaymentRequestResponse::where([
            'order_id' => $request->policyRefNumber,
            'active' => 1
        ])->first();

        if(empty($PaymentRequestData))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        $proposal = UserProposal::where('user_proposal_id', $PaymentRequestData->user_proposal_id)->first();

        if(empty($proposal))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($PaymentRequestData->user_product_journey_id)]));
        }

        $enquiryId = $proposal->user_product_journey_id;
        
        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
        ->first();

        $finsall_ic_data = DB::table('finsall_ic_mapping as fim')
        ->join('master_company as mc', 'mc.company_name', '=', 'fim.ic_name')
        ->where('mc.company_id', $proposal->ic_id)
        ->select('mc.*', 'fim.finsall_ic_name as finsall_ic_name')
        ->first();

        // $validatePaymentResponseService = FinsallController::validatePaymentResponse($request->encryptedResponse, $proposal, $proposal->proposal_no);

        // if($validatePaymentResponseService['status'])
        // {
        //     $paymentStatusService = $validatePaymentResponseService;
        // }
        // else
        // {
        //     $paymentStatusService = FinsallController::paymentStatus($proposal, $proposal->proposal_no);
        // }

        $paymentStatusService = FinsallController::paymentStatus($proposal, $proposal->proposal_no);

        $request->merge(['paymentStatusService' => $paymentStatusService ?? null]);

        if($paymentStatusService['status'])
        {
            PaymentRequestResponse::where('order_id', $request->policyRefNumber)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);

            $request->merge(['txnRefNo' => $paymentStatusService['txnRefNo'] ?? null]);
            $request->merge(['txnDateTime' => $paymentStatusService['txnDateTime'] ?? null]);

            switch($finsall_ic_data->company_alias ?? null)
            {
                case 'reliance':
                    $response = RelianceFinsallController::reliancePaymentCheck($request, $proposal);
                    break;
                case 'tata_aig':
                    $obj = new \App\Http\Controllers\Finsall\TataAigV2FinsallController();
                    $response = $obj->paymentCheck($request, $proposal);
                    break;
                case 'hdfc_ergo':
                    if (config('IC.HDFC_ERGO.V1.CV.ENABLED') == 'Y'){
                        $response = HDFC_ERGO_V1::finsallRehitService($enquiryId, $proposal->proposal_no, $paymentStatusService);
                    } else {
                        $response = hdfcErgoPaymentGateway::finsallRehitService($enquiryId, $proposal->proposal_no, $paymentStatusService);
                    }
                    if (is_object($response)) {
                        $response = json_decode($response->getContent(), true);
                    }
                    break;
                default:
                    $response = [
                        'status' => true,
                        // 'data' => [],
                        'message' => 'IC service not Found for - '.$finsall_ic_data->company_alias
                    ];
                    break;  
            }
        }
        else
        {
            PaymentRequestResponse::where('order_id', $request->policyRefNumber)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            $response = [
                'status' => false,
                'message' => STAGE_NAMES['PAYMENT_FAILED']
            ];
        }

        if($quote_log_data->product_sub_type_id == 1)
        {
            if($paymentStatusService['status'])
            {
                return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
            else{
                return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
        }
        else if($quote_log_data->product_sub_type_id == 2)
        {
            if($paymentStatusService['status'])
            {
                return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
            else{
                return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
        }
        else
        {
            if($paymentStatusService['status'])
            {
                return redirect(config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
            else{
                return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
        }
    }


    static function paymentStatus($proposal, $proposal_no)
    {
        try {
            $enquiryId = $proposal->user_product_journey_id;

            $details = FinsallPolicyDeatail::where([
                'user_product_journey_id' => $enquiryId
            ])
            ->select('mode', 'company_allias')
            ->first();
            //->toArray();

            if ($details)
            {
                $details = $details->toArray();
            }
            else
            {
                return [
                    'status' => false,
                    'message' => 'No data found',
                    'enquiry_id' => $enquiryId,
                    'method_name' => 'paymentStatus'
                ];
            }
            $mode = $details['mode'];
            $companyAlias = $details['company_allias'];
            $configMode = $mode == 'EMI' ? 'emi' : 'full_payment';

            $logged_in_unique_identifier_id = config("constants.finsall.{$companyAlias}.{$configMode}.UNIQUE_IDENTIFIER");
            $logged_in_user_id = config("constants.finsall.{$companyAlias}.{$configMode}.USER_ID");
            $client_id = config("constants.finsall.{$companyAlias}.{$configMode}.CLIENT_ID");
            $client_key = config("constants.finsall.{$companyAlias}.{$configMode}.CLIENT_KEY");

            $version = config('constants.finsall.VERSION');
            $roles = config('constants.finsall.ROLES');

            $paymentStatusRequest = [
                'serviceName' => 'PaymentService',
                'serviceMethod' => 'paymentStatus',

                "policyRefNumber" => $proposal_no,
                
                "loggedInUniqueIdentifierId" => $logged_in_unique_identifier_id,
                "loggedInUserId" => $logged_in_user_id,
                "clientId" => $client_id,
                "clientKey" => $client_key,
                "version" => $version,
                "roles" => $roles
            ];

            $url = config('constants.finsall.FINSALL_SERVICE_URL');

            $get_response = getWsData(
                $url,
                $paymentStatusRequest,
                'finsall',
                [
                    'headers' => [
                        'authentication-token' => config("constants.finsall.{$companyAlias}.{$configMode}.AUTH_TOKEN"),
                        'Content-Type' => 'application/json',
                        'authentication-username' => config("constants.finsall.{$companyAlias}.{$configMode}.AUTH_USERNAME")
                    ],
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'section' => 'finsall',
                    'method' => 'paymentStatus',
                    'transaction_type' => 'proposal',
                ]
            );
            $paymentStatusResponse = $get_response['response'];

            if ($paymentStatusResponse) {
                $paymentStatusResponse = json_decode($paymentStatusResponse);

                if (($paymentStatusResponse->statusCode ?? '') == 'FA_200') {

                    FinsallDataTable::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'method_name' => 'paymentStatus'
                        ],
                        [
                            'status' => true,
                            'message' => 'success',
                            'data' => json_encode($paymentStatusResponse)
                        ]
                    );

                    FinsallTransactionData::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'proposal_no' => $proposal_no,
                        ],
                        [
                            'payment_transaction_status' => 'Transaction Success',
                            'payment_transaction_date' => $paymentStatusResponse->txnDateTime,
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                        ]
                    );

                    FinsallPolicyDeatail::updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiryId,
                        ],
                        [
                            'proposal_no' => $proposal->proposal_no,
                            'is_payment_finsall' => 'Y',
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'message' => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'data' => json_encode($paymentStatusResponse)
                        ]
                    );

                    return [
                        'status' => true,
                        'message' => 'success',
                        'enquiry_id' => $enquiryId,
                        'method_name' => 'paymentStatus',
                        'txnRefNo' => $paymentStatusResponse->txnRefNo ?? null,
                        'txnDateTime' => $paymentStatusResponse->txnDateTime ?? null,
                        'data' => $paymentStatusResponse
                    ];
                } else {

                    FinsallDataTable::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'method_name' => 'paymentStatus'
                        ],
                        [
                            'status' => false,
                            'message' => $paymentStatusResponse->errorMessage ?? "Failure",
                            'data' => json_encode([$paymentStatusResponse])
                        ]
                    );

                    FinsallTransactionData::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'proposal_no' => $proposal_no,
                        ],
                        [
                            'status' => $paymentStatusResponse->errorMessage ?? "Failure",
                        ]
                    );
                    return [
                        'status' => false,
                        'message' => $paymentStatusResponse->errorMessage ?? 'Something went wrong',
                        'enquiry_id' => $enquiryId,
                        'method_name' => 'paymentStatus',
                        // 'data' => [],
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => 'Unable to connect to Finsall',
                    // 'data' => []
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }
    }

    static function validatePaymentResponse($encryptedResponse, $proposal, $proposal_no)
    {
        try {
            $enquiryId = $proposal->user_product_journey_id;

            $encryptedResponse = str_replace(' ', '+', $encryptedResponse);

            $validatePaymentResponseRequest = [
                "data" => $encryptedResponse
            ];
            if(env('APP_ENV') == 'local')
            {
                $validatePaymentResponseRequest['broker'] = config('constants.BROKER');
                $validatePaymentResponseRequest['env'] = 'UAT';
            }

            $additional_data = [
                'headers' => [
                    // 'authentication-token' => config('constants.finsall.FINSALL_DECRYPT_IV'), // IV
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    // 'authentication-username' => config('constants.finsall.FINSALL_DECRYPT_KEY') // KEY
                ],
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'section' => 'finsall',
                'method' => 'Payment Response Decryption',
                'transaction_type' => 'proposal',
            ];
            
            $get_response = getWsData(
                config('constants.finsall.FINSALL_DECRYPT_URL'),
                $validatePaymentResponseRequest,
                'finsall',
                $additional_data
            );

            $validatePaymentResponseResponse = $get_response['response'];

            if ($validatePaymentResponseResponse) {
                $validatePaymentResponseResponse = json_decode($validatePaymentResponseResponse);

                if (!isset($validatePaymentResponseResponse->errorMessage) && (($validatePaymentResponseResponse->txnStatus ?? '') == 'S')  ) {

                    FinsallDataTable::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'method_name' => 'validatePaymentResponse'
                        ],
                        [
                            'status' => true,
                            'message' => 'success',
                            'data' => json_encode($validatePaymentResponseResponse)
                        ]
                    );

                    FinsallTransactionData::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'proposal_no' => $proposal_no,
                        ],
                        [
                            'payment_transaction_status' => 'Transaction Success',
                            'payment_transaction_date' => $validatePaymentResponseResponse->txnDateTime,
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                        ]
                    );

                    FinsallPolicyDeatail::updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiryId,
                        ],
                        [
                            'proposal_no' => $proposal->proposal_no,
                            'is_payment_finsall' => 'Y',
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'message' => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'data' => json_encode($validatePaymentResponseResponse)
                        ]
                    );

                    return [
                        'status' => true,
                        'message' => 'success',
                        'enquiry_id' => $enquiryId,
                        'method_name' => 'validatePaymentResponse',
                        'txnRefNo' => $validatePaymentResponseResponse->txnRefNo ?? null,
                        'txnDateTime' => $validatePaymentResponseResponse->txnDateTime ?? null,
                        'data' => $validatePaymentResponseResponse
                    ];
                } else {
                    $errorMessage = ($validatePaymentResponseResponse->errorMessage ?? ( $validatePaymentResponseResponse->txnDescription ?? "Failure"));

                    FinsallDataTable::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'method_name' => 'validatePaymentResponse'
                        ],
                        [
                            'status' => false,
                            'message' => $errorMessage,
                            'data' => json_encode([$validatePaymentResponseResponse])
                        ]
                    );

                    FinsallTransactionData::updateOrCreate(
                        [
                            'enquiry_id' => $enquiryId,
                            'proposal_no' => $proposal_no,
                        ],
                        [
                            'status' => $errorMessage,
                        ]
                    );
                    return [
                        'status' => false,
                        'message' => $errorMessage,
                        'enquiry_id' => $enquiryId,
                        'method_name' => 'validatePaymentResponse',
                        // 'data' => [],
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => 'Unable to connect to Finsall',
                    // 'data' => []
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }
    }

    function checkFinsallAvailability ($company_alias, $section, $premium_type, $proposal)
    {
        if(config('constants.finsall.IS_FINSALL_ACTIVATED') == 'Y')
        {
            $finsall_config = config ('constants.finsall.FINSALL_ALLOWED_PRODUCTS');
            $finsall_config = json_decode($finsall_config,1);

            $is_finsall_active_for_product = $finsall_config[$company_alias][$section][$premium_type]['enable'] ?? 'N';

            if(($is_finsall_active_for_product == "Y"))
            {
                $finsallAvailability = 'Y';
            }
            else
            {
                $finsallAvailability = 'N';
            }
            UserProposal::updateOrCreate([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'user_proposal_id' => $proposal->user_proposal_id
            ],
            [
                'is_finsall_available' => $finsallAvailability
            ]);
        }
    }

    public static function finsallConfig($company_alias, $product_sub_type_id, $premium_type)
    {
        if (
            config('constants.finsall.IS_FINSALL_ACTIVATED') == 'Y' &&
            !empty($company_alias) && !empty($product_sub_type_id) && !empty($premium_type)
        ) {
            $section_list = [
                '1' => 'car',
                '2' => 'bike',
            ];

            $section = $section_list[$product_sub_type_id] ?? 'cv';
            $finsall_config = config('constants.finsall.FINSALL_ALLOWED_PRODUCTS');
            $finsall_config = json_decode($finsall_config, true);
            $is_finsall_active_for_product = $finsall_config[$company_alias][$section][$premium_type]['enable'] ?? 'N';
            if ($is_finsall_active_for_product == 'Y') {
                $modes = $finsall_config[$company_alias][$section][$premium_type]['mode'] ?? [];
                return array_keys(array_filter($modes, function($value) {
                    return $value === 'Y';
                }));
            }
        }

        return null;
    }
}
