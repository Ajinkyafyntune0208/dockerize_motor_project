<?php

namespace App\Http\Controllers\Proposal\Services;
include_once app_path().'/Helpers/CvWebServiceHelper.php';
include_once app_path().'/Helpers/IcHelpers/RoyalSundaramHelper.php';

use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Services\RoyalSundaramPremiumDetailController;
use DateTime;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class royalSundaramSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quote_data = json_decode($quote->premium_json,true);
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);
        $parent_code = get_parent_code($productData->product_sub_type_id);
        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $rto_data = DB::table('royal_sundaram_rto_master AS rsrm')
            ->where('rsrm.rto_no', str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
            ->first();

        if (empty($rto_data)) 
        {
            return [
                'status' => false,
                'premium' => '0',
                'message' => 'RTO not available'
            ];
        }

        $region = DB::table('royal_sundaram_motor_state_city_master')
            ->where('city', $proposal->is_car_registration_address_same == 0 ?  $proposal->car_registration_city : $proposal->city)
            ->first();

        if($region == null || empty($region))
        {
            $region = (object)['region'=> ''];
        }
        
        $mmv = get_mmv_details($productData, $requestData->version_id, 'royal_sundaram');

        if ($mmv['status'] == 1) 
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

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
            ];
        } 
        elseif ($mmv->ic_version_code == 'DNE') 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
            ];
        }
        $ncb_levels = [
            '0' => '0',
            '20' => '1',
            '25' => '2',
            '35' => '3',
            '45' => '4',
            '50' => '5'
        ];

        $prev_policy_end_date = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new \DateTime($vehicleDate);
        $date2 = new \DateTime($prev_policy_end_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        $vehicle_age_for_addons = (($age - 1) / 12);

        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') 
        {
            $policy_start_date = date('Y-m-d');
        }

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $cpa_tenure = '1';
        $is_previous_claim = 'No';
        $claimsReported = '';
        $isPreviousPolicyHolder = true;
        if ($requestData->business_type == 'newbusiness') 
        {
            $typeofCover  = "Comprehensive";
            $businessType = 'New Business';
            $previous_policy_type = '';
            $policy_start_date = date('Y-m-d');
            $isPreviousPolicyHolder = false;
        } 
        else if ($requestData->business_type == 'rollover') 
        {
            $product_name = 'RolloverCar';
            $businessType = 'Roll Over';
            $typeofCover  = "Comprehensive";           
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
            $previous_policy_type = ($requestData->previous_policy_type == 'Comprehensive' ? 'Comprehensive' : 'ThirdParty');
        } 
        else if ($requestData->business_type == 'breakin') 
        {
            $product_name = 'RolloverCar';#BreakinCar
            $businessType = 'Break-In';
            $typeofCover  = "Comprehensive";
            $policy_start_date = date('Y-m-d', strtotime('+3 day', strtotime(date('Y-m-d'))));
            $previous_policy_type = ($requestData->previous_policy_type == 'Comprehensive' ? 'Comprehensive' : 'ThirdParty');
        }

        if ($tp_only) 
        {
            $typeofCover  = "ThirdParty";
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
            $requestData->applicable_ncb = 0;
            $requestData->previous_ncb = 0;

            if ($requestData->business_type == 'breakin') {
                $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
            }
        }

        if ($businessType != 'New Business' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) 
        {
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_difference > 0) 
            {
                $policy_start_date = date('m/d/Y 00:00:00',strtotime('+3 day'));
            }

            if($date_difference > 90)
            {
                $requestData->applicable_ncb = 0;
            }
            if ($requestData->business_type == 'breakin' && $tp_only == 'true') {
                $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
            }
        }
        
        if (in_array($requestData->previous_policy_type, ['Not sure'])) 
        {
            $policy_start_date = date('m/d/Y 00:00:00',strtotime('+3 day'));
            $requestData->previous_policy_expiry_date = date('Y-m-d', strtotime('-120 days'));
            $proposal->prev_policy_expiry_date = date('Y-m-d', strtotime('-120 days'));
            $requestData->applicable_ncb = 0;
            $requestData->previous_ncb = 0;
            $previous_policy_type = '-';
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
        }
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        
        $isInspectionWaivedOff = false;
        $waiverExpiry = null;
        
        if (
            $requestData->business_type == 'breakin' &&
            !empty($requestData->previous_policy_expiry_date) &&
            strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
            config('ROYAL_SUNDARAM_INSPECTION_WAIVED_OFF_CV') == 'Y'
        ) {
                $isInspectionWaivedOff = true;
                $waiverExpiry = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date . ' +90 days'));
                $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
        }

        if($requestData->is_claim == 'Y')
        {
            $is_previous_claim = 'Yes';
            $claimsReported = 1;
            $requestData->applicable_ncb = 0;
            //$requestData->previous_ncb = 0;
        }

        $previous_insurer_addresss = '';
        $previous_insurer = DB::table('insurer_address')
            ->where('Insurer', $proposal->insurance_company_name)
            ->first();
        $previous_insurer = keysToLower($previous_insurer);

        if (!empty($previous_insurer) && isset($previous_insurer->address_line_1) && !(in_array($requestData->previous_policy_type, ['Not sure']))) 
        {
            $previous_insurer_addresss = $previous_insurer->address_line_1.' '.$previous_insurer->address_line_2.', '. $previous_insurer->pin;
        }

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

