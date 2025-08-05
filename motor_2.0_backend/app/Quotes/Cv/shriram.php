<?php

// namespace App\Quotes\Cv\V1\GCV;
use Illuminate\Support\Str;
use App\Models\SelectedAddons;
use App\Quotes\Cv\shriram_gcv\shriramgcv;
use Illuminate\Support\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

use function App\Quotes\Cv\shriram_gcv\getasQuote;
use function App\Quotes\Cv\V1\GCV\getV1GCVQuote; 
use function App\Quotes\Cv\V1\PCV\getV1PCVQuote; 

include_once app_path() . '/Quotes/Cv/shriram_gcv.php';
include_once app_path() . '/Quotes/Cv/V1/GCV/shriram.php';
include_once app_path() . '/Quotes/Cv/V1/PCV/shriram.php'; 
include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/V2/shriram.php';
function getQuote($enquiryId, $requestData, $productData)
{    
    if(config('IC.constant.SHRIRAM_GCV_PCV_JSON_V2_ENABLED') == 'Y')
    {
        return getJSONPCVGCVQuote($enquiryId, $requestData, $productData);
    }
    else if (policyProductType($productData->policy_id)->parent_id == 4 && config('IC.SHRIRAM.V1.GCV.ENABLE') == 'Y') 
    {     
        return getV1GCVQuote($enquiryId, $requestData, $productData);
    } 
    else if (policyProductType($productData->policy_id)->parent_id == 4 && config('constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE') == 'JSON') 
    {
        return getasQuote($enquiryId, $requestData, $productData);
    } 
    else if (policyProductType($productData->policy_id)->parent_id == 4) 
    {
        return getGcvQuotes($enquiryId, $requestData, $productData);
    } 
    else 
    {
        if(config('IC.SHRIRAM.V1.PCV.ENABLE') == 'Y')
        {
            return  getV1PCVQuote($enquiryId, $requestData, $productData);
        }
        elseif (config('constants.cv.shriram.SHRIRAM_CV_REQUEST_TYPE') == 'XML') 
        {
            return  getXmlPcvQuotes($enquiryId, $requestData, $productData);
        }
        else
        {
            return  getJsonPcvQuotes($enquiryId, $requestData, $productData);
        }
        //return getPcvQuotes($enquiryId, $requestData, $productData);
    }
}

