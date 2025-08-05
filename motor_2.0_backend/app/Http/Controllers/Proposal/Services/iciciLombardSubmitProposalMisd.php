<?php

namespace App\Http\Controllers\Proposal\Services;

use App\Http\Controllers\SyncPremiumDetail\Services\IciciLombardPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\IciciLombardPincodeMaster;
use App\Models\IciciMmvMaster;
use App\Models\IciciRtoMaster;
use App\Models\IcVersionMapping;
use App\Models\MasterPolicy;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MotorModelVersion;
use App\Models\QuoteLog;
use App\Models\Quotes\Cv\CvQuoteModel;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Config;
use DateTime;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;

class iciciLombardSubmitProposalMisd
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    { 
        

        $productData = getProductDataByIc($request['policyId']);

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        
        $parentCode = get_parent_code($productData->product_sub_type_id);
       

        if($parentCode != 'PCV' && $proposal->is_claim == 'Y')
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Quotes not allowed if vehicle have Claim History',
                'request' => [
                    'message' => 'Quotes not allowed if vehicle have Claim History',
                    'requestData' => $request
                ]
            ];
        }
        // $cvQuoteModel = new CvQuoteModel();

        // $productData = $cvQuoteModel->getProductDataByIc($request['policyId']);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $master_product_sub_type_code = MasterPolicy::find($productData->policy_id)->product_sub_type_code->product_sub_type_code;

        // Defined constant

        if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' ||$master_product_sub_type_code == 'TRUCK' ||$master_product_sub_type_code == 'TRACTOR' ||$master_product_sub_type_code == 'TANKER/BULKER') {
            $type = 'GCV';
            if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_TP');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE_TP');
            }elseif ($premium_type == 'breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
            }else{
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
            }
        } elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
            $type = 'PCV';
            if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_TP_PRODUCT_CODE');
            }elseif ($premium_type == 'breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            elseif ($premium_type == 'short_term_3_breakin')
            {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_3_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            elseif ($premium_type == 'short_term_6_breakin')
            {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_SHORT_TERM_6_BREAKIN');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
            else {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
            }
        }elseif ($master_product_sub_type_code === 'MISCELLANEOUS-CLASS') {
            $type = 'MISC';
            if ($premium_type == 'third_party'|| $premium_type == 'third_party_breakin') {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_TP_MISC'); #TP Deal for misc
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_TP_PRODUCT_CODE');
            }elseif (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN'); # breakin deal for misc
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
            }else {
                $ICICI_LOMBARD_DEAL_ID = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_DEAL_ID');
                $PRODUCT_CODE = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MISC_PRODUCT_CODE');
            }
        }

        $enquiryId = customDecrypt($request['enquiryId']);
        // $request['userProductJourneyId'] = customDecrypt($request['userProductJourneyId']);

        $requestData = getQuotation($enquiryId);

        // token Generation
        include_once app_path() . '/Helpers/CvWebServiceHelper.php';

        $additionData = [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'MISC',
            'enquiryId' => $enquiryId,
            'transaction_type'    => 'proposal'
        ];

        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
            'scope' => 'esbmotor',
            'productName'  => $productData->product_name,
        ];


        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $tokendata = $token = $get_response['response'];

        if (!empty($token)) {
            $token = json_decode($token, true);

            if(!isset($token))
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => strip_tags($tokendata)
                ];
            }


            $access_token = $token['access_token'];

