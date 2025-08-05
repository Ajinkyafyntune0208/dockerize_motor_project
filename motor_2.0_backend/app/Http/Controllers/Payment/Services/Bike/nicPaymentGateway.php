<?php

namespace App\Http\Controllers\Payment\Services\Bike;

use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\MasterProduct;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class nicPaymentGateway
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

        $checksum       = nicPaymentGateway::create_checksum($enquiryId ,$request);

        $user_proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $additional_details     = json_decode($proposal->additional_details);

        $additional_details->nic->order_id = $checksum['transaction_id'];

        $proposal->additional_details = json_encode($additional_details);
        $proposal->save();

        $return_data = [
            'form_action'       => config('constants.IcConstants.nic.PAYMENT_GATEWAY_URL_NIC'),
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

        $msg_desc = explode('|',$checksum['msg']);

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
            'order_id'                  => $msg_desc[1],
            'amount'                    => $proposal->final_payable_amount,
            'payment_url'               => config('constants.IcConstants.nic.PAYMENT_GATEWAY_URL_NIC'),
            'return_url'                => route(
                'bike.payment-confirm',
                [
                    'nic',
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

        $new_pg_transaction_id = strtoupper(config('constants.IcConstants.nic.PAYMENT_MERCHANT_ID_NIC')).date('Ymd').time().rand(10,99);

        $str_arr = [
            config('constants.IcConstants.nic.PAYMENT_MERCHANT_ID_NIC'),
            $new_pg_transaction_id,
            'NA',
            (env('APP_ENV') == 'local') ? '2.00' :$data->final_payable_amount,
            'NA',
            'NA',
            'NA',
            'INR',
            'NA',
            'R',
            config('constants.IcConstants.nic.PAYMENT_USER_ID_NIC'),
            'NA',
            'NA',
            'F',
            $data->proposal_no,
            config('constants.IcConstants.nic.PAYMENT_ADDITIONAL_INFO_2_NIC'),
            $data->vehicale_registration_number,
            $data->mobile_number,
            $data->chassis_number,
            $data->first_name.' '.$data->last_name,
            'NA',
            route('bike.payment-confirm', ['nic','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),

        ];

        $msg_desc = implode('|', $str_arr);
        $checksum = strtoupper(hash_hmac('sha256', $msg_desc, config('constants.IcConstants.nic.PAYMENT_CHECKSUM_NIC')));

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
        $request_data = $request->all();

        if($request_data!=null && isset($_REQUEST['msg'])) {

            $response   = $_REQUEST['msg'];
            $response   = explode('|', $response);

            $proposal   = UserProposal::where('proposal_no', $response[16])->first();

            $transaction_auth_status = [
                '0300'  => "Success - Successful Transaction",
                '0399'  => "Invalid Authentication at Bank - Cancel Transaction",
                'NA'    => "Invalid Input in the Request Message - Cancel Transaction",
                '0002'  => "BillDesk is waiting for Response from Bank - Cancel Transaction",
                '0001'  => "Error at BillDesk - Cancel Transaction"
            ];

            if ($response[14] == '0300')
            {

                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'response'      => $_REQUEST['msg'],
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                updateJourneyStage($data);

                $additional_details     = json_decode($proposal->additional_details);
                $nic_data      = $additional_details->nic;

                $payment_service = nicPaymentGateway::payment_info_service($proposal);

                if(!$payment_service['status']){
                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'));
                }

                $pdf_response_data = nicPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $payment_service['policy_no']
                    ]
                );

                /* return redirect(
                    config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL')
                    . '?'
                    . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id) ])
                ); */
                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'));
            }
            else
            {

                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'response'      => $_REQUEST['msg'],
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'status'        => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                return redirect(
                    config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL')
                    . '?'
                    . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            }
        }

        return redirect(Config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));

    }


    public static function payment_info_service($proposal)
    {
        $additional_details     = json_decode($proposal->additional_details);
        $nic_data      = $additional_details->nic;

        $payentInfoRequest = [   
            "depositType"           => "4",
            "policyNo"              => "",
            "cdAccountNo"           => "",
            "receiptDate"           => date('d/m/Y', strtotime($proposal->proposal_date)),
            "transactionDate"       => date('d/m/Y', strtotime($proposal->proposal_date)),
            "quoteNo"               => $proposal->proposal_no.';',
            "payorCode"             => $nic_data->customer_id,
            "receiptAmount"         => $proposal->final_payable_amount,
            "receiveAmountArr"      => $proposal->final_payable_amount,
            "payor"                 => $proposal->first_name.' '.$proposal->last_name,
            "transactionNo"         => config('constants.IcConstants.nic.TRANSACTION_NO_PREFIX_NIC_MOTOR').$nic_data->order_id,

            "userName"              => config('constants.IcConstants.nic.USER_TYPE_NIC_MOTOR'),
            "officeCode"            => config('constants.IcConstants.nic.OFFICE_CODE_NIC_MOTOR'),
            "payMode"               => config('constants.IcConstants.nic.PAY_MODE_NIC_MOTOR'),
        ];

        // quick quote service input

        $additional_data = [
            'enquiryId'         => $proposal->user_product_journey_id,
            'headers'           => [],
            'requestMethod'     => 'post',
            'section'           => 'Bike',
            'method'            => 'Payment - Proposal',
            'transaction_type'  => 'proposal',
            'content_type'      => 'text/plain',
            'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
        ];

        $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_PAYMENT'), $payentInfoRequest, 'nic', $additional_data);
        $payentInfoResponse = $get_response['response'];
        if ($payentInfoResponse) {

            $payentInfoResponse = json_decode($payentInfoResponse, true);
            if(!isset($payentInfoResponse['policyNo']) || $payentInfoResponse['policyNo'] == '')
            {
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                updateJourneyStage($data);

                return [
                    'status'    => false,
                    'message'   => $payentInfoResponse['responseMessage']
                ];
            }
            else
            {
                $payentInfoResponse['policyNo'] = str_replace(';','', $payentInfoResponse['policyNo']);

                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_number' => $payentInfoResponse['policyNo'],
                    ]
                );
                return [
                    'status'        => true,
                    'policy_no'     => $payentInfoResponse['policyNo'],
                ];
            }
        }
        else{

            $data['user_product_journey_id']    = $proposal->user_product_journey_id;
            $data['ic_id']                      = $proposal->ic_id;
            $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($data);

            return [
                'status'    => false,
                'message'   => 'no response from payment service'
            ];
        }
    }


    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
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
                'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no','up.unique_proposal_id', 'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id'
            )
            ->first();

        if($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found',
                'data'   => []
            ];

            return response()->json($pdf_response_data);
        }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if($policy_details->pdf_url == '')
        {
            $generatePolicy['status'] = false;

            if(is_null($policy_details->policy_number) || $policy_details->policy_number == ''){
                $generatePolicy = nicPaymentGateway::generatePolicy($proposal, $request->all());
            }

            if($generatePolicy['status'])
            {
                $pdf_response_data = nicPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $generatePolicy['data']['policy_no'],
                        'pdf_schedule' => $generatePolicy['data']['pdf_schedule']
                    ]
                );
            }
            else if($policy_details->policy_number != '')
            {
                $pdf_response_data = nicPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_schedule' => $policy_details->ic_pdf_url
                    ]
                );
            }
            else{
                $pdf_response_data = $generatePolicy;
            }
        }
        else
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'ic_pdf_url' => $policy_details->ic_pdf_url,
                    'pdf_url' => $policy_details->pdf_url,
                    'policy_number' => $policy_details->policy_number
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }


    static public function generatePolicy($proposal, $requestArray)
    {

        $is_package     = (($proposal->product_type == 'comprehensive') ? true : false);
        $is_liability   = (($proposal->product_type == 'third_party') ? true : false);
        $is_od          = (($proposal->product_type == 'own_damage') ? true : false);

        $additional_details     = json_decode($proposal->additional_details);
        $nic_data      = $additional_details->nic;

        $payment_service = nicPaymentGateway::payment_info_service($proposal);

        if ($payment_service['status'])
        {
            return [
                'status' => true,
                'msg'    => 'policy no generated successfully',
                'data'   => $payment_service
            ];
        }
        else
        {
            return [
                'status' => false,
                'msg'    => $payment_service['message'],
                'data'   => []
            ];
        }
    }


    static public function create_pdf($proposal, $policy_data){

        $policyDownload = [   
            'policyNo'  => $policy_data['policy_number'],
            'printType' => "policy",
            'Field1'    => "1",
        ];


        $additional_data = [
            'enquiryId'         => $proposal->user_product_journey_id,
            'headers'           => [],
            'requestMethod'     => 'post',
            'section'           => 'Bike',
            'method'            => 'Policy PDF Download - Proposal',
            'transaction_type'  => 'proposal',
            'content_type'      => 'text/plain',
            'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
        ];

        $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_DOWNLOAD_POLICY'), $policyDownload, 'nic', $additional_data);
        $policyDownloadResponse = $get_response['response'];

        if ($policyDownloadResponse)
        {

            $policyDownloadResponse = json_decode($policyDownloadResponse, true);

            if(!isset($policyDownloadResponse['pdf']) || $policyDownloadResponse['policyNo'] == '')
            {
                return [
                    'status'    => false,
                    'message'   => $policyDownloadResponse['responseMessage']
                ];
            }
            else
            {
                $policyDownloadResponse['pdf'] = base64_decode($policyDownloadResponse['pdf']);

                $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'nic/'. md5($proposal->user_proposal_id). '.pdf';

                $proposal_pdf = Storage::put($pdf_url, $policyDownloadResponse['pdf']);

                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'pdf_url' => $pdf_url,
                        'status' => 'SUCCESS'
                    ]
                );

                return [
                    'status' => true,
                    'msg' => 'sucess',
                    'data' => [
                        'policy_number' => $policy_data['policy_number'],
                        'pdf_link'      => file_url($pdf_url),
                        'ic_pdf_url'    => '',
                    ]
                ];
            }
        }
        else
        {
            return [
                'status'    => false,
                'message'   => 'no response from Policy Download service'
            ];
        }
    }
}
