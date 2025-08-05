<?php

namespace App\Http\Controllers\Proposal\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
include_once app_path().'/Helpers/IcHelpers/RoyalSundaramHelper.php';

use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\RoyalSundaramPremiumDetailController;

class royalSundaramSubmitProposal {

    public static function submit($proposal, $request) {
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quoteData = json_decode($quote->premium_json, true);
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);

        //this quote id is important for kyc purpose
        if (empty($quoteData['quoteId'] ?? null)) {
            return [
                'status' => false,
                'message' => 'Quote ID not found'
            ];
        }

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
        $vehicle_age = floor($age / 12);
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
        $od_only = ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false;
        $type_of_cover = '';
        $policy_start_date = date('Y-m-d');
        $previous_insurer_name = '';
        $previous_policy_type = '';
        $previous_policy_number = '';
        $previous_insurer_addresss = '';
        $is_previous_claim = $requestData->is_claim == 'Y' ? 'Yes' : 'No';
        if (!empty($proposal->previous_insurance_company) && $requestData->business_type != 'newbusiness') {
            /* $previousInsurer = PreviousInsurerList::where([
                'company_alias' => 'royal_sundaram',
                'code' => $proposal->previous_insurance_company
            ])->first(); */
            $previous_insurer_name = $proposal->previous_insurance_company;
            $previous_policy_type = (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive' : 'ThirdParty');
            $previous_policy_number = $proposal->previous_policy_number;
        }
        
        $cpa_tenure = '1';
        if ($requestData->business_type == 'newbusiness') {
            $product_name = 'BrandNewTwoWheeler';
            $business_type = 'New Business';
            $type_of_cover = 'Bundled';
            $cpa_tenure = '5';
            $tp_start_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime($policy_start_date)) : '';
            $tp_end_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime($tp_start_date))))) : '';
        } else if ($requestData->business_type == 'rollover') {
            $product_name = 'RolloverTwoWheeler';
            $business_type = 'Roll Over';
            $type_of_cover = 'Comprehensive';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
        } else if ($requestData->business_type == 'breakin') {
            $product_name = 'BreakinTwoWheeler';
            $business_type = 'Break-In';
            $policy_start_date = date('Y-m-d', strtotime('+2 day'));
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
        }

        if ($business_type != 'New Business' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
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

        if ($tp_only) {
            $type_of_cover = 'LiabilityOnly';
            $requestData->applicable_ncb = 0;
            $requestData->previous_ncb = 0;
        }

        if ($od_only) {
            $type_of_cover = 'standalone';
        }

        if($requestData->previous_policy_type == "ThirdParty"){
            $type_of_cover = 'LiabilityOnly';
        }

        $previous_insurer = DB::table('insurer_address')
            ->where('Insurer', $proposal->insurance_company_name)
            ->first();
        $previous_insurer = keysToLower($previous_insurer);
        if (!empty($previous_insurer) && isset($previous_insurer->address_line_1) && !(in_array($requestData->previous_policy_type, ['Not sure']))) 
        {
            $previous_insurer_addresss = $previous_insurer->insurer.' '. $previous_insurer->address_line_1.' '.$previous_insurer->address_line_2.', '. $previous_insurer->pin;
        }

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'addons', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
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
        $opted_addons = [];
        $add_ons_opted_in_previous_policy = '';
        $cpa_selected = 'No';
        $cpa_reason = 'false';
        $standalonePAPolicy = 'false';
        $cpaCoverWithInternalAgent = 'false';
        $ReturnToInvoice = 'false';
        $Engine_protection = 'false';
        $Plan1 = "No";
        $Plan2 = "No";
        $RSA   = 'false';
        $prepolicy = (array) json_decode($proposal->additional_details);
        $prepolicy = !empty($prepolicy['prepolicy']) ? (array) $prepolicy['prepolicy'] : [];
        $companyName = !empty($proposal->cpa_ins_comp) ? $proposal->cpa_ins_comp : '';
        $expiryDate = !empty($proposal->cpa_policy_to_dt) ? $proposal->cpa_policy_to_dt : '';
        $policyNumber = !empty($proposal->cpa_policy_no) ? $proposal->cpa_policy_no : '';
        $nilDepreciationCover = false;

        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                if ($requestData->vehicle_owner_type == 'I') {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  {
                        $cpa_selected = 'Yes';
                        $cpa_tenure = isset($data['tenure'])? (string) $data['tenure'] : '1';
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
                            $companyName = !empty($companyName) ? $companyName : (!empty($prepolicy['CpaInsuranceCompany']) ? $prepolicy['CpaInsuranceCompany'] : '');
                            $expiryDate = !empty($expiryDate) ? date('d/m/Y', strtotime($expiryDate)) : (!empty($prepolicy['cpaPolicyEndDate']) ? date('d/m/Y', strtotime($prepolicy['cpaPolicyEndDate'])) : '');
                            $policyNumber = !empty($policyNumber) ? $policyNumber : (!empty($prepolicy['cpaPolicyNumber']) ? $prepolicy['cpaPolicyNumber'] : '');
                        } else {
                            $companyName = !empty($companyName) ? $companyName : (!empty($prepolicy['CpaInsuranceCompany']) ? $prepolicy['CpaInsuranceCompany'] : '');
                            $expiryDate = !empty($expiryDate) ? $expiryDate : (!empty($prepolicy['cpaPolicyEndDate']) ? $prepolicy['cpaPolicyEndDate'] : '');
                            $policyNumber = !empty($policyNumber) ? $policyNumber : (!empty($prepolicy['cpa_policy_number']) ? $prepolicy['cpa_policy_number'] : '');
                        }
                    }
                }
            }
        }
        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Zero Depreciation' && $vehicle_age <= 5) {
                    $opted_addons[] = 'DepreciationWaiverforTW';
                    $nilDepreciationCover = true;
                }
                    if ($data['name'] == 'Return To Invoice') {
                        $opted_addons[] = 'InvoicePrice';
                        $ReturnToInvoice = "true";
                        $Plan1 = "Yes";
                        $Plan2 = "No";
                    }
                    if($data['name'] == 'Road Side Assistance')
                    {
                        $RSA = 'true';
                    }
                    if($data['name'] == 'Engine Protector')
                    {
                        $Engine_protection = 'true';
                    } 
                    
                }
            }
        $add_ons_opted_in_previous_policy = !empty($opted_addons) ? implode(',', $opted_addons) : '';
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 30,
            'address_2_limit'   => 30,
            'address_3_limit'   => 30,
            'address_4_limit'   => 30,
        ];
        $getAddress = getAddress($address_data);
        
        $companyName = [];

        if(strlen($proposal->first_name) > 30) {
            $divideName = trim($proposal->first_name.' '.$proposal->last_name);
        } else {
            $divideName = $proposal->first_name;
        }

        $nameArray = array_values(getAddress([
            'address' => trim(($requestData->vehicle_owner_type == 'C') ? $proposal->first_name : $divideName),
            'address_1_limit' => 30,
            'address_2_limit' => 30
        ]));

        $firstName = $nameArray[0];
        $lastName = ($requestData->vehicle_owner_type == 'C' || strlen($proposal->first_name) > 30) ? $nameArray[1] ?? '' : $proposal->last_name;
        $registration_number = ($proposal->vehicale_registration_number == 'NEW') ? "" : str_replace('-', '', $proposal->vehicale_registration_number);

        $premium_request = [
            'quoteId' => $quoteData['quoteId'],
            'authenticationDetails' => [
                'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_BIKE'),
                'apikey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_BIKE'),
            ],
            'isNewUser' => $requestData->business_type == 'newbusiness' ? 'Yes' : 'No',
            'isproductcheck' => 'true',
            'istranscheck' => 'true',
            'premium' => '0.0',
            'proposerDetails' => [
                'addressOne' => trim($getAddress['address_1']),#$proposal->is_car_registration_address_same == 1 ? $proposal->address_line1 : $proposal->car_registration_address1,
                'addressTwo' => trim($getAddress['address_2']) == '' ? "" : trim($getAddress['address_2']),#$proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
                'addressThree' => trim($getAddress['address_3']),#$proposal->is_car_registration_address_same == 1 ? $proposal->address_line1 : $proposal->car_registration_address3,
                'addressFour' => trim($getAddress['address_4']),
                'contactAddress1' => trim($getAddress['address_1']),#$proposal->address_line1,
                'contactAddress2' => trim($getAddress['address_2']) == '' ? "" : trim($getAddress['address_2']),#$proposal->address_line2,
                'contactAddress3' => trim($getAddress['address_3']),#$proposal->address_line3,
                'contactAddress4' => trim($getAddress['address_4']),
                // 'contactCity' =>   $rto_data->city_name ,//  $proposal->city
                'contactCity' =>  $proposal->city,
                'contactPincode' => $proposal->pincode,
                'dateOfBirth' => date('d/m/Y', strtotime($proposal->dob)),
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
                'emailId' => $proposal->email,
                'firstName' => removeSpecialCharactersFromString($firstName, true),
                'lastName' =>  $requestData->vehicle_owner_type == 'I' ? removeSpecialCharactersFromString($lastName, true) : '',
                'mobileNo' => $proposal->mobile_number,
                'strPhoneNo' => '',
                'strStdCode' => '',
                'title' => $requestData->vehicle_owner_type == 'C' ? 'M/S' : ($proposal->gender == 'M' ? 'Mr' : ($proposal->marital_status == 'Married' ? 'Mrs' : 'Ms')),
                'userName' => $proposal->email,
                'panNumber' => $proposal->pan_number,
                'aadharNumber' => '',
                'GSTIN' => $proposal->gst_number
            ],
            'reqType' => 'XML',
            'respType' => 'XML',
            'vehicleDetails' => [
                'accidentCoverForPaidDriver' => '0',
                'automobileAssociationMembership' => 'No',
                'averageMonthlyMileageRun' => '',
                'carRegisteredCity' => $rto_data->city_name,
                'rtoName' => $rto_data->rto_name,#rto name added
                'chassisNumber' => $proposal->chassis_number,
                'addOnsOptedInPreviousPolicy' => $add_ons_opted_in_previous_policy,
                'validPUCAvailable' => $proposal->is_valid_puc == '1' ? 'Yes' : 'No',
                'pucnumber' => $proposal->is_valid_puc == '1' ? $proposal->puc_no : '',
                'pucvalidUpto' => $proposal->is_valid_puc == '1' && !empty($proposal->puc_expiry) ? date('d/m/Y', strtotime($proposal->puc_expiry)) : '',
                'isValidDrivingLicenseAvailable' => $cpa_reason == 'true' ? 'No' :'Yes',
                'claimAmountReceived' => ($is_previous_claim == 'Yes') ? '50000' : '0',
                'claimsMadeInPreviousPolicy' => $is_previous_claim,
                'claimsReported' => $requestData->is_claim == 'Y' ? '3' : '0',
                'companyNameForCar' => $requestData->vehicle_owner_type == 'I' ? '' : removeSpecialCharactersFromString($proposal->first_name , true),
                'cover_elec_acc' => $electrical_accessories,
                'cover_non_elec_acc' => $non_electrical_accessories,
                'depreciationWaiver' => 'On',
                'drivingExperience' => '1',
                'engineCapacityAmount'=> $mmv->engine_capacity_amount,
                'engineNumber' => $proposal->engine_number,
                'engineProtector' => 'off',
                'hdnEngineProtector' => $Engine_protection,
                "hdnFullInvoice" => $ReturnToInvoice,
                "fullInvoicePlan1" => $Plan1,
                "fullInvoicePlan2" => $Plan2,
                "hdnRoadSideAssistance" => $RSA,
                'engineProtectorPremium' => '',
                'financierName' => $proposal->is_vehicle_finance == 1 ? $proposal->name_of_financer : '',
                'fuelType' => 'Petrol',
                'hdnDepreciation' => $nilDepreciationCover ? 'true' : 'false',
                'idv' => $quote->idv,
                'isPreviousPolicyHolder' => $requestData->business_type == 'newbusiness' ? 'false' : 'true',
                'isProductCheck' => 'true',
                'isTwoWheelerFinanced'=> $proposal->is_vehicle_finance == 1 ? 'Yes' : 'No',
                'isTwoWheelerFinancedValue' => $proposal->is_vehicle_finance == 1 ? $proposal->financer_agreement_type : '',
                'legalliabilityToEmployees' => 'No',
                'legalliabilityToPaidDriver' => $cover_ll_paid_driver,
                'modelName' => $mmv->model,
                'ncbcurrent' => $requestData->applicable_ncb,
                'ncbprevious'=> $requestData->previous_ncb,
                'noClaimBonusPercent' => $ncb_levels[$requestData->applicable_ncb] ?? '0',
                'personalAccidentCoverForUnnamedPassengers' => $cover_pa_unnamed_passenger == 'Yes' ? $cover_pa_unnamed_passenger_amt : '0',
                'planOpted' => 'Flexi Plan',
                'previousInsurerName'=> $proposal->insurance_company_name,#$previous_insurer_name,
                "previousinsurersCorrectAddress" => $requestData->business_type != 'newbusiness' ? $previous_insurer_addresss : '',
                'previousPolicyNo' => $previous_policy_number,
                'previousPolicyExpiryDate' => date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
                'previousPolicyType' => $previous_policy_type,
                'productName'=> $product_name,
                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                'policyTerm' =>  '1',
                'registrationchargesRoadtax' => 'off',
                'registrationNumber' => $registration_number,
                'region' => $region->region,
                'tppdLimit' => $TPPDCover,
                'typeOfCover' => $type_of_cover,
                'vehicleManufacturerName' => $mmv->make,
                'vehicleModelCode' => $mmv->model_code,
                'vehicleMostlyDrivenOn' => 'City roads',
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
                'vechileOwnerShipChanged' => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
                'vehicleRegDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                'vehicleRegisteredInTheNameOf' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
                'vehicleSubLine' => 'motorCycle',
                'VIRNumber' => 'string',
                'voluntaryDeductible'=> $is_voluntary_access == 'Yes' ? $voluntary_excess_amt : '0',
                'yearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
            ]
        ];

        //removing puc expiry: IC Observation #32879
        if(empty($proposal->puc_expiry)){
            unset($premium_request['vehicleDetails']['pucvalidUpto']);
        }

        if($requestData->business_type == 'newbusiness'){
            unset($premium_request['vehicleDetails']['validPUCAvailable']);
            unset($premium_request['vehicleDetails']['pucnumber']);
            unset($premium_request['vehicleDetails']['pucvalidUpto']);
        }

        if (!$od_only) {
            $premium_request['vehicleDetails']['cpaCoverisRequired'] = $cpa_selected;
            $premium_request['vehicleDetails']['cpaPolicyTerm'] = $proposal->owner_type == 'I' ? $cpa_tenure : '';
            $premium_request['vehicleDetails']['cpaCoverDetails'] = [
                'noEffectiveDrivingLicense' => $cpa_reason,
                'cpaCoverWithInternalAgent' => $cpaCoverWithInternalAgent,
                'standalonePAPolicy' => $standalonePAPolicy,
                'companyName' => $companyName,
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
                'tpPolicyNumber' => removeSpecialCharactersFromString($tp_insurance_number),
                'tpInsurer' => $tp_insurance_company,
                'tpInceptionDate' => date('d/m/Y', strtotime($tp_start_date)),
                'tpExpiryDate' => date('d/m/Y', strtotime($tp_end_date)),
                'tpPolicyTerm' => '5',
            ];
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
            $POSPCode = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
            $premium_request['posOpted'] = 'true';
            $premium_request['posCode'] = '';
            $premium_request['posDetails'] = [
                'name' => removeSpecialCharactersFromString($pos_data->agent_name, true),
                'pan' => $POSPCode,
                'aadhaar' => $pos_data->aadhar_no,
                'mobile' => $pos_data->agent_mobile,
                'licenceExpiryDate' => '31/12/2050',
            ];
        }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_ROYAL_SUNDARAM') == 'Y' && $quote->idv <= 5000000)
        {
            $premium_request['posOpted'] = 'true';
            $premium_request['posCode'] = '';
            $premium_request['posDetails'] = [
                'name' => 'Agent',
                'pan' => 'ABGTY8890Z',
                'aadhaar' => '569278616999',
                'mobile' => '8850386204',
                'licenceExpiryDate' => '31/12/2050',
            ];
        }
        if ($proposal->is_ckyc_verified != 'Y') {
            $get_response = getWsData(config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_BIKE_PREMIUM'), $premium_request, 'royal_sundaram', [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'royal_sundaram',
                'section' => $productData->product_sub_type_code,
                'method' => 'Premium Calculation',
                'transaction_type' => 'proposal',
                'root_tag' => 'CALCULATEPREMIUMREQUEST',
            ]);
            $data = $get_response['response'];
            $response = json_decode($data, TRUE);
            if (!(isset($response['PREMIUMDETAILS']['Status']['StatusCode']) && $response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002')) {
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
        }
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
                            'unique_quote_id' => $response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                        ];
                    } else { 
                        $request_data = [
                            'companyAlias' => 'royal_sundaram',
                            'mode' =>  $proposal->ckyc_type == 'ckyc_number' ? 'ckyc_number' :'pan_number_with_dob',
                            'unique_quote_id' => $response['PREMIUMDETAILS']['DATA']['QUOTE_ID'],
                            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                        ];
                    }
    
                    $ckycController = new CkycController;
                    $response = $ckycController->ckycVerifications(new Request($request_data));
                    $response = $response->getOriginalContent();
                    if (isset($response['data']['verification_status']) && $response['data']['verification_status'] && !empty($response['data']['ckyc_id']) )
                    {
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
                    }else{
                        $kyc_url = $response['data']['redirection_url'];
                        $is_kyc_url_present = !empty($response['data']['redirection_url']);
                        $kyc_message = (!empty($response['data']['message']) ? $response['data']['message'] : 'Kyc verification false');
                        $kyc_status = false;
                    }
                
                }
            }
     $proposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
     
     $proposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)->first();

     $registration_number = $proposal->vehicale_registration_number;
     $registration_number = explode('-', $registration_number);

     if ($registration_number[0] == 'DL') {
        $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true); 
        $registration_number = $registration_no.'-'.$registration_number[2].'-'.$registration_number[3];
      } else {
        $registration_number = ($proposal->vehicale_registration_number == 'NEW') ? "" : str_replace('-', '', $proposal->vehicale_registration_number);
     }

    if($kyc_status || $proposal->is_ckyc_verified == 'Y')
    {
                // $premium_response_idv = (!$tp_only) ? round($premium_response['PREMIUMDETAILS']['DATA']['IDV']) : 0;
                $premium_response_idv =(!$tp_only) ? $quoteData['modifiedIdv'] : 0;

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

                $update_premium_request = [
                    'authenticationDetails' => [
                        'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_BIKE'),
                        'apikey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_BIKE')
                    ],
                    'quoteId' => $quoteData['quoteId'],
                    'isNewUser' => $requestData->business_type == 'newbusiness' ? 'Yes' : 'No',
                    'isproductcheck' => 'true',
                    'istranscheck' => 'true',
                    'premium' => $quoteData['pREMIUM'],
                    'proposerDetails' => [
                        'ResidenceAddressOne' => trim($getAddress['address_1']), 
                        'ResidenceAddressTwo' => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']), 
                        'ResidenceAddressThree' => trim($getAddress['address_3']) == '' ? '' : trim($getAddress['address_3']), 
                        'ResidenceAddressFour' => trim($getAddress['address_4']),
                        'permanentAddress1' => $proposal->is_car_registration_address_same == 1 ? trim($getAddress['address_1']) : $proposal->car_registration_address1,
                        'permanentAddress2' => $proposal->is_car_registration_address_same == 1 ? (trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2'])) : $proposal->car_registration_address2,
                        'permanentAddress3' => $proposal->is_car_registration_address_same == 1 ? trim($getAddress['address_3']) : $proposal->car_registration_address3,
                        'permanentAddress4' => trim($getAddress['address_4']),
                        'permanentCity' => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city, 
                        'permanentPincode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                        'dateOfBirth' => date('d/m/Y', strtotime($proposal->dob)),
                        'guardianAge' => '',
                        'guardianName' => '',
                        'nomineeAge' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_age : '',
                        'nomineeName' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_name : '',
                        'occupation' => $proposal->occupation_name,
                        'ResidenceCity' => $proposal->city,
                        'ResidencePinCode' => $proposal->pincode,
                        'relationshipWithNominee' => $requestData->vehicle_owner_type == 'I' ? $proposal->nominee_relationship : '',
                        'relationshipwithGuardian' => '',
                        'sameAdressReg' => $proposal->is_car_registration_address_same == 1 ? 'Yes' : 'No',
                        'emailId' => $proposal->email,
                        'firstName' => removeSpecialCharactersFromString($firstName, true),
                        'lastName' => removeSpecialCharactersFromString($lastName, true),
                        'mobileNo' => $proposal->mobile_number,
                        'strPhoneNo' => '',
                        'strStdCode' => '',
                        'title' => $requestData->vehicle_owner_type == 'C' ? 'M/S' : ($proposal->gender == 'M' ? 'Mr' : ($proposal->marital_status == 'Married' ? 'Mrs' : 'Ms')),
                        'userName' => $proposal->email,
                        'panNumber' => $proposal->pan_number,
                        'aadharNumber' => '',
                        'GSTIN' => $proposal->gst_number
                    ],
                    'vehicleDetails' => [
                        'accidentCoverForPaidDriver' => '0',
                        'automobileAssociationMembership' => 'No',
                        'averageMonthlyMileageRun' => '',
                        'carRegisteredCity' => $rto_data->city_name,
                        'rtoName' => $rto_data->rto_name,
                        'addOnsOptedInPreviousPolicy' => $add_ons_opted_in_previous_policy,
                        'validPUCAvailable' => $proposal->is_valid_puc == '1' ? 'Yes' : 'No',
                        'pucnumber' => $proposal->is_valid_puc == '1' ? $proposal->puc_no : '',
                        'isValidDrivingLicenseAvailable' => $cpa_reason == 'true' ? 'No' :'Yes',
                        'pucvalidUpto' => $proposal->is_valid_puc == '1' ? date('d/m/Y', strtotime($proposal->puc_expiry)) : '',
                        'chassisNumber' => $proposal->chassis_number,
                        'claimAmountReceived' => ($is_previous_claim == 'Yes') ? '50000' : '0',
                        'claimsMadeInPreviousPolicy' => $is_previous_claim,
                        'claimsReported' => $requestData->is_claim == 'Y' ? '3' : '0',
                        'companyNameForCar' => $requestData->vehicle_owner_type == 'I' ? '' :  removeSpecialCharactersFromString($proposal->first_name , true),
                        'cover_elec_acc' => $electrical_accessories,
                        'cover_non_elec_acc' => $non_electrical_accessories,
                        'depreciationWaiver' => '',
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
                        'drivingExperience' => '1',
                        'engineCapacityAmount'=> $mmv->engine_capacity_amount,
                        'engineNumber' => $proposal->engine_number,
                        'engineProtector' => 'off',
                        'engineProtectorPremium' => '',
                        'financierName' => $proposal->is_vehicle_finance == 1 ? $proposal->name_of_financer : '',
                        'fuelType' => 'Petrol',
                        'hdnDepreciation' => $nilDepreciationCover ? 'true' : 'false',#$productData->zero_dep == '1' ? 'false' : 'true',
                        'hdnEngineProtector' => $Engine_protection,
                        "hdnFullInvoice" => $ReturnToInvoice,
                        "hdnRoadSideAssistance" => $RSA,
                        "fullInvoicePlan1" => $Plan1,
                        "fullInvoicePlan2" => $Plan2,
                        'idv' => $premium_response_idv,
                        'isPreviousPolicyHolder' => $requestData->business_type == 'newbusiness' ? 'false' : 'true',
                        'isProductCheck' => 'true',
                        'isTwoWheelerFinanced'=> $proposal->is_vehicle_finance == 1 ? 'Yes' : 'No',
                        'isTwoWheelerFinancedValue' => $proposal->is_vehicle_finance == 1 ? $proposal->financer_agreement_type : '',
                        'legalliabilityToEmployees' => 'No',
                        'legalliabilityToPaidDriver' => $cover_ll_paid_driver,
                        'modelName' => $mmv->model,
                        'ncbcurrent' => $requestData->applicable_ncb,
                        'ncbprevious'=> $requestData->previous_ncb,
                        'noClaimBonusPercent' => $ncb_levels[$requestData->applicable_ncb] ?? '0',
                        'personalAccidentCoverForUnnamedPassengers' => $cover_pa_unnamed_passenger == 'Yes' ? $cover_pa_unnamed_passenger_amt : '0',
                        'planOpted' => 'Flexi Plan',
                        'previousInsurerName'=> $previous_insurer_name,
                        "previousinsurersCorrectAddress" => $requestData->business_type != 'newbusiness' ? $previous_insurer_addresss : '',
                        'previousPolicyNo' => $previous_policy_number,
                        'previousPolicyExpiryDate' => date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
                        'previousPolicyType' => $previous_policy_type,
                        'productName'=> $product_name,
                        'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                        'policyTerm' =>  '1',
                        'registrationchargesRoadtax' => 'off',
                        'registrationNumber' => $registration_number,
                        'region' => $region->region,
                        'tppdLimit' => $TPPDCover,
                        'typeOfCover' => $type_of_cover,
                        'vehicleManufacturerName' => $mmv->make,
                        'vehicleModelCode' => $mmv->model_code,
                        'vehicleMostlyDrivenOn' => 'City roads',
                        'vechileOwnerShipChanged' => $requestData->ownership_changed == 'Y' ? 'Yes' : 'No',
                        'vehicleRegDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'vehicleRegisteredInTheNameOf' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Company',
                        'vehicleSubLine' => 'motorCycle',
                        'VIRNumber' => 'string',
                        'voluntaryDeductible'=> $is_voluntary_access == 'Yes' ? $voluntary_excess_amt : '0',
                        'yearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                    ]
                ];

                //removing puc expiry: IC Observation #32879
                if(empty($proposal->puc_expiry)){
                    unset($update_premium_request['vehicleDetails']['pucvalidUpto']);
                }

                if($requestData->business_type == 'newbusiness'){
                    unset($update_premium_request['vehicleDetails']['validPUCAvailable']);
                    unset($update_premium_request['vehicleDetails']['pucnumber']);
                    unset($update_premium_request['vehicleDetails']['pucvalidUpto']);
                }

                if (!$tp_only) {
                    
                    $update_premium_request['vehicleDetails']['discountIdvPercent'] = ($quote->idv) * 100 / $premium_response_idv - 100;
                    $update_premium_request['vehicleDetails']['modifiedIdv'] = $quote->idv;
                }
                if (!$od_only){
                    $update_premium_request['vehicleDetails']['cpaCoverisRequired'] = $cpa_selected;
                    $update_premium_request['vehicleDetails']['cpaPolicyTerm'] = $proposal->owner_type == 'I' ? $cpa_tenure : '';
                    $update_premium_request['vehicleDetails']['cpaCoverDetails'] = [
                        'noEffectiveDrivingLicense' => $cpa_reason,
                        'cpaCoverWithInternalAgent' => $cpaCoverWithInternalAgent,
                        'standalonePAPolicy' => $standalonePAPolicy,
                        'companyName' => $companyName,
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
            
                if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                    $POSPCode = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
                    $update_premium_request['posOpted'] = 'true';
                    $update_premium_request['posCode'] = '';
                    $update_premium_request['posDetails'] = [
                        'name' => removeSpecialCharactersFromString($pos_data->agent_name, true),
                        'pan' => $POSPCode,
                        'aadhaar' => $pos_data->aadhar_no,
                        'mobile' => $pos_data->agent_mobile,
                        'licenceExpiryDate' => '31/12/2050',
                    ];
                }

                if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_ROYAL_SUNDARAM') == 'Y')
                {
                    $premium_request['posOpted'] = 'true';
                    $premium_request['posCode'] = '';
                    $premium_request['posDetails'] = [
                        'name' => 'Agent',
                        'pan' => 'ABGTY8890Z',
                        'aadhaar' => '569278616999',
                        'mobile' => '8850386204',
                        'licenceExpiryDate' => '31/12/2050',
                    ];
                }

                $documentData = getMandatoryDocumentData($proposal);

                $update_premium_request['proposerDetails'] = array_merge(
                    $update_premium_request['proposerDetails'],
                    $documentData
                );

                $get_response = getWsData(config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_BIKE_UPDATEVEHICLEDETAILS'), $update_premium_request, 'royal_sundaram', [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'royal_sundaram',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Update Premium Calculation',
                    'transaction_type' => 'proposal',
                    'root_tag' => 'CALCULATEPREMIUMREQUEST',
                ]);
                $data = $get_response['response'];

                if ($data) {
                    $update_premium_response = json_decode($data, TRUE);
                    if(empty($update_premium_response))
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Invalid response recived from ic'
                        ];
                    }
                    if (isset($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode']) && ($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0005') || (($requestData->business_type == 'breakin') && ($update_premium_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0002'))) {
                        $is_breakin_case = ($requestData->business_type == 'breakin') ? 'Y' : 'N';
                        $city_name = DB::table('master_city AS mc')
                            ->where('mc.city_name', $rto_data->city_name)
                            ->select('mc.zone_id')
                            ->first();

                        $car_tariff = DB::table('motor_tariff AS mt')
                            //->whereRaw($mmv->engine_capacity_amount.' BETWEEN mt.cc_min and mt.cc_max')
                            ->whereRaw($vehicle_age . ' BETWEEN mt.age_min and mt.age_max')
                            ->where('mt.zone_id', $city_name->zone_id)
                            ->first();

                        $llpaiddriver_premium = $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['TO_PAID_DRIVERS'];
                        $cover_pa_owner_driver_premium = $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNDER_SECTION_III_OWNER_DRIVER'];
                        $cover_pa_paid_driver_premium = $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['PA_COVER_TO_PAID_DRIVER'];
                        $cover_pa_unnamed_passenger_premium = $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['UNNAMED_PASSENGRS'];
                        $voluntary_excess = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['VOLUNTARY_DEDUCTABLE'];
                        $anti_theft = 0;
                        $ic_vehicle_discount = 0;
                        $ncb_discount = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NO_CLAIM_BONUS'] ?? 0;
                        $electrical_accessories_amt = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ELECTRICAL_ACCESSORIES'];
                        // $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' ? $non_electrical_accessories_value * ($car_tariff->rate_per_thousand/100) : 0;
                        $non_electrical_accessories_amt = $non_electrical_accessories == 'Yes' ? $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NON_ELECTRICAL_ACCESSORIES'] : 0;
                        // $od = round($update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES'] - $non_electrical_accessories_amt);
                        $od = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BASIC_PREMIUM_AND_NON_ELECTRICAL_ACCESSORIES'];
                        $tppd = $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BASIC_PREMIUM_INCLUDING_PREMIUM_FOR_TPPD'];
                        $tppd_discount = 0;
                        if (!empty($TPPDCover)) {
                            $tppd_discount = 50* ($requestData->business_type == 'newbusiness' ? 5 : 1);
                            $tppd += $tppd_discount;
                        }
                        $cng_lpg = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['BI_FUEL_KIT'];
                        $cng_lpg_tp = $update_premium_response['PREMIUMDETAILS']['DATA']['LIABILITY']['BI_FUEL_KIT_CNG'];

                        $zero_depreciation = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['DEPRECIATION_WAIVER'];
                        $engine_protection = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ENGINE_PROTECTOR'];
                        $ncb_protection = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['NCB_PROTECTOR'];
                        $key_replace = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['KEY_REPLACEMENT'];
                        $tyre_secure = 0;
                        $return_to_invoice = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['INVOICE_PRICE_INSURANCE'];
                        $lopb = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['LOSS_OF_BAGGAGE'];
                        $rsa_premium = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['ROADSIDEASSISSTANCE'];

                        $addon_premium = $zero_depreciation + $engine_protection + $ncb_protection + $key_replace + $return_to_invoice + $tyre_secure + $lopb + $rsa_premium;
                        $od_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount;
                        $loading_amount = $update_premium_response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['UNDERWRITER_LOADING'] ?? 0;
                        $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt - $od_discount + $loading_amount;
                        $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_owner_driver_premium + $cover_pa_unnamed_passenger_premium - $tppd_discount;
                        $final_total_discount = $od_discount + $tppd_discount;
                        $final_net_premium = $update_premium_response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM'];
                        $final_gst_amount = $update_premium_response['PREMIUMDETAILS']['DATA']['PREMIUM'] - $update_premium_response['PREMIUMDETAILS']['DATA']['PACKAGE_PREMIUM'];
                        $final_payable_amount = $update_premium_response['PREMIUMDETAILS']['DATA']['PREMIUM'];

                        // $final_od_premium =  $response['PREMIUMDETAILS']['DATA']['OD_PREMIUM']['TOTAL_OD_PREMIUM'];

                        $vehicleDetails = [
                            'manf_name' => $mmv->make,
                            'model_name' => $mmv->model,
                            'version_name' => $mmv->model,
                            'seating_capacity' => $mmv->min_seating_capacity,
                            'carrying_capacity' => ((int) $mmv->min_seating_capacity) - 1,
                            'cubic_capacity' => $mmv->engine_capacity_amount,
                            'fuel_type' => 'Petrol',
                            'vehicle_type' => 'Bike',
                            'version_id' => $mmv->ic_version_code,
                        ];

                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update([
                            'od_premium' => $final_od_premium,
                            'tp_premium' => $final_tp_premium,
                            'ncb_discount' => $ncb_discount,
                            'total_discount' => $final_total_discount,
                            'addon_premium' => $addon_premium,
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
                            'policy_end_date' => $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?  date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))): date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)))),
                            'tp_start_date' => $tp_start_date,
                            'tp_end_date'   => $tp_end_date,
                            'ic_vehicle_details' => $vehicleDetails,
                            'is_breakin_case' => $is_breakin_case
                        ]);

                        RoyalSundaramPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
                        
                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

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
    
    public static function renewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'royal_sundaram');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $vehicleDetails = [
            'manufacture_name'      => $mmv->make,
            'model_name'            => $mmv->model,
            'version'               => '',
            'fuel_type'             => $mmv->fuel_type,
            'seating_capacity'      => $mmv->min_seating_capacity,
            'carrying_capacity'     => '',
            'cubic_capacity'        => $mmv->engine_capacity_amount,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => ''
        ];

        $fetch_url = config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_FETCH_POLICY_DETAILS').'?policyNumber='.$proposal->previous_policy_number.'&expiryDate='.str_replace('-','/',$requestData->previous_policy_expiry_date).'&lob=motor';
        $get_response = getWsData($fetch_url,$fetch_url, 'royal_sundaram', [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'get',
            'productName'       => $productData->product_name,
            'company'           => 'royal_sundaram',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Fetch Policy Details',
            'transaction_type'  => 'proposal'
        ]);  
        $data = $get_response['response'];
        $response_data = json_decode($data,true);
        if(isset($response_data['statusCode']) && $response_data['statusCode'] == 'S-0001')
        {
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
            if($proposal->is_ckyc_verified != 'Y' && $ckycProposal)
            {
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $request_data = [
                        'companyAlias' => 'royal_sundaram',
                        'mode' =>  $proposal->ckyc_type == 'ckyc_number' ? 'ckyc_number' : 'pan_number_with_dob',
                        'unique_quote_id' => $response_data['quoteId'],
                        'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    ];

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
                                'proposal_no' => $response_data['quoteId'],
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
                        $is_kyc_url_present = true;
                        $kyc_message = '';
                        $kyc_status = false;
                    }
                }
            }
            $proposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            if(!$kyc_status || $proposal->is_ckyc_verified != 'Y')
            {
                return response()->json([
                    'status' => false,
                    'msg' => "Kyc verification false",
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $proposal->user_product_journey_id,
                        'proposalNo' => $proposal->unique_proposal_id,
                        'kyc_url' => $kyc_url,
                        'is_kyc_url_present' => $is_kyc_url_present,
                        'kyc_message' => $kyc_message,
                        'kyc_status' => $kyc_status,
                    ]
                ]);
            }
            
            $UpdatePolicyDetails = [
                'clientCode' => $response_data['clientCode'],
                //'addonCoverages' => $add_ons,
                'policyNumber' => $response_data['policyNumber'],
                'proposerDob' =>$requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : '18/09/1995',
                'panNumber' => $proposal->pan_number,
                'registrationNumber' => str_replace('-', '', $proposal->vehicale_registration_number)
            ];
            if(!empty($proposal->mobile_number))
            {
                $UpdatePolicyDetails['mobileNo'] = $proposal->mobile_number;
            }

            if(!empty($proposal->email))
            {
                $UpdatePolicyDetails['emailId'] = $proposal->email;
            }

            RoyalSundaramPremiumDetailController::saveRenewalPremiumDetails($get_response['webservice_id']);
            
            $get_response = getWsData('https://dtcdocstag.royalsundaram.in/Services/Renewal/UpdatePolicy',$UpdatePolicyDetails, 'royal_sundaram', [
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'post',
                'productName'       => $productData->product_name,
                'company'           => 'royal_sundaram',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Update Policy Details',
                'transaction_type'  => 'proposal',
                'root_tag' => 'UpdatePolicyDetails',
                    'headers' => [
                            'Content-Type'=>'application/xml'
                    ]
            ]); 
            $UpdatePolicyDetailsData = $get_response['response'];
