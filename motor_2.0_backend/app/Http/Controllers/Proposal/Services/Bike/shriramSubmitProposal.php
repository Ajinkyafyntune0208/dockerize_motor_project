<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Arr;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\ckycUploadDocuments;
use App\Models\ShriramPinCityState;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\Proposal\Services\shriramSubmitProposal as ServicesShriramSubmitProposal;
use App\Http\Controllers\SyncPremiumDetail\Bike\ShriramPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
// include_once app_path() . '/Http/Controllers/Proposal/Services/Bike/V1/ShriramSubmitProposal.php';

class shriramSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        if (config('constants.motor.shriram.SHRIRAM_BIKE_JSON_REQUEST_TYPE') == 'JSON') {
            return  self::submitJSON($proposal, $request);
        }else{
            return  self::submitXML($proposal, $request);
        }
    }

    public static function submitXML($proposal, $request)
    {
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
        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d/m/Y');
        } elseif ($requestData->business_type == 'breakin') {
            $policy_start_date = today()->addDay(2)->format('d/m/Y');
        }else{
            $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));  
        }
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
        $mmv = get_mmv_details($productData,$requestData->version_id,'shriram');
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
        /*if (!$ic_version_mapping) {
            return [
                'status' => false,
                'msg' => 'Vehicle does not exist with insurance company'
            ];
        }*/

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $quote_data['vehicle_register_date'];
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($proposal->prev_policy_expiry_date == 'New' ? date('Y-m-d') : $proposal->prev_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $bike_age = ceil($age / 12);
        //$pkg_selected = ($bike_age > 5) ? "ADDON_01" : "ADDON_03";

        $pkg_selected = $productData->zero_dep == '0' ? "ADDON_03" : "ADDON_01";
        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $RSACover = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $additional_details = json_decode($proposal->additional_details);
        // new business
        $PreviousNilDepreciation = '0'; // addon
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
        $previous_not_sure = strtolower($requestData->previous_policy_expiry_date) == 'new';
        if ($requestData->business_type == 'newbusiness') {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $policy_start_date = today()->format('d/m/Y');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d/m/Y');
            $proposalType = "FRESH";
            $previous_ncb = "";
            $soapAction = "GenerateLTTwoWheelerProposal";
            $url = config('constants.motor.shriram.RENEW_PROPOSAL_URL');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d/m/Y');
            $db_policy_enddate = today()->addYear(3)->subDay(1)->format('d/m/Y');
        } else {
            // If previous policy is not sure then consider it as a break in case with more than 90days case.
            $PreviousPolicyFromDt = $previous_not_sure ? '' : Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
            $PreviousPolicyToDt = $previous_not_sure ? '' : Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
            $proposalType = "RENEWAL OF OTHERS";
            $previous_ncb = $requestData->previous_ncb;
            $soapAction = "GenerateProposal";
            $url = config('constants.motor.shriram.NEW_PROPOSAL_URL');
            $prev_policy_details = isset($additional_details->prepolicy) ? $additional_details->prepolicy :'';
        }
        
         ($pkg_selected == 'ADDON_01' ? 'N' : 'Y' );

        if ($productData->zero_dep == '0') {
            $DepDeductWaiverYN = "N";
            $nilDepreciationCover = 1;
            $PreviousNilDepreciation = 1; // addon
        } else {
            $DepDeductWaiverYN = "Y";
        }

        $LLtoPaidDriverYN = '0';
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
        }
        $InvReturnYN = 'Y';
        $consumable = $engine_protection = '0';
        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value)) {
                $nilDepreciationCover = 1;
                $DepDeductWaiverYN = "N";
                $PreviousNilDepreciation = 1; // addon
            }

            if (in_array('Road Side Assistance', $value)) {
                $RSACover = "1";
            }
            if (in_array('Return To Invoice', $value)) {
                $InvReturnYN = 'N';
            }

            if (in_array('Engine Protector', $value) && $productData->zero_dep == '0') {
                $engine_protection = '1';
            }

            if (in_array('Consumable', $value) && $productData->zero_dep == '0') {
                $consumable = '1';
            }

        }

        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = 1;
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = 1;
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = 1;
                $externalCNGKITSI = $value['sumInsured'];
            }
        }

        $voluntary_insurer_discounts = 'TWVE1'; // Discount of 0
        $voluntary_discounts = [
            '0'     => 'TWVE1', // Discount of 0
            '500'  => 'TWVE2', // Discount of 750
            '750'  => 'TWVE3', // Discount of 1500
            '1000'  => 'TWVE4', // Discount of 2000
            '1500' => 'TWVE5', 
            '3000' => 'TWVE6',// Discount of 2500
        ];
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            // As suggested by Paras sir, Disabling Anti Theft - 20-08-2021
            /*if (in_array('anti-theft device', $value)) {
                $antitheft = '1';
            }*/
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)) {
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }
        }

        // salutaion  
        $proposal->gender = (strtolower($proposal->gender) == "male" || $proposal->gender == "M") ? "M" :"F";
      
        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $insured_prefix = '1'; // Mr
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $insured_prefix = '2'; // Mrs
                } else {
                    $insured_prefix = '4'; // Miss
                }
            }
        }
        else{
            $insured_prefix = '3'; // M/S
        }
        // salutaion
        // CPA
        $PAOwnerDriverExclusion = "1";
        $excludeCPA = false;
        if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
            if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
                $PAOwnerDriverExclusion = "0";
                $PAOwnerDriverExReason = "";
            }
            else {
                if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
                    $PAOwnerDriverExReason = "PA_TYPE2";
                    $excludeCPA = true;
                } else {
                    $PAOwnerDriverExReason = "PA_TYPE4";
                }
            }
        } elseif ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
            $PAOwnerDriverExReason = "PA_TYPE1";
            $excludeCPA = true;
        }
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if ( !($PAOwnerDriverExclusion == '0' || $excludeCPA) ) {
            $cPAInsComp = isset($prev_policy_details->cPAInsComp) ? $prev_policy_details->cPAInsComp:'';
            $cPAPolicyNo = isset($prev_policy_details->cPAPolicyNo) ? $prev_policy_details->cPAPolicyNo:'';
            $cPASumInsured = isset($prev_policy_details->cPASumInsured) ? $prev_policy_details->cPASumInsured:'';
            $cPAPolicyFmDt = isset($prev_policy_details->cPAPolicyFmDt) ?Carbon::parse($prev_policy_details->cPAPolicyFmDt)->format('d/m/Y'):'';
            $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d/m/Y'):'';
        }
        // CPA
        // Policy Type
        switch ($master_policy->premium_type_id) 
               {
                   case '1':
                       $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-021" : "MOT-PRD-002";
                       $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-014" :'MOT-PLT-001';
                       $PreviousPolicyType = ($requestData->business_type != 'newbusiness') ? 'MOT-PLT-001' :'';
                       $URL = ($requestData->business_type == 'newbusiness') ? config('constants.motor.shriram.NBQUOTE_URL') : config('constants.motor.shriram.QUOTE_URL');
                       $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                       break;
                    case '4':
                        $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-021" : "MOT-PRD-002";
                        $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-014" :'MOT-PLT-001';
                        $PreviousPolicyType = ($requestData->business_type != 'newbusiness') ? 'MOT-PLT-001' :'';
                        $URL = ($requestData->business_type == 'newbusiness') ? config('constants.motor.shriram.NBQUOTE_URL') : config('constants.motor.shriram.QUOTE_URL');
                        $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                        break;
                   case '2':
                       $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-017" : "MOT-PRD-002";
                       $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-014" :'MOT-PLT-002';
                       $PreviousPolicyType = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness' ? '' :'MOT-PLT-001') : 'MOT-PLT-002';#($requestData->business_type != 'newbusiness') ? 'MOT-PLT-002' :'';
                       $URL = ($requestData->business_type == 'newbusiness') ? config('constants.motor.shriram.NBQUOTE_URL') :config('constants.motor.shriram.QUOTE_URL');
                       $quote_log->ex_showroom_price_idv = '';
                       break;
                    case '7':
                        $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-021" : "MOT-PRD-002";
                        $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-014" : 'MOT-PLT-002';
                        $PreviousPolicyType = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness' ? '' :'MOT-PLT-001') : 'MOT-PLT-002';#($requestData->business_type != 'newbusiness') ? 'MOT-PLT-002' :'';
                        $URL = ($requestData->business_type == 'newbusiness') ? config('constants.motor.shriram.NBQUOTE_URL') :config('constants.motor.shriram.QUOTE_URL');
                        $quote_log->ex_showroom_price_idv = '';
                        break;
                    case '3':
                       $ProdCode = "MOT-PRD-021";
                       $policy_type = 'MOT-PLT-013';
                       $PreviousPolicyType = 'MOT-PLT-013';
                       $soapAction = "GenerateLTTwoWheelerProposal";
                       $URL = config('constants.motor.shriram.ODQUOTE_URL');
                       $tp_start_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
                       $tp_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
                       $prev_tp_policy_no = '';
                       $prev_tp_comp_name = '';
                       $prev_tp_address = '';
                       $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                       #$PAOwnerDriverExReason = "PA_TYPE1";
                       #$insured_prefix = '3';
                       break;
                    case '6':
                        $ProdCode = "MOT-PRD-021";
                        $policy_type = 'MOT-PLT-013';
                        $PreviousPolicyType = 'MOT-PLT-013';
                        $soapAction = "GenerateLTTwoWheelerProposal";
                        $URL = config('constants.motor.shriram.ODQUOTE_URL');
                        $tp_start_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
                        $tp_end_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
                        $prev_tp_policy_no = '';
                        $prev_tp_comp_name = '';
                        $prev_tp_address = '';
                        $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                        break;
                   
               }

        // Policy Type

        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number[0] = '';
        }


        //Hypothecation
        $HypothecationType = $HypothecationBankName = $HypothecationAddress1 = $HypothecationAddress2 = $HypothecationAddress3 = $HypothecationAgreementNo = $HypothecationCountry = $HypothecationState = $HypothecationCity = $HypothecationPinCode = '';
        $vehicleDetails = $additional_details->vehicle;
        
        if ($vehicleDetails->isVehicleFinance == true) {
            $HypothecationType = $vehicleDetails->financerAgreementType;
            $HypothecationBankName = $vehicleDetails->nameOfFinancer;
            $HypothecationAddress1 = $vehicleDetails->hypothecationCity;
            $HypothecationAddress2 = '';
            $HypothecationAddress3 = '';
            $HypothecationAgreementNo = '';
            $HypothecationCountry = '';
            $HypothecationState = '';
            $HypothecationCity = $vehicleDetails->hypothecationCity;
            $HypothecationPinCode = '';
        }
        //Hypothecation

        $rto_code = $quote_log->premium_json['rtoNo'];

        // state_code
        $state_code = ShriramPinCityState::where('pin_code', $proposal->pincode)->first()->state;
        // state_code
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posp_name = '';
        $posp_pan_number = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('user_proposal_id',$proposal['user_proposal_id'])
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
         {
            if($pos_data) {
                $posp_name = $pos_data->agent_name;
                $posp_pan_number = $pos_data->pan_no;
            }
        }elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_SHRIRAM') == 'Y'){
            $posp_name = 'Ravindra Singh';
            $posp_pan_number = 'DNPPS5548E';
        }
        $input_array = [
            "soap:Header" => [
                "AuthHeader" => [
                    "Username" => config('constants.motor.shriram.AUTH_NAME_SHRIRAM_MOTOR'),
                    "Password" => config('constants.motor.shriram.AUTH_PASS_SHRIRAM_MOTOR'),
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                ]
            ],
            "soap:Body" => [
                $soapAction => [
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                    'objPolicyEntryETT' => [
                        'ReferenceNo'       => '',
                        'ProdCode'          => $ProdCode,
                        'PolicyFromDt'      => $policy_start_date,
                        'PolicyToDt'        => $policy_end_date,
                        'PolicyIssueDt'     => today()->format('d/m/Y'),
                        'InsuredPrefix'     => ($requestData->vehicle_owner_type == 'I') ?$insured_prefix : '3',
                        'InsuredName'       => $proposal->first_name . ' ' . $proposal->last_name,
                        "Gender"            => $proposal->gender,
                        "Address1"          => $proposal->address_line1,
                        "Address2"          => $proposal->address_line2,
                        "Address3"          => $proposal->address_line3,
                        "State"             => $state_code,
                        "City"              => $proposal->city,
                        "PinCode"           => $proposal->pincode,
                        "PanNo"             => $proposal->pan_number,
                        "GSTNo"             => $proposal->gst_number,
                        "TelephoneNo"       => "",
                        'FaxNo'             => '',
                        'EMailID'           => $proposal->email,
                        'PolicyType'        => $policy_type,
                        'ProposalType'      => $proposalType,
                        'MobileNo'          => $proposal->mobile_number,
                        'DateOfBirth'       => Carbon::parse($proposal->dob)->format('d/m/Y'),
                        'POSAgentName'      => $posp_name,
                        'POSAgentPanNo'     => $posp_pan_number,
                        'CoverNoteNo'       => '',
                        'CoverNoteDt'       => '',
                        'VehicleCode'       => $mmv_data->veh_code,
                        'EngineNo'          => $proposal->engine_number,
                        'FirstRegDt'        => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                        'VehicleType'       => ($requestData->business_type == 'newbusiness') ? 'W' : 'U',
                        'ChassisNo'         => $proposal->chassis_number,
                        "RegNo1"            => $requestData->business_type == 'newbusiness' ? "" : explode('-', $rto_code)[0],
                        "RegNo2"            => $requestData->business_type == 'newbusiness' ? "" : explode('-', $rto_code)[1],
                        "RegNo3"            => $requestData->business_type == 'newbusiness' ? "" : $vehicale_registration_number[2],
                        "RegNo4"            => $requestData->business_type == 'newbusiness' ? "" : $vehicale_registration_number[3],
                        'RTOCode'           => $rto_code,
                        'IDV_of_Vehicle'    => $quote_log->idv, //$idv,
                        'Colour'            => $proposal->vehicle_color,
                        'NoEmpCoverLL'          => '',
                        'VehiclePurposeYN'      => '',
                        'DriverAgeYN'           => '',
                        'LimitOwnPremiseYN'     => '0',
                        'CNGKitYN'              => $externalCNGKIT,
                        'CNGKitSI'              => $externalCNGKITSI,
                        'LimitedTPPDYN'         => $LimitedTPPDYN,
                        'InBuiltCNGKitYN'       => $requestData->fuel_type == 'CNG' ? '1' : '0', // maSTER and  fuel type
                        'VoluntaryExcess'       => $voluntary_insurer_discounts,//($requestData->business_type == 'newbusiness') ? 'PCVE2' : 'PCVE1',
                        'Bangladesh'                => '',
                        'Bhutan'                    => '',
                        'SriLanka'                  => '',
                        'Pakistan'                  => '',
                        'Nepal'                     => '',
                        'Maldives'                  => '',
                        'DeTariff'                  => 0,
                        'PreInspectionReportYN'     => '',
                        'PreInspection'             => '',
                        'BreakIn'                   => 'NO',
                        'AddonPackage'                  => $pkg_selected,
                        'NilDepreciationCoverYN'        => $nilDepreciationCover, // addon zero deprecian
                        'PAforUnnamedPassengerYN'       => $PAforUnnamedPassenger,
                        'PAforUnnamedPassengerSI'       => $PAforUnnamedPassengerSI,
                        'ElectricalaccessYN'            => $Electricalaccess,
                        'ElectricalaccessSI'            => $ElectricalaccessSI,
                        'ElectricalaccessRemarks'       => $ElectricalaccessSI > 0 ? 'electric' : "",
                        'NonElectricalaccessYN'         => $NonElectricalaccess,
                        'NonElectricalaccessSI'         => $NonElectricalaccessSI,
                        'NonElectricalaccessRemarks'    => $NonElectricalaccessSI > 0 ? 'non electric' : "",
                        'PAPaidDriverConductorCleanerYN' => $PAPaidDriverConductorCleaner,
                        'PAPaidDriverConductorCleanerSI' => $PAPaidDriverConductorCleanerSI,
                        'PAPaidDriverCount'             => '0',
                        'PAPaidConductorCount'          => '0',
                        'PAPaidCleanerCount'            => '',
                        'NomineeNameforPAOwnerDriver'   => $proposal->nominee_name == null ? '' : $proposal->nominee_name,
                        'NomineeAgeforPAOwnerDriver'    => $proposal->nominee_age == null ? '' : $proposal->nominee_age,
                        'NomineeRelationforPAOwnerDriver' => $proposal->nominee_relationship == null ? '' : $proposal->nominee_relationship,
                        'AppointeeNameforPAOwnerDriver' => '',
                        'AppointeeRelationforPAOwnerDriver' => '',
                        'LLtoPaidDriverYN'          => $LLtoPaidDriverYN,
                        'AntiTheftYN'               => '0',
                        'PreviousPolicyNo'          => $proposal->previous_policy_number,
                        'PreviousInsurer'           => $proposal->previous_insurance_company,
                        'PreviousPolicyFromDt'      => $PreviousPolicyFromDt,
                        'PreviousPolicyToDt'        => $PreviousPolicyToDt,
                        'PreviousPolicyUWYear'      => '',
                        'PreviousPolicySI'          => '',
                        'PreviousPolicyClaimYN'     => $requestData->is_claim == 'Y' ? '1' : '0', // input
                        'PreviousPolicyNCBPerc'     => $requestData->business_type == 'newbusiness' || in_array($master_policy->premium_type_id,[2,7]) ? "" : (int) $previous_ncb,
                        'PreviousPolicyType'        => $PreviousPolicyType,
                        'PreviousNilDepreciation'   => $PreviousNilDepreciation,
                        'HypothecationType'         => $HypothecationType,
                        'HypothecationBankName'     => $HypothecationBankName,
                        'HypothecationAddress1'     => $HypothecationAddress1,
                        'HypothecationAddress2'     => $HypothecationAddress2,
                        'HypothecationAddress3'     => $HypothecationAddress3,
                        'HypothecationAgreementNo'  => $HypothecationAgreementNo,//$hypo_agreement_no,
                        'HypothecationCountry'      => $HypothecationCountry,
                        'HypothecationState'        => $HypothecationState,
                        'HypothecationCity'         => $HypothecationCity,
                        'HypothecationPinCode'      => $HypothecationPinCode,
                        'SpecifiedPersonField'      => '', 
                        'PAOwnerDriverExclusion'    => $PAOwnerDriverExclusion,
                        'PAOwnerDriverExReason'     => $PAOwnerDriverExReason,
                        'CPAInsComp'                => $cPAInsComp,
                        'CPAPolicyNo'               => $cPAPolicyNo,
                        'CPASumInsured'             => $cPASumInsured,
                        'CPAPolicyFmDt'             => $cPAPolicyFmDt,
                        'CPAPolicyToDt'             => $cPAPolicyToDt,
                        'DepDeductWaiverYN'         => $DepDeductWaiverYN,
                        'DailyExpRemYN'             => 'N',
                        'RSACover'                  => $RSACover,
                        'InvReturnYN'               => $InvReturnYN,
                        'Eng_Protector'             => $engine_protection,
                        'Consumables'               => $consumable,
                        'TpFmDt'                    => ($master_policy->premium_type_id == '3') ? date('d/m/Y', strtotime($proposal->tp_start_date)) : '',
                        'TpToDt'                    => ($master_policy->premium_type_id == '3') ? date('d/m/Y', strtotime($proposal->tp_end_date)) : '',
                        'TpPolNo'                   => ($master_policy->premium_type_id == '3') ? $proposal->tp_insurance_number:'',
                        'TpCompName'                => ($master_policy->premium_type_id == '3') ? $proposal->tp_insurance_company : '',
                        'TpAddress'                 => ($master_policy->premium_type_id == '3') ? $proposal->state.'-'.$proposal->city : '',
                        "TRANSFEROFOWNER"           => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                        "VehicleManufactureYear" => $manufacture_year,
                    ]
                ]
            ]
        ];
        if (in_array($master_policy->premium_type_id, ['3', '6'])) {
            if (isset($input_array['soap:Body']['GenerateLTTwoWheelerProposal']['objPolicyEntryETT']['PAOwnerDriverExclusion']) && isset($input_array['soap:Body']['GenerateLTTwoWheelerProposal']['objPolicyEntryETT']['PAOwnerDriverExReason'])) {
                unset($input_array['soap:Body']['GenerateLTTwoWheelerProposal']['objPolicyEntryETT']['PAOwnerDriverExclusion']);
                unset($input_array['soap:Body']['GenerateLTTwoWheelerProposal']['objPolicyEntryETT']['PAOwnerDriverExReason']);
            }
        }
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'SOAPAction' => 'http://tempuri.org/'.$soapAction,
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Bike',
            'method' => 'Proposal Submit',
            'transaction_type' => 'proposal',
            'productName' => $productData->product_name . ($productData->zero_dep == '0' ? ' zero_dep' : '')
        ];
        $root = [
            'rootElementName' => 'soap:Envelope',
            '_attributes' => [
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
            ]
        ];
        $input_array = ArrayToXml::convert($input_array, $root, false, 'utf-8');

        $get_response = getWsData($URL, $input_array, $request['companyAlias'], $additional_data);
        $response = $get_response['response'];

        $vehicleDetails = [
            'manufacture_name'  => $mmv_data->veh_model,
            'model_name'        => $mmv_data->model_desc,
            'version'           => '',
            'fuel_type'         => $mmv_data->fuel,
            'seating_capacity'  => $mmv_data->veh_seat_cap,
            'carrying_capacity' => $mmv_data->veh_seat_cap,
            'cubic_capacity'    => $mmv_data->veh_cc,
            'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
            'vehicle_type'      => $mmv_data->veh_ob_type ?? '',
        ];

        $proposal_response = XmlToArray::convert($response);
        if($proposalType == "RENEWAL" && $master_policy->premium_type_id != '3'){
            $response = $proposal_response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'];
        }elseif($proposalType == 'RENEWAL' && $master_policy->premium_type_id == '3')
        {
            $response = isset($proposal_response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult']) ? $proposal_response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'] : $proposal_response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'];
        }else{
            $response = isset($proposal_response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult']) ? $proposal_response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'] : $proposal_response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'];
        }
        if ($response['ERROR_CODE'] == 0){
            $proposal->proposal_no = $response['PROPOSAL_NO'];
            $proposal->pol_sys_id = $response['POL_SYS_ID'];
            $proposal->ic_vehicle_details = $vehicleDetails;
            $proposal->save();
            
            $coverDTList = $response['CoverDtlList']['CoverDtl'];
            $final_od_premium = $final_tp_premium = $cpa_premium = $NetPremium = $addon_premium = $ncb_discount = $total_discount = 0;
            $addons_available = [
                'Nil Depreciation', 'Nil Depreciation Cover','Nil Depreciation - 1 YEAR','ROAD SIDE ASSISTANCE - 1 YEAR', 'ROAD SIDE ASSISTANCE'
            ];
            $discounts_available = [
                'VOLUNTARY EXCESS DISCOUNT-IMT-22A', 'DETARIFF DISCOUNT ON BASIC OD', 'NO CLAIM BONUS-GR27','GR39A-TPPD COVER', 'IMT22A-VOLUNTARY EXCESS DISCOUNT', 'DETARIFF DISCOUNT ON BASIC OD - 1 YEAR'
            ];
            foreach($coverDTList as $key => $value){
                if ( in_array($value['CoverDesc'], array('OD TOTAL', 'OD TOTAL - 1 YEAR')) ) {
                    $final_od_premium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('TP TOTAL', 'TP TOTAL - 1 YEAR')) ) {
                    $final_tp_premium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER', 'GR36A-PA FOR OWNER DRIVER - 1 YEAR'])) {
                    $cpa_premium = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'TOTAL PREMIUM') {
                    $NetPremium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], $addons_available) ) {
                    $addon_premium += $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], $discounts_available) ) {
                    $total_discount += $value['Premium'];
                }
                if ( $value['CoverDesc'] == 'NO CLAIM BONUS-GR27' ) {
                    $ncb_discount = $value['Premium'];
                }
            }

            UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date'     => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                    'policy_end_date'       => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                    'proposal_no'           => $proposal->proposal_no,
                    'unique_proposal_id'    => $proposal->proposal_no,
                    'od_premium'            => $final_od_premium - $addon_premium,
                    'tp_premium'            => $final_tp_premium,
                    'addon_premium'         => $addon_premium,
                    'cpa_premium'           => $cpa_premium,
                    'final_premium'         => $NetPremium,
                    'total_premium'         => $NetPremium,
                    'service_tax_amount'    => $final_payable_amount - $NetPremium,
                    'final_payable_amount'  => $final_payable_amount,
                    'product_code'          => $mmv_data->vap_prod_code,
                    'ic_vehicle_details'    => json_encode($vehicleDetails),
                    'ncb_discount'          => $ncb_discount,
                    'total_discount'        => $total_discount,
                    'cpa_ins_comp'          => $cPAInsComp,
                    'cpa_policy_fm_dt'      => str_replace('/', '-', $cPAPolicyFmDt),
                    'cpa_policy_no'         => $cPAPolicyNo,
                    'cpa_policy_to_dt'      => str_replace('/', '-', $cPAPolicyToDt),
                    'cpa_sum_insured'       => $cPASumInsured,
                    'electrical_accessories'    => $ElectricalaccessSI,
                    'non_electrical_accessories'=> $NonElectricalaccessSI
                ]);
                
            // CKYC verification
            $final_response = [
                'status' => false,
                'msg' => $response['ERROR_DESC'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $enquiryId,
                    'proposalNo' => $proposal->proposal_no,
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => '',
                    'inspection_number' => '',
                    'kyc_url' => '',
                    'is_kyc_url_present' => false,
                    'kyc_message' => '',
                    'kyc_status' => false,
                ]
            ];

            ShriramPremiumDetailController::saveXmlPremiumDetails($get_response['webservice_id']);

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $policy_approval = self::policyApprovalXml($proposal, $request, $response, $URL);

                if ( ! $policy_approval) {
                    $ckyc_documents = ckycUploadDocuments::where('user_product_journey_id', $enquiryId)->first();

                    if (empty($ckyc_documents)) {
                        return response()->json([
                            'status' => false,
                            'msg' => 'No documents available'
                        ]);
                    }

                    $ckyc_request = [
                        'companyAlias' => 'shriram',
                        'enquiryId' => customEncrypt($enquiryId),
                        'mode' => $proposal->ckyc_type == 'ckyc_number' ? 'ckyc_number' : ''
                    ];

                    $document_upload_request = [
                        'companyAlias' => 'shriram',
                        'enquiryId' => customEncrypt($enquiryId),
                        'mode' => 'documents'
                    ];

                    $ckyc_type = '';
                    $ckyc_type_value = '';

                    $ckyc_doc_data = json_decode($ckyc_documents->cky_doc_data, true);

                    if ($ckyc_request['mode'] != 'ckyc_number') {
                        switch ($ckyc_doc_data['proof_of_identity']['poi_identity']) {
                            case 'panNumber':
                                $ckyc_request['mode'] = 'pan_number';
                                $ckyc_type = 'pan_card';
                                $ckyc_type_value = $ckyc_doc_data['proof_of_identity']['poi_panNumber'];
                                break;

                            case 'aadharNumber':
                                $ckyc_request['mode'] = 'aadhar';
                                $ckyc_type = 'aadhar_card';
                                $ckyc_type_value = $ckyc_doc_data['proof_of_identity']['poi_aadharNumber'];
                                break;

                            case 'drivingLicense':
                                $ckyc_request['mode'] = 'driving_licence';
                                $ckyc_type = 'driving_license';
                                $ckyc_type_value = $ckyc_doc_data['proof_of_identity']['poi_drivingLicense'];
                                break;

                            case 'voterId':
                                $ckyc_request['mode'] = 'voter_id';
                                $ckyc_type = 'voter_id';
                                $ckyc_type_value = $ckyc_doc_data['proof_of_identity']['poi_voterId'];
                                break;

                            case 'passportNumber':
                                $ckyc_request['mode'] = 'passport';
                                $ckyc_type = 'passport';
                                $ckyc_type_value = $ckyc_doc_data['proof_of_identity']['poi_passportNumber'];
                                break;

                            case 'nationalPopulationRegisterLetter':
                                $ckyc_request['mode'] = 'national_population_register_letter';
                                $ckyc_type = 'national_population_register_letter';
                                $ckyc_type_value = $ckyc_doc_data['proof_of_identity']['poi_nationalPopulationRegisterLetter'];
                                break;

                            default:
                                return response()->json([
                                    'status' => false,
                                    'msg' => 'Unkown CKYC verification type'
                                ]);
                                break;
                        }

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->update([
                                'ckyc_type' => $ckyc_type,
                                'ckyc_type_value' => $ckyc_type_value
                            ]);
                    }

                    $ckyc_controller = new CkycController;

                    $ckyc_verification = $ckyc_controller->ckycVerifications(new Request($ckyc_request));

                    if ($ckyc_verification) {
                        $ckyc_verification = $ckyc_verification->getOriginalContent();

                        if (isset($ckyc_verification['status']) && $ckyc_verification['status']) {
                            $data['user_product_journey_id'] = $enquiryId;
                            $data['ic_id'] = $master_policy->insurance_company_id;
                            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                            $data['proposal_id'] = $proposal->user_proposal_id;
                            updateJourneyStage($data);

                            $final_response['status'] = true;
                            $final_response['data']['kyc_status'] = true;
                        } else {
                            if ($ckyc_request['mode'] == 'ckyc_number') {
                                $final_response['data']['kyc_message'] = $final_response['msg'] = 'CKYC verification failed';
                            } else {
                                $ckyc_document_upload = $ckyc_controller->ckycUploadDocuments(new Request($document_upload_request));

                                if ($ckyc_document_upload) {
                                    $ckyc_document_upload_response = $ckyc_document_upload->getOriginalContent();
    
                                    if (isset($ckyc_document_upload_response['status']) && $ckyc_document_upload_response['status']) {
                                        $data['user_product_journey_id'] = $enquiryId;
                                        $data['ic_id'] = $master_policy->insurance_company_id;
                                        $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                                        $data['proposal_id'] = $proposal->user_proposal_id;
                                        updateJourneyStage($data);
    
                                        $final_response['status'] = true;
                                        $final_response['data']['kyc_status'] = true;
                                    } else {
                                        $final_response['data']['kyc_message'] = $final_response['msg'] = 'CKYC verification failed';
                                    }
                                } else {
                                    $final_response['data']['kyc_message'] = $final_response['msg'] = 'An error occurred while verifying CKYC';
                                }
                            }
                        }
                    } else {
                        $final_response['data']['kyc_message'] = $final_response['msg'] = 'An error occurred while verifying CKYC';
                    }
                } else {
                    $final_response['status'] = true;
                    $final_response['data']['kyc_status'] = true;

                    UserProposal::where('user_product_journey_id', $enquiryId)
                        ->update([
                            'is_ckyc_verified' => 'Y'
                        ]);
                }
            } else {
                $final_response['status'] = true;
            }

            return response()->json($final_response);
        } else {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['ERROR_DESC'],
            ]);
        }
    }
    
    public static function renewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'shriram');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        
        $vehicleDetails = [
            'manufacture_name'      => $mmv->manf,
            'model_name'            => $mmv->model_desc,
            'version'               => '',
            'fuel_type'             => $mmv->fuel,
            'seating_capacity'      => $mmv->veh_seat_cap,
            'carrying_capacity'     => '',
            'cubic_capacity'        => $mmv->veh_cc,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => ''
        ];
        
        $inputArray = [
                'EmailId'       =>   $proposal['email'],
                "MobileNo"      =>   $proposal['mobile_number'],
                "PolicyNumber"  =>   trim($proposal['previous_policy_number']),
                "VehicleRegno"  =>   trim($proposal['vehicale_registration_number'])
        ];
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'BIKE',
            'method' => 'Proposal Submit',
            'transaction_type' => 'proposal',
        ];
        $url = 'https://novauat.shriramgi.com/UATWebAggrNAPI/PolicyGeneration.svc/RestService/GetRenewalDetailsResult';
        //$response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_PROPOSAL_SUBMIT_URL'), $inputArray, $request['companyAlias'], $additional_data);
        $get_response = getWsData($url, $inputArray, 'shriram', $additional_data);
        $response = $get_response['response'];
        $response = json_decode($response, true);
        
        if(empty($response)){
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Insurer Not Reachable.'
            ]);
        }
        if($response['ERROR_CODE'] == 0 && $response['ERROR_DESC'] == 'Successful Completion')
        {            
            $reponse_data = $response['RenewalDtlList'][0];
            $premium_lists = $response['RenewalDtlList'][0]['GetRenewCoverDtl'];
            $tppd_discount = 0;
            foreach ($premium_lists as $key => $premium_list) {
                if($premium_list['CoverDesc'] == 'Basic OD Premium')
                {
                  $basic_od = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'De-Tariff Discount')
                {
                  $other_discount = abs($premium_list['Premium']);
                }
                else if(trim($premium_list['CoverDesc']) == 'NCB Discount')
                {
                  $ncb_discount = abs($premium_list['Premium']);
                }
                else if($premium_list['CoverDesc'] == 'OD Total')
                {
                  $OD_Total = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Basic TP Premium')
                {
                  $Basic_TP_Premium = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER')
                {
                  $pa_owner = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Legal Liability To Employees')
                {
                  $Legal_Liability_To_Employees = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'TP Total')
                {
                  $final_tp_premium = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Total Premium')
                {
                  $final_net_premium = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Total Amount')
                {
                  $final_payable_amount = $premium_list['Premium'];
                }
            }
            
            $policy_start_date = Carbon::parse($reponse_data['PolFromDate'])->format('d-m-Y');
            $policy_end_date = Carbon::parse($reponse_data['PolEndDate'])->format('d-m-Y');
            //$previous_end_date =  Carbon::parse($reponse_data['PolFromDate'])->subDay(1)->format('d-m-Y');            
            $idv = $reponse_data['VehIdv']; 
            
            $proposal->idv          = $idv;
            $proposal->proposal_no          = $reponse_data['NewProposalNo'];
            $proposal->pol_sys_id           = $reponse_data['ProposalSysId'];
            $proposal->od_premium           = $OD_Total;
            $proposal->tp_premium           = $final_tp_premium;
            $proposal->ncb_discount         = abs($ncb_discount);
            $proposal->addon_premium        = 0;//$addon_premium + ($od_lpg_kit + $electrical_premium + $non_electrical_accessories);
            $proposal->total_premium        = $final_net_premium;
            $proposal->service_tax_amount   = $final_payable_amount - $final_net_premium;
            $proposal->final_payable_amount = $final_payable_amount;
            $proposal->cpa_premium          = $pa_owner;
            $proposal->total_discount       = abs($ncb_discount) + abs($other_discount) + $tppd_discount;
            $proposal->ic_vehicle_details   = $vehicleDetails;
            //$proposal->cpa_ins_comp         = $cPAInsComp;
            //$proposal->cpa_policy_fm_dt     = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyFmDt)) : "";
            //$proposal->cpa_policy_no        = $cPAPolicyNo;
            //$proposal->cpa_policy_to_dt     = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyToDt)) : "";
            //$proposal->cpa_sum_insured      = $cPASumInsured;
            //$proposal->electrical_accessories = $ElectricalaccessSI;
            //$proposal->non_electrical_accessories = $NonElectricalaccessSI;
            $proposal->policy_start_date    = $policy_start_date;
            $proposal->policy_end_date      = $policy_end_date;
            // $proposal->final_payable_amount = Arr::last($response['GeneratePCCVProposalResult']['CoverDtlList'])['Premium'];
            $proposal->save();
            
                $data['user_product_journey_id'] = $enquiryId;
                $data['ic_id'] = '33';
                $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                $data['proposal_id'] = $proposal->user_proposal_id;
                updateJourneyStage($data);
                return response()->json([
                    'status' => true,
                    'msg' => "Proposal Submitted Successfully!",
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => camelCase([
                        'proposal_no'        => $reponse_data['NewProposalNo'],
                        'finalPayableAmount' => $final_payable_amount
                    ]),
                ]);
            
        }
        else
        {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'ERROR FOUND'
            ]); 
        }
    }
    
    public static function submitJSON($proposal, $request)
    {
        // dd($proposal->additional_details);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        UserProposal::where('user_product_journey_id', $enquiryId)
        ->update([
            'is_ckyc_verified' => 'N'
        ]);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-M-Y');
        } elseif ($requestData->business_type == 'breakin') {
            $policy_start_date = today()->addDay(2)->format('d-M-Y');
       } elseif($requestData->previous_policy_type =="Third-party" && $master_policy->premium_type_id == 4)
       {
        $policy_start_date = date('d-M-Y', strtotime('+2 day', strtotime($requestData->previous_policy_expiry_date)));
       }     
        else{
            $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));  
        }
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d-M-Y');
        $mmv = get_mmv_details($productData,$requestData->version_id,'shriram');
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
        /*if (!$ic_version_mapping) {
            return [
                'status' => false,
                'msg' => 'Vehicle does not exist with insurance company'
            ];
        }*/

        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false;

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $quote_data['vehicle_register_date'];
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($proposal->prev_policy_expiry_date == 'New' ? date('Y-m-d') : $proposal->prev_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $bike_age = ceil($age / 12);
        //$pkg_selected = ($bike_age > 5) ? "ADDON_01" : "ADDON_03";

        $pkg_selected = $productData->zero_dep == '0' ? "" : "ADDON_01";
        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $RSACover = 'N';
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $additional_details = json_decode($proposal->additional_details);
        $tp_start_date =  $policy_start_date;
        $tp_end_date = $policy_end_date;
        // new business
        $PreviousNilDepreciation = '0'; // addon
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
        $previous_not_sure = strtolower($requestData->previous_policy_expiry_date) == 'new';
        if ($requestData->business_type == 'newbusiness') {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $policy_start_date = today()->format('d-M-Y');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d-M-Y');
            $proposalType = "FRESH";
            $previous_ncb = "";
            $soapAction = "GenerateLTTwoWheelerProposal";
            $url = config('constants.motor.shriram.RENEW_PROPOSAL_URL');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d-M-Y');
            if(in_array($premium_type , ['comprehensive','third_party'])){
                $tp_start_date = today()->format('d-m-Y');
                $tp_end_date = today()->addYear(5)->subDay(1)->format('d-m-Y');
            }
            if($premium_type == 'third_party'){
                $policy_end_date = $tp_end_date;
            }
            $db_policy_enddate = today()->addYear(5)->subDay(1)->format('d-M-Y');
        } else {
            // If previous policy is not sure then consider it as a break in case with more than 90days case.
            $PreviousPolicyFromDt = $previous_not_sure ? '' : Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
            $PreviousPolicyToDt = $previous_not_sure ? '' : Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
            $proposalType = $previous_not_sure ? "RENEWAL.WO.PRV INS DTL" : "RENEWAL OF OTHERS";
            $previous_ncb = $requestData->previous_ncb;
            $soapAction = "GenerateProposal";
            $url = config('constants.motor.shriram.NEW_PROPOSAL_URL');
            $prev_policy_details = isset($additional_details->prepolicy) ? $additional_details->prepolicy :'';
        }
        
         ($pkg_selected == 'ADDON_01' ? 'N' : 'Y' );

        if ($productData->zero_dep == '0') {
            $DepDeductWaiverYN = "N";
            $nilDepreciationCover = 1;
            $PreviousNilDepreciation = 1; // addon
        } else {
            $DepDeductWaiverYN = "Y";
        }

        $LLtoPaidDriverYN = '0';
        $LimitOwnPremiseYN = 'N';

        $Bangladesh="0";
        $Bhutan="0";
        $SriLanka="0";
        $Nepal="0";
        $Maldives="0";
        $Pakistan="0";
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

            if(in_array('Geographical Extension',$value))
            {
                foreach($value['countries'] as $Countries)
                {
                    if($Countries == 'Bhutan')
                    {
                        $Bhutan = '1';
                    }

                    if($Countries == 'Sri Lanka')
                    {
                        $SriLanka = '1';
                    }

                    if($Countries == 'Nepal')
                    {
                        $Nepal = '1';
                    }

                    if($Countries == 'Bangladesh')
                    {
                        $Bangladesh = '1';
                    }

                    if($Countries == 'Pakistan')
                    {
                        $Pakistan = '1';
                    }

                    if($Countries == 'Maldives')
                    {
                        $Maldives = '1';
                    }

                    
                }
                $LimitOwnPremiseYN = 'Y';
             
            }
        }
        $InvReturnYN = 'N';
        $consumable = $engine_protection = '0';
        $nilDepreciationCover = "No";
        $DepDeductWaiverYN = "N";
        $PreviousNilDepreciation = 0;
        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value)) {
                if( $bike_age <= 5)
                {
                    $nilDepreciationCover = "YES";
                    $DepDeductWaiverYN = "Y";
                    $PreviousNilDepreciation = 1; // addon
                }
            }

            if (in_array('Road Side Assistance', $value) && $bike_age < 12) {
                $RSACover = "Y";
            }
            if (in_array('Return To Invoice', $value) && $interval->y<=4 && $interval->m<=11 && $interval->d<=28) {
                $InvReturnYN = 'Y';
            }

            if (in_array('Engine Protector', $value) && $interval->y<=4 && $interval->m<=11 && $interval->d<=28) {
                $engine_protection = 'Y';
            }

            if (in_array('Consumable', $value) && $interval->y<=4 && $interval->m<=11 && $interval->d<=28) {
                $consumable = 'Y';
            }
            $pkg_selected = 'ADDON_05';
        }

        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = 1;
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = 1;
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = 1;
                $externalCNGKITSI = $value['sumInsured'];
            }
        }

        $voluntary_insurer_discounts = 'TWVE1'; // Discount of 0
        $voluntary_discounts = [
            '0'     => 'TWVE1', // Discount of 0
            '500'  => 'TWVE2', // Discount of 750
            '750'  => 'TWVE3', // Discount of 1500
            '1000'  => 'TWVE4', // Discount of 2000
            '1500' => 'TWVE5', 
            '3000' => 'TWVE6',// Discount of 2500
        ];
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            // As suggested by Shyam, Enabling Anti Theft - 09-08-2024
            if (in_array('anti-theft device', $value)) {
                $antitheft = '1';
            }
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)) {
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }
        }

        // salutaion
        $proposal->gender = (strtolower($proposal->gender) == "male" || $proposal->gender == "M") ? "M" : "F";
        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $insured_prefix = '1'; // Mr
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $insured_prefix = '4'; // Miss
                } else {
                    $insured_prefix = '2'; // Mrs
                }
            }
        }
        else{
            $insured_prefix = '3'; // M/S
        }
        // salutaion
        // CPA
        $PAOwnerDriverExclusion = "1";
        $excludeCPA = false;
        $PAYEAR = 1;
        if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
            if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
                $PAOwnerDriverExclusion = "0";
                $PAOwnerDriverExReason = "";
                if(isset($selected_addons->compulsory_personal_accident['0']['tenure'])){
                    $PAYEAR = 5;
                }
            }
            else {
                if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && ( $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license." || $selected_addons->compulsory_personal_accident[0]['reason'] == "I have another motor policy with PA owner driver cover in my name" )) {
                    $PAOwnerDriverExReason = "PA_TYPE2";
                    $excludeCPA = true;
                } else {
                    $PAOwnerDriverExReason = "PA_TYPE4";
                }
            }
        } elseif ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
            $PAOwnerDriverExReason = "PA_TYPE1";
            $excludeCPA = true;
        }
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if ( !($PAOwnerDriverExclusion == '0' || $excludeCPA) ) {
            $cPAInsComp = isset($prev_policy_details->cPAInsComp) ? $prev_policy_details->cPAInsComp:'';
            $cPAPolicyNo = isset($prev_policy_details->cPAPolicyNo) ? $prev_policy_details->cPAPolicyNo:'';
            $cPASumInsured = isset($prev_policy_details->cPASumInsured) ? $prev_policy_details->cPASumInsured:'';
            $cPAPolicyFmDt = isset($prev_policy_details->cPAPolicyFmDt)  ? Carbon::parse($prev_policy_details->cPAPolicyFmDt)->format('d-m-Y') : '';
            $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt)  ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d-m-Y') : '';
        }
        // CPA
        // Policy Type
        switch ($master_policy->premium_type_id) 
        {
                case '1':
                    $ProdCode = "MOT-PRD-002";
                    $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-014" : 'MOT-PLT-001';
                    $PreviousPolicyType = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness' ? '' :'MOT-PLT-001') : "MOT-PLT-002";
                    $URL = config('constants.motor.shriram.PROPOSAL_URL_JSON');
                    $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                    break;
                case '4':
                    $ProdCode = "MOT-PRD-002";
                    $policy_type = 'MOT-PLT-001';
                    $PreviousPolicyType = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness' ? '' :'MOT-PLT-001') : "MOT-PLT-002";
                    $URL = config('constants.motor.shriram.PROPOSAL_URL_JSON');
                    $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                    break;
                case '2':
                    $ProdCode = "MOT-PRD-002";
                    $policy_type = 'MOT-PLT-002';
                    $PreviousPolicyType = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness' ? '' :'MOT-PLT-001') : "MOT-PLT-002";
                    $URL = config('constants.motor.shriram.PROPOSAL_URL_JSON');
                    $quote_log->ex_showroom_price_idv = '';
                    break;
                case '7':
                    $ProdCode = "MOT-PRD-002";
                    $policy_type = 'MOT-PLT-002';
                    $PreviousPolicyType = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness' ? '' :'MOT-PLT-001') : "MOT-PLT-002";
                    $URL = config('constants.motor.shriram.PROPOSAL_URL_JSON');
                    $quote_log->ex_showroom_price_idv = '';
                    break;
                case '3':
                    $ProdCode = "MOT-PRD-002";
                    $policy_type = 'MOT-PLT-013';
                    $PreviousPolicyType = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness'? '' :'MOT-PLT-009') : "MOT-PLT-002";
                    $URL = config('constants.motor.shriram.PROPOSAL_URL_JSON');
                    $tp_start_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d-M-Y');
                    $tp_end_date = date('d-M-Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
                    $prev_tp_policy_no = '';
                    $prev_tp_comp_name = '';
                    $prev_tp_address = '';
                    $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                    #$PAOwnerDriverExReason = "PA_TYPE1";
                    #$insured_prefix = '3';
                    break;
                case '6':
                    $ProdCode = "MOT-PRD-002";
                    $policy_type = 'MOT-PLT-013';
                    $PreviousPolicyType = 'MOT-PLT-013';
                    $soapAction = "GenerateLTTwoWheelerProposal";
                    $URL = config('constants.motor.shriram.PROPOSAL_URL_JSON');
                    $tp_start_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d-M-Y');
                    $tp_end_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d-M-Y');
                    $prev_tp_policy_no = '';
                    $prev_tp_comp_name = '';
                    $prev_tp_address = '';
                    $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                    break;   
        }

        // Policy Type

        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number[0] = '';
        }


        //Hypothecation
        $HypothecationType = $HypothecationBankName = $HypothecationAddress1 = $HypothecationAddress2 = $HypothecationAddress3 = $HypothecationAgreementNo = $HypothecationCountry = $HypothecationState = $HypothecationCity = $HypothecationPinCode = '';
        $vehicleDetails = $additional_details->vehicle;
        
        if ($vehicleDetails->isVehicleFinance == true) {
            $HypothecationType = $vehicleDetails->financerAgreementType;
            $HypothecationBankName = $vehicleDetails->nameOfFinancer;
            $HypothecationAddress1 = $vehicleDetails->hypothecationCity;
            $HypothecationAddress2 = '';
            $HypothecationAddress3 = '';
            $HypothecationAgreementNo = '';
            $HypothecationCountry = '';
            $HypothecationState = '';
            $HypothecationCity = $vehicleDetails->hypothecationCity;
            $HypothecationPinCode = '';
        }
        //Hypothecation

        $rto_code = $quote_log->premium_json['rtoNo'];

        // state_code
        $state_code = ShriramPinCityState::where('pin_code', $proposal->pincode)->first()->state;
        // state_code
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posp_name = '';
        $posp_pan_number = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('user_proposal_id',$proposal['user_proposal_id'])
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
         {
            if($pos_data) {
                $posp_name = $pos_data->agent_name;
                $posp_pan_number = $pos_data->pan_no;
            }
        }elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_SHRIRAM') == 'Y'){
            $posp_name = 'Ravindra Singh';
            $posp_pan_number = 'DNPPS5548E';
        }

        if(!empty($cPAPolicyFmDt) && !empty($cPAPolicyToDt))
        {
            $cPAPolicyFmDt =  str_replace('/', '-', $cPAPolicyFmDt);
            
            $cPAPolicyToDt =  str_replace('/', '-', $cPAPolicyToDt);
        }
           
        if ($tp_only) 
        {
            $RSACover = 'N';
        }

        $getAddress = getAddress([
            'address' => $proposal->address_line1,
            'address_1_limit'   => 100,
            'address_2_limit'   => 100,
            'address_3_limit'   => 100,
        ]);
        $input_array = [
            "objPolicyEntryETT" => 
            [
                "ReferenceNo" => "",
                "ProdCode" => $ProdCode,
                "PolicyFromDt" => $policy_start_date, //"19/08/2021",
                "PolicyToDt" => $policy_end_date, //"18/08/2022",
                "PolicyIssueDt" => today()->format('d-M-y'),
                "InsuredPrefix" => ($requestData->vehicle_owner_type == 'I') ? $insured_prefix : "3",
                "InsuredName" => $proposal->first_name . ' ' . $proposal->last_name,
                "Gender" => $proposal->gender,
                "Address1" =>  trim(($getAddress['address_1'] ?? '')),
                "Address2" =>  trim(($getAddress['address_2'] ?? '')),
                "Address3" =>  trim(($getAddress['address_3'] ?? '')),
                "State" => $state_code,
                "City" => $proposal->city, 
                "PinCode" => $proposal->pincode,
                "PanNo" => $proposal->pan_number,
                "GSTNo" => $proposal->gst_number,
                "TelephoneNo" => "",
                "ProposalType" => $proposalType,
                "PolicyType" => $policy_type,
                "DateOfBirth" => Carbon::parse($proposal->dob)->format('d M Y'),
                "MobileNo" => $proposal->mobile_number,
                "FaxNo" => "",
                "EmailID" => $proposal->email,
                "POSAgentName" => $posp_name, //"Gopi",
                "POSAgentPanNo" => $posp_pan_number, //"12344",
                "CoverNoteNo" => "",
                "CoverNoteDt" => "",
                "VehicleCode" => $mmv_data->veh_code, //"M_10075",
                "FirstRegDt" => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                "VehicleType" =>  ($requestData->business_type == 'newbusiness') ? 'W' : 'U',
                "EngineNo" => $proposal->engine_number,
                "ChassisNo" => $proposal->chassis_number,
                "RegNo1" => explode('-', $rto_code)[0],
                "RegNo2" => explode('-', $rto_code)[1],
                "RegNo3" => $requestData->business_type == 'newbusiness' ? "" : $vehicale_registration_number[2],
                "RegNo4" => $requestData->business_type == 'newbusiness' ? "" : $vehicale_registration_number[3],
                "RTOCode" => $rto_code,
                "IDV_of_Vehicle" => $quote_log->idv, //$idv,
                "Colour" => $proposal->vehicle_color,
                "VoluntaryExcess" => $voluntary_insurer_discounts,//$BusinessType == "2" ? "PCVE1" : "PCVE2", //"MOT-DED-002", $voluntary_insurer_discounts,
                "NoEmpCoverLL" => "0",
                "NoOfCleaner" => "",
                "NoOfDriver" => "1",
                "NoOfConductor" => "",
                "VehicleMadeinindiaYN" => "Y",
                "VehiclePurposeYN" => "",
                "NFPP_Employees" => "",
                "NFPP_OthThanEmp" => "",
                "LimitOwnPremiseYN" => $LimitOwnPremiseYN,
                "Bangladesh" => $Bangladesh,
                "Bhutan" => $Bhutan,
                "SriLanka" => $SriLanka,
                "Nepal" => $Nepal,
                "Pakistan" => $Pakistan,
                "Maldives" => $Maldives,
                "CNGKitYN" => $externalCNGKIT,
                "CNGKitSI" => $externalCNGKITSI,
                "InBuiltCNGKit" => $requestData->fuel_type == 'CNG' ? '1' : '0', // maSTER and  fuel type
                // "LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                "DeTariff" => 0,
                "IMT23YN" => "",
                "BreakIn" => "No",
                "PreInspectionReportYN" => "0",
                "PreInspection" => "",
                "FitnessCertificateno" => "",
                "FitnessValidupto" => "",
                "VehPermit" => "",
                "PermitNo" => "",
                "PAforUnnamedPassengerYN" => $PAforUnnamedPassenger,
                "PAforUnnamedPassengerSI" => $PAforUnnamedPassengerSI,
                "ElectricalaccessYN" => ($tp_only) ? 0 : $Electricalaccess,
                "ElectricalaccessSI" =>  ($tp_only) ? 0 :$ElectricalaccessSI,
                "ElectricalaccessRemarks" => "electric",
                "NonElectricalaccessYN" =>  ($tp_only) ? 0 :$NonElectricalaccess,
                "NonElectricalaccessSI" =>  ($tp_only) ? 0 :$NonElectricalaccessSI,
                "NonElectricalaccessRemarks" => "non electric",
                "PAPaidDriverConductorCleanerYN" =>$PAPaidDriverConductorCleaner,
                "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI,
                "PAPaidDriverCount" => "0",
                "PAPaidConductorCount" => "",
                "PAPaidCleanerCount" => "",
                "NomineeNameforPAOwnerDriver" => $proposal->nominee_name == null ? '' : $proposal->nominee_name,
                "NomineeAgeforPAOwnerDriver" => $proposal->nominee_age == null ? '' : $proposal->nominee_age,
                "NomineeRelationforPAOwnerDriver" => $proposal->nominee_relationship == null ? '' : $proposal->nominee_relationship,
                "AppointeeNameforPAOwnerDriver" => "",
                "AppointeeRelationforPAOwnerDriver" => "",
                "LLtoPaidDriverYN" => $LLtoPaidDriverYN,
                "AntiTheftYN" => $antitheft,
                "PreviousPolicyNo" => $proposal->previous_policy_number ?? '',
                "PreviousInsurer" => $proposal->previous_insurance_company ?? '',
                "PreviousPolicyFromDt" => $PreviousPolicyFromDt,
                "PreviousPolicyToDt" => $PreviousPolicyToDt,
                "PreviousPolicySI" => "",
                "PreviousPolicyClaimYN" =>  $requestData->is_claim == 'Y' ? '1' : '0', // input
                "PreviousPolicyUWYear" => "",
                "PreviousPolicyNCBPerc" => $requestData->business_type == 'newbusiness' ? "" : (int) $previous_ncb,
                "PreviousPolicyType" => $PreviousPolicyType,
                'AddonPackage'                  => $pkg_selected,
                "NilDepreciationCoverYN" => $nilDepreciationCover, // addon zero deprecian
                "PreviousNilDepreciation" => $PreviousNilDepreciation,
                "HypothecationType" => $HypothecationType,
                "HypothecationBankName" => $HypothecationBankName,
                "HypothecationAddress1" => $HypothecationAddress1,
                "HypothecationAddress2" => $HypothecationAddress2,
                "HypothecationAddress3" => $HypothecationAddress3,
                "HypothecationAgreementNo" => $HypothecationAgreementNo,//$hypo_agreement_no,
                "HypothecationCountry" => $HypothecationCountry,
                "HypothecationState" => $HypothecationState,
                "HypothecationCity" => $HypothecationCity,
                "HypothecationPinCode" => $HypothecationPinCode,
                "SpecifiedPersonField" => "",
                "PAOwnerDriverExclusion" =>  $PAOwnerDriverExclusion,
                "PAOwnerDriverExReason" => $PAOwnerDriverExReason,
                "CPAInsComp" => $cPAInsComp,
                "CPAPolicyFmDt" => $cPAPolicyFmDt, 
                "CPAPolicyNo" =>  $cPAPolicyNo,
                "CPAPolicyToDt" =>  $cPAPolicyToDt,
                "CPASumInsured" => $cPASumInsured,
                'DepDeductWaiverYN'         => $DepDeductWaiverYN,
                'DailyExpRemYN'             => 'N',
                'RSACover'                  =>$RSACover, // ($bike_age < 12 ? 'Y':'N'), //$RSACover,
                'InvReturnYN'               => $InvReturnYN,
                'Eng_Protector'             => $engine_protection,
                'Consumables'               => $consumable,
                "EmergencyTranHotelExpRemYN"=>  "N",
                "KeyReplacementYN"=>  "N",
                "DailyExpRemYN"=>  "N",
                "tpPolAddr"=> ($master_policy->premium_type_id == '3' || $master_policy->premium_type_id == '6' ) ? $proposal->state.'-'.$proposal->city : '',
                "tpPolComp"=> ($master_policy->premium_type_id == '3' || $master_policy->premium_type_id == '6' ) ? $proposal->tp_insurance_company : '',
                "tpPolFmdt"=> ($master_policy->premium_type_id == '3' || $master_policy->premium_type_id == '6' ) ? date('d-M-Y', strtotime($proposal->tp_start_date)) : '',
                "tpPolNo"=>  ($master_policy->premium_type_id == '3' || $master_policy->premium_type_id == '6' ) ? $proposal->tp_insurance_number:'',
                "tpPolTodt"=>  ($master_policy->premium_type_id == '3' || $master_policy->premium_type_id == '6' ) ? date('d-M-Y', strtotime($proposal->tp_end_date)) : '',
                "PAYEAR" => $PAYEAR,
                "TRANSFEROFOWNER" => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                "VehicleManufactureYear" => $manufacture_year,
                "PHYSICALPOLICY" => "1"
            ],
        ];

        if ($mmv_data->fuel == "ELECTRIC") {
            $input_array['objPolicyEntryETT']['EngineNo'] = "";
            $input_array['objPolicyEntryETT']['MotorNumber'] = $proposal->engine_number;
        }

        if(in_array($premium_type,['own_damage','own_damage_breakin'])){
            $input_array['objPolicyEntryETT']['PAOwnerDriverExReason'] = "";
            $input_array['objPolicyEntryETT']['PAOwnerDriverExclusion'] = "";
        }
        if($requestData->vehicle_owner_type == 'C'){
            $input_array['objPolicyEntryETT']['NomineeNameforPAOwnerDriver'] = "";
            $input_array['objPolicyEntryETT']['NomineeAgeforPAOwnerDriver'] = "";
            $input_array['objPolicyEntryETT']['NomineeRelationforPAOwnerDriver'] = "";
            $input_array['objPolicyEntryETT']['PAOwnerDriverExclusion'] = "";
            $input_array['objPolicyEntryETT']['PAOwnerDriverExReason'] = "";
        }
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' =>  [
                'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_JSON'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Bike',
            'method' => 'Proposal Submit',
            'transaction_type' => 'proposal',
            'productName' => $productData->product_name. " ($requestData->business_type)",
        ];

        if(config('constants.IS_CKYC_ENABLED') == 'Y') {

            $userProductJouney=UserProductJourney::where('user_product_journey_id',$enquiryId)->first();
                $input_array['objPolicyEntryETT']['CKYC_NO']='';
                $input_array['objPolicyEntryETT']['DOB']=$proposal->dob ? date('d-m-Y',strtotime($proposal->dob)) : '';
                $input_array['objPolicyEntryETT']['POI_Type']='';
                $input_array['objPolicyEntryETT']['POI_ID']='';
                $input_array['objPolicyEntryETT']['POA_Type']='';
                $input_array['objPolicyEntryETT']['POA_ID']='';
                $input_array['objPolicyEntryETT']['FatherName']=$proposal->proposer_ckyc_details->related_person_name ?? '';
                $input_array['objPolicyEntryETT']['POI_DocumentFile']='';
                $input_array['objPolicyEntryETT']['POA_DocumentFile']='';
                $input_array['objPolicyEntryETT']['Insured_photo']='';
                $input_array['objPolicyEntryETT']['POI_DocumentExt']='';
                $input_array['objPolicyEntryETT']['POA_DocumentExt']='';
                $input_array['objPolicyEntryETT']['Insured_photoExt']='';

            if ($proposal->ckyc_type == 'ckyc_number') {
                $input_array['objPolicyEntryETT']['CKYC_NO']=$proposal->ckyc_type_value;
            } else if($proposal->ckyc_type == 'documents') {

                if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $request['userProductJourneyId'])) {
                    $filesList = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId']);

                    $poaFile=\Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId'].'/poa');
                    $poiFile=\Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId'].'/poi');
                    $photoFile=\Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId'].'/photos');

                    if (empty($photoFile) && $requestData->vehicle_owner_type == 'I') {
                        return [
                            'status' => false,
                            'message' => 'Please upload photograph to complete proposal.'
                        ];
                    }

                    if (empty($poiFile)) {
                        return [
                            'status' => false,
                            'message' => 'Please upload Proof of Identity file to complete proposal.'
                        ];
                    }

                    if (empty($poaFile)) {
                        return [
                            'status' => false,
                            'message' => 'Please upload Proof of Address file to complete proposal.'
                        ];
                    }

                    $ckycDocumentData=ckycUploadDocuments::
                    select('cky_doc_data')
                    ->where('user_product_journey_id',$proposal->user_product_journey_id)->first();
                    $ckycDocumentData=json_decode($ckycDocumentData->cky_doc_data, true);
                    //No Photo required for company case            
                    if($requestData->vehicle_owner_type == 'I'){
                        $photoExtension=explode('.',$photoFile[0]);
                        $photoExtension='.'.end($photoExtension);
                    }

                    $poaExtension=explode('.',$poaFile[0]);
                    $poaExtension='.'.end($poaExtension);

                    $poiExtension=explode('.',$poiFile[0]);
                    $poiExtension='.'.end($poiExtension);

                    $poiType=$ckycDocumentData['proof_of_identity']['poi_identity'];
                    $poaType=$ckycDocumentData['proof_of_address']['poa_identity'];

                    switch ($poiType) {
                        case 'panNumber':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'PAN';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_panNumber'];

                            // AML tags
                            $input_array['objPolicyEntryETT']['PANorForm60'] = 'PAN';
                            $input_array['objPolicyEntryETT']['PanNo'] = $ckycDocumentData['proof_of_identity']['poi_panNumber'];
                            $input_array['objPolicyEntryETT']['Pan_Form60_Document_Name'] = "1";
                            $input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $poiExtension;
                            // $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poiFile[0]));
                            $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($poiFile[0]));
                            break;
                        case 'aadharNumber':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'PROOF OF POSSESSION OF AADHAR';
                            $input_array['objPolicyEntryETT']['POI_ID']= substr($ckycDocumentData['proof_of_identity']['poi_aadharNumber'], -4);
                            break;
                        case 'passportNumber':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'PASSPORT';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_passportNumber'];
                            break;
                        case 'drivingLicense':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'Driving License';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_drivingLicense'];
                            break;
                        case 'voterId':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'VOTER ID';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_voterId'];
                            break;
                        case 'registrationCertificate':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'Registration Certificate';
                            $input_array['objPolicyEntryETT']['POI_ID']= $ckycDocumentData['proof_of_identity']['poi_registrationCertificate'];
                            break;
                        case 'cretificateOfIncorporaion':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'Certificate of Incorporation';
                            $input_array['objPolicyEntryETT']['POI_ID']= $ckycDocumentData['proof_of_identity']['poi_certificateOfIncorporation'];
                            break;
                        default:
                            return [
                                'status' => false,
                                'message' => 'Proof of Identity details not found'
                            ];
                    }
                    switch ($poaType) {
                        case 'aadharNumber':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'PROOF OF POSSESSION OF AADHAR';
                            $input_array['objPolicyEntryETT']['POA_ID']= substr($ckycDocumentData['proof_of_address']['poa_aadharNumber'], -4);
                            break;
                        case 'passportNumber':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'PASSPORT';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_passportNumber'];
                            break;
                        case 'drivingLicense':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'Driving License';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_drivingLicense'];
                            break;
                        case 'voterId':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'VOTER ID';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_voterId'];
                            break;
                        case 'registrationCertificate':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'Registration Certificate';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_registrationCertificate'];
                            break;
                        case 'cretificateOfIncorporaion':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'Certificate of Incorporation';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_certificateOfIncorporation'];
                            break;
                        default:
                            return [
                                'status' => false,
                                'message' => 'Proof of Address details not found'
                            ];
                    }

                    // $input_array['objPolicyEntryETT']['POI_DocumentFile'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poiFile[0]));
                    // $input_array['objPolicyEntryETT']['POA_DocumentFile'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poaFile[0]));
                    // $input_array['objPolicyEntryETT']['Insured_photo'] = base64_encode(\Illuminate\Support\Facades\Storage::get($photoFile[0]));

                    $input_array['objPolicyEntryETT']['POI_DocumentFile'] = base64_encode(ProposalController::getCkycDocument($poiFile[0]));
                    $input_array['objPolicyEntryETT']['POA_DocumentFile'] = base64_encode(ProposalController::getCkycDocument($poaFile[0]));
                    if($requestData->vehicle_owner_type == 'I'){
                        $input_array['objPolicyEntryETT']['Insured_photo'] = base64_encode(ProposalController::getCkycDocument($photoFile[0]));
                    }


                    $input_array['objPolicyEntryETT']['POI_DocumentExt']=$poiExtension;
                    $input_array['objPolicyEntryETT']['POA_DocumentExt']=$poaExtension;
                    if($requestData->vehicle_owner_type == 'I'){
                        $input_array['objPolicyEntryETT']['Insured_photoExt']=$photoExtension;
                    }

                    if (config('SHRIRAM_AML_ENABLED') != 'Y') {
                        unset($input_array['objPolicyEntryETT']['PANorForm60']);
                        unset($input_array['objPolicyEntryETT']['PanNo']);
                        unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Name']);
                        unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext']);
                        unset($input_array['objPolicyEntryETT']['Pan_Form60_Document']);
                    }
                }
            }
        }

        if (config('SHRIRAM_AML_ENABLED') == 'Y') {

            $panFile = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId'] . '/pan_document');
            
            if (!empty($panFile) && !empty($proposal->pan_number)) {
                $panFileExtension = explode('.', $panFile[0]);
                $panFileExtension = '.' . end($panFileExtension);
                $input_array['objPolicyEntryETT']['PANorForm60'] = 'PAN';
                $input_array['objPolicyEntryETT']['PanNo'] = $proposal->pan_number;
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Name'] = '1';
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $panFileExtension;
                // $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($panFile[0]));
                $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($panFile[0]));
            }

            $form60File = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId'] . '/form60');
        
            if (!empty($form60File)) {
                $form60Extension = explode('.', $form60File[0]);
                $form60Extension = '.' . end($form60Extension);
                $input_array['objPolicyEntryETT']['PANorForm60'] = 'FORM60';
                $input_array['objPolicyEntryETT']['PanNo'] = '';
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Name'] = '1';
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $form60Extension;
                // $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($form60File[0]));
                $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($form60File[0]));
            }

            if (!isset($input_array['objPolicyEntryETT']['PANorForm60'])) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Please upload Pan or Form60 document'
                ]);
            }
        } else {
            unset($input_array['objPolicyEntryETT']['PANorForm60']);
            unset($input_array['objPolicyEntryETT']['PanNo']);
            unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Name']);
            unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext']);
            unset($input_array['objPolicyEntryETT']['Pan_Form60_Document']);
        }
        $additional_data['url'] = $URL;
        
        $get_response = ServicesShriramSubmitProposal::proposalSubmit($input_array, $proposal, $additional_data, $request);
    //    $get_response = getWsData($URL, $input_array, 'shriram', $additional_data);
       $response = $get_response['response'];
        
       $response = json_decode($response,True);

        $vehicleDetails = [
            'manufacture_name'  => $mmv_data->veh_model,
            'model_name'        => $mmv_data->model_desc,
            'version'           => '',
            'fuel_type'         => $mmv_data->fuel,
            'seating_capacity'  => $mmv_data->veh_seat_cap,
            'carrying_capacity' => $mmv_data->veh_seat_cap,
            'cubic_capacity'    => $mmv_data->veh_cc,
            'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
            'vehicle_type'      => $mmv_data->veh_ob_type ?? '',
        ];

        if ($response['MessageResult']['Result'] == 'Success'){
            $proposal->proposal_no = $response['GenerateProposalResult']['PROPOSAL_NO'];
            $proposal->pol_sys_id = $response['GenerateProposalResult']['POL_SYS_ID'];
            $proposal->ic_vehicle_details = $vehicleDetails;
            $proposal->is_ckyc_verified = 'Y';
            $proposal->save();

            $coverDTList = $response['GenerateProposalResult']['CoverDtlList'];
            $final_od_premium = $final_tp_premium = $cpa_premium = $NetPremium = $addon_premium = $ncb_discount = $total_discount = 0;
            $igst           = $anti_theft = $other_discount = 0;
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 0;
            $ncb_discount   = $tppd = $final_tp_premium =  0;
            $final_od_premium = $final_net_premium =0;
            $final_payable_amount = $basic_od = $electrical_accessories = 0;
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = $tppd_discount=0;
            $pa_owner       = $voluntary_excess = $pa_unnamed =  0;
            $ll_paid_driver =$engine_protection = $consumables_cover = $return_to_invoice = $loading_amount = 0;
            $geog_Extension_TP_Premium = $geog_Extension_OD_Premium = $geo_ext_one = $geo_ext_two = 0;
            $Minimum_OD_Loading = $NilDepreciationLoading = 0;
            $addons_available = [
                'Nil Depreciation', 'Nil Depreciation Cover','Nil Depreciation - 1 YEAR','Road Side Assistance', 'ROAD SIDE ASSISTANCE','Nil Depreciation Loading'
            ];
            $discounts_available = [
                'VOLUNTARY EXCESS DISCOUNT-IMT-22A', 'De-Tariff Discount', 'NCB Discount','GR39A-TPPD COVER', 'Voluntary excess/deductibles','GR39A-Limit The Third Party Property Damage Cover','GR30-Anti Theft Discount Cover - 1 Year - OD'
            ];
            
            foreach ($coverDTList as $key => $value) {
                $value['CoverDesc'] = trim($value['CoverDesc']);

                if (in_array($value['CoverDesc'], [
                    'Basic OD Premium',
                    'Basic Premium - 1 Year',
                    'Basic Premium - OD',
                    'Daily Expenses Reimbursement - OD',
                    'Basic Premium - 1 Year - OD'
                ])) {
                    $basic_od = $value['Premium'];
                    $od_key = $key;
                }
                if (in_array($value['CoverDesc'], [
                    'Voluntary excess/deductibles',
                    'Voluntary excess/deductibles - 1 Year',
                    'Voluntary excess/deductibles - 1 Year - OD'
                ])) {
                    $voluntary_excess = abs($value['Premium']);
                }
                if ( in_array($value['CoverDesc'], ['OD Total']) ) {
                    $final_od_premium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('Basic TP Premium','Basic Premium - TP')) ) {
                    $tppd = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'Basic Premium - 1 Year' &&  $value['Premium'] != $basic_od && $key != $od_key) {
                    $tppd = $value['Premium'];
                }
                
                if ($value['CoverDesc'] == 'Total Premium') {
                    $final_net_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'IGST(18.00%)') {
                    $igst = $igst + $value['Premium'];
                }

                if ($value['CoverDesc'] == 'SGST/UTGST(0.00%)') {
                    $sgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'CGST(0.00%)') {
                    $cgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], [
                    'NCB Discount',
                    'NCB Discount  - OD'
                ])) {
                    $ncb_discount = abs($value['Premium']);
                }

                if ($value['CoverDesc'] == 'UW LOADING-MIN PREMIUM') {
                    $loading_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('Minimum OD Loading','Minimum OD Loading - OD'))) {
                    $Minimum_OD_Loading = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['Nil Depreciation Cover','Nil Depreciation Cover - 1 Year'] )) {
                    $zero_dep_amount = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('Return to Invoice', 'Return to Invoice - 1 Year')) ) {
                    $return_to_invoice = $value['Premium'];
                    
                }
                if (in_array($value['CoverDesc'], ['Consumable','Consumable - 1 Year','Consumable - OD'])) {
                    $consumables_cover = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Engine Protector','Engine Protector - 1 Year','Engine Protector - OD'])) {
                    $engine_protection = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR41-Cover For Electrical and Electronic Accessories','GR41-Cover For Electrical and Electronic Accessories - OD'])) {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG-KIT-COVER-GR42', 'INBUILT CNG/LPG KIT'))) {
                    $lpg_cng = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER'))) {
                    $lpg_cng_tp = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('Cover For Non Electrical Accessories','Cover For Non Electrical Accessories - OD'))) {
                    $non_electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR36B2-PA Cover For Passengers (Un-Named Persons)','GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year','GR36B2-PA Cover For Passengers (Un-Named Persons) - TP'])) {
                    $pa_unnamed = $value['Premium'];
                }

                if ($value['CoverDesc'] == '  ') {
                    $pa_paid_driver = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER','GR36A-PA FOR OWNER DRIVER - 1 Year','GR36A-PA FOR OWNER DRIVER - TP'])) {
                    $pa_owner = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], [
                    'GR36A-PA FOR OWNER DRIVER - 1 Year - TP',
                    'GR36A-PA FOR OWNER DRIVER'
                ])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 2 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 3 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 4 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 5 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Legal Liability Coverages For Paid Driver','Legal Liability Coverages For Paid Driver - 1 Year','Legal Liability Coverages For Paid Driver - TP'])) {
                    $ll_paid_driver = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['De-Tariff Discount' ,'De-Tariff Discount - 1 Year', 'De-Tariff Discount - OD'])) {
                    $other_discount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'GR30-Anti Theft Discount Cover - 1 Year - OD', 'GR30-Anti Theft Discount Cover - OD'])) {
                    $anti_theft = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('Road Side Assistance','Road Side Assistance - 1 Year','Road Side Assistance - OD')) ) {
                    $rsapremium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('GR39A-Limit The Third Party Property Damage Cover','GR39A-Limit The Third Party Property Damage Cover - TP')) ) {
                    //$tppd_discount = $value['Premium'];
                    $tppd_discount = abs(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }
                if ( in_array($value['CoverDesc'], ['TP Total']) ) {
                    $final_tp_premium = $value['Premium'];
                    //$final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 5) + $pa_owner ) - $tppd_discount : $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension','GR4-Geographical Extension - 1 Year']) ) {
                    if($geo_ext_one > 0)
                    {
                        $geo_ext_two = $value['Premium'];
                    }else
                    {
                        $geo_ext_one = $value['Premium'];
                    }
                    //$final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 5) + $pa_owner ) - $tppd_discount : $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['Nil Depreciation Loading','Nil Depreciation Loading - 1 Year'])) {
                    $NilDepreciationLoading = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension - OD']) ) {
                    $geog_Extension_OD_Premium = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension - TP']) ) {
                    $geog_Extension_TP_Premium = $value['Premium'];
                }
            }

            UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date'     => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                    'policy_end_date'       => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                    'proposal_no'           => $proposal->proposal_no,
                    'unique_proposal_id'    => $proposal->proposal_no,
                    'od_premium'            => $final_od_premium - $addon_premium,
                    'tp_premium'            => $final_tp_premium,
                    'addon_premium'         => $addon_premium,
                    'cpa_premium'           => $cpa_premium,
                    'final_premium'         => $final_net_premium,
                    'total_premium'         => $final_net_premium,
                    'service_tax_amount'    => $final_payable_amount - $NetPremium,
                    'final_payable_amount'  => $final_payable_amount,
                    'product_code'          => $mmv_data->vap_prod_code,
                    'ic_vehicle_details'    => json_encode($vehicleDetails),
                    'ncb_discount'          => $ncb_discount,
                    'total_discount'        => $total_discount,
                    'cpa_ins_comp'          => $cPAInsComp,
                    'cpa_policy_fm_dt'      => str_replace('/', '-', $cPAPolicyFmDt),
                    'cpa_policy_no'         => $cPAPolicyNo,
                    'cpa_policy_to_dt'      => str_replace('/', '-', $cPAPolicyToDt),
                    'cpa_sum_insured'       => $cPASumInsured,
                    'electrical_accessories'    => $ElectricalaccessSI,
                    'non_electrical_accessories'=> $NonElectricalaccessSI,
                    'tp_start_date' => isset($proposal->tp_start_date) ? date('d-m-Y', strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime(str_replace('/', '-',$policy_start_date))),
                    'tp_end_date' => isset($proposal->tp_end_date) ? date('d-m-Y', strtotime($proposal->tp_end_date)) :
                    ($requestData->business_type == 'newbusiness'  
                        ? date('d-m-Y', strtotime('+5 year -1 day', strtotime(str_replace('/', '-',$policy_start_date))))
                        : date('d-m-Y', strtotime('+1 year -1 day', strtotime(str_replace('/', '-',$policy_start_date))))
                    ),
                ]);
                
            $data['user_product_journey_id'] = $enquiryId;
            $data['ic_id'] = $master_policy->insurance_company_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);

            ShriramPremiumDetailController::saveJsonPremiumDetails($get_response['webservice_id']);

            return response()->json([
                'status' => true,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['MessageResult']['ErrorMessage'] ." ".$response['GenerateProposalResult']['ERROR_DESC'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $data['user_product_journey_id'],
                    'proposalNo' => $proposal->proposal_no,
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => '',
                    'inspection_number' => ''
                ]
            ]);
        } else {
            $error_message = '';
            if(isset($response['GenerateProposalResult']['ERROR_DESC']))
            {
                $error_message = $response['MessageResult']['ErrorMessage'] ." ".$response['GenerateProposalResult']['ERROR_DESC'];
            }else
            {
                $error_message = $response['MessageResult']['ErrorMessage'];
            }
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $error_message,
            ]);
        }
    }

    public static function policyApprovalXml($proposal, $request, $response, $URL)
    {
        $productData = getProductDataByIc($request['policyId']);

        $root = [
            'rootElementName' => 'soap:Envelope',
            '_attributes' => [
                'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema'
            ]
        ];

        $policy_approval_input = [
            'soap:Header' => [
                'AuthHeader' => [
                    'Username' => config('constants.motor.shriram.AUTH_NAME_SHRIRAM_MOTOR'),
                    'Password' => config('constants.motor.shriram.AUTH_PASS_SHRIRAM_MOTOR'),
                    '_attributes' => [
                        'xmlns' => 'http://tempuri.org/',
                        'MyAttribute' => ''
                    ],
                ]
            ],
            'soap:Body' => [
                'PolicyApprove' => [
                    'objPolicyApprovalETT' => [
                        'ProposalNo' => $response['PROPOSAL_NO'],
                        'TransactionNumber' => $response['POL_SYS_ID'],
                        'CardNumber' => '',
                        'CardholderName' => '',
                        'CardType' => '',
                        'CardValidUpTo' => '',
                        'BankName' => '',
                        'BranchName' => '',
                        'PaymentType' => 'CC',
                        'TransactionDate' => date('d-m-Y', time()),
                        'ChequeType' => '',
                        'ChequeClearType' => '',
                        'CashType' => '',
                    ],
                    '_attributes' => [
                        'xmlns' => 'http://tempuri.org/',
                    ]
                ]
            ]
        ];

        $input_xml = ArrayToXml::convert($policy_approval_input, $root, false, 'utf-8');

        $get_response = getWsData($URL, $input_xml, $request['companyAlias'], [
            'enquiryId' => $proposal['user_product_journey_id'],
            'headers' => [
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Bike',
            'method' => 'Policy Approval',
            'transaction_type' => 'proposal',
            'productName' => $productData->product_name . ($productData->zero_dep == 0 ? ' (zero_dep)' : ''),
        ]);

        if ($get_response) {
            $response = $get_response['response'];
            $response = XmlToArray::convert($response);

            if ($response['soap:Body']['PolicyApproveResponse']['PolicyApproveResult']['Err_Code'] == 0) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
