<?php

namespace App\Http\Controllers\Payment\Services\Car;
include_once app_path().'/Helpers/CarWebServiceHelper.php';
include_once app_path().'/Helpers/IcHelpers/ShriramHelper.php';


use App\Http\Controllers\SyncPremiumDetail\Car\ShriramPremiumDetailController;
use App\Http\Controllers\Proposal\Services\shriramSubmitProposal;
use App\Models\CvAgentMapping;
use App\Models\CvBreakinStatus;
use App\Models\MasterPremiumType;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;

class shriramPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
                ->pluck('quote_id')
                ->first();
        $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

        DB::table('payment_request_response')
              ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
              ->update(['active' => 0]);
              $enquiryId = customDecrypt($request->enquiryId);
              $requestData = getQuotation($enquiryId);
              $productData = getProductDataByIc($request->policyId);

        if (config('constants.motor.shriram.SHRIRAM_CAR_JSON_REQUEST_TYPE') != 'JSON')
        {

            $payment_url = config('constants.motor.shriram.PAYMENT_URL');

            $return_data = [
                'form_action' => $payment_url,
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'PolicySysID' => $user_proposal->pol_sys_id,
                    'ReturnURL' => route('car.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id])
                ],
            ];
        }
        else
        {    
            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
            if(in_array($premium_type,['breakin','own_damage_breakin']) && !(in_array($premium_type,['third_party','third_party_breakin']))){
                $breakinDetails = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->first();
                if (!empty($breakinDetails) && json_decode($breakinDetails)->breakin_status == STAGE_NAMES['INSPECTION_APPROVED']) {
                    $response = self::postInspectionSubmit($user_proposal, $productData, $request);
                    if (($response['status'] ?? false) != true) {
                        return response()->json([
                            'status' => false,
                            'msg' => $response['message'] ?? 'Something went wrong'
                        ]);
                    }

                    $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                } else {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Inspection is pending'
                    ]);
                }
            }
        }
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();
        $payment_url = config('constants.motor.shriram.PAYMENT_URL_JSON');
        $paymentFrom = config('constants.motor.shriram.PAYMENT_URL_JSON_PAYMENTFROM');

        $b2b_seller_type = CvAgentMapping::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->first();

        $return_data = [
            'form_action' => $payment_url,
            'form_method' => 'POST',
            'payment_type' => 0, // form-submit
            'form_data' => 
            [
                "DbFrom" =>  "NOVA",
                "description" =>  $user_proposal->proposal_no,
                "isForWeb" =>  "true",             
                'createdBy' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON'),
                'paymentFrom' =>  $paymentFrom,
                'prodCode'  => $user_proposal->product_code,
                'QuoteId' => $user_proposal->pol_sys_id,
                'amount' => $user_proposal->final_payable_amount,
                'sourceUrl' => route('car.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id,'EnquiryId' => customEncrypt($user_proposal->user_product_journey_id)]),
                'ReturnUrl' => route('car.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id,'EnquiryId' => customEncrypt($user_proposal->user_product_journey_id)]),
                'application' => config('SHRIRAM_CAR_APPLICATION_NAME'),
            ],
        ];

        //b2b seller type headers and application name segregation
        if(!empty($b2b_seller_type) && !empty($b2b_seller_type->seller_type) && config('IS_SEGREGATION_ALLOWED_FOR_IC_CREDENTIALS') == 'Y'){
            switch($b2b_seller_type->seller_type){
                case 'P':
                    $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON_FOR_POS');
                    $return_data['form_data']['application'] = config('SHRIRAM_CAR_APPLICATION_NAME_FOR_POS');
                break;

                case 'E':
                    $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON_FOR_EMPLOYEE');
                    $return_data['form_data']['application'] = config('SHRIRAM_CAR_APPLICATION_NAME_FOR_EMPLOYEE');
                break;

                case 'MISP':
                    $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON_FOR_MISP');
                    $return_data['form_data']['application'] = config('SHRIRAM_CAR_APPLICATION_NAME_FOR_MISP');
                break;
                
                default:
                $return_data['form_data']['createdBy'] = config('constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON_FOR_POS');
                $return_data['form_data']['application'] = config('SHRIRAM_CAR_APPLICATION_NAME');
                break;
            }
        }
        if(in_array($user_proposal->business_type,['breakin','own_damage_breakin'])){
            $return_data['form_data']['description'] = $user_proposal->proposal_no."-".$user_proposal->pol_sys_id;
        }
        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $user_proposal->user_product_journey_id,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            'payment_url'               => $payment_url,
            'proposal_no'               => $user_proposal->proposal_no,
            'return_url'                => route('car.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id,'EnquiryId' => customEncrypt($user_proposal->user_product_journey_id)]),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'created_at'                => date('Y-m-d H:i:s'),
            'xml_data'                  => json_encode($return_data)
        ]);

        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);
        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        $user_proposal = UserProposal::find($request->all()['user_proposal_id']);
       $proposalNumber = $request->all()['ProposalNumber'];
        if (!$user_proposal) {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $user_proposal->user_product_journey_id)) {
            \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/' . $user_proposal->user_product_journey_id);
        }
        if(empty($request->ProposalNumber)) {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','FAILURE'));
        }
        $payment_status = true;
        if (config('constants.motor.shriram.SHRIRAM_CAR_JSON_REQUEST_TYPE') == 'JSON') 
        {            
            if(isset($request->all()['ResponseCode']) && in_array($request->all()['ResponseCode'], ['Failure', 'Aborted'])) {
                $payment_status = false;
            }
            DB::table('payment_request_response')
            ->where('order_id', $request->ProposalNumber)
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => $payment_status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'], //$request->Status == 'Successful Completion' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'] ,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return self::JSONConfirm($request, $user_proposal);
        }

        DB::table('payment_request_response')
            ->where('order_id', $proposalNumber)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => $payment_status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'] ,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
        
            //xml flow
            $policyNumber = $request->all()['PolicyNumber'];
       $policyURL = $request->all()['PolicyURL'];
            if ($request->ErrorCode != '0')
            {
                $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                $data['ic_id'] = $user_proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','FAILURE'));
            }
            if ($policyNumber != '')
            {
                PolicyDetails::updateOrCreate(
                    [ 'proposal_id' => $user_proposal->user_proposal_id ],
                    [
                        'policy_number'     => $policyNumber,
                        'created_on'        => date('Y-m-d H:i:s')
                    ]
                );
                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('user_proposal_id', $user_proposal->user_proposal_id)
                    ->update(['policy_no' => $policyNumber]);
                    
                //Generate Policy PDF - Start
                if(!empty($request->PolicySysID)){
                $doc_link = config('constants.motor.shriram.SHRIRAM_MOTOR_GENERATE_PDF').'PolSysId=' . $request->PolicySysID . '&LogoYN=Y';
                }
                else{
                    $doc_link = $policyURL;
                }

                $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'shriram/' . md5($request->user_proposal_id) . '.pdf';

                try
                {
                    $pdf_data = httpRequestNormal($doc_link,'GET',[],[],[],[],false)['response'];
                    if (checkValidPDFData($pdf_data)) {
                        $proposal_pdf = Storage::put($pdf_name, $pdf_data);
                    } else {
                        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                        $data['ic_id'] = '33';
                        $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                        updateJourneyStage($data);
                        PolicyDetails::updateOrCreate(
                            [ 'proposal_id' => $user_proposal->user_proposal_id ],
                            [
                                'ic_pdf_url' => $doc_link
                            ]
                        );
                        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
                    }
                }
                catch (\Throwable $th)
                {
                    $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                    $data['ic_id'] = $user_proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($data);
                    PolicyDetails::updateOrCreate(
                        [ 'proposal_id' => $user_proposal->user_proposal_id ],
                        [
                            'ic_pdf_url' => $doc_link
                        ]
                    );
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','SUCCESS'));
                }
                if ($proposal_pdf)
                {
                    PolicyDetails::updateOrCreate(
                        [ 'proposal_id' => $user_proposal->user_proposal_id ],
                        [
                            'ic_pdf_url' => $doc_link,
                            'pdf_url' => $pdf_name,
                        ]
                    );
                    DB::table('payment_request_response')
                        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                        ->where('active',1)
                        ->update([
                            'response' => $request->All(),
                            'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]);
                    $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                    $data['ic_id'] = $user_proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                    updateJourneyStage($data);
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','SUCCESS'));
                }
                else
                {
                    $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                    $data['ic_id'] = $user_proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($data);
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','SUCCESS'));
                }
            }
            else
            {
                if(empty($request->PolicyNumber) && $request->Status == 'SUCCESS'){
                    $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                    $data['ic_id'] = $user_proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
                    updateJourneyStage($data);
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','SUCCESS'));
                }
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CAR','FAILURE'));
            }
        
        
    }
    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            ->where('prr.active',1)
            //->where('prr.status',STAGE_NAMES['PAYMENT_SUCCESS'])
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url', 'prr.xml_data' , 'prr.order_id'
                )
            ->first();
        if (!$policy_details) {
            return response()->json([
                'status'=>false,
                'msg'=>'details not found'
            ]);
        }
        if($policy_details->ic_pdf_url != '')
        {
            $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'shriram/' . md5($policy_details->user_proposal_id) . '.pdf';
            $pdf_data = httpRequestNormal($policy_details->ic_pdf_url, 'GET', [], [], [], [], false)['response'];
            if (checkValidPDFData($pdf_data)) {
                Storage::put($pdf_name, $pdf_data);
                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]);
                
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'IC\'s PDF data content is not a valid PDF data.',
                    'data' => [
                        'ic_pdf_url' => $policy_details->ic_pdf_url
                    ],
                ]);
            }
            $pdf_response_data = [
                'status' => true,
                'msg' => 'sucess',
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => file_url($pdf_name)
                ]
            ];
        }
        else
        {
            if (strtolower(config('constants.motor.shriram.SHRIRAM_BIKE_JSON_REQUEST_TYPE')) == 'json') {
                $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
                return paymentstatuscheck($policy_details, $proposal, true);
            }
            
            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
            $input_array = [
                'soap:Header' => [
                    'soap:AuthHeader' => [
                        '@attributes' => [
                            'xmlns' => 'http://tempuri.org/'
                        ],
                        'Username'    => config('constants.motor.shriram.AUTH_NAME_SHRIRAM_MOTOR'),
                        'Password'    => config('constants.motor.shriram.AUTH_PASS_SHRIRAM_MOTOR'),
                    ]
                ],
                'soap:Body'   => [
                    'PolicyScheduleURL' => [
                        '@attributes' => [
                            'xmlns' => 'http://tempuri.org/'
                        ],
                        'strPolSysId' => $proposal->pol_sys_id
                    ]
                ]
            ];
            $root_elements = [
                'rootElementName' => 'soap:Envelope',
                '_attributes' => [
                    'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                ],
            ];
            
            $pol_doc_array = ArrayToXml::convert($input_array, $root_elements, true, 'UTF-8');
            
            $get_response = getWsData(
                    config('constants.IcConstants.shriram.SHRIRAM_POLICY_APPROVED_URL'),
                    $pol_doc_array, 'shriram', [
                        'enquiryId' => $user_product_journey_id,
                        'headers' => [
                            'SOAPAction' => 'http://tempuri.org/PolicyScheduleURL',
                            'Content-Type' => 'text/xml; charset="utf-8"',
                        ],
                        'requestMethod' => 'post',
                        'requestType' => 'xml',
                        'section' => 'Car',
                        'method' => 'Document Generation',
                        'transaction_type' => 'proposal',
                    ]
            );
            $fetch_data = $get_response['response'];
            $response = XmlToArray::convert($fetch_data);
            //Generate Policy PDF - Start
            $doc_link = config('constants.motor.shriram.SHRIRAM_MOTOR_GENERATE_PDF').'PolSysId=' . $proposal->pol_sys_id . '&LogoYN=Y';

            $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'shriram/' . md5($policy_details->user_proposal_id) . '.pdf';
            $pdf_data = httpRequestNormal($doc_link, 'GET', [], [], [], [], false)['response'];
            if (checkValidPDFData($pdf_data))
            {  
                Storage::put($pdf_name, $pdf_data);
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $policy_details->user_proposal_id],
                    [
                        'policy_number' => $proposal->policy_no,
                        'ic_pdf_url' => $doc_link,
                        'pdf_url' => $pdf_name,
                        'status' => 'SUCCESS'
                    ]
                );
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'ic_id' => $proposal->ic_id,
                    'proposal_id' => $proposal->user_proposal_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]);
                $pdf_response_data = [
                    'status' => true,
                    'msg' => 'sucess',
                    'data' => [
                        'policy_number' => $proposal->policy_no,
                        'pdf_link'      =>  file_url($pdf_name)
                    ]
                ];
            }
            else
            {
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'IC\'s PDF data content is not a valid PDF data.',
                    'data' => [
                        'ic_pdf_url' => $doc_link
                    ]
                ];
            }
        }
        return response()->json($pdf_response_data);
    }

    static public function JSONConfirm($request, $user_proposal)
    {
        //json flow
        $proposalNumber = $request->all()['ProposalNumber'];
        $payment_status = true;

        if (in_array(($request->all()['ResponseCode'] ?? ''), ['Failure', 'Aborted'])){
            $payment_status = false;
        }

        DB::table('payment_request_response')
        ->where('order_id', $proposalNumber)
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('active', 1)
        ->update([
            'response' => $request->All(),
            'status' => $payment_status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'], 
            // 'proposal_no'   => $request->all()['ProposalNumber'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$payment_status) {
            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            //$data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CAR', 'FAILURE'));
        }

        $policyDetails = DB::table('payment_request_response')
        ->where('order_id', $proposalNumber)
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->first();
        $policyNumber = $request->all()['PolicyNumber'];
        $policyURL = $request->all()['PolicyURL'];
        if (!empty($policyNumber)) 
        {
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal->user_proposal_id],
                [
                    'policy_number'     => $policyNumber
                ]
            );

            if (!empty($policyURL)) 
            {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'ic_pdf_url'     => $policyURL
                    ]
                );
                downloadPDFFromURL($policyNumber, $policyURL, $user_proposal, 'CAR');
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
            } 
            else 
            {
                policyPDFJSON($user_proposal, $policyNumber);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
            }
        } 
        else 
        {
            paymentstatuscheck($policyDetails, $user_proposal);
             return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
        }
    }

    public static function postInspectionSubmit($user_proposal, $productData, $request){
        $enquiryId = customDecrypt($request->enquiryId);
        $requestData = getQuotation($enquiryId);
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $master_policy = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first(); 

        $product_code = DB::table('master_product')
            ->where('master_policy_id',$request['policyId'])
            ->where('ic_id',$master_policy)
            ->pluck('product_id')
            ->first();
        $ProductCode = $product_code;
        $breakinId = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->pluck('breakin_number')->first();
        $business_type = $requestData->business_type;
        $KeyReplacementYN = $InvReturnYN = $engine_protection = $LossOfPersonBelongYN = $LLtoPaidDriverYN = $PAPaidDriverConductorCleanerSI = $tyresecure = $LossOfPersonalBelonging_SI = 0;
        $policy_start_date = date('Y-m-d'/*, strtotime('+2 day')*/);
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $reqarray = DB::table('user_proposal')
                    ->where('user_product_journey_id',$enquiryId)
                    ->pluck('additional_details_data')
                    ->first();
        $newarr = json_decode($reqarray);
        $newarr->objPolicyEntryETT->PreInspectionReportYN = "1";
        $newarr->objPolicyEntryETT->PreInspection = $breakinId;
        $newarr->objPolicyEntryETT->PolicyFromDt = date('d-M-Y', strtotime($policy_start_date));
        $newarr->objPolicyEntryETT->PolicyToDt = date('d-M-Y', strtotime($policy_end_date));
        $newarr->objPolicyEntryETT->PolicyIssueDt = date('d-M-Y');

        $additionData = 
        [
            'headers' =>  [
                'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_JSON'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'requestType' => 'json',
            'method' => 'Proposal Submit',
            'requestMethod' => 'post',
            'section' => 'car',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name . " ($business_type)",
            'transaction_type' => 'proposal',
        ];

            $get_response = getWsData(config('constants.motor.shriram.PROPOSAL_URL_JSON'), $newarr, 'shriram', $additionData);
            $proposal_submit_response = json_decode($get_response['response'], true);
            if ((isset($get_response['status_code']) && $get_response['status_code'] == 200) &&  $proposal_submit_response['MessageResult']['Result'] == 'Success') {
                $proposal_data = $proposal_submit_response['GenerateProposalResult']['CoverDtlList'];
                $quoteId = $proposal_submit_response['GenerateProposalResult']['POL_SYS_ID'];
                $final_od_premium = $final_tp_premium = $cpa_premium = $NetPremium = $addon_premium = $ncb_discount = $total_discount = 0;
                $igst           = $anti_theft = $other_discount =
                $rsapremium     = $pa_paid_driver = $zero_dep_amount =
                $ncb_discount   = $tppd = $final_tp_premium =
                $final_od_premium = $final_net_premium =  $total_od_amount =
                $final_payable_amount = $basic_od = $electrical_accessories =
                $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories =
                $pa_owner       = $voluntary_excess = $pa_unnamed = $key_rplc = $tppd_discount =
                $ll_paid_driver = $personal_belonging = $engine_protection = $consumables_cover = $return_to_invoice = $basic_tp_premium = $imt29amt =
                $geog_Extension_TP_Premium = $geog_Extension_OD_Premium = $geo_ext_one = $geo_ext_two = 0;
                $zero_dep_loading = $engine_protection_loading = $consumable_loading=   0;

                foreach($proposal_data as $key => $value){
                    if (in_array($value['CoverDesc'], [
                        'Basic OD Premium',
                        'Basic OD Premium - 1 Year',
                        'Basic Premium - 1 Year',
                        'Basic Premium - OD',
                        'Daily Expenses Reimbursement - OD'
                    ])) {
                        $basic_od = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], ['Voluntary excess/deductibles','Voluntary excess/deductibles - 1 Year','Voluntary excess/deductibles - OD'])) {
                        $voluntary_excess = abs($value['Premium']);
                    }

                    if ($value['CoverDesc'] == 'OD Total') {
                        $total_od_amount = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], array('Basic TP Premium','Basic TP Premium - 1 Year','Basic TP Premium - 2 Year','Basic TP Premium - 3 Year','Basic Premium - TP')) ) {
                        $basic_tp_premium += $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Total Premium') {
                        $final_net_premium = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'IGST(18.00%)') {
                        $igst = $igst + $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'SGST/UTGST(0.00%)') {
                        $sgst = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'CGST(0.00%)') {
                        $cgst = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Total Amount') {
                        $final_payable_amount = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], array('NCB Discount','NCB Discount ','NCB Discount - OD'))) {
                        $ncb_discount = abs($value['Premium']);
                    }

                    if ( in_array($value['CoverDesc'], array('Depreciation Deduction Waiver (Nil Depreciation) - 1 Year','Depreciation Deduction Waiver (Nil Depreciation)')) ) {
                        $zero_dep_amount = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], array('GR41-Cover For Electrical and Electronic Accessories - 1 Year','GR41-Cover For Electrical and Electronic Accessories', 'GR41-Cover For Electrical and Electronic Accessories - OD')) ) {
                        $electrical_accessories = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], array(
                        'GR42-Outbuilt CNG/LPG-Kit-Cover',
                        'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year',
                        'GR42-Outbuilt CNG\/LPG-Kit-Cover - OD',
                        'InBuilt CNG Cover - OD',
                        'InBuilt  CNG  Cover - OD',
                        'InBuilt CNG Cover'
                    )) && $value['Premium'] != 60) {
                        $lpg_cng = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], array(
                        'GR42-Outbuilt CNG/LPG-Kit-Cover',
                        'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year',
                        'InBuilt CNG Cover',
                        'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year - TP',
                        'GR42-Outbuilt CNG/LPG-Kit-Cover - 2 Year - TP',
                        'GR42-Outbuilt CNG/LPG-Kit-Cover - 3 Year - TP'
                    )) && $value['Premium'] == 60) {
                        $lpg_cng_tp += $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER','CNG/LPG KIT - TP  COVER-GR-42 - 1 YEAR', 'InBuilt CNG Cover - TP', 'InBuilt  CNG  Cover - TP'))) {
                        $lpg_cng_tp = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], array('Cover For Non Electrical Accessories - 1 Year', 'Cover For Non Electrical Accessories','Cover For Non Electrical Accessories - OD'))) {
                        $non_electrical_accessories = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], ['GR36B2-PA Cover For Passengers (Un-Named Persons)','GR36B2-PA Cover For Passengers (Un-Named Persons) - TP'])) {
                        $pa_unnamed = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], ['PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3','PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3 - 1 YEAR'])) {
                        $pa_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER', 'GR36A-PA FOR OWNER DRIVER - 1 YEAR','GR36A-PA FOR OWNER DRIVER - 1 Year','GR36A-PA FOR OWNER DRIVER - TP'])) {
                        $pa_owner = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 1 Year - TP'])) {
                        $pa_owner = $pa_owner + (float)($value['Premium']);
                    }
                    if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 2 Year - TP'])) {
                        $pa_owner = $pa_owner + (float)($value['Premium']);
                    }
                    if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 3 Year - TP'])) {
                        $pa_owner = $pa_owner + (float)($value['Premium']);
                    }
                    if (in_array($value['CoverDesc'], ['Legal Liability Coverages For Paid Driver','Legal Liability Coverages For Paid Driver - TP'])) {
                        $ll_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                    }
                    if ( in_array($value['CoverDesc'], ['TP Total','TP Total']) ) {
                        $final_tp_premium += $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], ['De-Tariff Discount - 1 Year', 'De-Tariff Discount','De-Tariff Discount - OD'])) {
                        $other_discount = abs($value['Premium']);
                    }

                    if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'ANTI-THEFT DISCOUNT-GR-30', 'GR30-Anti Theft Discount Cover - 1 Year - OD'])) {
                        $anti_theft = $value['Premium'];
                    }
                    if ( in_array($value['CoverDesc'], array('Road Side Assistance','Road Side Assistance - 1 Year','Road Side Assistance - OD')) ) {
                        $rsapremium = $value['Premium'];
                    }
                    if ( in_array($value['CoverDesc'], array('KEY REPLACEMENT', 'KEY REPLACEMENT - 1 YEAR','Key Replacement','Key Replacement - 1 Year')) ) {
                        $key_rplc = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], ['Engine Protector','Engine Protector - 1 Year'])) {
                        $engine_protection += $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], ['Consumable','Consumable - 1 Year'])) {
                        $consumables_cover += $value['Premium'];
                    }
                    if ( in_array($value['CoverDesc'], array('Personal Belonging','Personal Belonging - 1 Year')) ) {
                        $personal_belonging = $value['Premium'];
                    }
                    if ( in_array($value['CoverDesc'], array('INVOICE RETURN', 'INVOICE RETURN - 1 YEAR','Return to Invoice - 1 Year')) ) {
                        $return_to_invoice = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'], ['GR39A-Limit The Third Party Property Damage Cover','GR39A-Limit The Third Party Property Damage Cover - 1 Year','GR39A-Limit The Third Party Property Damage Cover - 2 Year','GR39A-Limit The Third Party Property Damage Cover - 3 Year','GR39A-Limit The Third Party Property Damage Cover - TP'])) {
                        $tppd_discount += abs($value['Premium']);
                    }
                    if (in_array($value['CoverDesc'], ['Nil Depreciation Loading'])) {
                        $zero_dep_loading += abs($value['Premium']);
                    }
                    if (in_array($value['CoverDesc'], ['Engine Protector Loading'])) {
                        $engine_protection_loading += abs($value['Premium']);
                    }
                    if (in_array($value['CoverDesc'], ['Consumable Loading'])) {
                        $consumable_loading += abs($value['Premium']);
                    }
                    // GEO Extension
                    if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension','GR4-Geographical Extension - 1 Year','GR4-Geographical Extension - 1 Year - OD','GR4-Geographical Extension - 1 Year - OD','GR4-Geographical Extension - OD','GR4-Geographical Extension']) ) {
                        $geo_ext_one = $value['Premium'];
                    }
                    $validCoverDescs = [
                        'GR4-Geographical Extension - 1 Year - TP',
                        'GR4-Geographical Extension - TP',
                        'GR4-Geographical Extension - 2 Year - TP',
                        'GR4-Geographical Extension - 3 Year - TP'
                    ];

                    if (in_array($value['CoverDesc'], $validCoverDescs)) {
                            $geo_ext_two += (float)$value['Premium'];
                    }

                    if ( in_array($value['CoverDesc'], ['Legal Liability To Employees - 1 Year - TP','Legal Liability To Employees - 2 Year - TP','Legal Liability To Employees - 3 Year - TP','Legal Liability To Employees - TP'])){
                        $imt29amt += $value['Premium'];
                    }
            }

            ShriramPremiumDetailController::saveJsonPremiumDetails($get_response['webservice_id']);

                $final_gst_amount = isset($igst) ? $igst : 0;
            $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount ;
            $basic_od += $zero_dep_loading + $consumable_loading + $engine_protection_loading;
            $final_total_discount = $anti_theft + $ncb_discount + $other_discount + $voluntary_excess + $tppd_discount ;
            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
            $addon_premium = $zero_dep_amount + $rsapremium + $key_rplc + $engine_protection + $consumables_cover + $personal_belonging +$return_to_invoice;

                if ($proposal_submit_response['GenerateProposalResult']['PROPOSAL_NO'] == null) {
                    return ([
                        'status' => false,
                        'message' => "The proposal number cannot have a null value",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                    ]);
                }
                UserProposal::where('user_product_journey_id', $enquiryId)->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'tp_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'tp_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'final_payable_amount' => $final_payable_amount,
                    'proposal_no' => $proposal_submit_response['GenerateProposalResult']['PROPOSAL_NO'],
                    'od_premium'            =>   $total_od_amount,
                    'tp_premium'            => $final_tp_premium,
                    'addon_premium'         => $addon_premium,
                    'total_premium'         => $final_net_premium,
                    'total_discount'        => ($ncb_discount  +$tppd_discount),
                    'pol_sys_id'            => $quoteId

                ]);
                $data['user_product_journey_id'] = $enquiryId;
                $data['ic_id'] = $master_policy;
                $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                $data['proposal_id'] = $user_proposal->user_proposal_id;
                updateJourneyStage($data);
                return ([
                    'status' => true,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Proposal Submitted Successfully!"
                ]);
            }
            else{
                return ([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Proposal Cannot be submitted!"
                ]);
            }
        }

}