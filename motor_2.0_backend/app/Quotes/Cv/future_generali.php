<?php

use App\Models\IcVersionMapping;
use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\MotorManufacturer;
use App\Models\MotorModel;
use App\Models\MotorModelVersion;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use Carbon\Carbon;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    if(config('IC.FUTURE_GENERALI.V1.GCV.ENABLED') == 'Y')
    {
        include_once app_path() . '/Quotes/Cv/V1/GCV/future_generali.php';
        return getQuoteV1($enquiryId, $requestData, $productData);
    }
//   if(($requestData->ownership_changed ?? '' ) == 'Y')
//     {
//         return [
//             'premium_amount' => 0,
//             'status' => false,
//             'message' => 'Quotes not allowed for ownership changed vehicle',
//             'request' => [
//                 'message' => 'Quotes not allowed for ownership changed vehicle',
//                 'requestData' => $requestData
//             ]
//         ];
//     }
    $parent_id = get_parent_code($productData->product_sub_type_id) ;
    if($parent_id != 'GCV')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes not allowed for '.$parent_id,
        ];
    }
    $mmv = get_mmv_details($productData,$requestData->version_id,'future_generali');
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
    $GVW = $mmv['gvw'] ?? 0;
    if($GVW > 3500)
    {
        return  [
            'status'=> false,
            'message'=> 'GVW cannot be more than 3500',
            'premium_amount'=> 0
        ];
        // fg allow GCV quotes for GVW less then 3500 as per now
    } 

    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
  
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];
    }

    $mmv_data->manf_name = $mmv_data->make;
    $mmv_data->model_name = $mmv_data->model;
