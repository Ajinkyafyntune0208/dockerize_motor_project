<?php
include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;

function getQuoteV1($enquiryId, $requestData, $productData)
{
    /*
        Product Lists
        1. BASIC (Without All addon)

        2. ZERO_DEP (With zero_dep - DepreciationWaiverforTW,RSA,Engine_protection,InvoicePrice)

        3. BASIC_ADDON (Except zero_dep - RSA,Engine_protection,InvoicePrice)
        */
        
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if (empty($requestData->rto_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available',
            'request'=> $requestData->rto_code
        ];
    }

    $rto_code = $requestData->rto_code;  
    $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code   
    
    $rto_data = DB::table('royal_sundaram_rto_master AS rsrm')
        ->where('rsrm.rto_no', str_replace('-', '', $rto_code))
        ->first();

    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available',
            'request'=> [
                'rto_code'=>$requestData->rto_code,
                'rto_data'=>$rto_data
            ]
        ];
    }

    $mmv = get_mmv_details($productData, $requestData->version_id, 'royal_sundaram');

    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ];
    }

    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ];
    } elseif ($mmv->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ];
    }

    $mmv_data = [
        'manf_name' => $mmv->make,
        'model_name' => $mmv->model,
        'version_name' => $mmv->model,
        'seating_capacity' => $mmv->min_seating_capacity,
        'carrying_capacity' => ((int) $mmv->min_seating_capacity) - 1,
        'cubic_capacity' => $mmv->engine_capacity_amount,
        'engine_capacity_amount' => $mmv->engine_capacity_amount,
        // 'fuel_type' =>  $mmv->fuel_type,
        // 'gross_vehicle_weight' => $mmv->vehicle_weight,
        'vehicle_type' => 'BIKE',
        'version_id' => $mmv->ic_version_code,
    ];

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
    $od_only = ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false;
    
    //Removing age validation for all iC's  #31637 
    // if ($od_only == true || $premium_type == 'comprehensive' || $premium_type == 'breakin') {
    //     if ($requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party') {

    //         return  [
    //             'premium_amount' => 0,
    //             'status' => false,
    //             'message' => 'Quotes not available when previous policy type is not sure or third party',
    //         ];
    //     }
    // }

    $prev_policy_end_date = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($prev_policy_end_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = floor($age / 12);
    $vehicle_age2 = ($age / 12);

    // if (($interval->y >= 15) && ($tp_only)) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
    //     ];
    // }
    // if ($vehicle_age2 > 5 && $productData->zero_dep == 0) {
    //     return [
    //         'status' => false,
    //         'premium' => '0',
    //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
    //         'request'=> [
    //             'vehicle_age'=>$vehicle_age,
    //             'product_data'=>$productData->zero_dep
    //         ]
    //     ];
    // }

    $bike_manf_date = '01-'.$requestData->manufacture_year;

    $ncb_levels = [
        '0' => '0',
        '20' => '1',
        '25' => '2',
        '35' => '3',
        '45' => '4',
        '50' => '5'
    ];

    $opted_addons = [];
    //$tp_only = false;
    $add_ons_opted_in_previous_policy = '';
    $type_of_cover = '';
    $policyType = 'Comprehensive';
    $previous_insurer_name = !empty($requestData->previous_insurer) ? $requestData->previous_insurer : 'Bajaj';
    $previous_policy_type = (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive' : 'ThirdParty');
    $previous_insurers_correct_address = 'Mumbai';
    $previous_policy_number = '1234213';
    $is_previous_claim = $requestData->is_claim == 'Y' ? 'Yes' : 'No';
    $cpa_tenure = '1';
    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                    
                }
            }
        }  

    if ($requestData->business_type == 'newbusiness') {
        $product_name = 'BrandNewTwoWheeler';
        $businessType = 'New Business';
        $type_of_cover = 'Bundled';
        $policy_start_date = date('Y-m-d');
        $previous_insurer_name = '';
        $previous_policy_type = '';
        $previous_policy_number = '';
        $is_previous_claim = 'No';
        $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '';
    } else if ($requestData->business_type == 'rollover') {
        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
        $product_name = 'RolloverTwoWheeler';
        $businessType = 'Roll Over';
        $type_of_cover = 'Comprehensive';
    } else if ($requestData->business_type == 'breakin') {
        $policy_start_date = date('Y-m-d', strtotime('+2 day'));
        $product_name = 'BreakinTwoWheeler';
        $businessType = 'Break-In';
    }

    if ($tp_only) {
        $tp_only = true;
        $policyType = 'Third Party';
        $type_of_cover = 'LiabilityOnly';
        $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
    } elseif ($od_only){
        $policyType = 'Own Damage';
        $type_of_cover = 'standalone';
    }

    if($requestData->previous_policy_type == "ThirdParty"){
        $type_of_cover = 'LiabilityOnly';
    }

    // if (!$tp_only && $vehicle_age <= 5 && $productData->zero_dep == '0') {
    //     $opted_addons[] = 'DepreciationWaiverforTW';
    // }
    if (!$tp_only && $productData->zero_dep == '0') {
        $opted_addons[] = 'DepreciationWaiverforTW';
    }
        $opted_addons[] = 'InvoicePrice';
        // $ReturnToInvoice = $vehicle_age2 <= 4 ? "true":"false";
        $ReturnToInvoice = ($productData->product_identifier == "basic") ? "false" : "true";
        $Plan1 = "Yes";
        $Plan2 = "No";
        // $Engine_protection = $vehicle_age2 <= 5 ? "true" : "false";
        $Engine_protection = ($productData->product_identifier == "basic") ? "false" : "true";
        // dd($Engine_protection, $vehicle_age2);
        $RSA = ($productData->product_identifier == "basic") ? "false" : "true";
    // $add_ons_opted_in_previous_policy = implode(',', $opted_addons);
       $add_ons_opted_in_previous_policy = ($productData->product_identifier == "basic") ? $opted_addons = [] : implode(',', $opted_addons);

    if ($businessType != 'New Business' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
        $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
        if ($date_difference > 0) {
            $policy_start_date = date('m/d/Y 00:00:00',strtotime('+2 day'));
        }

        if($date_difference > 90){
            $requestData->applicable_ncb = 0;
        }
    }

    if (in_array($requestData->previous_policy_type, ['Not sure'])) {
        $policy_start_date = date('m/d/Y 00:00:00',strtotime('+2 day'));
        $requestData->previous_policy_expiry_date = date('Y-m-d', strtotime('-120 days'));
        $requestData->applicable_ncb = 0;
    }

    $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    $requestData->applicable_ncb = $is_previous_claim == 'Yes' ? 0 : $requestData->applicable_ncb;

    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

    $electrical_accessories = 'No';
    $electrical_accessories_value = '0';
    $non_electrical_accessories = 'No';
    $non_electrical_accessories_value = '0';
    $external_fuel_kit = 'No';
    $external_fuel_kit_amount = '';
    $cover_pa_paid_driver = $cover_pa_unnamed_passenger = 'No';
    $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
    $cover_ll_paid_driver = 'NO';

    if (!empty($additional['additional_covers'])) {
        foreach ($additional['additional_covers'] as $key => $data) {
            if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                $cover_pa_paid_driver = 'Yes';
                $cover_pa_paid_driver_amt = $data['sumInsured'];
            }

            if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                $cover_pa_unnamed_passenger = 'Yes';
                $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
            }

            if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                $cover_ll_paid_driver = 'YES';
            }
        }
    }
    if (!empty($additional['accessories'])) {
        foreach ($additional['accessories'] as $key => $data) {
            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                $external_fuel_kit = 'Yes';
                $external_fuel_kit_amount = $data['sumInsured'];
            }

            if ($data['name'] == 'Non-Electrical Accessories') {
                $non_electrical_accessories = 'Yes';
                $non_electrical_accessories_value = $data['sumInsured'];
            }

            if ($data['name'] == 'Electrical Accessories') {
                $electrical_accessories = 'Yes';
                $electrical_accessories_value = $data['sumInsured'];
            }
        }
    }
    $is_voluntary_access = 'No';
    $voluntary_excess_amt = 0;
    $TPPDCover = '';

    if (!empty($additional['discounts'])) {
        foreach ($additional['discounts'] as $key => $data) {
            if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                $is_voluntary_access = 'Yes';
                $voluntary_excess_amt = $data['sumInsured'];
            }
            if ($data['name'] == 'TPPD Cover') {
                $TPPDCover = '6000';
            }
        }
    }
    $engine_no = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTVWXYZ"), 0, 9);
    $chassis_no = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTVWXYZ"), 0, 7);
    $premium_request = [
        'authenticationDetails' => [
            'agentId' => config('IC.ROYAL_SUNDARAM.V1.BIKE.AGENTID'),
            'apikey' => config('IC.ROYAL_SUNDARAM.V1.BIKE.APIKEY'),
        ],
        'isNewUser' => $requestData->business_type == 'newbusiness' ? 'Yes' : 'No',
        'isproductcheck' => 'true',
        'istranscheck' => 'true',
        'premium' => '0.0',
        'proposerDetails' => [
            'addressOne' => '',
            'addressTwo' => '',
            'addressThree' => '',
            'addressFour' => '',
            'contactAddress1' => '',
            'contactAddress2' => '',
            'contactAddress3' => '',
            'contactAddress4' => '',
            'contactCity' => '',
            'contactPincode' => '',
            'dateOfBirth' => '18/09/1995',
            'guardianAge' => '',
            'guardianName' => '',
            'nomineeAge' => '18',
            'nomineeName' => '',
            'occupation' => '',
            'regCity' => '',
            'regPinCode' => '',
            'relationshipWithNominee' => '',
            'relationshipwithGuardian' => '',
            'same_addr_reg' => '',
            'title' => 'Mr',
            'firstName' => 'ABC',
            'lastName' => 'XYZ',
            'emailId' => 'abc@gmail.com',
            'mobileNo' => '7898732798',
            'strStdCode' => '044',
            'strPhoneNo' => '2456984',
            'userName' => '',
        ],
        'reqType' => 'XML',
        'respType' => 'XML',
        'vehicleDetails' => [
            'VIRNumber' => 'string',
            'vehicleModelCode' => $mmv->model_code,
            'planOpted' => 'Flexi Plan',
            'yearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
            'drivingExperience' => '1',
            'voluntaryDeductible'=> $is_voluntary_access == 'Yes' ? $voluntary_excess_amt : '0',
            'vehicleManufacturerName' => $mmv->make,
            'engineProtectorPremium' => '',
            'idv' => '',
            'addOnsOptedInPreviousPolicy' => $add_ons_opted_in_previous_policy,
            'tppdLimit' => $TPPDCover,
            'vehicleMostlyDrivenOn' => 'City roads',
            'vehicleRegisteredInTheNameOf' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
            'modelName' => $mmv->model,
            'vehicleRegDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
            'isPreviousPolicyHolder' => $requestData->business_type == 'newbusiness' ? 'false' : 'true',
            'previousInsurerName' => $previous_insurer_name,
            'previousPolicyExpiryDate' => date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
            'previousPolicyType' => $previous_policy_type,
            'previousinsurersCorrectAddress' => $previous_insurers_correct_address,
            'previousPolicyNo' => $previous_policy_number,
            'registrationNumber' => $requestData->vehicle_registration_no != "" ? str_replace('-', '', $requestData->vehicle_registration_no) : str_replace('-', '', $requestData->rto_code.'N4662'),
            'productName'=> $product_name,
            'engineProtector' => 'off',
            'depreciationWaiver' => 'On',
            'hdnEngineProtector' => $Engine_protection,
            "hdnFullInvoice" => $ReturnToInvoice,
            "fullInvoicePlan1" => $Plan1,
            "fullInvoicePlan2" => $Plan2,
            "hdnRoadSideAssistance" => $RSA,
            'companyNameForCar' => $requestData->vehicle_owner_type == 'I' ? '' : 'Test 400',
            'engineNumber' => $engine_no,#'565465466',
            'chassisNumber' => $chassis_no,#'5654656',
            'isTwoWheelerFinanced'=> 'No',
            'isTwoWheelerFinancedValue' => '',
            'financierName' => '',
            'vehicleSubLine' => 'motorCycle',
            'fuelType' => 'Petrol',
            'hdnDepreciation' => $productData->zero_dep == '1' ? 'false' : 'true',
            'automobileAssociationMembership' => 'No',
            'region' => '',//'East Region',
            'carRegisteredCity' => $rto_data->city_name,
            'rtoName' => $rto_data->rto_name,#rto name added
            'averageMonthlyMileageRun' => '',
            'isProductCheck' => 'true',
            'engineCapacityAmount'=> $mmv_data['engine_capacity_amount'],
            'personalAccidentCoverForUnnamedPassengers' =>  $cover_pa_unnamed_passenger == 'Yes' ? $cover_pa_unnamed_passenger_amt : '0',
            'accidentCoverForPaidDriver' => '0',
            'isValidDrivingLicenseAvailable' => 'Yes',
            'legalliabilityToPaidDriver' => $cover_ll_paid_driver,
            'legalliabilityToEmployees' => 'No',
            'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
            'cover_elec_acc' => $electrical_accessories,
            'cover_non_elec_acc' => $non_electrical_accessories,
            'vechileOwnerShipChanged' => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
            'electricalAccessories' => [
                'electronicAccessoriesDetails' => [
                    'MakeModel' => 'MCJYzzJyZd',
                    'NameOfElectronicAccessories' => 'COJuOtbUaF',
                    'Value' => $electrical_accessories_value,
                ],
            ],
            'nonElectricalAccesories' => [
                'nonelectronicAccessoriesDetails' => [
                    'MakeModel' => 'xcnJLzqamh',
                    'NameOfElectronicAccessories' => 'ItNMnantjt',
                    'Value' => $non_electrical_accessories_value,
                ],
            ],
            'claimsMadeInPreviousPolicy' => $is_previous_claim,
            'noClaimBonusPercent' => $ncb_levels[$requestData->applicable_ncb] ?? '0',
            'ncbcurrent' => $requestData->applicable_ncb,
            'claimAmountReceived' => ($is_previous_claim == 'Yes') ? '50000' : '0',
            'claimsReported' => '0',
            'ncbprevious'=> $requestData->previous_ncb,
            'policyTerm' =>  '1',
            'typeOfCover'=> $type_of_cover,
            'cpaCoverisRequired' => 'Yes',
            'cpaPolicyTerm' => $cpa_tenure,
            'cpaCoverDetails' => [
                'noEffectiveDrivingLicense' => '',
                'cpaCoverWithInternalAgent' => '',
                'standalonePAPolicy' => '',
                'companyName'=> '',
                'expiryDate' => '',
                'policyNumber'=> ''
            ]
        ],
    ];
 
    if ($od_only) {
        $premium_request['existingTPPolicyDetails'] = [
            'tpPolicyNumber' => 'RTGRT4523fTRF',
            'tpInsurer' => 'National Insurance Co. Ltd.',
            'tpInceptionDate' => date('d/m/Y', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date))),
            'tpExpiryDate' => date('d/m/Y', strtotime('+5 year +1 day', strtotime($requestData->previous_policy_expiry_date))),
            'tpPolicyTerm' => '5',
        ];
    }
    $data = $premium_request;
    unset($data['vehicleDetails']['chassisNumber']);
    unset($data['vehicleDetails']['engineNumber']);
    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    $checksum_data = checksum_encrypt($data);
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'royal_sundaram',$checksum_data,'BIKE');
    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
        $get_response = $is_data_exits_for_checksum;
    }else{
        $get_response = getWsData(config('IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL'), $premium_request, 'royal_sundaram', [
            'enquiryId' => $enquiryId,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'royal_sundaram',
            'section' => $productData->product_sub_type_code,
            'method' =>'Premium Calculation',
            'transaction_type' => 'quote',
            'checksum' => $checksum_data,
            'root_tag' => 'CALCULATEPREMIUMREQUEST',
        ]);
    }
   
   /*  $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        $POSPCode = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
        $premium_request['isPosOpted'] = 'Yes';
        $premium_request['posCode'] = '';
        $premium_request['posDetails'] = [
            'name' => $pos_data->agent_name,
            'pan' => $POSPCode,
            'aadhaar' => $pos_data->aadhar_no,
            'mobile' => $pos_data->agent_mobile,
            'licenceExpiryDate' => '31/12/2050',
        ];
    }

    if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_ROYAL_SUNDARAM') == 'Y')
    {
        $premium_request['isPosOpted'] = 'Yes';
        $premium_request['posCode'] = '';
        $premium_request['posDetails'] = [
            'name' => 'Agent',
            'pan' => 'ABGTY8890Z',
            'aadhaar' => '569278616999',
            'mobile' => '8850386204',
            'licenceExpiryDate' => '31/12/2050',
        ];
    } */
    $data = $premium_request;
    unset($data['vehicleDetails']['chassisNumber']);
    unset($data['vehicleDetails']['engineNumber']);
    $checksum_data = checksum_encrypt($data);
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'royal_sundaram',$checksum_data,'BIKE');
    
    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
        $get_response = $is_data_exits_for_checksum;
    }else{
        $get_response = getWsData(config('IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL'), $premium_request, 'royal_sundaram', [
            'enquiryId' => $enquiryId,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'royal_sundaram',
            'section' => $productData->product_sub_type_code,
            'method' =>'Premium Calculation',
            'transaction_type' => 'quote',
            'checksum' => $checksum_data,
            'root_tag' => 'CALCULATEPREMIUMREQUEST',
        ]);
    }
    

    $data = $get_response['response'];
    if($get_response['response'] == 'Access denied')
    {
        return [
            'premium_amount'    => 0,
            'status'            => false,
            'message'           => 'Access denied'  
        ];
    }
    if ($data) {
        $premium_response = json_decode($data, TRUE);
        $vehicle_idv = $default_idv = $min_idv = $max_idv = $PREMIUM = 0;
        $skip_second_call = false;

        if (isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002') {
            if (!$tp_only) {
                $vehicle_idv = round($premium_response['PREMIUMDETAILS']['DATA']['IDV']);
                $min_idv = ceil($premium_response['PREMIUMDETAILS']['DATA']['MINIMUM_IDV']);
                $max_idv = floor($premium_response['PREMIUMDETAILS']['DATA']['MAXIMUM_IDV']);
                $default_idv = round($premium_response['PREMIUMDETAILS']['DATA']['MODEL_IDV']);

                if ($requestData->is_idv_changed == 'Y') {
                    if ($requestData->edit_idv >= $max_idv) {
                        $premium_request_idv = $max_idv;
                    } elseif ($requestData->edit_idv <= $min_idv) {
                        $premium_request_idv = $min_idv;
                    } else {
                        $premium_request_idv = $requestData->edit_idv;
                    }
                } else {
                    #$premium_request_idv = $min_idv;
                    $getIdvSetting = getCommonConfig('idv_settings');
                    switch ($getIdvSetting) {
                        case 'default':
                            $premium_request_idv = $vehicle_idv;
                            $skip_second_call = true;
                            break;
                        case 'min_idv':
                            $premium_request_idv = $min_idv;
                            break;
                        case 'max_idv':
                            $premium_request_idv = $max_idv;
                            break;
                        default:
                            $premium_request_idv = $min_idv;
                            break;
                    }
                }

                //$premium_request['vehicleDetails']['modified_idv_value'] = $premium_request['vehicleDetails']['original_idv'] = $premium_request['vehicleDetails']['totalIdv'] = $premium_request['vehicleDetails']['idv'];

                $premium_request['vehicleDetails']['idv'] = $default_idv;

                $premium_request['vehicleDetails']['discountIdvPercent'] = round(((round($premium_request_idv) * 100) / $default_idv) - 100);

                $premium_request['vehicleDetails']['modifiedIdv'] = $premium_request_idv;
                if(!$skip_second_call){
                    $data = $premium_request;
                    unset($data['vehicleDetails']['chassisNumber']);
                    unset($data['vehicleDetails']['engineNumber']);
                    $checksum_data = checksum_encrypt($data);
                    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'royal_sundaram',$checksum_data,'BIKE');
                   if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                    $get_response = $is_data_exits_for_checksum;
                  }else{
                    $get_response = getWsData(config('IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL'), $premium_request, 'royal_sundaram', [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'royal_sundaram',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Premium Recalculation',
                        'transaction_type' => 'quote',
                        'checksum' => $checksum_data,
                        'root_tag' => 'CALCULATEPREMIUMREQUEST',
                    ]);
                  }
              
                }
                $data = $get_response['response'];
                if (!empty($data)) {
                    $premium_response = json_decode($data, TRUE);

                    if (isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002') {
                        $vehicle_idv = round($premium_response['PREMIUMDETAILS']['DATA']['IDV']);
                        $default_idv = round($premium_response['PREMIUMDETAILS']['DATA']['IDV']);
                        $PREMIUM = round($premium_response['PREMIUMDETAILS']['DATA']['PREMIUM']);
                    } else {
                        if (isset($premium_response['PREMIUMDETAILS']['Status'])) {
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'premium' => 0,
                                'message' => $premium_response['PREMIUMDETAILS']['Status']['Message']
                            ];
                        } else {
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'premium' => 0,
                                'message' => 'Insurer not reachable'
                            ];
                        }
                    }
                } else {
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable'
                    ];
                }
            }

            $city_name = DB::table('master_city AS mc')
                ->where('mc.city_name', $rto_data->city_name)
                ->select('mc.zone_id')
                ->first();

            if (empty($city_name)) {
                return [
                    'status' => false,
                    'message' => 'City not found',
                    'request' => [
                        'message' => 'City not found',
                        'requestData' => [
                            'rto_city_name' => $rto_data->city_name
                        ]
                    ]
                ];
            }

            $bike_tariff = DB::table('motor_tariff AS mt')
               // ->whereRaw($mmv_data['cubic_capacity'].' BETWEEN mt.cc_min and mt.cc_max')
                ->whereRaw($vehicle_age . ' BETWEEN mt.age_min and mt.age_max')
                ->where('mt.zone_id', $city_name->zone_id)
                ->first();

            $llpaiddriver_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS']);
            $cover_pa_owner_driver_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER']);
            $cover_pa_paid_driver_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER']);
            $cover_pa_unnamed_passenger_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS']);
            $voluntary_excess = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE']);
            $anti_theft = 0;
            $ic_vehicle_discount = 0;
            $ncb_discount = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS'] ?? 0);
            $electrical_accessories_amt = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES']);
            $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' ? round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES']) : 0;
            // $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' ? (int) $non_electrical_accessories_value * ((int) $bike_tariff->rate_per_thousand/100) : 0;
            $od = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES']);
            $tppd = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD']);
            $tppd_discount = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            if (!empty($TPPDCover)) {
                $tppd_discount = 50 * ($requestData->business_type == 'newbusiness' ? 5 : 1);
                $tppd += $tppd_discount;
            }
            $cng_lpg = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT']);
            $cng_lpg_tp = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG']);
            $underwriting_loading_amt = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['UNDERWRITER_LOADING'] ?? 0);
            $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium;
            $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount + $tppd_discount;
            $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
            $final_gst_amount = round($final_net_premium * 0.18);
            $final_payable_amount = $final_net_premium + $final_gst_amount;

            $applicable_addons = [];
            $addons_data = [
                'in_built' => [],
                'additional' => [],
                'other_premium' => 0
            ];
            if (!$tp_only) {
                // if ($vehicle_age <= 5) {
                //     $applicable_addons = ['zeroDepreciation'];
                //     $addons_data['additional'] = [
                //         'zero_depreciation' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER']),
                //         'engine_protector' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR']),
                //         'return_to_invoice' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['INVOICE_PRICE_INSURANCE']),

                //     ];
                // }
                    $applicable_addons = ['zeroDepreciation'];
                    $addons_data['additional'] = [
                        'zero_depreciation' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER']),
                        'engine_protector' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR']),
                        'return_to_invoice' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['INVOICE_PRICE_INSURANCE']),

                    ];
                $addons_data['additional']['road_side_assistance'] = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDEASSISSTANCE']);
                $addons_data['additional']['engine_protector'] = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR']);
                $addons_data['additional']['road_side_assistance'] = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDEASSISSTANCE']);
                $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                $addons_data['other_premium'] = 0;
            }

            $final_response =([
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => $vehicle_idv,
                    'min_idv' => round($min_idv),
                    'max_idv' => round($max_idv),
                    'default_idv' => $default_idv,
                    'modified_idv'=>$vehicle_idv,
                    'vehicle_idv' => $vehicle_idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $policyType,
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => $voluntary_excess,
                    'version_id' => $mmv->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => $vehicle_idv,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => $mmv_data,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                        'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                        'sum_insured' => $productData->sum_insured,
                        'corp_client_id' => $productData->corp_client_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'insurance_company_id' => $productData->company_id,
                        'status' => $productData->status,
                        'corp_name' => "Ola Cab",
                        'company_name' => $productData->company_name,
                        'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => "",
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $bike_manf_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'bike_age' => $vehicle_age,
                        'ic_vehicle_discount' => $ic_vehicle_discount,
                    ],
                    'ic_vehicle_discount' => $ic_vehicle_discount,
                    'basic_premium' => $od,
                    'deduction_of_ncb' => $ncb_discount,
                    'tppd_premium_amount' => $tppd,
                    'tppd_discount' => $tppd_discount,
                    'motor_electric_accessories_value' => $electrical_accessories_amt,
                    'motor_non_electric_accessories_value' => $non_electrical_accessories_amt,
                    'underwriting_loading_amount'=> $premium_type == 'third_party' ? 0 : $underwriting_loading_amt,
                    'motor_lpg_cng_kit_value' => $cng_lpg,
                    'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                    'seating_capacity' => $mmv->min_seating_capacity,
                    'default_paid_driver' => $llpaiddriver_premium,
                    'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                    'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => $final_od_premium,
                    'cng_lpg_tp' => $cng_lpg_tp,
                    'total_liability_premium' => $final_tp_premium,
                    'net_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount'  => $final_payable_amount,
                    'antitheft_discount' => $anti_theft,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => round($final_total_discount),
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => $businessType,
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => NULL,
                    'get_policy_expiry_date' => NULL,
                    'get_changed_discount_quoteid' => 0,
                    'vehicle_discount_detail' => [
                        'discount_id' => NULL,
                        'discount_rate' => NULL
                    ],
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                    'policy_id' => $productData->policy_id,
                    'insurane_company_id' => $productData->company_id,
                    "max_addons_selection"=> NULL,
                    'add_ons_data' => $addons_data,
                    'applicable_addons' => $applicable_addons,
                    'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
                    'quote_id'                => $premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'] ?? '',
                    'PREMIUM' => $PREMIUM,
                ]
            ]);
            if(isset($cpa_tenure) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '5')
            {
                // unset($final_response['Data']['compulsory_pa_own_driver']);
                $final_response['Data']['multi_Year_Cpa'] = $cover_pa_owner_driver_premium;
            }
            return camelCase($final_response);
        }  else {
            if (isset($premium_response['PREMIUMDETAILS']['Status'])) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'premium' => 0,
                    'message' => $premium_response['PREMIUMDETAILS']['Status']['Message']
                ];
            } else {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'premium' => 0,
                    'message' => 'Insurer not reachable'
                ];
            }
        }
    } else {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'status' => false,
            'premium' => '0',
            'message' => 'Insurer not reachable'
        ];
    }
}
