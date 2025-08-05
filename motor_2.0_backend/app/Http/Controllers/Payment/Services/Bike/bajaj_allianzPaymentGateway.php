<?php

namespace App\Http\Controllers\Payment\Services\Bike;
use App\Models\UserProposal;
use App\Http\Controllers\Controller;
use App\Models\QuoteLog;
use Illuminate\Http\Request;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\PolicyDetails;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';


class bajaj_allianzPaymentGateway extends Controller
{
    public static function make($request)
    {

        $enquiry_id = customDecrypt($request->userProductJourneyId);
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiry_id)
        ->pluck('quote_id')
        ->first();

        $payment_url = $user_proposal['payment_url'];
        DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiry_id)
            ->update(['active' => 0]);

        updateJourneyStage([
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['PAYMENT_INITIATED']
        ]);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiry_id,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            'payment_url'               => trim($payment_url),
            'return_url'                => route('bike.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiry_id,'policy_id' => $request['policyId']]),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'payment_type' => 1,
                'paymentUrl' => trim($payment_url)
            ]
        ]);

    } //EO make

    public static function confirm($request) {
        $request_data = $pg_return_data = $request->all();
        unset($pg_return_data['enquiry_id'],$pg_return_data['policy_id']);
        $enquiryId = $request_data['enquiry_id'];
        DB::table('payment_request_response')
        ->where('user_product_journey_id', $enquiryId)
        ->where('active',1)
        ->update([
            'response' => json_encode($pg_return_data),
            ]);
        
        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $policy_id = $request_data['policy_id'] ?? $quote_log_data['master_policy_id'];
        $productData = getProductDataByIc($policy_id);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $stage_data = [];
        $bajaj_new_tp_url = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE");

        if ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') {
            $status = $request_data['status'];
            $policy_no = $request_data['referenceno'];
        } else {
            $status = $request_data['p_pay_status'];
            $policy_no = $request_data['p_policy_ref'];
        }

        if(((strtoupper($policy_no) != 'NULL') && (in_array($status, ['Y', 'success'])))) { 
            //if Payment Success and policy number is not null
            UserProposal::where('user_proposal_id' , $user_proposal['user_proposal_id'])
            ->update([
                'policy_no'      => $policy_no,
            ]);

            DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->where('active',1)
            ->update([
                'status'   => STAGE_NAMES['PAYMENT_SUCCESS'] ,
            ]);
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal['user_proposal_id']],
                [
                    'policy_number' => $policy_no,
                    'ic_pdf_url' => ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') ? config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP') 
                    : config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR'),
                    'idv' => $user_proposal['idv'] ,
                    'policy_start_date' => $user_proposal['policy_start_date'] ,
                    'ncb' => $user_proposal['ncb_discount'] ,
                    'premium' => $user_proposal['final_payable_amount'] ,
                ]
            );
            $userId = self::getUserIdAuthName($enquiryId);//config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE");
            if ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') {
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PASSWORD_TP");
            } else {
                $password = config("constants.motor.bajaj_allianz.AUTH_PASS_BAJAJ_ALLIANZ_BIKE");
            }
            
            $issue_array = [
                "userid" => $userId,
                "password" => $password,
                "pdfmode" => ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') ? "MOTOR" : "WS_POLICY_PDF",
                "policynum" => $policy_no,
            ];

            $additional_data = [
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'section' => 'Bike',
                'method' => 'Generate Policy Motor',
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal',
            ];

            if ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') {
                $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP'), $issue_array, 'bajaj_allianz', $additional_data);
            } else {
                $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR'), $issue_array, 'bajaj_allianz', $additional_data);
            }
            $response = $get_response['response'];
            $pdf_data = json_decode($response, true);
            if (isset($pdf_data) && $pdf_data['fileByteObj'] != '' && $pdf_data['fileByteObj'] !== NULL) {
                $pdf_path = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'bajaj_allianz/'. md5($user_proposal['user_proposal_id']). '.pdf';
                Storage::put($pdf_path, trim(base64_decode($pdf_data['fileByteObj'])));
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal['user_proposal_id']],
                    [
                        'pdf_url' => $pdf_path,
                        'status' => 'SUCCESS'
                    ]
                );

                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($stage_data);
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    if(\Illuminate\Support\Facades\Storage::exists('ckyc_photos/'.customEncrypt($user_proposal['user_product_journey_id']))) 
                    {
                        \Illuminate\Support\Facades\Storage::deleteDirectory('ckyc_photos/'.customEncrypt($user_proposal['user_product_journey_id']));
                    }
                }
                //$enquiryId = $proposal->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
            
                //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]));
                
            } else {
                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
                //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]));
            }
        } else if($status == 'Y' || $status == 'success') {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->where('active',1)
            ->update([
                'status'   => STAGE_NAMES['PAYMENT_SUCCESS'] ,
                ]);
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($stage_data);
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]));
        } else {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->where('active',1)
            ->update([
                'status'   => STAGE_NAMES['PAYMENT_FAILED'] ,
                ]);
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($stage_data);
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','FAILURE'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
        }

    } //EO confirm

    public static function generatePdf($request) {
        $request_data = $request->all();
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $productData = getProductDataByIc($request_data['master_policy_id']);
        $is_policy_no = FALSE;
        $policy_no = FALSE;

        $payment_log = DB::table('payment_request_response')
        ->where('user_product_journey_id', $user_product_journey_id)
        ->where('active', 1)
        ->first();

        // PAYMENT STATUS //
        if($user_proposal['policy_no'] == NULL) {
            $order_id = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->distinct()
                ->pluck('order_id')->toArray();
            array_push($order_id, $user_proposal['proposal_no']);
            $order_id = array_unique($order_id);
            foreach ($order_id as $proposal_no) {
                $status_array = [
                    'pRequestId' => $proposal_no,
                    'flag' => 'WS_MOTOR'
                ];

                $additional_data = [
                    'enquiryId' => $user_product_journey_id,
                    'requestMethod' => 'post',
                    'requestType' => 'JSON',
                    'section' => 'Bike',
                    'method' => 'Recon_PG_Status',
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                ];

                $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CHECK_PG_TRANS_STATUS'), $status_array, 'bajaj_allianz', $additional_data);
            }
            $response = $get_response['response'];

            $status_data = json_decode($response, true);
            if ($status_data['pTransStatus'] == 'Y' && !empty($status_data['pPolicyRef'])) {
                $policy_no = $status_data['pPolicyRef'];
                $is_policy_no  = TRUE;
                UserProposal::where('user_proposal_id' , $user_proposal['user_proposal_id'])
                ->update([
                    'policy_no' => $policy_no,
                ]);

                PaymentRequestResponse::where('user_product_journey_id', $user_proposal['user_product_journey_id'])
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);

                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal['user_proposal_id']],
                    [
                        'policy_number' => $policy_no,
                        'ic_pdf_url' => config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR'),
                        'idv' => $user_proposal['idv'] ,
                        'policy_start_date' => $user_proposal['policy_start_date'] ,
                        'ncb' => $user_proposal['ncb_discount'] ,
                        'premium' => $user_proposal['final_payable_amount'] ,
                    ]
                );

                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);
            } else if (
                isset($status_data['errorlist'][0]['errtext']) 
                && !empty($status_data['errorlist'][0]['errtext']) 
                && $status_data['errorcode'] == 1) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Policy number generation API failed : ' . $status_data['errorlist'][0]['errtext']
                ]);
            }
        }
        // EO PAYMENT STATUS //

        // PDF GENERATION //
        if(($user_proposal['policy_no'] != NULL || $is_policy_no  == TRUE)) {
            // policy number is either present in table or generated with above api
            $policy_no = !empty($policy_no) ? $policy_no : $user_proposal['policy_no'] ;

            $userId  = self::getUserIdAuthName($user_product_journey_id);//config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE");
            $bajaj_new_tp_url = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE");
            if ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') {
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PASSWORD_TP");
            } else {
                $password = config("constants.motor.bajaj_allianz.AUTH_PASS_BAJAJ_ALLIANZ_BIKE");
            }

            $issue_array = [
                "userid" => $userId,
                "password" => $password,
                "pdfmode" => ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') ? "MOTOR" : "WS_POLICY_PDF",
                "policynum" => $policy_no,
            ];

            $additional_data = [
                'enquiryId' =>$user_product_journey_id,
                'requestMethod' => 'post',
                'section' => 'Bike',
                'method' => 'Generate Policy Motor',
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal',
            ];

            if ($productData->premium_type_code == 'third_party' && $bajaj_new_tp_url == 'Y') {
                $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP'), $issue_array, 'bajaj_allianz', $additional_data);
            } else {
                $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR'), $issue_array, 'bajaj_allianz', $additional_data);
            }
            $response = $get_response['response'];
            $pdf_data = json_decode($response, true);
            if (isset($pdf_data) && $pdf_data['fileByteObj'] != '' && $pdf_data['fileByteObj'] !== NULL) {
                $pdf_path = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'bajaj_allianz/'. md5($user_proposal['user_proposal_id']). '.pdf';
                Storage::put($pdf_path, trim(base64_decode($pdf_data['fileByteObj'])));
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal['user_proposal_id']],
                    [
                        'pdf_url' => $pdf_path,
                        'status' => 'SUCCESS'
                    ]
                );

                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($stage_data);

                return response()->json([
                    'status' => true,
                    'msg' => STAGE_NAMES['POLICY_ISSUED'],
                    'data' => [
                        'policy_number' => $policy_no,
                        'pdf_link' => file_url($pdf_path)
                    ]
                ]);
            } else {
                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);

                return response()->json([
                    'status' => false,
                    'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Policy Number Not Found'
            ]);
        }
        // EO PDF GENERATION //

    } //EO generatePdf

    // start getUserIdAuthName
    // user ID or AUTH_NAME for POS and NON POS is different
    static public function getUserIdAuthName($user_product_journey_id)
    {
        $userId = config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE");

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

        if(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
            $userId = config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE_POS");
        }

        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $user_product_journey_id)
        ->where('seller_type','P')
        ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
        {
            if($pos_data) {
                $userId = config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE_POS");
            }
        }
        return $userId;
    }
    // end getUserIdAuthName
}
