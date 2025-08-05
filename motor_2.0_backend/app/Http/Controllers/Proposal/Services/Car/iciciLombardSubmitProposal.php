<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Http\Controllers\SyncPremiumDetail\Car\IciciLombardPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\IciciMmvMaster;
use App\Models\IciciRtoMaster;
use App\Models\IcVersionMapping;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MotorModelVersion;
use App\Models\MasterProduct;
use App\Models\Quotes\Cv\CvQuoteModel;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use App\Models\QuoteLog;
use App\Models\MasterPremiumType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Config;
use DateTime;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
include_once app_path().'/Helpers/CarWebServiceHelper.php';

class iciciLombardSubmitProposal
{

    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {

        if(env('APP_ENV') == 'local' && config('ICICI_LOMBARD_CAR_PLAN_TYPE_ENABLE') == 'Y')
        {
          return \App\Http\Controllers\Proposal\Services\Car\iciciLombardPlanSubmitProposal::submit($proposal, $request);
        }
    
       
        $additional_details = json_decode($proposal->additional_details);
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_log_premium_json = $quote_log->premium_json;

        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $corporate_vehicle_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $master_policy_id = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        
        $mmv = get_mmv_details($productData,$requestData->version_id,'icici_lombard');
        $IsPos = 'N';
        $breakingFlag = false;

        if($mmv['status'] == 1)
        {
        $mmv = $mmv['data'];
        }
        else
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);


        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }
        $is_breakin     = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);
        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
        $vehicale_registration_number =  $proposal->vehicale_registration_number;
        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number = 'NEW';
        }

        if (isBhSeries($proposal->vehicale_registration_number)) 
        {
            $vehicale_registration_number = strtoupper(preg_replace('/-/', '',$proposal->vehicale_registration_number));
        }
        else{
            $vehicale_registration_number = strtoupper(preg_replace('/-/', '',$proposal->vehicale_registration_number));

        }

        

        $request['userProductJourneyId'] = customDecrypt($request['userProductJourneyId']);

        $requestData = getQuotation($request['userProductJourneyId']);
        $enquiryId = customDecrypt($request['enquiryId']);

         // car age calculation
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = ceil($age / 12);
    
    

      


        $proposerVehDet = $corporate_vehicle_quotes_request;
        $additional_data = $proposal->additonal_data;

        $countrycode=100;
        $master_rto = MasterRto::where('rto_code', $requestData->rto_code)->first();
        if (empty($master_rto->icici_4w_location_code))
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $requestData->rto_code.' RTO Location Code Not Found',
                'request' => [
                    'rto_code' => $requestData->rto_code
                ]
            ];
        }
        $state_name = MasterState::where('state_id', $master_rto->state_id)->first();
        $state_name = strtoupper($state_name->state_name);
        /* $state_id = $rto_cities->state_id;
        $state_name = MasterState::where('state_id', $state_id)->first();
        $state_name = strtoupper($state_name->state_name);
        $rto_cities = explode('/',  $rto_cities->rto_name); */
        /* foreach($rto_cities as $rto_city)
        {
            $rto_city = strtoupper($rto_city);
            $rto_data = DB::table('car_icici_lombard_rto_location')
                        ->where('txt_rto_location_desc', $state_name ."-". $rto_city)
                        ->first();
           
            if($rto_data)
            {
                
                break;
            }
        } */

        $rto_data = DB::table('car_icici_lombard_rto_location')
                    ->where('txt_rto_location_code', $master_rto->icici_4w_location_code)
                    ->first();
      
        if (empty($rto_data)) 
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Premium is not available for this RTO Location',
            ];
        }
        else
        {
            $txt_rto_location_code = $rto_data->txt_rto_location_code;
        }
        $isGddEnabled = config('constants.motorConstant.IS_GDD_ENABLED_ICICI') == 'Y' ? true : false;

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();

        $isInspectionWaivedOff = false;
        $isInspectionRequired = $is_breakin && !(in_array($premium_type, ['third_party', 'third_party_breakin']));

        if (
            $isInspectionRequired &&
            !empty($requestData->previous_policy_expiry_date) &&
            strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
            config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_INSPECTION_WAIVED_OFF') == 'Y'
        ) {
            $date1 = new DateTime($requestData->previous_policy_expiry_date);
            $date2 = new DateTime();
            $interval = $date1->diff($date2);

            //inspection is not required for breakin within 1 days
            if ($interval->days <= 1) {
                $isInspectionWaivedOff = true;
            }
        }
        
        
        if ($requestData->business_type == 'newbusiness') 
        {
            $policy_start_date = date('Y-m-d');
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
            $BusinessType = 'New Business';
            $isnoprevinsurance = 'true';
            $previous_policy_number = '';
            $PreviousPolicyType = '';
            $IsPreviousClaim = 'N';
            $current_ncb_rate = '';
            $applicable_ncb = '';
            $previous_vehicle_sale_date = '';
            // $policy_end_date = date('Y-m-d', strtotime('+3 year -1 day', strtotime($policy_start_date)));
            if ($premium_type == 'comprehensive') {
                $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            } elseif ($premium_type == 'third_party') {
                $policy_end_date = date('Y-m-d', strtotime('+3 year -1 day', strtotime($policy_start_date)));
            }
            $Tenure = '1';
            $TPTenure = '3';
            $first_reg_date = date('Y-m-d',strtotime($requestData->vehicle_register_date));
           
        }
        else
        {
            if($requestData->previous_policy_type == 'Not sure')
            {
                $proposerVehDet->previous_policy_expiry_date = $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                $isnoprevinsurance = 'true';
                
            }
            else
            {
                $isnoprevinsurance = 'false';
            }
            if($requestData->previous_policy_type == 'Third-party' && $premium_type == 'own_damage')
            {
                $isnoprevinsurance = 'true';
            }
            if($requestData->previous_policy_type_identifier_code == '33')
            {
                $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-3 year +1 day', strtotime($proposerVehDet->previous_policy_expiry_date)));
            }
            else
            {
               $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($proposerVehDet->previous_policy_expiry_date))); 
            }
            
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($proposerVehDet->previous_policy_expiry_date));
            if (($requestData->business_type == 'breakin')) 
            {   
                if($isInspectionWaivedOff ===true)
                {
                    $policy_start_date = date('Y-m-d');  
                }
                else{
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));  
                }
                                        
            }
            else
            {
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($proposerVehDet->previous_policy_expiry_date)));
            }
            if($requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party' || 
                $requestData->business_type == 'breakin' ||
                $requestData->ownership_changed == 'Y' || $productData->good_driver_discount=="Yes" && $isGddEnabled)
            {
                $breakingFlag = true;
            }
            
            $PreviousPolicyType = ($requestData->previous_policy_type == 'Third-party') ? 'TP': 'Comprehensive Package';
            $BusinessType = 'Roll Over';
            $previous_vehicle_sale_date = date('Y-m-d',strtotime($requestData->vehicle_register_date));
            $first_reg_date = $previous_vehicle_sale_date;
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $Tenure = '1';
            $TPTenure = '1';
            $breakin_days = get_date_diff('day', $requestData->previous_policy_expiry_date);   
            $IsPreviousClaim = ($requestData->is_claim == 'Y') ? 'Y' :'N';

            if ($requestData->is_claim == 'N'  && $premium_type != 'third_party' && $breakin_days < 90)
            {
                $applicable_ncb = $requestData->applicable_ncb;
                $current_ncb_rate = $requestData->previous_ncb;
            }
            else
            {
                $current_ncb_rate = 0;
                $applicable_ncb = 0;

            }

            if($requestData->is_claim == 'Y'  && $premium_type != 'third_party') {
                $current_ncb_rate = $requestData->previous_ncb;
            }

            


            $PreviousPolicyDetails = [
                    'previousPolicyStartDate' => $PrevYearPolicyStartDate,
                    'previousPolicyEndDate' => $PrevYearPolicyEndDate,
                    'PreviousInsurerName' => $proposal->previous_insurance_company,
                    'ClaimOnPreviousPolicy'   => ($IsPreviousClaim == 'Y') ? true : false,
                    'PreviousPolicyType' => $PreviousPolicyType,
                    'TotalNoOfODClaims'       => ($IsPreviousClaim == 'Y') ? '1' : '0',
                    'BonusOnPreviousPolicy' => $requestData->previous_policy_type == 'Third-party' ? 0 :$current_ncb_rate,
                    'PreviousPolicyNumber' => $proposal->previous_policy_number,
                    'PreviousVehicleSaleDate' => '',
                    'NoOfClaimsOnPreviousPolicy'   => ($IsPreviousClaim == 'Y') ? '1' : '0',
                ];


        }

        
        $IsConsumables = false;
        $IsRTIApplicableflag = false;
        $IsEngineProtectPlus = false;
        $eme_cover = false;
        $eme_Plan_name = '';
        $LossOfPersonalBelongingPlanName = '';
        $KeyProtectPlan = '';
        $RSAPlanName = '';                  //valid upto 15 years
        $tyreProtect = false;
        $ZeroDepPlanName = '';
        $ILSmartAssistPlanName = '';
        $ispacoverownerdriver = 'false';
        /* if($productData->zero_dep == 0)
        {
            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';   
           
        }
        else
        {
            $ZeroDepPlanName = '';
        }

        if($selected_addons && $selected_addons->addons != NULL && $selected_addons->addons != '')
        {
            $addons = $selected_addons->addons;

            foreach ($addons as $value) {
          
               if($value['name'] == 'Engine Protector'  && $car_age <=5)
               {
                    $IsEngineProtectPlus = true;
               }
               if($value['name'] == 'Key Replacement'  && $car_age <=5)
               {
                    $KeyProtectPlan = 'KP1';
               }
               if($value['name'] == 'Road Side Assistance')
               {
                    
                    $RSAPlanName = 'RSA-Plus'; 
                    if($productData->zero_dep == 0)
                    {
                        if($car_age > 5 && $car_age <= 8)
                        {

                            $RSAPlanName = 'RSA-including Key Protect';
                        }   
                    }     
               }
               if($value['name'] == 'Loss of Personal Belongings'  && $car_age <= 5)
               {
                    $LossOfPersonalBelongingPlanName = 'PLAN A';
               }
               if($value['name'] == 'Consumable')
               {
                    $IsConsumables = true; 
                    if($productData->zero_dep == 0)
                    {
                        if($car_age <= 8)
                        {

                            $IsConsumables = true;
                        }
                        else
                        {
                            $IsConsumables = false;
                        }   
                    }
                    elseif($car_age > 5)
                    {
                       $IsConsumables = false;
                    } 
      
               }
               if($value['name'] == 'Return To Invoice'  && $car_age <=5)
               {
                    $IsRTIApplicableflag = true;
               }
               if($value['name'] == 'Tyre Secure'  && $car_age <=3)
               {
                    $tyreProtect = true;
               }
               if($value['name'] == 'Emergency Medical Expenses'  && $car_age <= 15)
               {
                    $eme_cover = true;
                    $eme_Plan_name = 'Premium Segment';
               }

            }
        } */
        if($selected_addons && !empty($selected_addons->applicable_addons))
        {
            $addons = $selected_addons->applicable_addons;

            foreach ($addons as $value) {
                if ($value['name'] == 'Zero Depreciation' && $productData->zero_dep == 0) {
                    $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                }
                if ($value['name'] == 'Engine Protector') {
                    $IsEngineProtectPlus = true;
                }
                if ($value['name'] == 'Key Replacement') {
                    $KeyProtectPlan = 'KP1';
                    if (in_array($masterProduct->product_identifier, ["silver", "gold", "gold_plus", "platinum", "platinum_with_rti"]) && $car_age <= 10) {
                        $KeyProtectPlan = '';
                        if($productData->zero_dep == 0){
                            if (trim($mmv_data->car_segment) == 'Premium Cars C') {
                                $KeyProtectPlan = 'KP8';
                            } else{
                                $KeyProtectPlan = 'KP1';
                            }
                        }
                    } else if (!in_array($masterProduct->product_identifier, ["silver", "gold", "gold_plus", "platinum", "platinum_with_rti"]) && $productData->zero_dep == 0) {
                        if (trim($mmv_data->car_segment) != 'Premium Cars C' && $car_age <= 10) {
                            $KeyProtectPlan = 'KP2';
                        } else if ($car_age <= 10) {
                            $RSAPlanName = 'RSA-Standard';
                        } else {
                            $KeyProtectPlan = '';
                        }
                    } else {
                        $KeyProtectPlan = '';
                    }
                }
                if ($value['name'] == 'Road Side Assistance') {
                    if (in_array($masterProduct->product_identifier, ["silver", "gold", "gold_plus", "platinum","platinum_with_rti"]) && $car_age <= 10) {
                        $RSAPlanName = ($car_age <= 7) ? 'RSA-including Key Protect' : '';
                        if($productData->zero_dep == 0){
                            if (trim($mmv_data->car_segment) == 'Premium Cars C') {
                                $RSAPlanName = 'RSA-Plus';
                            } else {
                                $RSAPlanName = 'RSA-Standard';
                            }
                        }
                    } else if (!in_array($masterProduct->product_identifier, ["silver", "gold", "gold_plus", "platinum","platinum_with_rti"]) && $productData->zero_dep == 0) {
                        if (trim($mmv_data->car_segment) == 'Premium Cars C' && $car_age <= 10) {
                            $RSAPlanName = 'RSA-Plus';
                        } else if ($car_age <= 10) {
                            $RSAPlanName = 'RSA-Standard';
                        } else {
                            $RSAPlanName = '';
                        }
                    } else {
                        $RSAPlanName = '';
                        }
                    // $RSAPlanName = 'RSA-Plus';
                    // dd($masterProduct->product_identifier);
                    // if ($productData->zero_dep == 0) {
                    //     if ($car_age > 5 && $car_age <= 7 && ($mmv_data->car_segment) != 'Premium Cars C') {
                    //         $KeyProtectPlan = '';
                    //         $RSAPlanName = 'RSA-including Key Protect';
                    //     }
                    //     if(trim($mmv_data->car_segment) == 'Premium Cars C') {
                    //         $RSAPlanName = 'RSA-Plus';
                    //     }
                    // }else if($car_age <= 7){
                    //     if($car_age > 5){
                    //         $KeyProtectPlan = '';
                    //         $RSAPlanName = 'RSA-including Key Protect';
                    //     }
                    //     if(trim($mmv_data->car_segment) == 'Premium Cars C') {
                    //         $RSAPlanName = 'RSA-Plus';
                    //     }
                    // }
                }
                if ($value['name'] == 'Loss of Personal Belongings') {
                    $LossOfPersonalBelongingPlanName = 'PLAN A';
                }
                if ($value['name'] == 'Consumable') {
                    $IsConsumables = true;
                    if ($productData->zero_dep == 0) {
                        if ($car_age <= 8) {

                            $IsConsumables = true;
                        } else {
                            $IsConsumables = false;
                        }
                    } elseif ($car_age > 5) {
                        $IsConsumables = false;
                    }
                }
                if ($value['name'] == 'Return To Invoice') {
                    $IsRTIApplicableflag = true;
                }
                if ($value['name'] == 'Tyre Secure') {
                    if(trim($mmv_data->car_segment) == 'Premium Cars C' && $car_age <= 5)
                    {
                        $tyreProtect = true;
                    }else if($car_age <= 3){
                        $tyreProtect = true;
                    }
                }
                if ($value['name'] == 'Emergency Medical Expenses'  && $car_age <= 15) {
                    $eme_cover = true;
                    $eme_Plan_name = 'Premium Segment';
                }
            }
        }
        if($RSAPlanName == 'RSA-including Key Protect') {
            $KeyProtectPlan   = '';
        }
        if($premium_type == 'third_party')
        {
            $IsConsumables = false;
            $IsRTIApplicableflag = false;
            $IsEngineProtectPlus = false;
            $LossOfPersonalBelongingPlanName = '';
            $KeyProtectPlan = '';
            $RSAPlanName = '';  
            $ZeroDepPlanName = ''; 
            $ILSmartAssistPlanName = '';   
        }
        $addonNames = array_column($selected_addons['addons'] ?? [], 'name');

        if ((in_array($masterProduct->product_identifier, ["silver", "gold", "gold_plus", "platinum", "platinum_with_rti"]) || ($IsConsumables && isset($ZeroDepPlanName))) && round($age / 12, 2) <= 4.80) {
            $ILSmartAssistPlanName = "Prestige";
            $manufacturer = strtoupper($mmv_data->manufacturer_name);
            if ($productData->zero_dep == 0) {
                if (trim($mmv_data->car_segment) == 'Premium Cars C') {
                    $ILSmartAssistPlanName = "Elite";
                    if($manufacturer == "MARUTI" && in_array('Key Replacement', $addonNames))
                    {
                    $KeyProtectPlan = 'KP8';
                    }
                    elseif(!(in_array($manufacturer, ['MARUTI','Maruti'])))
                    {
                        $KeyProtectPlan = 'KP8';
                    }
                } elseif (trim($mmv_data->car_segment) != 'Premium Cars C') {
                    $ILSmartAssistPlanName = "Pro";
                    if($manufacturer == "MARUTI" && in_array('Key Replacement', $addonNames))
                    {
                        $KeyProtectPlan = 'KP2';
                    }elseif(!(in_array($manufacturer, ['MARUTI','Maruti'])))
                    {
                    $KeyProtectPlan = 'KP2'; 
                    }
                }
            }
        } else if (
            (in_array($masterProduct->product_identifier, ["silver", "gold", "gold_plus", "platinum", "platinum_with_rti"])) || ($IsConsumables && isset($ZeroDepPlanName)) & round($age / 12, 2) >= 4.81
            && round($age / 12, 2) <= 9.80 && $requestData->applicable_ncb != 0
        ) {
            $manufacturer = strtoupper($mmv_data->manufacturer_name);
            $ILSmartAssistPlanName = "Prestige";
            if (trim($mmv_data->car_segment) == 'Premium Cars C') {
                $ILSmartAssistPlanName = "Elite";
                if($manufacturer == "MARUTI" && in_array('Key Replacement', $addonNames))
                {
                $KeyProtectPlan = 'KP8';
                }
                elseif(!(in_array($manufacturer, ['MARUTI','Maruti'])))
                {
                    $KeyProtectPlan = 'KP8'; 
                }
            } elseif (trim($mmv_data->car_segment) != 'Premium Cars C' && $mmv_data->manufacturer_name != "Maruti") {
                $ILSmartAssistPlanName = "Pro";
                if($manufacturer == "MARUTI" && in_array('Key Replacement', $addonNames))
                {
                $KeyProtectPlan = 'KP2';
                }
                elseif(!(in_array($manufacturer, ['MARUTI','Maruti'])))
                {
                    $KeyProtectPlan = 'KP2'; 
                }
            }
        }

        $PACoverTenure = 0;
        if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
                $addons = $selected_addons->compulsory_personal_accident;
            foreach ($addons as $value) 
            {
                
                if(isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident') && $requestData->vehicle_owner_type == 'I')
                {
                    $ispacoverownerdriver = 'true';
                    $PACoverTenure = 1;
                    $PACoverTenure = isset($value['tenure'])? $value['tenure'] :$PACoverTenure;
                }
                else
                {
                    $ispacoverownerdriver = 'false';
                }
             }
        }
        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage")
        {
            if ($requestData->business_type == 'newbusiness')
            {
                $PACoverTenure = isset($PACoverTenure) ? $PACoverTenure : '3';
            }
            else
            {
                $PACoverTenure = isset($PACoverTenure) ? $PACoverTenure : '1';
            }
        }             
        

        $IsVehicleHaveCNG = 'false';
        $IsVehicleHaveLPG = 'false';
        $SIVehicleHaveLPG_CNG = 0;
        $SIHaveElectricalAccessories = 0;
        $SIHaveNonElectricalAccessories = 0;
        $IsPACoverUnnamedPassenger = 'false';
        $SIPACoverUnnamedPassenger = 0;
        $IsLLPaidDriver = false;
        $tppd_limit = 750000;
        $IsLLPaidEmployee = false;
        $geoExtension = false;
        $extensionCountryName = '';

        if(isset($mmv_data->fyntune_version['fuel_type']) && $mmv_data->fyntune_version['fuel_type'] == 'CNG')
        {
            $bifuel_type = 'CNG';
            $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
            $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
            $SIVehicleHaveLPG_CNG = 0;
        }else if(isset($mmv_data->fyntune_version['fuel_type']) && $mmv_data->fyntune_version['fuel_type'] == 'LPG')
        {
            $bifuel_type = 'LPG';
            $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
            $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
            $SIVehicleHaveLPG_CNG = 0;
        }
        if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
        {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if($value['name'] == 'Electrical Accessories')
                {
                    $SIHaveElectricalAccessories = $value['sumInsured'];
                }
                else if($value['name'] == 'Non-Electrical Accessories')
                {
                    $SIHaveNonElectricalAccessories = $value['sumInsured'];
                }
                else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                {
                    $bifuel_type = 'CNG';
                    $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                    $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                    $SIVehicleHaveLPG_CNG = $value['sumInsured'];
                }
            }
        }
        if(isset($mmv_data->fyntune_version['fuel_type']) && ($mmv_data->fyntune_version['fuel_type'] == 'CNG' || $mmv_data->fyntune_version['fuel_type'] == 'LPG')){
            $SIVehicleHaveLPG_CNG = ($premium_type == 'third_party' && $car_age > 5 && $bifuel_type == 'CNG') ? 1 : 0;
        }

        if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
        {
            $additional_covers = $selected_addons->additional_covers;
            foreach ($additional_covers as $value) {
               if($value['name'] == 'Unnamed Passenger PA Cover')
               {
                    $IsPACoverUnnamedPassenger = 'true';
                    $SIPACoverUnnamedPassenger = ($value['sumInsured'] * $mmv_data->seating_capacity);
                    
               }
               if($value['name'] == 'LL paid driver')
               {
                    $IsLLPaidDriver = true;
               }
                if ($value['name'] == 'Geographical Extension') {
                    $geoExtension = true;
                    $geoExtensionCountryName = array_filter($value['countries'], fn($country) => $country !== false);
                    $extensionCountryName = !empty($geoExtensionCountryName) ? implode(', ', $geoExtensionCountryName) : 'No Extension';
                }
            }
        }

        if ($requestData->vehicle_owner_type == 'I') 
        {
                $customertype = 'INDIVIDUAL';         
        }
        else 
        {
            $customertype = 'CORPORATE';

        }
        $voluntary_deductible_amount = 0;

        if($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '')
        {
            $discounts_opted = $selected_addons->discounts;
            foreach ($discounts_opted as $value) {
               if($value['name'] == 'TPPD Cover')
               {
                    $tppd_limit = 6000;
               }
               if($value['name'] == 'voluntary_insurer_discounts')
               {
                   $voluntary_deductible_amount = $value['sumInsured'];
               }
            }
        }
   
       
        if($premium_type == 'own_damage') 
        {
            /*if(new DateTime(date('Y-m-d', strtotime($additional_data['prepolicy']['tpEndDate']))) < new DateTime($policy_end_date))
            {
                return
                [
                    'status' => false,
                    'message' => 'TP Policy Expiry Date should be greater than or equal to OD policy expiry date'
                ];
            }*/
            
                   
            $IsPACoverUnnamedPassenger = 'false';
            $SIPACoverUnnamedPassenger = 0;
            $IsLLPaidDriver = false;
            $tppd_limit = 750000;
            $ispacoverownerdriver = 'false';
             
        }

        $PreviousPolicy_IsZeroDept_Cover = false;
        if(!empty($proposal->previous_policy_addons_list))
        {
            $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
            foreach ($previous_policy_addons_list as $key => $value) {
               if($key == 'zeroDepreciation' && $value)
               {
                    $PreviousPolicy_IsZeroDept_Cover = true;  
               }
            }                
        }
        if(!empty($ZeroDepPlanName) && $PreviousPolicy_IsZeroDept_Cover == false && $requestData->business_type == 'rollover'){
            $breakingFlag = true;
        }

        if ($requestData->vehicle_owner_type == 'C') 
        {
                $IsLLPaidEmployee = true;         
        }
        // token Generation
         $additionData = 
         [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'car',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name
        ];
        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
            'scope' => 'esbmotor',
        ];


        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
       


        if (!empty($token)) 
        {
            $token = json_decode($token, true);
            
            if (isset($token['access_token'])) 
            {
                
                $access_token = $token['access_token'];

                $corelationId = getUUID($enquiryId);

                $isapprovalrequired = false;

                if(($requestData->business_type == 'breakin') && config('constants.IcConstants.icici_lombard.IS_TYRE_PROTECT_DISABLED_FOR_BREAKIN') == 'Y'){
                    $tyreProtect = false;
                }

                $model_config_premium = [
                    'BusinessType' => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
                    'CustomerType' => $customertype,
                    'PolicyStartDate' => $policy_start_date,
                    'PolicyEndDate' => $policy_end_date,
                    'VehicleMakeCode' => $mmv_data->manufacturer_code,
                    'VehicleModelCode' => $mmv_data->model_code,
                    'RTOLocationCode' =>$rto_data->txt_rto_location_code,
                    'ManufacturingYear' =>  date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'DeliveryOrRegistrationDate' => date('Y-m-d',strtotime($vehicleDate)),
                    'FirstRegistrationDate' => $first_reg_date,
                    'ExShowRoomPrice' => isset($quote_log_premium_json['showroomPrice']) ? $quote_log_premium_json['showroomPrice'] : '0', 
                    "Tenure" => $Tenure,
                    "TPTenure" => $TPTenure,
                    'IsValidDrivingLicense' => false,
                    'IsMoreThanOneVehicle' => false,
                    'IsNoPrevInsurance' => $isnoprevinsurance,
                    'IsTransferOfNCB' => false,
                    'TransferOfNCBPercent' => 0,    //($applicable_ncb == '') ? 0 : $applicable_ncb, //0,
                    'IsLegalLiabilityToPaidDriver' => $IsLLPaidDriver,
                    'IsPACoverOwnerDriver' => $ispacoverownerdriver,
                    'isPACoverWaiver'      => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                    // 'PACoverTenure'        => 1,
                    'PACoverTenure'        => $PACoverTenure,
                    'IsVehicleHaveLPG' => $IsVehicleHaveLPG,
                    'IsVehicleHaveCNG' => $IsVehicleHaveCNG,
                    'SIVehicleHaveLPG_CNG' => $SIVehicleHaveLPG_CNG,
                    'TPPDLimit' => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                    'SIHaveElectricalAccessories' => $SIHaveElectricalAccessories,
                    'SIHaveNonElectricalAccessories' => $SIHaveNonElectricalAccessories,
                    'IsPACoverUnnamedPassenger' => $IsPACoverUnnamedPassenger,
                    'SIPACoverUnnamedPassenger' => $SIPACoverUnnamedPassenger,
                    'IsLegalLiabilityToPaidEmployee' => $IsLLPaidEmployee,
                    'NoOfEmployee' => $mmv_data->seating_capacity,
                    'IsLegaLiabilityToWorkmen' => false,
                    'IsTyreProtect' => $tyreProtect,
                    'NoOfWorkmen' => 0,
                    'IsFiberGlassFuelTank' => false,
                    'IsVoluntaryDeductible' => ($voluntary_deductible_amount != 0) ? false : false,
                    'VoluntaryDeductiblePlanName' => ($voluntary_deductible_amount != 0) ? 0 : 0,
                    'IsAutomobileAssocnFlag' => false,
                    'AutomobileAssociationNumber' => '', //
                    'IsAntiTheftDisc' => false,
                    'IsHandicapDisc' => false,
                    'IsExtensionCountry' => $geoExtension,
                    'ExtensionCountryName' => $extensionCountryName,
                    'IsGarageCash' => false,
                    'GarageCashPlanName' => 4,
                    'ZeroDepPlanName' => ($productData->zero_dep == '0') ? $ZeroDepPlanName : '',
                    'IsEngineProtectPlus' =>$IsEngineProtectPlus,
                    'KeyProtectPlan' => $KeyProtectPlan,
                    // 'RSAPlanName' => $RSAPlanName,
                    'IsConsumables' => $IsConsumables,
                    'LossOfPersonalBelongingPlanName' => $LossOfPersonalBelongingPlanName,
                    'IsRTIApplicableflag' => $IsRTIApplicableflag,
                    'IsApprovalRequired' => $isapprovalrequired,
                    'ProposalStatus' => NULL,
                    'OtherLoading' => 0,
                    'OtherDiscount' => 0,
                    'GSTToState' => $state_name,
                    'CorrelationId' => $corelationId,
                    'FinancierName' => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : '',
                    'BranchName' => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : '',
                    'AgreementType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type :'',
                    'SmartAssistPlanName'             => $ILSmartAssistPlanName
                    
                ];
                 
                  
                if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') 
                {
                    $model_config_premium['PreviousPolicyDetails'] = $PreviousPolicyDetails;
                } 
                else 
                {
                    $model_config_premium['Tenure'] = 1;
                    $model_config_premium['TPTenure'] = 3;
                    $model_config_premium['PACoverTenure'] = $PACoverTenure; #3
                }
                if($premium_type == 'own_damage') 
                {
                    if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                        $model_config_premium['TPStartDate'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                        $model_config_premium['TPEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                        $model_config_premium['TPInsurerName'] =  $proposal->tp_insurance_company;
                        $model_config_premium['TPPolicyNo'] = $proposal->tp_insurance_number;
                        $model_config_premium['PreviousPolicyDetails']['PreviousPolicyType'] = 'Bundled Package Policy';
                    }
                    $model_config_premium['Tenure'] = 1;
                    $model_config_premium['TPTenure'] = 0;
                    
                    $model_config_premium['IsLegalLiabilityToPaidDriver'] = false;
                    $model_config_premium['IsPACoverOwnerDriver'] = false;
                    $model_config_premium['IsPACoverUnnamedPassenger'] = false;
                }
                if($premium_type == 'own_damage' && $requestData->previous_policy_type == 'Third-party')
                {
                    unset($model_config_premium['PreviousPolicyDetails']);
                }
                if($eme_cover)
                {
                    $model_config_premium['EMECover'] = $eme_Plan_name;
                    $model_config_premium['NoOfPassengerHC'] = $mmv_data->seating_capacity - 1;
                }
                #adding product code
                $ProductCode = '';
                
                if($IsPos == 'N')
                {
                    switch($premium_type)
                    {
                        case "comprehensive":
                             $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
                             $ProductCode = '2311';
                        break;
                        case "own_damage":
                            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');
                            $ProductCode = '2311';

                        break;
                        case "third_party":
                            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
                            $ProductCode = '2319';
                        break;
                    }
                    if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
                    {
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');
                    }

                    #for third party 
                    if (($premium_type_array->premium_type_code ?? '') == 'third_party') {

                        $ProductCode = '2319';
                    }
                }
                
                //query for fetching POS details
                $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = '';
                $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                $is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
                $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_ICICI');

                $pos_data = DB::table('cv_agent_mappings')
                    ->where('user_product_journey_id',$enquiryId)
                    ->where('user_proposal_id',$proposal->user_proposal_id)
                    ->where('seller_type','P')
                    ->first();
                        
                if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log->idv <= 5000000)
                {

                    if($pos_data)
                    {
                        $IsPos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                        $CertificateNumber = $pos_data->unique_number;#$pos_data->user_name;
                        $PanCardNo = $pos_data->pan_no;
                        $AadhaarNo = $pos_data->aadhar_no;
                    }

                    if($pos_testing_mode === 'Y')
                    {
                        $IsPos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                        $CertificateNumber = 'TMI0001';
                        $PanCardNo = 'ABGTY8890Z';
                        $AadhaarNo = '569278616999';
                    }

                    if(config('ICICI_LOMBARD_IS_NON_POS') == 'Y')
                    {
                        $IsPos = 'N';
                        $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = $ProductCode = '';
                        $model_config_premium['DealId'] = $deal_id;
                    }
                    //$ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');
                }
                elseif($pos_testing_mode === 'Y' && $quote_log->idv <= 5000000)
                {
                    $IsPos = 'Y';
                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                    $CertificateNumber = 'TMI0001';
                    $PanCardNo = 'ABGTY8890Z';
                    $AadhaarNo = '569278616999';
                    //$ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');
                }
                else
                {
                    $model_config_premium['DealId'] = $deal_id;
                }

                if ($premium_type == 'third_party') 
                {
                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR_TP');
                }
                else
                {
                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR');
                }

                if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') 
                {
                    unset($model_config_premium['PreviousPolicyDetails']);
                }

                $additionPremData = [
                    'requestMethod' => 'post',
                    'type' => 'premiumCalculation',
                    'section' => 'car',
                    'token' => $access_token,
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'proposal',
                    'productName'  => $productData->product_name
                ];

                if($IsPos == 'Y')
                {
                    if(isset($model_config_premium['DealId']))
                    {
                        unset($model_config_premium['DealId']);
                    }
                }
                else
                {
                    if(!isset($model_config_premium['DealId']))
                    {
                       $model_config_premium['DealId'] = $deal_id;
                    }
                }

                if($IsPos == 'Y')
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
                if($productData->good_driver_discount=="Yes" && $isGddEnabled)
                {
                    $breakingFlag = true;
                    $randomNumber=0;
                    $sel_payud=$quote_log_premium_json['payAsYouDrive'];
                    foreach($sel_payud as $sel_key=> $sel_value)
                    {
                        if($sel_value['isOptedByCustomer']==true)
                        {
                            $sel_payud_distance=$sel_value['initialPlan'];
                        }
                        else
                        {
                            $sel_payud_distance="000";
                        }
                    }
                    $payud_Arr['0']['InitialPlan']="5000";
                    $payud_Arr['0']['OdometerCaptureDate']="2023-02-01T00:00:00";
                    $payud_Arr['0']['OdometerReading']=34535;
                    $payud_Arr['1']['InitialPlan']="7500";
                    $payud_Arr['1']['OdometerCaptureDate']="2023-02-01T00:00:00";
                    $payud_Arr['1']['OdometerReading']=54535;
                    foreach($payud_Arr as $payu_key => $payu_value)
                    {
                        if($payu_value['InitialPlan']==$sel_payud_distance)
                        {
                            $randomNumber=$payu_key;
                            break;

                        }
                        else
                        {

                            // dd($randomNumber);
                        }

                    }
                    $payud_initialplan=$payud_Arr[$randomNumber]['InitialPlan'];
                    $payud_odometercapturedate=$payud_Arr[$randomNumber]['OdometerCaptureDate'];
                    $payud_odometerreading=$payud_Arr[$randomNumber]['OdometerReading'];
                    $model_config_premium['IsPAYU']="true";
                    $model_config_premium['PAYUDetails']['InitialPlan']=$payud_initialplan;
                    $model_config_premium['PAYUDetails']['OdometerCaptureDate']=$payud_odometercapturedate;
                    $model_config_premium['PAYUDetails']['OdometerReading']=$payud_odometerreading;
                    $model_config_premium['IsProposal']='true';
                    $model_config_premium['IsQuote']='false';
                    if($BusinessType=='New Business' )
                    {
                        $model_config_premium['IsBreakignFlag']='false';
                    }
                }
                // hitting premium calculation getting response
                $get_response = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
                $prem_calc_web_id = $get_response['webservice_id'];

                $data = $get_response['response'];
               
                if (!empty($data)) 
                {
                    $premiumResponse = json_decode($data, true);
                    if(!isset($premiumResponse['status']))
                    {
                        return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $premiumResponse
                        ]);

                    }
                    if ($premiumResponse['status'] == 'Success') 
                    {
                        $msg = '';
                        if (isset($premiumResponse['isQuoteDeviation']) && ($premiumResponse['isQuoteDeviation'] == true)) 
                        {
                            $msg = "Ex-Showroom price provided is not under permissable limits";
                            
                        }

                        if (isset($premiumResponse['breakingFlag']) && isset($premiumResponse['isApprovalRequired']) && ($premiumResponse['breakingFlag'] == false) && ($premiumResponse['isApprovalRequired'] == true)) {
                            $msg = "Proposal application didn't pass underwriter approval";
                           
                        }

                        if(empty($msg))
                        { 
                            $address_data = [
                                'address' => $proposal->address_line1,
                                'address_1_limit'   => 79,
                                'address_2_limit'   => 79            
                            ];
                
                            $getAddress = getAddress($address_data);
                            

                            //  * generating request for proposal submit
                        $proposal_array = 
                        [
                            'BusinessType' => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
                            'CustomerType' => $customertype,
                            'PolicyStartDate' => $policy_start_date,
                            'PolicyEndDate' => $policy_end_date,
                            'VehicleMakeCode' => $mmv_data->manufacturer_code,
                            'VehicleModelCode' => $mmv_data->model_code,
                            'Tenure' => $Tenure,
                            'TPTenure' => $TPTenure,
                            'RTOLocationCode' => $rto_data->txt_rto_location_code,
                            'ManufacturingYear' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            'DeliveryOrRegistrationDate' => date('Y-m-d',strtotime($vehicleDate)),
                            // 'RegistrationNumber' => ($requestData->business_type == 'newbusiness') ? 'New' : str_replace('-', '', $proposal->vehicale_registration_number),
                            'RegistrationNumber' => strtoupper($vehicale_registration_number),
                            'ExShowRoomPrice' => $premiumResponse['generalInformation']['showRoomPrice'],
                            'IsTransferOfNCB' => false,
                            'TransferOfNCBPercent' => 0,
                            'IsNoPrevInsurance' => $isnoprevinsurance,
                            'IsVehicleHaveLPG' => $IsVehicleHaveLPG,
                            'IsVehicleHaveCNG' => $IsVehicleHaveCNG,
                            'SIVehicleHaveLPG_CNG' => $SIVehicleHaveLPG_CNG,
                            'TPPDLimit' => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                            'SIHaveElectricalAccessories' => $SIHaveElectricalAccessories,
                            'SIHaveNonElectricalAccessories' => $SIHaveNonElectricalAccessories,
                            'IsPACoverUnnamedPassenger' => $IsPACoverUnnamedPassenger,
                            'SIPACoverUnnamedPassenger' => $SIPACoverUnnamedPassenger,
                            'EngineNumber' => $proposal->engine_number,
                            'ChassisNumber' => $proposal->chassis_number,
                            'CustomerDetails' => [
                                'CustomerType' => $customertype,
                                'CustomerName' =>$requestData->vehicle_owner_type == 'I'? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name,
                                'DateOfBirth' => $requestData->vehicle_owner_type == 'I'?date('Y-m-d', strtotime($proposal->dob)):'',
                                'PinCode' => $proposal->pincode,
                                'PANCardNo' => ($proposal->pan_number != '') ? $proposal->pan_number : '',
                                'Email' => $proposal->email,
                                'MobileNumber' => $proposal->mobile_number,
                                'AddressLine1' => $getAddress['address_1'].''.$getAddress['address_2'],//$proposal->address_line1 . ' ' . $proposal->address_line2 . ' '. $proposal->address_line3,
                                'CountryCode' => $countrycode,
                                'StateCode' => $additional_details->owner->stateId,
                                'CityCode' => $additional_details->owner->cityId,
                            ],
                            'IsLegalLiabilityToPaidDriver' => $IsLLPaidDriver,
                            'IsPACoverOwnerDriver' => $ispacoverownerdriver,
                            'isPACoverWaiver'      => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                            // 'PACoverTenure'        => 1,
                            'PACoverTenure'        => $PACoverTenure,
                            'CorrelationId' => $corelationId,
                            'FirstRegistrationDate' => $first_reg_date,
                            'IsValidDrivingLicense' => false,
                            'IsMoreThanOneVehicle' => false,
                            'IsLegalLiabilityToPaidEmployee' => $IsLLPaidEmployee,
                            'NoOfEmployee' => $mmv_data->seating_capacity,
                            'IsLegaLiabilityToWorkmen' => false,
                            'NoOfWorkmen' => 0,
                            'IsFiberGlassFuelTank' => false,
                            'IsVoluntaryDeductible' => ($voluntary_deductible_amount != 0) ? false : false,
                            'VoluntaryDeductiblePlanName' => ($voluntary_deductible_amount != 0) ? 0 : 0,
                            'IsAutomobileAssocnFlag' => false,
                            'IsAntiTheftDisc' => false,
                            'IsHandicapDisc' => false,
                            'IsExtensionCountry' => $geoExtension,
                            'ExtensionCountryName' => $extensionCountryName,
                            'IsGarageCash' => false,
                            'GarageCashPlanName' => 4,
                            'ZeroDepPlanName' => ($productData->zero_dep == '0') ? $ZeroDepPlanName : '',
                            // 'RSAPlanName' => $RSAPlanName,
                            'IsEngineProtectPlus' => $IsEngineProtectPlus, //added engine_protector
                            'KeyProtectPlan' => $KeyProtectPlan, //added 
                            'IsConsumables' => $IsConsumables,
                            'LossOfPersonalBelongingPlanName' => $LossOfPersonalBelongingPlanName,
                            'IsRTIApplicableflag' => $IsRTIApplicableflag,
                            'IsTyreProtect' => $tyreProtect,
                            'OtherLoading' => 0,
                            'OtherDiscount' => 0,
                            'GSTToState' => $state_name,
                            'SmartAssistPlanName'             => $ILSmartAssistPlanName,
                        ];

                        if($IsPos == 'N')
                        {
                            $proposal_array['DealId'] = $deal_id;
                        }
                       
                        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') 
                        {
                            $proposal_array['PreviousPolicyDetails'] = $PreviousPolicyDetails;
                        } else {
                            $proposal_array['Tenure'] = 1;
                            $proposal_array['TPTenure'] = 3;
                            $proposal_array['PACoverTenure'] = $PACoverTenure;#3
                        }

                        if ($proposal->gst_number != '') {
                            $proposal_array['CustomerDetails']['GSTDetails']['GSTExemptionApplicable'] = 'Yes';
                            $proposal_array['CustomerDetails']['GSTDetails']['GSTInNumber'] = $proposal->gst_number;
                            $proposal_array['CustomerDetails']['GSTDetails']['GSTToState'] = $state_name;
                        }

                        if(config('constants.IS_CKYC_ENABLED') == 'Y') {
                            $proposal_array['CustomerDetails']['CKYCID'] = $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number;
                            $proposal_array['CustomerDetails']['EKYCid'] = null;
                            $proposal_array['CustomerDetails']['ilkycReferenceNumber'] = $proposal->ckyc_reference_id;
                            if($proposal->is_car_registration_address_same == '0') {
                                $proposal_array['CustomerDetails']['correspondingAddress'] = [
                                    'AddressLine1' => implode(' ',[$proposal->car_registration_address1, $proposal->car_registration_address2, $proposal->car_registration_address3]),
                                    'AddressLine2' => implode(' ',[$proposal->car_registration_address1, $proposal->car_registration_address2, $proposal->car_registration_address3]),
                                    'CountryCode' => $countrycode,
                                    'Statecode' => $proposal->car_registration_state_id,
                                    'CityCode' => $proposal->car_registration_city_id,
                                    'Pincode' => $proposal->car_registration_pincode,
                                ];
                            }
                            //$proposal_array['CustomerDetails']['SkipDedupeLogic'] = ;
                        }

                        if($ispacoverownerdriver == 'true')
                        {
                             $proposal_array['NomineeDetails']  = [
                                'NomineeType'               => 'PA-Owner Driver', 
                                'NameOfNominee'             => $proposal->nominee_name , 
                                'Age'                       => get_date_diff('year',$proposal->nominee_dob),
                                'Relationship'              => $proposal->nominee_relationship
                            ];
                        }
                       
                        if($premium_type == 'own_damage')
                        {
                            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                                $proposal_array['TPStartDate'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                                $proposal_array['TPEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                                $proposal_array['TPInsurerName'] = $proposal->tp_insurance_company;
                                $proposal_array['TPPolicyNo'] = $proposal->tp_insurance_number;
                                $proposal_array['PreviousPolicyDetails']['PreviousPolicyType'] = 'Bundled Package Policy';
                            }
                            $proposal_array['Tenure'] = 1;
                            $proposal_array['TPTenure'] = 0;
                        }
                        if($premium_type == 'own_damage' && $requestData->previous_policy_type == 'Third-party')
                        {
                            unset($proposal_array['PreviousPolicyDetails']);
                        }
                        
                        if($eme_cover)
                        {
                            $proposal_array['EMECover'] = $eme_Plan_name;
                            $proposal_array['NoOfPassengerHC'] = $mmv_data->seating_capacity - 1;
                        }
                        
                        if($premium_type == 'third_party')
                        {
                            $url = config('constants.IcConstants.icici_lombard.PROPOSAL_END_POINT_URL_ICICI_LOMBARD_MOTOR_TP');
                        }
                        else
                        {
                            $url = config('constants.IcConstants.icici_lombard.PROPOSAL_END_POINT_URL_ICICI_LOMBARD_MOTOR'); 
                        }
                       
                        $additionPremData = [
                            'requestMethod' => 'post',
                            'type' => 'proposalService',
                            'section' => 'car',
                            'token' => $access_token,
                            'enquiryId' => $enquiryId,
                            'transaction_type' => 'proposal',
                            'productName'  => $productData->product_name
                        ];

                        if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') 
                        {
                            unset($proposal_array['PreviousPolicyDetails']);
                        }

                        if($IsPos == 'Y')
                        {
                            if(isset($proposal_array['DealId']))
                            {
                                unset($proposal_array['DealId']);
                            }
                        }
                        else
                        {
                            if(!isset($proposal_array['DealId']))
                            {
                               $proposal_array['DealId'] = $deal_id;
                            }
                        }
                        
                        if($IsPos == 'Y')
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
                        if ($proposal->is_vehicle_finance == '1') {
                            $proposal_array["FinancierDetails"] = [
                                "FinancierName" => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : "",
                                "BranchName" => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : "",
                                "AgreementType" => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : ""
                            ];
                        }
                        if($productData->good_driver_discount=="Yes" && $isGddEnabled)
                        {
                            $proposal_array['IsPAYU']=true;
                            $proposal_array['PAYUDetails']['InitialPlan']=$payud_initialplan;
                            $proposal_array['PAYUDetails']['OdometerCaptureDate']=$payud_odometercapturedate;
                            $proposal_array['PAYUDetails']['OdometerReading']=$payud_odometerreading;
                        }
                        // hitting the proposal service and getting response
                        $get_response = getWsData($url, $proposal_array, 'icici_lombard', $additionPremData);
                        $proposalServiceResponse = $get_response['response'];
                        
                    

                     
                       $proposal_request = json_encode($proposal_array);

                       UserProposal::where('user_product_journey_id', $request['userProductJourneyId'])
                       ->where('user_proposal_id', $proposal->user_proposal_id)
                       ->update([
                           'additional_details_data' => $proposal_request
               
                       ]);
                    

                        if (!empty($proposalServiceResponse)) 
                        {
                            $arr_premium = json_decode($proposalServiceResponse, true);

                            if(!isset($arr_premium['status']))
                            {
                                return response()->json([
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => $arr_premium
                                ]);
                            }

                            $msg = '';
                           
                            if ($arr_premium['status'] == 'Success') 
                            {
                                
                                if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true)) 
                                {
                                    $msg = "Ex-Showroom price provided is not under permissable limits";
                                    return response()->json([
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => $msg
                                    ]);
                                }

                                if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true)) {
                                    $msg = "Proposal application didn't pass underwriter approval";
                                    return response()->json([
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => $msg
                                    ]);
                                }
                                if($breakingFlag == true && $PreviousPolicy_IsZeroDept_Cover == false && $productData->zero_dep == 0)
                                    {
                                        $arr_premium['breakingFlag'] = true;
                                    }

                                if(isset($arr_premium['breakingFlag']))
                                {
                                    if($breakingFlag == true && $arr_premium['breakingFlag'] == false && $isInspectionWaivedOff!=true)
                                    {
                                        $msg = 'Breakin Flag must be True in Breakin Case';
                                        return response()->json([
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => $msg
                                        ]);
                                        
                                    }
                                    elseif($breakingFlag == false && $arr_premium['breakingFlag'] == true && ($requestData->ownership_changed == 'N'))
                                    {
                                        $msg = 'Breakin Flag must be False in Non Breakin Case';
                                        return response()->json([
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => $msg
                                        ]);
                                    }
                                }

                                $od_premium = 0;
                                $breakingLoadingAmt =0;
                                $automobile_assoc = 0;
                                $anti_theft = 0;
                                $voluntary_deductible = 0;
                                $elect_acc = 0; 
                                $non_elec_acc = 0; 
                                $lpg_cng_od = 0;
                                $lpg_cng_tp = 0;
                                $tp_premium = 0;
                                $llpd_amt = 0;
                                $ncb_discount = 0;
                                $unnamed_pa_amt = 0;
                                $zero_dept = 0;
                                $tppd_discount = 0;
                                $llpdemp_amt = 0;
                                $rsa = $zero_dept = $eng_protect = $key_replace = $consumable_cover = $return_to_invoice = $loss_belongings = $cpa_cover = $eme_cover_amount = 0 ;
                                $total_od = $total_tp = $total_discount = $basePremium = $totalTax = $final_premium = $addon_premium = 0;
                                $geog_Extension_OD_Premium = $geog_Extension_TP_Premium = 0;
            
                                $geog_Extension_OD_Premium = isset($arr_premium['riskDetails']['geographicalExtensionOD'])  ? ($arr_premium['riskDetails']['geographicalExtensionOD']) : '0';
                                $geog_Extension_TP_Premium = isset($arr_premium['riskDetails']['geographicalExtensionTP'])  ? ($arr_premium['riskDetails']['geographicalExtensionTP']) : '0';
                                $od_premium = $arr_premium['totalOwnDamagePremium'] ?? 0;
                                $basicOD = $arr_premium['riskDetails']['basicOD'] ?? 0;
                                $basicTP = $arr_premium['riskDetails']['basicTP'] ?? 0;
                                $breakingLoadingAmt = isset($arr_premium['riskDetails']['breakinLoadingAmount']) ? $arr_premium['riskDetails']['breakinLoadingAmount'] : '0';
                                $automobile_assoc = isset($arr_premium['riskDetails']['automobileAssociationDiscount']) ? ($arr_premium['riskDetails']['automobileAssociationDiscount']) : '0';
                                $anti_theft =  isset($arr_premium['riskDetails']['antiTheftDiscount']) ? ($arr_premium['riskDetails']['antiTheftDiscount']) : '0';
                                $elect_acc = isset($arr_premium['riskDetails']['electricalAccessories']) ? ($arr_premium['riskDetails']['electricalAccessories']) : '0';
                                $non_elec_acc = isset($arr_premium['riskDetails']['nonElectricalAccessories']) ? ($arr_premium['riskDetails']['nonElectricalAccessories']) : '0';
                                $lpg_cng_od = isset($arr_premium['riskDetails']['biFuelKitOD']) ? ($arr_premium['riskDetails']['biFuelKitOD']) : '0';
                                $ncb_discount = isset($arr_premium['riskDetails']['bonusDiscount']) ? $arr_premium['riskDetails']['bonusDiscount'] : '0';
                                $tppd_discount = isset($arr_premium['riskDetails']['tppD_Discount']) ? $arr_premium['riskDetails']['tppD_Discount'] : '0';

                                $tyre_secure = ($arr_premium['riskDetails']['tyreProtect'] ?? 0);
                                $tp_premium = (isset($arr_premium['totalLiabilityPremium']) ?$arr_premium['totalLiabilityPremium'] : '0');
                                $lpg_cng_tp = isset($arr_premium['riskDetails']['biFuelKitTP']) ? ($arr_premium['riskDetails']['biFuelKitTP']) : '0';
                                $llpd_amt = isset($arr_premium['riskDetails']['paidDriver']) ? ($arr_premium['riskDetails']['paidDriver']) : '0' ;
                                $unnamed_pa_amt = isset($arr_premium['riskDetails']['paCoverForUnNamedPassenger']) ? $arr_premium['riskDetails']['paCoverForUnNamedPassenger'] : '0';
                                // $rsa = isset($arr_premium['riskDetails']['roadSideAssistance']) ? $arr_premium['riskDetails']['roadSideAssistance'] : '0';
                                $rsa = isset($arr_premium['riskDetails']['smartAssist']) ? $arr_premium['riskDetails']['smartAssist'] : '0';
                                $zero_dept = isset($arr_premium['riskDetails']['zeroDepreciation']) ? $arr_premium['riskDetails']['zeroDepreciation'] : '0';
                                $eng_protect = isset($arr_premium['riskDetails']['engineProtect']) ? $arr_premium['riskDetails']['engineProtect'] : '0';
                                $key_replace = isset($arr_premium['riskDetails']['keyProtect']) ? $arr_premium['riskDetails']['keyProtect'] : '0';
                                $consumable_cover = isset($arr_premium['riskDetails']['consumables']) ? $arr_premium['riskDetails']['consumables'] : '0';
                                $return_to_invoice = isset($arr_premium['riskDetails']['returnToInvoice']) ? $arr_premium['riskDetails']['returnToInvoice'] : '0';
                                $loss_belongings = isset($arr_premium['riskDetails']['lossOfPersonalBelongings']) ? $arr_premium['riskDetails']['lossOfPersonalBelongings'] : '0';
                                $cpa_cover = isset($arr_premium['riskDetails']['paCoverForOwnerDriver']) ? $arr_premium['riskDetails']['paCoverForOwnerDriver'] : '0';
                                $eme_cover_amount = isset($arr_premium['riskDetails']['emeCover']) ? $arr_premium['riskDetails']['emeCover'] : 0;
                                $llpdemp_amt = $arr_premium['riskDetails']['employeesOfInsured'] ?? 0;

                                if(isset($arr_premium['riskDetails']['voluntaryDiscount']))
                                {
                                    $voluntary_deductible = $arr_premium['riskDetails']['voluntaryDiscount'];
                                }
                                else
                                {
                                    $voluntary_deductible = voluntary_deductible_calculation($od_premium,$requestData->voluntary_excess_value,'car');
                                }
                                if(isset($arr_premium['payuDetails']['discount']))
                                {
                                    $gdd_discount=$arr_premium['payuDetails']['discount'];
                                }
                                else
                                {
                                    $gdd_discount=0;
                                }
                                // ! premium calculation
                                $total_od = $basicOD + $breakingLoadingAmt + $elect_acc + $non_elec_acc + $lpg_cng_od + $geog_Extension_OD_Premium;
                                $total_tp = $basicTP + $llpd_amt + $unnamed_pa_amt + $lpg_cng_tp + $cpa_cover + $llpdemp_amt + $geog_Extension_TP_Premium;

                                
                

                                $addon_premium = $rsa + $zero_dept + $eng_protect + $key_replace + $consumable_cover + $return_to_invoice + $loss_belongings + $eme_cover_amount + $tyre_secure;
                                $total_discount = $ncb_discount + $automobile_assoc + $anti_theft + $voluntary_deductible ;
                                $total_discount=$total_discount+$gdd_discount;

                                //$basePremium = $total_od + $total_tp - $total_discount;

                                if($premium_type == 'third_party')
                                {
                                    $basePremium = $arr_premium['totalLiabilityPremium'];
                                }
                                else
                                {
                                    $basePremium = $arr_premium['packagePremium'];
                                }
                                
                                $totalTax = $arr_premium['totalTax'];
                                $final_premium = $arr_premium['finalPremium'];
                                $is_breakin_case = 'N';

                                updateJourneyStage([
                                        'user_product_journey_id' => $request['userProductJourneyId'],
                                        'ic_id' => $productData->company_id,
                                        'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                        'proposal_id' => $proposal->user_proposal_id
                                    ]);
                                
                                $vehDetails['manf_name'] = $mmv_data->manufacturer_name;
                                $vehDetails['model_name'] = $mmv_data->model_name;
                                $vehDetails['version_name'] = $mmv_data->model_name;
                                $vehDetails['version_id'] = $mmv_data->model_code;
                                $vehDetails['seating_capacity'] = $mmv_data->seating_capacity;
                                $vehDetails['cubic_capacity'] = $mmv_data->cubic_capacity;
                                $vehDetails['fuel_type'] = $mmv_data->fuel_type;
                                $vehDetails['car_segment'] = $mmv_data->car_segment;

                                $cpa_end_date = '';
                                if($ispacoverownerdriver == 'true' && $requestData->business_type == 'newbusiness')
                                {
                                    $cpa_end_date=date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date)));
                                }
                                else if($ispacoverownerdriver == 'true' && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' ) {
                                    $cpa_end_date =date('d-m-Y',strtotime($policy_end_date));
                                }

                                if(!$arr_premium['generalInformation']['proposalNumber']) {
                                    return response()->json([
                                        'status' => false,
                                        'message' => "Proposal number is null"
                                    ]);
                                }

                                IciciLombardPremiumDetailController::savePremiumDetails($prem_calc_web_id);
                                if ($isInspectionRequired && !$isInspectionWaivedOff) {
                                if($premium_type != 'third_party')
                                {
                                    // * breakin id generation
                                    if (($arr_premium['status'] == 'Success') && ($arr_premium['breakingFlag'] == '1' || $arr_premium['breakingFlag'] == true ) || ($productData->good_driver_discount=="Yes" && $isGddEnabled))
                                    {
                                        $is_breakin_case = 'Y';
                                        $inspection_type_self = strtoupper($proposal->inspection_type) == 'MANUAL' ? 'No' : 'Yes';

                                        $breakin_create_array = [
                                            'CorrelationId' => $corelationId,
                                            'BreakInType' => 'Break-in Policy lapse',
                                            'BreakInDays' => $breakin_days ?? 0,
                                            'CustomerName' =>$requestData->vehicle_owner_type == 'I'? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name,
                                            'CustomerAddress' => $proposal->address_line1.' '. $proposal->address_line2.' '.$proposal->address_line3,
                                            'State' => $proposal->state,
                                            'City' => $proposal->city,
                                            'MobileNumber' => $proposal->mobile_number,
                                            'TypeVehicle' => "PRIVATE CAR",
                                            'VehicleMake' => $mmv_data->manufacturer_name,
                                            'VehicleModel' => $mmv_data->model_name,
                                            'ManufactureYear' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                                            'RegistrationNo' => trim(str_replace('-', '', $proposal->vehicale_registration_number)),
                                            'EngineNo' => $proposal->engine_number,
                                            'ChassisNo' => $proposal->chassis_number,
                                            'SubLocation' => $proposal->city,
                                            'DistributorInterID' => '',
                                            'DistributorName' => 'Emmet',
                                            'InspectionType' => 'NEW',
                                            "selfInspection" => $inspection_type_self
                                            
                                        ];

                                        if($IsPos == 'Y')
                                        {
                                            if(isset($breakin_create_array['DealId']))
                                            {
                                                unset($breakin_create_array['DealId']);
                                            }
                                        }
                                        else
                                        {
                                            if(!isset($breakin_create_array['DealId']))
                                            {
                                               $breakin_create_array['DealId'] = $deal_id;
                                            }
                                        }

                                        $additionPremData = [
                                            'requestMethod' => 'post',
                                            'type' => 'Break-in Id Generation',
                                            'section' => 'car',
                                            'token' => $access_token,
                                            'enquiryId' => $enquiryId,
                                            'transaction_type' => 'proposal',
                                            'productName'  => $productData->product_name
                                        ];

                                        if($IsPos == 'Y')
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

                                        $url = config('constants.IcConstants.icici_lombard.GENERATE_BREAKINID_END_POINT_URL_ICICI_LOMBARD_MOTOR');
                                        
                                        $get_response = getWsData($url, $breakin_create_array, 'icici_lombard', $additionPremData);
                                        $breakin_data = $get_response['response'];
                                        
                                        $breakin_id_response = json_decode($breakin_data, true);
                                      
                                        if($breakin_id_response)
                                        {
                                            if($breakin_id_response['status'] == 'Success')
                                            {
                                                DB::table('cv_breakin_status')->insert([
                                                'user_proposal_id' => $proposal->user_proposal_id,
                                                'ic_id' => $productData->company_id,
                                                'breakin_number' =>  $breakin_id_response['brkId'],
                                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                                'breakin_response' => $breakin_data,
                                                'payment_end_date' => Carbon::today()->addDay(9)->toDateString(),
                                                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'updated_at' => date('Y-m-d H:i:s')
                                                ]);

                                                $updateProposal = UserProposal::where('user_product_journey_id', $request['userProductJourneyId'])
                                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                                    ->update([
                                                        'proposal_no' => trim($arr_premium['generalInformation']['proposalNumber']),
                                                        'customer_id' => $arr_premium['generalInformation']['customerId'],
                                                        'unique_proposal_id' => $corelationId,
                                                        'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                                                        'policy_end_date' =>  date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                                                        'od_premium' => ($total_od) - ($total_discount),
                                                        'tp_premium' => ($total_tp),
                                                        'addon_premium' => $addon_premium,
                                                        'cpa_premium' => $cpa_cover,
                                                        'service_tax_amount' => $totalTax,
                                                        'total_discount' => $total_discount + $tppd_discount,
                                                        'total_premium'  => $basePremium,
                                                        'final_payable_amount' => $final_premium,
                                                        'ic_vehicle_details' => json_encode($vehDetails),
                                                        // 'is_breakin_case' => (isset($arr_premium['breakingFlag']) && $arr_premium['breakingFlag'] == 'true' && $requestData->business_type == 'breakin') ? 'Y' : 'N',
                                                        'tp_start_date' => !empty($proposal->tp_start_date) ? date('d-m-Y', strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime($policy_start_date)),
                                                        'tp_end_date' => !empty($proposal->tp_end_date) ? date('d-m-Y', strtotime($proposal->tp_end_date)) : (($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y', strtotime($policy_end_date))),
                                                        'cpa_start_date' => (($ispacoverownerdriver == 'true') ? date('d-m-Y', strtotime($policy_start_date)) : ''),
                                                        'cpa_end_date'   => $cpa_end_date,
                                                        'is_cpa' => ($ispacoverownerdriver == 'true') ? 'Y' : 'N',
                                                        'is_breakin_case' => 'Y'
                                                    ]);

                                                updateJourneyStage([
                                                    'user_product_journey_id' => $enquiryId,
                                                    'ic_id' => $productData->company_id,
                                                    'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                                    'proposal_id' => $proposal->user_proposal_id,
                                                ]);
                                                
                                               
                                                return response()->json([
                                                    'status' => true,
                                                    'message' => "Proposal Submitted Successfully!",
                                                    'webservice_id' => $get_response['webservice_id'],
                                                    'table' => $get_response['table'],
                                                    'data' => [                            
                                                        'proposalId' => $proposal->user_proposal_id,
                                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                                        'proposalNo' => $corelationId,
                                                        'finalPayableAmount' => ($final_premium), 
                                                        'is_breakin' => $is_breakin_case,
                                                        'inspection_number' => $breakin_id_response['brkId'],
                                                    ]
                                                ]);
                                            }
                                            else
                                            {
                                                if(isset($breakin_id_response['message']))
                                                {
                                                    $contains = Str::contains($breakin_id_response['message'], 'Already on this combination Break-in ID : ');
                                                    if($contains)
                                                    { 
                                                        $breakin_id_array = explode(' ', $breakin_id_response['message']);
                                                        $breakin_id_response['brkId'] = $breakin_id_array[7];

                                                        return response()->json([
                                                            'status' => false,
                                                            'webservice_id' => $get_response['webservice_id'],
                                                            'table' => $get_response['table'],
                                                            'message' => $breakin_id_response['message'],
                                                        ]);

                                                         /* $breakinDetails = DB::table('cv_breakin_status')
                                                            ->where('user_proposal_id', '=', $proposal->user_proposal_id)
                                                            ->where('ic_id','=',$productData->company_id)
                                                            ->select('*')
                                                            ->first();
                                                        if($breakinDetails)
                                                        {   
                                                            $id_data = 
                                                            [
                                                                'ic_id' => $productData->company_id,
                                                                'breakin_number' =>  $breakin_id_response['brkId'],
                                                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                                                'breakin_response' => $breakin_data,
                                                                'payment_end_date' => Carbon::today()->addDay(3)->toDateString(),
                                                                'breakin_check_url' => env('APP_FRONTEND_URL').'motor/check-inspection-status',
                                                                'created_at' => date('Y-m-d H:i:s'),
                                                                'updated_at' => date('Y-m-d H:i:s')
                                                            ];
                                                            DB::table('cv_breakin_status')
                                                            ->where('user_proposal_id',$proposal->user_proposal_id)
                                                            ->update($id_data); 

                                                        }
                                                        else
                                                        {
                                                            
                                                            DB::table('cv_breakin_status')->insert([
                                                            'user_proposal_id' => $proposal->user_proposal_id,
                                                            'ic_id' => $productData->company_id,
                                                            'breakin_number' =>  $breakin_id_response['brkId'],
                                                            'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                                            'breakin_response' => $breakin_data,
                                                            'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                                            'payment_end_date' => Carbon::today()->addDay(3)->toDateString(),
                                                            'breakin_check_url' => env('APP_FRONTEND_URL').'motor/check-inspection-status',
                                                            'created_at' => date('Y-m-d H:i:s'),
                                                            'updated_at' => date('Y-m-d H:i:s')
                                                            ]);

                                                        }

                                                        
                                                        updateJourneyStage([
                                                                'user_product_journey_id' => $enquiryId,
                                                                'ic_id' => $productData->company_id,
                                                                'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                                                'proposal_id' => $proposal->user_proposal_id,
                                                            ]);
                                                            
                                                           
                                                        return response()->json([
                                                                'status' => true,
                                                                'message' => "Proposal Submitted Successfully!",
                                                                'data' => [                            
                                                                    'proposalId' => $proposal->user_proposal_id,
                                                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                                                    'proposalNo' => $corelationId,
                                                                    'finalPayableAmount' => ($final_premium), 
                                                                    'is_breakin' => $is_breakin_case,
                                                                    'inspection_number' => $breakin_id_response['brkId'],
                                                                ]
                                                            ]); */
                                                        
                                                    }
                                                    else
                                                    {
                                                        return response()->json([
                                                                'status' => false,
                                                                'webservice_id' => $get_response['webservice_id'],
                                                                'table' => $get_response['table'],
                                                                'message' => $breakin_id_response['message'],
                                                                'data' => [                            
                                                                    'proposalId' => $proposal->user_proposal_id,
                                                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                                                    'proposalNo' => $corelationId,
                                                                    'finalPayableAmount' => ($final_premium), 
                                                                
                                                                    
                                                                ]
                                                            ]);
                                                    }
                                                }
                                                else
                                                { 
                                                     return response()->json([
                                                        'status' => false,
                                                        'webservice_id' => $get_response['webservice_id'],
                                                        'table' => $get_response['table'],
                                                        'message' => "Error in breakin ID creation service"
                                                    ]);
                                                }
                                            }
                                        }
                                        else
                                        {
                                            return response()->json([
                                                'status' => false,
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'message' => "Error in breakin ID creation service"
                                            ]);
                                        }
                                    }
                                }
                            }

                                $updateProposal = UserProposal::where('user_product_journey_id', $request['userProductJourneyId'])
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'proposal_no' => trim($arr_premium['generalInformation']['proposalNumber']),
                                        'customer_id' => $arr_premium['generalInformation']['customerId'],
                                        'unique_proposal_id' => $corelationId,
                                        'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                                        'policy_end_date' =>  date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                                        'od_premium' => ($total_od) - ($total_discount),
                                        'tp_premium' => $tp_premium,
                                        'addon_premium' => $addon_premium,
                                        'cpa_premium' => $cpa_cover,
                                        'service_tax_amount' => $totalTax,
                                        'total_discount' => $total_discount + $tppd_discount,
                                        'total_premium'  => $basePremium,
                                        'final_payable_amount' => $final_premium,
                                        'ic_vehicle_details' => json_encode($vehDetails),
                                        'is_breakin_case' =>  (isset($arr_premium['breakingFlag']) && $arr_premium['breakingFlag'] == 'true' && $requestData->business_type == 'breakin' && $isInspectionRequired && !$isInspectionWaivedOff) ? 'Y' : 'N',
                                        'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime($policy_start_date)),
                                        'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y',strtotime($policy_end_date))),
                                        'cpa_start_date' => (($ispacoverownerdriver == 'true' ) ? date('d-m-Y',strtotime($policy_start_date)) :''),
                                        'cpa_end_date'   => $cpa_end_date,
                                        'is_cpa' => ($ispacoverownerdriver == 'true') ?'Y' : 'N',
                                    ]);

                                updateJourneyStage([
                                    'user_product_journey_id' => $request['userProductJourneyId'],
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                    'proposal_id' => $proposal->user_proposal_id
                                ]);

                                if ($updateProposal) 
                                {
                                    $proposal_data = UserProposal::find($proposal->user_proposal_id);
                    
                                    return response()->json([
                                        'status' => true,
                                        'message' => "Proposal Submitted Successfully!",
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'data' => [
                                            'proposalId' =>  $proposal->user_proposal_id,
                                            'userProductJourneyId' => $proposal_data->user_product_journey_id,
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
                            } 
                            elseif ($arr_premium['status'] == 'Failed') {
                                return response()->json([
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => isset($arr_premium['Message']) ? $arr_premium['Message'] : (isset($arr_premium['message']) ? $arr_premium['message'] : "Error Occured")
                                ]);
                            }

                        } else {
                            return response()->json([
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => "No response received from proposal service"
                            ]);
                        }

                      }
                      else
                      {
                        return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $msg,
                          ];

                      }
                    } 
                    elseif ($premiumResponse['status'] == 'Failed') {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => isset($premiumResponse['Message']) ? $premiumResponse['Message'] : (isset($premiumResponse['message']) ? $premiumResponse['message'] : "Error Occured")
                        ]);
                    }
                   } 

                else {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Error in Premium Calculation proposal service"
                    ]);
                }
           } 
           else 
           {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Error in token generation',
                ];
            }
        } 
        else {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => "No response received from Token Generation service"
            ]);
        }
    }
    
    public static function renewalSubmit($proposal, $request)
    {
        if(config('constant.ICICI_LOMBARD_CAR_NEW_RENEWAL_FLOW') == 'Y') {
            return self::newRenewalSubmit($proposal,$request);
          }
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $vehicleDetails = [
            'manf_name'             => $mmv->manufacturer_name,
            'model_name'            => $mmv->model_name,
            'version_name'          => '',
            'seating_capacity'      => ((int) $mmv->seating_capacity) - 1,
            'carrying_capacity'     => $mmv->seating_capacity,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel_type,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'CAR',
            'version_id'            => $mmv->ic_version_code,
        ];
        $additionData = [
            'requestMethod'     => 'post',
            'type'              => 'tokenGeneration',
            'section'           => 'CAR',
            'productName'       => $productData->product_name,
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'proposal'
        ];

        $tokenParam = [
            'grant_type'    => 'password',
            'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
            'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
            'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
            'scope'         => 'esbmotor',
        ];

//        $tokenParam = [
//            'grant_type'    => 'password',
//            'username'      => 'renewBuy',
//            'password'      => 'r3n3w&u4',
//            'client_id'     => 'ro.renewBuy',
//            'client_secret' => 'ro.r3n3w&u4',
//            'scope'         => 'esbmotor',
//        ];
        
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
//        print_r($token);
//        die;
//$token = '{"access_token":"eyJhbGciOiJSUzI1NiIsImtpZCI6IjZCN0FDQzUyMDMwNUJGREI0RjcyNTJEQUVCMjE3N0NDMDkxRkFBRTEiLCJ0eXAiOiJKV1QiLCJ4NXQiOiJhM3JNVWdNRnY5dFBjbExhNnlGM3pBa2ZxdUUifQ.eyJuYmYiOjE2NTA1MzQ4NjMsImV4cCI6MTY1MDUzODQ2MywiaXNzIjoiaHR0cHM6Ly9pbGVzYnNhbml0eS5pbnN1cmFuY2VhcnRpY2xlei5jb20vY2VyYmVydXMiLCJhdWQiOlsiaHR0cHM6Ly9pbGVzYnNhbml0eS5pbnN1cmFuY2VhcnRpY2xlei5jb20vY2VyYmVydXMvcmVzb3VyY2VzIiwiZXNibW90b3IiXSwiY2xpZW50X2lkIjoicm8ucmVuZXdCdXkiLCJzdWIiOiIwYzBmZTc2OC0zOTdkLTQ5OWQtYTdjZC0zOTZiYTk3ZDE3NDEiLCJhdXRoX3RpbWUiOjE2NTA1MzQ4NjMsImlkcCI6ImxvY2FsIiwic2NvcGUiOlsiZXNibW90b3IiXSwiYW1yIjpbImN1c3RvbSJdfQ.ojXDOW0b6Dj0Nnfnn-5ZrR_zYf6W-dsWg9VsUo0ClUIXZ-4fecrhn-zWk-GpxnIrHQcaP32hV0-3898qA-PfP4AOmCJzmUeop8VGO6cB7UCiexx5MqBTowH8EVncQcQooVkSMNqlwbUCCBD64pg2G0FRgKww4QisaHwq3JhiKaPs4sbK86mtqLq2-waxZsqfysUNF2iy87PbmG-Ue0KQgODqdFVcl6s2KvvlXTuW-bYImGmxDuN-o6dHKIlIU7jnUdzOnriQ7MTq3JqqyS8TPZU0gN4CPfActP43XZYEwVEspH0y6CMjiNpNXWfh_W3IztMd8LKXeuVyjfiIdC1LnA","expires_in":3600,"token_type":"Bearer"}';
        if (!empty($token))
        {
            $token = json_decode($token, true);

            if(isset($token['access_token']))
            {
                $access_token = $token['access_token'];
            }
            else
            {
                return [
                    'status' => false,
                    'message' => "Insurer not reachable,Issue in Token Generation service"
                ];
            }

            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
            $car_age = ceil($age / 12);

            $premium_type_array = DB::table('master_premium_type')
                ->where('id', $productData->premium_type_id)
                ->select('premium_type_code','premium_type')
                ->first();
            $premium_type = $premium_type_array->premium_type_code;
            $policy_type = $premium_type_array->premium_type;

            if($premium_type == 'breakin')
            {
                $premium_type = 'comprehensive';
                $policy_type = 'Comprehensive';
            }
            if($premium_type == 'third_party_breakin')
            {
                $premium_type = 'third_party';
                $policy_type = 'Third Party';
            }
            if($premium_type == 'own_damage_breakin')
            {
                $premium_type = 'own_damage';
                $policy_type = 'Own Damage';
            }
            switch($premium_type)
            {
                case "comprehensive":
                     $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
                break;
                case "own_damage":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');
                break;
                case "third_party":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
                break;
            }
            if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
            {
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');
            }
            
            $businessType = '';
            switch ($requestData->business_type)
            {
                case 'newbusiness':
                    $businessType = 'New Business';
                break;
                case 'rollover':
                    $businessType = 'Roll Over';
                break;

                case 'breakin':
                    $businessType = 'Break- In';
                break;

            }

            $corelationId = getUUID($enquiryId);
            
            //$DealID = 'DL-3001/913908';
            $DealID = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $deal_id : config('DEAL_ID_ICICI_LOMBARD_TESTING');
             
            $fetch_policy_data = [	
                "PolicyNumber"              => trim($proposal->previous_policy_number),
                "CorrelationId"             => $corelationId,
                "EngineNumberLast5Char"     => substr($proposal->engine_number,-5),
                "ChassisNumberLast5Char"    => substr($proposal->chassis_number,-5),
                "DealID"                    => $DealID
            ];

            $additionPremData = [
                'requestMethod'     => 'post',
                'type'              => 'Fetch Policy Details',
                'section'           => 'CAR',
                'productName'       => $productData->product_name,
                'enquiryId'         => $enquiryId,
                'transaction_type'  => 'proposal',
                'token'             => $access_token
            ];
            //$url  = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/PrivateCar/Fetch';
            $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_MOTOR_FETCH_POLICY_DATA');
            $get_response = getWsData($url, $fetch_policy_data, 'icici_lombard', $additionPremData);
            $data = $get_response['response'];
            $reponse            = json_decode($data,true);

            //if(isset($reponse['status']) && $reponse['status'] == true)
            if(isset($reponse['status']) && $reponse['status'] == true && 
                $reponse['proposalDetails']['isQuoteDeviation'] == false && 
                $reponse['proposalDetails']['breakingFlag'] == false &&
                $reponse['proposalDetails']['isApprovalRequired'] == false
            )
            {
                $proposalDetails            = $reponse['proposalDetails'];        
                $previousPolicyDetails      = $reponse['previousPolicyDetails']; 
                $vehicleDetails      = $reponse['vehicleDetails']; 
                
                $riskDetails        = $proposalDetails['riskDetails'];
                $generalInformation = $proposalDetails['generalInformation'];
                $coverDetails = $reponse['coverDetails'] ?? null;
               
                $policy_start_date = str_replace('/','-',$generalInformation['policyInceptionDate']);
                $policy_end_date = str_replace('/','-',$generalInformation['policyEndDate']);
               
                $registrationDate = str_replace('/','-',$generalInformation['registrationDate']);
                
                $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
                
                
                $IsConsumables = false;
                $isTyreProtect = false;
                $IsRTIApplicableflag = false;
                $IsEngineProtectPlus = false;
                $LossOfPersonalBelongingPlanName = '';
                $KeyProtectPlan = '';
                $RSAPlanName = '';               //valid upto 15 years
                $ZeroDepPlanName = '';
                
                if($selected_addons->applicable_addons != NULL)
                {                 

                    foreach ($selected_addons->applicable_addons as $key => $value) 
                    {
                       if($value['name'] == 'Zero Depreciation')
                       {
                            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                       }
                       else if($value['name'] == 'Engine Protector')
                       {
                            $IsEngineProtectPlus = true;
                       }
                       else if($value['name'] == 'Key Replacement')
                       {
                            $KeyProtectPlan = 'KP1';
                       }
                       else if($value['name'] == 'Road Side Assistance')
                       {

                            // $RSAPlanName = 'RSA-Plus';   // for premium mismatch of rsa due to plan name handled as per IC EMAIL  RE: PRODUCTION / RENEWBUY / ICICI / 4W RENEWAL / PREMIUM MISMATCH #Business#
                            $RSAPlanName = 'RSA-Standard';
                            if (!empty($coverDetails['rsaPlanName'] ?? null)) {
                                $RSAPlanName = $coverDetails['rsaPlanName'];
                            }
                       }
                       else if($value['name'] == 'Loss of Personal Belongings')
                       {
                            $LossOfPersonalBelongingPlanName = 'PLAN A';
                       }
                       else if($value['name'] == 'Consumable')
                       {
                            $IsConsumables = true;
                       }
                       else if($value['name'] == 'Return To Invoice')
                       {
                            $IsRTIApplicableflag = true;
                       } elseif ($value['name'] == 'Tyre Secure') {
                            $isTyreProtect = true;
                        }
                    }
                }
                //removed age validation #33553
                // if ($car_age > 4) {
                //     $IsConsumables = false;
                // }

                // if ($car_age > 5)
                // {
                //     $isTyreProtect = false;
                //     $IsRTIApplicableflag = false;
                //     $IsEngineProtectPlus = false;
                //     $LossOfPersonalBelongingPlanName = '';
                //     $KeyProtectPlan = '';
                //     $ZeroDepPlanName = '';   
    
                // }

                // if ($car_age > 15) {
                //     $RSAPlanName = '';
                // }

                if($premium_type == 'third_party')
                {
                    $isTyreProtect = false;
                    $IsConsumables = false;
                    $IsRTIApplicableflag = false;
                    $IsEngineProtectPlus = false;
                    $LossOfPersonalBelongingPlanName = '';
                    $KeyProtectPlan = '';
                    $RSAPlanName = '';  
                    $ZeroDepPlanName = '';       
                }


                if ($selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') 
                {
                    $addons = $selected_addons->compulsory_personal_accident;
                    foreach ($addons as $value) 
                    {
                        if(isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident') && $requestData->vehicle_owner_type == 'I')
                        {
                            $ispacoverownerdriver = 'true';
                        }
                        else
                        {
                            $ispacoverownerdriver = 'false';
                        }
                     }
                }
        
                $IsVehicleHaveCNG = 'false';
                $IsVehicleHaveLPG = 'false';
                $SIVehicleHaveLPG_CNG = 0;
                $SIHaveElectricalAccessories = 0;
                $SIHaveNonElectricalAccessories = 0;
                $IsPACoverUnnamedPassenger = 'false';
                $SIPACoverUnnamedPassenger = 0;
                $IsLLPaidDriver = false;
                $tppd_limit = 750000;
                
        
                if(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'CNG')
                {
                    $bifuel_type = 'CNG';
                    $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                    $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                    $SIVehicleHaveLPG_CNG = 0;
                }else if(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'LPG')
                {
                    $bifuel_type = 'LPG';
                    $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                    $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                    $SIVehicleHaveLPG_CNG = 0;
                }
                if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
                {
                    $accessories = ($selected_addons->accessories);
                    foreach ($accessories as $value) {
                        if($value['name'] == 'Electrical Accessories')
                        {
                            $SIHaveElectricalAccessories = $value['sumInsured'];
                        }
                        else if($value['name'] == 'Non-Electrical Accessories')
                        {
                            $SIHaveNonElectricalAccessories = $value['sumInsured'];
                        }
                        else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                        {
                            $bifuel_type = 'CNG';
                            $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                            $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                            $SIVehicleHaveLPG_CNG = $value['sumInsured'];
                        }
                    }
                }

                if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
                {
                    $additional_covers = $selected_addons->additional_covers;
                    foreach ($additional_covers as $value) {
                       if($value['name'] == 'Unnamed Passenger PA Cover')
                       {
                            $IsPACoverUnnamedPassenger = 'true';
                            $SIPACoverUnnamedPassenger = ($value['sumInsured'] * $mmv->seating_capacity);

                       }
                       if($value['name'] == 'LL paid driver')
                       {
                            $IsLLPaidDriver = true;
                       }
                    }
                }

                if($riskDetails['paidDriver'] > 0)
                {
                    $IsLLPaidDriver = true;
                }

                $voluntary_deductible_amount = 0;

                if($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '')
                {
                    $discounts_opted = $selected_addons->discounts;
                    foreach ($discounts_opted as $value) {
                       if($value['name'] == 'TPPD Cover')
                       {
                            $tppd_limit = 6000;
                       }
                       if($value['name'] == 'voluntary_insurer_discounts')
                       {
                           $voluntary_deductible_amount = $value['sumInsured'];
                       }
                    }
                }
                $rto_cities = MasterRto::where('rto_code', $requestData->rto_code)->first();
                $state_id = $rto_cities->state_id;
                $state_name = MasterState::where('state_id', $state_id)->first();
                $state_name = strtoupper($state_name->state_name);
                //echo "EEEE";

                if (config('ICICI_LOMBARD_RENEWAL_IDV_SERVICE_CAR') == 'Y') {
                    $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
                    $premiumJson = $quoteLog->premium_json;
                    $showroomPrice = $premiumJson['showroomPrice'] ?? 0;
                } else {
                    $showroomPrice = $generalInformation['showRoomPrice'];
                }

                $proposal_submit_data = [
                    'PolicyNumber'                  => $previousPolicyDetails['previousPolicyNumber'],
                    'ProposalRefNo'                 => $generalInformation['referenceProposalNo'],
                    'CustomerID'                    => $generalInformation['customerId'],
                    'DealID'                        => $DealID,
                    'EngineNumberLast5Char'         => substr($vehicleDetails['engineNumber'],-5),
                    'ChassisNumberLast5Char'        => substr($vehicleDetails['chassisNumber'],-5),
                    'IsCustomerModified'            => false,
                    'CorrelationId'                 => $corelationId,
                    'ProposalDetails'               => [
                        'BusinessType'              => $generalInformation['transactionType'],
                        'CustomerType'              => $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE',
                        'PolicyStartDate'           => date('Y-m-d', strtotime($policy_start_date)),
                        'PolicyEndDate'             => date('Y-m-d', strtotime($policy_end_date)),
                        'VehicleMakeCode'           => $vehicleDetails['vehicleMakeCode'],
                        'VehicleModelCode'          => $vehicleDetails['vehicleModelCode'],
                        'RTOLocationCode'           => $generalInformation['rtoLocationCode'],
                        'EngineNumber'              => $vehicleDetails['engineNumber'],
                        'ChassisNumber'               => $vehicleDetails['chassisNumber'],
                        'RegistrationNumber'          => $vehicleDetails['registrationNumber'],
                        'ManufacturingYear'           => $generalInformation['manufacturingYear'],
                        'DeliveryOrRegistrationDate'  => date('Y-m-d', strtotime($registrationDate)),
                        'FirstRegistrationDate'       => date('Y-m-d', strtotime($registrationDate)),
                        'ExShowRoomPrice'             => $showroomPrice,
                        'IsValidDrivingLicense'       => false,
                        'IsMoreThanOneVehicle'        => false,
                        'IsNoPrevInsurance'           => false,
                        'IsTransferOfNCB'             => false,
                        'TransferOfNCBPercent'        => 0,
                        'IsLegalLiabilityToPaidDriver' => $IsLLPaidDriver,
                        'IsPACoverOwnerDriver'      => $ispacoverownerdriver,
                        'isPACoverWaiver'           => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                        'PACoverTenure'             => 1,
                        'Tenure'                    => 1,
                        'IsVehicleHaveLPG'          => $IsVehicleHaveLPG,
                        'IsVehicleHaveCNG'          => $IsVehicleHaveCNG,
                        'SIVehicleHaveLPG_CNG'      => $SIVehicleHaveLPG_CNG,
                        'TPPDLimit'                 => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                        'SIHaveElectricalAccessories' => $SIHaveElectricalAccessories,
                        'SIHaveNonElectricalAccessories' => $SIHaveNonElectricalAccessories,
                        'IsPACoverUnnamedPassenger' => $IsPACoverUnnamedPassenger,
                        'SIPACoverUnnamedPassenger' => $SIPACoverUnnamedPassenger,
                        'IsFiberGlassFuelTank'      => false,
                        'IsVoluntaryDeductible'         => ($voluntary_deductible_amount != 0) ? false : false,
                        'VoluntaryDeductiblePlanName'   => ($voluntary_deductible_amount != 0) ? 0 : 0,
                        'IsAutomobileAssocnFlag' => false,
                        'IsAntiTheftDisc' => ( $riskDetails['antiTheftDiscount']  > 0 ) ? true : false,
                        'IsHandicapDisc' => false,
                        'IsExtensionCountry' => false,
                        'ExtensionCountryName' => NULL,
                        'IsGarageCash' => false,
                        'GarageCashPlanName' => NULL,
                        'ZeroDepPlanName' => $ZeroDepPlanName,
                        'RSAPlanName' => $RSAPlanName,
                        'KeyProtectPlan' => $KeyProtectPlan,
                        'LossOfPersonalBelongingPlanName' => $LossOfPersonalBelongingPlanName,
                        'IsRTIApplicableflag' => $IsRTIApplicableflag,
                        'IsEngineProtectPlus' =>$IsEngineProtectPlus,
                        'IsConsumables' => $IsConsumables,
                        'IsTyreProtect' => $isTyreProtect,
                        'OtherLoading' => 0,
                        'OtherDiscount' => 0,
                        'GSTToState' => $state_name,
                        'CorrelationId' => $corelationId,
                        'PreviousPolicyDetails' => [
                        'previousPolicyStartDate' => $previousPolicyDetails['previousPolicyStartDate'],//'2018-07-02',
                        'previousPolicyEndDate' => $previousPolicyDetails['previousPolicyEndDate'],
                        'ClaimOnPreviousPolicy' => 0,
                        'PreviousPolicyType' => $previousPolicyDetails['previousPolicyType'],
                        'PreviousPolicyNumber' => $previousPolicyDetails['previousPolicyNumber'],
                        'PreviousInsurerName' => 'GIC',
                      ]
                    ]
                  ];
                
                if ($proposal->is_vehicle_finance == '1') 
                {
                    $proposal_submit_data["FinancierDetails"] = 
                    [
                        "FinancierName" => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : "",
                        "BranchName" => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : "",
                        "AgreementType" => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : ""
                    ];
                }
                
                //print_r($proposal_submit_data);
                
                
                $additionPremData = [
                    'requestMethod' => 'post',
                    'type' => 'Renewal Proposal Service',
                    'section' => 'CAR',
                    'token' => $access_token,
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'proposal',
                    'productName'  => $productData->product_name
                ];
                //$url = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/PrivateCar/RenewPolicy';
                $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_MOTOR_PROPOSAL_RENEWAL_SUBMIT');
                $get_response = getWsData($url, $proposal_submit_data, 'icici_lombard', $additionPremData);
                $proposalServiceResponse = $get_response['response'];
                $proposalServiceResponse = json_decode($proposalServiceResponse,true);
                if(isset($proposalServiceResponse['status']) && $proposalServiceResponse['status'] == 'Success')
                {
                    
                    $proposalRiskDetails = $proposalServiceResponse['riskDetails'];
                    $od_premium = (int) $proposalRiskDetails['basicOD'] - (int) $proposalRiskDetails['bonusDiscount'];
                    $total_tp = $proposalServiceResponse['totalLiabilityPremium'];
                    $addon_premium = 0;
                    $NoCliamDiscount = 0;
                    $total_discount = 0;
                    $proposal->idv                  = $generalInformation['depriciatedIDV'];
                    $proposal->proposal_no          = trim($proposalServiceResponse['generalInformation']['proposalNumber']);
                    $proposal->unique_proposal_id   = $corelationId;
                    $proposal->customer_id          = $proposalServiceResponse['generalInformation']['customerId'];
                    $proposal->od_premium           = $od_premium;
                    $proposal->tp_premium           = $proposalServiceResponse['totalLiabilityPremium'];
                    $proposal->ncb_discount         = $total_tp;
                    $proposal->addon_premium        = $addon_premium;
                    $proposal->total_premium        = $proposalServiceResponse['packagePremium'];
                    $proposal->service_tax_amount   = $proposalServiceResponse['totalTax'];
                    $proposal->final_payable_amount = $proposalServiceResponse['finalPremium'];
                    $proposal->cpa_premium          = $NoCliamDiscount;
                    $proposal->total_discount       = $total_discount;
                    $proposal->ic_vehicle_details   = $vehicleDetails;
                    $proposal->policy_start_date    = $policy_start_date;
                    $proposal->policy_end_date      = $policy_end_date;
                    $proposal->save();
                
                    $updateJourneyStage['user_product_journey_id'] = $enquiryId;
                    $updateJourneyStage['ic_id'] = '40';
                    $updateJourneyStage['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                    $updateJourneyStage['proposal_id'] = $proposal->user_proposal_id;
                    updateJourneyStage($updateJourneyStage);
                    
                    IciciLombardPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
                
                    return response()->json([
                        'status' => true,
                        'msg' => "Proposal Submitted Successfully!",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => camelCase([
                            'proposal_no'        => trim($proposalServiceResponse['generalInformation']['proposalNumber']),
                            'finalPayableAmount' => $proposalServiceResponse['finalPremium']
                        ]),
                    ]); 
                }
                else
                {
                    return [
                        'status' => false,
                        'premium' => '0',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $proposalServiceResponse['message'] ?? 'Service Issue'
                    ];
                }
            }
            else
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $reponse['message'] ?? 'Service Issue'
                ];            
            }
        }
        else
        {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => "Issue in Token Generation service"
            ];
        }       
    }
    //NEW FLOW
    public static function newRenewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $vehicleDetails = [
            'manf_name'             => $mmv->manufacturer_name,
            'model_name'            => $mmv->model_name,
            'version_name'          => '',
            'seating_capacity'      => ((int) $mmv->seating_capacity) - 1,
            'carrying_capacity'     => $mmv->seating_capacity,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel_type,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'CAR',
            'version_id'            => $mmv->ic_version_code,
        ];
        $additionData = [
            'requestMethod'     => 'post',
            'type'              => 'tokenGeneration',
            'section'           => 'CAR',
            'productName'       => $productData->product_name,
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'proposal'
        ];

        $tokenParam = [
            'grant_type'    => 'password',
            'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
            'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
            'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
            'scope'         => 'esbmotor',
        ];

