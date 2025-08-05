<?php


namespace App\Http\Controllers\Proposal\Services\V2;



use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\ckycUploadDocuments;
use App\Models\ShriramPinCityState;
use App\Models\MasterProductSubType;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\SyncPremiumDetail\Services\ShriramPremiumDetailController;

use function Composer\Autoload\includeFile;
use App\Models\CorporateVehiclesQuotesRequest;



include_once app_path() . '/Helpers/CvWebServiceHelper.php';


class ShriramgcvpcvSubmitProposal
{

function submitV2($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $premium_type = MasterPremiumType::where('id',$master_policy->premium_type_id)->pluck('premium_type_code')->first();
        $requestData = getQuotation($enquiryId);

        UserProposal::where('user_product_journey_id', $enquiryId)
        ->update([
            'is_ckyc_verified' => 'N'
        ]);

        $b2b_seller_type = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->first();

        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-m-Y');
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }else if($requestData->business_type == 'breakin')
        {
            $policy_start_date = today()/*->addDay(1)*/->format('d-m-Y');
        }

        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
        if($requestData->business_type == "newbusiness")
        {
            $vehicale_registration_number = explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code,true));
        }
        else{
            $vehicale_registration_number = explode('-', getRegisterNumberWithOrWithoutZero(formatRegistrationNo($proposal->vehicale_registration_number)));
        }
        $break_in = (Carbon::parse($proposal->prev_policy_expiry_date)->diffInDays($policy_start_date) > 0) ? 'YES' : 'NO';

        $selected_addons = SelectedAddons::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $roadsideassistance = 'N';
        $ElectricalaccessSI = $rsacover = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
    	//$requestData = getQuotation($enquiryId);
    	$productData = getProductDataByIc($request['policyId']);
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
    
        $ic_version_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $is_breakin = 'No';
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
        $PreviousNilDepreciation = '0';

        // new business
        if ($requestData->business_type == 'newbusiness') {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $tp_start_date = $policy_start_date = today()->format('d-m-Y');
            $tp_end_date = $policy_end_date = today()->addYear(1)->subDay(1)->format('d-m-Y');
            $proposalType = "Fresh";
            $previous_ncb = "";
            $PreviousPolicyType = "";
            $PreviousNilDepreciation = 1; // addon

        } else {
            $PreviousPolicyFromDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($proposal->prev_policy_expiry_date)->subYear(1)->addDay(1)->format('d-m-Y');
            $PreviousPolicyToDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($proposal->prev_policy_expiry_date)->format('d-m-Y');
            $tp_start_date = $policy_start_date = Carbon::parse($proposal->prev_policy_expiry_date)->addDay(1)->format('d-M-Y');
            $tp_end_date = $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
            $proposalType = ($requestData->previous_policy_type == 'Not sure') ? "RENEWAL.WO.PRV INS DTL" : "RENEWAL OF OTHERS";
            $PreviousPolicyType = $requestData->previous_policy_type == 'Third-party' ? "MOT-PLT-002" : "MOT-PLT-001";
            $previous_ncb = $requestData->previous_ncb;//$quote_log->quote_details['previous_ncb'];
            $PreviousNilDepreciation = '0';
        }
        if($requestData->business_type == 'breakin' || $requestData->business_type == 'Break-in'){
            $tp_start_date = $policy_start_date = today()/*->addDay(2)*/->format('d-m-Y');
            $tp_end_date = $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
            $is_breakin = 'Y';
        }

        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value)) {
                $nilDepreciationCover = 1;
                $PreviousNilDepreciation = 1;
            }

            if (in_array('Road Side Assistance', $value)) {
                $roadsideassistance = "Y";
            }
        }
        if (isset($request->all()['declaredAddons'])) {
            if ($request->all()['declaredAddons']['zeroDepreciation']) {
                $PreviousNilDepreciation = 1;
            } else {
                $PreviousNilDepreciation = 0;
            }
        }
        if ($nilDepreciationCover == 1 && $PreviousNilDepreciation != 1) {
            $is_breakin = 'Y';
        }

        $LLtoPaidDriverYN = '0';
        $no_of_cleanerLL = $no_of_conductorLL = $no_of_driverLL = 0;
        $countries = [];
        foreach($additional_covers as $key => $value) {
            if (isset($value['name']) && $value['name'] === 'LL paid driver') {
                $LLtoPaidDriverYN = '1';
            }
            if ($value['name'] == 'LL paid driver/conductor/cleaner' && isset($value['LLNumberCleaner']) && $value['LLNumberCleaner'] > 0) 
            {
                $LLtoPaidDriverYN = '1';
                $no_of_cleanerLL = $value['LLNumberCleaner'];
               
            }
            if ($value['name']== 'LL paid driver/conductor/cleaner' && isset($value['LLNumberConductor']) && $value['LLNumberConductor'] > 0) 
            {
                $LLtoPaidDriverYN = '1';
                $no_of_conductorLL = $value['LLNumberConductor'];
            }
            if ($value['name'] == 'LL paid driver/conductor/cleaner' && isset($value['LLNumberDriver']) && $value['LLNumberDriver'] > 0) 
            {
                $LLtoPaidDriverYN = '1';
                $no_of_driverLL = $value['LLNumberDriver'];
            }

            if ($value['name'] == 'Geographical Extension')
            {
                $countries = $value['countries'];
            }            

            if ($value['name'] == 'PA cover for additional paid driver') {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }

            if ($value['name'] == 'anti-theft device') {
                $antitheft = 1;
            }

        }
        foreach ($accessories as $key => $value) {
            if ($value['name'] == 'Electrical Accessories') {
                $Electricalaccess = 1;
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if ($value['name'] == 'Non-Electrical Accessories') {
                $NonElectricalaccess = 1;
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                $externalCNGKIT = 1;
                $externalCNGKITSI = $value['sumInsured'];
            }

            if ($value['name'] == 'PA To PaidDriver Conductor Cleaner') {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }

            if ($value['name'] == 'PA To Unnamed Passenger') {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
        }
        
        // insured prefix 
        if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
            // If gender is not M nor F, then return with a msg. If passed another value, then the proposal API will fail.
            if(!in_array($proposal->gender, ['M', 'F'])){
                return  [   
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Please select the Gender.'
                ]; 
            }
            if ($proposal->gender == "M") {
                $insured_prefix = '1'; // Mr
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $insured_prefix = '4'; // Mrs
                } else {
                    $insured_prefix = '2'; // Miss
                }
            }
        }
        else{
            $insured_prefix = '3'; // M/S
        }
        // insured prefix 
        // CPA
        $PAOwnerDriverExclusion = "1";
        $excludeCPA = false;
        if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
            if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
                $PAOwnerDriverExclusion = "0";
                $PAOwnerDriverExReason = "";
            }
            else {
                if (config('constants.IS_OLA_BROKER') == 'Y')
                {
                    $PAOwnerDriverExReason = "PA_TYPE2";
                    $excludeCPA = true;
                }
                else
                {
                    if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
                        $PAOwnerDriverExReason = "PA_TYPE2";
                        $excludeCPA = true;
                    } else {
                        $PAOwnerDriverExReason = "PA_TYPE4";
                    }
                }
            }
        } elseif ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
            $PAOwnerDriverExReason = "PA_TYPE1";
            $excludeCPA = true;
        }
        $additional_details = json_decode($proposal->additional_details);
        $prev_policy_details = $additional_details->prepolicy ?? '';
        
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if ( !($PAOwnerDriverExclusion == '0' || $excludeCPA) && !empty($prev_policy_details)) {
            $cPAInsComp = $prev_policy_details->cPAInsComp ?? '';
            $cPAPolicyNo = $prev_policy_details->cPAPolicyNo ?? '' ;
            $cPASumInsured = $prev_policy_details->cPASumInsured ?? '';
            $cPAPolicyFmDt = !empty($prev_policy_details->cPAPolicyFmDt) ? Carbon::parse($prev_policy_details->cPAPolicyFmDt)->format('d-M-Y') : '';
            $cPAPolicyToDt = !empty($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d-M-Y') : '';
        }
        // CPA
        // Policy Type
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
        $policy_type = ($premium_type == 'comprehensive' ? 'MOT-PLT-001' : 'MOT-PLT-002');

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
            $HypothecationAddress1 = $vehicleDetails->hypothecationCity ?? '';
            $HypothecationCity = $vehicleDetails->hypothecationCity ?? '';
        }
        //Hypothecation

        if (!empty($corporate_vehicles_quotes_request->rto_code)) {
            $rto_code = $corporate_vehicles_quotes_request->rto_code;
        } else {
            $rto_code =  $vehicale_registration_number[0] ?? '' . '-' . $vehicale_registration_number[1] ?? '';
        }

        // state_code
        $state_code = ShriramPinCityState::where('pin_code', $proposal->pincode)->first()->state;
        // state_code

        $additional_details = json_decode($proposal->additional_details);
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posAgentName = $posAgentPanNo = $MISPDealerCode = '';
    $misp_testing_mode = config('MISP_TESTING_MODE_ENABLE_SHRIRAM_GCV') == 'Y';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $posAgentName = $pos_data->agent_name;
                $posAgentPanNo = $pos_data->pan_no;
            }
        }else if(isset($pos_data->seller_type) && $pos_data->seller_type == 'misp'){
            $MISPDealerCode = $pos_data->relation_shriram;
                if (empty($pos_data->relation_shriram) && !$misp_testing_mode)
                {
                    return [
                        'status' => false,
                        'message' => 'MISP CODE not available',
                    ];
                }
                if($misp_testing_mode){
                    $MISPDealerCode = config('MISP_DEALER_CODE_SHRIRAM_GCV'); //'BRC0000796',
                }
        }
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 60,
            'address_2_limit'   => 20,         
            'address_3_limit'   => 20,         
        ];
        $getAddress = getAddress($address_data);
        $is_pcv_vehicle = MasterProductSubType::where('parent_id', 8)->pluck('product_sub_type_id')->toArray();
        $is_pcv = in_array($requestData->product_sub_type_id, $is_pcv_vehicle);
        $is_gcv_vehicle = MasterProductSubType::where('parent_id', 4)->pluck('product_sub_type_id')->toArray();
        $is_gcv = in_array($requestData->product_sub_type_id, $is_gcv_vehicle);
        $vehicleClass = '';
        $veh_category = null;
        $vehdesc = '';
        if($is_pcv){
        if($productData->product_sub_type_code == 'TAXI')
        {
            $veh_category = '4W';
        }
        else if(in_array($productData->product_sub_type_code, ['AUTO-RICKSHAW','ELECTRIC-RICKSHAW']))
        {
            $veh_category = '3W';
        }

        if ($veh_category == '3W' && $ic_version_details->veh_seat_cap <= 6) 
        {
            $vehicleClass = "CLASS_4C1B"; //PCCV-3 wheelers-carrying passengers-capacity NOT > 6
            $vehdesc = "PCCV-3 wheelers-carrying passengers-capacity NOT > 6";
        } 
        else if ($veh_category == '4W' && $ic_version_details->veh_seat_cap <= 6) 
        {
            $vehicleClass = "CLASS_4C1A"; //PCCV-4 wheelers - carrying passengers-capacity NOT > 6
            $vehdesc = "PCCV-4 wheelers - carrying passengers-capacity NOT > 6";
        } 
        else if ($veh_category == '3W'&& $ic_version_details->veh_seat_cap > 6 && $ic_version_details->veh_seat_cap <= 17) 
        {
            $vehicleClass = "CLASS_4C3";  //PCCV-3 wheeled vehicles-carrying passengers > 6 but NOT >17
            $vehdesc = "PCCV-3 wheeled vehicles-carrying passengers > 6 but NOT >17";
        } 
        else if (($veh_category == '4W' && $ic_version_details->veh_seat_cap > 6) || ($veh_category == '3W' && $ic_version_details->veh_seat_cap > 17)) 
        {
            $vehicleClass = "CLASS_4C2";  // PCCV-4 (or more) wheeled vehicles-capacity > 6 and 3 wheelers-carrying passengers -capacity > 17
            $vehdesc = "PCCV-4 (more) wheeled vehicles-capacity > 6 and 3 wheelers-carrying passengers-capacity > 17";
        }
    }
    if($is_gcv){
        if ($ic_version_details->no_of_wheels == '3' && $requestData->gcv_carrier_type == "PUBLIC") {
            $vehicleClass = "CLASS_4A3"; //GOODS CARRYING MOTORISED THREE WHEELERS AND MOTORISED PEDAL CYCLES- PUBLIC CARRIERS
            $vehdesc = "GOODS CARRYING MOTORISED THREE WHEELERS AND MOTORISED PEDAL CYCLES-PUBLIC CARRIERS";
        }
        elseif($ic_version_details->no_of_wheels == '3'  && $requestData->gcv_carrier_type == "PRIVATE")
        {
            $vehicleClass = "CLASS_4A4"; //GOODS CARRYING MOTORISED THREE WHEELERS AND MOTORISED PEDAL  CYCLES- PRIVATE CARRIERS
            $vehdesc = ">GOODS CARRYING MOTORISED THREE WHEELERS AND MOTORISED PEDAL CYCLES-PRIVATE CARRIERS";
          
        }
         elseif ($requestData->gcv_carrier_type == "PRIVATE") {
            $vehicleClass = "CLASS_4A2";  //GCCV-PRIVATE CARRIERS OTHER THAN THREE WHEELERS
            $vehdesc = "GCCV-PRIVATE CARRIERS OTHER THAN THREE WHEELERS";
        } elseif ($requestData->gcv_carrier_type == "PUBLIC") {
            $vehicleClass = "CLASS_4A1"; //GCCV-PUBLIC CARRIERS OTHER THAN THREE WHEELERS
            $vehdesc = "GCCV-PUBLIC CARRIERS OTHER THAN THREE WHEELERS";
        } 
    }
        $imt23yn = 0;
    
        $inputArray = [
            "objPolicyEntryETT" => [
                "ReferenceNo" => "",
                "ProdCode" => 'MOT-PRD-005', // For PCCV
                "PolicyFromDt" => $policy_start_date,
                "PolicyToDt" => $policy_end_date,
                "PolicyIssueDt" => today()->format('d-m-Y'),
                "InsuredPrefix" => $insured_prefix, // kit prefix
                "InsuredName" => $proposal->first_name . ' ' . $proposal->last_name,
                "Gender" => $proposal->gender,
                "Address1" => empty(trim($getAddress['address_1'])) ? "." : trim($getAddress['address_1']),
                "Address2" => empty(trim($getAddress['address_2'])) ? "." : trim($getAddress['address_2']),
                "Address3" => empty(trim($getAddress['address_3'])) ? "." : trim($getAddress['address_3']),
                "State" => $state_code,
                "City" => $proposal->city,
                "PinCode" => $proposal->pincode,
                "PanNo" => $proposal->pan_number,
                "GSTNo" => $proposal->gst_number,
                "TelephoneNo" => "",
                "ProposalType" => $proposalType, // kit
                "PolType" => $policy_type, // kit
                "DateOfBirth" => Carbon::parse($proposal->dob)->format('Y M d'),
                "MobileNo" => $proposal->mobile_number,
                "FaxNo" => "",
                "EmailID" => $proposal->email,
                "POSAgentName" => $posAgentName,
                "POSAgentPanNo" => $posAgentPanNo,
                "CoverNoteNo" => "",
                "CoverNoteDt" => "",
                "VehicleCategory" => $vehicleClass, //$ic_version_details->veh_category,
                "VehicleCode" => $ic_version_details->veh_code, // kit
                "FirstRegDt" => $requestData->vehicle_register_date, //  car regi date
                "VehicleType" => $requestData->business_type == "newbusiness" ? "W" : "U", // kit
                "EngineNo" => $proposal->engine_number,
                "ChassisNo" => $proposal->chassis_number,
                "RegNo1" => $vehicale_registration_number[0] ?? '',
                "RegNo2" => $vehicale_registration_number[1] ?? '',
                "RegNo3" => $vehicale_registration_number[2] ?? '',
                "RegNo4" => $vehicale_registration_number[3] ?? '',
                "RTOCode" => RtoCodeWithOrWithoutZero($vehicale_registration_number[0] . '-' . $vehicale_registration_number[1], true),
                "IDV_of_Vehicle" => $quote_log->idv, // quote data
                "Colour" => $proposal->vehicle_color,
                "VoluntaryExcess" => "0", // quote
                "NoEmpCoverLL" => "0",
                "NoOfCleaner" => $no_of_conductorLL,
                "NoOfDriver" => $no_of_driverLL,
                "NoOfConductor" => $no_of_conductorLL,
                "VehicleMadeinindiaYN" => "",
                "VehiclePurposeYN" => "",
                "NFPP_Employees" => "",
                "NFPP_OthThanEmp" => "",
                // "LimitOwnPremiseYN" => "",
                "Bangladesh" => in_array('Bangladesh', $countries) ? 1 : 0,
                "Bhutan" => in_array('Bhutan', $countries) ? 1 : 0,
                "Srilanka" => in_array('Sri Lanka', $countries) ? 1 : 0,
                "Nepal" => in_array('Nepal', $countries) ? 1 : 0,
                "Pakistan" => in_array('Pakistan', $countries) ? 1 : 0,
                "Maldives" => in_array('Maldives', $countries) ? 1 : 0,
                "CNGKitYN" => $externalCNGKIT, // input page
                "CNGKitSI" => $externalCNGKITSI, //input
                "InBuiltCNGKit" => $requestData->fuel_type == 'CNG' ? "1" : "0", // maSTER and  fuel type
                // "LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                "DeTariff" => 0,
                "IMT23YN" => "", // for GCV
                "BreakIn" => $is_breakin, // prev insu expiry date and today date
                "PreInspectionReportYN" => "0",
                "PreInspection" => "",
                "FitnessCertificateno" => "",
                "FitnessValidupto" => "",
                "VehPermit" => "",
                "PermitNo" => "",
                "PAforUnnamedPassengerYN" => $PAforUnnamedPassenger, // addon quote page
                "PAforUnnamedPassengerSI" => $PAforUnnamedPassengerSI, // addon quote page   user addon table
                "ElectricalaccessYN" => $Electricalaccess, // addon quote page
                "ElectricalaccessSI" => $ElectricalaccessSI, // addon quote page   user addon table
                "ElectricalaccessRemarks" => "",
                "NonElectricalaccessYN" => $NonElectricalaccess, // addon quote page   user addon table
                "NonElectricalaccessSI" => $NonElectricalaccessSI, // addon quote page   user addon table
                "NonElectricalaccessRemarks" => "",
                "PAPaidDriverConductorCleanerYN" => $PAPaidDriverConductorCleaner, // addon
                "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI, // addon
                "PAPaidDriverCount" => "", // addon
                "PAPaidConductorCount" => "", // addon
                "PAPaidCleanerCount" => "", // addon
                "NomineeNameforPAOwnerDriver" => $proposal->nominee_name == null ? '' : $proposal->nominee_name,
                "NomineeAgeforPAOwnerDriver" => $proposal->nominee_age == null ? '0' : $proposal->nominee_age,
                "NomineeRelationforPAOwnerDriver" => $proposal->nominee_relationship == null ? '' : $proposal->nominee_relationship,
                "AppointeeNameforPAOwnerDriver" => "", //  nominne ke page
                "AppointeeRelationforPAOwnerDriver" => "", //  nominne ke page
                "LLtoPaidDriverYN" => $LLtoPaidDriverYN, // input
                "AntiTheftYN" => $antitheft, // addon
                "PreviousPolicyNo" => $proposal->previous_policy_number,
                "PreviousInsurer" => $proposal->previous_insurance_company,
                "PreviousPolicyFromDt" => $PreviousPolicyFromDt,
                "PreviousPolicyToDt" => $PreviousPolicyToDt,
                "PreviousPolicySI" => "",
                "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0', // input
                "PreviousPolicyUWYear" => "",
                "PreviousPolicyNCBPerc" => (int) $previous_ncb, // prev ncb %
                "PreviousPolicyType" => $PreviousPolicyType, // master
                "NilDepreciationCoverYN" => ($nilDepreciationCover == 1) ? 'Yes' : 'No', // addon zero deprecian
                "PreviousNilDepreciation" => $PreviousNilDepreciation, // addon
                "RSACover" => $roadsideassistance, // addon
                "HypothecationType" => $HypothecationType, // proposal page
                "HypothecationBankName" => $HypothecationBankName, // proposal page
                "HypothecationAddress1" => $HypothecationAddress1, // proposal page
                "HypothecationAddress2" => $HypothecationAddress2, // proposal page
                "HypothecationAddress3" => $HypothecationAddress3, // proposal page
                "HypothecationAgreementNo" => $HypothecationAgreementNo, // proposal page
                "HypothecationCountry" => $HypothecationCountry, // proposal page
                "HypothecationState" => $HypothecationState, // proposal page
                "HypothecationCity" => $HypothecationCity, // proposal page
                "HypothecationPinCode" => $HypothecationPinCode, // proposal page
                "SpecifiedPersonField" => "", // master
                "PAOwnerDriverExclusion" => $PAOwnerDriverExclusion, // addon
                "PAOwnerDriverExReason" => $PAOwnerDriverExReason, //"PA_TYPE2",  // master
                "CPAInsComp" => $cPAInsComp,
                "CPAPolicyFmDt" => $cPAPolicyFmDt,
                "CPAPolicyNo" => $cPAPolicyNo,
                "CPAPolicyToDt" => $cPAPolicyToDt,
                "CPASumInsured" => $cPASumInsured,
                "PCCVVehType" => $veh_category == '3W' ? "" : "Other Taxi", // master data
                "TRANSFEROFOWNER" => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                "VehicleManufactureYear" => $manufacture_year,
                "PHYSICALPOLICY" => '1'
            ],
        ];

        if($rto_code == 'AP-39')
        {
            $inputArray['objPolicyEntryETT']['RTOCity'] = 'Prakasam';
        }

        if ($is_pcv) {
            if ($productData->zero_dep == '0') {
                $imt23yn = ($veh_category == '3W') ? 1 : 0;
            }
            $inputArray['objPolicyEntryETT']['IMT23YN'] = $imt23yn;
        }

        if($is_gcv){
        $inputArray['objPolicyEntryETT']['ProdCode'] = 'MOT-PRD-003';
        $inputArray['objPolicyEntryETT']['Consumables'] = "";
        $inputArray['objPolicyEntryETT']['CoverLampTyreTubeYN'] = "";
        $inputArray['objPolicyEntryETT']['DateOfPurchaseOfVehAsPerInvOrSaleLetter'] = "";
        $inputArray['objPolicyEntryETT']['DE_TARIFFDIS'] = "";
        $inputArray['objPolicyEntryETT']['GCCVVehType'] = "";
        $inputArray['objPolicyEntryETT']['GVW'] =  $mmv['veh_gvw'];
        $inputArray['objPolicyEntryETT']['IndemnityToHirerYN'] = "";
        $inputArray['objPolicyEntryETT']['LimitOwnPremiseYN'] = "";
        $inputArray['objPolicyEntryETT']['NatureOfGoods'] = "";
        $inputArray['objPolicyEntryETT']['NFPPEMP'] = "";
        $inputArray['objPolicyEntryETT']['NoOfClaims'] = "";
        $inputArray['objPolicyEntryETT']['NoOfClaims1'] = "";
        $inputArray['objPolicyEntryETT']['PuccState'] = "";
        $inputArray['objPolicyEntryETT']['PuccNo'] = "";
        $inputArray['objPolicyEntryETT']['Permit'] = "";
        $inputArray['objPolicyEntryETT']['NoOfTrailers'] = "";
        $inputArray['objPolicyEntryETT']['NoOfDCCforPA'] = "";
        $inputArray['objPolicyEntryETT']['NoOfCoolies'] = "";
        $inputArray['objPolicyEntryETT']['ResidentialStatus'] = "";
        $inputArray['objPolicyEntryETT']['PuccYN'] = "";
        $inputArray['objPolicyEntryETT']['SeatingCapacity'] = "";
        $inputArray['objPolicyEntryETT']['SHRIMOTORPROTECTION_YN'] = "";
        $inputArray['objPolicyEntryETT']['SpeedometerReading'] = "";
        $inputArray['objPolicyEntryETT']['SpouseName'] = "";
        $inputArray['objPolicyEntryETT']['TDChassisNo'] = "";
        $inputArray['objPolicyEntryETT']['TDRegNo'] = "";
        $inputArray['objPolicyEntryETT']['TrailerVehicleCode'] = "";
        // $inputArray['objPolicyEntryETT']['TRANSFEROFOWNER'] = "";
        $inputArray['objPolicyEntryETT']['UseofVehisLimitedOwnPremisesYN'] = "";
        $inputArray['objPolicyEntryETT']['Validupto'] = "";
        $inputArray['objPolicyEntryETT']['VehFittedWithFGTankYN'] = "";
        $inputArray['objPolicyEntryETT']['VehFitWithTublessTyresYN'] = "";
        $inputArray['objPolicyEntryETT']['VehicleAge'] = "";
        $inputArray['objPolicyEntryETT']['VehicleManufactureYear'] = $manufacture_year;
        $inputArray['objPolicyEntryETT']['VehParkedDuringNight'] = "";
        $inputArray['objPolicyEntryETT']['AgeOfOwner'] = "";
        $inputArray['objPolicyEntryETT']['AgeOfPaidDriver'] = "";
        $inputArray['objPolicyEntryETT']['Amount'] = "";
        $inputArray['objPolicyEntryETT']['Amount1'] = "";
        $inputArray['objPolicyEntryETT']['BodyType'] = "OPEN WOODEN BODY";
        $inputArray['objPolicyEntryETT']['CancelOrRefuseRenew'] = "";
        $inputArray['objPolicyEntryETT']['CC'] = "";
        $inputArray['objPolicyEntryETT']['ClaimsLodged'] = "";
        $inputArray['objPolicyEntryETT']['MISPDealerCode'] = $MISPDealerCode;
        $inputArray['objPolicyEntryETT']['VoluntaryExcess'] = "";
        $inputArray['objPolicyEntryETT']['VehicleMadeinindiaYN'] = "";
        $inputArray['objPolicyEntryETT']['VehPermit'] = "";
        $inputArray['objPolicyEntryETT']['NilDepreciationCoverYN'] =  $productData->zero_dep == 0 ? 'YES' : '';
        $inputArray['objPolicyEntryETT']['PreviousNilDepreciation'] = $PreviousNilDepreciation;
        $inputArray['objPolicyEntryETT']['SpecifiedPersonField'] = "";
        unset($inputArray['objPolicyEntryETT']['PCCVVehType']);

        if ($productData->zero_dep == '0') {
            $imt23yn = 1;
        }
        $inputArray['objPolicyEntryETT']['IMT23YN'] = $imt23yn;
    }
        
        if ($requestData->fuel_type == 'ELECTRIC') {
            unset($inputArray['objPolicyEntryETT']['EngineNo']);
            $inputArray['objPolicyEntryETT']['MotorNumber'] = $proposal->engine_number;
        }

        if ($requestData->vehicle_owner_type == 'C') {
            $input_array['objPolicyEntryETT']['NomineeNameforPAOwnerDriver'] = "";
            $input_array['objPolicyEntryETT']['NomineeAgeforPAOwnerDriver'] = "";
            $input_array['objPolicyEntryETT']['NomineeRelationforPAOwnerDriver'] = "";
            $input_array['objPolicyEntryETT']['AppointeeNameforPAOwnerDriver'] = "";
            $input_array['objPolicyEntryETT']['AppointeeRelationforPAOwnerDriver'] = "";
        }

        $additional_data = [
            'enquiryId' => customDecrypt($request['userProductJourneyId']),
            'headers' => [
                'Username' => config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'transaction_type' => 'proposal',
        ];

         //b2b seller type headers and application name segregation
        if (!empty($b2b_seller_type) && !empty($b2b_seller_type->seller_type) && config('IS_SEGREGATION_ALLOWED_FOR_IC_CREDENTIALS_FOR_CV') == 'Y') {
           switch($b2b_seller_type->seller_type){
               case 'P':
                   $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_POS');
                   $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_FOR_POS');
               break;
               case 'E':
                   $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_EMPLOYEE');
                   $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_FOR_EMPLOYEE');
               break;
               case 'MISP':
                   $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME_FOR_MISP');
                   $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD_FOR_MISP');
               break;
               default:
                   $additional_data['headers']['Username'] = config('constants.IcConstants.shriram.SHRIRAMGCVPCV_USERNAME');
                   $additional_data['headers']['Password'] = config('constants.IcConstants.shriram.SHRIRAM_PASSWORD');
               break;
           }
        }

        if($is_gcv){
$additional_data['section'] = get_parent_code($productData->product_sub_type_id);
        }
        $vehicleDetails = [
            'manufacture_name' => $ic_version_details->manf,
            'model_name' => $ic_version_details->model_desc,
            'version' => '',
            'fuel_type' => $ic_version_details->fuel,
            'seating_capacity' => $ic_version_details->veh_seat_cap,
            'carrying_capacity' => '',
            'cubic_capacity' => $ic_version_details->veh_cc,
            'gross_vehicle_weight' => '',
            'vehicle_type' => 'GCV/PCV',
        ];

        if(config('constants.IS_CKYC_ENABLED') == 'Y') {

            $userProductJouney=UserProductJourney::where('user_product_journey_id',$enquiryId)->first();
                $inputArray['objPolicyEntryETT']['CKYC_NO']='';
                $inputArray['objPolicyEntryETT']['DOB']=$proposal->dob ? date('d-m-Y',strtotime($proposal->dob)) : '';
                $inputArray['objPolicyEntryETT']['POI_Type']='';
                $inputArray['objPolicyEntryETT']['POI_ID']='';
                $inputArray['objPolicyEntryETT']['POA_Type']='';
                $inputArray['objPolicyEntryETT']['POA_ID']='';
                $inputArray['objPolicyEntryETT']['FatherName']=$proposal->proposer_ckyc_details->related_person_name ?? '';
                $inputArray['objPolicyEntryETT']['POI_DocumentFile']='';
                $inputArray['objPolicyEntryETT']['POA_DocumentFile']='';
                $inputArray['objPolicyEntryETT']['Insured_photo']='';
                $inputArray['objPolicyEntryETT']['POI_DocumentExt']='';
                $inputArray['objPolicyEntryETT']['POA_DocumentExt']='';
                $inputArray['objPolicyEntryETT']['Insured_photoExt']='';

            if ($proposal->ckyc_type == 'ckyc_number') {
                $inputArray['objPolicyEntryETT']['CKYC_NO']=$proposal->ckyc_type_value;
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
                            $inputArray['objPolicyEntryETT']['POI_Type'] = 'PAN';
                            $inputArray['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_panNumber'];

                            // AML tags
                            $inputArray['objPolicyEntryETT']['PANorForm60'] = 'PAN';
                            $inputArray['objPolicyEntryETT']['PanNo'] = $ckycDocumentData['proof_of_identity']['poi_panNumber'];
                            $inputArray['objPolicyEntryETT']['Pan_Form60_Document_Name'] = "1";
                            $inputArray['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $poiExtension;
                            // $inputArray['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poiFile[0]));
                            $inputArray['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($poiFile[0]));
                            break;
                        case 'aadharNumber':
                            $inputArray['objPolicyEntryETT']['POI_Type'] = 'PROOF OF POSSESSION OF AADHAR';
                            $inputArray['objPolicyEntryETT']['POI_ID']= substr($ckycDocumentData['proof_of_identity']['poi_aadharNumber'], -4);
                            break;
                        case 'passportNumber':
                            $inputArray['objPolicyEntryETT']['POI_Type'] = 'PASSPORT';
                            $inputArray['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_passportNumber'];
                            break;
                        case 'drivingLicense':
                            $inputArray['objPolicyEntryETT']['POI_Type'] = 'Driving License';
                            $inputArray['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_drivingLicense'];
                            break;
                        case 'voterId':
                            $inputArray['objPolicyEntryETT']['POI_Type'] = 'VOTER ID';
                            $inputArray['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_voterId'];
                            break;
                        case 'registrationCertificate':
                            $inputArray['objPolicyEntryETT']['POI_Type'] = 'Registration Certificate';
                            $inputArray['objPolicyEntryETT']['POI_ID'] = $ckycDocumentData['proof_of_identity']['poi_registrationCertificate'];
                            break;
                        case 'cretificateOfIncorporaion':
                            $inputArray['objPolicyEntryETT']['POI_Type'] = 'Certificate of Incorporation';
                            $inputArray['objPolicyEntryETT']['POI_ID'] = $ckycDocumentData['proof_of_identity']['poi_certificateOfIncorporation'];
                            break;
                        default:
                            return [
                                'status' => false,
                                'message' => 'Proof of Identity details not found'
                            ];
                    }
                    switch ($poaType) {
                        case 'aadharNumber':
                            $inputArray['objPolicyEntryETT']['POA_Type'] = 'PROOF OF POSSESSION OF AADHAR';
                            $inputArray['objPolicyEntryETT']['POA_ID']= substr($ckycDocumentData['proof_of_address']['poa_aadharNumber'], -4);
                            break;
                        case 'passportNumber':
                            $inputArray['objPolicyEntryETT']['POA_Type'] = 'PASSPORT';
                            $inputArray['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_passportNumber'];
                            break;
                        case 'drivingLicense':
                            $inputArray['objPolicyEntryETT']['POA_Type'] = 'Driving License';
                            $inputArray['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_drivingLicense'];
                            break;
                        case 'voterId':
                            $inputArray['objPolicyEntryETT']['POA_Type'] = 'VOTER ID';
                            $inputArray['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_voterId'];
                            break;
                        case 'registrationCertificate':
                            $inputArray['objPolicyEntryETT']['POA_Type'] = 'Registration Certificate';
                            $inputArray['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_registrationCertificate'];
                            break;
                        case 'cretificateOfIncorporaion':
                            $inputArray['objPolicyEntryETT']['POA_Type'] = 'Certificate of Incorporation';
                            $inputArray['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_certificateOfIncorporation'];
                            break;
                        default:
                            return [
                                'status' => false,
                                'message' => 'Proof of Address details not found'
                            ];
                    }

                    // $inputArray['objPolicyEntryETT']['POI_DocumentFile'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poiFile[0]));
                    // $inputArray['objPolicyEntryETT']['POA_DocumentFile'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poaFile[0]));
                    // $inputArray['objPolicyEntryETT']['Insured_photo'] = base64_encode(\Illuminate\Support\Facades\Storage::get($photoFile[0]));

                    $inputArray['objPolicyEntryETT']['POI_DocumentFile'] = base64_encode(ProposalController::getCkycDocument($poiFile[0]));
                    $inputArray['objPolicyEntryETT']['POA_DocumentFile'] = base64_encode(ProposalController::getCkycDocument($poaFile[0]));
                    if($requestData->vehicle_owner_type == 'I'){
                        $inputArray['objPolicyEntryETT']['Insured_photo'] = base64_encode(ProposalController::getCkycDocument($photoFile[0]));
                    }


                    $inputArray['objPolicyEntryETT']['POI_DocumentExt']=$poiExtension;
                    $inputArray['objPolicyEntryETT']['POA_DocumentExt']=$poaExtension;
                    if($requestData->vehicle_owner_type == 'I'){
                        $inputArray['objPolicyEntryETT']['Insured_photoExt']=$photoExtension;
                    }
                }
            }
        }



        if (config('SHRIRAM_AML_ENABLED') == 'Y') {

            $panFile = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId'] . '/pan_document');
            
            if (!empty($panFile) && !empty($proposal->pan_number)) {
                $panFileExtension = explode('.', $panFile[0]);
                $panFileExtension = '.' . end($panFileExtension);
                $inputArray['objPolicyEntryETT']['PANorForm60'] = 'PAN';
                $inputArray['objPolicyEntryETT']['PanNo'] = $proposal->pan_number;
                $inputArray['objPolicyEntryETT']['Pan_Form60_Document_Name'] = '1';
                $inputArray['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $panFileExtension;
                // $inputArray['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($panFile[0]));
                $inputArray['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($panFile[0]));
            }

            $form60File = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId'] . '/form60');
        
            if (!empty($form60File)) {
                $form60Extension = explode('.', $form60File[0]);
                $form60Extension = '.' . end($form60Extension);
                $inputArray['objPolicyEntryETT']['PANorForm60'] = 'FORM60';
                $inputArray['objPolicyEntryETT']['PanNo'] = '';
                $inputArray['objPolicyEntryETT']['Pan_Form60_Document_Name'] = '1';
                $inputArray['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $form60Extension;
                // $inputArray['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($form60File[0]));
                $inputArray['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($form60File[0]));
            }

            if (!isset($inputArray['objPolicyEntryETT']['PANorForm60'])) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Please upload Pan or Form60 document'
                ]);
            }
        } else {
            unset($inputArray['objPolicyEntryETT']['PANorForm60']);
            unset($inputArray['objPolicyEntryETT']['PanNo']);
            unset($inputArray['objPolicyEntryETT']['Pan_Form60_Document_Name']);
            unset($inputArray['objPolicyEntryETT']['Pan_Form60_Document_Ext']);
            unset($inputArray['objPolicyEntryETT']['Pan_Form60_Document']);
        }
        if($is_pcv){
        $additional_data['url'] = config('constants.IcConstants.shriram.SHRIRAM_PROPOSAL_SUBMIT_URL_JSON');
    }
        if($is_gcv){
            $inputArray['objGCCVProposalEntryETT'] = $inputArray['objPolicyEntryETT'];
            unset($inputArray['objPolicyEntryETT']);
            $additional_data['url'] = config('constants.IcConstants.shriram.SHRIRAM_GCV_PROPOSAL_SUBMIT_URL_JSON');
        }
        $get_response = self::proposalSubmit($inputArray, $proposal, $additional_data, $request);
        // $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_PROPOSAL_SUBMIT_URL_JSON'), $inputArray, $request['companyAlias'], $additional_data);
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
        if($is_gcv){
            $response['GeneratePCCVProposalResult'] = $response['GenerateGCCVProposalResult'];
            unset($response['GenerateGCCVProposalResult']);
        }
        $err_msg = $response['MessageResult']['ErrorMessage'];
        $pattern = "/PRE-INSPECTION SURVEY ID IS MANDATORY/i";
        $breakin_case = preg_match($pattern, $err_msg);
        if (( isset($response['MessageResult']['Result']) && $response['MessageResult']['Result'] == 'Success') || ($get_response['status_code'] == 200 && !empty($response['GeneratePCCVProposalResult']['PROPOSAL_NO']) && $breakin_case == 1)) {

            UserProposal::where('user_product_journey_id', $enquiryId)
            ->update([
                'is_ckyc_verified' => 'Y'
            ]);
            // $is_breakin = '';
            $inspection_id = '';

            // $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();


            $od_premium = 0;
            $tp_premium = 0;
            $ncb_discount = 0;
            $total_premium = 0;
            $final_payable_amount = 0;
            $non_electrical_accessories = $cpa_premium = 0;
            $voluntary_deductible = $other_discount = $tppd_discount = 0;
            $addon_premium = $tp_lpg_kit = $electrical_premium = $nonelectrical_premium = $od_lpg_kit = 0;
            $geoextensionod = $geoextensiontp = 0;
            $minimumOdLoading = $ll_paid_driver = 0;
        
            $addons_available = [
                'Nil Depreciation', 'Nil Depreciation Cover', 'Road Side Assistance', 'Cover for lamps tyres / tubes mudguards bonnet /side parts bumpers headlights and paintwork of damaged portion only (IMT-23)'
            ];
            foreach ($response['GeneratePCCVProposalResult']['CoverDtlList'] as $key => $premium_data) {
                if ($premium_data['CoverDesc'] == 'Basic OD Premium') {
                    $od_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TP Total') {
                    $tp_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR42-Outbuilt CNG\/LPG-Kit-Cover' || $premium_data['CoverDesc'] ==  'GR42-Outbuilt CNG/LPG-Kit-Cover') {
                    if (round($premium_data['Premium']) == 60) {
                        $tp_lpg_kit = round($premium_data['Premium']);
                    }else{
                        $od_lpg_kit = round($premium_data['Premium']);
                    }
                }
                if ($premium_data['CoverDesc'] == 'GR41-Cover For Electrical and Electronic Accessories') {
                    $electrical_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                    $nonelectrical_premium = round($premium_data['Premium']);
                }
                if (in_array($premium_data['CoverDesc'], ['NCB Discount ','NCB Discount'])) {
                    $ncb_discount = round(abs($premium_data['Premium']));
                }
                if ($premium_data['CoverDesc'] == 'Total Premium') {
                    $total_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                    $cpa_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'De-Tariff Discount') {
                    $other_discount = round(abs($premium_data['Premium']));
                }
                if ($premium_data['CoverDesc'] == 'Legal Liability Coverages For Paid Driver - TP')
                {
                    $ll_paid_driver = $premium_data['Premium'];
                }
                if ($premium_data['CoverDesc'] == 'LL paid driver ')
                {
                    $LLtoPaidDriverYN = $premium_data['Premium'];
                }
                if (in_array($premium_data['CoverDesc'], ['LL TO PAID DRIVER', 'LL TO PAID CLEANER', 'LL TO PAID CONDUCTOR', 'Legal Liability Coverages For Conductor'])) {
                    $ll_paid_driver = $ll_paid_driver + $premium_data['Premium'];
                }
                if ($premium_data['CoverDesc'] == 'GR39A-Limit The Third Party Property Damage Cover') {
                    $tppd_discount = round(abs($premium_data['Premium']));
                }
                if ( in_array($premium_data['CoverDesc'], $addons_available) ) {
                    $addon_premium += round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'InBuilt CNG Cover') {
                    if (round($premium_data['Premium']) == 60) {
                        $tp_lpg_kit = round($premium_data['Premium']);
                    }else {
                        $od_lpg_kit = round(abs($premium_data['Premium'])); 
                    }
                }
                if ($premium_data['CoverDesc'] == 'GR4-Geographical Extension')
                {
                    if ($premium_data['Premium'] == 100)
                    {
                        $geoextensiontp = $premium_data['Premium'];
                    }
                    else
                    {
                        $geoextensionod = $premium_data['Premium'];
                    }
                }
                if (in_array($premium_data['CoverDesc'], array('Minimum OD Loading','Minimum OD Loading - OD'))) {
                    $minimumOdLoading = $premium_data['Premium'];
                }
            }
            $total_discount = $ncb_discount + $other_discount + $voluntary_deductible;
            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (string) round(($NonElectricalaccessSI * 3.283 ) / 100);
                $od_premium = ($od_premium - $non_electrical_accessories);
            }
            if($is_pcv){
                $masterProduct = '17';
                $productCode = 'MOT-PRD-005';
            }
            if($is_gcv){
                $masterProduct = '16';
                $productCode = 'MOT-PRD-003';
            }
            if ((in_array($requestData->business_type,['breakin','Breakin','Break-in']) || $is_breakin == 'Y') && $premium_type != 'third_party'){
                //  ShriramPremiumDetailController::saveJsonPremiumDetails($get_response['webservice_id']);
                 $leadArray = [
                     'Userpartyid' => config('constant.SHRIRAM_BREAKIN_USERPARTYID'),
                     'Userip' => $requestData->user_email ,
                     'ROOTORGBY' => config('constant.SHRIRAM_BREAKIN_ROOTORGBY'),
                     'STRIPTORGBY' => config('constant.SHRIRAM_BREAKIN_STRIPTORGBY_BRANCHID'),
                     'ISFORPHONE' => "0",
                     'BRANCHID' => config('constant.SHRIRAM_BREAKIN_STRIPTORGBY_BRANCHID'),
                     'PRODUCTMASTERID' => $masterProduct,
                     'VEHCATEGORY' =>  $vehicleClass,
                     'VEHCATEGORYDESC' => $vehdesc,
                     'REGNUMBERFORMAT' => 'New Format',
                     'VEHICLETYPE' => 'old',
                     'VEHICLEREGNO' => $requestData->vehicle_registration_no,
                     'ENGINENO' => $proposal->engine_number ,
                     'CHASSISNO' => $proposal->chassis_number,
                     'TITLE' => ($proposal->gender == 'M')? "Mr" : "Ms",
                     'FIRST_NAME' => $proposal->first_name,
                     'MIDDLE_NAME' => $proposal->middle_name ?? 'null',
                     'LAST_NAME' => ($proposal->last_name == "") ? "last" :($proposal->last_name),
                     'CONTACTTOSENDLINK' => $requestData->user_mobile,
                     'INTIMATIONPURPOSE' =>  (($nilDepreciationCover == 1 && $PreviousNilDepreciation == 1) || ($nilDepreciationCover == 1 && $PreviousNilDepreciation != 1)) ? "Break-In with Nil Dep." : "Break-In",
                     'TYPEOFENDOR' => 'Previous Policy Expired',
                     'NCB_PERCENTAGE' => "0",
                     'INTIMATIONREMARKS' =>'Self PI',
                     'UserId' => config('constant.SHRIRAM_BREAKIN_USERPARTYID'),
                     'FLAG' => 'SAVE',
                     'PREINSPECTIONID' => ''
                 ];
                 if(!in_array($requestData->business_type,['breakin','Breakin','Break-in']) && $is_breakin == 'Y'){
                     $leadArray['INTIMATIONPURPOSE'] = "Nil Dep.";
                 }
                 $getResponse = getWsData(
                     config('IC.SHRIRAM.V1.CAR.INSPECTION_CREATION_URL'),
                     $leadArray,
                     'shriram',
                     [
                         'section' => $productData->product_sub_type_code,
                         'method' => 'Lead Creation',
                         'requestType' => 'json',
                         'requestMethod' => 'post',
                         'enquiryId' => $enquiryId,
                         'productName' => $productData->product_name,
                         'transaction_type' => 'proposal',
                         'headers' => [
                             'Content-type' => 'application/json',
                             'Accept' => 'application/json'
                         ]
                     ]

                 );
                 $invalidLeadResponse = json_decode($getResponse['response'], true);
                 if (isset($invalidLeadResponse['statusCode']) && $invalidLeadResponse['statusCode'] !== 200) {
                     return [
                         'status' => false,
                         'webservice_id' => $getResponse['webservice_id'],
                         'table' => $getResponse['table'],
                         'message' => $invalidLeadResponse['message'] ?? 'Error in Lead Creation',
                     ];
                 }
                 $leadResponse = $getResponse['response'];
                 if (empty($leadResponse)) {
                     return [
                         'status' => false,
                         'webservice_id' => $getResponse['webservice_id'],
                         'table' => $getResponse['table'],
                         'message' => 'Error in lead creation service'
                     ];
                 }
             $finalleadResponse = json_decode( $leadResponse, true);
                     $inspectionId="";
                     $successMessage = $finalleadResponse['MessageResult']['SuccessMessage'];
                     preg_match("/PI Id (\d+)/",$successMessage, $matches);
                     if(isset($matches[1])){
                     $inspectionId = $matches[1];}
                     else{
                        return [
                        'status' => false,
                        'webservice_id' => $getResponse['webservice_id'],
                        'table' => $getResponse['table'],
                        'message' => 'Breakin Id Already created and wait until approved/rejected/cancelled '.$inspectionId
                        ];
                     }
                     if (!empty($inspectionId)) {
                        DB::table('cv_breakin_status')->insert([
                            'user_proposal_id' => $proposal->user_proposal_id,
                            'ic_id' => $productData->company_id,
                            'breakin_number' => $inspectionId,
                            'breakin_status' => 'Pending From IC',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    DB::table('user_proposal')
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'is_breakin_case' => 'Y',
                        'policy_start_date'     => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                        'policy_end_date'       => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                        'tp_start_date'         => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                        'tp_end_date'           => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                        'proposal_no'           => $response['GeneratePCCVProposalResult']['PROPOSAL_NO'],
                        'unique_proposal_id'    => $proposal->proposal_no,
                        'pol_sys_id'            => $response['GeneratePCCVProposalResult']['POL_SYS_ID'],
                        'od_premium'            => ($od_premium - $total_discount),
                        'tp_premium'            => $tp_premium,
                        'addon_premium'         => $addon_premium,
                        'cpa_premium'           => $cpa_premium,
                        // 'final_premium'         => $final_net_premium,
                        'total_premium'         => $total_premium,
                        'service_tax_amount'    => $final_payable_amount - $total_premium,
                        'final_payable_amount'  => $final_payable_amount,
                        'product_code'          => $productCode,#$mmv_data->vap_prod_code,
                        'ic_vehicle_details'    => json_encode($vehicleDetails),
                        'ncb_discount'          => $ncb_discount,
                        'total_discount'        => abs($ncb_discount) + abs($other_discount) + $tppd_discount,
                        'cpa_ins_comp'          => $cPAInsComp,
                        'cpa_policy_fm_dt'      => str_replace('/', '-', $cPAPolicyFmDt),
                        'cpa_policy_no'         => $cPAPolicyNo,
                        'cpa_policy_to_dt'      => str_replace('/', '-', $cPAPolicyToDt),
                        'cpa_sum_insured'       => $cPASumInsured,
                        'electrical_accessories'    => $ElectricalaccessSI,
                        'non_electrical_accessories'=> $NonElectricalaccessSI,
                        'additional_details_data'  => json_encode($inputArray)
                    ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $enquiryId,
                        'ic_id' => $productData->company_id,
                        'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        'proposal_id' => $proposal->user_proposal_id,
                    ]);
                    //return proposal submitted succesfully with breakin id and is_breakin = Y
                    return [
                        'status' => true,
                        'message' => "Inspection Pending",
                        'webservice_id' => $getResponse['webservice_id'],
                        'table' => $getResponse['table'],
                        'data' => [
                            "kyc_status"=> true,
                            "hidePopup"=> true,
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal->user_product_journey_id,
                            'proposalNo' => $response['GeneratePCCVProposalResult']['PROPOSAL_NO'],
                            'finalPayableAmount' => $final_payable_amount,
                            'is_breakin' => 'Y',
                            'inspection_number' => $inspectionId
                        ]
                    ];
                } else {
                    return [
                        'status' => false,
                        'webservice_id' => $getResponse['webservice_id'],
                        'table' => $getResponse['table'],
                        'message' => 'Error in Break-in Service'
                    ];
                }
            }

            if($minimumOdLoading > 0)
            {
                $od_premium = $od_premium + $minimumOdLoading;     
            }

            $proposal->proposal_no = $response['GeneratePCCVProposalResult']['PROPOSAL_NO'];
            $proposal->pol_sys_id = $response['GeneratePCCVProposalResult']['POL_SYS_ID'];
            $proposal->od_premium       = ($od_premium - $total_discount);
            $proposal->tp_premium       = $tp_premium;
            $proposal->ncb_discount     = abs($ncb_discount);
            $proposal->addon_premium    = $addon_premium + ($od_lpg_kit + $electrical_premium + $non_electrical_accessories + $geoextensionod);
            $proposal->total_premium = $total_premium;
            $proposal->service_tax_amount = $final_payable_amount - $total_premium;
            $proposal->final_payable_amount = $final_payable_amount;
            $proposal->cpa_premium = $cpa_premium;
            $proposal->total_discount = abs($ncb_discount) + abs($other_discount) + $tppd_discount;
            $proposal->ic_vehicle_details = $vehicleDetails;
            $proposal->cpa_ins_comp       = $cPAInsComp;
            $proposal->cpa_policy_fm_dt   = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyFmDt)) : "";
            $proposal->cpa_policy_no      = $cPAPolicyNo;
            $proposal->cpa_policy_to_dt   = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyToDt)) : "";
            $proposal->cpa_sum_insured    = $cPASumInsured;
            $proposal->electrical_accessories = $ElectricalaccessSI;
            $proposal->non_electrical_accessories = $NonElectricalaccessSI;
            $proposal->policy_start_date = date('d-m-Y', strtotime($policy_start_date));
            $proposal->policy_end_date = date('d-m-Y', strtotime($policy_end_date));
            $proposal->tp_start_date = date('d-m-Y', strtotime($policy_start_date));
            $proposal->tp_end_date = date('d-m-Y', strtotime($policy_end_date));
            $proposal->product_code = $productCode;
            // $proposal->final_payable_amount = Arr::last($response['GeneratePCCVProposalResult']['CoverDtlList'])['Premium'];
            $proposal->save();

            $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
            $data['ic_id'] = $master_policy->insurance_company_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);

            if($is_gcv) {
                ShriramPremiumDetailController::saveGcvJsonPremiumDetails($get_response['webservice_id']);
            } else {
                ShriramPremiumDetailController::savePcvJsonPremiumDetails($get_response['webservice_id']);
            }

            return response()->json([
                'status' => true,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['MessageResult']['SuccessMessage'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'proposalNo' => $response['GeneratePCCVProposalResult']['PROPOSAL_NO'],
                    'userProductJourneyId' => $data['user_product_journey_id'],
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => $is_breakin,
                    'inspection_number' => $inspection_id
                ]
            ]);
        } else {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['GeneratePCCVProposalResult']['ERROR_DESC'] ?? $response['MessageResult']['ErrorMessage'],
            ]);
        }

    }
    public static function proposalSubmit($inputArray, $proposal, $additional_data, $request)
    {
        $url = $additional_data['url'];
        unset($additional_data['url']);

        $get_response = getWsData(
            $url,
            $inputArray,
            $request['companyAlias'],
            $additional_data
        );
        $response = $get_response['response'];
        $response = json_decode($response, true);

        if (empty($response) || ($response['MessageResult']['Result'] ?? '') == 'Success') {
            return $get_response;
        }

        $pan_path ='ckyc_photos/' . $request['userProductJourneyId'] . '/pan_document';
        $panFile = \Illuminate\Support\Facades\Storage::allFiles($pan_path);

        if (
            config('IC.SHRIRAM.ENABLE_REHIT_PROPOSAL') != 'Y'
            || empty($proposal->pan_number) ||
            empty($panFile) || $proposal->ckyc_type == 'ckyc_number'
        ) {
            return $get_response;
        }

        $panFile = $panFile[0];

        $poiExtension = explode('.', $panFile);
        $poiExtension = '.' . end($poiExtension);
        // $panFile = \Illuminate\Support\Facades\Storage::get($panFile);
        $panFile = ProposalController::getCkycDocument($panFile);

        $inputArray['objPolicyEntryETT']['POI_Type'] = 'PAN';
        $inputArray['objPolicyEntryETT']['POI_ID'] = $proposal->pan_number;
        $inputArray['objPolicyEntryETT']['POI_DocumentFile'] = base64_encode($panFile);
        $inputArray['objPolicyEntryETT']['POI_DocumentExt'] = $poiExtension;

        return getWsData(
            $url,
            $inputArray,
            $request['companyAlias'],
            $additional_data
        );
        
    }
}