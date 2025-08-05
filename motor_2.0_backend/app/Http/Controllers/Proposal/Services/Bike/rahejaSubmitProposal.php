<?php

namespace App\Http\Controllers\Proposal\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Models\UserProposal;
use App\Models\MasterPremiumType;
use App\Models\RahejaRtoLocation;
use App\Models\RahejaMotorPincodeMaster;

class rahejaSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $additional_covers=[];
        $applicable_addons=[];
        $Prev_policy_code = "1";
        $Prev_policy_name = "COMPREHENSIVE";
        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $nominee_rel_text = $proposal->nominee_name;
        $nominee_rel = $proposal->nominee_relationship;
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
            if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed because zero dep is not part of your previous policy',
            ];
        }
        if($premium_type =='third_party_breakin'){
            $premium_type ='third_party';
        }
        if($premium_type =='own_damage_breakin'){
            $premium_type ='own_damage';
        }
        if ($premium_type == "third_party")
        {
            $ncb_discount_rate = 0;
        }
        else
        {
            $ncb_discount_rate = $requestData->applicable_ncb;
        }
        $BusinessTypeID='25';
        $PHNumericFeild1='1';
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $ProductCode='2312';
            $CoverType='1476';
            $Tennure='118';

            $ProductName='TWO WHEELER PACKAGE POLICY(2312)';
            if($premium_type == "third_party"){
                $ProductCode='2325';
                $ProductName='TWO WHEELER LIABILITY POLICY(2325)';
                $CoverType='1694';
                $Tennure='155';
            }
        } elseif ($requestData->business_type == 'newbusiness'){
            $BusinessTypeID='24';
            $ProductCode='2369';
            $CoverType='1480';
            $Tennure='111';
            $PHNumericFeild1='5';
            $ProductName='TWO WHEELER BUNDLED POLICY(2369)';
            if($premium_type == "third_party"){
                $ProductCode='2326';
                $ProductName='TWO WHEELER LONG TERM LIABILITY POLICY(2326)';
                $CoverType='1695';
                $Tennure='157';
            }
        } else {
            $ProductCode='2324';
            $CoverType='1686';
            $Tennure='153';
            $ProductName='TWO WHEELER STANDALONE OD(2324)';
        }

        $proposal_addtional_details = json_decode($proposal->additional_details, true);
        $reg_no = explode('-', $proposal->vehicale_registration_number);
        $tpOnly = ($premium_type == "third_party");
        $odOnly = ($premium_type == "own_damage");
        if ($proposal->vehicale_registration_number == 'NEW') {
            $permitAgency  = str_replace("-", "", $requestData->rto_code);
        }
        if($requestData->business_type != "newbusiness") {
                if($reg_no[0] != "") {
                    $permitAgency = $reg_no[0] . '' . $reg_no[1];
                } else {
                    $reg_no = explode('-', $proposal_addtional_details['vehicle']['regNo1']);
                    $permitAgency = $reg_no[0] . '' . $reg_no[1];
                }
        }
        $rto_data = RahejaRtoLocation::where('rto_code',$permitAgency)
                        ->select('*')->first();
        $pincode_area = RahejaMotorPincodeMaster::where('pincode', $proposal->pincode)
                        ->first();
        $mmv_data = get_mmv_details($productData,$requestData->version_id,'raheja');
        $mmv_data = (object) array_change_key_case((array) $mmv_data,CASE_LOWER);
        $mmv_data  = $mmv_data->data;
        $vehicleDetails = [
            'manufacture_name' => $mmv_data['make_desc'],
            'model_name' => $mmv_data['model_desc'],
            'version' => $mmv_data['variant'],
            'fuel_type' => $mmv_data['Fuel_Type'],
            'seating_capacity' => $mmv_data['Seating_Capacity'],
            'carrying_capacity' => $mmv_data['Seating_Capacity'],
            'cubic_capacity' => $mmv_data['cc'],
            'gross_vehicle_weight' => '',
            'vehicle_type' => '4w'
        ];
        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('Y-m-d');
            $previousNoClaimBonus = '0';
            $claims_made = 'false';
            $applicable_ncb = 0;
            $policy_end_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime($policy_start_date)));
            $saod_policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $previous_insurername = '';
            $previousinsurer_policyexpirydate = '';
            $previousinsurer_policystartdate ='';
            $previous_insurercode = '';
            $cpa_start_date = date('Y-m-d');
            $cpa_end_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime($cpa_start_date)));
            $tpp_start_date = date('Y-m-d');
            $tpp_end_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime($tpp_start_date)));

        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $saod_policy_end_date = '';
            if ($requestData->applicable_ncb == 0) {
                $claims_made = 'false';
                $no_claim_bonus_val = '0';
                $no_claim_bonus = ['ZERO', 'TWENTY', 'TWENTY_FIVE', 'THIRTY_FIVE', 'FORTY_FIVE', 'FIFTY', 'FIFTY_FIVE', 'SIXTY_FIVE'];
                $previousNoClaimBonus = $no_claim_bonus[$no_claim_bonus_val];
            } else {
                $claims_made = 'true';
                $applicable_ncb = 0;
                $previousNoClaimBonus = '0';
            }
            $previousinsurer_policyexpirydate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            $previousinsurer_policystartdate =date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $cpa_start_date = "";
            $cpa_end_date = "";
            $tp_start_date = date('Y-m-d', strtotime($proposal->tp_start_date));
            $tp_end_date = date('Y-m-d', strtotime($proposal->tp_start_date));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $previous_insurercode = $proposal->additonal_data['prepolicy']['previousInsuranceCompany'];
            $tp_insurercode = $proposal->tp_insurance_company;
        }
        if($requestData->business_type == 'breakin'){
            $saod_policy_end_date = '';
            $policy_start_date = date('Y-m-d');
            $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime($policy_start_date)));
            $policy_end_date=date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $previousinsurer_policystartdate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $TPPolicyStartDate = $policy_start_date;
            $TPPolicyEndDate = date('Y-m-d', strtotime('+5 year -1 day', strtotime($TPPolicyStartDate)));
            $previous_insurercode = $proposal->additonal_data['prepolicy']['previousInsuranceCompany'];
        }
        if($proposal->vehicale_registration_number != null ){
            $registration_no = explode('-',$proposal->vehicale_registration_number);
        } else {
            $registration_no = explode('-',$proposal_addtional_details['vehicle']['vehicaleRegistrationNumber']);
        }
        if($requestData->business_type == "newbusiness") {
            $licencePlate = $permitAgency;
            $registration_no[3] = '';$registration_no[2] = '';
        } else {
            $licencePlate = $permitAgency . $registration_no[2] . $registration_no[2];
        }
        $registration_no_add = '';
        if(strlen($registration_no[3]) < 1) {
            $registration_no_add = '0000';
        }
        elseif(strlen($registration_no[3]) < 2) {
            $registration_no_add = '000';
        }
        elseif(strlen($registration_no[3]) < 3) {
            $registration_no_add = '00';
        }
        elseif(strlen($registration_no[3]) < 4) {
            $registration_no_add = '0';
        }
        else {
            $registration_no[3];
        }
        $isPOSP = false;
        $pospName = '';
        $pospUniqueNumber = '';
        $pospLocation = '';
        $pospPanNumber = '';
        $pospAadhaarNumber = '';
        $pospContactNumber ='';
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

        if ($tpOnly) {
            $isZeroDepCover = "false";
            $nonElectricalCoverSelection = "false";
            $nonElectricalCoverInsuredAmount = 0;
            $electricalCoveSelection = "false";
            $electricalCoverInsuredAmount = 0;
            $engine_and_gear_boxprotection_cover = "false";
            $consumable_cover = "false";
            $return_to_invoice_cover = "false";
            $tyre_protect_cover = "false";
            $loss_of_personal = "false";
            $ownDamageCover = "false";
            $idv = 0;
            $productCode = Config('constants.IcConstants.raheja.RAHEJA_RO_PRODUCT_CODE_BIKE');
        }
        else {
            $productCode = ($premium_type == 'own_damage') ? Config('constants.IcConstants.raheja.RAHEJA_OD_PRODUCT_CODE_BIKE') : Config('constants.IcConstants.raheja.RAHEJA_RO_PRODUCT_CODE_BIKE');
            $ownDamageCover = "true";

            if($premium_type == "comprehensive" || $premium_type == "own_damage" || $premium_type == "third_party"  || $premium_type == "breakin")
            {
                $Prev_policy_code = "1";
                $Prev_policy_name = "COMPREHENSIVE";
            }
            else
            {
                $Prev_policy_code = "2";
                $Prev_policy_name = "LIABILITY ONLY";
            }
           $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
            $applicable_addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);

            if($premium_type == "own_damage") {
                $tp_insurer_name    = $proposal_addtional_details['prepolicy']['tpInsuranceCompanyName'];
                $tp_insured         = $proposal_addtional_details['prepolicy']['tpInsuranceCompany'];
                $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
                $tp_insurer_address = keysToLower($tp_insurer_address);
            }
            $motor_lpg_cng_kit=0;$motor_non_electric_accessories=0;$motor_electric_accessories=0;
            if (!empty($additional['accessories'])) {
                foreach ($additional['accessories'] as $key => $data) {
                    if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $motor_lpg_cng_kit = $data['sumInsured'];
                    }
                    if ($data['name'] == 'Non-Electrical Accessories') {
                        $motor_non_electric_accessories = $data['sumInsured'];
                    }
                    if ($data['name'] == 'Electrical Accessories') {
                        $motor_electric_accessories = $data['sumInsured'];
                    }
                }
            }
            if ($motor_non_electric_accessories != 0 && $motor_non_electric_accessories < 200000 ) {
                $nonElectricalCoverSelection = "true";
                $nonElectricalCoverInsuredAmount = $motor_non_electric_accessories;
            } else {
                $nonElectricalCoverSelection = "false";
                $nonElectricalCoverInsuredAmount = 0;
            }
            if ($motor_electric_accessories != 0 && $motor_electric_accessories < 200000) {
                $electricalCoveSelection = "true";
                $electricalCoverInsuredAmount = $motor_electric_accessories;
            } else {
                $electricalCoveSelection = "false";
                $electricalCoverInsuredAmount = 0;
            }
             /*LPG-CNG additional cover*/
            if ($motor_lpg_cng_kit != 0 && $motor_lpg_cng_kit > 1000 && $motor_lpg_cng_kit < 60000) {
                $cngCoverSelection = "true";
                $cngCoverInsuredAmount = $motor_lpg_cng_kit;
            }
            else
            {
                $cngCoverSelection = 'false';
                $cngCoverInsuredAmount = 0;
            }
            /*LPG-CNG additional cover*/
        }
        $pa_unnamed='';$isPACoverPaidDriverAmount='';$legal_liability='';
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data)  {
                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $pa_unnamed = $data['sumInsured'];
                }
                if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                    $legal_liability = 'Y';
                }
            }
        }
        $motor_acc_cover_unnamed_passenger = '';
        foreach($additional_covers as $key => $value) {

            if (in_array('LL paid driver', $value)) {
                $legal_liability = 'true';
            }

            if (in_array('PA cover for additional paid driver', $value)) {
                $isPACoverPaidDriverSelected = 'true';
                $isPACoverPaidDriverAmount = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $motor_acc_cover_unnamed_passenger = $value['sumInsured'];
            }
        }
        /*paunnamed passenger*/
        if ($motor_acc_cover_unnamed_passenger != '') {
            $paUnnamedPersonCoverselection = "true";
            $paUnnamedPersonCoverinsuredAmount = $motor_acc_cover_unnamed_passenger;
        } else {
            $paUnnamedPersonCoverselection = "false";
            $paUnnamedPersonCoverinsuredAmount = 0;
        }
        /*paunnamed passenger*/

         /*paid driver*/
        if ($isPACoverPaidDriverAmount != '')
        {
            $paid_driver_selection = "true";
            $paid_driver_amount = $isPACoverPaidDriverAmount;
        }
        else
        {
            $paid_driver_selection = "false";
            $paid_driver_amount = 0;
        }
        /*paid driver*/
        /*cpa cover*/
        $cpa_type = 'false';
        $cpa_reason = false;
        if(!empty($additional['compulsory_personal_accident']))
        {
             foreach ($additional['compulsory_personal_accident'] as $key => $data)
             {
                if (isset($data['name']) && $data['name']  == 'Compulsory Personal Accident')
                {
                     $cpa_type = 'true';
                     $PHNumericFeild1 = isset($data['tenure']) ? (string) $data['tenure'] :'1';
                }elseif (isset($data['reason']) && $data['reason'] != "") {
                     if ($data['reason'] == 'I do not have a valid driving license.') {
                         $cpa_reason = true;
                         $PHBooleanField1 = '1';
                    }else if($data['reason'] == 'I have another PA policy with cover amount of INR 15 Lacs or more')
                    {
                        $cpa_reason = true;
                        $PHBooleanField2 = '1';
                    }
                 }
             }
        }

        $PrevNCB=($requestData->previous_ncb == "0") ? "0" : $requestData->previous_ncb;
        $IsClaimedLastYear=($requestData->applicable_ncb == "0") ? "1" : "2";
        $NatureOfLoss=($requestData->applicable_ncb == '0') ? "1" : "";
        $CurrentNCBHidden=($requestData->applicable_ncb == '20') ? "20" : $ncb_discount_rate;

        if ($requestData->business_type == 'breakin') {
            $vehicle_in_90_days = $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_difference > 90) {
                $PrevNCB = "0";
                $IsClaimedLastYear = "1";
                $NatureOfLoss = "1";
                $CurrentNCBHidden = "0";
            }
        }
        $voluntary_deductible = 'false';$tppd_discount = 'false'; $voluntary_deductible_amount=0;
        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                    $voluntary_deductible = 'true';
                    $voluntary_deductible_amount = $data['sumInsured'];
                }
                if ($data['name'] == 'TPPD Cover') {
                    $tppd_discount = 'true';
                    //$tppd_cover = '6000';
                }
            }
        }
        $isZeroDepCover='false';$key_replacement='false';$ep_check=false;
        $ncb_protection='false';$ce_check=false;$loss_of_personal='false';$tyre_protect_cover='false';
        $RTI=false;
        foreach ($applicable_addons as $key => $name) {
            if (in_array('Zero Depreciation', $name)) {
                $isZeroDepCover = 'true';
            }
            if (in_array('Key Replacement', $name)) {
                $key_replacement = 'true';
            }
            if (in_array('Engine Protector', $name)) {
                $ep_check = true;
            }
            if (in_array('NCB Protection', $name)) {
                $ncb_protection = 'true';
            }
            if (in_array('Consumable', $name)) {
                $ce_check = true;
            }
            if (in_array('Loss of Personal Belongings', $name)) {
                $loss_of_personal = 'true';
            }
            if (in_array('Tyre Secure', $name)) {
                $tyre_protect_cover = 'true';
            }
            if (in_array('Return To Invoice', $name)) {
                $RTI = true;
            }
        }
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 50,
            'address_2_limit'   => 50            
        ];
        $getAddress = getAddress($address_data);
        /*trace-id generation*/
        $trace_id= array();
        $get_response = getWsData(
            Config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_TRACE_ID'),
            [],
            'raheja',
            [
                'webUserId' => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE') ,
                'password' =>  Config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                'request_method'       => 'get',
                'request_data' => [
                    'section'     => 'bike',
                    'method' => 'traceid',
                    'proposal_id' => '0',
                ],
                'section'     => 'bike',
                'company'     => $productData->company_name,
                'productName'     => $productData->product_sub_type_name,
                'enquiryId'     => $enquiryId,
                'method'       => 'Trace Id Generation',
                'transaction_type' => 'proposal',
            ]
        );
        $trace_id_response = $get_response['response'];
        /*IDV 2nd api*/
        $year = explode('-', $requestData->manufacture_year);
        $yearOfManufacture = trim(end($year));
        if($trace_id_response)
        {
            $idv_api = [
                    'objVehicleDetails' => [                //IDV API
                    "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant']. "|" . $mmv_data['cc'] . "CC",
                    "RtoLocation" => $rto_data->rto_code,
                    "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                    "ManufacturingYear" =>$yearOfManufacture,
                    "ManufacturingMonth" => date('m', strtotime($requestData->vehicle_register_date))
                ],
                'objPolicy' => [
                    "UserName" => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                    "ProductCode" => $ProductCode,
                        "TraceID" => str_replace('"','',$trace_id_response),// "TAPI240620018939",
                        "SessionID" => "",
                        "TPSourceName" => Config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                        "BusinessTypeID" => $BusinessTypeID,
                        "PolicyStartDate" => $policy_start_date
                    ],
                ];
                $get_response = getWsData(Config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_IDV'), $idv_api, 'raheja',
                    [
                        'webUserId' => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                        'password' => Config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                        'request_data' => [
                            'proposal_id' => '0',
                            'method' => 'IDV calculations',
                            'section' => 'bike'
                        ],
                        'section'     => 'bike',
                        'method'      => 'IDV calculations',
                        'request_method'       => 'post',
                        'requestMethod'  => 'post',
                        'company'     => $productData->company_name,
                        'productName'     => $productData->product_sub_type_name,
                        'enquiryId'     => $enquiryId,
                        'transaction_type' => 'proposal',
                    ]
                );
                $data = $get_response['response'];
                /*IDV 2nd api*/
                $vehicle_reg_num = explode('-', $proposal->vehicale_registration_number);
                $veh_reg_date = explode('-', $requestData->vehicle_register_date);
                $response = array_change_key_case_recursive(json_decode($data, true));
        } else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Please Generate Trace ID.',
            ];
        }
        $dobd = $proposal->dob;
        $nominee_age = (date('Y') - date('Y',strtotime($dobd)));
        /*objPreviousInsurance new business code*/
        if (($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')) {
            $objPreviousInsurance = [
                "PrevPolicyType" => $Prev_policy_code,
                "PrevPolicyStartDate" => $previousinsurer_policystartdate,
                "PrevPolicyEndDate" => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
                "ProductCode" =>$ProductCode,
                "PrevInsuranceCompanyID" =>$previous_insurercode,
//                "PrevNCB" => ($requestData->previous_ncb == '0') ? "0" : $requestData->previous_ncb,
//                "IsClaimedLastYear" => ($requestData->applicable_ncb == '0') ? "1" : "2",
//                "NatureOfLoss" => ($requestData->applicable_ncb == '0') ? "1" : "",
//                "prevPolicyCoverType" => $Prev_policy_name,
//                "CurrentNCBHidden" => ($requestData->applicable_ncb == '20') ? "20" : $requestData->applicable_ncb
                 "PrevNCB" =>$PrevNCB,
                        "IsClaimedLastYear" =>$IsClaimedLastYear,
                        "NatureOfLoss" =>$NatureOfLoss,
                        "prevPolicyCoverType" => $Prev_policy_name,
                        "CurrentNCBHidden" =>$CurrentNCBHidden,
            ];
        } else {
            $objPreviousInsurance = '';
        }
        /*objPreviousInsurance new business code*/
        $voluntary_deductibles = [
            'null'                 => 0,
            'ZERO'                 => 0,
            'TWENTYFIVE_HUNDRED'   => 3000,

        ];
        if (($premium_type == 'own_damage')) { //od addons

            $objCovers = [
                [
                    "CoverID" => "9",
                    "CoverName" => "Basic - OD",
                    "CoverType" => "ODPackage",
                    "PackageName" => "ODPackage",
                    "objCoverDetails" => null,
                    "IsChecked" => "true"
                ],
                [
                    "CoverID" => "97",
                    "CoverName" => "Zero Depreciation",
                    "CoverType" => "AddOnCovers",
                    "PackageName" => "AddOnCovers",
                    "IsChecked" => $isZeroDepCover,
                    "objCoverDetails" => [
                        "PHIntField1" => 0,
                        "PHVarcharFeild1" => ($requestData->business_type == 'newbusiness') ? "2" : ">2",
                        "PHVarcharFeild2" => ($requestData->business_type == 'newbusiness') ? "" : "yes",
                        "PHNumericFeild1" => 0,
                        "PHIntField2" => 0
                    ]
                ],
                [
                    "CoverID" => "99",
                    "CoverName" => "NCB Retention",
                    "CoverType" => "AddOnCovers",
                    "PackageName" => "AddOnCovers",
                    "IsChecked" => ($requestData->applicable_ncb == '0' || $requestData->applicable_ncb == '- Select -' || $requestData->applicable_ncb == 'Not Applicable') ? 'false' : $ncb_protection,
                    "objCoverDetails" => null
                ],
                [
                    "CoverID" => "91",
                    "CoverName" => "Voluntary Deductibles",
                    "CoverType" => "Discount",
                    "PackageName" => "Discount",
                    "IsChecked" => ($voluntary_deductible == 'false') ? "false" : "true",
                    "objCoverDetails" => [
                        "PHNumericFeild1" => $voluntary_deductible_amount,
                    ]
                ],
                [
                    "CoverID"=>"37",
                    "CoverName"=>"Engine Protect",
                    "CoverType"=>"AddOnCovers",
                    "PackageName"=>"AddOnCovers",
                    "IsChecked"=>$ep_check,
                    "objCoverDetails"=>[
                        "PHVarcharFeild2"=>"Geared"

                    ]

                ],
                [
                    "CoverID"=>"24",
                    "CoverName"=>"Consumable Expenses",
                    "CoverType"=>"AddOnCovers",
                    "PackageName"=>"AddOnCovers",
                    "IsChecked"=>$ce_check,
                    "objCoverDetails"=>null

                ],
                [
                    "CoverID"=>"80",
                    "CoverName"=>"Return To Invoice",
                    "CoverType"=>"AddOnCovers",
                    "PackageName"=>"AddOnCovers",
                    "IsChecked"=>$RTI,
                    "objCoverDetails"=>null

                ],
            ];
        }
        else if($premium_type == 'third_party') {
            $objCovers = [
                [
                    "CoverID" => "10",
                    "CoverName" => "Basic - TP",
                    "CoverType" => "LiabilityPackage",
                    "PackageName" => "LiabilityPackage",
                    "objCoverDetails" => null,
                    "IsChecked" => "true"
                ],
                [
                    "CoverID" => "94",
                    "CoverName" => "PA - Unnamed Person",
                    "CoverType" => "LiabilityPackage",
                    "PackageName" => "LiabilityPackage",
                    "IsChecked" => $paUnnamedPersonCoverselection,
                    "objCoverDetails" => [
                        "PHNumericFeild2" => $paUnnamedPersonCoverinsuredAmount,
                        "PHNumericFeild1" => isset($mmv_data['Seating_Capacity']) ? $mmv_data['Seating_Capacity'] : "5"
                    ]
                ],
                [
                    "CoverID" => "73",
                    "CoverName" => "PA - Owner",
                    "CoverType" => "LiabilityPackage",
                    "PackageName" => "LiabilityPackage",
                    "IsChecked" => $cpa_type,
                    "objCoverDetails" => [
                        "PHintFeild1" => ($cpa_type == 'true') ? $nominee_age : "",
                        "PHVarcharFeild1" => ($cpa_type == 'true') ? $proposal->nominee_name : "",
                        "PHVarcharFeild2" => ($cpa_type == 'true') ? $nominee_rel : "",
                        "PHNumericFeild2" => ($cpa_type == 'true') ? "1500000" : "",
                        "PHNumericFeild1" => $PHNumericFeild1,
                        "PHVarcharFeild4" => "",
                        "PHVarcharFeild5" => ""
                    ]
                ],
                [
                    "CoverID"=> "87",
                    "CoverName"=> "TPPD",
                    "CoverType"=> "LiabilityPackage",
                    "PackageName"=> "LiabilityPackage",
                    "IsChecked"=> $tppd_discount,
                    "objCoverDetails"=> null,
                ],
            ];
            if($cpa_reason)
            {
                if($PHBooleanField1 == '1')
                {
                    $objCovers[3]['objCoverDetails']['PHBooleanField1'] = '1';
                }
                else if($PHBooleanField2 == '1')
                {
                    $objCovers[3]['objCoverDetails']['PHBooleanField2'] = '1';
                }
            }
        }
        else
        {
            $objCovers = [      //new and rollover addons
                [
                    "CoverID" => "9",
                    "CoverName" => "Basic - OD",
                    "CoverType" => "ODPackage",
                    "PackageName" => "ODPackage",
                    "objCoverDetails" => null,
                    "IsChecked" => "true"
                ],
                [
                    "CoverID" => "10",
                    "CoverName" => "Basic - TP",
                    "CoverType" => "LiabilityPackage",
                    "PackageName" => "LiabilityPackage",
                    "objCoverDetails" => null,
                    "IsChecked" => "true"
                ],
                [
                    "CoverID" => "94",
                    "CoverName" => "PA - Unnamed Person",
                    "CoverType" => "LiabilityPackage",
                    "PackageName" => "LiabilityPackage",
                    "IsChecked" => $paUnnamedPersonCoverselection,
                    "objCoverDetails" => [
                        "PHNumericFeild2" => $paUnnamedPersonCoverinsuredAmount,
                        "PHNumericFeild1" => isset($mmv_data['Seating_Capacity']) ? $mmv_data['Seating_Capacity'] : "5"
                    ]
                ],
                // [
                //     "CoverID" => "76",
                //     "CoverName" => "Paid Driver",
                //     "CoverType" => "LiabilityPackage",
                //     "PackageName" => "LiabilityPackage",
                //     "IsChecked" => $paid_driver_selection,
                //     "objCoverDetails" => [
                //       "PHVarcharFeild1" => $paid_driver_amount,
                //       "PHNumericFeild1" => "5"
                //     ]
                // ],
                [
                    "CoverID" => "73",
                    "CoverName" => "PA - Owner",
                    "CoverType" => "LiabilityPackage",
                    "PackageName" => "LiabilityPackage",
                    "IsChecked" => $cpa_type,
                    "objCoverDetails" => [
                        "PHintFeild1" => ($cpa_type == 'true') ? $nominee_age : "",
                        "PHVarcharFeild1" => ($cpa_type == 'true') ? $proposal->nominee_name : "",
                        "PHVarcharFeild2" => ($cpa_type == 'true') ? $nominee_rel : "",
                        "PHNumericFeild2" => ($cpa_type == 'true') ? 1500000 : "",
                        "PHNumericFeild1" => $PHNumericFeild1,
                        "PHVarcharFeild4" => "",
                        "PHVarcharFeild5" => ""
                    ]
                ],
                [
                    "CoverID" => "97",
                    "CoverName" => "Zero Depreciation",
                    "CoverType" => "AddOnCovers",
                    "PackageName" => "AddOnCovers",
                    "IsChecked" => $isZeroDepCover,
                    "objCoverDetails" => [
                        "PHIntField1" => 0,
                        "PHVarcharFeild1" => ($premium_type == 'third_party') ? "2" : ">2",
                        "PHVarcharFeild2" => ($premium_type == 'third_party') ? "" : "",
                        "PHNumericFeild1" => 0,
                        "PHIntField2" => 0
                    ]
                ],
                [
                    "CoverID" => "91",
                    "CoverName" => "Voluntary Deductibles",
                    "CoverType" => "Discount",
                    "PackageName" => "Discount",
                    "IsChecked" => ($voluntary_deductible == 'false') ? "false" : "true",
                    "objCoverDetails" => [
                        "PHNumericFeild1" => $voluntary_deductible_amount,
                    ]
                ],
                [
                    "CoverID"=> "87",
                    "CoverName"=> "TPPD",
                    "CoverType"=> "LiabilityPackage",
                    "PackageName"=> "LiabilityPackage",
                    "IsChecked"=> $tppd_discount,
                    "objCoverDetails"=> null,
                ],
                [
                    "CoverID"=>"37",
                    "CoverName"=>"Engine Protect",
                    "CoverType"=>"AddOnCovers",
                    "PackageName"=>"AddOnCovers",
                    "IsChecked"=>$ep_check,
                    "objCoverDetails"=>[
                        "PHVarcharFeild2"=>"Geared"

                    ]

                ],
                [
                    "CoverID"=>"24",
                    "CoverName"=>"Consumable Expenses",
                    "CoverType"=>"AddOnCovers",
                    "PackageName"=>"AddOnCovers",
                    "IsChecked"=>$ce_check,
                    "objCoverDetails"=>null

                ],
                [
                    "CoverID"=>"80",
                    "CoverName"=>"Return To Invoice",
                    "CoverType"=>"AddOnCovers",
                    "PackageName"=>"AddOnCovers",
                    "IsChecked"=>$RTI,
                    "objCoverDetails"=>null

                ],
            ];
            if($cpa_reason)
            {
                if($PHBooleanField1 == '1')
                {
                    $objCovers[3]['objCoverDetails']['PHBooleanField1'] = '1';
                }
                else if($PHBooleanField2 == '1')
                {
                    $objCovers[3]['objCoverDetails']['PHBooleanField2'] = '1';
                }
            }
        }
        if($data)
        {
            $premium_api = [
                "Loading" => "",
                "Discount" => "",
                "objClientDetails" => [
                "MobileNumber" => $proposal->mobile_number,
                "ClientType" => ($requestData->vehicle_owner_type == 'I') ? '0' : '1',
                "EmailId" => $proposal->email
                ],
                "objVehicleDetails" => [
                    "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant']. "|" . $mmv_data['cc'] . "CC",
                    "RtoLocation" => trim($rto_data->rto_loc_name . '|' . $rto_data->rto_code),
                    "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                    "Registration_Number1" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[0]),
                    "Registration_Number2" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[1]),
                    "Registration_Number3" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[2]),
                    "Registration_Number4" =>($requestData->business_type == 'newbusiness' ? '': $registration_no_add . $registration_no[3]),
                    "ManufacturingYear" => $veh_reg_date[2],
                    "ManufacturingMonth" => $veh_reg_date[1],
                    "FuelType" => $mmv_data['Fuel_Type'],
                    "BodyType" => "scooter",
                    "TypeofModification" => "",
                    "ModifiedIDV" => $proposal->idv
                ],
                "objPolicy" => [
                   "TraceID" => str_replace('"','',$trace_id_response),
                   "UserName" => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                   "TPSourceName" => Config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                   "SessionID" => "",
                   "ProductCode" =>$ProductCode,
                   "ProductName" =>$ProductName,
                   "PolicyStartDate" => $policy_start_date,
                   "PolicyEndDate" => $policy_end_date,
                   "BusinessTypeID" =>$BusinessTypeID,
                   "CoverType" =>$CoverType,
                   "Tennure" =>$Tennure,
                   "TPPolicyStartDate" => ($premium_type == 'own_damage'  || $requestData->business_type == 'breakin') ? date('Y-m-d', strtotime($proposal->tp_start_date)) : "",
                   "TPPolicyEndDate" => ($premium_type == 'own_damage'  || $requestData->business_type == 'breakin') ? date('Y-m-d', strtotime($proposal->tp_end_date)) : "",
                ],
                "objPreviousInsurance" => $objPreviousInsurance,
                "objCovers" => $objCovers,
            ];
            if ($requestData->business_type == 'newbusiness')
            {
                unset($premium_api['objPreviousInsurance']);
            }
