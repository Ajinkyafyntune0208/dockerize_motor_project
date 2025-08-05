<?php

namespace App\Http\Controllers\Proposal\Services\V1;

use App\Http\Controllers\Controller;
use App\Models\IcVersionMapping;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\UserProposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\SyncPremiumDetail\Services\HdfcErgoPremiumDetailController;
use App\Http\Controllers\Proposal\Services\hdfcErgoSubmitProposalMiscd;
use App\Models\AgentIcRelationship;
use App\Models\ProposalExtraFields;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class hdfcErgoSubmitProposals extends Controller
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
        }
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
    
        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.AUTHENTICATE_URL'), [], 'hdfc_ergo', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Token Generation',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
            'product_code' => $product_code,
            'transaction_id' => $transaction_id,
            'headers' => [
                'Content-type' => 'application/json',
                'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                'PRODUCT_CODE' => $product_code,
                'TransactionID' => $transaction_id,
                'Accept' => 'application/json',
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CV.CREDENTIAL')
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
                        'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
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

                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.CALCULATE_PREMIUM_URL'), $premium_calculation_request, 'hdfc_ergo', [
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
                        'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
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

                        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.CREATE_PROPOSAL'), $premium_calculation_request, 'hdfc_ergo', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Proposal Generation',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'proposal',
                            'requestMethod' => 'post',
                            'product_code' => $product_code,
                            'transaction_id' => $transaction_id,
                            'token' => $token_data['Authentication']['Token'],
                            'headers' => [
                                'Content-type' => 'application/json',
                                'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                                'PRODUCT_CODE' => $product_code,
                                'TransactionID' => $transaction_id,
                                'Accept' => 'application/json',
                                'Token' => $token_data['Authentication']['Token']
                            ]
                        ]);

                        $getpremium = $get_response['response'];
                        $arr_proposal = json_decode($getpremium, true);
                        $premWebserviceId = $get_response['webservice_id'];

                        if ($parent_id == 'PCV')
                        {
                            $proposal_premium = $arr_proposal['Resp_PCV'] ?? null;
                        }
                        else
                        {
                            $proposal_premium = $arr_proposal['Resp_GCV'] ?? null;
                        }

                        if (isset($arr_proposal['StatusCode']) && $arr_proposal['StatusCode'] == 200)
                        {
                            $basic_od = $proposal_premium['Basic_OD_Premium'] + ($proposal_premium['HighTonnageLoading_Premium'] ?? 0);
                            $tppd = $proposal_premium['Basic_TP_Premium'];
                            $pa_owner = $proposal_premium['PAOwnerDriver_Premium'];
                            $pa_unnamed = 0;
                            $pa_paid_driver = $proposal_premium['PAPaidDriverCleaCondCool_Premium'];
                            $electrical_accessories = $proposal_premium['Electical_Acc_Premium'];
                            $non_electrical_accessories = $proposal_premium['NonElectical_Acc_Premium'];
                            $zero_dep_amount = $proposal_premium['Vehicle_Base_ZD_Premium'];
                            $ncb_discount = $proposal_premium['NCBBonusDisc_Premium'];
                            $lpg_cng = $proposal_premium['BiFuel_Kit_OD_Premium'];
                            $lpg_cng_tp = isset($proposal_premium['BiFuel_Kit_TP_Premium']) && $proposal_premium['BiFuel_Kit_TP_Premium'] > 0 ? $proposal_premium['BiFuel_Kit_TP_Premium'] : (isset($proposal_premium['InBuilt_BiFuel_Kit_Premium']) && $proposal_premium['InBuilt_BiFuel_Kit_Premium'] > 0 ? $proposal_premium['InBuilt_BiFuel_Kit_Premium'] : 0);
                            $automobile_association = 0;
                            $anti_theft = $parent_id == 'PCV' ? $proposal_premium['AntiTheftDisc_Premium'] : 0;
                            $tppd_discount_amt = $proposal_premium['TPPD_premium'] ?? 0;
                            $other_addon_amount = 0;
                            $liabilities = 0;
                            $ll_paid_cleaner = $proposal_premium['NumberOfDrivers_Premium'];
                            $imt_23 = $parent_id == 'GCV' ? $proposal_premium['VB_InclusionofIMT23_Premium'] : 0;
                            $ic_vehicle_discount = 0;
                            $voluntary_excess = 0;
                            $other_discount = 0;

                            if ($electrical_accessories > 0)
                            {
                                $zero_dep_amount += (int)$proposal_premium['Elec_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $proposal_premium['Elec_InclusionofIMT23_Premium'] : 0;
                            }

                            if ($non_electrical_accessories > 0)
                            {
                                $zero_dep_amount += (int)$proposal_premium['NonElec_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $proposal_premium['NonElec_InclusionofIMT23_Premium'] : 0;
                            }

                            if ($lpg_cng > 0)
                            {
                                $zero_dep_amount += (int)$proposal_premium['Bifuel_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $proposal_premium['BiFuel_InclusionofIMT23_Premium'] : 0;
                            }

                            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt;
                            $final_od_premium = $basic_od - $final_total_discount - ($proposal_premium['LimitedtoOwnPremises_OD_Premium'] ?? 0);
                            $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $ll_paid_cleaner + $pa_owner - ($proposal_premium['LimitedtoOwnPremises_TP_Premium'] ?? 0);
                            $total_addon_premium = $zero_dep_amount + $imt_23 + $electrical_accessories + $non_electrical_accessories + $lpg_cng;

                            if($arr_proposal['Policy_Details']['ProposalNumber'] == null){
                                return response()->json([
                                    'status' => false,
                                    'msg' => "The proposal number cannot have a null value",
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                ]);
                            }
                            //GET CIS DOCUMENT
                            if(config('IC.HDFC_ERGO.CIS_DOCUMENT_ENABLE') == 'Y'){
                                if(!empty($arr_proposal['Policy_Details']['ProposalNumber'])){
                                    $get_cis_document_array = [
                                        'TransactionID' => $transaction_id,
                                        'Req_Policy_Document' => [
                                            'Proposal_Number' => $arr_proposal['Policy_Details']['ProposalNumber'] ?? null,
                                        ],
                                    ];
        
                                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_CREATE_CIS_DOCUMENT'), $get_cis_document_array, 'hdfc_ergo', [
                                        'section' => $productData->product_sub_type_code,
                                        'method' => 'Get CIS Document',
                                        'enquiryId' => $enquiryId,
                                        'productName' => $productData->product_name,
                                        'transaction_type' => 'proposal',
                                        'requestMethod' => 'post',
                                        'product_code' => $product_code,
                                        'transaction_id' => $transaction_id,
                                        'token' => $token_data['Authentication']['Token'],
                                        'headers' => [
                                            'Content-type' => 'application/json',
                                            'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                                            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                                            'PRODUCT_CODE' => $product_code,
                                            'TransactionID' => $transaction_id,
                                            'Accept' => 'application/json',
                                            'Token' => $token_data['Authentication']['Token']
                                        ]
                                    ]);;
                                    $cis_doc_resp = json_decode($get_response['response']);
                                    $pdfData = base64_decode($cis_doc_resp->Resp_Policy_Document->PDF_BYTES);
                                    if (checkValidPDFData($pdfData)) {
                                        Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) .'_cis' .'.pdf', $pdfData);
        
                                        // $pdf_url = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id) .'_cis' . '.pdf';
                                        ProposalExtraFields::insert([
                                            'enquiry_id' => $enquiryId,
                                            'cis_url'    => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id) .'_cis' . '.pdf'
                                        ]);
                                    }else{
                                        return response()->json([
                                            'status' => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'msg'    => $cis_doc_resp->Error ?? 'CIS Document service Issue'
                                        ]);
                                    }
                                }
                            }

                            HdfcErgoPremiumDetailController::savePremiumDetails($premWebserviceId);

                            UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                ->update([
                                    'od_premium' => $final_od_premium,
                                    'tp_premium' => $final_tp_premium,
                                    'ncb_discount' => $proposal_premium['NCBBonusDisc_Premium'],
                                    'addon_premium' => $total_addon_premium,
                                    'total_premium' => $proposal_premium['Net_Premium'],
                                    'service_tax_amount' =>$proposal_premium['Service_Tax'],
                                    'final_payable_amount' => $proposal_premium['Total_Premium'],
                                    'cpa_premium' => $proposal_premium['PAOwnerDriver_Premium'],
                                    'total_discount' => $final_total_discount,
                                    'proposal_no' => $arr_proposal['Policy_Details']['ProposalNumber'],
                                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                    'tp_start_date' => date('d-m-Y', strtotime($policy_start_date)),
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
                                    'proposalNo' => $arr_proposal['Policy_Details']['ProposalNumber'],
                                    'finalPayableAmount' => $proposal_premium['Total_Premium'], 
                                    'is_breakin' => 'N',//$is_breakin_case,
                                    'inspection_number' => '',//$proposal_response->policyNumber,
                                ]
                            ]);
                        }
                        else
                        {
                            return response()->json([
                                'premium_amount' => 0,
                                'status' => false,
                                'msg' => $proposal_premium['Error'] ?? "Proposal Service Issue",
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                            ]);
                        }
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
}