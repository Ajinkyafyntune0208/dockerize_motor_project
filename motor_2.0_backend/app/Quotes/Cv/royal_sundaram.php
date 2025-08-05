<?php
    include_once app_path() . '/Helpers/CvWebServiceHelper.php';

    use App\Models\SelectedAddons;
    use Illuminate\Support\Facades\DB;
    use App\Models\MasterPolicy;
    use App\Models\UserProposal;

    function getQuote($enquiryId, $requestData, $productData)
    {
        // dd($productData);
        $premium_type_array = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->select('premium_type_code', 'premium_type')
        ->first();
        $isInspectionApplicable = 'N';
        if (($requestData->ownership_changed ?? '') == 'Y' && false) 
        {
            if (!in_array($premium_type_array->premium_type_code, ['third_party', 'third_party_breakin'])) 
            {
                $isInspectionApplicable = 'Y';
                $premium_type_id = null;
                if (in_array($productData->premium_type_id, [1, 4])) {
                    $premium_type_id = 4;
                } else if (in_array($productData->premium_type_id, [3, 6])) {
                    $premium_type_id = 6;
                }
                $MasterPolicy = MasterPolicy::where('product_sub_type_id', $productData->product_sub_type_id)
                ->where('insurance_company_id', 35)
                ->where('premium_type_id', $premium_type_id)
                    ->where('status', 'Active')
                    ->get()
                    ->first();
                if ($MasterPolicy == false) {
                    return [
                        'premium_amount'    => 0,
                        'status'            => false,
                        'message'           => 'Breakin Product is Required Enable For OwnershipChange Inspection',
                        'request' => [
                            'message'       => 'Breakin Product is Required Enable For OwnershipChange Inspection',
                            'requestData'   => $requestData
                        ]
                    ];
                }
            }
        }
        if (empty($requestData->rto_code)) {
            return [
                'status' => false,
                'premium' => '0',
                'message' => 'RTO not available',
                'request' => [
                    'message' => 'RTO not available',
                    'requestData' => $requestData
                ]
            ];
        }

        $rto_data = DB::table('royal_sundaram_rto_master AS rsrm')
            ->where('rsrm.rto_no', str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
            ->first();

        if (empty($rto_data)) {
            return [
                'status' => false,
                'premium' => '0',
                'message' => 'RTO not available',
                'request' => [
                    'message' => 'RTO not available',
                    'requestData' => $requestData
                ]
            ];
        }

        $mmv = get_mmv_details($productData, $requestData->version_id, 'royal_sundaram');
        
        if (isset($mmv['status']) && $mmv['status'] == 1) 
        {
            $mmv = $mmv['data'];
        } 
        else 
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'] ?? 'Something went wrong',
                'request' => [
                    'mmv' => $mmv
                ]
            ];
        }
        
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        
        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'message' => 'Vehicle Not Mapped',
                    'mmv' => $mmv
                ]
            ];
        } 
        elseif ($mmv->ic_version_code == 'DNE') 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'message' => 'Vehicle code does not exist with Insurance company',
                    'mmv' => $mmv
                ]
            ];
        }

        $mmv_data = [
            'manf_name'             => $mmv->make,
            'model_name'            => $mmv->model_name,
            'version_name'          => $mmv->fyntune_version['version_name'],
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => ((int) $mmv->seating_capacity) - 1,
            'cubic_capacity'        => $mmv->engine_capacity_amount,
            'fuel_type'             =>  $mmv->fuel_type,
            'gross_vehicle_weight'  => $mmv->vehicleweight,
            'vehicle_type'          => $mmv->vehicle_type,
            'version_id'            => $mmv->ic_version_code,
        ];
        
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $prev_policy_end_date = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);        
        $date2 = new DateTime($prev_policy_end_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        $vehicle_age_for_addons = (($age - 1) / 12);

        $motor_manf_date = '01-'.$requestData->manufacture_year;

        $ncb_levels = [
            '0' => '0',
            '20' => '1',
            '25' => '2',
            '35' => '3',
            '45' => '4',
            '50' => '5'
        ];
        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $is_breakin         = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);
        
        $policyType = 'Comprehensive';
        $isNewOrSecondHand  = "New";
        $isPreviousPolicyHolder = true;  
        $is_previous_claim = 'No';
        $claimsReported = '';
        if ($requestData->business_type == 'newbusiness') 
        {
            $businessType = 'New Business';
            $typeofCover  = "Comprehensive";
            $isPreviousPolicyHolder = false;
            $previous_insurer_name = '';
            $previous_policy_type = '';
            $previous_insurers_correct_address = '';
            $previous_policy_number = '';
            $policy_start_date = date('Y-m-d');
            $isNewOrSecondHand  = "New";
            
        } 
        else if($requestData->business_type == 'rollover') 
        {
            $isPreviousPolicyHolder = true;
            $businessType = 'Roll Over';
            $typeofCover  = "Comprehensive";
            $previous_insurer_name = !empty($requestData->previous_insurer) ? $requestData->previous_insurer : 'Bajaj';
            $previous_policy_type = ($requestData->previous_policy_type == 'Comprehensive' ? 'Comprehensive' : 'ThirdParty');
            $previous_insurers_correct_address = 'Mumbai';
            $previous_policy_number = '1234213';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
        } 
        else if ($requestData->business_type == 'breakin') 
        {
            $isPreviousPolicyHolder = true;
            $businessType = 'Break-In';
            $typeofCover  = "Comprehensive";
            $previous_insurer_name = !empty($requestData->previous_insurer) ? $requestData->previous_insurer : 'Bajaj';
            $previous_policy_type = (($requestData->previous_policy_type == 'Comprehensive' ) ? 'Comprehensive' : 'ThirdParty');
            $previous_insurers_correct_address = 'Mumbai';
            $previous_policy_number = '1234213';
            $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
        }

        if ($tp_only) 
        {
            $policyType = 'Third Party';
            $typeofCover  = "ThirdParty";
            $requestData->applicable_ncb = 0;
            $requestData->previous_ncb = 0;
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
        
            if ($requestData->business_type == 'breakin') {
                $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
            }
        } 

        if ($businessType != 'New Business' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) 
        {
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_difference > 0) 
            {
                $policy_start_date = date('m/d/Y 00:00:00',strtotime('+2 day'));
            }

            if($date_difference > 90)
            {
                $requestData->applicable_ncb = 0;
            }
        }

        if (in_array($requestData->previous_policy_type, ['Not sure'])) 
        {
            $policy_start_date = date('m/d/Y 00:00:00',strtotime('+2 day'));
            $requestData->previous_policy_expiry_date = date('Y-m-d', strtotime('-120 days'));
            $requestData->applicable_ncb = 0;
            $requestData->previous_ncb = 0;
            $previous_policy_type = '-';
            $previous_policy_number = '';
            $previous_insurer_name = '';            
        }
        
        if($requestData->is_claim == 'Y')
        {
            $is_previous_claim = 'Yes';
            $claimsReported = 1;
            $requestData->applicable_ncb = 0;
            //$requestData->previous_ncb = 0;
        }
        $previousinsurerAddress = '';        
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $opted_addons = [];
        $add_ons_opted_in_previous_policy = [];
        if ($premium_type != 'third_party') {
            if ($productData->zero_dep == '0') {
                $opted_addons[] = 'DepreciationWaiver';
            }
            $add_ons_opted_in_previous_policy = implode(',', $opted_addons);
        }

        $electrical_accessories = 'No';
        $electrical_accessories_value = '0';
        $non_electrical_accessories = 'No';
        $non_electrical_accessories_value = '0';
        $external_fuel_kit = 'No';
        $typeOfBiFuelKit = '';
        $external_fuel_kit_amount = 0;

        if (!empty($additional['accessories'])) 
        {
            foreach ($additional['accessories'] as $key => $data) 
            {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') 
                {
                    $external_fuel_kit = 'Yes';
                    $external_fuel_kit_amount = $data['sumInsured'];
                    $typeOfBiFuelKit = 'ADD ON';
                }

                if ($data['name'] == 'Non-Electrical Accessories') 
                {
                    $non_electrical_accessories = 'Yes';
                    $non_electrical_accessories_value = $data['sumInsured'];
                }

                if ($data['name'] == 'Electrical Accessories') 
                {
                    $electrical_accessories = 'Yes';
                    $electrical_accessories_value = $data['sumInsured'];
                }
            }
        }
        if (in_array($requestData->fuel_type, ['CNG', 'LPG', 'PETROL+CNG'])) {
            $external_fuel_kit = 'Yes';
            $typeOfBiFuelKit = 'InBuilt';
        }

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = 'No';
        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = $no_of_cleanerLL = $no_of_driverLL = $no_of_conductorLL = $total_no_of_coolie_cleaner = $no_ll_paid_driver  =  0;
        $cover_ll_paid_driver = 'NO';
        $geoExtension = 'No';

        if (!empty($additional['additional_covers'])) 
        {
            foreach ($additional['additional_covers'] as $key => $data) 
            {
                if($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']))
                {
                   $cover_pa_paid_driver = 'Yes';
                   $cover_pa_paid_driver_amt = $data['sumInsured'];
                }
    
                if($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']))
                {
                    $cover_pa_unnamed_passenger = 'Yes';
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'LL paid driver') 
                {
                    $cover_ll_paid_driver = 'YES';
                    $no_ll_paid_driver = 1;
                }

                if ($data['name'] == 'LL paid driver/conductor/cleaner' && isset($data['LLNumberCleaner']) && $data['LLNumberCleaner'] > 0) 
                {
                    $cover_ll_paid_driver = 'YES';
                    $no_of_cleanerLL = $data['LLNumberCleaner'];
                }

                if ($data['name'] == 'LL paid driver/conductor/cleaner' && isset($data['LLNumberConductor']) && $data['LLNumberConductor'] > 0) 
                {
                    $cover_ll_paid_driver = 'YES';
                    $no_of_conductorLL = $data['LLNumberConductor'];
                }
                if ($data['name'] == 'LL paid driver/conductor/cleaner' && isset($data['LLNumberDriver']) && $data['LLNumberDriver'] > 0) 
                {
                    $cover_ll_paid_driver = 'YES';
                    $no_ll_paid_driver = $data['LLNumberDriver'];
                }
    
                if ($data['name'] == 'PA paid driver/conductor/cleaner' && isset($data['sumInsured'])) 
                {
                    $cover_pa_paid_driver = 'Yes';
                    $cover_pa_paid_driver_amt = $data['sumInsured'];
                }
                if ($data['name'] == 'Geographical Extension') 
                {
                    foreach ($data['countries'] as $country) {
                        $geoExtension = 'Yes';
                    }
                }

            }
        }
        $is_voluntary_access = 'No';
        $voluntary_excess_amt = 0;
        $TPPDCover = 'No';
        $noOfPaiddriverOrCleaner = 0;
        //echo $no_of_driverLL;
        // $noOfPaiddriverOrCleaner = $no_of_driverLL;
        if($cover_ll_paid_driver == 'YES')
        {
           $noOfPaiddriverOrCleaner =  $no_of_cleanerLL + $no_of_driverLL;
        }
        
          $total_no_of_coolie_cleaner =   $no_of_cleanerLL + $no_of_conductorLL;
        if (!empty($additional['discounts'])) 
        {
            foreach ($additional['discounts'] as $key => $data) 
            {
                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) 
                {
                    $is_voluntary_access = 'Yes';
                    $voluntary_excess_amt = $data['sumInsured'];
                }
                if ($data['name'] == 'TPPD Cover') 
                {
                    $TPPDCover = 'Yes';
                }
            }
        }

        $is_PCV = get_parent_code($productData->product_sub_type_id) == 'PCV';
        $engine_no = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTVWXYZ"), 0, 21);
        $chassis_no = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTVWXYZ"), 0, 17);
        $userProposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        if (!empty($userProposal)) {
            $engine_no = !empty($userProposal->engine_number ?? null) ? $userProposal->engine_number : $engine_no;
            $chassis_no = !empty($userProposal->chassis_number ?? null) ? $userProposal->chassis_number : $chassis_no;
        }
            $premium_request = [
                "quoteId"       => "",
                "premium"       => 0.0,
                "isPosOpted"    => "No",
                'authenticationDetails' => [
                    "apikey" => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
                    "agentId" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
                    "partner" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR')
                ],
                'proposerDetails' => [
                    "title"                     => "Mr.",
                    "firstName"                 => "SURYA",
                    "emailId"                   => "website.support@royalsundaram.in",
                    "mobileNo"                  => "8072135743",
                    "dateOfBirth"               => "11/11/1987",
                    "occupation"                => "Business / Sales Profession",
                    "nomineeName"               => "PAWAN KUMAR",
                    "nomineeAge"                => "35",
                    "relationshipWithNominee"   => "Brother",
                    "relationshipwithGuardian"  => "0",
                    "contactAddress1"           => "AT BITHAN PO BITHAN",
                    "contactAddress2"           => "PS BITHAN DIST SAMASTIPUR",
                    "contactAddress3"           => "Address3",
                    "contactAddress4"           => "Address4",
                    "contactCity"               => $rto_data->city_name,
                    "contactPinCode"            => "848207",
                    "contactState"              => "Tamilnadu"
                ],
                'vehicleDetails' => [
                    "isCarOwnershipChanged"             => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
                    "rtoName"                           => $rto_data->rto_name,
                    "typeofCover"                       => $typeofCover,
                    "usageType"                         => "Commercial",
                    "yearOfManufacture"                 => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                    "vehicleRegistrationDate"           => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    "vehicleManufacturerName"           => $mmv->make,
                    "vehicleModelCode"                  => $mmv->ic_version_code,
                    //"idv"                               => "0",
                    "original_idv"                      => 0,
                    "modifiedIdvValue"                  => 0,
                    "modifyYourIdv"                     => 0,
                    "parkingDuringDay"                  => "Open Park",
                    "parkingDuringNight"                => "Road Side Parking",
                    'hdnDepreciation'                   => $productData->zero_dep == '1' ? 'false' : 'true',
                    'addOnsOptedInPreviousPolicy'       => $add_ons_opted_in_previous_policy,
                    "vehicleRegisteredInTheNameOf"      => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
                    "companyNameForCar"                 => $requestData->vehicle_owner_type == 'I' ? '' : 'ABC CORP',
                    "isBiFuelKit"                       => $external_fuel_kit,
                    "typeOfBiFuelKit"                   => $typeOfBiFuelKit,//"ADD ON",
                    "addonValue"                        => $external_fuel_kit_amount,
                    "cover_non_elec_acc"                => $non_electrical_accessories,
                    "cover_elec_acc"                    => $electrical_accessories,
                    "vehicleMostlyDrivenOn"             => 'City roads',
                    "isVehicleUsedFordrivingTuition"    => "No",
                    "isPreviousPolicyHolder"            => $isPreviousPolicyHolder,
                    "previousPolicyExpiryDate"          => $isPreviousPolicyHolder == true ? date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)) : '',
                    "previousPolicyType"                => $isPreviousPolicyHolder == true ? $previous_policy_type : '',
                    "previousPolicyNo"                  => $isPreviousPolicyHolder == true ? $previous_policy_number : '',
                    "previousInsurerName"               => $isPreviousPolicyHolder == true ? $previous_insurer_name : '',
                    "previousinsurerAddress"            => $previousinsurerAddress,
                    "claimsMadeInPreviousPolicy"        => $is_previous_claim,
                    "claimsReported"                    => $claimsReported,
                    //"claimAmountReceived"               => '',                    
                    "ncbcurrent"                        => $requestData->applicable_ncb,
                    "ncbprevious"                       => $requestData->previous_ncb,                    
                    "registrationNumber"                => $requestData->vehicle_registration_no != "" ? str_replace('-', '', $requestData->vehicle_registration_no) : str_replace('-', '', $requestData->rto_code.'N4662'),
                    "engineNumber"                      => $engine_no,
                    "chassisNumber"                     => $chassis_no,
                    "isFinanced"                        => "No",
                    "cover_dri_othr_car_ass"            => "No",
                    "accidentcoverforpaiddriver"        => $cover_pa_paid_driver_amt,
                    // "legalliabilitytopaiddriverorcleaner"        => $cover_ll_paid_driver,
                    "legalliabilitytopaiddriver"        => $cover_ll_paid_driver,
                    "legalliabilitytocoolies"           => $total_no_of_coolie_cleaner > 0 ? "Yes": "No",
                    "noOfDrivers"                       => $no_ll_paid_driver,
                    "noOfCoolies"                       => $total_no_of_coolie_cleaner,
                    "noOfPaiddriverOrCleaner"           => "0",
                    "tpriskOwndriver"                   => "Yes",
                    "tpriskOtherPaidDriver"             => "No",
                    "tpriskStatutoryTPPD"               => $TPPDCover,
                    "tpriskLluwEmployees"               => "0",
                    "tpriskAdditionalTPPD"              => "No",
                    "depreciationWaiver"                =>  ($productData->zero_dep == '0' ? 'on' : 'off'),
                    "nonElectricalAccesories" => [
                        "nonelectronicAccessoriesDetails" => [
                                [
                                    "nameOfElectronicAccessories" => "",
                                    "makeModel" => "MCJYzzJyZd",
                                    "value" => $non_electrical_accessories_value,
                                ]
                            ]
                        ],
                    "electricalAccessories" => [
                        "electronicAccessoriesDetails" => [
                                [
                                "nameOfElectronicAccessories" => "",
                                "makeModel" => "MCJYzzJyZd",
                                "value" => $electrical_accessories_value
                                ]
                            ]
                        ],
                    "tyreMudguard" => ($tp_only ? "No" : "Yes"),
                    "fibreglass"   =>  "No",
                    "depreciationWaiverCover"                       => ($productData->zero_dep == '0' ? 'Yes' : 'No'),
                    "dop_proposer"                                  => "11/11/1987",
                    "isNewOrSecondHand"                             => $isNewOrSecondHand,
                    "isGoodCondition"                               => "Yes",
                    "conditionDetails"                              => "good",
                    "isPSDPP_Purpose"                               => "Yes",
                    "isCarriageOfGoods"                             => "Yes",
                    "geoExtension"                                  => $geoExtension,
                    "ownerDOB"                                      => "11/11/1987",
                    "driverDOB"                                     => "11/11/1987",
                    "driverPhysicalInfirmity"                       => "No",
                    "isDriverAnyAccident"                           => "No",
                    "isRegistrationAddressSameAsContactAddress"     => "Yes",
                    "vehicleRegistrationAddress1"                   => "AT BITHAN PO BITHAN",
                    "vehicleRegistrationAddress2"                   => "PS BITHAN DIST SAMASTIPUR",
                    "vehicleRegistrationCity"                       => $rto_data->city_name,
                    "vehicleRegistrationPinCode"                    => "848207",
                    "vehicleRegistrationState"                      => "Bihar",
                    "noClaimBonusPercent"                           => $ncb_levels[$requestData->applicable_ncb] ?? '0',
                    'validPUCAvailable'                             => 'Yes',
                    'pucnumber'                                     => '',
                    'pucvalidUpto'                                  => '',
                    "isValidDrivingLicenseAvailable"                => 'Yes',
                    "drivingExperience"                             => '5 to 6 years',
                    'cpaCoverisRequired'                            => $requestData->vehicle_owner_type == 'I' ? 'Yes' : 'No',
                    'cpaPolicyTerm'                                 => 1,
                    'cpaCoverDetails' => [
                        'noEffectiveDrivingLicense' => '',
                        'cpaCoverWithInternalAgent' => '',
                        'standalonePAPolicy'        => '',
                        'companyName'               => '',
                        'expiryDate'                => '',
                        'policyNumber'              => ''
                    ]
                    //"enhancedPACoverForPaidDriver"                  => "500000"
                ],
            ];

            if($is_PCV){
                unset($premium_request['vehicleDetails']['depreciationWaiverCover']);
                unset($premium_request['vehicleDetails']['dop_proposer']);
                unset($premium_request['vehicleDetails']['driverDOB']);
                unset($premium_request['vehicleDetails']['driverPhysicalInfirmity']);
                unset($premium_request['vehicleDetails']['drivingExperience']);
                $premium_request['vehicleDetails']['tyreMudguard'] = '';
            }

            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            if (get_parent_code($productData->product_sub_type_id) == 'PCV')
            {
                $constant = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_PCV_PREMIUM';
            }
            elseif (get_parent_code($productData->product_sub_type_id) == 'GCV') 
            {
                $constant = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_GCV_PREMIUM';
            }
            if(isset($requestData->selected_gvw) && !empty($requestData->selected_gvw))
            {
                $premium_request['vehicleDetails']['additionalGVW'] = $requestData->selected_gvw;
            }
            
            $get_response = getWsData(config($constant), $premium_request, 'royal_sundaram', [
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'post',
                'productName'       => $productData->product_name. " ($businessType)",
                'company'           => 'royal_sundaram',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Premium Calculation',
                'transaction_type'  => 'quote',
            ]);
           
            $data = $get_response['response'];
            if ($data) 
            {
                $premium_response = json_decode($data, TRUE);
                $vehicle_idv = $default_idv = $min_idv = $max_idv = $orignal_idv = $PREMIUM= 0;
                $skip_second_call = false;
                if (isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002') 
                {
                    if (!$tp_only) 
                    {   
                        $original_idv = $premium_response['PREMIUMDETAILS']['DATA']['IDV'];
                        $min_idv = ceil($premium_response['PREMIUMDETAILS']['DATA']['MINIMUM_IDV']);
                        $max_idv = floor($premium_response['PREMIUMDETAILS']['DATA']['MAXIMUM_IDV']);                        

                        if ($requestData->is_idv_changed == 'Y') 
                        {
                            if ($requestData->edit_idv >= $max_idv) 
                            {
                                $premium_request_idv = $max_idv;
                            } 
                            elseif ($requestData->edit_idv <= $min_idv) 
                            {
                                $premium_request_idv = $min_idv;
                            } 
                            else 
                            {
                                $premium_request_idv = $requestData->edit_idv;
                            }
                        } 
                        else 
                        {
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
                        
                        $premium_request['vehicleDetails']['original_idv'] = $original_idv;
                        $premium_request['vehicleDetails']['modifiedIdvValue'] = $premium_request_idv;
                        $premium_request['quoteId'] = $premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'];
                        if(!$skip_second_call){
                        $get_response = getWsData(config($constant), $premium_request, 'royal_sundaram', [
                            'enquiryId'         => $enquiryId,
                            'requestMethod'     => 'post',
                            'productName'       => $productData->product_name. " ($businessType)",
                            'company'           => 'royal_sundaram',
                            'section'           => $productData->product_sub_type_code,
                            'method'            => 'Premium Re Calculation',
                            'transaction_type'  => 'quote'
                        ]);
                       }
                        $data = $get_response['response'];
                        if (!empty($data)) 
                        {
                            $premium_response = json_decode($data, TRUE);
                            
                            if (isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] != 'S-0002') 
                            {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'premium' => 0,
                                    'message' => $premium_response['PREMIUMDETAILS']['Status']['Message'] ?? 'Insurer not reachable'
                                ];
                            }
                        } 
                        else 
                        {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => 'Insurer not reachable'
                            ];
                        }
                    }

                    $city_name = DB::table('master_city AS mc')
                        ->where('mc.city_name', $rto_data->city_name)
                        ->select('mc.zone_id')
                        ->first();
                    
                    $default_idv = round($premium_response['PREMIUMDETAILS']['DATA']['IDV']);
                    $vehicle_idv = round($premium_response['PREMIUMDETAILS']['DATA']['IDV']);
                    $orignal_idv = round($premium_response['PREMIUMDETAILS']['DATA']['IDV']);
                    $llpaiddriver_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS']);
                    $llpaidcleaner_conductor_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['LLDriverConductorCleaner']);
                    $cover_pa_owner_driver_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER']);
                    $cover_pa_paid_driver_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER']);
                    $cover_pa_unnamed_passenger_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS']);
                    $voluntary_excess = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE']);
                    $anti_theft = 0;
                    $llPassengers = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['LEGALLIABILITY_TO_PASSENGERS']);
                    $ic_vehicle_discount = 0;
                    // $ncb_discount =($tp_only ? 0 :round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS']));
                    $electrical_accessories_amt = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES']);

                    $non_electrical_accessories_amt = $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES']; 
                    $od = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES']) + round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW'] ?? 0);
                    $tppd_discount = $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TPPDStatutoryDiscount'] ?? 0;
                    $tppd = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD'] + $tppd_discount + $llPassengers);
                    $geog_Extension_OD_Premium = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['OD_GEO_EXTENSION']);
                    $geog_Extension_TP_Premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TP_GEO_EXTENSION']);
                    $cng_lpg = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT']);
                    $cng_lpg_tp = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG']);
                    $auto_acc_discount = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['AUTOMOBILE_ASSOCIATION_DISCOUNT']);
                    // if(!$is_PCV){
                    //     $tppd = $tppd ;
                    // }
                    // if($is_PCV){
                    //     $ncb_discount = ($tp_only) ? 0 : round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS']);
                    // }
                    $ncb_discount = ($tp_only) ? 0 : round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS']);

                    $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt + $geog_Extension_OD_Premium; //non electrical are inbulid
                    $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $llpaidcleaner_conductor_premium + $geog_Extension_TP_Premium;
                    $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount; // $tppd_discount
                     
                    $ncb_discount = ($final_od_premium  - $final_total_discount)*$requestData->applicable_ncb/100; 
                    $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount + $tppd_discount;
                    $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
                    $final_gst_amount = round($final_net_premium * 0.18);
                    // $final_payable_amount  = $final_net_premium + $final_gst_amount;
                    $final_payable_amount  =  $final_net_premium + $final_gst_amount;
                    $applicable_addons = [];
                    $addons_data = [
                        'in_built' => [],
                        'additional' => [],
                        'other_premium' => 0
                    ];

                    if (!$tp_only) {

                        if ($productData->zero_dep == 1) 
                        {
                            $addons_data = [
                                'in_built'   => [],
                                'additional' => [
                                    'zero_depreciation'     => 0,
                                    'consumables'           => 0,
                                    'road_side_assistance'  => 0,
                                    'imt23'                 => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYREMUDGUARD'])

                                ],
                                'other' =>  []
                            ];

                            if($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYREMUDGUARD'] > 0){
                                $applicable_addons[] = 'imt23';
                            }

                        } 
                        else 
                        {
                            $addons_data = [
                                'in_built'   => [
                                    'zero_depreciation' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'] ?? 0 ),
                                ],
                                'additional' => [
                                    'imt23' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYREMUDGUARD'])
                                ],
                                'other'=>[]
                            ];
                            if(empty($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'] ?? 0)){
                                return [
                                    'status' => false,
                                    'message' => 'Zero dep is not available..!',
                                    'request' => [
                                        'message' => 'Zero dep is not available..!',
                                        'vehicle_age' => $vehicle_age,
                                        'request' => $premium_request,
                                        'response' => $premium_response,
                                    ]
                                ];
                            }
                            // if($llPassengers > 0){
                            //     $addons_data['other']['legal_liability_passenger'] = $llPassengers;
                            // }
                            if($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYREMUDGUARD'] > 0){
                                $applicable_addons[] = 'imt23';
                            }
                            if($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'] > 0){
                                $applicable_addons[] = 'zeroDepreciation';
                            }
                        }
                        
                        $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                        $addons_data['other_premium'] = 0;
                    }

                    // if(!empty($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW']) && $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW'] != '0')
                    // {
                    //     $addons_data['other']['additional_gvw'] = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW']);
                    // }

                    //INSPECTION WAIVER OF 30 DAYS
                    $isInspectionWaivedOff = false;
                    $waiverExpiry = null;
                    $ribbonMessage = null;
                    
                    if (
                        $is_breakin &&
                        !empty($requestData->previous_policy_expiry_date) &&
                        strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
                        config('ROYAL_SUNDARAM_INSPECTION_WAIVED_OFF_CV') == 'Y' 
                    ) {
                            $isInspectionWaivedOff = true;
                            $ribbonMessage = (config("INSPECTION_RIBBON_TEXT", 'No Inspection Required'));  
                            $waiverExpiry = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date .' +90 days'));
                    }
                    
                    $final_response = [
                        'status' => true,
                        'msg' => 'Found',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'Data' => [
                            'quote_id'=>$premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                            'premium'=>$PREMIUM,
                            'idv' => ($tp_only) ? 0 : $vehicle_idv,
                            'min_idv' => ($tp_only) ? 0 : round($min_idv),
                            'max_idv' => ($tp_only) ? 0 : round($max_idv),
                            'default_idv' => ($tp_only) ? 0 : $default_idv,
                            'modified_idv'=> ($tp_only) ? 0 : $vehicle_idv,
                            'original_idv'=> ($tp_only) ? 0 : $orignal_idv,
                            'vehicle_idv' => ($tp_only) ? 0 : $vehicle_idv,
                            'pp_enddate' => $requestData->previous_policy_expiry_date,
                            'policy_type' => $policyType,
                            'vehicle_registration_no' => $requestData->rto_code,
                            'voluntary_excess' => $voluntary_excess,
                            'version_id' => $mmv->ic_version_code,
                            'selected_addon' => [],
                            'showroom_price' => $vehicle_idv,
                            'fuel_type' => $mmv->fuel_type,
                            'ncb_discount' => $requestData->applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                            'product_name' => $productData->product_sub_type_name,
                            'mmv_detail' => $mmv_data,
                            'vehicle_register_date' => $requestData->vehicle_register_date,
                            'master_policy_id' => [
                                'policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'insurance_company_id' => $productData->company_id,                               
                                'company_name' => $productData->company_name,
                                'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                                'product_sub_type_name' => $productData->product_sub_type_name,
                                'flat_discount' => $productData->default_discount,
                                'is_premium_online' => $productData->is_premium_online,
                                'is_proposal_online' => $productData->is_proposal_online,
                                'is_payment_online' => $productData->is_payment_online
                            ],
                            'motor_manf_date' => $motor_manf_date,
                            'vehicleDiscountValues' => [
                                'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'car_age' => $vehicle_age,
                                'ic_vehicle_discount' => $ic_vehicle_discount,
                            ],
                            'ic_vehicle_discount' => $ic_vehicle_discount,
                            'basic_premium' => $od,
                            'deduction_of_ncb' => $ncb_discount,
                            'tppd_premium_amount' => $tppd,
                            'tppd_discount' => $tppd_discount,
                            'motor_electric_accessories_value' => $electrical_accessories_amt,
                            'motor_non_electric_accessories_value' => $non_electrical_accessories_amt,
                            /* 'motor_lpg_cng_kit_value' => $cng_lpg, */
                            'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                            'legal_liability_passenger' => 0,//$llPassengers,
                            'seating_capacity' => $mmv->seating_capacity,
                            'default_paid_driver' => $llpaiddriver_premium,
                            'll_paid_driver_premium' => $llpaiddriver_premium,
                            'll_paid_conductor_premium' => $llpaidcleaner_conductor_premium,
                            'll_paid_cleaner_premium' => 0,
                            'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                            'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                            'total_accessories_amount(net_od_premium)' => 0,
                            'total_own_damage' => $final_od_premium,
                            /* 'cng_lpg_tp' => $cng_lpg_tp, */
                            'total_liability_premium' => $final_tp_premium,
                            'net_premium' => $final_net_premium,
                            'service_tax_amount' => $final_gst_amount,
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                            'premium_amount'  => $final_payable_amount,
                            'antitheft_discount' => $anti_theft,
                            'final_od_premium' => $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' => round($final_total_discount),
                            'final_net_premium' => $final_net_premium,
                            'final_gst_amount' => $final_gst_amount,
                            'final_payable_amount' => $final_payable_amount,
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'business_type' => $businessType,
                            
                            'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                            'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                            'ic_of' => $productData->company_id,
                            'vehicle_discount_detail' => [
                                'discount_id' => NULL,
                                'discount_rate' => NULL
                            ],
                            'policy_id' => $productData->policy_id,
                            'insurane_company_id' => $productData->company_id,
                            'add_ons_data' => $addons_data,
                            'applicable_addons' => $applicable_addons,
                            'isInspectionApplicable' => $isInspectionApplicable,
                            'ribbon' => $ribbonMessage,
                        ]
                    ];

                    if($isInspectionWaivedOff) {
                        $data_response['Data']['isPremiumWaivedOff'] = true;
                        $data_response['Data']['waiverExpiry'] = $waiverExpiry;
                    }

                    if($external_fuel_kit == 'Yes')
                    {
                        $final_response['Data']['motor_lpg_cng_kit_value'] = $cng_lpg;
                        $final_response['Data']['cng_lpg_tp'] = $cng_lpg_tp;
                    }
                    return camelCase($final_response);
                } 
                else 
                {
                    if (isset($premium_response['PREMIUMDETAILS']['Status'])) 
                    {
                        return [
                            'status' => false,
                            'premium' => 0,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $premium_response['PREMIUMDETAILS']['Status']['Message']
                        ];
                    } 
                    else 
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium' => 0,
                            'message' => 'Insurer not reachable'
                        ];
                    }
                }
            } 
            else 
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium' => '0',
                    'message' => 'Insurer not reachable'
                ];
            }
    }
