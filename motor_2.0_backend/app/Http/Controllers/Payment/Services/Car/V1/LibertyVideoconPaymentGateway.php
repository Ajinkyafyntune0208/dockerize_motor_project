<?php

namespace App\Http\Controllers\Payment\Services\Car\V1;
include_once app_path().'/Helpers/CarWebServiceHelper.php';

use App\Http\Controllers\SyncPremiumDetail\Car\LibertyVideoconPremiumDetailController;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\Storage;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use App\Models\PaymentRequestResponse;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;

class LibertyVideoconPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {

        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $productData = getProductDataByIc($request['policyId']);

        if($proposal->is_breakin_case == 'Y')
        {   
            $submitProposalResponse = self::submitProposal($proposal, $productData);

            if(!$submitProposalResponse['status'])
            {
                return [
                    'status' => false,
                    'msg' => $submitProposalResponse['message'],
                    'message' => $submitProposalResponse['message']
                ];
            }
        }

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
                'form_action' => config('IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_GATEWAY_LINK'),
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'txnid' => $proposal->unique_proposal_id,
                    'amount' => $proposal->final_payable_amount,
                    'productinfo' => 'Payment for Liberty General Insurance',
                    'SURL' => route('car.payment-confirm', ['liberty_videocon', 'user_proposal_id' => $proposal->user_proposal_id, 'status' => 'success']),
                    'FURL' => route('car.payment-confirm', ['liberty_videocon', 'user_proposal_id' => $proposal->user_proposal_id, 'status' => 'failure']),
                    'key' => self::getTpSourceName($proposal->user_product_journey_id),
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
                'payment_url' => config('IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_GATEWAY_LINK'),
                'return_url' => route('car.payment-confirm', ['liberty_videocon', 'user_proposal_id' => $proposal->user_proposal_id, 'status' => 'success']),
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

            // $is_package     = (($proposal->product_type == 'comprehensive') ? true : false);
            // $is_liability   = (($proposal->product_type == 'third_party') ? true : false);
            // $is_od          = (($proposal->product_type == 'own_damage') ? true : false);

            //$productCode = ($is_od) ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_OD') :(($is_liability) ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_TP') : config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_PACKAGE'));
            $productCode = $proposal->product_code;
            $enquiryId = $proposal->user_product_journey_id;
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

                    $tp_source_name = self::getTpSourceName($proposal->user_product_journey_id);

                    DB::table('payment_request_response')
                        ->where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->where('active',1)
                        ->update([
                            'response' => $request->All(),
                            'status'   => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]);

                    $pdf_request_data = [
                        'GCCustomerID' => $proposal['customer_id'],
                        'PremiumAmount' => $proposal['final_payable_amount'],
                        'ProductCode' => $productCode,
                        'QuotationNumber' => $proposal['unique_proposal_id'],
                        'PaymentSource' => config('IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_SOURCE'),
                        //'PaymentDate' => date('d/m/Y'),
                        'PaymentDate' => date('d/m/Y', strtotime($request_data['addedon'])),
                        'TransactionID' => $proposal['unique_proposal_id'],
                        'TPSourceName' => $tp_source_name,
                        'TPEmailID' => config('IC.LIBERTY_VIDEOCON.V1.CAR.EMAIL'),
                        'SendEmailtoCustomer' => 'true',
                        'OTP' => config('IC.LIBERTY_VIDEOCON.V1.CAR.OTP'),
                        'OTPCreatedDate' => date('d/m/Y'),
                        'OTPEnteredDate' => date('d/m/Y', strtotime('+1 day')),
                    ];

                    $get_response = getWsData(config('IC.LIBERTY_VIDEOCON.V1.CAR.CREATE_POLICY'), $pdf_request_data, 'liberty_videocon', 
                    [
                        'enquiryId' => $proposal->user_product_journey_id,
                        'requestMethod' =>'post',
                        'section' => 'car',
                        'productName'  => $product->product_name ?? '',
                        'company'  => 'liberty_videocon',
                        'method'   => 'Generate Policy - Policy No',
                        'transaction_type' => 'proposal',
                    ]);
                    $pdf_res_data = $get_response['response'];