function getGcvQuotes($enquiryId, $requestData, $productData)
{ 
    $refer_webservice = $productData->db_config['quote_db_cache'];
    // if(($requestData->ownership_changed ?? '' ) == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }
    if ($requestData->gcv_carrier_type != 'PUBLIC') {
        return [
            'premium_amount' => 0,
            'status'    => false,
            'message'   => 'Insurer doesn\'t provide quotes for GCV - Private Carrier',
            'request' => [
                'requestData' => $requestData,
                'carrier_type' => $requestData->gcv_carrier_type,
                'message' => 'Insurer doesn\'t provide quotes for GCV - Private Carrier',
            ]
        ];
    }
    $mmv = get_mmv_details($productData, $requestData->version_id, 'shriram');
    if($mmv['status'] == 1)
    {
        $mmv_data = (object) $mmv['data'];
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
    $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code, true);

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $policy_type = ($premium_type == 'comprehensive' ? 'MOT-PLT-001' : 'MOT-PLT-002');
    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    if ($requestData->previous_policy_type == 'Third-party' && $tp_only == 'false') {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes not available for Previous policy as Third-Party.',
            'request' => [
                'requestData' => $requestData,
                'previous_policy_type' => $requestData->previous_policy_type,
                'message' => 'Quotes not available for Previous policy as Third-Party.',
            ]
        ]; 
    }
    if ($requestData->business_type == 'newbusiness') {
        $policy_start_date = Carbon::parse(date('d-m-Y'));
    } elseif ($requestData->business_type == 'breakin') {
        $policy_start_date = Carbon::parse(date('d-m-Y', strtotime('+2 day', time())));
    } elseif ($requestData->business_type == 'rollover') {
        $policy_start_date = Carbon::parse(date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date))));
    }
    $manufacture_year = explode('-',$requestData->manufacture_year)[1];
    if ($requestData->business_type == 'newbusiness') {
        $BusinessType = '1';
        $ISNewVehicle = 'true';
        $Registration_Number = $rto_code;
        $NCBEligibilityCriteria = '1';
        $previous_ncb = '0';
        $proposalType = 'Fresh';
        //$policy_start_date = today();
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
        $PreviousPolicyFromDt = $PreviousPolicyToDt = $PreviousNilDepreciation = $PreviousPolicyType = $previous_ncb = '';
        $break_in = 'NO';
        $vehicale_registration_number = explode('-', $rto_code);
        $vehicle_in_90_days = 'N';
        $car_age = 0;
        $PreviousNilDepreciation = 0;
    } else {
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new \DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date);
        //$policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date)->format('d-M-Y') >= now()->format('d-M-Y')) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1) : today()->addDay(2);
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d/m/Y');
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = ceil($age / 12);
        $BusinessType = '5';
        $ISNewVehicle = 'false';
        $proposalType = "RENEWAL";
        $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');
        $PreviousPolicyToDt = Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y');
        $PreviousPolicyType = $requestData->previous_policy_type == 'Third-party' ? "MOT-PLT-002" : "MOT-PLT-001";
        $previous_ncb = $requestData->previous_ncb;
        if ($requestData->vehicle_registration_no != '') {
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
            $Registration_Number = $requestData->vehicle_registration_no;
        } else {
            $vehicale_registration_number = explode('-', $rto_code);
            $Registration_Number = $rto_code;
        }
        $PreviousNilDepreciation = 0;
        $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
        $break_in = "No";
        $vehicle_in_90_days = 'N';
    }
    $nilDepreciationCover = 0;
    $applicable_addon = ['imt23'];
    $roadsideassistance = 1;
    // if ( $car_age > 12 ) {
    //     $roadsideassistance = 0;
    // }else{
        array_push($applicable_addon, "roadSideAssistance");
    // }
    if ($tp_only == 'true') {
        $applicable_addon = [];
        $nilDepreciationCover = $PreviousNilDepreciation = $roadsideassistance = 0;
    }

    // Addons And Accessories
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $ElectricalaccessSI = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
    //$addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
    $NoOfTrailers = 0;
    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value) && $tp_only == 'false') {
            $Electricalaccess = 1;
            $ElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('Non-Electrical Accessories', $value) && $tp_only == 'false') {
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
        /* if ('Trailer' == $value['name']) {
            $NoOfTrailers = $value['sumInsured'];
        } */
    }
    $LimitedTPPDYN = 0;
    foreach ($discounts as $key => $value) {
        if (in_array('TPPD Cover', $value)) {
            $LimitedTPPDYN = 1;
        }
    }
    $LLtoPaidDriverYN = $PAPaidDriverConductorCleanerSI = $PAPaidDriverConductorCleanerYN = $NoOfCleaner = $NoOfConductor = $NoOfDriver = 0;
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
    }
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
    $inputArray = [
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
                    "ProdCode" => "MOT-PRD-003"/* $mmv_data->vap_prod_code */, //"MOT-PRD-003",
                    "PolicyFromDt" => $policy_start_date->format('d/m/Y'), //"19/08/2021",
                    "PolicyToDt" => $policy_end_date, //"18/08/2022",
                    "PolicyIssueDt" => today()->format('d/m/y'),
                    "InsuredPrefix" => ($requestData->vehicle_owner_type == 'I') ? "1" : "3",
                    "InsuredName" => ($requestData->user_fname ?? "Test") . ' ' . ($requestData->user_lname ?? "Test"), //"Gopi",
                    "Gender" => ($requestData->vehicle_owner_type == 'I') ? "M" : "", //"M",
                    "Address1" => "Address1",
                    "Address2" => "Addres    s2",
                    "Address3" => "Address3",
                    "State" => explode('-', $rto_code)[0], //"TN",
                    "City" => 'Mumbai', //"Erode",
                    "PinCode" => "400005",
                    "PanNo" => "",
                    'GSTNo' => '',
                    'TelephoneNo' => '',
                    "ProposalType" => $proposalType, //"Renewal",
                    "PolType" => $policy_type, //"MOT-PLT-001",
                    "DateOfBirth" => "", //"05/06/1993",
                    "MobileNo" => $requestData->user_mobile ?? "9876543211", //"9626616284",
                    "FaxNo" => "",
                    "EmailID" => $requestData->user_email ?? 'ABC@testmail.com', //"Gopi@testmail.com",
                    "POSAgentName" => $posAgentName,
                    "POSAgentPanNo" => $posAgentPanNo,
                    "CoverNoteNo" => '',
                    "CoverNoteDt" => '',
                    "VehicleCategory" => $mmv_data->no_of_wheels == '3' ? "CLASS_4A3" : "CLASS_4A1", // "CLASS_4A3" in case of 3W
                    "VehicleCode" => $mmv_data->veh_code, //"M_10075",
                    "BodyType" => '',
                    "FuelType" => '',
                    "Make" => '',
                    "CC" => '',
                    "SeatingCapacity" => '',
                    "FirstRegDt" => date('d/m/Y', strtotime($requestData->vehicle_register_date)), //"10/07/2021", //,
                    "PlusDriver" => "",
                    "VehicleType" => $BusinessType == "1" ? "W" : "U",
                    "EngineNo" => Str::upper(Str::random(8)),
                    "ChassisNo" => Str::upper(Str::random(12)),
                    "RegNo1" => $vehicale_registration_number[0], // "MH",
                    "RegNo2" => $vehicale_registration_number[1], // "01",
                    "RegNo3" => $vehicale_registration_number[2] ?? 'OK', // "OK",
                    "RegNo4" => $vehicale_registration_number[3] ?? '4521', // "4521",
                    "RTOCode" => $rto_code, // "MH-01",
                    "VehicleAge" => "",
                    "IDV_of_Vehicle" => "",
                    "Model" => "",
                    "Colour" => "",
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
                    "Bangladesh" => "",
                    "Bhutan" => "",
                    "SriLanka" => "",
                    "Nepal" => "",
                    "Pakistan" => "",
                    "Maldives" => "",
                    "CNGKitYN" => $externalCNGKIT,
                    "CNGKitSI" => $externalCNGKITSI,
                    "InBuiltCNGKit" => $requestData->fuel_type == 'CNG' ? 1 : 0,
                    // "LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                    "DeTariff" => 0,//$productData->default_discount,
                    "IMT23YN" => "1",
                    "BreakIn" => "NO",
                    "PreInspectionReportYN" => "",
                    "CaptiveUseYN" => "",
                    "IndemnityToHirerYN" => "",
                    "FitnessCertificateno" => "",
                    "Validupto" => "",
                    "VehPermit" => "",
                    "PermitNo" => "",
                    "NilDepreciationCoverYN" => $nilDepreciationCover,
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
                    "NomineeNameforPAOwnerDriver" => "SURESH",
                    "NomineeAgeforPAOwnerDriver" => "28",
                    "NomineeRelationforPAOwnerDriver" => "others",
                    "AppointeeNameforPAOwnerDriver" => "Gopi",
                    "AppointeeRelationforPAOwnerDriver" => "others",
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
                    "PreviousPolicyNo" => $BusinessType == "1" ? "" : "D044353536",
                    "PreviousInsurer" => $BusinessType == "1" ? "" : "Go Digit General Insurance Ltd",
                    "PreviousPolicyFromDt" => $PreviousPolicyFromDt, //"19/08/2020",
                    "PreviousPolicyToDt" => $PreviousPolicyToDt, // "18/08/2021", 
                    "PreviousPolicyUWYear" => "", //$PreviousPolicyToDt, 
                    "PreviousPolicySI" => "",
                    "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0', 
                    "PreviousPolicyNCBPerc" => (int) $previous_ncb, 
                    "PreviousPolicyType" => $PreviousPolicyType,
                    "PreviousNilDepreciation" => $PreviousNilDepreciation,
                    "HypothecationType" => "",
                    "HypothecationBankName" => "",
                    "HypothecationAddress1" => "",
                    "HypothecationAddress2" => "",
                    "HypothecationAddress3" => "",
                    "HypothecationAgreementNo" => "",
                    "HypothecationCountry" => "",
                    "HypothecationState" => "",
                    "HypothecationCity" => "",
                    "HypothecationPinCode" => "",
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
                    "PAOwnerDriverExclusion" => ($requestData->vehicle_owner_type == 'I') ? "0" : "1",
                    "PAOwnerDriverExReason" => ($requestData->vehicle_owner_type == 'I') ? "" : "PA_TYPE1",
                    "AadharNo" => "",
                    "AadharEnrollNo" => "",
                    "Form16" => "",
                    "VehicleManufactureYear" => "",
                    "GCCVVehType" => "",
                    "PuccNo" => "",
                    "validfrom" => "",
                    "validTo" => "",
                    "PuccState" => "",
                    "CPAPolicyNo" => "",
                    "CPASumInsured" => "",
                    "CPAInsComp" => "",
                    "CPAPolicyFmDt" => "",
                    "CPAPolicyToDt" => "",
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

    if($requestData->rto_code == 'AP-39')
    {
        $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['RTOCity'] = 'Prakasam';
    }

    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [
            'SOAPAction' => 'http://tempuri.org/GenerateGCCVProposal',
            'Content-Type' => 'text/xml; charset="utf-8"',
        ],
        'requestMethod' => 'post',
        'requestType' => 'xml',
        'section' => 'GCV',
        'method' => $tp_only == 'true' ? 'TP Quote' : 'Quote',
        'transaction_type' => 'quote',
    ];
    $root = [
        'rootElementName' => 'soap:Envelope',
        '_attributes' => [
            "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
            "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
            "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
        ]
    ];
    $input_array = ArrayToXml::convert($inputArray, $root, false, 'utf-8');

    $checksum_data = checksum_encrypt($input_array);
    // dump($checksum_data);
    $additional_data['checksum'] =  $checksum_data;
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'shriram', $checksum_data, 'CV');
    // dump($is_data_exits_for_checksum);

    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
        
        $get_response = $is_data_exits_for_checksum;
    }
    else
    {
        $get_response = getWsData(config("constants.gcv.shriram.SHRIRAM_GCV_QUOTE_URL"), $input_array, 'shriram', $additional_data);
    }
    $response = XmlToArray::convert($get_response['response']);

    if ($response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_CODE'] == 0) {
        $skip_second_call = false;
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "{$additional_data['method']} Success", "Success" );
        $quote_response1 = $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult'];
        if ($tp_only == 'true') {
            $idv = $idv_min = $idv_max = 0;
        }else{
            $idv = $quote_response1['VehicleIDV'];
            $idv_min = (string) ceil(0.85 * $quote_response1['VehicleIDV']);
            $idv_max = (string) floor(1.20 * $quote_response1['VehicleIDV']);
        }
        if ($requestData->is_idv_changed == 'Y' && $tp_only == 'false')
        {
            if ($requestData->edit_idv >= floor($idv_max))
            {
                $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = floor($idv_max);
            }
            elseif ($requestData->edit_idv <= ceil($idv_min))
            {
                $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = ceil($idv_min);
            }
            else 
            {
                $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = $requestData->edit_idv;
            }
        }
        else
        {
            /* $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = $idv_min; */
            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = $idv;
                    $skip_second_call = true;
                    break;
                case 'min_idv':
                    $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = $idv_min;
                    break;
                case 'max_idv':
                    $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = $idv_max;
                    break;
                default:
                    $inputArray['soap:Body']['GenerateGCCVProposal']['objGCCVProposalEntryETT']['IDV_of_Vehicle'] = $idv_min;
                    break;
            }
        }
        if ($tp_only == 'false' && !$skip_second_call) {
            $additional_data['method'] = 'Premium Re Calculation';
            $input_array = ArrayToXml::convert($inputArray, $root, false, 'utf-8');
            
            
        $checksum_data = checksum_encrypt($input_array);
        $additional_data['checksum'] =  $checksum_data;
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'shriram', $checksum_data, 'CV');
        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
            
            $get_response = $is_data_exits_for_checksum;
        }
        else
        {
            $get_response = getWsData(config('constants.gcv.shriram.SHRIRAM_GCV_QUOTE_URL'), $input_array, 'shriram', $additional_data);
            
        }
            $response = $get_response['response'];
            $response = XmlToArray::convert($response);
        }

        if (!isset($response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_CODE']) || $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_CODE'] != '0') {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_DESC'] ?? 'Insurer Not Reachable',
            ];
        }
        if ($tp_only == 'false') {
            $idv = $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['VehicleIDV'];
        }
        $imt_23 = $igst = $anti_theft = $other_discount = $rsapremium = $pa_paid_driver = $zero_dep_amount = $ncb_discount = $tppd = $final_tp_premium = $final_od_premium = $final_net_premium = $igst = $final_payable_amount = $basic_od  = $electrical_accessories = $lpg_cng_tp = $lpg_cng = $non_electrical_accessories = $pa_owner = $ll_paid_driver = $tppd_discount = 0;

        foreach ($response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['CoverDtlList']['CoverDtl'] as $key => $value) {
            if ($value['CoverDesc'] == 'BASIC OD COVER') {
                $basic_od = $value['Premium'];
            }
            if ($value['CoverDesc'] == 'IMT23-COVERAGE FOR IMT 21 EXCLUSIONS') {
                $imt_23 = $value['Premium'];
            }
            if (in_array($value['CoverDesc'], ['GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES'])) {
                $electrical_accessories = $value['Premium'];
            }
            if (in_array($value['CoverDesc'], ['GR42--CNG-KIT-COVER'])) {
                $lpg_cng = $value['Premium'];
            }
            if (in_array($value['CoverDesc'] , ['GR42--CNG KIT - TP  COVER', 'In Built CNG/LPG Kit TP Cover', 'In Built CNG Kit TP Cover'])){
                $lpg_cng_tp = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                $pa_owner = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'Legal Liability Coverages For Paid Driver') {
                $pa_paid_driver = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                $other_discount = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'GR30-Anti Theft Discount Cover') {
                $anti_theft = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'ROAD SIDE ASSISTANCE') {
                $rsapremium = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                $ncb_discount = $value['Premium'];
            }
            if (in_array($value['CoverDesc'], ['LL TO PAID DRIVER', 'LL TO PAID CLEANER', 'LL TO PAID CONDUCTOR'])) {
                $ll_paid_driver = $ll_paid_driver + $value['Premium'];
            }
            if ($value['CoverDesc'] == 'BASIC TP COVER') {
                $tppd = $value['Premium'];
            }
            if ($value['CoverDesc'] == 'TP TOTAL') {
                $final_tp_premium = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'OD TOTAL') {
                $final_od_premium = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'TOTAL PREMIUM') {
                $final_net_premium = $value['Premium'];
            }

            if ($value['CoverDesc'] == 'IGST') {
                $igst = $igst + $value['Premium'];
            }

            if ($value['CoverDesc'] == 'TOTAL AMOUNT') {
                $final_payable_amount = $value['Premium'];
            }
            
            if ($value['CoverDesc'] == 'GR39A-TPPD COVER') {
                $tppd_discount = (float)($value['Premium']);
            }

        }
        if ((int) $NonElectricalaccessSI > 0) {
            $non_electrical_accessories = (float) (($NonElectricalaccessSI * 3.283 ) / 100);
            $basic_od = ($basic_od - $non_electrical_accessories);
        }

        $temp_od = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
        $imt_23 = ($temp_od * 0.15);
        //$basic_od = $basic_od - $imt_23;
        $ncb_discount = (($temp_od - $other_discount) * $requestData->applicable_ncb / 100);

        $final_gst_amount = isset($igst) ? $igst : 0;

        $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount;

        $final_total_discount = $anti_theft + $ncb_discount + $other_discount + $tppd_discount;

        $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;

        if ($productData->zero_dep == 0 && $zero_dep_amount == 0)
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                'request' => [
                    'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                ]
            ];
        }        
       elseif($imt_23 == 0)
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'IMT-23 Premium not available for IMT-23 Product',
                'request' => [
                    'message' => 'IMT-23 Premium not available for IMT-23 Product',
                ]
            ];
        }
        if ($imt_23 == 0) {
            unset($applicable_addons['imt23']);
        }
        if ($rsapremium == 0) {
            unset($applicable_addons['roadSideAssistance']);
        }
        $data_response = [
            'status' => true,
            'msg' => 'Found',
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'Data' => [
                'idv' => $idv,
                'min_idv' => $idv_min,
                'max_idv' => $idv_max,
                'vehicle_idv' => $idv,
                'qdata' => null,
                'pp_enddate' => $requestData->previous_policy_expiry_date,
                'addonCover' => null,
                'addon_cover_data_get' => '',
                'rto_decline' => null,
                'rto_decline_number' => null,
                'mmv_decline' => null,
                'mmv_decline_name' => null,
                'policy_type' => $tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                'cover_type' => '1YC',
                'hypothecation' => '',
                'hypothecation_name' => '',
                'vehicle_registration_no' => $rto_code,
                'rto_no' => $rto_code,
                'version_id' => $requestData->version_id,
                'selected_addon' => [],
                'showroom_price' => 0,
                'fuel_type' => $requestData->fuel_type,
                'ncb_discount' => $requestData->applicable_ncb,
                'company_name' => $productData->company_name,
                'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                'product_name' => $productData->product_sub_type_name,
                'mmv_detail' => $mmv_data,
                'master_policy_id' => [
                    'policy_id' => $productData->policy_id,
                    'policy_no' => $productData->policy_no,
                    'policy_start_date' => $policy_start_date,
                    'policy_end_date' => $policy_end_date,
                    'sum_insured' => $productData->sum_insured,
                    'corp_client_id' => $productData->corp_client_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'insurance_company_id' => $productData->company_id,
                    'status' => $productData->status,
                    'corp_name' => '',
                    'company_name' => $productData->company_name,
                    'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_sub_type_name' => $productData->product_sub_type_name,
                    'flat_discount' => $productData->default_discount,
                    'predefine_series' => '',
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                ],
                'motor_manf_date' => $requestData->vehicle_register_date,
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'vehicleDiscountValues' => [
                    'master_policy_id' => $productData->policy_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'segment_id' => 0,
                    'rto_cluster_id' => 0,
                    'car_age' => $car_age,
                    'aai_discount' => 0,
                    'ic_vehicle_discount' => (float)(abs($other_discount)),
                ],
                'ic_vehicle_discount' => (float)(abs($other_discount)),
                'basic_premium' => (float)($basic_od),
                'motor_electric_accessories_value' => (float)($electrical_accessories),
                'motor_non_electric_accessories_value' => (float)($non_electrical_accessories),
                'motor_lpg_cng_kit_value' => (float)($lpg_cng),
                'total_accessories_amount(net_od_premium)' => (float)($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                'total_own_damage' => (float)($final_od_premium),
                'tppd_premium_amount' => (float)($tppd),
                'compulsory_pa_own_driver' => (float)($pa_owner), // Not added in Total TP Premium
                'cover_unnamed_passenger_value' => 0, //$pa_unnamed,
                'default_paid_driver' => $ll_paid_driver,
                'll_paid_driver_premium' => $ll_paid_driver,
                'll_paid_conductor_premium' => 0,
                'll_paid_cleaner_premium' => 0,
                'motor_additional_paid_driver' => (float)($pa_paid_driver),
                'cng_lpg_tp' => (float)($lpg_cng_tp),
                'seating_capacity' => $mmv_data->veh_seat_cap,
                'deduction_of_ncb' => (float)(abs($ncb_discount)),
                'antitheft_discount' => (float)(abs($anti_theft)),
                'aai_discount' => '', //$automobile_association,
                'voluntary_excess' => '', //$voluntary_excess,
                'other_discount' => (float)(abs($other_discount)),
                'total_liability_premium' => (float)($final_tp_premium),
                'net_premium' => (float)($final_net_premium),
                'service_tax_amount' => (float)($final_gst_amount),
                'service_tax' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'tppd_discount' => $tppd_discount,
                'quotation_no' => '',
                'premium_amount' => (float)($final_payable_amount),
                'service_data_responseerr_msg' => 'success',
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'business_type' => $requestData->business_type == 'rollover' ? 'Rollover' : 'New Business',
                'service_err_code' => null,
                'service_err_msg' => null,
                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                'ic_of' => $productData->company_id,
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'get_policy_expiry_date' => null,
                'get_changed_discount_quoteid' => 0,
                'vehicle_discount_detail' => [
                    'discount_id' => null,
                    'discount_rate' => null,
                ],
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online,
                'policy_id' => $productData->policy_id,
                'insurane_company_id' => $productData->company_id,
                'max_addons_selection' => null,
                'add_ons_data' => [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation' => (float)($zero_dep_amount),
                        'road_side_assistance' => (float)($rsapremium),
                        'imt23' => (float)($imt_23),
                    ],
                    'in_built_premium'   => 0,
                    'additional_premium' => array_sum( [ $zero_dep_amount, $rsapremium, $imt_23] ),
                    'other_premium'      => 0,
                ],
                'applicable_addons' => $applicable_addon,
                'final_od_premium' => (float)($final_od_premium),
                'final_tp_premium' => (float)($final_tp_premium),
                'final_total_discount' => (float)(abs($final_total_discount)),
                'final_net_premium' => (float)($final_net_premium),
                'final_gst_amount' => (float)($final_gst_amount),
                'final_payable_amount' => (float)($final_payable_amount),
                'mmv_detail' => [
                    'manf_name' => $mmv_data->manf,
                    'model_name' => $mmv_data->model_desc,
                    'version_name' => '',//$mmv_data->model_desc,
                    'fuel_type' => $mmv_data->fuel,
                    'seating_capacity' => $mmv_data->veh_seat_cap,
                    'carrying_capacity' => $mmv_data->veh_seat_cap,
                    'cubic_capacity' => $mmv_data->veh_cc,
                    'gross_vehicle_weight' => '',
                    'vehicle_type' => 'Taxi',
                ],
            ],
        ];
        return camelCase($data_response);
    } else {
        return [
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'msg' => $response['soap:Body']['GenerateGCCVProposalResponse']['GenerateGCCVProposalResult']['ERROR_DESC'],
        ];
    }
}

