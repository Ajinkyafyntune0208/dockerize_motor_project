<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProposal;
use App\Models\MasterPolicy;
use Illuminate\Support\Facades\DB;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\Storage;
use App\Models\PolicyDetails;

class kotakPaymentGateway extends Controller
{
    public static function make($request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $key = config('constants.IcConstants.kotak.CHECKSUM_KEY_KOTAK_BIKE');
        $txnid = bin2hex(random_bytes(25));
        $amount = $user_proposal->final_payable_amount;
        $productinfo = 'Kotak Bike Insurance '. $user_proposal->product_type;
        $firstname = $user_proposal->first_name;
        $email = $user_proposal->email;
        $salt = config('constants.IcConstants.kotak.BIKE_SALT_KOTAK');
        $udf1 = $user_proposal->unique_proposal_id;

        $hash_string = "$key|$txnid|$amount|$productinfo|$firstname|$email|$udf1||||||||||$salt";

        $hash = hash('sha512', $hash_string);

        $return_data = [
            'form_action' => config('constants.IcConstants.kotak.PAYMENT_GATEWAY_LINK_KOTAK_BIKE'),
            'form_method' => "post",
            'payment_type' => 0,
            'form_data' => [
                'firstname' => $user_proposal->first_name,
                'lastname' => $user_proposal->last_name,
                'surl' => route('bike.payment-confirm', ['kotak','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),
                'phone' => $user_proposal->mobile_number,
                'key' => config('constants.IcConstants.kotak.CHECKSUM_KEY_KOTAK_BIKE'),
                'hash' => $hash,
                'curl' => route('bike.payment-confirm', ['kotak','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),
                'furl' => route('bike.payment-confirm', ['kotak','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),
                'txnid' => $txnid,
                'productinfo' => $productinfo,
                'amount' => $amount,
                'email' => $user_proposal->email,
                'udf1' => $user_proposal->unique_proposal_id,
            ]
        ];

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
                                    ->pluck('insurance_company_id')
                                    ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                                    ->pluck('quote_id')
                                    ->first();

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $enquiryId)
        ->update(['active' => 0]);

        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            'payment_url'               => config('constants.IcConstants.kotak.PAYMENT_GATEWAY_LINK_KOTAK_BIKE'),
            'return_url'                => route('bike.payment-confirm', ['kotak']),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);

    } //EO make

    public static function confirm($request,$rehitdata = '')
    {
        if(!empty($rehitdata)) {
            $pg_return_data = $request_data = $rehitdata;
        } else {
            $pg_return_data = $request_data = $request->all();
        }
        unset($pg_return_data['enquiry_id'],$pg_return_data['policy_id']);
        $enquiryId = $request_data['enquiry_id'];
        $productData = getProductDataByIc($request_data['policy_id']);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $stage_data = [];
        if(isset($request_data['status']) && $request_data['status'] == 'success') 
        {
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($stage_data);
            if(empty($rehitdata)) {
                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $enquiryId)
                    ->where('active',1)
                    ->update([
                        'status'   => STAGE_NAMES['PAYMENT_SUCCESS'] ,
                        'response' => json_encode($pg_return_data),
                    ]);
            }

            $tokenData = getKotakTokendetails('bike');

            $token_req_array = [
                'vLoginEmailId' => $tokenData['vLoginEmailId'],
                'vPassword' => $tokenData['vPassword'],
            ];

            $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE'), $token_req_array, 'kotak', [
                'Key' => $tokenData['vRanKey'],
                'headers' => [
                    'vRanKey' => $tokenData['vRanKey']
                ],
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'kotak',
                'section' => $productData->product_sub_type_code,
                'method' =>'Token Generation',
                'transaction_type' => 'proposal',
            ]);
            $data = $get_response['response'];
            if ($data) {
                $token_response = json_decode($data, true);
                if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                    try {
                        if ($user_proposal['vehicale_registration_number'] == 'NEW') {
                            $reg_no = explode("-", $user_proposal['rto_location']);
                            $reg_no[2] = '';
                            $reg_no[3] = '';
                        } else {
                            $reg_no = explode("-", $user_proposal['vehicale_registration_number']);
                        }

                        $org_type = $user_proposal['owner_type'];
                        if($user_proposal['is_vehicle_finance'] == '1'){
                            $Financing_Institution_Name = DB::table('kotak_financier_master')
                            ->where('code', $user_proposal['name_of_financer'])
                            ->pluck('name')
                            ->first();
                        }
                        
                        $proposal_payment_req = [
                            /* "objclsPartnerTwoWheelerSaveProposal" => [ */
                                /* "objTwoWheelerSaveProposalRequest" => [
                                    "vUserLoginId" => config('constants.IcConstants.kotak.KOTAK_BIKE_USERID'),
                                    "vWorkFlowID" =>$user_proposal['customer_id'],#in customer_id workflowid is saved 
                                    "vQuoteID" => $user_proposal['unique_proposal_id'],
                                    "objCustomerDetails" => [
                                        "vCustomerId" => "",
                                        "vCustomerType" => $org_type,
                                        "vIDProof" => "0",
                                        "vIDProofDetails" => "",
                                        "vCustomerFirstName" => ($org_type == 'I' ? $user_proposal['first_name'] : ''),
                                        "vCustomerMiddleName" => "",
                                        "vCustomerLastName" => ($org_type == 'I' ? $user_proposal['last_name'] : ''),
                                        "vCustomerEmail" => ($org_type == 'I' ? $user_proposal['email'] : ''),
                                        "vCustomerMobile" => ($org_type == 'I' ? $user_proposal['mobile_number'] : ''),
                                        "vCustomerDOB" => ($org_type == 'I' ? date('d/m/Y', strtotime($user_proposal['dob'])) : ''),
                                        "vCustomerSalutation" => ($org_type == 'I' ? ($user_proposal['gender'] == 'F' ? ($user_proposal['marital_status'] == 'Single' ? 'MISS' : 'MRS') : 'MR') : ''),
                                        "vCustomerGender" => ($org_type == 'I' ? ($user_proposal['gender'] == 'F' ? 'FEMALE' : 'MALE') : ''),
                                        "vOccupationCode" => "1",
                                        "vCustomerPanNumber" => ($org_type == 'I' ? $user_proposal['pan_number'] : ''),
                                        "vMaritalStatus" => ($org_type == 'I' ? $user_proposal['marital_status']  : ''),
                                        "vCustomerPincode" => ($org_type == 'I' ? $user_proposal['pincode'] : ''),
                                        //"vCustomerPincodeLocality" => "",
                                        //"vCustomerStateCd" => "",
                                        //"vCustomerStateName" => "",
                                        //"vCustomerCityDistrict" => "",
                                        //"vCustomerCityDistrictCd" => "",
                                        //"vCustomerCity" => "",
                                        //"vCustomerCityCd" => "",
                                        "vCustomerAddressLine1" => ($org_type == 'I' ?  $user_proposal['address_line1'] : ''),
                                        "vCustomerAddressLine2" => ($org_type == 'I' ? $user_proposal['address_line2'] : ''),
                                        "vCustomerAddressLine3" => ($org_type == 'I' ? $user_proposal['address_line3'] : ''),
                                        #"vOrganizationName" => ($org_type != 'I' ? $user_proposal['last_name'] : ''),
                                        #"vOrganizationContactName" => ($org_type != 'I' ? $user_proposal['first_name'] : ''),
                                        #"vOrganizationEmail" => ($org_type != 'I' ? $user_proposal['email'] : ''),
                                        #"vOrganizationMobile" => ($org_type != 'I' ? $user_proposal['mobile_number'] : ''),
                                        #"vOrganizationPincode" => ($org_type != 'I' ? $user_proposal['pincode'] : ''),
                                        // "vOrganizationCity" => ($org_type != 'I' ? $user_det['Corres_Address1'] : ''),
                                        // "vOrganizationCityCd" => ($org_type != 'I' ? $user_det['Corres_Address1'] : ''),
                                        // "vOrganizationStateCd" => ($org_type != 'I' ? $user_det['Corres_Address1'] : ''),
                                        // "vOrganizationStateName" => ($org_type != 'I' ? $user_det['Corres_Address1'] : ''),
                                        // "vOrganizationCityDistrict" => ($org_type != 'I' ? $user_det['Corres_Address1'] : ''),
                                        // "vOrganizationCityDistrictCd" => ($org_type != 'I' ? $user_det['Corres_Address1'] : ''),
                                        #// "vOrganizationTANNumber" => ($org_type != 'I' ? $user_det['Corres_Address1'] : ''),
                                        #"vOrganizationGSTNumber" => ($org_type != 'I' ? $user_proposal['gst_number'] : ''),
                                        #"vOrganizationAddressLine1" => ($org_type != 'I' ? $user_proposal['address_line1'] : ''),
                                        "vOrganizationAddressLine2" => ($org_type != 'I' ? $user_proposal['address_line2'] : ''),
                                        "vOrganizationAddressLine3" => ($org_type != 'I' ? $user_proposal['address_line3'] : ''),
                                        "vCustomerCRNNumber" => "",
                                    ],
                                    "vNomineeName" => ($org_type == 'I'  ? $user_proposal['nominee_name'] : ''),
                                    "vNomineeDOB" => ($org_type == 'I'  ? date('d/m/Y', strtotime($user_proposal['nominee_dob'])) : ''),
                                    "vNomineeRelationship" => ($org_type == 'I' ? $user_proposal['nominee_relationship'] : ''),
                                    "vNomineeAppointeeName" => "",
                                    "vNomineeAppointeeRelationship" => "",
                                    "vRMCode" => "",
                                    "vBranchInwardNumber" => "",
                                    "dBranchInwardDate" => date('d/m/Y'),
                                    "vCustomerCRNNumber" => "",
                                    "bIsVehicleFinanced" => $user_proposal['is_vehicle_finance'] == '1' ? 'true' : 'false', //false ????????????
                                    "vFinancierAddress" => $user_proposal['is_vehicle_finance'] == '1' ? $user_proposal['financer_location'] : '',
                                    "vFinancierAgreementType" => $user_proposal['is_vehicle_finance'] == '1' ? 'Hypothecation' : '',
                                    "vFinancierCode" => $user_proposal['is_vehicle_finance'] == '1' ? $user_proposal['name_of_financer'] : '',
                                    "vFinancierName" => $user_proposal['is_vehicle_finance'] == '1' ? $Financing_Institution_Name : '', # fetch name from code
                                    "vRegistrationNumber1" => $reg_no['0'],
                                    "vRegistrationNumber2" => $reg_no['1'],
                                    "vRegistrationNumber3" => $reg_no['2'],
                                    "vRegistrationNumber4" => $reg_no['3'],
                                    "vEngineNumber" => $user_proposal['engine_number'],
                                    "vChassisNumber" => $user_proposal['chassis_number'],
                                    "vPrevInsurerCode" => $user_proposal['previous_insurance_company'],
                                    "vPrevInsurerExpiringPolicyNumber" => $user_proposal['previous_policy_number'],
                                    "vPreInspectionNumber" => "",
                                ], */
                                "objParaPaymentDetails" => [
                                    "vCdAccountNumber" => "",
                                    "vWorkFlowId" => $user_proposal['customer_id'],#in customer_id workflowid is saved,
                                    "vQuoteId" => $user_proposal['unique_proposal_id'],
                                    "vProposalId" => "",
                                    "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_BIKE_INTERMEDIARY_CODE'),
                                    "vCustomerId" => "",
                                    "vPaymentNumber" =>  $request_data['mihpayid'],
                                    "nPremiumAmount" => $user_proposal['total_premium'],
                                    "vTransactionFlag" => "BPOS",
                                    "vLoggedInUser" => config('constants.IcConstants.kotak.KOTAK_BIKE_USERID'),
                                    "vProductInfo" => "Two Wheeler Comprehensive",
                                    "vPaymentModeCode" => "PA",
                                    "vPaymentModeDescription" => "PAYMENT AGGREGATOR",
                                    "vPayerType" => "1",
                                    "vPayerCode" => "",
                                    "vPayerName" => "",
                                    "vApplicationNumber" => "",
                                    "vBranchName" => "",
                                    "vBankCode" => "0",
                                    "vBankName" => "",
                                    "vIFSCCode" => "",
                                    "vBankAccountNo" => $request_data['bank_ref_num'],
                                    "vHouseBankBranchCode" => "14851091",
                                    "vInstrumentNo" => $request_data['mihpayid'],
                                    "vCustomerName" => $user_proposal['first_name'] . " " . $user_proposal['last_name'],
                                    "vHouseBankId" => "",
                                    //"vInstrumentDate" => date('d/m/Y'),
                                    "vInstrumentDate" => date('d/m/Y', strtotime($request_data['addedon'])),
                                    "vInstrumentAmount" => $user_proposal['total_premium'],
                                    "vPaymentLinkStatus" => "",
                                    "vPaymentEntryId" => "",
                                    "vPaymentAllocationId" => "",
                                    "vPolicyNumber" => "",
                                    "vPolicyStartDate" => "",
                                    "vProposalDate" => "",
                                    "vCustomerFullName" => "",
                                    "vIntermediaryName" => "",
                                    "vCustomerEmailId" => "",
                                    "nCustomerMobileNumber " => "",
                                    "vErrorMsg  " => "",
                                ],
                           /*  ], */
                        ];
                       
                        /* if ($org_type == 'C') {
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationName'] = $user_proposal['first_name'];
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationContactName'] = $user_proposal['last_name'];
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationEmail'] = $user_proposal['email'];
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationMobile'] = $user_proposal['mobile_number'];
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationPincode'] = $user_proposal['pincode'];
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationAddressLine1'] = $user_proposal['address_line1'] . '' . $user_proposal['address_line2'] . '' . $user_proposal['address_line3'];
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationTANNumber'] = '';
                            $proposal_payment_req['objclsPartnerTwoWheelerSaveProposal']['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationGSTNumber'] = $user_proposal['gst_number'];;
                        } */

                        $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_BIKE_PROPOSAL_PAYMENT'),$proposal_payment_req,'kotak',
                            [
                                'token' => $token_response['vTokenCode'],
                                'headers' => [
                                    'vTokenCode' => $token_response['vTokenCode']
                                ],
                                'enquiryId' => $enquiryId,
                                'requestMethod' =>'post',
                                'productName'  => $productData->product_name,
                                'company'  => 'kotak',
                                'section' => $productData->product_sub_type_code,
                                'method' =>'Proposal Payment',
                                'transaction_type' => 'proposal',
                            ]);
                        $data = $get_response['response'];

                        if ($data) {
                            $proposal_payment_resp = json_decode($data, true);
                            if (isset($proposal_payment_resp['Fn_Save_Partner_Two_Wheeler_PaymentResult'])) {
                                $proposal_payment_resp = $proposal_payment_resp['Fn_Save_Partner_Two_Wheeler_PaymentResult'];
                            }
                            // if(!empty($rehitdata)) { #if hit second time in response vErrorMessage is vacant
                            //     $proposal_payment_resp['vErrorMessage'] = (!empty($proposal_payment_resp['vPolicyNumber']) && !empty($proposal_payment_resp['vProposalNumber'])) ? 'Success' : '';
                            // }

                            if (isset($proposal_payment_resp['vProposalNumber']) && isset($proposal_payment_resp['vPolicyNumber']) && isset($proposal_payment_resp['vErrorMessage']) && $proposal_payment_resp['vErrorMessage'] == '') {
                                $policyNo = $proposal_payment_resp['vPolicyNumber'];
                                $proposalNo = $proposal_payment_resp['vProposalNumber'];
                                $prop_status = $proposal_payment_resp['vErrorMessage'];
                                $product_code = $proposal_payment_resp['vProductCode'];

                                UserProposal::where('user_proposal_id' , $user_proposal['user_proposal_id'])
                                    ->update([
                                        'proposal_no'    => $proposalNo,
                                        'policy_no'      => $policyNo,
                                        'product_code'   => $product_code,
                                    ]);
                                PolicyDetails::updateOrCreate(
                                    ['proposal_id' => $user_proposal['user_proposal_id']],
                                    [
                                        'policy_number' => $policyNo,
                                        'idv' => $user_proposal['idv'] ,
                                        'policy_start_date' => $user_proposal['policy_start_date'] ,
                                        'ncb' => $user_proposal['ncb_discount'] ,
                                        'premium' => $user_proposal['final_payable_amount'] ,
                                        'status' => 'SUCCESS'
                                    ]
                                );


                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal['user_product_journey_id'],
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]);
                                $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                                                ->pluck('quote_id')
                                                ->first();

                                DB::table('payment_request_response')
                                    ->where('user_product_journey_id', $enquiryId)
                                    ->where('active',1)
                                    ->update([
                                        'status'   => STAGE_NAMES['PAYMENT_SUCCESS'],
                                        'order_id' => $proposalNo,
                                        'proposal_no' => $proposalNo,
                                    ]);
                                    
                                $tokenData = getKotakTokendetails('bike');
                                $token_req_array = [
                                    'vLoginEmailId' => $tokenData['vLoginEmailId'],
                                    'vPassword' => $tokenData['vPassword'],
                                ];

                                $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE'), $token_req_array, 'kotak', [
                                    'Key' => $tokenData['vRanKey'],
                                    'headers' => [
                                        'vRanKey' => $tokenData['vRanKey']
                                    ],
                                    'enquiryId' => $enquiryId,
                                    'requestMethod' =>'post',
                                    'productName'  => $productData->product_name,
                                    'company'  => 'kotak',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'Token Generation',
                                    'transaction_type' => 'proposal',
                                ]);
                                $data = $get_response['response'];

                                if ($data) {
                                    if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {

                                        $pdf_generate_url = config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_BIKE_PDF') . '' . $proposalNo . '/' . $policyNo . '/' . $product_code . '/' . config('constants.IcConstants.kotak.KOTAK_BIKE_USERID');
                                        $additional_data = [
                                            'TokenCode' => $token_response['vTokenCode'],
                                            'headers' => [
                                                'vTokenCode' => $token_response['vTokenCode']
                                            ],
                                            'requestMethod' => 'get',
                                            'enquiryId' => $enquiryId,
                                            'method' => 'PDF Generation',
                                            'section' => 'BIKE',
                                            'transaction_type' => 'proposal',
                                            'productName'  => $productData->product_name,
                                            'request_method' => 'get',
                                        ];

                                        $get_response = getWsData($pdf_generate_url, '', 'kotak', $additional_data);
                                        $pdf_generation_result = $get_response['response'];

                                        if (!empty($pdf_generation_result) && checkValidPDFData(base64_decode($pdf_generation_result))) {
                                            $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'kotak/'. md5($user_proposal['user_proposal_id']). '.pdf';
                                            Storage::put($pdf_name, base64_decode($pdf_generation_result));
                                            PolicyDetails::updateOrCreate(
                                                ['proposal_id' => $user_proposal['user_proposal_id']],
                                                [
                                                    'ic_pdf_url' => $pdf_generate_url,
                                                    'pdf_url' => $pdf_name
                                                ]
                                            );

                                            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                            $stage_data['ic_id'] = $user_proposal['ic_id'];
                                            $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                                            updateJourneyStage($stage_data);
                                            if(!empty($rehitdata)) {
                                                return [
                                                    'status' => 'true',
                                                    'msg' => STAGE_NAMES['POLICY_ISSUED']
                                                ];
                                            } else {
                                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'BIKE', 'SUCCESS'));
                                            }
                                        } else {
                                            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                            $stage_data['ic_id'] = $user_proposal['ic_id'];
                                            $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                            updateJourneyStage($stage_data);
                                            if(!empty($rehitdata)) {
                                                return [
                                                    'status' => false,
                                                    'msg' => 'Pdf generation service not working'
                                                ];
                                            } else {
                                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'BIKE', 'SUCCESS'));
                                            }
                                        }

                                    } else {
                                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                        updateJourneyStage($stage_data);
                                        if(!empty($rehitdata)) {
                                            return [
                                                'status' => false,
                                                'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                            ];
                                        } else {
                                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'BIKE', 'SUCCESS'));
                                        }
                                    }

                                } else {
                                    $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                    $stage_data['ic_id'] = $user_proposal['ic_id'];
                                    $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                    updateJourneyStage($stage_data);
                                    if(!empty($rehitdata)) {
                                        return [
                                            'status' => false,
                                            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                        ];
                                    } else {
                                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'BIKE', 'SUCCESS'));
                                    }
                                }
                                    

                            } else {
                                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                $stage_data['ic_id'] = $user_proposal['ic_id'];
                                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                updateJourneyStage($stage_data);
                                if(!empty($rehitdata)) {
                                    return [
                                        'status' => false,
                                        'msg' => $proposal_payment_resp['vErrorMessage']
                                    ];
                                } else {
                                    /* return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)])); */
                                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                                }
                            }   
                        } else {
                            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                            $stage_data['ic_id'] = $user_proposal['ic_id'];
                            $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                            updateJourneyStage($stage_data);
                            if(!empty($rehitdata)) {
                                return [
                                    'status' => false,
                                    'msg' => 'Proposal Payment service not working'
                                ];
                            } else {
                                /* return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)])); */
                                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                            }
                        }

                    } catch (\Exception $e) {
                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                        updateJourneyStage($stage_data);
                        if(!empty($rehitdata)) {
                            return [
                                'status' => false,
                                'msg' => $e->getMessage()
                            ];
                        } else {
                            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
                        }

                    }

                } else {
                    $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                    $stage_data['ic_id'] = $user_proposal['ic_id'];
                    $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($stage_data);
                    if(!empty($rehitdata)) {
                        return [
                            'status' => false,
                            'msg' => 'token generation service not working'
                        ];
                    } else {
                        /* return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)])); */
                        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                    }
                }

            } else {
                $data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $data['ic_id'] = $user_proposal['ic_id'];
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);
                if(!empty($rehitdata)) {
                    return [
                        'status' => false,
                        'msg' => 'token generation service not working'
                    ];
                } else {
                    /* return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)])); */
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                }

            }
        } else {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('active',1)
                ->update([
                    'status'   => STAGE_NAMES['PAYMENT_FAILED'] ,
                    'response' => json_encode($pg_return_data),
                    ]);
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($stage_data);

            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
        }
    } //EO confirm

    public static function generatePdf($request)
    {
        $request_data = $request->all();
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $productData = getProductDataByIc($request_data['master_policy_id']);
        $stage_data = [];
        if($user_proposal['policy_no'] != NULL) {
            ##pdf generation
            $tokenData = getKotakTokendetails('bike');
            $token_req_array = [
                'vLoginEmailId' => $tokenData['vLoginEmailId'],
                'vPassword' => $tokenData['vPassword'],
            ];

            $get_response = getWsData(
                config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE'),
                $token_req_array,
                'kotak',
                [
                    'Key' => $tokenData['vRanKey'],
                    'headers' => [
                        'vRanKey' => $tokenData['vRankey']
                    ],
                    'enquiryId' =>  $user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'kotak',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Token Generation',
                    'transaction_type' => 'rehit',
                ]
            );

            $data = $get_response['response'];
            if ($data) {
                $token_response = json_decode($data, true);
                if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                    $pdf_generate_url = config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_BIKE_PDF') . '' . $user_proposal['proposal_no'] . '/' . $user_proposal['policy_no'] . '/' . $user_proposal['product_code'] . '/' . config('constants.IcConstants.kotak.KOTAK_BIKE_USERID');
                    $additional_data = [
                        'TokenCode' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'requestMethod' => 'get',
                        'enquiryId' => $user_product_journey_id,
                        'method' => 'PDF Generation',
                        'section' => 'BIKE',
                        'transaction_type' => 'rehit',
                        'productName'  => $productData->product_name,
                        'request_method' => 'get',
                    ];

                    $get_response = getWsData($pdf_generate_url, '', 'kotak', $additional_data);
                    $pdf_generation_result = $get_response['response'];

                    if (!empty($pdf_generation_result) && checkValidPDFData(base64_decode($pdf_generation_result))) {
                        $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'kotak/'. md5($user_proposal['user_proposal_id']). '.pdf';
                        Storage::put($pdf_name, base64_decode($pdf_generation_result));
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $user_proposal['user_proposal_id']],
                            [
                                'policy_number' => $user_proposal['policy_no'],
                                'ic_pdf_url' => $pdf_generate_url,
                                'pdf_url' => $pdf_name,
                                'idv' => $user_proposal['idv'] ,
                                'policy_start_date' => $user_proposal['policy_start_date'] ,
                                'ncb' => $user_proposal['ncb_discount'] ,
                                'premium' => $user_proposal['final_payable_amount'] ,
                                'status' => 'SUCCESS'
                            ]
                        );

                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                        updateJourneyStage($stage_data);
                        return [
                            'status' => true,
                            'msg' => STAGE_NAMES['POLICY_PDF_GENERATED'],
                            'data' => [
                                'policy_number' => $user_proposal['policy_no'],
                                'pdf_link' => file_url($pdf_name)
                            ]
                        ];

                    } else {
                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                        updateJourneyStage($stage_data);
                        return [
                            'status' => false,
                            'msg' => 'pdf service service not working'
                        ];
                    }
                } else {
                    $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                    $stage_data['ic_id'] = $user_proposal['ic_id'];
                    $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($stage_data);
                    return [
                        'status' => false,
                        'msg' => 'token generation service not working'
                    ];
                }

            } else {
                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);
                return [
                    'status' => false,
                    'msg' => 'token generation service not working'
                ];
            }

        } else {
            ##need to run save proposal payment and pdf generation service

            $paymentLog =  DB::table('payment_request_response')
                                ->where('user_product_journey_id', $user_product_journey_id)
                                ->where('active',1)
                                ->first();

            if($paymentLog->status == STAGE_NAMES['PAYMENT_SUCCESS']) {
                $paymentResponse = json_decode($paymentLog->response,true);
                $paymentResponse['enquiry_id'] = $user_product_journey_id;
                $paymentResponse['policy_id']  = $request_data['master_policy_id'];
                $paymentResponse['isRehit']    = 'true';
                if(isset($paymentResponse['mihpayid']))
                {
                    $rehit = self::confirm('',(array)$paymentResponse);
                    return $rehit;
                }else
                {
                    return [
                        'status' => false,
                        'msg' => 'payment response is not proper for this case please confirm'
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'msg' => 'payment not done for this case please confirm'
                ];
            }
        }
    } //EO generatePdf
}