//            function getUUID()
//            {
//                try {
//                    $data = random_bytes(16);
//                } catch (\Exception $e) {
//                    $data = openssl_random_pseudo_bytes(16);
//                }
//
//                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
//                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
//
//                return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
//            }

            $corelationId = getUUID($enquiryId);

            $proposerVehDet = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)
                ->select('*')
                ->get();

            /* $rtoLocationCode = IciciRtoMaster::where('rto_code', $proposerVehDet[0]->rto_code)
                ->where('product_sub_type_id', $productData->product_sub_type_id)
                ->select('*')
                ->get(); */

            $rto_cities = MasterRto::where('rto_code', $proposerVehDet[0]->rto_code)->first();
            $state_id = $rto_cities->state_id;
            $state_name = MasterState::where('state_id', $state_id)->first();
            $state_name = strtoupper($state_name->state_name);
            $rto_cities = explode('/',  $rto_cities->rto_name);
            foreach($rto_cities as $rto_city)
            {
                $rto_city = strtoupper($rto_city);
                $rto_data = DB::table('icici_lombard_city_disctrict_master')
                            ->where('TXT_CITYDISTRICT', $rto_city)
                            ->where('GST_STATE', $state_name)
                            ->first();
                $rto_data = keysToLower($rto_data);
                if($rto_data)
                {
                    $state_code = $rto_data->il_state_cd;
                    $city_district_code = $rto_data->il_citydistrict_cd;
                    $country_code = $rto_data->il_country_cd;
                    break;
                }
            }

            if($master_product_sub_type_code == 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW'){
                $pcv_rto_master_user_from_master_rto = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_RTO_MASTER_FROM_MASTER_RTO') == 'Y' ? true : false;
                if(Schema::hasColumn('master_rto', 'icici_pcv_location_code') && $pcv_rto_master_user_from_master_rto)
                {
                    $rto_cities = MasterRto::where('rto_code', $proposerVehDet[0]->rto_code)->first();
        
                    if(!blank($rto_cities) && !empty($rto_cities->icici_pcv_location_code))
                    {
                        $rto_location_code = DB::table('pcv_icici_lombard_rto_master')
                        ->where('RTOLocationCode', trim($rto_cities->icici_pcv_location_code))
                        ->where('ActiveFlag', 'Y');
                    }
                }else
                {
                    $rto_location_code = DB::table('icici_lombard_rto_master')
                    ->where('ILStateCode', $state_code)
                    ->where('CityDistrictCode', $city_district_code)
                    ->where('ActiveFlag', 'Y');
                }


                if ($master_product_sub_type_code == 'AUTO-RICKSHAW') {
                    $rto_location_code = $rto_location_code->where('Vehicle_Subclass', '2')->get();
                }elseif ($master_product_sub_type_code == 'ELECTRIC-RICKSHAW') {
                    $rto_location_code = $rto_location_code->where('Vehicle_Subclass', '8')->get();
                }else {
                    $rto_location_code = $rto_location_code->where('Vehicle_Subclass', '1')->get();
                }

                $rto_location_code = keysToLower($rto_location_code);
                if (count($rto_location_code) > 1) {
                    foreach( $rto_location_code as $key => $value){
                        if($value->rtolocationdesciption == $state_name.'-'.$rto_city.'-C1-C4-2WD-4WD') {
                            $rtoLocationCode = $value->rtolocationcode;
                            break;
                        }
                    }
                }else{
                    $rtoLocationCode = $rto_location_code[0]->rtolocationcode;
                }

            }
            // elseif($master_product_sub_type_code == 'MISCELLANEOUS-CLASS'){
            //     $rtoLocationCode = '5207'; # for testing purpose
            // }
            else {
                $rto_location_code = DB::table('misc_icici_lombard_rto_master')
                    ->where('ILStateCode', $state_code)
                    ->where('CityDistrictCode', $city_district_code)
                    ->first();
                $rto_location_code = keysToLower($rto_location_code);
                if ($rto_location_code) {
                    $rtoLocationCode = $rto_location_code->rtolocationcode;
                } else {
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'RTO not available'
                    ];
                }
        
                
            }

            if ($type == 'MISC') {
                $mmv = get_mmv_details($productData,$requestData->version_id,'icici_lombard');
                if (empty($mmv)) {
                    return  [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'mmv details not found',
                        'request' => [
                            'mmv' => $mmv,
                        ]
                    ];
                }
                
                if($mmv['status'] == 1)
                {
                $mmv = $mmv['data'];
                }
                else
                {
                    return  [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => $mmv['message'],
                        'request' => [
                            'mmv' => $mmv
                        ]
                    ];
                }
                $mmvData = (object) array_change_key_case((array) $mmv,CASE_LOWER);
                if (empty($mmvData->ic_version_code) || $mmvData->ic_version_code == '') {
                    return  [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle Not Mapped',
                        'request' => [
                            'message' => 'Vehicle Not Mapped',
                            'mmvData' => $mmvData
                        ]
                    ];
                } else if ($mmvData->ic_version_code == 'DNE') {
                    return  [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle code does not exist with Insurance company',
                        'request' => [
                            'message' => 'Vehicle code does not exist with Insurance company',
                            'mmvData' => $mmvData
                        ]
                    ];
                }
            }

            if(isset($mmvData->model_build)) {
              $model_build = $mmvData->model_build;
            } else{
                    $model_build = '';
            }

            if($type == 'MISC')
            {
                $mmvDetails['manf_name'] = $mmvData->manf_name;
                $mmvDetails['model_name'] = $mmvData->model_name;
                // $mmvDetails['version_name'] = $mmvData->model_name;
                $mmvDetails['version_id'] = $mmvData->model_code;
                $mmvDetails['seating_capacity'] = $mmvData->seating_capacity;
                $mmvDetails['cubic_capacity'] = $mmvData->cubic_capacity;
                $mmvDetails['fuel_type'] = $mmvData->fuel_type;
            }



            if ($proposerVehDet[0]->business_type == 'rollover') {
                $businessType = "Roll Over";
                $policyStartDate = Carbon::createFromDate($proposerVehDet[0]->previous_policy_expiry_date)->addDay(1);
                // $policyEndDate = Carbon::createFromDate($proposerVehDet[0]->previous_policy_expiry_date)->addYear(1);
                if ($premium_type == 'short_term_3') {
                    $policyEndDate = Carbon::createFromDate($proposerVehDet[0]->previous_policy_expiry_date)->addMonth(3);
                }elseif($premium_type == 'short_term_6'){
                    $policyEndDate = Carbon::createFromDate($proposerVehDet[0]->previous_policy_expiry_date)->addMonth(6);
                }else {
                    $policyEndDate = Carbon::createFromDate($proposerVehDet[0]->previous_policy_expiry_date)->addYear(1);
                }
            } elseif ($requestData->business_type == 'newbusiness') {
                $businessType = "New Business";
                $policyStartDate = Carbon::today();
                $policyEndDate = Carbon::today()->addYear(1)->subDay(1);
            }

            $brekinFlag = ($proposerVehDet[0]->business_type == 'brekin') ? true : false;

            $proposerVehDet[0]->previous_policy_expiry_date = $proposerVehDet[0]->business_type == 'newbusiness' ? "" : $proposerVehDet[0]->previous_policy_expiry_date;

            $previousPolicyExpiryDate = $requestData->previous_policy_type == 'Not sure' ? '' : Carbon::createFromDate($proposerVehDet[0]->previous_policy_expiry_date);

            $previousPolicyStartDate = $requestData->previous_policy_type == 'Not sure' ? '' : Carbon::createFromDate($proposerVehDet[0]->previous_policy_expiry_date)->subYear(1)->addDay(1);
            $additional_details = json_decode($proposal->additional_details);
            if($requestData->prev_short_term){
                if(isset($additional_details->prepolicy->previousPolicyStartDate))
                {
                    $previousPolicyStartDate = $requestData->previous_policy_type == 'Not sure' ? '' : (Carbon::createFromDate($additional_details->prepolicy->previousPolicyStartDate));
                }
            }
            $date_diff_in_prev_policy = 0;
            if(!empty( $previousPolicyExpiryDate))
            {
            if ($previousPolicyExpiryDate->lt(Carbon::today())) {
                $businessType = "Roll Over";
                $policyStartDate = Carbon::today()->addDay(3);
                $policyEndDate = Carbon::today()->addDay(2)->addYear(1);
                if (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                    $brekinFlag = true;

                    if ($premium_type == 'short_term_3_breakin')
                    {
                        $policyEndDate = Carbon::today()->addDay(2)->addMonth(3);
                    }
                    elseif ($premium_type == 'short_term_6_breakin')
                    {
                        $policyEndDate = Carbon::today()->addDay(2)->addMonth(6);
                    }

                    $date_diff_in_prev_policy = $previousPolicyExpiryDate->diffInDays(Carbon::today());
                    if ($date_diff_in_prev_policy > 90) {
                        $applicable_ncb_rate = 0;
                        $current_ncb_rate = 0;
                    }
                }
            }
            }else if($requestData->business_type != 'newbusiness'){
                $businessType = 'Roll Over';
                $policyStartDate = Carbon::today()->addDay(3);
                $policyEndDate = Carbon::today()->addDay(2)->addYear(1);

                if ($premium_type == 'short_term_3' || $premium_type == 'short_term_3_breakin')
                {
                    $policyEndDate = Carbon::today()->addDay(2)->addMonth(3);
                }
                elseif ($premium_type == 'short_term_6' || $premium_type == 'short_term_6_breakin')
                {
                    $policyEndDate = Carbon::today()->addDay(2)->addMonth(6);
                }
            }

            // vehicle age calculation
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new DateTime($vehicleDate);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $car_age = floor($age / 12);
            $product_name = policyProductType($productData->policy_id)->product_name;

            if ($is_zero_dep && $interval->y >= 3) {
                if ($product_name == 'imt_23') {
                    $is_zero_dep = false;
                } else {
                    return [
                        'status' => false,
                        'message' => 'Zero not available for vehicle above 3 years'
                    ];
                }
            }

            $selectedAddons = SelectedAddons::where('user_product_journey_id', $enquiryId)
                ->select('*')
                ->get();

            $eleAccessories = [];
            $nonEleAccessories = [];
            $lpgAndCng = [];
            $zeroDep = [];
            $rsa = [];
            $addionalPaidDriver = [];
            $ownerDriver = [];
            $unnamedPassenger = [];
            $antiTheftDisc = [];
            $compulsorypaOwnDriver = [];
            $compulsorypaOwnDriver['status'] = 'false';
            $llPaidDriverCC =  [];
            $llPaidDriverCC['noOfDriver'] = 1;
            $LiabilityToPaidDriver_IsChecked = 'false';
            $imt23 = 'false';
            $is_imt = false;
            $is_zero_dept = false;
          
            if (!$selectedAddons->isEmpty()) {

                if (!empty($selectedAddons[0]->addons)) {
                    // foreach ($selectedAddons[0]->addons as $addonVal) {

                    //     if (in_array('Zero Depreciation', $addonVal)) {
                    //         $is_zero_dept = true;
                    //     }

                    //     if (
                    //         in_array('IMT - 23', $addonVal)
                    //     ) {
                    //         $is_imt = true;
                    //     }
                    // }
                }
                if (!empty($selectedAddons[0]->accessories)) {

                    foreach ($selectedAddons[0]->accessories as $addonVal) {

                        if (in_array('Electrical Accessories', $addonVal)) {
                            $eleAccessories['status'] = 'true';
                            $eleAccessories['name'] = $addonVal['name'];
                            $eleAccessories['addonSI'] = $addonVal['sumInsured'];
                        }

                        if (in_array('Non-Electrical Accessories', $addonVal)) {
                            $nonEleAccessories['status'] = 'true';
                            $nonEleAccessories['name'] = $addonVal['name'];
                            $nonEleAccessories['addonSI'] = $addonVal['sumInsured'];
                        }

                        if (in_array('External Bi-Fuel Kit CNG/LPG', $addonVal)) {
                            $lpgAndCng['status'] = 'true';
                            $lpgAndCng['name'] = $addonVal['name'];
                            $lpgAndCng['addonSI'] = $addonVal['sumInsured'];
                        }
                    }
                }


                 // set addons plan name according to its vehicle sub type
                switch($master_product_sub_type_code){
                    case 'TAXI':
                    case 'ELECTRIC-RICKSHAW':
                    case 'AUTO-RICKSHAW' :
                        $zero_dep_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_ZERO_DEP_PLAN_NAME');
                        $rsa_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_RSA_PLAN_NAME');
                        break;
                    case 'PICK UP/DELIVERY/REFRIGERATED VAN':
                    case 'DUMPER/TIPPER' :
                    case 'TRUCK' :
                    case 'TRACTOR' :
                    case 'TANKER/BULKER' :
                    case 'MISCELLANEOUS-CLASS' :
                        $zero_dep_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_ZERO_DEP_PLAN_NAME');
                        $rsa_plan_name = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_RSA_PLAN_NAME');
                        break;
                }

                // zero dep condition for gcv pcv
                $zeroDepPlanName ='';

                if (!empty($selectedAddons[0]->applicable_addons)) {

                    foreach ($selectedAddons[0]->applicable_addons as $addon) {
                        if (in_array('Zero Depreciation', $addon)) {
                            $is_zero_dept  = true;
                            // if ($type == 'GCV')
                            // {
                            //     if ($mmv->gvw < 3500 && $car_age <= 3) {
                            //         $zeroDep['status'] = 'true';
                            //         $zeroDep['name'] = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_ZERO_DEP_PLAN_NAME');
                            //     }
                            // }elseif($type == 'PCV')
                            // {
                            //     if($mmv->carrying_capacity <= 6 && $car_age <= 3)
                            //     {
                            //         $zeroDep['status'] = 'true';
                            //         $zeroDep['name'] = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_ZERO_DEP_PLAN_NAME');
                            //     }
                            // }
                            // else{
                            //     $zeroDep['status'] = 'false';
                            //     $zeroDep['name'] ='';
                            // }
                        }

                        // if (in_array('Road Side Assistance', $addon)) {
                        //     $rsa['status'] = 'true';
                        //     $rsa['name'] = $rsa_plan_name;
                        // }
                        if (in_array('IMT - 23', $addon) && $type != 'PCV') {
                            $imt23 = 'true';
                        }
                        if (in_array('IMT - 23', $addon)) {
                             $is_imt = true;
                        }

                    }
                }

               /*  if ($productData->is_premium_online == 'No' && config('constants.brokerName') == 'OLA') {
                    $rsa['status'] = 'true';
                    $rsa['name'] = $rsa_plan_name;
                } */

                if (!empty($selectedAddons[0]->additional_covers)) {

                    foreach ($selectedAddons[0]->additional_covers as $addon) {
                        if (in_array('PA cover for additional paid driver', $addon)) {
                            $addionalPaidDriver['status'] = 'true';
                            $addionalPaidDriver['name'] = 'Silver PCV';
                            $addionalPaidDriver['addonSI'] = $addon['sumInsured'];
                        }

                        if (in_array('Owner Driver PA Cover', $addon)) {
                            $ownerDriver['status'] = 'true';
                            $ownerDriver['name'] = 'RSA1';
                            $ownerDriver['addonSI'] = $addon['sumInsured'];
                        }

                        if (in_array('Unnamed Passenger PA Cover', $addon)) {
                            $unnamedPassenger['status'] = 'true';
                            $unnamedPassenger['name'] = 'RSA1';
                            $unnamedPassenger['addonSI'] = $addon['sumInsured'];
                        }

                        if (in_array('LL paid driver/conductor/cleaner', $addon)) {
                            $LiabilityToPaidDriver_IsChecked = 'true';
                            $llPaidDriverCC['status'] = 'true';
                            $llPaidDriverCC['noOfDriver'] = isset($addon['LLNumberDriver']) ? $addon['LLNumberDriver'] : 0;
                            $llPaidDriverCC['noOfCleaner'] = isset($addon['LLNumberCleaner']) ? $addon['LLNumberCleaner'] : 0;
                            $llPaidDriverCC['noOfConductor'] = isset($addon['LLNumberConductor']) ? $addon['LLNumberConductor'] : 0;
                        }

                        if (in_array('LL paid driver', $addon)) {
                            $LiabilityToPaidDriver_IsChecked = 'true';
                        }

                    }
                }

                $tppdUser = false;
                if (!empty($selectedAddons[0]->discounts)) {

                    foreach ($selectedAddons[0]->discounts as $discount) {
                        if (in_array('anti-theft device', $discount)) {
                            $antiTheftDisc['status'] = 'true';
                        }

                        if (in_array('TPPD Cover', $discount)) {
                            $tppdUser = true;
                        }
                    }
                }


                if (!empty($selectedAddons[0]->compulsory_personal_accident)) {
                    foreach ($selectedAddons[0]->compulsory_personal_accident as $addon) {
                        if (in_array('Compulsory Personal Accident', $addon)) {
                            $compulsorypaOwnDriver['status'] = 'true';
                        }
                    }
                }
            }

            // Applying IC condition on cleaner and conductor
            $noOfCleanerAndConductor = 0;
            if (isset($llPaidDriverCC['noOfCleaner']) || isset($llPaidDriverCC['noOfConductor'])) {
                $noOfCleanerAndCond = $llPaidDriverCC['noOfCleaner'] + $llPaidDriverCC['noOfConductor'];
                if ($noOfCleanerAndCond <= $mmvDetails['seating_capacity']) {
                    $noOfCleanerAndConductor = $noOfCleanerAndCond;
                } else {
                    return  [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Number of cleaner and conductor should not be greater than vehicle seating capacity',
                    ];
                }
            }


            $vehicleHaveLPG = false;

            # inbuilt CNG Logic :
            if($type !== 'MISC'){ #short term misc condition

                if(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'CNG')
                {
                    $vehicleHaveLPG = false;
                    $lpgAndCng['status'] = true;
                    $lpgAndCng['addonSI'] = 0;
                }else if(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'LPG')
                {
                    $vehicleHaveLPG = true;
                    $lpgAndCng['status'] = false;
                    $lpgAndCng['addonSI'] = 0;
                }
            }

            //check carrier type
            if ($proposerVehDet[0]->gcv_carrier_type == 'PRIVATE') {
                $gcvCarrierType = true;
            } else {
                $gcvCarrierType = false;
            }
            
            $vehiclebodyPrice = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('ex_showroom_price_idv')->first();

            $address_data = [
                'address' => $proposal->address_line1,
                'address_1_limit'   => 79,
                'address_2_limit'   => 79            
            ];

            $getAddress = getAddress($address_data);

            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                $breakin_days = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);
             }
            // Customer Payload
            $customer_payload = IciciLombardPincodeMaster::where('num_pincode', $proposal->pincode)->first();

            if ($is_zero_dep && $is_zero_dept) {
                $zeroDepPlanName = "Silver MISD";
            }
            $premiumRecalcRequest = [
                // "DealId" => $ICICI_LOMBARD_DEAL_ID,
                "CustomerType" => ($proposerVehDet[0]->vehicle_owner_type == 'I') ? "INDIVIDUAL" : "Corporate",
                "CorrelationId" => $corelationId,
                "PolicyEndDate" => $policyEndDate->toDateString(),
                "PolicyStartDate" => $policyStartDate->toDateString(),
                "RTOLocationCode" => $rtoLocationCode, //$rtoLocationCode->rto_location_code,#5207
                "VehicleMakeCode" =>  $mmvData->manf_code,#366
                "VehicleModelCode" =>  $mmvData->ic_version_code,#6338
                "ManufacturingYear" => date('Y', strtotime('01-' . $proposal->vehicle_manf_year)),
                "DeliveryOrRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet[0]->vehicle_register_date)),
                "FirstRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet[0]->vehicle_register_date)),
                "GSTToState" => $state_name, //$rtoLocationCode[0]->state_name,
                "BusinessType" => ($premium_type == 'third_party' && $businessType == 'Roll Over' ) ? 'Used' : $businessType,
                "ProductCode" =>$PRODUCT_CODE,
                "IsVehicleHaveCNG" => (!empty($lpgAndCng)) ? $lpgAndCng['status'] : 'false',
                "IsVehicleHaveLPG" => $vehicleHaveLPG,
                "SI_VehicleLPGCNG_KIT" => (!empty($lpgAndCng)) ? $lpgAndCng['addonSI'] : '0',
                "IsPrivateUse" => "false",
                "IsLimitedToOwnPremises" => $imt23 ? 'true' :'false',
                "IsNonFarePayingPassengers" => "false",
                // "IsNCBApproved" => (($requestData->is_claim == 'Y') || ($requestData->business_type == 'breakin' && $breakin_days > 90) || $requestData->previous_policy_expiry_date == 'New')? 'false' : 'true',
                // "IsNCBApplicable" => (($requestData->is_claim == 'Y') || ($requestData->business_type == 'breakin' && $breakin_days > 90) || $requestData->previous_policy_expiry_date == 'New') ? 'false' : 'true',
                "NoOfNonFarePayingPassenger" => 2,
                "IsGarageCash" => "false",
                "IsTyreProtect" => "false",
                "IsHireOrHiresEmployee" => "false", // default
                // "InclusionOfIMT" => $imt23,
                "InclusionOfIMT" => $is_imt ,
                "IsAutomobileAssocnFlag" => false,

                "IsPACoverOwnerDriver" => (!empty($compulsorypaOwnDriver)) ? $compulsorypaOwnDriver['status'] : 'false',
                "ISPACoverWaiver" => (!empty($compulsorypaOwnDriver) && $compulsorypaOwnDriver['status'] == "true") ? 'false' : 'true',
                // "ISPACoverWaiver" =>'false',
                "IsNoPrevInsurance" => $requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure' ? "true" : "false",
                "IsAntiTheftDisc" => (!empty($antiTheftDisc)) ? $antiTheftDisc['status'] : 'false',
                "IsConsumables" => "false", // default
                "ZeroDepPlanName" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '' : $zeroDepPlanName,
                "RSAPlanName" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? "" : ((!empty($rsa)) ? $rsa['name'] : ''),
                "IsHaveElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? 'false' : ((!empty($eleAccessories)) ? $eleAccessories['status'] : 'false'),
                "SIHaveElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '0' : ((!empty($eleAccessories)) ? $eleAccessories['addonSI'] : '0'),
                "IsHaveNonElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? 'false' : ((!empty($nonEleAccessories)) ? $nonEleAccessories['status'] : 'false'),
                "SIHaveNoNElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '0' : ((!empty($nonEleAccessories)) ? $nonEleAccessories['addonSI'] : '0'),
                "IsLegalLiabilityToPaidDriver" => $type == 'GCV' && isset($llPaidDriverCC['noOfDriver']) && $llPaidDriverCC['noOfDriver'] == 0 ? false : $LiabilityToPaidDriver_IsChecked,
                "NoOfDriver" => $llPaidDriverCC['status'] = 'true' ? $llPaidDriverCC['noOfDriver'] : "1",
                "NoOfCleanerOrConductor" => $noOfCleanerAndConductor,
                "RegistrationNumber" => str_replace("-", "", $proposal->vehicale_registration_number),
                "EngineNumber" => $proposal->engine_number,
                "ChassisNumber" => $proposal->chassis_number,
                "IsPrivateCarrier" =>  $gcvCarrierType,
                "CustomerDetails" => [
                    "CustomerType" => ($proposerVehDet[0]->vehicle_owner_type == 'I') ? "INDIVIDUAL" : "Corporate",
                    "CustomerName" => ($proposerVehDet[0]->vehicle_owner_type == 'I') ? $proposal->first_name.' '.$proposal->last_name : $proposal->first_name,
                    "DateOfBirth" => ($proposerVehDet[0]->vehicle_owner_type == 'I' && $proposal->dob != NULL) ? Carbon::createFromDate($proposal->dob) : '',
                    "PinCode" => $proposal->pincode,
                    "PANCardNo" => isset($proposal->pan_number) ? $proposal->pan_number : "",
                    "Email" => $proposal->email,
                    "MobileNumber" => $proposal->mobile_number,
                    "AddressLine1" => $getAddress['address_1'].''.$getAddress['address_2'],
                    "CountryCode" => $customer_payload->il_country_id , //$country_code, //$rtoLocationCode[0]->country_code, //100,
                    "StateCode" => $customer_payload->il_state_id, //$state_code, //$rtoLocationCode[0]->state_code, //65,
                    "CityCode" => $customer_payload->il_citydistrict_id, //$city_district_code, //$rtoLocationCode[0]->city_code, //200,
                    "AdharNumber" => ""
                ],
                "FinancierDetails" => [
                    "FinancierName" => $proposal->name_of_financer,
                    "BranchName" => $proposal->hypothecation_city,
                    "AgreementType" => "Hypothecation"
                ],
                "PreviousPolicyDetails" => [
                    "previousPolicyStartDate" => $requestData->previous_policy_type == 'Not sure' ? '' : $previousPolicyStartDate->toDateString(),
                    "previousPolicyEndDate" => $requestData->previous_policy_type == 'Not sure' ? '' : $previousPolicyExpiryDate->toDateString(),
                    "ClaimOnPreviousPolicy" => ($requestData->is_claim == 'Y') ? true : false,
                    "PreviousPolicyType" => $requestData->previous_policy_type == 'Third-party' ? "TP" : "Comprehensive Package",
                    "PreviousInsurerName" => $requestData->business_type == 'newbusiness' ? "" : $proposal->previous_insurance_company,
                    "PreviousPolicyNumber" => $requestData->business_type == 'newbusiness' ? "" : $proposal->previous_policy_number,
                    // "BonusOnPreviousPolicy" => $requestData->previous_policy_type == 'Third-party' ? 0 : (($requestData->is_claim == 'Y') ? '0' : $requestData->previous_ncb)
                    "BonusOnPreviousPolicy" => $requestData->previous_policy_type == 'Third-party' ? 0 : $requestData->previous_ncb
                ]
            ];


            if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                $premiumRecalcRequest['TypeOfCalculation'] = "Pro Rata";
            }

            if ($tppdUser) {
                $premiumRecalcRequest['tppdLimit'] =  config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? 6000 : 750000;
            }


            if (($compulsorypaOwnDriver['status'] == 'true') && ($proposerVehDet[0]->vehicle_owner_type == 'I')) {
                $premiumRecalcRequest['NomineeDetails'] = [
                    "Relationship"  => $proposal->nominee_relationship,
                    "NameOfNominee" => $proposal->nominee_name,
                    'Age'           => get_date_diff('year', $proposal->nominee_dob),
                ];
            }


           /*  $premiumRecalcRequest['VehiclechasisPrice'] = $chassisPrice;
            $premiumRecalcRequest['vehiclebodyPrice'] = '0'; */

           /*  $premiumRecalcRequest['VehiclechasisPrice'] = 0;
            $premiumRecalcRequest['vehiclebodyPrice'] =  $vehiclebodyPrice; */

        $premiumRecalcRequest['VehiclechasisPrice'] = $vehiclebodyPrice;
        $premiumRecalcRequest['vehiclebodyPrice'] = 0;


            if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') {
                unset($premiumRecalcRequest['PreviousPolicyDetails']);
            }

            //query for fetching POS details
            $is_pos = 'N';
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
            $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
            $pos_data = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id',$enquiryId)
                ->where('user_proposal_id',$proposal['user_proposal_id'])
                ->where('seller_type','P')
                ->first();
            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {
                if($pos_data)
                {
                    $is_pos = 'Y';
                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                    $CertificateNumber = $pos_data->unique_number;#$pos_data->user_name;
                    $PanCardNo = $pos_data->pan_no;
                    $AadhaarNo = $pos_data->aadhar_no;
                }

                if($pos_testing_mode === 'Y')
                {
                    $is_pos = 'Y';
                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                    $CertificateNumber = 'TMI0001';
                    $PanCardNo = 'ABGTY8890Z';
                    $AadhaarNo = '569278616999';
                }

                if(config('ICICI_LOMBARD_IS_NON_POS') == 'Y')
                {
                    $is_pos = 'N';
                    $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = $ProductCode = '';
                    $premiumRecalcRequest['DealId'] = $ICICI_LOMBARD_DEAL_ID;
                }

                $ProductCode = $PRODUCT_CODE;
            }
            elseif($pos_testing_mode === 'Y')
            {
                $is_pos = 'Y';
                $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                $CertificateNumber = 'TMI0001';
                $PanCardNo = 'ABGTY8890Z';
                $AadhaarNo = '569278616999';
                $ProductCode = $PRODUCT_CODE;
            }
            if (config('ICICI_LOMBARD_IS_NON_POS') == 'Y') {
                $is_pos = 'N';
                $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = $ProductCode = '';
                $premiumRecalcRequest['DealId'] = $ICICI_LOMBARD_DEAL_ID;
            }

            $additionPremData = [
                'requestMethod' => 'post',
                'type' => 'premiumCalculation',
                'section' => 'MISC',
                'token' => $access_token,
                'enquiryId' => $enquiryId,
                'transaction_type'    => 'proposal',
                'productName'  => $productData->product_name,
            ];

            if($is_pos == 'Y')
            {
                $pos_details = [
                    'pos_details' => [
                        'IRDALicenceNumber' => $IRDALicenceNumber,
                        'CertificateNumber' => $CertificateNumber,
                        'PanCardNo'         => $PanCardNo,
                        'AadhaarNo'         => $AadhaarNo,
                        'ProductCode'       => $ProductCode
                    ]
                ];
                $additionPremData = array_merge($additionPremData,$pos_details);
            }
            $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_QUOTE_PREMIUM_CALC_URL'), $premiumRecalcRequest, 'icici_lombard', $additionPremData);
            $premRecalculateResponse = $get_response['response'];
            if (!empty($premRecalculateResponse)) {
                $premiumResponse = json_decode($premRecalculateResponse, true);

                if(!isset($premiumResponse))
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => strip_tags($premRecalculateResponse)
                    ];
                }

                if (isset($premiumResponse['statusMessage']) && $premiumResponse['statusMessage'] == 'SUCCESS') {
                    if ( ! empty($premiumResponse['isQuoteDeviation']) && $premiumResponse['isQuoteDeviation'] && $parentCode == 'PCV') {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => "Quotes are not allowed if Deviation flag is true"
                        ];
                    }

                    $proposalRequestArray = [
                        // "DealId" => $ICICI_LOMBARD_DEAL_ID,
                        "CustomerType" => ($proposerVehDet[0]->vehicle_owner_type == 'I') ? "INDIVIDUAL" : "Corporate",
                        "CorrelationId" => $corelationId,
                        "PolicyEndDate" => $policyEndDate->toDateString(),
                        "PolicyStartDate" => $policyStartDate->toDateString(),
                        "RTOLocationCode" => $rtoLocationCode, //$rtoLocationCode->rto_location_code,#5207
                         "VehicleMakeCode" =>  $mmvData->manf_code,#366
                         "VehicleModelCode" =>  $mmvData->ic_version_code,#6338
                        "ManufacturingYear" => date('Y', strtotime('01-' . $proposal->vehicle_manf_year)),
                        "DeliveryOrRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet[0]->vehicle_register_date)),
                        "FirstRegistrationDate" => date('Y-m-d', strtotime($proposerVehDet[0]->vehicle_register_date)),
                        "GSTToState" => $state_name, //$rtoLocationCode[0]->state_name,
                        "BusinessType" => ($premium_type == 'third_party' && $businessType == 'Roll Over' ) ? 'Used' : $businessType,
                        "ProductCode" =>$PRODUCT_CODE,
                        "IsVehicleHaveCNG" => (!empty($lpgAndCng)) ? $lpgAndCng['status'] : 'false',
                        "IsVehicleHaveLPG" => $vehicleHaveLPG,
                        "SI_VehicleLPGCNG_KIT" => (!empty($lpgAndCng)) ? $lpgAndCng['addonSI'] : '0',
                        "IsPrivateUse" => "false",
                        "IsLimitedToOwnPremises" => "false",
                        "IsNonFarePayingPassengers" => "false",
                         "InclusionOfIMT" => $is_imt ,

                        // "IsNCBApproved" => (($requestData->is_claim == 'Y') || ($requestData->business_type == 'breakin' && $breakin_days > 90) || $requestData->previous_policy_expiry_date == 'New') ? 'false' : 'true',
                        // "IsNCBApplicable" => (($requestData->is_claim == 'Y') || ($requestData->business_type == 'breakin' && $breakin_days > 90) || $requestData->previous_policy_expiry_date == 'New') ? 'false' : 'true',
                        "NoOfNonFarePayingPassenger" => 2,
                        "IsHireOrHiresEmployee" => "false", // default
                        "IsPACoverOwnerDriver" => (!empty($compulsorypaOwnDriver)) ? $compulsorypaOwnDriver['status'] : 'false',
                        "ISPACoverWaiver" => (!empty($compulsorypaOwnDriver) && $compulsorypaOwnDriver['status'] == "true") ? 'false' : 'true',
                        // "ISPACoverWaiver" =>  'false',
                        "IsNoPrevInsurance" => $requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure' ? "true" : "false",
                        "IsAntiTheftDisc" => (!empty($antiTheftDisc)) ? $antiTheftDisc['status'] : 'false',
                        "IsConsumables" => "false", // default
                        "ZeroDepPlanName" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '' : $zeroDepPlanName,
                        // "ZeroDepPlanName" => ($premium_type == 'third_party' || $premium_type == 'third_party_breakin' || $master_product_sub_type_code == 'PICKUP-DELIVERY-VAN') ? "" : ((!empty($zeroDep)) ? $zeroDep['name'] : ''),
                        "RSAPlanName" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? "" : ((!empty($rsa)) ? $rsa['name'] : ''),
                        "IsHaveElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? 'false' : ((!empty($eleAccessories)) ? $eleAccessories['status'] : 'false'),
                        "SIHaveElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '0' : ((!empty($eleAccessories)) ? $eleAccessories['addonSI'] : '0'),
                        "IsHaveNonElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? 'false' : ((!empty($nonEleAccessories)) ? $nonEleAccessories['status'] : 'false'),
                        "SIHaveNoNElectricalAccessories" => ($premium_type == 'third_party') || $premium_type == 'third_party_breakin' ? '0' : ((!empty($nonEleAccessories)) ? $nonEleAccessories['addonSI'] : '0'),
                        "IsLegalLiabilityToPaidDriver" => $type == 'GCV' && isset($llPaidDriverCC['noOfDriver']) && $llPaidDriverCC['noOfDriver'] == 0 ? false : $LiabilityToPaidDriver_IsChecked,
                        "NoOfDriver" => $llPaidDriverCC['status'] = 'true' ? $llPaidDriverCC['noOfDriver'] : "1",
                        "NoOfCleanerOrConductor" => $noOfCleanerAndConductor,
                        "RegistrationNumber" => str_replace("-", "", $proposal->vehicale_registration_number),
                        "EngineNumber" => $proposal->engine_number,
                        "ChassisNumber" => $proposal->chassis_number,
                        "IsPrivateCarrier" =>  $gcvCarrierType,
                        "CustomerDetails" => [
                            "CustomerType" => ($proposerVehDet[0]->vehicle_owner_type == 'I') ? "INDIVIDUAL" : "Corporate",
                            "CustomerName" => ($proposerVehDet[0]->vehicle_owner_type == 'I') ? $proposal->first_name.' '.$proposal->last_name : $proposal->first_name,
                            "DateOfBirth" => ($proposerVehDet[0]->vehicle_owner_type == 'I' && $proposal->dob != NULL) ? Carbon::createFromDate($proposal->dob) : '',
                            "PinCode" => $proposal->pincode,
                            "PANCardNo" => isset($proposal->pan_number) ? $proposal->pan_number : "",
                            "Email" => $proposal->email,
                            "MobileNumber" => $proposal->mobile_number,
                            "AddressLine1" => $getAddress['address_1'].''.$getAddress['address_2'],//$proposal->address_line1." ".$proposal->address_line2." ".$proposal->address_line3,
                            "CountryCode" => $customer_payload->il_country_id , //$country_code, //$rtoLocationCode[0]->country_code, //100,
                            "StateCode" => $customer_payload->il_state_id, //$state_code, //$rtoLocationCode[0]->state_code, //65,
                            "CityCode" => $customer_payload->il_citydistrict_id, //$city_district_code, //$rtoLocationCode[0]->city_code, //200,
                            "AdharNumber" => ""
                        ],
                        "PreviousPolicyDetails" => [
                            "previousPolicyStartDate" => $requestData->previous_policy_type == 'Not sure' ? '' : $previousPolicyStartDate->toDateString(),
                            "previousPolicyEndDate" => $requestData->previous_policy_type == 'Not sure' ? '' : $previousPolicyExpiryDate->toDateString(),
                            "ClaimOnPreviousPolicy" => ($requestData->is_claim == 'Y') ? true : false,
                            "PreviousPolicyType" => $requestData->previous_policy_type == 'Third-party' ? "TP" : "Comprehensive Package",
                            "PreviousInsurerName" => $requestData->business_type == 'newbusiness' ? "" : $proposal->previous_insurance_company,
                            "PreviousPolicyNumber" => $requestData->business_type == 'newbusiness' ? "" : $proposal->previous_policy_number,
                            "BonusOnPreviousPolicy" => $requestData->previous_policy_type == 'Third-party' ? 0 : $requestData->previous_ncb
                        ]
                    ];


                    if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                        $proposalRequestArray['TypeOfCalculation'] = "Pro Rata";
                    }

                    if ($tppdUser) {
                        $proposalRequestArray['tppdLimit'] = 6000;
                    }

                    if ($proposal->is_vehicle_finance == '1') {
                        $proposalRequestArray["FinancierDetails"] = [
                            "FinancierName" => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : "",
                            "BranchName" => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : "",
                            "AgreementType" => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : ""
                        ];
                    }

                    if (($compulsorypaOwnDriver['status'] == 'true') && ($proposerVehDet[0]->vehicle_owner_type == 'I')) {
                        $proposalRequestArray['NomineeDetails'] = [
                            "Relationship"  => $proposal->nominee_relationship,
                            "NameOfNominee" => $proposal->nominee_name,
                            'Age'           => get_date_diff('year',$proposal->nominee_dob),
                        ];
                    }

                    if ($proposal->gst_number != '') {
                        $proposalRequestArray['CustomerDetails']['GSTDetails']['GSTExemptionApplicable'] = 'Yes';
                        $proposalRequestArray['CustomerDetails']['GSTDetails']['GSTInNumber'] = $proposal->gst_number;
                        $proposalRequestArray['CustomerDetails']['GSTDetails']['GSTToState'] = $state_name;
                    }

                    if(config('constants.IS_CKYC_ENABLED') == 'Y') {
                        $proposalRequestArray['CustomerDetails']['CKYCID'] = $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number;
                        $proposalRequestArray['CustomerDetails']['EKYCid'] = null;
                        $proposalRequestArray['CustomerDetails']['ilkycReferenceNumber'] = $proposal->ckyc_reference_id;
                        if($proposal->is_car_registration_address_same == '0') {
                            $proposalRequestArray['CustomerDetails']['correspondingAddress'] = [
                                'AddressLine1' => implode(' ',[$proposal->car_registration_address1, $proposal->car_registration_address2, $proposal->car_registration_address3]),
                                'CountryCode' => $customer_payload->il_country_id,//$countrycode,
                                'Statecode' => $proposal->car_registration_state_id,
                                'CityCode' => $proposal->car_registration_city_id,
                                'Pincode' => $proposal->car_registration_pincode,
                            ];
                        }
                        //$proposalRequestArray['CustomerDetails']['SkipDedupeLogic'] = ;
                    }


                   /*  $proposalRequestArray['VehiclechasisPrice'] = $chassisPrice;
                    $proposalRequestArray['vehiclebodyPrice'] = '0'; */

                    /* $proposalRequestArray['VehiclechasisPrice'] = 0;
                    $proposalRequestArray['vehiclebodyPrice'] = $vehiclebodyPrice; */
                   
                        $proposalRequestArray['VehiclechasisPrice'] = $vehiclebodyPrice;
                        $proposalRequestArray['vehiclebodyPrice'] = 0;


                    if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') {
                        unset($proposalRequestArray['PreviousPolicyDetails']);
                    }


                    $additionPremData = [
                        'requestMethod' => 'post',
                        'type' => 'proposalService',
                        'section' => 'MISC',
                        'token' => $access_token,
                        'enquiryId' => $enquiryId,
                        'transaction_type'    => 'proposal',
                        'productName'  => $productData->product_name,
                    ];

                    if($is_pos == 'Y')
                    {
                        $pos_details = [
                            'pos_details' => [
                                'IRDALicenceNumber' => $IRDALicenceNumber,
                                'CertificateNumber' => $CertificateNumber,
                                'PanCardNo'         => $PanCardNo,
                                'AadhaarNo'         => $AadhaarNo,
                                'ProductCode'       => $ProductCode
                            ]
                        ];
                        $additionPremData = array_merge($additionPremData,$pos_details);
                    }
                    else
                    {
                       $proposalRequestArray['DealId'] = $ICICI_LOMBARD_DEAL_ID;
                    }
                    $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PROPOSAL_URL'), $proposalRequestArray, 'icici_lombard', $additionPremData);
                    $proposalServiceResponse = $get_response['response'];

                    if (!empty($proposalServiceResponse)) {
                        $proposalServiceResponseData = json_decode($proposalServiceResponse, true);

                        if (isset($proposalServiceResponseData['statusMessage']) && $proposalServiceResponseData['statusMessage'] == 'SUCCESS') {

                            if(isset($proposalServiceResponseData['isQuoteDeviation']) && $proposalServiceResponseData['isQuoteDeviation'])
                            {
                               return [
                                   'status' => false,
                                   'webservice_id' => $get_response['webservice_id'],
                                   'table' => $get_response['table'],
                                   'message' => 'Ex-Showroom price provided is not under permissable limits'
                               ];
                            }

                            if(isset($proposalServiceResponseData['isApprovalRequired']) && $proposalServiceResponseData['isApprovalRequired'] == 'true' && $requestData->business_type != 'breakin')
                                {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => 'Proposal application did not pass underwriter approval'
                                ];
                                }


                            if($premium_type == 'comprehensive' || $premium_type == 'own_damage' || $premium_type == 'short_term_3' || $premium_type == 'short_term_6')
                            {
                                // if(isset($proposalServiceResponseData['isApprovalRequired']) && $proposalServiceResponseData['isApprovalRequired'] == 'true')
                                // {
                                // return [
                                //     'status' => false,
                                //     'webservice_id' => $get_response['webservice_id'],
                                //     'table' => $get_response['table'],
                                //     'message' => 'Proposal is not eligible'
                                // ];
                                // }

                                if(isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'true')
                                {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => 'Breakin Flag must be False in Non Breakin Case'
                                ];
                                } elseif (isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'false')
                                {
                                    return [
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => 'Breakin Flag must be True in Breakin Case'
                                    ];
                                }
                            }

                            $zeroDepreciation = isset($premiumResponse['riskDetails']['zeroDepreciation']) ? $premiumResponse['riskDetails']['zeroDepreciation'] : 0;
                            $imt23addon = isset($premiumResponse['riskDetails']['imT23OD']) ? $premiumResponse['riskDetails']['imT23OD'] : 0;
                            $roadSideAssistance = isset($premiumResponse['riskDetails']['roadSideAssistance']) ? $premiumResponse['riskDetails']['roadSideAssistance'] : 0;
                            $basicOd = isset($premiumResponse['riskDetails']['basicOD']) ? $premiumResponse['riskDetails']['basicOD'] : 0;
                            $basicTp = isset($premiumResponse['riskDetails']['basicTP']) ? $premiumResponse['riskDetails']['basicTP'] : 0;
                            $electricalAccessories = isset($premiumResponse['riskDetails']['electricalAccessories']) ? $premiumResponse['riskDetails']['electricalAccessories'] : 0;
                            $nonElectricalAccessories = isset($premiumResponse['riskDetails']['nonElectricalAccessories']) ? $premiumResponse['riskDetails']['nonElectricalAccessories'] : 0;
                            $voluntaryDiscount = isset($premiumResponse['riskDetails']['voluntaryDiscount']) ? $premiumResponse['riskDetails']['voluntaryDiscount'] : 0;
                            $antiTheftDiscount = isset($premiumResponse['riskDetails']['antiTheftDiscount']) ? $premiumResponse['riskDetails']['antiTheftDiscount'] : 0;
                            $paidDriver = isset($premiumResponse['riskDetails']['paidDriver']) ? $premiumResponse['riskDetails']['paidDriver'] : 0;
                            $paCoverForUnNamedPassenger = isset($premiumResponse['riskDetails']['paCoverForUnNamedPassenger']) ?: 0;
                            $paCoverForOwnerDriver = isset($premiumResponse['riskDetails']['paCoverForOwnerDriver']) ? $premiumResponse['riskDetails']['paCoverForOwnerDriver'] : 0;
                            $bonusDiscount = isset($premiumResponse['riskDetails']['bonusDiscount']) ? $premiumResponse['riskDetails']['bonusDiscount'] : 0;
                            $ncbPercentage = isset($premiumResponse['riskDetails']['ncbPercentage']) ? $premiumResponse['riskDetails']['ncbPercentage'] : 0;
                            $biFuelKitOD = isset($premiumResponse['riskDetails']['biFuelKitOD']) ? $premiumResponse['riskDetails']['biFuelKitOD'] : 0;
                            $biFuelKitTP = isset($premiumResponse['riskDetails']['biFuelKitTP']) ? $premiumResponse['riskDetails']['biFuelKitTP'] : 0;
                            $totalTax = isset($premiumResponse['premiumDetails']['totalTax']) ? $premiumResponse['premiumDetails']['totalTax'] : 0;
                            $tppd_discount = isset($premiumResponse['riskDetails']['tppD_Discount']) ? $premiumResponse['riskDetails']['tppD_Discount'] : 0;


                            $totalOdPremium = isset($proposalServiceResponseData['premiumDetails']['totalOwnDamagePremium']) ? $proposalServiceResponseData['premiumDetails']['totalOwnDamagePremium'] : 0;
                            $totalTpPremium = isset($proposalServiceResponseData['premiumDetails']['totalLiabilityPremium']) ? $proposalServiceResponseData['premiumDetails']['totalLiabilityPremium'] : 0;                            
                            $totalDiscount = round($antiTheftDiscount + $voluntaryDiscount + $bonusDiscount);
                            $addon_premium = $zeroDepreciation + $roadSideAssistance + $imt23addon;

                            $final_premium = isset($proposalServiceResponseData['premiumDetails']['finalPremium']) ? $proposalServiceResponseData['premiumDetails']['finalPremium'] : 0;
                            if($premium_type == 'third_party')
                                {
                                    $basePremium = isset($proposalServiceResponseData['premiumDetails']['totalLiabilityPremium']) ? $proposalServiceResponseData['premiumDetails']['totalLiabilityPremium'] : 0;
                                }
                                else
                                {
                                    $basePremium = isset($proposalServiceResponseData['premiumDetails']['packagePremium']) ? $proposalServiceResponseData['premiumDetails']['packagePremium'] : 0;
                                }
                           /* $mmvData = IcVersionMapping::leftjoin('icici_mmv_master', function ($join) {
                               $join->on('icici_mmv_master.model_code', '=', 'ic_version_mapping.ic_version_code');
                           })
                               ->where([
                                   'ic_version_mapping.fyn_version_id' => $proposerVehDet[0]->version_id,
                                   'ic_version_mapping.ic_id' => '40'
                               ])
                               ->select('ic_version_mapping.*', 'icici_mmv_master.*')
                               ->first();

                           $mmvDet = MotorModelVersion::where('version_id', $requestData->version_id)
                               ->select('*')
                               ->first(); */

                            if($type == 'MISC'){
                                $vehDetails['manf_name'] = $mmvData->manf_name;
                                $vehDetails['model_name'] = $mmvData->model_name;
                                // $vehDetails['version_name'] = $mmvData->model_name;
                                $vehDetails['version_id'] = $mmvData->model_code;
                                $vehDetails['seating_capacity'] = $mmvData->seating_capacity;
                                $vehDetails['cubic_capacity'] = $mmvData->cubic_capacity;
                                $vehDetails['fuel_type'] = $mmvData->fuel_type;
                                $vehDetails['vehicle_type'] = 'MISC';
                            }else{ #remove this
                                $vehDetails['manf_name'] = '';
                                $vehDetails['model_name'] = '';
                                $vehDetails['version_name'] = '';
                                $vehDetails['version_id'] = '';
                                $vehDetails['seating_capacity'] = '';
                                $vehDetails['cubic_capacity'] = '';
                                $vehDetails['fuel_type'] = '';
                                $vehDetails['vehicle_type'] = '';
                            }

                            IciciLombardPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                            if (isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'true' && in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) {

                                $brekinIdRequestArray = [
                                    "CustomerName" => $proposerVehDet[0]->vehicle_owner_type == 'I' ? $proposal->first_name.' '.$proposal->last_name : $proposal->first_name,
                                    "CustomerAddress" => $proposal->address_line1. $proposal?->address_line2. $proposal?->address_line3,
                                    "State" => $proposal->state,
                                    "City" => $proposal->city,
                                    "MobileNumber" => $proposal->mobile_number,
                                    "TypeVehicle" => $type == 'GCV'? 'GOODS CARRYING' :'PASSENGER CARRYING',
                                    "VehicleMake" => $vehDetails['manf_name'],
                                    "VehicleModel" =>  $vehDetails['model_name'],
                                    "RegistrationNo" => $proposal->vehicale_registration_number,
                                    "EngineNo" => $proposal->engine_number,
                                    "ChassisNo" => $proposal->chassis_number,
                                    "ManufactureYear" => date('Y', strtotime('01-' . $proposal->vehicle_manf_year)),
                                    "SubLocation" => $proposal->city,
                                    "InspectionType" => "ROLLOVER",
                                    "BreakInDays" => $date_diff_in_prev_policy,
                                    "BreakInType" => "Break-in Policy lapse",
                                    "BasicODPremium" => $totalOdPremium,
                                    "DistributorName" => "Emmet",
                                    // "DealId" =>  $type == 'GCV' ? config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN') :, //$ICICI_LOMBARD_DEAL_ID,
                                    "DealId" =>  $type == 'GCV' ? "DEAL-3008-0206119" : config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN'),//$ICICI_LOMBARD_DEAL_ID,
                                    "IsPOSDealId" => false,
                                    "CorrelationId" => $corelationId,
                                    "selfInspection" => 'Yes'
                                ];

                                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GENERATE_BREKIN_ID'), $brekinIdRequestArray, 'icici_lombard', $additionPremData);
                                $brekinIdResponse = $get_response['response'];
                                if (!empty($brekinIdResponse)) {
                                    $brekinIdResponseData = json_decode($brekinIdResponse, true);
                                    if ($brekinIdResponseData['status'] == 'Success') {
                                        $brekinId = $brekinIdResponseData['brkId'];

                                        if($brekinId === '0' || $brekinId === 0){
                                            return [
                                                'status' => false,
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'message' => 'Something went wrong in breakin generation'
                                            ];
                                        }

                                        DB::table('cv_breakin_status')
                                            ->updateOrInsert(
                                                ['user_proposal_id' => $proposal->user_proposal_id],
                                                [
                                                    'ic_id' => 40,
                                                    'breakin_number' => $brekinId,
                                                    'breakin_id' => $brekinId,
                                                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                                    'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                                    'breakin_response' => $brekinIdResponse,
                                                    'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                                    'payment_end_date' => Carbon::today()->addDay(3)->toDateString(),
                                                    'created_at' => Carbon::today()->toDateTimeString(),
                                                    'updated_at' => Carbon::today()->toDateTimeString()
                                                ]
                                            );

                                        updateJourneyStage([
                                            'user_product_journey_id' => $enquiryId,
                                            'ic_id' => $productData->company_id,
                                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                            'proposal_id' => $proposal->user_proposal_id
                                        ]);
                                    } else {
                                        return [
                                            'status' => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => $brekinIdResponseData['message']
                                        ];
                                    }
                                }
                            } else {
                                updateJourneyStage([
                                    'user_product_journey_id' => $enquiryId,
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                    'proposal_id' => $proposal->user_proposal_id
                                ]);
                            }


                            if(!$proposalServiceResponseData['generalInformation']['proposalNumber']) {
                                return response()->json([
                                    'status' => false,
                                    'message' => "Proposal number is null"
                                ]);
                            }
                            $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'proposal_no' => $proposalServiceResponseData['generalInformation']['proposalNumber'],
                                    'customer_id' => $proposalServiceResponseData['generalInformation']['customerId'],
                                    'unique_proposal_id' => $corelationId,
                                    'policy_start_date' => Carbon::parse($policyStartDate->toDateString())->format('d-m-Y'),
                                    'policy_end_date' =>  Carbon::parse($policyEndDate->toDateString())->format('d-m-Y'),
                                    'od_premium' => $totalOdPremium,
                                    'tp_premium' => $totalTpPremium,
                                    'addon_premium' => $addon_premium,
                                    'cpa_premium' => $paCoverForOwnerDriver,
                                    'service_tax_amount' => $totalTax,
                                    'total_premium'  => $basePremium,
                                    'total_discount' => $totalDiscount,
                                    'final_payable_amount' => $final_premium,
                                    'ic_vehicle_details' => json_encode($vehDetails),
                                    'is_breakin_case' => (isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'true' && in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) ? 'Y' : 'N',
                                    'body_idv' => isset($proposalServiceResponseData['generalInformation']['bodyPrice']) ? (int) $proposalServiceResponseData['generalInformation']['bodyPrice'] : 0,
                                    'chassis_idv' => isset($proposalServiceResponseData['generalInformation']['chassisPrice']) ? (int) $proposalServiceResponseData['generalInformation']['chassisPrice'] : 0,
                                    'tp_start_date' =>   Carbon::parse($policyStartDate->toDateString())->format('d-m-Y'),
                                    'tp_end_date' => Carbon::parse($policyEndDate->toDateString())->format('d-m-Y')
                                ]);

                            updateJourneyStage([
                                'user_product_journey_id' => $enquiryId,
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id
                            ]);

                            if ($updateProposal) {
                                $proposal_data = UserProposal::find($proposal->user_proposal_id);
                                return response()->json([
                                    'status' => true,
                                    'message' => "Proposal Submitted Successfully!",
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'data' => [
                                        'proposalId' =>  $proposal->user_proposal_id,
                                        'proposalNumber' => $proposalServiceResponseData['generalInformation']['proposalNumber'],
                                        'customerId' => $proposalServiceResponseData['generalInformation']['customerId'],
                                        'userProductJourneyId' => $proposal_data->user_product_journey_id,
                                        'title' => $proposal_data->title,
                                        'firstName' => $proposal_data->first_name,
                                        'lastName' => $proposal_data->last_name,
                                        'email' => $proposal_data->email,
                                        'officeEmail' => $proposal_data->office_email,
                                        'maritalStatus' => $proposal_data->marital_status,
                                        'mobileNumber' => $proposal_data->mobile_number,
                                        'dob' => $proposal_data->dob,
                                        'occupation' => $proposal_data->occupation,
                                        'gender' => $proposal_data->gender,
                                        'panNumber' => $proposal_data->pan_number,
                                        'gstNumber' => $proposal_data->gst_number,
                                        'addressLine1' => $proposal_data->address_line1,
                                        'addressLine2' => $proposal_data->address_line2,
                                        'addressLine3' => $proposal_data->address_line3,
                                        'pincode' => $proposal_data->pincode,
                                        'state' => $proposal_data->state,
                                        'city' => $proposal_data->city,
                                        'street' => $proposal_data->street,
                                        'rtoLocation' => $proposal_data->rto_location,
                                        'vehicleColor' => $proposal_data->vehicle_color,
                                        'isValidPuc' => $proposal_data->is_valid_puc,
                                        'financerAgreementType' => $proposal_data->financer_agreement_type,
                                        'financerLocation' => $proposal_data->financer_location,
                                        'isCarRegistrationAddressSame' => $proposal_data->is_car_registration_address_same,
                                        'carRegistrationAddress1' => $proposal_data->car_registration_address1,
                                        'carRegistrationAddress2' => $proposal_data->car_registration_address2,
                                        'carRegistrationAddress3' => $proposal_data->car_registration_address3,
                                        'carRegistrationPincode' => $proposal_data->car_registration_pincode,
                                        'carRegistrationState' => $proposal_data->car_registration_state,
                                        'carRegistrationCity' => $proposal_data->car_registration_city,
                                        'vehicaleRegistrationNumber' => $proposal_data->vehicale_registration_number,
                                        'vehicleManfYear' => $proposal_data->vehicle_manf_year,
                                        'engineNumber' => $proposal_data->engine_number,
                                        'chassisNumber' => $proposal_data->chassis_number,
                                        'isVehicleFinance' => $proposal_data->is_vehicle_finance,
                                        'nameOfFinancer' => $proposal_data->name_of_financer,
                                        'hypothecationCity' => $proposal_data->hypothecation_city,
                                        'previousInsuranceCompany' => $proposal_data->previous_insurance_company,
                                        'previousPolicyNumber' => $proposal_data->previous_policy_number,
                                        'previousInsurerPin' => $proposal_data->previous_insurer_pin,
                                        'previousInsurerAddress' => $proposal_data->previous_insurer_address,
                                        'nomineeName' => $proposal_data->nominee_name,
                                        'nomineeAge' => $proposal_data->nominee_age,
                                        'nomineeRelationship' => $proposal_data->nominee_relationship,
                                        'proposalDate' => $proposal_data->proposal_date,
                                        'premiumPaidBy' => $proposal_data->premium_paid_by,
                                        'status' => $proposal_data->status,
                                        'isPolicyIssued' => $proposal_data->is_policy_issued,
                                        'createdDate' => $proposal_data->created_date,
                                        'isProposalVerifed' => $proposal_data->is_proposal_verifed,
                                        'prevPolicyExpiryDate' => $proposal_data->prev_policy_expiry_date,
                                        'isBreakinCase' => $proposal_data->is_breakin_case,
                                        'policyType' => $proposal_data->business_type,
                                        'paymentUrl' => $proposal_data->payment_url,
                                        'odPremium' => $proposal_data->od_premium,
                                        'tpPremium' => $proposal_data->tp_premium,
                                        'ncbDiscount' => $proposal_data->ncb_discount,
                                        'totalPremium' => $proposal_data->total_premium,
                                        'serviceTaxAmount' => $proposal_data->service_tax_amount,
                                        'finalPayableAmount' => $proposal_data->final_payable_amount,
                                        'is_breakin' => (isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'true' && in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) ? $proposal_data->is_breakin_case : 'N',
                                        'inspection_number' => (isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'true' && in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin'])) ? $brekinId : "N/A"
                                    ]
                                ]);
                            } else {
                                return response()->json([
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => "Error Occured"
                                ]);
                            }
                        } elseif (isset($proposalServiceResponseData['statusMessage']) && $proposalServiceResponseData['statusMessage'] == 'Failed' || isset($proposalServiceResponseData['StatusMessage']) && $proposalServiceResponseData['StatusMessage'] == 'Failed') {
                            return response()->json([
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => isset($proposalServiceResponseData['Message']) ? $proposalServiceResponseData['Message'] : (isset($proposalServiceResponseData['message']) ? $proposalServiceResponseData['message'] : "Error Occured")
                            ]);
                        }
                    } else {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => "Error in Proposal Service"
                        ]);
                    }
                } elseif (isset($premiumResponse['StatusMessage']) && $premiumResponse['StatusMessage'] == 'Failed' || isset($premiumResponse['statusMessage']) && $premiumResponse['statusMessage'] == 'Failed') {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => isset($premiumResponse['Message']) ? $premiumResponse['Message'] : (isset($premiumResponse['message']) ? $premiumResponse['message'] : "Error Occured")
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Error in Premium Calculation"
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => "Error in token generation"
            ]);
        }
    }

    public static function offlineSubmit($proposal, $request)
    {
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $policyStartDate = Carbon::parse($proposal->prev_policy_expiry_date)->addDays(1);
        $policyEndDate = Carbon::parse($policyStartDate)->addYear(1)->subDay(1);
        $updateProposal = $proposal->update([
                'proposal_no' => rand(),
                'customer_id' => rand(),
                'unique_proposal_id' => rand(),
                'policy_start_date' => Carbon::parse($policyStartDate->toDateString())->format('d-m-Y'),
                'policy_end_date' =>  Carbon::parse($policyEndDate->toDateString())->format('d-m-Y'),
                'od_premium' => $quoteLog->premium_json['finalOdPremium'],
                'tp_premium' => $quoteLog->premium_json['finalTpPremium'],
                'addon_premium' => $quoteLog->premium_json['addonPremium'],
                'cpa_premium' => $paCoverForOwnerDriver ?? null,
                'service_tax_amount' => $quoteLog->premium_json['finalGstAmount'],
                'total_premium'  => $quoteLog->premium_json['finalNetPremium'],
                'total_discount' => $quoteLog->premium_json['finalTotalDiscount'],
                'final_payable_amount' => $quoteLog->premium_json['finalPayableAmount'],
                'ic_vehicle_details' => json_encode($quoteLog->mmvDetail),
                // 'is_breakin_case' => (isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'true' && $premium_type == 'breakin') ? 'Y' : 'N'
            ]);
            
            \App\Models\PolicyDetails::updateOrCreate([
                'proposal_id' => $proposal->user_proposal_id],[
                'proposal_id' => $proposal->user_proposal_id,
                'policy_number' => rand(),
                'idv' => '',
                'policy_start_date' => Carbon::parse($policyStartDate->toDateString())->format('d-m-Y'),
                'ncb' => null,
                'premium' => $quoteLog->premium_json['finalPayableAmount'],
            ]);

            return response()->json([
                'status' => true,
                'message' => "Proposal Submitted Successfully!",
                'data' =>
                [
                    'proposal_no' => rand(),
                    'customer_id' => rand(),
                    'unique_proposal_id' => rand(),
                    'policy_start_date' => Carbon::parse($policyStartDate->toDateString())->format('d-m-Y'),
                    'policy_end_date' =>  Carbon::parse($policyEndDate->toDateString())->format('d-m-Y'),
                    'od_premium' => $quoteLog->premium_json['finalOdPremium'],
                    'tp_premium' => $quoteLog->premium_json['finalTpPremium'],
                    'addon_premium' => $quoteLog->premium_json['addonPremium'],
                    'cpa_premium' => $paCoverForOwnerDriver ?? null,
                    'service_tax_amount' => $quoteLog->premium_json['finalGstAmount'],
                    'total_premium'  => $quoteLog->premium_json['finalNetPremium'],
                    'total_discount' => $quoteLog->premium_json['finalTotalDiscount'],
                    'final_payable_amount' => $quoteLog->premium_json['finalPayableAmount'],
                    'ic_vehicle_details' => json_encode($quoteLog->mmvDetail),
                    // 'is_breakin_case' => (isset($proposalServiceResponseData['breakingFlag']) && $proposalServiceResponseData['breakingFlag'] == 'true' && $premium_type == 'breakin') ? 'Y' : 'N'
                ]
            ]);
    }
}

