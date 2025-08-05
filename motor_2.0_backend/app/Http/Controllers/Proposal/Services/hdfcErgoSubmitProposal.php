<?php

namespace App\Http\Controllers\Proposal\Services;

use App\Http\Controllers\Controller;
use App\Models\IcVersionMapping;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\UserProposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\Proposal\Services\hdfcErgoSubmitProposalMiscd;
use App\Http\Controllers\SyncPremiumDetail\Services\HdfcErgoPremiumDetailController;
use App\Models\AgentIcRelationship;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class hdfcErgoSubmitProposal extends Controller
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request)
    {
        $productData = getProductDataByIc($request['policyId']);
        $is_MISC = policyProductType($productData->policy_id)->parent_id;
        if($is_MISC == 3){
            return hdfcErgoSubmitProposalMiscd::submit($proposal, $request);
        }elseif(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_REQUEST_TYPE') == 'JSON') {
            return self::jsonSubmit($proposal, $request);
        } else {
            return self::xmlSubmit($proposal, $request);
        }
    }

    public static function xmlSubmit($proposal, $request)
    {
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $product_data = getProductDataByIc($request['policyId']);

        $requestData = $quote_data;
        $productData = $product_data;
        $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
        if ($is_GCV) {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo', $requestData->gcv_carrier_type);
        }else{
            $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
        }

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
            $rto_location = DB::table('cv_hdfc_ergo_rto_location')
            ->where('Txt_Rto_Location_desc', 'like', '%' . $rto_city . '%')
            ->where('Num_Vehicle_Subclass_Code', '=', $get_mapping_mmv_details->vehicle_subclass_code)
            ->where('Num_Vehicle_Class_code', '=', $get_mapping_mmv_details->vehicle_class_code)
            ->first();
            $rto_location = keysToLower($rto_location);

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
            $businesstype = 'New Business';
            $policy_start_date = date('d-m-Y');
            $IsPreviousClaim = '0';
            $prepolstartdate = '01-01-1900';
            $prepolicyenddate = '01-01-1900';
            if ($tp_only) {
                $policy_start_date = date('d-m-Y', strtotime($today . ' + 1 days'));
            }
        } else {
            $businesstype = 'Rollover';
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

            if (!empty($selected_addons) && $selected_addons->additional_covers != '') {
                $additional_covers = json_decode($selected_addons->additional_covers);
                foreach ($additional_covers as $value) {
                    //PCV
                    if ($value->name == 'PA cover for additional paid driver') {
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                    }
                    if ($value->name == 'LL paid driver') {
                        $nooflldrivers = '1';
                    }

                    //GCV
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
            $addons = json_decode($selected_addons->applicable_addons);
            if (!empty($addons)) {   
                foreach ($addons as $value) {
                    if ($value->name == 'IMT - 23') {
                        $IMT23 = '1';
                    }
                    if ($value->name == 'Zero Depreciation') {
                        $zeroDepth = '1';
                    }
                }
            }

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

            if ($is_GCV) {
                // $rto_location->Txt_Rto_Location_code = '69227';
                // $get_mapping_mmv_details->vehicle_model_code = '24813';
                // $get_mapping_mmv_details->vehicle_class_code = '24';
                // $get_mapping_mmv_details->vehicle_subclass_code = '7';
                // $get_mapping_mmv_details->manufacturer_code = '18';
                // $rto_location->Num_State_Code = '14';
            }

            $productcode = $is_GCV ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMP_GCV_PRODUCT_CODE') : config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMP_PCV_PRODUCT_CODE');

            $model_config_premium = [
                'VehicleClassCode=' . $get_mapping_mmv_details->vehicle_class_code . '&str',
                [
                    'CommercialVehicleInput' => [
                        'agentcode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE'),
                        'productcode' => $productcode,
                        'typeofbusiness' => $businesstype,
                        'policystartdate' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                        'policyenddate' => Carbon::createFromFormat('d-m-Y', $policy_end_date)->format('d/m/Y'),
                        'rtolocationcode' => $rto_location->txt_rto_location_code,
                        'vehiclemodelcode' => $get_mapping_mmv_details->vehicle_model_code,
                        'vehicleclasscode' => $get_mapping_mmv_details->vehicle_class_code,
                        'vehiclesubclasscode' => $get_mapping_mmv_details->vehicle_subclass_code,
                        'manufacturer_code' => $get_mapping_mmv_details->manufacturer_code,
                        'purchaseregndate' => str_replace('-', '/', $first_reg_date),
                        'IdvAmount' => $idv,
                        'InclusionIMT23' => isset($IMT23) ? $IMT23 : '0',
                        'nooflldrivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                        'paiddriversi' => $PAToPaidDriver_SumInsured,
                        'noofemployees' => '0',
                        'IsPreviousClaim' => $previous_policy_type == 'Third-party' ? '0' : $IsPreviousClaim,
                        'previousdiscount' => $previous_policy_type == 'Third-party' ? '0' : $quote_data->previous_ncb,
                        'prepolstartdate' => ($businesstype == 'New Business' || $prepolicyenddate == 'New') ? '' : Carbon::createFromFormat('d-m-Y', $prepolstartdate)->format('d/m/Y'),
                        'prepolicyenddate' => ($businesstype == 'New Business' || $prepolicyenddate == 'New') ? '' : Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d/m/Y'),
                        'IsZeroDepth' => $zeroDepth,
                        'electicalacc' => (($quote_data->electrical_acessories_value != '') ? $quote_data->electrical_acessories_value : '0'),
                        'nonelecticalacc' => (($quote_data->nonelectrical_acessories_value != '') ? $quote_data->nonelectrical_acessories_value : '0'),
                        'lpg_cngkit' => (($quote_data->bifuel_kit_value != '') ? $quote_data->bifuel_kit_value : '0'),
                        'txt_cust_type' => $quote_data->vehicle_owner_type == 'I' ? 'I' : 'C',
                        'is_pa_cover_owner_driver' => isset($is_pa_cover_owner_driver) ? $is_pa_cover_owner_driver : 0,
                        'NoOfFPP' => '0',
                        'NoOfNFPP' => '0',
                        'Cust_StateCode' => $rto_location->num_state_code,
                        'CustomerInspection' => '0',
                        'limitedownpremises' => '0',
                        'privateuse' => $requestData->gcv_carrier_type == 'PUBLIC' ? "0" : "1" ,
                        'Bustype' => '',
                        'Name_Financial_Institution' => $proposal->name_of_financer,
                    ],
                ],
            ];

            if ($product_data->premium_type_id == 4) {
                $model_config_premium[1]['CommercialVehicleInput']['manufacturingyear'] = date('Y', strtotime('01-' . $quote_data->manufacture_year));
                $model_config_premium[1]['CommercialVehicleInput']['page_calling'] = '1';
                $model_config_premium[1]['CommercialVehicleInput']['num_is_rti'] = '0';
            }

            $get_response = getWsData(
                config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_PREMIUM_URL'),
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
                }elseif(isset($premium_data['TXT_ERR_MSG']) && !empty($premium_data['TXT_ERR_MSG'])){
                    return response()->json([
                        'status' => false,
                        'msg' => $premium_data['TXT_ERR_MSG'],
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                    ]);
                }
                if (isset($premium_data['PREMIUMOUTPUT']['PARENT'])) {
                    $premium_data = $premium_data['PREMIUMOUTPUT']['PARENT'][0];
                } else {
                    $premium_data = $premium_data['PREMIUMOUTPUT'];
                }

                $final_net_premium = $premium_data['NUM_NET_PREMIUM'];
                $final_gst_amount = $premium_data['NUM_SERVICE_TAX'];
                $final_payable_amount = $premium_data['NUM_TOTAL_PREMIUM'];

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
                
                $proposal_array = [
                    'str',
                    [
                        'xmlmotorpolicy' => [
                            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE'),
                            'Type_of_Business' => $businesstype,
                            'Policy_Startdate' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                            'Policy_Enddate' => Carbon::createFromFormat('d-m-Y', $policy_end_date)->format('d/m/Y'),
                            'Policy_Type' => $previous_policy_type == 'Third-party' ? 'T' : 'C',
                            'Year_of_Manufacture' => Carbon::createFromFormat('d-m-Y', $year_of_manufacture)->format('d/m/Y'),
                            'Registration_Citycode' => $rto_location->txt_rto_location_code,
                            'Registration_City' => $proposal->city,
                            'Manufacturer_Code' => $get_mapping_mmv_details->manufacturer_code,
                            'Manufacture_Name' => $get_mapping_mmv_details->manufacturer,
                            'Vehicle_Modelcode' => $get_mapping_mmv_details->vehicle_model_code,
                            'Vehicle_Model' => $get_mapping_mmv_details->vehicle_model,
                            'Purchase_Regndate' => Carbon::createFromFormat('d-m-Y', $first_reg_date)->format('d/m/Y'),
                            'Vehicle_Regno' => $proposal->vehicale_registration_number,
                            'Engine_No' => $proposal->engine_number,
                            'Chassis_No' => $proposal->chassis_number,
                            'Fuel_Type' => $premium_data['NUM_FUEL_TYPE'],
                            'Vehicle_Ownedby' => $quote_data->vehicle_owner_type == 'I' ? 'I' : 'O',
                            'Ex-showroom_Price' => $ex_showroom,
                            'Sum_Insured' => $premium_data['NUM_VEHICLE_IDV'],
                            'Electical_Acc' => (($quote_data->electrical_acessories_value != '') ? $quote_data->electrical_acessories_value : '0'), //$premium_data['NUM_ELEC_ACC_PREM'],
                            'NonElectical_Acc' => (($quote_data->nonelectrical_acessories_value != '') ? $quote_data->nonelectrical_acessories_value : '0'), //$premium_data['NUM_NON_ELEC_ACC_PREM'],
                            'No_of_LLdrivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                            'No_of_Employees' => '0',
                            'limitedownpremises' => '0',
                            'privateuse' => $requestData->gcv_carrier_type == 'PUBLIC' ? "0" : "1" ,
                            'InclusionIMT23' => isset($IMT23) ? $IMT23 : '0',
                            'IsZeroDepth' => $zeroDepth,
                            'NoOfFPP' => '0',
                            'NoOfNFPP' => '0',
                            'NCB_ExpiringPolicy' => $previous_policy_type == 'Third-party' ? '0' : $quote_data->previous_ncb,
                            'NCB_RenewalPolicy' => $NCB_RenewalPolicy,
                            'PreInsurerCode' => $proposal->previous_insurance_company,
                            'PrePolicy_Number' => $proposal->previous_policy_number,
                            'Total_Premium' => $final_net_premium,
                            'Service_Tax' => $final_gst_amount,
                            'Total_Amoutpayable' => $final_payable_amount,
                            'PrePolicy_Startdate' => ($businesstype == 'New Business' || $prepolicyenddate == 'New') ? '01/01/1900' : Carbon::createFromFormat('d-m-Y', $prepolstartdate)->format('d/m/Y'),
                            'PrePolicy_Enddate' => ($businesstype == 'New Business' || $prepolicyenddate == 'New') ? '01/01/1900' : Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d/m/Y'),
                            'IsPrevious_Claim' => $previous_policy_type == 'Third-party' ? '0' : $IsPreviousClaim,
                            'First_Name' => $proposal->first_name,
                            'Last_Name' => !empty($proposal->last_name) ? $proposal->last_name : '.',
                            'Date_of_Birth' => date('d/m/Y', strtotime($proposal->dob)),
                            'Gender' => $proposal->gender,
                            'Email_Id' => $proposal->email,
                            'Contactno_Mobile' => $proposal->mobile_number,
                            'Car_Address1' => trim($getAddress['address_1']),
                            'Car_Address2' => trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : '',
                            'Car_Address3' => trim($getAddress['address_3']),
                            'Car_State' => $proposal->state,
                            'Car_Statecode' => $proposal->state_id,
                            'Car_City' => $proposal->city,
                            'Car_Citycode' => $proposal->city_id,
                            'Car_Pin' => $proposal->pincode,
                            'Corres_Address1' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_address1 : trim($getAddress['address_1']),
                            'Corres_Address2' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_address2 : (trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : ''),
                            'Corres_Address3' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_address3 : trim($getAddress['address_3']),
                            'Corres_State' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_state : $proposal->state,
                            'Corres_Statecode' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_state_id : $proposal->state_id,
                            'Corres_City' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_city : $proposal->city,
                            'Corres_Citycode' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_city_id : $proposal->city_id,
                            'Corres_Pin' => !($proposal->is_car_registration_address_same) ? $proposal->car_registration_pincode : $proposal->pincode,
                            'LPG-CNG_Kit' => (($quote_data->bifuel_kit_value != '') ? $quote_data->bifuel_kit_value : '0'),
                            'Owner_Driver_Nominee_Name' => $proposal->nominee_name,
                            'Owner_Driver_Nominee_Age' => $proposal->nominee_age,
                            'Owner_Driver_Nominee_Relationship' => $proposal->nominee_relationship,
                            'Owner_Driver_Appointee_Name' => $proposal->apointee_name,
                            'Owner_Driver_Appointee_Relationship' => $proposal->appointee_relationship,
                            'is_pa_cover_owner_driver' => isset($is_pa_cover_owner_driver) ? $is_pa_cover_owner_driver : 0,
                            'BiFuelType' => 'CNG',
                            'PAN_Card' => $proposal->pan_number,
                            'GSTIN' => $proposal->gst_number,
                            'Paiddriver_Si' => $PAToPaidDriver_SumInsured,
                            'Name_Financial_Institution' => $proposal->name_of_financer,
                        ],
                    ],
                ];

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $proposal_array[1]['xmlmotorpolicy']['KYCID'] = $proposal->ckyc_reference_id;
                }

                if ($product_data->premium_type_id == 4) {
                    $proposal_array[1]['xmlmotorpolicy']['CustomerInspection'] = '1';
                }

                HdfcErgoPremiumDetailController::saveXmlPremiumDetails($get_response['webservice_id']);

                $get_response = getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_PROPOSAL_URL'),
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
                            'model_name' => $get_mapping_mmv_details->vehicle_model,
                            'version' => $get_mapping_mmv_details->txt_variant,
                            'fuel_type' => $get_mapping_mmv_details->txt_fuel,
                            'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                            'carrying_capacity' => $get_mapping_mmv_details->carrying_capacity,
                            'cubic_capacity' => $get_mapping_mmv_details->cubic_capacity,
                            'gross_vehicle_weight' => $get_mapping_mmv_details->gross_vehicle_weight,
                            'vehicle_type' => 'PCV',
                        ];

                        $pa_paid_driver = $premium_data['NUM_PA_PAID_DRVR_PREM'];
                        $liabilities = $premium_data['NUM_LL_PAID_DRIVER'] + $premium_data['NUM_PA_COVER_OWNER_DRVR'];
                        $final_tp_premium = $premium_data['NUM_TP_RATE'] + $liabilities + $premium_data['NUM_LPG_CNGKIT_TP_PREM'] + $pa_paid_driver;

                        $motor_electric_accessories_value = $premium_data['NUM_ELEC_ACC_PREM'];
                        $motor_non_electric_accessories_value = $premium_data['NUM_NON_ELEC_ACC_PREM'];
                        $total_own_damage = $premium_data['NUM_BASIC_OD_PREMIUM'];
                        $total_accessories_amount = $motor_electric_accessories_value + $motor_non_electric_accessories_value;
                        $final_od_premium = $total_own_damage + $total_accessories_amount + $premium_data['NUM_LPG_CNGKIT_OD_PREM'];

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
                                'final_payable_amount' => $premium_data['NUM_TOTAL_PREMIUM'],
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
        } else if ($tp_only) {

            $selected_addons = DB::table('selected_addons')
                ->where('user_product_journey_id', $enquiryId)
                ->first();

            $PAToPaidDriver_SumInsured = '0';
            $bifuel = 'N';

            if (!empty($selected_addons) && $selected_addons->additional_covers != '') {
                $additional_covers = json_decode($selected_addons->additional_covers);
                foreach ($additional_covers as $value) {
                    // PCV
                    if ($value->name == 'PA cover for additional paid driver') {
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                    }
                    if ($value->name == 'LL paid driver') {
                        $nooflldrivers = '1';
                    }

                    //GCV
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

            if (!empty($selected_addons) && $selected_addons->accessories != '') {
                $accessories = json_decode($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if ($value->name == 'External Bi-Fuel Kit CNG/LPG') {
                        $bifuel = 'Y';
                    }
                }
            }
            $TPPD = 'N';
            if (!empty($selected_addons) && $selected_addons->discounts != '') {
                $discount = json_decode($selected_addons->discounts);
                foreach ($discount as $value) {
                    if ($value->name == 'TPPD Cover') {
                        $TPPD = 'Y';
                    }
                }
            }
            $cpa_opted = 'N';
            if (!empty($selected_addons) && $selected_addons->compulsory_personal_accident != '') {
                $cpa = json_decode($selected_addons->compulsory_personal_accident);
                foreach ($cpa as $value) {
                    if (isset($value->name)) {
                        $cpa_opted = 'Y';
                    }
                }
            }
            $cpa_opted = $quote_data->vehicle_owner_type == 'I' ? $cpa_opted : 'N';

            $productcode = $is_GCV ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_GCV_PRODUCT_CODE') : config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_PCV_PRODUCT_CODE');
            
            //TP
            $model_config_premium = [
                'VehicleClassCode='. $get_mapping_mmv_details->vehicle_class_code .'&str',
                [
                    'TPPremium' => [
                        'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_AGENT_CODE'),
                        'ProductCode' => $productcode,
                        'vehicleModelCode' => $get_mapping_mmv_details->vehicle_model_code,
                        'Num_Of_Paid_Driver' => 0,
                        'Num_Of_SI_Paid_Driver' => $PAToPaidDriver_SumInsured,
                        'Num_Of_UnNamedPasgr' => 0,
                        'Num_Of_SI_UnNamedPasgr' => 0,
                        'Num_Of_NamedPasgr' => 0,
                        'Num_Of_SI_NamedPasgr' => 0,
                        'IB_LPG_CNGKit' => 'N',
                        'EX_LPG_CNGKit' => $bifuel,
                        'Num_Of_LL_Drivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                        'Num_Of_LL_Employee' => 0,
                        'Num_Of_Trailers' => 0,
                        'TPPD' => $TPPD,
                        'CustomerType' => $quote_data->vehicle_owner_type == 'I' ? 'I' : 'O',
                        'UnNamed_Pillion' => 'N',
                        'SI_Unnamed_Pillion' => 0,
                        'Num_Eff_Driving_Lic' => $cpa_opted,
                        'Distance_To_From' => 0,
                        'RTO_Location_Code' => $rto_location->txt_rto_location_code,
                        'Policy_Effective_From_Date' => Carbon::createFromFormat('d-m-Y', $policy_start_date)->format('d/m/Y'),
                        'Name_Financial_Institution' => $proposal->name_of_financer,
                    ],
                ],
            ];

            $get_response = getWsData(
                config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_PREMIUM_URL'),
                $model_config_premium, 'hdfc_ergo',
                [
                    'root_tag' => 'PCVPremiumCalc',
                    'section' => $product_data->product_sub_type_code,
                    'method' => 'TP Premium Calculation',
                    'requestMethod' => 'post',
                    'enquiryId' => $enquiryId,
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
                $premium_data = $premium_data['PREMIUMOUTPUT'];
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
                $realtionships = [
                    'Daughter'  => 'D',
                    'Father'    => 'F',
                    'Guardian'  => 'G',
                    'Mother'    => 'M',
                    'Others'    => 'O',
                    'Son'       => 'S',
                    'Spouse'    => 'SP',
                ];
                $nominee_relationship = isset($realtionships[$proposal->nominee_relationship]) ? $realtionships[$proposal->nominee_relationship] : 'F';
                $year_of_manufacture = '01-' . $proposal->vehicle_manf_year;

                $final_net_premium = $premium_data['NUM_NET_PREMIUM'];
                $final_gst_amount = $premium_data['NUM_SERVICE_TAX'];
                $final_payable_amount = $premium_data['NUM_TOTAL_PREMIUM'];
                
                if($businesstype == 'New Business' || $requestData->previous_policy_type == 'Not sure')
                {
                    $PrePolicy_Startdate = '';
                    $PrePolicy_Enddate = '';                    
                }
                else
                {
                    $PrePolicy_Startdate = Carbon::createFromFormat('d-m-Y', $prepolstartdate)->format('d-M-Y');
                    $PrePolicy_Enddate = Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d-M-Y');               
                }

                $proposal_array = [
                    'str',
                    [
                        'xmlmotorpolicy' => [
                            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_AGENT_CODE'),
                            'Product_Code' => $productcode,
                            'Type_of_Business' => $businesstype == 'Rollover' ? 'RO' : 'NB',
                            'Vehicle_Subclass_Code' => $get_mapping_mmv_details->vehicle_subclass_code,
                            'First_Regndate' => date('d-M-Y', strtotime($first_reg_date)),
                            'Year_of_Manufacture' => Carbon::createFromFormat('d-m-Y', $year_of_manufacture)->format('d-M-Y'),
                            'Registration_Citycode' => $rto_location->txt_rto_location_code,
                            'Manufacturer_Code' => $get_mapping_mmv_details->manufacturer_code,
                            'Vehicle_Modelcode' => $get_mapping_mmv_details->vehicle_model_code,
                            'Vehicle_Ownedby' => $quote_data->vehicle_owner_type == 'I' ? 'I' : 'O',
                            'Vehicle_Regno' => $proposal->vehicale_registration_number,
                            'Engine_No' => $proposal->engine_number,
                            'Chassis_No' => $proposal->chassis_number,
                            'Effective_Driving_License' => $cpa_opted, //$quote_data->vehicle_owner_type == 'I' ? 'Y' : 'N',
                            'LPG_CNG_Kit' => $bifuel,
                            'TPPD' => $TPPD,
                            'Paiddriver_Si' => $PAToPaidDriver_SumInsured,
                            'No_of_Unnamed_Passengers' => '0',
                            'No_of_Employees' => '0',
                            'Total_Premium' => $final_net_premium,
                            'Service_Tax' => $final_gst_amount,
                            'Total_Amoutpayable' => $final_payable_amount,
                            'PrePolicy_Startdate' => $PrePolicy_Startdate,//$businesstype == 'New Business' ? '' : Carbon::createFromFormat('d-m-Y', $prepolstartdate)->format('d-M-Y'),
                            'PrePolicy_Enddate' => $PrePolicy_Enddate,//$businesstype == 'New Business' ? '' : Carbon::createFromFormat('d-m-Y', $prepolicyenddate)->format('d-M-Y'),
                            'PreInsurerCode' => $proposal->previous_insurance_company,
                            'PrePolicy_Number' => $proposal->previous_policy_number,
                            'Owner_Driver_Nominee_Name' => $proposal->nominee_name,
                            'Owner_Driver_Nominee_Age' => $proposal->nominee_age,
                            'Owner_Driver_Nominee_Relationship' => $nominee_relationship,//$proposal->nominee_relationship,
                            'Owner_Driver_Appointee_Name' => '', //$proposal->apointee_name,
                            'Title_Code' => isset($title_code) ? $title_code : 'Mr',
                            'First_Name' => $proposal->first_name,
                            'Last_Name' => !empty($proposal->last_name) ? $proposal->last_name : '.',
                            'Date_of_Birth' => date('d-M-Y', strtotime($proposal->dob)),
                            'Gender' => isset($proposal->gender) ? $proposal->gender : 'Male',
                            'PAN_Card' => $proposal->pan_number,
                            'Email_Id' => $proposal->email,
                            'GSTIN' => $proposal->gst_number,
                            'Contactno_Mobile' => $proposal->mobile_number,
                            'Reg_Address1' => removeSpecialCharactersFromString($proposal->address_line1, true),
                            'Reg_Address2' => $proposal->address_line2,
                            'Reg_Address3' => $proposal->address_line3,
                            'Reg_Citycode' => $proposal->city_id,
                            'Reg_Statecode' => $proposal->state_id,
                            'Reg_Pin' => $proposal->pincode,
                            'Corres_Address1' => isset($proposal->car_registration_address1) ? $proposal->car_registration_address1 : removeSpecialCharactersFromString($proposal->address_line1, true),
                            'Corres_Address2' => isset($proposal->car_registration_address2) ? $proposal->car_registration_address2 : $proposal->address_line2,
                            'Corres_Address3' => isset($proposal->car_registration_address3) ? $proposal->car_registration_address3 : $proposal->address_line3,
                            'Corres_Citycode' => isset($proposal->car_registration_city_id) ? $proposal->car_registration_city_id : $proposal->city_id,
                            'Corres_Statecode' => isset($proposal->car_registration_state_id) ? $proposal->car_registration_state_id : $proposal->state_id,
                            'Corres_Pin' => isset($proposal->car_registration_pincode) ? $proposal->car_registration_pincode : $proposal->pincode,
                            'Data1' => 'CC',
                            'Num_Of_LL_Drivers' => isset($nooflldrivers) ? $nooflldrivers : '0',
                            'Name_Financial_Institution' => $proposal->name_of_financer,
                        ],
                    ],
                ];
                
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $proposal_array[1]['xmlmotorpolicy']['KYCID'] = $proposal->ckyc_reference_id;
                }
                if($requestData->previous_policy_type == 'Not sure')
                {
                    $proposal_array[1]['xmlmotorpolicy']['PrePolicy_Startdate'] = NULL;
                    $proposal_array[1]['xmlmotorpolicy']['PrePolicy_Enddate'] = NULL;
                    $proposal_array[1]['xmlmotorpolicy']['PreInsurerCode'] = NULL;
                    $proposal_array[1]['xmlmotorpolicy']['PrePolicy_Number'] = NULL;
                    
                }

                $get_response = getWsData(
                    config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_TP_PROPOSAL_URL'),
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
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => 'Insurer not reachable',
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
                            'model_name' => $get_mapping_mmv_details->vehicle_model,
                            'version' => $get_mapping_mmv_details->txt_variant,
                            'fuel_type' => $get_mapping_mmv_details->txt_fuel,
                            'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                            'carrying_capacity' => $get_mapping_mmv_details->carrying_capacity,
                            'cubic_capacity' => $get_mapping_mmv_details->cubic_capacity,
                            'gross_vehicle_weight' => $get_mapping_mmv_details->gross_vehicle_weight,
                            'vehicle_type' => 'PCV',
                        ];

                        $lpg_cng_kit = 0;

                        if (isset($premium_data['NUM_IB_LPG_CNGKIT']) && (int) $premium_data['NUM_IB_LPG_CNGKIT'] > 0)
                        {
                            $lpg_cng_kit = (int) $premium_data['NUM_IB_LPG_CNGKIT'];
                        }
                        elseif (isset($premium_data['NUM_EX_LPG_CNGKIT']) && (int) $premium_data['NUM_EX_LPG_CNGKIT'] > 0)
                        {
                            $lpg_cng_kit = (int) $premium_data['NUM_EX_LPG_CNGKIT'];
                        }

                        $pa_paid_driver = $premium_data['NUM_PA_PAID_DRIVER'];
                        $final_tp_premium = $premium_data['NUM_TP_PREMIUM'] + $lpg_cng_kit + $pa_paid_driver + $premium_data['NUM_PA_OWNER_DRIVER'] + $premium_data['NUM_NOOFLLDRIVERS'];

                        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'policy_start_date' => $policy_start_date,
                                'policy_end_date' => $policy_end_date,
                                'tp_start_date' => $policy_start_date,
                                'tp_end_date' => $policy_end_date,
                                'tp_premium' => $final_tp_premium,
                                'final_premium' => $final_net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'proposal_no' => $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'],
                                'final_payable_amount' => $premium_data['NUM_TOTAL_PREMIUM'],
                                'ic_vehicle_details' => json_encode($vehicleDetails),
                            ]);

                        return response()->json([
                            'status' => true,
                            'msg' => 'Proposal submitted successfully',
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'id' => $proposal->user_proposal_id,
                                'proposal_no' => $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'],
                            ],
                        ]);
                    } else {
                        $err_msg = $submit_proposal_data['WsResult']['WsResultSet']['WsMessage'];
                        return response()->json([
                            'status' => false,
                            'msg' => empty($err_msg) ? 'HDFC is unable to process your request at this moment. Please contact support.' : $err_msg,
                            // 'data' => [
                            //     'id' => $proposal->user_proposal_id,
                            //     'proposal_no' => '',
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

    public static function jsonSubmit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote_log_data = DB::table('quote_log')
            ->where('user_product_journey_id',$enquiryId)
            ->select('idv')
            ->first();
        $idv = $quote_log_data->idv;

        if (empty($requestData->rto_code)) {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request' => [
                    'message' => 'RTO not available',
                    'rto_code' => $requestData->rto_code
                ]
            ]; 
        }
    
        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
    
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [   
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
                'request' => [
                    'mmv' => $mmv
                ],
            ];          
        }
    
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    
        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
            return  [   
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'message' => 'Vehicle Not Mapped',
                    'mmv' => $mmv
                ]
            ];        
        } elseif ($mmv->ic_version_code == 'DNE') {
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
    
        $parent_id = get_parent_code($productData->product_sub_type_id);
    
        $premium_type = DB::table('master_premium_type')
            ->where('id',$productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
    
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    
        // $mmv_data = [
        //     'manf_name' => $mmv->manufacturer,
        //     'model_name' => $mmv->vehicle_model,
        //     'version_name' => $mmv->txt_variant,
        //     'seating_capacity' => $mmv->seating_capacity,
        //     'carrying_capacity' => $mmv->carrying_capacity,
        //     'cubic_capacity' => $mmv->cubic_capacity,
        //     'fuel_type' =>  $mmv->txt_fuel,
        //     'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
        //     'vehicle_type' => $parent_id,
        //     'version_id' => $mmv->version_id,
        // ];
    
        $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();
    
        if (empty($rto_data)) {
            return [
                'status' => false,
                'premium' => 0,
                'message' => 'RTO code does not exist',
                'request' => [
                    'message' => 'RTO code does not exist',
                    'rto_code' => $requestData->rto_code
                ]
            ];
        }

        $vehicle_class_code = [
            'TAXI' => [
                'vehicle_class_code' => 41,
                'vehicle_sub_class_code' => 1
            ],
            'AUTO-RICKSHAW' => [
                'vehicle_class_code' => 41,
                'vehicle_sub_class_code' => 5
            ],
            'ELECTRIC-RICKSHAW' => [
                'vehicle_class_code' => 41,
                'vehicle_sub_class_code' => 5
            ],
            'PICK UP/DELIVERY/REFRIGERATED VAN' => [
                'vehicle_class_code' => 24,
                'vehicle_sub_class_code' => 2
            ],
            'DUMPER/TIPPER' => [
                'vehicle_class_code' => 24,
                'vehicle_sub_class_code' => 3
            ],
            'TRUCK' => [
                'vehicle_class_code' => 24,
                'vehicle_sub_class_code' => 7
            ],
            'TRACTOR' => [
                'vehicle_class_code' => 24,
                'vehicle_sub_class_code' => 5
            ],
            'TANKER/BULKER' => [
                'vehicle_class_code' => 24,
                'vehicle_sub_class_code' => 4#6
            ]
        ];
    
        // $rto_location = DB::table('hdfc_ergo_rto_master')
        //     ->where('txt_rto_location_desc', $rto_data->rto_name)
        //     ->where('num_vehicle_class_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_class_code'])
        //     ->where('num_vehicle_subclass_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_sub_class_code'])
        //     ->first();

        $rto_cities = explode('/',  $rto_data->rto_name);
        foreach ($rto_cities as $rto_city)
        {
            $rto_city = strtoupper($rto_city);
            $rto_location = DB::table('hdfc_ergo_rto_master')
                ->where('txt_rto_location_desc', 'like', '%' . $rto_city . '%')
                ->where('num_vehicle_class_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_class_code'])
                ->where('num_vehicle_subclass_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_sub_class_code'])
                ->first();
            $rto_location = keysToLower($rto_location);
            if (!empty($rto_location))
            {
                break;
            }
        }

        if (empty($rto_location)) {
            return [
                'status' => false,
                'premium' => 0,
                'message' => 'RTO details does not exist with insurance company',
                'request' => [ 
                    'rto_code' => $requestData->rto_code,
                    'message' => 'RTO details does not exist with insurance company',
                ]
            ];
        }

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
    
        if ($parent_id == 'PCV') {
            $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2313 : 2314;
        } else {
            $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2317 : 2315;
        }

        if (in_array($productData->premium_type_code, [
            'short_term_3', 'short_term_3_breakin', 'short_term_6', 'short_term_6_breakin'
        ])) {
            $product_code = in_array($premium_type, ['third_party', 'third_party_breakin']) ? 2313 : 2314;
            if ($parent_id == 'GCV') {
                $product_code = in_array($premium_type, ['third_party', 'third_party_breakin']) ? 2317 : 2315;
            }
        }
        $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;

        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
        $motor_manf_year = $motor_manf_year_arr[1];
        $motor_manf_date = '01-'.$requestData->manufacture_year;
        $current_date = Carbon::now()->format('Y-m-d');
    
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $is_vehicle_new = 'false';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', ! is_null($requestData->previous_policy_expiry_date) && $requestData->previous_policy_expiry_date != 'New' ? strtotime($requestData->previous_policy_expiry_date) : time()));

            if ($requestData->business_type == 'breakin' && $tp_only) {
                $today = date('Y-m-d');
                $policy_start_date = date('Y-m-d', strtotime($today . ' + 1 days'));
            }
            
            $registration_number = getRegisterNumberWithHyphen($proposal->vehicale_registration_number);
        } else if ($requestData->business_type == 'newbusiness')  {
            $requestData->vehicle_register_date = date('d-m-Y');
            $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
            $registration_number = 'NEW';
    
            if ($date_difference > 0) {  
                return [
                    'status' => false,
                    'message' => 'Please Select Current Date for New Business',
                    'request' => [
                        'message' => 'Please Select Current Date for New Business',
                        'business_type' => $requestData->business_type,
                        'vehicle_register_date' => $requestData->vehicle_register_date
                    ]
                ];
            }
    
            $is_vehicle_new = 'true';
            $policy_start_date = Carbon::today()->format('Y-m-d');
            $previousNoClaimBonus = 'ZERO';
    
            if ($requestData->vehicle_registration_no == 'NEW') {
                $vehicle_registration_no  = str_replace("-", "", $requestData->rto_code) . "-NEW";
            } else {
                $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            }
        }
    
        $transaction_id = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
    
        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_AUTHENTICATE_URL'), [], 'hdfc_ergo', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Token Generation',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
            'product_code' => $product_code,
            'transaction_id' => $transaction_id,
            'headers' => [
                'Content-type' => 'application/json',
                'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                'PRODUCT_CODE' => $product_code,
                'TransactionID' => $transaction_id,
                'Accept' => 'application/json',
                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CREDENTIAL')
            ]
        ]);
        $token_data = $get_response['response'];
        if ($token_data) {
            $token_data = json_decode($token_data, TRUE);
    
            if ($token_data['StatusCode'] == 200)
            {    
                $business_types = [
                    'rollover' => 'Roll Over',
                    'newbusiness' => 'New Vehicle',
                    'breakin' => 'Breakin'
                ];

                $selected_addons = DB::table('selected_addons')
                    ->where('user_product_journey_id', $enquiryId)
                    ->first();

                $is_cpa = false;
                $is_electrical_accessories = NULL;
                $is_non_electrical_accessories = NULL;
                $external_kit_type = NULL;
                $external_kit_value = 0;
                $electrical_accessories_value = 0;
                $non_electrical_accessories_value = 0;
                $pa_paid_driver_sum_insured = 0;
                $no_of_unnamed_passenger = 0;
                $unnamed_passenger_sum_insured = 0;
                $no_of_ll_paid_drivers = 0;
                $no_of_ll_paid_conductors = 0;
                $no_of_ll_paid_cleaners = 0;
                $is_anti_theft = false;
                $voluntary_excess_value = NULL;
                $is_tppd_cover = 0;
                $is_imt23_selected = 0;
                $is_vehicle_limited_to_own_premises = 0;
                $no_of_ll_paid = 0;
                $isRsa = false;
    
                if ($selected_addons && !empty($selected_addons))
                {
                    if ($selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '')
                    {
                        $compulsory_personal_accident = json_decode($selected_addons->compulsory_personal_accident, TRUE);

                        foreach ($compulsory_personal_accident as $cpa)
                        {
                            if (isset($cpa['name']) && $cpa['name'] == 'Compulsory Personal Accident')
                            {
                                $is_cpa = true;
                            }
                        }
                    }

                    if ($selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '')
                    {
                        $addons = json_decode($selected_addons->applicable_addons, TRUE);

                        foreach ($addons as $key => $addon)
                        {
                            if ($addon['name'] == 'IMT - 23')
                            {
                                $is_imt23_selected = 1;
                            }
                            if ($addon['name'] == 'Road Side Assistance') {
                                $isRsa = true;
                            }
                        }
                    }

                    if ($selected_addons->accessories != NULL && $selected_addons->accessories != '')
                    {
                        $accessories = json_decode($selected_addons->accessories, TRUE);

                        foreach ($accessories as $accessory) {
                            if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG')
                            {
                                $external_kit_type = 'CNG';
                                $external_kit_value = $accessory['sumInsured'];
                            }
                            elseif ($accessory['name'] == 'Electrical Accessories')
                            {
                                $is_electrical_accessories = 'Y';
                                $electrical_accessories_value = $accessory['sumInsured'];
                            }
                            elseif ($accessory['name'] == 'Non-Electrical Accessories')
                            {
                                $is_non_electrical_accessories = 'Y';
                                $non_electrical_accessories_value = $accessory['sumInsured'];
                            }
                        }
                    }

                    if ($selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
                    {
                        $additional_covers = json_decode($selected_addons->additional_covers, TRUE);

                        foreach ($additional_covers as $additional_cover)
                        {
                            if ($additional_cover['name'] == 'PA cover for additional paid driver')
                            {
                                $pa_paid_driver_sum_insured = $additional_cover['sumInsured'];
                            }
                            elseif ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                            {
                                $no_of_unnamed_passenger = $mmv->seating_capacity;
                                $unnamed_passenger_sum_insured = $additional_cover['sumInsured'];
                            }
                            elseif ($additional_cover['name'] == 'LL paid driver')
                            {
                                $no_of_ll_paid_drivers = 1;
                            }
                            elseif ($additional_cover['name'] == 'LL paid driver/conductor/cleaner')
                            {
                                if (isset($additional_cover['LLNumberCleaner']) && $additional_cover['LLNumberCleaner'] > 0)
                                {
                                    $no_of_ll_paid_cleaners = $additional_cover['LLNumberCleaner'];
                                }

                                if (isset($additional_cover['LLNumberDriver']) && $additional_cover['LLNumberDriver'] > 0)
                                {
                                    $no_of_ll_paid_drivers = $additional_cover['LLNumberDriver'];
                                }

                                if (isset($additional_cover['LLNumberConductor']) && $additional_cover['LLNumberConductor'] > 0)
                                {
                                    $no_of_ll_paid_conductors = $additional_cover['LLNumberConductor'];
                                }

                                $no_of_ll_paid = $no_of_ll_paid_cleaners + $no_of_ll_paid_drivers + $no_of_ll_paid_conductors;
                            }
                            elseif ($additional_cover['name'] == 'PA paid driver/conductor/cleaner' && isset($additional_cover['sumInsured']))
                            {
                                $pa_paid_driver_sum_insured = $additional_cover['sumInsured'];
                            }
                        }
                    }

                    if ($selected_addons->discounts != NULL && $selected_addons->discounts != '')
                    {
                        $discounts = json_decode($selected_addons->discounts, TRUE);

                        foreach ($discounts as $discount)
                        {
                            if ($discount['name'] == 'anti-theft device')
                            {
                                $is_anti_theft = true;
                            }
                            elseif ($discount['name'] == 'voluntary_insurer_discounts')
                            {
                                $voluntary_excess_value = $discount['sumInsured'];
                            }
                            elseif ($discount['name'] == 'TPPD Cover')
                            {
                                $is_tppd_cover = 1;
                            }
                            elseif ($discount['name'] == 'Vehicle Limited to Own Premises' && $parent_id != 'GCV') // #9062 [20-09-2022]
                            {
                                $is_vehicle_limited_to_own_premises = 1;
                            }
                        }
                    }
                }

                $previous_ncb = null;
                if (in_array(
                    $premium_type,
                    ['comprehensive', 'short_term_3', 'short_term_3_breakin', 'short_term_6', 'short_term_6_breakin']
                )) {
                    $previous_ncb = $requestData->previous_ncb;
                }
 
                $premium_calculation_request = [
                    'TransactionID' => $transaction_id,
                    'Customer_Details' => [
                        'Customer_Type' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Corporate'
                    ],
                    'Policy_Details' => [
                        'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                        'ProposalDate' => date('d/m/Y', time()),
                        'AgreementType' => $proposal->is_vehicle_finance ? $proposal->financer_agreement_type : NULL,
                        'FinancierCode' => $proposal->is_vehicle_finance ? $proposal->name_of_financer : NULL,
                        'BranchName' => $proposal->is_vehicle_finance ? $proposal->hypothecation_city : NULL,
                        'PreviousPolicy_CorporateCustomerId_Mandatary' => $proposal->previous_insurance_company,
                        'PreviousPolicy_NCBPercentage' => $previous_ncb,
                        'PreviousPolicy_PolicyEndDate' => $requestData->business_type == 'newbusiness' ? NULL : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
                        'PreviousPolicy_PolicyNo' => $proposal->previous_policy_number != NULL && $proposal->previous_policy_number != '' ? $proposal->previous_policy_number : NULL,
                        'PreviousPolicy_PolicyClaim' => $requestData->is_claim == 'N' ? 'NO' : 'YES',
                        'BusinessType_Mandatary' => $business_types[$requestData->business_type],
                        'VehicleModelCode' => $mmv->vehicle_model_code,
                        'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                        'YearOfManufacture' => $motor_manf_year,
                        'Registration_No' => $registration_number,
                        'EngineNumber' => $proposal->engine_number,
                        'ChassisNumber' => $proposal->chassis_number,
                        'RTOLocationCode' => $rto_location->txt_rto_location_code,
                        'Vehicle_IDV'=> $idv
                    ]
                ];
                
                if (in_array($productData->premium_type_code, ['short_term_3', 'short_term_3_breakin'])) {
                    $premium_calculation_request['Policy_Details']['TypeofPlan'] = '3 Months';
                    $policy_end_date = date('Y-m-d', strtotime('-1 days + 3 months', strtotime($policy_start_date)));
                } elseif (in_array($productData->premium_type_code, ['short_term_6', 'short_term_6_breakin'])) {
                    $premium_calculation_request['Policy_Details']['TypeofPlan'] = '6 Months';
                    $policy_end_date = date('Y-m-d', strtotime('-1 days + 6 months', strtotime($policy_start_date)));
                } else{
                    $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                }

                //pass previous policy start date in case of previous policy is short term
                //hdfc requires this field in case of short term
                if (
                    $requestData->prev_short_term == '1' &&
                    !empty($proposal->prev_policy_start_date)
                ) {
                    $premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyStartDate'] = date(
                        'd/m/Y',
                        strtotime($proposal->prev_policy_start_date)
                    );
                }

                if ($requestData->previous_policy_type == 'Not sure' || $premium_type == 'third_party_breakin')
                {
                    if ($requestData->previous_policy_type == 'Not sure')
                    {
                        unset($premium_calculation_request['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary']);
                        unset($premium_calculation_request['Policy_Details']['PreviousPolicy_NCBPercentage']);
                        unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyEndDate']);
                        unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyNo']);
                        unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyClaim']);
                    }

                    $premium_calculation_request['Policy_Details']['BusinessType_Mandatary'] = 'Roll Over';
                }

                $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

                $pos_data = DB::table('cv_agent_mappings')
                    ->where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('seller_type','P')
                    ->first();

                $is_pos = false;
                if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                    $hdfc_pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('hdfc_ergo_code')
                    ->first();
                    if ((empty($hdfc_pos_code) || is_null($hdfc_pos_code))) {
                        return [
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'HDFC POS Code Not Available'
                        ];
                    }
                    $is_pos = true;
                    $pos_code = $hdfc_pos_code;
                }

                $premium_calculation_request['Req_GCV'] = "";
                $premium_calculation_request['Req_MISD'] = "";
                $premium_calculation_request['Req_PCV'] = "";
                $premium_calculation_request['Payment_Details'] = "";
                $premium_calculation_request['IDV_DETAILS'] = "";
    
                if ($parent_id == 'PCV')
                {
                    $premium_calculation_request['Req_PCV'] = [
                        'POSP_CODE' => ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $pos_data) || config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE') : '',
                        'ExtensionCountryCode' => '0',
                        'ExtensionCountryName' => '',
                        'Effectivedrivinglicense' => $is_cpa ? false : true,
                        'NumberOfDrivers' => $no_of_ll_paid_drivers,
                        'NumberOfEmployees' => '0',
                        'NoOfCleanerConductorCoolies' =>  $pa_paid_driver_sum_insured > 0 ? 1 : 0,
                        'BiFuelType' => $external_kit_type,
                        'BiFuel_Kit_Value' => $external_kit_value,
                        'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                        'Owner_Driver_Nominee_Name' => $is_cpa ? $proposal->nominee_name : NULL,
                        'Owner_Driver_Nominee_Age' => $is_cpa ? (int) $proposal->nominee_age : NULL,
                        'Owner_Driver_Nominee_Relationship' => $is_cpa ? $proposal->nominee_relationship : NULL,
                        'Owner_Driver_Appointee_Name' => NULL,
                        'Owner_Driver_Appointee_Relationship' => NULL,
                        'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                        'ElecticalAccessoryIDV' => $electrical_accessories_value,
                        'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                        'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                        'IsPrivateUseLoading' => 0,
                        'IsInclusionofIMT23' => 0,
                        'OtherLoadDiscRate' => 0,
                        'AntiTheftDiscFlag' => false,
                        'HandicapDiscFlag' => false,
                        'Voluntary_Excess_Discount' => $voluntary_excess_value,
                        'UnnamedPersonSI' => $unnamed_passenger_sum_insured,
                        // 'TPPDLimit' => $is_tppd_cover, as per #23856
                        'IsRTI_Cover' => 1,
                        'IsCOC_Cover' => 1,
                        'Bus_Type' => "",
                        'NoOfFPP' => 0,
                        'NoOfNFPP' => 0,
                        'IsCOC_Cover' => 0,
                        'IsTowing_Cover' => 0,
                        'Towing_Limit' => "",
                        'IsEngGearBox_Cover' => 0,
                        'IsNCBProtection_Cover' => 0,
                        'IsRTI_Cover' => 0,
                        'IsEA_Cover' => $isRsa ? 1 : 0,
                        'IsEAW_Cover' => 0
                    ];
                    if($is_pos)
                    {
                        $premium_calculation_request['Req_PCV']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
                    }else{
                        unset($premium_calculation_request['Req_PCV']['POSP_CODE']);
                    }
                }
                elseif ($parent_id == 'GCV')
                {
                    $premium_calculation_request['Req_GCV'] = [
                        'POSP_CODE' => ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $pos_data) || config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_POSP_TEST_UNIQUE_CODE') : '',
                        'ExtensionCountryCode' => '0',
                        'ExtensionCountryName' => '',
                        'Effectivedrivinglicense' => $is_cpa ? false : true,
                        'NumberOfDrivers' => $no_of_ll_paid > $mmv->seating_capacity ? $mmv->seating_capacity : $no_of_ll_paid,
                        'NumberOfEmployees' => '0',
                        'NoOfCleanerConductorCoolies' => $pa_paid_driver_sum_insured > 0 ? $mmv->seating_capacity : 0,
                        'BiFuelType' => $external_kit_type,
                        'BiFuel_Kit_Value' => $external_kit_value,
                        'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                        'Owner_Driver_Nominee_Name' => $is_cpa ? $proposal->nominee_name : NULL,
                        'Owner_Driver_Nominee_Age' => $is_cpa ? $proposal->nominee_age : NULL,
                        'Owner_Driver_Nominee_Relationship' => $is_cpa ? $proposal->nominee_relationship : NULL,
                        'Owner_Driver_Appointee_Name' => NULL,
                        'Owner_Driver_Appointee_Relationship' => NULL,
                        'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                        'NoOfTrailers' => 0,
                        'TrailerChassisNo' => "",
                        'TrailerIDV' => 0,
                        'ElecticalAccessoryIDV' => $electrical_accessories_value,
                        'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                        'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                        'IsPrivateUseLoading' => 0,
                        'IsInclusionofIMT23' => $is_imt23_selected,
                        'IsOverTurningLoading' => 0,
                        'OtherLoadDiscRate' => 0,
                        'AntiTheftDiscFlag' => false,
                        'HandicapDiscFlag' => false,
                        'PrivateCarrier' => $requestData->gcv_carrier_type == 'PRIVATE' ? true : false,
                        'Voluntary_Excess_Discount' => NULL,
                        // 'TPPDLimit' => $is_tppd_cover, as per #23856
                        'IsRTI_Cover' => 1,
                        'IsCOC_Cover' => 1,
                        'Bus_Type' => "",
                        'NoOfFPP' => 0,
                        'NoOfNFPP' => 0,
                        'IsCOC_Cover' => 0,
                        'IsTowing_Cover' => 0,
                        'Towing_Limit' => "",
                        'IsEngGearBox_Cover' => 0,
                        'IsNCBProtection_Cover' => 0,
                        'IsRTI_Cover' => 0,
                        'IsEA_Cover' => 0,
                        'IsEAW_Cover' => 0
                    ];
                    if($is_pos)
                    {
                        $premium_calculation_request['Req_GCV']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
                    }else
                    {
                        unset($premium_calculation_request['Req_GCV']['POSP_CODE']);
                    }
                }

                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CALCULATE_PREMIUM_URL'), $premium_calculation_request, 'hdfc_ergo', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Premium Calculation',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'requestMethod' => 'post',
                    'product_code' => $product_code,
                    'transaction_id' => $transaction_id,
                    'token' => $token_data['Authentication']['Token'],
                    'headers' => [
                        'Content-type' => 'application/json',
                        'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                        'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                        'PRODUCT_CODE' => $product_code,
                        'TransactionID' => $transaction_id,
                        'Accept' => 'application/json',
                        'Token' => $token_data['Authentication']['Token']
                    ]
                ]);
                $premium_data = $get_response['response'];
    
                if ($premium_data)
                {
                    $premium_data = json_decode($premium_data, TRUE);

                    if (isset($premium_data['StatusCode']) && $premium_data['StatusCode'] == 200)
                    {
                        if ($parent_id == 'PCV')
                        {
                            $premium = $premium_data['Resp_PCV'];
                        }
                        else
                        {
                            $premium = $premium_data['Resp_GCV'];
                        }

                        $communication_pincode_data = DB::table('hdfc_ergo_motor_pincode_master AS hempm')
                            ->leftJoin('hdfc_ergo_motor_city_master AS hemcm', 'hempm.num_citydistrict_cd', '=', 'hemcm.num_citydistrict_cd')
                            ->leftJoin('hdfc_ergo_motor_state_master AS hemsm', 'hemcm.num_state_cd', '=', 'hemsm.num_state_cd')
                            ->where('hempm.num_pincode', $proposal->pincode)
                            ->select('hempm.*', 'hemcm.txt_citydistrict', 'hemsm.txt_state', 'hemsm.num_state_cd')
                            ->get();

                        if ($communication_pincode_data && isset($communication_pincode_data[0])) {                              
                            $mailing_address = [
                                'address_1' => removeSpecialCharactersFromString($proposal->address_line1, true),
                                'address_2' => $proposal->address_line2.', '.$proposal->address_line3,
                                'city_district_code' => $communication_pincode_data[0]->num_citydistrict_cd,
                                'city_district' => $communication_pincode_data[0]->txt_citydistrict,
                                'state_code' => $communication_pincode_data[0]->num_state_cd,
                                'state' => $communication_pincode_data[0]->txt_state,
                                'pincode' => $communication_pincode_data[0]->num_pincode,
                                'pincode_locality' => $communication_pincode_data[0]->txt_pincode_locality
                            ];

                            if ($proposal->is_car_registration_address_same) {
                                $permenant_address = $mailing_address;
                            } else {
                                $registration_pincode_data = DB::table('hdfc_ergo_motor_pincode_master AS hempm')
                                    ->leftJoin('hdfc_ergo_motor_city_master AS hemcm', 'hempm.num_citydistrict_cd', '=', 'hemcm.num_citydistrict_cd')
                                    ->leftJoin('hdfc_ergo_motor_state_master AS hemsm', 'hemcm.num_state_cd', '=', 'hemsm.num_state_cd')
                                    ->where('hempm.num_pincode', $proposal->car_registration_pincode)
                                    ->select('hempm.*', 'hemcm.txt_citydistrict', 'hemsm.txt_state', 'hemsm.num_state_cd')
                                    ->get();

                                if ($registration_pincode_data && isset($registration_pincode_data[0])) {
                                    $permenant_address = [
                                        'address_1' => $proposal->car_registration_address1,
                                        'address_2' => $proposal->car_registration_address2.', '.$proposal->car_registration_address3,
                                        'city_district_code' => $registration_pincode_data[0]->num_citydistrict_cd,
                                        'city_district' => $registration_pincode_data[0]->txt_citydistrict,
                                        'state_code' => $registration_pincode_data[0]->num_state_cd,
                                        'state' => $registration_pincode_data[0]->txt_state,
                                        'pincode' => $registration_pincode_data[0]->num_pincode,
                                        'pincode_locality' => $registration_pincode_data[0]->txt_pincode_locality
                                    ];
                                } else {
                                    return [
                                        'premium_amount' => 0,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'status' => false,
                                        'message' => 'Invalid pincode in registration address'
                                    ];
                                }
                            }
                        }
                        else
                        {
                            return [
                                'premium_amount' => 0,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'message' => 'Invalid pincode in communication address'
                            ];
                        }

                        $premium_calculation_request['Customer_Details'] = [
                            'GC_CustomerID' => "",
                            'Company_Name' => $requestData->vehicle_owner_type == 'C' ? $proposal->first_name : NULL,
                            'Customer_Type' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Corporate',
                            'Customer_FirstName' => $requestData->vehicle_owner_type == 'I' ? $proposal->first_name : NULL,
                            'Customer_MiddleName' => "",
                            'Customer_LastName' => $requestData->vehicle_owner_type == 'I' ? ( ! empty($proposal->last_name) ? $proposal->last_name : '.') : NULL,
                            'Customer_DateofBirth' => date('d/m/Y', strtotime($proposal->dob)),
                            'Customer_Email' => $proposal->email,
                            'Customer_Mobile' => $proposal->mobile_number,
                            'Customer_Telephone' => "",
                            'Customer_PanNo' => $proposal->pan_number,
                            'Customer_Salutation' => $proposal->gender == 'Male' ? 'MR' : ($proposal->marital_status == 'Married' ? 'MRS' : 'MS'),
                            'Customer_Gender' => $requestData->vehicle_owner_type == 'I' ? $proposal->gender : NULL,
                            'Customer_Perm_Address1' => $permenant_address['address_1'],
                            'Customer_Perm_Address2' => $permenant_address['address_2'],
                            'Customer_Perm_Apartment' => "",
                            'Customer_Perm_Street' => "",
                            'Customer_Perm_CityDistrictCode' => $permenant_address['city_district_code'],
                            'Customer_Perm_CityDistrict' => $permenant_address['city_district'],
                            'Customer_Perm_StateCode' => $permenant_address['state_code'],
                            'Customer_Perm_State' => $permenant_address['state'],
                            'Customer_Perm_PinCode' => $permenant_address['pincode'],
                            'Customer_Perm_PinCodeLocality' => $permenant_address['pincode_locality'],
                            'Customer_Mailing_Address1' => $mailing_address['address_1'],
                            'Customer_Mailing_Address2' => $mailing_address['address_2'],
                            'Customer_Mailing_Apartment' => "",
                            'Customer_Mailing_Street' => "",
                            'Customer_Mailing_CityDistrictCode' => $mailing_address['city_district_code'],
                            'Customer_Mailing_CityDistrict' => $mailing_address['city_district'],
                            'Customer_Mailing_StateCode' => $mailing_address['state_code'],
                            'Customer_Mailing_State' => $mailing_address['state'],
                            'Customer_Mailing_PinCode' => $mailing_address['pincode'],
                            'Customer_Mailing_PinCodeLocality' => $mailing_address['pincode_locality'],
                            'Customer_GSTIN_Number' => $proposal->gst_number != '' && $proposal->gst_number != NULL ? $proposal->gst_number : ""
                        ];

                        if (in_array($premium_calculation_request['Customer_Details']['Customer_Gender'], ['M', 'F'])) {
                            $premium_calculation_request['Customer_Details']['Customer_Gender'] = $premium_calculation_request['Customer_Details']['Customer_Gender'] == 'M' ? 'MALE' : 'FEMALE';
                        }

                        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                            $premium_calculation_request['Customer_Details']['Customer_Pehchaan_id'] = $proposal->ckyc_reference_id;
                        }
                        HdfcErgoPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        $basic_od = $premium['Basic_OD_Premium'] + ($premium['HighTonnageLoading_Premium'] ?? 0);
                        $tppd = $premium['Basic_TP_Premium'];
                        $pa_owner = $premium['PAOwnerDriver_Premium'];
                        $pa_unnamed = 0;
                        $pa_paid_driver = $premium['PAPaidDriverCleaCondCool_Premium'];
                        $electrical_accessories = $premium['Electical_Acc_Premium'];
                        $non_electrical_accessories = $premium['NonElectical_Acc_Premium'];
                        $zero_dep_amount = $premium['Vehicle_Base_ZD_Premium'];
                        $ncb_discount = $premium['NCBBonusDisc_Premium'];
                        $lpg_cng = $premium['BiFuel_Kit_OD_Premium'];
                        $lpg_cng_tp = isset($premium['BiFuel_Kit_TP_Premium']) && $premium['BiFuel_Kit_TP_Premium'] > 0 ? $premium['BiFuel_Kit_TP_Premium'] : (isset($premium['InBuilt_BiFuel_Kit_Premium']) && $premium['InBuilt_BiFuel_Kit_Premium'] > 0 ? $premium['InBuilt_BiFuel_Kit_Premium'] : 0);
                        $automobile_association = 0;
                        $anti_theft = $parent_id == 'PCV' ? $premium['AntiTheftDisc_Premium'] : 0;
                        $tppd_discount_amt = $premium['TPPD_premium'] ?? 0;
                        $other_addon_amount = 0;
                        $liabilities = 0;
                        $ll_paid_cleaner = $premium['NumberOfDrivers_Premium'];
                        $imt_23 = $parent_id == 'GCV' ? $premium['VB_InclusionofIMT23_Premium'] : 0;
                        $ic_vehicle_discount = 0;
                        $voluntary_excess = 0;
                        $other_discount = 0;

                        if ($electrical_accessories > 0)
                        {
                            $zero_dep_amount += (int)$premium['Elec_ZD_Premium'];
                            $imt_23 += $parent_id == 'GCV' ? (int) $premium['Elec_InclusionofIMT23_Premium'] : 0;
                        }

                        if ($non_electrical_accessories > 0)
                        {
                            $zero_dep_amount += (int)$premium['NonElec_ZD_Premium'];
                            $imt_23 += $parent_id == 'GCV' ? (int) $premium['NonElec_InclusionofIMT23_Premium'] : 0;
                        }

                        if ($lpg_cng > 0)
                        {
                            $zero_dep_amount += (int)$premium['Bifuel_ZD_Premium'];
                            $imt_23 += $parent_id == 'GCV' ? (int) $premium['BiFuel_InclusionofIMT23_Premium'] : 0;
                        }

                        $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt;
                        $final_od_premium = $basic_od - $final_total_discount - ($premium['LimitedtoOwnPremises_OD_Premium'] ?? 0);
                        $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $ll_paid_cleaner + $pa_owner - ($premium['LimitedtoOwnPremises_TP_Premium'] ?? 0);
                        $total_addon_premium = $zero_dep_amount + $imt_23 + $electrical_accessories + $non_electrical_accessories + $lpg_cng;

                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update([
                                'od_premium' => $final_od_premium,
                                'tp_premium' => $final_tp_premium,
                                'ncb_discount' => $premium['NCBBonusDisc_Premium'],
                                'addon_premium' => $total_addon_premium,
                                'total_premium' => $premium['Net_Premium'],
                                'service_tax_amount' =>$premium['Service_Tax'],
                                'final_payable_amount' => $premium['Total_Premium'],
                                'cpa_premium' => $premium['PAOwnerDriver_Premium'],
                                'total_discount' => $final_total_discount,
                                'proposal_no' => $premium_data['TransactionID'],
                                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                'tp_start_date' =>  date('d-m-Y', strtotime($policy_start_date)),
                                'tp_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                'ic_vehicle_details' => json_encode([
                                    'manufacture_name' => $mmv->manufacturer,
                                    'model_name' => $mmv->vehicle_model,
                                    'version' => $mmv->txt_variant,
                                    'fuel_type' => $mmv->txt_fuel,
                                    'seating_capacity' => $mmv->seating_capacity,
                                    'carrying_capacity' => $mmv->carrying_capacity,
                                    'cubic_capacity' => $mmv->cubic_capacity,
                                    'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
                                    'vehicle_type' => ''//$mmv_data->veh_type_name,
                                ]),
                                'is_breakin_case' => 'N',
                                'additional_details_data' => json_encode($premium_calculation_request)
                            ]);

                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

                        $finsall = new \App\Http\Controllers\Finsall\FinsallController();
                        $finsall->checkFinsallAvailability('hdfc_ergo', 'cv', $premium_type, $proposal);


                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [                            
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $premium_data['TransactionID'],
                                'finalPayableAmount' => $premium['Total_Premium'], 
                                'is_breakin' => 'N',//$is_breakin_case,
                                'inspection_number' => '',//$proposal_response->policyNumber,
                            ]
                        ]);
                    }
                    else
                    {
                        return [
                            'premium_amount' => 0,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'status' => false,
                            'message' => isset($premium_data['Error']) ? $premium_data['Error'] : 'Service Error'
                        ];
                    }
                }
                else
                {
                    return [
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => 'Something went wrong while calculating premium'
                    ];
                }
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => isset($token_data['Error']) ? $token_data['Error'] : 'Service Error'
                ];
            }
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Something went wrong while generating token'
            ];
        }
    }
    
    public static function renewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
        
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $vehicleDetails = [
            'manf_name'             => $mmv->vehicle_manufacturer,
            'model_name'            => $mmv->vehicle_model_name,
            'version_name'          => $mmv->variant,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->carrying_capacity,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'BIKE',
            'version_id'            => $mmv->ic_version_code,
        ];
        
      
        $policy_data = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE')
            ],
            'PreviousPolicyNumber' =>  $proposal->previous_policy_number,
            'VehicleRegistrationNumber' => $proposal->vehicale_registration_number,
        ];
        
        $policy_data = [
            'ConfigurationParam' => [
                'AgentCode' => 'PBIN0001'
            ],
            'PreviousPolicyNumber' =>  '2319201172935000000',
            'VehicleRegistrationNumber' => 'MH-01-CC-1011',
        ];
    $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_FETCH_POLICY_DETAILS');
    $url = 'https://uatcp.hdfcergo.com/PCOnline/ChannelPartner/RenewalCalculatePremium';
