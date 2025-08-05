<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use DB;
use Config;
use DateTime;
use App\Models\MasterRto;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use App\Models\IcVersionMapping;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\NewIndiaPremiumDetailController;
use App\Models\AgentIcRelationship;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';

class newIndiaSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
    	$requestData = getQuotation($enquiryId);
    	$productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $mmv = get_mmv_details($productData,$requestData->version_id,'new_india');
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
        $veh_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
        $city_name =  DB::table('master_rto')
                    ->select('rto_name as city_name')
                    ->where('rto_code', $requestData->rto_code)
                    ->where('status', 'Active')
                    ->first();
        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
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

        $is_package         = (($premium_type == 'comprehensive') ? true : false);
        $is_liability       = (($premium_type == 'third_party') ? true : false);
        $is_od              = (($premium_type == 'own_damage') ? true : false);
        $reg_no = explode('-', strtoupper($requestData->vehicle_registration_no));
        $Color_as_per_RC_Book = 'As per RC book';
        //$age = get_date_diff('month', $requestData->vehicle_register_date);
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age =  (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        
        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                ->select('applicable_addons', 'compulsory_personal_accident','accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                ->first();

        $voluntary = 0;
        $srilanka = 0;
        $pak = 0;
        $bang = 0;
        $bhutan = 0;
        $nepal = 0;
        $maldive = 0;
        $is_geo_ext = 0;
        $anti_theft = 'N';

        //POS 
        $is_pos = 'No';
        $pos_name = null;
        $pos_name_uiic = null;
        $partyCode = null;
        $partyStakeCode = null;
        $pos_partyCode = null ;
        $pos_partyStakeCode = null ;
        $is_pos_testing_mode = config('IC.NEWINDIA.BIKE.TESTING_MODE') === 'Y';

        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

        if($pos_data && !$is_pos_testing_mode){
            //Properties
            $is_pos = 'YES';
            $pos_name = $pos_data->agent_name;
            $pos_name_uiic = 'uiic';

            //party
            $partyCode = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
            ->pluck('new_india_code')
            ->first();
            $pos_partyStakeCode = $pos_name;
            if(empty($partyCode) || is_null($partyCode))
            {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => 'POS details Not Available'
                ];
            }
            $partyStakeCode = 'POS';
        }
        if($is_pos_testing_mode == 'Y')
        {
             //properties
             $is_pos = 'YES';
             $pos_name = 'POS Applicable';
             $pos_name_uiic = 'uiic';
             //properties
             //party
             $partyCode = config('IC.NEWINDIA.BIKE.POS_PARTY_CODE');//PP00000015
             $partyStakeCode = 'POS';
             //party
        }

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'voluntary_insurer_discounts') {
                    $voluntary = $data['sumInsured'];
                }
                if ($data['name'] == 'anti-theft device') {
                    $anti_theft = 'Y';
                }
            }
        }
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'Geographical Extension') {
                    $countries = $data['countries'];
                    $is_geo_ext = 1;
                    if(in_array('Sri Lanka',$countries))
                    {
                        $srilanka = 1;
                    }
                    if(in_array('Bangladesh',$countries))
                    {
                        $bang = 1; 
                    }
                    if(in_array('Bhutan',$countries))
                    {
                        $bhutan = 1; 
                    }
                    if(in_array('Nepal',$countries))
                    {
                        $nepal = 1; 
                    }
                    if(in_array('Pakistan',$countries))
                    {
                        $pak = 1; 
                    }
                    if(in_array('Maldives',$countries))
                    {
                        $maldive = 1; 
                    }
                }
            }
        }

        
        $legal_liability_to_paid_driver_cover_flag = '0';

        $additional_data = $proposal->additonal_data;
        
        if($premium_type == 'own_damage')
        {
            $legal_liability_to_paid_driver_cover_flag = '0';
        }
        if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10))
        {
            $bike_registration_no = $reg_no[0] . '-0' . $reg_no[1];
        }
        $company_rto_data = DB::table('new_india_rto_master')
                        ->where('rto_code', strtr($requestData->rto_code, ['-' => '']))
                        ->first();
        $reg = explode("-",$proposal->vehicale_registration_number);
         $Registration_No_4 = $reg[3] ?? '';
         if(isset($Registration_No_4) && $Registration_No_4 != '')
         {
            if (strlen($Registration_No_4) == 3) {
                $Registration_No_4 = '0' . $Registration_No_4;
            } else if (strlen($Registration_No_4) == 2) {
                $Registration_No_4 = '00' . $Registration_No_4;
            } else if (strlen($Registration_No_4) == 1) {
                $Registration_No_4 = '000' . $Registration_No_4;
            }else{
                $Registration_No_4 = $reg[3];
            }
         }
         if($requestData->business_type == 'newbusiness')
         {
            $policy_start_date = Date('d/m/Y');
            $policy_type = "New";
            $reg[0] = 'NEW';
            $reg[1] = '0';
            $reg[2] = 'none';
            $reg[3] = '0001';
            $new_vehicle = 'Y';
            $PreInsurerAdd = '';
         }
         else
         {
            $today_date =date('Y-m-d'); 
            $insured_name = '';
            if(new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date) > new DateTime($today_date))
            {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            else if(new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d'): $requestData->previous_policy_expiry_date) < new DateTime($today_date))
            {
               $policy_start_date = date('d/m/Y', strtotime("+4 day")); 
            }
            else
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day")); 
            }
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date))/(60*60*24);
            if($premium_type == 'own_damage') 
            {
                    $policy_start_date1 = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                   
                    $policy_end_date1 = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date1)));
                    if(new DateTime(date('Y-m-d', strtotime($proposal->tp_end_date))) < new DateTime($policy_end_date1))
                    {
                        return
                        [
                            'status' => false,
                            'message' => 'TP Policy Expiry Date should be greater than or equal to OD policy expiry date'
                        ];
                    }
                 
            }
            $PreInsurerAdd =  $requestData->previous_policy_expiry_date == 'New' ? '' : $insurer->address_line_1;
            $PreInsurerAdd2 =  $requestData->previous_policy_expiry_date == 'New' ? '' : $insurer->address_line_2;
            $prev_pincode = $requestData->previous_policy_expiry_date == 'New' ? '' : $insurer->pin;
            if($premium_type == 'own_damage')
            {
                $tp_previous_insurer_address1 = $insurer->address_line_1;
                $tp_previous_insurer_address2 = $insurer->address_line_2;
                $tp_previous_insurer_pin = $insurer->pin;
            }else
            {
                $tp_previous_insurer_address1 = $tp_previous_insurer_address2 = $tp_previous_insurer_pin = '';
            }
         }
         $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
         $cpa_with_greater_15 = 'No';
         $sex = $proposal->gender;
         $cpa_type ='false';
         if ($quote_data->vehicle_owner_type == 'I')
        {
            $Gender_of_Nominee = 'NA';
            if (!empty($additional['compulsory_personal_accident'])) {//cpa
                foreach ($additional['compulsory_personal_accident'] as $key => $data)  
                {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')
                    {
                        $cpa_with_greater_15 = 'No';
                        $cpa_type = "true";
                    }
                }
            }
        }
        else
        {
            $cpa_type ='false';
            $cpa_with_greater_15 = 'Yes';
            $sex = '';
            $marital_status = '';
            $occupation = '';

            $Name_of_Nominee = $Age_of_Nominee = $Relationship_with_the_Insured = $Gender_of_Nominee = 'NA';
        }
        $birthDate = strtr($proposal->dob, ['-' => '/']);
      
        $partyType = ($quote_data->vehicle_owner_type == 'I') ? 'I' : 'O';
        if ($partyType == 'I')
        {
            $policy_holder_array = [
                'typ:userCode'        => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'typ:rolecode'        => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                'typ:PRetCode'        => '0',
                'typ:userId'          => '',
                'typ:stakeCode'       => 'BROKER',
                'typ:roleId'          => '',
                'typ:userroleId'      => '',
                'typ:branchcode'      => '',
                'typ:PRetErr'         => '',
                'typ:startDate'       => $policy_start_date,
                'typ:stakeName'       => '',
                'typ:title'           => '',
                'typ:typeOfOrg'       => '',
                'typ:address'         => $proposal->address_line1,
                'typ:firstName'       => $proposal->first_name,
                'typ:partyCode'       => '',
                'typ:company'         => '',
                'typ:sex'             => $sex,
                'typ:address2'        => $proposal->address_line2,
                'typ:EMailid2'        => '',
                'typ:partyStakeCode'  => 'POLICY-HOL',
                'typ:partyType'       => $partyType,
                'typ:regNo'           => '',
                'typ:midName'         => '',
                'typ:city'            => $proposal->city,
                'typ:phNo3'           => $proposal->mobile_number,
                'typ:regDate'         => '',
                'typ:contactType'     => 'Permanent',
                'typ:businessName'    => '',
                'typ:status'          => '',
                'typ:EMailid1'        => $proposal->email,
                'typ:clientType'      => '',
                'typ:birthDate'       => $birthDate,
                'typ:lastName'        => $proposal->last_name,
                'typ:sector'          => 'NP',
                'typ:country'         => '',
                'typ:pinCode'         => $proposal->pincode,
                'typ:prospectId'      => '',
                'typ:state'           => $proposal->state_id,
                'typ:address3'        => $proposal->address_line3 . ' ' . $proposal->city,
                'typ:phNo1'           => '',
                'typ:businessAddress' => '',
                'typ:phNo2'           => '',
                'typ:partyName'       => '',
                'typ:panNo'           => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                'typ:gstRegIdType'    => 'NCC',
                'typ:gstin'           => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
            ];
        }
        else
        {
            $policy_holder_array = [
                'typ:userCode'        => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'typ:rolecode'        => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                'typ:PRetCode'        => '0',
                'typ:userId'          => '',
                'typ:stakeCode'       => 'BROKER',
                'typ:roleId'          => '',
                'typ:userroleId'      => '',
                'typ:branchcode'      => '',
                'typ:PRetErr'         => '',
                'typ:startDate'       => $policy_start_date,
                'typ:stakeName'       => '',
                'typ:title'           => '',
                'typ:typeOfOrg'       => '',
                'typ:address'         => $proposal->address_line1,
                'typ:firstName'       => '',
                'typ:partyCode'       => '',
                'typ:company'         => '',
                'typ:sex'             => $sex,
                'typ:address2'        => $proposal->address_line2,
                'typ:EMailid2'        => '',
                'typ:partyStakeCode'  => 'POLICY-HOL',
                'typ:partyType'       => $partyType,
                'typ:regNo'           => '',
                'typ:midName'         => '',
                'typ:city'            => $proposal->city,
                'typ:phNo3'           => $proposal->mobile_number,
                'typ:regDate'         => '',
                'typ:contactType'     => 'Permanent',
                'typ:businessName'    => $requestData->business_type,
                'typ:status'          => '',
                'typ:EMailid1'        => $proposal->email,
                'typ:clientType'      => '',
                'typ:birthDate'       => '01/01/2000',//$birthDate,
                'typ:lastName'        => '',
                'typ:sector'          => 'NP',
                'typ:country'         => '',
                'typ:pinCode'         => $proposal->pincode,
                'typ:prospectId'      => '',
                'typ:state'           => $proposal->state_id,
                'typ:address3'        => $proposal->address_line3 . ' ' . $proposal->city,
                'typ:phNo1'           => '',
                'typ:businessAddress' => '',
                'typ:phNo2'           => '',
                'typ:partyName'       => '',
                'typ:panNo'           => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                'typ:gstRegIdType'    => 'NCC',
                'typ:gstin'           => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
            ];
        }
        
        $policy_holder_array = trim_array($policy_holder_array);
        $get_response = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA_BIKE'),$policy_holder_array, 'new_india',
            [
                'root_tag'      => 'typ:createPolicyHol_GenElement',
                'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="http://iims.services/types/"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'new_india',
                'section' => $productData->product_sub_type_code,
                'method' =>'Create Policy Holder',
                'transaction_type' => 'proposal',
            ]);
        $data = $get_response['response'];
        unset($policy_holder_array);
        if ($data)
        {
            $policy_holder_resp = XmlToArray::convert((string) remove_xml_namespace($data));
            $createPolicyHol_GenResponseElement = array_search_key('createPolicyHol_GenResponseElement', $policy_holder_resp);

            unset($policy_holder_resp);
            if ($createPolicyHol_GenResponseElement && ($createPolicyHol_GenResponseElement['PRetCode'] == '0') && (isset($createPolicyHol_GenResponseElement['partyCode'])))
            {
                $cost_of_consumable = 'No';
                $engine_protector_cover = 'No';
                $return_to_invoice_cover = 'No';
                $rsa_cover = 'No';
                $key_replacement_cover = 'No';
                $tyre_secure_cover = 'No';
                $loss_of_personal_belongings_cover = 'No';
                $ncb_protector_cover = 'No';
                $no_of_unnamed_persons = '0';
                $no_of_paid_drivers = '0';
                $si_for_paid_drivers = '0';
                $include_pa_cover_for_paid_driver = 'N';
                $cover_pa_unnamed_passenger = '0';
                $capital_si_for_unnamed_persons = '0';
                $individual_si_for_unnamed_person = '0';
                $include_pa_cover_for_unnamed_person = 'N';
                $lpg_cng_kit = '0.00';
                $bi_fuel_type = '';
                $extra_electrical_electronic_fittings = 'N';
                $extra_non_electrical_electronic_fittings = 'N';
                $total_value_of_electrical_electronic_fittings = '0';
                $total_value_of_non_electrical_electronic_fittings = '0';
                $type_of_fuel = $veh_data->motor_fule;
                $si_for_paid_drivers = '0';

                $no_of_unnamed_persons = '0';
                $individual_si_for_unnamed_person = '0';
                $capital_si_for_unnamed_persons = '0';
                $include_pa_cover_for_unnamed_person = 'N';
                $llpd_flag = '0';
               

                if($productData->zero_dep == 0 && $premium_type != 'third_party')
                {

                    if($age <= 36)
                    {
                        $discount_column_selector = 'discount_percent_with_addons_0_to_36_months';
                    }
                    elseif ($age >36 && $age <=58) 
                    {
                        $discount_column_selector = 'discount_percent_with_addons_37_to_58_months';
                    } 
                }
                else
                {
                    if($age <= 120)
                    {
                        $discount_column_selector = 'discount_percent_without_addons_0_to_120_months';
                    }
                    elseif ($age >120 && $age <= 178) 
                    {
                        $discount_column_selector = 'discount_percent_without_addons_121_to_178_months';
                    }
                }
                $discount_percent = DB::table('new_india_motor_discount_grid')
                                    ->select("{$discount_column_selector} as discount_col")
                                    ->where('section','bike')
                                    ->first();
                if($discount_percent == 'third_party')
                {
                    $discount_percent = '0';
                }

                if($premium_type == 'own_damage' && $age <= 120) {
                    $discount_percent->discount_col  = '0';
                }

                $bike_expired_more_than_90_days = 'N';
                if($requestData->business_type != 'newbusiness')
                {
                    if($date_diff > 90)
                    {
                    $bike_expired_more_than_90_days = 'Y';
                    }
                }
                else
                {
                    $bike_expired_more_than_90_days = 'N';
                 
                }
                
                if ($requestData->previous_ncb !== '' && $requestData->is_claim == 'N' && $premium_type != 'third_party' && $bike_expired_more_than_90_days == 'N')
                {
                    $no_claim_bonus = $requestData->applicable_ncb;
                }
                else
                {
                    $no_claim_bonus = '0';
                }
                if($requestData->business_type == 'newbusiness')
                {
                    $Previous_Policy_Number = '0';
                }
                
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                        if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                            $lpg_cng_kit = $data['sumInsured'];
                            $type_of_fuel = 'CNG'; #.' '.$veh_data->motor_fule;
                        }
            
                        if ($data['name'] == 'Non-Electrical Accessories') {
                            $extra_non_electrical_electronic_fittings = 'Y';
                            $total_value_of_non_electrical_electronic_fittings = $data['sumInsured'];
                        }
            
                        if ($data['name'] == 'Electrical Accessories') {
                            $extra_electrical_electronic_fittings = 'Y';
                            $total_value_of_electrical_electronic_fittings = $data['sumInsured'];
                            }
                        }
                    }   
                    $zone_cities = [
                        'ahmedabad',
                        'bangalore',
                        'bengaluru',
                        'chennai',
                        'delhi',
                        'hyderabad',
                        'kolkata',
                        'mumbai',
                        'new delhi',
                        'pune'
                    ];

                    $vehicle_zone = (in_array(strtolower($city_name->city_name), $zone_cities)) ? 'A' : 'B';
                    $registration_authority = $company_rto_data->rta_name. ' ' . $company_rto_data->rta_address;
    
                    unset($city_name->city_name, $zone_cities, $requestData->rto_code, $company_rto_data);
                    $total_accessories_idv = $total_value_of_electrical_electronic_fittings + $total_value_of_non_electrical_electronic_fittings + $lpg_cng_kit;
                    $idv = $quote->idv ;
                    $ex_showroom_price = $veh_data->motor_invoice;
                    $insured_name = $proposal->previous_insurance_company;
                    if (trim($insured_name) == '')
                    {
                        $insured_name = 'Option24';
                    }
                    else if($requestData->previous_policy_type == 'Not sure'){
                        $insured_name = 'Option25';
                    }
                    if (trim($PreInsurerAdd) == '')
                    {
                        $PreInsurerAdd = 'NA';
                    }
                    if($premium_type != 'own_damage')
                    {
                        if (!empty($additional['additional_covers'])) 
                        {
                            foreach ($additional['additional_covers'] as $value)  
                            {   
                                if($value['name'] == 'Unnamed Passenger PA Cover' && $premium_type != 'own_damage') 
                                {  
                                    $no_of_unnamed_persons = $veh_data->motor_carrying_capacity;
                                    $capital_si_for_unnamed_persons = ($veh_data->motor_carrying_capacity) * $value['sumInsured'];
                                    $individual_si_for_unnamed_person = $value['sumInsured'];
                                    $include_pa_cover_for_unnamed_person = 'Y';
                                }
                                if($value['name'] == 'LL paid driver' && $premium_type != 'own_damage')
                                {
                                    $llpd_flag = '1';
                                }
                                if ($value['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                                    $si_for_paid_drivers = $data['sumInsured'];
                                   $no_of_paid_drivers = '1';
                                   $include_pa_cover_for_paid_driver = 'Y';
                                }

                            }
                        }
                    }
                    
                    
                    switch ($premium_type) 
                    {
                        
                        case 'comprehensive':
                            $coverCode = 'PACKAGE';
                            $product_id = '194731914122007';
                            $product_code = 'TW';
                            $product_name = 'TWO WHEELER';
                            $plan_type = 'NBPL';
                            break;
                        case 'third_party' :
                            $coverCode =  'LIABILITY';
                            $product_id = '194731914122007';
                            $product_code = 'TW';
                            $product_name = 'TWO WHEELER';
                            $plan_type = 'NLTL';
                            break;
                        case 'own_damage':
                            $coverCode =  'ODWTOTADON';//'ODWTHADDON';
                            $product_id = '121625924072019';
                            $product_code = 'SQ';
                            $product_name = 'Standalone OD policy for TW';

                            $no_of_unnamed_persons = '0';
                            $individual_si_for_unnamed_person = '0';
                            $capital_si_for_unnamed_persons = '0';
                            $include_pa_cover_for_unnamed_person = 'N';
                            $llpd_flag = '0';
                            break;
                        
                    }

                    $covers = [
                        'typ:productCode'     => $product_code,
                        'typ:policyDetailid'  => '',
                        'typ:coverCode'       => ($productData->zero_dep == '1') ? $coverCode : (($premium_type == 'own_damage') ? 'ODWTHADDON' : 'EHMNTCOVER'),
                        'typ:productId'       => $product_id,
                        'typ:coverExpiryDate' => '',
                        'typ:properties'      => [
                            [
                                'typ:value' => 'N',
                                'typ:name'  => 'Do You want to reduce TPPD cover to the statutory limit of Rs.6000',
                            ],
                            [
                                'typ:value' => '100000',
                                'typ:name'  => 'Sum Insured for PA to Owner Driver',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name'  => 'Do you want to include PA cover for Named Person',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name'  => 'Number of Named Persons',
                            ],
                            [
                                'typ:value' => 'none',
                                'typ:name'  => 'Names of Named person',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name'  => 'Individual SI for Named Person',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name'  => 'Capital SI for All Named Persons',
                            ],
                            [
                                'typ:value' => $no_of_paid_drivers,
                                'typ:name'  => 'No of Paid Drivers',
                            ],
                            [
                                'typ:value' => $si_for_paid_drivers,
                                'typ:name'  => 'Individual SI for Paid Driver',
                            ],
                            [
                                'typ:value' => $si_for_paid_drivers,
                                'typ:name'  => 'Capital SI for Drivers',
                            ],
                            [
                                'typ:value' => $no_of_unnamed_persons,
                                'typ:name'  => 'No of unnamed Persons',
                            ],
                            [
                                'typ:value' => $individual_si_for_unnamed_person,
                                'typ:name'  => 'Individual SI for unnamed Person',
                            ],
                            [
                                'typ:value' => $capital_si_for_unnamed_persons,
                                'typ:name'  => 'Capital SI for unnamed Persons',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name'  => 'Number of LL to Soldiers/Sailors/Airmen',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name'  => 'Number Of Legal Liable Employees',
                            ],
                            [
                                'typ:value' => $llpd_flag,
                                'typ:name'  => 'Number of Legal Liable Drivers',
                            ],
                            [
                                'typ:value' => $include_pa_cover_for_paid_driver,
                                'typ:name'  => 'Do you wish to include PA Cover for Paid Drivers',
                            ],
                            [
                                'typ:value' => $include_pa_cover_for_unnamed_person,
                                'typ:name'  => 'Do you want to include PA cover for unnamed person/hirer/pillion passangers',
                            ],
                            [
                                'typ:value' => $llpd_flag == '1'? 'Y' : 'N',
                                'typ:name'  => 'LL to paid drivers,cleaner employed  for opn. and/or maint. of vehicle under WCA',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name'  => 'LL to Employees of Insured traveling and / or driving the Vehicle',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name'  => 'LL to Soldiers/Sailors/Airmen employed as Drivers',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name'  => 'Sum Insured for TPPD',
                            ],
                            [
                                'typ:value' => 'A',
                                'typ:name'  => 'Type of Liability Coverage',
                            ],
                            //addons start
                            
                        ],
                    ];
    
                    $covers = trim_array($covers);
                    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                    $vehicleDate = strtr($vehicleDate, ['-' => '/']);
                    $first_reg_date = strtr($requestData->vehicle_register_date, ['-' => '/']);
                    
                    $existing_policy_expiry_date = strtr($requestData->previous_policy_expiry_date, ['-' => '/']);
                    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
                    $manf_year = $motor_manf_year_arr[1];
                    $properties = [
                        [
                            'typ:value' => $is_pos,
                            'typ:name'  => $pos_name_uiic,
                        ],
                        [
                            'typ:value' => 'STDTWWHL',
                            'typ:name'  => 'Type of Two Wheeler',
                        ],
                        [
                            'typ:value' => 'New',
                            'typ:name'  => 'Current Ownership',
                        ],
                        [
                            'typ:value' => $proposal->vehicle_color,
                            'typ:name'  => 'Color of Vehicle',
                        ],
                        [
                            'typ:value' => $manf_year,
                            'typ:name'  => 'Year of Manufacture',
                        ],
                        [
                            'typ:value' => ($requestData->business_type == 'newbusiness') ? 'Y' : 'N',
                            'typ:name'  => 'New Vehicle',
                        ],
                        [
                            'typ:value' => $reg[0],
                            'typ:name'  => 'Registration No (1)',
                        ],
                        [
                            'typ:value' => $reg[1],
                            'typ:name'  => 'Registration No (2)',
                        ],
                        [
                            'typ:value' => $reg[2],
                            'typ:name'  => 'Registration No (3)',
                        ],
                        [
                            'typ:value' => $Registration_No_4,
                            'typ:name'  => 'Registration No (4)',
                        ],
                        [
                            'typ:value' => $vehicleDate,
                            'typ:name'  => 'Date of Sale',
                        ],
                        [
                            'typ:value' => $first_reg_date,
                            'typ:name'  => 'Date of Registration',
                        ],
                        [
                            'typ:value' => '01/01/2029',
                            'typ:name'  => 'Registration Validity Date',
                        ],
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'Give Vehicle Details',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Whether trailer attached to the vehicle',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Number of Trailers Attached',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Total IDV of the Trailer Attached',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Value of Music System',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Value of AC/Fan',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Value of Lights',
                        ],
                        [
                            'typ:value' => $total_value_of_electrical_electronic_fittings,
                            'typ:name'  => 'Value of Other Fittings',
                        ],
                        [
                            'typ:value' => $total_value_of_electrical_electronic_fittings,
                            'typ:name'  => 'Total Value of Extra Electrical/ Electronic fittings',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Additional Towing Coverage Amount',
                        ],
                        [
                            'typ:value' => $requestData->business_type == 'newbusiness' ? 'NA' : $proposal->previous_policy_number,
                            'typ:name'  => 'Previous Policy Number',
                        ],
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'Name of Association',
                        ],
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'Membership No',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Is Life Member',
                        ],
                        [
                            'typ:value' => '01/01/0001',
                            'typ:name'  => 'Date of Membership Expiry',
                        ],
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'Details of Vehicle Condition',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Discretion to RO',
                        ],
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'Approval No',
                        ],
                        [
                            'typ:value' => '01/01/0001',
                            'typ:name'  => 'Approval Date',
                        ],
                        [
                            'typ:value' =>  $bang == 1 ? 'Y':'N' ,
                            'typ:name'  => 'Extension of Geographical Area to Bangladesh',
                        ],
                        [
                            'typ:value' =>  $bhutan == 1 ? 'Y':'N' ,
                            'typ:name'  => 'Extension of Geographical Area to Bhutan',
                        ],
                        [
                            'typ:value' =>  $nepal == 1 ? 'Y':'N' ,
                            'typ:name'  => 'Extension of Geographical Area to Nepal',
                        ],
                        [
                            'typ:value' =>  $pak == 1 ? 'Y':'N' ,
                            'typ:name'  => 'Extension of Geographical Area to Pakistan',
                        ],
                        [
                            'typ:value' =>  $srilanka == 1 ? 'Y':'N' ,
                            'typ:name'  => 'Extension of Geographical Area to Sri Lanka',
                        ],
                        [
                            'typ:value' =>  $maldive == 1 ? 'Y':'N' ,
                            'typ:name'  => 'Extension of Geographical Area to Maldives',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Value of Fibre glass fuel tanks',
                        ],
                        [
                            'typ:value' => $lpg_cng_kit,
                            'typ:name'  => 'Bi-fuel System Value',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'In Built Bi-fuel System fitted',
                        ],
                        [
                            'typ:value' => 'SALOON',
                            'typ:name'  => 'Type of Body',
                        ],
                        [
                            'typ:value' => $proposal->engine_number,
                            'typ:name'  => '(*)Engine No',
                        ],
                        [
                            'typ:value' => $proposal->chassis_number,
                            'typ:name'  => '(*)Chassis No',
                        ],
                        [
                            'typ:value' => substr($veh_data->motor_make, 0, 10),
                            'typ:name'  => 'Make',
                        ],
                        [
                            'typ:value' => $veh_data->motor_model,
                            'typ:name'  => 'Model',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name'  => 'Bike in roadworthy condition and free from damage',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Vehicle Requisitioned by Government',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Whether vehicle is used for driving tuition',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Vehicle use is limited to own premises',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Whether vehicle belongs to foreign embassy or consulate',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Whether vehicle is certified as Vintage bike by Vintage and Classic Bike Club',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Vehicle designed for Blind/Handicapped/Mentally Challenged persons and endorsed by RTA',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Are you a member of Automobile Association of India',
                        ],
                        [
                            'typ:value' => ($anti_theft == 'Y')? 'Y' :'N',
                            'typ:name'  => 'Is the vehicle fitted with Anti-theft device',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Obsolete Vehicle',
                        ],
                        [
                            'typ:value' =>  $is_geo_ext == 1 ? 'Y':'N',
                            'typ:name'  => 'Extension of Geographical Area required',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Whether Vehicle belong to Embassies or imported without Custom Duty',
                        ],
                        [
                            'typ:value' => $vehicle_zone,
                            'typ:name'  => 'Vehicle Zone for Private Bike',
                        ],
                        [
                            'typ:value' => $veh_data->motor_variant,
                            'typ:name'  => 'Variant',
                        ],
                        [
                            'typ:value' => $registration_authority,
                            'typ:name'  => 'Name and Address of Registration Authority',
                        ],
                        [
                            'typ:value' => $veh_data->motor_cc,
                            'typ:name'  => '(*)Cubic Capacity',
                        ],
                        [
                            'typ:value' => $veh_data->motor_carrying_capacity,
                            'typ:name'  => '(*)Seating Capacity',
                        ],
                        [
                            'typ:value' => $type_of_fuel,
                            'typ:name'  => 'Type of Fuel',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Fibre Glass Tank Fitted',
                        ],
                        [
                            'typ:value' => ($premium_type == 'third_party') ? '0' :$ex_showroom_price,
                            'typ:name'  => 'Vehicle Invoice Value',
                        ],
                        [
                            'typ:value' => $age,
                            'typ:name'  => 'Vehicle Age in Months',
                        ],
                        [
                            'typ:value' => $extra_electrical_electronic_fittings,
                            'typ:name'  => 'Extra Electrical/ Electronic fittings',
                        ],
                        [
                            'typ:value' => $extra_non_electrical_electronic_fittings,
                            'typ:name'  => 'Non-Electrical/ Electronic fittings',
                        ],
                        [
                            'typ:value' => $total_value_of_non_electrical_electronic_fittings,
                            'typ:name'  => 'Value of Non- Electrical/ Electronic fittings',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Additional Towing Coverage Required',
                        ],
                        //cpa section
                        [
                            'typ:value' => ($cpa_type == 'true') ?'N':'Y',
                            'typ:name'  => 'Do You Hold Valid Driving License',
                        ],
    
                        [
                            'typ:value' => $cpa_with_greater_15,
                            'typ:name'  => 'Do you have a general PA cover with motor accident coverage for CSI of atleast 15 lacs?',
                        ],
                        [
                            'typ:value' =>  ($cpa_type == 'true') ? 'No':'Yes',
                            'typ:name'  => 'Do you have a standalone CPA cover product for Owner Driver?',
                        ],
                        //cpa section end
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'License Type of Owner Driver',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Age of Owner Driver',
                        ],
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'Owner Driver Driving License No',
                        ],
                        [
                            'typ:value' => '01/01/0001',
                            'typ:name'  => 'Owner Driver License Expiry Date',
                        ],
                        [
                            'typ:value' => 'none',
                            'typ:name'  => 'License Issuing Authority for Owner Driver',
                        ],
                        [
                            'typ:value' => !empty($proposal->nominee_name) ? $proposal->nominee_name : 'NA' ,
                            'typ:name'  => 'Name of Nominee',
                        ],
                        [
                            'typ:value' => !empty($proposal->nominee_age) ? $proposal->nominee_age : 'NA',
                            'typ:name'  => 'Age of Nominee',
                        ],
                        [
                            'typ:value' => !empty($proposal->nominee_relationship) ? $proposal->nominee_relationship : 'NA' ,
                            'typ:name'  => 'Relationship with the Insured',
                        ],
                        [
                            'typ:value' =>  $Gender_of_Nominee,
                            'typ:name'  => 'Gender of the Nominee',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Do you Have Any other Driver',
                        ],
                        [
                            'typ:value' => ($premium_type == 'third_party') ?'0':$idv,
                            'typ:name'  => 'Insureds declared Value (IDV)',
                        ],
                        [
                            'typ:value' => $total_accessories_idv,
                            'typ:name'  => 'IDV of Accessories',
                        ],
                        [
                            'typ:value' => ($premium_type == 'third_party') ? '0' : $idv + $total_accessories_idv ,
                            'typ:name'  => 'Total IDV',
                        ],
                        [
                            'typ:value' => ($premium_type == 'third_party') ?'0':$no_claim_bonus,
                            'typ:name'  => 'NCB Applicable Percentage',
                        ],
                        [
                            'typ:value' => $insured_name,
                            'typ:name'  => 'Name of Previous Insurer',
                        ],
                        [
                            'typ:value' => $PreInsurerAdd,
                            'typ:name'  => 'Address of the Previous Insurer',
                        ],
                        [
                            'typ:value' => $first_reg_date,
                            'typ:name'  => 'Date of Purchase of Vehicle by Proposer',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name'  => 'Vehicle New or Second hand at the time of purchase',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name'  => 'Vehicle Used for Private,Social,domestic,pleasure,professional purpose',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name'  => 'Is vehicle in good condition',
                        ],
                        [
                            'typ:value' => ($requestData->business_type == 'newbusiness' ? '01/01/0001' : strtr($existing_policy_expiry_date, ['-' => '/'])),
                            'typ:name'  => 'Expiry date of previous Policy',
                        ],
                        [
                            'typ:value' => '0.00',
                            'typ:name'  => 'Policy Excess (Rs)',
                        ],
                        [
                            'typ:value' => $voluntary,
                            'typ:name'  => 'Voluntary Excess for TW',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Imposed Excess (Rs)',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name'  => 'Loading amount for OD',
                        ],
                        [
                            'typ:value' => ($premium_type == 'third_party') ? '0' : $discount_percent->discount_col,
                            'typ:name'  => 'OD discount (%)',
                        ],
                        [
                            'typ:value' => '',
                            'typ:name'  => 'Owner Driver License Issue Date',
                        ],
                        [
                            'typ:value' => $Color_as_per_RC_Book,
                            'typ:name'  => 'Color as per RC book',
                        ],
                        //tp details
                        // [
                        //     'typ:value' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_company:'',
                        //     'typ:name'  => 'Name (Bundled/Long Term Liability policy Insurer)',
                        // ],
                        // [
                        //     'typ:value' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_number :'',
                        //     'typ:name'  => 'Bundled/Long Term Liability Policy No.',
                        // ],
                        // [
                        //     'typ:value' => ($premium_type == 'own_damage') ? strtr($proposal->tp_start_date,'-','/') :'',
                        //     'typ:name'  => 'Bundled/Long Term Policy Start Date',
                        // ],
                        // [
                        //     'typ:value' => ($premium_type == 'own_damage') ? strtr($proposal->tp_end_date,'-','/') : '',
                        //     'typ:name'  => 'Bundled/Long Term Policy Expiry Date',
                        // ],
                    ];

                    if($productData->zero_dep == '0'){
                        $plan_type = 'NBEL';
                    }

                    if($requestData->business_type == 'newbusiness'){
                        array_push($properties, [
                            'typ:value' => $plan_type,
                            'typ:name'  => 'Policy Cover Plan Type',
                        ]);
                    };

                    if($requestData->business_type != 'newbusiness' || $premium_type == 'third_party'){
                        $tp_details =  [
                        [
                            'typ:value' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_company:'',
                            'typ:name'  => 'Name (Bundled/Long Term Liability policy Insurer)',
                        ],
                        [
                            'typ:value' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_number :'',
                            'typ:name'  => 'Bundled/Long Term Liability Policy No.',
                        ],
                        [
                            'typ:value' => ($premium_type == 'own_damage') ? strtr($proposal->tp_start_date,'-','/') :'',
                            'typ:name'  => 'Bundled/Long Term Policy Start Date',
                        ],
                        [
                            'typ:value' => ($premium_type == 'own_damage') ? strtr($proposal->tp_end_date,'-','/') : '',
                            'typ:name'  => 'Bundled/Long Term Policy Expiry Date',
                        ],
                        ];
                        $properties = array_merge($properties, $tp_details);
                    }
                    $properties = trim_array($properties);
                    $policyHoldercode = $createPolicyHol_GenResponseElement['partyCode'];
                    unset($reg[0], $reg[1], $reg[2], $reg[3], $createPolicyHol_GenResponseElement);

                    $party = null;
                    if($pos_data){
                        $party=[
                            [
                                'typ:partyCode' =>  $partyCode,
                                'typ:partyStakeCode' =>  $partyStakeCode,
                            ],
                        ];
                    }
                    if($is_pos_testing_mode == 'Y'){
                        $party=[
                            [
                                'typ:partyCode' =>  $partyCode,
                                'typ:partyStakeCode'  =>  $partyStakeCode,
                            ],
                        ];
                    }
                    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
                    if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
                        $addons = $selected_CPA->compulsory_personal_accident;
                        foreach ($addons as $value) {
                            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                                    $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                                
                            }
                        }
                    }
                    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage") {
                        if ($requestData->business_type == 'newbusiness') {
                            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '3';
                        } else {
                            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '1';
                        }
                    }      
                    $premium_array = [
                        'typ:userCode'                 => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),//'MAHINDRA',
                        'typ:rolecode'                 => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                        'typ:PRetCode'                 => '0',
                        'typ:userId'                   => '',
                        'typ:stakeCode'                => 'BROKER',
                        'typ:roleId'                   => '',
                        'typ:userroleId'               => '',
                        'typ:branchcode'               => '',
                        'typ:PRetErr'                  => '',
                        'typ:polBranchCode'            => '',
                        'typ:productName'              => $product_name,
                        'typ:policyHoldercode'         => $proposal->state_id,
                        'typ:eventDate'                => $policy_start_date,
                        'typ:party'                    => $party,
                        'typ:netPremium'               => '',
                        'typ:termUnit'                 => 'G',
                        'typ:grossPremium'             => '',
                        'typ:policyType'               => '',
                        'typ:polInceptiondate'         => $policy_start_date,
                        'typ:polStartdate'             => $policy_start_date,
                        'typ:polExpirydate'            => $policy_end_date,
                        'typ:branchCode'               => '',
                        // 'typ:term'                     => '1',
                        'typ:term'                     => isset($cpa_tenure) ? $cpa_tenure : '1',
                        'typ:polEventEffectiveEndDate' => '',
                        'typ:productCode'              => $product_code,
                        'typ:policyHolderName'         => 'POLICY USER',
                        'typ:productId'                => $product_id,
                        'typ:serviceTax'               => '',
                        'typ:status'                   => '',
                        'typ:cbDetails'                => '',
                        'typ:sumInsured'               => '',
                        'typ:updateDate'               => '',
                        'typ:policyId'                 => '',
                        'typ:polDetailLastUpdateDate'  => '',
                        'typ:quoteNo'                  => '',
                        'typ:policyNo'                 => '',
                        'typ:policyDetailid'           => '',
                        'typ:documentLink'             => '',
                        'typ:polLastUpdateDate'        => '',
                        'typ:properties'               => [
                            [
                                'typ:value' => 'Model1TieUp',
                                'typ:name'  => 'channelcode',
                            ],
                            [
                                'typ:value' => 'NONINSPEC',
                                'typ:name'  => 'Break-In Indicator',
                            ],
                            [
                                'typ:value' => '',
                                'typ:name'  => 'Break-in Approval Number(Inspection Number)',
                            ],
                            [
                                'typ:value' => '',
                                'typ:name'  => 'Break-in Approval Date',
                            ],
                            [
                                'typ:value' => $is_pos,
                                'typ:name'  => $pos_name,
                            ]
    
                        ],
                        'typ:risks'                    => [
                            'typ:riskCode'       => 'VEHICLE',
                            'typ:riskSuminsured' => '0',
                            'typ:covers'         => $covers,
                            'typ:properties'     => $properties,
                        ],
                    ];
                    if($requestData->business_type == 'newbusiness'){
                        // $premium_array['typ:term'] = '5';
                        $premium_array['typ:term'] = isset($cpa_tenure) ? $cpa_tenure : 5;
                        $premium_array['typ:polExpirydate'] = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))); 
                    }
                    
                    $premium_array = trim_array($premium_array);
                    $get_response = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA_BIKE'),$premium_array, 'new_india',
                    [
                        'root_tag'      => 'typ:calculatePremiumMasterElement',
                        'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="http://iims.services/types/"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                        'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'new_india',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Premium Calculation',
                        'transaction_type' => 'proposal',
                    ]);
                    $data = $get_response['response'];
                    unset($covers, $properties, $premium_array);
                if ($data)
                {
                    $premium_resp = XmlToArray::convert((string) remove_xml_namespace($data));
                    $PRetCode = array_search_key('PRetCode', $premium_resp);
                    $basic_tp_key= 0;
                    $electrical_key = 0;
                    $nonelectrical_key = 0;
                    $basic_od_key = 0;
                    $calculated_ncb= 0;
                    $ll_paid_driver = 0;
                    $pa_paid_driver= 0;
                    $pa_unnamed_person = 0;
                    $additional_od_prem_cnglpg = 0;
                    $additional_tp_prem_cnglpg = 0;
                    $anti_theft_discount_key = 0;
                    $aai_discount_key = 0;
                    $total_od = 0;
                    $total_tp = 0;
                    $total_discount = 0;
                    $pa_owner = 0;
                    $zero_dep_amount = 0;
                    $eng_prot = 0;
                    $ncb_prot = 0;
                    $rsa = 0;
                    $tyre_secure_amount = 0;
                    $return_to_invoice_amount = 0;
                    $consumable_amount = 0;
                    $ncb_prot_amount = 0;
                    $belonging_amount = 0;
                    $total_tp_premium =0;
                    $pa_owner_driver =0;
                    $od_discount =0;
                    $voluntary_Deductible_Discount = 0;
                    if ($PRetCode == '0')
                    {
                        $properties = array_search_key('properties', $premium_resp);

                        $premium_resp = array_change_key_case_recursive($properties);
                        
                        unset($PRetCode, $properties);
                        foreach ($premium_resp as $key => $cover)
                        {
                            if ($cover['name'] === 'Calculated Premium')
                            {
                                $calculated_premium = $cover['value'];
                            }

                            if ($cover['name'] === 'Net Total Premium')
                            {
                                $net_total_premium = $cover['value'];
                            }

                            if ($cover['name'] === 'Service Tax')
                            {
                                $service_tax = $cover['value'];
                            }

                            if ($cover['name'] === 'Additional Premium for Electrical fitting')
                            {
                                $electrical_key = $cover['value'];
                            }
                            elseif ($cover['name'] === 'Additional Premium for Non-Electrical fitting')
                            {
                                $nonelectrical_key = $cover['value'];
                            }
                            // elseif ($cover['name'] === 'Basic OD Premium')
                            // {
                            //     $basic_od_key = $cover['value'];
                            // }
                            //elseif ($cover['name'] === 'Basic OD Premium_IMT')
                            elseif ($cover['name'] === 'IMT Rate Basic OD Premium')
                            {
                                $basic_od_key = $cover['value'];
                            }
                            elseif ($cover['name'] === 'Basic TP Premium' && $requestData->business_type !='newbusiness')
                            {
                                $basic_tp_key = $cover['value'];
                            }
                            //new business tp calculation
                            elseif (in_array($cover['name'], ['(#)Total TP Premium' , '(#)Total TP Premium for 2nd Year', '(#)Total TP Premium for 3rd Year', '(#)Total TP Premium for 4th Year', '(#)Total TP Premium for 5th Year'])  && $requestData->business_type =='newbusiness')
                            {
                                $basic_tp_key += $cover['value'];
                            }
                            elseif ($cover['name'] === 'Calculated NCB Discount')
                            {
                                $calculated_ncb = $cover['value'];
                            }
                            elseif ($cover['name'] === 'OD Premium Discount Amount')
                            {
                                $od_discount = ($cover['value']);
                            }                         
                            elseif ($cover['name'] === 'Compulsory PA Premium for Owner Driver')
                            {
                                $pa_owner_driver = $cover['value'];
                            }
                            
                            elseif ($cover['name'] === 'Legal Liability Premium for Paid Driver')
                            {
                                $ll_paid_driver = $cover['value'];
                            }
                            elseif ($cover['name'] === 'PA premium for Paid Drivers And Others')
                            {
                                $pa_paid_driver = $cover['value'];
                            }
                            elseif ($cover['name'] === 'PA premium for UnNamed/Hirer/Pillion Persons')
                            {
                                $pa_unnamed_person = $cover['value'];
                            }
                                                                
                            elseif ($cover['name'] == 'Additional OD Premium for CNG/LPG')
                            {
                                $additional_od_prem_cnglpg = $cover['value'];
                            }
                            elseif ($cover['name'] == 'Additional TP Premium for CNG/LPG')
                            {
                                $additional_tp_prem_cnglpg = $cover['value'];
                            }
                            elseif ($cover['name'] === 'Calculated Voluntary Deductible Discount')
                            {
                            $voluntary_Deductible_Discount = $cover['value'];
                            }
                            //addons
                            elseif (($productData->zero_dep == '0') && ($cover['name'] === 'Premium for nil depreciation cover'))
                            {
                                $zero_dep_key = $cover['value'];
                            }
                            
                        }

                        if($premium_type != 'third_party')
                        {
                            $total_od = $basic_od_key + $electrical_key +
                           $nonelectrical_key +$additional_od_prem_cnglpg ;
                        }
                        else
                        {
                            $total_od = '0';
                        }

                        
                        if($premium_type != 'own_damage')
                        {
                            $total_tp = $basic_tp_key + $pa_paid_driver + $pa_unnamed_person + $additional_tp_prem_cnglpg + $ll_paid_driver;
                        }
                        $total_discount = $anti_theft_discount_key + $aai_discount_key + $od_discount +  $voluntary_Deductible_Discount;
                        if($premium_type != 'third_party')
                        {
                            if ($requestData->is_claim == 'N')
                            {
                                $total_discount = $total_discount + $calculated_ncb;

                            }
                        }
                        if($productData->zero_dep == '0')
                        {
                            $zero_dep_amount = $zero_dep_key;
                            
                        }
                        $addon_premium = $zero_dep_amount + $eng_prot + $rsa +$tyre_secure_amount +$return_to_invoice_amount +$consumable_amount+$ncb_prot_amount + $belonging_amount;

                        $vehicleDetails = [
                            'manf_name' => $veh_data->motor_make,
                            'model_name' => $veh_data->motor_model,
                            'version_name' => $veh_data->motor_variant,
                            'seating_capacity' => $veh_data->motor_carrying_capacity,
                            'bikerying_capacity' => $veh_data->motor_carrying_capacity,
                            'cubic_capacity' => $veh_data->motor_cc,
                            'fuel_type' =>  $veh_data->motor_fule,
                            'vehicle_type' => 'CAR',
                        ];

                        $save = UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                            ->update([
                                        'od_premium' => $total_od,
                                        'tp_premium' => $total_tp,
                                        'ncb_discount' => $calculated_ncb,
                                        'addon_premium' => $addon_premium,
                                        'total_premium' => $calculated_premium,
                                        'service_tax_amount' => $service_tax,
                                        'final_payable_amount' => $net_total_premium,
                                        'cpa_premium' =>$pa_owner_driver,
                                        //'policy_no' => $proposal_response->policyNumber,
                                        'proposal_no' => '',
                                        'unique_proposal_id' => '',
                                        'is_policy_issued' => 'Accepted',
                                        'policy_start_date' => str_replace('/','-',$policy_start_date),
                                        'policy_end_date' => str_replace('/','-',$policy_end_date),
                                        'ic_vehicle_details' => $vehicleDetails,
                                        'additional_details_data' => $policyHoldercode,
                                ]);
                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);

                            NewIndiaPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        $kyc_url = '';
                        $is_kyc_url_present = false;
                        $kyc_message = '';
                        $kyc_status = true;

                        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                            $kyc_verification = self::ckycVerifications($proposal, $request, $policyHoldercode, $enquiryId);
                            extract($kyc_verification);
                            
                            $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                        }

                        return response()->json([
                            'status' => true,//$kyc_status,
                            'msg' => $kyc_status ? "Proposal Submitted Successfully!" : $kyc_message,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [                            
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => '',
                                'finalPayableAmount' => $net_total_premium, 
                                'is_breakin' => 'N',
                                'kyc_url' => $kyc_url,
                                'is_kyc_url_present' => $is_kyc_url_present,
                                'kyc_message' => $kyc_message,
                                'kyc_status' => $kyc_status,
                                'verification_status' => $kyc_status
                            ]
                        ]);

                    }
                    $PRetErr = array_search_key('PRetErr', $premium_resp);
                        return response()->json([
                            'status'  => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => $PRetErr
                        ]);
                }//proposal
                return response()->json([
                    'status'  => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.'
                ]);
            }
            else
            {
                return response()->json( [
                    'status'  => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => !empty($createPolicyHol_GenResponseElement['PRetErr']) && !is_array($createPolicyHol_GenResponseElement['PRetErr']) ? $createPolicyHol_GenResponseElement['PRetErr'] : 'Error in Create Policy Holder service '

                ]);
            }
        }
        else
        {
            return response()->json([
                'status'  => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.'
            ]);
        }
    }

    public static function ckycVerifications(UserProposal $proposal, $request, $policyHoldercode, $enquiryId)
    {
        $ckyc_controller = new CkycController;

        $ckyc_modes = [
            'ckyc_number' => 'ckyc_number',
            'pan_card' => 'pan_number_with_dob',
            'aadhar_card' => 'aadhar',
            'passport' => 'passport',
            'driving_license' => 'driving_licence',
            'voter_id' => 'voter_id'
        ];

        $ckyc_response = $ckyc_controller->ckycVerifications(new Request([
            'companyAlias' => 'new_india',
            'enquiryId' => $request['enquiryId'],
            'mode' => $ckyc_modes[$proposal->ckyc_type],
            'policyHolderCode' => $policyHoldercode
        ]))->getOriginalContent();

        $kyc_url = '';
        $is_kyc_url_present = false;
        $kyc_message = '';
        $kyc_status = false;

        if ($ckyc_response['status']) {
            $ckyc_response['data']['customer_details'] = [
                'name' => $ckyc_response['data']['customer_details']['fullName'] ?? null,
                'mobile' => $ckyc_response['data']['customer_details']['mobileNumber'] ?? null,
                'dob' => $ckyc_response['data']['customer_details']['dob'] ?? null,
                'address' => $ckyc_response['data']['customer_details']['address'] ?? null,
                'pincode' => $ckyc_response['data']['customer_details']['pincode'] ?? null,
                'email' => $ckyc_response['data']['customer_details']['email'] ?? null,
                'pan_no' => $ckyc_response['data']['customer_details']['panNumber'] ?? null,
                'ckyc' => $ckyc_response['data']['customer_details']['ckycNumber'] ?? null
            ];

            $ckyc_response_to_save['response'] = $ckyc_response;

            $updated_proposal = $ckyc_controller->saveCkycResponseInProposal(new Request([
                'company_alias' => 'kotak',
                'trace_id' => $request['enquiryId']
            ]), $ckyc_response_to_save, $proposal);

            foreach ($updated_proposal as $key => $u) {
                $proposal->$key = $u;
            }

            $proposal->save();

            $kyc_status = true;
        } else {
            $kyc_url = $ckyc_response['data']['redirection_url'] ?? null;
            $is_kyc_url_present = isset($ckyc_response['data']['redirection_url']);
            $kyc_message = 'CKYC Verification Failed';
        }

        return compact('kyc_status', 'is_kyc_url_present', 'kyc_message', 'kyc_url');
    }
}