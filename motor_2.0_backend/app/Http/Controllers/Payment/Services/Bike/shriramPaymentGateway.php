<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
include_once app_path().'/Helpers/IcHelpers/ShriramHelper.php';

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
        $quote_log_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->pluck('quote_id')
                ->first();
        $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

        DB::table('payment_request_response')
              ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
              ->update(['active' => 0]);

        if (config('constants.motor.shriram.SHRIRAM_BIKE_JSON_REQUEST_TYPE') != 'JSON')
        {
            $payment_url = config('constants.motor.shriram.PAYMENT_URL');

            $return_data = [
                'form_action' => $payment_url,
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'PolicySysID' => $user_proposal->pol_sys_id,
                    'ReturnURL' => route('bike.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                    //'DbFrom' => 'NOVA',
                    //'QuoteId' => $user_proposal->pol_sys_id,
                    //'amount' => $user_proposal->final_payable_amount,
                    //'application' => 'OLA',
                    //'createdBy' => 'online_agent@gmail.com',
                    //'description' => '-',
                    //'isForWeb' => 'true',
                    //'paymentFrom' => 'CCAVENUE',
                    //'return_url' => route('bike.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                    //'sourceUrl' => route('bike.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                ],
            ];

        }
        else
        {
            $payment_url = config('constants.motor.shriram.PAYMENT_URL_JSON');
            $paymentFrom = config('constants.motor.shriram.PAYMENT_URL_JSON_PAYMENTFROM');

            $return_data = [
                'form_action' => $payment_url,
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    "DbFrom" =>  "NOVA",
                    "description" =>  $user_proposal->proposal_no,
                    "isForWeb" =>  "true",             
                    'createdBy' => $user_proposal->email,
                    'paymentFrom' =>  $paymentFrom,
                    'prodCode'  => $user_proposal->product_code,
                    'QuoteId' => $user_proposal->pol_sys_id,
                    'amount' => $user_proposal->final_payable_amount,
                    'sourceUrl' => route('bike.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id, 'EnquiryId' => customEncrypt($user_proposal->user_product_journey_id)]),
                    'DbFrom' => 'NOVA',
                    'application' => config('SHRIRAM_BIKE_APPLICATION_NAME'),
                    ],
                ];
            
        }

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $user_proposal->user_product_journey_id,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            'payment_url'               => $payment_url,
            'return_url'                => route('bike.payment-confirm', ['shriram', 'user_proposal_id' => $user_proposal->user_proposal_id,'EnquiryId' => customEncrypt($user_proposal->user_product_journey_id)]),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'created_at'                => date('Y-m-d H:i:s'),
            'proposal_no'               => $user_proposal->proposal_no,
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
        $user_proposal = UserProposal::find($request->user_proposal_id);
        if (!$user_proposal) {
            return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $user_proposal->user_product_journey_id)) {
            \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/' . $user_proposal->user_product_journey_id);
        }
        if(empty($request->ProposalNumber)) {
            /* return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)])); */
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','FAILURE'));
        }
        
        if(config('constants.motor.shriram.SHRIRAM_BIKE_JSON_REQUEST_TYPE') != 'JSON')
        {//xml_flow
            DB::table('payment_request_response')
            ->where('order_id', $request->ProposalNumber)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => $request->Status == 'SUCCESS' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                'proposal_no'   => $request->ProposalNumber,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($request->ErrorCode != '0') 
            {
                $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                $data['ic_id'] = $user_proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                /* return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)])); */
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','FAILURE'));
            }
        
            if ($request->PolicyNumber != '') 
            {
                PolicyDetails::updateOrCreate(
                    [ 'proposal_id' => $user_proposal->user_proposal_id ],
                    [
                        'policy_number'     => $request->PolicyNumber,
                        'created_on'        => date('Y-m-d H:i:s')
                    ]
                );
                UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->where('user_proposal_id', $user_proposal->user_proposal_id)
                    ->update(['policy_no' => $request->PolicyNumber]);



                //Generate Policy PDF - Start
                $doc_link = config('constants.motor.shriram.SHRIRAM_MOTOR_GENERATE_PDF').'PolSysId=' . $request->PolicySysID . '&LogoYN=Y';
    
                $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'shriram/' . md5($request->user_proposal_id) . '.pdf';
    
                try {
                    //$proposal_pdf = Storage::put($pdf_name, file_get_contents($doc_link));
                    $proposal_pdf = Storage::put($pdf_name, httpRequestNormal($doc_link,'GET',[],[],[],[],false)['response']);
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
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
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
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
                }
                else
                {
                    $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                    $data['ic_id'] = $user_proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($data);
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
                }
            }
            else
            {
                if(empty($request->PolicyNumber) && $request->Status == 'SUCCESS'){
                    $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                    $data['ic_id'] = $user_proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
                    updateJourneyStage($data);
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
                }
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
            }
        } 
        else 
        { //json_flow
            $payment_status = true;

            if ((isset($request->ResponseCode) && in_array($request->ResponseCode, ['Failure', 'Aborted'])) || !isset($request->Status) || $request->Status !== 'Successful Completion') {
                $payment_status = false;
            }

            DB::table('payment_request_response')
            ->where('order_id', $request->ProposalNumber)
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => $payment_status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'], //$request->ResponseCode == 'Successful Completion' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'] ,
                'proposal_no'   => $request->ProposalNumber,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$payment_status) 
            {
                $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
                //$data['ic_id'] = $user_proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                /* return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)])); */
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE'));
            }

            $policyDetails = DB::table('payment_request_response')
            ->where('order_id', $request->ProposalNumber)
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('active', 1)
            ->first();

            if (!empty($request->PolicyNumber)) 
            {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal->user_proposal_id],
                    [
                        'policy_number'     => $request->PolicyNumber
                    ]
                );

                if (!empty($request->PolicyURL)) 
                {
                    PolicyDetails::updateOrCreate(
                        ['proposal_id' => $user_proposal->user_proposal_id],
                        [
                            'ic_pdf_url'     => $request->PolicyURL

                        ]
                    );
                    downloadPDFFromURL($request->PolicyNumber, $request->PolicyURL, $user_proposal, 'BIKE');
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                } 
                else 
                {
                    policyPDFJSON($user_proposal, $request->PolicyNumber);
                    return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                }
            } 
            else 
            {
                paymentstatuscheck($policyDetails, $user_proposal);
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
            }
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
        'pd.policy_number','pd.pdf_url','pd.ic_pdf_url', 'prr.xml_data', 'prr.order_id'
        )
        ->first();
        if (!$policy_details) 
        {
            return response()->json([
                'status' => false,
                'msg' => 'Details not found'
            ]);
        }
        if($policy_details->ic_pdf_url != '')
        {
            //Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'shriram/'. md5($policy_details->user_proposal_id). '.pdf', file_get_contents($policy_details->ic_pdf_url));
            Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'shriram/'. md5($policy_details->user_proposal_id). '.pdf', httpRequestNormal($policy_details->ic_pdf_url,'GET',[],[],[],[],false)['response']);
            $pdf_url = file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'shriram/'. md5($policy_details->user_proposal_id). '.pdf');
            updateJourneyStage([
                'user_product_journey_id' => $user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED']
            ]);
            $pdf_response_data = [
                'status' => true,
                'msg' => 'sucess',
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => $pdf_url
                ]
            ];
        }
        else
        {
            if (strtolower(config('constants.motor.shriram.SHRIRAM_BIKE_JSON_REQUEST_TYPE')) == 'json') 
            {
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
                        'section' => 'Bike',
                        'method' => 'Document Generation',
                        'transaction_type' => 'proposal',
                    ]
            );
            $fetch_data = $get_response['response'];
            $response = XmlToArray::convert($fetch_data);
            //Generate Policy PDF - Start
            $doc_link = config('constants.motor.shriram.SHRIRAM_MOTOR_GENERATE_PDF').'PolSysId=' . $proposal->pol_sys_id . '&LogoYN=Y';

            $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'shriram/' . md5($policy_details->user_proposal_id) . '.pdf';
            //$pdf_response = Storage::put($pdf_name, file_get_contents($doc_link));
            $pdf_response = Storage::put($pdf_name, httpRequestNormal($doc_link,'GET',[],[],[],[],false)['response']);
            if($pdf_response)
            {  
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $policy_details->user_proposal_id],
                    [
                        'policy_number' => $proposal->policy_no,
                        'ic_pdf_url' => $doc_link,
                        'pdf_url' => $pdf_name,
                        'status' => 'SUCCESS'
                    ]
                );
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
                    'msg'    => 'Error Occured',
                    'dev'    => $pdf_response
                ];
            }
        }
        return response()->json($pdf_response_data);
    }
}