//            $UpdatePolicyDetailsData = '{
//                "policyNumber" : "VPC1905844000102",
//                "statusCode" : "S-0001",
//                "message" : "UPDATION SUCCESS"
//                }';
            $UpdatePolicyDetailsData = json_decode($UpdatePolicyDetailsData,true);
            
            if(isset($UpdatePolicyDetailsData['statusCode']) &&  $UpdatePolicyDetailsData['statusCode'] == 'S-0001')
            {
                $policy_start_date = str_replace('/','-',$response_data['currentInceptionDate']);
                $policy_end_date = str_replace('/','-',$response_data['currentExpiryDate']);
                $idv = $response_data['idv'];
                
                if($response_data['totalodpremium'] > 0 && $response_data['totaltppremium'] > 0)
                {
                   $policyType = 'Comprehensive'; 
                }
                else if ($response_data['totalodpremium'] > 0 && $response_data['totaltppremium'] == 0)
                {
                    $policyType = 'Own Damage';
                }
                else if($response_data['totaltppremium'] > 0 && $response_data['totalodpremium'] == 0)
                {
                   $policyType = 'Third Party';
                }           

                $canTakeKeyReplacementCover = $canTakeTyreCoverClause = $canTakeLossofBaggage = $canTakeTyreCoverClause = $canTakeInvoicePrice = 
                $canTakeNCBProtectorCover = $canTakeSpareCar = $canTakeDepreciationWaiver = $canTakeRegistrationchargesRoadtax = 
                $canTakeAggravationCover = $canTakeWindShieldGlass = 0;
                if (isset($response_data['addonCoverages'])) {
                    foreach ($response_data['addonCoverages'] as $key => $addonCoverages) {
                        if ($addonCoverages['name'] == 'KeyReplacementCover') {
                            $canTakeKeyReplacementCover = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'LossofBaggage') {
                            $canTakeLossofBaggage = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'TyreCoverClause') {
                            $canTakeTyreCoverClause = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'InvoicePrice') {
                            $canTakeInvoicePrice = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'NCBProtectorCover') {
                            $canTakeNCBProtectorCover = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'SpareCar') {
                            $canTakeSpareCar = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'DepreciationWaiver') {
                            $canTakeDepreciationWaiver = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'RegistrationchargesRoadtax') {
                            $canTakeRegistrationchargesRoadtax = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'AggravationCover') {
                            $canTakeAggravationCover = $addonCoverages['premium'];
                        } else if ($addonCoverages['name'] == 'WindShieldGlass') {
                            $canTakeWindShieldGlass = $addonCoverages['premium'];
                        }
                    }
                }
                $VoluntaryDed = $VPC_OwnDamageCover = $VPC_TPBasicCover = $VPC_CompulsoryPA = 
                $ServiceeTax = $AntiTheftDiscount = $VPC_ODBasicCover = $AutoAssociationMembership = 
                $VPC_LiabilityCover = $NoCliamDiscount = $OwnPremisesDiscount = 0;

                $VPC_FiberGlass = $SpareCar = $AggravationCover = $DepreciationWaiver = $VPC_ElectAccessories = $VPC_PAPaidDriver = 
                $EnhancedPAUnnamedPassengersCover = $TP_GeoExtension = $GeoExtension = $AdditionalTowingChargesCover = 
                $WindShieldGlass = $LossofBaggage = $EnhancedPAPaidDriverCover = $VPC_PAUnnamed = $VPC_WLLDriver = 
                $EnhancedPANamedPassengersCover = $KeyReplacementCover = $NonElectricalAccessories = $VPC_PANamedOCcupants = 0;
                $NCBProtectorCover = $TyreCoverClause = $InvoicePrice =  0;
                $electrical_accessories_amt = 0;
                $tppd_discount = 0;
                $non_electrical_accessories_amt = 0;
                $cng_lpg = 0;
                $cover_pa_unnamed_passenger_premium = 0;
                $llpaiddriver_premium = 0;
                $cover_pa_paid_driver_premium = 0;
                $cover_pa_owner_driver_premium = 0;
                $cng_lpg_tp = 0;
                $anti_theft = 0;
                $ic_vehicle_discount = 0;
                $total_discount = 0;
                $addon_premium = 0;
                foreach ($response_data['coverages'] as $key => $coverages) {
                    if($coverages['name'] == 'VoluntaryDed')
                    {
                        $total_discount += $VoluntaryDed = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_OwnDamageCover')
                    {
                        $VPC_OwnDamageCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_TPBasicCover')
                    {
                        $VPC_TPBasicCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_CompulsoryPA')
                    {
                        $cover_pa_owner_driver_premium = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'ServiceeTax')
                    {
                        $ServiceeTax = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'AntiTheftDiscount')
                    {
                        $total_discount += $anti_theft = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_ODBasicCover')
                    {
                        $VPC_ODBasicCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'AutoAssociationMembership')
                    {
                        $total_discount += $AutoAssociationMembership = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_LiabilityCover')
                    {
                        $VPC_LiabilityCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'NoCliamDiscount')
                    {
                        $total_discount += $NoCliamDiscount = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'OwnPremisesDiscount')
                    {
                        $total_discount += $OwnPremisesDiscount = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_FiberGlass')
                    {
                        $VPC_FiberGlass = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'SpareCar')
                    {
                        $SpareCar = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'DepreciationWaiver')
                    {
                        $addon_premium += $DepreciationWaiver = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'AggravationCover')
                    {
                        $addon_premium += $AggravationCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'NCBProtectorCover')
                    {
                        $addon_premium += $NCBProtectorCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'InvoicePrice')
                    {
                        $addon_premium += $InvoicePrice = $coverages['premium'];
                    }            
                    else if($coverages['name'] == 'TyreCoverClause')
                    {
                        $addon_premium += $TyreCoverClause = $coverages['premium'];
                    }            
                    else if($coverages['name'] == 'VPC_ElectAccessories')
                    {
                        $addon_premium += $electrical_accessories_amt = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'NonElectricalAccessories')
                    {
                        $addon_premium += $non_electrical_accessories_amt = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_PAPaidDriver')
                    {
                        $llpaiddriver_premium = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'EnhancedPAUnnamedPassengersCover')
                    {
                        $EnhancedPAUnnamedPassengersCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'TP_GeoExtension')
                    {
                        $TP_GeoExtension = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'GeoExtension')
                    {
                        $addon_premium += $GeoExtension = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'AdditionalTowingChargesCover')
                    {
                        $AdditionalTowingChargesCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'WindShieldGlass')
                    {
                        $addon_premium += $WindShieldGlass = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'LossofBaggage')
                    {
                        $addon_premium += $LossofBaggage = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'EnhancedPAPaidDriverCover')
                    {
                        $cover_pa_paid_driver_premium = (int) $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_PAUnnamed')
                    {
                        $cover_pa_unnamed_passenger_premium = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'VPC_WLLDriver')
                    {
                        $VPC_WLLDriver = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'EnhancedPANamedPassengersCover')
                    {
                        $EnhancedPANamedPassengersCover = $coverages['premium'];
                    }
                    else if($coverages['name'] == 'KeyReplacementCover')
                    {
                        $addon_premium += $KeyReplacementCover = $coverages['premium'];
                    }            
                    else if($coverages['name'] == 'VPC_PANamedOCcupants')
                    {
                        $VPC_PANamedOCcupants = $coverages['premium'];
                    }
                }
                $final_gst_amount = $response_data['serviceTax'];
                $final_net_premium = $response_data['netPremium'];
                $final_payable_amount = $response_data['renewalPremium'];
            
            
                $proposal->idv                  = $idv;
                $proposal->proposal_no          = $response_data['quoteId'];
                $proposal->unique_proposal_id   = $response_data['quoteId'];
                $proposal->customer_id          = $response_data['clientCode'];
                $proposal->od_premium           = $response_data['totalodpremium'];
                $proposal->tp_premium           = $response_data['totaltppremium'];
                $proposal->ncb_discount         = $response_data['totaltppremium'];
                $proposal->addon_premium        = $addon_premium;
                $proposal->total_premium        = $final_net_premium;
                $proposal->service_tax_amount   = $final_gst_amount;
                $proposal->final_payable_amount = $final_payable_amount;
                $proposal->cpa_premium          = $NoCliamDiscount;
                $proposal->total_discount       = $total_discount;
                $proposal->ic_vehicle_details   = $vehicleDetails;
                $proposal->policy_start_date    = $policy_start_date;
                $proposal->policy_end_date      = $policy_end_date;
                $proposal->save();
                
                $updateJourneyStage['user_product_journey_id'] = $enquiryId;
                $updateJourneyStage['ic_id'] = '35';
                $updateJourneyStage['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                $updateJourneyStage['proposal_id'] = $proposal->user_proposal_id;
                updateJourneyStage($updateJourneyStage);
                
                return response()->json([
                    'status' => true,
                    'msg' => "Proposal Submitted Successfully!",
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => camelCase([
                        'proposal_no'        => $response_data['quoteId'],
                        'finalPayableAmount' => $final_payable_amount,
                        'kyc_url' => $kyc_url,
                        'is_kyc_url_present' => $is_kyc_url_present,
                        'kyc_message' => $kyc_message,
                        'kyc_status' => $proposal->is_ckyc_verified == 'Y' ? true : $kyc_status,
                    ]),
                ]);
            }
            else
            {
                return [
                    'status' => false,
                    'premium' => '0',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $data['message'] ?? 'Insurer not reachable'
                ];                
            }
        }
        else
        {
            return [
                'status' => false,
                'premium' => '0',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $data['message'] ?? 'Insurer not reachable'
            ];        
        }
    }
}