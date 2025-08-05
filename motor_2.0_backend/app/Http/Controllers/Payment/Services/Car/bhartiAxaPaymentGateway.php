<?php

namespace App\Http\Controllers\Payment\Services\Car;

use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use App\Models\UserProposal;

class bhartiAxaPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        if($proposal){
            $enquiryId = customDecrypt($request['userProductJourneyId']);

            $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

            $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('quote_id')
                ->first();

            $return_data = [
                'form_action' => config('constants.IcConstants.bharti_axa.BHARTI_AXA_PAYMENT_GATEWAY_LINK'),
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'OrderNo'   => $proposal->proposal_no,
                    'QuoteNo'   => $proposal->unique_proposal_id,
                    'Channel'   => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_CHANNEL_ON_PAYMENT'),
                    'Product'   => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_PRODUCT_ON_PAYMENT'),
                    'Amount'    => $proposal->final_payable_amount,
                    'IsMobile'  => 'N'
                    //'RETURN_URL' => route('car.payment-confirm', ['bharti_axa']),
                ]
            ];

            return response()->json([
                'status' => true,
                'msg' => "Payment Redirectional",
                'data' => $return_data,
            ]);

        }else{
            return [
                'status' => false,
                'message' => 'proposal data not found'
            ];
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
    }
}