//        $hdn_ncb_protector = 'false';
//        $hdn_key_replacement = 'false';
//        $hdn_protector = 'false';
//        $hdn_invoice_price = 'false';
//        $hdn_loss_of_baggage = 'false';
//        $hdn_tyre_cover = 'false';
//        $opted_addons = [];
        $tyreMudguard = 'No';
        $zeroDep = 'off';

       if (!empty($additional['applicable_addons'])) {
           foreach ($additional['applicable_addons'] as $key => $data) {
//                if ($data['name'] == 'Engine Protector' && $vehicle_age < 10) {
//                    $hdn_protector = 'true';
//                    $opted_addons[] = 'AggravationCover';
//                }
//
            if ($data['name'] == 'Zero Depreciation') {
                $zeroDep = 'on';
                $opted_addons[] = 'DepreciationWaiver';
            }

            if ($data['name'] == 'IMT - 23') {
            $tyreMudguard = 'Yes';
            }
//
//                if ($data['name'] == 'Tyre Secure' && $vehicle_age_for_addons <= 7) {
//                    $hdn_tyre_cover = 'true';
//                    $opted_addons[] = 'TyreCoverClause';
//                }
//
//                if ($data['name'] == 'Return To Invoice' && $vehicle_age_for_addons < 4) {
//                    $hdn_invoice_price = 'true';
//                    $opted_addons[] = 'InvoicePrice';
//                }
//
//                if ($data['name'] == 'Loss of Personal Belongings' && $vehicle_age < 10) {
//                    $hdn_loss_of_baggage = 'true';
//                    $opted_addons[] = 'LossOfBaggage';
//                }
//
//                if ($data['name'] == 'Key Replacement' && $vehicle_age < 10) {
//                    $hdn_key_replacement = 'true';
//                    $opted_addons[] = 'KeyReplacement';
//                }
//
//                if ($data['name'] == 'NCB Protection') {
//                    $hdn_ncb_protector = 'true';
//                    $opted_addons[] = 'NCBProtector';
//                }
           }
       }
