<?php

namespace App\Http\Controllers\Payment\Services\V1\PCV;


use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use Hamcrest\Type\IsObject;

use function Composer\Autoload\includeFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;


include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class shriramPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
       
       
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();
        $quote = QuoteLog::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $quote_data = json_decode($quote->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $quote_data['version_id'], 'shriram');
        if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
        $ic_version_mapping = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        } else {
            return [
                'premium_amount' => '0',
                'status' => false,
                'message' => $mmv['message'],
            ];
        }
        $ic_version_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $service_type = config('IC.SHRIRAM.V1.PCV.REQUEST_TYPE'); //constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE

        $return_data = [
            'form_action' => 
             config('IC.SHRIRAM.V1.PCV.PAYMENT_URL'), //constants.IcConstants.shriram.SHRIRAM_PAYMENT_BEFORE_URL
            //'form_action' => 'http://sginovauat.shriramgi.com/MyPymt/mydefaultcc.aspx',
            'form_method' => config('IC.SHRIRAM.V1.PCV.PAYMENT_BEFORE_URL_METHOD'), //constants.IcConstants.shriram.SHRIRAM_PAYMENT_BEFORE_URL_METHOD
            'payment_type' => 0, // form-submit
            'form_data' => [
                'DbFrom' => 'NOVA',
                'QuoteId' => $user_proposal->pol_sys_id,
                'amount' => $user_proposal->final_payable_amount,
                'application' => config('IC.SHRIRAM.V1.PCV.APPLICATION_NAME'), // HeroIns
                'createdBy' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_USERNAME'), //constants.IcConstants.shriram.SHRIRAM_USERNAME
                'description' => $user_proposal->proposal_no.'-'.$user_proposal->pol_sys_id,
                'isForWeb' => 'true',
                //'paymentFrom' => $service_type == 'XML' ? 'CCAVENUE' : 'PAYTM',
                // As per IC : use CCAVENUE In UAT and PAYTM in Production
                'paymentFrom' => config('IC.SHRIRAM.V1.PCV.PAYMENT_TYPE'), //constants.IcConstants.shriram.SHRIRAM_CV_PAYMENT_TYPE
                'PolicySysID' => $user_proposal->pol_sys_id,
                'ReturnURL' => route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'prodCode' => $productData->product_sub_type_code == 'TAXI' ? 'MOT-PRD-005' : $ic_version_details->vap_prod_code,
                'prodName' => 'MOTOR',
                'return_url' => route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'sourceUrl' => route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
            ],
        ];

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
            'return_url' => route('cv.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'active' => 1,
            'xml_data' => json_encode($return_data)
        ]);

       
        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'] ;
        updateJourneyStage($data);
        return response()->json([
            'status' => true,
            'msg' => "Payment Redirectional",
            'data' => $return_data,
        ]);
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    
    static function JSONConfirm($request)
    {
        
        $user_proposal = UserProposal::find($request->user_proposal_id); 
     
        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;

        $rehit_payment_api_request = [
            'ProposalNo' => $user_proposal->proposal_no,
            'QuoteID' => $user_proposal->pol_sys_id
        ];

        //constants.IcConstants.shriram.SHRIRAM_PAYMENT_STATUS_CHECK_URL
        $get_response = getWsData(config('IC.SHRIRAM.V1.PCV.PAYMENT_STATUS_CHECK_URL'), $rehit_payment_api_request, 'shriram', [
            'enquiryId' => $data['user_product_journey_id'],
            'headers' => [
                'Username' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_USERNAME'), //constants.IcConstants.shriram.SHRIRAM_USERNAME
                'Password' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_PASSWORD'), //constants.IcConstants.shriram.SHRIRAM_PASSWORD
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
       
        if ($rehit_payment_api_response)
        {
            $rehit_payment_api_res = json_decode($rehit_payment_api_response, TRUE);
         
          

            if ($rehit_payment_api_res['MessageResult']['Result'] == 'Success')
            {
                $request->Status = 'Successful Completion';
                $request->PolicyNumber = $rehit_payment_api_res['Response'][0]['PolicyNo'];
                $request->PolicyURL = $rehit_payment_api_res['Response'][0]['PolicyURL'];

                PaymentRequestResponse::where('user_product_journey_id', $data['user_product_journey_id'])  
                    ->update([
                        'status' => $request->Status == 'Successful Completion' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                        'response' => $rehit_payment_api_response
                    ]);
                
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($data);
            }
            else
            {
                $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
            }
        }

        if ($request->Status != 'Successful Completion') { // Paytm will send 'ResponseMsg' only if the payment is unsuccessful/fail
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
        if(!isset($request->PolicyNumber) || empty($request->PolicyNumber)) { // We will get PolicyNumber tag only in case of successfull transaction - 16-03-2022
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
        // $data['stage'];
        PolicyDetails::updateOrCreate(
            ['proposal_id' => $user_proposal->user_proposal_id],
            [
                'policy_number' => $request->PolicyNumber,
                'policy_start_date' => $user_proposal->policy_start_date,
                'premium' => $user_proposal->final_payable_amount,
                'created_on' => date('Y-m-d H:i:s'),
            ]
        );
        $user_proposal->policy_no = $request->PolicyNumber;
        $user_proposal->save();

        $policyPdf = self:: PolicyPDFJSON($request, $user_proposal);

        if (!$policyPdf) {

            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
        }

        if (isset($request->PolicyURL)) {
            $user_journey_data = UserProductJourney::find($user_proposal->user_product_journey_id);
            $product_parent =  strtolower(get_parent_code($user_journey_data->product_sub_type_id));

            $folder = ($product_parent == 'gcv') ? config('constants.motorConstant.GCV_PROPOSAL_PDF_URL') : config('constants.motorConstant.CV_PROPOSAL_PDF_URL');
            $pdf_name = $folder . 'shriram/' . md5($request->user_proposal_id) . '.pdf';

            try {
                $pdf_data = httpRequestNormal($request->PolicyURL, 'GET', [], [], [], [], false);

                $policy_pdf = false;

                if (
                    $pdf_data && $pdf_data['status'] == 200 &&
                    !empty($pdf_data['response']) && checkValidPDFData($pdf_data['response'])
                ) {
                    $policy_pdf = Storage::put($pdf_name, $pdf_data['response']);
                }
            } catch (\Throwable $th) {
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] ;
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
            }
            if ($policy_pdf) {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'ic_pdf_url' => $request->PolicyURL,
                        'pdf_url' => $pdf_name,
                    ]
                );
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
            } else {
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] ;
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
            }
        }
         else {
            $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'] ;//STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
    }

    static public function generatePdf($request)
    {
      
   
        //constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE
        if(config('IC.SHRIRAM.V1.PCV.REQUEST_TYPE') == 'JSON'){
            return self::generateJSONPDF($request);
        }
      
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $payment_response = DB::table('payment_request_response')
            ->where([
                'user_product_journey_id' => $user_product_journey_id,
                'active' => 1
            ])->first();
        if(!$payment_response){
            return response()->json([
                'status' => false,
                'msg' => 'Payment Details not found.'
            ]);
        }
        $data['user_product_journey_id'] = $user_product_journey_id;
        $data['ic_id'] = $payment_response->ic_id;
        $response = json_decode($payment_response->response);
        if(!is_object($response)){
            return response()->json([
                'status' => false,
                'msg' => 'Payment Response Details not found. Payment Pending.'
            ]);
        }
        $input_array = [
            'soap:Header' => [
                'AuthHeader' => [
                    '@attributes' => [
                        'xmlns' => 'http://tempuri.org/',
                    ],
                    'Username' => config('constants.cv.shriram.SHRIRAM_XML_AUTH_USERNAME_PCV'),
                    'Password' => config('constants.cv.shriram.SHRIRAM_XML_AUTH_PASSWORD_PCV'),
                ],
            ],
            'soap:Body' => [
                'PolicyApprove' => [
                    '@attributes' => [
                        'xmlns' => 'http://tempuri.org/',
                    ],
                    'objPolicyApprovalETT' => [
                        'ProposalNo' => $response->ProposalNumber,
                        'TransactionNumber' => '',
                        'CardNumber' => '',
                        'CardholderName' => '',
                        'CardType' => '',
                        'CardValidUpTp' => '',
                        'BankName' => '',
                        'BranchName' => '',
                        'PaymentType' => 'CC',
                        'TransactionDate' => '',
                        'ChequeType' => '',
                        'ChequeClearType' => '',
                        'CashType' => '',
                    ],
                ],
            ],
        ];

        $root_elements = [
            'rootElementName' => 'soap:Envelope',
            '_attributes' => [
                'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            ],
        ];
        $input_array = ArrayToXml::convert($input_array, $root_elements, true, 'UTF-8');
        $additional_data = [
            'enquiryId' => $user_product_journey_id,
            'headers' => [
                'SOAPAction' => 'http://tempuri.org/PolicyApprove',
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Taxi',
            'method' => 'Proposal Approval',
            'transaction_type' => 'proposal',
        ];
 //constants.IcConstants.shriram.SHRIRAM_POLICY_APPROVED_URL
        $get_response = getWsData(config('IC.SHRIRAM.V1.PCV.POLICY_APPROVED_URL'), $input_array, 'shriram', $additional_data);
        $response = $get_response['response'];

        $response = XmlToArray::convert($response);
        if ($response['soap:Body']['PolicyApproveResponse']['PolicyApproveResult']['Err_Code'] == 0) {
            $PolicyApproveResult = $response['soap:Body']['PolicyApproveResponse']['PolicyApproveResult'];
            //Generate Policy PDF - Start
            $doc_link = config('constants.motor.shriram.SHRIRAM_MOTOR_GENERATE_PDF') . 'PolSysId=' . $PolicyApproveResult['ApprovePolSysId'] . '&LogoYN=Y';
            $user_journey_data = UserProductJourney::find($user_product_journey_id);
            $product_parent =  strtolower(get_parent_code($user_journey_data->product_sub_type_id));

            $folder = ($product_parent == 'gcv') ? config('constants.motorConstant.GCV_PROPOSAL_PDF_URL') : config('constants.motorConstant.CV_PROPOSAL_PDF_URL');
            $pdf_name = $folder . 'shriram/' . md5($payment_response->user_proposal_id) . '.pdf';

            try {
                $pdf_data = httpRequestNormal($doc_link, 'GET', [], [], [], [], false);

                $policy_pdf = false;

                if ($pdf_data && $pdf_data['status'] == 200 && isset($pdf_data['response'])) {
                    $policy_pdf = Storage::put($pdf_name, $pdf_data['response']);
                }
            } catch (\Throwable $th) {
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($data);
                return response()->json([
                    'status' => false,
                    'msg' => $data['stage']
                ]);
            }
            if ($policy_pdf) {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $payment_response->user_proposal_id],
                    [
                        'ic_pdf_url' => $doc_link,
                        'pdf_url' => $pdf_name,
                    ]
                );
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data);
                return response()->json([
                    'status' => true,
                    'msg' => $data['stage']
                ]);
            }
            $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] ;
            updateJourneyStage($data);
            return response()->json([
                'status' => false,
                'msg' => $data['stage']
            ]);
        }
        return response()->json([
            'status' => false,
            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
        ]);
    }

    static public function generateJSONPDF($request){
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $service_type = config('IC.SHRIRAM.V1.PCV.REQUEST_TYPE'); //constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE
   
        $payment_response = DB::table('payment_request_response')
            ->where([
                'user_product_journey_id' => $user_product_journey_id,
                'active' => 1
            ])->first();

        $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if(!$payment_response){
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
        if(!is_object($response) && !empty($payment_response->proposal_no) && !empty($user_proposal->pol_sys_id))
        {
            $status_data = self::check_payment_status_json($user_product_journey_id,$payment_response->proposal_no,$user_proposal->pol_sys_id,$payment_response->user_proposal_id);

            if(!$status_data['status'])
            {
                return  [
                    'status' => false,
                    'msg'    => 'Payment Is Pending'
                ];
            }else
            {
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
        if(!is_object($response)){
            return response()->json([
                'status' => false,
                'msg' => 'Payment Response Details not found. Payment Pending.'
            ]);
        }
        $status = false;

        if ( ! isset($response->PolicyURL))
        {
            $rehit_payment_api_request = [
                'ProposalNo' => $payment_response->proposal_no,
                'QuoteID' => $user_proposal->pol_sys_id
            ];
    
            //constants.IcConstants.shriram.SHRIRAM_PAYMENT_STATUS_CHECK_URL
            $get_response = getWsData(config('IC.SHRIRAM.V1.PCV.PAYMENT_STATUS_CHECK_URL'), $rehit_payment_api_request, 'shriram', [
                'enquiryId' => $user_product_journey_id,
                'headers' => [
                    'Username' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_USERNAME'), //constants.IcConstants.shriram.SHRIRAM_USERNAME
                    'Password' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_PASSWORD'), //constants.IcConstants.shriram.SHRIRAM_PASSWORD
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

            if ($rehit_payment_api_response)
            {
                $rehit_payment_api_res = json_decode($rehit_payment_api_response, TRUE);

                if ($rehit_payment_api_res['MessageResult']['Result'] == 'Success')
                {
                    PaymentRequestResponse::where([
                        'user_product_journey_id' => $user_product_journey_id,
                        'active' => 1
                    ])
                    ->update([
                        'status' => 'Successful Completion',
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
                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'msg' => $rehit_payment_api_res['MessageResult']['ErrorMessage'] ?? 'An unexpected error occurred'
                    ]);
                }
            }
            else
            {
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

                if (
                    $pdf_data && $pdf_data['status'] == 200
                    && !empty($pdf_data['response']) && checkValidPDFData($pdf_data['response'])
                ) {
                    $policy_pdf = Storage::put($pdf_name, $pdf_data['response']);
                    $pdf_url = file_url($pdf_name);
                }
            } catch (\Throwable $th) {
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] ;
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
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'] ;
                updateJourneyStage($data);
            } else {
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
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

    public static function check_payment_status_json($user_product_journey_id,$ProposalNo,$QuoteID,$user_proposal_id)
    {

        $policy_number='';
        $ic_pdf_url = '';
        $status = false;

        $rehit_payment_api_request = [
            'ProposalNo' => $ProposalNo,
            'QuoteID' => $QuoteID
        ];

        //constants.IcConstants.shriram.SHRIRAM_PAYMENT_STATUS_CHECK_URL
        $get_response = getWsData(config('IC.SHRIRAM.V1.PCV.PAYMENT_STATUS_CHECK_URL'), $rehit_payment_api_request, 'shriram', [
            'enquiryId' => $user_product_journey_id,
            'headers' => [
                'Username' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_USERNAME'), //constants.IcConstants.shriram.SHRIRAM_USERNAME
                'Password' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_PASSWORD'), //constants.IcConstants.shriram.SHRIRAM_PASSWORD
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

        if ($rehit_payment_api_response)
        {
            $rehit_payment_api_res = json_decode($rehit_payment_api_response, TRUE);

            if ($rehit_payment_api_res['MessageResult']['Result'] == 'Success')
            {

                if(is_array(($rehit_payment_api_res['Response'])))
                {
                    foreach ($rehit_payment_api_res['Response'] as $resp_key => $resp_value) 
                    {
                        if($resp_value['TransactionStatus'] == 'Success')
                        {
                            $rehit_payment_api_response = $resp_value;
                            $policy_number =  $resp_value['PolicyNo'];
                            $ic_pdf_url = $resp_value['PolicyURL'];
                            $status = true;

                        } 
                    }
                }

                if($policy_number !== '')
                {
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
                    'status' => ($status) ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                    'response' => $rehit_payment_api_response,
                ]);

                return [
                    'status' => true,
                    'msg' => 'success',
                    'policy_number' => $policy_number
                ];
            }
            else
            {
                return response()->json([
                    'status' => false,
                    'msg' => $rehit_payment_api_res['MessageResult']['ErrorMessage'] ?? 'An unexpected error occurred'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => false,
                'msg' => 'An error occurred in payment status check service'
            ]);
        }


    }
    public static function PolicyPDFJSON($request, $user_proposal)
    {

        $user_product_journey_id = $user_proposal->user_product_journey_id;
       
        $payment_response = DB::table('payment_request_response')
        ->where([
            'user_product_journey_id' => $user_product_journey_id,
            'active' => 1
        ])->first();
    $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

    if(!$payment_response){
        return response()->json([
            'status' => false,
            'msg' => 'Payment Details not found.'
        ]);
    }

    $data['user_product_journey_id'] = $user_product_journey_id;
    $data['ic_id'] = $payment_response->ic_id;
    $response = json_decode($payment_response->response);

        $Policy_payment_api_request = [
            'strPolSysId' => $user_proposal->pol_sys_id
        ];

        $get_response = getWsData(config('constants.cv.iffco.SHRIRAM_POLICY_PDF_URL'), $Policy_payment_api_request, 'shriram', [
            'enquiryId' => $user_product_journey_id,
            'headers' => [
                'Username' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_USERNAME'), //constants.IcConstants.shriram.SHRIRAM_POLICY_USERNAME
                'Password' => config('IC.SHRIRAM.V1.PCV.SHRIRAM_PASSWORD'), //constants.IcConstants.shriram.SHRIRAM_POLICY_PASSWORD
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Taxi',
            'method' => 'Policy PDF ',
            'transaction_type' => 'proposal',
        ]);
        $Policy_PDF_Response = $get_response['response'];  
         $policy_number = '';

            if ($Policy_PDF_Response)
            {
                $policy_Pdf_api_res = json_decode($Policy_PDF_Response, TRUE);

                if ($policy_Pdf_api_res['PolicyScheduleURLResult'] != '')
                {
                    PaymentRequestResponse::where([
                        'user_product_journey_id' => $user_product_journey_id,
                        'active' => 1
                    ])
                    ->update([
                        'status' => 'Successful Completion',
                        'response' => $Policy_PDF_Response
                    ]);
                    foreach ($policy_Pdf_api_res as $value) {
                        
                        if (($value['PolicyScheduleURLResult'] ?? '')) {
                            $policy_number = $request->PolicyNumber;
                            $response->PolicyURL = $value['PolicyScheduleURLResult'];
                            PolicyDetails::updateOrCreate([
                                'proposal_id' => $payment_response->user_proposal_id
                            ], [
                                'policy_number' => $request->PolicyNumber
                            ]);
                        } 
                    }
                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'msg' => $policy_Pdf_api_res['PolicyScheduleURLResult'] ?? 'An unexpected error occurred'
                    ]);
                }
            }
            else
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'An error occurred in payment status check service'
                ]);
            }
         $policy_pdf = $policy_Pdf_api_res['PolicyScheduleURLResult'];
         $policy_number = $response->Response["0"]->PolicyNo;

        if (isset($response->Response["0"]->PolicyURL
        ) ) {
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

                if (
                    $pdf_data && $pdf_data['status'] == 200
                    && !empty($pdf_data['response']) && checkValidPDFData($pdf_data['response'])
                ) {
                    $policy_pdf = Storage::put($pdf_name, $pdf_data['response']);
                    $pdf_url = file_url($pdf_name);
                }
            } catch (\Throwable $th) {
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] ;
                updateJourneyStage($data);
            }
            if ($policy_pdf) {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $payment_response->user_proposal_id],
                    [
                        'ic_pdf_url' =>$response->Response["0"]->PolicyURL,
                        'pdf_url' => $pdf_name,
                    ]
                );
                $status = true;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'] ;
                updateJourneyStage($data);
            }
            
            else {
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] ;
                updateJourneyStage($data);
            }
        }else {
            $data['stage'] = 'Payment Pending';
        }
        return $status;
       
        }

    

}