//    $policy_data_response = getWsData($url, $policy_data, 'hdfc_ergo', [
//        'section' => $productData->product_sub_type_code,
//        'method' => 'Fetch Policy Details',
//        'requestMethod' => 'post',
//        'enquiryId' => $enquiryId,
//        'productName' => $productData->product_name,
//        'transaction_type' => 'proposal',
//        'headers' => [
//            'MerchantKey' => 'RENEWBUY',//config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MERCHANT_KEY'),
//            'SecretToken' => 'vaHspz4yj6ixSaTFS4uEVw==',//config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_SECRET_TOKEN'),
//            'Content-Type' => 'application/json',
//            //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
//            'User-Agent' => $_SERVER['HTTP_USER_AGENT']
//            //'Accept-Language' => 'en-US,en;q=0.5'
//        ]
//    ]);
    //print_r($policy_data_response);
    
    $policy_data_response = '{
    "Status": 200,
    "UniqueRequestID": "100a0cd5-3928-44c2-a38f-7785e6c85300",
    "Message": null,
    "Data": {
        "PreviousPolicyNo": "2311201091832500000",
        "VehicleMakeCode": 84,
        "VehicleMakeName": "MARUTI",
        "VehicleModelName": "ALTO 800",
        "VehicleModelCode": 25373,
        "RegistrationNo": "MH-02-YP-2987",
        "PurchaseRegistrationDate": "2017-04-30",
        "ManufacturingYear": "2017",
        "PreviousPolicyStartDate": "2018-04-30",
        "PreviousPolicyEndDate": "2019-04-29",
        "RtoLocationCode": 10406,
        "RegistrationCity": "MUMBAI",
        "EngineNo": "4658965874",
        "ChassisNo": "3658965874",
        "VehicleCubicCapacity": 796,
        "VehicleSeatingCapacity": 5,
        "NcbDiscount": "20",
        "OldNcbPercentage": 20,
        "PolicyIssuingAddress": "LEELA BUSINESS PARK, 6TH FLR, ANDHERI - KURLA RD, MUMBAI, 400059.",
        "PolicyIssuingPhoneno": "+91-22-66383600",
        "IntermediaryCode": "200278133519",
        "AddOnsOptedLastYear": "ZERODEP,EMERGASSIST,NCBPROT",
        "PAPaidDriverLastYear": "NO",
        "UnnamedPassengerLastYear": "YES",
        "LLPaidDriverLastyear": "NO",
        "LLEmployeeLastYear": "NO",
        "TppdDiscountLastYear": null,
        "ExLpgCngKitLastYear": null,
        "ElectricalAccessoriesIdv": 0,
        "NonelectricalAccessoriesIdv": 0,
        "LpgCngKitIdv": 0,
        "PAPaidDriverSI": 0,
        "PAOwnerDriverSI": 100000,
        "UnnamedPassengerSI": 40000,
        "NoOfUnnamedPassenger": 5,
        "NoOfLLPaidDrivers": 0,
        "NumberOfLLEmployees": 0,
        "TppdLimit": 0,
        "IsBreakin": 1,
        "IsWaiver": 0,
        "CustomerDetail": [
            {
                "CustomerType": "INDIVIDUAL",
                "Title": "MR",
                "Gender": "MALE",
                "FirstName": "AMIT",
                "MiddleName": "KUMAR",
                "LastName": "SHARMA",
                "DateofBirth": "1970-01-25",
                "EmailAddress": "AMEYA.GOKHALE@HDFCERGO.CO.IN",
                "MobileNumber": "9899999999",
                "OrganizationName": null,
                "OrganizationContactPersonName": "NA",
                "Pancard": "BJKLI3653P",
                "GstInNo": "27AAACB5343E1Z1"
            }
        ],
        "PrivateCarRenewalPremiumList": [
            {
                "VehicleIdv": 255494,
                "BasicODPremium": 5611,
                "BasicTPPremium": 1850,
                "NetPremiumAmount": 9192,
                "TaxPercentage": 18,
                "TaxAmount": 1655,
                "TotalPremiumAmount": 10847,
                "NewNcbDiscountAmount": 1403,
                "NewNcbDiscountPercentage": 25,
                "VehicleIdvMin": 255494,
                "VehicleIdvMax": 268269,
                "BestIdv": 255494,
                "VehicleIdvYear2": 0,
                "VehicleIdvYear3": 0,
                "ElectricalAccessoriesPremium": 0,
                "NonelectricalAccessoriesPremium": 0,
                "LpgCngKitODPremium": 0,
                "LpgCngKitTPPremium": 60,
                "LLPaidDriverRate": 50,
                "LLPaidDriversPremium": 0,
                "UnnamedPassengerRate": 0.0005,
                "UnnamedPassengerPremium": 100,
                "PAPaidDriverRate": 0.0005,
                "PAPaidDriverPremium": 0,
                "LLEmployeeRate": 50,
                "LLEmployeePremium": 0,
                "PACoverOwnerDriverPremium": 325,
                "NewPolicyStartDate": "2019-05-13",
                "NewPolicyEndDate": "2020-05-12",
                "CgstPercentage": 9,
                "CgstAmount": 827.5,
                "SgstPercentage": 9,
                "SgstAmount": 827.5,
                "IgstPercentage": 0,
                "IgstAmount": 0,
                "OtherDiscountAmount": 0,
                "TotalAppliedDiscount": 29.77,
                "DiscountLimit": 70,
                "ODRate": 0.02196,
                "ElectricalODRate": 0,
                "LpgcngodRate": 0,
                "NonelectricalODRate": 0,
                "OtherDiscountPercentage": 0,
                "BreakinAmount": 0,
                "BreakinRate": 0,
                "PremiumPAPaidDriverSI": 0,
                "PremiumNoOfLLPaidDrivers": 0,
                "PremiumUnnamedPassengerSI": 40000,
                "PremiumNoOfUnnamedPassenger": 5,
                "PremiumNumberOfLLEmployees": 0,
                "PolicyIssuingBranchOfficeCode": 1000,
                "AddOnCovers": [
                    {
                        "CoverName": "ZERODEP",
                        "CoverPremium": 1788
                    },
                    {
                        "CoverName": "NCBPROT",
                        "CoverPremium": 511
                    },
                    {
                        "CoverName": "ENGEBOX",
                        "CoverPremium": 511
                    },
                    {
                        "CoverName": "RTI",
                        "CoverPremium": 639
                    },
                    {
                        "CoverName": "COSTCONS",
                        "CoverPremium": 1277
                    },
                    {
                        "CoverName": "EMERGASSIST",
                        "CoverPremium": 350
                    },
                    {
                        "CoverName": "EMERGASSISTWIDER",
                        "CoverPremium": 250
                    }
                ]
            }
        ]
    }
}';

    $policy_data_response = json_decode($policy_data_response,true);
    if($policy_data_response['Status'] == 200)
    {
        $all_data = $policy_data_response['Data'];
        $AddOnsOptedLastYear = explode(',',$all_data['AddOnsOptedLastYear']);
        $PrivateCarRenewalPremiumList = $all_data['PrivateCarRenewalPremiumList'][0];
        $AddOnCovers = $PrivateCarRenewalPremiumList['AddOnCovers'] ?? '';
        $CustomerDetail = $all_data['CustomerDetail'][0];
        $UniqueRequestID = $policy_data_response['UniqueRequestID'];
        
        $zeroDepreciation           = 0;
        $engineProtect              = 0;
        $keyProtect                 = 0;
        $tyreProtect                = 0;
        $returnToInvoice            = 0;
        $lossOfPersonalBelongings   = 0;
        $roadSideAssistance         = 0;
        $consumables                = 0;
        $ncb_protection             = 0;
        
        $IsZeroDepCover = $IsLossOfUse = $IsEmergencyAssistanceCover = $IsNoClaimBonusProtection = $IsEngineAndGearboxProtectorCover = 
        $IsCostOfConsumable = $IsReturntoInvoice = $IsEmergencyAssistanceWiderCover = $IsTyreSecureCover = 'NO';
        
        if(is_array($AddOnCovers))
        {
            foreach ($AddOnCovers as $key => $value) 
            {
                if(in_array($value['CoverName'], $AddOnsOptedLastYear))
                {
                    if($value['CoverName'] == 'ZERODEP')
                    {
                       $zeroDepreciation = $value['CoverPremium'];
                       $IsZeroDepCover = 'YES';
                    }
                    else if($value['CoverName'] == 'NCBPROT')
                    {
                        $ncb_protection = $value['CoverPremium'];
                        $IsNoClaimBonusProtection = 'YES';
                    }
                    else if($value['CoverName'] == 'ENGEBOX')
                    {
                        $engineProtect = $value['CoverPremium'];
                        $IsEngineAndGearboxProtectorCover = 'YES';
                    }
                    else if($value['CoverName'] == 'RTI')
                    {
                        $returnToInvoice = $value['CoverPremium'];
                        $IsReturntoInvoice = 'YES';
                    }
                    else if($value['CoverName'] == 'COSTCONS')
                    {
                        $consumables = $value['CoverPremium'];//consumable
                        $IsCostOfConsumable = 'YES';
                    }
                    else if($value['CoverName'] == 'EMERGASSIST')
                    {
                        $roadSideAssistance = $value['CoverPremium'];//road side assis
                        $IsEmergencyAssistanceCover = 'YES';
                    }
                    else if($value['CoverName'] == 'EMERGASSISTWIDER')
                    {
                       $keyProtect = $value['CoverPremium'];//$key_replacement 
                       $IsEmergencyAssistanceWiderCover = 'YES';
                    }
                    else if($value['CoverName'] == 'TYRESECURE')
                    {
                        $tyreProtect = $value['CoverPremium'];
                        $IsZeroDepCover = 'YES';
                    }
                    else if($value['CoverName'] == 'LOSSUSEDOWN')
                    {
                        $lossOfPersonalBelongings = $value['CoverPremium'];
                        $IsLossOfUse = 'YES';
                    }               
                }                                 
            }                       
        } 
        $is_cpa = (int)$PrivateCarRenewalPremiumList['PACoverOwnerDriverPremium'] > 0 ? true : false;
        
        $proposal_input_data = [
            'UniqueRequestID' => $UniqueRequestID,
            'ProposalDetails' => [
                'ConfigurationParam' => [
                    'AgentCode' => 'PBIN0001',//config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE')
                ],
                'PreviousPolicyNumber'              => $all_data['PreviousPolicyNo'],//'2311201090946600000',
                'VehicleRegistrationNumber'         => $all_data['RegistrationNo'],//'MH-02-LO-3534',
                'IsEADiscount'                      => 0,
                'RequiredIdv'                       => $PrivateCarRenewalPremiumList['VehicleIdv'] ?? 0,//487364,
                'NetPremiumAmount'                  => $PrivateCarRenewalPremiumList['NetPremiumAmount'],//15410,
                'TotalPremiumAmount'                => $PrivateCarRenewalPremiumList['TotalPremiumAmount'],//18184,
                'TaxAmount'                         => $PrivateCarRenewalPremiumList['TaxAmount'],//2774,
            
                'AddOnCovers' => [
                  'IsZeroDepCover'                  => $IsZeroDepCover,
                  'IsLossOfUse'                     => $IsLossOfUse,
                  'IsEmergencyAssistanceCover'      => $IsEmergencyAssistanceCover,
                  'IsNoClaimBonusProtection'        => $IsNoClaimBonusProtection,
                  'IsEngineAndGearboxProtectorCover' => $IsEngineAndGearboxProtectorCover,//'NO',
                  'IsCostOfConsumable'                  => $IsCostOfConsumable,//'NO',
                  'IsReturntoInvoice' => $IsReturntoInvoice,
                  'IsEmergencyAssistanceWiderCover' => $IsEmergencyAssistanceWiderCover,//'NO',
                  'IsTyreSecureCover' => $IsTyreSecureCover,//'YES',
                  'NonelectricalAccessoriesIdv' => $all_data['NonelectricalAccessoriesIdv'],
                  'ElectricalAccessoriesIdv' => $all_data['ElectricalAccessoriesIdv'],
                  'LpgCngKitIdv' => $all_data['LpgCngKitIdv'],
                  'SelectedFuelType' => (int) $all_data['LpgCngKitIdv'] > 0 ? 'CNG' : NULL,
                  'IsPAPaidDriver' => $all_data['PAPaidDriverLastYear'],
                  'PAPaidDriverSumInsured' => $all_data['PAPaidDriverSI'],
                  'IsPAUnnamedPassenger' => $all_data['UnnamedPassengerLastYear'],
                  'PAUnnamedPassengerNo' => $all_data['NoOfUnnamedPassenger'],
                  'PAPerUnnamedPassengerSumInsured' => $all_data['UnnamedPassengerSI'],//40000,
                  'IsLegalLiabilityDriver' => $all_data['LLPaidDriverLastyear'],
                  'LLPaidDriverNo' => $all_data['NoOfLLPaidDrivers'],
                  'IsLLEmployee' => $all_data['LLEmployeeLastYear'],//'YES',
                  'LLEmployeeNo' => $all_data['NumberOfLLEmployees'],
                  'CpaYear' => $is_cpa == true ? 1 : 0,
                ]
            ],
            'CustomerDetails' => [
              'EmailAddress'    => $CustomerDetail['EmailAddress'],//'ankit.mori@synoverge.com',
              'MobileNumber'    => $CustomerDetail['MobileNumber'],//'8128911914',
              'PanCard'         => $CustomerDetail['Pancard'],
              'GstInNo'         => $CustomerDetail['GstInNo'],
              //'LGCode'          => 'LGCODE123',
              'CorrespondenceAddress1' => 'sunrise park',
              'CorrespondenceAddress2' => 'opp hamalaya maal',
              'CorrespondenceAddress3' => 'LandMark',
              'CorrespondenceAddressCitycode' => 117,
              'CorrespondenceAddressCityName' => 'GURGAON',
              'CorrespondenceAddressStateCode' => 8,
              'CorrespondenceAddressStateName' => 'HARYANA',
              'CorrespondenceAddressPincode' => 122001,
              //'InspectionMethod' => 'SURVEYOR',
              //'InspectionStateCode' => 14,
              //'InspectionCityCode' => 273,
              //'InspectionLocationCode' => 143,
              'IsGoGreen' => 0,
            ]
          ];

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $proposal_input_data['CustomerDetails']['KYCId'] = $proposal->ckyc_reference_id;
        }

        if($is_cpa == true)
        {
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeName'] = $proposal->nominee_name;
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeAge'] = $proposal->nominee_age;
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeRelationship'] = $proposal->nominee_relationship;           
        }
        
        $url = "https://uatcp.hdfcergo.com/PCOnline/ChannelPartner/RenewalSaveTransaction";
