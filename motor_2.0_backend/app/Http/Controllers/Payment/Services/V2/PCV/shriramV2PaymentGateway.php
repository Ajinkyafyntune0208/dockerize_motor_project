<?php

namespace App\Http\Controllers\Payment\Services\V2\PCV;

use Carbon\Carbon;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use App\Models\CvBreakinStatus;
use Hamcrest\Type\IsObject;
use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\SyncPremiumDetail\Services\ShriramPremiumDetailController;
use App\Models\CvAgentMapping;

use function Composer\Autoload\includeFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';


class shriramV2PaymentGateway
{

    public static function  make($request)
    {
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();
        $quote = QuoteLog::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $quote_data = json_decode($quote->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $quote_data['version_id'], 'shriram');
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return response()->json([
                'premium_amount' => '0',
                'status' => false,
                'message' => $mmv['message'],
            ]);
        }
        if (in_array($productData->premium_type_code, ['breakin', 'own_damage_breakin']) && !(in_array($productData->premium_type_code, ['third_party', 'third_party_breakin']))) {
            $breakindetails = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->first();
            if (isset($breakindetails->breakin_status) && ($breakindetails->breakin_status === 'Inspection Approved')) {
                $response = self::postinspectionsubmit($user_proposal, $productData, $request);
                if (($response->original['status'] ?? false) != true) {
                    return response()->json([
                        'status' => false,
                        'msg' => $response->original['msg'] ?? 'Something went wrong'
                    ]);
                }
            }
        }
        $user_proposal->refresh();
        $check_payment = self::check_payment_status_json($user_proposal->user_product_journey_id, $user_proposal->proposal_no, $user_proposal->pol_sys_id, $user_proposal->user_proposal_id);
        
        $check_payment_response = $check_payment->original;
        $ic_version_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $service_type = config('constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE');
        
        $b2b_seller_type = CvAgentMapping::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->first();

