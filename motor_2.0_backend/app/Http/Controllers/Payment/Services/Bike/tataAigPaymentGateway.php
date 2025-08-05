<?php

namespace App\Http\Controllers\Payment\Services\Bike;

use Exception;
use stdClass;
use Carbon\Carbon;

use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/TataAigCkycHelper.php';

class tataAigPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
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

        $return_data = [
            'form_action' => config('constants.IcConstants.tata_aig.END_POINT_PAYMENT_BEFORE_URL'),
            'form_method' => config('constants.IcConstants.tata_aig.END_POINT_PAYMENT_BEFORE_URL_METHOD'),
            'payment_type' => 0, // form-submit
            'form_data' => [
                'proposal_no' => $user_proposal->proposal_no,
                'src' => config('constants.IcConstants.tata_aig.SRC'),
            ]
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
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'proposal_no'               => $user_proposal->proposal_no,
            'order_id'                  => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            'payment_url'               => config('constants.IcConstants.tata_aig.END_POINT_PAYMENT_BEFORE_URL'),
            'return_url'                => route(
                'bike.payment-confirm',
                [
                    'tata_aig',
                    'user_proposal_id'      => $user_proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]
            ),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }

    public static function confirm($request)
    {
        $response = json_decode(base64_decode(request()->response), true);
        $user_proposal = UserProposal::where('proposal_no', $response['data']['proposalno'])->first();

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->where('user_proposal_id', $user_proposal->user_proposal_id)
        ->where('proposal_no', $response['data']['proposalno'])
        ->where('active', 1)
        ->update([
            'response'      => request()->response,
            'updated_at'    => date('Y-m-d H:i:s')
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
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','FAILURE'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
        }
        else
        {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('user_proposal_id', $user_proposal->user_proposal_id)
            ->where('active', 1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);
        }
        

        $ic_pdf_url = config('constants.IcConstants.tata_aig.bike.END_POINT_URL_POLICY_GENERATION') . '?' . http_build_query(['polno' => $response['data']['policyno'], 'src' => 'app', 'key' => $response['data']['rnd_str']]);

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

        try{
            /* $proposal_pdf = Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'tata_aig/' . md5($user_proposal->user_proposal_id) . '.pdf', file_get_contents($ic_pdf_url));*/
            $proposal_pdf = Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'tata_aig/' . md5($user_proposal->user_proposal_id) . '.pdf', httpRequestNormal($ic_pdf_url,'GET',[],[],[],[],false)['response']);
        }
        catch(Exception $e)
        {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
            //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
        }

        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];

        updateJourneyStage($data);
        PolicyDetails::updateOrCreate(
            ['proposal_id' => $user_proposal->user_proposal_id],
            [
                'policy_number' => $response['data']['policyno'],
                'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'tata_aig/' . md5($user_proposal->user_proposal_id) . '.pdf',
                'ic_pdf_url' => $ic_pdf_url
            ]
        );
        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
        //return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
    }

    static public function create_pdf($proposal)
    {

        $additional_details = $proposal->additional_details;

        // echo "<pre>";print_r([$additional_details]);echo "</pre>";die();

        $ic_pdf_url = config(
            'constants.IcConstants.tata_aig.bike.END_POINT_URL_POLICY_GENERATION')
        . '?'
        . http_build_query(
            [
                'polno' => $additional_details['tata_aig']['policy_no'],
                'src' => 'app',
                'key' => $additional_details['tata_aig']['rnd_key']
            ]
        );

        $pdf_url = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'tata_aig/'. md5($proposal->user_proposal_id).'.pdf';

        try
        {
            //Storage::put($pdf_url, file_get_contents($ic_pdf_url));
            Storage::put($pdf_url, httpRequestNormal($ic_pdf_url,'GET',[],[],[],[],false)['response']);
        }
        catch(Exception $e)
        {
            Log::debug($e);
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
                'policy_number' => $additional_details['tata_aig']['policy_no'],
                'ic_pdf_url' => $ic_pdf_url,
                'pdf_url' => $pdf_url,
                'status' => 'SUCCESS'
            ]
        );

        return [
            'status' => true,
            'msg' => 'sucess',
            'data' => [
                'policy_number' => $additional_details['tata_aig']['policy_no'],
                'pdf_link'      => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'tata_aig/'. md5($proposal->user_proposal_id).'.pdf')
            ]
        ];
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
                'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no','up.unique_proposal_id', 'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id', 'prr.response as response'
            )
            ->first();

        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        $isgeneratePolicy = true;

        if($policy_details == null)
        {
            $generatePolicy = tataAigPaymentGateway::generatePolicy($proposal, $request->all());

            if(!$generatePolicy['status'])
            {
                return $generatePolicy;
            }

            $policy_details = (object)[
                'policy_number' => $generatePolicy['data']['policy_number'],
                'pdf_url'    => ''
            ];

            $isgeneratePolicy = false;
        }

        if($policy_details->pdf_url == '')
        {
            if($isgeneratePolicy)
            {
                $generatePolicy = tataAigPaymentGateway::generatePolicy($proposal, $request->all());
            }
            if($generatePolicy['status'])
            {
                $pdf_response_data = tataAigPaymentGateway::create_pdf($proposal);
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
                    'pdf_link' => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'tata_aig/'. md5($proposal->user_proposal_id).'.pdf'),
                    'policy_number' => $policy_details->policy_number,
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }

    static public function generatePolicy($proposal, $requestArray)
    {
        $additional_details = json_decode($proposal->additional_details, true);

        $input_array = [
            'paymenttype' => 'CPI',
            'proposalno'  => $proposal->proposal_no,
            'source'      => config('constants.IcConstants.tata_aig.SRC'),
            'transno'     => '',
            'transdate'   => Carbon::parse($proposal->proposal_date)->format('Ymd'),
            'premiumamt'  => $proposal->total_premium,
            'paydat'      => Carbon::parse($proposal->proposal_date)->format('Ymd'),
        ];

        $additional_data = [
          'enquiryId' => $proposal->user_product_journey_id,
          'headers' => [],
          'requestMethod' => 'post',
          'requestType' => 'json',
          'section' => 'Bike',
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
            ->where('user_proposal_id', $proposal->user_proposal_id)
            ->where('active', 1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                'response'      => base64_encode(json_encode([
                    'status' => '1',
                    'data' => [
                        'proposalno' => $proposal->proposal_no,
                        'policyno' => $response['data']['policyno'],
                        'rnd_str' => $response['data']['key'],
                        'status' => '1',
                    ],
                ])),
            ]);

            $additional_details['tata_aig']['rnd_key'] = $response['data']['key'];
            $additional_details['tata_aig']['policy_no'] = $response['data']['policyno'];
            $additional_details['tata_aig']['generate_policy_response'] = $response;

            $proposal->additional_details = $additional_details;
            $proposal->save();

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






}
