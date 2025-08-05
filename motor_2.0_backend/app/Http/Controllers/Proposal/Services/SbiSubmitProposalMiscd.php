<?php

namespace App\Http\Controllers\Proposal\Services;

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\Quotes\Cv\CvQuoteModel;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Services\SbiPremiumDetailController;
use Illuminate\Support\Carbon;
use Exception;
use App\Models\ckycUploadDocuments;
use App\Models\CvAgentMapping;
use App\Models\MasterPremiumType;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/SbiCkycHelper.php';

class SbiSubmitProposalMiscd
{

    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {

        if ($proposal->is_ckyc_verified != 'Y' && config('SBI.CKYC_VALIDATION') == 'Y') {
            return  [
                'status' => false,
                'message' => 'CKYC Failed'
            ];
        }
        if ($proposal->is_ckyc_verified != 'Y' && config('SBI.COMPANY_CASE.CKYC_VALIDATION') == 'Y' && $proposal->owner_type == 'C') {
            return  [
                'status' => false,
                'message' => 'CKYC Failed for Company Case'
            ];
        }
        try {

            # constant for SBI
            $TOKEN_URL = config('constants.IcConstants.sbi.MISCD_TOKEN_URL');
            $X_IBM_Client_Id = config('constants.IcConstants.sbi.MISCD_X_IBM_Client_Id');
            $X_IBM_Client_Secret = config('constants.IcConstants.sbi.MISCD_X_IBM_Client_Secret');


            $END_POINT_URL_MISCD_FULL_QUOTE = config('constants.IcConstants.sbi.END_POINT_URL_MISCD_FULL_QUOTE');

            $productData = getProductDataByIc($request['policyId']);
            $request['userProductJourneyId'] = customDecrypt($request['userProductJourneyId']);
            $requestData = getQuotation($request['userProductJourneyId']);
            $enquiryId = customDecrypt($request['enquiryId']);
            $quote = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            $jsontoarray = $quote->premium_json;
            $quotationNo = $jsontoarray['quotationNo'];

            $additional_details = json_decode($proposal->additional_details, true);

            $get_response = getWsData($TOKEN_URL, '', 'sbi', [
                'requestMethod' => 'get',
                'method' => 'Token Generation',
                'section' => $productData->product_sub_type_code,
                'enquiryId' => $enquiryId,
                'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                'transaction_type' => 'proposal',
                'client_id' => $X_IBM_Client_Id,
                'client_secret' => $X_IBM_Client_Secret
            ]);
            $token = $get_response['response'];

            if ($token) {
                $token_response = json_decode($token, true);
                if ($token_response) {
                    $access_token = $token_response['access_token'];
                } else {
                    throw new \Exception("Error in getting token");
                }
            } else {
                throw new \Exception("Insurer not reachable");
            }

            $mmv = get_mmv_details($productData, $requestData->version_id, 'sbi');

            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                throw new \Exception($mmv['message']);
            }

            $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

            if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
                throw new \Exception("Vehicle Not Mapped");
            } elseif ($mmv_data->ic_version_code == 'DNE') {
                throw new \Exception("Vehicle code does not exist with Insurance company");
            }


            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

            if ($premium_type == 'third_party_breakin') {
                $premium_type = 'third_party';
            }
            if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
                $policy_start_date = date('Y-m-d');
                $tp_start_date = date('Y-m-d');
               