function getJsonPcvQuotes($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    // if(($requestData->ownership_changed ?? '' ) == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }
    $mmv = get_mmv_details($productData,$requestData->version_id,'shriram');

    if ($mmv['status'] == 1)
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
    
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    }
    elseif ($mmv_data->ic_version_code == 'DNE')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }
    else
    {
        $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code, true);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $policy_type = ($premium_type == 'comprehensive' ? 'MOT-PLT-001' : 'MOT-PLT-002');
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

        if ($requestData->previous_policy_type == 'Third-party' && $tp_only == 'false')
        {
            return  [   
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Quotes not available for Previous policy as Third-Party.',
                'request' => [
                    'requestData' => $requestData,
                    'previous_policy_type' => $requestData->previous_policy_type,
                    'message' => 'Quotes not available for Previous policy as Third-Party.',
                ]
            ]; 
        }

        if ( ! empty($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no!="NEW")
        {
            $vehicale_registration_number = explode('-', getRegisterNumberWithOrWithoutZero($requestData->vehicle_registration_no));
        }
        else
        {
            $rto = explode('-', RtoCodeWithOrWithoutZero($rto_code,true));
        }

        $registration_numbers = [
            $vehicale_registration_number[0] ?? $rto[0],
            $vehicale_registration_number[1] ?? $rto[1],
            $vehicale_registration_number[2] ?? ($requestData->business_type != 'newbusiness' ? 'AT' : 'AT'),
            $vehicale_registration_number[3] ?? ($requestData->business_type != 'newbusiness' ? '1234' : '1234')
        ];
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];

        if ($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $ISNewVehicle = 'true';
            $Registration_Number = $rto_code;
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $proposalType = 'Fresh';
            $policy_start_date = today()->format('d-m-Y');
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');

            $PreviousPolicyFromDt = $PreviousPolicyToDt = $PreviousNilDepreciation = $PreviousPolicyType = $previous_ncb = '';
            $break_in = 'NO';
            $vehicle_in_90_days = 'N';
            $previous_ncb = $requestData->previous_ncb ? $requestData->previous_ncb : '0';
        }
        else
        {
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new \DateTime($vehicleDate);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $policy_start_date = $requestData->business_type != 'breakin' ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1)->format('d-m-Y') : today()->addDay(1)->format('d-m-Y');
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $car_age = floor($age / 12);

            $BusinessType = '5';
            $ISNewVehicle = 'false';
            $proposalType = ($requestData->previous_policy_type == 'Not sure') ? "RENEWAL.WO.PRV INS DTL" : "RENEWAL OF OTHERS";
            $PreviousPolicyFromDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-m-Y');
            $PreviousPolicyToDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($requestData->previous_policy_expiry_date)->format('d-m-Y');
            $PreviousPolicyType = $requestData->previous_policy_type == 'Third-party' ? "MOT-PLT-002" : "MOT-PLT-001";
            $PreviousNilDepreciation = 25;
            $previous_ncb = $requestData->previous_ncb ? $requestData->previous_ncb : '0';

            if ($requestData->vehicle_registration_no != '')
            {
                $Registration_Number = $requestData->vehicle_registration_no;
            }
            else
            {
                $Registration_Number = $rto_code;
            }
            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
            // $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            // $break_in = (Carbon::parse($requestData->previous_policy_expiry_date)->diffInDays($policy_start_date) > 0) ? 'YES' : 'NO';
            $break_in = "No";
            $vehicle_in_90_days = 'N';
        }

        // Addons And Accessories
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

        $ElectricalaccessSI = $PAforUnnamedPassengerSI = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKITSI = $antitheft = $LimitOwnPremiseYN = $LimitedTPPDYN = $Electricalaccess = $NonElectricalaccess = $externalCNGKIT = $LLtoPaidDriverYN = 0;

        $countries = [];

        // dd($selected_addons->additional_covers);
        if ($selected_addons)
        {
            if (isset($selected_addons->accessories) && ! is_null($selected_addons->accessories))
            {
                foreach ($selected_addons->accessories as $accessories)
                {
                    if ($accessories['name'] == 'Electrical Accessories' && $tp_only == 'false')
                    {
                        $Electricalaccess = 1;
                        $ElectricalaccessSI = $accessories['sumInsured'];
                        // if ($ElectricalaccessSI > 20000) {
                        //     return [
                        //         'status' => false,
                        //         'message' => 'Electrical sumInsured should not be greater than 20,000',
                        //     ];
                        // }
                    }
                    elseif ($accessories['name'] == 'Non-Electrical Accessories' && $tp_only == 'false')
                    {
                        $NonElectricalaccess = 1;
                        $NonElectricalaccessSI = $accessories['sumInsured'];
                        // if ($NonElectricalaccessSI > 20000) {
                        //     return [
                        //         'status' => false,
                        //         'message' => 'Non-Electrical sumInsured should not be greater than 20,000',
                        //     ];
                        // }
                    }
                    elseif ($accessories['name'] == 'External Bi-Fuel Kit CNG/LPG')
                    {
                        $externalCNGKIT = 1;
                        $externalCNGKITSI = $accessories['sumInsured'];
                        if($accessories['sumInsured'] < 15000)
                        {
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
//                            return [
//                                'status' => false,
//                                'message' => 'CNG/LPG sumInsured should not be greater than 30,000',
//                            ];
                        }
                    }
                }
            }

            if (isset($selected_addons->discounts) && ! is_null($selected_addons->discounts))
            {
                foreach ($selected_addons->discounts as $discount)
                {
                    if ($discount['name'] == 'anti-theft device')
                    {
                        $antitheft = 1;
                    }
                    elseif ($discount['name'] == 'Vehicle Limited to Own Premises')
                    {
                        $LimitOwnPremiseYN = 1;
                    }
                    elseif ($discount['name'] == 'TPPD Cover')
                    {
                        $LimitedTPPDYN = 1;
                    }
                }
            }

            if (isset($selected_addons->additional_covers) && ! is_null($selected_addons->additional_covers))
            {
                foreach ($selected_addons->additional_covers as $additional_cover)
                {
                    if ($additional_cover['name'] == 'PA cover for additional paid driver')
                    {
                        $PAPaidDriverConductorCleaner = 1;
                        $PAPaidDriverConductorCleanerSI = $additional_cover['sumInsured'];
                    }
                    elseif ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                    {
                        $PAforUnnamedPassenger = 1;
                        $PAforUnnamedPassengerSI = $additional_cover['sumInsured'];
                    }
                    elseif ($additional_cover['name'] == 'LL paid driver')
                    {
                        $LLtoPaidDriverYN = 1;
                    }
                    elseif ($additional_cover['name'] == 'Geographical Extension')
                    {
                        $countries = $additional_cover['countries'];
                    }
                }
            }
        }

        /* foreach ($accessories as $key => $value) {
            if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }

            if (in_array('PA To Unnamed Passenger', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
        } */

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posAgentName = $posAgentPanNo = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
        {
            if ($pos_data)
            {
                $posAgentName = $pos_data->agent_name;
                $posAgentPanNo = $pos_data->pan_no;
            }
        }
       // dd($mmv_data);
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

        if ($veh_category == '3W' && $mmv_data->veh_seat_cap <= 6) 
        {
            $vehicleClass = "CLASS_4C1B"; //PCCV-3 wheelers-carrying passengers-capacity NOT > 6
        } 
        else if ($veh_category == '4W' && $mmv_data->veh_seat_cap <= 6) 
        {
            $vehicleClass = "CLASS_4C1A"; //PCCV-4 wheelers - carrying passengers-capacity NOT > 6
        } 
        else if ($veh_category == '3W'&& $mmv_data->veh_seat_cap > 6 && $mmv_data->veh_seat_cap <= 17) 
        {
            $vehicleClass = "CLASS_4C3";  //PCCV-3 wheeled vehicles-carrying passengers > 6 but NOT >17
        } 
        else if (($veh_category == '4W' && $mmv_data->veh_seat_cap > 6) || ($veh_category == '3W' && $mmv_data->veh_seat_cap > 17)) 
        {
            $vehicleClass = "CLASS_4C2";  // PCCV-4 (or more) wheeled vehicles-capacity > 6 and 3 wheelers-carrying passengers -capacity > 17
        }

       // dd($veh_category,$vehicleClass,$mmv_data->veh_seat_cap);

        $inputArray = [
            "objPolicyEntryETT" => [
                "ReferenceNo" => "",
                "ProdCode" => 'MOT-PRD-005',//$mmv_data->vap_prod_code, // kit pref
                "PolicyFromDt" => $policy_start_date,
                "PolicyToDt" => $policy_end_date,
                "PolicyIssueDt" => today()->format('d-m-y'),
                "InsuredPrefix" => "1", // kit prefix
                "InsuredName" => ($requestData->user_fname ?? 'Shriram') . ' ' . ($requestData->user_lname ?? 'Insurance'), #9954
                "Gender" => '',
                "Address1" => 'sa',
                "Address2" => 'sas',
                "Address3" => 'sas',
                "State" => explode('-', $rto_code)[0],
                "City" => 'Mumbai',
                "PinCode" => '400005',
                "PanNo" => '',
                "GSTNo" => '',
                "TelephoneNo" => '',
                "ProposalType" => $proposalType, // kit
                "PolicyType" => $policy_type, // kit
                "DateOfBirth" => '',
                "MobileNo" => $requestData->user_mobile ?? '9999999999', #9954
                "FaxNo" => "",
                "EmailID" => $requestData->user_email ?? 'insurer@gmail.com', #9954
                "POSAgentName" => $posAgentName,
                "POSAgentPanNo" => $posAgentPanNo,
                "CoverNoteNo" => "",
                "CoverNoteDt" => "",
                "VehicleCategory" => $vehicleClass, //$mmv_data->veh_category,
                "VehicleCode" => $mmv_data->veh_code,
                "FirstRegDt" => $requestData->vehicle_register_date, //  car regi date
                "VehicleType" => "U", // kit
                "EngineNo" => Str::upper(Str::random(8)),
                "ChassisNo" => Str::upper(Str::random(12)),
                "RegNo1" => $registration_numbers[0],
                "RegNo2" => $registration_numbers[1],
                "RegNo3" => $registration_numbers[2],
                "RegNo4" => $registration_numbers[3],
                "RTOCode" => $rto_code,
                "IDV_of_Vehicle" => '',
                "Colour" => '',
                "VoluntaryExcess" => '0',
                "NoEmpCoverLL" => "0",
                "NoOfCleaner" => "",
                "NoOfDriver" => "1",
                "NoOfConductor" => "",
                "VehicleMadeinindiaYN" => "",
                "VehiclePurposeYN" => "",
                "NFPP_Employees"  => '',
                "NFPP_OthThanEmp" => "",
                // "LimitOwnPremiseYN" => $LimitOwnPremiseYN,
                "Bangladesh" => in_array('Bangladesh', $countries) ? 1 : 0,
                "Bhutan" => in_array('Bhutan', $countries) ? 1 : 0,
                "SriLanka" => in_array('Sri Lanka', $countries) ? 1 : 0,
                "Nepal" => in_array('Nepal', $countries) ? 1 : 0,
                "Pakistan" => in_array('Pakistan', $countries) ? 1 : 0,
                "Maldives" => in_array('Maldives', $countries) ? 1 : 0,
                "CNGKitYN" => $externalCNGKIT,
                "CNGKitSI" => $externalCNGKITSI,
                "InBuiltCNGKit" => $requestData->fuel_type == 'CNG' ? 1 : 0,
                // "LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                "DeTariff" => 0,
                "IMT23YN" => "",
                "BreakIn" => "No", // prev insu expiry date and today date
                "PreInspectionReportYN" => "0", 
                "PreInspection" => "",
                "FitnessCertificateno" => "",
                "FitnessValidupto" => "",
                "VehPermit" => "",
                "PermitNo" => "",
                "PAforUnnamedPassengerYN" => $PAforUnnamedPassenger,
                "PAforUnnamedPassengerSI" => $PAforUnnamedPassengerSI,
                "ElectricalaccessYN" => $Electricalaccess,
                "ElectricalaccessSI" => $ElectricalaccessSI,
                "ElectricalaccessRemarks" => "",
                "NonElectricalaccessYN" => $NonElectricalaccess,
                "NonElectricalaccessSI" => $NonElectricalaccessSI,
                "NonElectricalaccessRemarks" => "",
                "PAPaidDriverConductorCleanerYN" => $PAPaidDriverConductorCleaner,
                "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI,
                "PAPaidDriverCount" => "1",
                "PAPaidConductorCount" => "",
                "PAPaidCleanerCount" => "",
                "NomineeNameforPAOwnerDriver" => '',
                "NomineeAgeforPAOwnerDriver" => '',
                "NomineeRelationforPAOwnerDriver" => '',
                "AppointeeNameforPAOwnerDriver" => "",
                "AppointeeRelationforPAOwnerDriver" => "",
                "LLtoPaidDriverYN" => $LLtoPaidDriverYN,
                "AntiTheftYN" => $antitheft,
                "PreviousPolicyNo" => '',
                "PreviousInsurer" => 'Acko General Insurance Ltd',
                "PreviousPolicyFromDt" => $PreviousPolicyFromDt,
                "PreviousPolicyToDt" => $PreviousPolicyToDt,
                "PreviousPolicySI" => "",
                "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0',
                "PreviousPolicyUWYear" => "",
                "PreviousPolicyNCBPerc" => $requestData->previous_ncb ? $requestData->previous_ncb : '0',
                "PreviousPolicyType" => $PreviousPolicyType,
                "NilDepreciationCoverYN" => $productData->zero_dep == 0 ? 'Y' : '',
                "PreviousNilDepreciation" => $PreviousNilDepreciation, // addon
                "RSACover" => 'Y', // Roadside assistance
                "HypothecationType" => '',
                "HypothecationBankName" => '',
                "HypothecationAddress1" => '',
                "HypothecationAddress2" => '',
                "HypothecationAddress3" => '',
                "HypothecationAgreementNo" => '',
                "HypothecationCountry" => '',
                "HypothecationState" => '',
                "HypothecationCity" => '',
                "HypothecationPinCode" => '',
                "SpecifiedPersonField" => '',
                "PAOwnerDriverExclusion" => '0',
                "PAOwnerDriverExReason" => '',
                "PCCVVehType" => "Other Taxi",
                "VehicleManufactureYear" => $manufacture_year,
            ],
        ];

        if($requestData->rto_code == 'AP-39')
        {
            $inputArray['objPolicyEntryETT']['RTOCity'] = 'Prakasam';
        }

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' =>  [
                'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Taxi',
            'productName' => $productData->product_name,
            'method' => 'Premium Calculation',
            'transaction_type' => 'quote',
        ];

        $checksum_data = checksum_encrypt($inputArray);
        $additional_data['checksum'] =  $checksum_data;
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'shriram', $checksum_data, 'CV');
        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
            
            $get_response = $is_data_exits_for_checksum;
        }
        else
        {
        $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_PCV_JSON_QUOTE_URL'), $inputArray, 'shriram', $additional_data);
        }
        $response = $get_response['response'];
        if ($response)
        {
            $response = json_decode($response, true);

            $idv = $min_idv = $max_idv = 0;
            $skip_second_call = false;
            if ($response['MessageResult']['Result'] == 'Success')
            {
                if ( ! in_array($premium_type, ['third_party', 'third_party_breakin']))
                {
                    $idv = $response['GetQuotResult']['VehicleIDV'];
                    $min_idv = (int) ceil((int) $idv * 0.85);
                    $max_idv = (int) floor((int) $idv * 1.2);

                    if ($requestData->is_idv_changed == 'Y')
                    {                       	
                        if ($requestData->edit_idv >= $max_idv)
                        {
                            $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $max_idv;
                        }
                        elseif ($requestData->edit_idv <= $min_idv)
                        {
                            $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                        }
                        else
                        {
                            $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $requestData->edit_idv;
                        }
                    }
                    else
                    {
                        #$inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv;
                                $skip_second_call = true;
                                break;
                            case 'min_idv':
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                                break;
                            case 'max_idv':
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $max_idv;
                                break;
                            default:
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                                break;
                        }
                    }

                    $additional_data = [
                        'enquiryId' => $enquiryId,
                        'headers' =>  [
                            'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                            'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json'
                        ],
                        'requestMethod' => 'post',
                        'requestType' => 'json',
                        'section' => 'Taxi',
                        'productName' => $productData->product_name,
                        'method' => 'Premium Recalculation',
                        'transaction_type' => 'quote',
                    ];
                    if(!$skip_second_call){
                        $checksum_data = checksum_encrypt($inputArray);
                        $additional_data['checksum'] =  $checksum_data;
                        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'shriram', $checksum_data, 'CV');
                        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                            
                            $get_response = $is_data_exits_for_checksum;
                        }
                        else
                        {
                            $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_PCV_JSON_QUOTE_URL'), $inputArray, 'shriram', $additional_data);
                        }
                    }
                    $response = $get_response['response'];
                    if ($response)
                    {
                        $response = json_decode($response, true);

                        if ( ! isset($response['MessageResult']['Result']) || $response['MessageResult']['Result'] != 'Success')
                        {
                            return [
                                'status' => false,
                                'premium_amount' => 0,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $response['MessageResult']['ErrorMessage'] ?? 'Insurer not reachable'
                            ];
                        }

                        $idv = $response['GetQuotResult']['VehicleIDV'];
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'premium_amount' => 0,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Insurer not reachable'
                        ];
                    }
                }

                $final_payable_amount = $final_net_premium = $final_od_premium = $final_tp_premium = $basic_tp = $ncb_discount = $rsapremium = $anti_theft = $other_discount = $zero_dep_amount = $pa_paid_driver = $pa_owner = $non_electrical_accessories = $lpg_cng_tp = $lpg_cng = $electrical_accessories = $basic_od = $rsapremium = $geoextensionod = $geoextensiontp = $tppd_discount = $limited_to_own_premises = $pa_unnamed = $ll_paid_driver = 0;

                foreach ($response['GetQuotResult']['CoverDtlList'] as $key => $value)
                {
                    if ($value['CoverDesc'] == 'Road Side Assistance - OD')
                    {
                        $rsapremium = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Basic Premium - OD')
                    {
                        $basic_od = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR41-Cover For Electrical and Electronic Accessories - OD')
                    {
                        $electrical_accessories = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'] , ['InBuilt  CNG  Cover - OD','InBuilt CNG Cover - OD']))
                    {
                        $lpg_cng = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'] , ['InBuilt  CNG  Cover - TP','InBuilt CNG Cover - TP']))
                    {
                        $lpg_cng_tp = $value['Premium'];
                    }

                    /* if ($value['CoverDesc'] == 'InBuilt  CNG  Cover' || $value['CoverDesc'] == 'GR42-Outbuilt CNG\/LPG-Kit-Cover' || $value['CoverDesc'] ==  'GR42-Outbuilt CNG/LPG-Kit-Cover')
                    {
                        if ($value['Premium'] == 60)
                        {
                            $lpg_cng_tp = $value['Premium'];
                        }
                        else
                        {
                            $lpg_cng = $value['Premium'];
                        }
                    } */

                    if ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - OD') {
                        $lpg_cng = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - TP') {
                        $lpg_cng_tp = $value['Premium'];
                    }

                    /* if ($value['CoverDesc'] == 'GR4-Geographical Extension')
                    {
                        if ($value['Premium'] == 100)
                        {
                            $geoextensiontp = $value['Premium'];
                        }
                        else
                        {
                            $geoextensionod = $value['Premium'];
                        }
                    } */

                    if ($value['CoverDesc'] == 'GR4-Geographical Extension - OD') {
                        $geoextensionod = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR4-Geographical Extension - TP') {
                        $geoextensiontp = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Cover For Non Electrical Accessories - OD')
                    {
                        $non_electrical_accessories = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER - TP')
                    {
                        $pa_owner = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Legal Liability Coverages For Paid Driver - TP')
                    {
                        $ll_paid_driver = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR39A-Limit The Third Party Property Damage Cover - TP')
                    {
                        $tppd_discount = abs($value['Premium']);
                    }

                    if ($value['CoverDesc'] == 'GR35-Cover For Limited To Own Premises')
                    {
                        $limited_to_own_premises = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Nil Depreciation Cover - OD' || $value['CoverDesc'] == 'Nil Depreciation Loading - OD')
                    {
                        $zero_dep_amount += $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'De-Tariff Discount - OD')
                    {
                        $other_discount = abs($value['Premium']);
                    }

                    if ($value['CoverDesc'] == 'GR30-Anti Theft Discount Cover')
                    {
                        $anti_theft = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Basic Premium - TP')
                    {
                        $basic_tp = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR36B3-PA-Paid Driver, Conductor,Cleaner - TP')
                    {
                        $pa_paid_driver = $value['Premium'];
                    }
                }

                $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $geoextensionod;
                $ncb_discount = ($final_od_premium - $other_discount) * ($requestData->applicable_ncb / 100);
                $final_tp_premium = $basic_tp + $lpg_cng_tp + $pa_unnamed + $pa_paid_driver + $ll_paid_driver + $geoextensiontp;
                $final_total_discount = $ncb_discount + $other_discount + $limited_to_own_premises + $tppd_discount;
                $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount;
                $final_gst_amount = $final_net_premium * 0.18;
                $final_payable_amount = $final_net_premium + $final_gst_amount;

                $applicable_addons = ['zeroDepreciation', 'roadSideAssistance'];

                if ($productData->zero_dep == 0 && $zero_dep_amount == 0)
                {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                        'request' => [
                            'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                        ]
                    ];
                }          
                $business_types = [
                    'rollover' => 'Rollover',
                    'newbusiness' => 'New Business',
                    'breakin' => 'Break-in'
                ];

                $data_response = [
                    'status' => true,
                    'msg' => 'Found',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Data' => [
                        'idv' => $idv,
                        'min_idv' => $min_idv,
                        'max_idv' => $max_idv,
                        'vehicle_idv' => $idv,
                        'qdata' => null,
                        'pp_enddate' => $requestData->previous_policy_expiry_date,
                        'addonCover' => null,
                        'addon_cover_data_get' => '',
                        'rto_decline' => null,
                        'rto_decline_number' => null,
                        'mmv_decline' => null,
                        'mmv_decline_name' => null,
                        'policy_type' => $tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $rto_code,
                        'rto_no' => $rto_code,
                        'version_id' => $requestData->version_id,
                        'selected_addon' => [],
                        'showroom_price' => 0,
                        'fuel_type' => $requestData->fuel_type,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'tppd_discount' => $tppd_discount,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                        'product_name' => $productData->product_sub_type_name,
                        'mmv_detail' => $mmv_data,
                        'master_policy_id' => [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $productData->policy_no,
                            'policy_start_date' => $policy_start_date,
                            'policy_end_date' => $policy_end_date,
                            'sum_insured' => $productData->sum_insured,
                            'corp_client_id' => $productData->corp_client_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'insurance_company_id' => $productData->company_id,
                            'status' => $productData->status,
                            'corp_name' => '',
                            'company_name' => $productData->company_name,
                            'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'predefine_series' => '',
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online,
                        ],
                        'motor_manf_date' => $requestData->vehicle_register_date,
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'vehicleDiscountValues' => [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => '0',
                            'rto_cluster_id' => '0',
                            'car_age' => 2, //$car_age,
                            'aai_discount' => 0,
                            'ic_vehicle_discount' => round(abs($other_discount)),
                        ],
                        'ic_vehicle_discount' => round(abs($other_discount)),
                        'basic_premium' => round($basic_od),
                        'motor_electric_accessories_value' => round($electrical_accessories),
                        'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                        'motor_lpg_cng_kit_value' => round($lpg_cng),
                        'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                        'total_own_damage' => round($final_od_premium),
                        'tppd_premium_amount' => round($basic_tp),
                        'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                        'GeogExtension_ODPremium' => $geoextensionod,
                        'GeogExtension_TPPremium' => $geoextensiontp,
                        'default_paid_driver' => round($ll_paid_driver),
                        'll_paid_driver_premium' => $ll_paid_driver,
                        'll_paid_conductor_premium' => 0,
                        'll_paid_cleaner_premium' => 0,
                        'motor_additional_paid_driver' => round($pa_paid_driver),
                        'cng_lpg_tp' => round($lpg_cng_tp),
                        'seating_capacity' => $mmv_data->veh_seat_cap,
                        'deduction_of_ncb' => round(abs($ncb_discount)),
                        'aai_discount' => '', //$automobile_association,
                        'voluntary_excess' => '', //$voluntary_excess,
                        'other_discount' => round(abs($other_discount)),
                        'total_liability_premium' => round($final_tp_premium),
                        'net_premium' => round($final_net_premium),
                        'service_tax_amount' => round($final_gst_amount),
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'quotation_no' => '',
                        'premium_amount' => round($final_payable_amount),
                        'service_data_responseerr_msg' => 'success',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type] ?? 'Rollover',
                        'service_err_code' => null,
                        'service_err_msg' => null,
                        'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                        'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                        'ic_of' => $productData->company_id,
                        'vehicle_in_90_days' => $vehicle_in_90_days,
                        'get_policy_expiry_date' => null,
                        'get_changed_discount_quoteid' => 0,
                        'vehicle_discount_detail' => [
                            'discount_id' => null,
                            'discount_rate' => null,
                        ],
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                        'policy_id' => $productData->policy_id,
                        'insurane_company_id' => $productData->company_id,
                        'max_addons_selection' => null,
                        'add_ons_data' => [
                            'in_built' => [],
                            'additional' => [
                                'road_side_assistance' => round($rsapremium)
                            ],
                        ],
                        'applicable_addons' => $applicable_addons,
                        'final_od_premium' => round($final_od_premium),
                        'final_tp_premium' => round($final_tp_premium),
                        'final_total_discount' => round(abs($final_total_discount)),
                        'final_net_premium' => round($final_net_premium),
                        'final_gst_amount' => round($final_gst_amount),
                        'final_payable_amount' => round($final_payable_amount),
                        'mmv_detail' => [
                            'manf_name' => $mmv_data->manf,
                            'model_name' => $mmv_data->model_desc,
                            'version_name' => '',//$mmv_data->model_desc,
                            'fuel_type' => $mmv_data->fuel,
                            'seating_capacity' => $mmv_data->veh_seat_cap,
                            'carrying_capacity' => $mmv_data->veh_seat_cap,
                            'cubic_capacity' => $mmv_data->veh_cc,
                            'gross_vehicle_weight' => '',
                            'vehicle_type' => 'Taxi',
                        ],
                    ],
                ];

                if ($productData->zero_dep == 0 && $zero_dep_amount > 0)
                {
                    $data_response['Data']['add_ons_data']['in_built']['zero_depreciation'] = round($zero_dep_amount);
                }

                if ($data_response['Data']['motor_lpg_cng_kit_value'] == 0)
                {
                    unset($data_response['Data']['motor_lpg_cng_kit_value']);
                }

                if ($data_response['Data']['cng_lpg_tp'] == 0)
                {
                    unset($data_response['Data']['cng_lpg_tp']);
                }

                if ($data_response['Data']['motor_additional_paid_driver'] == 0)
                {
                    unset($data_response['Data']['motor_additional_paid_driver']);
                }

                if ($data_response['Data']['motor_electric_accessories_value'] == 0)
                {
                    unset($data_response['Data']['motor_electric_accessories_value']);
                }

                if ($data_response['Data']['motor_non_electric_accessories_value'] == 0)
                {
                    unset($data_response['Data']['motor_non_electric_accessories_value']);
                }

                if ($data_response['Data']['GeogExtension_ODPremium'] == 0)
                {
                    unset($data_response['Data']['GeogExtension_ODPremium']);
                }

                return camelCase($data_response);
            }
            else
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'msg' => $response['MessageResult']['ErrorMessage'] ?? 'Insurer not reachable'
                ];
            }
        }
        else
        {
            return [
                'status' => false,
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ];
        }
    }
}

function getXmlPcvQuotes($enquiryId, $requestData, $productData)
{
    
    $refer_webservice = $productData->db_config['quote_db_cache'];
    // if(($requestData->ownership_changed ?? '' ) == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }
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
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];          
    }
    
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code, true);

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $policy_type = ($premium_type == 'comprehensive' ? 'MOT-PLT-001' : 'MOT-PLT-002');
    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    if ($requestData->business_type == 'newbusiness') {
        $policy_start_date = Carbon::parse(date('d-m-Y'));
    } elseif ($requestData->business_type == 'breakin') {
        $policy_start_date = Carbon::parse(date('d-m-Y', strtotime('+2 day', time())));
    } elseif ($requestData->business_type == 'rollover') {
        $policy_start_date = Carbon::parse(date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date))));
    }
    $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
    if ($requestData->previous_policy_type == 'Third-party' && $tp_only == 'false') {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes not available for Previous policy as Third-Party.',
            'request' => [
                'requestData' => $requestData,
                'previous_policy_type' => $requestData->previous_policy_type,
                'message' => 'Quotes not available for Previous policy as Third-Party.',
            ]
        ]; 
    }
    $manufacture_year = explode('-',$requestData->manufacture_year)[1];
    if ($requestData->business_type == 'newbusiness') {
        $Registration_Number = $rto_code;
        $PreviousNCB = '0';
        $proposalType = 'FRESH';
        $VehicleType = 'W';
        $PreviousPolicyFromDt = $PreviousPolicyToDt = $PreviousNilDepreciation = $PreviousPolicyType = $previous_ncb = '';
        $break_in = 'NO';
        $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
        $vehicle_in_90_days = 'N';
    } else {
        $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
        $proposalType = "RENEWAL OF OTHERS";
        $VehicleType = 'U';
        $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-m-Y');
        $PreviousPolicyToDt = Carbon::parse($requestData->previous_policy_expiry_date)->format('d-m-Y');
        $PreviousPolicyType = $requestData->previous_policy_type == 'Third-party' ? "MOT-PLT-002" : "MOT-PLT-001";
        $PreviousNilDepreciation = 1;
        $previous_ncb = $requestData->previous_ncb;
        if ($requestData->vehicle_registration_no != '') {
            $Registration_Number = $requestData->vehicle_registration_no;
        } else {
            $Registration_Number = $rto_code;
        }
        // $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        // $break_in = (Carbon::parse($requestData->previous_policy_expiry_date)->diffInDays($policy_start_date) > 0) ? 'YES' : 'NO';
        $break_in = "No";
        $vehicle_in_90_days = 'N';
    }
    
    // Addons And Accessories
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $ElectricalaccessSI = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new \DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $car_age = ceil($age / 12);
    // zero depriciation validation
    $nilDepreciationCover = 1;
    $applicable_addon = [];
    // if ( $car_age > 5 ) {
    //     $nilDepreciationCover = 0;
    //     $PreviousNilDepreciation = 0;
    // }else{
        array_push($applicable_addon, "zeroDepreciation");
    // }
    $roadsideassistance = 1;
    // if ( $car_age > 12 ) {
        // $roadsideassistance = 0;
    // }else{
        array_push($applicable_addon, "roadSideAssistance");
    // }
    if ($tp_only == 'true') {
        $applicable_addon = [];
        $nilDepreciationCover = $PreviousNilDepreciation = $roadsideassistance = 0;
    }
    $LimitedTPPDYN = 0;
    foreach ($discounts as $key => $value) {
        if (in_array('TPPD Cover', $value)) {
            $LimitedTPPDYN = 1;
        }
    }
    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value) && $tp_only == 'false') {
            $Electricalaccess = 1;
            $ElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('Non-Electrical Accessories', $value) && $tp_only == 'false') {
            $NonElectricalaccess = 1;
            $NonElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
            $externalCNGKIT = 1;
            $externalCNGKITSI = $value['sumInsured'];
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
        // Not allowed for PCV
        // if (in_array('Unnamed Passenger PA Cover', $value)) {
        //     $PAforUnnamedPassenger = 1;
        //     $PAforUnnamedPassengerSI = $value['sumInsured'];
        // }
    }

    // $PAOwnerDriverExclusion = "1";
    // $PAOwnerDriverExReason = "PA_TYPE2";
    // if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
    //     $PAOwnerDriverExclusion = "0";
    //     $PAOwnerDriverExReason = "";
    // }
    $PAOwnerDriverExclusion = "0";
    $PAOwnerDriverExReason = "";
    if($requestData->vehicle_owner_type != 'I'){
        $PAOwnerDriverExclusion = "1";
        $PAOwnerDriverExReason = "PA_TYPE1";
    }

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

    //XML 
    $inputArray = [
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
                'objPolicyEntryETT' => [
                    'ReferenceNo'   => '',
                    'ProdCode'      => 'MOT-PRD-005',
                    'PolicyFromDt'  => $policy_start_date->format('d/m/Y'),
                    'PolicyToDt'    => date('d/m/Y', strtotime($policy_end_date)),
                    'PolicyIssueDt' => today()->format('d/m/Y'),
                    'InsuredPrefix' => ($requestData->vehicle_owner_type == 'I') ? '1': '3',
                    'InsuredName'   => 'Test Test',//Hardcoded value - then only quotes will come on production
                    'Gender'        => ($requestData->vehicle_owner_type == 'C') ? '' : 'M',
                    'Address1'      =>'Abc',
                    'Address2'      =>'',
                    'Address3'      =>'',
                    'State'         => explode('-', $rto_code)[0],//'MH',
                    'City'          => 'Mumbai',
                    'PinCode'       => '400001',
                    'PanNo'         => '',
                    'GSTNo'         => '',
                    'TelephoneNo'   => '',
                    'ProposalType'  => $proposalType,
                    'PolType'       => $policy_type,
                    'DateOfBirth'   => ($requestData->vehicle_owner_type == 'C') ? '' : '01/01/1991',
                    'MobileNo'      => $requestData->user_mobile ?? '9854547812',
                    'FaxNo'         => '',
                    'EmailID'       => $requestData->user_email ?? 'ABSTES@gmail.com',
                    "POSAgentName"  => $posAgentName,
                    "POSAgentPanNo" => $posAgentPanNo,
                    'CoverNoteNo'   => '',
                    'CoverNoteDt'   =>'',
                    'VehicleCategory' => "CLASS_4C1A", //$mmv_data->veh_category, /*  */
                    'VehicleCode'   => $mmv_data->veh_code,
                    'FirstRegDt'    => Carbon::createFromFormat('d-m-Y', $requestData->vehicle_register_date)->format('d/m/Y'), //  car registration date
                    'VehicleType' => $VehicleType,
                    'EngineNo'  => '4364374765856865865',
                    'ChassisNo' => '8658658567457436535',
                    'RegNo1'    => explode('-', $rto_code)[0],
                    'RegNo2'    => explode('-', $rto_code)[1],
                    'RegNo3'    => !empty($vehicale_registration_number[2]) ? substr($vehicale_registration_number[2], 0, 3) : 'TT', // "OK",
                    'RegNo4'    => $vehicale_registration_number[3] ?? '4521', // "4521",
                    'RTOCode'   => $rto_code, /*  */
                    'IDV_of_Vehicle'    => '',
                    'Colour'            => 'Red',
                    'VoluntaryExcess'   => '0',//($requestData->business_type == 'rollover') ? 'PCVE1' : 'PCVE2',
                    'NoEmpCoverLL'      => '0',
                    'NoOfCleaner'       => '0',
                    'NoOfDriver'        => $LLtoPaidDriverYN == 0 ? '0' : '1',
                    'NoOfConductor'     => '0',
                    'VehicleMadeinindiaYN' => '1',
                    'VehiclePurposeYN'  => '',
                    'NFPP_Employees'    => '',
                    'NFPP_OthThanEmp'   => '',
                    'LimitOwnPremiseYN' => '0',
                    'Bangladesh'        => '0',
                    'Bhutan'            => '0',
                    'SriLanka'          => '0',
                    'Nepal'             => '0',
                    'Pakistan'          => '0',
                    'Maldives'          => '0',
                    'CNGKitYN'          => $externalCNGKIT, // input page/*  */
                    'CNGKitSI'          => $externalCNGKITSI, //$externalCNGKITSI, //input/*  */
                    'InBuiltCNGKit'     => $requestData->fuel_type == 'CNG' ? '1' : '0', // maSTER and  fuel type/*  */,
                    'LimitedTPPDYN'     => $LimitedTPPDYN,
                    'DeTariff'          => 0,//$productData->default_discount,
                    'IMT23YN'           => '0',
                    'BreakIn'           => 'NO',
                    'PreInspectionReportYN' => '',
                    'PreInspection'     => '',
                    'FitnessCertificateno' => '',
                    'FitnessValidupto'  => '',
                    'VehPermit'         => '',
                    'PermitNo'          => '',
                    'PAforUnnamedPassengerYN'   => $PAforUnnamedPassenger, // addon quote page /*  */,
                    'PAforUnnamedPassengerSI'   => $PAforUnnamedPassengerSI, // addon quote page   user addon table /*  */
                    'ElectricalaccessYN'        => $Electricalaccess, // addon quote page /*  */,
                    'ElectricalaccessSI'        => $ElectricalaccessSI, // addon quote page   user addon table /*  */
                    'ElectricalaccessRemarks'   =>'electric',
                    'NonElectricalaccessYN'     => $NonElectricalaccess, // addon quote page   user addon table /*  */
                    'NonElectricalaccessSI'     => $NonElectricalaccessSI, // addon quote page   user addon table /*  */
                    'NonElectricalaccessRemarks' =>'non electric',
                    'PAPaidDriverConductorCleanerYN' => $PAPaidDriverConductorCleaner, // addon /*  */
                    'PAPaidDriverConductorCleanerSI' => $PAPaidDriverConductorCleanerSI, // addon /*  */
                    'PAPaidDriverCount'         => '1',
                    'PAPaidConductorCount'      => '',
                    'PAPaidCleanerCount'        => '',
                    'NomineeNameforPAOwnerDriver' => ($requestData->vehicle_owner_type == 'C') ? '' : 'Test Nominee',
                    'NomineeAgeforPAOwnerDriver' =>  ($requestData->vehicle_owner_type == 'C') ? '' : '30',
                    'NomineeRelationforPAOwnerDriver' =>  ($requestData->vehicle_owner_type == 'C') ? '' : 'Brother',
                    'AppointeeNameforPAOwnerDriver' => '',
                    'AppointeeRelationforPAOwnerDriver' => '',
                    'LLtoPaidDriverYN'          => $LLtoPaidDriverYN,
                    'AntiTheftYN'               => $antitheft, // addon /*  */
                    'PreviousPolicyNo'          => ($requestData->business_type == 'rollover') ? 'AFAFSAFSAFASFSAF' : '',
                    'PreviousInsurer'           => ($requestData->business_type == 'rollover') ? 'BHARATI AXA GENERAL INSURANCE CO LTD' : '',
                    'PreviousPolicyFromDt'      => ($requestData->business_type == 'newbusiness') ? '' : Carbon::createFromFormat('d-m-Y', $PreviousPolicyFromDt)->format('d/m/Y'),
                    'PreviousPolicyToDt'        => ($requestData->business_type == 'rollover') ? Carbon::createFromFormat('d-m-Y', $PreviousPolicyToDt)->format('d/m/Y') : '',
                    'PreviousPolicySI'          => '',
                    'PreviousPolicyClaimYN'     => $requestData->is_claim == 'Y' ? '1' : '0', // input /*  */
                    'PreviousPolicyUWYear'      => '',
                    'PreviousPolicyNCBPerc'     => (int) $previous_ncb, // prev ncb % /*  */,
                    'PreviousPolicyType'        => ($requestData->business_type == 'newbusiness') ? '' : $PreviousPolicyType,
                    'NilDepreciationCoverYN'    => $nilDepreciationCover, // addon zero deprecian /*  */
                    'PreviousNilDepreciation'   => $PreviousNilDepreciation, // addon /*  */,
                    'RSACover'                  => $roadsideassistance,
                    'HypothecationType'         => '',
                    'HypothecationBankName'     => '',
                    'HypothecationAddress1'     => '',
                    'HypothecationAddress2'     => '',
                    'HypothecationAddress3'     => '',
                    'HypothecationAgreementNo'  => '',
                    'HypothecationCountry'      => '',
                    'HypothecationState'        => '',
                    'HypothecationCity'         => '',
                    'HypothecationPinCode'      => '',
                    'SpecifiedPersonField'      => '',
                    'PAOwnerDriverExclusion'    => $PAOwnerDriverExclusion, // addon 
                    'PAOwnerDriverExReason'     => $PAOwnerDriverExReason, //"PA_TYPE2",  // master /*  */
                    'PCCVVehType' => "Other Taxi",
                    "VehicleManufactureYear" => $manufacture_year,
                ],
                '_attributes' => [
                    "xmlns" => "http://tempuri.org/"
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
        'method' => $tp_only == 'true' ? 'TP Quote' : 'Quote',
        'transaction_type' => 'quote',
    ];
    $root = [
        'rootElementName' => 'soap:Envelope',
        '_attributes' => [
            "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
            "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
            "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
        ]
    ];
    
    $input_array = ArrayToXml::convert($inputArray, $root, false, 'utf-8');
    $checksum_data = checksum_encrypt($input_array);
    $additional_data['checksum'] =  $checksum_data;
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'shriram', $checksum_data, 'CV');
    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
        
        $get_response = $is_data_exits_for_checksum;
    }
    else
    {
        $get_response = getWsData(config('constants.cv.shriram.SHRIRAM_XML_PROPOSAL_SUBMIT_URL'), $input_array, 'shriram', $additional_data);
    }
    $response = XmlToArray::convert($get_response['response']);
    
    if (isset($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE'])
        && ($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE'] == 0)) 
    {
        $skip_second_call = false;
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "{$additional_data['method']} Success", "Success" );
        $quote_response1 = $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult'];
        if ($tp_only == 'true') {
            $idv = $idv_min = $idv_max = 0;
        }else{
            $idv = $quote_response1['VehicleIDV'];
            $idv_min = (string) ceil(0.85 * $quote_response1['VehicleIDV']);
            $idv_max = (string) floor(1.20 * $quote_response1['VehicleIDV']);
        }
        if ($requestData->is_idv_changed == 'Y' && $tp_only == 'false')
        {
            if ($requestData->edit_idv >= floor($idv_max))
            {
                $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = floor($idv_max);
                $idv = $idv_max;
            }
            elseif ($requestData->edit_idv <= ceil($idv_min))
            {
                $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = ceil($idv_min);
                $idv = $idv_min;
            }
            else 
            {
                $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = $requestData->edit_idv;
                $idv = $requestData->edit_idv;
            }
        }
        else
        {
            /* $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv_min;
            $idv = $idv_min; */
            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv;
                    $skip_second_call = true;
                    $idv = $idv;
                    break;
                case 'min_idv':
                    $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv_min;
                    $idv = $idv_min;
                    break;
                case 'max_idv':
                    $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv_max;
                    $idv = $idv_max;
                    break;
                default:
                    $inputArray['soap:Body']['GeneratePCCVProposal']['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv_min;
                    $idv = $idv_min;
                    break;
            }
        }
        // http://119.226.131.2/ShriramService/ShriramService.asmx
        // http://119.226.131.2/ShriramService/ShriramService.asmx?op=GenerateProposal
        if ($tp_only == 'false' && !$skip_second_call) {
            $additional_data['method'] = 'Premium Re Calculation';
            $input_array = ArrayToXml::convert($inputArray, $root, false, 'utf-8');
            $checksum_data = checksum_encrypt($input_array);
            $additional_data['checksum'] =  $checksum_data;
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'shriram', $checksum_data, 'CV');
            if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                
                $get_response = $is_data_exits_for_checksum;
            }
            else
            {
                $get_response = getWsData(config('constants.cv.shriram.SHRIRAM_XML_PROPOSAL_SUBMIT_URL'), $input_array, 'shriram', $additional_data);
            }
            $response = XmlToArray::convert($get_response['response']);
        }

        if (!isset($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE']) || $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_CODE'] != '0') {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_DESC'] ?? 'Insurer Not Reachable',
            ];
        }
        $final_payable_amount = $cgst = $sgst = $igst = $final_net_premium = $final_od_premium = $final_tp_premium = $tppd = $ncb_discount = $rsapremium = $anti_theft = $other_discount = $zero_dep_amount = $pa_paid_driver = $pa_owner = $ll_paid_driver =$non_electrical_accessories = $lpg_cng_tp = $lpg_cng = $electrical_accessories = $basic_od = 
        $voluntary_excess_discount = $rsapremium = $pa_unnamed = $tp_discount = 0;

        foreach ($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['CoverDtlList']['CoverDtl'] as $key => $cover) 
        {
            if ($cover['CoverDesc'] == 'BASIC OD COVER') {
                $basic_od = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'VOLUNTARY EXCESS DISCOUNT-IMT-22A') {
                $voluntary_excess_discount = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'OD TOTAL') {
                $final_od_premium = $cover['Premium'];
            } 
            if ($cover['CoverDesc'] == 'BASIC TP COVER') {
                $tppd = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'TP TOTAL') {
                $final_tp_premium = $cover['Premium'];
            } 
            if ($cover['CoverDesc'] == 'TOTAL PREMIUM') {
                $final_net_premium = $cover['Premium'];
            } 
            if ($cover['CoverDesc'] == 'IGST') {
                $igst = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'CGST') {
                $cgst = $cover['Premium'];
            } 
            if ($cover['CoverDesc'] == 'SGST') {
                $sgst = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'TOTAL AMOUNT') {
                $final_payable_amount = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                $ncb_discount = $cover['Premium'];
            }
            if (in_array($cover['CoverDesc'], array('Nil Depreciation', 'Nil Depreciation Cover'))) {
                $zero_dep_amount = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES') {
                $electrical_accessories = $cover['Premium'];
            }
            if (in_array($cover['CoverDesc'], array('GR42--CNG-KIT-COVER','CNG/LPG-KIT-COVER-GR42', 'INBUILT CNG/LPG KIT','In Built CNG Kit','In Built CNG/LPG Kit'))) {
                $lpg_cng = $cover['Premium'];
            }
            if (in_array($cover['CoverDesc'], ['GR42--CNG/LPG KIT - TP  COVER','GR42--CNG KIT - TP  COVER','CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER','IN-BUILT CNG KIT - TP  COVER', 'IN-BUILT CNG TP COVER'])) {
                $lpg_cng_tp = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'GR36B2--PA-UN-NAMED') {
                $pa_unnamed = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3') {
                $pa_paid_driver = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER') {
                $pa_owner = $cover['Premium']; // CPA
            }
            if ($cover['CoverDesc'] == 'LL TO PAID DRIVER') {
                $ll_paid_driver = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'TPPD COVER') {
                $tp_discount = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                $other_discount = $cover['Premium'];
            }
            if ($cover['CoverDesc'] == 'ROAD SIDE ASSISTANCE') {
                $rsapremium = $cover['Premium'];
            }
        }
        
        if ((int) $NonElectricalaccessSI > 0) {
            $non_electrical_accessories = (string) round(($NonElectricalaccessSI * 3.283 ) / 100);
            $basic_od = ($basic_od - $non_electrical_accessories);
        }
        
        $final_gst_amount = isset($igst) ? $igst : $sgst + $cgst;

        $final_tp_premium = $tppd + $ll_paid_driver + $lpg_cng_tp + $pa_paid_driver;
        //$final_tp_premium = $final_tp_premium - ($pa_owner);

        $final_total_discount = $ncb_discount + $other_discount + $tp_discount;

        $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;

        $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount;

        $final_payable_amount = $final_net_premium + $final_gst_amount;
        $business_types = [
            'rollover' => 'Rollover',
            'newbusiness' => 'New Business',
            'breakin' => 'Break-in'
        ];

        if ($productData->zero_dep == 0 && $zero_dep_amount == 0)
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                'request' => [
                    'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                ]
            ];
        }         
       
        if ($rsapremium == 0) {
            unset($applicable_addons['roadSideAssistance']);
        }
        
        
        $data_response = [
            'status' => true,
            'msg' => 'Found',
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'Data' => [
                'idv' => $idv,
                'min_idv' => $idv_min,
                'max_idv' => $idv_max,
                'vehicle_idv' => $idv,
                'qdata' => null,
                'pp_enddate' => $requestData->previous_policy_expiry_date,
                'addonCover' => null,
                'addon_cover_data_get' => '',
                'rto_decline' => null,
                'rto_decline_number' => null,
                'mmv_decline' => null,
                'mmv_decline_name' => null,
                'policy_type' => $tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                'cover_type' => '1YC',
                'hypothecation' => '',
                'hypothecation_name' => '',
                'vehicle_registration_no' => $rto_code,
                'rto_no' => $rto_code,
                'version_id' => $requestData->version_id,
                'selected_addon' => [],
                'showroom_price' => 0,
                'fuel_type' => $requestData->fuel_type,
                'ncb_discount' => $requestData->applicable_ncb,
                'company_name' => $productData->company_name,
                'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                'product_name' => $productData->product_sub_type_name,
                'mmv_detail' => $mmv_data,
                'master_policy_id' => [
                    'policy_id' => $productData->policy_id,
                    'policy_no' => $productData->policy_no,
                    'policy_start_date' => $policy_start_date,
                    'policy_end_date' => $policy_end_date,
                    'sum_insured' => $productData->sum_insured,
                    'corp_client_id' => $productData->corp_client_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'insurance_company_id' => $productData->company_id,
                    'status' => $productData->status,
                    'corp_name' => '',
                    'company_name' => $productData->company_name,
                    'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_sub_type_name' => $productData->product_sub_type_name,
                    'flat_discount' => $productData->default_discount,
                    'predefine_series' => '',
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                ],
                'motor_manf_date' => $requestData->vehicle_register_date,
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'vehicleDiscountValues' => [
                    'master_policy_id' => $productData->policy_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'segment_id' => '0',
                    'rto_cluster_id' => '0',
                    'car_age' => 2, //$car_age,
                    'aai_discount' => 0,
                    'ic_vehicle_discount' => round(abs($other_discount)),
                ],
                'ic_vehicle_discount' => round(abs($other_discount)),
                'basic_premium' => round($basic_od),
                'motor_electric_accessories_value' => round($electrical_accessories),
                'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                'motor_lpg_cng_kit_value' => round($lpg_cng),
                'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                'total_own_damage' => round($final_od_premium),
                'tppd_premium_amount' => round($tppd),
                'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                'cover_unnamed_passenger_value' => $pa_unnamed,
                'default_paid_driver' => $ll_paid_driver,
                'll_paid_driver_premium' => $ll_paid_driver,
                'll_paid_conductor_premium' => 0,
                'll_paid_cleaner_premium' => 0,
                'motor_additional_paid_driver' => round($pa_paid_driver),
                'cng_lpg_tp' => round($lpg_cng_tp),
                'seating_capacity' => $mmv_data->veh_seat_cap,
                'deduction_of_ncb' => round(abs($ncb_discount)),
                'antitheft_discount' => round(abs($anti_theft)),
                'aai_discount' => '', //$automobile_association,
                'voluntary_excess' => $voluntary_excess_discount, //$voluntary_excess,
                'other_discount' => round(abs($other_discount)),
                'total_liability_premium' => round($final_tp_premium),
                'tppd_discount' => round($tp_discount),
                'net_premium' => round($final_net_premium),
                'service_tax_amount' => round($final_gst_amount),
                'service_tax' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'quotation_no' => '',
                'premium_amount' => round($final_payable_amount),

                'service_data_responseerr_msg' => 'success',
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'business_type' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type],#$requestData->business_type == 'rollover' ? 'Rollover' :(($requestData->business_type == 'breakin') ? 'Break-in' : 'New Business'),
                'service_err_code' => null,
                'service_err_msg' => null,
                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                'ic_of' => $productData->company_id,
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'get_policy_expiry_date' => null,
                'get_changed_discount_quoteid' => 0,
                'vehicle_discount_detail' => [
                    'discount_id' => null,
                    'discount_rate' => null,
                ],
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online,
                'policy_id' => $productData->policy_id,
                'insurane_company_id' => $productData->company_id,
                'max_addons_selection' => null,

                'add_ons_data' => [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation' => round($zero_dep_amount),
                        'road_side_assistance' => round($rsapremium),
                    ],
                    'in_built_premium'   => 0,
                    'additional_premium' => array_sum( [ $zero_dep_amount, $rsapremium] ),
                    'other_premium'      => 0,
                ],
                'applicable_addons' => $applicable_addon,
                'final_od_premium' => round($final_od_premium),
                'final_tp_premium' => round($final_tp_premium),
                'final_total_discount' => round(abs($final_total_discount)),
                'final_net_premium' => round($final_net_premium),
                'final_gst_amount' => round($final_gst_amount),
                'final_payable_amount' => round($final_payable_amount),
                'mmv_detail' => [
                    'manf_name' => $mmv_data->manf,
                    'model_name' => $mmv_data->model_desc,
                    'version_name' => '',//$mmv_data->model_desc,
                    'fuel_type' => $mmv_data->fuel,
                    'seating_capacity' => $mmv_data->veh_seat_cap,
                    'carrying_capacity' => $mmv_data->veh_seat_cap,
                    'cubic_capacity' => $mmv_data->veh_cc,
                    'gross_vehicle_weight' => '',
                    'vehicle_type' => 'Taxi',
                ],
            ],
        ];

        return camelCase($data_response);
    } 
    else if(isset($response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_DESC']))
    {
        return [
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'msg' => $response['soap:Body']['GeneratePCCVProposalResponse']['GeneratePCCVProposalResult']['ERROR_DESC'],
        ];
    }
    else
    {
        return [
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'msg' => 'Insurer Not Reachable',
        ];
    }
}