//            $proposal_input_response = getWsData($url, $proposal_input_data, 'hdfc_ergo', [
//                'section' => $productData->product_sub_type_code,
//                'method' => 'Renewal Proposal Submit',
//                'requestMethod' => 'post',
//                'enquiryId' => $enquiryId,
//                'productName' => $productData->product_name,
//                'transaction_type' => 'proposal',
//                'headers' => [
//                    'MerchantKey' => 'RENEWBUY',//config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MERCHANT_KEY'),
//                    'SecretToken' => 'vaHspz4yj6ixSaTFS4uEVw==',//config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_SECRET_TOKEN'),
//                    'Content-Type' => 'application/json',
//                    //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
//                    'User-Agent' => $_SERVER['HTTP_USER_AGENT']
//                    //'Accept-Language' => 'en-US,en;q=0.5'
//                ]
//            ]);
//    
        
//        print_r($proposal_input_response);
//        die;
        
        $proposal_reponse = '{
            "Status": 200,
            "UniqueRequestID": "8483721e-8ca3-47a9-bb74-4aa2b18c45db",
            "Message": null,
            "Data": {
                "TransactionNo": 1122204083207.0,
                "QuoteNo": 19122042535780.0,
                "IsBreakin": 0,
                "IsWaiver": 0,
                "PaymentMethod": "",
                "NewPolicyStartDate": "2022-04-29",
                "NewPolicyEndDate": "2023-04-28",
                "TPNewPolicyStartDate": "2022-04-29",
                "TPNewPolicyEndDate": "2023-04-28"
            }
        }';
        $proposal_reponse = json_decode($proposal_reponse,true);
       // print_r($proposal_reponse);
        //die;
        if($proposal_reponse['Status'] == 200)
        {
            //$PrivateCarRenewalPremiumList;
            $policy_start_date = date('d-m-Y', strtotime($proposal_reponse['Data']['NewPolicyStartDate']));
            $policy_end_date = date('d-m-Y', strtotime($proposal_reponse['Data']['NewPolicyStartDate']));
            
             //OD Premium
            $basicOD = $PrivateCarRenewalPremiumList['BasicODPremium'] ?? 0;
            $ElectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['ElectricalAccessoriesPremium'];
            $NonelectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['NonelectricalAccessoriesPremium'];
            $LpgCngKitODPremium = $PrivateCarRenewalPremiumList['LpgCngKitODPremium'];

            $fianl_od = $basicOD + $ElectricalAccessoriesPremium  + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;

            //TP Premium           
            $basic_tp = $PrivateCarRenewalPremiumList['BasicTPPremium'] ?? 0;        
            $LLPaidDriversPremium = $PrivateCarRenewalPremiumList['LLPaidDriversPremium'] ?? 0;
            $UnnamedPassengerPremium = $PrivateCarRenewalPremiumList['UnnamedPassengerPremium'] ?? 0;
            $PAPaidDriverPremium = $PrivateCarRenewalPremiumList['PAPaidDriverPremium'] ?? 0;       
            $PremiumNoOfLLPaidDrivers = $PrivateCarRenewalPremiumList['PremiumNoOfLLPaidDrivers'];
            $LpgCngKitTPPremium = $PrivateCarRenewalPremiumList['LpgCngKitTPPremium'] ?? 0;
            $PACoverOwnerDriverPremium = $PrivateCarRenewalPremiumList['PACoverOwnerDriverPremium'] ?? 0;

            $final_tp = $basic_tp + $LLPaidDriversPremium + $UnnamedPassengerPremium + $PAPaidDriverPremium + $PremiumNoOfLLPaidDrivers + $LpgCngKitTPPremium;

            //Discount 
            $applicable_ncb = $PrivateCarRenewalPremiumList['NewNcbDiscountPercentage'];
            $NcbDiscountAmount = $PrivateCarRenewalPremiumList['NewNcbDiscountAmount'];
            $OtherDiscountAmount = $PrivateCarRenewalPremiumList['OtherDiscountAmount'];
            $tppD_Discount = 0;
            $total_discount = $NcbDiscountAmount + $OtherDiscountAmount ;

            //final calc

            $NetPremiumAmount = $PrivateCarRenewalPremiumList['NetPremiumAmount'];
            $TaxAmount = $PrivateCarRenewalPremiumList['TaxAmount'];
            $TotalPremiumAmount = $PrivateCarRenewalPremiumList['TotalPremiumAmount'];  
            
            $addon_premium = $zeroDepreciation + $engineProtect + $keyProtect + $tyreProtect + $returnToInvoice + $lossOfPersonalBelongings + 
            $roadSideAssistance + $consumables + $ncb_protection + $ElectricalAccessoriesPremium + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;
            $idv = $PrivateCarRenewalPremiumList['VehicleIdv'];
            $proposal->idv                  = $idv;
            $proposal->proposal_no          = $proposal_reponse['Data']['TransactionNo'];
            $proposal->unique_proposal_id   = $proposal_reponse['Data']['TransactionNo'];
            //$proposal->customer_id          = $generalInformation['customerId'];
            $proposal->od_premium           = $basicOD - $NcbDiscountAmount;
            $proposal->tp_premium           = $final_tp;
            $proposal->ncb_discount         = $NcbDiscountAmount;
            $proposal->addon_premium        = $addon_premium;
            $proposal->total_premium        = $NetPremiumAmount;
            $proposal->service_tax_amount   = $TaxAmount;
            $proposal->final_payable_amount = $TotalPremiumAmount;
            $proposal->cpa_premium          = $PACoverOwnerDriverPremium;
            $proposal->total_discount       = $total_discount;
            $proposal->ic_vehicle_details   = $vehicleDetails;
            $proposal->policy_start_date    = $policy_start_date;
            $proposal->policy_end_date      = $policy_end_date;
            $proposal->tp_start_date        = $policy_start_date;
            $proposal->tp_end_date          = $policy_end_date;
            $proposal->save();

            $updateJourneyStage['user_product_journey_id'] = $enquiryId;
            $updateJourneyStage['ic_id'] = '11';
            $updateJourneyStage['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $updateJourneyStage['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($updateJourneyStage);
                
            return response()->json([
                'status' => true,
                'msg' => "Proposal Submitted Successfully!",
                'data' => camelCase([
                    'proposal_no'        => $proposal_reponse['Data']['TransactionNo'],
                    'finalPayableAmount' => $TotalPremiumAmount
                ]),
            ]);        

        }
        else
        {
            return [
                'status' => false,
                'message' => $proposal_reponse['Status'] ?? 'Service Issue'
            ];
        }
     }
    }
}
