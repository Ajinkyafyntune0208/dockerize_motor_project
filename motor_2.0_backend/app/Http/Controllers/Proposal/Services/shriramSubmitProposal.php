<?php

namespace App\Http\Controllers\Proposal\Services;

// use App\Http\Controllers\Proposal\Services\V1\PCV\shriramSubmitProposal as ShriramSubmitProposalV1PCV;
// use App\Http\Controllers\Proposal\Services\V1\GCV\ShriramSubmitProposal as ShriramSubmitProposalV1GCV;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\ckycUploadDocuments;
use App\Models\ShriramPinCityState;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\SyncPremiumDetail\Services\ShriramPremiumDetailController;
use function App\Http\Controllers\Proposal\Services\shriramgcvsubmit\gcvsubmit;
// use function App\Http\Controllers\Proposal\Services\V1\GCV\submitV1Gcv;
// use function App\Http\Controllers\Proposal\Services\V1\PCV\submitV1Pcv;
use function Composer\Autoload\includeFile;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;


include_once app_path() . '/Http/Controllers/Proposal/Services/shriramgcvsubmit.php' ;
// include_once app_path() . '/Http/Controllers/Proposal/Services/V1/GCV/ShriramSubmitProposal.php' ;
// include_once app_path() . '/Http/Controllers/Proposal/Services/V1/PCV/shriramSubmitProposal.php' ;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class shriramSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        if (policyProductType($request['policyId'])->parent_id == 4  &&  config('constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE') == 'JSON') 
        {
            return gcvsubmit($proposal, $request);
        } 
        else if(policyProductType($request['policyId'])->parent_id == 4)
        {
            return self::gcvSubmit($proposal, $request);
        }
        else 
        {
            if (config('constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE') == 'XML') 
            {
                return  self::xmlPcvSubmit($proposal, $request);
            }
            else
            {
                return  self::jsonPcvSubmit($proposal, $request);
            }
        }
    }

    public static function jsonPcvSubmit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $requestData = getQuotation($enquiryId);

        UserProposal::where('user_product_journey_id', $enquiryId)
        ->update([
            'is_ckyc_verified' => 'N'
        ]);

        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-M-Y');
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }else if($requestData->business_type == 'breakin')
        {
            $policy_start_date = today()->addDay(1)->format('d-m-Y');
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
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
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

        // new business
        if ($requestData->business_type == 'newbusiness') {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $policy_start_date = today()->format('d-M-Y');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d-M-Y');
            $proposalType = "Fresh";
            $previous_ncb = "";
            $PreviousPolicyType = "";
            $PreviousNilDepreciation = ''; // addon

        } else {
            $PreviousPolicyFromDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($proposal->prev_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
            $PreviousPolicyToDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($proposal->prev_policy_expiry_date)->format('d-M-Y');
            $proposalType = ($requestData->previous_policy_type == 'Not sure') ? "RENEWAL.WO.PRV INS DTL" : "RENEWAL OF OTHERS";
            $PreviousPolicyType = "MOT-PLT-001";
            $previous_ncb = $requestData->previous_ncb;//$quote_log->quote_details['previous_ncb'];
            $PreviousNilDepreciation = '0';
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

        $LLtoPaidDriverYN = '0';
        $countries = [];
        foreach($additional_covers as $key => $value) {
            if (in_array('LL paid driver', $value)) {
                $LLtoPaidDriverYN = '1';
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
            $cPAInsComp = $prev_policy_details->cPAInsComp;
            $cPAPolicyNo = $prev_policy_details->cPAPolicyNo;
            $cPASumInsured = $prev_policy_details->cPASumInsured;
            $cPAPolicyFmDt = Carbon::parse($prev_policy_details->cPAPolicyFmDt)->format('d-M-Y');
            $cPAPolicyToDt = Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d-M-Y');
        }
        // CPA
        // Policy Type

        if ($master_policy->premium_type_id == 1) {
            $quote_log->idv = $quote_log->idv;
            $policy_type = 'MOT-PLT-001';
        } else {
            $quote_log->idv = '';
            $policy_type = 'MOT-PLT-002';
        }

        // Policy Type

        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number[0] = '';
        }

        //Hypothecation
        $HypothecationType = $HypothecationBankName = $HypothecationAddress1 = $HypothecationAddress2 = $HypothecationAddress3 = $HypothecationAgreementNo = $HypothecationCountry = $HypothecationState = $HypothecationCity = $HypothecationPinCode = '';
        $vehicleDetails = $additional_details->vehicle ?? null;
        
        if ($vehicleDetails->isVehicleFinance  ?? null == true) {
            $HypothecationType = $vehicleDetails->financerAgreementType ?? '';
            $HypothecationBankName = $vehicleDetails->nameOfFinancer ?? '';
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
        $posAgentName = $posAgentPanNo = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $posAgentName = $pos_data->agent_name;
                $posAgentPanNo = $pos_data->pan_no;
            }
        }
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 60,
            'address_2_limit'   => 20,         
            'address_3_limit'   => 20,         
        ];
        $getAddress = getAddress($address_data);

        $vehicleClass = '';
        $veh_category = null;
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
        } 
        else if ($veh_category == '4W' && $ic_version_details->veh_seat_cap <= 6) 
        {
            $vehicleClass = "CLASS_4C1A"; //PCCV-4 wheelers - carrying passengers-capacity NOT > 6
        } 
        else if ($veh_category == '3W'&& $ic_version_details->veh_seat_cap > 6 && $ic_version_details->veh_seat_cap <= 17) 
        {
            $vehicleClass = "CLASS_4C3";  //PCCV-3 wheeled vehicles-carrying passengers > 6 but NOT >17
        } 
        else if (($veh_category == '4W' && $ic_version_details->veh_seat_cap > 6) || ($veh_category == '3W' && $ic_version_details->veh_seat_cap > 17)) 
        {
            $vehicleClass = "CLASS_4C2";  // PCCV-4 (or more) wheeled vehicles-capacity > 6 and 3 wheelers-carrying passengers -capacity > 17
        }

        $inputArray = [
            "objPolicyEntryETT" => [
                "ReferenceNo" => "",
                "ProdCode" => 'MOT-PRD-005', // For PCCV
                "PolicyFromDt" => $policy_start_date,
                "PolicyToDt" => $policy_end_date,
                "PolicyIssueDt" => today()->format('d-M-y'),
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
                "NoOfCleaner" => "",
                "NoOfDriver" => "1",
                "NoOfConductor" => "",
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
                "BreakIn" => "No", // prev insu expiry date and today date
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
                "PAPaidDriverCount" => "1", // addon
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
                "VehicleManufactureYear" => $manufacture_year,
            ],
        ];

        if($rto_code == 'AP-39')
        {
            $inputArray['objPolicyEntryETT']['RTOCity'] = 'Prakasam';
        }

        if ($requestData->fuel_type == 'ELECTRIC') {
            unset($inputArray['objPolicyEntryETT']['EngineNo']);
            $inputArray['objPolicyEntryETT']['MotorNumber'] = $proposal->engine_number;
        }

        $additional_data = [
            'enquiryId' => customDecrypt($request['userProductJourneyId']),
            'headers' => [
                'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Taxi',
            'method' => 'Proposal Submit',
            'transaction_type' => 'proposal',
        ];
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

        $additional_data['url'] = config('constants.IcConstants.shriram.SHRIRAM_PROPOSAL_SUBMIT_URL_JSON');
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
        if ($response['MessageResult']['Result'] == 'Success') {

            UserProposal::where('user_product_journey_id', $enquiryId)
            ->update([
                'is_ckyc_verified' => 'Y'
            ]);
            $is_breakin = '';
            $inspection_id = '';

            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

            if (($premium_type == 'breakin' || ($requestData->previous_policy_type == 'Third-party' && ! in_array($premium_type, ['third_party', 'third_party_breakin']))) && ($proposal->is_inspection_done == 'N' || $proposal->is_inspection_done == NULL))
            {
                $is_breakin = 'Y';

                // dd($ic_version_details);
                $product_category = '';

                switch ($productData->product_sub_type_code)
                {
                    case 'TAXI':
                        if ($ic_version_details->veh_seat_cap <= 6)
                        {
                            $product_category = 'PCCV-4 wheelers - carrying passengers-capacity NOT > 6';
                        }
                        else
                        {
                            $product_category = 'PCCV-4 (more) wheeled vehicles-capacity > 6 and 3 wheelers-carrying passengers-capacity > 17';
                        }
                        break;

                    case 'AUTO-RICKSHAW':
                    case 'ELECTRIC-RICKSHAW':
                        if ($ic_version_details->veh_seat_cap <= 6)
                        {
                            $product_category = 'PCCV-3 wheelers-carrying passengers-capacity NOT > 6';
                        }
                        elseif ($ic_version_details->veh_seat_cap > 17)
                        {
                            $product_category = 'PCCV-4 (more) wheeled vehicles-capacity > 6 and 3 wheelers-carrying passengers-capacity > 17';
                        }
                        else
                        {
                            $product_category = 'PCCV-3 wheeled vehicles-carrying passengers > 6 but NOT >17';
                        }
                        break;
                }

                $pre_inspection_request = [
                    'BranchPartyId' => config('constants.IcConstants.shriram.SHRIRAM_BREAKIN_BRANCH_PARTY_ID'),
                    'SurveyorPartyId' => config('constants.IcConstants.shriram.SHRIRAM_BREAKIN_SURVEYOR_PARTY_ID'),
                    'ProductType' => 'PCCV',
                    'ProductCategory' => $product_category,
                    'VehicleType' => 'OLD',
                    'RegistrationFormat' => 'New Format',
                    'RegistrationNo' => $proposal->vehicale_registration_number,
                    'EngineNo' => $proposal->engine_number,
                    'ChassisNo' => $proposal->chassis_number,
                    'Make' => $ic_version_details->manf,
                    'Model' => $ic_version_details->model_desc,
                    'YearOfManufacturing' => explode('-', $requestData->manufacture_year)[1],
                    'InspectionLocation' => $proposal->address_line1 . ' ' . ($proposal->address_line2 ?? '') . ($proposal->address_line3 ?? '') . ', ' . $proposal->city . ', ' . $proposal->state,
                    'ContactPerson' => $proposal->first_name . ($proposal->last_name ? ' ' . $proposal->last_name : ''),
                    'ContactMobileNo' => $proposal->mobile_number,
                    'InsuredName' => $proposal->first_name . ' ' . $proposal->last_name,
                    'PIPurpose' => 'Break-In',
                    'EndorsementType' => '',
                    'PolicyNo' => '',
                    'NCBPercentage' => $requestData->applicable_ncb,
                    'ContactNoforSMS' => $proposal->mobile_number,
                    'IntimationRemarks' => 'Break-in',
                    'UserPartyId' => config('constants.IcConstants.shriram.SHRIRAM_BREAKIN_USER_PARTY_ID'),
                    'SourceFrom' => config('constants.IcConstants.shriram.SHRIRAM_BREAKIN_SOURCE_FROM'),
                    'LoginId' => config('constants.IcConstants.shriram.SHRIRAM_BREAKIN_LOGIN_ID'),
                    'IDVOFVEHICLE' => $proposal->idv,
                    'ProposalType' => $requestData->previous_policy_type == 'Not sure' ? 'Market Renewal without previous insurance' : 'Market Renewal',
                    'SGICPolicyNumber' => '',
                    'ENGINEPROTECTORCOVER' => '',
                    'CONTACTNO_TO_SENDLINK' => '',
                    'GVW' => '',
                    'SEATINGCAPACITY' => '',
                    'RequestPIFILESUPLOADObj' => [],
                ];

                $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_BREAKIN_ID_GENERATION_URL'), $pre_inspection_request, $request['companyAlias'], [
                    'enquiryId' => customDecrypt($request['userProductJourneyId']),
                    'headers' => [
                        'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                        'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'requestMethod' => 'post',
                    'requestType' => 'json',
                    'section' => 'Taxi',
                    'method' => 'Breakin ID Generation',
                    'transaction_type' => 'proposal',
                ]);
                $pre_inspection_response = $get_response['response'];

                if ($pre_inspection_response)
                {
                    $pre_inspection_response = json_decode($pre_inspection_response, TRUE);

                    if (isset($pre_inspection_response['MessageResult']['Result']) && $pre_inspection_response['MessageResult']['Result'] != 'Failure') {
                        $inspection_id = $pre_inspection_response['PreInspectionId'];

                        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'customer_id' => $inspection_id
                            ]);
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $pre_inspection_response['MessageResult']['ErrorMessage'] ?? 'An error in breakin id generation service'
                        ];
                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'An error in breakin id generation service'
                    ];
                }

                $wimwisure = new WimwisureBreakinController();

                $payload = [
                    'user_name' => $proposal->first_name . ($proposal->last_name ? ' ' . $proposal->last_name : ''),
                    'user_email' => $proposal->email,
                    'reg_number' => $proposal->vehicale_registration_number,
                    'mobile_number' => $proposal->mobile_number,
                    'fuel_type' => strtolower($ic_version_details->fuel),
                    'enquiry_id' => $enquiryId,
                    'inspection_number' => $inspection_id,
                    'section' => 'cv',
                    'chassis_number' => $proposal->chassis_number,
                    'engine_number' => $proposal->engine_number,
                    'api_key' => config('constants.wimwisure.API_KEY_SHRIRAM')
                ];

                $breakin_data = $wimwisure->WimwisureBreakinIdGen($payload);

                if ($breakin_data)
                {
                    if ($breakin_data->original['status'] == true && ! is_null($breakin_data->original['data']))
                    {
                        $proposal->is_breakin_case = 'Y';
                        $proposal->save();

                        DB::table('cv_breakin_status')->insert([
                            'user_proposal_id' => $proposal->user_proposal_id,
                            'ic_id' => $productData->company_id,
                            'breakin_number' => $inspection_id,
                            'wimwisure_case_number' => $breakin_data->original['data']['ID'],
                            'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                            'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                            'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
            
                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $breakin_data->original['data']['message'] ?? 'An error occurred while sending data to wimwisure'
                        ];
                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Error in wimwisure breakin service'
                    ];
                }
            }

            $od_premium = 0;
            $tp_premium = 0;
            $ncb_discount = 0;
            $total_premium = 0;
            $final_payable_amount = 0;
            $non_electrical_accessories = $cpa_premium = 0;
            $voluntary_deductible = $other_discount = $tppd_discount = 0;
            $addon_premium = $tp_lpg_kit = $electrical_premium = $nonelectrical_premium = $od_lpg_kit = 0;
            $geoextensionod = $geoextensiontp = 0;
            $addons_available = [
                'Nil Depreciation', 'Nil Depreciation Cover', 'Road Side Assistance'
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
            }
            $total_discount = $ncb_discount + $other_discount + $voluntary_deductible;
            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (string) round(($NonElectricalaccessSI * 3.283 ) / 100);
                $od_premium = ($od_premium - $non_electrical_accessories);
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
            // $proposal->final_payable_amount = Arr::last($response['GeneratePCCVProposalResult']['CoverDtlList'])['Premium'];
            $proposal->save();

            $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
            $data['ic_id'] = $master_policy->insurance_company_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);

            ShriramPremiumDetailController::savePcvJsonPremiumDetails($get_response['webservice_id']);

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

    public static function xmlPcvSubmit($proposal, $request)
    {
        $enquiryId      = customDecrypt($request['enquiryId']);
        $quote_log      = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_data     = json_decode($quote_log->quote_data, true);
        $requestData    = getQuotation($enquiryId);

        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-m-Y');
        } elseif ($requestData->business_type == 'breakin') {
            $policy_start_date = date('d-m-Y', strtotime('+2 day', time()));
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');

        $master_policy = MasterPolicy::find($request['policyId']);

        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        if ($proposal->prev_policy_expiry_date == 'New') {
            $break_in = 'NO';
        }else{
            $break_in = (Carbon::parse($proposal->prev_policy_expiry_date)->diffInDays($policy_start_date) > 0) ? 'YES' : 'NO';
        }
        $selected_addons = SelectedAddons::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        $roadsideassistance = '0';
        $ElectricalaccessSI = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
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
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];

        // new business
        if ($requestData->business_type == 'newbusiness') 
        {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $proposalType = "Fresh";
            $previous_ncb = "";
            $PreviousPolicyType = "";
            $PreviousNilDepreciation = ''; // addon
        } 
        else 
        {
            $PreviousPolicyFromDt = Carbon::parse($proposal->prev_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');
            $PreviousPolicyToDt = Carbon::parse($proposal->prev_policy_expiry_date)->format('d/m/Y');
            $proposalType = "RENEWAL OF OTHERS";
            $PreviousPolicyType = "MOT-PLT-001";
            $previous_ncb = $requestData->previous_ncb;
            $PreviousNilDepreciation = 0;
        }

        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value)) {
                $nilDepreciationCover = 1;
                $PreviousNilDepreciation = 1;
            }

            if (in_array('Road Side Assistance', $value)) {
                $roadsideassistance = "1";
            }
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
        }
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }
        }

        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = 1;
                $ElectricalaccessSI = $value['sumInsured'];
                if ($ElectricalaccessSI > 20000) {
                    return [
                        'status' => false,
                        'message' => 'Electrical sumInsured should not be greater than 20,000',
                    ];
                }
            } elseif (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = 1;
                $NonElectricalaccessSI = $value['sumInsured'];
                if ($accessories['sumInsured'] < 15000) {
                    return [
                        'status' => false,
                        'message' => 'External Bi-Fuel Kit CNG/LPG value should be more than 15000.',
                        'request' => [
                            'requestData' => $requestData,
                            'accessories' => $accessories,
                            'message' => 'External Bi-Fuel Kit CNG/LPG value should be more than 15000.',
                        ]
                    ];
                } elseif ($externalCNGKITSI > 30000) {
                    return [
                        'status' => false,
                        'message' => 'CNG/LPG sumInsured should not be greater than 30,000',
                    ];
                }
            } elseif (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = 1;
                $externalCNGKITSI = $value['sumInsured'];
            }

            if (in_array('PA To Unnamed Passenger', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
        }
        
        // insured prefix 
        $insured_prefix = '1';
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
                $insured_prefix = '1';
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $insured_prefix = '4';
                } else {
                    $insured_prefix = '2';
                }
            }
        }
        else{
            $insured_prefix = '3';
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
        $additional_details = json_decode($proposal->additional_details);
        $prev_policy_details = $additional_details->prepolicy ?? '';
        
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if ( !($PAOwnerDriverExclusion == '0' || $excludeCPA) && !empty($prev_policy_details)) {
            $cPAInsComp = $prev_policy_details->cPAInsComp;
            $cPAPolicyNo = $prev_policy_details->cPAPolicyNo;
            $cPASumInsured = $prev_policy_details->cPASumInsured;
            $cPAPolicyFmDt = Carbon::parse($prev_policy_details->cPAPolicyFmDt)->format('d-m-Y');
            $cPAPolicyToDt = Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d-m-Y');
        }
        // CPA
        // Policy Type

        if ($master_policy->premium_type_id == 1) 
        {
            $quote_log->idv = $quote_log->idv;
            $policy_type = 'MOT-PLT-001';
        } 
        else 
        {
            $quote_log->idv = '';
            $policy_type = 'MOT-PLT-002';
        }
        
        // Policy Type

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

        if (isset($quote_data['rto_code'])) {
            $rto_code = $quote_data['rto_code'];
        } else {
            $rto_code =  $vehicale_registration_number[0] ?? '' . '-' . $vehicale_registration_number[1] ?? '';
        }
        if (strtolower($vehicale_registration_number[0]) == 'new') {
            $vehicale_registration_number = explode('-', $rto_code);
        }
        // state_code
        $state_code = ShriramPinCityState::where('pin_code', $proposal->pincode)->first()->state;
        // state_code

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posAgentName = $posAgentPanNo = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $posAgentName = $pos_data->agent_name;
                $posAgentPanNo = $pos_data->pan_no;
            }
        }
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 60,
            'address_2_limit'   => 20,         
            'address_3_limit'   => 20,         
        ];

        $getAddress = getAddress($address_data);
        //XML 
        $input_array = [
            "soap:Header" => [
                "AuthHeader" => [
                    "Username" => config('constants.cv.shriram.SHRIRAM_XML_AUTH_USERNAME_PCV'),
                    "Password" => config('constants.cv.shriram.SHRIRAM_XML_AUTH_PASSWORD_PCV'),
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                ]
            ],
            "soap:Body" => [
                "GeneratePCCVProposal" => [
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                    'objPolicyEntryETT' => [
                        "ReferenceNo" => "",
                        "ProdCode" => $productData->product_sub_type_code == 'TAXI' ? 'MOT-PRD-005' : $ic_version_details->vap_prod_code, // kit pref
                        "PolicyFromDt" => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                        "PolicyToDt" => Carbon::createFromFormat('d-m-Y', $policy_end_date)->format('d/m/Y'),
                        "PolicyIssueDt" => today()->format('d/m/Y'),
                        "InsuredPrefix" => $insured_prefix,
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
                        "DateOfBirth" => ($requestData->vehicle_owner_type == 'C') ? '' : Carbon::createFromFormat('d-m-Y', $proposal->dob)->format('d/m/Y'),
                        "MobileNo" => $proposal->mobile_number,
                        "FaxNo" => "",
                        "EmailID" => $proposal->email,
                        "POSAgentName" => $posAgentName,
                        "POSAgentPanNo" => $posAgentPanNo,
                        "CoverNoteNo" => "",
                        "CoverNoteDt" => "",
                        "VehicleCategory" => "CLASS_4C1A", //$ic_version_details->veh_category,
                        "VehicleCode" => $ic_version_details->veh_code, // kit
                        "FirstRegDt" => date('d/m/Y', strtotime($requestData->vehicle_register_date)), //  car regi date
                        "VehicleType" => $proposalType == "Fresh" ? "W" : "U", // kit
                        "EngineNo" => $proposal->engine_number,
                        "ChassisNo" => $proposal->chassis_number,
                        "RegNo1" => $vehicale_registration_number[0] ?? '',
                        "RegNo2" => $vehicale_registration_number[1] ?? '',
                        "RegNo3" => $vehicale_registration_number[2] ?? '',
                        "RegNo4" => $vehicale_registration_number[3] ?? '',
                        "RTOCode" => RtoCodeWithOrWithoutZero(implode('-', [$vehicale_registration_number[0], $vehicale_registration_number[1]]), true),
                        "IDV_of_Vehicle" => $quote_log->idv, // quote data
                        "Colour"        => $proposal->vehicle_color,
                        "VoluntaryExcess" => "0", // quote
                        "NoEmpCoverLL"  => "0",
                        "NoOfCleaner"   => "",
                        "NoOfDriver"    => $LLtoPaidDriverYN == 0 ? '0' : '1',
                        "NoOfConductor" => "",
                        "VehicleMadeinindiaYN"  => "",
                        "VehiclePurposeYN"      => "",
                        "NFPP_Employees"        => "",
                        "NFPP_OthThanEmp"       => "",
                        "LimitOwnPremiseYN"     => "",
                        "Bangladesh"    => "0",
                        "Bhutan"        => "0",
                        "Srilanka"      => "0",
                        "Nepal"         => "0",
                        "Pakistan"      => "0",
                        "Maldives"      => "0",
                        "CNGKitYN"      => $externalCNGKIT, // input page
                        "CNGKitSI"      => $externalCNGKITSI, //input
                        "InBuiltCNGKit" => $requestData->fuel_type == 'CNG' ? 1 : 0, // maSTER and  fuel type
                        // "LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                        "DeTariff" => 0,//$master_policy->default_discount,
                        "IMT23YN" => "", // for GCV
                        "BreakIn" => "NO", // prev insu expiry date and today date
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
                        "PAPaidDriverCount" => "1", // addon
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
                        "PreviousPolicyClaimYN"     => $requestData->is_claim == 'Y' ? '1' : '0', // input
                        "PreviousPolicyUWYear"      => "",
                        "PreviousPolicyNCBPerc"     => (int) $previous_ncb, // prev ncb %
                        "PreviousPolicyType"        => $PreviousPolicyType, // master
                        "NilDepreciationCoverYN"    => ($nilDepreciationCover == 1) ? '1' : '0', // addon zero deprecian
                        "PreviousNilDepreciation"   => $PreviousNilDepreciation, // addon
                        "RSACover"                  => $roadsideassistance, // addon
                        "HypothecationType"         => $HypothecationType, // proposal page
                        "HypothecationBankName"     => $HypothecationBankName, // proposal page
                        "HypothecationAddress1"     => $HypothecationAddress1, // proposal page
                        "HypothecationAddress2"     => $HypothecationAddress2, // proposal page
                        "HypothecationAddress3"     => $HypothecationAddress3, // proposal page
                        "HypothecationAgreementNo"  => $HypothecationAgreementNo, // proposal page
                        "HypothecationCountry"      => $HypothecationCountry, // proposal page
                        "HypothecationState"        => $HypothecationState, // proposal page
                        "HypothecationCity"         => $HypothecationCity, // proposal page
                        "HypothecationPinCode"      => $HypothecationPinCode, // proposal page
                        "SpecifiedPersonField"      => "", // master
                        "PAOwnerDriverExclusion"    => $PAOwnerDriverExclusion, // addon
                        "PAOwnerDriverExReason" => $PAOwnerDriverExReason, //"PA_TYPE2",  // master
                        "CPAInsComp"            => $cPAInsComp,
                        "CPAPolicyFmDt"         => $cPAPolicyFmDt,
                        "CPAPolicyNo"           => $cPAPolicyNo,
                        "CPAPolicyToDt"         => $cPAPolicyToDt,
                        "CPASumInsured"         => $cPASumInsured,
                        "PCCVVehType"           => "Other Taxi", // master data
                        "VehicleManufactureYear" => $manufacture_year,
                    ],
                ],
            ],
        ];

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'SOAPAction' => 'http://tempuri.org/GeneratePCCVProposal',
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Taxi',
            'method' => 'Proposal Submit',
            'transaction_type' => 'proposal',
        ];
        $root = [
            'rootElementName' => 'soap:Envelope',
            '_attributes' => [
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
            ]
        ];
        
        $input_array = ArrayToXml::convert($input_array, $root, false, 'utf-8');
        $get_response = getWsData(config('constants.cv.shriram.SHRIRAM_XML_PROPOSAL_SUBMIT_URL'), $input_array, 'shriram', $additional_data);
        $response = $get_response['response'];
        $response = XmlToArray::convert($response);

        if (!isset($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE']) || $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE'] != '0') {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_DESC'] ?? 'Insurer Not Reachable',
            ]);
        }
        if ($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE'] == '0') {
            $result = $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult'];

            $od_premium = 0;
            $tp_premium = 0;
            $ncb_discount = 0;
            $total_premium = 0;
            $final_payable_amount = 0;
            $non_electrical_accessories = $cpa_premium = 0;
            $voluntary_deductible = $other_discount = 0;
            $addon_premium = $tp_lpg_kit = $electrical_premium = $od_lpg_kit = $tppd_discount = 0;
            $addons_available = [
                'ROAD SIDE ASSISTANCE', 'Nil Depreciation Cover'
            ];
            foreach ($result['CoverDtlList']['CoverDtl'] as $key => $premium_data) {
                if ($premium_data['CoverDesc'] == 'BASIC OD COVER') {
                    $od_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TP TOTAL') {
                    $tp_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR42--CNG KIT - TP  COVER') {
                        $tp_lpg_kit = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR42--CNG-KIT-COVER') {
                    $od_lpg_kit = round($premium_data['Premium']);
                }
                
                if ($premium_data['CoverDesc'] == 'GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES') {
                    $electrical_premium = round($premium_data['Premium']);
                }
                // Non-Electrical is included in Basic OD
                /*if ($premium_data['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                    $nonelectrical_premium = round($premium_data['Premium']);
                }*/
                if ($premium_data['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                    $ncb_discount = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TOTAL PREMIUM') {
                    $total_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                    $cpa_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                    $other_discount = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TPPD COVER') {
                    $tppd_discount = round($premium_data['Premium']);
                }
                if ( in_array($premium_data['CoverDesc'], $addons_available) ) {
                    $addon_premium += round($premium_data['Premium']);
                }
            }
            $total_discount = $ncb_discount + $other_discount + $voluntary_deductible;
            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (string) round(($NonElectricalaccessSI * 3.283 ) / 100);
                $od_premium = ($od_premium - $non_electrical_accessories);
            }

            $proposal->proposal_no      = $result['PROPOSAL_NO'];
            $proposal->pol_sys_id       = $result['POL_SYS_ID'];
            $proposal->od_premium       = ($od_premium - $total_discount);
            $proposal->tp_premium       = $tp_premium;
            $proposal->ncb_discount     = abs($ncb_discount);
            $proposal->addon_premium    = $addon_premium + ($od_lpg_kit + $electrical_premium + $non_electrical_accessories);
            $proposal->total_premium    = $total_premium;
            $proposal->service_tax_amount = $final_payable_amount - $total_premium;
            $proposal->final_payable_amount = $final_payable_amount;
            $proposal->cpa_premium      = $cpa_premium;
            $proposal->total_discount   = abs($ncb_discount) + abs($other_discount) + $tppd_discount;
            $proposal->ic_vehicle_details = $vehicleDetails;
            $proposal->cpa_ins_comp     = $cPAInsComp;
            $proposal->cpa_policy_fm_dt = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyFmDt)) : "";
            $proposal->cpa_policy_no    = $cPAPolicyNo;
            $proposal->cpa_policy_to_dt = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyToDt)) : "";
            $proposal->cpa_sum_insured  = $cPASumInsured;
            $proposal->electrical_accessories       = $ElectricalaccessSI;
            $proposal->non_electrical_accessories   = $NonElectricalaccessSI;
            $proposal->policy_start_date = date('d-m-Y', strtotime($policy_start_date));
            $proposal->policy_end_date  = date('d-m-Y', strtotime($policy_end_date));
            $proposal->tp_start_date = date('d-m-Y', strtotime($policy_start_date));
            $proposal->tp_end_date  = date('d-m-Y', strtotime($policy_end_date));
            // $proposal->final_payable_amount = Arr::last($response['GeneratePCCVProposalResult']['CoverDtlList'])['Premium'];
            $proposal->save();

            // CKYC verification
            $final_response = [
                'status' => false,
                'msg' => $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_DESC'],
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

            ShriramPremiumDetailController::savePcvXmlPremiumDetails($get_response['webservice_id']);

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $response = $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult'];
                $policy_approval = self::policyApprovalXml($proposal, $request, $response, config('constants.cv.shriram.SHRIRAM_XML_PROPOSAL_SUBMIT_URL'));

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
                'msg' => $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_DESC'],
            ]);
        }
    }

    public static function gcvSubmit($proposal, $request)
    {
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $productData = getProductDataByIc($request['policyId']);
        $requestData = getQuotation($enquiryId);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'shriram');
        if($mmv['status'] == 1)
        {
            $ic_version_details = (object) $mmv['data'];
        }
        else
        {
            return  [   
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];          
        }
        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-m-Y');
        } elseif ($requestData->business_type == 'breakin') {
            $policy_start_date = date('d-m-Y', strtotime('+2 day', time()));
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        // Policy Type

        if ($master_policy->premium_type_id == 1) {
            $quote_log->idv = $quote_log->idv;
            $policy_type = 'MOT-PLT-001';
            $tp_only = 'false';
        } else {
            $quote_log->idv = '';
            $policy_type = 'MOT-PLT-002';
            $tp_only = 'true';
        }

        // Policy Type
        $policy_end_date = date('d-m-Y', strtotime($policy_start_date. ' +1 Year -1 Day'));
        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $break_in = (Carbon::parse($proposal->prev_policy_expiry_date)->diffInDays($policy_start_date) > 0) ? 'YES' : 'NO';
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $PAforUnnamedPassengerSI  = $antitheft = $roadsideassistance = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $externalCNGKIT = $externalCNGKITSI = 0;
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
        // new business
        if ($requestData->business_type == 'newbusiness') {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $proposalType = "Fresh";
            $previous_ncb = "";
            $PreviousPolicyType = "";
            $car_age = 0;
        } else {
            $PreviousPolicyFromDt = Carbon::parse($proposal->prev_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
            $PreviousPolicyToDt = Carbon::parse($proposal->prev_policy_expiry_date)->format('d-M-Y');
            $proposalType = "RENEWAL";
            $PreviousPolicyType = "MOT-PLT-001";
            $previous_ncb = (int) $requestData->previous_ncb;
            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $car_age = ceil($age / 12);
        }
        $imt23 = $NilDepreciationCoverYN = $PreviousNilDepreciation = 0;
        foreach ($addons as $key => $value) {
            if (in_array('Road Side Assistance', $value)) {
                $roadsideassistance = "1";
            }
            if (in_array('IMT - 23', $value)) {
                $imt23 = "1";
            }
        }
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
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

            if (in_array('PA To Unnamed Passenger', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
        }
        $LLtoPaidDriverYN = $NoOfTrailers = $PAPaidDriverConductorCleanerSI = $PAPaidDriverConductorCleanerYN = $NoOfCleaner = $NoOfConductor = $NoOfDriver = 0;
        foreach ($additional_covers as $key => $value) {
            if ('LL paid driver/conductor/cleaner' == $value['name']) {
                $LLtoPaidDriverYN = '1';
                $NoOfDriver = isset($value['LLNumberDriver']) ? ($value['LLNumberDriver'] > 3 ? 3 : $value['LLNumberDriver']) : 0;
                $NoOfConductor = isset($value['LLNumberConductor']) ? ($value['LLNumberConductor'] > 3 ? 3 : $value['LLNumberConductor']) : 0;
                $NoOfCleaner = isset($value['LLNumberCleaner']) ? ($value['LLNumberCleaner'] > 3 ? 3 : $value['LLNumberCleaner']) : 0;
            }
            if ('PA paid driver/conductor/cleaner' == $value['name']) {
                $PAPaidDriverConductorCleanerYN = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
            // if ('Trailer' == $value['name']) {
            //     $NoOfTrailers = $value['sumInsured'];
            // }
        }
        // insured prefix 
        if($corporate_vehicles_quotes_request->vehicle_owner_type == 'I'){
            // If gender is not M nor F, then return with a msg. If passed another value, then the proposal API will fail.
            if(!in_array($proposal->gender, ['M', 'F'])){
                return  [   
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Please select the Gender.'
                ]; 
            }
            if ($proposal->gender == "M") {
                $insured_prefix = '1';
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $insured_prefix = '4';
                } else {
                    $insured_prefix = '2';
                }
            }
        }
        else{
            $insured_prefix = '3';
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
        $additional_details = json_decode($proposal->additional_details);
        $prev_policy_details = $additional_details->prepolicy ?? '';
        
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if ( !($PAOwnerDriverExclusion == '0' || $excludeCPA) && !empty($prev_policy_details)) {
            $cPAInsComp = $prev_policy_details->cPAInsComp;
            $cPAPolicyNo = $prev_policy_details->cPAPolicyNo;
            $cPASumInsured = $prev_policy_details->cPASumInsured;
            $cPAPolicyFmDt = Carbon::parse($prev_policy_details->cPAPolicyFmDt)->format('d-m-Y');
            $cPAPolicyToDt = Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d-m-Y');
        }
        // CPA

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
            $rto_code = ($vehicale_registration_number[0] ?? '') . '-' . ($vehicale_registration_number[1] ?? '');
        }
        
        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number = explode('-', $rto_code);
        }

        // state_code
        $state_code = ShriramPinCityState::where('pin_code', $proposal->pincode)->first()->state;
        // state_code

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posAgentName = $posAgentPanNo = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $posAgentName = $pos_data->agent_name;
                $posAgentPanNo = $pos_data->pan_no;
            }
        }
        $input_array = [
            "soap:Header" => [
                "AuthHeader" => [
                    "Username" => config("constants.gcv.shriram.SHRIRAM_GCV_USERNAME"),
                    "Password" => config("constants.gcv.shriram.SHRIRAM_GCV_PASSWORD"),
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                ]
            ],
            "soap:Body" => [
                "GenerateGCCVProposal" => [
                    "objGCCVProposalEntryETT" => [
                        "ReferenceNo" => '',
                        "ProdCode" => "MOT-PRD-003", /* $mmv_data->vap_prod_code */ //"MOT-PRD-003",
                        "PolicyFromDt" => date('d/m/Y', strtotime($policy_start_date)), //"19/08/2021",
                        "PolicyToDt" => date('d/m/Y', strtotime($policy_end_date)), //"18/08/2022",
                        "PolicyIssueDt" => today()->format('d/m/y'),
                        "InsuredPrefix" => $insured_prefix,
                        "InsuredName" => $proposal->first_name . ' ' . $proposal->last_name, //"Gopi",
                        "Gender" => $proposal->gender, //"M",
                        "Address1" => $proposal->address_line1,
                        "Address2" => $proposal->address_line2,
                        "Address3" => $proposal->address_line3,
                        "State" => $state_code, //"TN",
                        "City" => $proposal->city, //"Erode",
                        "PinCode" => $proposal->pincode,
                        "PanNo" => $proposal->pan_number,
                        'GSTNo' => $proposal->gst_number,
                        'TelephoneNo' => '',
                        "ProposalType" => $proposalType, //"Renewal",
                        "PolType" => $policy_type, //"MOT-PLT-001",
                        "DateOfBirth" => ($requestData->vehicle_owner_type == 'C') ? '' : date('d/m/Y', strtotime($proposal->dob)), //"05/06/1993",
                        "MobileNo" => $proposal->mobile_number, //"9626616284",
                        "FaxNo" => "",
                        "EmailID" => $proposal->email, //"Gopi@testmail.com",
                        "POSAgentName" => $posAgentName,
                        "POSAgentPanNo" => $posAgentPanNo,
                        "CoverNoteNo" => '',
                        "CoverNoteDt" => '',
                        "VehicleCategory" => $ic_version_details->no_of_wheels == '3' ? "CLASS_4A3" : "CLASS_4A1",
                        "VehicleCode" => $ic_version_details->veh_code,  //"M_10075",
                        "BodyType" => '',
                        "FuelType" => '', //$ic_version_details->fuel,
                        "Make" => '', //$ic_version_details->veh_make,
                        "CC" => '', //$ic_version_details->veh_cc,
                        "SeatingCapacity" => '', //$ic_version_details->veh_seat_cap,
                        "FirstRegDt" => date('d/m/Y', strtotime($requestData->vehicle_register_date)), //"10/07/2021", //,
                        "PlusDriver" => "",
                        "VehicleType" => $proposalType == "Fresh" ? "W" : "U",
                        "EngineNo" => $proposal->engine_number,
                        "ChassisNo" => $proposal->chassis_number,
                        "RegNo1" => $vehicale_registration_number[0] ?? '', // "MH",
                        "RegNo2" => $vehicale_registration_number[1] ?? '', // "01",
                        "RegNo3" => $vehicale_registration_number[2] ?? '', // "OK",
                        "RegNo4" => $vehicale_registration_number[3] ?? '', // "4521",
                        "RTOCode" => RtoCodeWithOrWithoutZero($rto_code, true), // "MH-01",
                        "VehicleAge" => "",
                        "IDV_of_Vehicle" => $quote_log->idv,
                        "Model" => "", //$ic_version_details->veh_model,
                        "Colour" => $proposal->vehicle_color,
                        "TowingVehicleType" => "",
                        "VoluntaryExcess" => '0', //"MOT-DED-002", $voluntary_insurer_discounts,
                        "GeographicalExcess" => "",
                        "NoEmpCoverLL" => "",
                        "NoOfCleaner" => $NoOfCleaner,
                        "NoOfDriver" => $NoOfDriver,
                        "NoOfConductor" => $NoOfConductor,
                        "VehicleMadeinindiaYN" => "",
                        "DriverTutionYN" => "",
                        "VehiclePurposeYN" => "",
                        "DriverAgeYN" => "",
                        "NoOfCoolies" => "",
                        "NFPP_OthThanEmp" => "",
                        "NoOfTrailers" => $NoOfTrailers,
                        "LimitOwnPremiseYN" => '0',
                        "TaxiYN" => "",
                        "Bangladesh" => "0",
                        "Bhutan" => "0",
                        "Srilanka" => "0",
                        "Nepal" => "0",
                        "Pakistan" => "0",
                        "Maldives" => "0",
                        "CNGKitYN" => $externalCNGKIT,
                        "CNGKitSI" => $externalCNGKITSI,
                        "InBuiltCNGKit" => $quote_data['fuel_type'] == 'CNG' ? 1 : 0,
                        //"LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                        "DeTariff" => 0,//$master_policy->default_discount,
                        "IMT23YN" => $imt23,
                        "BreakIn" => "NO",
                        "PreInspectionReportYN" => "",
                        "CaptiveUseYN" => "",
                        "IndemnityToHirerYN" => "",
                        "FitnessCertificateno" => "",
                        "Validupto" => "",
                        "VehPermit" => "",
                        "PermitNo" => "",
                        "NilDepreciationCoverYN" => $NilDepreciationCoverYN,
                        "TrailerVehicleCode" => "",
                        "TDChassisNo" => "",
                        "TDRegNo" => "",
                        "VehicleUsageForCommercialPurposeYN" => "",
                        "CoverLampTyreTubeYN" => "",
                        "UseofVehisLimitedOwnPremisesYN" => "",
                        "VehDesignedForBlindYN" => "",
                        "EntitledToNCBYN" => "",
                        "DeclinePropYN" => "",
                        "VehFitWithTublessTyresYN" => "",
                        "VehwithAntiTheftDevApprByARAIYN" => "",
                        "VehParkedDuringNight" => "",
                        "DateOfPurchaseOfVehAsPerInvOrSaleLetter" => "",
                        "VehUsedExclusiveFor" => "",
                        "ProofRenewalNoticeYN" => "",
                        "NCBCertificateYN" => "",
                        "ImpSplCondForExcess" => "",
                        "SpeedometerReading" => "",
                        "SelfDeclarationYN" => "",
                        "CancelOrRefuseRenew" => "",
                        "NoOfClaims" => "",
                        "Amount" => "",
                        "DetOfProof" => "",
                        "VehFittedWithFGTankYN" => "",
                        "TWFittedWithSideCarYN" => "",
                        "VehUsedForRacingYN" => "",
                        "VinCarByVntageCCCIYN" => "",
                        "NoOfClaims1" => "",
                        "Amount1" => "",
                        "PrvSocPlsureProffPurpYN" => "",
                        "NatureOfGoods" => "",
                        "VehEmbConsulDutyEleIDVYN" => "",
                        "PAforUnnamedPassengerYN" => $PAforUnnamedPassenger,
                        "PAforUnnamedPassengerSI" => $PAforUnnamedPassengerSI,
                        "ElectricalaccessYN" => $Electricalaccess,
                        "ElectricalaccessSI" => $ElectricalaccessSI,
                        "ElectricalaccessRemarks" => "",
                        "NonElectricalaccessYN" => $NonElectricalaccess,
                        "NonElectricalaccessSI" => $NonElectricalaccessSI,
                        "NonElectricalaccessRemarks" => "",
                        "PAPaidDriverConductorCleanerYN" => $PAPaidDriverConductorCleanerYN,
                        "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI,
                        "PAPaidDriverCount" => "1",
                        "PAPaidConductorCount" => "1",
                        "PAPaidCleanerCount" => "1",
                        "NomineeNameforPAOwnerDriver" => $proposal->nominee_name == null ? '' : $proposal->nominee_name,
                        "NomineeAgeforPAOwnerDriver" => $proposal->nominee_age == null ? '0' : $proposal->nominee_age,
                        "NomineeRelationforPAOwnerDriver" => $proposal->nominee_relationship == null ? '' : $proposal->nominee_relationship,
                        "AppointeeNameforPAOwnerDriver" => "",
                        "AppointeeRelationforPAOwnerDriver" => "",
                        "LLtoPaidDriverYN" => $LLtoPaidDriverYN,
                        "AntiTheftYN" => $antitheft,
                        "DriverName" => '',
                        "DriverAddress1" => '',
                        "DriverAddress2" => '',
                        "DriverAddress3" => '',
                        "DriverState" => '',
                        "DriverCity" => '',
                        "DriverPinCode" => '',
                        "DriverQualification" => '',
                        "DriverOwnerYN" => '',
                        "AgeOfPaidDriver" => '',
                        "AgeOfOwner" => '',
                        "DriverExperience" => '',
                        "DefectiveVision_YN" => '',
                        "IfDefectiveVisionGiveDetails" => '',
                        "Driver_Convicted_YN" => '',
                        "a_DriverName" => '',
                        "b_DateOfAccident" => '',
                        "c_CircumstanceOfAccident" => '',
                        "d_Loss_Cost_Amount" => '',
                        "e_AnyOtherInfo" => '',
                        "PreviousPolicyNo" => $proposalType == "Fresh" ? "" : $proposal->previous_policy_number,
                        "PreviousInsurer" => $proposalType == "Fresh" ? "" : $proposal->previous_insurance_company,
                        "PreviousPolicyFromDt" => $proposalType == "Fresh" ? "" : date('d/m/Y', strtotime($PreviousPolicyFromDt)), //"19/08/2020",
                        "PreviousPolicyToDt" => $proposalType == "Fresh" ? "" : date('d/m/Y', strtotime($PreviousPolicyToDt)), // "18/08/2021", 
                        "PreviousPolicyUWYear" => "", //$PreviousPolicyToDt, 
                        "PreviousPolicySI" => "",
                        "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0', 
                        "PreviousPolicyNCBPerc" => (int) $previous_ncb, 
                        "PreviousPolicyType" => $PreviousPolicyType,
                        "PreviousNilDepreciation" => $PreviousNilDepreciation,
                        "HypothecationType" => $HypothecationType,
                        "HypothecationBankName" => $HypothecationBankName,
                        "HypothecationAddress1" => $HypothecationAddress1,
                        "HypothecationAddress2" => $HypothecationAddress2,
                        "HypothecationAddress3" => $HypothecationAddress3,
                        "HypothecationAgreementNo" => $HypothecationAgreementNo,
                        "HypothecationCountry" => $HypothecationCountry,
                        "HypothecationState" => $HypothecationState,
                        "HypothecationCity" => $HypothecationCity,
                        "HypothecationPinCode" => $HypothecationPinCode,
                        "TransferOfOwnerShipYN" => "",
                        "AgeOfVehicle" => "",
                        "ClaimsLodged" => "",
                        "DrivenBy" => "",
                        "DriversAge" => "",
                        "DriversExp" => "",
                        "DriversQlfn" => "",
                        "IncurredClms" => "",
                        "TotalPoints" => "",
                        "Permit" => "",
                        "SpecifiedPersonField" => "",
                        "PAOwnerDriverExclusion" => $PAOwnerDriverExclusion,
                        "PAOwnerDriverExReason" => $PAOwnerDriverExReason,
                        "AadharNo" => "",
                        "AadharEnrollNo" => "",
                        "Form16" => "",
                        "VehicleManufactureYear" => "",
                        "GCCVVehType" => "",
                        "PuccNo" => "",
                        "validfrom" => "",
                        "validTo" => "",
                        "PuccState" => "",
                        "CPAPolicyNo" => $cPAPolicyNo,
                        "CPASumInsured" => $cPASumInsured,
                        "CPAInsComp" => $cPAInsComp,
                        "CPAPolicyFmDt" => $cPAPolicyFmDt,
                        "CPAPolicyToDt" => $cPAPolicyToDt,
                        "GVW" => "",
                        "NFPPEMP" => "",
                        "DE_TARIFFDIS" => $car_age < 11 ? '60' : '', //Hardcoded 15-09-2021
                        "FitnessValidupto" => "",
                        "PermitNum" => "",
                        "TRANSFEROFOWNER" => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                        "GOVTVEH" => "",
                        "NoOfDCCforPA" => "",
                        "RSACover" => $roadsideassistance,
                        "VehicleManufactureYear" => $manufacture_year,
                    ],
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                ]
            ],
        ];

        if($rto_code == 'AP-39')
        {
            $input_array['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['RTOCity'] = 'Prakasam';
        }

        $additional_data = [
            'enquiryId' => customDecrypt($request['userProductJourneyId']),
            'headers' => [
                'SOAPAction' => 'http://tempuri.org/GenerateGCCVProposal',
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'GCV',
            'method' => 'Proposal',
            'transaction_type' => 'proposal',
        ];
        $root = [
            'rootElementName' => 'soap:Envelope',
            '_attributes' => [
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
                "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
            ]
        ];
        $input_array = ArrayToXml::convert($input_array, $root, false, 'utf-8');

        $get_response = getWsData(config("constants.gcv.shriram.SHRIRAM_GCV_PROPOSAL_SUBMIT_URL"), $input_array, 'shriram', $additional_data);
        $response = $get_response['response'];

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
        $response = XmlToArray::convert($response);

        if (!isset($response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_CODE']) || $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_CODE'] != '0') {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_DESC'] ?? 'Insurer Not Reachable',
            ]);
        }
        if ($response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_CODE'] == '0') {
            $result = $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult'];
            $od_premium = 0;
            $tp_premium = 0;
            $ncb_discount = 0;
            $total_premium = 0;
            $final_payable_amount = 0;
            $cpa_premium = 0;
            $non_electrical_accessories = $other_discount = 0;//$nonelectrical_premium = 0;
            $voluntary_deductible = $addon_premium = $tp_lpg_kit = $electrical_premium = $od_lpg_kit = $tppd_discount = 0;
            $addons_available = [
                'ROAD SIDE ASSISTANCE', 'IMT23-COVERAGE FOR IMT 21 EXCLUSIONS'
            ];
            foreach ($result['CoverDtlList']['CoverDtl'] as $key => $premium_data) {
                if ($premium_data['CoverDesc'] == 'BASIC OD COVER') {
                    $od_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TP TOTAL') {
                    $tp_premium = round($premium_data['Premium']);
                }
                if (in_array($premium_data['CoverDesc'] , ['GR42--CNG KIT - TP  COVER', 'In Built CNG/LPG Kit TP Cover' , 'In Built CNG Kit TP Cover'])) {
                    $tp_lpg_kit = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR42--CNG-KIT-COVER') {
                    $od_lpg_kit = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES') {
                    $electrical_premium = round($premium_data['Premium']);
                }
                // Non-Electrical is included in Basic OD
                /*if ($premium_data['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                    $nonelectrical_premium = round($premium_data['Premium']);
                }*/
                if ($premium_data['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                    $ncb_discount = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TOTAL PREMIUM') {
                    $total_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                    $cpa_premium = round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                    $other_discount = round($premium_data['Premium']);
                }
                if ( in_array($premium_data['CoverDesc'], $addons_available) ) {
                    $addon_premium += round($premium_data['Premium']);
                }
                if ($premium_data['CoverDesc'] == 'GR39A-TPPD COVER') {
                    $tppd_discount = $premium_data['Premium'];
                }
            }
            $total_discount = $ncb_discount + $other_discount + $voluntary_deductible;
            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = (string) round(($NonElectricalaccessSI * 3.283 ) / 100);
                $od_premium = ($od_premium - $non_electrical_accessories);
            }
            $proposal->proposal_no      = $result['PROPOSAL_NO'];
            $proposal->pol_sys_id       = $result['POL_SYS_ID'];
            $proposal->od_premium       = ($od_premium - $total_discount);
            $proposal->tp_premium       = $tp_premium;
            $proposal->ncb_discount     = abs($ncb_discount);
            $proposal->addon_premium    = $addon_premium + ($od_lpg_kit + $electrical_premium + $non_electrical_accessories);//$zero_depreciation + $road_side_assistance;
            $proposal->total_premium    = $total_premium;
            $proposal->service_tax_amount = $final_payable_amount - $total_premium;
            $proposal->final_payable_amount = $final_payable_amount;
            $proposal->cpa_premium      = $cpa_premium;
            $proposal->total_discount   = abs($ncb_discount) + abs($other_discount) + $tppd_discount;
            $proposal->ic_vehicle_details = $vehicleDetails;
            $proposal->cpa_ins_comp     = $cPAInsComp;
            $proposal->cpa_policy_fm_dt = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyFmDt)) : "";
            $proposal->cpa_policy_no    = $cPAPolicyNo;
            $proposal->cpa_policy_to_dt = !empty($cPAPolicyFmDtdate) ? date('d-m-Y', strtotime($cPAPolicyToDt)) : "";
            $proposal->cpa_sum_insured  = $cPASumInsured;
            $proposal->electrical_accessories       = $ElectricalaccessSI;
            $proposal->non_electrical_accessories   = $NonElectricalaccessSI;
            $proposal->policy_start_date = date('d-m-Y', strtotime($policy_start_date));
            $proposal->policy_end_date  = date('d-m-Y', strtotime($policy_end_date));
            $proposal->tp_start_date = date('d-m-Y', strtotime($policy_start_date));
            $proposal->tp_end_date  = date('d-m-Y', strtotime($policy_end_date));
            $proposal->save();
            // $proposal->final_payable_amount = Arr::last($response['GeneratePCCVProposalResult']['CoverDtlList'])['Premium'];

            ShriramPremiumDetailController::saveGcvPremiumDetails($get_response['webservice_id']);

            // CKYC verification
            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $final_response = [
                    'status' => false,
                    'msg' => $result['ERROR_DESC'],
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

                $policy_approval = self::policyApprovalXml($proposal, $request, $result, config("constants.gcv.shriram.SHRIRAM_GCV_PROPOSAL_SUBMIT_URL"));

                if ( ! $policy_approval) {
                    $verification_modes = [
                        'ckyc_number' => 'ckyc_number',
                        'pan_card' => 'pan_number',
                        'aadhar_card' => 'aadhar',
                        'passport' => 'passport',
                        'driving_license' => 'driving_licence',
                        'voter_id' => 'voter_id'
                    ];

                    $ckyc_controller = new CkycController;
                    $ckyc_verification = $ckyc_controller->ckycVerifications(new Request([
                        'companyAlias' => 'shriram',
                        'enquiryId' => customEncrypt($enquiryId),
                        'mode' => $verification_modes[$proposal->ckyc_type]
                    ]));

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
                            $final_response['data']['kyc_message'] = $final_response['msg'] = 'CKYC Verification failed';                           
                        }
                    } else {
                        $final_response['data']['kyc_message'] = $final_response['msg'] = 'An error occurred while verifying CKYC';
                    }
                } else {
                    $final_response['status'] = true;
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
                'msg' => $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_DESC'],
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
            'manufacture_name'      => $mmv->veh_model,
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
            'section' => 'TAXI',
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
            $ncb_discount = 0;
            $pa_owner = 0;
            $other_discount = 0;
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
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => "Proposal Submitted Successfully!",
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
            'section' => get_parent_code($productData->product_sub_type_id) ,
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