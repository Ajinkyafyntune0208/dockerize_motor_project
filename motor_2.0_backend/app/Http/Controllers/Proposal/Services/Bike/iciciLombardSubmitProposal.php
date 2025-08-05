<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use App\Http\Controllers\SyncPremiumDetail\Bike\IciciLombardPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\IciciMmvMaster;
use App\Models\IciciRtoMaster;
use App\Models\IcVersionMapping;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MotorModelVersion;
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
use App\Http\Controllers\Proposal\Services\Bike\multiyear\iciciLombardSubmitProposalMultiyear;
use Illuminate\Support\Str;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';

class iciciLombardSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $additional_details = json_decode($proposal->additional_details);
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_log_premium_json = $quote_log->premium_json;

        $productData = getProductDataByIc($request['policyId']);

        if(in_array($productData->tenure , [11,22,33,01,02,03])){
            $a = new iciciLombardSubmitProposalMultiyear();
             return $a->submit($proposal, $request);
        }
        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $corporate_vehicle_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $proposerVehDet = $corporate_vehicle_quotes_request;
        $master_policy_id = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        
        $mmv = get_mmv_details($productData,$requestData->version_id,'icici_lombard');
        $IsPos = 'N';

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

        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
    

        $request['userProductJourneyId'] = customDecrypt($request['userProductJourneyId']);

        $requestData = getQuotation($request['userProductJourneyId']);
        $enquiryId = customDecrypt($request['enquiryId']);
        $additional_data = $proposal->additonal_data;



         // bike age calculation
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $bike_age = ceil($age / 12);
    




      

        $countrycode=100;
        $master_rto = MasterRto::where('rto_code', $requestData->rto_code)->first();
        if (empty($master_rto->icici_2w_location_code))
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
        $rto_cities = explode('/',  $rto_cities->rto_name);
        foreach($rto_cities as $rto_city)
        {
            $rto_city = strtoupper($rto_city);
            $rto_data = DB::table('bike_icici_lombard_rto_location')
                        ->where('txt_rto_location_desc', $state_name ."-". $rto_city)
                        ->first();
           
            if($rto_data)
            {
                
                break;
            }
        } */

        $rto_data = DB::table('bike_icici_lombard_rto_location')
                    ->where('txt_rto_location_code', $master_rto->icici_2w_location_code)
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
            $tenure_year = ($premium_type == 'third_party' && $requestData->business_type == 'newbusiness') ? 5 : 1;
            $policy_end_date = date('Y-m-d', strtotime(date('Y-m-d', strtotime("+$tenure_year year -1 day", strtotime(strtr($policy_start_date, ['-' => '']))))));
            $Tenure = '1';
            $TPTenure = '5';
            $first_reg_date = date('Y-m-d',strtotime($requestData->vehicle_register_date));
        }
        else
        {
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                $proposerVehDet->previous_policy_expiry_date =  date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                $isnoprevinsurance = 'true';
                
            }
            else
            {
                $isnoprevinsurance = 'false';
            }
            if($requestData->is_multi_year_policy == 'Y' && $premium_type != 'own_damage')
            {
                $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-5 year +1 day', strtotime($proposerVehDet->previous_policy_expiry_date)));
            }
            else
            {
               $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($proposerVehDet->previous_policy_expiry_date)));
            }
            
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($proposerVehDet->previous_policy_expiry_date));
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($proposerVehDet->previous_policy_expiry_date)));
            $date_diff = get_date_diff('day', $proposerVehDet->previous_policy_expiry_date);         
            if ($date_diff > 0)
            {   
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime(date('d-m-Y'))));           
            }       
            
            $PreviousPolicyType = ($requestData->previous_policy_type == 'Third-party') ? 'TP': 'Comprehensive Package';
            $BusinessType = 'Roll Over';
            
            $previous_vehicle_sale_date = date('Y-m-d',strtotime($requestData->vehicle_register_date));
            $first_reg_date = $previous_vehicle_sale_date;
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $Tenure = '1';
            $TPTenure = '1';

            $IsPreviousClaim = ($requestData->is_claim == 'Y') ? 'Y' : 'N';
            

            if ($requestData->is_claim == 'N' && $premium_type != 'third_party' && $date_diff < 90)
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
                'previousPolicyStartDate'    => $PrevYearPolicyStartDate,
                'previousPolicyEndDate'      => $PrevYearPolicyEndDate,
                'PreviousInsurerName'        => $proposal->previous_insurance_company,
                'ClaimOnPreviousPolicy'      => ($IsPreviousClaim == 'Y') ? true : false,
                'PreviousPolicyType'         => $PreviousPolicyType,
                'TotalNoOfODClaims'          => ($IsPreviousClaim == 'Y') ? '1' : '0',
                'BonusOnPreviousPolicy'      => $current_ncb_rate,
                'PreviousPolicyNumber'       => $proposal->previous_policy_number,
                'PreviousVehicleSaleDate'    => '',
                'NoOfClaimsOnPreviousPolicy' => ($IsPreviousClaim == 'Y') ? '1' : '0',
            ];
        }

        
        $IsConsumables = false;
        $IsRTIApplicableflag = false;
        $IsEngineProtectPlus = false;
        $LossOfPersonalBelongingPlanName = '';
        $KeyProtectPlan = '';
        $RSAPlanName = ''; 
        $tppd_limit = 0;  

        $IsVehicleHaveCNG = 'false';
        $IsVehicleHaveLPG = 'false';
        $SIVehicleHaveLPG_CNG = 0;
        $SIHaveElectricalAccessories = 0;
        $SIHaveNonElectricalAccessories = 0;
        $IsPACoverUnnamedPassenger = false;
        $SIPACoverUnnamedPassenger = 0;
        $llpd_flag = false;
        $voluntary_deductible_amount = 0;
        $eme_cover = false;
        $eme_Plan_name = '';          
        $geoExtension = false;
        $extensionCountryName = '';

        /* if($productData->zero_dep == 0 && $age < 5)
        {
            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver TW' : 'Silver';   
           
        }
        else
        {
            $ZeroDepPlanName = '';
        } */
        $ZeroDepPlanName = '';
        if($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '')
        {
            $addons = $selected_addons->applicable_addons;

            foreach ($addons as $value) {
          
               
               if($value['name'] == 'Road Side Assistance')
               {

                    $RSAPlanName =  'TW-299'; 
                    
               }
               if($value['name'] == 'Zero Depreciation')
               {

                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver TW' : 'Silver'; 
                    
               }
               if($value['name'] == 'Emergency Medical Expenses'  && $bike_age <= 15)
               {
                    $eme_cover = true;
                    $eme_Plan_name = 'Premium Segment';
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

        if($selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
        {
            $additional_covers = $selected_addons->additional_covers;

            foreach ($additional_covers as $value) 
            {
                if($value['name'] == 'Unnamed Passenger PA Cover' && $premium_type != 'own_damage') 
                {  
                    $SIPACoverUnnamedPassenger = $value['sumInsured'] * ($mmv_data->seatingcapacity);
                    $IsPACoverUnnamedPassenger = true;
                }
                if($value['name'] == 'LL paid driver' && $premium_type != 'own_damage')
                {
                    $llpd_flag = true;
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
            $tppd_limit = 0;
            $ispacoverownerdriver = 'false';
             
        }
         
       
        // token Generation
         $additionData = 
         [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'bike',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name
        ];
        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
            'scope' => 'esbmotor',
        ];


        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
       
        if (!empty($token)) 
        {
            $token = json_decode($token, true);
            
            if (isset($token['access_token'])) 
            {
                
                $access_token = $token['access_token'];

                $corelationId = getUUID($enquiryId);

                $isapprovalrequired = false;
                $breakingflag = false;
            
                $model_config_premium = [
                    'RegistrationNumber'            => str_replace('-', '', $proposal->vehicale_registration_number),
                    'EngineNumber'                  => $proposal->engine_number,
                    'ChassisNumber'                 => $proposal->chassis_number,
                    'VehicleDescription'            => '',
                    'VehicleModelCode'              => $mmv_data->vehiclemodelcode,
                    'RTOLocationCode'               => $rto_data->txt_rto_location_code,
                    'ManufacturingYear'             => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'ExShowRoomPrice'               => isset($quote_log_premium_json['showroomPrice']) ? $quote_log_premium_json['showroomPrice'] : '0',
                    'BusinessType'                  => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
                    'VehicleMakeCode'               => $mmv_data->manufacturer_code,
                    'Tenure'                        => '1',
                    // 'PACoverTenure'                 => '1',
                    'PACoverTenure'                 => $PACoverTenure,
                    'IsLegaLiabilityToWorkmen'      => false,
                    'NoOfWorkmen'                   => 0,
                    'DeliveryOrRegistrationDate'    => date('Y-m-d',strtotime($vehicleDate)),
                    'FirstRegistrationDate'         => $first_reg_date,
                    'PolicyStartDate'               => $policy_start_date,
                    'PolicyEndDate'                 => $policy_end_date,
                    'GSTToState'                    => $state_name,
                    'IsPACoverOwnerDriver'          => $ispacoverownerdriver,
                    'IsPACoverWaiver'               => ($ispacoverownerdriver == 'true') ? 'false' : 'true',            //pass true in case of CPA is not opted
                    'IsNoPrevInsurance'             => $isnoprevinsurance,
                    'CustomerType'                  => $customertype,
                    'IsAntiTheftDisc'               => false,
                    'IsHandicapDisc'                => false,
                    'IsExtensionCountry'            => $geoExtension,
                    'ExtensionCountryName'          => $extensionCountryName,
                    'IsMoreThanOneVehicle'          => false,
                    'IsLegalLiabilityToPaidDriver'  => $llpd_flag,
                    'IsLegalLiabilityToPaidEmployee'=> false,
                    'NoOfEmployee'                  => 0,
                    'IsVoluntaryDeductible' =>  ($voluntary_deductible_amount != 0) ? false : false,
                    'VoluntaryDeductiblePlanName' => ($voluntary_deductible_amount != 0) ? 0: 0,
                    'IsTransferOfNCB'               => false,
                    'NoOfDriver'                    => 1,                        
                    'ZeroDepPlanName'               => $ZeroDepPlanName,                        
                    'RSAPlanName'                   => $RSAPlanName,
                    'IsValidDrivingLicense'         => false,
                    'IsAutomobileAssocnFlag'        => false,
                    'AutomobileAssociationNumber'   => false,
                    'IsRTIApplicableflag'           => false,
                    'IsPACoverUnnamedPassenger'     => $IsPACoverUnnamedPassenger,
                    'SIPACoverUnnamedPassenger'     => $SIPACoverUnnamedPassenger,
                    'IsHaveElectricalAccessories'   => false,
                    'SIHaveElectricalAccessories'   => 0,
                    'IsHaveNonElectricalAccessories'=> false,
                    'SIHaveNonElectricalAccessories'=> 0,
                    'OtherLoading'                  => 0,
                    // 'TPPDLimit'                     => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                    'TPPDLimit'                     => 100000, // As per git id 15093
                    'CorrelationId'                 => $corelationId,
                ];
                 
                 
                if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') 
                {
                    $model_config_premium['TPTenure'] = 1; 
                    $model_config_premium['PreviousPolicyDetails'] = $PreviousPolicyDetails;
                    if($IsPreviousClaim == 'Y')
                    {
                        $model_config_premium['PreviousPolicyDetails']['NoOfClaimsOnPreviousPolicy'] = 1;
                        
                    }   
                } 
                else 
                {
                    $model_config_premium['Tenure'] = 1;
                    $model_config_premium['TPTenure'] = 5;
                    $model_config_premium['PACoverTenure'] = $PACoverTenure;
                }
                if($premium_type == 'own_damage') 
                {
                    if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                        $model_config_premium['TPStartDate'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                        $model_config_premium['TPEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                        $model_config_premium['TPInsurerName'] = $proposal->tp_insurance_company;
                        $model_config_premium['TPPolicyNo'] = $proposal->tp_insurance_number;
                        $model_config_premium['PreviousPolicyDetails']['PreviousPolicyType'] = 'Bundled Package Policy';
                    }
                    $model_config_premium['Tenure'] = 1;
                    $model_config_premium['TPTenure'] = 0;
                    $model_config_premium['IsLegalLiabilityToPaidDriver'] = false;
                    $model_config_premium['IsPACoverOwnerDriver'] = false;
                    $model_config_premium['IsPACoverUnnamedPassenger'] = false;
                }

                #for adding product code
                $ProductCode = '';

                if($IsPos == 'N')
                {
                    switch($premium_type)
                    {
                        case "comprehensive":
                            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
                            $ProductCode = '2312';
                        break;
                        case "own_damage":
                            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
                            $ProductCode = '2312';
                        break;
                        case "third_party":
                            $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
                            $ProductCode = '2320';
                        break;

                    }

                    if($requestData->business_type == 'breakin' && $premium_type != 'third_party')
                    {
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_BREAKIN');
                        $ProductCode = '2312';
                    }
                  
                }

                //query for fetching POS details
                $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo =  '';
                $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                $is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
                $pos_testing_mode = config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');

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
                    // $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
                }
                elseif($pos_testing_mode === 'Y' && $quote_log->idv <= 5000000)
                {
                    $IsPos = 'Y';
                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                    $CertificateNumber = 'TMI0001';
                    $PanCardNo = 'ABGTY8890Z';
                    $AadhaarNo = '569278616999';
                    // $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_BIKE');
                }
                else
                {
                    $model_config_premium['DealId'] = $deal_id;
                }


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

            
                if($premium_type == 'third_party') 
                {
                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_BIKE_TP');
                }
                else
                {
                    
                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_BIKE');
                }

               
                if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') 
                {
                    unset($model_config_premium['PreviousPolicyDetails']);
                }
                if($eme_cover)
                {
                    $model_config_premium['EMECover'] = $eme_Plan_name;
                    $model_config_premium['NoOfPassengerHC'] = $mmv_data->seatingcapacity - 1;
                }
                $additionPremData = [
                    'requestMethod' => 'post',
                    'type' => 'premiumCalculation',
                    'section' => 'bike',
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
                if ($proposal->is_vehicle_finance == '1') {
                    $model_config_premium["FinancierDetails"] = [
                        "FinancierName" => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : "",
                        "BranchName" => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : "",
                        "AgreementType" => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : ""
                    ];
                }
                $get_response = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);
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
                            'message' => json_encode($premiumResponse)
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

                        $proposal_array = 
                        [
                            'RegistrationNumber'            => ($requestData->business_type == 'newbusiness') ? 'New' : str_replace('-', '', $proposal->vehicale_registration_number),
                            'EngineNumber'                  => $proposal->engine_number,
                            'ChassisNumber'                 => $proposal->chassis_number,
                            'VehicleDescription'            => '',
                            'VehicleModelCode'              => $mmv_data->vehiclemodelcode,
                            'RTOLocationCode'               => $rto_data->txt_rto_location_code,
                            'ManufacturingYear'             => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            'ExShowRoomPrice'               =>  $premiumResponse['generalInformation']['showRoomPrice'],
                            'BusinessType'                  => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
                            'VehicleMakeCode'               => $mmv_data->manufacturer_code,
                            'Tenure'                        => '1',
                            // 'PACoverTenure'                 => '1',
                            'PACoverTenure'                 => $PACoverTenure,
                            'DeliveryOrRegistrationDate'    => date('Y-m-d',strtotime($vehicleDate)),
                            'FirstRegistrationDate'         => $first_reg_date,
                            'PolicyStartDate'               => $policy_start_date,
                            'PolicyEndDate'                 => $policy_end_date,
                            'GSTToState'                    => $state_name,
                            'IsPACoverOwnerDriver'          => $ispacoverownerdriver,
                            'IsPACoverWaiver'               => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                            'IsNoPrevInsurance'             => $isnoprevinsurance,
                            'CustomerType'                  => $customertype,
                            'IsAntiTheftDisc'               => false,
                            'IsHandicapDisc'                => false,
                            'IsExtensionCountry'            => $geoExtension,
                            'ExtensionCountryName'          => $extensionCountryName,
                            'IsMoreThanOneVehicle'          => false,
                            'IsLegalLiabilityToPaidDriver'  => $llpd_flag,
                            'IsLegalLiabilityToPaidEmployee'=> false,
                            'NoOfEmployee'                  => 0,
                            'IsVoluntaryDeductible' =>  ($voluntary_deductible_amount != 0) ? false : false,
                            'VoluntaryDeductiblePlanName' => ($voluntary_deductible_amount != 0) ? 0 : 0,
                            'IsTransferOfNCB'               => false,
                            'NoOfDriver'                    => 0,
                            'ZeroDepPlanName'               => $ZeroDepPlanName,                        
                            'RSAPlanName'                   => $RSAPlanName,
                            'IsValidDrivingLicense'         => false,
                            'IsAutomobileAssocnFlag'        => false,
                            'AutomobileAssociationNumber'   => false,
                            'IsRTIApplicableflag'           => false,
                            'IsPACoverUnnamedPassenger'     => $IsPACoverUnnamedPassenger,
                            'SIPACoverUnnamedPassenger'     => $SIPACoverUnnamedPassenger,
                            'IsHaveElectricalAccessories'   => false,
                            'SIHaveElectricalAccessories'   => 0,
                            'IsHaveNonElectricalAccessories'=> false,
                            'SIHaveNonElectricalAccessories'=> 0,
                            'OtherLoading'                  => 0,
                            // 'TPPDLimit'                     => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                            'TPPDLimit'                     => 100000, // As per git id 15093
                            'CustomerDetails' => [
                                'CustomerType' => $customertype,
                                'CustomerName' => $requestData->vehicle_owner_type == 'I'? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name,
                                'DateOfBirth' => $requestData->vehicle_owner_type == 'I' ? date('Y-m-d', strtotime($proposal->dob)):'',
                                'PinCode' => $proposal->pincode,
                                'PANCardNo' => ($proposal->pan_number != '') ? $proposal->pan_number : '',
                                'Email' => $proposal->email,
                                'MobileNumber' => $proposal->mobile_number,
                                'AddressLine1' => $getAddress['address_1'].''.$getAddress['address_2'],//$proposal->address_line1 . ' ' . $proposal->address_line2 . ' '. $proposal->address_line3,
                                'CountryCode' => $countrycode,
                                'StateCode' => $additional_details->owner->stateId,
                                'CityCode' => $additional_details->owner->cityId,
                                'MobileISD'                 => '91',
                                'Gender'                    => ($proposal->gender == 'MALE')? 'Male':'Female',
                            ],
                            'CorrelationId'                 =>$corelationId,
                        ];

                        

                        if ($premium_type == 'own_damage') 
                        {
                            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                                $proposal_array['TPStartDate'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                                $proposal_array['TPEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                                $proposal_array['TPInsurerName'] =  $proposal->tp_insurance_company;
                                $proposal_array['TPPolicyNo'] = $proposal->tp_insurance_number;
                            }
                            
                            $proposal_array['Tenure'] = 1;
                            $proposal_array['TPTenure'] = 0;
                            $proposal_array['IsLegalLiabilityToPaidDriver'] = false;
                            $proposal_array['IsPACoverOwnerDriver'] = false;
                            $proposal_array['IsPACoverUnnamedPassenger'] = false;
                        }
                        else
                        {
                            $proposal_array['TPTenure'] = 1;
                        }
                       
                        
                        
                        if ($proposal->gst_number != '') 
                        {
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

                        if ($requestData->business_type == 'newbusiness') 
                        {
                            unset($proposal_array['PreviousPolicyDetails']);
                            $proposal_array['Tenure'] = 1; 
                            $proposal_array['TPTenure'] = 5; 
                            $proposal_array['PACoverTenure'] = $PACoverTenure; 

                            
                        }
                        else
                        {
                          
                           
                           

                            $proposal_array['PreviousPolicyDetails'] 
                                = 
                            [
                                    'PreviousPolicyType'        => $PreviousPolicyType,
                                    'BonusOnPreviousPolicy'     => $current_ncb_rate,
                                    'NoOfClaimsOnPreviousPolicy'=> ($IsPreviousClaim == 'Y') ? '1' : '0',                        
                                    'PreviousPolicyStartDate'   => $PrevYearPolicyStartDate, 
                                    'PreviousPolicyEndDate'     => $PrevYearPolicyEndDate,                        
                                    'ClaimOnPreviousPolicy'     => ($IsPreviousClaim == 'Y') ? true : false, 
                                    'TotalNoOfODClaims'         => ($IsPreviousClaim == 'Y') ? '1' : '0',
                                    'PreviousInsurerName'       => $proposal->previous_insurance_company,
                                    'PreviousPolicyNumber'      => $proposal->previous_policy_number,
                                    'PreviousPolicyTenure'      => '1'
                            ]; 
                            if($premium_type == 'own_damage')
                            {
                                $proposal_array['PreviousPolicyDetails']['PreviousPolicyType'] = 'Bundled Package Policy';
                            }
                            
                        }

                        if($IsPos == 'N')
                        {
                            $proposal_array['DealId'] = $deal_id;
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

                        
                        
                        if($premium_type == 'third_party')
                        {
                            $url = config('constants.IcConstants.icici_lombard.PROPOSAL_END_POINT_URL_ICICI_LOMBARD_BIKE_TP'); 
                        }else
                        {
                            $url = config('constants.IcConstants.icici_lombard.PROPOSAL_END_POINT_URL_ICICI_LOMBARD_BIKE'); 
                        }

                        if($requestData->previous_policy_type == 'Not sure')
                        {
                            unset($proposal_array['PreviousPolicyDetails']);
                        }
                        if($eme_cover)
                        {
                            $proposal_array['EMECover'] = $eme_Plan_name;
                            $proposal_array['NoOfPassengerHC'] = $mmv_data->seatingcapacity - 1;
                        }
                        $additionPremData = [
                            'requestMethod' => 'post',
                            'type' => 'proposalService',
                            'section' => 'bike',
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
                        if ($proposal->is_vehicle_finance == '1') {
                            $proposal_array["FinancierDetails"] = [
                                "FinancierName" => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : "",
                                "BranchName" => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : "",
                                "AgreementType" => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : ""
                            ];
                        }
                        $get_response = getWsData($url, $proposal_array, 'icici_lombard', $additionPremData);
                        $proposalServiceResponse = $get_response['response'];
                        

                        if (!empty($proposalServiceResponse)) {
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
                           
                            if ($arr_premium['status'] == 'Success') 
                            {
                                $msg = '';
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
                                // if ((isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && isset($arr_premium['isQuoteDeviation'])) && (($arr_premium['breakingFlag'] == true) && ($arr_premium['isApprovalRequired'] == true)) && ($arr_premium['isQuoteDeviation'] == false)) {
                                //     $msg = "Proposal application didn't pass underwriter approval";
                                //     return response()->json([
                                //         'status' => false,
                                //         'webservice_id' => $get_response['webservice_id'],
                                //         'table' => $get_response['table'],
                                //         'message' => $msg
                                //     ]);
                                // }
                                // if ((isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && isset($arr_premium['isQuoteDeviation'])) && ($arr_premium['breakingFlag'] == true) && (($arr_premium['isApprovalRequired'] == false) && ($arr_premium['isQuoteDeviation'] == false))) {
                                //     $msg = "Proposal application didn't pass underwriter approval";
                                //     return response()->json([
                                //         'status' => false,
                                //         'webservice_id' => $get_response['webservice_id'],
                                //         'table' => $get_response['table'],
                                //         'message' => $msg
                                //     ]);
                                // }

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
                                $rsa = $zero_dept = $eng_protect = $key_replace = $consumable_cover = $return_to_invoice = $loss_belongings = $cpa_cover = 0 ;
                                $total_od = $total_tp = $total_discount = $basePremium = $totalTax = $final_premium = $addon_premium = $eme_cover_amount = 0;
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



                                $tp_premium = $arr_premium['totalLiabilityPremium'] ?? 0;
                                $tyre_secure = ($arr_premium['riskDetails']['tyreProtect'] ?? 0);
                                $lpg_cng_tp = isset($arr_premium['riskDetails']['biFuelKitTP']) ? ($arr_premium['riskDetails']['biFuelKitTP']) : '0';
                                $llpd_amt = isset($arr_premium['riskDetails']['paidDriver']) ? ($arr_premium['riskDetails']['paidDriver']) : '0' ;
                                $unnamed_pa_amt = isset($arr_premium['riskDetails']['paCoverForUnNamedPassenger']) ? $arr_premium['riskDetails']['paCoverForUnNamedPassenger'] : '0';
                                $rsa = isset($arr_premium['riskDetails']['roadSideAssistance']) ? $arr_premium['riskDetails']['roadSideAssistance'] : '0';
                                $zero_dept = isset($arr_premium['riskDetails']['zeroDepreciation']) ? $arr_premium['riskDetails']['zeroDepreciation'] : '0';
                                $eng_protect = isset($arr_premium['riskDetails']['engineProtect']) ? $arr_premium['riskDetails']['engineProtect'] : '0';
                                $key_replace = isset($arr_premium['riskDetails']['keyProtect']) ? $arr_premium['riskDetails']['keyProtect'] : '0';
                                $consumable_cover = isset($arr_premium['riskDetails']['consumables']) ? $arr_premium['riskDetails']['consumables'] : '0';
                                $return_to_invoice = isset($arr_premium['riskDetails']['returnToInvoice']) ? $arr_premium['riskDetails']['returnToInvoice'] : '0';
                                $loss_belongings = isset($arr_premium['riskDetails']['lossOfPersonalBelongings']) ? $arr_premium['riskDetails']['lossOfPersonalBelongings'] : '0';
                                $cpa_cover = isset($arr_premium['riskDetails']['paCoverForOwnerDriver']) ? $arr_premium['riskDetails']['paCoverForOwnerDriver'] : '0';
                                $eme_cover_amount =  $arr_premium['riskDetails']['emeCover']?? 0;

                                if(isset($arr_premium['riskDetails']['voluntaryDiscount']))
                                {
                                    $voluntary_deductible = $arr_premium['riskDetails']['voluntaryDiscount'];
                                }
                                else
                                {
                                    $voluntary_deductible = voluntary_deductible_calculation($od_premium,$requestData->voluntary_excess_value,'bike');

                                }


                                $total_od = $basicOD + $elect_acc + $non_elec_acc + $lpg_cng_od + $geog_Extension_OD_Premium; //+ $breakingLoadingAmt + $elect_acc + $non_elec_acc + $lpg_cng_od;
                                $total_tp = $tp_premium + $geog_Extension_TP_Premium;// + $llpd_amt + $unnamed_pa_amt + $lpg_cng_tp + $cpa_cover;

                                $addon_premium = $rsa + $zero_dept + $eng_protect + $key_replace + $consumable_cover + $return_to_invoice + $loss_belongings + $eme_cover_amount ;
                                $total_discount = $ncb_discount + $automobile_assoc + $anti_theft + $voluntary_deductible;// + $tppd_discount;
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

                                # for breakin
                                if ($premium_type != 'third_party') {

                                    // * breakin id generation
                                    if (($arr_premium['status'] == 'Success') && ($arr_premium['breakingFlag'] == '1' || $arr_premium['breakingFlag'] == true) && ($mmv_data->cubiccapacity >= 350)) {
                                        $is_breakin_case = 'Y';
                                        $inspection_type_self = $proposal->inspection_type == 'Manual' ? 'No' : 'Yes';


                                        $breakin_create_array = [
                                            'CorrelationId' => $corelationId,
                                            'BreakInType' => 'Break-in Policy lapse',
                                            'BreakInDays' => $breakin_days ?? 0,
                                            'CustomerName' => $requestData->vehicle_owner_type == 'I' ? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name,
                                            'CustomerAddress' => $proposal->address_line1 . ' ' . $proposal->address_line2 . ' ' . $proposal->address_line3,
                                            'State' => $proposal->state,
                                            'City' => $proposal->city,
                                            'MobileNumber' => $proposal->mobile_number,
                                            'TypeVehicle' => $mmv_data->carcategory == 'Scooter' ? "Scooter" : "MotorCycle",
                                            'VehicleMake' => $mmv_data->manufacturer_name,
                                            'VehicleModel' => $mmv_data->vehiclemodel,
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

                                        if ($IsPos == 'Y') {
                                            if (isset($breakin_create_array['DealId'])) {
                                                unset($breakin_create_array['DealId']);
                                            }
                                        } else {
                                            if (!isset($breakin_create_array['DealId'])) {
                                                $breakin_create_array['DealId'] = $deal_id;
                                            }
                                        }

                                        $additionPremData = [
                                            'requestMethod' => 'post',
                                            'type' => 'Break-in Id Generation',
                                            'section' => 'bike',
                                            'token' => $access_token,
                                            'enquiryId' => $enquiryId,
                                            'transaction_type' => 'proposal',
                                            'productName'  => $productData->product_name
                                        ];

                                        if ($IsPos == 'Y') {
                                            $pos_details = [
                                                'pos_details' => [
                                                    'IRDALicenceNumber' => $IRDALicenceNumber,
                                                    'CertificateNumber' => $CertificateNumber,
                                                    'PanCardNo'         => $PanCardNo,
                                                    'AadhaarNo'         => $AadhaarNo,
                                                    'ProductCode'       => $ProductCode
                                                ]
                                            ];
                                            $additionPremData = array_merge($additionPremData, $pos_details);
                                        }

                                        $url = config('constants.IcConstants.icici_lombard.GENERATE_BREAKINID_END_POINT_URL_ICICI_LOMBARD_MOTOR');

                                        $get_response = getWsData($url, $breakin_create_array, 'icici_lombard', $additionPremData);
                                        $breakin_data = $get_response['response'];
                                        

                                        $breakin_id_response = json_decode($breakin_data, true);



                                        if ($breakin_id_response) {


                                            if ($breakin_id_response['status'] == 'Success') {

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

                                                $cpa_end_date = '';

                                                if ($ispacoverownerdriver == 'true' && $requestData->business_type == 'newbusiness') {
                                                    $cpa_end_date = date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date)));
                                                } else if ($ispacoverownerdriver == 'true' && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                                                    $cpa_end_date = date('d-m-Y', strtotime($policy_end_date));
                                                }

                                                $vehDetails['manf_name'] = $mmv_data->manufacturer_name;
                                                $vehDetails['vehiclemodel'] = $mmv_data->vehiclemodel;
                                                $vehDetails['version_name'] = $mmv_data->vehiclemodel;
                                                $vehDetails['version_id'] = $mmv_data->vehiclemodelcode;
                                                $vehDetails['seatingcapacity'] = $mmv_data->seatingcapacity;
                                                $vehDetails['cubic_capacity'] = $mmv_data->cubiccapacity;
                                                $vehDetails['fuel_type'] = $mmv_data->fueltype;
                                                $vehDetails['bike_segment'] = $mmv_data->carsegment;

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
                                            } else {
                                                if (isset($breakin_id_response['message'])) {
                                                    $contains = Str::contains($breakin_id_response['message'], 'Already on this combination Break-in ID : ');
                                                    if ($contains) {
                                                        $breakin_id_array = explode(' ', $breakin_id_response['message']);
                                                        $breakin_id_response['brkId'] = $breakin_id_array[7];

                                                        return response()->json([
                                                            'status' => false,
                                                            'webservice_id' => $get_response['webservice_id'],
                                                            'table' => $get_response['table'],
                                                            'message' => $breakin_id_response['message'],
                                                        ]);

                                                     
                                                    } else {
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
                                                                'is_breakin' => $is_breakin_case,
                                                            ]
                                                        ]);
                                                    }
                                                } else {
                                                    return response()->json([
                                                        'status' => false,
                                                        'webservice_id' => $get_response['webservice_id'],
                                                        'table' => $get_response['table'],
                                                        'message' => "Error in breakin ID creation service"
                                                    ]);
                                                }
                                            }
                                        } else {
                                            return response()->json([
                                                'status' => false,
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'message' => "Error in breakin ID creation service"
                                            ]);
                                        }
                                    }
                                }
                                updateJourneyStage([
                                        'user_product_journey_id' => $request['userProductJourneyId'],
                                        'ic_id' => $productData->company_id,
                                        'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                        'proposal_id' => $proposal->user_proposal_id
                                    ]);
                                
                                $vehDetails['manf_name'] = $mmv_data->manufacturer_name;
                                $vehDetails['vehiclemodel'] = $mmv_data->vehiclemodel;
                                $vehDetails['version_name'] = $mmv_data->vehiclemodel;
                                $vehDetails['version_id'] = $mmv_data->vehiclemodelcode;
                                $vehDetails['seatingcapacity'] = $mmv_data->seatingcapacity;
                                $vehDetails['cubic_capacity'] = $mmv_data->cubiccapacity;
                                $vehDetails['fuel_type'] = $mmv_data->fueltype;
                                $vehDetails['bike_segment'] = $mmv_data->carsegment;
                                $cpa_end_date = '';
                                if($ispacoverownerdriver == 'true' && $requestData->business_type == 'newbusiness')
                                {
                                    $cpa_end_date=date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date)));
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
                                $updateProposal = UserProposal::where('user_product_journey_id', $request['userProductJourneyId'])
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'proposal_no' => $arr_premium['generalInformation']['proposalNumber'],
                                        'customer_id' => $arr_premium['generalInformation']['customerId'],
                                        'unique_proposal_id' => $corelationId,
                                        'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                                        'policy_end_date' =>  date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                                        'od_premium' => ($total_od) - ($total_discount),
                                        'tp_premium' => $total_tp,
                                        'addon_premium' => $addon_premium,
                                        'cpa_premium' => $cpa_cover,
                                        'service_tax_amount' => $totalTax,
                                        'total_discount' => $total_discount + $tppd_discount,
                                        'total_premium'  => $basePremium,
                                        'final_payable_amount' => $final_premium,
                                        'ic_vehicle_details' => json_encode($vehDetails),
                                        'is_breakin_case' =>   'N',
                                        'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime($policy_start_date)),
                                        'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))) : date('d-m-Y',strtotime($policy_end_date))),
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
        
                                IciciLombardPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                                if ($updateProposal) {
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
                            } elseif ($arr_premium['status'] == 'Failed') {
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
                             return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $msg
                           ]);
                        }
                    } elseif ($premiumResponse['status'] == 'Failed') {
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
        if(config('constant.ICICI_LOMBARD_BIKE_NEW_RENEWAL_FLOW') == 'Y') {
          return self::newRenewalSubmit($proposal,$request);
        }
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        // bike age calculation
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $bike_age = ceil($age / 12);
        
        $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        
        $mmv_data = [
            'manf_name'             => $mmv->manufacturer_name,
            'model_name'            => $mmv->vehiclemodel,
            'version_name'          => '',
            'seating_capacity'      => $mmv->seatingcapacity,
            'carrying_capacity'     => $mmv->carryingcapacity,
            'cubic_capacity'        => $mmv->cubiccapacity,
            'fuel_type'             => $mmv->fueltype,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'BIKE',
            'version_id'            => $mmv->ic_version_code,
        ];        
        
        $additionData = [
            'requestMethod'     => 'post',
            'type'              => 'tokenGeneration',
            'section'           => 'BIKE',
            'productName'       => $productData->product_name,
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'proposal'
        ];

        $tokenParam = [
            'grant_type'    => 'password',
            'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
            'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
            'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
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
        
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
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
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Insurer not reachable,Issue in Token Generation service"
                ];
            }

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
            
            switch($premium_type)
            {
                case "comprehensive":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
                break;

                case "own_damage":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
                break;

                case "third_party":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
                break;
            }

            $corelationId = getUUID($enquiryId);
            
            //$DealID = 'DL-3001/913908';
            $DealID = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $deal_id : config('DEAL_ID_ICICI_LOMBARD_TESTING');
             
            $fetch_policy_data = [	
                "PolicyNumber"              => $proposal->previous_policy_number,
                "CorrelationId"             => $corelationId,
                "EngineNumberLast5Char"     => substr($proposal->engine_number,-5),
                "ChassisNumberLast5Char"    => substr($proposal->chassis_number,-5),
                "DealID"                    => $DealID,
                "TenureList"                => $premium_type == 'own_damage' ? [6] : [1]
            ];
            $additionPremData = [
                'requestMethod'     => 'post',
                'type'              => 'Fetch Policy Details',
                'section'           => 'BIKE',
                'productName'       => $productData->product_name,
                'enquiryId'         => $enquiryId,
                'transaction_type'  => 'proposal',
                'token'             => $access_token
            ];
            //$url  = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/TwoWheeler/Fetch/';
            $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_BIKE_FETCH_POLICY_DATA');
            $get_response = getWsData($url, $fetch_policy_data, 'icici_lombard', $additionPremData);
            $data = $get_response['response'];

            $reponse            = json_decode($data,true);
            //if(isset($reponse['status']) && $reponse['status'] == true)
            if(isset($reponse['status']) && $reponse['status'] == true && 
                $reponse['proposalDetails'][0]['isQuoteDeviation'] == false && 
                $reponse['proposalDetails'][0]['breakingFlag'] == false &&
                $reponse['proposalDetails'][0]['isApprovalRequired'] == false
                )
            {
                $proposalDetails            = $reponse['proposalDetails'][0];        
                $previousPolicyDetails      = $reponse['previousPolicyDetails']; 
                $vehicleDetails      = $reponse['vehicleDetails']; 
                
                $riskDetails        = $proposalDetails['riskDetails'];
                $generalInformation = $proposalDetails['generalInformation'];
               
                $policy_start_date = str_replace('/','-',$generalInformation['policyInceptionDate']);
                $policy_end_date = str_replace('/','-',$generalInformation['policyEndDate']);
               
                $registrationDate = str_replace('/','-',$generalInformation['registrationDate']);
                
                $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
                
                
                $IsConsumables = false;
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
                            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver TW' : 'Silver';
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
                            $rsa_amount = $riskDetails['roadSideAssistance'];
                            if($rsa_amount == '199.0')
                            {
                                $RSAPlanName = 'TW-199'; 
                            }else if($rsa_amount == '299.0')
                            {
                                $RSAPlanName = 'TW-299'; 
                            }else
                            {
                                $RSAPlanName = 'TW-199'; 
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
                       }
                    }
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
                $tppd_limit = 0;
                
        
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
                            $SIPACoverUnnamedPassenger = ($value['sumInsured'] * $mmv->seatingcapacity);

                       }
                       if($value['name'] == 'LL paid driver')
                       {
                            $IsLLPaidDriver = true;
                       }
                    }
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

                if (config('ICICI_LOMBARD_RENEWAL_IDV_SERVICE_BIKE') == 'Y') {
                    $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
                    $premiumJson = $quoteLog->premium_json;
                    $showroomPrice = $premiumJson['showroomPrice'] ?? 0;
                } else {
                    $showroomPrice = $generalInformation['showRoomPrice'];
                }

                $rto_cities = MasterRto::where('rto_code', $requestData->rto_code)->first();
                $state_id = $rto_cities->state_id;
                $state_name = MasterState::where('state_id', $state_id)->first();
                $state_name = strtoupper($state_name->state_name);
                //echo "EEEE";
                $proposal_submit_data = [
                    'PolicyNumber'                  => $previousPolicyDetails['previousPolicyNumber'],
                    'ProposalRefNo'                 => !empty($generalInformation['referenceProposalNo']) ? $generalInformation['referenceProposalNo'] : 0,
                    'CustomerID'                    => $generalInformation['customerId'],
                    'DealID'                        => $DealID,
                    'EngineNumberLast5Char'         => substr($vehicleDetails['engineNumber'],-5),
                    'ChassisNumberLast5Char'        => substr($vehicleDetails['chassisNumber'],-5),
                    'IsCustomerModified'            => false,
                    'CorrelationId'                 => $corelationId,
                    "TenureList"                    => $premium_type == 'own_damage' ? [6] : [1],
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
                        'TPTenure'                  => 1,
                        'IsVehicleHaveLPG'          => $IsVehicleHaveLPG,
                        'IsVehicleHaveCNG'          => $IsVehicleHaveCNG,
                        'SIVehicleHaveLPG_CNG'      => $SIVehicleHaveLPG_CNG,
                        // 'TPPDLimit'                 => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                        'TPPDLimit'                     => 100000, // As per git id 15093
                        'SIHaveElectricalAccessories' => $SIHaveElectricalAccessories,
                        'SIHaveNonElectricalAccessories' => $SIHaveNonElectricalAccessories,
                        'IsPACoverUnnamedPassenger' => $IsPACoverUnnamedPassenger,
                        'SIPACoverUnnamedPassenger' => $SIPACoverUnnamedPassenger,
                        'IsFiberGlassFuelTank'      => false,
                        'IsVoluntaryDeductible'         => ($voluntary_deductible_amount != 0) ? false : false,
                        'VoluntaryDeductiblePlanName'   => ($voluntary_deductible_amount != 0) ? 0 : 0,
                        'IsAutomobileAssocnFlag' => false,
                        'IsAntiTheftDisc' => false,
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
                    'requestMethod'     => 'post',
                    'type'              => 'Renewal Proposal Service',
                    'section'           => 'BIKE',
                    'token'             => $access_token,
                    'enquiryId'         => $enquiryId,
                    'transaction_type'  => 'proposal',
                    'productName'       => $productData->product_name
                ];
                //$url = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/TwoWheeler/RenewPolicy';
                $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_BIKE_PROPOSAL_RENEWAL_SUBMIT');
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
                    $proposal->proposal_no          = $proposalServiceResponse['generalInformation']['proposalNumber'];
                    $proposal->unique_proposal_id   = $corelationId;
                    $proposal->customer_id          = $proposalServiceResponse['generalInformation']['customerId'];
                    $proposal->od_premium           = $od_premium;
                    $proposal->tp_premium           = $proposalServiceResponse['totalLiabilityPremium'];
                    $proposal->ncb_discount         = ($reponse['proposalDetails']['bonusDiscount'] ?? ($proposalRiskDetails['bonusDiscount'] ?? 0));
                    $proposal->addon_premium        = $addon_premium;
                    $proposal->total_premium        = $proposalServiceResponse['packagePremium'];
                    $proposal->service_tax_amount   = $proposalServiceResponse['totalTax'];
                    $proposal->final_payable_amount = $proposalServiceResponse['finalPremium'];
                    $proposal->cpa_premium          = ($reponse['proposalDetails']['paCoverForOwnerDriver'] ?? ($proposalRiskDetails['paCoverForOwnerDriver'] ?? 0));
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
                            'proposal_no'        => $proposalServiceResponse['generalInformation']['proposalNumber'],
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
                        'message' => $proposalServiceResponse['ProposalRefNo'][0] ?? ($proposalServiceResponse['message'] ?? 'Insurer not reachable')
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
    public static function newRenewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        // bike age calculation
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $bike_age = ceil($age / 12);
        
        $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        
        $mmv_data = [
            'manf_name'             => $mmv->manufacturer_name,
            'model_name'            => $mmv->vehiclemodel,
            'version_name'          => '',
            'seating_capacity'      => $mmv->seatingcapacity,
            'carrying_capacity'     => $mmv->carryingcapacity,
            'cubic_capacity'        => $mmv->cubiccapacity,
            'fuel_type'             => $mmv->fueltype,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'BIKE',
            'version_id'            => $mmv->ic_version_code,
        ];        
        
        $additionData = [
            'requestMethod'     => 'post',
            'type'              => 'tokenGeneration',
            'section'           => 'BIKE',
            'productName'       => $productData->product_name,
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'proposal'
        ];

        $tokenParam = [
            'grant_type'    => 'password',
            'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
            'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
            'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
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
        
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
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
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Insurer not reachable,Issue in Token Generation service"
                ];
            }

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
            
            switch($premium_type)
            {
                case "comprehensive":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
                break;

                case "own_damage":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
                break;

                case "third_party":
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
                break;
            }

            // $corelationId = getUUID($enquiryId);
            $corelationId = ($proposal->unique_proposal_id);
            
            //$DealID = 'DL-3001/913908';
            $DealID = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $deal_id : config('DEAL_ID_ICICI_LOMBARD_TESTING');
             
            $fetch_policy_data = [	
                "PolicyNumber"              => $proposal->previous_policy_number,
                "CorrelationId"             => $corelationId,
                "EngineNumberLast5Char"     => substr($proposal->engine_number,-5),
                "ChassisNumberLast5Char"    => substr($proposal->chassis_number,-5),
                "DealID"                    => $DealID,
                "TenureList"                => $premium_type == 'own_damage' ? [6] : [1]
            ];
            $additionPremData = [
                'requestMethod'     => 'post',
                'type'              => 'Fetch Policy Details',
                'section'           => 'BIKE',
                'productName'       => $productData->product_name,
                'enquiryId'         => $enquiryId,
                'transaction_type'  => 'proposal',
                'token'             => $access_token
            ];
            //$url  = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/TwoWheeler/Fetch/';
            $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_BIKE_FETCH_POLICY_DATA');
            $get_response = getWsData($url, $fetch_policy_data, 'icici_lombard', $additionPremData);
            $data = $get_response['response'];

            $reponse            = json_decode($data,true);
            //if(isset($reponse['status']) && $reponse['status'] == true)
            if(isset($reponse['status']) && $reponse['status'] == true && 
                $reponse['proposalDetails'][0]['isQuoteDeviation'] == false && 
                $reponse['proposalDetails'][0]['breakingFlag'] == false &&
                $reponse['proposalDetails'][0]['isApprovalRequired'] == false
                )
            {
                $proposalDetails            = $reponse['proposalDetails'][0];        
                $previousPolicyDetails      = $reponse['previousPolicyDetails']; 
                $vehicleDetails      = $reponse['vehicleDetails']; 
                
                $riskDetails        = $proposalDetails['riskDetails'];
                $generalInformation = $proposalDetails['generalInformation'];
               
                $policy_start_date = str_replace('/','-',$generalInformation['policyInceptionDate']);
                $policy_end_date = str_replace('/','-',$generalInformation['policyEndDate']);
               
                $registrationDate = str_replace('/','-',$generalInformation['registrationDate']);
                
                $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

                if (config('ICICI_LOMBARD_RENEWAL_IDV_SERVICE_BIKE') == 'Y') {
                    $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
                    $premiumJson = $quoteLog->premium_json;
                    $showroomPrice = $premiumJson['showroomPrice'] ?? 0;
                } else {
                    $showroomPrice = $generalInformation['showRoomPrice'];
                }
                
                $IsConsumables = false;
                $IsRTIApplicableflag = false;
                $IsEngineProtectPlus = false;
                $LossOfPersonalBelongingPlanName = '';
                $KeyProtectPlan = '';
                $RSAPlanName = '';               //valid upto 15 years
                $ZeroDepPlanName = '';
                $eme_cover = false;
                $eme_Plan_name = '';
                
                if($selected_addons->applicable_addons != NULL)
                {
                    foreach ($selected_addons->applicable_addons as $key => $value) 
                    {
                       if($value['name'] == 'Zero Depreciation')
                       {
                            $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver TW' : 'Silver';
                       }
                       elseif($value['name'] == 'Emergency Medical Expenses'  && $bike_age <= 15)
                        {
                                $eme_cover = true;
                                $eme_Plan_name = 'Premium Segment';
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
                            $rsa_amount = $riskDetails['roadSideAssistance'];
                            if($rsa_amount == '199.0')
                            {
                                $RSAPlanName = 'TW-199'; 
                            }else if($rsa_amount == '299.0')
                            {
                                $RSAPlanName = 'TW-299'; 
                            }else
                            {
                                $RSAPlanName = 'TW-199'; 
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
                       }
                    }
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
                $tppd_limit = 0;
                
        
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
                            $SIPACoverUnnamedPassenger = ($value['sumInsured'] * $mmv->seatingcapacity);

                       }
                       if($value['name'] == 'LL paid driver')
                       {
                            $IsLLPaidDriver = true;
                       }
                    }
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
                $proposal_submit_data = [
                    "ProposalDetails" => [
                        "IsAutoInstaTagging" => false,
                        "VehicleDescription" => '',
                        "EngineNumber" => $vehicleDetails['engineNumber'],
                        "ChassisNumber" => $vehicleDetails['chassisNumber'],
                        "RegistrationNumber" => $vehicleDetails['registrationNumber'],
                        // "NomineeDetails" => null,
                        // "financierDetails" => null,
                        "CustomerDetails" => [
                            "CustomerType" => $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE',
                            "CustomerName" => $requestData->vehicle_owner_type == 'I' ? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name,
                            "DateOfBirth" => $requestData->vehicle_owner_type == 'I' ? date('Y-m-d', strtotime($proposal->dob)) : '',
                            "PinCode" => $proposal->pincode,
                            "PANCardNo" => ($proposal->pan_number != '') ? $proposal->pan_number : '',
                            "Email" => $proposal->email,
                            "MobileNumber" => $proposal->mobile_number,
                            "AddressLine1" => "DEHRI ADSF,,,,,,NEAR JUWELI VILLAGE,NEAR JUWELI VILLAGE",
                            "AddressLine2" => null,
                            "CountryCode" => 100,
                            "StateCode" => 147,
                            "CityCode" => 424416,
                            "Gender" => ($proposal->gender == 'MALE') ? 'Male' : 'Female',
                            "MobileISD" => null,
                            // "GSTDetails" => [
                            //     "GSTExemptionApplicable" => null,
                            //     "GSTInNumber" => $proposal->gst_number,
                            //     "UINNumber" => null,
                            //     "GSTToState" => $state_name,
                            //     "ConstitutionOfBusiness" => null,
                            //     "CustomerType" => null,
                            //     "PanDetails" => null,
                            //     "GSTRegistrationStatus" => null,
                            //     "GSTToStateCode" => 0
                            // ],
                            "AadharNumber" => null,
                            "IsCollectionofform60" => false,
                            "AadharEnrollmentNo" => null,
                            "eIA_Number" => null,
                            "CorelationId" => null,
                            "CustomerID" => $generalInformation['customerId'],
                            // "CKYCId" => null,
                            // "EKYCid" => null,
                            "PEPFlag" => false,
                            // "ILKYCReferenceNumber" => null,
                            "CorrespondingAddress" => null,
                            "SkipDedupeLogic" => null,
                            "DateOfIncorporation" => null,
                            "SourceOfFunds" => null,
                            "OtherFunds" => null,
                            "CIN" => null
                        ],
                        "SPDetails" => null,
                        "IsProposal" => false,
                        "IsQuote" => false,
                        "ExShowRoomPrice" => $generalInformation['showRoomPrice'],
                        "ServiceTaxExemptionCategory" => null,
                        "IsHandicapDisc" => false,
                        "BodyType" => null,
                        "IsLegalLiabilityToPaidEmployee" => false,
                        "NoOfEmployee" => 0,
                        "LossOfPersonalBelongingPlanName" => $LossOfPersonalBelongingPlanName,
                        'IsPACoverUnnamedPassenger' => $IsPACoverUnnamedPassenger,
                        'SIPACoverUnnamedPassenger' => $SIPACoverUnnamedPassenger,
                        "IsQCByPass" => null,
                        "OwnershipSerialNumber" => null,
                        'IsVoluntaryDeductible'         => ($voluntary_deductible_amount != 0) ? false : false,
                        'VoluntaryDeductiblePlanName'   => ($voluntary_deductible_amount != 0) ? 0 : 0,
                        "IsAutomobileAssocnFlag" => false,
                        "AutomobileAssociationNumber" => null,
                        "IsAntiTheftDisc" => false,
                        "ZeroDepPlanName" => $ZeroDepPlanName,
                        "IsRTIApplicableflag" => $IsRTIApplicableflag,
                        "IsConsumables" => $IsConsumables,
                        "IsEngineProtectPlus" => $IsEngineProtectPlus,
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
                        // "TPTenure" => 1,
                        "OtherLoading" => 0.0,
                        "OtherDiscount" => 0.0,
                        // "TPEndDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_end_date)) : null,
                        // "TPInsurerName" => $proposal->tp_insurance_company,
                        // "TPPolicyNo" => $proposal->tp_insurance_number,
                        // "TPStartDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_start_date)) : null,
                        "EMECover" => $eme_Plan_name,
                        "NoOfPassengerHC" => 0,
                        "PYPCoverDetails" => null,
                        "IsFloater" => null,
                        "FloaterDetails" => null,
                        "FleetID" => null,
                        "IsPAYU" => false,
                        "PAYUDetails" => null,
                        "IsEarlyPay" => false,
                        "IsPHYU" => false,
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
                        'BusinessType'              => $generalInformation['transactionType'],
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
                        "ExtensionCountryName" => "",
                        "TPPDLimit" => 100000,
                        'PreviousPolicyDetails' => [
                            'PreviousPolicyStartDate' => $previousPolicyDetails['previousPolicyStartDate'], //'2018-07-02',
                            'PreviousPolicyEndDate' => $previousPolicyDetails['previousPolicyEndDate'],
                            'ClaimOnPreviousPolicy' => 0,
                            'PreviousPolicyType' => $previousPolicyDetails['previousPolicyType'],
                            'PreviousPolicyNumber' => $previousPolicyDetails['previousPolicyNumber'],
                            'PreviousInsurerName' => 'GIC',
                            "BonusOnPreviousPolicy" => ($requestData->is_claim == 'Y'  && $premium_type != 'third_party') ? $requestData->previous_ncb : '0',
                            "NatureOfLoss" => "false", #
                            "NoOfClaimsOnPreviousPolicy" => ($requestData->is_claim == 'Y') ? 1 : 0,
                            "PreviousPolicyTenure" => 1, #
                            "PreviousVehicleSaleDate" => "", #
                            "TotalNoOfODClaims" => ($requestData->is_claim == 'Y') ? "1" : "0"
                        ],
                        "IsPACoverWaiver" => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                        "OldReferenceNo" => null,
                        "IsCoverRatesApplicable" => false,
                        "VehicleAge" => $bike_age,
                        "AlternatePolicyNo" => null,
                        "ChannelSource" => "IAGENT",
                        "SoftCopyFlag" => null,
                        "IsSelfInspection" => true,
                        "SourcePolicy" => null,
                        "IsBreakinFlag" => true,
                        "NoOfClaimsInLast3Years" => ($requestData->is_claim == 'Y') ? 1 : 0,
                        "IsNCBApplicable" => true,
                        "IIBScore" => null,
                        "CIBILScore" => null,
                        "CoverLevelDiscounting" => [
                            "ZeroDepreciation" => 0.0,
                            "ReturnToInvoice" => 0.0,
                            "NCBProtect" => null,
                            "Consumables" => 0.0,
                            "GarageCash" => 0.0,
                            "EngineProtectPlus" => null,
                            "TyreProtect" => null,
                            "KeyProtect" => null,
                            "LossOfPersonalBelonging" => null,
                            "RSA" => null,
                            "BatteryProtect" => null,
                            "SmartSaver" => null
                        ],
                        "TypeOfCalculation" => null,
                        "SeatingCapacity" => $mmv->seatingcapacity,
                        "IsPOSDealId" => false,
                        "POSStarttime" => null,
                        "POSEndtime" => null,
                        "IsRegisteredCustomer" => null,
                        "CorrelationId" => $corelationId,
                        "PolicyPeriod" => 0.0
                    ],
                    "CustomerID" => $generalInformation['customerId'],
                    "IsCustomerModified" => true,
                    "ProposalRefNo" => !empty($generalInformation['referenceProposalNo']) ? $generalInformation['referenceProposalNo'] : 0,
                    "PolicyNumber" => $previousPolicyDetails['previousPolicyNumber'],
                    "DealID" => $DealID,
                    'EngineNumberLast5Char'         => substr($vehicleDetails['engineNumber'], -5),
                    'ChassisNumberLast5Char'        => substr($vehicleDetails['chassisNumber'], -5),
                    "ChannelSource" => null,
                    "IsPOSDealId" => false,
                    "POSStarttime" => null,
                    "POSEndtime" => null,
                    "IsRegisteredCustomer" => null,
                    "CorrelationId" => $corelationId
                ];

                if ($proposal->gst_number != '') {
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['GSTExemptionApplicable'] = 'Yes';
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['GSTInNumber'] = $proposal->gst_number;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['GSTToState'] = $state_name;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['UINNumber'] = null;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['ConstitutionOfBusiness'] = null;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['CustomerType'] = $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE';
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['GSTRegistrationStatus'] = null;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['PanDetails'] = null;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['GSTDetails']['GSTToStateCode'] = 0;
                }
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['CKYCID'] = $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['EKYCid'] = $proposal->ckyc_type == 'ckyc_number' ? $proposal->ckyc_type_value : $proposal->ckyc_number;
                    $proposal_submit_data['ProposalDetails']['CustomerDetails']['ilkycReferenceNumber'] = $proposal->ckyc_reference_id;
                    //$proposal_array['CustomerDetails']['SkipDedupeLogic'] = ;
                }
                
                if ($ispacoverownerdriver == 'true') {
                    $proposal_array['NomineeDetails']  = [
                        'NomineeType'               => 'PA-Owner Driver',
                        'NameOfNominee'             => $proposal->nominee_name ,
                        'Age'                       => get_date_diff('year',$proposal->nominee_dob),
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
                    'requestMethod'     => 'post',
                    'type'              => 'Renewal Proposal Service',
                    'section'           => 'BIKE',
                    'token'             => $access_token,
                    'enquiryId'         => $enquiryId,
                    'transaction_type'  => 'proposal',
                    'productName'       => $productData->product_name
                ];
                //$url = 'https://ilesbsanity.insurancearticlez.com/ILServices/Motor/v1/Renew/TwoWheeler/RenewPolicy';
                $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_BIKE_PROPOSAL_RENEWAL_SUBMIT');
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
                    $proposal->proposal_no          = $proposalServiceResponse['generalInformation']['proposalNumber'];
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
                            'proposal_no'        => $proposalServiceResponse['generalInformation']['proposalNumber'],
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
                        'message' => $proposalServiceResponse['ProposalRefNo'][0] ?? ($proposalServiceResponse['message'] ?? 'Insurer not reachable')
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
