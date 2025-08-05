<?php
namespace App\Http\Controllers\Payment\Services;

use App\Http\Controllers\Controller;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
class bajaj_allianzPaymentGateway extends Controller
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
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
            'stage' => STAGE_NAMES['PAYMENT_INITIATED'],
        ]);

        DB::table('payment_request_response')->insert([
            'quote_id' => $quote_log_id,
            'user_product_journey_id' => $enquiry_id,
            'user_proposal_id' => $user_proposal->user_proposal_id,
            'ic_id' => $icId,
            'order_id' => $user_proposal->proposal_no,
            'amount' => $user_proposal->final_payable_amount,
            'payment_url' => trim($payment_url),
            'return_url' => route('cv.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiry_id, 'policy_id' => $request['policyId']]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'active' => 1,
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'payment_type' => 1,
                'paymentUrl' => trim($payment_url),
            ],
        ]);

    } // EO make()

    public static function confirm($request)
    {
        $request_data = $pg_return_data = $request->all();
        unset($pg_return_data['enquiry_id'], $pg_return_data['policy_id']);
        $enquiryId = $request_data['enquiry_id'];
        if(empty($enquiryId))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->where('active', 1)
            ->update([
                'response' => json_encode($pg_return_data),
            ]);

        $productData = getProductDataByIc($request_data['policy_id']);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $stage_data = [];

        $status = $request_data['p_pay_status'];
        $policy_no = $request_data['p_policy_ref'];
        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
        $stage_data['ic_id'] = $user_proposal['ic_id'];
        $stage_data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
        updateJourneyStage($stage_data);
        if (((strtoupper($policy_no) != 'NULL') && ($status == 'Y'))) {
            //if Payment Success and policy number is not null
            UserProposal::where('user_proposal_id', $user_proposal['user_proposal_id'])
                ->update([
                    'policy_no' => $policy_no,
                ]);

            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                ]);
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
            updateJourneyStage($stage_data);
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal['user_proposal_id']],
                [
                    'policy_number' => $policy_no,
                    'ic_pdf_url' => config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR'),
                    'idv' => $user_proposal['idv'],
                    'policy_start_date' => $user_proposal['policy_start_date'],
                    'ncb' => $user_proposal['ncb_discount'],
                    'premium' => $user_proposal['final_payable_amount'],
                ]
            );

            $product_sub_types = [
                'AUTO-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_AUTO_RICKSHAW_KIT_TYPE'),
                'TAXI' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TAXI_KIT_TYPE'),
                'ELECTRIC-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_E_RICKSHAW_KIT_TYPE'),
                'PICK UP/DELIVERY/REFRIGERATED VAN' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_PICKUP_DELIVERY_VAN_KIT_TYPE'),
                'DUMPER/TIPPER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_DUMPER_TIPPER_KIT_TYPE'),
                'TRUCK' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRUCK_KIT_TYPE'),
                'TRACTOR' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRACTOR_KIT_TYPE'),
                'TANKER/BULKER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TANKER_BULKER_KIT_TYPE')
            ];

            $is_pos = config('constants.motorConstant.IS_POS_ENABLED');

            $user_product_journey = UserProductJourney::find($enquiryId);
            $quote_log = $user_product_journey->quote_log;
            $agent_details = $user_product_journey->agent_details;

            if ($product_sub_types[$productData->product_sub_type_code] == 'JSON')
            {
                $userId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_USER_ID");
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PASSWORD");

                if ($quote_log->idv <= 5000000 && (($is_pos == 'Y' && $agent_details && isset($agent_details[0]->seller_type) && $agent_details[0]->seller_type == 'P') || config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y'))
                {
                    $userId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_USERNAME");
                    $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_PASSWORD");
                }
            }
            else
            {
                $userId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_USERNAME");
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PASSWORD");
            }

            $issue_array = [
                "userid" => $userId,
                "password" => $password,
                "pdfmode" => "WS_POLICY_PDF",
                "policynum" => $policy_no,
            ];

            $additional_data = [
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'section' => 'CV',
                'method' => 'Generate Policy Motor',
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal',
            ];

            $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR'), $issue_array, 'bajaj_allianz', $additional_data);
            $response = $get_response['response'];
            $pdf_data = json_decode($response, true);
            if (isset($pdf_data) && $pdf_data['fileByteObj'] != '' && $pdf_data['fileByteObj'] !== null) {
                $pdf_path = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'bajaj_allianz/' . md5($user_proposal['user_proposal_id']) . '.pdf';
                Storage::put($pdf_path, trim(base64_decode($pdf_data['fileByteObj'])));
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal['user_proposal_id']],
                    [
                        'pdf_url' => $pdf_path,
                        'status' => 'SUCCESS',
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
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CV', 'SUCCESS'));

            } else {
                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CV', 'SUCCESS'));
            }
        } else if ($status == 'Y') {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                ]);
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($stage_data);

            return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CV', 'SUCCESS'));
        } else {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_FAILED'],
                ]);
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($stage_data);

            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
    } //EO confirm()

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
            $status_array = [
                'pRequestId' => $user_proposal['proposal_no'],
                'flag' => 'WS_MOTOR'
            ];

            $additional_data = [
                'enquiryId' => $user_product_journey_id,
                'requestMethod' => 'post',
                'requestType' => 'JSON',
                'section' => 'CV',
                'method' => 'Recon_PG_Status',
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal',
            ];

            $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CHECK_PG_TRANS_STATUS'), $status_array, 'bajaj_allianz', $additional_data);
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

            $product_sub_types = [
                'AUTO-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_AUTO_RICKSHAW_KIT_TYPE'),
                'TAXI' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TAXI_KIT_TYPE'),
                'ELECTRIC-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_E_RICKSHAW_KIT_TYPE'),
                'PICK UP/DELIVERY/REFRIGERATED VAN' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_PICKUP_DELIVERY_VAN_KIT_TYPE'),
                'DUMPER/TIPPER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_DUMPER_TIPPER_KIT_TYPE'),
                'TRUCK' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRUCK_KIT_TYPE'),
                'TRACTOR' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRACTOR_KIT_TYPE'),
                'TANKER/BULKER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TANKER_BULKER_KIT_TYPE')
            ];

            $is_pos = config('constants.motorConstant.IS_POS_ENABLED');

            $user_product_journey = UserProductJourney::find($user_proposal['user_product_journey_id']);
            $quote_log = $user_product_journey->quote_log;
            $agent_details = $user_product_journey->agent_details;

            if ($product_sub_types[$productData->product_sub_type_code] == 'JSON')
            {
                $userId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_USER_ID");
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PASSWORD");

                if ($quote_log->idv <= 5000000 && (($is_pos == 'Y' && $agent_details && isset($agent_details[0]->seller_type) && $agent_details[0]->seller_type == 'P') || config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y'))
                {
                    $userId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_USERNAME");
                    $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_PASSWORD");
                }
            }
            else
            {
                $userId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_USERNAME");
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PASSWORD");
            }

            $issue_array = [
                "userid" => $userId,
                "password" => $password,
                "pdfmode" => "WS_POLICY_PDF",
                "policynum" => $policy_no,
            ];

            $additional_data = [
                'enquiryId' =>$user_product_journey_id,
                'requestMethod' => 'post',
                'section' => 'CV',
                'method' => 'Generate Policy Motor',
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal',
            ];

            $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR'), $issue_array, 'bajaj_allianz', $additional_data);
            $response = $get_response['response'];
            $pdf_data = json_decode($response, true);
            if (isset($pdf_data) && $pdf_data['fileByteObj'] != '' && $pdf_data['fileByteObj'] !== NULL) {
                Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'bajaj_allianz/'. md5($user_proposal['user_proposal_id']). '.pdf', trim(base64_decode($pdf_data['fileByteObj'])));
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $user_proposal['user_proposal_id']],
                    [
                        'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'bajaj_allianz/'. md5($user_proposal['user_proposal_id']). '.pdf',
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
                        'pdf_link' => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'bajaj_allianz/'. md5($user_proposal['user_proposal_id']). '.pdf')
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
        } elseif($payment_log->status == STAGE_NAMES['PAYMENT_SUCCESS'] && $is_policy_no == FALSE) {
            return response()->json([
                'status' => false,
                'msg' => 'Payment Received, but policy number is not generated. Please contact the Insurance Company.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Payment Response Details not found. Payment Pending.'
            ]);
        }
        // EO PDF GENERATION //

    } //EO generatePdf
}
