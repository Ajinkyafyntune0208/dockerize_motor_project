<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use App\Models\CvAgentMapping;
use Config;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\Storage;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\DB;

class libertyVideoconPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        if ($proposal) {
            $enquiryId = customDecrypt($request['userProductJourneyId']);

            $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

            $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('quote_id')
                ->first();

            $productData = getProductDataByIc($request['policyId']);

            $return_data = [
                'form_action' => config('constants.IcConstants.liberty_videocon.PAYMENT_GATEWAY_LINK_LIBERTY_VIDEOCON'),
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'txnid' => $proposal->unique_proposal_id,
                    'amount' => $proposal->final_payable_amount,
                    'productinfo' => 'Payment for Liberty General Insurance',
                    'SURL' => route('bike.payment-confirm', ['liberty_videocon', 'user_proposal_id' => $proposal->user_proposal_id, 'status' => 'success']),
                    'FURL' => route('bike.payment-confirm', ['liberty_videocon', 'user_proposal_id' => $proposal->user_proposal_id, 'status' => 'failure']),
                    'key' => config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR'),
                    'FirstName' => $proposal->first_name." ".$proposal->last_name,
                    'Email' => $proposal->email,
                    'Phone' => $proposal->mobile_number,
                    'quotationNumber' => $proposal->unique_proposal_id,
                    'customerID' => $proposal->customer_id,
                ]
            ];

            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->update(['active' => 0]);

            DB::table('payment_request_response')->insert([
                'quote_id' => $quote_log_id,
                'user_product_journey_id' => $enquiryId,
                'user_proposal_id' => $proposal->user_proposal_id,
                'ic_id' => $icId,
                'order_id' => $proposal->proposal_no,
                'amount' => $proposal->final_payable_amount,
                'proposal_no' => $proposal->proposal_no,
                'payment_url' => config('constants.IcConstants.liberty_videocon.PAYMENT_GATEWAY_LINK_LIBERTY_VIDEOCON'),
                'return_url' => route('bike.payment-confirm', ['liberty_videocon', 'user_proposal_id' => $proposal->user_proposal_id, 'status' => 'success']),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'active' => 1,
                'xml_data' => json_encode($return_data['form_data'],JSON_UNESCAPED_SLASHES)
            ]);

            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'ic_id' => $productData->company_id,
                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            ]);

            return response()->json([
                'status' => true,
                'msg' => "Payment Redirectional",
                'data' => $return_data,
            ]);
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
        $request_data = $request->all();

        if (isset($request_data['user_proposal_id']) && $request_data['user_proposal_id'] != "") {
            $proposal = UserProposal::where('user_proposal_id', $request_data['user_proposal_id'])->first();

            // $is_package     = (($proposal->product_type == 'comprehensive' || $proposal->product_type == 'breakin') ? true : false);
            // $is_liability   = (($proposal->product_type == 'third_party' || $proposal->product_type == 'third_party_breakin') ? true : false);
            // $is_od          = (($proposal->product_type == 'own_damage' || $proposal->product_type == 'own_damage_breakin') ? true : false);

            if ($proposal) {
                $quote_data = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->first();

                if ($quote_data) {
                    $product = getProductDataByIc($quote_data->master_policy_id);
                }

                if (isset($request_data['status']) && $request_data['status'] == "success") {
                    // updateJourneyStage([
                    //     'user_product_journey_id' => $proposal->user_product_journey_id,
                    //     'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    // ]);

                    DB::table('payment_request_response')
                        ->where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->where('active',1)
                        ->update([
                            'response' => $request->All(),
                            'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]);

                    //$productcode = ($is_od ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_OD') : config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_PACKAGE'));
                    //$productcode = ($is_od) ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_OD') :(($is_liability) ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_TP'): config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_PACKAGE'));
                    $productcode = $proposal->product_code;
                    $pdf_request_data = [
                        'GCCustomerID' => $proposal['customer_id'],
                        'PremiumAmount' => $proposal['final_payable_amount'],
                        'ProductCode' => $productcode,
                        'QuotationNumber' => $proposal['unique_proposal_id'],
                        'PaymentSource' => config('constants.IcConstants.liberty_videocon.LIBERTY_VIDEOCON_PAYMENT_SOURCE'),
                        //'PaymentDate' => date('d/m/Y'),
                        'PaymentDate' => date('d/m/Y', strtotime($request_data['addedon'])),
                        'TransactionID' => $proposal['unique_proposal_id'],
                        'TPSourceName' => config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR'),
                        'TPEmailID' => config('constants.IcConstants.liberty_videocon.BIKE_EMAIL'),
                        'SendEmailtoCustomer' => 'true',
                        'OTP' => config('constants.IcConstants.liberty_videocon.OTP'),
                        'OTPCreatedDate' => date('d/m/Y'),
                        'OTPEnteredDate' => date('d/m/Y', strtotime('+1 day')),
                    ];

                    $get_response = getWsData(config('constants.IcConstants.liberty_videocon.CREATE_POLICY_LIBERTY_VIDEOCON_MOTOR'), $pdf_request_data, 'liberty_videocon', 
                    [
                        'enquiryId' => $proposal->user_product_journey_id,
                        'requestMethod' =>'post',
                        'section' => 'bike',
                        'transaction_type' => 'proposal',
                        'productName'  => $product->product_name ?? '',
                        'company'  => 'liberty_videocon',
                        'method'   => 'Generate Policy - Policy No',
                    ]);
                    $pdf_response_data = $get_response['response'];

                    if (!empty($pdf_response_data)) {
                        $pdf_response = json_decode($pdf_response_data, TRUE);

                        if (!empty($pdf_response['PolicyNumber']) && $pdf_response['PolicyNumber'] != 'NA') {

                            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                            $data['ic_id'] = $proposal->ic_id;
                            $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];

                            updateJourneyStage($data);

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' => $pdf_response['PolicyNumber'],
                                ]
                            );
                            if (config('IC.LIBERTY_VIDEOCON.BIKE.OLD_POLICY_PDF_SERVICE') == 'Y') {
                                $policy_html = [
                                    'strCustomerGcId' => $proposal['customer_id'],
                                    'strPolicyNumber' => $pdf_response['PolicyNumber'],
                                    'strProductType' => $productcode,
                                    'strTPSourceName' => config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR')
                                ];

                            $url = config('constants.IcConstants.liberty_videocon.POLICY_PDF_LIBERTY_VIDEOCON_MOTOR').'?'. http_build_query($policy_html);

                                $get_response = getWsData(
                                    $url,
                                    '',
                                    'liberty_videocon',
                                    [
                                        'enquiryId' => $proposal->user_product_journey_id,
                                        'requestMethod' => 'get',
                                        'section' => 'bike',
                                        'transaction_type' => 'proposal',
                                        'productName'  => $product->product_name ?? '',
                                        'company'  => 'liberty_videocon',
                                        'method'   => 'Generate pdf',
                                    ]
                                );
                                $pdf_data = $get_response['response'];
                                $output = json_decode($pdf_data, TRUE);
                                $pdf_data = base64_decode(trim($output['policyBytePDF']));
                                if (!checkValidPDFData($pdf_data)) {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                                }
                                Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/' . md5($proposal->user_proposal_id) . '.pdf', $pdf_data);
                            } else {
                                $url = config('constants.IcConstants.liberty_videocon.NEW_POLICY_PDF_LIBERTY_VIDEOCON_MOTOR');
                                $encryption_key = config('IC.LIBERTY_VIDEOCON.BIKE.ENCRYPTION_KEY');
                                $IMD_code = self::getImdCode($proposal->user_product_journey_id);
                                // $Authorization_key = config('IC.LIBERTY_VIDEOCON.V1.CAR.AUTH_KEY');
                                $Authorization_key = self::getEncryptionKey($proposal->user_product_journey_id);
                                $encryptedpolicyno = self::get_encrypt($pdf_response['PolicyNumber'], $encryption_key);
                                $encryptedImdCode = self::get_encrypt($IMD_code, $encryption_key);

                                $pdf_request_data = [
                                    'PolicyNumber' =>  $encryptedpolicyno,
                                    'IMDCode'      =>  $encryptedImdCode
                                ];
                                $additionalData = [
                                    'enquiryId' => $proposal->user_product_journey_id,
                                    'requestMethod' => 'post',
                                    'section' => 'bike',
                                    'productName'  => $product->product_name ?? '',
                                    'company'  => 'liberty_videocon',
                                    'method'   => 'Generate pdf',
                                    'transaction_type' => 'proposal',
                                    'headers' => [
                                        'Authorization' => $Authorization_key
                                    ],
                                ];


                                $data = getWsData($url, $pdf_request_data, 'liberty_videocon', $additionalData);
                                if (empty($data['response'])) {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'BIKE', 'SUCCESS'));
                                }
                                Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/' . md5($proposal->user_proposal_id) . '.pdf', $data['response']);
                            }

                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);

                            PolicyDetails::updateOrCreate(
                                ['proposal_id' => $proposal->user_proposal_id],
                                [
                                    'policy_number' => $pdf_response['PolicyNumber'],
                                    'ic_pdf_url' => $url,
                                    'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id). '.pdf',
                                    'status' => 'SUCCESS'
                                ]
                            );

                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'));
                        } else {
                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','FAILURE'));
                        }
                    } else {
                        return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','FAILURE'));
                    }
                } else {
                    DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('active',1)
                    ->update([
                        'response' => $request->All(),
                        'status'   => STAGE_NAMES['PAYMENT_FAILED']
                    ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['PAYMENT_FAILED']
                    ]);

                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','FAILURE'));
                }
            }
        } else {
            return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL'));
        }
    }

    static public function retry_pdf($proposal)
    {
        $get_response = getWsData(config('constants.IcConstants.liberty_videocon.GODIGIT_POLICY_PDF'),['policyId' => $proposal->unique_proposal_id],'liberty_videocon',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' =>'post',
                'section' => 'Policy PDF',
                'productName'  => '',
                'company'  => 'liberty_videocon',
                'authorization' => config('constants.IcConstants.liberty_videocon.GODIGIT_PG_AUTHORIZATION'),
                'method'   => 'Policy PDF',
                'transaction_type' => 'proposal',
            ]);

        $data = $get_response['response'];
        if (!empty($data)) {
            $pdf_response = json_decode($data);

            if (isset($pdf_response->schedulePath)) {
                //$pdf_data= Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. $proposal->user_proposal_id. '.pdf', file_get_contents($pdf_response->schedulePath));
                $pdf_data= Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. $proposal->user_proposal_id. '.pdf', httpRequestNormal($pdf_response->schedulePath,'GET',[],[],[],[],false)['response']);

                if ($pdf_data) {
                    PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                        'proposal_id' => $proposal->user_proposal_id,
                        'policy_number' => $proposal->policy_no,
                        'policy_start_date' => $proposal->policy_start_date,
                        'ncb' => $proposal->ncb_discount,
                        'policy_start_date' => $proposal->policy_start_date,
                        'premium' => $proposal->total_premium,
                        'ic_pdf_url' => $pdf_response->schedulePath,
                        'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. $proposal->user_proposal_id. '.pdf',
                        'status' => 'SUCCESS'
                    ]);

                    return response()->json([
                        'status' => true,
                        'msg' => 'PDF Regenrated SuccessFully..!',
                    ]);
                }
            }
        }

        return response()->json([
            'status' => false,
            'msg' => 'PDF Regenration Failed...!'
        ]);
    }

    static public function create_pdf($proposal, $policy_no){

        $productCode = $proposal->product_code;
        if (empty($proposal->product_code)) {
            $quoteLog = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            if (!empty($quoteLog->master_policy_id)) {
                $productData = getProductDataByIc($quoteLog->master_policy_id);

                $premium_type = DB::table('master_premium_type')
                ->where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

                $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
                $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
                $is_od          = ((in_array($premium_type, ['own_damage', 'own_damage_breakin'])) ? true : false);

                $productCode = ($is_od) ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_OD') :(($is_liability) ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_TP'): config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_PACKAGE'));
            }
        }
         if (config('IC.LIBERTY_VIDEOCON.BIKE.OLD_POLICY_PDF_SERVICE') == 'Y') {
        $policy_html = [
            'strCustomerGcId' => $proposal->customer_id,
            'strPolicyNumber' => $policy_no,
            //'strProductType' => ($is_od ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_OD') : config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_PACKAGE')),
            'strProductType' => $productCode,
            'strTPSourceName' => config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR')
        ];

        $url = config('constants.IcConstants.liberty_videocon.POLICY_PDF_LIBERTY_VIDEOCON_MOTOR').'?'. http_build_query($policy_html);

        // echo "<pre>";print_r([$policy_html, $url]);echo "</pre>";die();

        // sleep('5');
        // header('Access-Control-Allow-Origin: *');
        // header('Access-Control-Allow-Headers: *');
        // header('Content-type: text/plain');
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $output = curl_exec($ch);
        // curl_close($ch);

        // $output = json_decode($output, TRUE);
        $get_response = getWsData(
            $url,
            '',
            'liberty_videocon',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'get',
                'section' => 'bike',
                'transaction_type' => 'proposal',
                'productName'  => '',
                'company'  => 'liberty_videocon',
                'method'   => 'Generate pdf',
            ]
        );
        $pdf_data = $get_response['response'];
        $output = json_decode($pdf_data, TRUE);

        $pdf_url = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id).'.pdf';

        $pdf_data = base64_decode(trim($output['policyBytePDF']));
        if(!checkValidPDFData($pdf_data)) {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
            return [
                'status' => false,
                'msg' => 'PDF generation Failed...! Not a valid PDF data.'
            ];
        }

        Storage::put($pdf_url, $pdf_data);
    }else{
        $url = config('constants.IcConstants.liberty_videocon.NEW_POLICY_PDF_LIBERTY_VIDEOCON_MOTOR');
        $encryption_key = config('IC.LIBERTY_VIDEOCON.BIKE.ENCRYPTION_KEY');
        $IMD_code = self::getImdCode($proposal->user_product_journey_id);
        $Authorization_key = self::getEncryptionKey($proposal->user_product_journey_id);
        $encryptedpolicyno = self::get_encrypt($policy_no, $encryption_key);
        $encryptedImdCode = self::get_encrypt($IMD_code, $encryption_key);

        $pdf_request_data = [
            'PolicyNumber' =>  $encryptedpolicyno,
            'IMDCode'      =>  $encryptedImdCode
        ];
        $additionalData = [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' => 'post',
            'section' => 'bike',
            'productName'  => $product->product_name ?? '',
            'company'  => 'liberty_videocon',
            'method'   => 'Generate pdf',
            'transaction_type' => 'proposal',
            'headers' => [
                'Authorization' => $Authorization_key
            ],
        ];
        $data = getWsData($url, $pdf_request_data, 'liberty_videocon', $additionalData);
        if (empty($data['response'])) {
                updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
           ]);
            return [
                'status' => false,
                'msg' => 'PDF generation Failed...! Not a valid PDF data.'
            ];
        }
        Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/' . md5($proposal->user_proposal_id) . '.pdf', $data['response']);
    }

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['POLICY_ISSUED']
        ]);

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $proposal->user_proposal_id],
            [
                'policy_number' => $policy_no,
                'ic_pdf_url' => $url,
                'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id). '.pdf',
                'status' => 'SUCCESS'
            ]
        );

        return [
            'status' => true,
            'msg' => 'success',
            'data' => [
                'policy_number' => $policy_no,
                // 'pdf_link'      => $pdf_url,
                'pdf_link'      => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id).'.pdf')
            ]
        ];
        
        return [
            'status' => false,
            'msg' => 'PDF Regenration Failed...!'
        ];
    }

    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $payment_response = self::check_payment_status($user_product_journey_id);
        if(!$payment_response['status']){
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Payment is pending'
            ];

            return response()->json($pdf_response_data);
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
                'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no','up.unique_proposal_id', 'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id'
            )
            ->first();

        if($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'No data Found!'
            ];

            return response()->json($pdf_response_data);
        }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if($policy_details->ic_pdf_url == '')
        {
            $generatePolicy['status'] = false;

            if(is_null($policy_details->policy_number) || $policy_details->policy_number == ''){
                $generatePolicy = libertyVideoconPaymentGateway::generatePolicy($proposal, $request->all());
            }

            if($generatePolicy['status'] || $policy_details->policy_number != '')
            {
                $pdf_response_data = libertyVideoconPaymentGateway::create_pdf($proposal, $policy_details->policy_number);
            }
            else{
                $pdf_response_data = $generatePolicy;
            }
            // $pdf_response_data = libertyVideoconPaymentGateway::create_pdf($proposal, $policy_details->policy_number);
        }
        else
        {
            $pdf_response_data = [
                'status' => true,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'ic_pdf_url' => $policy_details->ic_pdf_url,
                    'pdf_url' => $policy_details->pdf_url,
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id).'.pdf')
                    
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }

    static public function  generatePolicy($proposal, $requestArray)
    {
        $additional_details = json_decode($proposal->additional_details, true);

        // $is_package     = (($proposal->product_type == 'comprehensive') ? true : false);
        // $is_liability   = (($proposal->product_type == 'third_party') ? true : false);
        // $is_od          = (($proposal->product_type == 'own_damage') ? true : false);

        // $is_package     = (($proposal->product_type == 'comprehensive' || $proposal->product_type == 'breakin') ? true : false);
        // $is_liability   = (($proposal->product_type == 'third_party' || $proposal->product_type == 'third_party_breakin') ? true : false);
        // $is_od          = (($proposal->product_type == 'own_damage' || $proposal->product_type == 'own_damage_breakin') ? true : false);
        // $productcode = ($is_od) ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_OD') :(($is_liability) ? config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_TP'): config('constants.IcConstants.liberty_videocon.bike.PRODUCT_CODE_PACKAGE'));
        $payment_details = PaymentRequestResponse::where([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'active' => 1,
            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
        ])->first();

        if (empty($payment_details)) {
            return [
                'status' => false,
                'msg'    => 'Payment is pending.',
                'data'   => []
            ];
        }
        $response = json_decode($payment_details->response, true);
        
        $productcode = $proposal->product_code;
        $pdf_request_data = [
            'GCCustomerID' => $proposal['customer_id'],
            'PremiumAmount' => $proposal['final_payable_amount'],
            //'ProductCode' => ($is_od ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_OD') : config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_PACKAGE')),
            'ProductCode'   => $productcode,
            'QuotationNumber' => $proposal['unique_proposal_id'],
            'PaymentSource' => config('constants.IcConstants.liberty_videocon.LIBERTY_VIDEOCON_PAYMENT_SOURCE'),
            //'PaymentDate' => date('d/m/Y'),
            'PaymentDate' => date('d/m/Y', strtotime($response['addedon'])),
            'TransactionID' => $proposal['unique_proposal_id'],
            'TPSourceName' => config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR'),
            'TPEmailID' => config('constants.IcConstants.liberty_videocon.CAR_EMAIL'),
            'SendEmailtoCustomer' => 'true',
            'OTP' => config('constants.IcConstants.liberty_videocon.OTP'),
            'OTPCreatedDate' => date('d/m/Y'),
            'OTPEnteredDate' => date('d/m/Y', strtotime('+1 day')),
        ];

        $additional_data = [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' =>'post',
            'company'  => 'liberty_videocon',
            'headers' => [],
            'requestType' => 'json',
            'section' => 'Bike',
            'method' => 'Generate Policy - Policy No',
            'transaction_type' => 'proposal',
            'productName' => $requestArray['product_name'],
        ];

        $get_response = getWsData(config('constants.IcConstants.liberty_videocon.CREATE_POLICY_LIBERTY_VIDEOCON_MOTOR'), $pdf_request_data, 'liberty_videocon', $additional_data);
        $response = $get_response['response'];

        $response = json_decode($response, true);
        if (empty($response['PolicyNumber']) || ($response['PolicyNumber'] ?? '') == 'NA')
        {
            return [
                'status' => false,
                'msg'    => $response['ErrorText'],
                'data'   => []
            ];
        }
        else
        {
            $additional_details['liberty']['policy_no'] = $response['PolicyNumber'];
            $additional_details['liberty']['generate_policy_response'] = $response;

            $proposal->additional_details = $additional_details;
            $proposal->save();

            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];

            updateJourneyStage($data);

            PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_number' => $response['PolicyNumber'],
                ]
            );

            return [
                'status' => true,
                'msg'    => 'policy no generated successfully',
                'data'   => []
            ];
        }
    }
    public static function check_payment_status($enquiry_id)
    {
        $get_payment_details = PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                                ->select('xml_data','id','user_proposal_id','response')
                                ->get();
        if(empty($get_payment_details))
        {
            return [
                'status' => false
            ];
        }
        foreach ($get_payment_details as $value)
        {    
            
            if(!empty($value->response) )
            {
                $check_payment_response = json_decode($value->response,true);
                if(($check_payment_response['status'] ?? '') =='success')
                {
                    return [
                        'status' => true,
                        'msg'    => 'response received already'
                    ];
                }
            }
            if(empty($value->xml_data))
            {
                continue;
            }
            $payment_data = json_decode($value->xml_data,true);
            $order_id = $payment_data['txnid'];
            $url = config('constants.IcConstants.liberty_videocon.PAYMENT_STATUS_CHECK_END_POINT_URL').$order_id;
            $get_response = getWsData($url , '', 'liberty_videocon', [
                'enquiryId' => $enquiry_id,
                'requestMethod' => 'get',
                'requestType' => 'json',
                'section' => '',
                'method' => 'Check Payment Status',
                'transaction_type' => 'proposal'
            ]);
            $verify_payment = trim($get_response['response'], '"');

            $payment_check_response = explode('|', $verify_payment);
            
            if(isset($payment_check_response[0]) && $payment_check_response[0] == 'True')
            {
                $payment_array = [
                    'status' => true,
                    'txnid'  => $order_id,
                    'addedon' => $payment_check_response[1],
                    'amount'  => $payment_check_response[2] ?? ''
                ];
                PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                    ->update([
                        'active'  => 0
                    ]);

                PaymentRequestResponse::where('id', $value->id)
                    ->update([
                        'response'      => json_encode($payment_array),
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                        'active'        => 1
                    ]);
                    $data['user_product_journey_id']    = $enquiry_id;
                    $data['proposal_id']                = $value->user_proposal_id;
                    $data['ic_id']                      = '32';
                    $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                    updateJourneyStage($data);

                    return [
                        'status' => true,
                        'msg' => 'success'
                    ];
                    
            }
        }

        return [
            'status' => false
        ];
    }
    public static function get_encrypt($input, $encryption_key)
    {
        $iv = substr($encryption_key, 0, 16);
        return openssl_encrypt($input, "aes-256-cbc", $encryption_key, 0, $iv);
    }
    public static function getImdCode($enquiryId)
    {
        $is_pos = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_data = config('constants.motorConstant.IS_POS_ENABLED_FOR_LIBERTY');
        $pos_data = CvAgentMapping::where('user_product_journey_id', $enquiryId)
            ->where('seller_type', 'P')
            ->first();
        if ($is_pos == 'Y' && !empty($pos_data) && $is_pos_data == 'Y') {
            $imd_code = config('constants.IcConstants.liberty_videocon.pos.LIBERTY_VIDEOCON_BIKE_POS_IMD'); //this value is passed for only TMIBSL as per git #35205
        } else {
            $imd_code = config('constants.IcConstants.liberty_videocon.bike.IMD_NUMBER_LIBERTY_VIDEOCON');
        }
        return $imd_code;
    }
    public static function getEncryptionKey($enquiryId)
    {
        $is_pos = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_data = config('constants.motorConstant.IS_POS_ENABLED_FOR_LIBERTY');
        $pos_data = CvAgentMapping::where('user_product_journey_id', $enquiryId)
            ->where('seller_type', 'P')
            ->first();
        if ($is_pos == 'Y' && !empty($pos_data) && $is_pos_data == 'Y') {
            $encryption_key = config('IC.LIBERTY_VIDEOCON.BIKE.AUTH_KEY_POS'); //this value is passed for only TMIBSL as per git #35205
        } else {
            $encryption_key = config('IC.LIBERTY_VIDEOCON.BIKE.AUTH_KEY');
        }
        return $encryption_key;
    }
}
