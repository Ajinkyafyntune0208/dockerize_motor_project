<?php

namespace App\Http\Controllers\Proposal\Services\V1\GCV;

use App\Http\Controllers\Controller;
use App\Models\SelectedAddons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Mtownsend\XmlToArray\XmlToArray;
use DateTime;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\UserProposal;
use App\Http\Controllers\SyncPremiumDetail\Services\FutureGeneraliPremiumDetailController;
include_once app_path().'/Helpers/CvWebServiceHelper.php';

class FutureGeneraliProposalSubmit extends Controller
{
    //
    public static function submit($proposal , $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote_log_data = DB::table('quote_log')
            ->where('user_product_journey_id',$enquiryId)
            ->select('idv')
            ->first();
        // * getting mmv 
        $parent_id = get_parent_code($productData->product_sub_type_id);
        $mmv = get_mmv_details($productData,$requestData->version_id,'future_generali', $parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL);
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $GVW = $mmv['gvw'] ?? 0;
        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
        
        // * vehicle details  and age
        $rto_code = $requestData->rto_code;
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true);
        $rto_data = DB::table('future_generali_rto_master')
                ->where('rta_code', strtr($rto_code, ['-' => '']))
                ->first();
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m);
        $vehicle_age = $age / 12;
        $idv = 0;

        // if (($vehicle_age > 5) && ($productData->zero_dep == '0'))
        // {
        //     return [
        //         'premium_amount' => 0,
        //         'status'         => false,
        //         'message'        => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //         'request' => [
        //             'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //             'vehicle_age' => $vehicle_age
        //         ]
        //     ];
        // }
        // $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';
        $reg_no = $requestData->vehicle_registration_no;

            $registration_number = $reg_no;
            // $registration_number = explode('-', $registration_number);

        
        // * policy start , end date and previos policy start ,end date  generation based on bussinesstype 
        $valid_puc = 'Y';

        $PolicyNo = $InsuredName = $PreviousPolExpDt = $PreviousPolStartDt = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date ='';
        if ($requestData->business_type == 'newbusiness') 
        {
          $motor_no_claim_bonus = '0';
          $motor_applicable_ncb = '0';
          $claimMadeinPreviousPolicy = 'N';
          $ncb_declaration = 'N';
          $NewCar = 'Y';
          $rollover = 'N';
          $policy_start_date = date('d/m/Y');
          $PreviousPolExpDt = $PreviousPolStartDt = '';
        }
        else
        {
            // rollover
          $usedCar = $valid_puc = 'Y';
          $motor_no_claim_bonus = $motor_applicable_ncb = '0';
          $claimMadeinPreviousPolicy = $requestData->is_claim;
          $ncb_declaration = 'N';
          $NewCar = 'N';
          $rollover = 'Y';
          $PolicyNo = '1234567';
          $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
          $ClientCode = '43207086';
        
          $tp_start_date  = $policy_start_date = Carbon::Parse($requestData->previous_policy_expiry_date)->addDay()->format('d/m/Y');
          $tp_end_date = Carbon::createFromFormat('d/m/Y',$tp_start_date)->addYear()->subDay()->format('d/m/Y');
          $PreviousPolStartDt = !empty($requestData->previous_policy_expiry_date) ? Carbon::createFromFormat('d-m-Y',$requestData->previous_policy_expiry_date)->subYear(1)->subDay(1)->format('d/m/Y') : ''; // ()
          $reg_no = isset($requestData->vehicle_registration_no) ? str_replace("-", "", $requestData->vehicle_registration_no) : '';
        }
        //  * premium type getting premium type based on bussiness logic
        $premium_type_array = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->select('premium_type_code','premium_type')
        ->first();
        $premium_type = $premium_type_array->premium_type_code;
        $policy_type = $premium_type_array->premium_type;
        $is_breakin = 'N';
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
            $is_breakin = 'Y';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
            $is_breakin = 'Y';
        }
        
       
        switch($premium_type)
        {
            case "comprehensive":
            $cover_type = "CO";
            break;
            case "third_party":
            $cover_type = "LO";
            break;
        }
        // * if calimed in previous policy   && if owner changed in previous policy
        $corporate_vehicle_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $previous_insure_name = $prev_policy_number = $prev_ic_address1 = $prev_ic_address2 = $prev_ic_pincode = $motor_expired_more_than_90_days = $previous_insurer_name =  '';
        
        if($requestData->business_type != 'newbusiness')
        {
            $today_date = Carbon::now()->format('d/m/Y') ;
            if($requestData->business_type == "breakin")
            {
                $policy_start_date = Carbon::createFromFormat('d/m/Y', $today_date)->addDay(1)->format('d/m/Y');
            }
            if($requestData->previous_policy_type == 'Not sure')
            {
                $days_sub = Carbon::now()->subDay(100)->format('d-m-Y');
                $requestData->previous_policy_expiry_date = Carbon::createFromFormat('d-m-Y',$days_sub)->format('d-m-Y');

                // * getting previous insurer details 

                
            }
            $previous_insure_name = DB::table('future_generali_prev_insurer')
                                ->where('insurer_id', $proposal->previous_insurance_company)->first();
            $previous_insurer_name = $previous_insure_name->name;
            $ClientCode = $proposal->previous_insurance_company;
            $PreviousPolExpDt = date('d/m/Y', strtotime($corporate_vehicle_quotes_request->previous_policy_expiry_date));
            $prev_policy_number = $proposal->previous_policy_number;

                $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
                $insurer = keysToLower($insurer);
            $prev_ic_address1 = $insurer->address_line_1;
            $prev_ic_address2 = $insurer->address_line_2;
            $prev_ic_pincode = $insurer->pin;
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);
            // $date_diff = 
            // dd('previous_pol_exp_dt',$requestData->previous_policy_expiry_date);    

            if($date_diff > 90)
            {
               $motor_expired_more_than_90_days = 'Y';
            }
            else
            {
                $motor_expired_more_than_90_days = 'N';

            }
            if ($claimMadeinPreviousPolicy == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party')
            {
                $ncb_declaration = 'Y';
                $motor_no_claim_bonus = $requestData->previous_ncb;
                $motor_applicable_ncb = $requestData->applicable_ncb;
            }
            else
            {
                $ncb_declaration = 'N';
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
            }

            if($claimMadeinPreviousPolicy == 'Y' && $premium_type != 'third_party') 
            {
                $motor_no_claim_bonus = $requestData->previous_ncb;
            }

            if($requestData->previous_policy_type == 'Third-party')
            {
                $ncb_declaration = 'N';
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
            }
        }
        if($is_breakin == 'Y')
        {
            $policy_start_date = Carbon::now()->addDay()->format('d/m/Y');
            if(in_array($premium_type_array->premium_type_code, ['third_party_breakin'])) {
                $policy_start_date = Carbon::now()->addDay(3)->format('d/m/Y');
            }
        }
        $policy_end_date =  Carbon::createFromFormat('d/m/Y',$policy_start_date)->addYear()->subDay()->format('d/m/Y');


        // * if owner type is corporate case or company
        $policy_holder_type = $requestData->vehicle_owner_type == 'I' ? 'I' : 'C';

        // * selected addons 
        $imt_23 = $usedCar = 'N';  
        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = $BiFuelKitSi = 0;
        $bifuel = 'false';
        $addon = $c_addons = [];

        if($selected_addons && $selected_addons->addons != NULL && $selected_addons->addons != '')
        {
            $addons = ($selected_addons->addons);
            foreach ($addons as $addon_value) {
                if ($addon_value['name'] == 'IMT - 23') 
                {
                    $imt_23 = 'Y';
                }
                if ($addon_value['name'] == 'Road Side Assistance') 
                {
                    array_push($addon, 'RODSA');
                }
                if ($addon_value['name'] == 'Zero Depreciation') 
                {
                    array_push($addon, 'ZODEP');
                   
                }
            }
        }

        if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
            {
                $accessories = ($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if($value['name'] == 'Electrical Accessories')
                    {
                        $IsElectricalItemFitted = 'true';
                        $ElectricalItemsTotalSI = $value['sumInsured'];
                    }
                    else if($value['name'] == 'Non-Electrical Accessories')
                    {
                        $IsNonElectricalItemFitted = 'true';
                        $NonElectricalItemsTotalSI = $value['sumInsured'];
                    }
                    else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                    {
                        $type_of_fuel = '5';
                        $bifuel = 'true';
                        $Fueltype = 'CNG';
                        $BiFuelKitSi = $value['sumInsured'];
                    }
                }
            }

            //PA for un named passenger
            $IsPAToUnnamedPassengerCovered = 'false';
            $PAToUnNamedPassengerSI = 0;
            $IsLLPaidDriver = '';

            if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
            $additional_covers = $selected_addons->additional_covers;
            foreach ($additional_covers as $value) {
              
                if($value['name'] == 'LL paid driver/conductor/cleaner')
                {
                    $IsLLPaidDriver = '1';
                }
                // if($value['name'] == 'PA paid driver/conductor/cleaner')
                // {
                //     $IsPAPaidDriver = '1';
                //     $papaidDriverSumInsured = $value['sumInsured'];
                // }
            }
            }
            $IsTPPDDiscount = '';

            if($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '')
            {
                $discount = $selected_addons->discounts;
                foreach ($discount as $value) {
                  
                    if($value['name'] == 'TPPD Cover')
                    {
                         $IsTPPDDiscount = 'Y';
                    }
                }
            }
            
            // $cpa_selected = false;
            // if($selected_addons && !empty($selected_addons->compulsory_personal_accident))
            // {
            //     $cpa_selected = true;
            // }

            $cpa_selected = false;
            if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
                $addons = $selected_addons->compulsory_personal_accident;
                foreach ($addons as $value) {
                    if(isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident'))
                    {
                        $cpa_selected = true;
                       
                    }
                 }
            }

            if(!empty($addon))
            {
                foreach ($addon as $value) 
                {
                    $c_addons[]['CoverCode'] = $value; 
                }
            }
            else
            {
                $c_addons = ['CoverCode' => []];
            }
            if(in_array($premium_type,['third_party','third_party_breakin']) /*|| $vehicle_age >= 3*/)
            {
                 $c_addons = ['CoverCode' => ''];
                 $addon_enable = 'N';
            }
            // else
            // {
                $c_addons;
                $addon_enable = 'Y';
            // }
            // ! contract and risk types 
            $contract_type = 'FCV';
            $risk_type = 'FGV';

            if($requestData->previous_policy_type == 'Not sure')
            {
                $is_breakin = 'Y';
                $usedCar = 'Y';
                $rollover = 'N';
            }
            

        // * pos details 
        $IsPos = $is_pos_enabled = 'N';
        $pos_data = [];
        if ($is_pos_enabled == 'Y') {
            # code...
            $pos_data = DB::table('pos_master as pm')
            ->where('pm.user_product_journey_id',$enquiryId)
            ->select('pm.*')
            ->first();
        }
        if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
              {
                  if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    $contract_type = 'PCV';
                    $risk_type = 'FGV';
                }
              
              }
            //   * customer details 
            $proposal->gender = (strtolower($proposal->gender) == "male" || $proposal->gender == "M") ? "M" : "F";
            $address_data = [
                'address' => $proposal->address_line1,
                'address_1_limit'   => 30,
                'address_2_limit'   => 30,
                'address_3_limit'   => 30,
                'address_4_limit'   => 30
            ];
            $getAddress = getAddress($address_data);

            // * customer salutation 
            if($requestData->vehicle_owner_type == "I")
            {
                if ($proposal->gender == "M")
                {
                    $salutation = 'MR';
                }
                else
                {
                    $salutation = 'MS';
                }
            }
            else
            {
                $salutation = '';
            }

              // * nominee details 
              if ($requestData->vehicle_owner_type == 'I' && $cpa_selected == true && $premium_type != "own_damage")
            {
                $CPAReq = 'Y';
                $cpa_nom_name = $proposal->nominee_name;
                $cpa_nom_age = $proposal->nominee_age;
                $cpa_nom_age_det = 'Y';
                $cpa_nom_perc = '100';
                $cpa_relation = $proposal->nominee_relationship;
                $cpa_appointee_name = '';
                $cpa_appointe_rel = '';
                $cpa_year = '';
            }
            else
            {
                $CPAReq = 'N';
                $cpa_nom_name = '';
                $cpa_nom_age = '';
                $cpa_nom_age_det = '';
                $cpa_nom_perc = '';
                $cpa_relation = '';
                $cpa_appointee_name = '';
                $cpa_appointe_rel = '';
                $cpa_year = '';
            }
            $type_of_vehicle = $vehicle_class = $body_type = '';
            if($requestData->gcv_carrier_type == 'PUBLIC')
            {
                $vehicle_class = 'A1';
            }
            elseif($requestData->gcv_carrier_type == 'PRIVATE')
            {
                
                $vehicle_class = 'A2';
            }
            if(!empty($mmv_data->no_of_wheels))
            {
                $vehicle_count = $mmv_data->no_of_wheels;
                switch ($vehicle_count) {
                    case '2':
                        $type_of_vehicle = 'T';
                        break;
                        case '3':
                            $type_of_vehicle = 'W';
                            if($requestData->gcv_carrier_type == 'PUBLIC')
                            {
                                $vehicle_class = 'A3';
                            }
                            elseif($requestData->gcv_carrier_type == 'PRIVATE')
                            {
                                $vehicle_class = 'A4';
                            }
                        break;
                    case '4':
                        $type_of_vehicle = 'O';
                        break;
                    
                    default:
                        $type_of_vehicle = 'O'; 
                        break;
                }
            }
            $body_type = FG_bodymaster($mmv_data->body_type);
            $body_type == false ? $body_type = $mmv_data->body_type : $body_type = $body_type;
            if($productData->product_identifier == 'basic')
            {
                $c_addons = ['CoverCode' => ''];
                $addon_enable = 'N';
            }
            $fuel_type = $fuel_type_arr = '';
            $fuel_type_arr = [
                'PETROL' => 'P',
                'DIESEL' => 'D',
                'LPG' => 'L',
                'BATTERY' => 'B',
                'GAS' => 'G',
                'DUAL' => 'A',
                'ELECTRIC BATTERY' => 'E',
                'UNLEADED PETROL' => 'U',
                'CNG' => 'C',
            ];
            $fuel_type = array_key_exists($mmv_data->fuel_code,$fuel_type_arr) ? $fuel_type_arr[$mmv_data->fuel_code] : '' ;
            if(empty($fuel_type))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'fuel type is not exist',
                ];
            }


            // * request generation 
            $uid = time().rand(10000, 99999);
        $proposal_array = [
            '@attributes'  => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            ],
            'Uid'          => $uid, //date('Ymshis').rand(0,9),
            'VendorCode'   => config('IC.FUTURE_GENERALI.V1.GCV.VENDOR_CODE'),
            'VendorUserId' =>  config('IC.FUTURE_GENERALI.V1.GCV.VENDOR_CODE'),
            'PolicyHeader' => [
                'PolicyStartDate' => $policy_start_date,
                'PolicyEndDate'   => $policy_end_date,
                'AgentCode'       => config('IC.FUTURE_GENERALI.V1.GCV.AGENT_CODE'),
                'BranchCode'      => ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.GCV.BRANCH_CODE'),
                'MajorClass'      => 'MOT',
                'ContractType'    => $contract_type,
                'METHOD'          => 'ENQ',
                'PolicyIssueType' => $policy_holder_type,
                'PolicyNo'        => $PolicyNo,
                'ClientID'        => '',
                'ReceiptNo'       => '',
              ],
              'POS_MISP'     => [
                  'Type'  => ($IsPos == 'Y') ? 'P' : '',
                  'PanNo' => ($IsPos == 'Y') ? $PanCardNo : '',
              ],
            'Client'       => [
                'ClientType'    => $requestData->vehicle_owner_type,
                'CreationType'  => 'C',
                'Salutation'    => $salutation,
                'FirstName'     => $proposal->first_name,
                'LastName'      => $proposal->last_name,
                'DOB'           => date('d/m/Y', strtotime($proposal->dob)),
                'Gender'        => $proposal->gender,
                'MaritalStatus' => $proposal->marital_status == "Single" ? 'S' : 'M',
                'Occupation'    => $requestData->vehicle_owner_type == 'C' ? 'OTHR' : $proposal->occupation,
                'PANNo'         => isset($proposal->pan_number) ? $proposal->pan_number : '',
                'GSTIN'         => isset($proposal->gst_number) ? $proposal->gst_number : '',
                'AadharNo'      => '',
                'EIANo'         => '',
                'CKYCNo'        => $proposal->ckyc_number,
                'CKYCRefNo'     => $proposal->ckyc_reference_id,
                'Address1'      => [
                    'AddrLine1'   => trim($getAddress['address_1']),
                    'AddrLine2'   => trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : '..',
                    'AddrLine3'   => trim($getAddress['address_3']),
                    'Landmark'    => trim($getAddress['address_4']),
                    'Pincode'     => $proposal->pincode,
                    'City'        => $proposal->city,
                    'State'       => $proposal->state,
                    'Country'     => 'IND',
                    'AddressType' => 'R',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => $proposal->mobile_number,
                    'EmailAddr'   => $proposal->email,
                ],

                'Address2'      => [
                    'AddrLine1'   => '',
                    'AddrLine2'   => '',
                    'AddrLine3'   => '',
                    'Landmark'    => '',
                    'Pincode'     => '',
                    'City'        => '',
                    'State'       => '',
                    'Country'     => '',
                    'AddressType' => '',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => $proposal->mobile_number,
                    'EmailAddr'   => $proposal->email,
                ],
                'VIPFlag'       => 'N',
                'VIPCategory'   => '',
            ],

            'Receipt'      => [
                'UniqueTranKey'   => '',
                'CheckType'       => '',
                'BSBCode'         => '',
                'TransactionDate' => '',
                'ReceiptType'     => '',
                'Amount'          => '',
                'TCSAmount'       => '',
                'TranRefNo'       => '',
                'TranRefNoDate'   => '',
            ],
            'Risk'         => [
                'RiskType'          => $risk_type,
                'Zone'              => $rto_data->zone,
                'Cover'             => $cover_type,
                'Vehicle'           => [
                    'TypeOfVehicle'           => $type_of_vehicle,
                    'VehicleClass'            => $vehicle_class,
                    'RTOCode'                 => str_replace('-', '', $requestData->rto_code),
                    'Make'                    => $mmv_data->make,
                    'ModelCode'               => $mmv_data->vehicle_code ,
                    'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : str_replace('-', '', $registration_number),
                    'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'ManufacturingYear'       => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'FuelType'                => $fuel_type,
                    'CNGOrLPG'                => [
                        'InbuiltKit'    =>  in_array($fuel_type, ['C', 'L']) ? 'Y' : 'N',
                        'IVDOfCNGOrLPG' => $bifuel == 'true' ? $BiFuelKitSi : '',
                    ],
                    'BodyType'                => $body_type ?? 'SOLO',
                    'EngineNo'                => isset($proposal->engine_number) ? $proposal->engine_number : '',
                    'ChassiNo'                => isset($proposal->chassis_number) ? $proposal->chassis_number: '',
                    'CubicCapacity'           => $mmv_data->cc,
                    'SeatingCapacity'         => $mmv_data->seating_capacity,
                    'IDV'                     => $quote_log_data->idv,
                    'GrossWeigh'              => $GVW ?? 16200,
                    'CarriageCapacityFlag'    => 'S',
                    'ValidPUC'                => $valid_puc,    
                    'TrailerTowedBy'          => '',  
                    'TrailerRegNo'            => '',
                    'NoOfTrailer'             => '',
                    'TrailerValLimPaxIDVDays' => '',
                    'TrailerChassisNo'        => '',
                    'TrailerMfgYear'          => '',
                    'SchoolBusFlag'          => '',
                    'BancaSegment'           => '',
                ],

                'InterestParty'     => [
                    'Code'     => $proposal->is_vehicle_finance == '1' ? 'HY' : '',
                    'BankName' => $proposal->is_vehicle_finance == '1' ? strtoupper($proposal->name_of_financer) : '',
                ],
                'AdditionalBenefit' => [
                    'Discount'                                 => '',
                    'ElectricalAccessoriesValues'              => $IsElectricalItemFitted == 'true' ? $ElectricalItemsTotalSI : '',
                    'NonElectricalAccessoriesValues'           => $IsNonElectricalItemFitted == 'true' ? $NonElectricalItemsTotalSI : '',
                    'FibreGlassTank'                           => '',
                    'GeographicalArea'                         => '',
                    'PACoverForUnnamedPassengers'              => $IsPAToUnnamedPassengerCovered == 'true' ? $PAToUnNamedPassengerSI : '',
                    'LegalLiabilitytoPaidDriver'               => $IsLLPaidDriver,
                    'LegalLiabilityForOtherEmployees'          => '',
                    'LegalLiabilityForNonFarePayingPassengers' => '',
                    'UseForHandicap'                           => '',
                    'AntiThiefDevice'                          => '',
                    'NCB'                                      => $motor_applicable_ncb,
                    'RestrictedTPPD'                           => $IsTPPDDiscount,
                    'PrivateCommercialUsage'                   => '',
                    'CPAYear'                                  => $cpa_year, 
                    'CPADisc'                                  => '',
                    'IMT23'                                    => $imt_23,
                    'CPAReq'                                   => $CPAReq,
                    'CPA'                                      => [
                        'CPANomName'       => $cpa_nom_name,
                        'CPANomAge'        => $cpa_nom_age,
                        'CPANomAgeDet'     => $cpa_nom_age_det,
                        'CPANomPerc'       => $cpa_nom_perc,
                        'CPARelation'      => $cpa_relation,
                        'CPAAppointeeName' => $cpa_appointee_name,
                        'CPAAppointeRel'   => $cpa_appointe_rel
                    ],
                    'NPAReq'              => 'N',
                    'NPA'                 => [
                        'NPAName'         => '',
                        'NPALimit'        => '',
                        'NPANomName'      => '',
                        'NPANomAge'       => '',
                        'NPANomAgeDet'    => '',
                        'NPARel'          => '',
                        'NPAAppinteeName' => '',
                        'NPAAppinteeRel'  => '',
                    ],
                ],
    
              // ! change this addonreq later for now using hard coded and remove this comment
                'AddonReq'          => $addon_enable, // FOR ZERO DEP value is Y and COVER CODE is PLAN1
                'Addon'             => $c_addons,
                 'PreviousInsDtls'   => [
                     'UsedCar'        => $usedCar,
                     'UsedCarList'    => [
                         'PurchaseDate'    => ($usedCar == 'Y') ? date('d/m/Y', strtotime($requestData->vehicle_register_date)) : '' ,
                         'InspectionRptNo' => '',
                         'InspectionDt'    => '',
                     ],
                     'RollOver'       => $rollover,
                     'RollOverList'   => [
                        'PolicyNo'              => ($rollover == 'N') ? '' :$prev_policy_number,
                        'InsuredName'           => ($rollover == 'N') ? '' :$previous_insurer_name,
                        'PreviousPolExpDt'      => ($rollover == 'N') ? '' :$PreviousPolExpDt,
                        'ClientCode'            => ($rollover == 'N') ? '' :$ClientCode,
                        'Address1'              => ($rollover == 'N') ? '' :$prev_ic_address1,
                        'Address2'              => ($rollover == 'N') ? '' :$prev_ic_address2,
                        'Address3'              => '',
                        'Address4'              => '',
                        'Address5'              => '',
                        'PinCode'               => ($rollover == 'N') ? '' :$prev_ic_pincode,
                         'InspectionRptNo'       => '',
                         'InspectionDt'          => '',
                         'NCBDeclartion'         => ($rollover == 'N') ? 'N' :$ncb_declaration,
                         'ClaimInExpiringPolicy' => ($rollover == 'N') ? 'N' :$claimMadeinPreviousPolicy,
                         'NCBInExpiringPolicy'   => ($rollover == 'N') ? 0 :$motor_no_claim_bonus,
                         'PreviousPolStartDt'    => $PreviousPolStartDt,
                         'TypeOfDoc'             => '',
                         'NoOfClaims'            => '',
                     ],
                     'NewVehicle'     => $NewCar,
                     'NewVehicleList' => [
                         'InspectionRptNo' => '',
                         'InspectionDt'    => '',
                     ],
                 ],
            ],
          ];

          if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
            $proposal_array['Risk']['PreviousTPInsDtls']['PreviousInsurer'] = '';
            $proposal_array['Risk']['PreviousTPInsDtls']['TPPolicyNumber'] = '';
            $proposal_array['Risk']['PreviousTPInsDtls']['TPPolicyEffdate'] = '';
            $proposal_array['Risk']['PreviousTPInsDtls']['TPPolicyExpiryDate'] = '';
        }

        $additional_data = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'CreatePolicy',
            'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
            'method' => 'Proposal submit', 
            'section' => 'GCV',
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name
        ];
        // dd($proposal_array);
        // * proposal submit 
        // * call api 
        $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.GCV.END_POINT_URL'), $proposal_array, 'future_generali', $additional_data);


        // *  getting response from api for addons and premium
        $data = $get_response['response'];
        $proposal_output_policy_data = '';
        if (!empty($data)) 
        {
            $proposal_output = html_entity_decode($data);
            $proposal_output = XmlToArray::convert($proposal_output);
          
            function show_errors($errors,$get_response)
            {
                if (isset($errors['Error'])) {
                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'message'        => $errors['Error']
                    ];
                } elseif (isset($errors['ErrorMessage'])) {
                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'message'        => $errors['ErrorMessage']
                    ];
                } elseif (isset($errors['ValidationError'])) {

                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'message'        => $errors['ValidationError']
                    ];
                }
            }
            // dd($proposal_output);
            $error_CreatePolicyResult = $proposal_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult'];

            if (!empty($error_CreatePolicyResult['Status']) && $error_CreatePolicyResult['Status'] == 'Fail') 
            {
                return show_errors($error_CreatePolicyResult,$get_response);
            }
            $errors_data_status = $proposal_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root'];
            if (!empty($errors_data_status['Status']) && $errors_data_status['Status'] == 'Fail') 
            {
                return show_errors($errors_data_status,$get_response);
            }
            $proposal_output_policy_data = $proposal_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];
            $total_od_premium = $total_tp_premium = $od_premium = $tp_premium = $liability = $pa_owner =$pa_unnamed = $lpg_cng_amount = $zero_dep_amount = $ncb_discount = $rsa = $return_to_invoice = $service_tax_od = $service_tax_tp = $service_tax = $discperc = $ncb_prot = $tyre_secure = $cover_pa_owner_driver_premium = $eng_prot = $discount_amount = $lpg_cng_tp_amount = $imt23_premium = $non_electrical_acc_premium = $electrical_acc_premium = $TPPDPremium = $ll_to_emp =  0;
            if ($proposal_output_policy_data['Status'] == 'Fail') {
                return show_errors($proposal_output_policy_data,$get_response);
            }
            else
            {
                $quote_no = $proposal_output_policy_data['ProductUINNo'];
              foreach ($proposal_output_policy_data['NewDataSet']['Table1'] as $key => $cover) {
                $cover = array_map('trim', $cover);
                $value = $cover['BOValue'];

                if (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'OD')) {
                    $total_od_premium = $value;
                } elseif (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'TP')) {
                    $total_tp_premium = $value;
                }
                // elseif (($cover['Code'] == 'Compulsory PA owner driver') && ($cover['Type'] == 'TP')) {
                //     $cover_pa_owner_driver_premium = $value;
                // } 
                elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD')) {
                    $od_premium = $value;
                } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'TP')) {
                    $tp_premium = $value;
                } elseif (($cover['Code'] == 'LLDE') && ($cover['Type'] == 'TP')) {
                    $liability = $value;
                } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                    $pa_owner = $value;
                } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                    $pa_unnamed = $value;
                } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'OD')) {
                    $lpg_cng_amount = $value;
                } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'TP')) {
                    $lpg_cng_tp_amount = $value;
                }  elseif (($cover['Code'] == 'ZODEP') && ($cover['Type'] == 'OD')) {
                    $zero_dep_amount = $value;
                } elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                    $ncb_discount = abs($value);
                } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                    $discount_amount = (str_replace('-', '', $value));
                } elseif (($cover['Code'] == 'ENGPR') && ($cover['Type'] == 'OD')) {
                    $eng_prot = $value;
                } elseif (($cover['Code'] == '00004') && ($cover['Type'] == 'OD')) {
                    $ncb_prot = $value;
                } elseif (($cover['Code'] == 'RODSA') && ($cover['Type'] == 'OD')) {
                    $rsa = $value;
                } elseif (($cover['Code'] == '00001') && ($cover['Type'] == 'OD')) {
                    $tyre_secure = $value;
                } elseif (($cover['Code'] == '00006') && ($cover['Type'] == 'OD')) {
                    $return_to_invoice = $value;
                } elseif (($cover['Code'] == '00005') && ($cover['Type'] == 'OD')) {
                    $return_to_invoice = $value;
                } elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'OD')) {
                    $service_tax_od = $value;
                }
                 elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'TP')) {
                    $service_tax_tp = $value;
                
                } elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                    $discperc = $value;
                }elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                    $electrical_acc_premium = $value;
                }elseif (($cover['Code'] == 'NEAV') && ($cover['Type'] == 'OD')) {
                    $non_electrical_acc_premium = $value;
                }elseif (($cover['Code'] == 'IMT23') && ($cover['Type'] == 'OD')) {
                    $imt23_premium = $value;
                } elseif (($cover['Code'] == 'RTC') && ($cover['Type'] == 'TP')) {
                    $TPPDPremium = (str_replace('-', '', $value));
                
                } elseif (($cover['Code'] == 'LLEE') && ($cover['Type'] == 'TP')) {
                    $ll_to_emp = ($value);
                }
            }
            $service_tax = $service_tax_od + $service_tax_tp;
            $addon_premium = $imt23_premium + $return_to_invoice + $rsa + $eng_prot + $zero_dep_amount + $lpg_cng_amount ;
            $final_total_discount = $ncb_discount + $discount_amount;
            $final_od_premium =  $od_premium - $final_total_discount + $addon_premium + $electrical_acc_premium + $non_electrical_acc_premium;
            $final_tp_premium = $tp_premium - $TPPDPremium + $lpg_cng_tp_amount + $liability + $ll_to_emp;
            $final_net_premium = $od_premium - $final_total_discount + $addon_premium + $final_tp_premium;
            $final_payable_amount = $total_od_premium + $total_tp_premium ;

            $vehicleDetails = [
                'manufacture_name' => $mmv_data->make,
                'model_name' => $mmv_data->model,
                // 'version' => $mmv_data->variant,
                'fuel_type' => $mmv_data->fuel_code,
                'seating_capacity' => $mmv_data->seating_capacity,
                'carrying_capacity' => $mmv_data->seating_capacity,
                'cubic_capacity' => $mmv_data->cc,
                'gross_vehicle_weight' => $mmv_data->gvw,
                'vehicle_type' => $parent_id,
                'body_type' => $mmv_data->body_type,
                'vehicle_code' => $mmv_data->vehicle_code,

            ];
            updateJourneyStage([
                'user_product_journey_id' =>$enquiryId,
                'ic_id' => $productData->company_id,
                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                'proposal_id' => $proposal->user_proposal_id
            ]);
            $policy_start_date  = Carbon::createFromFormat('d/m/Y',$policy_start_date)->format('d-m-Y');
            $policy_end_date    = Carbon::createFromFormat('d/m/Y',$policy_end_date)->format('d-m-Y');
            $save = UserProposal::find($proposal->user_proposal_id)
                                ->update([
                            'proposal_no' => $uid,
                            'unique_proposal_id' => $uid,
                            'od_premium' => $final_od_premium,
                            'tp_premium' => $final_tp_premium,
                            'ncb_discount' => $ncb_discount,
                            'addon_premium' => $addon_premium,
                            'total_premium' => $final_net_premium,
                            'service_tax_amount' => $service_tax,
                            'final_payable_amount' => $final_payable_amount,
                            'cpa_premium' => $pa_owner,
                            'total_discount' => $final_total_discount,
                            'policy_start_date' => $policy_start_date,
                            'policy_end_date' => $policy_end_date,
                            'ic_vehicle_details' => $vehicleDetails,
                            'is_breakin_case' => $is_breakin,
                            'vehicale_registration_number' => $registration_number,
                        
                    ]);

                    FutureGeneraliPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
                    

                    if($is_breakin == 'Y')
                    {
                        $inspection_data['fullname'] = ($proposal->owner_type == 'I')? $proposal->first_name." ".$proposal->last_name : $proposal->last_name.' .';
                        $inspection_data['email_addr'] = $proposal->email;
                        $inspection_data['mobile_no'] = $proposal->mobile_number;
                        $inspection_data['address'] = $proposal->address_line1.",".$proposal->address_line2.",".$proposal->address_line3;
                        $inspection_data['regNumber'] = str_replace('-',' ',$proposal->vehicale_registration_number);
                        $inspection_data['make'] = $mmv_data->make;
                        $inspection_data['brand'] = $mmv_data->vehicle_code;
                        $inspection_data['companyId'] = config('IC.FUTURE_GENERALI.V1.GCV.COMPANY_ID_MOTOR');
                        $inspection_data['branchId'] = config('IC.FUTURE_GENERALI.V1.GCV.BRANCH_ID_MOTOR');
                        $inspection_data['refId'] = 'FG'.rand(00000,99999);
                        $inspection_data['appId'] = config('IC.FUTURE_GENERALI.V1.GCV.APP_ID_MOTOR');
                        $inspection_data['enquiryId'] = $enquiryId;
                        $inspection_data['company_name'] = 'Future Generali India Insurance Co. Ltd.';
                        $inspection_data['Vehicle_category'] = 'GCV';
                        $proposal_date = date('Y-m-d H:i:s');
                        $inspection_response_array = create_request_for_inspection_through_live_check($inspection_data);

                        if($inspection_response_array)
                        {
                            $inspection_response_array_output = array_change_key_case_recursive(json_decode($inspection_response_array, TRUE));
                            if($inspection_response_array_output['status']['code'] == 200)
                            {

                                $breakin_response =
                                [
                                    'live_check_reference_number' => $inspection_response_array_output['data']['refid'],
                                    'inspection_message'=> isset($inspection_response_array_output['data']['status']) ? $inspection_response_array_output['data']['status'] : '',
                                    'inspection_reference_id_created_date' => $proposal_date,

                                ];

                                DB::table('cv_breakin_status')->insert([
                                    'user_proposal_id' => $proposal->user_proposal_id,
                                    'ic_id' => $productData->company_id,
                                    'breakin_number' =>  $breakin_response['live_check_reference_number'],
                                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);

                                updateJourneyStage([
                                    'user_product_journey_id' => $enquiryId,
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                    'proposal_id' => $proposal->user_proposal_id,
                                ]);


                                return response()->json([
                                    'status' => true,
                                    'msg' => "Proposal Submitted Successfully!",
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'data' => [
                                        'proposalId' => $proposal->user_proposal_id,
                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                        'proposalNo' => $uid,
                                        'finalPayableAmount' => $final_payable_amount,
                                        'is_breakin' => $is_breakin,
                                        'inspection_number' => $breakin_response['live_check_reference_number'],
                                    ]
                                ]);
                            }
                            else
                            {

                                 return [
                                            'type' => 'inspection',
                                            'status'  => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => 'Live Check API not reachable',
                                            'inspection_id'=> '',
                                        ];

                            }

                        }
                        else
                        {

                             return [
                                        'type' => 'inspection',
                                        'status'  => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => 'Live Check API not reachable,Did not got any response',
                                        'inspection_id'=> '',
                                    ];
                        }

                    }
                    else
                    {
                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $enquiryId,
                                'proposalNo' => $quote_no,
                                'finalPayableAmount' => $final_payable_amount,
                            ]
                        ]);
                    }

                    
                
            }
            
        }
        else
        {
            return [
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message'        => "insurer Not Reachable"
            ];
        }

    }
}