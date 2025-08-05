<?php

namespace App\Http\Controllers\Payment\Services\Car\V2;

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\PolicyDetails;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Proposal\Services\Car\V2\nicSubmitProposal as NIC_V2;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class NicPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        // PAYMENT
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        $enquiryId = customDecrypt($request->enquiryId);

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();

        $checksum = NicPaymentGateway::create_checksum($enquiryId, $request);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $additional_details = json_decode($proposal->additional_details);
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

        $msg_desc = explode('|', $checksum['msg']);

        PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('user_proposal_id', $proposal->user_proposal_id)
            ->update(['active' => 0]);

        PaymentRequestResponse::create([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $msg_desc[1],
            'amount'                    => $proposal->final_payable_amount,
            'payment_url'               => config('constants.IcConstants.nic.PAYMENT_GATEWAY_URL_NIC'),
            'return_url'                => route(
                'car.payment-confirm',
                [
                    'nic',
                    'user_proposal_id' => $proposal->user_proposal_id,
                    'policy_id'        => $request->policyId
                ]
            ),
            'xml_data'                  => $checksum['msg'],
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);

        return response()->json([
            'status'    => true,
            'msg'       => "Payment Reidrectional",
            'data'      => $return_data,
        ]);
    }

    public static  function  create_checksum($enquiryId, $request)
    {
        $policy_id = $request['policyId'];
        DB::enableQueryLog();
        $data = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();

        $new_pg_transaction_id = strtoupper(config('IC.NIC.V2.CAR.PAYMENT_MERCHANT_ID_NIC')) . date('Ymd') . time() . rand(10, 99);

        $str_arr = [
            config('IC.NIC.V2.CAR.PAYMENT_MERCHANT_ID_NIC'),
            $new_pg_transaction_id,
            'NA',
            round((float)$data->final_payable_amount, 2),
            'NA',
            'NA',
            'NA',
            'INR',
            'NA',
            'R',
            config('IC.NIC.V2.CAR.PAYMENT_USER_ID_NIC'),
            'NA',
            'NA',
            'F',
            $data->proposal_no,
            config('IC.NIC.V2.CAR.PAYMENT_ADDITIONAL_INFO_2_NIC'),
            $data->vehicale_registration_number,
            $data->mobile_number,
            $data->chassis_number,
            $data->first_name . ' ' . $data->last_name,
            $data->proposal_no,
            route('car.payment-confirm', ['nic', 'enquiry_id' => $enquiryId, 'policy_id' => $request['policyId']]),

        ];

        $msg_desc = implode('|', $str_arr);
        $checksum = strtoupper(hash_hmac('sha256', $msg_desc, config('IC.NIC.V2.CAR.PAYMENT_CHECKSUM_NIC')));

        $new_string = $msg_desc . '|' . $checksum;

        return [
            'status' => 'true',
            'msg' => $new_string,
            'transaction_id' => $new_pg_transaction_id
        ];
    }

    public static function confirm($request)
    {
        $request_data = $request->all();

        if ($request_data != null && isset($_REQUEST['msg'])) {

            $response = $_REQUEST['msg'];
            $response = explode('|', $response);
            $proposal = UserProposal::where('proposal_no', $response[16])->first();

            $transaction_auth_status = [
                '0300'  => "Success - Successful Transaction",
                '0399'  => "Invalid Authentication at Bank - Cancel Transaction",
                'NA'    => "Invalid Input in the Request Message - Cancel Transaction",
                '0002'  => "BillDesk is waiting for Response from Bank - Cancel Transaction",
                '0001'  => "Error at BillDesk - Cancel Transaction"
            ];

            if ($response[14] == '0300') {
                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'response'   => $_REQUEST['msg'],
                        'updated_at' => now(),
                        'status'     => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);
                $data = [
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'ic_id'                   => $proposal->ic_id,
                    'stage'                   => STAGE_NAMES['PAYMENT_SUCCESS']
                ];
                updateJourneyStage($data);

                $additional_details = json_decode($proposal->additional_details);
                $additional_details->nic->transaction_no = $response[2];
                $proposal->additional_details = json_encode($additional_details);
                $proposal->save();

                $payment_service = NicPaymentGateway::payment_info_service($proposal);

                if (!$payment_service['status']) {
                    return redirect(
                        config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL')
                            . '?'
                            . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)])
                    );
                }

                $pdf_response_data = NicPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $payment_service['policy_no']
                    ]
                );

                return redirect(
                    config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL')
                        . '?'
                        . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)])
                );
            } else {
                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'response'   => $_REQUEST['msg'],
                        'updated_at' => now(),
                        'status'     => STAGE_NAMES['PAYMENT_FAILED']
                    ]);
                $data = [
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'ic_id'                   => $proposal->ic_id,
                    'stage'                   => STAGE_NAMES['PAYMENT_FAILED']
                ];
                updateJourneyStage($data);
                return redirect(
                    config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL')
                        . '?'
                        . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)])
                );
            }
        }

        return redirect(Config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
    }

    public static function payment_info_service($proposal)
    {
        $additional_details = json_decode($proposal->additional_details);
        $nic_data = $additional_details->nic;

        $tokenResponse = NIC_V2::generateToken('Car Insurance',$proposal->user_product_journey_id, 'proposal');

        $payentInfoRequest = [
            "ProposalNo" => $proposal->proposal_no,
            "IsCustomerUpdate" => "Y",
            "PolicyPaymentInfoList" => [
                [
                    "PayModeCode" => config('IC.NIC.V2.CAR.NIC_PAYMENT_MODE_CODE'),
                    "PaymentMethodName" => "Online",
                    "PaymentDate" => date('Y-m-d\TH:i:s'),
                    "Amount" => $proposal->final_payable_amount,
                    "TransactionNo" => $nic_data->transaction_no,
                    "IsInstallment" => "N",
                    "InstallmentType" => "-1",
                    "PgID" => config('IC.NIC.V2.CAR.NIC_PAYMENT_PGID'),
                    "MerchantId" => config('IC.NIC.V2.CAR.PAYMENT_MERCHANT_ID_NIC'),
                    "PaymentUqTranID" => config('IC.NIC.V2.CAR.TRANSACTION_NO_PREFIX_NIC_MOTOR') . $nic_data->order_id
                ]
            ]
        ];

        $additional_data = [
            'enquiryId'         => $proposal->user_product_journey_id,
            'requestMethod'     => 'post',
            'section'           => 'Car',
            'method'            => 'Payment - Proposal',
            'transaction_type'  => 'proposal',
            'content_type'      => 'application/json',
            'headers' => [
                'Content-Type'      => 'application/json',
                'User-Agent'        => $_SERVER['HTTP_USER_AGENT'],
                'Authorization'     => 'Bearer ' . $tokenResponse['token']
            ]
        ];

        $get_response = getWsData(config('IC.NIC.V2.CAR.END_POINT_URL_NIC_PAYMENT'), $payentInfoRequest, 'nic', $additional_data);
        $payentInfoResponse = $get_response['response'];
        if ($payentInfoResponse) {

            $payentInfoResponse = json_decode($payentInfoResponse, true);
            if (!isset($payentInfoResponse['PolicyNo']) || $payentInfoResponse['PolicyNo'] == '') {
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = 'Payment Success';
                updateJourneyStage($data);

                return [
                    'status'    => false,
                    'message'   => $payentInfoResponse['responseMessage'] ?? 'Policy issuance failed.'
                ];
            } else {
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($data);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_number' => $payentInfoResponse['PolicyNo'],
                    ]
                );
                return [
                    'status'        => true,
                    'policy_no'     => $payentInfoResponse['PolicyNo'],
                ];
            }
        } else {

            $data['user_product_journey_id']    = $proposal->user_product_journey_id;
            $data['ic_id']                      = $proposal->ic_id;
            $data['stage']                      = 'Payment Success';
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
        $policy_details = PaymentRequestResponse::where([
            'payment_request_response.user_product_journey_id' => $user_product_journey_id,
            'payment_request_response.active'                  => 1,
            'payment_request_response.status'                  => STAGE_NAMES['PAYMENT_SUCCESS']
        ])
            ->leftJoin('policy_details as pd', 'pd.proposal_id', '=', 'payment_request_response.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'payment_request_response.user_product_journey_id')
            ->select(
                'up.user_proposal_id',
                'up.proposal_no',
                'up.unique_proposal_id',
                'pd.policy_number',
                'pd.pdf_url',
                'pd.ic_pdf_url',
                'payment_request_response.order_id'
            )
            ->first();

        if ($policy_details == null) {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found',
                'data'   => []
            ];

            return response()->json($pdf_response_data);
        }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if ($policy_details->pdf_url == '') {
            $generatePolicy['status'] = false;

            if (is_null($policy_details->policy_number) || $policy_details->policy_number == '') {
                $generatePolicy = NicPaymentGateway::generatePolicy($proposal, $request->all());
            }

            if ($generatePolicy['status']) {
                $pdf_response_data = NicPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $generatePolicy['data']['policy_no'],
                        'pdf_schedule' => $generatePolicy['data']['pdf_schedule']
                    ]
                );
            } else if ($policy_details->policy_number != '') {
                $pdf_response_data = NicPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_schedule' => $policy_details->ic_pdf_url
                    ]
                );
            } else {
                $pdf_response_data = $generatePolicy;
            }
        } else {
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

        $is_package   = $proposal->product_type == 'comprehensive';
        $is_liability = $proposal->product_type == 'third_party';
        $is_od        = $proposal->product_type == 'own_damage';
        $additional_details = json_decode($proposal->additional_details);
        $nic_data = $additional_details->nic;

        $payment_service = NicPaymentGateway::payment_info_service($proposal);

        if ($payment_service['status']) {
            return [
                'status' => true,
                'msg'    => 'policy no generated successfully',
                'data'   => $payment_service
            ];
        } else {
            return [
                'status' => false,
                'msg'    => $payment_service['message'],
                'data'   => []
            ];
        }
    }

    static public function create_pdf($proposal, $policy_data)
    {

        $tokenResponse = NIC_V2::generateToken('Car Insurance',$proposal->user_product_journey_id, 'proposal');

        $additional_data = [
            'enquiryId'         => $proposal->user_product_journey_id,
            'requestMethod'     => 'get',
            'section'           => 'Car',
            'method'            => 'Policy PDF Download - Proposal',
            'transaction_type'  => 'proposal',
            'content_type'      => 'application/json',
            'headers' => [
                'Content-Type'      => 'application/json',
                'User-Agent'        => $_SERVER['HTTP_USER_AGENT'],
                'Authorization'     => 'Bearer ' . $tokenResponse['token']
            ]
        ];

        $get_response = getWsData(config('IC.NIC.V2.CAR.END_POINT_URL_NIC_POLICY_DOWNLOAD') . $policy_data['policy_number'], [], 'nic', $additional_data);
        $policyDownloadResponse = $get_response['response'];

        if ($policyDownloadResponse) {
            $jsonResponse = json_decode($policyDownloadResponse, true);

            if (is_array($jsonResponse) && isset($jsonResponse['message'])) {
                return [
                    'status'    => false,
                    'message'   => $jsonResponse['message']
                ];
            } else {
                $policyDownloadResponse = base64_decode($policyDownloadResponse);

                $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'nic/' . md5($proposal->user_proposal_id) . '.pdf';

                $proposal_pdf = Storage::put($pdf_url, $policyDownloadResponse);

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
        } else {
            return [
                'status'    => false,
                'message'   => 'no response from Policy Download service'
            ];
        }
    }

    public static function get_token_response($enquiryId)
    {
        // Token Generation
        $tokenParam = [
            "username" => config('IC.NIC.V2.CAR.USERNAME_NIC_MOTOR'),
            "password" => config('IC.NIC.V2.CAR.PASSWORD_NIC_MOTOR')
        ];

        $tokenResponse = getWsData(config('IC.NIC.V2.CAR.NIC_TOKEN_GENERATION_URL_MOTOR'), $tokenParam, 'nic', [
            'enquiryId'         => $enquiryId,
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'car',
            'method' => 'Token Generation',
            'transaction_type' => 'proposal',
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => $_SERVER['HTTP_USER_AGENT']
            ]
        ]);

        $tokenResponse = json_decode($tokenResponse['response'], true);
        if (!isset($tokenResponse['access_token'])) {
            return [
                'status' => false,
                'message' => 'Token generation failed'
            ];
        }
    }
}
