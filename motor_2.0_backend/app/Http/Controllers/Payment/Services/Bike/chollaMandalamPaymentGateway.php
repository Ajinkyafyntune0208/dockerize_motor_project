<?php

namespace App\Http\Controllers\Payment\Services\Bike;

use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterPolicy;
use App\Models\MasterPremiumType;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\chollamandalammodel;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Support\Str;
use Config;
use Illuminate\Http\Request;



include_once app_path() . '/Helpers/BikeWebServiceHelper.php';


class chollaMandalamPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {

        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();


        $enquiryId = customDecrypt($request->enquiryId);

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();
        ;
        $checksum=chollaMandalamPaymentGateway::create_checksum($enquiryId ,$request);

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $return_data = [
            'form_action' => config('constants.IcConstants.cholla_madalam.PAYMENT_GATEWAY_ID_CHOLLA_MANDALAM'),
            'form_method' => 'POST',
            'payment_type' => 0, // form-submit
            'form_data' => [
                'msg' => $checksum['msg'],
            ]
        ];
        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        $checksum=explode('|',$checksum['msg']);

        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
            ->update(['active' => 0]);

        PaymentRequestResponse::insert([
            'quote_id' => $quote_log_id,
            'user_product_journey_id' => $enquiryId,
            'user_proposal_id' => $proposal->user_proposal_id,
            'ic_id' => $icId,
            'order_id' => $checksum[1],
            'amount' => $proposal->final_payable_amount,
            'payment_url' => config('constants.IcConstants.cholla_madalam.PAYMENT_GATEWAY_ID_CHOLLA_MANDALAM'),
            'return_url' => route('bike.payment-confirm', ['cholla_mandalam', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'active' => 1,
            'xml_data' => json_encode($return_data)
        ]);


        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);


    }

