<?php

namespace App\Http\Controllers\Payment\Services\Car;

use Config;
use Storage;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;

class ackoPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal_data = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        $product_data = getProductDataByIc($request['policyId']);

        $ic_id = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))
                ->pluck('quote_id')
                ->first();

        if ($proposal_data) {
            $validate_proposal_request = [
                'proposal_id' => $proposal_data->proposal_no,
                'amount' => ($proposal_data->final_payable_amount)
            ];

            include_once app_path().'/Helpers/CvWebServiceHelper.php';

            $get_response = getWsData(config('constants.IcConstants.acko.ACKO_PAYMENT_WEB_SERIVCE_URL'), $validate_proposal_request, 'acko', [
                'section' => $product_data->product_sub_type_code,
                'method' => 'Validate Proposal',
                'requestMethod' => 'post',
                'enquiryId' => customDecrypt($request['userProductJourneyId']),
                'productName' => $product_data->product_name,
                'transaction_type' => 'Proposal'
            ]);
            $validate_proposal_response = $get_response['response'];

            if ($validate_proposal_response) {
                $validate_proposal_result = json_decode($validate_proposal_response, TRUE);

                if (isset($validate_proposal_result['success']) && $validate_proposal_result['success']) {
                    PaymentRequestResponse::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))
                        ->update(['active' => 0]);
                    
                    PaymentRequestResponse::insert([
                        'quote_id'                  => $quote_log_id,
                        'user_product_journey_id'   => customDecrypt($request['userProductJourneyId']),
                        'user_proposal_id'          => $proposal_data->user_proposal_id,
                        'ic_id'                     => $ic_id,
                        'order_id'                  => $proposal_data->proposal_no,
                        'amount'                    => $proposal_data->final_payable_amount,
                        'proposal_no'               => $proposal_data->proposal_no,
                        'payment_url'               => $validate_proposal_result['result']['payment_url'],
                        'return_url'                => route('car.payment-confirm', ['acko']),
                        'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                        'active'                    => 1
                    ]);

                    updateJourneyStage([
                        'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                        'stage' => STAGE_NAMES['PAYMENT_INITIATED']
                    ]);

                    return [
                        'status' => true,
                        'data' => [
                            'payment_type' => 1,
                            'paymentUrl' => $validate_proposal_result['result']['payment_url']
                        ]
                    ];
                } else {
                    $messages = '';

                    if (isset($validate_proposal_result['result']['field_errors'])) {
                        foreach ($validate_proposal_result['result']['field_errors'] as $field => $field_error) {
                            $messages = $messages.$field_error['msg'].'. ';
                        }
                    } else if (isset($validate_proposal_result['result']['msg'])) {
                        $messages = $validate_proposal_result['result']['msg'];
                    } else {
                        $messages = 'Service Temporarily Unavailables';
                    }

                    return [
                        'status' => false,
                        'msg' => $messages
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'msg' => 'Insurer not reachable'
                ];
            }
        } else {
            return [
                'status' => false,
                'msg' => 'Proposal data not found'
            ];
        }        
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        if (isset($request['proposal_id']) && isset($request['policy_number'])) {
            $proposal_data = UserProposal::where('proposal_no', $request['proposal_id'])->first();

            if ($proposal_data) {
                updateJourneyStage([
                    'user_product_journey_id' => $proposal_data->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                $quote_data = QuoteLog::where('user_product_journey_id', $proposal_data->user_product_journey_id)
                    ->first();

                if ($quote_data && $quote_data->quote_response != NULL && $quote_data->quote_response != '') {
                    $quote_response = json_decode($quote_data->quote_response, TRUE);

                    if ($quote_response['policy_id']) {
                        $product_data = getProductDataByIc($quote_response['policy_id']);
                    }
                }

                PaymentRequestResponse::where('order_id', $request['proposal_id'])
                    ->where('active',1)
                    ->update([
                        'status' => 'Success'
                        ]);

                include_once app_path().'/Helpers/CvWebServiceHelper.php';

                $get_response = getWsData(config('constants.IcConstants.acko.ACKO_POLICY_WEB_SERVICE_URL').'?policy_number='.$request['policy_number'], array(), 'acko', [
                    'section' => $product_data->product_sub_type_code ?? '',
                    'method' => 'Payment Confirm',
                    'requestMethod' => 'get',
                    'enquiryId' => $proposal_data->user_product_journey_id,
                    'productName' => $product_data->product_name ?? '',
                    'transaction_type' => 'Proposal'
                ]);
                $policy_response = $get_response['response'];

                if ($policy_response) {
                    $policy_result = json_decode($policy_response, TRUE);

                    if (isset($policy_result['success']) && $policy_result['success']) {
                        $pdf_data = file_get_contents($policy_result['result']['policy']['pdf_url']);

                        if (Storage::put('policyDocs/Car/acko/'.md5($proposal_data->user_product_journey_id).'.pdf', $pdf_data)) {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal_data->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal_data->user_proposal_id],
                                [
                                    'policy_number' => $request['policy_number'],
                                    'pdf_url' => 'policyDocs/Car/acko/'.md5($proposal_data->user_product_journey_id).'.pdf',
                                    'ic_pdf_url' => $policy_result['result']['policy']['pdf_url'],
                                    'status' => 'Success'
                                ]
                            );
                        }
                    }
                }
                
                return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal_data->user_product_journey_id)]));
            } else {
                return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
            }
        } else {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }  
    }

    public function retry_pdf()
    {
        $policy_details_request = [
            'proposal_id' => 'ECURgQ02NV4FkXD9YRrDPg',
            'payment' => [
                'pg' => 'online',
                'token' => 'QuS2ZRRD7BFNqZP',
                'date' => '2021-07-07T00:00:00+05:30'
            ]
            ];

        $get_response = getWsData('partner.acko.com/api/motor/issue_policy', $policy_details_request, 'acko', [
            'section' => 'test',
            'method' => 'Payment Confirm',
            'requestMethod' => 'get',
            'enquiryId' => 1234,
            'productName' => 'test',
            'transaction_type' => 'Proposal'
        ]);
        $policy_response = $get_response['response'];

        print_r($policy_response);exit;
    }
}
