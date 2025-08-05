<?php

namespace App\Http\Controllers\Payment\Services;

use Config;
use App\Models\UserProposal;
use App\Models\CkycAckoFailedCasesData;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                'amount' => round($proposal_data->final_payable_amount)
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
                        ->where('ic_id', $ic_id)
                        ->where('user_proposal_id', $proposal_data->user_proposal_id)
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
                        'return_url'                => route('cv.payment-confirm', ['acko']),
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
        if (isset($request['proposal_id'])) {
            PaymentRequestResponse::where('order_id', $request['proposal_id'])
                ->where('active',1)
                ->update([
                    'response' => $request->all()
                ]);
        }
        //$ic_message = [];

        $kyc_url = '';
        if(config('constants.IS_CKYC_ENABLED') == 'Y')
        {
            $proposal_data_kyc = UserProposal::where('proposal_no', $request['proposal_id'])->first();
            $kyc_status = self::kycUpdate($proposal_data_kyc);
            if(!$kyc_status['status'])
            {
                $kyc_url = $kyc_status['kyc_url'];
            }
        }
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
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
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

                        if (Storage::put('policyDocs/Cv/acko/'.md5($proposal_data->user_product_journey_id).'.pdf', $pdf_data)) {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal_data->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal_data->user_proposal_id],
                                [
                                    'policy_number' => $request['policy_number'],
                                    'pdf_url' => 'policyDocs/Cv/acko/'.md5($proposal_data->user_product_journey_id).'.pdf',
                                    'ic_pdf_url' => $policy_result['result']['policy']['pdf_url'],
                                    'status' => 'Success'
                                ]
                            );
                        }
                    }
                }
                
                return redirect(config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal_data->user_product_journey_id),'kyc_url' => $kyc_url]));
                
            } else {
                PaymentRequestResponse::where('order_id', $request['proposal_id'])
                    ->where('active',1)
                    ->update([
                        'status' => STAGE_NAMES['PAYMENT_FAILED']
                    ]);

                return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
            }
        } else {
            PaymentRequestResponse::where('order_id', $request['proposal_id'])
                ->where('active',1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_FAILED']
                ]);

            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }  
    }

    public static function kycUpdate($proposal)
    {

        if(!empty($proposal))
        {
            $user_product_journey_id =  $proposal->user_product_journey_id; 
        }else
        {
            $data = [
                "status" => false,
                "kyc_url" => '',
            ];
        
            return $data;
        }
     

        $kyc_status_to_be_updated = 'N'; 
        $data = [];

        if(!empty($proposal))
        {
            include_once app_path().'/Helpers/CvWebServiceHelper.php';

            $pdf_request = [
                'proposal_id' => $proposal->proposal_no
            ];
            
            $get_response = getWsData(config('constants.IcConstants.acko.ACKO_PROPOSAL_STATUS_URL'), $pdf_request, 'acko',
            [
                'enquiryId'     => $user_product_journey_id,
                'requestMethod' => 'post',
                'section'       => $request->product_type ?? "CV",
                'productName'   => $request->product_name ?? " ",
                'company'       => 'acko',
                'method'        => 'PROPOSAL KYC STATUS',
                'transaction_type' => 'proposal'
            ]);
    
            $kyc_response = $get_response['response'];
            if (!empty($kyc_response)) 
            {
                $kyc_response = json_decode($kyc_response, TRUE);
    
                if($kyc_response['result']['kyc']['status'] == "KYC_SUCCESS")
                {
                    $kyc_status_to_be_updated = 'Y';
                    $data = [
                        "status" => true,
                    ];
                    CkycAckoFailedCasesData::updateOrCreate(
                        ['user_product_journey_id' => $user_product_journey_id],
                        [
                        'policy_no' => $kyc_response['result']['policy_number'],
                        'kyc_url' => $kyc_response['result']['kyc']['kyc_redirection_link'],
                        'status' => 'true',
                        'return_url' => '',
                        'post_data' => '',
                        ]
                    );
                }
                else
                {
        
                    CkycAckoFailedCasesData::updateOrCreate(
                        ['user_product_journey_id' => $user_product_journey_id],
                        [
                        'policy_no' => $proposal->proposal_no,
                        'kyc_url' => $kyc_response['result']['kyc']['kyc_redirection_link'],
                        'status' => 'false',
                        'return_url' => '',
                        'post_data' => '',
                        ]
                    );
        
                    $data = [
                        "status" => false,
                        "kyc_url" => $kyc_response['result']['kyc']['kyc_redirection_link'] ?? '',
                    ];
                }
        
                UserProposal::updateOrCreate(
                    ['proposal_no' => $proposal->proposal_no],
                    [
                    'is_ckyc_verified' => $kyc_status_to_be_updated
                    ]
                );

        }else
        {
            $data = [
                "status" => false,
                "kyc_url" => '',
            ];

        }
            return $data;
    }
    }
    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);      

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'prr.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->where('prr.user_product_journey_id', $user_product_journey_id)
            ->where('prr.active', 1)
            ->select('up.user_proposal_id', 'up.proposal_no', 'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url')
            ->first();

        Storage::delete(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'acko/'. md5($policy_details->user_proposal_id). '.pdf');

        if ($policy_details->ic_pdf_url != '') {
            Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'acko/'. md5($policy_details->user_proposal_id). '.pdf', file_get_contents($policy_details->ic_pdf_url));

            $pdf_url = file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'acko/'. md5($policy_details->user_proposal_id). '.pdf');

            $pdf_response_data = [
                'status' => true,
                'msg' => 'sucess',
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => $pdf_url
                ]
            ];

            PolicyDetails::updateOrCreate(
                ['proposal_id' => $policy_details->user_proposal_id],
                [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'acko/'. md5($policy_details->user_proposal_id). '.pdf',
                    'status' => 'SUCCESS'
                ]
            );

            updateJourneyStage([
                'user_product_journey_id' => $user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED']
            ]);

            DB::table('payment_request_response')
                ->where('user_product_journey_id', $user_product_journey_id)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
        } else {
            $pdf_request = [
                'proposal_id' => $policy_details->proposal_no
            ];

            include_once app_path().'/Helpers/CvWebServiceHelper.php';

            $get_response = getWsData(config('constants.IcConstants.acko.ACKO_PROPOSAL_STATUS_URL'), $pdf_request, 'acko',
            [
                'enquiryId'     => $user_product_journey_id,
                'requestMethod' => 'post',
                'section'       => strtoupper($request->product_type),
                'productName'   => $request->product_name,
                'company'       => 'acko',
                'method'        => 'RE HIT PDF',
                'transaction_type' => 'proposal'
            ]);
            $pdf_response = $get_response['response'];
            if (!empty($pdf_response)) {
                $pdf_response = json_decode($pdf_response, TRUE);

                if (isset($pdf_response['success']) && $pdf_response['success']) {
                    if ($pdf_response['result']['status'] == 'POLICY_CREATED' && $pdf_response['success'] == 'true') {
                        Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'acko/'. md5($policy_details->user_proposal_id). '.pdf', file_get_contents($pdf_response['result']['document_link']));

                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $policy_details->user_proposal_id],
                            [
                                'policy_number' => $pdf_response['result']['policy_number'],
                                'ic_pdf_url' => $pdf_response['result']['document_link'],
                                'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'acko/'. md5($policy_details->user_proposal_id). '.pdf',
                                'status' => 'SUCCESS'
                            ]
                        );

                        $pdf_url = file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'acko/'. md5($policy_details->user_proposal_id). '.pdf');

                        $pdf_response_data = [
                            'status' => true,
                            'msg' => 'sucess',
                            'data' => [
                                'policy_number' => $pdf_response['result']['policy_number'],
                                'pdf_link'      => $pdf_url
                            ]
                        ];

                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);

                        DB::table('payment_request_response')
                            ->where('user_product_journey_id', $user_product_journey_id)
                            ->where('active',1)
                            ->update([
                                'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                            ]);

                            if(isset($pdf_response['result']['kyc']['status']) && $pdf_response['result']['kyc']['status'] === 'KYC_SUCCESS')
                            {
                                CkycAckoFailedCasesData::updateOrCreate(
                                    ['user_product_journey_id' => $user_product_journey_id],
                                    [
                                    'policy_no' => $pdf_response['result']['policy_number'],
                                    'kyc_url' => $pdf_response['result']['kyc']['kyc_redirection_link'] ?? '',
                                    'status' => 'true',
                                    'return_url' => '',
                                    'post_data' => '',
                                    ]
                                );

                                UserProposal::updateOrCreate(
                                    ['user_proposal_id' => $policy_details->user_proposal_id],
                                    [
                                    'is_ckyc_verified' => 'Y'
                                    ]
                                );

                            }
                    } else {
                        $pdf_response_data = [
                            'status' => false,
                            'msg' =>  'Error : Service Error',
                            'dev' =>  $pdf_response['result']['status'] ?? 'Error : Service Error',
                            'data' => []
                        ];

                        if(isset($pdf_response['result']['kyc']['status']) && $pdf_response['result']['kyc']['status'] !== 'KYC_SUCCESS')
                        {
                            CkycAckoFailedCasesData::updateOrCreate(
                                ['user_product_journey_id' => $user_product_journey_id],
                                [
                                'policy_no' => $pdf_response['result']['policy_number'] ?? '',
                                'kyc_url' => $pdf_response['result']['kyc']['kyc_redirection_link'] ?? '',
                                'status' => 'false',
                                'return_url' => '',
                                'post_data' => '',
                                ]
                            );

                            UserProposal::updateOrCreate(
                                ['user_proposal_id' => $policy_details->user_proposal_id],
                                [
                                'is_ckyc_verified' => 'N'
                                ]
                            );

                        }
                    }
                } else {
                    $pdf_response_data = [
                        'status' => false,
                        'msg' => 'Error : Service Error',
                        'dev' => $pdf_response['result']['msg'] ?? 'Error : Service Error',
                        'data' => []
                    ];
                }
            } else {
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'Error : Service Error',
                    'dev' => $pdf_response['result']['msg'] ?? 'Error : Service Error',
                    'data' => []
                ];
            }
        }

        return response()->json($pdf_response_data);
    }
}