//
//        if ($proposal->ownership_changed == 'Y') {
//            $hdn_ncb_protector = 'false';
//        }
       $add_ons_opted_in_previous_policy = !empty($opted_addons) ? implode(',', $opted_addons) : '';

        $electrical_accessories = 'No';
        $electrical_accessories_value = '0';
        $non_electrical_accessories = 'No';
        $non_electrical_accessories_value = '0';
        $external_fuel_kit = 'No';
        $external_fuel_kit_amount = '';
        $typeOfBiFuelKit = '';
        $geoExtension = 'No';
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
                else if ($data['name'] == 'Non-Electrical Accessories') 
                {
                    $non_electrical_accessories = 'Yes';
                    $non_electrical_accessories_value = $data['sumInsured'];
                }
                else if ($data['name'] == 'Electrical Accessories') 
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

        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = $no_of_cleanerLL = $no_of_driverLL = $no_of_conductorLL = $no_ll_paid_driver = 0;
        $cover_ll_paid_driver = $total_no_of_coolie_cleaner = 'NO';

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
        $noOfPaiddriverOrCleaner = 0;
        //echo $no_of_driverLL;
        // $noOfPaiddriverOrCleaner =  $no_of_cleanerLL + $no_of_driverLL;
        if($cover_ll_paid_driver == 'YES')
        {
           $noOfPaiddriverOrCleaner =  $no_of_cleanerLL + $no_of_driverLL;
        }
        $total_no_of_coolie_cleaner =   $no_of_cleanerLL + $no_of_conductorLL;
        $cpa_selected = 'No';
        $cpa_reason = 'false';
        $prepolicy = (array) json_decode($proposal->additional_details);
        $prepolicy = !empty($prepolicy['prepolicy']) ? (array) $prepolicy['prepolicy'] : [];
        $companyName = !empty($proposal->cpa_ins_comp) ? $proposal->cpa_ins_comp : '';
        $expiryDate = !empty($proposal->cpa_policy_to_dt) ? $proposal->cpa_policy_to_dt : '';
        $policyNumber = !empty($proposal->cpa_policy_no) ? $proposal->cpa_policy_no : '';
        $standalonePAPolicy = 'false';
        $cpaCoverWithInternalAgent = 'false';
        $isValidDrivingLicenseAvailable = 'Yes';
        if (!empty($additional['compulsory_personal_accident'])) 
        {
            foreach ($additional['compulsory_personal_accident'] as $key => $data)  
            {
                if ($requestData->vehicle_owner_type == 'I') 
                {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  
                    {
                        $cpa_selected = 'Yes';
                        $cpa_tenure = isset($data['tenure']) ? (string) $data['tenure'] : '1';
                    } 
                    else if (isset($data['reason']) && $data['reason'] != "") 
                    {
                        if ($data['reason'] == 'I do not have a valid driving license.') 
                        {
                            $cpa_reason = 'true';
                            $isValidDrivingLicenseAvailable = 'No';
                        }
                        else if($data['reason'] == 'I have another PA policy with cover amount of INR 15 Lacs or more')
                        {
                            $standalonePAPolicy = 'true';
                        }
                        else if($data['reason'] == 'I have another motor policy with PA owner driver cover in my name')
                        {
                            $cpaCoverWithInternalAgent = 'true';
                            $companyName = !empty($companyName) ? $companyName : (!empty($prepolicy['CpaInsuranceCompany']) ? $prepolicy['CpaInsuranceCompany'] : '');
                            $expiryDate = !empty($expiryDate) ? date('d/m/Y', strtotime($expiryDate)) : (!empty($prepolicy['cpaPolicyEndDate']) ? date('d/m/Y', strtotime($prepolicy['cpaPolicyEndDate'])) : '');
                            $policyNumber = !empty($policyNumber) ? $policyNumber : (!empty($prepolicy['cpaPolicyNumber']) ? $prepolicy['cpaPolicyNumber'] : '');
                        } 
                        else 
                        {
                            $companyName = !empty($companyName) ? $companyName : (!empty($prepolicy['CpaInsuranceCompany']) ? $prepolicy['CpaInsuranceCompany'] : '');
                            $expiryDate = !empty($expiryDate) ? $expiryDate : (!empty($prepolicy['cpaPolicyEndDate']) ? $prepolicy['cpaPolicyEndDate'] : '');
                            $policyNumber = !empty($policyNumber) ? $policyNumber : (!empty($prepolicy['cpa_policy_number']) ? $prepolicy['cpa_policy_number'] : '');
                        }
                    }
                }
            }
        }

        $voluntary_excess_amt = 0;
        $TPPDCover = 'No';

        if (!empty($additional['discounts'])) 
        {
            foreach ($additional['discounts'] as $key => $data) 
            {
                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) 
                {
                    $voluntary_excess_amt = $data['sumInsured'];
                }
                if ($data['name'] == 'TPPD Cover') 
                {
                    $TPPDCover = 'Yes';
                }
            }
        }

        if (get_parent_code($productData->product_sub_type_id) == 'PCV')
        {
            $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_PCV_PREMIUM';
        }
        else if (get_parent_code($productData->product_sub_type_id) == 'GCV') 
        {
            $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_GCV_PREMIUM';
        }
       
        $OrganizationName = [];

        if ($requestData->vehicle_owner_type == 'C' && ! empty($proposal->first_name)) {
            $OrganizationName = array_values(getAddress([
                'address' => $proposal->first_name,
                'address_1_limit' => 30,
                'address_2_limit' => 30
            ]));
        }
        $contactAddress = explode(' ',$proposal->address_line1);
        $contactAddress_2 = end($contactAddress);
        array_pop($contactAddress);
        $contactAddress_1 = implode(' ',$contactAddress);
        $premium_request = [
            "quoteId" => $quote_data['quoteId'],
            "premium" => 0.0,
            "isPosOpted" => "No",
            'authenticationDetails' => [
                "apikey"    => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
                "agentId"   => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
                "partner"   => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR')
            ],
            'proposerDetails' => [
                "title"                     => $requestData->vehicle_owner_type == 'C' ? 'Ms' : ($proposal->gender == 'M' ? 'Mr' : ($proposal->marital_status == 'Married' ? 'Mrs' : 'Ms')),
                "firstName"                 => $requestData->vehicle_owner_type == 'I' ? removeSpecialCharactersFromString($proposal->first_name, true) : removeSpecialCharactersFromString($OrganizationName[0], true),
                "lastName"                 => $requestData->vehicle_owner_type == 'I' ? $proposal->last_name : $OrganizationName[1],
                "emailId"                   => $proposal->email,
                "mobileNo"                  => $proposal->mobile_number,
                "dateOfBirth"               => $requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : '18/09/1995',
                "occupation"                => $proposal->occupation_name,
                "nomineeName"               => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_name : '',
                "nomineeAge"                => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_age : '',
                "relationshipWithNominee"   => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_relationship : '',
                "relationshipwithGuardian"  => "0",
                "contactAddress1"           => $contactAddress_1,//$proposal->address_line1,
                "contactAddress2"           => $contactAddress_2,
                "contactAddress3"           => '',
                "contactAddress4"           => "",
                "contactCity"               => $proposal->city,
                "contactPinCode"            => $proposal->pincode,
                "contactState"              => $proposal->state,
                'GSTIN'                     => $proposal->gst_number
            ],
            'vehicleDetails' => [
                'hdnDepreciation'                   => $productData->zero_dep == '1' ? 'false' : 'true',
                'addOnsOptedInPreviousPolicy'       => $add_ons_opted_in_previous_policy,
                'GSTIN'                         => $proposal->gst_number,
                "isCarOwnershipChanged"         => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
                "rtoName"                       =>  $rto_data->rto_name,
                "typeofCover"                   => $typeofCover,
                "usageType"                     => "Commercial",
                "yearOfManufacture"             => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                "vehicleRegistrationDate"       => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                
                "vehicleManufacturerName"       => $mmv->make,
                "vehicleModelCode"              => $mmv->ic_version_code,
                "original_idv"                      => 0,
                "modifiedIdvValue"                  => 0,
                "modifyYourIdv"                     => 0,
                "parkingDuringDay"                  => "Open Park",
                "parkingDuringNight"                => "Road Side Parking",
                "vehicleRegisteredInTheNameOf"      => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
                "isBiFuelKit"                       => $external_fuel_kit,
                "typeOfBiFuelKit"                   => $typeOfBiFuelKit, //$external_fuel_kit == 'Yes' ? 'ADD ON' : '',
                "addonValue"                        => $external_fuel_kit_amount,
                "cover_non_elec_acc"                => $non_electrical_accessories,
                "cover_elec_acc"                    => $electrical_accessories,
                "vehicleMostlyDrivenOn"             => 'City roads',
                "isVehicleUsedFordrivingTuition"    => "No",
                "isPreviousPolicyHolder"            => $isPreviousPolicyHolder,
                "previousPolicyExpiryDate"          => $isPreviousPolicyHolder == true ? date('d/m/Y', strtotime($proposal->prev_policy_expiry_date)) : '',
                "previousPolicyType"                => $previous_policy_type,
                "previousPolicyNo"                  => $proposal->previous_policy_number,
                "previousInsurerName"               => $proposal->previous_insurance_company,
                "previousinsurerAddress"            => $requestData->business_type != 'newbusiness' ? $previous_insurer_addresss : '',
                "claimsMadeInPreviousPolicy"        => $is_previous_claim,
                "claimsReported"                    => $claimsReported,
                'companyNameForCar' => $requestData->vehicle_owner_type == 'I' ? '' : removeSpecialCharactersFromString($proposal->first_name , true),
              //"claimAmountReceived"               => '', 
                "ncbcurrent"                        => $requestData->ownership_changed == 'Y' ? '0' : $requestData->applicable_ncb,
                "ncbprevious"                       => $requestData->ownership_changed == 'Y' ? '0' : $requestData->previous_ncb,
                
                "registrationNumber"                => str_replace('-', '', $proposal->vehicale_registration_number),
                "engineNumber"                      => $proposal->engine_number,
                "chassisNumber"                     => $proposal->chassis_number,
                "isFinanced"                        => $proposal->is_vehicle_finance == 1 ? 'Yes' : 'No',
                "financedValue"                     => $proposal->is_vehicle_finance == 1 ? $proposal->financer_agreement_type : '',
                "financierName"                     => $proposal->is_vehicle_finance == 1 ? $proposal->name_of_financer : '',
                "cover_dri_othr_car_ass"            => "No",
                "depreciationWaiver"                =>  $zeroDep,
                "accidentcoverforpaiddriver"        => $cover_pa_paid_driver_amt,
                // "legalliabilitytopaiddriverorcleaner" => $cover_ll_paid_driver,
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
                "nonElectricalAccesories" => [
                    "nonelectronicAccessoriesDetails" => [
                            [
                                "nameOfElectronicAccessories"   => "",
                                "makeModel"                     => "MCJYzzJyZd",
                                "value"                         => $non_electrical_accessories_value
                            ]
                        ]
                    ],
                "electricalAccessories" => [
                    "electronicAccessoriesDetails" => [
                            [
                            "nameOfElectronicAccessories"   => "",
                            "makeModel"                     => "MCJYzzJyZd",
                            "value"                         => $electrical_accessories_value
                            ]
                        ]
                    ],
                "tyreMudguard"=>$tyreMudguard,
                "depreciationWaiverCover"=>$zeroDep,
                "dop_proposer"                              => $requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : '18/09/1995',
                "isNewOrSecondHand"                         => "New",
                "isGoodCondition"                           => "Yes",
                "conditionDetails"                          => "good",
                "isPSDPP_Purpose"                           => "Yes",
                "isCarriageOfGoods"                         => "Yes",
                "geoExtension"                              => $geoExtension,
                "ownerDOB"                                  => $requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : '18/09/1995',
                "driverDOB"                                 => $requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : '18/09/1995',//"11/11/1987",
                "driverPhysicalInfirmity"                   => "No",
                "isDriverAnyAccident"                       => "No",
                "isRegistrationAddressSameAsContactAddress" => $proposal->is_car_registration_address_same == 1 ? 'Yes' : 'No',
                "vehicleRegistrationAddress1"               => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line1 : $proposal->car_registration_address1,
                "vehicleRegistrationAddress2"               => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
                "vehicleRegistrationCity"                   => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city,
                "vehicleRegistrationPinCode"                => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                "vehicleRegistrationState"                  => $proposal->is_car_registration_address_same == 1 ? $proposal->state : $proposal->car_registration_state,
                "noClaimBonusPercent"                       => $ncb_levels[$requestData->applicable_ncb] ?? '0',
                'validPUCAvailable'                         => $proposal->is_valid_puc == '1' ? 'Yes' : 'No',
                'pucnumber'                                 => $proposal->is_valid_puc == '1' ? $proposal->puc_no : '',
                'pucvalidUpto'                              => $proposal->is_valid_puc == '1' && !empty($proposal->puc_expiry) ? date('d/m/Y', strtotime($proposal->puc_expiry)) : '',
                "isValidDrivingLicenseAvailable"            => $isValidDrivingLicenseAvailable,
                //"enhancedPACoverForPaidDriver"              => "500000"
            ]
        ];

        if(config('constants.royal_sundaram.VALIDATE_PAN') == 'Y'){
            $premium_request['proposerDetails']['panNumber'] = $proposal->pan_number;
            $premium_request['proposerDetails']['validatePan'] = 'yes';
        }

        $premium_request['vehicleDetails']['cpaCoverisRequired'] = $cpa_selected;
        $premium_request['vehicleDetails']['cpaPolicyTerm'] = $proposal->owner_type == 'I' ? $cpa_tenure : '';
        $premium_request['vehicleDetails']['cpaCoverDetails'] = 
        [
            'noEffectiveDrivingLicense' => $cpa_reason,
            'cpaCoverWithInternalAgent' => $cpaCoverWithInternalAgent,
            'standalonePAPolicy'        => $standalonePAPolicy,
            'companyName'               => $companyName,
            'expiryDate'                => $expiryDate,
            'policyNumber'              => $policyNumber,
        ];
        
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) 
        {
            $POSPCode = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
            $premium_request['isPosOpted'] = 'Yes';
            $premium_request['posCode'] = '';
            $premium_request['posDetails'] = [
                'name' => removeSpecialCharactersFromString($pos_data->agent_name, true),
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
        }
        if(isset($requestData->selected_gvw) && !empty($requestData->selected_gvw))
        {
            $premium_request['vehicleDetails']['additionalGVW'] = $requestData->selected_gvw;
        }
        if ($proposal->is_ckyc_verified == 'Y') {
        $get_response = getWsData(config($requestUrl), $premium_request, 'royal_sundaram', [
            'enquiryId'         => $proposal->user_product_journey_id,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name. " ($businessType)",
            'company'           => 'royal_sundaram',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Calculation Initial IDV',
            'transaction_type'  => 'proposal'
        ]);
        $data = $get_response['response'];
        $response = json_decode($data, TRUE);
        if (!(isset($response['PREMIUMDETAILS']['Status']['StatusCode']) && $response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002')) 
        {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium' => 0,
                'message' => $response['PREMIUMDETAILS']['Status']['Message'] ?? 'Insurer not reachable'
            ];
        }
        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
            ->update([
                'additional_details_data' => $data,
                'proposal_no' => $response['PREMIUMDETAILS']['DATA']['QUOTE_ID']
            ]);
        }else{
            $data = $proposal->additional_details_data;
        }
        //$premium_response_idv = (!$tp_only) ? round($quote_data['modifiedIdv']) : 0;

        //By enabling this, ckyc will verified on first card instead of proposal submission
        $ckycProposal = config('ENABLE_ROYAL_SUNDARAM_CKYC_ON_FIRST_CARD') != 'Y';

        if ($ckycProposal) {
            $kyc_url = '';
            $is_kyc_url_present = false;
            $kyc_message = '';
            $kyc_status = false;
        } else {
            $kyc_status = $proposal->is_ckyc_verified == 'Y';
            $kyc_url = null;
            $is_kyc_url_present = false;
            $kyc_message = null;

            if (!$kyc_status) {
                return [
                    'status' => false,
                    'message' => 'Please complete ckyc before submitting proposal'
                ];
            }
        }
        if ($proposal->is_ckyc_verified != 'Y' && $ckycProposal) {
            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                if(config('RSA_MANDORY_DOCUMENT_CHANGES_ENABLED') == 'Y') {
                    $ckycType = $proposal->ckyc_type;
                    $ckycTypeArray = [
                        'pan_card' => 'pan_number_with_dob',
                        'aadhar_card' => 'aadhar',
                        'passport' => 'passport',
                        'voter_id' => 'voter_card',
                        'driving_license' => 'driving_license',
                        'ckyc_number' => 'ckyc_number',
                    ];
                    if(isset($ckycTypeArray[$proposal->ckyc_type])) {
                        $ckycType = $ckycTypeArray[$proposal->ckyc_type];
                    }
                    $request_data = [
                        'companyAlias' => 'royal_sundaram',
                        'mode' =>  $ckycType,
                        'unique_quote_id' => $response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                        'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    ];
                } else { 
                    $request_data = [
                        'companyAlias' => 'royal_sundaram',
                        'mode' =>  $proposal->ckyc_type == 'ckyc_number' ? 'ckyc_number' : 'pan_number_with_dob',
                        'unique_quote_id' => $response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                        'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    ];
                }

                $ckycController = new CkycController;
                $response = $ckycController->ckycVerifications(new Request($request_data));
                $response = $response->getOriginalContent();
                if (isset($response['data']['verification_status']) && $response['data']['verification_status'] && !empty($response['data']['ckyc_id'])) {
                    $kyc_url = '';
                    $is_kyc_url_present = false;
                    $kyc_message = '';
                    $kyc_status = true;
                    $request_data = [
                        'company_alias' => 'royal_sundaram',
                        'trace_id' => customEncrypt($proposal->user_product_journey_id),
                        'skip_proposal' => true
                    ];
    
                    $ckycController = new CkycController;
                    $response = $ckycController->ckycResponse(new Request($request_data));
                    $response = $response->getOriginalContent();
                } else {
                    $kyc_url = $response['data']['redirection_url'];
                    $is_kyc_url_present = !empty($response['data']['redirection_url']);
                    $kyc_message = (!empty($response['data']['message']) ? $response['data']['message'] : 'Kyc verification false');
                    $kyc_status = false;
                }
            }
        }
        $proposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
    if($kyc_status || $proposal->is_ckyc_verified == 'Y')
    {   
        if ($data) 
        {
            $premium_response = json_decode($data, TRUE);
            if (isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002') 
            {
                $premium_request['quoteId'] = $quoteId = $premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'];
                if(!$tp_only)
                {
                    $premium_request['vehicleDetails']['original_idv'] = $premium_response['PREMIUMDETAILS']['DATA']['IDV'];
                    $premium_request['vehicleDetails']['modifiedIdvValue'] = $quote->idv;  
                    $premium_request['proposerDetails']['firstName']        = $requestData->vehicle_owner_type == 'I' ? removeSpecialCharactersFromString($proposal->first_name, true) : removeSpecialCharactersFromString($OrganizationName[0], true);
                    $premium_request['proposerDetails']['emailId']          = $proposal->email;
                    $premium_request['proposerDetails']['mobileNo']         = $proposal->mobile_number;
                    $premium_request['proposerDetails']['contactAddress1']  = $contactAddress_1;//$proposal->address_line1;

                    $premium_request['vehicleDetails']['companyNameForCar'] = $requestData->vehicle_owner_type == 'I' ? '' : removeSpecialCharactersFromString($proposal->first_name , true);

                    #pincode data
                    $premium_request['vehicleDetails']['vehicleRegistrationCity']  = $proposal->city;
                    $premium_request['vehicleDetails']['vehicleRegistrationPinCode']  = $proposal->pincode;
                    $premium_request['vehicleDetails']['vehicleRegistrationState']  = $proposal->state;
                    
                    $get_response = getWsData(config($requestUrl), $premium_request, 'royal_sundaram', [
                        'enquiryId'         => $proposal->user_product_journey_id,
                        'requestMethod'     => 'post',
                        'productName'       => $productData->product_name. " ($businessType)",
                        'company'           => 'royal_sundaram',
                        'section'           => $productData->product_sub_type_code,
                        'method'            => 'Premium Calculation With IDV',
                        'transaction_type'  => 'proposal'
                    ]);
                    $data = $get_response['response'];
                    
                    if ($data) 
                    {
                        $premium_response = json_decode($data, TRUE);
                        if (!(isset($premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002')) 
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
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium' => 0,
                            'message' => 'Insurer not reachable'
                        ];            
                    }                     
                }

                $documentData = getMandatoryDocumentData($proposal);
            
                $premium_request['proposerDetails'] = array_merge(
                    $premium_request['proposerDetails'],
                    $documentData
                );
                if (get_parent_code($productData->product_sub_type_id) == 'PCV')
                {
                    $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_PCV_PREMIUM_UPDATE';
                }
                elseif (get_parent_code($productData->product_sub_type_id) == 'GCV') 
                {
                    $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_GCV_PREMIUM_UPDATE';
                }

                $get_response = getWsData(config($requestUrl), $premium_request, 'royal_sundaram', [
                    'enquiryId'         => $proposal->user_product_journey_id,
                    'requestMethod'     => 'post',
                    'productName'       => $productData->product_name. " ($businessType)",
                    'company'           => 'royal_sundaram',
                    'section'           => $productData->product_sub_type_code,
                    'method'            => 'Update Premium Calculation',
                    'transaction_type'  => 'proposal',
                ]);
                $data = $get_response['response'];
                if($data)
                {
                    $premWebServiceId = $get_response['webservice_id'];
                    $update_premium_response = json_decode($data, TRUE);
                
                    if(isset($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && ($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0005'))
                    {
                        $proposalService =  [
                            "authenticationDetails" =>  [
                                "apikey" => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
                                "agentId" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
                                "partner" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR')
                            ],
                            "quoteId"       =>  $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                            "premium"       =>  $update_premium_response['PREMIUMDETAILS']['DATA']['GROSS_PREMIUM'],
                            "strEmail"      =>  $proposal->email,
                            //"reqType"       =>  "XML",
                            "isOTPVerified" =>  "Yes"
                        ];
                        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                            $proposalService['uniqueId'] = $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'];
                            $proposalService['ckycNo'] = $proposal->ckyc_number;
                        }                       
                        if (get_parent_code($productData->product_sub_type_id) == 'PCV')
                        {
                            $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_PCV_G_PROPOSAL';
                        }
                        elseif (get_parent_code($productData->product_sub_type_id) == 'GCV') 
                        {
                            $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_GCV_G_PROPOSAL';
                        }
                        $get_response = getWsData(config($requestUrl), $proposalService, 'royal_sundaram', [
                            'enquiryId'         => $proposal->user_product_journey_id,
                            'requestMethod'     => 'post',
                            'productName'       => $productData->product_name. " ($businessType)",
                            'company'           => 'royal_sundaram',
                            'section'           => $productData->product_sub_type_code,
                            'method'            => 'Proposal Service',
                            'transaction_type'  => 'proposal',
                        ]);
                        $data = $get_response['response'];
                        
                        $proposal_response = json_decode($data,TRUE);
                       //print_r($proposal_response);
                       //die;
                        if(isset($proposal_response['PREMIUMDETAILS']['DATA']['PREMIUM']))
                        {

                            $is_breakin_case = (!$tp_only && $requestData->business_type == 'breakin' && $isInspectionWaivedOff == false) ? 'Y' : 'N';
                            $city_name = DB::table('master_city AS mc')
                                ->where('mc.city_name', $rto_data->city_name)
                                ->select('mc.zone_id')
                                ->first();

                            $car_tariff = DB::table('motor_tariff AS mt')
                                // ->whereRaw($mmv->engine_capacity_amount.' BETWEEN mt.cc_min and mt.cc_max')
                                ->whereRaw($vehicle_age . ' BETWEEN mt.age_min and mt.age_max')
                                ->where('mt.zone_id', $city_name->zone_id)
                                ->first();

                            $llpaiddriver_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS']);
                            $llpaidcleaner_conductor_premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['LLDriverConductorCleaner']);
                            $cover_pa_owner_driver_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER']);
                            $cover_pa_paid_driver_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER']);
                            $cover_pa_unnamed_passenger_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS']);
                            $voluntary_excess = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE']);
                            $anti_theft = 0;
                            $ic_vehicle_discount = 0;
                            $llPassengers = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['LEGALLIABILITY_TO_PASSENGERS'] ?? 0);
                            $ncb_discount =($tp_only ? 0 :round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS']));
                            $electrical_accessories_amt = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES']);
                            // $non_electrical_accessories_amt = 0;
                            $non_electrical_accessories_amt = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES']);
                            // $od = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES'] - $non_electrical_accessories_amt);
                            $od = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES']);
                            $tppd = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD']);
                            $geog_Extension_OD_Premium = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['OD_GEO_EXTENSION']);
                            $geog_Extension_TP_Premium = round($premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TP_GEO_EXTENSION']);
                            $tppd_discount = $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TPPDStatutoryDiscount'] ?? 0;
                            $cng_lpg = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT']);
                            $cng_lpg_tp = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG']);
                            if(get_parent_code($productData->product_sub_type_id) == 'PCV'){
                                $auto_acc_discount = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['AUTOMOBILE_ASSOCIATION_DISCOUNT']);
                                $totalOd = $od + $electrical_accessories_amt + $cng_lpg;
                                $otherDiscount = $voluntary_excess + $auto_acc_discount + $ic_vehicle_discount;
                                $ncb_discount = ($totalOd  - $otherDiscount)*$requestData->applicable_ncb/100;
                            }
                            $zero_depreciation = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'] ?? 0);
                            // $engine_protection = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR']);
                            // $ncb_protection = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NCB_PROTECTOR']);
                            // $key_replace = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['KEY_REPLACEMENT']);
                            // $tyre_secure = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYRE_COVER']);
                            // $return_to_invoice = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VEHICLE_REPLACEMENT_COVER']);
                            // $lopb = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['LOSS_OF_BAGGAGE']);
                            $imt23 = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYREMUDGUARD'] ?? 0);

                            // $addon_premium = $zero_depreciation + $engine_protection + $ncb_protection + $key_replace + $return_to_invoice + $tyre_secure + $lopb;

                            $additional_gvw = 0;
                            if(!empty($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW']) && $premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW'] != '0')
                            {
                                $additional_gvw = round($premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ADDITIONAL_GVW']);
                            }

                            $addon_premium = $zero_depreciation + $imt23 + ($additional_gvw ?? 0);
                            $od_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount;
                            $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt + $geog_Extension_OD_Premium - $od_discount;
                            $final_tp_premium = $llPassengers + $tppd + $cng_lpg_tp +$llPassengers + $llpaiddriver_premium + $cover_pa_owner_driver_premium + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $llpaidcleaner_conductor_premium + $geog_Extension_TP_Premium - $tppd_discount;
                            $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount + $tppd_discount;
                            $final_net_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM']);
                            $final_gst_amount = round($update_premium_response['PREMIUMDETAILS']['DATA']['PREMIUM'] - $update_premium_response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM']);

                            // if(get_parent_code($productData->product_sub_type_id) == 'PCV'){
                            //     $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount + $addon_premium);
                            //     $final_gst_amount = round($final_net_premium * 0.18);
                            //     $final_payable_amount  =  $final_net_premium + $final_gst_amount;
                            // }else{
                            //     $final_payable_amount = $update_premium_response['PREMIUMDETAILS']['DATA']['GROSS_PREMIUM'];
                            // }
                            $final_payable_amount = $update_premium_response['PREMIUMDETAILS']['DATA']['GROSS_PREMIUM'];
                            $vehicleDetails = [
                                'manf_name'             => $mmv->make,
                                'seating_capacity'      => $mmv->seating_capacity,
                                'carrying_capacity'     => $mmv->seating_capacity - 1,
                                'fuel_type'             => $mmv->fuel_type,
                                'vehicle_type'          => $mmv->vehicle_type,
                                'version_id'            => $mmv->ic_version_code,
                            ];

                            UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                ->update([
                                'od_premium'                    => round($final_od_premium),
                                'tp_premium'                    => round($final_tp_premium),
                                'ncb_discount'                  => round($ncb_discount),
                                'total_discount'                => round($final_total_discount),
                                'addon_premium'                 => round($addon_premium),
                                'total_premium'                 => $final_net_premium,
                                'service_tax_amount'            => $final_gst_amount,
                                'electrical_accessories'        => $electrical_accessories_amt,
                                'non_electrical_accessories'    => $non_electrical_accessories_amt,
                                'final_payable_amount'          => $final_payable_amount,
                                'cpa_premium'                   => $cover_pa_owner_driver_premium,
                                'proposal_no'                   => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                                'unique_proposal_id'            => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                                'version_no'                    => $update_premium_response['PREMIUMDETAILS']['DATA']['VERSION_NO'],
                                'policy_start_date'             => date('d-m-Y', strtotime($policy_start_date)),
                                'policy_end_date'               => date('d-m-Y', strtotime($policy_end_date)),
                                'ic_vehicle_details'            => $vehicleDetails,
                                'is_breakin_case'               => $is_breakin_case,
                            ]);

                            RoyalSundaramPremiumDetailController::savePremiumDetails($premWebServiceId);
                            
                            if ($is_breakin_case == 'Y') 
                            {
                                DB::table('cv_breakin_status')->insert([
                                    'user_proposal_id'      => $proposal->user_proposal_id,
                                    'ic_id'                 => $productData->company_id,
                                    'breakin_number'        => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                                    'breakin_status'        => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_status_final'  => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_check_url'     => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                    'created_at'            => date('Y-m-d H:i:s'),
                                    'updated_at'            => date('Y-m-d H:i:s')
                                ]);

                                updateJourneyStage([
                                    'user_product_journey_id'   => $proposal->user_product_journey_id,
                                    'ic_id'                     => $proposal->ic_id,
                                    'stage'                     => STAGE_NAMES['INSPECTION_PENDING'],
                                    'proposal_id'               => $proposal->user_proposal_id,
                                ]);
                            } 
                            else 
                            {
                                updateJourneyStage([
                                    'user_product_journey_id'   => customDecrypt($request['userProductJourneyId']),
                                    'ic_id'                     => $productData->company_id,
                                    'stage'                     => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                    'proposal_id'               => $proposal->user_proposal_id,
                                ]);
                            }
                            
                            return [
                                'status'    => true,
                                'msg'       => 'Proposal submitted successfully',
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'data'      => [
                                    'proposalId'            => $proposal->user_proposal_id,
                                    'proposalNo'            => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                                    'odPremium'             => $final_od_premium,
                                    'tpPremium'             => $final_tp_premium,
                                    'ncbDiscount'           => $ncb_discount,
                                    'totalPremium'          => $final_net_premium,
                                    'serviceTaxAmount'      => $final_gst_amount,
                                    'finalPayableAmount'    => $final_payable_amount,
                                    'is_breakin'            => $is_breakin_case,
                                    'inspection_number'     => $is_breakin_case == 'Y' ? $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'] : '',
                                    'kyc_url' => $kyc_url,
                                    'is_kyc_url_present' => $is_kyc_url_present,
                                    'kyc_message' => $kyc_message,
                                    'kyc_status' => $proposal->is_ckyc_verified == 'Y' ? true : $kyc_status,
                                ]
                            ];                            
                        }
                        else
                        {
                            return [
                                'status'    => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'premium'   => 0,
                                'message'   => $proposal_response['PREMIUMDETAILS']['Status']['Message'] ?? 'Insurer not reachable ( Proposal Service )'
                            ];                            
                        }
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium' => 0,
                            'message' => $update_premium_response['PREMIUMDETAILS']['Status']['Message'] ?? 'Insurer not reachable'
                        ];
                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium' => 0,
                        'message' => 'Insurer not reachable ( Update Premium Calculation )'
                    ];
                }
            }
            else
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
                'status' => false,
                'webservice_id' => $get_response['webservice_id'] ?? null, 
                'table' => $get_response['table'] ?? null,
                'premium' => 0,
                'message' => 'Insurer not reachable'
            ];            
        }
    }else{
        return response()->json([
            'status' => ($is_kyc_url_present ? true : false),
            'msg' => "Kyc verification false",
            'data' => [
                'proposalId' => $proposal->user_proposal_id,
                'userProductJourneyId' => $proposal->user_product_journey_id,
                'proposalNo' => $proposal->unique_proposal_id,
                'kyc_url' => $kyc_url,
                'is_kyc_url_present' => $is_kyc_url_present,
                'kyc_message' => $kyc_message,
                'kyc_status' => $kyc_status,
                'verification_status' => $kyc_status
            ]
        ]);
    }   
    }
}