                $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                $tp_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date)));
                $expiry_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            } elseif ($requestData->business_type == 'rollover') {
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                $tp_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));

                $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                $tp_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date)));
            }

            $kind_of_policy = '1';

            if ($requestData->business_type == 'newbusiness') {
                $business_type = 1;
                $previous_policy_expiry_date = '1900-01-01';
                $previous_policy_start_date = '1900-01-01';
                $kind_of_policy = '1';
            } else {
                $business_type = 2;
                $previous_policy_expiry_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
                $previous_policy_start_date = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
                $kind_of_policy = ($premium_type == 'third_party') ? '2' : '1';
            }

            $rto_payload = DB::table('sbi_rto_location')
                ->where('rto_location_code', $requestData->rto_code)
                ->first();

            $rto_dic_id = DB::table('pcv_t_rto_location_cmv')->where('LOC_NAME', $rto_payload->rto_location_description)
                ->where('ID', $rto_payload->id)->first();
            $rto_dic_id = keysToLower($rto_dic_id);
            $rto_payload->rto_dis_code = $rto_dic_id->rto_dis_code;
            $rto_payload->LOC_ID = $rto_dic_id->loc_id;

            $rto_code = explode('-', $requestData->rto_code);


            if ($premium_type == 'comprehensive') {
                $policy_type = 1;
            } else {
                $policy_type = 2;
            }
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $vehicle_age = car_age($vehicleDate, $requestData->previous_policy_expiry_date);

            $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->select('applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts', 'compulsory_personal_accident')
                ->first();

            $electrical_accessories = false;
            $electrical_accessories_amount = 0;
            $non_electrical_accessories = false;
            $non_electrical_accessories_amount = 0;
            $external_fuel_kit = false;
            $external_fuel_kit_amount = 0;


            # for Addons -
            $zero_dep_selection_status = false;
            $imt23_status = false;

            if (!empty($additional['applicable_addons'])) {
                foreach ($additional['applicable_addons'] as $key => $value) {
                    if ($value['name'] == 'Zero Depreciation' && $vehicle_age <= 5) {
                        $zero_dep_selection_status = true;
                    }
                    if ($value['name'] == 'IMT - 23') {
                        $imt23_status = true;
                    }
                }
            }

            if (!empty($additional['accessories'])) {
                foreach ($additional['accessories'] as $key => $data) {
                    if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        // if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 50000) {
                        //     throw new \Exception("Vehicle External Bi-Fuel Kit CNG/LPG value should be between 10000 to 50000");
                        // } else {
                            $external_fuel_kit = true;
                           //
                            $external_fuel_kit_amount = $data['sumInsured'];
                        // }
                    }

                    if ($data['name'] == 'Non-Electrical Accessories') {
                        // if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                        //     throw new \Exception("Vehicle non-electric accessories value should be between 10000 to 25000");
                        // } else {
                            $non_electrical_accessories = true;
                            $non_electrical_accessories_amount = $data['sumInsured'];
                        // }
                    }

                    if ($data['name'] == 'Electrical Accessories') {
                        // if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                        //     throw new \Exception("Vehicle electric accessories value should be between 10000 to 25000");
                        // } else {
                            $electrical_accessories = true;
                            $electrical_accessories_amount = $data['sumInsured'];
                        // }
                    }
                }
            }

            $tppd_status = false;

            if (!empty($additional['discounts'])) {
                foreach ($additional['discounts'] as $key => $data) {
                    if ($data['name'] == 'anti-theft device') {
                        //
                    }

                    if ($data['name'] == 'TPPD Cover') {
                        $tppd_status = true;
                    }

                    if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                        //
                    }
                }
            }

            $cpa_selection_status = false;
            $inbuilt_fuel_kit = false;
            if ($mmv_data->fuel_type == 'CNG (Inbuilt)') {
                $inbuilt_fuel_kit = true;
            }
            if (!empty($additional['compulsory_personal_accident'])) {
                foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                        $cpa_selection_status = true;
                    }
                }
            }

            $cover_ll_paid_driver = $pa_ccc = false;
            $no_of_driver = 0;
            $no_of_CCC = 0;
            $pa_sumInsured = 0;
            $is_geo_code = 0;
            $srilanka = 0;
            $pak = 0;
            $bang = 0;
            $bhutan = 0;
            $nepal = 0;
            $maldive = 0;

            if (!empty($additional['additional_covers'])) {
                foreach ($additional['additional_covers'] as $key => $data) {
                    if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                        //
                    }

                    if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                        //
                    }

                    // if ($data['name'] == 'LL paid driver') {
                    //     $cover_ll_paid_driver = true;
                    // }

                    if ($data['name'] == 'LL paid driver/conductor/cleaner') {
                        $cover_ll_paid_driver = true;
                        $no_of_driver = isset($data['LLNumberDriver']) ? $data['LLNumberDriver'] : 0;
                        $no_of_cleaner = isset($data['LLNumberCleaner']) ? $data['LLNumberCleaner'] : 0;
                        $no_of_conductor = isset($data['LLNumberConductor']) ? $data['LLNumberConductor'] : 0;
                        $no_of_CCC = $no_of_cleaner + $no_of_conductor;
                    }

                    if ($data['name'] == 'PA paid driver/conductor/cleaner' && $requestData->vehicle_owner_type == 'I') {
                        $pa_ccc = true;
                        $pa_sumInsured = isset($data['sumInsured']) ? (int) $data['sumInsured'] : 0;
                        $no_of_driver = 1;
                    }
                    if ($data['name'] == 'Geographical Extension') {
                        $is_geo_ext = true;
                        $is_geo_code = 1;
                        $countries = $data['countries'];
                        if (in_array('Sri Lanka', $countries)) {
                            $srilanka = 1;
                        }
                        if (in_array('Bangladesh', $countries)) {
                            $bang = 1;
                        }
                        if (in_array('Bhutan', $countries)) {
                            $bhutan = 1;
                        }
                        if (in_array('Nepal', $countries)) {
                            $nepal = 1;
                        }
                        if (in_array('Pakistan', $countries)) {
                            $pak = 1;
                        }
                        if (in_array('Maldives', $countries)) {
                            $maldive = 1;
                        }
                    }
                }
            }

            $registartion_data = explode(
                '-',
                $proposal->vehicale_registration_number
            );

            $vehicle_sub_class = [
                'Agriculture Tractor' => 2,
                'Ambulances' => 3,
                'Angle Dozers' => 4,
                'Anti malarial Vans' => 5,
                'Breakdown Vehicles' => 6,
                'Bulldozers, Bullgraders' => 7,
                'Cinema Film Recording and Publicity vans' => 8,
                'Clark tractor Elevators' => 9,
                'Compressors' => 10,
                'Cranes' => 11,
                'Delivery Trucks Pedestrain Controlled' => 12,
                'Dispensaries' => 13
            ];

            $misc_vehicle_sub_class = $vehicle_sub_class[$mmv_data->bodystyle_desc];


            $pincodePayload = DB::table('sbi_pincode_state_city_master')
                ->where('CITY_CD', '=', $proposal->city_id)
                ->first();
            $pincodePayload = keysToLower($pincodePayload);
            $fuel = [
                'Petrol'              =>     '1',
                'Diesel'              =>     '2',
                'CNG (Inbuilt)'       =>     '3',
                'LPG (Inbuilt)'       =>     '4',
                'Hybrid'              =>     '5',
                'Any other'           =>     '6',
                'Battery'             =>     '7',
                'CNG (External Kit)'  =>     '8',
                'LPG (External Kit)'  =>     '9',
            ];
            $address_data = [
                'address' => $proposal->address_line1,
                'address_1_limit'   => 24,
                'address_2_limit'   => 24,
                'address_3_limit'   => 100,
            ];
            $getAddress = getAddress($address_data);

            //CKYC Address
            $address_ckyc_data = json_decode($proposal->ckyc_meta_data);

            $fuel_type_id = isset($fuel[$mmv_data->fuel_type]) && !empty($fuel[$mmv_data->fuel_type]) ? $fuel[$mmv_data->fuel_type] : '';
            $premium_calculation_request = [
                'RequestHeader' => [
                    'requestID' => mt_rand(100000, 999999),
                    'action' => 'fullQuote',
                    'channel' => 'SBIG',
                    'transactionTimestamp' => date('d-M-Y-H:i:s')
                ],
                'RequestBody' => [
                    'QuotationNo' => $quotationNo,
                    // 'AdditonalCompDeductible' => '',
                    /* 'AgentType' => 'POSP',
                    'AgentFirstName' => '',
                    'AgentMiddleName' => '',
                    'AgentLastName' => '',
                    'AgentPAN' => '',
                    'AgentMobile' => '',
                    'AgentBranchID' => '',
                    'AgentBranch' => '', */
                    'AgreementCode' => config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE'), //'6660', //'0006660',
                    'BusinessSubType' => 5, #deafault value according to kit
                    'BusinessType' => $business_type,
                    // 'giChannelType' => '3',  //Commenting as per git 34559
                    // 'AlternatePolicyNo' => 'AlternatePolicyNo',
                    // 'CoInsurancePer' => '',
                    // 'CustomerGSTINNo' => '22AAAAA00001Z5',
                    // 'CustomerSegment' => 'CustomerSegment',
                    'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date . 'T' . date('H:i:s') : $policy_start_date . 'T00:00:00',
                    'ExpiryDate' => $policy_end_date . 'T23:59:59',
                    // 'UWDiscount' => 0,
                    // 'UWLoading' => 0,
                    'GSTType' => 'IGST',
                    'HasPrePolicy' =>  $requestData->business_type == 'newbusiness' ? '0' : '1',
                    'IntermediaryCode' => '',
                    // 'InspectionDoneBy' => '1',
                    // "IssuingBranchCode" => "IssuingBranchCode",
                    // 'IssuingBranchGSTN' => '22AAAAA00001Z0',
                    'KindofPolicy' => $kind_of_policy,
                    "LossNature" => "1",
                    'NewBizClassification' => (string) $business_type,
                    // "UWDiscount" => "15",
                    'PolicyCustomerList' => [
                        [
                            // 'AadharNumUHID' => 'AadharNumUHID',
                            'PlotNo' => $address_ckyc_data->addressLine1 ?? $getAddress['address_1'] ?? '',
                            'BuildingHouseName' => $address_ckyc_data->addressLine2 ?? $getAddress['address_2'] ??  '',
                            'StreetName' => $address_ckyc_data->addressLine3 ?? $getAddress['address_3'] ??  '',
                            'City' => $proposal->city_id,
                            'Email' => $proposal->email,
                            'ContactEmail' => $proposal->email,
                            'ContactPersonTel' => $proposal->mobile_number,
                            'CountryCode' => '1000000108',
                            "FirstName" => $proposal->first_name,
                            "MiddleName" => '',
                            "LastName" => $proposal->last_name,
                            'CustomerName' => $proposal->first_name . ' ' . $proposal->last_name,
                            'DateOfBirth' => $requestData->vehicle_owner_type != 'I' ? '' : date('Y-m-d', strtotime($proposal->dob)),
                            'District' => $pincodePayload->district_code,
                            'GSTRegistrationNum' => !empty($proposal->gst_number) ? $proposal->gst_number : '',
                            'GenderCode' => $proposal->gender,
                            'IdNo' => !empty($proposal?->pan_number) ? $proposal?->pan_number : '',
                            'IdType' => '1',
                            'IsInsured' => 'N',
                            'IsOrgParty' => $requestData->vehicle_owner_type == 'I' ? 'N' : 'Y',
                            'IsPolicyHolder' => 'Y',
                            // 'Locality' => 'xvxcgdf',
                            'Mobile' => $proposal->mobile_number,
                            'NationalityCode' => 'IND',
                            'OccupationCode' => $proposal->occupation,
                            'PartyStatus' => '1',
                            'PostCode' => $proposal->pincode,
                            'PreferredContactMode' => 'EMAIL',
                            'SBIGCustomerId' => '',
                            'STDCode' => '',
                            'State' => $proposal->state_id, #$rto_payload->id,
                            // 'Title' => '9000000001',
                            // 'EducationCode' => '2300000003',
                            // 'GroupCompanyName' => 'SBIG',
                            // 'GroupEmployeeId' => 'GroupEmployeeId',
                            // 'CampaignCode' => 'CampaignCode',
                            // 'ISDNum' => 'ISDNum',
                            'MaritalStatus' => '1', # ? clear with pdf
                            //Regulatory changes *DUMMY DATA for quote page*
                            "AccountNo" => $additional_details['owner']['accountNumber'] ?? '',
                            "IFSCCode"  => $additional_details['owner']['ifsc'] ?? '',
                            "BankName"  => $additional_details['owner']['bankName'] ?? '',
                            "BankBranch" => $additional_details['owner']['branchName'] ?? '',
                            "DateOfIncorporation" => ($requestData->vehicle_owner_type == 'C') ? date('Y-m-d', strtotime($proposal->dob)) : '',
                        ],
                    ],
                    'PolicyLobList' => [
                        [
                            'PolicyRiskList' => [
                                [
                                    // 'PCVVehicleSubClass' => '1',
                                    /* 'IsForeignEmbassyVeh' => 0,
                                    'ProposedUsage' => '1',
                                    'MonthlyUseDistance' => '1',
                                    'VehicleCategory' => '1',
                                    'DayParkLoc' => '1',
                                    'DayParkLocPinCode' => '388215',
                                    'Last3YrDriverClaimPresent' => 0,
                                    'NoOfDriverClaims' => 1,
                                    'DriverClaimAmt' => '10000',
                                    'ClaimStatus' => 'false',
                                    'ClaimType' => '1', */
                                    'NightParkLoc' => 1,
                                    'DayParkLoc' => 1,
                                    'NightParkLocPinCode' => $proposal->pincode,
                                    'DayParkLocPinCode' => $proposal->pincode,
                                    'CountCCC' => $no_of_CCC,
                                    "IMT34" => 0,
                                    // "SiteAddress" => "SiteAddress",
                                    "IMT23" => $imt23_status === true ? 1 : 0,
                                    "IMT43" => 0,
                                    "IMT44" => 0,
                                    "AdditionalTowingCharges" => 0,
                                    'AntiTheftAlarmSystem' => 0,
                                    'BodyType' => 'Sedan',
                                    'FuelType' => $external_fuel_kit ? '8' : (($inbuilt_fuel_kit && ($mmv_data->fuel_type == 'CNG (Inbuilt)')) ? '3' : $fuel_type_id),
                                    'ChassisNo' => removeSpecialCharactersFromString($proposal?->chassis_number),
                                    'NFPPCount' => 0,
                                    'EmployeeCount' => 0,
                                    'EngineNo' =>  $proposal->engine_number,
                                    // 'FNBranchName' => $proposal->is_vehicle_finance == '1' ? 'FNBranchName' : '',
                                    // 'FNName' => $proposal->is_vehicle_finance == '1' ? $proposal->name_of_financer : '',
                                    // 'FNType' => $proposal->is_vehicle_finance == '1' ? '3' : '1',     //  $FNType => add here... bydefault added 1
                                    // 'FNBranchName' => $proposal->financer_location,          //Handling financier details below
                                    'GeoExtnBangladesh' => $bang,
                                    'GeoExtnBhutan' => $bhutan,
                                    'GeoExtnMaldives' => $maldive,
                                    'GeoExtnNepal' => $nepal,
                                    'GeoExtnPakistan' => $pak,
                                    'GeoExtnSriLanka' => $srilanka,
                                    // 'VehicleBodyPrice' => 10000,
                                    // 'IsConfinedOwnPremises' => 0,
                                    // 'IsDrivingTuitionsUse' => 0,
                                    'IsFGFT' => 0,
                                    'IsGeographicalExtension' => $is_geo_code,
                                    'IsNCB' => 0, //Made changes according to git #30395 $requestData->business_type == 'newbusiness' ? '0' : ($requestData->is_claim == 'Y' ? '1' : 0),
                                    'IsNewVehicle' => $requestData->business_type == 'newbusiness' ? '1' : 0,
                                    'ManufactureYear' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                                    'NCB' => $requestData->applicable_ncb / 100,
                                    'NCBLetterDate' => $requestData->applicable_ncb == 0 ? "" : '2021-09-09T00:00:00.000',
                                    'NCBPrePolicy' =>  $requestData->previous_ncb / 100,
                                    // 'NCBProof' => '1',
                                    'PaidDriverCount' => $no_of_driver,
                                    'ProductElementCode' => 'R10005',
                                    'RTOCityDistric' => $rto_payload->rto_dis_code, //$rto_data->rto_dis_code,
                                    'RTOCluster' => $rto_payload->id,
                                    'RTOLocation' => $rto_payload->rto_location_description, // $rto_data->rto_location_code,
                                    'RTONameCode' => $rto_payload->id,
                                    'RTORegion' => $rto_payload->rto_region,
                                    'RTOLocationID' => $rto_dic_id->loc_id,
                                    'RegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                                    //'RegistrationNo' => $proposal->vehicale_registration_number,
                                    'RegistrationNo' => $requestData->business_type == 'newbusiness' ? "" : str_replace('-', '', $proposal->vehicale_registration_number),
                                    "RegistrationNoBlk1" => $requestData->business_type == 'newbusiness' ? (isset($rto_code[0]) ? $rto_code[0] : "") : (isset($registartion_data[0]) ? $registartion_data[0] : ""),
                                    "RegistrationNoBlk2" => $requestData->business_type == 'newbusiness' ? (isset($rto_code[1]) ? $rto_code[1] : "") : (isset($registartion_data[1]) ? $registartion_data[1] : ""),
                                    "RegistrationNoBlk3" => $requestData->business_type == 'newbusiness' ? "" : (isset($registartion_data[2]) ? $registartion_data[2] : ""),
                                    "RegistrationNoBlk4" => $requestData->business_type == 'newbusiness' ? "" : (isset($registartion_data[3]) ? $registartion_data[3] : ""),
                                    'TrailerCount' => 0,
                                    // 'TrailerType' => '',
                                    'Variant' =>  $mmv_data->var_id, 
                                    'VehicleMake' => $mmv_data->mk_id,
                                    'VehicleModel' => $mmv_data->mod_id,
                                    'PolicyCoverageList' => [],
                                    'IDV_User' => $quote->idv,
                                ],
                            ],
                            'ProductCode' => 'CMVMI01',
                            'PolicyType' => $policy_type,
                        ],
                    ],
                    /* 'PreInsuranceComAddr' => $requestData->business_type == 'newbusiness' ? '' : 'Mumbai',
                    'PreInsuranceComName' => $requestData->business_type == 'newbusiness' ? '' : '15',
                    'PrePolicyEndDate' => $requestData->business_type == 'newbusiness' ? '' : $previous_policy_expiry_date . 'T23:59:59',
                    'PrePolicyNo' => $requestData->business_type == 'newbusiness' ? '' : '87654345678',
                    'PrePolicyStartDate' => $requestData->business_type == 'newbusiness' ? '' : $previous_policy_start_date . 'T00:00:00', */
                    'PremiumCurrencyCode' => 'INR',
                    'PremiumLocalExchangeRate' => 1,
                    'ProductCode' => 'CMVMI01',
                    'ProductVersion' => '1.0',
                    'ProposalDate' => $policy_start_date,
                    'QuoteValidTo' => date('Y-m-d', strtotime('+30 day', strtotime($policy_start_date))),
                    'SBIGBranchStateCode' => $rto_payload->id,
                    // 'SBIGBranchCode' => 'SBIGBranchCode',
                    'SiCurrencyCode' => 'INR',
                    // "SimFlowId" => "SimFlowId",
                    'TransactionInitiatedBy' => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY')
                ]
            ];
            if (config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y') {
                $premium_calculation_request['RequestBody']['SourceType'] = config('constants.IcConstants.sbi.SBI_CV_SOURCE_TYPE', '9');
            }
            #previous policy details in new business case
            if ($requestData->business_type != 'newbusiness' && ($proposal->previous_insurance_company != '')) {

                $preinsurercode = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();

                $previous_insurers_correctaddress = isset($preinsurercode->address_line_1) ? $preinsurercode->address_line_1 : '';
                $previous_insurers_correctaddress2 = isset($preinsurercode->address_line_2) ? $preinsurercode->address_line_2 : '';

                $premium_calculation_request['RequestBody']['PreInsuranceComAddr'] = $previous_insurers_correctaddress . ',' . $previous_insurers_correctaddress2;
                $premium_calculation_request['RequestBody']['PreInsuranceComName'] = $proposal->previous_insurance_company;
                $premium_calculation_request['RequestBody']['PrePolicyNo'] = $proposal->previous_policy_number;
                $premium_calculation_request['RequestBody']['PrePolicyEndDate'] = $previous_policy_expiry_date . 'T23:59:59';
                $premium_calculation_request['RequestBody']['PrePolicyStartDate'] = $previous_policy_start_date . 'T' . date('H:i:s');
            }


            if ($requestData->vehicle_owner_type != 'I') {
                unset($premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['GenderCode']);
            }
            if ($requestData->vehicle_owner_type == 'I') {
                $title = ['Title' => (($proposal->gender == 'M') ? '9000000001' : '9000000003'),];
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0] = array_merge($premium_calculation_request['RequestBody']['PolicyCustomerList'][0], $title);
            }

            if (($requestData->is_claim == 'N')) {
                unset($premium_calculation_request['RequestBody']['LossNature']);
            }

            if ($requestData->vehicle_owner_type != 'C') {
                unset($premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DateOfIncorporation']);
            }
            
            #pos details
            $is_pos = false;
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_SBI_TESTING');
            $posp_name = '';
            $posp_unique_number = '';
            $posp_pan_number = '';

            $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('seller_type', 'P')
                ->first();

            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                $pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('sbi_code')
                    ->first();
                if ((empty($pos_code) || is_null($pos_code)) && (config('IS_SBI_CV_NON_POS') != 'Y')) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'message' => 'Child Id Is Not Available'
                    ];
                }
                if (config('IS_SBI_CV_NON_POS') != 'Y') {
                    $is_pos = true;
                    $posp_name = $pos_data->agent_name;
                    $posp_unique_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
                    $posp_pan_number = $pos_data->pan_no;
                }
            } else if ($is_pos_enabled_testing == 'Y') {
                $is_pos = true;
                $posp_name = 'test';
                $posp_unique_number = '9768574564';
                $posp_pan_number = '569278616999';
            }

            if ($is_pos) {
                $premium_calculation_request['RequestBody']['AgentType'] = 'POSP';
                $premium_calculation_request['RequestBody']['AgentFirstName'] = $posp_name;
                $premium_calculation_request['RequestBody']['AgentMiddleName'] = '';
                $premium_calculation_request['RequestBody']['AgentLastName'] = '';
                $premium_calculation_request['RequestBody']['AgentPAN'] = $posp_pan_number;
                $premium_calculation_request['RequestBody']['AgentMobile'] = $posp_unique_number;
                $premium_calculation_request['RequestBody']['AgentBranchID'] = '';
                $premium_calculation_request['RequestBody']['AgentBranch'] = '';
            }


            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['MISDVehicleSubClass'] = $misc_vehicle_sub_class;
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['MonthlyUseDistance'] = '1';
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['TypeOfGoodsCarried'] = '2';


            if ($premium_type != 'third_party') {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101064',
                    'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date . 'T' . date('H:i:s') : $policy_start_date . 'T00:00:00',
                    'PolicyBenefitList' => [
                        [
                            'ProductElementCode' => 'B00002',
                        ]
                    ],
                    'ExpiryDate' => ($policy_type == '6') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
                ];
            }

            # for TP -
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'ProductElementCode' => 'C101065',
                'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date . 'T' . date('H:i:s') :  $policy_start_date . 'T00:00:00',
                'PolicyBenefitList' => [
                    [
                        'ProductElementCode' => 'B00008',
                    ]
                ],
                'ExpiryDate' => ($policy_type == '6') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
            ];

            # for Addons -
            if ($zero_dep_selection_status && $productData->product_identifier == 'zero_dep_sbi') {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101072',
                    'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date . 'T' . date('H:i:s') :  $policy_start_date . 'T00:00:00',
                    'ExpiryDate' => ($policy_type == '6') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
                ];
            }

            if ($cpa_selection_status && $requestData->vehicle_owner_type == 'I') {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date . 'T' . date('H:i:s') :  $policy_start_date . 'T00:00:00',
                    'PolicyBenefitList' => [
                        [
                            'SumInsured' => 1500000,
                            'ProductElementCode' => 'B00015',
                            'NomineeName' => $proposal->nominee_name,
                            'NomineeRelToProposer' => $proposal->nominee_relationship,
                            'NomineeDOB' => date('Y-m-d', strtotime($proposal->nominee_dob)),
                            'NomineeAge' => $proposal->nominee_age,
                            'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date . 'T' . date('H:i:s') : $policy_start_date . 'T00:00:00',
                            'ExpiryDate' => $policy_end_date . 'T23:59:59',
                            'AppointeeName' => '',
                            'AppointeeRelToNominee' => '2',
                        ]
                    ],
                    'OwnerHavingValidDrivingLicence' => '1',
                    'OwnerHavingValidPACover' => '1',
                    'ExpiryDate' => ($requestData->business_type == 'newbusiness') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
                    'ProductElementCode' => 'C101066',
                ];
            }

            #PA Owner driver/conductor/cleaner
            if ($pa_ccc && $requestData->vehicle_owner_type == 'I') {

                if ($cpa_selection_status) {
                    $index_id = search_for_id_sbi('C101066', $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];

                    if (isset($index_id) === true) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                            'Description' => 'Description',
                            'SumInsuredPerUnit' => $pa_sumInsured,
                            'TotalSumInsured' => $pa_sumInsured,
                            'ProductElementCode' => 'B00073'
                        ];
                    } else {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                            'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date . 'T' . date('H:i:s') : $policy_start_date . 'T00:00:00',
                            'PolicyBenefitList' => [
                                [
                                    'Description' => 'Description',
                                    'SumInsuredPerUnit' => $pa_sumInsured,
                                    'TotalSumInsured' => $pa_sumInsured,
                                    'ProductElementCode' => 'B00073'
                                ]
                            ],
                            'ExpiryDate' => ($requestData->business_type == 'newbusiness') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
                            'ProductElementCode' => 'C101066',
                        ];
                    }
                } else {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                        'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date . 'T' . date('H:i:s') : $policy_start_date . 'T00:00:00',
                        'PolicyBenefitList' => [
                            [
                                'Description' => 'Description',
                                'SumInsuredPerUnit' => $pa_sumInsured,
                                'TotalSumInsured' => $pa_sumInsured,
                                'ProductElementCode' => 'B00073'
                            ]
                        ],
                        'ExpiryDate' => ($requestData->business_type == 'newbusiness') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
                        'ProductElementCode' => 'C101066',
                    ];
                }
            }

            $add_cover = $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];

            foreach ($add_cover as $add) {
                if ($add['ProductElementCode'] == 'C101064') {
                    if ($non_electrical_accessories) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                            'SumInsured' => $non_electrical_accessories_amount,
                            'Description' => '',
                            'ProductElementCode' => 'B00003'
                        ];
                    }

                    if ($electrical_accessories) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                            'SumInsured' => $electrical_accessories_amount,
                            'Description' => '',
                            'ProductElementCode' => 'B00004'
                        ];
                    }

                    if ($external_fuel_kit) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                            'SumInsured' => $external_fuel_kit_amount,
                            'Description' => 'Description',
                            'ProductElementCode' => 'B00005'
                        ];
                    }

                    if ($inbuilt_fuel_kit) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                            'ProductElementCode' => 'B00006'
                        ];
                    }
                }

                if ($add['ProductElementCode'] == 'C101065') {
                    $index_id = search_for_id_sbi('C101065', $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];

                    if ($external_fuel_kit || $inbuilt_fuel_kit) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                            'SumInsured' => $external_fuel_kit_amount,
                            'Description' => 'Description',
                            'ProductElementCode' => 'B00010'
                        ];
                    }


                    if ($cover_ll_paid_driver) {
                        
                        if($no_of_driver !=  0 ){
                            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                                // 'Description' => 'Description', //Commenting as per git 34559
                                'ProductElementCode' => 'B00013'
                            ];
                        }

                        if ($no_of_conductor == 1  || $no_of_cleaner == 1) {
                            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                                // 'Description' => 'Description', //Commenting as per git 34559
                                'ProductElementCode' => 'B00069'
                            ];
                        }
                    }

                    if ($tppd_status) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                            // 'Description' => 'Description',
                            'SumInsured' => 6000,
                            'ProductElementCode' => 'B00009'
                        ];
                    }
                }
            }

            if ($premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == '0' || $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == 0) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate'] = '';
            }
            //Hypothetecation
            if ($proposal->is_vehicle_finance == '1') {
                $financetype = [
                    'FNName' => $proposal->name_of_financer,
                    'FNType' => $proposal->financer_agreement_type,
                    'FNBranchName' => $proposal->financer_location,
                ];
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0] = array_merge($premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0], $financetype);
            }

            //CKYC CHNAGES START

            $is_ckyc_verified = $proposal->is_ckyc_verified;
            $proposal->is_ckyc_details_rejected == 'Y' ? true : false;

            /* 1. PAN
            2. Passport
            3. Ration ID
            4. Voter ID
            5. GOV UID
            6. Driving License
            7. Aadhar  */
            $DOCTypeId = NULL;
            $DOCTypeName = NULL;
            switch ($proposal->ckyc_type) {
                case 'pan_card':
                    $DOCTypeId = 1;
                    $DOCTypeName = $proposal->ckyc_type_value;
                    break;

                case 'passport':
                    $DOCTypeId = 2;
                    $DOCTypeName = 'Passport';
                    break;

                case 'ration_id':
                    $DOCTypeId = 3;
                    $DOCTypeName = 'Ration ID';
                    break;

                case 'voter_id':
                    $DOCTypeId = 4;
                    $DOCTypeName = 'Voter ID';
                    break;

                case 'driving_license':
                    $DOCTypeId = 6;
                    $DOCTypeName = 'Driving License';
                    break;

                case 'aadhar_card':
                    $DOCTypeId = 7;
                    $DOCTypeName = $proposal->ckyc_type_value;
                    break;

                default:
                    break;
            }
            if ($requestData->business_type == 'newbusiness') {
                unset($premium_calculation_request['RequestBody']['KindofPolicy']);
            }

            if ($is_ckyc_verified != 'Y') {
                $CKYCUniqueId = SbiSubmitProposal::getUniqueId($proposal);
                $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
                $get_doc_data = json_decode($document_upload_data->cky_doc_data ?? '', true);

                if (empty($get_doc_data) || empty($document_upload_data)) {
                    return response()->json([
                        'data' => [
                            'message' => 'No documents found for CKYC Verification. Please upload any and try again.',
                            'verification_status' => false,
                        ],
                        'status' => false,
                        'message' => 'No documents found for CKYC Verification. Please upload any and try again.'
                    ]);
                } else {
                    try {
                        if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y' && $proposal->proposer_ckyc_details?->is_document_upload  == 'Y') {
                            $ckyc_doc_validation = ckycVerifications($proposal);
                            if ($ckyc_doc_validation['status'] != 'false' && $ckyc_doc_validation['message'] != 'File Upload successfully at both place') {
                                return [
                                    'status' => false,
                                    'message' => 'File Upload Unsuccessfully' . $ckyc_doc_validation['message']
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        \Illuminate\Support\Facades\Log::error('SBI KYC EXCEPTION trace_id=' . customEncrypt($proposal->user_product_journey_id), array($e));
                    }
                }
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['CKYCVerified'] = 'N';
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['KYCCKYCNo']    = '';
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DOCTypeId']    = '';
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DOCTypeName']  = '';
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'] = $CKYCUniqueId; //$proposal->ckyc_reference_id;
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['CKYCSourceType'] = strtoupper(config('constants.motorConstant.SMS_FOLDER'));
            } else {
                $CKYCUniqueId = SbiSubmitProposal::getUniqueId($proposal);
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['CKYCVerified'] = $is_ckyc_verified;
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['KYCCKYCNo']    = $is_ckyc_verified == 'Y' ? $proposal->ckyc_number : NULL;
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DOCTypeId']    = $is_ckyc_verified == 'Y' ? $DOCTypeId : '';
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DOCTypeName']  = $is_ckyc_verified == 'Y' ? $DOCTypeName : NULL;
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'] = $CKYCUniqueId; //CKYCUniqueID to be passed according to git #30366
                $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['CKYCSourceType'] = strtoupper(config('constants.motorConstant.SMS_FOLDER'));
            }
            //OVD CKYC Verified Changes when discarding data
            if (isset($additional_details['owner']['isCkycDetailsRejected'])) {
                if ($additional_details['owner']['isCkycDetailsRejected'] == 'Y') {
                    UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update([
                        'is_ckyc_verified' => 'N',
                    ]);
                    $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['CKYCVerified'] = 'N';
                    $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['KYCCKYCNo']    = '';
                    $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DOCTypeId']    = '';
                    $premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DOCTypeName']  = ''; //Pass empty when data is discarded after ckyc verification success
                }
            }
            //END CKYC CHANGES




            $get_response = getWsData(
                $END_POINT_URL_MISCD_FULL_QUOTE,
                $premium_calculation_request,
                'sbi',
                [
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'authorization' => $access_token,
                    'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' Zero Dep' : ''),
                    'company'  => 'sbi',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Premium Calculation',
                    'transaction_type' => 'proposal',
                    'client_id' => $X_IBM_Client_Id,
                    'client_secret' => $X_IBM_Client_Secret
                ]
            );
            $data = $get_response['response'];

            if ($data) {
                $response = json_decode($data, true);
                if (isset($response['UnderwritingResult']['MessageList']) || isset($response['message']) || isset($response['ValidateResult']['message'])) {

                    if (isset($arr_premium['messages']) && isset($arr_premium['messages'][0]['message'])) {
                        $error_message = json_encode($arr_premium['messages'][0]['message'], true);
                    } else if (isset($response['ValidateResult']['message'])) {
                        $error_message = json_encode($response['ValidateResult']['message'], true);
                    } else {
                        $error_message = json_encode($response['UnderwritingResult']['MessageList'], true);
                    }
                    return [
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => $error_message
                    ];
                }
                #idv deviation more than 50lakh
                $idv = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] ?? '';
                if ($idv >= 5000000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'proposal submition not  allowed as Vehicle IDV  selected is greater or equal to 50 lakh'
                    ];
                }
                # NCB Discount
                $ncb_discount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBDiscountAmt'] ?? 0;

                $policyCoverageList = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];



                foreach ($policyCoverageList as $policyCoverageListkey => $policyCoverageListvalue) {

                    if ($policyCoverageListvalue['ProductElementCode'] == 'C101064') {
                        foreach ($policyCoverageListvalue['PolicyBenefitList'] as $PolicyBenefitListkey => $PolicyBenefitListvalue) {
                            # Basic Own Damage
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00002') {
                                //
                            }
                            # Non Electrical Accessories
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00003') {
                                //
                            }
                            # Electrical Accessories
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00004') {
                                //
                            }
                            # CNG/LPG kit
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00005') {
                                //
                            }
                            # In CNG/LPG kit
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00006') {
                                //
                            }
                        }
                    }

                    if ($policyCoverageListvalue['ProductElementCode'] == 'C101065') {
                        foreach ($policyCoverageListvalue['PolicyBenefitList'] as $PolicyBenefitListkey => $PolicyBenefitListvalue) {
                            # Basic TP
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00008') {
                                //
                            }
                            # Basic Tp - CNG/LPG kit
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00010') {
                                //
                            }
                            # legal liability to paid driver
                            if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00013') {
                                //
                            }
                        }
                    }
                }


                $final_gst_amount = 0;

                $final_payable_amount = isset($response['PolicyObject']['DuePremium']) ? $response['PolicyObject']['DuePremium'] : 0;

                UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'od_premium' => 0,
                        'tp_premium' => 0,
                        'ncb_discount' => round($ncb_discount),
                        'proposal_no' => $quotationNo,
                        'addon_premium' => 0,
                        'total_premium' => 0,
                        'service_tax_amount' => $final_gst_amount,
                        'final_payable_amount' => $final_payable_amount,
                        'cpa_premium' => 0,
                        'unique_proposal_id' => $quotationNo,
                        'policy_start_date' =>  date('d-m-Y', strtotime($policy_start_date)),
                        'policy_end_date' =>  date('d-m-Y', strtotime($policy_end_date)),
                        'tp_start_date' =>  date('d-m-Y', strtotime($tp_start_date)),
                        'tp_end_date' =>  date('d-m-Y', strtotime($tp_end_date)),
                        'ic_vehicle_details' => 0,
                        'is_breakin_case' => 'N',
                    ]);

                    //updating stage
                    updateJourneyStage([
                        'user_product_journey_id' => $request['userProductJourneyId'],
                        'ic_id' => $productData->company_id,
                        'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                        'proposal_id' => $proposal->user_proposal_id,
                    ]);

                SbiPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y') {
                    $kyc_status = $verification_status = $status = ($proposal->is_ckyc_verified == 'Y');
                    $msg = $kyc_message = 'Proposal Submitted Successfully!';


                    return response()->json([
                        'status' => true,
                        'msg' => $msg,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'verification_status' => $verification_status,
                        'data' => $status ? [
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal->user_product_journey_id,
                            'finalPayableAmount' => $final_payable_amount,
                            'is_breakin' => 'N',
                            'inspection_number' => '',
                            'kyc_status' => $kyc_status,
                            'kyc_message' => $kyc_message,
                        ] : null
                    ]);
                }

                return response()->json([
                    'status' => true,
                    'msg' => "Proposal Submitted Successfully!",
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $proposal->user_product_journey_id,
                        'finalPayableAmount' => $final_payable_amount,
                        'is_breakin' => 'N',
                        'inspection_number' => ''
                    ]
                ]);
            } else {
                throw new \Exception("Something Went wrong");
            }
        } catch (\Exception $e) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $e->getMessage(),
                'line_no' => $e->getLine(),
                'file' => pathinfo($e->getFile())['basename']
            ];
        }
    }
}
