<?php

namespace App\Http\Controllers\Inspection\Service;

use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\MasterRto;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use DateTime;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

class HdfcErgoInspectionService
{
    public static function inspectionConfirm($request)
    {
        $breakinDetails = UserProposal::join('cv_breakin_status', 'user_proposal.user_proposal_id', '=', 'cv_breakin_status.user_proposal_id')
                        ->where('cv_breakin_status.breakin_number', '=', $request->inspectionNo)
                        ->first();
        if($breakinDetails)
        {
            $inspection_array = 'AgentCode=' . config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE') . '&PGTransNo=' . $request->inspectionNo;

            $get_response = getWsData(
                config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_INSPECTION_STATUS'),
                $inspection_array,
                'hdfc_ergo',
                [
                    'section'       => $request->productType,
                    'method'        => 'Breakin Inspection Status',
                    'requestMethod' => 'post',
                    'enquiryId'     => $breakinDetails->user_product_journey_id,
                    'productName'   => $request->productType,
                    'transaction_type' => 'proposal'
                ]
            );
            $inspection_data = $get_response['response'];

            if($inspection_data)
            {
                $inspection_data = html_entity_decode($inspection_data);
                $inspection_data = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $inspection_data);
                $inspection_data = XmlToArray::convert($inspection_data);

                $breakin_status = !empty($inspection_data['BreakinDTO']['BreakInStatus']) ? $inspection_data['BreakinDTO']['BreakInStatus'] : '';
                DB::table('cv_breakin_status')
                    ->where('breakin_number', $request->inspectionNo)
                    ->update(
                        [
                            'breakin_id' => $inspection_data['BreakinDTO']['BreakinID'],
                            'breakin_status' => $breakin_status,
                            'inspection_date' => date('Y-m-d', strtotime($inspection_data['BreakinDTO']['InspectionDT']))
                        ]
                    );

                if($inspection_data['BreakinDTO']['BreakInStatus'] == 'RECOMMENDED')
                {
                    DB::table('cv_breakin_status')
                    ->where('breakin_number', $request->inspectionNo)
                    ->update([
                        'inspection_date' => date('Y-m-d', strtotime($inspection_data['BreakinDTO']['InspectionDT'])),
                        'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED']
                    ]);

                    $journeyStageData = [
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                    ];
                    updateJourneyStage($journeyStageData);

                    $final_inspection_array = 'AgentCode=' . config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE') . '&PGTransNo=' . $request->inspectionNo;

                    $get_response = getWsData(
                            config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GET_BREAKIN_PROPOSAL_DATA_PREMIUM'),
                            $final_inspection_array,
                            'hdfc_ergo',
                            [
                                'section'       => $request->productType,
                                'method'        => 'Breakin Proposal Data Final Premium',
                                'requestMethod' => 'post',
                                'enquiryId'     => $breakinDetails->user_product_journey_id,
                                'productName'   => $request->productType,
                                'transaction_type' => 'proposal'
                            ]
                        );
                        $final_inspection_data = $get_response['response'];

                    if($final_inspection_data)
                    {
                        $final_inspection_data = html_entity_decode($final_inspection_data);
                        $final_inspection_data = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $final_inspection_data);
                        $final_inspection_data = XmlToArray::convert($final_inspection_data);
                        $final_inspection_data = $final_inspection_data['xmlmotorpolicy'];

                        $updateData = [
                            'policy_start_date'   =>  str_replace('/', '-', $final_inspection_data['Policy_Startdate']),
                            'policy_end_date'     =>  str_replace('/', '-', $final_inspection_data['Policy_Enddate']),
                            'total_premium'       =>  $final_inspection_data['PREMIUMOUTPUT']['NUM_NET_PREMIUM'],
                            'service_tax_amount'  =>  $final_inspection_data['PREMIUMOUTPUT']['NUM_SERVICE_TAX'],
                            'final_payable_amount'=>  $final_inspection_data['PREMIUMOUTPUT']['NUM_TOTAL_PREMIUM'],
                            'is_inspection_done'  =>  'Y',
                        ];

                        UserProposal::where('user_proposal_id', $breakinDetails->user_proposal_id)->update($updateData);
                        DB::table('cv_breakin_status')
                            ->where('user_proposal_id', $breakinDetails->user_proposal_id)
                            ->update([
                                'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                                'updated_at'    => date('Y-m-d H:i:s'),
                            ]);

                        /*--Final proposal array--*/
                        $quote_data = getQuotation($breakinDetails->user_product_journey_id);

                        /* $ic_version_code = IcVersionMapping::select('ic_version_code')
                                                ->where('ic_id', $breakinDetails->ic_id)
                                                ->where('fyn_version_id', $quote_data->version_id)
                                                ->first();
                        $get_mapping_mmv_details = DB::table('hdfc_ergo_model_master')->where('version_id', $ic_version_code->ic_version_code)->first(); */

                        $first_reg_date = date('d/m/Y', strtotime($quote_data->vehicle_register_date));
                        $policy_expiry_date = $quote_data->previous_policy_expiry_date;

                        $rto_data = MasterRto::where('rto_code', $quote_data->rto_code)->where('status', 'Active')->first();
                       /*  $rto_location = DB::table('cv_hdfc_ergo_rto_location')
                                    ->where('Txt_Rto_Location_desc', 'like', '%' . $rto_data->rto_name . '%')
                                    ->first(); */

                        $requestData = getQuotation($breakinDetails->user_product_journey_id);
                        $master_policy_id = QuoteLog::where('user_product_journey_id', $breakinDetails->user_product_journey_id)->first();
                        $productData = getProductDataByIc($master_policy_id->master_policy_id);


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
                                'premium_amount' => '0',
                                'status' => false,
                                'message' => $mmv['message'],
                            ];
                        }

