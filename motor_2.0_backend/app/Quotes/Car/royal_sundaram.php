<?php
    include_once app_path() . '/Helpers/CarWebServiceHelper.php';

    use App\Models\SelectedAddons;
    use Illuminate\Support\Facades\DB;

    function getQuote($enquiryId, $requestData, $productData)
    {

    if (config('IC.ROYAL_SUNDARAM.V1.CAR.ENABLE') == 'Y') {
        include app_path() . '/Quotes/Car/V1/royal_sundaram.php';
        return getQuoteV1($enquiryId, $requestData, $productData);
    }

    $refer_webservice = $productData->db_config['quote_db_cache'];

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
                'request' => [
                    'message' => 'RTO not available',
                    'requestData' => $requestData
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
                'request' => [
                    'mmv' => $mmv
                ]
            ];
        }

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'message' => 'Vehicle Not Mapped',
                    'mmv' => $mmv
                ]
            ];
        } elseif ($mmv->ic_version_code == 'DNE') {
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
            'manf_name' => $mmv->make,
            'model_name' => $mmv->model_name ?? '',
            'version_name' => '', //$mmv->model_name
            'seating_capacity' => $mmv->seating_capacity ?? $mmv->min_seating_capacity,
            'carrying_capacity' => ((int) $mmv->seating_capacity ?? $mmv->min_seating_capacity) - 1,
            'cubic_capacity' => $mmv->engine_capacity_amount,
            'fuel_type' =>  $mmv->fuel_type,
            'gross_vehicle_weight' => $mmv->vehicle_weight,
            'vehicle_type' => 'CAR',
            'version_id' => $mmv->ic_version_code,
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
        $vehicle_age = $age / 12;//floor($age / 12);
        $vehicle_age_for_addons = $vehicle_age;//(($age - 1) / 12);

        $motor_manf_date = '01-'.$requestData->manufacture_year;

        $ncb_levels = [
            '0' => '0',
            '20' => '1',
            '25' => '2',
            '35' => '3',
            '45' => '4',
            '50' => '5'
        ];

        $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    //     if (($interval->y >= 25) && ($tp_check == 'true')) {
    //         return [
    //             'premium_amount' => 0,
    //             'status' => false,
    //             'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 25 years',
    //         ];
    // }
        if ($vehicle_age > 10 && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('CAR_AGE_VALIDASTRION_ALLOWED_IC')))) {
            return [
                'status' => false,
                'premium' => '0',
                'message' => 'Zero dep is not allowed for vehicle age greater than 10 years',
                'request' => [
                    'message' => 'Zero dep is not allowed for vehicle age greater than 10 years',
                    'vehicle_age' => $vehicle_age
                ]
            ];
        }

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $add_ons_opted_in_previous_policy = '';
        $hdn_ncb_protector = 'false';
        $hdn_key_replacement = 'false';
        $hdn_protector = 'false';
        $hdn_invoice_price = 'false';
        $hdn_loss_of_baggage = 'false';
        $hdn_tyre_cover = 'false';
        $hdn_wind_shield = 'false';
        $opted_addons = [];
        $policyType = 'Comprehensive';
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
            $product_name = 'BrandNewCar';
            $businessType = 'New Business';
            $type_of_cover = 'Bundled';
            $previous_insurer_name = '';
            $previous_policy_type = '';
            $previous_insurers_correct_address = '';
            $previous_policy_number = '';
            $is_previous_claim = 'No';
            //$cpa_tenure = '3'; //By deafult CPA will be 1 year
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '3';
            $policy_start_date = date('Y-m-d');
        } else if($requestData->business_type == 'rollover') {
            $product_name = 'RolloverCar';
            $businessType = 'Roll Over';
            $type_of_cover = 'Comprehensive';
            $previous_insurer_name = !empty($requestData->previous_insurer) ? $requestData->previous_insurer : 'Bajaj';
            $previous_policy_type = (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive' : 'ThirdParty');
            $previous_insurers_correct_address = 'Mumbai';
            $previous_policy_number = '1234213';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
            $is_previous_claim = $requestData->is_claim == 'Y' ? 'Yes' : 'No';

            if ($requestData->is_claim == 'N' && $requestData->applicable_ncb >= 20 && $premium_type != 'third_party') {
                $hdn_ncb_protector = 'true';
            }
        } else if ($requestData->business_type == 'breakin') {
            $product_name = 'BreakinCar';
            $businessType = 'Break-In';
            $type_of_cover = '';
            $previous_insurer_name = !empty($requestData->previous_insurer) ? $requestData->previous_insurer : 'Bajaj';
            $previous_policy_type = (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive' : 'ThirdParty');
            $previous_insurers_correct_address = 'Mumbai';
            $previous_policy_number = '';
            $policy_start_date = date('Y-m-d', strtotime('+3 day', strtotime(date('Y-m-d'))));
            $is_previous_claim = $requestData->is_claim == 'Y' ? 'Yes' : 'No';
            if ($requestData->is_claim == 'N' && $requestData->applicable_ncb >= 20 && $premium_type != 'third_party') {
                $hdn_ncb_protector = 'true';
            }
        }

        if ($tp_only) {
            $policyType = 'Third Party';
            $type_of_cover = 'LiabilityOnly';
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
        } elseif ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin'){
            $policyType = 'Own Damage';
            $type_of_cover = 'standalone';
        }

        if($requestData->previous_policy_type == "ThirdParty"){
            $type_of_cover = 'LiabilityOnly';
        }
        if ($businessType != 'New Business' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_difference > 0) {
                $policy_start_date = date('m/d/Y 00:00:00',strtotime('+3 day'));
            }

            if($date_difference > 90){
                $requestData->applicable_ncb = 0;
            }
        }

        if (in_array($requestData->previous_policy_type, ['Not sure'])) {
            $previous_insurer_name = "";
            $previous_policy_type = "";
            $previous_insurers_correct_address = "";
            $previous_policy_number = "";
            $policy_start_date = date('m/d/Y 00:00:00',strtotime('+3 day'));
            $requestData->previous_policy_expiry_date = date('Y-m-d', strtotime('-120 days'));
            $requestData->applicable_ncb = 0;
        }

        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $requestData->applicable_ncb = $is_previous_claim == 'Yes' ? 0 : $requestData->applicable_ncb;

        if ($premium_type != 'third_party') {
            if ($vehicle_age < 10) {
                if ($productData->zero_dep == '0') {
                    $opted_addons[] = 'DepreciationWaiver';
                }

                $opted_addons[] = 'AggravationCover';
               /*  $opted_addons[] = 'KeyReplacement';//7
                $opted_addons[] = 'LossOfBaggage';//7 */
                $hdn_protector = 'true';
               /*  $hdn_key_replacement = 'true';
                $hdn_loss_of_baggage = 'true'; */
            }

            if ($vehicle_age_for_addons < 7) {
                $opted_addons[] = 'TyreCoverClause';
                $opted_addons[] = 'KeyReplacement';//7
                $opted_addons[] = 'LossOfBaggage';//7
                $hdn_tyre_cover = 'true';
                $hdn_key_replacement = 'true';
                $hdn_loss_of_baggage = 'true';
            }
            if ($vehicle_age_for_addons < 4 && $requestData->ownership_changed != 'Y') {
                $opted_addons[] = 'InvoicePrice';
                $hdn_invoice_price = 'true';
            }

            if ($interval->y < 7 || $requestData->business_type == 'newbusiness') {
                $hdn_wind_shield = 'true';
                $opted_addons[] = 'WindShield';
            }

            $opted_addons[] = 'NCBProtector';
            $opted_addons[] = 'consumabeCover';
            $add_ons_opted_in_previous_policy = implode(',', $opted_addons);
        }

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $electrical_accessories = 'No';
        $electrical_accessories_value = '0';
        $non_electrical_accessories = 'No';
        $non_electrical_accessories_value = '0';
        $external_fuel_kit = 'No';
        $external_fuel_kit_amount = '';

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
        // for inbuilt cng and lpg 
        if($requestData->fuel_type == 'CNG' || $requestData->fuel_type == 'LPG')
        {
            $external_fuel_kit = 'Yes';
            $external_fuel_kit_amount = 0;
        }

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

        $is_voluntary_access = 'No';
        $voluntary_excess_amt = 0;
        $TPPDCover = '';
        $max_idv = 0;
        $min_idv = 0;
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
        if ($requestData->is_idv_changed == 'Y') {
            $premium_request_idv = $requestData->edit_idv;
        } else {
            #$premium_request_idv = $min_idv;
            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $premium_request_idv = 0;
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
        $engine_no = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTVWXYZ"), 0, 21);
        $chassis_no = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTVWXYZ"), 0, 17);
    //rsa
    $default_rsa = "Yes";
    $rsa_plan_2 = "No";
   
    if ($productData->product_identifier == 'roadSideAssistancePlan2') {
        $rsa_plan_2 = 'Yes';
        $default_rsa = "No";
    }

    if ($requestData->fuel_type == 'PETROL' ) {
             
        $fuelType = $external_fuel_kit == "Yes" ? 'ADD ON' : '';
    } else {
        $fuelType = 'InBuilt';
    }
        if ($external_fuel_kit_amount < 100000 && $electrical_accessories_value < 100000 && $non_electrical_accessories_value < 100000) {
            $premium_request = [
                'authenticationDetails' => [
                    'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_MOTOR'),
                    'apikey'  => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_MOTOR'),
                ],
                'isNewUser' => 'Yes',
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
                    'contactCity' => 'Vashi',
                    'contactPincode' => '',
                    'dateOfBirth' => '18/09/1995',
                    'guardianAge' => '',
                    'guardianName' => '',
                    'nomineeAge' => '18',
                    'nomineeName' => 'subodh',
                    'occupation' => 'Central / State Government Employee',
                    'regCity' => 'Raigarh',
                    'regPinCode' => '410206',
                    'relationshipWithNominee' => 'Others',
                    'relationshipwithGuardian' => '',
                    'same_addr_reg' => '',
                    'strEmail' => 'abc@gmail.com',
                    'strFirstName' => 'abc',
                    'strLastName' => '',
                    'strMobileNo' => '8548565854',
                    'strPhoneNo' => '',
                    'strStdCode' => '',
                    'strTitle' => 'Mr',
                    'userName' => '',
                ],
                'reqType' => 'XML',
                'respType' => 'XML',
                'vehicleDetails' => [
                    'accidentcoverforpaiddriver' => $cover_pa_paid_driver == 'Yes' ? $cover_pa_paid_driver_amt : '0',
                    'addonValue' => $requestData->fuel_type == 'CNG' ? '' : $external_fuel_kit_amount,
                    'automobileAssociationMembership' => 'No',
                    'averageMonthlyMileageRun' => '',
                    'carRegisteredCity' => $rto_data->city_name,
                    'rtoName' => $rto_data->rto_name,
                    'addOnsOptedInPreviousPolicy' => $add_ons_opted_in_previous_policy,
                    'validPUCAvailable' => 'Yes',
                    'pucnumber' => '',
                    'pucvalidUpto' => '',
                    'chassisNumber' => $chassis_no,#'DFG345SDFGHTR3456',
                    'claimAmountReceived' => ($is_previous_claim == 'Yes') ? '50000' : '0',
                    'claimsMadeInPreviousPolicy' => $is_previous_claim,
                    'claimsReported' => ($is_previous_claim == 'Yes') ? '3' : '0',
                    'companyNameForCar' => $requestData->vehicle_owner_type == 'I' ? '' : 'ABC CORP',
                    'cover_dri_othr_car_ass' => 'Yes',
                    'cover_elec_acc' => $electrical_accessories,
                    'cover_non_elec_acc' => $non_electrical_accessories,
                    'depreciationWaiver' => 'off',
                    'drivingExperience' => '1',
                    'isValidDrivingLicenseAvailable' => 'Yes',
                    'electricalAccessories' => [
                        'electronicAccessoriesDetails' => [
                            'makeModel' => '',
                            'nameOfElectronicAccessories' => '',
                            'value' => $electrical_accessories_value,
                        ],
                    ],
                    'engineCapacityAmount' => $mmv->engine_capacity_amount . ' CC',
                    'engineNumber' => $engine_no,#'43534534534SFG3423432',
                    'engineprotector' => 'off',
                    'fibreGlass' => 'No',
                    'financierName' => '',
                    'fuelType' => $mmv->fuel_type,
                    'hdnDepreciation' => $productData->zero_dep == '1' ? 'false' : 'true',
                    "hdnVehicleReplacementCover"=>$hdn_invoice_price,
                    "vehicleReplacementCover"=> $hdn_invoice_price == 'true' ? "Yes":"No",
                    "fullInvoicePrice"=>"No",
                    "fullInvoicePriceRoadtax"=>"No",
                    "fullInvoicePriceRegCharges"=>"No",
                    "fullInvoicePriceInsuranceCost"=>"Yes",
                    // 'hdnInvoicePrice' => $hdn_invoice_price,
                    'hdnKeyReplacement' => $hdn_key_replacement,
                    'hdnLossOfBaggage' => $hdn_loss_of_baggage,
                    'lossOfBaggage'=> $hdn_loss_of_baggage == true ? 'on' : 'off',
                    'hdnNCBProtector' => $hdn_ncb_protector,
                    'hdnProtector' => $hdn_protector,
                    'hdnRoadTax' => 'false',
                    'hdnSpareCar' => 'false',
                    'hdnWindShield' => $hdn_wind_shield,
                    'hdnTyreCover' => $hdn_tyre_cover,
                    'hdnRoadSideAssistanceCover' => 'true',// As confirmed from IC RSA will be provided to all ages
                    'roadSideAssistancePlan1' => $rsa_plan_2,
                    'roadSideAssistancePlan2' => $default_rsa,
                    // 'idv' => 0,
                    'invoicePrice' => 'off',
                    'isBiFuelKit' => $external_fuel_kit,
                    'isBiFuelKitYes' => $fuelType,
                    'isCarFinanced' => 'No',
                    'isCarFinancedValue' => '',
                    'isCarOwnershipChanged' => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
                    'ownerSerialNumber' => $requestData->ownership_changed == 'Y' ? '2' : '1',
                    'isPreviousPolicyHolder' => $requestData->business_type == 'newbusiness' ? 'false' : 'true',
                    'keyreplacement' => 'on',
                    'legalliabilitytopaiddriver' => $cover_ll_paid_driver,
                    'modified_idv_value' => '',
                    'modify_your_idv' => '',
                    'ncbcurrent' => $requestData->applicable_ncb,
                    'ncbprevious' => $requestData->previous_ncb,
                    'ncbprotector' => 'off',
                    'noClaimBonusPercent' => $ncb_levels[$requestData->applicable_ncb] ?? '0',
                    'nonElectricalAccesories' => [
                        'nonelectronicAccessoriesDetails' => [
                            'makeModel' => '',
                            'nameOfElectronicAccessories' => '',
                            'value' => $non_electrical_accessories_value,
                        ],
                    ],
                    'original_idv' => 0,
                    'personalaccidentcoverforunnamedpassengers' => $cover_pa_unnamed_passenger == 'Yes' ? $cover_pa_unnamed_passenger_amt : '0',
                    'tppdLimit' => $TPPDCover,
                    'policyED' =>date('d/m/Y', strtotime($policy_end_date)),
                    'policySD' => date('d/m/Y', strtotime($policy_start_date)),
                    'previousInsurerName' => $previous_insurer_name,
                    'previousPolicyExpiryDate' => (in_array($requestData->previous_policy_type, ['Not sure'])) ? "" : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
                    'previousPolicyType' => $previous_policy_type,
                    'previousinsurersCorrectAddress' => $previous_insurers_correct_address,
                    'previuosPolicyNumber' => $previous_policy_number,
                    'ProductName' => $product_name,
                    'region' => '',
                    'registrationNumber' => $requestData->vehicle_registration_no != "" ? ($requestData->vehicle_registration_no == "NEW" ? '' : str_replace('-', '', $requestData->vehicle_registration_no)) : str_replace('-', '', $requestData->rto_code.'N4662'),
                    'registrationchargesRoadtax' => 'off',
                    'spareCar' => 'off',
                    'spareCarLimit' => '0',
                    // 'totalIdv' => 0,
                    'valueOfLossOfBaggage' => '2500',
                    'valueofelectricalaccessories' => $electrical_accessories_value,
                    'valueofnonelectricalaccessories' => $non_electrical_accessories_value,
                    'vehicleManufacturerName' => $mmv->make,
                    'vehicleModelCode' => $mmv->model_code,
                    'vehicleMostlyDrivenOn' => 'City roads',
                    'vehicleRegisteredInTheNameOf' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
                    'vehicleSubLine' => 'privatePassengerCar',
                    'vehicleregDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'voluntarydeductible' => $is_voluntary_access == 'Yes' ? $voluntary_excess_amt : '0',
                    'windShieldGlass' => 'off',
                    'yearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                    'typeOfCover' => $type_of_cover,
                    'policyTerm' => '1',
                    'cpaCoverisRequired' => $requestData->vehicle_owner_type == 'I' ? 'Yes' : 'No',
                    'cpaPolicyTerm' =>  $cpa_tenure ,
                    'cpaCoverDetails' => [
                        'noEffectiveDrivingLicense' => '',
                        'cpaCoverWithInternalAgent' => '',
                        'standalonePAPolicy'        => '',
                        'companyName' => '',
                        'expiryDate' => '',
                        'policyNumber' => ''
                    ],
                    "consumableCover" => 'on',
                    "geoExtension" => 'No'
                ],
            ];

            if(!in_array($premium_type, ['third_party', 'third_party_breakin']))
            {
                $premium_request['vehicleDetails']['roadSideAssistanceCover'] = 'on';
            }

            if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                $premium_request['existingTPPolicyDetails'] = [
                    'tpPolicyNumber' => 'RTGRT4523fTRF',
                    'tpInsurer' => 'National Insurance Co. Ltd.',
                    'tpInceptionDate' => date('d/m/Y', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date))),
                    'tpExpiryDate' => date('d/m/Y', strtotime('+3 year +1 day', strtotime($requestData->previous_policy_expiry_date))),
                    'tpPolicyTerm' => '3',
                ];
            }

            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $pos_data = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('seller_type','P')
                ->first();

           
            /*if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
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

            $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
            

            if($is_renewbuy || config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){

                $premium_request['isPosOpted'] = '';
                $premium_request['posCode'] = '';
                $premium_request['posDetails'] = [
                    'name' => '',
                    'pan' => '',
                    'aadhaar' => '',
                    'mobile' => '',
                    'licenceExpiryDate' => '',
        ];
    }


            $data = $premium_request;
            unset($data['vehicleDetails']['engineNumber'],$data['vehicleDetails']['chassisNumber']);
            $data['productName']  = $productData->product_name. " ($businessType)";
            $checksum_data = checksum_encrypt($data);
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'royal_sundaram',$checksum_data,'CAR');
            if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
            {
                $data = $is_data_exits_for_checksum;
            }
            else
            {
                $data = getWsData(config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_MOTOR_PREMIUM'), $premium_request, 'royal_sundaram', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name. " ($businessType)",
                    'company'  => 'royal_sundaram',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Premium Calculation',
                    'transaction_type' => 'quote',
                    'checksum' => $checksum_data,
                    'root_tag' => 'CALCULATEPREMIUMREQUEST',
                    'headers' => [
                            'Content-Type'=>'application/xml'
                    ]
                ]);
            }

            if($data['response'] == 'Access denied')
            {
                return [
                    'premium_amount'    => 0,
                    'status'            => false,
                    'message'           => 'Access denied'  
                ];
            }

            if ($data['response']) {
                $premium_response = json_decode($data['response'], TRUE);
                //  print_r(json_encode([$premium_response]));die;
                $vehicle_idv = $default_idv = $min_idv = $max_idv =$orignal_idv=$PREMIUM= 0;
                $skip_second_call = false;
                if (isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002') {
                    if (!$tp_only) {
                        $vehicle_idv = $premium_response['PREMIUMDETAILS']['DATA']['IDV'];
                        $orignal_idv = $premium_response['PREMIUMDETAILS']['DATA']['IDV'];
                        $min_idv =$premium_response['PREMIUMDETAILS']['DATA']['MINIMUM_IDV'];
                        $max_idv = $premium_response['PREMIUMDETAILS']['DATA']['MAXIMUM_IDV'];
                        $default_idv = $premium_response['PREMIUMDETAILS']['DATA']['IDV'];

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
                                    $skip_second_call = false;
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

                        $premium_request['vehicleDetails']['original_idv'] = $default_idv;

                        /* $premium_request['vehicleDetails']['modify_your_idv'] = round(100 - (round($premium_request_idv) * 100) / $default_idv); */
                        $premium_request['vehicleDetails']['discountIdvPercent'] = ((($premium_request_idv) * 100) / $default_idv) - 100;
                        $premium_request['vehicleDetails']['modified_idv_value'] = $premium_request_idv;
                        //$premium_request['vehicleDetails']['modify_your_idv'] = $premium_request_idv;

                        if ($requestData->is_idv_changed == 'Y') {
                            $premium_request['vehicleDetails']['modify_your_idv'] = '';
                        }

                        if (config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $premium_request_idv >= 5000000)
                        {
                            $premium_request['isPosOpted'] = '';
                            $premium_request['posCode'] = '';
                           
                        }elseif($is_renewbuy){
                            $premium_request['isPosOpted'] = '';
                            $premium_request['posCode'] = '';
                        } 
                        // elseif(!empty($pos_data))
                        // {
                        //   $premium_request['isPosOpted'] = 'Yes';
                        //   $premium_request['posCode'] =  $pos_data->pan_no ;
                        // }
                        update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], 'success', 'success');
                        $premium_request['quoteId'] = $premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'];
                        if(!$skip_second_call){

                            $data = $premium_request;
                            unset($data['vehicleDetails']['engineNumber'],$data['vehicleDetails']['chassisNumber'], $data['quoteId']);
                            $data['productName']  = $productData->product_name. " ($businessType)";
                            $checksum_data = checksum_encrypt($data);
                            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'royal_sundaram',$checksum_data,'CAR');
                            if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                            {
                                $data = $is_data_exits_for_checksum;
                            }
                            else
                            {    
                                $data = getWsData(config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_MOTOR_PREMIUM'), $premium_request, 'royal_sundaram', [
                                    'enquiryId' => $enquiryId,
                                    'requestMethod' =>'post',
                                    'productName'  => $productData->product_name. " ($businessType)",
                                    'company'  => 'royal_sundaram',
                                    'section' => $productData->product_sub_type_code,
                                    'method' =>'Premium Recalculation',
                                    'transaction_type' => 'quote',
                                    'checksum' => $checksum_data,
                                    'root_tag' => 'CALCULATEPREMIUMREQUEST',
                                    'headers' => [
                                            'Content-Type'=>'application/xml'
                                    ]
                                ]);
                           }
                    }

                        if (!empty($data['response'])) {
                            $premium_response = json_decode($data['response'], TRUE);

                            // if (isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002') {
                            if(isset($premium_response['PREMIUMDETAILS']) && isset($premium_response['PREMIUMDETAILS']['DATA'])) {
                                $vehicle_idv = $premium_response['PREMIUMDETAILS']['DATA']['IDV'];
                                $default_idv = $premium_response['PREMIUMDETAILS']['DATA']['IDV'];
                                $PREMIUM = $premium_response['PREMIUMDETAILS']['DATA']['PREMIUM'];
                            } else {
                                return [
                                    'status' => false,
                                    'premium' => 0,
                                    'message' => $premium_response['PREMIUMDETAILS']['Status']['Message'] ??'Insurer not reachable'
                                ];
                            }
                            // } else {
                            //     if (isset($premium_response['PREMIUMDETAILS']['Status'])) {
                            //         return [
                            //             'status' => false,
                            //             'premium' => 0,
                            //             'message' => $premium_response['PREMIUMDETAILS']['Status']['Message']
                            //         ];
                            //     } else {
                            //         return [
                            //             'status' => false,
                            //             'premium' => 0,
                            //             'message' => 'Insurer not reachable'
                            //         ];
                            //     }
                            // }
                        } else {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
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
                    
                    if(empty($city_name)) {
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

                    $car_tariff = DB::table('motor_tariff AS mt')
                        ->whereRaw($mmv_data['cubic_capacity'].' BETWEEN mt.cc_min and mt.cc_max')
                        ->whereRaw($vehicle_age . ' BETWEEN mt.age_min and mt.age_max')
                        ->where('mt.zone_id', $city_name->zone_id)
                        ->first();

                    $liability_to_employees  =  $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_EMPLOYESES'] ?? '' ;
                    $llpaiddriver_premium = $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS'];
                    $cover_pa_owner_driver_premium = $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER'];
                    $cover_pa_paid_driver_premium = $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER'];
                    $cover_pa_unnamed_passenger_premium = $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS'];
                    $voluntary_excess = $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE'];
                    $anti_theft = 0;
                    $ic_vehicle_discount = 0;
                    $ncb_discount =($tp_only ? 0 : $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS']);
                    $electrical_accessories_amt =  $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES'];
                    $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' && !$tp_only ? $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES'] : 0;
                    // $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' && !$tp_only ? $non_electrical_accessories_value * ($car_tariff->rate_per_thousand/100) : 0;
                    $od = $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES'];
                    $tppd =$premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD'];
                    $tppd_discount = 0;
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;
                    $employe_amount = 0;
                    if (!empty($TPPDCover)) {
                        //$tppd_discount = 100*$cpa_tenure;
                        $tppd_discount = 100 * ($requestData->business_type == 'newbusiness' ? 3 : 1);
                        $tppd += $tppd_discount;
                    }
                    $cng_lpg = $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT'];
                    $cng_lpg_tp = $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG'];
                    if($requestData->vehicle_owner_type == 'C'){
                        $employe_amount  = $premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_EMPLOYESES'];
                    }

                    $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
                    $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $employe_amount;
                    $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount + $tppd_discount;
                    $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount;
                    $final_gst_amount = $final_net_premium * 0.18;
                    $final_payable_amount  = $final_net_premium + $final_gst_amount;

                    $applicable_addons = [];
                    $addons_data = [
                        'in_built' => [],
                        'additional' => [],
                        'other_premium' => 0
                    ];

                    if (!$tp_only) {
                        if ($productData->zero_dep == 1) {
                            $addons_data = [
                                'in_built'   => [
                                    /* 'road_side_assistance' => 0, */
                                    // 'consumables' => 0,
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'engine_protector' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR'],
                                    'ncb_protection' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NCB_PROTECTOR'],
                                    'key_replace' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['KEY_REPLACEMENT'],
                                    'tyre_secure' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYRE_COVER'],
                                    'consumables' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['CONSUMABLE_COVER'],
                                    // 'return_to_invoice' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['INVOICE_PRICE_INSURANCE']),
                                    'return_to_invoice' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VEHICLE_REPLACEMENT_COVER'],
                                    'lopb' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['LOSS_OF_BAGGAGE'],
                                    'road_side_assistance' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDE_ASSISTANCE_COVER'],
                                    'windShield' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['WIND_SHIELD_GLASS'] ?? 0
                                ],
                                'other'=>[]
                            ];
                        } else {
                            $addons_data = [
                                'in_built'   => [
                                    'zero_depreciation' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'],
                                    /* 'road_side_assistance' => 0, */
                                    // 'consumables' => 0,
                                ],
                                'additional' => [
                                    'engine_protector' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR'],
                                    'ncb_protection' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NCB_PROTECTOR'],
                                    'key_replace' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['KEY_REPLACEMENT'],
                                    'tyre_secure' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYRE_COVER'],
                                    'consumables' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['CONSUMABLE_COVER'],
                                    // 'return_to_invoice' => round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['INVOICE_PRICE_INSURANCE']),
                                    'return_to_invoice' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VEHICLE_REPLACEMENT_COVER'],
                                    'lopb' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['LOSS_OF_BAGGAGE'],
                                    'road_side_assistance' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDE_ASSISTANCE_COVER'] ?? 0,
                                    'windShield' => $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['WIND_SHIELD_GLASS'] ?? 0
                                ],
                                'other'=>[]
                            ];
                        }
                        if($productData->product_identifier == 'roadSideAssistancePlan2'){
                            $addons_data['additional']['road_side_assistance_2'] = $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDE_ASSISTANCE_COVER'] ?? 0;
                            unset($addons_data['additional']['road_side_assistance']);
                        };
                        if($hdn_invoice_price=='true' && $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VEHICLE_REPLACEMENT_COVER'] > 0){
                            $addons_data['other'] = [
                                'fullInvoicePrice'      =>0,
                                'fullInvoicePriceRoadtax'  =>0,
                                'fullInvoicePriceRegCharges'  =>0,
                                'fullInvoicePriceInsuranceCost'  =>0,
                            ];
                        } else {
                            $addons_data['other'] = [];
                        }

                        $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                        $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                        $addons_data['other_premium'] = 0;

                        $applicable_addons = [
                            'zeroDepreciation', 'keyReplace', 'engineProtector', 'ncbProtection', 'tyreSecure', 'returnToInvoice', 'lopb','roadSideAssistance', 'windShield', 'consumables'
                        ];
                        if ($productData->product_identifier == 'roadSideAssistancePlan2') {
                            array_push($applicable_addons,'roadSideAssistance2');
                        }

                        if($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VEHICLE_REPLACEMENT_COVER'] <= 0){ //Changing condition as per git #30369
                            //unset($applicable_addons[5]);
                            array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                        }
                        if ($requestData->business_type == 'newbusiness') {
                            array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                        }

                        if ($vehicle_age > 10) {
                            array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                        }
                        if ($vehicle_age_for_addons > 7) {
                            array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                            array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                            array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                        }
                        if ($vehicle_age_for_addons > 3) {
                            array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                        }
                        if ($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['CONSUMABLE_COVER'] <= 0){
                            array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                        }
                    }
                    
                    if(config('ROYAL_SUNDARAM_ENABLE_CONSUMABLE_AS_BUILT_IN') == 'Y')
                    {
                        if(isset($addons_data['additional']['zero_depreciation']) && $addons_data['additional']['zero_depreciation'] > 0)
                        {
                            $addons_data['in_built']['consumables'] = 0;
                            $addons_data['in_built']['zero_depreciation'] = $addons_data['additional']['zero_depreciation'];
                            unset($addons_data['additional']['zero_depreciation']);
                        }

                    }
                
                    $quote_id = $premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'] ?? '';
                    $final_response = ([
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'quote_id'=> $quote_id,
                            'PREMIUM'=>$PREMIUM,
                            'idv' => $vehicle_idv,
                            'min_idv' => $min_idv,
                            'max_idv' => $max_idv,
                            'default_idv' => $default_idv,
                            'modified_idv'=>$vehicle_idv,
                            'original_idv'=>$orignal_idv,
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
                            'fuel_type' => $mmv->fuel_type,
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
                            'motor_manf_date' => $motor_manf_date,
                            'vehicleDiscountValues' => [
                                'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'segment_id' => 0,
                                'rto_cluster_id' => 0,
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
                            'seating_capacity' => $mmv->seating_capacity,
                            'default_paid_driver' => $llpaiddriver_premium,
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
                            'quotation_no' => '',
                            'premium_amount'  => $final_payable_amount,
                            'antitheft_discount' => $anti_theft,
                            'final_od_premium' => $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' => $final_total_discount,
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
                            'isInspectionApplicable' => 'N',
                            'quote_id' => $quote_id
                        ]
                    ]);
                    if ($liability_to_employees > 0 && !($requestData->vehicle_owner_type == 'I') ) {
                        $final_response['Data']['other_covers']['LegalLiabilityToEmployee'] = $liability_to_employees;
                        $final_response['Data']['LegalLiabilityToEmployee'] = $liability_to_employees;
                    }
                    if($external_fuel_kit == 'Yes')
                    {
                        $final_response['Data']['motor_lpg_cng_kit_value'] = $cng_lpg;
                        $final_response['Data']['cng_lpg_tp'] = $cng_lpg_tp;
                    }
                    if ($cng_lpg > 0) {
                        $final_response['Data']['motor_lpg_cng_kit_value'] = $cng_lpg;
                    }
                    if(isset($cpa_tenure) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
                    {
                        // unset($final_response['Data']['compulsory_pa_own_driver']);
                        $final_response['Data']['multi_Year_Cpa'] = $cover_pa_owner_driver_premium;
                    }    
                    return camelCase($final_response);
                } else {
                    if (isset($premium_response['PREMIUMDETAILS']['Status'])) {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'status' => false,
                            'premium' => 0,
                            'message' => $premium_response['PREMIUMDETAILS']['Status']['Message']
                        ];
                    } else {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'status' => false,
                            'premium' => 0,
                            'message' => 'Insurer not reachable'
                        ];
                    }
                }
            } else {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status' => false,
                    'premium' => '0',
                    'message' => 'Insurer not reachable'
                ];
            }
        } else {
            return [
                // 'webservice_id' => $data['webservice_id'],
                // 'table' => $data['table'],
                'status' => false,
                'premium' => '0',
                'message' => 'Sum insured for Electrical Accessories/Non-Electrical Accessories/Bi-Fuel Kit should be less than ₹1,00,000.',
                'request' => [
                    'message' => 'Sum insured for Electrical Accessories/Non-Electrical Accessories/Bi-Fuel Kit should be less than ₹1,00,000.',
                    'external_fuel_kit_amount' => $external_fuel_kit_amount,
                    'electrical_accessories_value' => $electrical_accessories_value,
                    'non_electrical_accessories_value' => $non_electrical_accessories_value,
                ]
            ];
        }
    }
