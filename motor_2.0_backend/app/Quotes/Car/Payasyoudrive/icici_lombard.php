<?php
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\SelectedAddons;
use App\Models\CorporateVehiclesQuotesRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

include_once app_path() . '/Quotes/Car/icici_lombard_plan.php';
include_once app_path() . '/Quotes/Car/icici_lombard_plan_separate.php';
include_once app_path() . '/Helpers/CarWebServiceHelper.php';
require_once app_path() . '/Quotes/Car/icici_lombard.php';

function getpayasyoudriveQuote($request)
{

    $enquiryId   = customDecrypt($request->enquiryId);
    $requestData = getQuotation($enquiryId);
    $requestData->distance=$request->distance;
    $productData = getProductDataByIc($request->policyId);
    if($productData->company_alias != $request->company_alias)
    {
        return response()->json([
            'status' => false,
            'message' => 'Invalid company alias'
        ]);
    }

    if(isset($request->addons))
    {
        $requestData->addons = $request->addons;
    }
    $quoteData = [];
    if(!empty($productData))
    {
        if(!($requestData->product_sub_type_id == 1 && $requestData->product_id == 1))
        {
            return response()->json([
                'status' => false,
                'message' => 'Product type mismatch'
            ]);
        }

        if (empty($requestData->previous_policy_expiry_date)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid previous policy expiry date'
            ]);
        }
        if(in_array($requestData->business_type,['rollover']) && $requestData->previous_policy_expiry_date !== NULL)
        {
            $policy_days = get_date_diff('day', $requestData->previous_policy_expiry_date) * -1;
            $policy_allowed_days = 90;
            if($policy_days > $policy_allowed_days)
            {
                return response()->json([
                    'status' 	=> false,
                    'message'   => 'Future Policy Expiry date is allowed only upto '.$policy_allowed_days.' days'
                ]);
            }
        }
        else if($requestData->business_type == 'newbusiness')
        {
            $reg_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));
            $today = date('Y-m-d');
            if($reg_date !== $today)
            {
                return response()->json([
                    'status'    => false,
                    'message'   => 'Registration date('.$reg_date.') should be today date('. $today.')for Newbusiness'
                ]);
            }
        }

        $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))
                                            ->first();
        if(isBhSeries($CorporateVehiclesQuotesRequest->vehicle_registration_no) && !(in_array($productData->company_alias,explode(',',config('CAR_BH_SERIES_ALLOWED_IC')))))
        {
            return [
                'premium_amount'    => 0,
                'status'            => false,
                'message'           => 'BH Series number not allowed',
            ];
        }
    }
    $getquote=getquote($enquiryId,$requestData,$productData);
    return $getquote;

}

function getquotess($request)
{
    $policy_id=$request->policyId;

    $response=[];
    foreach ($policy_id as $key => $value) {
        $request->policyID=$value;
        $getpayudquote=getpayasyoudriveQuote($request);
        array_push($response,$getpayudquote);
    }
    return json_encode($response);
    // return $response;
}



?>