        if (!$check_payment_response['status']) {
            $return_url = route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id, 'EnquiryId' => customEncrypt($user_proposal->user_product_journey_id)]);
            $return_data = [
                'form_action' => config('constants.motor.shriram.PAYMENT_URL_JSON'),
                'form_method' => config('constants.IcConstants.shriram.SHRIRAM_PAYMENT_BEFORE_URL_METHOD'),
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'DbFrom' => 'NOVA',
                    'QuoteId' => $user_proposal->pol_sys_id,
                    'amount' => $user_proposal->final_payable_amount,
                    'application' => config('SHRIRAM_GCV_APPLICATION_NAME'), //"HeroIns",//config('constants.brokerName'),
                    'createdBy' => config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME'), //"NiveshIns",//config('constants.IcConstants.shriram.SHRIRAMGCV_USERNAME'),
                    'description' => $user_proposal->proposal_no . '-' . $user_proposal->pol_sys_id,
                    'isForWeb' => 'true',
                    'paymentFrom' => config('constants.IcConstants.shriram.SHRIRAM_CV_PAYMENT_TYPE'),
                    'PolicySysID' => $user_proposal->pol_sys_id,
                    'ReturnURL' => $return_url, //route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                    'prodCode' =>   $user_proposal->product_code,
                    'prodName' => 'MOTOR',
                    'return_url' => $return_url, //route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                    'sourceUrl' => $return_url, //route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                ],
            ];

            //b2b seller type headers and application name segregation
            if(!empty($b2b_seller_type) && !empty($b2b_seller_type->seller_type) && config('IS_SEGREGATION_ALLOWED_FOR_IC_CREDENTIALS_FOR_CV') == 'Y'){
                switch($b2b_seller_type->seller_type){
                    case 'P':
                        $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_POS');
                        $return_data['form_data']['application'] = config('SHRIRAM_GCV_APPLICATION_NAME_FOR_POS');
                    break;

                    case 'E':
                        $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_EMPLOYEE');
                        $return_data['form_data']['application'] = config('SHRIRAM_GCV_APPLICATION_NAME_FOR_EMPLOYEE');
                    break;

                    case 'MISP':
                        $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_MISP');
                        $return_data['form_data']['application'] = config('SHRIRAM_GCV_APPLICATION_NAME_FOR_MISP');
                    break;

                    default:
                    $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME');
                    $return_data['form_data']['application'] = config('SHRIRAM_GCV_APPLICATION_NAME');
                    break;
                }
            }

            PaymentRequestResponse::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))
                ->update(['active' => 0]);

            PaymentRequestResponse::insert([
                'quote_id' => $quote->quote_id,
                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                'user_proposal_id' => $user_proposal->user_proposal_id,
                'ic_id' => $user_proposal->ic_id,
                'order_id' => $user_proposal->proposal_no,
                'amount' => $user_proposal->final_payable_amount,
                'proposal_no' => $user_proposal->proposal_no,
                'payment_url' => $return_data['form_action'],
                'return_url' => $return_url, //route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'active' => 1,
                'xml_data' => json_encode($return_data)
            ]);

            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = 'Payment Initiated';
            updateJourneyStage($data);
            return response()->json([
                'status' => true,
                'msg' => "Payment Redirectional",
                'data' => $return_data,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => "Payment is done for the given policy number",
            ]);
        }
    }

    public static function confirm($request)
    {
        $user_proposal = UserProposal::where('proposal_no',$request->ProposalNumber)->first();
        if (!$user_proposal) {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }

        if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $user_proposal->user_product_journey_id)) {
            \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/' . $user_proposal->user_product_journey_id);
        }
       
        DB::table('payment_request_response')
            ->where('order_id', $request->ProposalNumber)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => !in_array($request->ResponseCode,["Aborted","Failure"]) ? 'PAYMENT SUCCESS' : 'PAYMENT FAILURE',
                'proposal_no' => $user_proposal->proposal_no,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        
        if (in_array($request->ResponseCode,["Aborted","Failure"])) {
            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        } else {
            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($data);
        }
        $user_proposal_id = $user_proposal->user_proposal_id;
        $user_product_journey_id = $user_proposal->user_product_journey_id;
        $ProposalNo = $request->ProposalNumber;
        $response = self::check_payment_status_json($user_product_journey_id, $ProposalNo, $user_proposal->pol_sys_id, $user_proposal_id);
        
        if ($response['status'] == 'true') {
            
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal->user_proposal_id],
                [
                    'policy_number' => $response['policy_number'],
                    'policy_start_date' => $user_proposal->policy_start_date,
                    'premium' => $user_proposal->final_payable_amount,
                    'created_on' => date('Y-m-d H:i:s'),
                ]
            );
            UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update(['policy_no' => $response['policy_number']]);

            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
            updateJourneyStage($data);
            //Generate Policy PDF - Start
            $user_journey_data = UserProductJourney::find($user_proposal->user_product_journey_id);
            $product_parent =  strtolower(get_parent_code($user_journey_data->product_sub_type_id));
            $folder = ($product_parent == 'gcv') ? config('constants.motorConstant.GCV_PROPOSAL_PDF_URL') : config('constants.motorConstant.CV_PROPOSAL_PDF_URL');
            $pdf_name = $folder . 'shriram/' . md5($request->user_proposal_id) . '.pdf';
            $policy_pdf = false;
            if (($response['pdf_link'])) {
                $doc_link = config('constants.motor.shriram.SHRIRAM_MOTOR_GENERATE_PDF') . 'PolSysId=' . $user_proposal->pol_sys_id . '&LogoYN=Y';

                try {
                    $pdf_data = httpRequestNormal($response['pdf_link'], 'GET', [], [], [], [], false);
                    $policy_pdf = false;

                    if ($pdf_data && $pdf_data['status'] == 200 && isset($pdf_data['response'])) {
                        $policy_pdf = Storage::put($pdf_name, $pdf_data['response']);
                        $pdf_url = file_url($pdf_name);
                    }
                } catch (\Throwable $th) {
                    $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                    $data['ic_id'] = $user_proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($data);
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                }
            } 
            
            if ($policy_pdf) {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'ic_pdf_url' => $response['pdf_link'],
                        'pdf_url' => $pdf_name,
                    ]
                );
                UserProposal::updateOrCreate(
                    ['user_proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'is_policy_issued' => 'Policy Issued',
                        'policy_issued_date' => date('Y-m-d H:i:s'),
                    ]
                    );
                $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                $data['ic_id'] = $user_proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
            }
            
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        } else {
            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
    }

    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $service_type = config('constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE');
        $payment_response = DB::table('payment_request_response')
            ->where([
                'user_product_journey_id' => $user_product_journey_id,
                'active' => 1
            ])->first();

        $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if (!$payment_response) {
            return response()->json([
                'status' => false,
                'msg' => 'Payment Details not found.'
            ]);
        }
        $data['user_product_journey_id'] = $user_product_journey_id;
        $data['ic_id'] = $payment_response->ic_id;
        $response = json_decode($payment_response->response);
        //PAYMENT STATUS CHECK SERVICE START
        $policy_number = '';
        if (!is_object($response) && !empty($payment_response->proposal_no) && !empty($user_proposal->pol_sys_id)) {
            $status_data = self::check_payment_status_json($user_product_journey_id, $payment_response->proposal_no, $user_proposal->pol_sys_id, $payment_response->user_proposal_id);

            if (!$status_data['status']) {
                return  [
                    'status' => false,
                    'msg'    => 'Payment Is Pending'
                ];
            } else {
                $payment_response = DB::table('payment_request_response')
                    ->where([
                        'user_product_journey_id' => $user_product_journey_id,
                        'active' => 1
                    ])->first();

                $response = json_decode($payment_response->response);
                $policy_number = $status_data['policy_number'];
            }
        }
        // PAYMENT STATUS CHECK SERVICE END
        if (!is_object($response)) {
            return response()->json([
                'status' => false,
                'msg' => 'Payment Response Details not found. Payment Pending.'
            ]);
        }
        $status = false;

        if (! isset($response->PolicyURL)) {
            $rehit_payment_api_request = [
                'ProposalNo' => $payment_response->proposal_no,
                'QuoteID' => $user_proposal->pol_sys_id
            ];

            $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_PAYMENT_STATUS_CHECK_URL'), $rehit_payment_api_request, 'shriram', [
                'enquiryId' => $user_product_journey_id,
                'headers' => [
                    // 'Username' => config('constants.IcConstants.shriram.SHRIRAMGCV_USERNAME'),
                    // 'Password' => config('constants.IcConstants.shriram.SHRIRAMGCV_PASSWORD'),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'requestMethod' => 'post',
                'requestType' => 'json',
                'section' => 'Taxi',
                'method' => 'Payment Status Check',
                'transaction_type' => 'proposal',
            ]);
            $rehit_payment_api_response = $get_response['response'];
            $policy_number = '';

            if ($rehit_payment_api_response) {
                $rehit_payment_api_res = json_decode($rehit_payment_api_response, TRUE);

                if ($rehit_payment_api_res['MessageResult']['Result'] == 'Success') {
                    PaymentRequestResponse::where([
                        'user_product_journey_id' => $user_product_journey_id,
                        'active' => 1
                    ])
                        ->update([
                            'status' => 'Success',
                            'response' => $rehit_payment_api_response
                        ]);

                    foreach ($rehit_payment_api_res['Response'] as $value) {

                        if (($value['TransactionStatus'] ?? '') == 'Success') {
                            $policy_number = $value['PolicyNo'];
                            $response->PolicyURL = $value['PolicyURL'];
                            PolicyDetails::updateOrCreate([
                                'proposal_id' => $payment_response->user_proposal_id
                            ], [
                                'policy_number' => $value['PolicyNo']
                            ]);
                        }
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'msg' => $rehit_payment_api_res['MessageResult']['ErrorMessage'] ?? 'An unexpected error occurred'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'An error occurred in payment status check service'
                ]);
            }
        }

        if (isset($response->PolicyURL) &&  isset($policy_number)) {
            // $policy_number = $response->PolicyNo;
            $user_journey_data = UserProductJourney::find($user_product_journey_id);
            $product_parent = strtolower(get_parent_code($user_journey_data->product_sub_type_id));
            $folder = ($product_parent == 'gcv') ? config('constants.motorConstant.GCV_PROPOSAL_PDF_URL') : config('constants.motorConstant.CV_PROPOSAL_PDF_URL');
            $pdf_name = $folder . 'shriram/' . md5($payment_response->user_proposal_id) . '.pdf';
            $pdf_url = '';
            try {
                $pdf_data = httpRequestNormal($response->PolicyURL, 'GET', [], [], [], [], false);

                $policy_pdf = false;
                $pdf_url = '';

                if ($pdf_data && $pdf_data['status'] == 200 && isset($pdf_data['response'])) {
                    $policy_pdf = Storage::put($pdf_name, $pdf_data['response']);
                    $pdf_url = file_url($pdf_name);
                }
            } catch (\Throwable $th) {
                $data['stage'] = 'Policy Issued, but pdf not generated';
                updateJourneyStage($data);
            }
            if ($policy_pdf) {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $payment_response->user_proposal_id],
                    [
                        'ic_pdf_url' => $response->PolicyURL,
                        'pdf_url' => $pdf_name,
                    ]
                );
                $status = true;
                $data['stage'] = 'Policy Issued';
                updateJourneyStage($data);
            } else {
                $data['stage'] = 'Policy Issued, but pdf not generated';
                updateJourneyStage($data);
            }
        } else {
            $data['stage'] = 'Payment Pending';
        }

        return response()->json([
            'status' => $status,
            'msg' => $data['stage'],
            'data' => $status ? [
                'policy_number' => $policy_number,
                'pdf_link'      => $pdf_url
            ] : []
        ]);
    }

    public static function check_payment_status_json($user_product_journey_id, $ProposalNo, $QuoteID, $user_proposal_id)
    {

        $policy_number = '';
        $ic_pdf_url = '';
        $status = false;
        // dd($ProposalNo,$QuoteID);
        $rehit_payment_api_request = [
            'ProposalNo' => $ProposalNo,
            'QuoteID' => $QuoteID
        ];

        $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_PAYMENT_STATUS_CHECK_URL'), $rehit_payment_api_request, 'shriram', [
            'enquiryId' => $user_product_journey_id,
            'headers' => [
                // 'Username' => config('constants.IcConstants.shriram.SHRIRAMGCV_USERNAME'),
                // 'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'GCV/PCV',
            'method' => 'Payment Status Check',
            'transaction_type' => 'proposal',
        ]);
        $rehit_payment_api_response = $get_response['response'];

        if ($rehit_payment_api_response) {
            $rehit_payment_api_res = json_decode($rehit_payment_api_response, TRUE);

            if ($rehit_payment_api_res['MessageResult']['Result'] == 'Success') {

                if (is_array(($rehit_payment_api_res['Response']))) {
                    foreach ($rehit_payment_api_res['Response'] as $resp_key => $resp_value) {
                        if ($resp_value['TransactionStatus'] == 'Success') {
                            $rehit_payment_api_response = $resp_value;
                            $policy_number =  $resp_value['PolicyNo'];
                            $ic_pdf_url = $resp_value['PolicyURL'];
                            $status = true;
                        }
                    }
                }

                if ($policy_number !== '') {
                    PolicyDetails::updateOrCreate([
                        'proposal_id' => $user_proposal_id
                    ], [
                        'policy_number' => $policy_number,
                        'ic_pdf_url'  => $ic_pdf_url,
                    ]);

                    $updateProposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)
                        ->where('user_proposal_id', $user_proposal_id)
                        ->update([
                            'policy_no' => $policy_number,
                        ]);
                }

                PaymentRequestResponse::where([
                    'user_product_journey_id' => $user_product_journey_id,
                    'active' => 1
                ])
                    ->update([
                        'status' => ($status) ? 'PAYMENT SUCCESS' : 'PAYMENT FAILED',
                        'response' => $rehit_payment_api_response,
                    ]);

                return [
                    'status' => true,
                    'msg' => 'success',
                    'policy_number' => $policy_number,
                    'pdf_link' => $ic_pdf_url
                ];
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => $rehit_payment_api_res['MessageResult']['ErrorMessage'] ?? 'An unexpected error occurred'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'An error occurred in payment status check service'
            ]);
        }
    }

    public static function postinspectionsubmit($user_proposal, $productData, $request)
    {
        $enquiryId  = $user_proposal->user_product_journey_id;
        $proposalId = $user_proposal->user_proposal_id;
        $master_policy = MasterPolicy::find($request['policyId']);
        $breakindata = CvBreakinStatus::where('user_proposal_id', $proposalId)->first();
        $requestData = getQuotation($enquiryId);
        $inputArray = json_decode($user_proposal->additional_details_data, true);
        $inputArray['objPolicyEntryETT']['PreInspectionReportYN'] = "1";
        $inputArray['objPolicyEntryETT']['PreInspection'] = $breakindata->breakin_number;
        $is_gcv = $productData->parent_id == 4;
        $is_pcv = $productData->parent_id == 8;
        $policy_start_date = date('d-m-Y'/*, strtotime('+2 day')*/);
        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $inputArray['objPolicyEntryETT']['PolicyFromDt'] = $policy_start_date;
        $inputArray['objPolicyEntryETT']['PolicyToDt'] = $policy_end_date;
        $inputArray['objPolicyEntryETT']['PolicyIssueDt'] = date('d-m-Y');
        if($is_gcv){
            $inputArray['objGCCVProposalEntryETT']['PreInspectionReportYN'] = "1";
            $inputArray['objGCCVProposalEntryETT']['PreInspection'] = $breakindata->breakin_number;
            $inputArray['objGCCVProposalEntryETT']['PolicyFromDt'] = $policy_start_date;
            $inputArray['objGCCVProposalEntryETT']['PolicyToDt'] = $policy_end_date;
            $inputArray['objGCCVProposalEntryETT']['PolicyIssueDt'] = date('d-m-Y');
        }
        
        $b2b_seller_type = CvAgentMapping::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->first();

        $additional_data = [
            'enquiryId' => customDecrypt($request['userProductJourneyId']),
            'headers' => [
                'Username' => config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => $productData->product_sub_type_code,
            'method' => 'Proposal Submit',
            'transaction_type' => 'proposal',
        ];

        //b2b seller type headers and application name segregation
        if (!empty($b2b_seller_type) && !empty($b2b_seller_type->seller_type) && config('IS_SEGREGATION_ALLOWED_FOR_IC_CREDENTIALS_FOR_CV') == 'Y') {
            switch($b2b_seller_type->seller_type){
                case 'P':
                    $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_POS');
                    $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_FOR_POS');
                break;

                case 'E':
                    $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_EMPLOYEE');
                    $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_FOR_EMPLOYEE');
                break;

                case 'MISP':
                    $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_MISP');
                    $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_FOR_MISP');
                break;

                default:
                    $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME');
                    $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD');
                break;
            }
        }

        if($is_pcv){
            $url = config('constants.IcConstants.shriram.SHRIRAM_PROPOSAL_SUBMIT_URL_JSON');
        }
            if($is_gcv){
                $url = config('constants.IcConstants.shriram.SHRIRAM_GCV_PROPOSAL_SUBMIT_URL_JSON');
            }
        $get_response = getWsData($url, $inputArray,'shriram', $additional_data);

        $response = $get_response['response'];
        $response = json_decode($response, true);
        if (empty($response)) {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Insurer Not Reachable.'
            ]);
        }
        if ($response['MessageResult']['Result'] == 'Success') {
            if($is_gcv){
                $response['GeneratePCCVProposalResult'] = $response['GenerateGCCVProposalResult'];
                unset($response['GenerateGCCVProposalResult']);
            }
            // $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
            $od_premium = 0;
            $tp_premium = 0;
            $ncb_discount = 0;
            $total_premium = 0;
            $final_payable_amount = 0;
            $non_electrical_accessories = $cpa_premium = 0;
            $voluntary_deductible = $other_discount = $tppd_discount = 0;
            $addon_premium = $tp_lpg_kit = $electrical_premium = $nonelectrical_premium = $od_lpg_kit = 0;
            $geoextensionod = $geoextensiontp = 0;
            $addons_available = [
                'Nil Depreciation',
                'Nil Depreciation Cover',
                'Road Side Assistance'
            ];
            foreach ($response['GeneratePCCVProposalResult']['CoverDtlList'] as $key => $premium_data) {
                if ($premium_data['CoverDesc'] == 'Basic OD Premium') {
                    $od_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TP Total') {
                    $tp_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR42-Outbuilt CNG\/LPG-Kit-Cover' || $premium_data['CoverDesc'] ==  'GR42-Outbuilt CNG/LPG-Kit-Cover') {
                    if (round($premium_data['Premium']) == 60) {
                        $tp_lpg_kit = round($premium_data['Premium']);
                    } else {
                        $od_lpg_kit = round($premium_data['Premium']);
                    }
                }
                if ($premium_data['CoverDesc'] == 'GR41-Cover For Electrical and Electronic Accessories') {
                    $electrical_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                    $nonelectrical_premium = round($premium_data['Premium']);
                }
                if (in_array($premium_data['CoverDesc'], ['NCB Discount ', 'NCB Discount'])) {
                    $ncb_discount = round(abs($premium_data['Premium']));
                }
                if ($premium_data['CoverDesc'] == 'Total Premium') {
                    $total_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                    $cpa_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'De-Tariff Discount') {
                    $other_discount = round(abs($premium_data['Premium']));
                }
                if ($premium_data['CoverDesc'] == 'GR39A-Limit The Third Party Property Damage Cover') {
                    $tppd_discount = round(abs($premium_data['Premium']));
                }
                if (in_array($premium_data['CoverDesc'], $addons_available)) {
                    $addon_premium += round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'InBuilt CNG Cover') {
                    if (round($premium_data['Premium']) == 60) {
                        $tp_lpg_kit = round($premium_data['Premium']);
                    } else {
                        $od_lpg_kit = round(abs($premium_data['Premium']));
                    }
                }
                if ($premium_data['CoverDesc'] == 'GR4-Geographical Extension') {
                    if ($premium_data['Premium'] == 100) {
                        $geoextensiontp = $premium_data['Premium'];
                    } else {
                        $geoextensionod = $premium_data['Premium'];
                    }
                }
            }
            $total_discount = $ncb_discount + $other_discount + $voluntary_deductible;
            UserProposal::where('user_product_journey_id',$enquiryId)->update([
                'proposal_no' => $response['GeneratePCCVProposalResult']['PROPOSAL_NO'],
                'pol_sys_id' => $response['GeneratePCCVProposalResult']['POL_SYS_ID'],
                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
            ]);
            // $proposal->final_payable_amount = Arr::last($response['GeneratePCCVProposalResult']['CoverDtlList'])['Premium'];

            $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
            $data['ic_id'] = $master_policy->insurance_company_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $user_proposal->user_proposal_id;
            updateJourneyStage($data);

            ShriramPremiumDetailController::savePcvJsonPremiumDetails($get_response['webservice_id']);

            return response()->json([
                'status' => true,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => "Proposal submitted successfully",
            ]);
        } else {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['MessageResult']['ErrorMessage'],
            ]);
        }
    }
}