                        $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);

                        $rto_location = DB::table('cv_hdfc_ergo_rto_location')
                                        ->where('Txt_Rto_Location_desc', 'like', '%' . $rto_data->rto_name . '%')
                                        ->where('Num_Vehicle_Subclass_Code', '=', $get_mapping_mmv_details->vehicle_subclass_code)
                                        ->where('Num_Vehicle_Class_code', '=', $get_mapping_mmv_details->vehicle_class_code)
                                    ->first();
                        $rto_location = keysToLower($rto_location);

                        $quote_log_data = DB::table('quote_log')->where('user_product_journey_id', $breakinDetails->user_product_journey_id)->first();
                        $idv = $quote_log_data->idv;
                        $ex_showroom    = $quote_log_data->ex_showroom_price_idv;

                        if ($quote_data->policy_type == 'newbusiness') {
                            $businesstype       = 'new business';
                            $prepolstartdate    = '01/01/1900';
                            $prepolicyenddate   = '01/01/1900';
                        }else{
                            $businesstype       = 'rollover';
                            if($policy_expiry_date == 'New'){
                                $prepolstartdate = $prepolicyenddate = '';
                            }else{
                                $date1 = new DateTime(str_replace('/', '-', $final_inspection_data['Policy_Startdate']));
                                $date2 = new DateTime($policy_expiry_date);
                                $days = $date1->diff($date2)->format('%a');
                                if ($days >= 90) {
                                    $prepolstartdate = $prepolicyenddate = '';
                                }else{
                                    $prepolstartdate    = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($policy_expiry_date)))));
                                    $prepolicyenddate   = date('d/m/Y', strtotime($policy_expiry_date));
                                }
                            }
                        }

                        $ic_vehicle_details = json_decode($breakinDetails->ic_vehicle_details);

                        $selected_addons = DB::table('selected_addons')
                                            ->where('user_product_journey_id', $breakinDetails->user_product_journey_id)
                                            ->first();
                        $zeroDepth = '0';
                        if($selected_addons->addons != '')
                        {
                            $addons = json_decode($selected_addons->addons);
                            foreach ($addons as $value) {
                                if($value->name == 'Zero Depreciation')
                                {
                                    $zeroDepth = '1';
                                }
                            }
                        }
                        $PAToPaidDriver_SumInsured = 0;
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
                        $cpa_opted = '0';
                        if (!empty($selected_addons) && $selected_addons->compulsory_personal_accident != '') {
                            $cpa = json_decode($selected_addons->compulsory_personal_accident);
                            foreach ($cpa as $value) {
                                if (isset($value->name)) {
                                    $cpa_opted = '1';
                                }
                            }
                        }
                        $IMT23 = '0';
                        if (!empty($selected_addons) && $selected_addons->compulsory_personal_accident != '') {
                            $addons = json_decode($selected_addons->applicable_addons);
                            if (!empty($addons)) {
                                foreach ($addons as $value) {
                                    if ($value->name == 'IMT - 23') {
                                        $IMT23 = '1';
                                    }
                                }
                            }
                        }
                        $cpa_opted = $quote_data->vehicle_owner_type == 'I' ? $cpa_opted : '0';

                        $proposal_array = [
                            'str',
                                [
                                    'xmlmotorpolicy' => [
                                        'AgentCode'                             => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_COMPREHENSIVE_AGENT_CODE'),
                                        'Type_of_Business'                      => $businesstype,
                                        'Policy_Startdate'                      => $final_inspection_data['Policy_Startdate'],
                                        'Policy_Enddate'                        => $final_inspection_data['Policy_Enddate'],
                                        'Policy_Type'                           => 'C',
                                        'Year_of_Manufacture'                   => date('d/m/Y', strtotime('01-' . $breakinDetails->vehicle_manf_year)),
                                        'Registration_Citycode'                 => $rto_location->txt_rto_location_code,
                                        'Registration_City'                     => $breakinDetails->city,
                                        'Manufacturer_Code'                     => $final_inspection_data['Manufacturer_Code'],
                                        'Manufacture_Name'                      => $final_inspection_data['Manufacture_Name'],
                                        'Vehicle_Modelcode'                     => $final_inspection_data['Vehicle_Modelcode'],
                                        'Vehicle_Model'                         => $final_inspection_data['Vehicle_Model'],
                                        'Purchase_Regndate'                     => $first_reg_date,
                                        'Vehicle_Regno'                         => $breakinDetails->vehicale_registration_number,
                                        'Engine_No'                             => $breakinDetails->engine_number,
                                        'Chassis_No'                            => $breakinDetails->chassis_number,
                                        'Fuel_Type'                             => $ic_vehicle_details->fuel_type,
                                        'Vehicle_Ownedby'                       => $quote_data->vehicle_owner_type == 'I' ? 'I' : 'O',
                                        'Name_Financial_Institution'            => $breakinDetails->name_of_financer,
                                        'Ex-showroom_Price'                     => $ex_showroom,
                                        'Sum_Insured'                           => $breakinDetails->idv,
                                        'Electical_Acc'                         => (($quote_data->electrical_acessories_value != '') ? $quote_data->electrical_acessories_value : '0'),
                                        'NonElectical_Acc'                      => (($quote_data->nonelectrical_acessories_value != '') ? $quote_data->nonelectrical_acessories_value : '0'),
                                        'LPG-CNG_Kit'                           => (($quote_data->bifuel_kit_value != '') ? $quote_data->bifuel_kit_value : '0'),
                                        'No_of_LLdrivers'                       => isset($nooflldrivers) ? $nooflldrivers : '0',
                                        'Paiddriver_Si'                         => $PAToPaidDriver_SumInsured,
                                        'No_of_Employees'                       => '0',
                                        'limitedownpremises'                    => '0',
                                        'InclusionIMT23'                        => isset($IMT23) ? $IMT23 : '0',
                                        'NoOfFPP'                               => '0',
                                        'NoOfNFPP'                              => '0',
                                        'Bustype '                              => '',
                                        'Contactno_Office '                     => '',
                                        'Contactno_Home '                       => '',
                                        'LocationCode '                         => '',
                                        'CustomerInspection'                    => '0',
                                        'BreakinId'                             => '',
                                        'IsCustomerAuthenticationDone'          => '0',
                                        'SurveyorRegNo'                         => '',
                                        'AuthenticationType '                   => '',
                                        'VehicleClassCode'                      => $final_inspection_data['VehicleClassCode'],
                                        'productcode'                           => $final_inspection_data['productcode'],
                                        'vehiclesubclasscode'                   => $final_inspection_data['vehiclesubclasscode'],
                                        'IdvAmount'                             => $final_inspection_data['IdvAmount'],
                                        'intermediaryid'                        => $final_inspection_data['intermediaryid'],
                                        'privateuse'                            => '0',
                                        'Unnamed_Si'                            => '',
                                        'NCB_ExpiringPolicy'                    => $quote_data->previous_ncb,
                                        'NCB_RenewalPolicy'                     => $quote_data->applicable_ncb,
                                        'Total_Premium'                         => round($final_inspection_data['PREMIUMOUTPUT']['NUM_NET_PREMIUM']),
                                        'Service_Tax'                           => round($final_inspection_data['PREMIUMOUTPUT']['NUM_SERVICE_TAX']),
                                        'Total_Amoutpayable'                    => round($final_inspection_data['PREMIUMOUTPUT']['NUM_TOTAL_PREMIUM']),
                                        'PrePolicy_Startdate'                   => $prepolstartdate,
                                        'PrePolicy_Enddate'                     => $prepolicyenddate,
                                        'PreInsurerCode'                        => $breakinDetails->previous_insurance_company,
                                        'PrePolicy_Number'                      => $breakinDetails->previous_policy_number,
                                        'IsPrevious_Claim'                      => $final_inspection_data['IsPrevious_Claim'],
                                        'First_Name'                            => $breakinDetails->first_name,
                                        'Last_Name'                             => $breakinDetails->last_name,
                                        'Date_of_Birth'                         => date("d/m/Y", strtotime($breakinDetails->dob)),
                                        'Gender'                                => $breakinDetails->gender,
                                        'Email_Id'                              => $breakinDetails->email,
                                        'Contactno_Mobile'                      => $breakinDetails->mobile_number,
                                        'Car_Address1'                          => $breakinDetails->address_line1,
                                        'Car_Address2'                          => $breakinDetails->address_line2,
                                        'Car_Address3'                          => $breakinDetails->address_line3,
                                        'Car_State'                             => $breakinDetails->state,
                                        'Car_Statecode'                         => $breakinDetails->state_id,
                                        'Car_City'                              => $breakinDetails->city,
                                        'Car_Citycode'                          => $breakinDetails->city_id,
                                        'Car_Pin'                               => $breakinDetails->pincode,
                                        'Corres_Address1'                       => isset($breakinDetails->car_registration_address1) ? $breakinDetails->car_registration_address1 : $breakinDetails->address_line1,
                                        'Corres_Address2'                       => isset($breakinDetails->car_registration_address2) ? $breakinDetails->car_registration_address2 : $breakinDetails->address_line2,
                                        'Corres_Address3'                       => isset($breakinDetails->car_registration_address3) ? $breakinDetails->car_registration_address3 : $breakinDetails->address_line3,
                                        'Corres_State'                          => isset($breakinDetails->car_registration_state) ? $breakinDetails->car_registration_state : $breakinDetails->state,
                                        'Corres_Statecode'                      => isset($breakinDetails->car_registration_state_id) ? $breakinDetails->car_registration_state_id : $breakinDetails->state_id,
                                        'Corres_City'                           => isset($breakinDetails->car_registration_city) ? $breakinDetails->car_registration_city : $breakinDetails->city,
                                        'Corres_Citycode'                       => isset($breakinDetails->car_registration_city_id) ? $breakinDetails->car_registration_city_id : $breakinDetails->city_id,
                                        'Corres_Pin'                            => isset($breakinDetails->car_registration_pincode) ? $breakinDetails->car_registration_pincode : $breakinDetails->pincode,
                                        'Data1'                                 => '0',
                                        'Data2'                                 => '0',
                                        'Data3'                                 => '0',
                                        'Data4'                                 => '0',
                                        'Data5'                                 => '0',
                                        'IsEmergency_Cover'                     => '0',
                                        'Owner_Driver_Nominee_Name'             => $breakinDetails->nominee_name,
                                        'Owner_Driver_Nominee_Age'              => $breakinDetails->nominee_age,
                                        'Owner_Driver_Nominee_Relationship'     => $breakinDetails->nominee_relationship,
                                        'Owner_Driver_Appointee_Name'           => '',
                                        'Owner_Driver_Appointee_Relationship'   => '',
                                        'PAN_Card'                              => $breakinDetails->pan_number,
                                        'IsAgeDisc'                             => '0',
                                        'is_pa_cover_owner_driver'              => $cpa_opted,//$quote_data->vehicle_owner_type == 'C' ? 0 : 1,
                                        'PlanType'                              => '',
                                        'IsZeroDept_Cover'                      => $zeroDepth,
                                        'IsZeroDept_RollOver'                   => '0',
                                        'BiFuelType'                            => 'CNG',
                                        'IsRTICover'                            => '',
                                        'num_is_emr_asst_wider_cvr'             => '',
                                        'BrkPGTransNo'                          => $request->inspectionNo,
                                        'page_calling'                          => '2'
                                    ]
                                ]
                        ];
                        /*--Final proposal array--*/

                        $get_response = getWsData(
                            config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_BREAKIN_FINAL_PROPOSAL'),
                            $proposal_array,
                            'hdfc_ergo',
                            [
                                'root_tag'      => 'xmlmotorpolicy',
                                'section'       => $request->productType,
                                'method'        => 'Breakin Final Proposal Re Generation',
                                'requestMethod' => 'post',
                                'enquiryId'     => $breakinDetails->user_product_journey_id,
                                'productName'   => $request->productType,
                                'transaction_type'    => 'proposal'
                            ]
                        );
                        $final_submit_proposal_data = $get_response['response'];

                        if($final_submit_proposal_data)
                        {
                            $final_submit_proposal_data = html_entity_decode($final_submit_proposal_data);
                            $final_submit_proposal_data = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $final_submit_proposal_data);
                            $final_submit_proposal_data = XmlToArray::convert($final_submit_proposal_data);

                            if($final_submit_proposal_data['WsResult']['WsResultSet']['WsStatus'] == 0)
                            {
                                $payment_end_date = date('Y-m-d 23:59:59', strtotime($inspection_data['BreakinDTO']['InspectionDT'] . '+7 days'));

                                DB::table('cv_breakin_status')
                                    ->where('breakin_number', $request->inspectionNo)
                                    ->update(['payment_end_date' => $payment_end_date]);

                                UserProposal::where('user_proposal_id', $breakinDetails->user_proposal_id)
                                    ->update(
                                        [ 'proposal_no' => $final_submit_proposal_data['WsResult']['WsResultSet']['WsMessage'] ]
                                    );

                                $journey_payload = DB::table('cv_journey_stages')->where('proposal_id', $breakinDetails->user_proposal_id)
                                    ->first();

                                return response()->json([
                                    'status' => true,
                                    'msg' => 'Vehicle Inspection is Done By HDFC ERGO!',
                                    'data' => [
                                        'proposal_no' => $final_inspection_data['BrkPGTransNo'],
                                        'total_payable_amount' => round($final_inspection_data['Total_Amoutpayable']),
                                        'enquiryId' => customEncrypt($breakinDetails->user_product_journey_id),
                                        'proposalUrl' =>  str_replace('quotes','proposal-page',$journey_payload->proposal_url)
                                    ]
                                ]);
                            }
                            else
                            {
                                return response()->json([
                                    'status' => false,
                                    'msg' => 'Proposal submission failed',
                                    'data' => [
                                        'proposal_no' => $final_submit_proposal_data['WsResult']['WsResultSet']['WsMessage']
                                    ]
                                ]);
                            }
                        }
                        else
                        {
                            return response()->json([
                                'status' => false,
                                'msg' => 'Insurer not reachable'
                            ]);
                        }
                    }
                    else
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => 'Insurer not reachable'
                        ]);
                    }
                }
                else
                {
                    $journeyStageData = [
                        'user_product_journey_id' => $breakinDetails->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_PENDING']
                    ];
                    updateJourneyStage($journeyStageData);

                    $message = 'Your Vehicle Inspection is Pending. Please try after some time';

                    if ($inspection_data['BreakinDTO']['BreakInStatus'] == 'EXPIRED')
                    {
                        $message = 'Your Vehicle Inspection is Rejected';
                    }
                    else if ($inspection_data['BreakinDTO']['BreakInStatus'] == 'ISSUED')
                    {
                        $message = 'Policy already purchased';
                    }

                    return response()->json([
                        'status' => false,
                        'msg' => $message
                    ]);
                }
            }
            else
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Insurer not reachable'
                ]);
            }
        }
        else
        {
            return response()->json([
                        'status' => false,
                        'msg' => 'No breakin details found!'
                    ]);
        }
    }
}