                    if (!empty($pdf_res_data)) {
                        $pdf_response = json_decode($pdf_res_data, TRUE);

                        if (!empty($pdf_response['PolicyNumber']) || ($pdf_response['PolicyNumber'] ?? '') !== 'NA') {

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

                            //$productCode = ($is_od) ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_OD') :(($is_liability) ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_TP') : config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_PACKAGE'));
                            $productCode = $proposal->product_code;
                            if (config('IC.LIBERTY_VIDEOCON.V1.CAR.OLD_POLICY_PDF_SERVICE') == 'Y') {
                                $policy_html = [
                                    'strCustomerGcId' => $proposal['customer_id'],
                                    'strPolicyNumber' => $pdf_response['PolicyNumber'],
                                    'strProductType' => $productCode,
                                    'strTPSourceName' => $tp_source_name
                                ];
                                sleep(10);
                                $url = config('IC.LIBERTY_VIDEOCON.V1.CAR.POLICY_PDF') . '?' . http_build_query($policy_html);

                                //                            sleep('10');
                                //
                                //                            header('Access-Control-Allow-Origin: *');
                                //                            header('Access-Control-Allow-Headers: *');
                                //                            header('Content-type: text/plain');
                                //                            $ch = curl_init();
                                //                            curl_setopt($ch, CURLOPT_URL, $url);
                                //                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                //                            $output = curl_exec($ch);
                                //                            curl_close($ch);

                                $get_response = getWsData(
                                    $url,
                                    '',
                                    'liberty_videocon',
                                    [
                                        'enquiryId'         => $proposal->user_product_journey_id,
                                        'requestMethod'     => 'get',
                                        'section'           => 'CAR',
                                        'transaction_type'  => 'proposal',
                                        'productName'       => $product->product_name ?? '',
                                        'company'           => 'liberty_videocon',
                                        'method'            => 'Generate pdf',
                                    ]
                                );
                                $pdf_data = $get_response['response'];

                                $output = json_decode($pdf_data, TRUE);



                                if (!isset($output['policyBytePDF']) || empty($output['policyBytePDF'])) {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                }

                                Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/' . md5($proposal->user_proposal_id) . '.pdf', base64_decode(trim($output['policyBytePDF'])));
                            } else {
                                $url = config('IC.LIBERTY_VIDEOCON.V2.CAR.POLICY_PDF');
                                $encryption_key = config('IC.LIBERTY_VIDEOCON.V2.CAR.ENCRYPTION_KEY');
                                $IMD_code = self::getImdCode($enquiryId);
                                // $Authorization_key = config('IC.LIBERTY_VIDEOCON.V1.CAR.AUTH_KEY');
                                $Authorization_key = self::getEncryptionKey($enquiryId);
                                $encryptedpolicyno = self::get_encrypt($pdf_response['PolicyNumber'], $encryption_key);
                                $encryptedImdCode = self::get_encrypt($IMD_code, $encryption_key);

                                $pdf_request_data = [
                                    'PolicyNumber' =>  $encryptedpolicyno,
                                    'IMDCode'      =>  $encryptedImdCode
                                ];
                                $additionalData = [
                                    'enquiryId' => $proposal->user_product_journey_id,
                                    'requestMethod' => 'post',
                                    'section' => 'car',
                                    'productName'  => $product->product_name ?? '',
                                    'company'  => 'liberty_videocon',
                                    'method'   => 'Generate pdf',
                                    'transaction_type' => 'proposal',
                                    'headers' => [
                                        'Authorization' => $Authorization_key
                                    ],
                                ];


                                $data = getWsData($url, $pdf_request_data, 'liberty_videocon', $additionalData);
                                if (isset($data['response']) && empty($data['response'])) {
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                }
                                    Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/' . md5($proposal->user_proposal_id) . '.pdf', $data['response']);


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
                                    'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id). '.pdf',
                                    'status' => 'SUCCESS'
                                ]
                            );
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                        } else {
                            
                            $enquiryId = $proposal->user_product_journey_id;
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                        }
                    } else {
                        
                        $enquiryId = $proposal->user_product_journey_id;
                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
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
                    
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));

                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }
            }
        } else {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
    }

    static public function retry_pdf($proposal){
        $policy_details     = DB::table('policy_details')
            ->select('*')
            ->where('proposal_id', $proposal->user_proposal_id)
            ->first();

        if($policy_details->policy_number){
            $create_pdf = libertyVideoconPaymentGateway::create_pdf($proposal, $policy_details->policy_number);

            return response()->json($create_pdf);
        }else{
            return response()->json([
                'status' => false,
                'msg' => 'Policy Number Not Available ...!'
            ]);
        }
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

                $productCode = ($is_od) ? config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_OD') : (($is_liability) ? config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_TP') : config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_PACKAGE'));
            }
        }
        if (config('IC.LIBERTY_VIDEOCON.V1.CAR.OLD_POLICY_PDF_SERVICE') == 'Y') {
            $policy_html = [
                'strCustomerGcId' => $proposal->customer_id,
                'strPolicyNumber' => $policy_no,
                'strProductType' => $productCode,
                'strTPSourceName' => self::getTpSourceName($proposal->user_product_journey_id)
            ];

            $url = config('IC.LIBERTY_VIDEOCON.V1.CAR.POLICY_PDF') . '?' . http_build_query($policy_html);

            $get_response = getWsData(
                $url,
                '',
                'liberty_videocon',
                [
                    'enquiryId'         => $proposal->user_product_journey_id,
                    'requestMethod'     => 'get',
                    'section'           => 'CAR',
                    'transaction_type'  => 'proposal',
                    'productName'       =>  '',
                    'company'           => 'liberty_videocon',
                    'method'            => 'Generate pdf',
                ]
            );
            $pdf_data = $get_response['response'];

            $output = json_decode($pdf_data, TRUE);

            // sleep('10');

            // header('Access-Control-Allow-Origin: *');
            // header('Access-Control-Allow-Headers: *');
            // header('Content-type: text/plain');
            // $ch = curl_init();
            // curl_setopt($ch, CURLOPT_URL, $url);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // $output = curl_exec($ch);
            // curl_close($ch);

            // $output = json_decode($output, TRUE);

            $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/' . md5($proposal->user_proposal_id) . '.pdf';

            $pdf_data = base64_decode(trim($output['policyBytePDF']));
            if (!checkValidPDFData($pdf_data)) {
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
        } else {
            $url = config('IC.LIBERTY_VIDEOCON.V2.CAR.POLICY_PDF');
            $encryption_key = config('IC.LIBERTY_VIDEOCON.V2.CAR.ENCRYPTION_KEY');
            $enquiryId = $proposal->user_product_journey_id;
            $IMD_code = self::getImdCode($enquiryId);
            // $Authorization_key = config('IC.LIBERTY_VIDEOCON.V1.CAR.AUTH_KEY');
            $Authorization_key = self::getEncryptionKey($enquiryId);
            $encryptedpolicyno = self::get_encrypt($policy_no, $encryption_key);
            $encryptedImdCode = self::get_encrypt($IMD_code, $encryption_key);
            $pdf_request_data = [
                'PolicyNumber' =>  $encryptedpolicyno,
                'IMDCode'      =>  $encryptedImdCode
            ];
            $additionalData = [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'post',
                'section' => 'car',
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
                Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/' . md5($proposal->user_proposal_id) . '.pdf', $data['response']);
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
                'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id). '.pdf',
                'status' => 'SUCCESS'
            ]
        );

        return [
            'status' => true,
            'msg' => 'success',
            'data' => [
                'policy_number' => $policy_no,
                // 'pdf_link'      => $pdf_url,
                'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id).'.pdf')
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
                    'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'liberty_videocon/'. md5($proposal->user_proposal_id).'.pdf')
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }




    static public function generatePolicy($proposal, $requestArray)
    {
        $additional_details = json_decode($proposal->additional_details, true);

        // $is_package     = (($proposal->product_type == 'comprehensive') ? true : false);
        // $is_liability   = (($proposal->product_type == 'third_party') ? true : false);
        // $is_od          = (($proposal->product_type == 'own_damage') ? true : false);

        //$productCode = ($is_od) ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_OD') :(($is_liability) ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_TP') : config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_PACKAGE'));
        $productCode = $proposal->product_code;
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

        $pdf_request_data = [
            'GCCustomerID' => $proposal['customer_id'],
            'PremiumAmount' => $proposal['final_payable_amount'],
            'ProductCode' => $productCode,
            'QuotationNumber' => $proposal['unique_proposal_id'],
            'PaymentSource' => config('IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_SOURCE'),
            //'PaymentDate' => date('d/m/Y'),
            'PaymentDate' => date('d/m/Y', strtotime($response['addedon'])),
            'TransactionID' => $proposal['unique_proposal_id'],
            'TPSourceName' => self::getTpSourceName($proposal->user_product_journey_id),
            'TPEmailID' => config('IC.LIBERTY_VIDEOCON.V1.CAR.EMAIL'),
            'SendEmailtoCustomer' => 'true',
            'OTP' => config('IC.LIBERTY_VIDEOCON.V1.CAR.OTP'),
            'OTPCreatedDate' => date('d/m/Y'),
            'OTPEnteredDate' => date('d/m/Y', strtotime('+1 day')),
        ];

        $additional_data = [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' =>'post',
            'company'  => 'liberty_videocon',
            'headers' => [],
            'requestType' => 'json',
            'section' => 'Car',
            'method' => 'Generate Policy - Policy No',
            'transaction_type' => 'proposal',
            'productName' => $requestArray['product_name'],
        ];

        $get_response = getWsData(config('IC.LIBERTY_VIDEOCON.V1.CAR.CREATE_POLICY'), $pdf_request_data, 'liberty_videocon', $additional_data);
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
            $additional_details = json_decode($proposal->additional_details, true);
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

    static public function submitProposal($proposal, $productData)
    {
        $additional_details = json_decode($proposal->additional_details, true);

        $proposal_request = $additional_details['liberty']['proposal_request'];

        $breakinDetails = DB::table('cv_breakin_status')
            ->where([
                'user_proposal_id' => $proposal->user_proposal_id,
                'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED']
            ])
            ->select('*')
            ->first();
        $breakin_data = json_decode($breakinDetails->breakin_response, true);

        $quote_data = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->first();

        $productData = getProductDataByIc($quote_data->master_policy_id);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
            $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', ($proposal->user_product_journey_id))->first();
        $policy_start_date = $policy_end_date = NULL;
        if(in_array($premium_type, ['breakin', 'own_damage_breakin','comprehensive']) && $CorporateVehiclesQuotesRequest->business_type == 'rollover')
        {
            $policy_start_date = date('d-m-Y',strtotime($proposal->policy_start_date));#date('d/m/Y');
            $policy_end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))))));
        }
        else if(in_array($premium_type, ['breakin', 'own_damage_breakin']))
        {
            $policy_start_date = date('d-m-Y');
            $policy_end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))))));
        }
        else if(in_array($premium_type, ['comprehensive']) && $CorporateVehiclesQuotesRequest->business_type == 'breakin')
        {
            $policy_start_date = date('d-m-Y');
            $policy_end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))))));
        }
        if($policy_start_date == NULL)
        {
            return [
                'status' => false,
                'message' => "Invalid Date"
            ];
        }
        // if (in_array($premium_type, ['breakin', 'own_damage_breakin'])) {
        //     $policy_start_date = date('d-m-Y', strtotime("+1 day"));
        //     $policy_end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))))));
        // } else {
        //     $policy_start_date = date('d-m-Y',strtotime($proposal->policy_start_date));#date('d/m/Y');
        //     $policy_end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))))));
        // }

        $proposal_request['QuickQuoteNumber']       = config('IC.LIBERTY_VIDEOCON.V1.CAR.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7);
        $proposal_request['PolicyStartDate'] = date('d/m/Y',strtotime($policy_start_date));
        $proposal_request['PolicyEndDate'] = date('d/m/Y',strtotime($policy_end_date));

        $proposal_request['IsFullQuote']            = 'true';
        $proposal_request['IsInspectionDone']       = 'true';
        $proposal_request['InspectionDoneByWhom']   = 'Agency';
        $proposal_request['ReportDate']             = date('d/m/Y');
        $proposal_request['InspectionDate']         = date('d/m/Y');
        $proposal_request['InspectionReferenceNo']  = $breakinDetails->breakin_id;


        $get_response = getWsData(config('IC.LIBERTY_VIDEOCON.V1.CAR.END_POINT_URL_PREMIUM_CALCULATION'), $proposal_request, 'liberty_videocon', [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'liberty_videocon',
            'section' => $productData->product_sub_type_code,
            'method' =>'Proposal Submit-After Payment',
            'transaction_type' => 'proposal',
        ]);
        $data = $get_response['response'];

        if ($data && $data != '')
        {
            $proposal_response = json_decode($data, TRUE);

            if (trim($proposal_response['ErrorText']) == "") {
                $llpaiddriver_premium = ($proposal_response['LegalliabilityToPaidDriverValue']);
                $cover_pa_owner_driver_premium = ($proposal_response['PAToOwnerDrivervalue']);
                $cover_pa_paid_driver_premium = ($proposal_response['PatoPaidDrivervalue']);
                $cover_pa_unnamed_passenger_premium = ($proposal_response['PAToUnnmaedPassengerValue']);
                $voluntary_excess = ($proposal_response['VoluntaryExcessValue']);
                $anti_theft = ($proposal_response['AntiTheftDiscountValue']);
                $ic_vehicle_discount = ($proposal_response['Loading']) + ($proposal_response['Discount'] ?? 0);
                $ncb_discount = ($proposal_response['DisplayNCBDiscountvalue']);
                $od = ($proposal_response['BasicODPremium']);
                $tppd = ($proposal_response['BasicTPPremium']);
                $cng_lpg = ($proposal_response['FuelKitValueODpremium']);
                $cng_lpg_tp = ($proposal_response['FuelKitValueTPpremium']);
                $zero_depreciation = ($proposal_response['NilDepValue']);
                $road_side_assistance = ($proposal_response['RoadAssistCoverValue']);
                $engine_protection = ($proposal_response['EngineCoverValue']);
                $return_to_invoice = ($proposal_response['GAPCoverValue']);
                $consumables = ($proposal_response['ConsumableCoverValue']);
                $key_protect = ($proposal_response['KeyLossCoverValue'] ?? 0);
                $passenger_assist_cover = ($proposal_response['PassengerAssistCoverValue']);
                $electrical_accessories_amt = ($proposal_response['ElectricalAccessoriesValue']);
                $non_electrical_accessories_amt = ($proposal_response['NonElectricalAccessoriesValue']);

                $addon_premium = $zero_depreciation + $road_side_assistance + $engine_protection + $return_to_invoice + $consumables + $passenger_assist_cover;
                $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
                $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_owner_driver_premium;
                $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $anti_theft;
                $final_net_premium = ($proposal_response['NetPremium']);
                $final_gst_amount = ($proposal_response['GST']);
                $final_payable_amount  = $proposal_response['TotalPremium'];

                UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                    ->update([
                    'od_premium' => $final_od_premium,
                    'tp_premium' => $final_tp_premium,
                    'ncb_discount' => $ncb_discount,
                    'total_discount' => $final_total_discount,
                    'addon_premium' => $addon_premium,
                    'total_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'cpa_premium' => $cover_pa_owner_driver_premium,
                    'proposal_no' => $proposal_response['PolicyID'],
                    'customer_id' => $proposal_response['CustomerID'],
                    'unique_proposal_id' => $proposal_response['QuotationNumber'],
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'is_breakin_case' => 'Y',
                    'additional_details' => $additional_details,
                ]);

                LibertyVideoconPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                $return_data = [
                    'status' => true,
                    'message' => 'Proposal Submited'
                ];
            }
            else
            {
                $return_data = [
                    'status' => false,
                    'message' => $proposal_response['ErrorText']
                ];
            }
        }
        else
        {
            $return_data = [
                'status' => false,
                'message' => 'Insurer not reachable'
            ];
        }
        return $return_data;
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
            $url = config('IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_STATUS_CHECK_END_POINT_URL').$order_id;
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

    static public function getTpSourceName($enquiryId)
    {
        $is_pos = config('constants.motorConstant.IS_POS_ENABLED');

        $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME');

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $enquiryId)
            ->where('seller_type', 'P')
            ->first();

        $quote_data = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_data->idv <= 5000000) {
            if (config('IS_LIBERTY_CAR_NON_POS') != 'Y') {
                $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME_POS');
            }
        }

        return $tp_source_name;
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
            $imd_code = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER_POS'); //this value is passed for only TMIBSL as per git #35205
        }
        else
        {
            $imd_code = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER');
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
            $encryption_key = config('IC.LIBERTY_VIDEOCON.V1.CAR.AUTH_KEY_POS'); //this value is passed for only TMIBSL as per git #35205
        }
        else
        {
            $encryption_key = config('IC.LIBERTY_VIDEOCON.V1.CAR.AUTH_KEY');
        }
        return $encryption_key;
    }
}
