<?php

namespace App\Http\Controllers\Finsall;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Proposal\Services\Car\tataAigV2SubmitProposal as TATA_AIG;

use App\Http\Controllers\Payment\Services\{
    tataAigV2PaymentGateway as TATA_AIG_PAYMENT_CV,
    Car\tataAigV2PaymentGateway as TATA_AIG_PAYMENT_CAR
};

class TataAigV2FinsallController extends Controller
{
    public function paymentCheck($request, $proposal)
    {
        try {
            $proposal_additional_details_data = json_decode($proposal->additional_details_data);

            $productData = getProductDataByIc($proposal_additional_details_data->tata_aig_v2->master_policy_id);

            $token_response = TATA_AIG::getToken($proposal->user_product_journey_id, $productData);

            if (!$token_response['status']) {
                return $token_response;
            }
            $proposal_additional_details_data = json_decode($proposal->additional_details_data);

            $creds = $this->getCreds($proposal);

            $paymentRequest = [
                "payment_id" => $proposal_additional_details_data->tata_aig_v2->payment_id,
                "producer_code" => $creds->producer_code,
                "office_location_code" => "90101",
                "office_location_name" => "lname",
                "policy_start_date" => Carbon::parse($proposal->policy_start_date)->format('Y-m-d'),
                "payment_amount" => $proposal->final_payable_amount,
                "pan_no" => $proposal->pan_number,
                "payer_type" => "customer",
                "payer_id" => "",
                "payer_name" => "test",
                "payer_relationship" => "",
                "consumerAppTransId" => (string)$request->txnRefNo,
                "transactionStatus" => "Success",
                "gateway_txn_id" => (string)$request->txnRefNo,
                "txn_start_time" => (string)Carbon::parse($request->txnDateTime)->format('Y-m-d h:i:s')
            ];

            $additional_data = [
                'enquiryId'         => $proposal->user_product_journey_id,
                'headers'           => [
                    'Content-Type'  => 'application/JSON',
                    'Authorization'  => 'Bearer ' . $token_response['token'],
                    'x-api-key'      => $creds->x_api_key
                ],
                'requestMethod'     => 'post',
                'requestType'       => 'json',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Payment Status - Tata Finsall',
                'transaction_type'  => 'proposal',
                'productName'       => $productData->product_name,
                'token'             => $token_response['token'],
            ];

            $paymentRequstUrl = $creds->service_url . '?' .
                http_build_query([
                    'product' => 'motor'
                ]);

            $get_response = getWsData(
                $paymentRequstUrl,
                $paymentRequest,
                'tata_aig_v2',
                $additional_data
            );

            $paymentResponseData = $get_response['response'];

            $paymentResponseData = TATA_AIG::validaterequest($paymentResponseData);

            if (!$paymentResponseData['status']) {
                return $paymentResponseData;
            }

            $paymentResponse = [];
            if (isset($paymentResponseData['data'][0])) {
                foreach ($paymentResponseData['data'] as $elementArray) {
                    if (!empty($elementArray['policy_no'] ?? '')) {
                        $paymentResponse = $elementArray;
                        break;
                    }
                }
            } else {
                $paymentResponse = $paymentResponseData['data'];
            }

            $proposal_additional_details_data->tata_aig_v2->policy_no = $paymentResponse['policy_no'];
            $proposal_additional_details_data->tata_aig_v2->policy_id = $paymentResponse['encrypted_policy_id'];
            $proposal->policy_no = $paymentResponse['policy_no'];
            $proposal->additional_details_data = json_encode($proposal_additional_details_data);
            $proposal->save();

            $update_journey_stage_data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $update_journey_stage_data['ic_id'] = $proposal->ic_id;
            $update_journey_stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
            updateJourneyStage($update_journey_stage_data);

            \App\Models\PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_number' => $paymentResponse['policy_no']
                ]
            );

            $paymentResponseData['data'] = $paymentResponse;
            if(strtolower($productData->product_sub_type_code) == 'car') {
                $response = TATA_AIG_PAYMENT_CAR::policyDownloadService($proposal->user_product_journey_id, $proposal, $productData, $paymentResponseData, $token_response);
            } else {
                $response = TATA_AIG_PAYMENT_CV::policyDownloadService($proposal->user_product_journey_id, $proposal, $productData, $paymentResponseData, $token_response);
            }
            return $response;
        } catch (\Exception $e) {
            return [
                'line' => __LINE__,
                'status' => true,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }
    }

    function getCreds($section)
    {
        $cv_v2_config = config('constants.IcConstants.tata_aig_v2.cv');
        $pcv_data = [
            'service_url' => $cv_v2_config['END_POINT_URL_THIRD_PARTY_PAYMENT_VERIFY'] ?? null,
            "producer_code" => $cv_v2_config['SHORT_TERM_PRODUCER_CODE'] ?? null,
            "producer_email" => $cv_v2_config['SHORT_TERM_PRODUCER_EMAIL'] ?? null,
            "scope" => $cv_v2_config['TATA_AIG_V2_SCOPE'] ?? null,
            "client_id" => $cv_v2_config['TATA_AIG_V2_CLIENT_ID'] ?? null,
            "client_secret" => $cv_v2_config['TATA_AIG_V2_CLIENT_SECRET'] ?? null,
            "x_api_key" => $cv_v2_config['TATA_AIG_V2_XAPI_KEY'] ?? null,
        ];
        return (object)$pcv_data;
    }
}