//            print_r(json_encode($premium_api));
//            die;
            $get_response = getWsData(Config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_PREMIUM'), $premium_api, 'raheja',
                [
                    'webUserId' => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                    'password' => Config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                    'request_data' => [
                        'proposal_id' => '0',
                        'method' => 'premium',
                        'section' => 'bike'
                    ],
                    'section'     => 'bike',
                    'request_method' => 'post',
                    'requestMethod'  => 'post',
                    'company'     => $productData->company_name,
                    'productName'     => $productData->product_sub_type_name,
                    'enquiryId'     => $enquiryId,
                    'method' => 'premium',
                    'transaction_type' => 'proposal',
                ]
            );
            $data = $get_response['response'];
        }
        else
        {
            return [
                "status" => "false",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                "message" => "Something went wrong. Please re-verify details which you have provided."
            ];
        }
//print_r(json_encode($data));
//die;
        if($data) //if start $data
        {
            $premiumresonse = array_change_key_case_recursive(json_decode($data, true));
            $response = array_change_key_case_recursive(json_decode($data, true));
            if($response['objfault']['errormessage'] == '')
            {
                $save_motor_quotation = [
                    "Loading" => "",
                    "Discount" => "",
                    "objClientDetails" => [
                    "MobileNumber" => $proposal->mobile_number,
                    "ClientType" => ($requestData->vehicle_owner_type == 'I') ? '0' : '1',
                    "EmailId" => $proposal->email
                ],
                "objVehicleDetails" => [
                    "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant']. "|" . $mmv_data['cc'] . "CC",
                    "RtoLocation" => trim($rto_data->rto_loc_name . '|' . $rto_data->rto_code),
                    "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                    "Registration_Number1" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[0]),
                    "Registration_Number2" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[1]),
                    "Registration_Number3" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[2]),
                    "Registration_Number4" =>($requestData->business_type == 'newbusiness' ? '': $registration_no_add . $registration_no[3]),
                    "ManufacturingYear" => $veh_reg_date[2],
                    "ManufacturingMonth" => $veh_reg_date[1],
                    "FuelType" => $mmv_data['Fuel_Type'],
                    "BodyType" => "scooter",
                    "TypeofModification" => "",
                    "ModifiedIDV" => $proposal->idv
                ],
                "objPolicy" => [
                       "QuoteID" => $response['objpolicy']['quoteid'],
                       "QuoteNo" => $response['objpolicy']['quoteno'],
                       "TraceID" => str_replace('"','',$trace_id_response),
                       "UserName" => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                       "TPSourceName" => Config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                       "SessionID" => "",
                    "ProductCode" =>$ProductCode,
                    "ProductName" =>$ProductName,
                    "PolicyStartDate" => $policy_start_date,
                    "PolicyEndDate" => $policy_end_date,
                    "BusinessTypeID" => $BusinessTypeID,
                    "CoverType" =>$CoverType,
                    "Tennure" => $Tennure,
                    "TPPolicyStartDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_start_date)) : "",
                       "TPPolicyEndDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_end_date)) : "",
                    ],
                    "objPreviousInsurance" => $objPreviousInsurance,
                    "objCovers" => $objCovers,
                ];

                if ($requestData->business_type == 'newbusiness')
                {
                    unset($save_motor_quotation['objPreviousInsurance']);
                }
                $get_response = getWsData(Config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_SAVE_BIKE'), $save_motor_quotation, 'raheja', [

                        'webUserId' => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                        'password' => Config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                        'request_data' => [
                            'proposal_id' => '0',
                            'method' => 'save motor quotation',
                            'section' => 'bike',
                        ],
                        'section'     => 'bike',
                        'requestMethod' => 'post',
                        'request_method' => 'post',
                        'company'     => $productData->company_name,
                        'productName'     => $productData->product_sub_type_name,
                        'enquiryId'     => $enquiryId,
                        'method'       => 'save motor quotation',
                        'transaction_type' => 'proposal',
                    ]
                );
                $data = $get_response['response'];
            } else {
                return [
                    "status" => "false",
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    "message" => "Method Premium ". $response['objfault']['errormessage']
                ];
            }

            if($data)  //if 1$data start
            {
                $response = array_change_key_case_recursive(json_decode($data, true));

                $voluntary_deductibles = [
                    'null'                 => 0,
                    'ZERO'                 => 0,
                    'TWENTYFIVE_HUNDRED'   => 2500,
                    'FIVE_THOUSAND'        => 5000,
                    'SEVENTYFIVE_HUNDRED'  => 7500,
                    'FIFTEEN_THOUSAND'     => 15000,
                ];
                /*objPreviousInsurance new business code*/
                if (($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')) {
                    $objPreviousInsurance = [
                        "PrevPolicyType" => $Prev_policy_code,
                        "PrevPolicyStartDate" => $previousinsurer_policystartdate,
                        "PrevPolicyEndDate" =>date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
                        "ProductCode" =>$ProductCode,
                        "PrevPolicyNo" => $proposal->previous_policy_number,
                        "PrevInsuranceCompanyID" => $previous_insurercode,
                        "PrevInsurerAddress" => "vashi sector",
                        "PrevNCB" =>$PrevNCB,
                        "IsClaimedLastYear" =>$IsClaimedLastYear,
                        "NatureOfLoss" =>$NatureOfLoss,
                        "prevPolicyCoverType" => $Prev_policy_name,
                        "CurrentNCBHidden" =>$CurrentNCBHidden,
                    ];
                } else {
                    $objPreviousInsurance = '';
                }
                /*objPreviousInsurance new business code*/
                if($response['objfault']['errormessage'] == '')
                {
                    $KYC_DocType_master = [
                        'pan_card' => 'PANNo',
                        'voter_id' => 'VoterID',
                        'passport' => 'PassportNo',
                        'driving_license' => 'DL'
                    ];

                    $save_motor_proposal = [
                        "Discount" => "",
                        "Loading" => "",
                        "ContactNumber" => $proposal->mobile_number,
                        "mailid" => $proposal->email,
                        "objVehicleDetails" => [
                              "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant']. "|" . $mmv_data['cc'] . "CC",
                              "RtoLocation" => trim($rto_data->rto_loc_name . '|' . $rto_data->rto_code),
                              "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            "Registration_Number1" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[0]),
                            "Registration_Number2" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[1]),
                            "Registration_Number3" =>($requestData->business_type == 'newbusiness' ? '': $vehicle_reg_num[2]),
                            "Registration_Number4" =>($requestData->business_type == 'newbusiness' ? '': $registration_no_add . $registration_no[3]),
                              "ManufacturingYear" => $veh_reg_date[2],
                              "ManufacturingMonth" => $veh_reg_date[1],
                              "BodyType" => "scooter",
                              "TypeofModification" => "",
                              "FuelType" => $mmv_data['Fuel_Type'],
                              "ModifiedIDV" => $proposal->idv,
                              "EngineNumber" => $proposal->engine_number,
                              "ChassisNumber" => $proposal->chassis_number,
                              "AverageVehicleUsageId" => "",
                              "VehicleColorID" => "",
                              "Sector" => "",
                              "TaxExemption" => "",
                              "PUCNumber" => "",
                              "isPUC" => "true",
                              "PUCEndDate" =>  ''//date('Y-m-d', strtotime($puc_end_date))
                           ],
                           "objClientAddress" => [
                              "Address1" => trim($getAddress['address_1']),#$proposal->address_line1 == null ? "" : $proposal->address_line1,
                              "Address2" =>  trim($getAddress['address_2']) == '' ? "" : trim($getAddress['address_2']),#$proposal->address_line2 == null ? "" : $proposal->address_line2,
                              "Address3" => trim($getAddress['address_3']),#$proposal->address_line3 == null ? "" : $proposal->address_line3,
                              "City" => $proposal->city == null ? "" : $proposal->city,
                              "State" => $proposal->state == null ? "" :$proposal->state,
                              "Area" => $pincode_area->street == null ? "" : $pincode_area->street,
                              "Country" => "INDIA",
                              "Pincode" => $proposal->pincode == null ? "" : $proposal->pincode
                           ],
                           "objRegistrationAddress" => [
                              "Address1" => trim($getAddress['address_1']),#$proposal->is_car_registration_address_same == '1' ? $proposal->address_line1 : $proposal->car_registration_address1,
                              "Address2" => trim($getAddress['address_2']) == '' ? "" : trim($getAddress['address_2']),#$proposal->is_car_registration_address_same == '1'  ? $proposal->address_line2 : $proposal->car_registration_address2,
                              "Address3" => trim($getAddress['address_3']),#$proposal->is_car_registration_address_same == '1'  ? $proposal->address_line3 : $proposal->car_registration_address3,
                              "City" => $proposal->is_car_registration_address_same == '1' ? $proposal->city :$proposal->car_registration_city,
                              "State" =>  $proposal->is_car_registration_address_same == '1' ? $proposal->state :$proposal->car_registration_state,
                              "Area" =>  $pincode_area->street,
                              "Country" => "INDIA",
                              "Pincode" =>  $proposal->is_car_registration_address_same == '1' ? $proposal->pincode :$proposal->car_registration_pincode,
                           ],
                           "objClientDetails" => [
                              "ClientType" => ($requestData->vehicle_owner_type == 'I') ? '0' : '1',
                              "Client_FirstName" => ($requestData->vehicle_owner_type == 'I') ? $proposal->first_name : '',
                              "CorporateName" => ($requestData->vehicle_owner_type == 'I') ? '' : $proposal->last_name,
                              "Client_ForAttention" => "",
                              "TIN" => "",
                              "LastName" => ($requestData->vehicle_owner_type == 'I') ? $proposal->last_name : '',
                              "Client_DOB" => ($requestData->vehicle_owner_type == 'I') ? date('Y-m-d', strtotime($proposal->dob)) : '',
                              "InsuredOccupation" =>"",
                              "Gender" => ($requestData->vehicle_owner_type == 'I') ? ($proposal->gender == 'M') ? '20' : '21' : '',
                              "Salutation" => ($requestData->vehicle_owner_type == 'I') ? ($proposal->gender == 'M') ? '7' : '8' : '',
                              "MobileNumber" => $proposal->mobile_number,
                              "EmailId" => $proposal->email,
                              "PANNumber" => $proposal->pan_number == null ? "" : $proposal->pan_number,
                              "GSTINNumber" => $proposal->gst_number == null ? "" : $proposal->gst_number,
                              "OtherInformationOne" => "",
                              "OtherInformationTwo" => "",
                              "IsEIANoExists" => "false",
                              "EIANo" => "",
                              "KYC_DocType" => $KYC_DocType_master[$proposal->ckyc_type],
                              "KYC_DocID" => $proposal->ckyc_type_value,
                              'Annual_Income' => '',
                              'IsPEP_RelativeOfPEP' => 'FALSE',
                              'Residential_Status' => 'Indian',
                              'IS_NRI_foreign_Country' => ''
                           ],
                           "objVehicleHypoth" => [
                              "FinancierType" => ($proposal->financer_agreement_type == '') ? '' : $proposal->financer_agreement_type,
                              "Financier_Address" => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : '',
                              "Financier_Name" => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : '',
                           ],
                           "objPolicy" => [
                              "QuoteID" => $response['objpolicy']['quoteid'],
                              "QuoteNo" => $response['objpolicy']['quoteno'],
                              "Policyid" => $response['objpolicy']['policyid'],
                              "PolicyStartDate" => $policy_start_date,
                              "PolicyEndDate" => $policy_end_date,
                              "strODEndDate" => $saod_policy_end_date,
                              "ProductCode" =>$ProductCode,
                              "ProductName" =>$ProductName,
                              "TraceID" => $response['objpolicy']['traceid'],
                              "UserName" => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                              "TPSourceName" => Config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                              "SessionID" => "string",
                              "BusinessTypeID" =>$BusinessTypeID,
                              "CoverType" =>$CoverType,
                              "TPPolicyStartDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_start_date)) : "",
                              "TPPolicyEndDate" => ($premium_type == 'own_damage') ? date('Y-m-d', strtotime($proposal->tp_end_date)) : "",
                              "TPPolicyNo" => ($premium_type == 'own_damage') ? $proposal->tp_insurance_number : "",
                              "TPInsurerID" => ($premium_type == 'own_damage') ? $tp_insured : "",
                              "TPInsurerAddress" => ($premium_type == 'own_damage') ? $tp_insurer_address->address_line_1.' '.$tp_insurer_address->address_line_2 : "",
                              "Tennure" =>$Tennure,
                              "IsVehicleHypothicated" => ($proposal->is_vehicle_finance == '1') ? true : false,
                              "IsRegAddressSameasCorrAddress" => "false"
                            ],
                           "objPreviousInsurance" => $objPreviousInsurance,
                           "objCovers" => $objCovers,
                    ];

                    if ($proposal->is_vehicle_finance != '1')
                    {
                        $save_motor_proposal['objVehicleHypoth']['FinancierType']='';
                    }
                    if ($requestData->business_type == 'newbusiness')
                    {
                        unset($save_motor_proposal['objPreviousInsurance']);
                    }
                    $get_response =  getWsData(
                        Config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_BIKE_PROPOSAL'), $save_motor_proposal, 'raheja', [

                            'webUserId' => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                            'password' => Config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                            'request_data' => [
                                'proposal_id' => '0',
                                'method' => 'save motor proposal',
                                'section' => 'bike',
                            ],
                            'section'     => 'bike',
                            'request_method' => 'post',
                            'requestMethod'  => 'post',
                            'company'     => $productData->company_name,
                            'productName'     => $productData->product_sub_type_name,
                            'enquiryId'     => $enquiryId,
                            'transaction_type' => 'proposal',
                            'method'      => 'save motor proposal',
                        ]
                    );
                    $save_final = $get_response['response'];
                }
                else {
                    return [
                        "status" => "false",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        "message" => "Method Save Motor Quotation ". $response['objfault']['errormessage']
                    ];
                }

                $kyc_url = '';
                $is_kyc_url_present = false;
                $kyc_message = '';
                $kyc_status = true;

                if ($save_final) {
                      $response = array_change_key_case_recursive(json_decode($save_final, true));

                      if (isset($response['obj_kyc_api_response']['kycstate'])) {
                        if ($response['obj_kyc_api_response']['kycstate'] != 'KYCFOUND') {
                            if (isset($response['obj_kyc_api_response']['rqbeckycglink'])) {
                                $kyc_url = $response['obj_kyc_api_response']['rqbeckycglink'];
                                $is_kyc_url_present = true;
                            }

                            $kyc_status = false;
                            $kyc_message = 'CKYC Verification failed';
                        }
                    }

                    if($response['objfault']['errormessage'] == '')
                    {
                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'is_ckyc_verified' => 'Y'
                            ]);

                        $generate_transation = [
                            "objPolicy" => [
                                "TraceID" => str_replace('"','',$trace_id_response),
                                "QuoteNo" => $response['objpolicy']['quoteno'],
                                "SessionID" => "string",
                                "TPSourceName" => Config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                                "UserName" => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE')
                            ]
                        ];
                        $get_response = getWsData(
                            Config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_GENERATE_TRANSATION'), $generate_transation, 'raheja', [

                                'webUserId' => Config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                                'password' => Config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                                'request_data' => [
                                    'proposal_id' => '0',
                                    'method' => 'generate transation',
                                    'section' => 'bike',
                                ],
                                'section'     => 'bike',
                                'request_method' => 'post',
                                'requestMethod'  => 'post',
                                'company'     => $productData->company_name,
                                'productName'     => $productData->product_sub_type_name,
                                'enquiryId'     => $enquiryId,
                                'transaction_type' => 'proposal',
                                'method'      => 'generate transation',
                            ]
                        );
                        $transation_number = $get_response['response'];
                        $trace_id = $response['objpolicy']['traceid'];
                        $quote_no = $response['objpolicy']['quoteno'];
                        /*idv range calculation*/
                        $tppd= 0; $rsa = 0; $tyre_protect = 0; $zero_dep = 0; $ncb_protection = 0; $consumable = 0; $eng_protect = 0; $rti = 0; $od = 0;
                        $electrical_accessories = 0; $non_electrical_accessories = 0; $lpg_cng = 0; $lpg_cng_tp = 0; $pa_owner = 0; $llpaiddriver = 0;
                        $pa_unnamed = 0; $paid_driver = 0; $key_replacement = 0; $loss_of_personal_belongings = 0; $voluntary_deductible = 0;$ic_vehicle_discount=0;
                        $tppd_discount=0;$anti_theft=0;$automobile_assoc=0;
                        foreach ($premiumresonse['lstcoverresponce'] as $key => $value) {
                            if ($value['covername'] == 'Basic - OD')
                            {
                                $od = $value['coverpremium'];
                            } elseif ($value['covername'] == 'Basic - TP') {
                                $tppd = $value['coverpremium'];
                            } elseif ($value['covername'] == 'PA - Owner') {
                                $pa_owner = $value['coverpremium'];
                            } elseif ($value['covername'] == 'Zero Depreciation') {
                                $zero_dep = $value['coverpremium'];
                            }  elseif ($value['covername'] == 'PA - Unnamed Person') {
                                $pa_unnamed = $value['coverpremium'];
                            } elseif ($value['covername'] == 'Paid Driver') {
                                $paid_driver = $value['coverpremium'];
                            } elseif ($value['covername'] == 'Voluntary Deductibles') {
                                $voluntary_deductible = $value['coverpremium'];
                            } elseif ($value['covername'] == 'TPPD') {
                                $tppd_discount = $value['coverpremium'];
                            }
                            elseif ($value['covername'] == 'Engine Protect'){
                                $eng_protect = $value['coverpremium'];
                            } elseif ($value['covername'] == 'Consumable Expenses'){
                                $consumable = $value['coverpremium'];
                            }
                            elseif ($value['covername'] == 'Return To Invoice'){
                                $rti = $value['coverpremium'];
                            }
                        }
                        if(isset($premiumresonse['ncbpremium']))
                        {
                            $ncb_discount = str_replace("INR ", "", $premiumresonse['ncbpremium']);
                        }
                        else
                        {
                            $ncb_discount = 0;
                        }
                        //
                        $final_od_premium = $od;
                        //                        $final_tp_premium = $premiumresonse['totalliabilitypremium']+ + $tppd_discount;
                        //                        $final_tp_premium = $final_tp_premium - $pa_owner;
                        $final_tp_premium = $tppd_discount+$tppd+ $llpaiddriver + $pa_owner + $pa_unnamed +$paid_driver+$lpg_cng_tp;
                        $totalTax = $premiumresonse['totaltax'];
                        $total_discount = $ncb_discount + $tppd_discount;
                        $total_premium_amount = $premiumresonse['finalpremium'];
                        $base_premium_amount = $total_premium_amount / (1 + (18 / 100));
                        //                        print_r([$final_tp_premium]);
                        //                        return $premiumresonse;
                        if($transation_number) {
                            $transation_number = array_change_key_case_recursive(json_decode($transation_number, true));
                            UserProposal::where('user_product_journey_id', $enquiryId)
                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'policy_start_date'     => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                                    'policy_end_date'       => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                                    'proposal_no'           => $enquiryId,
                                    'payment_url'           => $transation_number['onlinepaymentpage'],
                                    'unique_proposal_id'     => $transation_number['txnno'],
                                    'unique_quote'          => $quote_no,
                                    'od_premium'            => round($final_od_premium),
                                    'tp_premium'            => $final_tp_premium,
                                    'cpa_premium'           => $pa_owner,
                                    'service_tax_amount' => $totalTax,
                                    'total_discount' => $total_discount,
                                    'final_payable_amount' => round($total_premium_amount),
                                    'ic_vehicle_details' => $vehicleDetails,
                                    'is_breakin_case' =>   'N',
                                    'vehicle_registration_no' => $proposal->vehicale_registration_number,
                                    'electrical_accessories'    => $electrical_accessories,
                                    'non_electrical_accessories' => $non_electrical_accessories,
                                    'tp_start_date'        =>       date('Y-m-d', strtotime($proposal->tp_start_date)),
                                    'tp_end_date'              =>      date('Y-m-d', strtotime($proposal->tp_end_date)),
                                    'tp_insurance_company'     =>  $proposal->tp_insurance_company,
                                    'tp_insurance_number'     => $proposal->tp_insurance_number
                                ]);
                                unset($data);
                                $data['user_product_journey_id'] = $enquiryId;
                                $data['ic_id'] = $master_policy->insurance_company_id;
                                $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                                $data['proposal_id'] = $proposal->user_proposal_id;
                                updateJourneyStage($data);
                                return response()->json([
                                    'status' => true,
                                    'msg' => "Proposal Submitted Successfully!",
                                    'data' => [
                                        'proposalId' => $proposal->user_proposal_id,
                                        'userProductJourneyId' => $data['user_product_journey_id'],
                                        'proposalNo' => $enquiryId,
                                        'finalPayableAmount' => round($total_premium_amount),
                                        'kyc_url' => $kyc_url,
                                        'is_kyc_url_present' => $is_kyc_url_present,
                                        'kyc_message' => $kyc_message,
                                        'kyc_status' => $kyc_status
                                    ]
                                ]);
                        } else {
                            return [
                                "status" => $kyc_status ? false : true,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                "message" => "Payment Link Not Generate",
                                'data' => [
                                    'proposalId' => null,
                                    'userProductJourneyId' => $enquiryId,
                                    'proposalNo' => null,
                                    'finalPayableAmount' => null,
                                    'is_breakin' => false,
                                    'inspection_number' => '',
                                    'kyc_url' => $kyc_url,
                                    'is_kyc_url_present' => $is_kyc_url_present,
                                    'kyc_message' => $kyc_message,
                                    'kyc_status' => $kyc_status
                                ]
                            ];
                        }
                    }
                    else
                    {
                        return [
                            'status' => $kyc_status ? false : true, 
                            'message' => "Method Save Motor Proposal " . $response['objfault']['errormessage'], 
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => null,
                                'userProductJourneyId' => $enquiryId,
                                'proposalNo' => null,
                                'finalPayableAmount' => null,
                                'is_breakin' => 'N',
                                'inspection_number' => '',
                                'kyc_url' => $kyc_url,
                                'is_kyc_url_present' => $is_kyc_url_present,
                                'kyc_message' => $kyc_message,
                                'kyc_status' => $kyc_status
                            ]
                        ];
                    }
                }

            }    //if 1$data close

        } //if close $data
  }

}