//    $mmv_data->version_name =  $mmv_data->model;
    $mmv_data->seating_capacity = $mmv_data->seating_capacity;
    $mmv_data->cubic_capacity = $mmv_data->cc;
    $mmv_data->fuel_type = $mmv_data->fuel_code;
    // dd("req fueltype $requestData->fuel_type","mmv fueltype $mmv_data->fuel_type");

    // vehicle age calculation
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m);
    $vehicle_age = $age / 12;
    $idv = 0;
    $idv = !empty($requestData->edit_idv) ? $requestData->edit_idv : 0;
    
    $type_of_vehicle = 'O';
    $vehicle_class = '';
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
    $fuel_type = array_key_exists($mmv_data->fuel_type,$fuel_type_arr) ? $fuel_type_arr[$mmv_data->fuel_type] : '' ;
    if(empty($fuel_type))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'fuel type is not exist',
        ];
    }

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
   
    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id',$requestData->user_product_journey_id)
    ->where('seller_type','P')
    ->first();
    $rta_code = $requestData->rto_code;
    if (preg_match('/^DL-(\d)$/', $rta_code, $matches)) {
        $rta_code = "DL-0" . $matches[1];
    }
    $rto_data = DB::table('future_generali_rto_master')
    ->where('rta_code', strtr($rta_code, ['-' => '']))
    ->first();
    $imt_23 = $usedCar = 'N';  
    $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
    $IsElectricalItemFitted = 'false';
    $ElectricalItemsTotalSI = 0;
    $IsNonElectricalItemFitted = 'false';
    $NonElectricalItemsTotalSI = $BiFuelKitSi = 0;
    $bifuel = 'false';
    if($selected_addons && $selected_addons->addons != NULL && $selected_addons->addons != '')
    {
        $addons = ($selected_addons->addons);
        foreach ($addons as $addon_value) 
        {
            if ($addon_value['name'] == 'IMT - 23') 
            {
                $imt_23 = 'Y';
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
                    $BiFuelKitSi =   $value['sumInsured'];
                }
            }
        }

         //PA for un named passenger
         $papaidDriverSumInsured = $IsLLPaidDriver = '';

         if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
        {
          $additional_covers = $selected_addons->additional_covers;
          foreach ($additional_covers as $value) {

              if($value['name'] == 'LL paid driver/conductor/cleaner')
              {
                  $IsLLPaidDriver = '1';
              }
            //   if($value['name'] == 'PA paid driver/conductor/cleaner')
            //   {
            //       $IsPAPaidDriver = '1';
            //       $papaidDriverSumInsured = $value['sumInsured'];
            //   }
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
       
            $motor_no_claim_bonus = $motor_applicable_ncb = '0';
            $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
            $policy_holder_type = ($requestData->vehicle_owner_type == "C" ? "C" : "I");
            $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
            $motor_manf_year = $motor_manf_year_arr[1];
            $motor_manf_date = '01-'.$requestData->manufacture_year;
            $contract_type = "FCV";
            $risk_type = "FGV";
            $IsPos = $valid_puc = 'N';
            $is_FG_pos_disabled = config('constants.motorConstant.IS_FG_POS_DISABLED');
            $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
            $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
            // cpa details 
            // $CPAReq = 'Y';
            // $cpa_nom_name = 'Legal Hair';
            // $cpa_nom_age = '21';
            // $cpa_nom_age_det = 'Y';
            // $cpa_nom_perc = '100';
            // $cpa_relation = 'SPOU';
            // $cpa_appointee_name = '';
            // $cpa_appointe_rel = '';
            // $cpa_year = '1';
            $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_year_data = isset($value['name']) ? '1' : '';
                    
                }
            }
        }
            if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage")
            {
                
                $CPAReq = 'Y';
                $cpa_nom_name = 'Legal Hair';
                $cpa_nom_age = '21';
                $cpa_nom_age_det = 'Y';
                $cpa_nom_perc = '100';
                $cpa_relation = 'SPOU';
                $cpa_appointee_name = '';
                $cpa_appointe_rel = '';
                $cpa_year = isset($cpa_year_data) ? $cpa_year_data : '';
    
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
            $claimMadeinPreviousPolicy = $requestData->is_claim;
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

            if($date_diff > 90)
            {
               $motor_expired_more_than_90_days = 'Y';
            }
            else
            {
                $motor_expired_more_than_90_days = 'N';

            }
            if ($requestData->business_type == 'newbusiness') 
            {
              $motor_no_claim_bonus = '0';
              $motor_applicable_ncb = '0';
              $claimMadeinPreviousPolicy = 'N';
              $ncb_declaration = 'N';
              $NewCar = 'Y';
              $rollover = 'N';
              $PolicyNo = $InsuredName = $PreviousPolExpDt = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date ='';
              $policy_start_date = date('d/m/Y');
            }
            else
            {
                // rollover
                $ncb_declaration = 'N';
                if($requestData->previous_policy_type == 'Third-party')
                {
                    $ncb_declaration = 'N';
                    $motor_no_claim_bonus = '0';
                    $motor_applicable_ncb = '0';
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
              $usedCar = $valid_puc = 'Y';
              $claimMadeinPreviousPolicy = $requestData->is_claim;
              $NewCar = 'N';
              $rollover = 'Y';
              $PolicyNo = '1234567';
              $InsuredName = 'Bharti Axa General Insurance Co. Ltd.';
              $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
              $ClientCode = '43207086';
              $Address1 = 'ABC';
              $Address2 = 'PQR';
              $tp_start_date  = $policy_start_date = Carbon::Parse($requestData->previous_policy_expiry_date)->addDay()->format('d/m/Y');
              $tp_end_date = Carbon::createFromFormat('d/m/Y',$tp_start_date)->addYear()->subDay()->format('d/m/Y');
              $reg_no = isset($requestData->vehicle_registration_no) ? str_replace("-", "", $requestData->vehicle_registration_no) : '';
            }
            if($requestData->previous_policy_type == 'Not sure')
            {
                $is_breakin = 'Y';
                $usedCar = 'Y';
                $rollover = 'N';
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
              if($is_breakin == 'Y')
              {
                  $policy_start_date = Carbon::now()->addDay()->format('d/m/Y');
                  if(in_array($premium_type_array->premium_type_code, ['third_party_breakin'])) {
                      $policy_start_date = Carbon::now()->addDay(3)->format('d/m/Y');
                  }
                }
                $policy_end_date =  Carbon::createFromFormat('d/m/Y',$policy_start_date)->addYear()->subDay()->format('d/m/Y');
            switch($premium_type)
            {
                case "comprehensive":
                $cover_type = "CO";
                break;
                case "third_party_breakin":
                case "third_party":
                    $cover_type = "LO";
                    $NonElectricalItemsTotalSI = $ElectricalItemsTotalSI = '';
                    $imt_23 = 'N';
                    break;
            }
            $c_addons = $addon = [];
            $addon_enable = '';
            $addon = ['ZODEP','RODSA'];
            foreach ($addon as $value) 
            {
                $c_addons[]['CoverCode'] = $value; 
            }
            if(in_array($premium_type,['third_party','third_party_breakin']) || $vehicle_age >= 3)
            {
                 $c_addons = ['CoverCode' => ''];
                 $addon_enable = 'N';
            }
            // else
            // {
                $c_addons;
                $addon_enable = 'Y';
            // }

            if($productData->product_identifier == 'basic')
            {
                $c_addons = ['CoverCode' => ''];
                $addon_enable = 'N';
            }
            $body_type = FG_bodymaster($mmv_data->body_type);
            $body_type == false ? $body_type = $mmv_data->body_type : $body_type = $body_type;
            
            $quote_array = [
              '@attributes'  => [
                  'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                  'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
              ],
              
              'Uid'          => time().rand(10000, 99999), //date('Ymshis').rand(0,9),
              'VendorCode'   => config('IC.FUTURE_GENERALI.GCV.VENDOR_CODE_FUTURE_GENERALI'),
              'VendorUserId' =>  config('IC.FUTURE_GENERALI.GCV.VENDOR_CODE_FUTURE_GENERALI'),
              'PolicyHeader' => [
                  'PolicyStartDate' => $policy_start_date,
                  'PolicyEndDate'   => $policy_end_date,
                  'AgentCode'       => config('IC.FUTURE_GENERALI.GCV.AGENT_CODE_FUTURE_GENERALI'),
                  'BranchCode'      => ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.GCV.BRANCH_CODE_FUTURE_GENERALI'),
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
                  'Salutation'    => 'MR',
                  'FirstName'     => 'sanjay',
                  'LastName'      => 'tamboli',
                  'DOB'           => '11/11/1999',
                  'Gender'        => 'M',
                  'MaritalStatus' => 'S',
                  'Occupation'    => 'OTHR',
                  'PANNo'         => '',
                  'GSTIN'         => '',
                  'AadharNo'      => '',
                  'EIANo'         => '',
                  'CKYCNo'        => '',
                  'CKYCRefNo'     => '',
                  'Address1'      => [
                      'AddrLine1'   => 'ghhtfh',
                      'AddrLine2'   => 'thfthhfh',
                      'AddrLine3'   => '67967758srgrgdr',
                      'Landmark'    => '',
                      'Pincode'     => '400001',
                      'City'        => 'delhi',
                      'State'       => 'delhi',
                      'Country'     => 'Ind',
                      'AddressType' => 'R',
                      'HomeTelNo'   => '',
                      'OfficeTelNo' => '',
                      'FAXNO'       => '',
                      'MobileNo'    => '8898675435',
                      'EmailAddr'   => 'sslbhhot@gmail.com',
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
                      'MobileNo'    => '',
                      'EmailAddr'   => '',
                  ],
                  'VIPFlag'       => 'N',
                  'VIPCategory'   => '',
              ],

              'Receipt'      => [
                  'UniqueTranKey'   => '',
                  'CheckType'       => '',
                  'BSBCode'         => '',
                  'TransactionDate' => '12/12/2021',
                  'ReceiptType'     => 'IVR',
                  'Amount'          => '20145',
                  'TCSAmount'       => '',
                  'TranRefNo'       => '49333201809111811420646',
                  'TranRefNoDate'   => '12/12/2021',
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
                      'ModelCode'               => $mmv_data->vehicle_code,
                      'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : (!empty($reg_no) ? $reg_no : str_replace('-', '', $rto_data->rta_code.'-AB-1234')),
                      'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                      'ManufacturingYear'       => date('Y', strtotime($motor_manf_date)),
                      'FuelType'                => $fuel_type,
                      'CNGOrLPG'                => [
                          'InbuiltKit'    =>  in_array($fuel_type, ['C', 'L']) ? 'Y' : 'N',
                          'IVDOfCNGOrLPG' => $bifuel == 'true' ? $BiFuelKitSi : '',
                      ],
                      'BodyType'                => $body_type ?? 'SOLO',
                    //   'BodyType'                => 'RICP',
                      'EngineNo'                => 'TESTENGINEE123456',
                      'ChassiNo'                => 'TESTCHASSIS8767894',
                      'CubicCapacity'           => $mmv_data->cc,
                      'SeatingCapacity'         => $mmv_data->seating_capacity,
                      'IDV'                     => $idv,
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
                      'Code'     => '',
                      'BankName' => '',
                  ],
                  'AdditionalBenefit' => [
                      'Discount'                                 => '',
                      'ElectricalAccessoriesValues'              => $IsElectricalItemFitted == 'true' ? $ElectricalItemsTotalSI : '',
                      'NonElectricalAccessoriesValues'           => $IsNonElectricalItemFitted == 'true' ? $NonElectricalItemsTotalSI : '',
                      'FibreGlassTank'                           => '',
                      'GeographicalArea'                         => '',
                      'PACoverForUnnamedPassengers'              => '',//$papaidDriverSumInsured,
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
                //    'AddonReq'          => ($premium_type == 'third_party' || $vehicle_age > 5 )? 'N' : 'Y',  //FOR ZERO DEP value is Y and COVER CODE is PLAN1
                  'AddonReq'          => $addon_enable, // FOR ZERO DEP value is Y and COVER CODE is PLAN1
                  'Addon'             =>  $c_addons,//['CoverCode' => []],
                //   'Addon'             =>  ['CoverCode' => []],
                
                   'PreviousInsDtls'   => [
                       'UsedCar'        => $usedCar,
                       'UsedCarList'    => [
                           'PurchaseDate'    => ($usedCar == 'Y') ? date('d/m/Y', strtotime($requestData->vehicle_register_date)) : '' ,
                           'InspectionRptNo' => '',
                           'InspectionDt'    => '',
                       ],
                       'RollOver'       => $rollover,
                       'RollOverList'   => [
                           'PolicyNo'              => ($rollover == 'N') ? '' :'1234567',
                           'InsuredName'           => ($rollover == 'N') ? '' :$InsuredName,
                           'PreviousPolExpDt'      => ($rollover == 'N') ? '' :$PreviousPolExpDt,
                           'ClientCode'            => ($rollover == 'N') ? '' :$ClientCode,
                           'Address1'              => ($rollover == 'N') ? '' :$Address1,
                           'Address2'              => ($rollover == 'N') ? '' :$Address2,
                           'Address3'              => '',
                           'Address4'              => '',
                           'Address5'              => '',
                           'PinCode'               => ($rollover == 'N') ? '' :'400001',
                           'InspectionRptNo'       => '',
                           'InspectionDt'          => '',
                           'NCBDeclartion'         => ($rollover == 'N') ? 'N' :$ncb_declaration,
                           'ClaimInExpiringPolicy' => ($rollover == 'N') ? 'N' :$claimMadeinPreviousPolicy,
                           'NCBInExpiringPolicy'   => ($rollover == 'N') ? 0 :$motor_no_claim_bonus,
                           'PreviousPolStartDt'    => '',
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
          $additional_data = [
              'requestMethod' => 'post',
              'enquiryId' => $enquiryId,
              'soap_action' => 'CreatePolicy',
              'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
              'method' => 'Quote Generation',
              'section' => 'GCV',
              'transaction_type' => 'quote',
              'productName'  => $productData->product_name
          ];
          
          $get_response = getWsData(config('constants.IcConstants.future_generali.END_POINT_URL_FUTURE_GENERALI'), $quote_array, 'future_generali', $additional_data);
          $data = $get_response['response'];
          $quote_output_policy_data = '';
          if (!empty($data)) 
          {
            $quote_output = html_entity_decode($data);
            $quote_output = XmlToArray::convert($quote_output);
          
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

            in_array($premium_type,['third_party','third_party_breakin']) ? $skip_second_call = true : $skip_second_call = false;
            $errors_data_status = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root'];
            if (!empty($errors_data_status['Status']) && $errors_data_status['Status'] == 'Fail') 
            {
                return show_errors($errors_data_status,$get_response);
            }
            $quote_output_policy_data = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];
            if($quote_output_policy_data['Status'] == 'Fail')
            {
               return show_errors($quote_output_policy_data,$get_response);
            }           
            else
            {
                
                if (isset($quote_output_policy_data['VehicleIDV'])) {
                    $quote_output_policy_data['VehicleIDV'] = str_replace(',', '', $quote_output_policy_data['VehicleIDV']);
                }

                if($premium_type != 'third_party')
                {
                    $idv = round($quote_output_policy_data['VehicleIDV']);
                    $min_idv = ceil($idv * 0.9);
                    $max_idv = floor($idv * 1.05);
                }
                else
                {
                    $idv = $min_idv = $max_idv = 0;
                }
            }
            // * continue code here 
            $quote_array['Risk']['Vehicle']['IDV'] =  $idv;
            $quote_array['Uid'] = time().rand(10000, 99999);
            $additional_data = [
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'soap_action' => 'CreatePolicy',
                'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
                'method' => 'Quote Generation - IDV changed',
                'section' => 'GCV',
                'transaction_type' => 'quote',
                'productName'  => $productData->product_name
            ];

            if(!$skip_second_call) {
                $get_response = getWsData(config('constants.IcConstants.future_generali.END_POINT_URL_FUTURE_GENERALI'), $quote_array, 'future_generali', $additional_data);
            }
            $data = $get_response['response'];
            if ($data) 
            {
                $quote_output = html_entity_decode($data);
                $quote_output = XmlToArray::convert($quote_output);
                
                if (isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy']))
                {
                    $error_status_2 = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy']['Status'];
                    $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];

                    if (isset($error_status_2['Status']) == 'Fail' && !empty($error_status_2['Status'])) 
                    {
                       return show_errors($error_status_2,$get_response);
                    }
                }
                else
                {
                    $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root'];
                    return show_errors($quote_output,$get_response);            
                }
            }

            if (isset($quote_output['VehicleIDV'])) {
                $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
            }

            $total_idv = ($premium_type == 'third_party') ? 0 : round($quote_output['VehicleIDV']);
            $total_od_premium =  $total_tp_premium =  $od_premium =  $tp_premium =  $liability =  $pa_owner =  $pa_unnamed =  $lpg_cng_amount =  $lpg_cng_tp_amount =  $electrical_amount =  $non_electrical_amount =  $ncb_discount =  $discount_amount =  $discperc =  $zero_dep_amount =  $eng_prot =  $ncb_prot =  $rsa =  $tyre_secure =  $return_to_invoice =  $consumable =  $basePremium =  $total_od =  $total_tp =  $total_discount = $imt23_premium = $TPPDPremium = $ll_to_emp =  0;
            foreach ($quote_output['NewDataSet']['Table1'] as $key => $cover) {
                $cover = array_map('trim', $cover);
                $value = $cover['BOValue'];

                if (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'OD')) {
                    $total_od_premium = $value;
                } elseif (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'TP')) {
                    $total_tp_premium = $value;
                } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD')) {
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
                } elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                    $electrical_amount = $value;
                } elseif (($cover['Code'] == 'NEAV') && ($cover['Type'] == 'OD')) {
                    $non_electrical_amount = $value;
                } elseif (($cover['Code'] == 'ZODEP') && ($cover['Type'] == 'OD')) {
                    $zero_dep_amount = $value;
                } elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                    $ncb_discount = abs($value);
                } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                    $discount_amount = round(str_replace('-', '', $value));
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
                    $consumable = $value;
                } elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                    $discperc = $value;
                } elseif (($cover['Code'] == 'IMT23') && ($cover['Type'] == 'OD')) {
                    $imt23_premium = $value;
                } elseif (($cover['Code'] == 'RTC') && ($cover['Type'] == 'TP')) {
                    $TPPDPremium = round(str_replace('-', '', $value));
                
                } elseif (($cover['Code'] == 'LLEE') && ($cover['Type'] == 'TP')) {
                    $ll_to_emp = round($value);
                }
            }
            $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
            $cal_tp_premium = $tp_premium - $TPPDPremium  ;
            $Tp_gst_premium = $cal_tp_premium * 0.12;
            $tp_addons_premium = $liability + $pa_unnamed + $lpg_cng_tp_amount + $ll_to_emp;
            $total_tp = $tp_premium + $tp_addons_premium;
            $total_discount = $ncb_discount + $discount_amount + $TPPDPremium;
            $basePremium = $total_od + $total_tp + $tp_addons_premium - $total_discount ;
            $tax_premium1 = $total_od - $total_discount + $tp_addons_premium ;
            $totalTax = $tax_premium1 * 0.18 + $Tp_gst_premium ;
            $final_premium = $basePremium + $totalTax;
            $policystartdatetime = DateTime::createFromFormat('d/m/Y', $policy_start_date);
            $policy_start_date = $policystartdatetime->format('d-m-Y');
            $policy_end_date = Carbon::createFromFormat('d/m/Y', $policy_end_date)->format('d-m-Y');
            // * continue here ok 
            $selected_addons_data = [
                'in_built' => [],
                'additional' => [
                    'zero_depreciation' => !empty($zero_dep_amount) ? $zero_dep_amount : '',
                    'consumables' => !empty($consumable) ? $consumable : '',
                    'road_side_assistance' => !empty($rsa) ? $rsa : '',
                    'imt23' => $imt23_premium,
                ],
            ];
            $additional_addons = ['imt23', 'zeroDepreciation','roadSideAssistance'];
            foreach($additional_addons as $ad_key=>$ad_val)
            {
                if(empty($rsa) && $ad_val == 'roadSideAssistance')
                {
                    unset($additional_addons[$ad_key]);
                }
                elseif(empty($imt23_premium) && $ad_val == 'imt23')
                {
                    unset($additional_addons[$ad_key]);
                }
                elseif(empty($zero_dep_amount) && $ad_val == 'zeroDepreciation')
                {
                    unset($additional_addons[$ad_key]);
                }
            }

            if($productData->zero_dep == 0 && empty($zero_dep_amount))
            {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Premium for Zero Depreciation not available',
                ];
            }

                $data_response = [
                    'status' => true,
                    'msg' => 'Found',
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'Data' => [
                        'idv' => $premium_type == 'third_party' ? 0 : round($total_idv),
                        'vehicle_idv' => $total_idv,
                        'min_idv' => $min_idv,
                        'max_idv' => $max_idv,
                        'rto_decline' => NULL,
                        'rto_decline_number' => NULL,
                        'mmv_decline' => NULL,
                        'mmv_decline_name' => NULL,
                        'policy_type' => $premium_type == 'third_party' ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'rto_no' => $requestData->rto_code,
                        'voluntary_excess' => $requestData->voluntary_excess_value,
                        'version_id' => $mmv_data->ic_version_code,
                        'showroom_price' => 0,
                        'fuel_type' => $requestData->fuel_type,
                        'ncb_discount' => $motor_applicable_ncb,
                        'tppd_discount' => $TPPDPremium,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_name' => $productData->product_sub_type_name,
                        'mmv_detail' => $mmv_data,
                        'master_policy_id' => [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $productData->policy_no,
                            'policy_start_date' => $policy_start_date,
                            'policy_end_date' =>   $policy_end_date,
                            'sum_insured' => $productData->sum_insured,
                            'corp_client_id' => $productData->corp_client_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'insurance_company_id' => $productData->company_id,
                            'status' => $productData->status,
                            'corp_name' => '',
                            'company_name' => $productData->company_name,
                            'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online
                        ],
                        'motor_manf_date' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'vehicleDiscountValues' => [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => 0,
                            'rto_cluster_id' => 0,
                            'car_age' => $vehicle_age,
                            'aai_discount' => 0,
                            'ic_vehicle_discount' =>  round($discount_amount),
                        ],
                        'basic_premium' => round($od_premium),
                        'deduction_of_ncb' => round($ncb_discount),
                        'tppd_premium_amount' => round($tp_premium),
                        'motor_electric_accessories_value' =>round($electrical_amount),
                        'motor_non_electric_accessories_value' => round($non_electrical_amount),
                        'motor_lpg_cng_kit_value' => round($lpg_cng_amount),
                        'cover_unnamed_passenger_value' => round($pa_unnamed),
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'default_paid_driver' => $liability,
                        'll_paid_driver_premium' => $liability,
                        'll_paid_conductor_premium' => 0,
                        'll_paid_cleaner_premium' => 0,
                        'motor_additional_paid_driver' => '',
                        'compulsory_pa_own_driver' => $pa_owner,
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' =>  round($total_od),
                        'cng_lpg_tp' => $lpg_cng_tp_amount,
                        'total_liability_premium' => round($total_tp),
                        'net_premium' => round($basePremium),
                        'service_tax_amount' => $totalTax,
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'voluntary_excess' => 0,
                        'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'quotation_no' => '',
                        'premium_amount' => round($final_premium),
                        'antitheft_discount' => '',
                        'final_od_premium' => round($total_od),
                        'final_tp_premium' => round($total_tp),
                        'final_total_discount' => round($total_discount),
                        'final_net_premium' => round($final_premium),
                        'final_gst_amount' => round($totalTax),
                        'final_payable_amount' => round($final_premium),
                        'service_data_responseerr_msg' => 'true',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => $requestData->business_type == 'newbusiness' ?  'New Business' : (($requestData->business_type == "rollover") ? 'Roll Over' : $requestData->business_type),
                        'service_err_code' => NULL,
                        'service_err_msg' => NULL,
                        'policyStartDate' => $policy_start_date,
                        'policyEndDate' => $policy_end_date,
                        'ic_of' => $productData->company_id,
                        'ic_vehicle_discount' => round($discount_amount),
                        'vehicle_in_90_days' => 0,
                        'get_policy_expiry_date' => NULL,
                        'get_changed_discount_quoteid' => 0,
                        'vehicle_discount_detail' => [
                            'discount_id' => NULL,
                            'discount_rate' => NULL
                        ],
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                        'policy_id' => $productData->policy_id,
                        'insurane_company_id' => $productData->company_id,
                        "max_addons_selection" => NULL,
                        'add_ons_data' => $selected_addons_data,
                        'applicable_addons' => $additional_addons
                    ]
                ];
                if ($ll_to_emp > 0 ) {
                    $data_response['Data']['other_covers']['LegalLiabilityToEmployee'] = $ll_to_emp;
                    $data_response['Data']['LegalLiabilityToEmployee'] = $ll_to_emp;
                }
                // return 
                // [
                //     'total_tp ' => $total_tp,
                //     'tp_addon_premium' => $tp_addons_premium,
                //     'cpa_premium' => $pa_owner,
                //     'res' => $data_response['Data']['total_liability_premium']
                // ];
                return camelCase($data_response);
        

          }
          else
          {
            return [
                'premium_amount' => 0,
                'final_payable_amount' => 0,
                'status'         => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message'        => "Insurer is not reachable"
            ];
          }
   
}
