<?php
namespace App\Http\Controllers\Payment\Services\Car;

use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\AgentIcRelationship;
use App\Models\NewIndiaDiscountGridV2;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
class newIndiaPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $old_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();


        $enquiryId = customDecrypt($request->enquiryId);

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();
        $approveproposal=newIndiaPaymentGateway::submit_motor_proposal($enquiryId ,$request);
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        if($approveproposal['status'] == 'true')
        {
            $reqchecksume = $approveproposal['str'];
        }
        else
        {
           return response()->json([
                'status' => false,
                'msg' => $approveproposal['message'],
                'data' => $approveproposal['message'],
            ]);
        }
        $reqchecksume = $approveproposal['str'];
        $checksume=newIndiaPaymentGateway::generate_checksum($reqchecksume);
        $msg = $reqchecksume.'|'.$checksume;
        $return_data = [
            'form_action' => config('constants.IcConstants.new_india.PAYMENT_GATEWAY_LINK_NEW_INDIA'),
            'form_method' => 'POST',
            'payment_type' => 0,
            'form_data' => [
                'msg' => $msg
            ]
        ];
        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
              ->update(['active' => 0]);

        PaymentRequestResponse::insert([
            'quote_id'                  => $quote_log_id,
            'ic_id' => $icId,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id' => $proposal->user_proposal_id,
            'payment_url'               => config('constants.IcConstants.new_india.PAYMENT_GATEWAY_LINK_NEW_INDIA'),
            'proposal_no'               => $proposal->proposal_no,
            'order_id'                  => $proposal->proposal_no,
            'amount'                    => $proposal->final_payable_amount,
            'return_url'                => route('car.payment-confirm', ['new_india', 'enquiry_id' => $enquiryId]),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'xml_data'                  => json_encode($return_data)
        ]);

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'ic_id' => $icId,
            'stage' => STAGE_NAMES['PAYMENT_INITIATED']
        ]);
        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }
    public static  function  submit_motor_proposal($enquiryId ,$request)
    { 
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
    	$requestData = getQuotation($enquiryId);
    	$productData = getProductDataByIc($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $enquiryId)->first();
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        $today_date =date('Y-m-d'); 
            $insured_name = '';
            if($requestData->previous_policy_expiry_date != 'New'){
                if(new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date))
                {
                    $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                }
                else if(new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date))
                {
                   $policy_start_date = date('d/m/Y', strtotime("+3 day")); 
                }
            }
            else
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day")); 
            }
            $mmv = get_mmv_details($productData,$requestData->version_id,'new_india');
            if($mmv['status'] == 1)
            {
            $mmv = $mmv['data'];
            }
            else
            {
                return  [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }

            //Pos details
            $is_pos = 'No';
            $pos_name = null;
            $pos_name_uiic = null;
            $partyCode = null;
            $partyStakeCode = 'FINANCIER';
            $is_pos_testing_mode = config('IC.NEWINDIA.CAR.TESTING_MODE') === 'Y';

            $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

            if($pos_data && !$is_pos_testing_mode)
            {
                //properties
                $is_pos = 'YES';
                $pos_name = $pos_data->agent_name;
                $pos_name_uiic = 'uiic';
                //properties

                //party
                $partyCode =  AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                ->pluck('new_india_code')
                ->first();
                if(empty($partyCode) || is_null($partyCode))
                {
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'POS details Not Available'
                    ];
                }                
                $partyStakeCode = 'POS';
                //party
            }
            if($is_pos_testing_mode)
            {
                //properties
                $is_pos = 'YES';
                $pos_name = 'POS Applicable';
                $pos_name_uiic = 'uiic';
                //properties
                //party
                $partyCode = config('IC.NEWINDIA.CAR.POS_PARTY_CODE');//PP00000015
                $partyStakeCode = 'POS';
                //party
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
                $reg_no = explode('-', strtoupper($requestData->vehicle_registration_no));
        $Color_as_per_RC_Book = 'As per RC book';
        $age = get_date_diff('month', $requestData->vehicle_register_date);
        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts','compulsory_personal_accident', 'discounts')
                                ->first();
        $anti_theft = 'N';
        $voluntary = 0;
        $legal_liability_to_paid_driver_cover_flag = '0';
        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'anti-theft device') {
                    $anti_theft = 'Y';
                }
                if ($data['name'] == 'voluntary_insurer_discounts') {
                    $voluntary = $data['sumInsured'];
                }
            }
        }
        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'automobile assiociation') {
                    $automobile_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime(strtr($requestData->automobile_association_expiry_date, '/', '-'))))));
                    $aai_name = 'Automobile Association of Eastern India';
                }
            }
        }

        $srilanka = 0;
        $pak = 0;
        $bang = 0;
        $bhutan = 0;
        $nepal = 0;
        $maldive = 0;
        $is_geo_ext = false;
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as  $data) {
                if ($data['name'] == 'LL paid driver') {
                    $legal_liability_to_paid_driver_cover_flag = '1';
                }
                if ($data['name'] == 'Geographical Extension' && $productData->zero_dep != 0) {
                    $is_geo_ext = true;
                    $is_geo_code = 1;
                    $countries = $data['countries'];
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
        if($premium_type == 'own_damage')
        {
            $legal_liability_to_paid_driver_cover_flag = '0';
        }
        if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10))
        {
            $car_registration_no = $reg_no[0] . '-0' . $reg_no[1];
        }
        $company_rto_data = DB::table('new_india_rto_master')
                        ->where('rto_code', strtr($requestData->rto_code, ['-' => '']))
                        ->first();
             
        if($requestData->business_type != 'newbusiness'){
            $reg = explode("-",$proposal->vehicale_registration_number);
             $Registration_No_4 = $reg[3];
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
            $insured_name = '';
         }else
         {
            $today_date =date('Y-m-d'); 
            $insured_name = '';
            if(new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date))
            {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            else if(new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date))
            {
               $policy_start_date = date('d/m/Y', strtotime("+3 day")); 
            }
            else
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day")); 
            }
            $PreInsurerAdd =  $insurer->address_line_1;
            $PreInsurerAdd2 =  $insurer->address_line_2;
            $prev_pincode = $insurer->pin;
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
         $cpa_with_greater_15 = 'Yes';
         $sex = $proposal->gender;
         $cpa_type ='false';
         if ($quote_data->vehicle_owner_type == 'I')
        {
            $Gender_of_Nominee = 'NA';
            if (!empty($additional['compulsory_personal_accident'])) {//cpa
                foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident'){
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

        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 69,
            'address_2_limit'   => 69            
        ];

        $getAddress = getAddress($address_data);

        $partyType = ($quote_data->vehicle_owner_type == 'I') ? 'I' : 'O';
        if ($partyType == 'I')
        {
            $policy_holder_array = [
                'typ:partyCode'       => $partyCode,
                'typ:partyStakeCode'  => $partyStakeCode,
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
                'typ:address'         => $getAddress['address_1'],
                'typ:firstName'       => $proposal->first_name,
                'typ:company'         => '',
                'typ:sex'             => $sex,
                'typ:address2'        => $getAddress['address_2'],
                'typ:EMailid2'        => '',
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
                'typ:clientType'      => ($proposal->financer_agreement_type !='')? 'HYPO' : 'NA',
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
                'typ:partyCode'       => $partyCode,
                'typ:partyStakeCode'  => $partyStakeCode,
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
                'typ:address'         => $getAddress['address_1'],
                'typ:firstName'       => '',
                'typ:company'         => '',
                'typ:sex'             => $sex,
                'typ:address2'        => $getAddress['address_2'],
                'typ:EMailid2'        => '',
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
                'typ:clientType'      => ($proposal->financer_agreement_type !='')? 'HYPO' : 'NA',
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
                $cover_pa_paid_driver = '0';
                $include_pa_cover_for_paid_driver = 'N';
                $cover_pa_unnamed_passenger = '0';
                $capital_si_for_unnamed_persons = '0';
                $individual_si_for_unnamed_person = '0';
                $include_pa_cover_for_unnamed_person = 'N';
                $lpg_cng_kit = '0.00';
                $bi_fuel_type = 'CNG';
                $extra_electrical_electronic_fittings = 'N';
                $extra_non_electrical_electronic_fittings = 'N';
                $total_value_of_electrical_electronic_fittings = '0';
                $total_value_of_non_electrical_electronic_fittings = '0';
                $type_of_fuel = $veh_data->motor_fule;
                $si_for_paid_drivers = '0';
                if (!empty($additional['addons'])) {
                    foreach ($additional['addons'] as $key => $data) {
                        if ($data['name'] == 'Road Side Assistance' && $age <=58) {
                            $rsa_cover = 'Yes';
                        }

                        if ($data['name'] == 'Tyre Secure' && $age <=34) {
                            $tyre_secure_cover = 'Yes';
                        }

                        if ($data['name'] == 'Engine Protector' && $age <=58 ) {
                            $engine_protector_cover = 'Yes';
                        }
        
                        if ($data['name'] == 'NCB Protection' && $age <=58 && $requestData->is_claim == 'N') {
                            $ncb_protector_cover = 'Yes'; 
                        }
        
                        if ($data['name'] == 'Return To Invoice' && $age <=34) {
                            $return_to_invoice_cover = 'Yes';
                        }
        
                        if ($data['name'] == 'Consumable' && $age <=58) {
                            $cost_of_consumable = 'Yes';
                        }
                        if ($data['name'] == 'Loss of Personal Belongings' && $age <=58) {
                            $loss_of_personal_belongings_cover = 'Yes'; 
                        }
        
                        if ($data['name'] == 'Key Replacement' && $age <=58) {
                            $key_replacement_cover = 'Yes';
                        }
                    }
                }

                if($productData->zero_dep == 0 && $premium_type != 'third_party')
                {
                    if($age < 36)
                    {
                        $discount_column_selector = 'discount_percent_with_addons_0_to_36_months';
                    }
                    elseif ($age>=36 && $age <=58) 
                    {
                        $discount_column_selector = 'discount_percent_with_addons_37_to_58_months';
                    }
                }
                else
                {
                    if($age< 120)
                    {
                        $discount_column_selector = 'discount_percent_without_addons_0_to_120_months';
                    }
                    elseif ($age>=120 && $age <=178) 
                    {
                        $discount_column_selector = 'discount_percent_without_addons_121_to_178_months';
                    }
                }
                $discount_percent = DB::table('new_india_motor_discount_grid')
                                    ->select("{$discount_column_selector} as discount_col")
                                    ->where('section','car')
                                    ->first();

                if (!empty($additional['additional_covers'])) {
                    foreach ($additional['additional_covers'] as $key => $data) {
                        if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                            $no_of_paid_drivers = '1';
                            $si_for_paid_drivers = $data['sumInsured'];
                            $include_pa_cover_for_paid_driver = 'Y';
                        }
            
                        if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                            $individual_si_for_unnamed_person = $data['sumInsured'];
                            $no_of_unnamed_persons = $veh_data->motor_carrying_capacity;
                            $include_pa_cover_for_unnamed_person = 'Y';
                            $capital_si_for_unnamed_persons = ($veh_data->motor_carrying_capacity) *$data['sumInsured'];
                        }
                    }
                }
                if ($requestData->previous_ncb !== '' && $requestData->is_claim == 'N' && $premium_type != 'third_party')
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
                            $type_of_fuel = 'CNG';#$bi_fuel_type .' '.$veh_data->motor_fule;
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
                    $idv = $quote->idv ;//- $total_accessories_idv;
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
                    switch ($premium_type) 
                    {
                        case 'comprehensive':
                            $coverCode = 'PACKAGE';
                            $product_id = '194398713122007';
                            $product_code = 'PC';
                            $product_name = 'PRIVATE CAR';
                            $plan_type = 'NBPL';
                            break;
                        case 'third_party' :
                            $coverCode =  'LIABILITY';
                            $product_id = '194398713122007';
                            $product_code = 'PC';
                            $product_name = 'PRIVATE CAR';
                            $plan_type = 'NLTL';
                            break;
                        case 'own_damage':
                            $coverCode =  'ODWTOTADON';//'ODWTHADDON';
                            $product_id = '120087123072019';
                            $product_code = 'SS';
                            $product_name = 'Standalone OD policy for PC';
                            break;
                        
                    }
                    //New india discount grid v2
                    if(config('IC_NEW_INDIA_CAR_DISCOUNT_GRID_V2') == 'Y'){
                        $discount_percent = NewIndiaDiscountGridV2::select("{$discount_column_selector} as discount_col")
                                        ->where('section', $product_code)
                                        ->first();
                    }

                    $covers = [
                        'typ:productCode'     => $product_code,
                        'typ:policyDetailid'  => '',
                        'typ:coverCode'       => ($productData->zero_dep == 1) ? $coverCode : (($premium_type== 'own_damage') ? 'ODWTHADDON' : 'ENHANCEMENTCOVER'),
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
                                'typ:value' => $legal_liability_to_paid_driver_cover_flag,
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
                                'typ:value' => $legal_liability_to_paid_driver_cover_flag == '1' ? 'Y' :'N',
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
                            [
                                'typ:value' => $engine_protector_cover,
                                'typ:name'  => 'Engine protect cover',
                            ],
                            [
                                'typ:value' => $cost_of_consumable,
                                'typ:name'  => 'Consumable Items Cover',
                            ],
                            [
                                'typ:value' => $tyre_secure_cover,
                                'typ:name'  => 'Tyre and Alloy Cover',
                            ],
                            [
                                'typ:value' => $key_replacement_cover,
                                'typ:name'  => 'Key Protect Cover',
                            ],
                            [
                                'typ:value' => $loss_of_personal_belongings_cover,
                                'typ:name'  => 'Personal Belongings Cover',
                            ],
                            [
                                'typ:value' => $rsa_cover,
                                'typ:name'  => 'Additional towing charges cover',
                            ],
                            [
                                'typ:value' => ($rsa_cover == 'Yes') ? '10000' :'0',
                                'typ:name'  => 'Additional towing charges Amount',
                            ],
                            [
                                'typ:value' => $ncb_protector_cover,
                                'typ:name'  => 'NCB Protection Cover',
                            ],
                            [
                                'typ:value' => $return_to_invoice_cover,
                                'typ:name'  => 'Return to invoice cover',
                            ],
                            [
                                'typ:value' => ($return_to_invoice_cover == 'Yes') ? $ex_showroom_price :'0',
                                'typ:name'  => 'Total Ex-Showroom Price',
                            ],
                            [
                                'typ:value' => ($return_to_invoice_cover == 'Yes') ? '1' :'0',
                                'typ:name'  => 'First Year Insurance Premium',
                            ],
                            [
                                'typ:value' => ($return_to_invoice_cover == 'Yes') ? '20' :'0',
                                'typ:name'  => 'Registration Charges',
                            ],
                        ],
                    ];
    
                    $covers = trim_array($covers);
                    $first_reg_date = strtr($requestData->vehicle_register_date, ['-' => '/']);
                    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                    $vehicleDate = strtr($vehicleDate, ['-' => '/']);
                    $existing_policy_expiry_date = strtr($requestData->previous_policy_expiry_date, ['-' => '/']);
                    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
                    $manf_year = $motor_manf_year_arr[1];
                    $properties = [
                        [
                            'typ:value' => $is_pos,
                            'typ:name'  => $pos_name_uiic,
                        ],
                        [
                            'typ:value' => 'Private',
                            'typ:name'  => 'Type of Private Car',
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
                            'typ:value' => $Registration_No_4 ?? $reg[3],
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
                            'typ:value' => ($requestData->business_type == 'newbusiness') ? 'NA' : $proposal->previous_policy_number,
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
                            'typ:value' => $bang == 1 ? 'Y' : 'N',
                            'typ:name'  => 'Extension of Geographical Area to Bangladesh',
                        ],
                        [
                            'typ:value' => $bhutan == 1 ? 'Y' : 'N',
                            'typ:name'  => 'Extension of Geographical Area to Bhutan',
                        ],
                        [
                            'typ:value' => $nepal == 1 ? 'Y' : 'N',
                            'typ:name'  => 'Extension of Geographical Area to Nepal',
                        ],
                        [
                            'typ:value' => $pak == 1 ? 'Y' : 'N',
                            'typ:name'  => 'Extension of Geographical Area to Pakistan',
                        ],
                        [
                            'typ:value' => $srilanka == 1 ? 'Y' : 'N',
                            'typ:name'  => 'Extension of Geographical Area to Sri Lanka',
                        ],
                        [
                            'typ:value' => $maldive == 1 ? 'Y' : 'N',
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
                            'typ:value' => in_array($type_of_fuel, ['CNG', 'CNGPetrol', 'LPG']) ? 'Y' : 'N',
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
                            'typ:name'  => 'Car in roadworthy condition and free from damage',
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
                            'typ:name'  => 'Whether vehicle is certified as Vintage car by Vintage and Classic Car Club',
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
                            'typ:value' => $is_geo_ext == 1 ? 'Y' : 'N',
                            'typ:name'  => 'Extension of Geographical Area required',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name'  => 'Whether Vehicle belong to Embassies or imported without Custom Duty',
                        ],
                        [
                            'typ:value' => $vehicle_zone,
                            'typ:name'  => 'Vehicle Zone for Private Car',
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
                            'typ:value' => !empty($proposal->nominee_name) ? $proposal->nominee_name : 'NA',
                            'typ:name'  => 'Name of Nominee',
                        ],
                        [
                            'typ:value' => !empty($proposal->nominee_age) ? $proposal->nominee_age : 'NA',
                            'typ:name'  => 'Age of Nominee',
                        ],
                        [
                            'typ:value' => !empty($proposal->nominee_relationship) ? $proposal->nominee_relationship : 'NA',
                            'typ:name'  => 'Relationship with the Insured',
                        ],
                        [
                            'typ:value' => $Gender_of_Nominee,
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
                            'typ:value' => ($premium_type == 'third_party') ? '0' :$total_accessories_idv,
                            'typ:name'  => 'IDV of Accessories',
                        ],
                        [
                            'typ:value' => ($premium_type == 'third_party') ? '0' : $idv + $total_accessories_idv,
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
                            'typ:value' => ($requestData->business_type == 'newbusiness') ? '' : $existing_policy_expiry_date,
                            'typ:name'  => 'Expiry date of previous Policy',
                        ],
                        // [
                        //     'typ:value' => ($premium_type == 'third_party') ? 'NLTL' : 'NBPL',
                        //     'typ:name'  => 'Policy Cover Plan Type',
                        // ],
                        [
                            'typ:value' => '0.00',
                            'typ:name'  => 'Policy Excess (Rs)',
                        ],
                        [
                            'typ:value' => $voluntary,
                            'typ:name'  => 'Voluntary Excess for PC',
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

                    $proposal_array = [
                        'typ:userCode'                 => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
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
                        'typ:policyHoldercode'         => $proposal->additional_details_data,
                        'typ:eventDate'                => $policy_start_date,
                        'typ:party'                    => $policy_holder_array,
                        'typ:netPremium'               => '',
                        'typ:termUnit'                 => 'G',
                        'typ:grossPremium'             => '',
                        'typ:policyType'               => '',
                        'typ:polInceptiondate'         => $policy_start_date,
                        'typ:polStartdate'             => $policy_start_date,
                        'typ:polExpirydate'            => $policy_end_date,
                        'typ:branchCode'               => '',
                        'typ:term'                     => '1',
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
                        $proposal_array['typ:term'] = '3';
                        $proposal_array['typ:polExpirydate'] = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))); 
                    }
                    $proposal_array = trim_array($proposal_array);
                    $get_response = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),$proposal_array, 'new_india',
                    [
                        'root_tag'      => 'typ:SaveQuote_ApproveProposalElement',
                        'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="http://iims.services/types/"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                        'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'new_india',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Save Quote And Approve Proposal',
                        'transaction_type' => 'proposal',
                    ]);
                    $data = $get_response['response'];
                    
            if ($data)
            {
                    $proposal_resp = XmlToArray::convert((string) remove_xml_namespace($data));

                    $SaveQuote_ApproveProposalResponseElement = array_search_key('SaveQuote_ApproveProposalResponseElement', $proposal_resp);
                if ($SaveQuote_ApproveProposalResponseElement)
                {
                if ($SaveQuote_ApproveProposalResponseElement['PRetCode'] == '0' || $SaveQuote_ApproveProposalResponseElement['netPremium'] == null || $SaveQuote_ApproveProposalResponseElement['netPremium'] == '')
                {
                    return [
                        'status'  => 'false',
                        'message' => isset($SaveQuote_ApproveProposalResponseElement['PRetErr'])? $SaveQuote_ApproveProposalResponseElement['PRetErr'] :'Something went wrong. Please re-verify details which you have provided.',
                    ];
                }
                else
                {
                    $quoteNo = $SaveQuote_ApproveProposalResponseElement['quoteNo'];
                    $netPremium = ($SaveQuote_ApproveProposalResponseElement['netPremium']);

                    $pg_transaction_no = generate_random_number(floor((rand(0, 99999)) + 1), config('constants.IcConstants.NEW_INDIA.BROKER_NAME'), 5);#MIBL
                    UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                            ->update([
                                        'unique_proposal_id' => $quoteNo,
                                        'pol_sys_id' =>$SaveQuote_ApproveProposalResponseElement['policyId'],
                                        
                                ]);
                    if ($netPremium != 0)
                    {

                           $str_arr = [
                                config('constants.IcConstants.new_india.MERCHANT_ID_NEW_INDIA_MOTOR'),
                                $pg_transaction_no,
                                'NA',
                                $netPremium,
                                'NA',
                                'NA',
                                'NA',
                                'INR',
                                'NA',
                                'R',
                                config('constants.IcConstants.new_india.SECURITY_ID_NEW_INDIA_MOTOR'),
                                'NA',
                                'NA',
                                'F',
                                config('constants.IcConstants.new_india.AGGREGATOR_ID_NEW_INDIA_MOTOR'),
                                $quoteNo,
                                'NA',
                                'NA',
                                'NA',
                                'NA',
                                'NA',
                                route('car.payment-confirm', ['new_india']),
                           ];
    
                        $str = implode('|', $str_arr);
                        return [
                            'status'  => 'true',
                            'message' => $quoteNo,
                            'str'             => $str,
                        ];
                    }
                    else
                    {
                        return [
                            'status'  => 'false',
                            'message' => $SaveQuote_ApproveProposalResponseElement['PRetErr'],
                        ];
                    }
                }
            }
        }else
        {
            return[
                'status'  => 'false',
                'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
            ];
        }
    }
    public static function generate_checksum($req)
    { 
        $checksum = hash('sha256', (($req) . '|'.config('constants.IcConstants.new_india.CHECKSUM_KEY_NEW_INDIA_MOTOR')));
        return strtoupper($checksum);
    }
    public static function confirm($request)
    {
        $response_data = $request->all();
        if(!isset($response_data['msg']))
        {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $response_array = explode('|', ($response_data['msg']));
        $new_checksum_string = implode('|', array_slice($response_array, 0, -1));
        $final_checksum = strtoupper(hash('sha256', ($new_checksum_string . '|'.config('constants.IcConstants.new_india.CHECKSUM_KEY_NEW_INDIA_MOTOR'))));
        unset($new_checksum_string);
        if(empty($response_array[17]))
        {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $proposal = UserProposal::where('unique_proposal_id', $response_array[17])->first();
        $enquiryId =$proposal->user_product_journey_id;
        $policyid= QuoteLog::where('user_product_journey_id',$enquiryId)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policyid);
        if (((end($response_array)) == $final_checksum) && ($response_array[14] == '0300')) 
        {
            DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active',1)
                    ->update(
                        [
                            'response' => $response_data['msg'],
                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]
                    );
                    PolicyDetails::Create(
                        ['proposal_id' => $proposal->user_proposal_id],
                        [
                            'policy_start_date'=>$proposal->policy_start_date,
                            'idv'=>$proposal->idv,
                            'ncb'=>$proposal->ncb_discount,
                            'premium'=>$proposal->final_payable_amount
                        ]
                    );
            $request = [
                'ns1:userCode' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'ns1:rolecode' => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                'ns1:PRetCode' => '1',
                'ns1:userId' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'ns1:stakeCode' => 'BROKER',
                'ns1:roleId' => '',
                'ns1:userroleId' => '',
                'ns1:branchcode' => '',
                'ns1:PRetErr' => '',
                'ns1:sourceOfCollection' => 'A',
                'ns1:collectionNo' => '',
                'ns1:receivedFrom' => '',
                'ns1:instrumentAmt' => $proposal->final_payable_amount,
                'ns1:collections' => [
                    'ns1:accountCode' => config('constants.IcConstants.new_india.ACCOUNTCODE_NEW_INDIA'),
                    'ns1:draweeBankName' => '',
                    'ns1:subCode' => '',
                    'ns1:draweeBankCode' => '',
                    'ns1:collectionMode' => 'ECS',
                    'ns1:debitCreditInd' => 'D',
                    'ns1:scrollNo' => '',
                    'ns1:chequeType' => '',
                    'ns1:quoteNo' => $proposal->unique_proposal_id,
                    'ns1:collectionAmount' => $proposal->final_payable_amount,
                    'ns1:chequeDate' => '',
                    'ns1:chequeNo' => $response_array[2],
                    'ns1:draweeBankBranch' => '',
                ],
                'ns1:quoteNo' => $proposal->unique_proposal_id,
                'ns1:collectionType' => 'A',
                'ns1:policyNo' => '',
                'ns1:documentLink' => '',
            ];
            $get_response = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),$request, 'new_india',
            [
                'root_tag'      => 'ns1:collectpremium_IssuepolElement',
                'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body xmlns:ns1="http://iims.services/types/">#replace</soapenv:Body></soapenv:Envelope>',
                'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'new_india',
                'section' => $productData->product_sub_type_code,
                'method' =>'Policy Number Generation',
                'transaction_type' => 'proposal',
            ]);
            $data = $get_response['response'];
            $collect_premium_array_response = XmlToArray::convert(remove_xml_namespace($data));
            $policy_no = array_search_key('policyNo', $collect_premium_array_response);
            if($policy_no != '' || $policy_no != NULL  && !empty($policy_no))
            {
            $updateProposal = UserProposal::where('user_product_journey_id',$proposal->user_product_journey_id)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_no' => $policy_no,
                        ]);
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                    'policy_number' => $policy_no,

        ]);
            $document_array =
                [
                    'ns1:userCode' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                    'ns1:docs'=>
                    [
                        'ns1:value'=> '',
                        'ns1:name' =>''
                    ],
                    'ns1:indexType'=>
                    [
                        'ns1:index'=>'',
                        'ns1:type'=>''

                    ],
                    'ns1:policyId' => $proposal->pol_sys_id
                ];
            $get_response = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),$document_array, 'new_india',
            [
                'root_tag'      => 'ns1:fetchDocumentNameElement',
                'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body xmlns:ns1="http://iims.services/types/">#replace </soapenv:Body></soapenv:Envelope>',
                'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'new_india',
                'section' => $productData->product_sub_type_code,
                'method' =>'Fetch policy Document',
                'transaction_type' => 'proposal',
            ]);
            $fetch_data = $get_response['response'];
            $fetch_array_response = XmlToArray::convert(remove_xml_namespace($fetch_data));
            if(isset($fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'][0]) && !empty($proposal->policy_no))
            {
                try
                {
                $document_array = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'];
                $policy_pdf_search_name = 'POLICYSCHEDULECIRTIFICATE';
                $doc_id = 0;
                foreach ($document_array as $key => $value)
                    {
                        $doc_found = strpos($value['name'], $policy_pdf_search_name);
                        if($doc_found != false)
                        {
                            $doc_id = $key;
                        }
                    }
                    $document_id = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['indexType'][$doc_id]['index'];
                    $doc = config('constants.IcConstants.new_india.POLICY_DWLD_LINK_NEW_INDIA') . $document_id;
                    #$data = file_get_contents($doc);
                    $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id) . '.pdf';
                    try {
                        $policy_pdf = Storage::put($pdf_name, httpRequestNormal($doc, 'GET', [], [], [], [], false)['response']);
                    } catch (\Throwable $th) {
                        PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'ic_pdf_url' => $doc
                            ]);
                        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                        $data['ic_id'] = $proposal->ic_id;
                        $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                        updateJourneyStage($data);
                        #return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
                        return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
                    }
                    unset($doc_id,$doc_found);
                    $doc_link = '';
                    if ($data != false && $proposal->policy_no !='')
                    {
                       PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'pdf_url' => $pdf_name,
                                'ic_pdf_url' => $doc,
                                'status' => 'SUCCESS'
                            ]);

                       updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);
                       return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
                    }
                    else
                    {
                        PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update(
                            [
                                'policy_number' => $proposal->policy_no,
                                'ic_pdf_url' => $data,
                                #'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/'. md5($proposal->user_proposal_id). '.pdf',
                                'status' => 'SUCCESS'
                            ]
                        );
                        updateJourneyStage([
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                        return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
                    }
                }
                catch(\Exception $e)
                {
                    updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));

                }
            }
            
                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
            }else {
                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
            }
        }else {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('active',1)
                ->update([
                    'response' => $request->All(),
                    'status'   => STAGE_NAMES['PAYMENT_FAILED']
                ]);
     updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','FAILURE'));
        }
    }
    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            ->where(array('prr.active'=>1,'prr.status'=>STAGE_NAMES['PAYMENT_SUCCESS']))
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id','prr.response'
            )
            ->first();
        if($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found',
                'data'   => []
            ];
            return response()->json($pdf_response_data);
        }
        if($policy_details->ic_pdf_url == '')
        {
            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
            $enquiryId =$proposal->user_product_journey_id;
            $policyid= QuoteLog::where('user_product_journey_id',$enquiryId)->pluck('master_policy_id')->first();
            $productData = getProductDataByIc($policyid);
            $document_array =
            [
                'ns1:userCode' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'ns1:docs'=>
                [
                    'ns1:value'=> '',
                    'ns1:name' =>''
                ],
                'ns1:indexType'=>
                [
                    'ns1:index'=>'',
                    'ns1:type'=>''

                ],
                'ns1:policyId' => $proposal->pol_sys_id
            ];

            $get_response = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),$document_array, 'new_india',
            [
                'root_tag'      => 'ns1:fetchDocumentNameElement',
                'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body xmlns:ns1="http://iims.services/types/">#replace </soapenv:Body></soapenv:Envelope>',
                'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'new_india',
                'section' => $productData->product_sub_type_code,
                'method' =>'Fetch policy Document',
                'transaction_type' => 'proposal',
            ]);
            $fetch_data = $get_response['response'];
            $fetch_array_response =  XmlToArray::convert((string) remove_xml_namespace($fetch_data));
            if(isset($fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'][0]))
            {
                try
                {
                    $document_array = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'];
                    $policy_pdf_search_name = 'POLICYSCHEDULECIRTIFICATE';
                    $doc_id = 0;
                    foreach ($document_array as $key => $value)
                    {
                        $doc_found = strpos($value['name'], $policy_pdf_search_name);
                        if($doc_found != false)
                        {
                            $doc_id = $key;
                        }
                    }

                    $document_id = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['indexType'][$doc_id]['index'];

                if(!empty($document_id))
                {
                    $doc = config('constants.IcConstants.new_india.POLICY_DWLD_LINK_NEW_INDIA') . $document_id;
                    $data = file_get_contents($doc);
                    unset($doc_id,$doc_found);
                    $doc_link = '';
                }
                else
                {
                    $data = false;
                }
                if ($data != false)
                {
                    Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id). '.pdf', $data);
                    PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id). '.pdf',
                            'ic_pdf_url'=>$doc
                        ]);

                    updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);

                    $pdf_response_data = [
                            'status' => true,
                            'msg' => 'sucess',
                            'data' => [
                                'policy_number' => $policy_details->policy_number,
                                'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id). '.pdf')
                            ]
                        ];
                }
                else
                {
                    updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        $pdf_response_data = [
                            'status' => false,
                            'msg' => 'Issue in pdf service',
                        ];
                }
                }
                catch(\Exception $e)
                {
                    updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                    $pdf_response_data = [
                            'status' => false,
                            'msg' => 'Issue in pdf service',
                        ];
                }
            }
            else
            {
                updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                $pdf_response_data = [
                            'status' => false,
                            'msg' => 'Issue in pdf service',
                        ];
            }

        }
        else
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => []
            ];
        }
        return response()->json($pdf_response_data);
    }
}