//        $tokenParam = [
//            'grant_type'    => 'password',
//            'username'      => 'renewBuy',
//            'password'      => 'r3n3w&u4',
//            'client_id'     => 'ro.renewBuy',
//            'client_secret' => 'ro.r3n3w&u4',
//            'scope'         => 'esbmotor',
//        ];
        
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
//        print_r($token);
//        die;
//$token = '{"access_token":"eyJhbGciOiJSUzI1NiIsImtpZCI6IjZCN0FDQzUyMDMwNUJGREI0RjcyNTJEQUVCMjE3N0NDMDkxRkFBRTEiLCJ0eXAiOiJKV1QiLCJ4NXQiOiJhM3JNVWdNRnY5dFBjbExhNnlGM3pBa2ZxdUUifQ.eyJuYmYiOjE2NTA1MzQ4NjMsImV4cCI6MTY1MDUzODQ2MywiaXNzIjoiaHR0cHM6Ly9pbGVzYnNhbml0eS5pbnN1cmFuY2VhcnRpY2xlei5jb20vY2VyYmVydXMiLCJhdWQiOlsiaHR0cHM6Ly9pbGVzYnNhbml0eS5pbnN1cmFuY2VhcnRpY2xlei5jb20vY2VyYmVydXMvcmVzb3VyY2VzIiwiZXNibW90b3IiXSwiY2xpZW50X2lkIjoicm8ucmVuZXdCdXkiLCJzdWIiOiIwYzBmZTc2OC0zOTdkLTQ5OWQtYTdjZC0zOTZiYTk3ZDE3NDEiLCJhdXRoX3RpbWUiOjE2NTA1MzQ4NjMsImlkcCI6ImxvY2FsIiwic2NvcGUiOlsiZXNibW90b3IiXSwiYW1yIjpbImN1c3RvbSJdfQ.ojXDOW0b6Dj0Nnfnn-5ZrR_zYf6W-dsWg9VsUo0ClUIXZ-4fecrhn-zWk-GpxnIrHQcaP32hV0-3898qA-PfP4AOmCJzmUeop8VGO6cB7UCiexx5MqBTowH8EVncQcQooVkSMNqlwbUCCBD64pg2G0FRgKww4QisaHwq3JhiKaPs4sbK86mtqLq2-waxZsqfysUNF2iy87PbmG-Ue0KQgODqdFVcl6s2KvvlXTuW-bYImGmxDuN-o6dHKIlIU7jnUdzOnriQ7MTq3JqqyS8TPZU0gN4CPfActP43XZYEwVEspH0y6CMjiNpNXWfh_W3IztMd8LKXeuVyjfiIdC1LnA","expires_in":3600,"token_type":"Bearer"}';
        if (!empty($token))
        {
            $token = json_decode($token, true);

            if(isset($token['access_token']))
            {
                $access_token = $token['access_token'];
            }
            else
            {
                return [
                    'status' => false,
                    'message' => "Insurer not reachable,Issue in Token Generation service"
                ];
            }

            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
            $car_age = ceil($age / 12);

            $premium_type_array = DB::table('master_premium_type')
                ->where('id', $productData->premium_type_id)
                ->select('premium_type_code','premium_type')
                ->first();
            $premium_type = $premium_type_array->premium_type_code;
            $policy_type = $premium_type_array->premium_type;

            if($premium_type == 'breakin')
            {
                $premium_type = 'comprehensive';
                $policy_type = 'Comprehensive';
            }
            if($premium_type == 'third_party_breakin')
            {
                $premium_type = 'third_party';
                $policy_type = 'Third Party';
            }
            if($premium_type == 'own_damage_breakin')
            {
                $premium_type = 'own_damage';
                $policy_type = 'Own Damage';
            }
            switch($premium_type)
            {
                case "comprehensive":
                     $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
                break;
                case "own_damage":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');
                break;
                case "third_party":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
                break;
            }
            if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
            {
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');
            }

            $businessType = '';
            switch ($requestData->business_type)
            {
                case 'newbusiness':
                    $businessType = 'New Business';
                break;
                case 'rollover':
                    $businessType = 'Roll Over';
                break;

                case 'breakin':
                    $businessType = 'Break- In';
                break;

            }

            // $corelationId = getUUID($enquiryId);
            $corelationId = ($proposal->unique_proposal_id);
            
            //$DealID = 'DL-3001/913908';
            $DealID = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $deal_id : config('DEAL_ID_ICICI_LOMBARD_TESTING');
             
            $fetch_policy_data = [	
                "PolicyNumber"              => trim($proposal->previous_policy_number),
                "CorrelationId"             => $corelationId,
                "EngineNumberLast5Char"     => substr($proposal->engine_number,-5),
                "ChassisNumberLast5Char"    => substr($proposal->chassis_number,-5),
                "DealID"                    => $DealID
            ];

            $additionPremData = [
                'requestMethod'     => 'post',
                'type'              => 'Fetch Policy Details',
                'section'           => 'CAR',
                'productName'       => $productData->product_name,
                'enquiryId'         => $enquiryId,
                'transaction_type'  => 'proposal',
                'token'             => $access_token
            ];
            //$url  = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/PrivateCar/Fetch';
            $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_MOTOR_FETCH_POLICY_DATA');
            $get_response = getWsData($url, $fetch_policy_data, 'icici_lombard', $additionPremData);
            $data = $get_response['response'];
            $reponse            = json_decode($data,true);

            //if(isset($reponse['status']) && $reponse['status'] == true)
            if(isset($reponse['status']) && $reponse['status'] == true && 
                $reponse['proposalDetails']['isQuoteDeviation'] == false && 
                $reponse['proposalDetails']['breakingFlag'] == false &&
                $reponse['proposalDetails']['isApprovalRequired'] == false
            )
            {
                $proposalDetails            = $reponse['proposalDetails'];        
                $previousPolicyDetails      = $reponse['previousPolicyDetails']; 
                $vehicleDetails      = $reponse['vehicleDetails'];
                $coverDetails = $reponse['coverDetails'] ?? null;
                
                $riskDetails        = $proposalDetails['riskDetails'];
                $generalInformation = $proposalDetails['generalInformation'];
               
                $policy_start_date = str_replace('/','-',$generalInformation['policyInceptionDate']);
                $policy_end_date = str_replace('/','-',$generalInformation['policyEndDate']);
               
                $registrationDate = str_replace('/','-',$generalInformation['registrationDate']);
                
                $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
                
                
                $IsConsumables = false;
                $isTyreProtect = false;
                $IsRTIApplicableflag = false;
                $IsEngineProtectPlus = false;
                $LossOfPersonalBelongingPlanName = '';
                $KeyProtectPlan = '';
                $RSAPlanName = '';               //valid upto 15 years
                $ZeroDepPlanName = '';
                // $eme_Plan_name = "";
                $eme_cover = "false";
                
                if($selected_addons->applicable_addons != NULL)
                {                 

                    foreach ($selected_addons->applicable_addons as $key => $value) 
                    {
                       if($value['name'] == 'Zero Depreciation')
                       {
                            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                       }
                       else if($value['name'] == 'Engine Protector')
                       {
                            $IsEngineProtectPlus = true;
                       }
                       else if($value['name'] == 'Key Replacement')
                       {
                            $KeyProtectPlan = 'KP1';
                       }
                       else if($value['name'] == 'Road Side Assistance')
                       {
                            // $RSAPlanName = 'RSA-Plus';   // for premium mismatch of rsa due to plan name handled as per IC EMAIL  RE: PRODUCTION / RENEWBUY / ICICI / 4W RENEWAL / PREMIUM MISMATCH #Business#
                            $RSAPlanName = 'RSA-Standard';
                            if (!empty($coverDetails['rsaPlanName'] ?? null)) {
                                $RSAPlanName = $coverDetails['rsaPlanName'];
                            }
                       }
                       else if($value['name'] == 'Loss of Personal Belongings')
                       {
                            $LossOfPersonalBelongingPlanName = 'PLAN A';
                       }
                       else if($value['name'] == 'Consumable')
                       {
                            $IsConsumables = true;
                       }
                       else if($value['name'] == 'Return To Invoice')
                       {
                            $IsRTIApplicableflag = true;
                       } elseif ($value['name'] == 'Tyre Secure') {
                            $isTyreProtect = true;
                        }
                        elseif ($value['name'] == 'Emergency Medical Expenses'  && $car_age <= 15) {
                            $eme_cover = "true";
                            $eme_Plan_name = 'Premium Segment';
                        }
                    }
                }

                if ($car_age > 4) {
                    $IsConsumables = false;
                }

                if ($car_age > 5)
                {
                    $isTyreProtect = false;
                    $IsRTIApplicableflag = false;
                    $IsEngineProtectPlus = false;
                    $LossOfPersonalBelongingPlanName = '';
                    $KeyProtectPlan = '';
                    $ZeroDepPlanName = '';   
    
                }

                if ($car_age > 15) {
                    $RSAPlanName = '';
                }

                if($premium_type == 'third_party')
                {
                    $isTyreProtect = false;
                    $IsConsumables = false;
                    $IsRTIApplicableflag = false;
                    $IsEngineProtectPlus = false;
                    $LossOfPersonalBelongingPlanName = '';
                    $KeyProtectPlan = '';
                    $RSAPlanName = '';  
                    $ZeroDepPlanName = '';       
                }


                if ($selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') 
                {
                    $addons = $selected_addons->compulsory_personal_accident;
                    foreach ($addons as $value) 
                    {
                        if(isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident') && $requestData->vehicle_owner_type == 'I')
                        {
                            $ispacoverownerdriver = true;
                        }
                        else
                        {
                            $ispacoverownerdriver = false;
                        }
                     }
                }
        
                $IsVehicleHaveCNG = 'false';
                $IsVehicleHaveLPG = 'false';
                $SIVehicleHaveLPG_CNG = 0;
                $SIHaveElectricalAccessories = 0;
                $SIHaveNonElectricalAccessories = 0;
                $IsPACoverUnnamedPassenger = 'false';
                $SIPACoverUnnamedPassenger = 0;
                $IsLLPaidDriver = false;
                $tppd_limit = 750000;
                
        
                if(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'CNG')
                {
                    $bifuel_type = 'CNG';
                    $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                    $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                    $SIVehicleHaveLPG_CNG = 0;
                }else if(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'LPG')
                {
                    $bifuel_type = 'LPG';
                    $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                    $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                    $SIVehicleHaveLPG_CNG = 0;
                }
                if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
                {
                    $accessories = ($selected_addons->accessories);
                    foreach ($accessories as $value) {
                        if($value['name'] == 'Electrical Accessories')
                        {
                            $SIHaveElectricalAccessories = $value['sumInsured'];
                        }
                        else if($value['name'] == 'Non-Electrical Accessories')
                        {
                            $SIHaveNonElectricalAccessories = $value['sumInsured'];
                        }
                        else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                        {
                            $bifuel_type = 'CNG';
                            $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                            $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                            $SIVehicleHaveLPG_CNG = $value['sumInsured'];
                        }
                    }
                }

                if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
                {
                    $additional_covers = $selected_addons->additional_covers;
                    foreach ($additional_covers as $value) {
                       if($value['name'] == 'Unnamed Passenger PA Cover')
                       {
                            $IsPACoverUnnamedPassenger = 'true';
                            $SIPACoverUnnamedPassenger = ($value['sumInsured'] * $mmv->seating_capacity);

                       }
                       if($value['name'] == 'LL paid driver')
                       {
                            $IsLLPaidDriver = true;
                       }
                    }
                }

                if($riskDetails['paidDriver'] > 0)
                {
                    $IsLLPaidDriver = true;
                }

                $voluntary_deductible_amount = 0;
                $is_voluntary_deductible_opted = false;

                if($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '')
                {
                    $discounts_opted = $selected_addons->discounts;
                    foreach ($discounts_opted as $value) {
                       if($value['name'] == 'TPPD Cover')
                       {
                            $tppd_limit = 6000;
                       }
                       if($value['name'] == 'voluntary_insurer_discounts')
                       {
                           $voluntary_deductible_amount = $value['sumInsured'];
                           $is_voluntary_deductible_opted = true;

                       }
                    }
                }
                $rto_cities = MasterRto::where('rto_code', $requestData->rto_code)->first();
                $state_id = $rto_cities->state_id;
                $state_name = MasterState::where('state_id', $state_id)->first();
                $state_name = strtoupper($state_name->state_name);

                //echo "EEEE";
                // $proposal_submit_data = [
                //     'PolicyNumber'                  => $previousPolicyDetails['previousPolicyNumber'],
                //     'ProposalRefNo'                 => $generalInformation['referenceProposalNo'],
                //     'CustomerID'                    => $generalInformation['customerId'],
                //     'DealID'                        => $DealID,
                //     'EngineNumberLast5Char'         => substr($vehicleDetails['engineNumber'], -5),
                //     'ChassisNumberLast5Char'        => substr($vehicleDetails['chassisNumber'], -5),
                //     'IsCustomerModified'            => false,
                //     'CorrelationId'                 => $corelationId,
                //     'ProposalDetails'               => [
                //         'BusinessType'              => $generalInformation['transactionType'],
                //         'CustomerType'              => $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE',
                //         'PolicyStartDate'           => date('Y-m-d', strtotime($policy_start_date)),
                //         'PolicyEndDate'             => date('Y-m-d', strtotime($policy_end_date)),
                //         'VehicleMakeCode'           => $vehicleDetails['vehicleMakeCode'],
                //         'VehicleModelCode'          => $vehicleDetails['vehicleModelCode'],
                //         'RTOLocationCode'           => $generalInformation['rtoLocationCode'],
                //         'EngineNumber'              => $vehicleDetails['engineNumber'],
                //         'ChassisNumber'               => $vehicleDetails['chassisNumber'],
                //         'RegistrationNumber'          => $vehicleDetails['registrationNumber'],
                //         'ManufacturingYear'           => $generalInformation['manufacturingYear'],
                //         'DeliveryOrRegistrationDate'  => date('Y-m-d', strtotime($registrationDate)),
                //         'FirstRegistrationDate'       => date('Y-m-d', strtotime($registrationDate)),
                //         'ExShowRoomPrice'             => $generalInformation['showRoomPrice'],
                //         'IsValidDrivingLicense'       => false,
                //         'IsMoreThanOneVehicle'        => false,
                //         'IsNoPrevInsurance'           => false,
                //         'IsTransferOfNCB'             => false,
                //         'TransferOfNCBPercent'        => 0,
                //         'IsLegalLiabilityToPaidDriver' => $IsLLPaidDriver,
                //         'IsPACoverOwnerDriver'      => $ispacoverownerdriver,
                //         'isPACoverWaiver'           => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                //         'PACoverTenure'             => 1,
                //         'IsVehicleHaveLPG'          => $IsVehicleHaveLPG,
                //         'IsVehicleHaveCNG'          => $IsVehicleHaveCNG,
                //         'SIVehicleHaveLPG_CNG'      => $SIVehicleHaveLPG_CNG,
                //         'TPPDLimit'                 => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                //         'SIHaveElectricalAccessories' => $SIHaveElectricalAccessories,
                //         'SIHaveNonElectricalAccessories' => $SIHaveNonElectricalAccessories,
                //         'IsPACoverUnnamedPassenger' => $IsPACoverUnnamedPassenger,
                //         'SIPACoverUnnamedPassenger' => $SIPACoverUnnamedPassenger,
                //         'IsFiberGlassFuelTank'      => false,
                //         'IsVoluntaryDeductible'         => ($voluntary_deductible_amount != 0) ? false : false,
                //         'VoluntaryDeductiblePlanName'   => ($voluntary_deductible_amount != 0) ? 0 : 0,
                //         'IsAutomobileAssocnFlag' => false,
                //         'IsAntiTheftDisc' => ($riskDetails['antiTheftDiscount']  > 0) ? true : false,
                //         'IsHandicapDisc' => false,
                //         'IsExtensionCountry' => false,
                //         'ExtensionCountryName' => NULL,
                //         'IsGarageCash' => false,
                //         'GarageCashPlanName' => NULL,
                //         'ZeroDepPlanName' => $ZeroDepPlanName,
                //         'RSAPlanName' => $RSAPlanName,
                //         'KeyProtectPlan' => $KeyProtectPlan,
                //         'LossOfPersonalBelongingPlanName' => $LossOfPersonalBelongingPlanName,
                //         'IsRTIApplicableflag' => $IsRTIApplicableflag,
                //         'IsEngineProtectPlus' => $IsEngineProtectPlus,
                //         'IsConsumables' => $IsConsumables,
                //         'IsTyreProtect' => $isTyreProtect,
                //         'OtherLoading' => 0,
                //         'OtherDiscount' => 0,
                //         'GSTToState' => $state_name,
                //         'CorrelationId' => $corelationId,
                //         'PreviousPolicyDetails' => [
                //             'previousPolicyStartDate' => $previousPolicyDetails['previousPolicyStartDate'], //'2018-07-02',
                //             'previousPolicyEndDate' => $previousPolicyDetails['previousPolicyEndDate'],
                //             'ClaimOnPreviousPolicy' => 0,
                //             'PreviousPolicyType' => $previousPolicyDetails['previousPolicyType'],
                //             'PreviousPolicyNumber' => $previousPolicyDetails['previousPolicyNumber'],
                //             'PreviousInsurerName' => 'GIC',
                //         ]
                //     ]
                // ];

                $proposal_submit_data = [
                    "ProposalDetails" => [
                        "IsAutoInstaTagging" => false,
                        'EngineNumber'              => $vehicleDetails['engineNumber'],
                        'ChassisNumber'               => $vehicleDetails['chassisNumber'],
                        'RegistrationNumber'          => $vehicleDetails['registrationNumber'],
                        // "NomineeDetails" => null,
                        // "financierDetails" => null,
                        // "CustomerDetails" => null,
                        "SPDetails" => null,
                        "IsEMIProtect" => false,
                        "EMIAmount" => 0.0,
                        "NoofEMI" => 0,
                        "TimeExcessindays" => 0,
                        "IsDrivingTuitionsFlag" => false,
                        'IsVehicleHaveLPG'          => $IsVehicleHaveLPG,
                        'IsVehicleHaveCNG'          => $IsVehicleHaveCNG,
                        "SIVehicleHaveLPG_CNG" => $SIVehicleHaveLPG_CNG,
                        "IsTyreProtect" => $isTyreProtect,
                        "IsNCBProtect" => false,
                        "NCBProtectPlanName" => null,
                        "IsGarageCash" => false,
                        "GarageCashPlanName" => null,
                        "NumberOfDrivers" => 1,
                        "NoOfTrailersTowed" => 0,
                        "TrailerChassisNumber" => null,
                        "IsLimitedToOwnPremises" => false,
                        "IsProposal" => false,
                        "IsQuote" => false,
                        "ExShowRoomPrice" => $generalInformation['showRoomPrice'],
                        "ServiceTaxExemptionCategory" => "No Exemption",
                        "IsHandicapDisc" => false,
                        "BodyType" => "Saloon",
                        "IsLegalLiabilityToPaidEmployee" => false,
                        "NoOfEmployee" => 0,
                        "LossOfPersonalBelongingPlanName" => $LossOfPersonalBelongingPlanName,
                        'IsPACoverUnnamedPassenger' => $IsPACoverUnnamedPassenger,
                        'SIPACoverUnnamedPassenger' => $SIPACoverUnnamedPassenger,
                        "IsQCByPass" => null,
                        "OwnershipSerialNumber" => null,
                        'IsVoluntaryDeductible'         => ($voluntary_deductible_amount != 0) ? true : false,
                        'VoluntaryDeductiblePlanName'   => $is_voluntary_deductible_opted,
                        "IsAutomobileAssocnFlag" => false,
                        "AutomobileAssociationNumber" => "",
                        "IsAntiTheftDisc" => ($riskDetails['antiTheftDiscount']  > 0) ? true : false,
                        "ZeroDepPlanName" => $ZeroDepPlanName,
                        "IsRTIApplicableflag" => $IsRTIApplicableflag,
                        "IsEngineProtectPlus" => $IsEngineProtectPlus,
                        "IsConsumables" => $IsConsumables,
                        "IsBatteryProtect" => false,
                        "KeyProtectPlan" => $KeyProtectPlan,
                        "RSAPlanName" => $RSAPlanName,
                        "IsTransferOfNCB" => false,
                        "TransferOfNCBPercent" => 0.0,
                        "IsHaveElectricalAccessories" => false,
                        "SIHaveElectricalAccessories" => $SIHaveElectricalAccessories,
                        "IsHaveNonElectricalAccessories" => false,
                        "SIHaveNonElectricalAccessories" => $SIHaveNonElectricalAccessories,
                        "Tenure" => 1,
                        // "TPTenure" => 1, //For OD business TP Details are not required in create renewal (proposal service).
                        "OtherLoading" => 0.0,
                        "OtherDiscount" => 0.0,
                        // "TPStartDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_start_date)) : null,
                        // "TPEndDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_end_date)) : null,
                        // "TPPolicyNo" => $proposal->tp_insurance_number,
                        // "TPInsurerName" => $proposal->tp_insurance_company,
                        // "EMECover" => $eme_cover,
                        // "NoOfPassengerHC" => null,
                        "PYPCoverDetails" => null,
                        "IsFloater" => null,
                        "FloaterDetails" => null,
                        "FleetID" => null,
                        "IsPAYU" => null,
                        "PAYUDetails" => null,
                        "IsEarlyPay" => null,
                        "IsPHYU" => null,
                        "PHYUDetails" => null,
                        "SmartSaverPlan" => null,
                        "IsAddonCoverInspection" => null,
                        "SIPACoverPaidDriver" => null,
                        'PolicyStartDate'           => date('Y-m-d', strtotime($policy_start_date)),
                        'PolicyEndDate'             => date('Y-m-d', strtotime($policy_end_date)),
                        "DealId" => $DealID,
                        'VehicleMakeCode'           => $vehicleDetails['vehicleMakeCode'],
                        'VehicleModelCode'          => $vehicleDetails['vehicleModelCode'],
                        'RTOLocationCode'           => $generalInformation['rtoLocationCode'],
                        'ManufacturingYear'           => $generalInformation['manufacturingYear'],
                        'DeliveryOrRegistrationDate'  => date('Y-m-d', strtotime($registrationDate)),
                        'FirstRegistrationDate'       => date('Y-m-d', strtotime($registrationDate)),
                        "BusinessType" => $generalInformation['transactionType'],
                        'CustomerType'              => $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE',
                        "IsValidDrivingLicense" => false,
                        "IsMoreThanOneVehicle" => false,
                        "IsPACoverOwnerDriver" => $ispacoverownerdriver,
                        "IsPACoverPaidDriver" => false,
                        "IsNoPrevInsurance" => false,
                        "PACoverTenure" => 1,
                        "IsFiberGlassFuelTank" => false,
                        "GSTToState" => $state_name,
                        "IsLegalLiabilityToPaidDriver" => $IsLLPaidDriver,
                        "NoOfDriver" => 0,
                        "IsExtensionCountry" => false,
                        "ExtensionCountryName" => null,
                        "PreviousPolicyDetails" => [
                            'previousPolicyStartDate' => $previousPolicyDetails['previousPolicyStartDate'], //'2018-07-02',
                            'previousPolicyEndDate' => $previousPolicyDetails['previousPolicyEndDate'],
                            "PreviousPolicyType" => $previousPolicyDetails['previousPolicyType'],
                            "BonusOnPreviousPolicy" => $requestData->previous_policy_type == 'Third-party' ? 0 : $requestData->previous_ncb,
                            "PreviousPolicyNumber" => $previousPolicyDetails['previousPolicyNumber'],
                            "ClaimOnPreviousPolicy" => false,
                            "TotalNoOfODClaims" => null,
                            "NoOfClaimsOnPreviousPolicy" => 0,
                            "PreviousInsurerName" => $proposal->previous_insurance_company,
                            "PreviousVehicleSaleDate" => "",
                            "PreviousPolicyTenure" => 0,
                            "NatureOfLoss" => null,
                        ],
                        "IsPACoverWaiver" => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                        "OldReferenceNo" => null,
                        "IsCoverRatesApplicable" => false,
                        "VehicleAge" => $car_age,
                        "AlternatePolicyNo" => null,
                        "ChannelSource" => "IAGENT",
                        "SoftCopyFlag" => null,
                        "IsSelfInspection" => null,
                        "SourcePolicy" => null,
                        "IsBreakinFlag" => false,
                        "NoOfClaimsInLast3Years" => 0,
                        "IsNCBApplicable" => true,
                        "IIBScore" => null,
                        "CIBILScore" => null,
                        "VehicleDescription" => null,
                        "CoverLevelDiscounting" => null,
                        "TypeOfCalculation" => null,
                        "SeatingCapacity" => $mmv->seating_capacity,
                        "IsPOSDealId" => false,
                        "POSStarttime" => null,
                        "POSEndtime" => null,
                        "IsRegisteredCustomer" => false,
                        "CorrelationId" => $corelationId,
                        "PolicyPeriod" => 0.0
                    ],
                    "CustomerID" => $generalInformation['customerId'],
                    "IsCustomerModified" => true,
                    "ProposalRefNo" => $generalInformation['referenceProposalNo'],
                    "PolicyNumber" => $previousPolicyDetails['previousPolicyNumber'],
                    "DealID" => $DealID,
                    'EngineNumberLast5Char'         => substr($vehicleDetails['engineNumber'], -5),
                    'ChassisNumberLast5Char'        => substr($vehicleDetails['chassisNumber'], -5),
                    "ChannelSource" => null,
                    "IsPOSDealId" => false,
                    "POSStarttime" => null,
                    "POSEndtime" => null,
                    "IsRegisteredCustomer" => false,
                    "CorrelationId" => $corelationId
                ];

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $pin_code_data  = DB::table('icici_lombard_pincode_master')
                    ->where('num_pincode', $proposal->pincode)
                        ->first();

                    if (empty($pin_code_data)) {
                        return  [
                            'satus' => false,
                            'msg' => 'pincode not found'
                        ];
                        exit;
                    }
                    // $proposal_submit_data['CustomerDetails']['CKYCID'] = $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number;
                    // $proposal_submit_data['CustomerDetails']['EKYCid'] = $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number;
                    // $proposal_submit_data['CustomerDetails']['ilkycReferenceNumber'] = $proposal->ckyc_reference_id;
                    //$proposal_array['CustomerDetails']['SkipDedupeLogic'] = ;
                    // $proposal_submit_data['ProposalDetails']['CustomerDetails'] = [

                    //     'CKYCID' => $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number,
                    //     'EKYCid' => $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number,
                    //     'ilkycReferenceNumber' => $proposal->ckyc_reference_id,
                    //     'AddressLine1' => $proposal->address_line1,
                    //     'AddressLine2' => null,
                    //     'CountryCode' => 100,
                    //     'Statecode' => $pin_code_data->il_state_id,
                    //     'CityCode' => $pin_code_data->il_citydistrict_id,
                    //     'Pincode' => $pin_code_data->num_pincode,
                    //     'CustomerName' =>$requestData->vehicle_owner_type == 'I'? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name,
                    //     'MobileNumber' => $proposal->mobile_number,
                    //     'CustomerType'              => $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE',
                    //     'AadharEnrollmentNo' => null,
                    //     'AadharNumber'=> null,
                    //     'CIN'=> null,
                    //     'CorelationId' => $corelationId,
                    //     'CorrespondingAddress'=> null,
                    //     "CustomerID" => $generalInformation['customerId'],
                    //     'DateOfBirth' => $requestData->vehicle_owner_type == 'I'?date('Y-m-d', strtotime($proposal->dob)). 'T00:00:00' : '',
                    //     'DateOfIncorporation'=> null,
                    //     'eIA_Number'=>null,
                    //     'Email' => $proposal->email,
                    //     'Gender'=>$proposal->gender,
                    //     'GSTDetails'=>null,
                    //     'IsCollectionofform60'=>false,
                    //     'MobileISD'=>null,
                    //     'MobileNumber' => $proposal->mobile_number,
                    //     'OtherFunds'=>null,
                    //     'PANCardNo' => ($proposal->pan_number != '') ? $proposal->pan_number : '',
                    //     'PEPFlag'=> false,
                    //     // 'Pincode' => $proposal->car_registration_pincode,
                    //     'SkipDedupeLogic'=>null,
                    //     'SourceOfFunds'=>null,
                    //     // 'Statecode' => $proposal->car_registration_state_id,
                    // ];
                    $proposal_submit_data['ProposalDetails']['CustomerDetails'] = [
                        "AadharEnrollmentNo"=> null,
                        "AadharNumber"=> null,
                        "AddressLine1"=> $proposal->address_line1,
                        "AddressLine2"=> null,
                        "CIN"=> null,
                        "CityCode"=> (int)$pin_code_data->il_citydistrict_id,
                        "CKYCId"=> $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number,
                        "CorelationId"=> $corelationId,
                        "CorrespondingAddress"=> null,
                        "CountryCode"=> (int)$pin_code_data->il_country_id,
                        "CustomerID"=> $generalInformation['customerId'],
                        "CustomerName"=> $requestData->vehicle_owner_type == 'I'? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name,
                        "CustomerType"=> $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE',
                        "DateOfBirth"=> $requestData->vehicle_owner_type == 'I'?date('Y-m-d', strtotime($proposal->dob)). 'T00:00:00' : '',
                        "DateOfIncorporation"=> null,
                        "eIA_Number"=> null,
                        "EKYCid"=> $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number,
                        "Email"=> $proposal->email,
                        "Gender"=> $proposal->gender,
                        "GSTDetails"=> null,
                        "ILKYCReferenceNumber"=> $proposal->ckyc_reference_id,
                        "IsCollectionofform60"=> false,
                        "MobileISD"=> null,
                        "MobileNumber"=> $proposal->mobile_number,
                        "OtherFunds"=> null,
                        "PANCardNo"=> ($proposal->pan_number != '') ? $proposal->pan_number : '',
                        "PEPFlag"=> false,
                        "PinCode"=> $pin_code_data->num_pincode,
                        "SkipDedupeLogic"=> null,
                        "SourceOfFunds"=> null,
                        "StateCode"=> $pin_code_data->il_state_id
                    ];
                }
                if($eme_cover)
                {
                    $proposal_submit_data['EMECover'] = null;
                    $proposal_submit_data['NoOfPassengerHC'] = $mmv->seating_capacity - 1;
                }

                if ($ispacoverownerdriver == 'true') {
                    $proposal_submit_data['NomineeDetails']  = [
                        'NomineeType'               => 'PA-Owner Driver',
                        'NameOfNominee'             => $proposal->nominee_name,
                        'Age'                       => get_date_diff('year', $proposal->nominee_dob),
                        'Relationship'              => $proposal->nominee_relationship
                    ];
                }
                
                if ($proposal->is_vehicle_finance == '1') 
                {
                    $proposal_submit_data["FinancierDetails"] = 
                    [
                        "FinancierName" => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : "",
                        "BranchName" => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : "",
                        "AgreementType" => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : ""
                    ];
                }
                
                //print_r($proposal_submit_data);
                
                
                $additionPremData = [
                    'requestMethod' => 'post',
                    'type' => 'Renewal Proposal Service',
                    'section' => 'CAR',
                    'token' => $access_token,
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'proposal',
                    'productName'  => $productData->product_name
                ];
                //$url = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/PrivateCar/RenewPolicy';
                $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_MOTOR_PROPOSAL_RENEWAL_SUBMIT');
                $get_response = getWsData($url, $proposal_submit_data, 'icici_lombard', $additionPremData);
                $proposalServiceResponse = $get_response['response'];
                $proposalServiceResponse = json_decode($proposalServiceResponse,true);
                if(isset($proposalServiceResponse['status']) && $proposalServiceResponse['status'] == 'Success')
                {
                    
                    $proposalRiskDetails = $proposalServiceResponse['riskDetails'];
                    $od_premium = (int) $proposalRiskDetails['basicOD'] - (int) $proposalRiskDetails['bonusDiscount'];
                    // $total_tp = $proposalServiceResponse['totalLiabilityPremium'];
                    $addon_premium = 0;
                    // $NoCliamDiscount = 0;
                    $total_discount = 0;
                    $proposal->idv                  = $generalInformation['depriciatedIDV'];
                    $proposal->proposal_no          = trim($proposalServiceResponse['generalInformation']['proposalNumber']);
                    $proposal->unique_proposal_id   = $corelationId;
                    $proposal->customer_id          = $proposalServiceResponse['generalInformation']['customerId'];
                    $proposal->od_premium           = $od_premium;
                    $proposal->tp_premium           = $proposalServiceResponse['totalLiabilityPremium'];
                    $proposal->ncb_discount         = $reponse['proposalDetails']['bonusDiscount'];
                    $proposal->addon_premium        = $addon_premium;
                    $proposal->total_premium        = $proposalServiceResponse['packagePremium'];
                    $proposal->service_tax_amount   = $proposalServiceResponse['totalTax'];
                    $proposal->final_payable_amount = $proposalServiceResponse['finalPremium'];
                    $proposal->cpa_premium          = $reponse['proposalDetails']['paCoverForOwnerDriver'];
                    $proposal->total_discount       = $total_discount;
                    $proposal->ic_vehicle_details   = $vehicleDetails;
                    $proposal->policy_start_date    = $policy_start_date;
                    $proposal->policy_end_date      = $policy_end_date;
                    $proposal->save();
                
                    $updateJourneyStage['user_product_journey_id'] = $enquiryId;
                    $updateJourneyStage['ic_id'] = '40';
                    $updateJourneyStage['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                    $updateJourneyStage['proposal_id'] = $proposal->user_proposal_id;
                    updateJourneyStage($updateJourneyStage);

                    IciciLombardPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
                
                    return response()->json([
                        'status' => true,
                        'msg' => "Proposal Submitted Successfully!",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => camelCase([
                            'proposal_no'        => trim($proposalServiceResponse['generalInformation']['proposalNumber']),
                            'finalPayableAmount' => $proposalServiceResponse['finalPremium']
                        ]),
                    ]); 
                }
                else
                {
                    return [
                        'status' => false,
                        'premium' => '0',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $proposalServiceResponse['message'] ?? 'Service Issue'
                    ];
                }
            }
            else
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $reponse['message'] ?? 'Service Issue'
                ];            
            }
        }
        else
        {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => "Issue in Token Generation service"
            ];
        }       
    }
}
