<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\OrientalMotorCityMaster;
use App\Models\OrientalPinCityState;
use DateTime;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\AgentIcRelationship;
use Illuminate\Http\Request;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\OrientalPremiumDetailController;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class orientalSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {   
        if($proposal->vehicle_color == '')
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle color is mandatory'
            ];            
        }
        
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $mmv = get_mmv_details($productData,$requestData->version_id,'oriental');
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
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $rto_code = RtoCodeWithOrWithoutZero($quote_log->premium_json['vehicleRegistrationNo'],true);
        $rto_location = DB::table('oriental_rto_master')
                    ->where('rto_code', $rto_code)
                    ->first();
        $mmv_data->zone = $rto_location->rto_zone;

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);

        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $RSACover = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers); // new business
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $PreviousNilDepreciation = '0'; // addon
        $additional_details = json_decode($proposal->additional_details);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $master_policy->premium_type_id)
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

        $is_liability   = (($premium_type == 'third_party') ? true : false);
        $is_od          = (($premium_type == 'own_damage') ? true : false);

        switch ($premium_type) 
        {
            case 'comprehensive':
                $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-013" : "MOT-PRD-002";
                $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-012" : "MOT-PLT-001";
                $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? (($requestData->business_type == 'newbusiness') ? '' : 'MOT-PLT-001') : "MOT-PRD-002";
                break;
            case 'third_party':
                $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-013" : "MOT-PRD-002";
                $policy_type = 'MOT-PLT-002';
                $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? (($requestData->business_type == 'newbusiness') ? '' : 'MOT-PLT-002') : 'MOT-PLT-002';
                $URL = ($requestData->business_type == 'newbusiness') ? config('constants.motor.oriental.NBQUOTE_URL') : config('constants.motor.oriental.QUOTE_URL');
                break;
             case 'own_damage':
                $ProdCode = "MOT-PRD-016";
                $policy_type = 'MOT-PLT-001';
                $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? 'MOT-PLT-013' : 'MOT-PLT-001';
                $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                $PreviousPolicyToDt = Carbon::parse($PreviousPolicyFromDt)->addYear(3)->subDay(1)->format('d-M-Y');
                break;
            
        }

        $is_breakin = (!in_array($requestData->business_type, ['newbusiness', 'rollover'])) ? true : false;

        if ($requestData->business_type == 'newbusiness') {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $policy_start_date = date('d-M-Y');
            $tp_start_date = $policy_start_date;
            $policy_end_date = $premium_type == 'comprehensive' ? Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y') : Carbon::parse($policy_start_date)->addYear(5)->subDay(1)->format('d-M-Y');
            $tp_end_date = Carbon::parse($policy_start_date)->addYear(3)->subDay(1)->format('d-m-Y') ;
            $PolicyProductType = '3TP1OD';
            $BusinessType       = 'New Vehicle';
            $proposal_date = $policy_start_date;
            $soapAction = "GetQuoteMotor";
        } else {
            $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $tp_start_date = $policy_start_date;
            $tp_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y') ;
            if($is_breakin){
                $policy_start_date = date('d-M-Y' , strtotime('+3 day'));
                $tp_start_date = $policy_start_date;
                $tp_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y') ;
            }
            if($is_od && $is_breakin)
            {
                $policy_start_date = Carbon::parse(date('d-M-Y'))->addDay(1)->format('d-M-Y');
                $tp_start_date = $policy_start_date;
                $tp_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y') ;
            }
            $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d-M-Y');

            if($is_od)
            {
                $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                $PreviousPolicyToDt = Carbon::parse($PreviousPolicyFromDt)->addYear(3)->subDay(1)->format('d-M-Y');   
            }
            else
            {
                $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d-M-Y');
                $PreviousPolicyToDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d-M-Y');
            }

            $PolicyProductType = '1TP1OD';
            $BusinessType       = 'Roll Over';
            $proposal_date = date('d-M-Y', strtotime(date('Y-m-d', strtotime(str_replace('/', '-', date('d/m/Y'))))));
            $prev_policy_details = isset($additional_details->prepolicy) ? $additional_details->prepolicy :'';
            $soapAction = "GetQuoteMotor";
        }
        $is_breakin = (in_array($requestData->previous_policy_type, ['Not sure', 'Third-party'])) ? true : $is_breakin;

        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';

        $consumable = $engine_protection = $ncb_protction =$KeyReplacementYN =  $engine_protection = $LossOfPersonBelongYN = 'N'; 
        $PAforUnnamedPassenger=$PAforUnnamedPassenger= $LLtoPaidDriverYN=$PAPaidDriverConductorCleanerSI=$RSACover =$InvReturnYN =$nilDepreciationCover='0';
        $LLtoPaidDriverYN = '0';
        $LimitedTPPDYN = '0';
        $voluntary_insurer_discounts = 'TWVE1'; // Discount of 0
        $voluntary_discounts = [
            '0'     => 'TWVE1', // Discount of 0
            '500'  => 'TWVE2', // Discount of 750
            '750'  => 'TWVE3', // Discount of 1500
            '1000'  => 'TWVE4', // Discount of 2000
            '1500' => 'TWVE5', 
            '3000' => 'TWVE6',// Discount of 2500
        ];
        foreach ($discounts as $key => $value) {
            if (in_array('anti-theft device', $value)) {
                $antitheft = '1';
            }
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)){
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = '1';
            }
        }
        $is_geo_ext = false;
        $countries = [];
        foreach($additional_covers as $key => $value) {
            if (in_array('LL paid driver', $value)) {
                $LLtoPaidDriverYN = '1';
            }

            if (in_array('PA cover for additional paid driver', $value)) {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }

            if ($value['name'] == 'Geographical Extension') {
                $countries = $value['countries'] ;
                $is_geo_ext = "true";
            }
        }
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = 'ELEC';
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = 'NONELEC';
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = '1';
                $externalCNGKITSI = $value['sumInsured'];
            }
        }
        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value)) {
                $nilDepreciationCover = '1';
            }
            if (in_array('Road Side Assistance', $value)) {
                $RSACover = '1';
            }
            if (in_array('Return To Invoice', $value)) {
                $InvReturnYN = '1';
            }
            if (in_array('Engine Protector', $value)) {
                $engine_protection = 'Y';
            }
            if (in_array('Consumable', $value)) {
                $consumable = 'Y';
            }
        }

        // salutaion
        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $salutation = 'Mr';
            } else {
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $salutation = 'Mrs';
                } else {
                    $salutation = 'Ms';
                }
            }
        } else {
            $salutation = 'Miss';
        }
        // salutaion
        // CPA
        $cpa_selected = '0';

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
        $flex_22 = '';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $cpaArr)  {
                if (isset($cpaArr['name']) && $cpaArr['name'] == 'Compulsory Personal Accident')  {
                    $cpa_selected = '1';
                    $tenure = 1;
                    $tenure = isset($cpaArr['tenure']) ? $cpaArr['tenure'] : $tenure;
                    if($tenure === 5 || $tenure === '5')
                    {
                        $flex_22 = '0';
                    }
                    else
                    {
                        $flex_22 = '1';
                    }
                }
            }
        }

        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage")
        {
            if($requestData->business_type == 'newbusiness')
            {
                $flex_22 = isset($flex_22) ? $flex_22 : '0'; 
            }
            else{
                $flex_22 = isset($flex_22) ? $flex_22 : '1';
            }
        }
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = $cpareason = '';
        if (!empty($prev_policy_details)) {
            $cpareason = isset($prev_policy_details->reason) ? $prev_policy_details->reason :'';
            $cPAInsComp = isset($prev_policy_details->cPAInsComp) ? $prev_policy_details->cPAInsComp :'';
            $cPAPolicyNo =  isset($prev_policy_details->cpaPolicyNumber)? $prev_policy_details->cpaPolicyNumber : '';
            $cPASumInsured = isset($prev_policy_details->cpaSumInsured) ? $prev_policy_details->cpaSumInsured:'';
            $cPAPolicyFmDt = isset($prev_policy_details->cpaPolicyStartDate) ? Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y'):'';
            $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d/m/Y'):'';
        }
        // CPA
        $vehicleDetails = $additional_details->vehicle;
        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number[0] = '';
        }
        $state = explode("-", $rto_location->rto_code);
        $fuelType = [
            'PETROL' => 'MFT1',
            'DIESEL' => 'MFT2',
            'CNG' => 'MFT3',
            'OCTANE' => 'MFT4',
            'LPG' => 'MFT5',
            'BATTERY POWERED - ELECTRICAL' => 'MFT6',
            'OTHERS' => 'MFT99',
            'ELECTRIC' => 'MFT6'
        ];

        $fuel_type_code = $fuelType[strtoupper($mmv_data->veh_fuel_desc)];

        if($mmv_data->fuel_type_code == null || $mmv_data->fuel_type_code == '')
        {
            $mmv_data->fuel_type_code = $fuel_type_code;
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_oriental_non_pos =config('IS_ORIENTAL_NON_POS');
        $posp_code = '';
    
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
    
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log->idv <= 5000000 && $is_oriental_non_pos != 'Y')
        {
            $pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('oriental_code')
                    ->first();
            if(empty($pos_code) || is_null($pos_code))
            {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => 'POS details Not Available'
                ];
            }
            else
            {
                $posp_code = $pos_code;
            }
            $is_pos = true;
        }
        else
        {
            $is_pos = false;
        }
        // city_code
        // $city_code = OrientalPinCityState::where('PINCODE', $proposal->pincode)->first()->CITY_CODE;

        $ProductCodeArray = [
            'pos' => [
                'newbusiness' => [
                    'comprehensive' => 'MOT-POS-013',
                    'third_party' => 'MOT-POS-013',

                ],
                'rollover' => [
                    'comprehensive' => 'MOT-POS-002',
                    'third_party' => 'MOT-POS-002',
                    'own_damage' => 'MOT-POS-016',
                ],
            ],
            'non_pos' => [
                'newbusiness' => [
                    'comprehensive' => 'MOT-PRD-013',
                    'third_party' => 'MOT-PRD-013',
                ],
                'rollover' => [
                    'comprehensive' => 'MOT-PRD-002',
                    'third_party' => 'MOT-PRD-002',
                    'own_damage' => 'MOT-PRD-016',
                ],
            ],
        ];

        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
        $is_no_ncb = (($requestData->is_claim == 'Y') || $is_liability || in_array($requestData->previous_policy_type, ['Not sure', 'Third-party'])) ? true : false;

        $discount_grid = DB::table('oriental_non_maruti_discount_grid')
            ->where('VEH_MODEL', $mmv_data->vehicle_code)
            ->first();
        $discount_grid = keysToLower($discount_grid);
        $discount_percentage = '';
        $discount_per_array = json_decode(config("NEW_DISCOUNT_PERCENTAGE_VALUES"), 1) ?? [];

        if (config("NEW_DISCOUNT_PERCENTAGE_CHANGES") == "Y" && !empty($discount_per_array) && !$is_liability) {
            if ($is_new) {
                $discount_percentage = $discount_per_array["oriental"]["bike"]["newbusiness"];
            } else if ($is_od) {
                $discount_percentage = $discount_per_array["oriental"]["bike"]["own_damage"];
            } else {
                foreach ($discount_per_array["oriental"]["bike"]['other'] as $val) {
                    if ($val['from'] < $age && $val['to'] >= $age) {
                        $discount_percentage = $val['percentage'];
                        break;
                    }
                }
            }
        } else if (!empty($discount_grid) && !$is_liability) {
            if ($car_age <= 5) {
                $discount_percentage = $discount_grid->disc_upto_5yrs;
            } else {
                $discount_percentage = $discount_grid->disc_5_to_10yrs;
            }
        }

        if ($premium_type != 'third_party') {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'oriental', 'bike');
            if ($agentDiscount['status'] ?? false) {
                $discount_percentage = $discount_percentage >= $agentDiscount['discount'] ? $agentDiscount['discount'] : $discount_percentage;
            } else {
                if (!empty($agentDiscount['message'] ?? '')) {
                    return [
                        'status' => false,
                        'message' => $agentDiscount['message']
                    ];
                }
            }
        }
        
        $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

        if($additional_details->vehicle->isVehicleFinance)
        {
            $financerAgreementType = $additional_details->vehicle->financerAgreementType;
            $nameOfFinancer = $additional_details->vehicle->nameOfFinancer;
            $hypothecationCity = $additional_details->vehicle->hypothecationCity;
        }

        $vehicale_registration_no = ($proposal->vehicale_registration_number == 'NEW') ? 'NEW-1234' : $proposal->vehicale_registration_number;

        if (
            strlen(str_replace('-', '', $vehicale_registration_no)) > 10 &&
            str_starts_with(strtoupper($vehicale_registration_no), 'DL-0')
        ) {
            $vehicale_registration_no = explode('-', $vehicale_registration_no);
            $vehicale_registration_no[1] = ((int) $vehicale_registration_no[1] * 1);
            $vehicale_registration_no = implode('-', $vehicale_registration_no);
        }

        $vehIdv = '';
        if ($premium_type != 'third_party' && !empty($quote_log->idv)) {
            $vehIdv = $quote_log->idv + $ElectricalaccessSI + $NonElectricalaccessSI + $externalCNGKITSI;
        }

        $proposal_array = [
            'soap:Body' => 
                  [
                    $soapAction => 
                    [
                      'objGetQuoteMotorETT' => 
                      [
                          'LOGIN_ID'  => config('constants.motor.oriental.LOGIN_ID_ORIENTAL_MOTOR'),//LOGIN_ID, //'POLBZR_2',
                        //   'DLR_INV_NO' => 'POLBZR122',
                          'DLR_INV_NO' => config('constants.motor.oriental.LOGIN_ID_ORIENTAL_MOTOR'). '-' .customEncrypt($enquiryId),
                          'DLR_INV_DT' => $policy_start_date,
                          'PRODUCT_CODE' => $ProductCodeArray[($is_pos) ? 'pos' : 'non_pos'][$is_new ? 'newbusiness' : 'rollover'][$premium_type], //'MOT-PRD-001',
                          'POLICY_TYPE' => $policy_type,
                          'START_DATE' => $policy_start_date,
                          'END_DATE' => $policy_end_date,
                          'INSURED_NAME' => $proposal->first_name . ' ' . $proposal->last_name,
                          'ADDR_01' => $proposal->address_line1,
                          'ADDR_02' => $proposal->address_line2,
                          'ADDR_03' => $proposal->address_line3,
                          'CITY' => $proposal->city_id, // '3810',
                          'STATE' => $state[0],
                          'PINCODE' => $proposal->pincode,
                          'COUNTRY' => 'IND',
                          'EMAIL_ID' => $proposal->email,
                          'MOBILE_NO' => $proposal->mobile_number,
                          'TEL_NO' => '',
                          'FAX_NO' => '',
                          'ROAD_TRANSPORT_YN' => '',//$RSACover, //addon rsa
                          'INSURED_KYC_VERIFIED' => '',
                          'MOU_ORG_MEM_ID' => '',
                          'MOU_ORG_MEM_VALI' => '',
                          'MANUF_VEHICLE_CODE' => $mmv_data->ven_manf_code,
                          'VEHICLE_CODE' => $mmv_data->vehicle_code, //'VEH_MAK_5267'
                          'VEHICLE_TYPE_CODE' => ($requestData->business_type== 'newbusiness') ? 'W' : 'P',
                          'VEHICLE_CLASS_CODE' => 'CLASS_3', //for nonpos PC class_2
                          'MANUF_CODE' => $mmv_data->ven_manf_code, //need to discuss'VEH_MANF_044'
                          'VEHICLE_MODEL_CODE' => $mmv_data->vehicle_code,
                          'TYPE_OF_BODY_CODE' => 'SALOON', //for the time include in table
                          'VEHICLE_COLOR' => $proposal->vehicle_color,
                          'VEHICLE_REG_NUMBER' => $vehicale_registration_no,
                          'FIRST_REG_DATE' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                          'ENGINE_NUMBER' => $proposal->engine_number,
                          'CHASSIS_NUMBER' => $proposal->chassis_number,
                          'VEH_IDV' => $vehIdv, //isset($idv) ? $idv : ($requestData->business_type== 'newbusiness') ? $mmv_data['UPTO_3_YEAR'] : $mmv_data['UPTO_1_YEAR'],
                          'CUBIC_CAPACITY' => $mmv_data->cubic_capacity,
                          'THREEWHEELER_YN' => '0',
                          'SEATING_CAPACITY' => $mmv_data->seating_capacity,
                          'VEHICLE_GVW' => '',
                          'NO_OF_DRIVERS' => '1',
                          'FUEL_TYPE_CODE' => $mmv_data->fuel_type_code, 
                          'RTO_CODE' => $rto_code,
                          'ZONE_CODE' => $mmv_data->zone,
                          'GEO_EXT_CODE' => '',
                          'VOLUNTARY_EXCESS' => $voluntary_insurer_discounts,
                          'MEMBER_OF_AAI' => '0',
                          'ANTITHEFT_DEVICE_DESC' => $antitheft,
                          'SIDE_CAR_ACCESS_DESC' => '',
                          'SIDE_CARS_VALUE' => '',
                          'TRAILER_DESC' => '',
                          'TRAILER_VALUE' => '',
                          'ARTI_TRAILER_DESC' => '',
                          'ARTI_TRAILER_VALUE' => '',
                          'PREV_YR_ICR' => '',
                          'NCB_DECL_SUBMIT_YN' => ($requestData->is_claim == 'Y') ? 'N': 'Y',
                          'LIMITED_TPPD_YN' => $LimitedTPPDYN,
                          'RALLY_COVER_YN' => '',
                          'RALLY_DAYS' => '',
                          'NIL_DEP_YN' => $nilDepreciationCover, //Zero DEP
                          'FIBRE_TANK_VALUE' => '0',
                          'ALT_CAR_BENEFIT' => '',
                          'PERS_EFF_COVER' => '', //Loss of personal belongings
                          'NO_OF_PA_OWNER_DRIVER' => $cpa_selected, // Pa cover to owner driver
                          'NO_OF_PA_NAMED_PERSONS' => '',
                          'PA_NAMED_PERSONS_SI' => '',
                          'NO_OF_PA_UNNAMED_PERSONS' => $PAforUnnamedPassenger,
                          'PA_UNNAMED_PERSONS_SI' => $PAforUnnamedPassengerSI,
                          'NO_OF_PA_UNNAMED_HIRER' => '',
                          'NO_OF_LL_EMPLOYEES' => '',
                          'NO_OF_LL_PAID_DRIVER' => $LLtoPaidDriverYN, //ll to paid driver
                          'NO_OF_LL_SOLDIERS' => '',
                          'OTH_SINGLE_FUEL_CVR' => '',
                          'IMP_CAR_WO_CUSTOMS_CVR' => '',
                          'DRIVING_TUITION_EXT_CVR' => '',
                          'NO_OF_COOLIES' => '',
                          'NO_OF_CONDUCTORS' => '',
                          'NO_OF_CLEANERS' => '',
                          'TOWING_TYPE' => '',
                          'NO_OF_TRAILERS_TOWED' => '',
                          'NO_OF_NFPP_EMPL' => '',
                          'NO_OF_NFPP_OTH_THAN_EMPL' => '',
                          'DLR_PA_NOMINEE_NAME' => $proposal->nominee_name == null ? '' : $proposal->nominee_name,
                          'DLR_PA_NOMINEE_DOB' => date('d-M-Y', strtotime($proposal->nominee_dob)),
                          'DLR_PA_NOMINEE_RELATION' => $proposal->nominee_relationship == null ? '' : $proposal->nominee_relationship,
                          'RETN_TO_INVOICE' => (($car_age > 2) || ($interval->y == 2 && ($interval->m > 0 || $interval->d > 0))) ? '0': $InvReturnYN, //Return to invoice
                          'HYPO_TYPE' => $financerAgreementType,
                          'HYPO_COMP_NAME' => $nameOfFinancer,
                          'HYPO_COMP_ADDR_01' => $hypothecationCity,
                          'HYPO_COMP_ADDR_02' => '',
                          'HYPO_COMP_ADDR_03' => '',
                          'HYPO_COMP_CITY' => '',
                          'HYPO_COMP_STATE' => '',
                          'HYPO_COMP_PINCODE' => '',
                          'PAYMENT_TYPE' => 'OT',
                          'NCB_PERCENTAGE' => ($is_no_ncb ? '' : $requestData->previous_ncb),
                          'PREV_INSU_COMPANY' => ($is_od ? $additional_details->prepolicy->tpInsuranceCompanyName : $proposal->previous_insurance_company),
                          'PREV_POL_NUMBER' => ($is_od ? $additional_details->prepolicy->tpInsuranceNumber : $proposal->previous_policy_number),
                          'PREV_POL_START_DATE' => ($is_od ? Carbon::parse($additional_details->prepolicy->tpStartDate)->format('d-M-Y') : $PreviousPolicyFromDt),
                          'PREV_POL_END_DATE' => ($is_od ? Carbon::parse($additional_details->prepolicy->tpEndDate)->format('d-M-Y') : $PreviousPolicyToDt),
                          'EXIS_POL_FM_OTHER_INSR' => '0',
                          'IP_ADDRESS' => '',
                          'MAC_ADDRESS' => '',
                          'WIN_USER_ID' => '',
                          'WIN_MACHINE_ID' => '',
                          'DISCOUNT_PERC' => trim($discount_percentage),
                        //   'P_FLEX_19' => 'LF0000000041',
                          'LPE_01' => '', //Loss of personal belongings
                          'FLEX_01' => $PAPaidDriverConductorCleanerSI,
                          'FLEX_02' => '',
                          'FLEX_03' => ($requestData->business_type== 'newbusiness') ? '' : date('Y', strtotime('01-'.$requestData->manufacture_year)), //manf year
                          'FLEX_05' => '',
                          'FLEX_06' => '',
                          'FLEX_07' => '',
                          'FLEX_08' => '', //GSTNO
                          'FLEX_09' => '', //towing
                          'FLEX_10' => '',
                          'FLEX_19' => $posp_code,
                          
                          'FLEX_20' => ($cpa_selected == '1') ? 'N':'Y',
                        //   'FLEX_12' => ((($car_age < 5) && $nilDepreciationCover == '1') ? '20' : ''),
                          'FLEX_12' =>  $nilDepreciationCover == '1' ? '20' : '',
                          'FLEX_21' => 'N',//($car_age > 10) ? 'N' : $engine_protection, //engine protector
                          'FLEX_22' => ($is_new ? $flex_22 : ''),
                          'FLEX_24' => (($premium_type == 'own_damage') ? ($proposal->previous_insurance_company . '~' . $proposal->previous_policy_number . '~' . $PreviousPolicyFromDt . '~' . $PreviousPolicyToDt) : '' ),

                          'NON_ELEC_ACCESS_DESC'    => $NonElectricalaccess,
                          'NON_ELEC_ACCESS_VALUE'   => $NonElectricalaccessSI,
                          'ELEC_ACCESS_DESC'        => $Electricalaccess,
                          'ELEC_ACCESS_VALUE'       => $ElectricalaccessSI,
                      ],
                    '_attributes' => [
                        "xmlns" => "http://MotorService/"
                    ],
                    ],
                ],
        ];

        if($is_geo_ext)
        {
            if (in_array('Sri Lanka',$countries))
            {
                $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD5';
            }
            elseif (in_array('Bangladesh',$countries))
            {
                $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD1'; 
            }
            elseif (in_array('Bhutan',$countries))
            {
                $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD2'; 
            }
            elseif (in_array('Nepal',$countries))
            {
                $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD3'; 
            }
            elseif (in_array('Pakistan',$countries))
            {
                $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD4'; 
            }
            elseif (in_array('Maldives',$countries))
            {
                $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD6'; 
            }
        }

        if (($externalCNGKITSI != '0')) {
            $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['CNG_KIT_VALUE'] = $externalCNGKITSI;
            $proposal_array['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_04'] = '';
        }
               $additional_data = [
                'enquiryId' => $enquiryId,
                'headers' => [
                    'Content-Type' => 'text/xml; charset="utf-8"',
                ],
                'requestMethod' => 'post',
                'requestType' => 'xml',
                'section' => 'Bike',
                'method' => 'Proposal Submit',
                'product' => 'Bike',
                'transaction_type' => 'proposal',
                'productName'       => $productData->product_name,
            ];
            $root = [
                'rootElementName' => 'soap:Envelope',
                '_attributes' => [
                    "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
                ]
            ];
            
          $input_array = ArrayToXml::convert($proposal_array,$root, false,'utf-8');
          $get_response = getWsData(config('constants.motor.oriental.QUOTE_URL'), $input_array, 'oriental', $additional_data);
          $response = $get_response['response'];

          if(!$response)
          {
              return response()->json([
                  'status' => false,
                  'webservice_id' => $get_response['webservice_id'],
                  'table' => $get_response['table'],
                  'msg' => "Insurer Not Reachable",
              ]);
          }

          $response = XmlToArray::convert($response);

          $quote_res_array = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];
         
        $vehicleDetails = [
            'manufacture_name'  => $mmv_data->ven_manf_code,
            'model_name'        => $mmv_data->veh_model_desc,
            'version'           => '',
            'fuel_type'         => $mmv_data->fuel_type_code,
            'seating_capacity'  => $mmv_data->seating_capacity,
            'carrying_capacity' => $mmv_data->seating_capacity,
            'cubic_capacity'    => $mmv_data->cubic_capacity,
            'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
            'vehicle_type'      => $mmv_data->veh_ob_type ?? '',
        ];

        if ($quote_res_array['ERROR_CODE'] == '0 0') {

            $proposal_addtional_details = json_decode($proposal->additional_details, true);

            $proposal_addtional_details['oriental']['proposal_policy_sys_id']   = $quote_res_array['POLICY_SYS_ID'];
            $proposal_addtional_details['oriental']['proposal_no']   = $quote_res_array['PROPOSAL_NO_OUT'];

            $proposal->additional_details       = $proposal_addtional_details;
            $proposal->save();

            $proposal->proposal_no = $quote_res_array['PROPOSAL_NO_OUT'];
            $proposal->pol_sys_id = $quote_res_array['POLICY_SYS_ID'];
            $proposal->ic_vehicle_details = $vehicleDetails;
            $proposal->save();
            $res_array = [];
                 $Depreciation_Reimbursement = 0;

                 $res_array = [
                     'OD_PREMIUM'   => '0',
                     'TP_PREMIUM'   => '0',
                     'ANNUAL_PREMIUM' => $quote_res_array['ANNUAL_PREMIUM'],
                     'NCB_AMOUNT' => $quote_res_array['NCB_AMOUNT'],
                     'SERVICE_TAX' => $quote_res_array['SERVICE_TAX'],
                     'ZERO_DEP' => '0',
                     'ZERO_DEP_DISC' => '0',
                     'PA_OWNER' => '0',
                     'ELEC' => '0',
                     'LL_PAID_DRIVER' => '0',
                     'CNG' => '0',
                     'CNG_TP' => '0',
                     'VOL_ACC_DIS' => '0',
                     'RTI' => '0',
                     'ENG_PRCT' => '0',
                     'DISC' => '0',
                     'FIB_TANK' => '0',
                     'NCB_DIS' => '0',
                     'ANTI_THEFT' => '0',
                     'AUTOMOBILE_ASSO' => '0',
                     'UNNAMED_PASSENGER' => '0',
                     'KEYREPLACEMENT' => '0',
                     'CONSUMABLES' => '0',
                     'LOSSPER_BELONG' => '0',
                     'NO_CLAIM_BONUS' => '0',
                     'OTHER_FUEL1' => '0',
                     'OTHER_FUEL2' => '0',
                     'IDV' => '0',
                     'PA_PAID_DRIVER' => '0',
                     'TPPD' => '0'
                 ];
                 $GeogExtension_od = 0;
                 $GeogExtension_tp = 0;
                 $flex_01 =  (!empty($quote_res_array['FLEX_02_OUT'])) ?  ($quote_res_array['FLEX_01_OUT'] . $quote_res_array['FLEX_02_OUT']) : $quote_res_array['FLEX_01_OUT'];
                 $flex = explode(",", $flex_01);


                 foreach ($flex as $val) {
                     $cover = explode("~", $val);
                     if ($cover[0] == "MOT-CVR-149") {

                         $res_array['ZERO_DEP'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-010") {

                         $res_array['PA_OWNER'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-002") {

                         $res_array['ELEC'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-015") {

                         $res_array['LL_PAID_DRIVER'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-003") {

                         $res_array['CNG'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-008") {

                         $res_array['CNG_TP'] = $cover[1];
                     } else if ($cover[0] == "MOT-DIS-004") {

                         $res_array['VOL_ACC_DIS'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-070") {

                         $res_array['RTI'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-EPC") {

                         $res_array['ENG_PRCT'] = $cover[1];
                     } else if ($cover[0] == "MOT-DLR-IMT") {

                         $res_array['DISC'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-005") {

                         $res_array['FIB_TANK'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-001") {

                         $res_array['OD_PREMIUM'] = $cover[1];
                         $res_array['IDV'] = $cover[2];
                     } else if ($cover[0] == "MOT-CVR-007") {

                         $res_array['TP_PREMIUM'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-012") {

                         $res_array['UNNAMED_PASSENGER'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-154") {

                         $res_array['KEYREPLACEMENT'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-155") {

                         $res_array['CONSUMABLES'] = $cover[1];
                     } else if ($cover[0] == "MOT-DIS-013") {

                         $res_array['NCB_DIS'] = $cover[1];
                     } else if ($cover[0] == "MOT-DLR-IMT") {

                         $res_array['DISC'] = $cover[1];
                     } else if ($cover[0] == "MOT-DIS-310") {

                         $res_array['NCB_DIS'] = $cover[1];
                     } else if ($cover[0] == "MOT-DIS-002") {

                         $res_array['ANTI_THEFT'] = $cover[1];
                     } else if ($cover[0] == "MOT-DIS-005") {

                         $res_array['AUTOMOBILE_ASSO'] = $cover[1];
                     } else if ($cover[0] == "MOT-DIS-ACN") {

                         $res_array['ZERO_DEP_DISC'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-152") {

                         $res_array['LOSSPER_BELONG'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-053") {

                         $res_array['OTHER_FUEL1'] = $cover[1];
                     } else if ($cover[0] == "MOT-CVR-058") {

                         $res_array['OTHER_FUEL2'] = $cover[1];
                     }
                     else if ($cover[0] == "MOT-CVR-013") {

                         $res_array['PA_PAID_DRIVER'] = $cover[1];
                     }
                     if ($cover[0] == "MOT-CVR-019")
                     {
                         $res_array['TPPD'] = $cover[1];
                     }
                     if ($cover[0] == 'MOT-CVR-006') {
                        $GeogExtension_od = $cover[1];
                    }
                    if ($cover[0] == 'MOT-CVR-051')
                    {
                        $GeogExtension_tp = $cover[1];
                    }
                     $new_array[$cover[0]] = $cover;
                 }
               $Depreciation_Reimbursement =   ($res_array['ZERO_DEP'] - $res_array['ZERO_DEP_DISC']);

            $od_discount = ($res_array['ANTI_THEFT']) + ($res_array['VOL_ACC_DIS']) + ($res_array['DISC']) + ($res_array['NCB_DIS']);
            $final_total_discount = $od_discount + ($res_array['TPPD']);
            $addon_premium =  $Depreciation_Reimbursement + $res_array['KEYREPLACEMENT'] + $res_array['LOSSPER_BELONG'] + $res_array['ENG_PRCT'] + $res_array['RTI'];

            $od_premium = ($master_policy->premium_type_id != '2') ? (($res_array['OD_PREMIUM']) + ($res_array['ELEC']) + ($res_array['CNG_TP'] + $GeogExtension_od)) :'0';

            $tp_premium = ($master_policy->premium_type_id != '3') ? (($res_array['TP_PREMIUM']) + ($res_array['LL_PAID_DRIVER']) + ($res_array['UNNAMED_PASSENGER']) + ($res_array['CNG'] + ($res_array['PA_OWNER']) +  $GeogExtension_tp)):'0';

            // UserProposal::where('user_product_journey_id', $enquiryId)
            //     ->where('user_proposal_id', $proposal->user_proposal_id)
            //     ->update([
            //         'policy_start_date'     => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
            //         'policy_end_date'       => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
            //         'proposal_no'           => $proposal->proposal_no,
            //         'unique_proposal_id'    => $proposal->proposal_no,
            //         'od_premium'            => $od_premium - ($od_discount),
            //         'tp_premium'            => $tp_premium,
            //         'addon_premium'         => $addon_premium,
            //         'cpa_premium'           => ($res_array['PA_OWNER']),
            //         'final_premium'         => ($res_array['ANNUAL_PREMIUM'] / (1 + (18.0 / 100))),
            //         'total_premium'         => ($res_array['ANNUAL_PREMIUM'] / (1 + (18.0 / 100))),
            //         'service_tax_amount'    => ($res_array['SERVICE_TAX']),
            //         'final_payable_amount'  => ($res_array['ANNUAL_PREMIUM']),
            //         'customer_id'           =>  '',
            //         'ic_vehicle_details'    => json_encode($vehicleDetails),
            //         'ncb_discount'          => ($res_array['NCB_DIS']),
            //         'total_discount'        => ($final_total_discount),
            //         'cpa_ins_comp'          => $cPAInsComp,
            //         'cpa_policy_fm_dt'      => str_replace('/', '-', $cPAPolicyFmDt),
            //         'cpa_policy_no'         => $cPAPolicyNo,
            //         'cpa_policy_to_dt'      => str_replace('/', '-', $cPAPolicyToDt),
            //         'cpa_sum_insured'       => $cPASumInsured,
            //         'electrical_accessories'    => $ElectricalaccessSI,
            //         'non_electrical_accessories'=> $NonElectricalaccessSI
            //     ]);

            
            $updateData = [
                'policy_start_date'     => date('d-m-Y', strtotime($policy_start_date)),
                'policy_end_date'       => date('d-m-Y', strtotime($policy_end_date)),
                'proposal_no'           => $proposal->proposal_no,
                'unique_proposal_id'    => $proposal->proposal_no,
                'od_premium'            => $od_premium - ($od_discount),
                'tp_premium'            => $tp_premium,
                'addon_premium'         => $addon_premium,
                'cpa_premium'           => ($res_array['PA_OWNER']),
                'final_premium'         => ($res_array['ANNUAL_PREMIUM'] / (1 + (18.0 / 100))),
                'total_premium'         => ($res_array['ANNUAL_PREMIUM'] / (1 + (18.0 / 100))),
                'service_tax_amount'    => ($res_array['SERVICE_TAX']),
                'final_payable_amount'  => ($res_array['ANNUAL_PREMIUM']),
                'customer_id'           =>  '',
                'ic_vehicle_details'    => json_encode($vehicleDetails),
                'ncb_discount'          => ($res_array['NCB_DIS']),
                'total_discount'        => ($final_total_discount),
                'cpa_ins_comp'          => $cPAInsComp,
                'cpa_policy_fm_dt'      => str_replace('/', '-', $cPAPolicyFmDt),
                'cpa_policy_no'         => $cPAPolicyNo,
                'cpa_policy_to_dt'      => str_replace('/', '-', $cPAPolicyToDt),
                'cpa_sum_insured'       => $cPASumInsured,
                'electrical_accessories'    => $ElectricalaccessSI,
                'non_electrical_accessories'=> $NonElectricalaccessSI,
                'tp_start_date' =>$tp_start_date,
                'tp_end_date' => $tp_end_date,
              
        ];
        if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
            unset($updateData['tp_start_date']);
            unset($updateData['tp_end_date']);
        }
        $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($updateData);
                
            $data['user_product_journey_id'] = $enquiryId;
            $data['ic_id'] = $master_policy->insurance_company_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);

            OrientalPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

            $kyc_token = $clientId = $clientSecret = null;
            $enable_hyperverse = config('ENABLE_HYPERVERGE_FOR_ORIENTAL') == "Y";
            if ($proposal->is_ckyc_verified != 'Y' && $enable_hyperverse) {
                $request_data = [
                    'companyAlias' => 'oriental',
                    'mode' =>  'ckyc',
                    'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    'lastProposalModifiedTime' =>  now()
                ];

                $ckycController = new CkycController;
                $response = $ckycController->ckycVerifications(new  Request($request_data));
                $response = $response->getOriginalContent();
                if(empty($response['data']['meta_data']['accessToken'])){
                    return[
                        'status' => false,
                        'msg' => $response['message'] ?? 'Token Generation Failed...!',
                    ];
                }
                $kyc_token = $response['data']['meta_data']['accessToken'];
                $clientId = $response['data']['meta_data']['clientId'] ?? null;
                $clientSecret = $response['data']['meta_data']['clientSecret'] ?? null;

                $additional_details_data = json_decode($proposal->additional_details_data, true);

                $additional_details_data['access_token'] = $kyc_token;
                $additional_details_data['clientId'] = $clientId;
                $additional_details_data['clientSecret'] = $clientSecret;

                UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'additional_details_data'     => json_encode($additional_details_data)
                    ]);
            }

            return response()->json([
                'status' => true,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Proposal Submitted Successfull ...!',
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $data['user_product_journey_id'],
                    'proposalNo' => $proposal->proposal_no,
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => '',
                    'inspection_number' => '',
                    'verification_status' => $proposal->is_ckyc_verified == 'Y' ? true : false
                    // 'token'=> $kyc_token ?? null,
                    // 'clientId' =>  $clientId,
                    // 'clientSecret' =>  $clientSecret
                ],
                // 'token'=> $kyc_token ?? null
            ]);
        } else {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $quote_res_array['ERROR_CODE'],
            ]);
        }
    }

}
