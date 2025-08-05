<?php

namespace App\Http\Controllers\Payment\Services;

use Exception;
use Config;
use stdClass;
use DateTime;
use Carbon\Carbon;

use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use App\Exceptions\Handler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\PaymentRequestResponse;
use App\Models\WebServiceRequestResponse;
use App\Models\WebserviceRequestResponseDataOptionList;
use App\Http\Controllers\Payment\Services\tataAigV2PaymentGateway as TATA_AIG_V2;
use App\Http\Controllers\CommonApi\TataAigApi;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/TataAigCkycHelper.php';

class tataAigPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        if(policyProductType($request->policyId)->parent_id == 4)
        {
          return TATA_AIG_V2::make($request);
        }
        elseif ( config('TATA_AIG_V2_PCV_FLOW') == 'Y' && in_array(policyProductType($request->policyId)->parent_id,[6,8])) 
        {
            return TATA_AIG_V2::make($request);
        }
        $enquiryId      = customDecrypt($request->enquiryId);
        $user_proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        
        $quote_log_id   = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();

        $icId           = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_ENABLED_TATA_AIG') == 'Y' && $user_proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'I' && config('constants.IcConstants.tata_aig_v2.IS_NEW_CKYC_FLOW_ENABLED_FOR_TATA_AIG_V2') == 'Y' && $user_proposal->proposer_ckyc_details->is_document_upload == 'Y') {
            $updateCkycDetails = updateCkycDetails($user_proposal);

            if ( ! $updateCkycDetails['status']) {
                return response()->json($updateCkycDetails);
            }
        }

        $payment_url = config('constants.IcConstants.tata_aig.END_POINT_PAYMENT_BEFORE_URL');
        $form_data = [
            'proposal_no' => $user_proposal->proposal_no,
            'src' => config('constants.IcConstants.tata_aig.SRC'),
        ];
        $payment_url = $payment_url . '?' .http_build_query($form_data);
        $return_data = [
            'form_action' => $payment_url,
            'form_method' => config('constants.IcConstants.tata_aig.END_POINT_PAYMENT_BEFORE_URL_METHOD'),
            'payment_type' => 0, // form-submit
            'form_data' => $form_data
        ];
        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('user_proposal_id', $user_proposal->user_proposal_id)
        ->update([
            'active'      => 0,
            // 'updated_at'    => date('Y-m-d H:i:s')
        ]);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'proposal_no'               => $user_proposal->proposal_no,
            'order_id'                  => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            // 'payment_url'               => config('constants.IcConstants.tata_aig.END_POINT_PAYMENT_BEFORE_URL'),
            'payment_url'               => $payment_url,
            'return_url'                => route(
                'cv.payment-confirm',
                [
                    'tata_aig',
                    'user_proposal_id'      => $user_proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]
            ),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'xml_data'                  => json_encode($form_data)
        ]);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }

    public static function confirm($request)
    {

        // echo "<pre>";print_r([$request->all()]);echo "</pre>";die();
        $response = json_decode(base64_decode(request()->response), true);

        $PaymentRequestResponse = PaymentRequestResponse::where('proposal_no', $response['data']['proposalno'])
            ->select('*')
            ->first();

        if(empty($PaymentRequestResponse))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        $user_proposal = UserProposal::where('user_proposal_id', $PaymentRequestResponse->user_proposal_id)
            ->orderBy('user_proposal_id', 'desc')
            ->select('*')
            ->first();

        if(empty($user_proposal))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('user_proposal_id', $user_proposal->user_proposal_id)
        ->where('proposal_no', $response['data']['proposalno'])
        ->where('active', 1)
        ->update([
            'response'      => request()->response,
            'updated_at'    => date('Y-m-d H:i:s'),
            'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
        ]);

        if ($response['data']['status'] == "0") {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->where('active', 1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['ic_id'] = $user_proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
        else
        {

            
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->update([
                'active'      => 0,
                // 'updated_at'    => date('Y-m-d H:i:s')
            ]);

            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('proposal_no', $response['data']['proposalno'])
            ->where('order_id',$response['data']['proposalno'])
            ->orderBy("id", "DESC")
            ->limit(1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                'active'        => 1
            ]);
        }
        

        $additional_details = json_decode($user_proposal->additional_details, true);
        $additional_details['tata_aig']['rnd_key'] = $response['data']['rnd_str'];
        $additional_details['tata_aig']['policy_no'] = $response['data']['policyno'];

        UserProposal::updateOrCreate(
            ['user_proposal_id' => $user_proposal->user_proposal_id],
            [
                'additional_details' => json_encode($additional_details),
            ]
        );

        $http_url = http_build_query(['polno' => $response['data']['policyno'], 'src' => 'app', 'key' => $response['data']['rnd_str']]);
        $pattern = '/%2F/';
        $finalQuery = preg_replace($pattern, '/', $http_url);

        // $ic_pdf_url = config('constants.IcConstants.tata_aig.cv.END_POINT_URL_POLICY_GENERATION') . '?' . http_build_query(['polno' => $response['data']['policyno'], 'src' => 'app', 'key' => $response['data']['rnd_str']]);

        $ic_pdf_url = config('constants.IcConstants.tata_aig.cv.END_POINT_URL_POLICY_GENERATION') . '?' . $finalQuery;

        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];

        updateJourneyStage($data);

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $user_proposal->user_proposal_id],
            [
                'policy_number' => $response['data']['policyno'],
                'ic_pdf_url' => $ic_pdf_url,
            ]
        );

        if(!empty($response['data']['proposalno']))
        {
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            //->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->update([
                'active'      => 0,
                // 'updated_at'    => date('Y-m-d H:i:s')
            ]);

            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('proposal_no', $response['data']['proposalno'])
            ->where('order_id',$response['data']['proposalno'])
            ->orderBy("id", "DESC")
            ->limit(1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                'active'        => 1,
                'updated_at'    => date('Y-m-d H:i:s')
            ]);
        }

        try{
		    $startTime = new DateTime(date('Y-m-d H:i:s'));
            $pdfGenerationResponse = httpRequestNormal($ic_pdf_url, 'GET', [], [], [], [], false)['response'];

            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $responseTime = $startTime->diff($endTime);

            $wsLogdata = [
                'enquiry_id'     => $user_proposal->user_product_journey_id,
                'product'       => '',
                'section'       => 'Cv',
                'method_name'   => 'PDF Service - Confirm',
                'company'       => 'tata_aig',
                'method'        => 'get',
                'transaction_type' => 'proposal',
                'request'       => $ic_pdf_url ?? '',
                'response'      => base64_encode($pdfGenerationResponse), 
                'endpoint_url'  => $ic_pdf_url,
                'ip_address'    => request()->ip(),
                'start_time'    => $startTime->format('Y-m-d H:i:s'),
                'end_time'      => $endTime->format('Y-m-d H:i:s'),
                // 'response_time'	=> $responseTime->format('%H:%i:%s'),
                'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
                'created_at'    => Carbon::now(),
                'headers'       => null
            ];

            WebServiceRequestResponse::create($wsLogdata);

            WebserviceRequestResponseDataOptionList::firstOrCreate([
                'company' => 'tata_aig',
                'section' => 'Cv',
                'method_name' => 'PDF Service - Confirm',
            ]);

            if(!checkValidPDFData($pdfGenerationResponse))
            {
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                if(!empty($user_proposal->user_product_journey_id))
                {
                    PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    //->where('user_proposal_id', $user_proposal->user_proposal_id)
                    ->update([
                        'active'      => 0,
                        // 'updated_at'    => date('Y-m-d H:i:s')
                    ]);

                    PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                    //->where('proposal_no', $response['data']['proposalno'])
                    //->where('order_id',$response['data']['proposalno'])
                    ->orderBy("id", "DESC")
                    ->limit(1)
                    ->update([
                        'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                        'active'        => 1,
                        'updated_at'    => date('Y-m-d H:i:s')
                    ]);
                }

                return redirect(config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
            }

            $proposal_pdf = Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'tata_aig/' . md5($user_proposal->user_proposal_id) . '.pdf', $pdfGenerationResponse);
            // $proposal_pdf = Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'tata_aig/' . md5($user_proposal->user_proposal_id) . '.pdf', file_get_contents($ic_pdf_url));
        }
        catch(Exception $e)
        {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
        }

        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];


        PolicyDetails::updateOrCreate(
            ['proposal_id' => $user_proposal->user_proposal_id],
            [
                'policy_number' => $response['data']['policyno'],
                'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'tata_aig/' . md5($user_proposal->user_proposal_id) . '.pdf',
                'ic_pdf_url' => $ic_pdf_url
            ]
        );

        // Updating journey stage after insert into policy details because after during data push PDF url was missing
        // as event trigger is added in the journey stage update.
        updateJourneyStage($data);

        if(!empty($user_proposal->user_product_journey_id))
        {
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->update([
                'active'      => 0
            ]);
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->orderBy("id", "DESC")
            ->limit(1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                'active'        => 1,
                'updated_at'    => date('Y-m-d H:i:s')
            ]);
        }
        sleep(2);
        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
    }

    

    static public function create_pdf($proposal, $policy_number, $policy_detail)
    {
        self::updatePaymentSuccessEntry($proposal, $policy_detail);
        $additional_details = json_decode($proposal->additional_details, true);

        if(tataAigPaymentGateway::isvalidIcPDFUrl($policy_detail->ic_pdf_url)){
            $ic_pdf_url = $policy_detail->ic_pdf_url;
        }
        else
        {
            if(empty($policy_number) || empty($additional_details['tata_aig']['rnd_key'] ?? ''))
            {
                return [
                    'status' => false,
                    'msg'    => "policy number and rnd_key is mandatory for policy issurane",
                    'data'   => $additional_details['tata_aig'] ?? []
                ];
            }

            // $ic_pdf_url = config(
            //     'constants.IcConstants.tata_aig.cv.END_POINT_URL_POLICY_GENERATION')
            // . '?'
            // . http_build_query(
            //     [
            //         'polno' => $policy_number,
            //         'src' => 'app',
            //         'key' => $additional_details['tata_aig']['rnd_key']
            //     ]
            // );

            $http_url = http_build_query(['polno' => $policy_number, 'src' => 'app', 'key' => $additional_details['tata_aig']['rnd_key']]);
            $pattern = '/%2F/';
            $finalQuery = preg_replace($pattern, '/', $http_url);

            $ic_pdf_url = config('constants.IcConstants.tata_aig.cv.END_POINT_URL_POLICY_GENERATION'). '?' . $finalQuery;
        }

        $pdf_url = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'tata_aig/'. md5($proposal->user_proposal_id).'.pdf';

        try
        {
            
		    $startTime = new DateTime(date('Y-m-d H:i:s'));
            $pdfGenerationResponse = httpRequestNormal($ic_pdf_url, 'GET', [], [], [], [], false)['response'];

            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $responseTime = $startTime->diff($endTime);

            $wsLogdata = [
                'enquiry_id'     => $proposal->user_product_journey_id,
                'product'       => '',
                'section'       => 'Cv',
                'method_name'   => 'PDF Service - create_pdf',
                'company'       => 'tata_aig',
                'method'        => 'get',
                'transaction_type' => 'proposal',
                'request'       => $ic_pdf_url ?? '',
                'response'      => base64_encode($pdfGenerationResponse),
                'endpoint_url'  => $ic_pdf_url,
                'ip_address'    => request()->ip(),
                'start_time'    => $startTime->format('Y-m-d H:i:s'),
                'end_time'      => $endTime->format('Y-m-d H:i:s'),
                // 'response_time'	=> $responseTime->format('%H:%i:%s'),
                'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
                'created_at'    => Carbon::now(),
                'headers'       => null
            ];

            WebServiceRequestResponse::create($wsLogdata);

            WebserviceRequestResponseDataOptionList::firstOrCreate([
                'company' => 'tata_aig',
                'section' => 'Cv',
                'method_name' => 'PDF Service - create_pdf',
            ]);

            if(!checkValidPDFData($pdfGenerationResponse))
            {
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                return [
                    'status' => true,
                    'msg' =>  STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                    'data' => [
                        'policy_number' => $policy_number
                    ]
                ];
            }

            Storage::put($pdf_url, $pdfGenerationResponse);

            PaymentRequestResponse::where('user_proposal_id', $proposal->user_proposal_id)
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('id', $policy_detail->id)
                ->where('order_id',$proposal->proposal_no) 
                ->update([
                    'active'    => 1,
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);
        }
        catch(Exception $e)
        {
            return [
                'status' => false,
                'msg'    => $e->getMessage(),
                'data'   => $additional_details['tata_aig']['generate_policy_response']
            ];
        }

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['POLICY_ISSUED']
        ]);


        PolicyDetails::updateOrCreate(
            ['proposal_id' => $proposal->user_proposal_id],
            [
                'policy_number' => $policy_number,
                'ic_pdf_url' => $ic_pdf_url,
                'pdf_url' => $pdf_url,
                'status' => 'SUCCESS'
            ]
        );

        return [
            'status' => true,
            'msg' => 'sucess',
            'data' => [
                'policy_number' => $policy_number,
                'pdf_link'      => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'tata_aig/'. md5($proposal->user_proposal_id).'.pdf')
            ]
        ];
    }

    static public function generatePdf($request)
    {
        if (config('TATA_AIG_V2_PCV_FLOW') == 'Y' && ($request->product_type == "pcv")) {
            return TATA_AIG_V2::generatePdf($request);
        }
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            // ->where('prr.active',1)
            ->select('prr.id', 'prr.user_product_journey_id', 'up.user_proposal_id', 'up.user_proposal_id', 'prr.proposal_no', 'up.unique_proposal_id', 'up.product_code', 'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url', 'prr.lead_source', 'prr.amount', 'prr.created_at'
            )
            ->get();
        $userProposal = UserProposal::select('user_product_journey_id', 'user_proposal_id', 'proposal_no', 'additional_details')
        ->where('user_product_journey_id', $user_product_journey_id)->first();
        if(empty($policy_details))
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Payment is pending'
            ]);
        }

        $return_data = [
            'status' => false,
            'msg'    => 'Payment is pending'
        ];
    
        foreach ($policy_details as $policy_detail) {
            if(empty($policy_detail) || empty($policy_detail->policy_number))
            {
                $proposalStatusService = tataAigPaymentGateway::generatePolicy($userProposal, $request->all(), $policy_detail);

                if($proposalStatusService['status']){
                    return tataAigPaymentGateway::create_pdf($userProposal, $proposalStatusService['data']['policy_number'], $policy_detail);
                }
            }
            else
            {
                if(!empty($policy_detail->pdf_url))
                {
                    self::updatePaymentSuccessEntry($userProposal, $policy_detail);
                    $current_policy_url = Storage::url($policy_detail->pdf_url);
                    $startTime = new DateTime(date('Y-m-d H:i:s'));
                    $current_policy_data = httpRequestNormal($current_policy_url, 'GET', [], [], [], [], false)['response'];
        
                    $endTime = new DateTime(date('Y-m-d H:i:s'));
                    $responseTime = $startTime->diff($endTime);
        
                    $wsLogdata = [
                        'enquiry_id'     => $userProposal->user_product_journey_id,
                        'product'       => '',
                        'section'       => 'Cv',
                        'method_name'   => 'PDF Service - generatePdf',
                        'company'       => 'tata_aig',
                        'method'        => 'get',
                        'transaction_type' => 'proposal',
                        'request'       => $ic_pdf_url ?? '',
                        'response'      => base64_encode($current_policy_data),
                        'endpoint_url'  => $current_policy_url,
                        'ip_address'    => request()->ip(),
                        'start_time'    => $startTime->format('Y-m-d H:i:s'),
                        'end_time'      => $endTime->format('Y-m-d H:i:s'),
                        // 'response_time'	=> $responseTime->format('%H:%i:%s'),
                        'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
                        'created_at'    => Carbon::now(),
                        'headers'       => null
                    ];
        
                    WebServiceRequestResponse::create($wsLogdata);
        
                    WebserviceRequestResponseDataOptionList::firstOrCreate([
                        'company' => 'tata_aig',
                        'section' => 'Cv',
                        'method_name' => 'PDF Service - generatePdf',
                    ]);

                    if(!checkValidPDFData($current_policy_data))
                    {
                        return tataAigPaymentGateway::create_pdf($userProposal, $policy_detail->policy_number, $policy_detail); 
                    }
                    return response()->json([
                        'status' => true,
                        'msg' => 'success',
                        'data' => [
                            'policy_number' => $policy_detail->policy_number,
                            'pdf_link' => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'tata_aig/'. md5($policy_detail->user_proposal_id). '.pdf'),//$policy_detail->pdf_url,
                            'ic_pdf_url' => $policy_detail->ic_pdf_url,
                        ]
                    ]);
                }
                return tataAigPaymentGateway::create_pdf($userProposal, $policy_detail->policy_number, $policy_detail); 
            }
        }
        return $return_data;
    }

    static public function generatePolicy($proposal, $requestArray, $policy_detail)
    {
        $additional_details = json_decode($proposal->additional_details, true);

        $transNo = '';

        if(config('constants.IcConstants.tata_aig.PROPOSAL_PAYMENT_STATUS_ENABLE') == 'Y')
        {
            $transNo = tataAigPaymentGateway::checkPaymentStaus($policy_detail->proposal_no, $requestArray, $proposal);
        }

        $input_array = [
            'paymenttype' => 'CPI',
            'proposalno'  => $policy_detail->proposal_no,
            'source'      => config('constants.IcConstants.tata_aig.SRC'),
            'transno'     => $transNo,
            'transdate'   => Carbon::parse($policy_detail->created_at)->format('Ymd'),
            'premiumamt'  => $policy_detail->amount,
            'paydat'      => Carbon::parse($policy_detail->created_at)->format('Ymd'),
        ];

        $additional_data = [
          'enquiryId' => $proposal->user_product_journey_id,
          'headers' => [],
          'requestMethod' => 'post',
          'requestType' => 'json',
          'section' => 'Cv',
          'method' => 'Generate Policy - Policy No',
          'transaction_type' => 'proposal',
          'productName' => $requestArray['product_name'],
        ];

        $inputArray = [
          'PDATA' => json_encode($input_array),
          'T' => config('constants.IcConstants.tata_aig.TOKEN'),
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_POLICY_NO_GENERATION'), $inputArray, 'tata_aig', $additional_data);
        $response = $get_response['response'];

        $response = json_decode($response, true);

        if (empty($response) || !isset($response['status'])) {
            return [
                'status' => false,
                'msg'    => 'Insurer Not Found',
                'data'   => []
            ];
        }

        if($response['status'] != '1' || !isset($response['data']['policyno']))
        {
            return [
                'status' => false,
                'msg'    => $response['data']['message'],
                'data'   => []
            ];
        }
        else
        {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            //->where('user_proposal_id', $proposal->user_proposal_id)
            ->update([
                'active'      => 0,
            ]);

            DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            //->where('user_proposal_id', $proposal->user_proposal_id)
            //->where('id', $policy_detail->id)
            //->where('order_id',$proposal->proposal_no)
            ->orderBy("id", "DESC")
            ->limit(1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                'active'        => '1',
                'response'      => base64_encode(json_encode([
                    'status' => '1',
                    'data' => [
                        'proposalno' => $policy_detail->proposal_no,
                        'policyno' => $response['data']['policyno'],
                        'rnd_str' => $response['data']['key'],
                        'status' => '1',
                    ],
                ])),
            ]);

            $additional_details['tata_aig']['rnd_key'] = $response['data']['key'];
            $additional_details['tata_aig']['policy_no'] = $response['data']['policyno'];
            $additional_details['tata_aig']['generate_policy_response'] = $response;

            UserProposal::updateOrCreate(
                ['user_proposal_id' => $proposal->user_proposal_id],
                [
                    'additional_details' => json_encode($additional_details),
                ]
            );

            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];

            updateJourneyStage($data);

            PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_number' => $response['data']['policyno'],
                ]
            );

            return [
                'status' => true,
                'msg'    => 'policy no generated successfully',
                'data'   => [
                    'policy_number' => $response['data']['policyno'],
                ]
            ];
        }
    }

    static public function checkPaymentStaus($proposalNo, $requestArray, $proposal)
    {
        $inputArray = [
            'appID' => config('constants.IcConstants.tata_aig.SRC'),
            'txnid' => $proposalNo,
        ];

        $additional_data = [
            'enquiryId' => $proposal->user_product_journey_id,
            'headers' => [],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Cv',
            'method' => 'checkProposalPaymentStatus',
            'transaction_type' => 'proposal',
            'productName' => $requestArray['product_name'],
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ];

        $response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_PROPOSAL_PAYMENT_STATUS_CHECK'), $inputArray, 'tata_aig', $additional_data)['response'];

        $get_response = json_decode($response, 1);

        if(!$response || !$get_response || !isset($get_response['serviceResponse']) || empty($get_response['serviceResponse']) || empty(json_decode($get_response['serviceResponse'])))
        {
            return '';
        }

        $serviceResponse = json_decode($get_response['serviceResponse'], 1);

        if(isset($serviceResponse[0]))
        {
            foreach ($serviceResponse as $key => $value) {
                if(isset($value['txn_status']) && strtoupper($value['txn_status']) == 'SUCCESS' && isset($value['gateway_txn_id']) && !empty($value['gateway_txn_id']))
                {
                    return $value['gateway_txn_id'];
                }
            }
        }
        return '';
    }

    static public function isvalidIcPDFUrl($url){
        if(empty($url)){
            return false;
        }

        parse_str(parse_url($url)['query'], $param);

        return (!empty($param['polno'] ?? '') && !empty($param['key'] ?? ''));
    }

    static public function updatePaymentSuccessEntry($proposal, $policy_detail)
    {
        $paymentSuccessEntry = PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('status', STAGE_NAMES['PAYMENT_SUCCESS'])->first();
        
        if(!empty($paymentSuccessEntry)){
            return true;
        }

        PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
        ->update([
            'active'      => 0,
        ]);

        $PaymentRequestResponseData = PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('id', $policy_detail->id)->get();

        if (!empty($PaymentRequestResponseData))
        {
            PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('id', $policy_detail->id)
                ->where('order_id', $proposal->proposal_no)
                ->update([
                    'active' => 1,
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
        }
        else
        {
            PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->orderBy("id", "DESC")
                ->where('order_id', $proposal->proposal_no)
                ->limit(1)
                ->update([
                    'active' => 1,
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
        }
        return true;
    }
}
