<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use DateTime;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\SelectedAddons;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;

class orientalPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function make($request)
    {

        // PAYMENT
        $proposal       = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        $enquiryId      = customDecrypt($request->enquiryId);

        $icId           = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        $quote_log_id   = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();

        $checksum       = self::create_checksum($enquiryId ,$request);

        $user_proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $additional_details     = json_decode($proposal->additional_details);

        $additional_details->oriental->order_id = $checksum['transaction_id'];

        $proposal->additional_details = json_encode($additional_details);
        $proposal->save();

        $return_data = [
            'form_action'       => config('constants.IcConstants.oriental.bike.END_POINT_URL_PAYMENT_GATEWAY_BIKE'),
            'form_method'       => 'POST',
            'payment_type'      => 0, // form-submit
            'form_data'         => [
                'msg'               => $checksum['msg'],
            ]
        ];
        $data['user_product_journey_id']    = $user_proposal->user_product_journey_id;
        $data['ic_id']                      = $user_proposal->ic_id;
        $data['stage']                      = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        $checksum_string = $checksum['msg'];

        $checksum = explode('|',$checksum['msg']);

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $proposal->user_product_journey_id)
        ->where('user_proposal_id', $proposal->user_proposal_id)
        ->update([
            'active' => 0
        ]);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $checksum[1],
            'amount'                    => $proposal->final_payable_amount,
            'xml_data'                  => $checksum_string,
            'payment_url'               => config('constants.IcConstants.oriental.bike.END_POINT_URL_PAYMENT_GATEWAY_BIKE'),
            'return_url'                => route(
                'bike.payment-confirm',
                [
                    'oriental',
                    'user_proposal_id'      => $proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]
            ),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);


        return response()->json([
            'status'    => true,
            'msg'       => "Payment Reidrectional",
            'data'      => $return_data,
        ]);


    }

    public static  function  create_checksum($enquiryId ,$request)
    {

        $policy_id=$request['policyId'];
        DB::enableQueryLog();
        $data = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();

        $new_pg_transaction_id = strtoupper(config('constants.IcConstants.oriental.bike.BIKE_PAYMENT_IDENTIFIER')).date('Ymd').time().rand(10,99);

        $str_arr = [
            config('constants.IcConstants.oriental.bike.BIKE_PAYMENT_MERCHANT_ID'),
            $new_pg_transaction_id,
            'NA',
            (env('APP_ENV') == 'local') ? '1.00' :$data->final_payable_amount,
            'NA',
            'NA',
            'NA',
            'INR',
            'NA',
            'R',
            config('constants.IcConstants.oriental.bike.BIKE_PAYMENT_USER_ID'),
            'NA',
            'NA',
            'F',
            $data->proposal_no,
            config('constants.IcConstants.oriental.bike.BIKE_PAYMENT_IDENTIFIER'),
            customDecrypt(customEncrypt($enquiryId)),
            $data->email,
            $data->chassis_number,
            $data->first_name.' '.$data->last_name,
            'NA',
            route('bike.payment-confirm', [
                    'oriental',
                    'user_proposal_id'      => $data['user_proposal_id'],
                    'policy_id'             => $policy_id
            ]),
        ];

        $msg_desc = implode('|', $str_arr);
        $checksum = strtoupper(hash_hmac('sha256', $msg_desc, config('constants.IcConstants.oriental.bike.BIKE_PAYMENT_CHECKSUM_KEY')));

        $new_string = $msg_desc.'|'.$checksum;


        $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
            ->where('user_proposal_id', $data->user_proposal_id)
            ->update([
                'unique_proposal_id'                 => $new_pg_transaction_id,
            ]);


        $quries = DB::getQueryLog();

        return [
            'status' => 'true',
            'msg' => $new_string,
            'transaction_id' => $new_pg_transaction_id
        ];
    }
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        $user_proposal = UserProposal::find($request->user_proposal_id);
        $user_proposal_id = $request->user_proposal_id;
        $master_policy_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);

        $response   = $_REQUEST['msg'];
        $response   = explode('|', $response);

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('user_proposal_id', $user_proposal->user_proposal_id)
        ->where('active', 1)
        ->update([
            'response'      => $request->All(),
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        if ($response[14] == '0300') {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update([
                    'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
                
            sleep(rand(0,5));
            $createPolicyResponse = self::createPolicyService($user_proposal, $productData, $response[1]);

            if(!$createPolicyResponse['status'])
            {
                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS')); 
            }

            $policy_details = DB::table('policy_details as pd')
                ->where([
                    'pd.proposal_id'   => $user_proposal_id
                ])
                ->first();

            if($policy_details->pdf_url == null || $policy_details->pdf_url == '')
            {
                $PolicyPDFResponse = self::PolicyPDFService($user_proposal, $productData);

                return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
            }
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
        } else {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);

            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','FAILURE'));
        }
    }

    static public function serverToServer($request)
    {
        $response   = $_REQUEST['msg'];
        $response   = explode('|', $response);
        $enquiry_id = $response['18'];
        $user_product_journey_id = customDecrypt($enquiry_id);


        $payment_request_data = DB::table('payment_request_response as prr')
            ->where([
                'prr.user_product_journey_id'   => $user_product_journey_id,
                'prr.active'                    => 1
            ])
            ->first();

        $user_proposal_id = $payment_request_data->user_proposal_id;

        $user_proposal = UserProposal::find($user_proposal_id);

        $productData = getProductDataByIc($request->master_policy_id);

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('user_proposal_id', $user_proposal->user_proposal_id)
        ->where('active', 1)
        ->update([
            'response'      => $request->All(),
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        if ($response[14] != '0300') {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);

            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','FAILURE'));
        }

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('user_proposal_id', $user_proposal->user_proposal_id)
        ->update([
            'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
        ]);

        $policy_details = DB::table('policy_details as pd')
            ->where([
                'pd.proposal_id'   => $user_proposal_id
            ])
            ->first();

        // if($policy_details == null)
        if(empty($policy_details) || empty($policy_details->policy_number) || $policy_details->policy_number == '[]')
        {
            
            sleep(rand(5,10));
            $createPolicyResponse = self::createPolicyService($user_proposal, $productData, $response[1]);

            if(!$createPolicyResponse['status'])
            {
                return $createPolicyResponse;
            }
        }

        $policy_details = DB::table('policy_details as pd')
            ->where([
                'pd.proposal_id'   => $user_proposal_id
            ])
            ->first();

        // if($policy_details->pdf_url == null || $policy_details->pdf_url == '')
        if(empty($policy_details->pdf_url))
        {
            $PolicyPDFResponse = self::PolicyPDFService($user_proposal, $productData);

            return $PolicyPDFResponse;
        }

        $policy_details = DB::table('policy_details as pd')
            ->where([
                'pd.proposal_id'   => $user_proposal_id
            ])
            ->first();

        return [
            'status' => ($policy_details->pdf_url == null || $policy_details->pdf_url == '') ? false : true,
            'data' => [
                'pdf_url' => $policy_details->pdf_url,
                'policy_number' => $policy_details->policy_number,
            ]
        ];

        
    }

    static public function createPolicyService($proposal, $product_data, $orderid)
    {
        $createPolicyRequestArray = [
            'Body' => [
                'CreatePolicy' => [
                    'objCreatePolicyETT' => [
                        'PROPOSAL_NO' => $proposal->proposal_no, //'POLBZR_2',
                        'MODE_OF_PAY' => 3,
                        'CHEQUE_TYPE' => '',
                        'CHEQUE_NO' => '', 
                        'CHEQUE_DT' => '',
                        'CHEQUE_BANK_NAME' => '',
                        'CHEQUE_BRANCH' => '',
                        'CHEQUE_AMT' => $proposal->final_payable_amount,
                        'CHEQUE_ISSUED_BY' => '',
                        'PAYINSLIP_NO' => '',
                        'PAYINSLIP_DT' => '',
                        'UNIQUE_REF_NO' => $orderid, 
                        'FLEX_01' => '',
                        'FLEX_02' => '',
                        'FLEX_03' => '', //manf year
                        'FLEX_04' => '',
                        'FLEX_05' => '',
                        'FLEX_06' => '',
                        'FLEX_07' => '',
                        'FLEX_08' => $proposal->proposal_no, //GSTNO
                        'FLEX_09' => config("constants.motor.oriental.ORIENTAL_CKYC_APPID"), //towing
                        'FLEX_10' => config("constants.motor.oriental.ORIENTAL_CKYC_APPKEY"),
                    ],
                    '_attributes' => [
                        "xmlns" => "http://MotorService/",
                    ]
                ]
            ]
        ];

        $additional_data = [
            'enquiryId' => $proposal->user_product_journey_id,
            'headers' => [
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Bike',
            'method' => 'Policy Number Generation - Payment',
            'transaction_type' => 'proposal',
            'productName'       => $product_data->product_name,
        ];

        $root = [
            'rootElementName' => 'Envelope',
            '_attributes' => [
                "xmlns" => "http://schemas.xmlsoap.org/soap/envelope/",
            ]
        ];

        $createPolicyRequestXML = ArrayToXml::convert($createPolicyRequestArray, $root, false, 'utf-8');

        $get_response = getWsData(config('constants.motor.oriental.QUOTE_URL'), $createPolicyRequestXML, 'oriental', $additional_data);
        $createPolicyResponse = $get_response['response'];

        if($createPolicyResponse == '')
        {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);

            return [
                'status' => false,
                'msg' => 'No response from service',
                'data' => []
            ]; 
        }

        $createPolicyResponseArray = XmlToArray::convert($createPolicyResponse);

        $createPolicyResponseArray = $createPolicyResponseArray['soap:Body']['CreatePolicyResponse']['CreatePolicyResult'];

        // if (isset($createPolicyResponseArray['POLICY_NO_OUT']) && $createPolicyResponseArray['POLICY_NO_OUT'] != '')
        if (isset($createPolicyResponseArray['POLICY_NO_OUT']) && !empty($createPolicyResponseArray['POLICY_NO_OUT']))
        {
            $policy_details = DB::table('policy_details as pd')->where(['pd.proposal_id'   => $proposal->user_proposal_id])->first();

            if(!empty($policy_details) && !empty($policy_details->policy_number) && $policy_details->policy_number != '[]')
            {
                return [
                    'status' => true,
                    'msg' => 'success',
                    'data' => [
                        'policy_no' => $policy_details->policy_number
                    ]
                ]; 
            }

            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);

            PolicyDetails::updateOrCreate(
                [ 'proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_number'     => $createPolicyResponseArray['POLICY_NO_OUT'],
                    'premium'           => $proposal->final_payable_amount
                ]
            );

            UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_no' => $createPolicyResponseArray['POLICY_NO_OUT']
                ]);

            return [
                'status' => true,
                'msg' => 'success',
                'data' => [
                    'policy_no' => $createPolicyResponseArray['POLICY_NO_OUT']
                ]
            ]; 
        }
        else
        {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
            return [
                'status' => false,
                'msg' => 'Policy No not Availabel',
                'data' => []
            ];  
        }
    }


    static public function PolicyPDFService($proposal, $product_data)
    {

        $policy_details = DB::table('policy_details as pd')
            ->where([
                'pd.proposal_id'   => $proposal->user_proposal_id
            ])
            ->first();


        $policyPDFRequestArray = [
            'Body' => [
                'PDF_Policy_Validator' => [
                    'USER_NAME' => config('constants.IcConstants.oriental.ORIENTAL_USER_NAME_POLICY_PDF'),
                    'USER_PASWD' => config('constants.IcConstants.oriental.ORIENTAL_PASSWROD_POLICY_PDF'),
                    'POL_NO' => $policy_details->policy_number,
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/",
                    ]
                ],
            ],
        ];

        $additional_data = [
            'enquiryId' => $proposal->user_product_journey_id,
            'headers' => [
                'Content-Type'  => 'text/xml; charset="utf-8"',
                'SOAPAction'    => 'http://tempuri.org/PDF_Policy_Validator'
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Bike',
            'method' => 'Policy PDF Generation - Payment',
            'transaction_type' => 'proposal',
            'productName'       => $product_data->product_name,
        ];

        $root = [
            'rootElementName' => 'Envelope',
            '_attributes' => [
                "xmlns" => "http://schemas.xmlsoap.org/soap/envelope/",
            ]
        ];

        $policyPDFRequestXML = ArrayToXml::convert($policyPDFRequestArray, $root, false, 'utf-8');

        $get_response = getWsData(config('constants.motor.oriental.ORIENTAL_PDF_URL'), $policyPDFRequestXML, 'oriental', $additional_data);
        $policyPDFResponse = $get_response['response'];

        if($policyPDFResponse == '')
        {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);

            return [
                'status' => false,
                'msg' => 'No response from service',
                'data' => []
            ]; 
        }

        $policyPDFResponseArray = XmlToArray::convert($policyPDFResponse);

        $policyPDFResponseArray = $policyPDFResponseArray['soap:Body']['PDF_Policy_ValidatorResponse'];

        if (isset($policyPDFResponseArray['PDF_Policy_ValidatorResult']) && $policyPDFResponseArray['PDF_Policy_ValidatorResult'] != '')
        {
            $pdf_doc_url = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'oriental/'.md5($proposal->user_proposal_id).'.pdf';

            Storage::put(
                $pdf_doc_url,
                base64_decode($policyPDFResponseArray['PDF_Policy_ValidatorResult'])
            );

            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED']
            ]);

            PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_number' => $policy_details->policy_number,
                    'ic_pdf_url' => '',
                    'pdf_url' => $pdf_doc_url,
                    'status' => 'SUCCESS'
                ]
            );

            return [
                'status' => true,
                'msg' => 'success',
                'data' => [
                    'policy_no' => $policy_details->policy_number,
                    'pdf_url' => $pdf_doc_url,
                    'pdf_link' => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'oriental/'. md5($proposal->user_proposal_id).'.pdf'),
                    'policy_number' => $policy_details->policy_number,
                ]
            ]; 
        }
        else
        {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
            return [
                'status' => false,
                'msg' => 'Policy PDF data not Availabel',
                'data' => []
            ];
        }
    }


    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $payment = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id,
            'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
            'active' => 1
        ])->get()->first();

        if (empty($payment)) {
            $payment_check = self::check_payment_status($user_product_journey_id, $request->product_name);

            if (empty($payment_check) || $payment_check['status'] == false) {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Payment is pending'
                ]);
            }
        }

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin(
                'policy_details as pd',
                'pd.proposal_id','=','prr.user_proposal_id'
            )
            ->join(
                'user_proposal as up',
                'up.user_product_journey_id','=','prr.user_product_journey_id'
            )
            ->where([
                'prr.user_product_journey_id'   => $user_product_journey_id,
                'prr.active'                    => 1,
                'prr.status'                    => STAGE_NAMES['PAYMENT_SUCCESS']
            ])
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no','up.unique_proposal_id', 'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id', 'prr.response as response'
            )
            ->first();

        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        $master_policy_id = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);

        $additional_details     = json_decode($proposal->additional_details);
        $orderId = $additional_details->oriental->order_id;

        $iscreatePolicy = true;

        // if($policy_details == null)
        if(empty($policy_details) || empty($policy_details->policy_number) || $policy_details->policy_number == '[]')
        {
            $createPolicyResponse = orientalPaymentGateway::createPolicyService($proposal, $productData, $orderId);

            if(!$createPolicyResponse['status'])
            {
                return $createPolicyResponse;
            }

            $policy_details = (object)[
                'policy_number' => $createPolicyResponse['data']['policy_no'],
                'pdf_url'    => ''
            ];

            $iscreatePolicy = false;
        }

        if($policy_details->pdf_url == '')
        {
            if($iscreatePolicy && ($policy_details->policy_number === null || $policy_details->policy_number == ''))
            {
                $createPolicyResponse = orientalPaymentGateway::createPolicyService($proposal, $productData, $orderId);
                
                if(!$createPolicyResponse['status'])
                {
                    return $createPolicyResponse;
                }
            }
            $pdf_response_data = self::PolicyPDFService($proposal, $productData);
        }
        else
        {
            $pdf_response_data = [
                'status' => true,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'pdf_link' => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'oriental/'. md5($proposal->user_proposal_id).'.pdf'),
                    'policy_number' => $policy_details->policy_number,
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }

    static public function check_payment_status($enquiry_id, $section)
    {
        $transactiondata = PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
            ->select('order_id', 'id', 'user_proposal_id', 'user_product_journey_id')
            ->get();

        if (empty($transactiondata)) {
            return [
                'status' => false
            ];
        }

        $api_merchant_id = config('constants.IcConstants.oriental.bike.BIKE_PAYMENT_MERCHANT_ID');
        $payment_checksum = config('constants.IcConstants.oriental.bike.BIKE_PAYMENT_CHECKSUM_KEY');

        foreach ($transactiondata as $value) {
            if (empty($value->order_id)) {
                continue;
            }
            $query_api_array = [
                '0122',
                $api_merchant_id,
                $value->order_id,
                date('Ymdhis'),
            ];

            $query_api = implode('|', $query_api_array);
            $checksum = strtoupper(hash_hmac('sha256', $query_api, $payment_checksum));

            $new_string = $query_api . '|' . $checksum;
            $query_api_request = [
                'msg' => $new_string
            ];

            $additional_payment_data = [
                'requestMethod' => 'post',
                'Authorization' => '',
                'proposal_id'   => $value->user_proposal_id,
                'enquiryId' => $value->user_product_journey_id,
                'section' => $section,
                'method'        => 'Query API - Payment Status',
                'type'          => 'Query API',
                'transaction_type' => 'proposal'
            ];

            $get_response = getWsData(
                Config('constants.IcConstants.oriental.QUERY_API_URL_ORIENTAL'),
                $query_api_request,
                'oriental',
                $additional_payment_data
            );

            $query_api_data = $get_response['response'];

            if (!empty($query_api_data) && $query_api_data != '{}') {
                $query_api_response = explode('|', $query_api_data);

                if ($query_api_response[15] == '0300') {

                    PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                        ->update([
                            'active'  => 0
                        ]);

                    PaymentRequestResponse::where('id', $value->id)
                        ->update([
                            'response'      => implode('|', $query_api_response),
                            'updated_at'    => date('Y-m-d H:i:s'),
                            'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'active'        => 1
                        ]);
                    $proposal = UserProposal::where('user_product_journey_id', $value->user_product_journey_id)
                        ->first();
                    $additional_details = json_decode($proposal->additional_details);
                    $additional_details->billdesk_txn_date   = $query_api_response[14];
                    $additional_details->billdesk_txn_ref_no = $query_api_response[3];
                    $additional_details = json_encode($additional_details);
                    UserProposal::where('user_product_journey_id', $value->user_product_journey_id)
                        ->where('user_proposal_id', $value->user_proposal_id)
                        ->update([
                            'additional_details' => $additional_details,
                            'unique_proposal_id' =>$value->order_id
                        ]);
                    $data['user_product_journey_id']    = $enquiry_id;
                    $data['proposal_id']                = $value->user_proposal_id;
                    $data['ic_id']                      = '44';
                    $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                    updateJourneyStage($data);
                    return [
                        'status'    => true
                    ];
                }
            }
        }
        return [
            'status'    => false,
            'message'   => 'No response Form Query API service'
        ];
    }
}
