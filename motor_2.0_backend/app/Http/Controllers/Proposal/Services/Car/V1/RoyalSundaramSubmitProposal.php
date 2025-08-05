<?php

namespace App\Http\Controllers\Proposal\Services\Car\V1;
include_once app_path().'/Helpers/CarWebServiceHelper.php';
include_once app_path().'/Helpers/IcHelpers/RoyalSundaramHelper.php';

use App\Models\UserProposal;
use App\Models\SelectedAddons;
use App\Models\CvBreakinStatus;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Car\RoyalSundaramPremiumDetailController;
use Illuminate\Http\Request;

class RoyalSundaramSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quote_data=json_decode($quote->premium_json,true);
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        
        $rto_code = $requestData->rto_code;  
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code    
        $rto_data = DB::table('royal_sundaram_rto_master AS rsrm')
            ->where('rsrm.rto_no', str_replace('-', '', $rto_code))
            ->first();

        if (empty($rto_data)) {
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

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [   
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];          
        }
      
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
            return [   
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
            ];        
        } elseif ($mmv->ic_version_code == 'DNE') {
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
        $vehicle_age = $age / 12;#floor($age / 12);
        $vehicle_age_for_addons = $vehicle_age;#(($age - 1) / 12);

        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
            $policy_start_date = date('Y-m-d');
        }

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $od_only = in_array($premium_type, ['own_damage','own_damage_breakin']);
        $type_of_cover = '';
        $cpa_tenure = '1';
        $is_previous_claim = $requestData->is_claim == 'Y' ? 'Yes' : 'No';

        if ($requestData->business_type == 'newbusiness') {
            $product_name = 'BrandNewCar';
            $type_of_cover = 'Bundled';
            $businessType = 'New Business';
            $cpa_tenure = '3';
            $previous_policy_type = '';
            $policy_start_date = date('Y-m-d');
            $tp_start_date =in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime($policy_start_date)) : '';
            $tp_end_date =in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime($tp_start_date))))) : '';
        } else if ($requestData->business_type == 'rollover') {
            $product_name = 'RolloverCar';
            $businessType = 'Roll Over';
            $type_of_cover = 'Comprehensive';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
            $previous_policy_type = (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive' : 'ThirdParty');
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
        } else if ($requestData->business_type == 'breakin') {
            $product_name = 'RolloverCar';#BreakinCar
            $businessType = 'Break-In';
            $type_of_cover = '';
            $policy_start_date = date('Y-m-d', strtotime('+3 day', strtotime(date('Y-m-d'))));
            $previous_policy_type = (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive' : 'ThirdParty');
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
            
        }

        if ($tp_only) {
            $type_of_cover = 'LiabilityOnly';
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
            $requestData->applicable_ncb = 0;
            $requestData->previous_ncb = 0;
        }

        if ($od_only) {
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
          
            $policy_start_date = date('d-m-Y 00:00:00',strtotime('+3 day'));
            $requestData->previous_policy_expiry_date = date('Y-m-d', strtotime('-120 days'));
            $proposal->prev_policy_expiry_date = date('d-m-Y', strtotime('-120 days'));
            $previous_policy_type = '';
            $requestData->applicable_ncb = 0;
        }

        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $requestData->applicable_ncb = $is_previous_claim == 'Yes' ? 0 : $requestData->applicable_ncb;

        $previous_insurer_addresss = '';
        $previous_insurer = DB::table('insurer_address')
            ->where('Insurer', $proposal->insurance_company_name)
            ->first();
        $previous_insurer = keysToLower($previous_insurer);
        if (!empty($previous_insurer && isset($previous_insurer->address_line_1))) {
            $previous_insurer_addresss = $previous_insurer->address_line_1.' '.$previous_insurer->address_line_2.', '. $previous_insurer->pin;
        }

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $hdn_ncb_protector = 'false';
        $hdn_key_replacement = 'false';
        $hdn_protector = 'false';
        $hdn_invoice_price = 'false';
        $hdn_loss_of_baggage = 'false';
        $hdn_tyre_cover = 'false';
        $hdn_wind_shield = 'false';
        $hdnRoadSideAssistanceCover = 'false';
        $opted_addons = [];
        $nilDepreciationCover = false;
        $consumableCover = "off";
        //rsa
        $default_rsa = "Yes";
        $rsa_plan_2 = "No";

        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                // if ($data['name'] == 'Engine Protector' && $vehicle_age < 10) {
                //     $hdn_protector = 'true';
                //     $opted_addons[] = 'AggravationCover';
                // }

                if ($data['name'] == 'Engine Protector') {
                    $hdn_protector = 'true';
                    $opted_addons[] = 'AggravationCover';
                }

                // if ($data['name'] == 'Zero Depreciation' && $vehicle_age <= 10) {
                //     $opted_addons[] = 'DepreciationWaiver';
                //     $nilDepreciationCover = true;
                // }

                if ($data['name'] == 'Zero Depreciation') {
                    $opted_addons[] = 'DepreciationWaiver';
                    $nilDepreciationCover = true;
                }

                // if ($data['name'] == 'Tyre Secure' && $vehicle_age_for_addons <= 7) {
                //     $hdn_tyre_cover = 'true';
                //     $opted_addons[] = 'TyreCoverClause';
                // }

                if ($data['name'] == 'Tyre Secure') {
                    $hdn_tyre_cover = 'true';
                    $opted_addons[] = 'TyreCoverClause';
                }

                // if ($data['name'] == 'Return To Invoice' && $vehicle_age_for_addons < 4 && $requestData->ownership_changed != 'Y') {
                //     $hdn_invoice_price = 'true';
                //     $opted_addons[] = 'InvoicePrice';
                // }

                if ($data['name'] == 'Return To Invoice' && $requestData->ownership_changed != 'Y') {
                    $hdn_invoice_price = 'true';
                    $opted_addons[] = 'InvoicePrice';
                }

                // if ($data['name'] == 'Loss of Personal Belongings' && $vehicle_age < 7) {
                //     $hdn_loss_of_baggage = 'true';
                //     $opted_addons[] = 'LossOfBaggage'; 
                // }

                if ($data['name'] == 'Loss of Personal Belongings') {
                    $hdn_loss_of_baggage = 'true';
                    $opted_addons[] = 'LossOfBaggage'; 
                }

                // if ($data['name'] == 'Key Replacement' && $vehicle_age < 7) {
                //     $hdn_key_replacement = 'true';
                //     $opted_addons[] = 'KeyReplacement';
                // }

                if ($data['name'] == 'Key Replacement') {
                    $hdn_key_replacement = 'true';
                    $opted_addons[] = 'KeyReplacement';
                }

                if ($data['name'] == 'NCB Protection') {
                    $hdn_ncb_protector = 'true';
                    $opted_addons[] = 'NCBProtector';
                }

                if ($data['name'] == 'Road Side Assistance (₹ 49)' || $data['name'] == 'Road Side Assistance') {
                    $hdnRoadSideAssistanceCover = 'true';
                    if ($productData->product_identifier == 'roadSideAssistancePlan2') {                        
                        $rsa_plan_2 = 'Yes';
                        $default_rsa = "No";
                    } 
                    // else {
                    //     $rsa_plan_2 = 'No';
                    //     $default_rsa = "Yes";
                    // }                    
                }
                // if ($data['name'] == 'Wind Shield' && ($requestData->business_type == 'newbusiness' || $interval->y < 7)) {
                //     $hdn_wind_shield = 'true';
                //     $opted_addons[] = 'WindShield';
                // }

                if ($data['name'] == 'Wind Shield') {
                    $hdn_wind_shield = 'true';
                    $opted_addons[] = 'WindShield';
                }

                if($data['name'] == 'Consumable')
                {
                   $consumableCover = "on";
                   $opted_addons[] = 'ConsumableCover';
                }
            }
        }

        if ($proposal->ownership_changed == 'Y') {
            $hdn_ncb_protector = 'false';
        }
        $add_ons_opted_in_previous_policy = !empty($opted_addons) ? implode(',', $opted_addons) : '';

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
        if($requestData->fuel_type == 'CNG' || $requestData->fuel_type == 'LPG')
        {
            $external_fuel_kit = 'Yes';
            $external_fuel_kit_amount = 0;
        }

        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
        $cover_ll_paid_driver = 'NO';

        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                    $cover_ll_paid_driver = 'YES';
                }
            }
        }

        //checking last addons
        $PreviousPolicy_IsZeroDept_Cover =  $is_breakin = false;
        if(env('APP_ENV') == 'local' && $requestData->business_type != 'newbusiness' && !in_array($premium_type ,['third_party', 'third_party_breakin']) )
        {
            if(!empty($proposal->previous_policy_addons_list))
            {
                $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
                foreach ($previous_policy_addons_list as $key => $value) {
                   if($key == 'zeroDepreciation' && $value)
                   {
                        $PreviousPolicy_IsZeroDept_Cover = true;  
                   }
                }                
            }
            if($nilDepreciationCover && !$PreviousPolicy_IsZeroDept_Cover)
            {
                $is_breakin = true;
            }
        }
        
        $cpa_selected = 'No';
        $cpa_reason = 'false';
        $prepolicy = (array) json_decode($proposal->additional_details);
        $prepolicy = !empty($prepolicy['prepolicy']) ? (array) $prepolicy['prepolicy'] : [];
        $cpa_companyName = !empty($proposal->cpa_ins_comp) ? $proposal->cpa_ins_comp : '';
        $expiryDate = !empty($proposal->cpa_policy_to_dt) ? $proposal->cpa_policy_to_dt : '';
        $policyNumber = !empty($proposal->cpa_policy_no) ? $proposal->cpa_policy_no : '';
        $standalonePAPolicy = 'false';
        $cpaCoverWithInternalAgent = 'false';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                if ($requestData->vehicle_owner_type == 'I') {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  {
                        $cpa_selected = 'Yes';
                        $cpa_tenure = isset($data['tenure']) ? (string) $data['tenure'] : '1';
                    } elseif (isset($data['reason']) && $data['reason'] != "") {
                        if ($data['reason'] == 'I do not have a valid driving license.') {
                            $cpa_reason = 'true';
                        }else if($data['reason'] == 'I have another PA policy with cover amount of INR 15 Lacs or more')
                        {
                            $standalonePAPolicy = 'true';
                        }
                        else if($data['reason'] == 'I have another motor policy with PA owner driver cover in my name')
                        {
                            $cpaCoverWithInternalAgent = 'true';
                            $cpa_companyName = !empty($cpa_companyName) ? $cpa_companyName : (!empty($prepolicy['CpaInsuranceCompany']) ? $prepolicy['CpaInsuranceCompany'] : '');
                            $expiryDate = !empty($expiryDate) ? date('d/m/Y', strtotime($expiryDate)) : (!empty($prepolicy['cpaPolicyEndDate']) ? date('d/m/Y', strtotime($prepolicy['cpaPolicyEndDate'])) : '');
                            $policyNumber = !empty($policyNumber) ? $policyNumber : (!empty($prepolicy['cpaPolicyNumber']) ? $prepolicy['cpaPolicyNumber'] : '');
                        } else {
                            $cpa_companyName = !empty($cpa_companyName) ? $cpa_companyName : (!empty($prepolicy['CpaInsuranceCompany']) ? $prepolicy['CpaInsuranceCompany'] : '');
                            $expiryDate = !empty($expiryDate) ? $expiryDate : (!empty($prepolicy['cpaPolicyEndDate']) ? $prepolicy['cpaPolicyEndDate'] : '');
                            $policyNumber = !empty($policyNumber) ? $policyNumber : (!empty($prepolicy['cpa_policy_number']) ? $prepolicy['cpa_policy_number'] : '');
                        }
                    }
                }
            }
        }

        $voluntary_excess_amt = 0;
        $TPPDCover = '';

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                    $voluntary_excess_amt = $data['sumInsured'];
                }
                if ($data['name'] == 'TPPD Cover') {
                    $TPPDCover = '6000';
                }
            }
        }
        $premium_request=[];
        // $premium_request = [
        //     'authenticationDetails' => [
        //         'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_MOTOR'),
        //         'apikey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_MOTOR')
        //     ],
        //     'isNewUser' => 'Yes',
        //     'isproductcheck' => 'true',
        //     'istranscheck' => 'true',
        //     'premium' => '0.0',
        //     'proposerDetails' => [
        //         'addressOne' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line1 : $proposal->car_registration_address1,
        //         'addressTwo' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
        //         'addressThree' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line1 : $proposal->car_registration_address3,
        //         'addressFour' => '',
        //         'contactAddress1' => $proposal->address_line1,
        //         'contactAddress2' => $proposal->address_line2,
        //         'contactAddress3' => $proposal->address_line3,
        //         'contactAddress4' => '',
        //         'contactCity' => $proposal->city,
        //         'contactPincode' => $proposal->pincode,
        //         'dateOfBirth' => $requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : '18/09/1995',
        //         'guardianAge' => '',
        //         'guardianName' => '',
        //         'nomineeAge' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_age : '',
        //         'nomineeName' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_name : '',
        //         'occupation' => $proposal->occupation_name,
        //         'regCity' => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city,
        //         'regPinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
        //         'relationshipWithNominee' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_relationship : '',
        //         'relationshipwithGuardian' => '',
        //         'same_addr_reg' => $proposal->is_car_registration_address_same == 1 ? 'Yes' : 'No',
        //         'strEmail' => $proposal->email,
        //         'strFirstName' => $proposal->first_name,
        //         'strLastName' => $proposal->last_name,
        //         'strMobileNo' => $proposal->mobile_number,
        //         'strPhoneNo' => '',
        //         'strStdCode' => '',
        //         'strTitle' => $requestData->vehicle_owner_type == 'C' ? 'Mr/Ms' : ($proposal->gender == 'M' ? 'Mr' : 'Ms'),
        //         'userName' => $proposal->email,
        //         'GSTIN' => $proposal->gst_number
        //     ],
        //     'reqType' => 'XML',
        //     'respType' => 'XML',
        //     'vehicleDetails' => [
        //         'GSTIN' => $proposal->gst_number,
        //         'accidentcoverforpaiddriver' => $cover_pa_paid_driver_amt,
        //         'addonValue' => $external_fuel_kit == 'Yes' ? $external_fuel_kit_amount : '0',
        //         'automobileAssociationMembership' => 'No',
        //         'averageMonthlyMileageRun' => '',
        //         'carRegisteredCity' => $rto_data->city_name,
        //         'RTO_NAME' => $rto_data->rto_name,
        //         'addOnsOptedInPreviousPolicy' => $add_ons_opted_in_previous_policy,
        //         'validPUCAvailable' => $proposal->is_valid_puc == '1' ? 'Yes' : 'No',
        //         'pucnumber' => '',
        //         'pucvalidUpto' => '',
        //         'chassisNumber' => $proposal->chassis_number,
        //         'claimAmountReceived' => $requestData->is_claim == 'Y' ? '50000' : '0',
        //         'claimsMadeInPreviousPolicy' => $requestData->is_claim == 'Y' ? 'Yes' : 'No',
        //         'claimsReported' => $requestData->is_claim == 'Y' ? '3' : '0',
        //         'companyNameForCar' => $requestData->vehicle_owner_type == 'I' ? '' : $proposal->first_name,
        //         'cover_dri_othr_car_ass' => 'Yes',
        //         'cover_elec_acc' => $electrical_accessories,
        //         'cover_non_elec_acc' => $non_electrical_accessories,
        //         'depreciationWaiver' => 'off',
        //         'drivingExperience' => '1',
        //         'electricalAccessories' => [
        //             'electronicAccessoriesDetails' => [
        //                 'makeModel' => 'MCJYzzJyZd',
        //                 'nameOfElectronicAccessories' => 'COJuOtbUaF',
        //                 'value' => $electrical_accessories_value,
        //             ]
        //         ],
        //         'engineCapacityAmount' => $mmv->engine_capacity_amount.' CC',
        //         'engineNumber' => $proposal->engine_number,
        //         'engineprotector' => 'off',
        //         'fibreGlass' => 'No',
        //         'fuelType' => $mmv->fuel_type,
        //         'financierName' => $proposal->is_vehicle_finance == 1 ? $proposal->name_of_financer : '',
        //         'financier_agreement_type' => $proposal->financer_agreement_type,
        //         'financier_city' => $proposal->financer_location,
        //         'hdnDepreciation' => $productData->zero_dep == '1' ? 'false' : 'true',
        //         // 'hdnInvoicePrice' => $hdn_invoice_price,
        //         "hdnVehicleReplacementCover"=>$hdn_invoice_price,
        //         "vehicleReplacementCover"=>"Yes",
        //         "fullInvoicePrice"=>"No",
        //         "fullInvoicePriceRoadtax"=>"No",              
        //         "fullInvoicePriceRegCharges"=>"No",              
        //         "fullInvoicePriceInsuranceCost"=>($hdn_invoice_price=='true' ? 'Yes' : "No"),
        //         'hdnKeyReplacement' => $hdn_key_replacement,
        //         'hdnLossOfBaggage' => $hdn_loss_of_baggage,
        //         'hdnNCBProtector' => $hdn_ncb_protector,
        //         'hdnProtector' => $hdn_protector,
        //         'hdnRoadTax' => 'false',
        //         'hdnSpareCar' => 'false',
        //         'hdnWindShield' => 'false',
        //         'hdnTyreCover' => $hdn_tyre_cover,
        //         'idv' => $quote->idv,
        //         'invoicePrice' => 'off',
        //         'isBiFuelKit' => $external_fuel_kit,
        //         'isBiFuelKitYes' => $external_fuel_kit == 'Yes' ? 'ADD ON' : '',
        //         'isCarFinanced' => $proposal->is_vehicle_finance == 1 ? 'Yes' : 'No',
        //         'isCarFinancedValue' => $proposal->is_vehicle_finance == 1 ? $proposal->financer_agreement_type : '',
        //         'isCarOwnershipChanged' => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
        //         'isPreviousPolicyHolder' => $requestData->business_type == 'newbusiness' ? 'false' : 'true',
        //         'keyreplacement' => $hdn_key_replacement == 'true' ? 'on' : 'off',
        //         'legalliabilitytopaiddriver' => $cover_ll_paid_driver,
        //         'modified_idv_value' => $quote->idv,
        //         'modify_your_idv' => '0',
        //         'ncbcurrent' => $requestData->ownership_changed == 'Y' ? '0' : $requestData->applicable_ncb,
        //         'ncbprevious' => $requestData->ownership_changed == 'Y' ? '0' : $requestData->previous_ncb,
        //         'ncbprotector' => 'off',
        //         'noClaimBonusPercent' => $requestData->ownership_changed == 'Y' ? '0' : $ncb_levels[$requestData->applicable_ncb],
        //         'nonElectricalAccesories' => [
        //             'nonelectronicAccessoriesDetails' => [
        //                 'makeModel' => 'xcnJLzqamh',
        //                 'nameOfElectronicAccessories' => 'ItNMnantjt',
        //                 'value' => $non_electrical_accessories_value,
        //             ]
        //         ],
        //         'original_idv' => $quote->idv,
        //         'personalaccidentcoverforunnamedpassengers' => $cover_pa_unnamed_passenger_amt,
        //         'policyED' => date('d/m/Y', strtotime($policy_end_date)),
        //         'policySD' => date('d/m/Y', strtotime($policy_start_date)),
        //         'previousInsurerName' => $proposal->insurance_company_name,
        //         'previousPolicyExpiryDate' => date('d/m/Y', strtotime($proposal->prev_policy_expiry_date)),
        //         'previousPolicyType' => $previous_policy_type,
        //         'previousinsurersCorrectAddress' => $requestData->business_type != 'newbusiness' ? $previous_insurer_addresss : '',
        //         'previuosPolicyNumber' => $proposal->previous_policy_number,
        //         'ProductName' => $product_name,
        //         'region' => $region->region, //South Region
        //         'registrationNumber' => str_replace('-', '', $proposal->vehicale_registration_number),
        //         'registrationchargesRoadtax' => 'off',
        //         'spareCar' => 'off',
        //         'spareCarLimit' => '0',
        //         'totalIdv' => $quote->idv,
        //         'tppdLimit' => $TPPDCover,
        //         'valueOfLossOfBaggage' => $hdn_loss_of_baggage == 'true' ? '2500' : '0',
        //         'valueofelectricalaccessories' => $electrical_accessories_value,
        //         'valueofnonelectricalaccessories' => $non_electrical_accessories_value,
        //         'vehicleManufacturerName' => $mmv->make,
        //         'vehicleModelCode' => $mmv->model_code,
        //         'vehicleMostlyDrivenOn' => 'City roads',
        //         'vehicleRegisteredInTheNameOf' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
        //         'vehicleSubLine' => 'privatePassengerCar',
        //         'vehicleregDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
        //         'voluntarydeductible' => $voluntary_excess_amt,
        //         'windShieldGlass' => 'off',
        //         'yearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
        //         'typeOfCover' => $type_of_cover,
        //         'policyTerm' =>  '1',
        //     ]
        // ];

        if (!$od_only) {
            $premium_request['vehicleDetails']['cpaCoverisRequired'] = $cpa_selected;
            $premium_request['vehicleDetails']['cpaPolicyTerm'] = $proposal->owner_type == 'I' ? $cpa_tenure : '';
            $premium_request['vehicleDetails']['cpaCoverDetails'] = [
                'noEffectiveDrivingLicense' => $cpa_reason,
                'cpaCoverWithInternalAgent' => $cpaCoverWithInternalAgent,
                'standalonePAPolicy' => $standalonePAPolicy,
                'companyName' => $cpa_companyName,
                'expiryDate' => $expiryDate,
                'policyNumber' => $policyNumber,
            ];
        }

        if ($od_only) {
            $tp_insurance_number = (!empty($proposal->tp_insurance_number)) ? $proposal->tp_insurance_number : (!empty($prepolicy['tpInsuranceNumber']) ? $prepolicy['tpInsuranceNumber']: '');
            $tp_insurance_company = (!empty($proposal->tp_insurance_company)) ? $proposal->tp_insurance_company : (!empty($prepolicy['tpInsuranceCompany']) ? $prepolicy['tpInsuranceCompany']: '');
            $tp_start_date = (!empty($proposal->tp_start_date)) ? $proposal->tp_start_date : (!empty($prepolicy['tpStartDate']) ? $prepolicy['tpStartDate']: '');
            $tp_end_date = (!empty($proposal->tp_end_date)) ? $proposal->tp_end_date : (!empty($prepolicy['tpEndDate']) ? $prepolicy['tpEndDate']: '');

            $premium_request['existingTPPolicyDetails'] = [
                'tpPolicyNumber' => $tp_insurance_number,
                'tpInsurer' => $tp_insurance_company,
                'tpInceptionDate' => date('d/m/Y', strtotime($tp_start_date)),
                'tpExpiryDate' => date('d/m/Y', strtotime($tp_end_date)),
                'tpPolicyTerm' => '3',
            ];
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

            
           

    
        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
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

        if(config('IC.ROYAL_SUNDARAM.V1.CAR.IS_POS_TESTING_MODE_ENABLE') == 'Y')
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
        if(config('constants.motorConstant.FIFTYLAKH_IDV_RESTRICTION_APPLICABLE') == 'Y' && $quote->idv >= 5000000){
            $POSPCode = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
            $update_premium_request['isPosOpted'] = false;
            $update_premium_request['posCode'] = '';
            $update_premium_request['posDetails'] = [
                        'name' => null,
                        'pan' => null,
                        'aadhaar' => null,
                        'mobile' => null,
                        'licenceExpiryDate' => null,
            ];
        }

        // $data = getWsData(config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_MOTOR_PREMIUM'), $premium_request, 'royal_sundaram', [
        //     'enquiryId' => $proposal->user_product_journey_id,
        //     'requestMethod' =>'post',
        //     'productName'  => $productData->product_name. " ($businessType)",
        //     'company'  => 'royal_sundaram',
        //     'section' => $productData->product_sub_type_code,
        //     'method' =>'Premium Calculation',
        //     'transaction_type' => 'proposal',
        //     'root_tag' => 'CALCULATEPREMIUMREQUEST',
        //     'headers' => [
        //             'Content-Type'=>'application/xml'
        //     ]
        // ]);

        //By enabling this, ckyc will verified on first card instead of proposal submission
        $ckycProposal = config('IC.ROYAL_SUNDARAM.V1.CAR.ENABLE_CKYC_ON_FIRST_CARD') != 'Y';

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
        if($proposal->is_ckyc_verified != 'Y' && $ckycProposal)
        {
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
                        'unique_quote_id' => $quote_data['quoteId'],
                        'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    ];
                } else {
                    $request_data = [
                        'companyAlias' => 'royal_sundaram',
                        'mode' =>  $proposal->ckyc_type == 'ckyc_number' ? 'ckyc_number' : 'pan_number_with_dob',
                        'unique_quote_id' => $quote_data['quoteId'],
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
                    UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'proposal_no' => $quote_data['quoteId'],
                    ]);
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
    
                    /* UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'additional_details_data' => $response['data']['redirection_url'],
                        ]); */
                }
            }
        }
        $proposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)->first();

        $registration_number = $proposal->vehicale_registration_number;
        $registration_number = explode('-', $registration_number);

        if ($registration_number[0] == 'DL') {
            $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true); 
            $registration_number = $registration_no.'-'.$registration_number[2].'-'.$registration_number[3];
        } else {
            $registration_number = $proposal->vehicale_registration_number;
        }
        
        if($kyc_status || $proposal->is_ckyc_verified == 'Y')
        {
            $premium_response_idv =(!$tp_only) ? round($quote_data['modifiedIdv']) : 0;

            $companyName = [];

            if ($requestData->vehicle_owner_type == 'C' && !empty($proposal->first_name)) {
                $companyName = array_values(getAddress([
                    'address' => $proposal->first_name,
                    'address_1_limit' => 30,
                    'address_2_limit' => 30
                ]));
            }

            /* $permanent_address = json_decode($proposal->proposer_ckyc_details?->permanent_address, true);

            if ( ! empty($permanent_address['permanent_address'])) {
                $per_address_data = [
                    'address' => $permanent_address['permanent_address'],
                    'address_1_limit' => 30,
                    'address_2_limit' => 30,
                    'address_3_limit' => 30
                ];

                $getPerAddress = getAddress($per_address_data);
            } */

            if ($requestData->fuel_type == 'PETROL' ) {
             
                $fuelType = $external_fuel_kit == "Yes" ? 'ADD ON' : '';
            } else {
                $fuelType = 'InBuilt';
            }
                $update_premium_request = [
                    'authenticationDetails' => [
                        'agentId' => config('IC.ROYAL_SUNDARAM.V1.CAR.AGENTID'),
                        'apikey' => config('IC.ROYAL_SUNDARAM.V1.CAR.APIKEY')
                    ],
                    'quoteId' =>$quote_data['quoteId'],
                    'isNewUser' => $requestData->business_type == 'newbusiness' ? 'Yes' : 'No',
                    'isproductcheck' => 'true',
                    'istranscheck' => 'true',
                    'premium' =>  $quote_data['pREMIUM'],
                    'proposerDetails' => [
                        'addressOne' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line1 : $proposal->car_registration_address1,
                        'addressTwo' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
                        'addressThree' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line3 : $proposal->car_registration_address3,
                        'addressFour' => '',
                        'contactAddress1' => $proposal->address_line1,
                        'contactAddress2' => $proposal->address_line2,
                        'contactAddress3' => $proposal->address_line3,
                        'contactAddress4' => '',
                        'contactCity' => $proposal->city,//$rto_data->city_name,
                        'contactPincode' => $proposal->pincode,
                        'dateOfBirth' => $requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : '18/09/1995',
                        'guardianAge' => '',
                        'guardianName' => '',
                        'nomineeAge' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_age : '',
                        'nomineeName' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_name : '',
                        'occupation' => $proposal->occupation_name,
                        'regCity' => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city,
                        'regPinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                        'relationshipWithNominee' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_relationship : '',
                        'relationshipwithGuardian' => '',
                        'same_addr_reg' => $proposal->is_car_registration_address_same == 1 ? 'Yes' : 'No',
                        'strEmail' => $proposal->email,
                        'strFirstName' => ! empty($companyName) && isset($companyName[0]) ? removeSpecialCharactersFromString($companyName[0], true) : removeSpecialCharactersFromString($proposal->first_name, true),
                        'strLastName' => $requestData->vehicle_owner_type == 'I' ? $proposal->last_name : ( ! empty($companyName) && isset($companyName[1]) ? $companyName[1] : ''),
                        'strMobileNo' => $proposal->mobile_number,
                        'strPhoneNo' => '',
                        'strStdCode' => '',
                        'strTitle' => $proposal->gender == 'M' ? 'Mr' : 'Ms',
                        'userName' => $proposal->email,
                        'panNumber' => $proposal->pan_number,
                        'aadharNumber' => '',
                        'GSTIN' => $proposal->gst_number
                    ],
                    'reqType' => 'XML',
                    'respType' => 'XML',
                    'vehicleDetails' => [
                        'GSTIN' => $proposal->gst_number,
                        'accidentcoverforpaiddriver' => $cover_pa_paid_driver_amt,
                        'addonValue' => $requestData->fuel_type == 'CNG' ? '' : $external_fuel_kit_amount,
                        'automobileAssociationMembership' => 'No',
                        'averageMonthlyMileageRun' => '',
                        'carRegisteredCity' => $rto_data->city_name,
                        'rtoName' => $rto_data->rto_name,
                        'addOnsOptedInPreviousPolicy' => ($PreviousPolicy_IsZeroDept_Cover) ? 'DepreciationWaiver' : $add_ons_opted_in_previous_policy,
                        'validPUCAvailable' => $proposal->is_valid_puc == '1' ? 'Yes' : 'No',
                        'pucnumber' => $proposal->is_valid_puc == '1' ? $proposal->puc_no : '',
                        'pucvalidUpto' => $proposal->is_valid_puc == '1' && !empty($proposal->puc_expiry) ? date('d/m/Y', strtotime($proposal->puc_expiry)) : '',
                        'chassisNumber' => $proposal->chassis_number,
                        'claimAmountReceived' => $requestData->is_claim == 'Y' ? '50000' : '0',
                        'claimsMadeInPreviousPolicy' => $requestData->is_claim == 'Y' ? 'Yes' : 'No',
                        'claimsReported' => $requestData->is_claim == 'Y' ? '3' : '0',
                        'companyNameForCar' => $requestData->vehicle_owner_type == 'I' ? '' : removeSpecialCharactersFromString($proposal->first_name , true) ,
                        'cover_dri_othr_car_ass' => 'Yes',
                        'cover_elec_acc' => $electrical_accessories,
                        'cover_non_elec_acc' => $non_electrical_accessories,
                        'depreciationWaiver' => $productData->zero_dep == '0' ? 'On' : 'off',
                        'drivingExperience' => '1',
                        'isValidDrivingLicenseAvailable' =>  $cpa_reason == 'true' ? 'No' :'Yes',
                        'electricalAccessories' => [
                            'electronicAccessoriesDetails' => [
                                'makeModel' => '',
                                'nameOfElectronicAccessories' => '',
                                'value' => $electrical_accessories_value
                            ]
                        ],
                        'engineCapacityAmount' => $mmv->engine_capacity_amount.' CC',
                        'engineNumber' => $proposal->engine_number,
                        'engineprotector' => 'off',
                        'fibreGlass' => 'No',
                        'fuelType' => $mmv->fuel_type,
                        'financierName' => $proposal->is_vehicle_finance == 1 ? $proposal->name_of_financer : '',
                        'financier_agreement_type' => $proposal->financer_agreement_type,
                        'financier_city' => $proposal->financer_location,
                        'hdnDepreciation' => $nilDepreciationCover  ? 'true' : 'false',
                        // 'hdnInvoicePrice' => $hdn_invoice_price,
                        "hdnVehicleReplacementCover"=>$hdn_invoice_price,
                        "vehicleReplacementCover"=> $hdn_invoice_price == 'true' ? "Yes":"No",
                        "fullInvoicePrice"=>"No",
                        "fullInvoicePriceRoadtax"=>"No",              
                        "fullInvoicePriceRegCharges"=>"No",              
                        "fullInvoicePriceInsuranceCost"=>$hdn_invoice_price == 'true' ? "Yes":"No",
                        'hdnKeyReplacement' => $hdn_key_replacement,
                        'hdnLossOfBaggage' => $hdn_loss_of_baggage,
                        'lossOfBaggage'=> $hdn_loss_of_baggage == true ? 'on' : 'off',
                        'hdnNCBProtector' => $hdn_ncb_protector,
                        'hdnProtector' => $hdn_protector,
                        'hdnRoadTax' => 'false',
                        'hdnSpareCar' => 'false',
                        'hdnWindShield' => $hdn_wind_shield,#$productData->zero_dep == '0' ? 'true' :
                        'hdnTyreCover' => $hdn_tyre_cover,
                        'hdnRoadSideAssistanceCover' => $hdnRoadSideAssistanceCover,// As confirmed from IC RSA will be provided to all ages
                        'roadSideAssistancePlan1' => $rsa_plan_2,
                        'roadSideAssistancePlan2' => $default_rsa,
                        // 'idv' => $premium_response_idv,
                        'invoicePrice' => 'off',
                        'isBiFuelKit' => $external_fuel_kit,
                        'isBiFuelKitYes' => $fuelType,
                        'isCarFinanced' => $proposal->is_vehicle_finance == 1 ? 'Yes' : 'No',
                        'isCarFinancedValue' => $proposal->is_vehicle_finance == 1 ? $proposal->financer_agreement_type : '',
                        'isCarOwnershipChanged' => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
                        'ownerSerialNumber' => $requestData->ownership_changed == 'Y' ? '2' : '1',
                        'isPreviousPolicyHolder' => $requestData->business_type == 'newbusiness' ? 'false' : 'true',
                        'keyreplacement' => $hdn_key_replacement == 'true' ? 'on' : 'off',
                        'legalliabilitytopaiddriver' => $cover_ll_paid_driver,
                        'modified_idv_value' => $premium_response_idv,
                        'modify_your_idv' => 0,
                        'ncbcurrent' => $requestData->ownership_changed == 'Y' ? '0' : $requestData->applicable_ncb,
                        'ncbprevious' => $requestData->ownership_changed == 'Y' ? '0' : $requestData->previous_ncb,
                        'ncbprotector' => 'off',
                        'noClaimBonusPercent' => $requestData->ownership_changed == 'Y' ? '0' : ($ncb_levels[$requestData->applicable_ncb] ?? '0'),
                        'nonElectricalAccesories' => [
                            'nonelectronicAccessoriesDetails' => [
                                'makeModel' => '',
                                'nameOfElectronicAccessories' => '',
                                'value' => $non_electrical_accessories_value
                            ]
                        ],
                        'original_idv' =>$quote_data['originalIdv'],
                        'personalaccidentcoverforunnamedpassengers' => $cover_pa_unnamed_passenger_amt,
                        'policyED' => date('d/m/Y', strtotime($policy_end_date)),
                        'policySD' => date('d/m/Y', strtotime($policy_start_date)),
                        'previousInsurerName' => $requestData->previous_policy_type == "Not sure" ? "" : $proposal->previous_insurance_company,#$proposal->insurance_company_name,
                        'previousPolicyExpiryDate' => (isset($proposal->prev_policy_expiry_date) && !empty($proposal->prev_policy_expiry_date)) ? date('d/m/Y', strtotime($proposal->prev_policy_expiry_date)) : '',
                        'previousPolicyType' => $previous_policy_type,
                        'previousinsurersCorrectAddress' => $requestData->business_type != 'newbusiness' || !$requestData->previous_policy_type == "Not sure" ? $previous_insurer_addresss : '',
                        'previuosPolicyNumber' => $requestData->previous_policy_type == "Not sure" ? "" : $proposal->previous_policy_number,
                        'ProductName' => $product_name,
                        'region' => $region->region,
                        'registrationNumber' => $registration_number == 'NEW' ? '' : str_replace('-', '', $registration_number),
                        'registrationchargesRoadtax' => 'off',
                        'spareCar' => 'off',
                        'spareCarLimit' => '0',
                        // 'totalIdv' => $premium_response_idv,
                        'tppdLimit' => $TPPDCover,
                        'valueOfLossOfBaggage' => '2500',
                        'valueofelectricalaccessories' => $electrical_accessories_value,
                        'valueofnonelectricalaccessories' => $non_electrical_accessories_value,
                        'vehicleManufacturerName' => $mmv->make,
                        'vehicleModelCode' => $mmv->model_code,
                        'vehicleMostlyDrivenOn' => 'City roads',
                        'vehicleRegisteredInTheNameOf' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
                        'vehicleSubLine' => 'privatePassengerCar',
                        'vehicleregDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'voluntarydeductible' => $voluntary_excess_amt,
                        'windShieldGlass' => 'off',
                        'yearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                        'typeOfCover' => $type_of_cover,
                        'policyTerm' => '1',
                        'consumableCover' => $consumableCover,
                    ]
                ];

                if(!in_array($premium_type, ['third_party', 'third_party_breakin']) && $hdnRoadSideAssistanceCover == 'true')
                {
                    $update_premium_request['vehicleDetails']['roadSideAssistanceCover'] = 'on';
                }

                if (!$od_only) {
                    $update_premium_request['vehicleDetails']['cpaCoverisRequired'] = $cpa_selected;
                    $update_premium_request['vehicleDetails']['cpaPolicyTerm'] = $proposal->owner_type == 'I' ? $cpa_tenure : '';
                    $update_premium_request['vehicleDetails']['cpaCoverDetails'] = [
                        'noEffectiveDrivingLicense' => $cpa_reason,
                        'cpaCoverWithInternalAgent' => $cpaCoverWithInternalAgent,
                        'standalonePAPolicy' => $standalonePAPolicy,
                        'companyName' => $cpa_companyName,#implode(' ',$companyName),
                        'expiryDate' => $expiryDate,
                        'policyNumber' => $policyNumber,
                    ];
                }

                if ($od_only) {
                    $update_premium_request['existingTPPolicyDetails'] = $premium_request['existingTPPolicyDetails'];
                }

                $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                $pos_data = DB::table('cv_agent_mappings')
                    ->where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('seller_type','P')
                    ->first();
            
                 

                if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
                    $POSPCode = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
                    $update_premium_request['isPosOpted'] = 'Yes';
                    $update_premium_request['posCode'] = '';
                    $update_premium_request['posDetails'] = [
                        'name' => removeSpecialCharactersFromString($pos_data->agent_name, true),
                        'pan' => $POSPCode,
                        'aadhaar' => $pos_data->aadhar_no,
                        'mobile' => $pos_data->agent_mobile,
                        'licenceExpiryDate' => '31/12/2050',
                    ];
                }

                if(config('IC.ROYAL_SUNDARAM.V1.CAR.IS_POS_TESTING_MODE_ENABLE') == 'Y')
                {
                    $update_premium_request['isPosOpted'] = 'Yes';
                    $update_premium_request['posCode'] = '';
                    $update_premium_request['posDetails'] = [
                        'name' => 'Agent',
                        'pan' => 'ABGTY8890Z',
                        'aadhaar' => '569278616999',
                        'mobile' => '8850386204',
                        'licenceExpiryDate' => '31/12/2050',
                    ];
                }
                if(config('constants.motorConstant.FIFTYLAKH_IDV_RESTRICTION_APPLICABLE') == 'Y' && $quote->idv >= 5000000){
                    $POSPCode = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
                    $update_premium_request['isPosOpted'] = false;
                    $update_premium_request['posCode'] = '';
                    $update_premium_request['posDetails'] = [
                                'name' => null,
                                'pan' => null,
                                'aadhaar' => null,
                                'mobile' => null,
                                'licenceExpiryDate' => null,
                    ];
                }

                $documentData = getMandatoryDocumentData($proposal);

                $update_premium_request['proposerDetails'] = array_merge(
                    $update_premium_request['proposerDetails'],
                    $documentData
                );
               
                $get_response = getWsData(config('IC.ROYAL_SUNDARAM.V1.CAR.UPDATEVEHICLEDETAILS_END_POINT_URL'), $update_premium_request, 'royal_sundaram', [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name. " ($businessType)",
                    'company'  => 'royal_sundaram',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Update Premium Calculation',
                    'transaction_type' => 'proposal',
                    'root_tag' => 'CALCULATEPREMIUMREQUEST',
                    'headers' => [
                            'Content-Type'=>'application/xml'
                    ]
                ]);

                $data = $get_response['response'];
                $webserviceId = $get_response['webservice_id'];

                if ($data) {
                    $update_premium_response = json_decode($data, TRUE);

                    if(isset($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && $update_premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'E-0521')
                    {
                        $registration_number = $proposal->vehicale_registration_number;
                        $registration_number = explode('-', $registration_number);
                        $special_reg_no = str_replace('0','',$registration_number[1]);
                        $update_premium_request['vehicleDetails']['registrationNumber'] = $registration_number[0].$special_reg_no.$registration_number[2].$registration_number[3];
                        $get_response2 = getWsData(config('IC.ROYAL_SUNDARAM.V1.CAR.UPDATEVEHICLEDETAILS_END_POINT_URL'), $update_premium_request, 'royal_sundaram', [
                            'enquiryId' => $proposal->user_product_journey_id,
                            'requestMethod' =>'post',
                            'productName'  => $productData->product_name. " ($businessType) " .$update_premium_request['vehicleDetails']['registrationNumber'],
                            'company'  => 'royal_sundaram',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Update Premium Calculation',
                            'transaction_type' => 'proposal',
                            'root_tag' => 'CALCULATEPREMIUMREQUEST',
                            'headers' => [
                                    'Content-Type'=>'application/xml'
                            ]
                        ]);

                        $data2 = $get_response2['response'];
                        if ($data2) 
                        {
                            $webserviceId = $get_response2['webservice_id'];
                            $update_premium_response = json_decode($data2, TRUE);
                        }
                    }

                    if (isset($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && ($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0005') || (($requestData->business_type == 'breakin') && ($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002'))) {
                        $is_breakin_case = (!$tp_only && ($requestData->business_type == 'breakin' || ($is_breakin))) ? 'Y' : 'N';
                        $city_name = DB::table('master_city AS mc')
                            ->where('mc.city_name', $rto_data->city_name)
                            ->select('mc.zone_id')
                            ->first();

                        $car_tariff = DB::table('motor_tariff AS mt')
                            ->whereRaw($mmv->engine_capacity_amount.' BETWEEN mt.cc_min and mt.cc_max')
                            ->whereRaw($vehicle_age . ' BETWEEN mt.age_min and mt.age_max')
                            ->where('mt.zone_id', $city_name->zone_id)
                            ->first();

                        $llpaiddriver_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS']);
                        $cover_pa_owner_driver_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER']);
                        $cover_pa_paid_driver_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER']);
                        $cover_pa_unnamed_passenger_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS']);
                        $voluntary_excess = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE']);
                        $anti_theft = 0;
                        $ic_vehicle_discount = 0;
                        $ncb_discount =($tp_only ? 0 :round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS']));
                        $electrical_accessories_amt = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES']);
                        // $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' && !$tp_only ? $non_electrical_accessories_value * ($car_tariff->rate_per_thousand/100) : 0;
                        $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' && !$tp_only ? round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES']) : 0;
                        // $od = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES'] - $non_electrical_accessories_amt);
                        $od = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES']);
                        $tppd = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD']);
                        $tppd_discount = 0;
                        if (!empty($TPPDCover)) {
                            //$tppd_discount = 100*$cpa_tenure;
                            $tppd_discount = 100 * ($requestData->business_type == 'newbusiness' ? 3 : 1);
                            $tppd += $tppd_discount;
                        }
                        $cng_lpg = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT']);
                        $cng_lpg_tp = round($update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG']);

                        $zero_depreciation = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'] ?? 0);
                        $rsa = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDE_ASSISTANCE_COVER'] ?? 0);
                        $engine_protection = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR']);
                        $ncb_protection = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NCB_PROTECTOR']);
                        $key_replace = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['KEY_REPLACEMENT']);
                        $tyre_secure = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TYRE_COVER']);
                        $return_to_invoice = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VEHICLE_REPLACEMENT_COVER']);
                        $lopb = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['LOSS_OF_BAGGAGE']);
                        $Consumable_cover = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['CONSUMABLE_COVER']);

                        $addon_premium = $zero_depreciation + $engine_protection + $ncb_protection + $key_replace + $return_to_invoice + $tyre_secure + $lopb + $rsa + $Consumable_cover;
                        $final_od_premium =  $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TOTAL_OD_PREMIUM']; //$od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
                        $final_tp_premium =  $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TOTAL_LIABILITY_PREMIUM']; //$tppd + $cng_lpg_tp + $llpaiddriver_premium + $cover_pa_owner_driver_premium + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium;
                        $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount + $tppd_discount;
                        $final_net_premium = round($update_premium_response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM']);
                        $final_gst_amount = round($update_premium_response['PREMIUMDETAILS']['DATA']['PREMIUM'] - $update_premium_response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM']);
                        $final_payable_amount = round($update_premium_response['PREMIUMDETAILS']['DATA']['PREMIUM']);

                        $vehicleDetails = [
                            'manf_name' => $mmv->make,
                            'model_name' => $mmv->model_name,
                            'version_name' => $mmv->model_name,
                            'seating_capacity' => $mmv->seating_capacity,
                            'carrying_capacity' => $mmv->seating_capacity - 1,
                            'cubic_capacity' => $mmv->engine_capacity_amount,
                            'fuel_type' =>  $mmv->fuel_type,
                            'gross_vehicle_weight' => $mmv->vehicle_weight,
                            'vehicle_type' => 'CAR',
                            'version_id' => $mmv->ic_version_code,
                        ];

                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update([
                            'od_premium' =>  round($final_od_premium - $addon_premium), // IC sends Addons Premium included in Total OD
                            'tp_premium' => round($final_tp_premium),
                            'ncb_discount' => round($ncb_discount),
                            'total_discount' => round($final_total_discount),
                            'addon_premium' => round($addon_premium),
                            'total_premium' => $final_net_premium,
                            'service_tax_amount' => $final_gst_amount,
                            'electrical_accessories' => $electrical_accessories_amt,
                            'non_electrical_accessories' => $non_electrical_accessories_amt,
                            'final_payable_amount' => $final_payable_amount,
                            'cpa_premium' => $cover_pa_owner_driver_premium,
                            'proposal_no' => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                            'customer_id' => $update_premium_response['PREMIUMDETAILS']['DATA']['CLIENTCODE'] ?? '',
                            'unique_proposal_id' => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                            'version_no' => $update_premium_response['PREMIUMDETAILS']['DATA']['VERSION_NO'],
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?  date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))): date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)))),
                            'tp_start_date' => $tp_start_date,
                            'tp_end_date'   => $tp_end_date,
                            'ic_vehicle_details' => $vehicleDetails,
                            'is_breakin_case' => $is_breakin_case,
                        ]);

                        RoyalSundaramPremiumDetailController::savePremiumDetails($webserviceId);
                        
                        if ($is_breakin_case == 'Y') {

                            $proposal_array = [
                                'authenticationDetails' => [
                                    'agentId' => config('IC.ROYAL_SUNDARAM.V1.CAR.AGENTID'),
                                    'apiKey' => config('IC.ROYAL_SUNDARAM.V1.CAR.APIKEY'),
                                ],
                                'premium' => $final_payable_amount,
                                'quoteId' => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                                'strEmail' => $proposal->email,
                                'isOTPVerified'=> 'Yes',
                                'reqType' => 'xml'
                            ];
                            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                            $proposal_array['uniqueId'] = $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'];
                            $proposal_array['ckycNo'] = $proposal->ckyc_number;
                            }
                            $get_response = getWsData(config('IC.ROYAL_SUNDARAM.V1.CAR.END_POINT_URL_ROYAL_SUNDARAM_MOTOR_PROPOSAL'), $proposal_array, 'royal_sundaram', [
                                'enquiryId' => $proposal->user_product_journey_id,
                                'requestMethod' =>'post',
                                'productName' => $productData->product_name. " ($businessType)",
                                'company' => 'royal_sundaram',
                                'section' => $productData->product_sub_type_code,
                                'method' =>'Proposal Generation',
                                'transaction_type' => 'proposal',
                                'root_tag' => 'GPROPOSALREQUEST',
                            ]);
                            $data = $get_response['response'];

                            if($data)
                            {
                               
                                $proposal_response = json_decode($data, TRUE);

                                if(isset($proposal_response['PREMIUMDETAILS']['Status']['StatusCode']) && ($proposal_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0005') || (($requestData->business_type == 'breakin') && ($proposal_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002')))
                                {
                                    
                                    CvBreakinStatus::insert([
                                        'user_proposal_id' => $proposal->user_proposal_id,
                                        'ic_id' => $productData->company_id,
                                        'breakin_number' => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                                        'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'updated_at' => date('Y-m-d H:i:s')

                                    ]);

                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'ic_id' => $proposal->ic_id,
                                        'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                        'proposal_id' => $proposal->user_proposal_id,
                                    ]);

                                }else
                                {
                                    if (isset($proposal_response['PREMIUMDETAILS']['Status'])) {
                                        return [
                                            'status' => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => $proposal_response['PREMIUMDETAILS']['Status']['Message']
                                        ];
                                    } else {
                                        return [
                                            'status' => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => 'Insurer not reachable'
                                        ];
                                    }
                                    
                                }

                            }else
                            {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => 'Insurer not reachable'
                                ];
                            }

                        } else {
                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                        }

                        return [
                            'status' => true,
                            'msg' => 'Proposal submitted successfully',
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'proposalNo' => $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                                'odPremium' => $final_od_premium,
                                'tpPremium' => $final_tp_premium,
                                'ncbDiscount' => $ncb_discount,
                                'totalPremium' => $final_net_premium,
                                'serviceTaxAmount' => $final_gst_amount,
                                'finalPayableAmount' => $final_payable_amount,
                                'is_breakin' => $is_breakin_case,
                                'inspection_number' => $is_breakin_case == 'Y' ? $update_premium_response['PREMIUMDETAILS']['DATA']['QUOTE_ID'] : '',
                                'kyc_url' => $kyc_url,
                                'is_kyc_url_present' => $is_kyc_url_present,
                                'kyc_message' => $kyc_message,
                                'kyc_status' => $proposal->is_ckyc_verified == 'Y' ? true : $kyc_status,
                            ]
                        ];
                    } else {
                        if (isset($update_premium_response['PREMIUMDETAILS']['Status'])) {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $update_premium_response['PREMIUMDETAILS']['Status']['Message']
                            ];
                        } else {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => 'Insurer not reachable'
                            ];
                        }
                    }
                } else {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable'
                    ];
                }
            } else {
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