    public static  function  create_checksum($enquiryId ,$request)
    {

        $policy_id=$request['policyId'];
        DB::enableQueryLog();
        $data = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();


        // $new_pg_transaction_id = strtoupper(config('constants.IcConstants.cholla_madalam.BROKER_NAME')).customEncrypt($enquiryId) . date('His', strtotime(now()));
        $new_pg_transaction_id = strtoupper(config('constants.IcConstants.cholla_madalam.BROKER_NAME')). substr(strtoupper(md5(mt_rand())), 0, 7) . date('His', strtotime(now()));

        $str_arr = [
            config('constants.IcConstants.cholla_madalam.PAYMENT_MERCHANT_ID_CHOLLA_MANDALAM'),
            $new_pg_transaction_id,
            'NA',
            (env('APP_ENV') == 'local') ? config('constants.IcConstants.cholla_madalam.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM') :$data->final_payable_amount,
            'NA',
            'NA',
            'NA',
            'INR',
            'NA',
            'R',
            config('constants.IcConstants.cholla_madalam.PAYMENT_SECURITY_ID_CHOLLA_MANDALAM'),
            'NA',
            'NA',
            'F',
            $data->proposal_no,
            $data->vehicale_registration_number,
            $data->mobile_number,
            $data->email,
            $data->chassis_number,
            $data->first_name.' '.$data->last_name,
            'NA',
            route('bike.payment-confirm', ['cholla_mandalam','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),

        ];

        $msg_desc = implode('|', $str_arr);
        $checksum = strtoupper(hash_hmac('sha256', $msg_desc, config('constants.IcConstants.cholla_madalam.PAYMENT_CHECKSUM_CHOLLA_MANDALAM')));

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

    public static function confirm($request)
    {
        $cholla_model = new chollamandalammodel();
        $request_data = $request->all();

        $proposal = UserProposal::where('user_product_journey_id', $request_data['enquiry_id'])->first();
        if(empty($proposal))
        {
            return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        if($request_data!=null && isset($_REQUEST['msg'])) {
            $response = $_REQUEST['msg'];
            $response = explode('|', $response);

            if ($response[14] == '0300') {

                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update(
                        [
                            'response' => implode(' ', $request_data),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]
                    );


                $additional_details=json_decode($proposal->additional_details);
                $additional_details->billdesk_txn_date=$response[13];
                $additional_details->billdesk_txn_ref_no=$response[2];
                $additional_details=json_encode($additional_details);


                $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'additional_details' => $additional_details,

                    ]);


                $pdf_data = $cholla_model->bike_retry_pdf(base64_encode($response[1]), $request_data['policy_id'], $proposal->user_product_journey_id);

                if ($pdf_data['status'] || $pdf_data['status']== true) {
                    $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                    $data['ic_id'] = $proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                    updateJourneyStage($data);
                    PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([

                        'policy_number' => $pdf_data['policy_no'],
                        'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                        'policy_start_date' => $proposal->policy_start_date,
                        'status' =>  'Success'

                    ]);
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
                    //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));
                } else {
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
                    //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));
                }

            } else {

                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update(
                        [
                            'response' => implode(' ', $request_data),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => STAGE_NAMES['PAYMENT_FAILED']
                        ]
                    );
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                $enquiryId = $proposal->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','FAILURE'));
                //return redirect(Config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));
            }


        } else {
            PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update(
                    [
                        'response' => 'response',
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => STAGE_NAMES['PAYMENT_FAILED']
                    ]
                );
            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            
            $enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
            //return redirect(Config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));

        }
    }

    static public function generatePdf($request){
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $cholla_model = new chollamandalammodel();
        $policy_details = PaymentRequestResponse::leftjoin('policy_details as pd','pd.proposal_id','=','payment_request_response.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','payment_request_response.user_product_journey_id')
            ->where('payment_request_response.user_product_journey_id',$user_product_journey_id)
            ->where(array('payment_request_response.active'=>1))
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','payment_request_response.order_id'
            )
            ->first();

             if($policy_details == null){
                 $pdf_response_data = [
                     'status' => false,
                     'msg'    => 'Payment Details Data Not Found'
                 ];
                 return response()->json($pdf_response_data);
             }
        $payment_check = self::check_payment_status($user_product_journey_id, $request->product_name);
        // if(!$payment_check['status'])
        // {
        //     $pdf_response_data = [
        //         'status' => false,
        //         'msg'    => 'Payment is Pending'
        //     ];
        //     return response()->json($pdf_response_data);
        // }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        if($policy_details->ic_pdf_url == '')
        {
            $pdf_data = $cholla_model->bike_retry_pdf(base64_encode($policy_details->order_id),$request->master_policy_id, $proposal->user_product_journey_id);

            if ($pdf_data['status'] || $pdf_data['status']== true) {
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data);
                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([

                    'policy_number' => $pdf_data['policy_no'],
                    'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                    'policy_start_date' => $proposal->policy_start_date,
                    'status' =>  'Success'

                ]);
                $pdf_response_data = [
                    'status' => true,
                    'msg' => 'sucess',
                    'data' => [
                        'policy_number' => $pdf_data['policy_no'],
                        'pdf_link'      => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf')
                    ]
                ];

            } else {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'Error Occured',
                    'dev'    => $pdf_data['message']
                ];
            }



        } else {
            $pdf_response_data = [
                'status' => true,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => file_url($policy_details->pdf_url)
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }

    static public function ReconService(Request $request){
        $cholla_model = new chollamandalammodel();
        $response = $cholla_model->update_transaction_summary((object)$request->all(),'bike');
        return response()->json($response);
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
        $api_merchant_id = config('constants.IcConstants.cholla_madalam.QUERY_API_MERCHANT_ID_CHOLLA_MANDALAM');
        $payment_checksum = config('constants.IcConstants.cholla_madalam.PAYMENT_CHECKSUM_CHOLLA_MANDALAM');
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
                Config('constants.IcConstants.cholla_madalam.QUERY_API_URL_CHOLLA_MANDALAM'),
                $query_api_request,
                'cholla_mandalam',
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
                    $data['ic_id']                      = '30';
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
