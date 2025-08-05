<?php

namespace App\Http\Controllers\Extra;

use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use App\Models\CvJourneyStages;
use Illuminate\Support\Str;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\UserProposal;
use App\Models\QuoteLog;
use App\Models\MasterPremiumType;

class Datechanger extends Controller
{
    ### Sample Input ###
    // array [
    //     "policyId" => 1645
    //     "enquiryId" => "2024101000008663"
    //     "userProductJourneyId" => "2024101000008663"
    // ]
    public static function Datechange($data)
    {
        $enquiry_id = customDecrypt($data['userProductJourneyId']);
        $productData = getProductDataByIc($data['policyId']);
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }
        if($premium_type != 'own_damage')
        {
            $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
            $business_type = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
                                            ->pluck('business_type')
                                            ->first();
            $tp_end_date = $proposal->policy_end_date;
            if($business_type == 'newbusiness')
            {
                if($data['segment'] == 'CAR')
                {
                    $tp_end_date = date('d-m-Y', strtotime('+3 year -1 day', strtotime($proposal->policy_start_date)));
                }
                else if($data['segment'] == 'BIKE')
                {
                    $tp_end_date = date('d-m-Y', strtotime('+5 year -1 day', strtotime($proposal->policy_start_date)));
                }
            }
            $proposal->tp_start_date    = $proposal->policy_start_date;
            $proposal->tp_end_date      = $tp_end_date;
            $proposal->save();
        }
    }
}
