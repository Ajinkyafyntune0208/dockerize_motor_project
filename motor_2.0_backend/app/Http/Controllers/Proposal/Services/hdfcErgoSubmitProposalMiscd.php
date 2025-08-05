<?php

namespace App\Http\Controllers\Proposal\Services;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SyncPremiumDetail\Services\HdfcErgoPremiumDetailController;
use App\Models\IcVersionMapping;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\UserProposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class hdfcErgoSubmitProposalMiscd extends Controller
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request)
    {
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $product_data = getProductDataByIc($request['policyId']);

        $requestData = $quote_data;
        $productData = $product_data;


        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
            ];
        }

        $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $first_reg_date = $quote_data->vehicle_register_date;

        $policy_expiry_date = $quote_data->previous_policy_expiry_date;

        $rto_data = MasterRto::where('rto_code', $quote_data->rto_code)->where('status', 'Active')->first();

        $rto_cities = explode('/',  $rto_data->rto_name);
        foreach ($rto_cities as $rto_city) 
        {
            $rto_city = strtoupper($rto_city);
            $rto_location = DB::table('miscd_hdfc_ergo_rto_location')
            ->where('Txt_Rto_Location_desc', 'like', '%' . $rto_city . '%')
            ->where('Num_Vehicle_Subclass_Code', '=', $get_mapping_mmv_details->num_vehicle_subclass_code)
            ->where('Num_Vehicle_Class_code', '=', $get_mapping_mmv_details->vehicleclasscode)
            ->first();

            if (!empty($rto_location)) 
            {
                break;
            }
        }
       
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $quote_log_data = DB::table('quote_log')->where('user_product_journey_id', $enquiryId)->first();

        $idv = $quote_log_data->idv;
        $ex_showroom = $quote_log_data->ex_showroom_price_idv;
        $tp_only = in_array($productData->premium_type_id, [2, 7]) ;
        
        $is_breakin = false;
        $today = date('d-m-Y');
        if ($quote_data->business_type == 'newbusiness') {
            $businesstype = 'NEW BUSINESS';
            $policy_start_date = date('d-m-Y');
            $IsPreviousClaim = '0';
            $prepolstartdate = '01-01-1900';
            $prepolicyenddate = '01-01-1900';
            if ($tp_only) {
                $policy_start_date = date('d-m-Y', strtotime($today . ' + 1 days'));
            }
        } else {
            $businesstype = 'ROLL OVER';
            $policy_start_date = date('d-m-Y', strtotime($quote_data->previous_policy_expiry_date . ' + 1 days'));
            $IsPreviousClaim = $quote_data->is_claim == 'N' ? 1 : 0;
            $prepolstartdate = date('d-m-Y', strtotime($policy_expiry_date . '-1 year +1 day'));
            $prepolicyenddate = $policy_expiry_date;

            if ($proposal->business_type == 'breakin') {
                $is_breakin = true;
                if ($tp_only) {
                    $is_breakin = false;
                    $policy_start_date = date('d-m-Y', strtotime($today . ' + 1 days'));
                }
            }
        }
        $previous_policy_type = $requestData->previous_policy_type;
        $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));

        if ($is_breakin && !$tp_only) { // In comprehensive break-in case
            $policy_start_date = date('d-m-Y');
            $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
        }

        if ($previous_policy_type == 'Third-party' && $businesstype == 'Rollover' && !$tp_only) {
            $is_breakin = true;
        }

        if (in_array($product_data->premium_type_id, [1, 4])) {
            $selected_addons = DB::table('selected_addons')
                ->where('user_product_journey_id', $enquiryId)
                ->first();
            $PAToPaidDriver_SumInsured = $zeroDepth = '0';

            if (!empty($selected_addons) && $selected_addons->additional_covers != null && $selected_addons->additional_covers != '') {
                $additional_covers = json_decode($selected_addons->additional_covers);
                foreach ($additional_covers as $value) {
                    if ($value->name == 'PA cover for additional paid driver') {
                        $pa_paid_driver = '1';
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                    }
                    if ($value->name == 'LL paid driver') {
                        $nooflldrivers = '1';
                    }

                    if ($value->name == 'LL paid driver/conductor/cleaner' && isset($value->selectedLLpaidItmes[0])) {
                        if ($value->selectedLLpaidItmes[0] == 'DriverLL' && !empty($value->LLNumberDriver)) {
                            $nooflldrivers = $value->LLNumberDriver;
                        }
                    }
                    if ($value->name == 'PA paid driver/conductor/cleaner') {
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                    }
                }
            }
            if (!empty($selected_addons) && $selected_addons->discounts != null && $selected_addons->discounts != '') {
                $discounts = json_decode($selected_addons->discounts);
                foreach ($discounts as $data) {
                    if ($data->name == 'TPPD Cover') {
                        $tppd_cover = 6000;
                    }
                    if ($data->name == 'Vehicle Limited to Own Premises') {
                        $own_premises = '1';
                    }
                }
            }
            if (!empty($selected_addons) && $selected_addons->addons != null && $selected_addons->addons != '') {
                $addons = json_decode($selected_addons->addons);
                foreach ($addons as $value) {
                    if ($value->name == 'IMT - 23') {
                        $imt23 = '1';
                    }
                }
            }
            $ElectricalaccessSI  = 0;
            $NonElectricalaccessSI = 0;
            if (!empty($selected_addons) && $selected_addons->accessories != null && $selected_addons->accessories != '') {
                $accessories = json_decode($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if ($value->name == 'Electrical Accessories') {
                        $Electricalaccess = 1;
                        $ElectricalaccessSI = $value->sumInsured;
                    }
                    if ($value->name == 'Non-Electrical Accessories') {
                        $NonElectricalaccess = 1;
                        $NonElectricalaccessSI = $value->sumInsured;
                    }

                    // if ($value->name == 'External Bi-Fuel Kit CNG/LPG') {
                    //     $externalCNGKIT = 'LPG';
                    //     $externalCNGKITSI = $value->sumInsured;
                    // }
                }
            }
            $is_pa_cover_owner_driver = 0;
            if (!empty($selected_addons && $selected_addons->compulsory_personal_accident != '')) {
                $compulsory_personal_accident = json_decode($selected_addons->compulsory_personal_accident);
                foreach ($compulsory_personal_accident as $value) {
                    if (isset($value->name) && ($value->name == 'Compulsory Personal Accident')) {
                        if ($quote_data->vehicle_owner_type == 'I') {
                            $is_pa_cover_owner_driver = 1;
                        }
                    }
                }
            }

            $model_config_premium = [
                'VehicleClassCode=' . $get_mapping_mmv_details->vehicleclasscode . '&str',
                [
                    'CSCTractorPremiumCalc' => [
                        'agent_cd' => config('constants.IcConstants.hdfc_ergo.HDFC_MISCD_AGENT_CODE'),
                        'typeofbusiness' => $businesstype,
                        'intermediaryid' => '201764376894',
                        'vehiclemakeidv' => $idv,
                        'vehiclemodelcode' => $get_mapping_mmv_details->vehiclemodelcode,
                        'rtolocationcode' => $rto_location->Txt_Rto_Location_code,
                        'customertype' => $requestData->vehicle_owner_type == 'I' ? "I" : "O",
                        'policystartdate' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                        'policyenddate' => $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d/m/Y'),
                        'purchaseregndate' => Carbon::createFromFormat('d-m-Y', $first_reg_date)->format('d/m/Y'),
                        'numofpaiddriver' => isset($pa_paid_driver) ? $pa_paid_driver : '0',
                        'numsipaiddriver' => $PAToPaidDriver_SumInsured,
                        'numnonelectricalidv' => isset($NonElectricalaccessSI) ? $NonElectricalaccessSI : '0',
                        'numelectricalidv' => isset($ElectricalaccessSI) ? $ElectricalaccessSI : '0',
                        'nooflldrivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                        'nooftrailers' => '0',
                        'numtrailersidv' => '0',
                        'previousdiscount' => $previous_policy_type == 'Third-party' ? '0' : $requestData->previous_ncb,
                        'limitedownpremises' => '0',
                        'privateuse' => '0',
                        'inclusionimt23' => isset($imt23) ? $imt23 : '0',
                        'previousisclaim' => $IsPreviousClaim,
                        'prev_pol_end_dt' => ($businesstype == 'NEW BUSINESS' || $prepolicyenddate == 'New') ? '01/01/1900' : Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d/m/Y'),
                        'effective_driving_lic' => $is_pa_cover_owner_driver,
                        'lpg_cngkit' => '0',
                        'source_request_type' => 'ONLINE',
                        'customer_state_code' => '0',
                        'i_gcpospcode' => '',
                        'is_gstin_applicable' => '1',
                        'misc_code' => '0'
                    ]
                ],
            ];


     

            if ($product_data->premium_type_id == 4) {
                $model_config_premium[1]['CommercialVehicleInput']['manufacturingyear'] = date('Y', strtotime('01-' . $quote_data->manufacture_year));
                $model_config_premium[1]['CommercialVehicleInput']['page_calling'] = '1';
                $model_config_premium[1]['CommercialVehicleInput']['num_is_rti'] = '0';
            }

            $get_response = getWsData(
                config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TRACTOR_PREMIUM_URL'),
                $model_config_premium, 'hdfc_ergo',
                [
                    'root_tag' => 'PCVPremiumCalc',
                    'section' => $product_data->product_sub_type_code,
                    'method' => 'Premium Calculation',
                    'requestMethod' => 'post',
                    'enquiryId' => customDecrypt($request['userProductJourneyId']),
                    'productName' => $product_data->product_sub_type_name,
                    'transaction_type' => 'proposal',
                ]
            );
            $premium_data = $get_response['response'];
            if (preg_match('/Service Unavailable/i', $premium_data)) {
                return response()->json(
                    [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Insurer not reachable',
                    ]
                );
            }
            if ($premium_data) {
                $premium_data = html_entity_decode($premium_data);
                $premium_data = XmlToArray::convert($premium_data);
                if (empty($premium_data)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Insurer not reachable',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                    ]);
                }elseif(isset($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['TXT_ERROR_MSG']) && !empty($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['TXT_ERROR_MSG'])){
                    return response()->json([
                        'status' => false,
                        'msg' => $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['TXT_ERROR_MSG'],
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                    ]);
                }
                if (isset($premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['PARENT'])) {
                    $premium_data = $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT']['PARENT'][0];
                } else {
                    $premium_data = $premium_data['PREMIUMOUTPUTOUTER']['PREMIUMOUTPUT'];
                }
                $year_of_manufacture = '01-' . $proposal->vehicle_manf_year;
                if ($previous_policy_type == 'Third-party') {
                    $NCB_RenewalPolicy = '0';
                }else{
                    $NCB_RenewalPolicy = (int)$quote_data->applicable_ncb > 0 ? $quote_data->applicable_ncb : '0';
                }
                $address_data = [
                    'address' => removeSpecialCharactersFromString($proposal->address_line1, true),
                    'address_1_limit'   => 50,
                    'address_2_limit'   => 50,         
                    'address_3_limit'   => 250,         
                ];
                $getAddress = getAddress($address_data);

                if ($quote_data->vehicle_owner_type == "I") {
                    if ($proposal->gender == "Male") {
                        $title_code = 'Mr';
                    } else {
                        if ($proposal->gender == "Female" && $proposal->marital_status == "Single") {
                            $title_code = 'Ms';
                        } else {
                            $title_code = 'Mrs';
                        }
                    }
                } else {
                    $title_code = 'M/S';
                }
                $cpa_opted = '0';
                $cpa_premium = 0;
                if (!empty($selected_addons) && $selected_addons->compulsory_personal_accident != '') {
                    $cpa = json_decode($selected_addons->compulsory_personal_accident);
                    foreach ($cpa as $value) {
                        if (isset($value->name)) {
                            $cpa_opted = '1';
                            $cpa_premium = $premium_data['NUM_PA_OWNER_DRIVER_PREM'];
                        }
                    }
                }
                $cpa_opted = $quote_data->vehicle_owner_type == 'I' ? $cpa_opted : '0';

                $pa_paid_driver = $premium_data['NUM_PA_PAID_DRIVER_PREM'];
                $liabilities = $premium_data['NUM_NOOFLLDRIVERS_PREM'];
                $tppd_discount = isset($premium_data['NUM_TPPD_AMT']) ? round($premium_data['NUM_TPPD_AMT']) : 0;
                $motor_electric_accessories_value = $premium_data['NUM_ELECTRICAL_PREM'];
                $motor_non_electric_accessories_value = $premium_data['NUM_NON_ELECTRICAL_PREM'];
                $final_tp_premium = $premium_data['NUM_BASIC_TP_PREM'] + $liabilities + $pa_paid_driver + $cpa_premium;
                $total_own_damage = $premium_data['NUM_BASIC_OD_PREM'];
                $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;
                $imt_23 = round($premium_data['NUM_INCLUSION_IMT23_PREM']);
                $final_od_premium = $total_own_damage + $total_accessories_amount + $imt_23;
                $final_total_discount = (int)($final_od_premium * ($requestData->applicable_ncb / 100));
                $deduction_of_ncb = round($final_od_premium * $requestData->applicable_ncb / 100);   
                
                $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
                $final_gst_amount = round($final_net_premium * 0.18);
                $final_payable_amount = $final_net_premium + $final_gst_amount;

                $proposal_array = [
                    'str',
                    [
                        'xmlmotorpolicy' => [
                            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_MISCD_AGENT_CODE'),
                            'POLICY_START_DATE' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                            'vehicle_class_cd' => $get_mapping_mmv_details->vehicleclasscode,
                            'vehicle_subclass_cd' => $get_mapping_mmv_details->num_vehicle_subclass_code,
                            'Registration_Citycode' => $rto_location->Txt_Rto_Location_code,
                            'Vehicle_Modelcode' => $get_mapping_mmv_details->vehiclemodelcode,
                            'First_Regndate' => Carbon::createFromFormat('d-m-Y', $first_reg_date)->format('d/m/Y'),
                            'Type_of_Business' => $businesstype,
                            'Year_of_Manufacture' => Carbon::createFromFormat('d-m-Y', $year_of_manufacture)->format('Y'),
                            'Manufacturer_Code' => $get_mapping_mmv_details->manufacturercode,
                            'Vehicle_Ownedby' => $quote_data->vehicle_owner_type == 'I' ? 'I' : 'O',
                            'Name_Financial_Institution' => $proposal->name_of_financer,
                            'Vehicle_Regno' => $proposal->vehicale_registration_number,
                            'Engine_No' => $proposal->engine_number,
                            'Chassis_No' => $proposal->chassis_number,
                            'Trailer_Chassis_No' => 'kjsdfkn545sdfa645',
                            'Effective_Driving_License' => $cpa_opted,
                            'LPG_CNG_Kit' => '0',
                            'LPG_CNG_IDV' => '0',
                            'Paiddriver_Si' => $PAToPaidDriver_SumInsured,
                            'Total_Premium' => $final_net_premium,
                            'Service_Tax' => $final_gst_amount,
                            'Total_Amoutpayable' => $final_payable_amount,
                            'PrePolicy_Startdate' => ($businesstype == 'NEW BUSINESS' || $prepolicyenddate == 'New') ? '01/01/1900' : Carbon::createFromFormat('d-m-Y', $prepolstartdate)->format('d/m/Y'),
                            'PrePolicy_Enddate' => ($businesstype == 'NEW BUSINESS' || $prepolicyenddate == 'New') ? '01/01/1900' : Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d/m/Y'),
                            'PreInsurerCode' => $proposal->previous_insurance_company,
                            'PrePolicy_Number' => $proposal->previous_policy_number,
                            'Owner_Driver_Nominee_Name' => $proposal->nominee_name,
                            'Owner_Driver_Nominee_Age' => $proposal->nominee_age,
                            'Owner_Driver_Nominee_Relationship' => $proposal->nominee_relationship,
                            'Owner_Driver_Appointee_Name' => $proposal->apointee_name,
                            'Owner_Driver_Appointee_Relationship' => $proposal->appointee_relationship,
                            'Title_Code' => isset($title_code) ? $title_code : 'Mr',
                            'First_Name' => $proposal->first_name,
                            'Last_Name' => !empty($proposal->last_name) ? $proposal->last_name : '.',
                            'Date_of_Birth' => date('d/m/Y', strtotime($proposal->dob)),
                            'Gender' => $proposal->gender,
                            'PAN_Card' => $proposal->pan_number,
                            'Email_Id' => $proposal->email,
                            'Contactno_Office' => '',
                            'Contactno_Home' => '',
                            'Contactno_Mobile' => $proposal->mobile_number,
                            'Reg_Address1' => removeSpecialCharactersFromString($proposal->address_line1, true),
                            'Reg_Address2' => $proposal->address_line2,
                            'Reg_Address3' => $proposal->address_line3,
                            'Reg_Citycode' => $proposal->city_id,
                            'Reg_Statecode' => $proposal->state_id,
                            'Reg_Pin' => $proposal->pincode,
                            'Corres_Address1' => isset($proposal->car_registration_address1) ? $proposal->car_registration_address1 : trim($getAddress['address_1']),
                            'Corres_Address2' => isset($proposal->car_registration_address2) ? $proposal->car_registration_address2 : (trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : ''),
                            'Corres_Address3' => isset($proposal->car_registration_address3) ? $proposal->car_registration_address3 : trim($getAddress['address_3']),
                            'Corres_State' => isset($proposal->car_registration_state) ? $proposal->car_registration_state : $proposal->state,
                            'Corres_Statecode' => isset($proposal->car_registration_state_id) ? $proposal->car_registration_state_id : $proposal->state_id,
                            'Corres_City' => isset($proposal->car_registration_city) ? $proposal->car_registration_city : $proposal->city,
                            'Corres_Citycode' => isset($proposal->car_registration_city_id) ? $proposal->car_registration_city_id : $proposal->city_id,
                            'Corres_Pin' => isset($proposal->car_registration_pincode) ? $proposal->car_registration_pincode : $proposal->pincode,
                            'Data1' => 'CC',
                            'Data2' => '',
                            'Data3' => '',
                            'Data4' => '',
                            'Data5' => '',
                            'PolicyTenure' => '1',
                            'IsCustomerAuthenticationDone' => '1',
                            'AuthenticationType' => 'OTP',
                            'UIDNo' => 'kjtjij6454',
                            'GSTIN' => $proposal->gst_number,
                            'privateuse' => '0',
                            'POSPCOde' => '',
                            'Num_Of_LL_Drivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                            'EIA_NUMBER' => '9999999999999',
                            'ELECTRIC_IDV' => isset($ElectricalaccessSI) ? $ElectricalaccessSI : '0',
                            'NONELECTRIC_IDV' => isset($NonElectricalaccessSI) ? $NonElectricalaccessSI : '0',
                            'NO_OF_PAID_DRIVERS' => isset($pa_paid_driver) ? $pa_paid_driver : '0',
                            'IS_PREVIOUS_CLAIM' => $IsPreviousClaim,
                            'NO_OF_TRAILERS' => '0',
                            'NO_OF_TRAILERS_IDV' => '0',
                            'LIMITED_OWN_PREMISES' => '0',
                            'INCLUSION_IMT_23' =>  isset($imt23) ? $imt23 : '0',
                            'PREVIOUS_DISCOUNT' => isset($requestData->previous_ncb) ? $requestData->previous_ncb : '0',
                            'SumInsured' => $idv,
                        ],
                    ],
                ];
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $proposal_array[1]['xmlmotorpolicy']['KYCID'] = $proposal->ckyc_reference_id;
                }

                if ($product_data->premium_type_id == 4) {
                    $proposal_array[1]['xmlmotorpolicy']['CustomerInspection'] = '1';
                }

                HdfcErgoPremiumDetailController::saveMiscPremiumDetails($get_response['webservice_id']);

                $get_response = getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TRACTOR_PROPOSAL_URL'),
                    $proposal_array,
                    'hdfc_ergo',
                    [
                        'root_tag' => 'xmlmotorpolicy',
                        'section' => $product_data->product_sub_type_code,
                        'method' => 'Submit Proposal',
                        'requestMethod' => 'post',
                        'enquiryId' => customDecrypt($request['userProductJourneyId']),
                        'productName' => $product_data->product_sub_type_name,
                        'transaction_type' => 'proposal',
                    ]
                );
                $submit_proposal_data = $get_response['response'];
                if (preg_match('/Service Unavailable/i', $submit_proposal_data)) {
                    return response()->json(
                        [
                            'status' => false,
                            'msg' => 'Insurer not reachable',
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                        ]
                    );
                }

                if ($submit_proposal_data) {
                    $submit_proposal_data = html_entity_decode($submit_proposal_data);
                    $submit_proposal_data = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $submit_proposal_data);
                    $doc = @simplexml_load_string($submit_proposal_data);
                    if (!$doc) {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => 'HDFC is unable to process your request at this moment. Please contact support.'
                        ]);
                    }
                    $submit_proposal_data = XmlToArray::convert($submit_proposal_data);

                    $error_message = $submit_proposal_data['soap:Body']['soap:Fault']['soap:Reason']['soap:Text']['@content'] ?? '';
                    if(!empty($error_message)) {
                        return response()->json(
                            [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => $error_message
                            ]
                        );
                    }
                    if(!isset($submit_proposal_data['WsResult'])) {
                        return response()->json(
                            [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => 'Something went wrong. Unable to parse the response.',
                            ]
                        );
                    }

                    if ($submit_proposal_data['WsResult']['WsResultSet']['WsStatus'] == 0) {
                        if(empty(trim($submit_proposal_data['WsResult']['WsResultSet']['WsMessage']))) {
                            return response()->json(
                                [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'msg' => 'HDFC is unable to generate proposal number.',
                                ]
                            );
                        }
                        $vehicleDetails = [
                            'manufacture_name' => $get_mapping_mmv_details->manufacturer,
                            'model_name' => $get_mapping_mmv_details->vehiclemodel,
                            'version' => $get_mapping_mmv_details->txt_variant,
                            'fuel_type' => $get_mapping_mmv_details->txt_fuel,
                            'seating_capacity' => $get_mapping_mmv_details->seatingcapacity,
                            'carrying_capacity' => $get_mapping_mmv_details->carryingcapacity,
                            'cubic_capacity' => $get_mapping_mmv_details->cubiccapacity,
                            'gross_vehicle_weight' => $get_mapping_mmv_details->grossvehicleweight,
                            'vehicle_type' => '',
                        ];

                        $pa_paid_driver = $premium_data['NUM_PA_PAID_DRIVER_PREM'];
                        $liabilities = $premium_data['NUM_NOOFLLDRIVERS_PREM'] + $premium_data['NUM_PA_OWNER_DRIVER_PREM'];
                        $final_tp_premium = $premium_data['NUM_BASIC_TP_PREM'] + $liabilities + $pa_paid_driver;
                        $motor_electric_accessories_value = $premium_data['NUM_ELECTRICAL_PREM'];
                        $motor_non_electric_accessories_value = $premium_data['NUM_NON_ELECTRICAL_PREM'];
                        $total_own_damage = $premium_data['NUM_BASIC_OD_PREM'];
                        $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;
                        $final_od_premium = $total_own_damage + $total_accessories_amount;

                        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'policy_start_date' => $policy_start_date,
                                'policy_end_date' => $policy_end_date,
                                'tp_start_date' => $policy_start_date,
                                'tp_end_date' => $policy_end_date,
                                'tp_premium' => $final_tp_premium,
                                'od_premium' => $final_od_premium,
                                'final_premium' => $final_net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'proposal_no' => $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'],
                                'final_payable_amount' => $final_payable_amount,
                                'ic_vehicle_details' => json_encode($vehicleDetails),
                                'is_breakin_case' => $is_breakin ? "Y" : "N",
                            ]);
                            
                        $journeyStageData = [
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'proposal_id' => $proposal->user_proposal_id,
                        ];
                        if ($is_breakin) {

                            $cvBreakinStatus = [
                                'ic_id' => $product_data->company_id,
                                'user_proposal_id' => $proposal->user_proposal_id,
                                'breakin_number' => $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'],
                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_response' => json_encode($submit_proposal_data),
                                'created_at' => date('Y-m-d H:i:s')
                            ];

                            DB::table('cv_breakin_status')->updateOrInsert(['user_proposal_id' => $proposal->user_proposal_id], $cvBreakinStatus);

                            $is_breakin = 'Y';
                            $inspection_no = $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'];
                            $journeyStageData['stage'] = STAGE_NAMES['INSPECTION_PENDING'];
                        } else {
                            $is_breakin = '';
                            $inspection_no = '';
                            $journeyStageData['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                        }
                        updateJourneyStage($journeyStageData);

                        return response()->json([
                            'status' => true,
                            'msg' => 'Proposal submitted successfully',
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'id' => $proposal->user_proposal_id,
                                'proposalNo' => $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'],
                                'is_breakin' => $is_breakin,
                                'inspection_number' => $inspection_no,
                            ],
                        ]);
                    } else {
                        $err_msg = $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'];
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => empty($err_msg) ? 'HDFC is unable to process your request at this moment. Please contact support.' : $err_msg,
                            // 'data' => [
                            //     'id' => $proposal->user_proposal_id,
                            //     'proposalNo' => '',
                            // ],
                        ]);
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Insurer not reachable',
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => 'Insurer not reachable',
                ]);
            }
        }
    }
}


