<?php

namespace App\Http\Controllers\Proposal\Services\Pcv\V2;
include_once app_path().'/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/GoDigitHelper.php';

use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use Carbon\Carbon;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use App\Models\ProposalHash;
use Illuminate\Support\Facades\DB;
use App\Models\CkycGodigitFailedCasesData;
use App\Http\Controllers\Proposal\Services\goDigitSubmitProposal_Misc;
use App\Http\Controllers\SyncPremiumDetail\Services\GodigitPremiumDetailController;
use App\Models\CvBreakinStatus;
use DateTime;

class goDigitSubmitProposal
{
    public static function oneApisubmit($proposal, $request)
    {
        if(strlen($proposal->engine_number) > 20)
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Engine Number Not Greater than 20 Characters'
            ];            
        }
        $productData = getProductDataByIc($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);
        $policy_holder_type = ($proposal->owner_type == "I" ? "INDIVIDUAL" : "COMPANY");

       
        $mmv = get_mmv_details($productData, $requestData->version_id, 'godigit');
        
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];

        if ((config('constants.IS_CKYC_ENABLED') == 'Y') && (config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y') && (config('DIGIT_STATUS_CHECK_BEFORE_PROCEEDING') == 'Y') &&  !empty($proposal->proposal_no ?? '')) {
            if ($proposal->is_ckyc_verified == 'Y') {
                $responseArray = [
                    "status" => true,
                    "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "msg" => "Proposal Submited Successfully..!",
                    "data" => [
                        "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $proposal->user_product_journey_id,
                        'proposalNo' => $proposal->proposal_no,
                        'finalPayableAmount' => $proposal->final_payable_amount,
                    ]
                ];
                return response()->json($responseArray);
            } else {
                $commonController = new \App\Http\Controllers\CommonController();
                $godigitKycStatus = $commonController->GodigitKycStatus(new \Illuminate\Http\Request([
                    'UserProductJourneyId' => customEncrypt($proposal->user_product_journey_id)
                ]));
                $godigitKycStatus = $godigitKycStatus->getOriginalContent();
                if ($godigitKycStatus['status']) {
                    return response()->json($godigitKycStatus);
                }
            }
        }

        $premium_type = DB::table('master_premium_type')
                                ->where('id', $productData->premium_type_id)
                                ->pluck('premium_type_code')
                                ->first();

        $is_satp = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin');

        if($premium_type == 'third_party' || $premium_type == 'third_party_breakin') 
        {
            $insurance_product_code = '20302';
            $previousNoClaimBonus = 'ZERO';
        }
        else
        {
            $insurance_product_code = '20301';
            $ncb_percent = $requestData->previous_ncb;
            $no_claim_bonus = [
                                    '0'  => 'ZERO',
                                    '20' => 'TWENTY',
                                    '25' => 'TWENTY_FIVE',
                                    '35' => 'THIRTY_FIVE',
                                    '45' => 'FORTY_FIVE',
                                    '50' => 'FIFTY',
                                    '55' => 'FIFTY_FIVE',
                                    '65' => 'SIXTY_FIVE',
                            ];
            $previousNoClaimBonus = $no_claim_bonus[$ncb_percent] ?? 'ZERO';
        }

        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
        $motor_manf_year = $motor_manf_year_arr[1];
        $motor_manf_date = '01-'.$requestData->manufacture_year;
        $current_date = Carbon::now()->format('Y-m-d');
    
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') 
        {
            $is_vehicle_new = 'false';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $sub_insurance_product_code = 'PB';
            $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no != "" ? $requestData->vehicle_registration_no : $proposal->vehicale_registration_number);
            if($requestData->business_type == 'breakin')
            {
                if ($premium_type == 'third_party_breakin') 
                {        
                    $policy_start_date = date('Y-m-d', strtotime('+1 day'));
                } else {
                    $policy_start_date = date('Y-m-d'); 
                }
            }
        }    
        else if ($requestData->business_type == 'newbusiness') 
        {
            $is_vehicle_new = 'true';
            $policy_start_date = Carbon::today()->format('Y-m-d');
            $sub_insurance_product_code = 'PB';
            $previousNoClaimBonus = 'ZERO';
            if($requestData->vehicle_registration_no == 'NEW')
            {
                $vehicle_registration_no  = str_replace("-", "", godigitRtoCode($requestData->rto_code));
            }
            else
            {
                $vehicle_registration_no  = str_replace("-", "", $proposal->vehicale_registration_number);
            }
        }

        if ($premium_type == 'short_term_3' || $premium_type == 'short_term_3_breakin') {
            $sub_insurance_product_code = 'ST';
            $policy_end_date = date('Y-m-d', strtotime('+3 month -1 day', strtotime($policy_start_date)));
        } elseif ($premium_type == 'short_term_6' || $premium_type == 'short_term_6_breakin') {
            $sub_insurance_product_code = 'ST';
            $policy_end_date = date('Y-m-d', strtotime('+6 month -2 day', strtotime($policy_start_date)));
        } else {
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $vehicle_in_90_days = $date_difference = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);

            if ($date_difference > 90 || $requestData->previous_policy_expiry_date == 'New') {  
                $previousNoClaimBonus = 'ZERO';
            }
        }

        $voluntary_deductible_amount = 'ZERO';
        
        $cng_lpg_amt = $non_electrical_amt = $electrical_amt = null;
        $is_tppd = false;

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                        ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                        ->first();
                                        
        $cpa_selected = 'false';
        if(!empty($additional['compulsory_personal_accident']))
        {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) 
            {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') 
                {
                    $cpa_selected = 'true';
                }
            }
        }

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'TPPD Cover') {
                    $is_tppd = true;
                }
            }
        }

        if(!empty($additional['accessories']))
        {
            foreach ($additional['accessories'] as $key => $data) 
            {
                if($data['name'] == 'External Bi-Fuel Kit CNG/LPG')
                {
                    $cng_lpg_amt = $data['sumInsured'];
                }
    
                if($data['name'] == 'Non-Electrical Accessories')
                {
                    $non_electrical_amt = $data['sumInsured'];
                }
    
                if($data['name'] == 'Electrical Accessories')
                {
                    $electrical_amt = $data['sumInsured'];
                }
            }
        }

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = null;
        $driverLL = false;
        $cleanerLL = false;
        $no_of_cleanerLL = NULL;
        $no_of_driverLL = 1;

        if(!empty($additional['additional_covers']))
        {
            foreach ($additional['additional_covers'] as $key => $data) 
            {
                if($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']))
                {
                    $cover_pa_paid_driver = $data['sumInsured'];
                }
    
                if($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']))
                {
                    $cover_pa_unnamed_passenger = $data['sumInsured'];
                }

                if ($data['name'] == 'LL paid driver') {
                    $driverLL = true;
                }

                if ($data['name'] == 'LL paid driver/conductor/cleaner' && isset($data['LLNumberCleaner']) && $data['LLNumberCleaner'] > 0) {
                    $cleanerLL = true;
                    $no_of_cleanerLL = $data['LLNumberCleaner'];
                }
    
                if ($data['name'] == 'LL paid driver/conductor/cleaner' && isset($data['LLNumberDriver']) && $data['LLNumberDriver'] > 0) {
                    $driverLL = true;
                    $no_of_driverLL = $data['LLNumberDriver'];
                }
    
                if ($data['name'] == 'PA paid driver/conductor/cleaner' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = $data['sumInsured'];
                }
            }
        }

        $is_imt_23 = false;
        $consumablesAvailable = false;

        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'IMT - 23') {
                    $is_imt_23 = true;
                }

                if ($data['name'] == 'Consumable') {
                    $consumablesAvailable = true;
                }
            }
        }

        if (is_null($proposal->engine_number) || empty($proposal->engine_number) || is_null($proposal->chassis_number) || empty($proposal->chassis_number))
        {
            return [
                'status' => false,
                'message' => 'Please enter valid engine and chassis number'
            ];
        }

        $is_pos = 'false';
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_testing_mode = config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_GODIGIT');
        $posp_name = '';
        $posp_unique_number = '';
        $posp_pan_number = '';
        $posp_aadhar_number = '';
        $posp_contact_number = '';
        $posp_location = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('user_proposal_id',$proposal['user_proposal_id'])
            ->where('seller_type','P')
            ->first();

        $integrationId = config("IC.GODIGIT.V2.CV.QUOTE_INTEGRATION_ID");

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') { 
            if($pos_data) {

                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $pos_data->agent_id,
                    'productSubTypeId' => $productData->product_sub_type_id,
                    'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $integrationId = $credentials['data']['integration_id'];
                }

                $is_pos = 'true';
                $posp_name = $pos_data->agent_name;
                $posp_unique_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
                $posp_pan_number = $pos_data->pan_no;
                $posp_aadhar_number = $pos_data->aadhar_no;
                $posp_contact_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
                $posp_location = $pos_data->region_name;
            }
            if($is_pos_testing_mode == 'Y')
            {
                $is_pos = 'true';
                $posp_name = 'test';
                $posp_unique_number = '9768574564';
                $posp_pan_number = 'ABGTY8890Z';
                $posp_aadhar_number = '569278616999';
                $posp_contact_number = '9768574564';
            }
        }
        else if($is_pos_testing_mode == 'Y')
        {
            $is_pos = 'true';
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = 'ABGTY8890Z';
            $posp_aadhar_number = '569278616999';
            $posp_contact_number = '9768574564';
        }
    
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);

        //Quick Quote - Premium              
        $access_token_resp =getToken($proposal->user_product_journey_id, $productData,'proposal',$requestData->business_type);
        $access_token = ($access_token_resp['token']);
        $isPreviousInsurerKnown = true;
        $previousInsurerCode =  isset($proposal->previous_insurance_company) ? $proposal->previous_insurance_company : "NA";
        if (($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New') {
            $isPreviousInsurerKnown = true;
            $previousInsurerCode = "NA";
        }
        $quote_premium_request = 
        [
            "motorQuickQuote" => 
            [
                'enquiryId' => 'GODIGIT_QQ_CV_PACKAGE_01',
                'contract' => [
                    'insuranceProductCode' => $insurance_product_code,
                    'subInsuranceProductCode' => $sub_insurance_product_code,
                    'startDate' => $policy_start_date,
                    'endDate' => $policy_end_date,
                    'policyHolderType' =>  $policy_holder_type,
                    'externalPolicyNumber' => null,
                    'isNCBTransfer' => null,
                    'coverages' => [
                        'voluntaryDeductible' => $voluntary_deductible_amount,
                        'thirdPartyLiability' => [
                            'isTPPD' => $is_tppd,
                        ],
                        'ownDamage' => [
                            'discount' => [
                                'userSpecialDiscountPercent' => 0,
                                'discounts' => [],
                            ],
                            'surcharge' => [
                                'loadings' => [],
                            ],
                        ],
                        'personalAccident' => [
                            'selection' => ($cpa_selected == 'true' && $requestData->vehicle_owner_type == "I") ? true : false,
                            'insuredAmount' => ($cpa_selected == 'true') ? 1500000 : 0,
                            'coverTerm' => null,
                        ],
                        'accessories' => [
                            'cng' => [
                                'selection' => !empty($cng_lpg_amt) ? 'true' : 'false',
                                'insuredAmount' => !empty($cng_lpg_amt) ? $cng_lpg_amt : 0,
                            ],
                            'electrical' => [
                                'selection' => !empty($electrical_amt) ? 'true' : 'false',
                                'insuredAmount' => !empty($electrical_amt) ? $electrical_amt : 0,
                            ],
                            'nonElectrical' => [
                                'selection' => !empty($non_electrical_amt) ? 'true' : 'false',
                                'insuredAmount' => !empty($non_electrical_amt) ? $non_electrical_amt : 0,
                            ],
                        ],
                        'addons' => [
                            'partsDepreciation' => [
                                'claimsCovered' => NULL,
                                'selection' => "false",
                            ],
                            'roadSideAssistance' => [
                                'selection' => "false",
                            ],
                            'personalBelonging' => [
                                'selection' => "false",
                            ],
                            'keyAndLockProtect' => [
                                'selection' => "false",
                            ],
                            'engineProtection' => [
                                'selection' => "false",
                            ],
                            'tyreProtection' => [
                                'selection' => "false",
                            ],
                            'rimProtection' => [
                                'selection' => "false",
                            ],
                            'returnToInvoice' => [
                                'selection' => "false",
                            ],
                            'consumables' => [
                                'selection' => "false",
                            ],
                        ],
                        'legalLiability' => [
                            'paidDriverLL' => [
                                'selection' => $driverLL,
                            'insuredCount' => $no_of_driverLL
                            ],
                            'employeesLL' => [
                                'selection' => 'false',
                                'insuredCount' => null,
                            ],
                            'unnamedPaxLL' => [
                                'selection' => 'false',
                                'insuredCount' => null,
                            ],
                            'cleanersLL' => [
                                'selection' => $cleanerLL ? "true" : "false",
                                'insuredCount' => $no_of_cleanerLL,
                            ],
                            'nonFarePaxLL' => [
                                'selection' => 'false',
                                'insuredCount' => null,
                            ],
                            'workersCompensationLL' => [
                                'selection' => 'false',
                                'insuredCount' => null,
                            ],
                        ],
                        'unnamedPA' => [
                            'unnamedPax' => [
                                'selection' => !empty($cover_pa_unnamed_passenger) ? 'true' : 'false',
                                'insuredAmount' => 0,
                                'insuredCount' => NULL,
                            ],
                            'unnamedPaidDriver' => [
                                'selection' => !empty($cover_pa_paid_driver) ? 'true' : 'false',
                                'insuredAmount' => 0,
                                'insuredCount' => NULL,
                            ],
                            'unnamedHirer' => [
                                'selection' => 'false',
                                'insuredAmount' => null,
                                'insuredCount' => null,
                            ],
                            'unnamedPillionRider' => [
                                'selection' => 'false',
                                'insuredAmount' => null,
                                'insuredCount' => null,
                            ],
                            'unnamedCleaner' => [
                                'selection' => !empty($cover_pa_paid_cleaner) ? 'true' : 'false',
                                'insuredAmount' => !empty($cover_pa_paid_cleaner) ? $cover_pa_paid_cleaner : 0,
                                'insuredCount' => null,
                            ],
                            'unnamedConductor' => [
                                'selection' => !empty($cover_pa_paid_conductor) ? 'true' : 'false',
                                'insuredAmount' => !empty($cover_pa_paid_conductor) ? $cover_pa_paid_conductor : 0,
                                'insuredCount' => null,
                            ],
                        ],
                    ],
                ],
                'vehicle' => [
                    'seatingCapacity' =>  $mmv->seating_capacity,
                    'isVehicleNew' => $is_vehicle_new,
                    'vehicleMaincode' => $mmv->ic_version_code,
                    'licensePlateNumber' => $vehicle_registration_no,
                    'registrationAuthority' => str_replace('-', '', godigitRtoCode($requestData->rto_code)),
                    'vehicleIdentificationNumber' => $proposal->chassis_number,
                    'engineNumber' => $proposal->engine_number,
                    'manufactureDate' =>  date('Y-m-d', strtotime('01-'.$requestData->manufacture_year)),
                    'registrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                    'vehicleIDV' => [
                        'idv' => $quote->idv,
                    ],
                    'usageType' => 'KFZOT',
                'permitType' => 'PUBLIC',
                'motorType' => null,
                'vehicleType' => $mmv->vehicle_type == 'Passenger Carrying' ? 'PASSENGER' : ($mmv->vehicle_type == 'Miscellaneous' ? 'MISC' : 'GOODS')
            ],
                'previousInsurer' => [
                    'isPreviousInsurerKnown' => $isPreviousInsurerKnown,
                'previousInsurerCode' => $previousInsurerCode,
                'previousPolicyNumber' => !empty($proposal->previous_policy_number) ? removeSpecialCharactersFromString($proposal->previous_policy_number) : null,
                'previousPolicyExpiryDate' => (($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || $requestData->ownership_changed == 'Y' || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d', strtotime('-91 days', time())) : (!empty($proposal->prev_policy_expiry_date) ? date('Y-m-d', strtotime($proposal->prev_policy_expiry_date)) : null),
                'isClaimInLastYear' => ($requestData->is_claim == 'Y') ? 'true' : 'false',
                'originalPreviousPolicyType' => ($requestData->prev_short_term ? 'SHORTERM' : ($requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP')),
                'previousPolicyType' => ($requestData->prev_short_term ? '0OD_1TP' : ($requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP')),
                'previousNoClaimBonus' => $previousNoClaimBonus,
                    'currentThirdPartyPolicy' => null,
                ],
                'pincode' => $proposal->pincode,
                'pospInfo' => [
                    'isPOSP' => $is_pos,
                    'pospName' => $posp_name,
                    'pospUniqueNumber' => $posp_unique_number,
                    'pospLocation' => $posp_location,
                    'pospPanNumber' => $posp_pan_number,
                    'pospAadhaarNumber' => $posp_aadhar_number,
                    'pospContactNumber' => $posp_contact_number
                ]
            ]
        ];

        if ($mmv->vehicle_type != 'Passenger Carrying') {
            $quote_premium_request['contract']['coverages']['isIMT23'] = $is_imt_23;
        }

        if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
            $quote_premium_request['motorQuickQuote']['previousInsurer']['isPreviousInsurerKnown'] = false;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousInsurerCode'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousPolicyNumber'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousPolicyExpiryDate'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['isClaimInLastYear'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['originalPreviousPolicyType'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousPolicyType'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousNoClaimBonus'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy'] = null;
        }

        if(config('IC.GODIGIT.V2.CAR.ENVIRONMENT') == 'UAT'){
            $quote_premium_request = $quote_premium_request['motorQuickQuote'];
        }

        $get_response = getWsData(config('IC.GODIGIT.V2.CV.END_POINT_URL'),$quote_premium_request, 'godigit',
        [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'godigit',
            'method'   => 'Premium Calculation',
            'section' => $productData->product_sub_type_code,
            'authorization' => $access_token,
            'integrationId' => $integrationId,
            'transaction_type' => 'proposal',
        ]);
        
        $data = $get_response['response'];
        if (!empty($data)) 
        {
            $response = json_decode($data);
           
            if (isset($response->error->errorCode) && $response->error->errorCode == '0') 
            { 
                if(isset($response->motorBreakIn->isBreakin) && ($response->motorBreakIn->isBreakin == 1))
                {
                    $self_inspection = 'true';
                    $is_breakin_case = 'Y';
                }
                else
                {
                    $is_breakin_case = 'N';
                }
                if(in_array($premium_type, ['third_party', 'third_party_breakin']))
                {
                    $self_inspection = 'false';
                    $is_breakin_case = 'N'; 
                }
               
                $address = DB::table('godigit_pincode_state_city_master as gdstcm')
                                ->where([   'pincode' => $proposal->pincode,
                                            'city' => $proposal->city,
                                            'state' => $proposal->state,
                                        ])
                                ->select('statecode','city', 'country', 'district')
                                ->first();

                $address_data = [
                    'address' => $proposal->address_line1,
                    'address_1_limit'   => 79,
                    'address_2_limit'   => 79
                ];
                $getAddress = getAddress($address_data);
                
                $person = [];
                if ($policy_holder_type == 'COMPANY') 
                {
                    $person = 
                    [   "personType" => "COMPANY",
                        "addresses" => 
                        [
                            [
                                "addressType"   => "HEAD_QUARTER",
                                "streetNumber"  => null,
                                "street"        => $getAddress['address_1'],
                                "district"      => $getAddress['address_2'],
                                "state"         => isset($address->statecode) ? $address->statecode : null,
                                "city"          => $proposal->city,
                                "country"       => "IN",
                                "pincode"       => $proposal->pincode,
                                "geoCode"       => null,
                                "zone"          => null
                            ]
                        ],
                        "communications" => 
                        [
                            [
                                "communicationType" => "MOBILE",
                                "communicationId" => $proposal->mobile_number,
                                "isPrefferedCommunication" => "true"
                            ],
                            [
                                "communicationType" => "EMAIL",
                                "communicationId" => $proposal->email,
                                "isPrefferedCommunication" => "true"
                            ]
                        ],
                        "identificationDocuments" => 
                        [
                            [
                                "documentType"=> "GST",
                                "documentId"=> isset($proposal->gst_number) ? $proposal->gst_number : null,
                                "issuingAuthority"=> null,
                                "issuingPlace"=> null,
                                "issueDate"=> null,
                                "expiryDate"=> null
                            ],
                            [
                                "documentType"=> "PAN_CARD",
                                "documentId"=> isset($proposal->pan_number) ? $proposal->pan_number : null,
                                "issuingAuthority"=> null,
                                "issuingPlace"=> null,
                                "issueDate"=> null,
                                "expiryDate"=> null
                            ]
                        ],
                        "isPolicyHolder" => "true",
                        "isPayer" => null,
                        "isVehicleOwner" => "true",
                        "companyName" => $proposal->first_name,
                    ];
                } 
                else 
                {
                    if($proposal->last_name === null || $proposal->last_name == '')
                    {
                        $proposal->last_name = '.';
                    }

                    $person = 
                    [
                            "personType" => "INDIVIDUAL",
                            "addresses" => [
                                [
                                    "addressType"       => "PRIMARY_RESIDENCE",
                                    "flatNumber"        => null,
                                    "streetNumber"      => null,
                                    "street"            => $getAddress['address_1'],
                                    "district"          => $getAddress['address_2'],
                                    "state"             => isset($address->statecode) ? $address->statecode : null,
                                    "city"              => isset($address->city) ? trim($address->city) : null ,
                                    "country"           => "IN",
                                    "pincode"           => $proposal->pincode,
                                ]
                            ],
                            "communications" => [
                                [
                                    "communicationType" => "MOBILE",
                                    "communicationId" => $proposal->mobile_number,
                                    "isPrefferedCommunication" => true
                                ],
                                [
                                    "communicationType" => "EMAIL",
                                    "communicationId" => $proposal->email,
                                    "isPrefferedCommunication" => true
                                ]
                            ],
                            "identificationDocuments" => [
                                [
                                    "identificationDocumentId" => "",
                                    "documentType" => "GST",
                                    "documentId" => isset($proposal->gst_number) ? $proposal->gst_number : null,
                                    "issuingAuthority" => null,
                                    "issuingPlace" => null,
                                    "issueDate" => null,
                                    "expiryDate" => null
                                ],
                                [
                                    "documentType"=> "PAN_CARD",
                                    "documentId"=> isset($proposal->pan_number) ? $proposal->pan_number : null,
                                    "issuingAuthority"=> null,
                                    "issuingPlace"=> null,
                                    "issueDate"=> null,
                                    "expiryDate"=> null
                                ]
                            ],
                            "isPolicyHolder" => true,
                            "isVehicleOwner" => true,
                            "firstName" => $proposal->first_name,
                            "middleName" => null,
                            "lastName" => $proposal->last_name,
                            "dateOfBirth" =>date('Y-m-d', strtotime($proposal->dob)),
                            "gender" => ($proposal->gender == 'MALE') ? 'MALE' : 'FEMALE',
                            "isDriver" => true,
                            "isInsuredPerson" => true
                        ];
                }
                $proposal_data = 
                [
                    "motorCreateQuote"=> [
                        "enquiryId" => "GODIGIT_CQ_CV_PACKAGE_01",
                        "contract" => [
                            "insuranceProductCode" => $insurance_product_code,
                            "subInsuranceProductCode" => $sub_insurance_product_code,
                            "startDate" => $policy_start_date,
                            "endDate" => $policy_end_date,
                            "policyHolderType" => $policy_holder_type,
                            "externalPolicyNumber" => null,
                            "isNCBTransfer" => null,
                            "coverages" => [
                                "voluntaryDeductible" => $voluntary_deductible_amount,
                                "thirdPartyLiability" => [
                                    "isTPPD" => $is_tppd
                                ],
                                "ownDamage" => [
                                    "discount" => [
                                        "userSpecialDiscountPercent" => 0,
                                        "discounts" => []
                                    ],
                                    "surcharge" => [
                                        "loadings" => []
                                    ]
                                ],
                                "personalAccident" => [
                                    "selection" => ($cpa_selected == 'true' && $requestData->vehicle_owner_type == "I") ? true : false,
                                    "insuredAmount" => ($cpa_selected == 'true') ? 1500000 : 0,
                                    "coverTerm" => null,
                                ],
                                "accessories" => [
                                    "cng" => [
                                        "selection" => !empty($cng_lpg_amt) ? 'true' : 'false',
                                        "insuredAmount" => !empty($cng_lpg_amt) ? $cng_lpg_amt : 0,
                                    ],
                                    "electrical" => [
                                        "selection" => !empty($electrical_amt) ? 'true' : 'false',
                                        "insuredAmount" => !empty($electrical_amt) ? $electrical_amt : 0,
                                    ],
                                    "nonElectrical" => [
                                        "selection" => !empty($non_electrical_amt) ? 'true' : 'false',
                                        "insuredAmount" => !empty($non_electrical_amt) ? $non_electrical_amt : 0,
                                    ],
                                ],
                                "addons" => [
                                    "partsDepreciation" => [
                                        "claimsCovered" => null,
                                        "selection" => "false"
                                    ],
                                    "roadSideAssistance" => [
                                        "selection" => "false"
                                    ],
                                    'personalBelonging' => [
                                        'selection' => "false"
                                    ],
                                    'keyAndLockProtect' => [
                                        'selection' => "false"
                                    ],
                                    "engineProtection" => [
                                        "selection" => "false"
                                    ],
                                    "tyreProtection" => [
                                        "selection" => "false"
                                    ],
                                    "rimProtection" => [
                                        "selection" => null
                                    ],
                                    "returnToInvoice" => [
                                        "selection" => "false"
                                    ],
                                    "consumables" => [
                                        "selection" => "false"
                                    ]
                                ],
                                "legalLiability" => [
                                    "paidDriverLL" => [
                                        "selection" => $driverLL,
                                        "insuredCount" => $no_of_driverLL,
                                    ],
                                    "employeesLL" => [
                                        "selection" => null,
                                        "insuredCount" => null
                                    ],
                                    "unnamedPaxLL" => [
                                        "selection" => null,
                                        "insuredCount" => null
                                    ],
                                    "cleanersLL" => [
                                        "selection" => $cleanerLL ? "true" : "false",
                                    "insuredCount" => $no_of_cleanerLL
                                    ],
                                    "nonFarePaxLL" => [
                                        "selection" => null,
                                        "insuredCount" => null
                                    ],
                                    "workersCompensationLL" => [
                                        "selection" => null,
                                        "insuredCount" => null
                                    ]
                                ],
                                "unnamedPA" => [
                                    "unnamedPax" => [
                                        "selection" => !empty($cover_pa_unnamed_passenger) ? 'true' : 'false',
                                        "insuredAmount" => 0,
                                        "insuredCount" => NULL,
                                    ],
                                    "unnamedPaidDriver" => [
                                        "selection" => !empty($cover_pa_paid_driver) ? 'true' : 'false',
                                        "insuredAmount" => 0,
                                        "insuredCount" => NULL,
                                    ],
                                    "unnamedHirer" => [
                                        "selection" => null,
                                        "insuredAmount" => null,
                                        "insuredCount" => null
                                    ],
                                    "unnamedPillionRider" => [
                                        "selection" => null,
                                        "insuredAmount" => null,
                                        "insuredCount" => null
                                    ],
                                    "unnamedCleaner" => [
                                        "selection" => !empty($cover_pa_paid_cleaner) ? 'true' : 'false',
                                        "insuredAmount" => !empty($cover_pa_paid_cleaner) ? $cover_pa_paid_cleaner : 0,
                                        "insuredCount" => null
                                    ],
                                    "unnamedConductor" => [
                                        "selection" => !empty($cover_pa_paid_conductor) ? 'true' : 'false',
                                        "insuredAmount" => !empty($cover_pa_paid_conductor) ? $cover_pa_paid_conductor : 0,
                                        "insuredCount" => null
                                    ]
                                ]
                            ]
                        ],
                        "vehicle" => [
                            'isVehicleNew' => $is_vehicle_new,
                            'seatingCapacity' =>  $mmv->seating_capacity,
                            'vehicleMaincode' => $mmv->ic_version_code,
                            'licensePlateNumber' => $vehicle_registration_no,
                            'vehicleIdentificationNumber' => $proposal->chassis_number,
                            'registrationAuthority' => str_replace('-', '', godigitRtoCode($requestData->rto_code)),
                            'engineNumber' => $proposal->engine_number,
                            'manufactureDate' =>  date('Y-m-d', strtotime('01-'.$requestData->manufacture_year)),
                            'registrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            "vehicleIDV" => [
                                'idv' => $quote->idv,
                            ],
                            'usageType' => 'KFZOT',
                            "permitType" => 'PUBLIC',
                            "motorType" => "",
                            'vehicleType' => $mmv->vehicle_type == 'Passenger Carrying' ? 'PASSENGER' : ($mmv->vehicle_type == 'Miscellaneous' ? 'MISC' : 'GOODS')
                        ],
                        'hypothecation' => [
                            'isHypothecation' => $proposal->is_vehicle_finance ? true : false,
                            'hypothecationAgency' => $proposal->is_vehicle_finance ? $proposal->name_of_financer : '',
                            'hypothecationCIty' => $proposal->is_vehicle_finance ? $proposal->hypothecation_city : '',
                        ],
                        "previousInsurer" => [
                            "isPreviousInsurerKnown" => $isPreviousInsurerKnown,
                            "previousInsurerCode" => $previousInsurerCode,
                            "previousPolicyNumber" => !empty($proposal->previous_policy_number) ? $proposal->previous_policy_number : null,
                            "previousPolicyExpiryDate" => (($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || $requestData->ownership_changed == 'Y' || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d', strtotime('-91 days', time())) : (!empty($proposal->prev_policy_expiry_date) ? date('Y-m-d', strtotime($proposal->prev_policy_expiry_date)) : null),
                            "isClaimInLastYear" => ($requestData->is_claim == 'Y') ? 'true' : 'false',
                            'originalPreviousPolicyType' => ($requestData->prev_short_term ? 'SHORTERM' : ($requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP')),
                            'previousPolicyType' => ($requestData->prev_short_term ? '0OD_1TP' : ($requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP')),
                            "previousNoClaimBonus" =>  $previousNoClaimBonus,
                            "currentThirdPartyPolicy" => null
                        ],
                        'pospInfo' => [
                            'isPOSP' => $is_pos,
                            'pospName' => $posp_name,
                            'pospUniqueNumber' => $posp_unique_number,
                            'pospLocation' => $posp_location,
                            'pospPanNumber' => $posp_pan_number,
                            'pospAadhaarNumber' => $posp_aadhar_number,
                            'pospContactNumber' => $posp_contact_number
                        ],
                        'persons' => [
                            $person
                        ],
                        "dealer" => [
                            "dealerName" => "",
                            "city" => "",
                            "deliveryDate" => null
                        ],
                        "motorQuestions" => [
                            "furtherAgreement" => "",
                            "selfInspection" => isset($self_inspection) ? $self_inspection : false,
                            "financer" => $proposal->is_vehicle_finance ? $proposal->name_of_financer : ''
                        ],
                        "motorBreakIn" => [
                                "isBreakin" => isset($self_inspection) ? $self_inspection : false,
                                "breakinExcess" => null,
                                "breakinComments" => null,
                                "isPreInspectionWaived" => false,
                                "isPreInspectionCompleted" => null,
                                "isDocumentUploaded" => null
                            ],
                    ]
                        ];
                if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                {
                    if(config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y')
                    {
                        if($proposal->is_ckyc_verified && !empty($proposal->is_ckyc_verified))
                        {
        
        
                            $isKYCDone = ($proposal->is_ckyc_verified == 'N') ? false : true;
                            $kycDoclist =  ['ckyc_number', 'pan_card', 'passport','driving_license','voter_id'];
                            $icKycDoclist = [
                                                "ckyc_number" => "D02",
                                                "driving_license" => "D04",
                                                "voter_id" => "D05",
                                                "passport" => "D06",
                                                "pan_card" => "D07",
                                                "aadhar_card" => "D03"
                                            ];
                            if(empty($proposal->ckyc_type) || empty($proposal->ckyc_type_value))
                            {
                                return [
                                    'status' => false,
                                    'premium' => '0',
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => 'Please Re-Submit KYC details to complete the proposal.'
                                ]; 
                                
                            }
                            $ckycReferenceDocId = $icKycDoclist[$proposal->ckyc_type];
                            $ckycReferenceNumber = $proposal->ckyc_type_value;
                            if($proposal->ckyc_type == 'aadhar_card') {
                                $ckycReferenceNumber = substr( $ckycReferenceNumber, -4);
                            }
                            $dateOfBirth = date('Y-m-d', strtotime($proposal->dob));
                            $photo = '';
                            $photos_list = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId']);
                            if(\Illuminate\Support\Facades\Storage::exists('ckyc_photos/'.$request['userProductJourneyId'])) 
                            {
                                if(!isset($photos_list[0]) && empty($photos_list[0]))
                                {
                                    return [
                                        'errorType' =>'INFO',
                                        'status' => false,
                                        'premium' => '0',
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => 'Please upload photograph to complete proposal.'
                                    ]; 
                                }else
                                {
                                    $photo = ProposalController::getCkycDocument($photos_list[0]);
                                    $photo = base64_encode($photo);
                                }
        
                            } 
                            //Commenting as photo is not necessary #34453
                            // else if ($requestData->vehicle_owner_type == 'I')
                            // {
                            //     return [
                            //         'errorType' => 'INFO',
                            //         'status' => false,
                            //         'premium' => '0',
                            //         'webservice_id' => $get_response['webservice_id'],
                            //         'table' => $get_response['table'],
                            //         'message' => 'Please upload photograph to complete proposal.'
                            //     ]; 
        
                            // }
        
                            $kyc = [
                                    "isKYCDone" => $isKYCDone,
                                    "ckycReferenceDocId" => $ckycReferenceDocId,
                                    "ckycReferenceNumber" => $ckycReferenceNumber,
                                    "dateOfBirth" => $dateOfBirth,
                                    "photo" => $photo
                            ];
       
                            if($proposal->ckyc_type == 'aadhar_card') {
                                if(in_array(strtoupper($proposal->gender), ['M', 'MALE'])) {
                                    $gender = 'M';
                                }  else {
                                    $gender = 'W';
                                }
                                $kyc['gender'] = $gender;
                            }
                            $proposal_data['motorCreateQuote']['kyc'] = $kyc;
        
                        }else
                        {
                            return 
                            [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => "CKYC verification needed"
                            ];
                        }
                    }
                }

                if ($mmv->vehicle_type != 'Passenger Carrying') {
                    $proposal_data['motorCreateQuote']['contract']['coverages']['isIMT23'] = $is_imt_23;
                }

                $splitName = explode(' ', $proposal->nominee_name, 2);
                $nominee_fname = $splitName[0];
                $nominee_lname = !empty($splitName[1]) ? $splitName[1] : '';

                if($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true')
                {
                        $proposal_data['motorCreateQuote']['nominee']['firstName'] = $nominee_fname;
                        $proposal_data['motorCreateQuote']['nominee']['middleName']= '';
                        $proposal_data['motorCreateQuote']['nominee']['lastName'] = $nominee_lname;
                        $proposal_data['motorCreateQuote']['nominee']['dateOfBirth'] = date('Y-m-d', strtotime($proposal->nominee_dob));
                        $proposal_data['motorCreateQuote']['nominee']['relation'] = strtoupper($proposal->nominee_relationship);
                        $proposal_data['motorCreateQuote']['nominee']['personType'] = 'INDIVIDUAL';
                }  
                if ($premium_type == "own_damage") {
                    $proposal_data['motorCreateQuote']['previousInsurer']['originalPreviousPolicyType'] = "1OD_3TP";
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = $proposal->tp_insurance_company;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = $proposal->tp_insurance_number;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                }

                if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                    $proposal_data['motorCreateQuote']['previousInsurer']['isPreviousInsurerKnown'] = 'false';
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousInsurerCode'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousPolicyNumber'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousPolicyExpiryDate'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['isClaimInLastYear'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['originalPreviousPolicyType'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousPolicyType'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousNoClaimBonus'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy'] = null;
                }

                if(config('IC.GODIGIT.V2.CAR.ENVIRONMENT') == 'UAT'){
                    $proposal_data = $proposal_data['motorCreateQuote'];
                }

                $get_response = getWsData(config('IC.GODIGIT.V2.CV.END_POINT_URL'),$proposal_data, 'godigit',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_sub_type_name,
                    'company'  => 'godigit',
                    'method'   => 'Proposal Submit',
                    'section' => $productData->product_sub_type_code,
                    'authorization' => $access_token,
                    'integrationId' => config('IC.GODIGIT.V2.CV.PROPOSAL_INTEGRATION_ID'),
                    'transaction_type' => 'proposal',
                ]);

                $data = $get_response['response'];
                if(!empty($data))
                {

                    $prem_web_id = $get_response['webservice_id'];
                    set_time_limit(400);
                    $proposal_response = json_decode($data);
                    if (isset($proposal_response->error->errorCode) && $proposal_response->error->errorCode == '0') 
                    {        
                        $vehicle_idv = $proposal_response->vehicle->vehicleIDV->idv;
                        $contract = $proposal_response->contract;
                        $llpaiddriver_premium = 0;
                        $llcleaner_premium = 0;
                        $cover_pa_owner_driver_premium = 0;
                        $cover_pa_paid_driver_premium = 0;
                        $cover_pa_unnamed_passenger_premium = 0;
                        $cover_pa_paid_cleaner_premium = 0;
                        $cover_pa_paid_conductor_premium = 0;
                        $voluntary_excess = 0;
                        $ic_vehicle_discount = 0;
                        $ncb_discount_amt = 0;
                        $cng_lpg_selected = 'N';
                        $electrical_selected = 'N';
                        $non_electrical_selected = 'N';
                        $ncb_discount_amt = 0;
                        $tppd_discount_amt = $is_tppd ? (get_parent_code($productData->product_sub_type_id) == 'PCV' ? 150 : 200) : 0;
                        $od = 0;
                        $cng_lpg_tp = 0;
                        $addon_premium = 0;
            
                        $zero_depreciation = 0;
                        $consumables = 0;

                        foreach ($contract->coverages as $key => $value) 
                        {
                            switch ($key) 
                            {
                                case 'thirdPartyLiability':
                                    if (isset($value->netPremium)) 
                                    {
                                        $tppd = round(str_replace("INR ", "", $value->netPremium));
                                    }
                                    
                                break;
                    
                                case 'addons':
                                    foreach ($value as $key => $addon) {
                                        switch ($key) {
                                            case 'partsDepreciation':
                                                if ($addon->selection == 'true' && isset($addon->coverAvailability) && $addon->coverAvailability == 'AVAILABLE') {
                                                    $zero_depreciation = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'consumables':
                                                if ($addon->selection == 'true' && isset($addon->coverAvailability) && $addon->coverAvailability == 'AVAILABLE') {
                                                    $consumables = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            }
                                        }
                                break;
            
                                case 'ownDamage':
                                    
                                    if(isset($value->netPremium))
                                    {
                                         $od = round(str_replace("INR ", "", $value->netPremium));
                                         foreach ($value->discount->discounts as $key => $type) 
                                         {
                                             if ($type->discountType == "NCB_DISCOUNT") 
                                             {
                                                 $ncb_discount_amt = round(str_replace("INR ", "", $type->discountAmount));
                                             }
                                         }
                                    } 
                                break;
            
                                case 'legalLiability' :
                                    foreach ($value as $cover => $subcover) 
                                    {
                                        if ($cover == "paidDriverLL") 
                                        {
                                            if($subcover->selection == 1)
                                            {
                                                $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                            }
                                        }

                                        if ($cover == "cleanersLL") {
                                            if ($subcover->selection == 1) {
                                                $llcleaner_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                            }
                                        }
                                    }
                                break;
                            
                                case 'personalAccident':
                                    // By default Complusory PA Cover for Owner Driver
                                    if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                                    {
                                        $cover_pa_owner_driver_premium = round(str_replace("INR ", "", $value->netPremium));
                                    } 
                                break;
            
                                case 'accessories' :
                                break;
            
                                case 'unnamedPA':
                                    
                                    foreach ($value as $cover => $subcover) 
                                    {
                                        if ($cover == 'unnamedPaidDriver') 
                                        {
                                            if (isset($subcover->selection) && $subcover->selection == 1) 
                                            {
                                                if (isset($subcover->netPremium)) 
                                                {
                                                    $cover_pa_paid_driver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                                }
                                            }
                                        }
            
                                        if ($cover == 'unnamedPax') 
                                        {
                                            if (isset($subcover->selection) && $subcover->selection == 1) 
                                            {
                                                if (isset($subcover->netPremium)) 
                                                {
                                                    $cover_pa_unnamed_passenger_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                                }
                                            }
                                        }

                                        if ($cover == 'unnamedCleaner') {
                                            if (isset($subcover->selection) && $subcover->selection == 1) {
                                                if (isset($subcover->netPremium)) {
                                                    $cover_pa_paid_cleaner_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                                }
                                            }
                                        }
            
                                        if ($cover == 'unnamedConductor') {
                                            if (isset($subcover->selection) && $subcover->selection == 1) {
                                                if (isset($subcover->netPremium)) {
                                                    $cover_pa_paid_conductor_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                                }
                                            }
                                        }
                                    }
                                break;
                            }
                        }
                   
                        if(isset($cng_lpg_amt) && !empty($cng_lpg_amt))
                        {
                            $cng_lpg_tp = 60;
                            $tppd = $tppd - 60;
                        }

                        $imt23 = 0;
                        $addon_premium = $imt23 + round($zero_depreciation) + round($consumables);
                        $ncb_discount = $ncb_discount_amt;
                        $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount;
                        $final_od_premium = $od - $imt23 - $final_total_discount + $addon_premium;

                        $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium + $cover_pa_owner_driver_premium;
                        $final_net_premium  = round(str_replace("INR ", "", $proposal_response->netPremium));
                        $final_gst_amount = round(str_replace("INR ", "", $proposal_response->serviceTax->totalTax));
                        $final_payable_amount = round(str_replace("INR ", "", $proposal_response->grossPremium)); 
                        $vehicleDetails = [
                            'manufacture_name' => $mmv->make,
                            'model_name' => $mmv->model,
                            'version' => $mmv->variant,
                            'fuel_type' => $mmv->fuel_type,
                            'seating_capacity' => $mmv->seating_capacity,
                            'carrying_capacity' => $mmv->seating_capacity - 1,
                            'cubic_capacity' => $mmv->cubic_capacity,
                            'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
                            'vehicle_type' => ($mmv->vehicle_type == 'Passenger Carrying') ? 'PCV' : 'GCV'
                        ];

                        // $save = UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                        //                     ->update([
                        //                 'od_premium' => $final_od_premium,
                        //                 'tp_premium' => $final_tp_premium,
                        //                 'ncb_discount' => $ncb_discount,
                        //                 'addon_premium' => $addon_premium,
                        //                 'total_premium' => $final_net_premium,
                        //                 'service_tax_amount' => $final_gst_amount,
                        //                 'final_payable_amount' => $final_payable_amount,
                        //                 'cpa_premium' => $cover_pa_owner_driver_premium,
                        //                 'total_discount' => $final_total_discount,
                        //                 'proposal_no' => $proposal_response->policyNumber,
                        //                 'unique_proposal_id' => $proposal_response->applicationId,
                        //                 'is_policy_issued' => $proposal_response->policyStatus,
                        //                 'policy_start_date' => date('d-m-Y', strtotime($proposal_response->contract->startDate)),
                        //                 'policy_end_date' => date('d-m-Y', strtotime($proposal_response->contract->endDate)),
                        //                 'tp_start_date' => !empty($proposal->tp_start_date) ? date('d-m-Y', strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime($policy_start_date)),
                        //                 'tp_end_date' => !empty($proposal->tp_end_date) ? date('d-m-Y', strtotime($proposal->tp_end_date)) : (($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y', strtotime($policy_end_date))),
                        //                 'ic_vehicle_details' => $vehicleDetails,
                        //                 'is_breakin_case' => $is_breakin_case,
                        //         ]);
                        $update_proposal=[
                            'od_premium' => $final_od_premium,
                            'tp_premium' => $final_tp_premium,
                            'ncb_discount' => $ncb_discount,
                            'addon_premium' => $addon_premium,
                            'total_premium' => $final_net_premium,
                            'service_tax_amount' => $final_gst_amount,
                            'final_payable_amount' => $final_payable_amount,
                            'cpa_premium' => $cover_pa_owner_driver_premium,
                            'total_discount' => $final_total_discount,
                            'proposal_no' => $proposal_response->policyNumber,
                            'unique_proposal_id' => $proposal_response->applicationId,
                            'is_policy_issued' => $proposal_response->policyStatus,
                            'policy_start_date' => date('d-m-Y', strtotime($proposal_response->contract->startDate)),
                            'policy_end_date' => date('d-m-Y', strtotime($proposal_response->contract->endDate)),
                            'tp_start_date' => !empty($proposal->tp_start_date) ? date('d-m-Y', strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime($policy_start_date)),
                            'tp_end_date' => !empty($proposal->tp_end_date) ? date('d-m-Y', strtotime($proposal->tp_end_date)) : (($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))) : date('d-m-Y', strtotime($policy_end_date))),
                            'ic_vehicle_details' => $vehicleDetails,
                            'is_breakin_case' => $is_breakin_case,
                        ];
                        if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                            unset($update_proposal['tp_start_date']);
                            unset($update_proposal['tp_end_date']);
                        }
                        $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($update_proposal);

                        GodigitPremiumDetailController::saveOneApiPremiumDetails($prem_web_id);
                        if($is_breakin_case == 'Y')
                        {
                            if (config('constants.motor.IS_WIMWISURE_GODIGIT_ENABLED') == 'Y')
                            {
                                $wimwisure = new WimwisureBreakinController();

                                if($requestData->vehicle_owner_type != 'C' && ($proposal->last_name === null || $proposal->last_name == ''))
                                {
                                    $proposal->last_name = '.';
                                }

                                $payload = [
                                    'user_name' => $requestData->vehicle_owner_type == 'C' ? $proposal->first_name : $proposal->first_name . ' ' . $proposal->last_name,
                                    'user_email' => $proposal->email,
                                    'reg_number' => $proposal->vehicale_registration_number,
                                    'mobile_number' => $proposal->mobile_number,
                                    'fuel_type' => strtolower($mmv->fuel_type),
                                    'enquiry_id' => $proposal->user_product_journey_id,
                                    'inspection_number' => $proposal_response->policyNumber,
                                    'section' => 'cv',
                                    'chassis_number' => $proposal->chassis_number,
                                    'engine_number' => $proposal->engine_number,
                                    'api_key' => config('constants.wimwisure.API_KEY_GODIGIT')
                                ];
            
                                $breakin_data = $wimwisure->WimwisureBreakinIdGen($payload);
                
                                if ($breakin_data)
                                {
                                    if ($breakin_data->original['status'] == true)
                                    {
                                        CvBreakinStatus::updateorCreate([
                                            'user_proposal_id' => $proposal->user_proposal_id,
                                            'ic_id'                     => $productData->company_id
                                        ], [
                                            'breakin_number'            => $proposal_response->policyNumber,
                                            'wimwisure_case_number'     => $breakin_data->original['data']['ID'],
                                            'breakin_status'            => STAGE_NAMES['PENDING_FROM_IC'],
                                            'breakin_status_final'      => STAGE_NAMES['PENDING_FROM_IC'],
                                            'breakin_check_url'         => config('constants.motorConstant.BREAKIN_CHECK_URL')
                                        ]);

                                        updateJourneyStage([
                                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                            'ic_id' => $productData->company_id,
                                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                            'proposal_id' => $proposal->user_proposal_id,
                                        ]);
                                    }
                                    else
                                    {
                                        return [
                                            'status' => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => $breakin_data->original['data']['message']
                                        ];
                                    }
                                }
                                else
                                {
                                    return [
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => 'Error in breakin service'
                                    ];
                                }
                            }
                            else
                            {
                                CvBreakinStatus::updateOrCreate(
                                    ['user_proposal_id' => $proposal->user_proposal_id], 
                                    [ 
                                        'ic_id'                => $productData->company_id,
                                        'breakin_number'       => $proposal_response->policyNumber,
                                        'breakin_status'       => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_check_url'    => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                        'created_at'           => date('Y-m-d H:i:s'), 
                                        'updated_at'           => date('Y-m-d H:i:s')
                                    ]
                                );

                                updateJourneyStage([
                                    'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                    'proposal_id' => $proposal->user_proposal_id,
                                ]);
                            }
                        }
                        else
                        {
                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                        }

                        if(config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y')
                        {
                            if(config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y')
                            {
                                sleep(10);
                                if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                                {
                                    $KycStatusApiResponse = GetKycStatusGoDIgitOneapi( $proposal->user_product_journey_id,$proposal_response->policyNumber,  $productData->product_sub_type_name,$proposal->user_proposal_id,customEncrypt( $proposal->user_product_journey_id),$productData);
                                    if($KycStatusApiResponse['status'] !== true)
                                    {
                                        CkycGodigitFailedCasesData::updateOrCreate(
                                            ['user_product_journey_id' => $proposal->user_product_journey_id],
                                            [
                                                'policy_no' => $proposal_response->policyNumber,
                                                'kyc_url' => $KycStatusApiResponse['message'] ?? '',
                                                'return_url' => '',
                                                'status' => 'failed',
                                                'post_data' => ''
                                            ]
                                        );
                                        UserProposal::where('user_product_journey_id',$proposal->user_product_journey_id)
                                        ->where('user_proposal_id' ,$proposal->user_proposal_id)
                                        ->update(['is_ckyc_verified' => 'N']);
                                        
                                        $message = '';
                                        $KycError = [
                                                        'S'	=> 'Success',
                                                        'F'	=> 'Fail',
                                                        'P'	=> 'Name Mismatch',
                                                        'A'	=> 'Address Mismatch',
                                                        'B'	=> 'Name & Address Mismatch'
                                                    ];
                                        if(isset($KycStatusApiResponse['response']) && !empty($KycStatusApiResponse['response']->mismatchType))
                                        {
                                            $message = in_array($KycStatusApiResponse['response']->mismatchType,['P','A','B']) ? "Your kyc verification failed due to ".$KycError[$KycStatusApiResponse['response']->mismatchType]." , after successful kyc completion please fill proposal data as per KYC documents" : $KycError[$KycStatusApiResponse['response']->mismatchType];
                                        }else if(isset($KycStatusApiResponse['response']) && empty($KycStatusApiResponse['response']->mismatchType) && filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL))
                                        {
                                            $message = 'Please fill correct proposal data as per documents provided for KYC verification';
                                        }

                                        if((filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL)))
                                        {
                                            return response()->json([
                                                'status' => true,
                                                'msg' => "Proposal Submitted Successfully!",
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'data' => [                            
                                                    'proposalId' => $proposal->user_proposal_id,
                                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                                    'proposalNo' => $proposal_response->applicationId,
                                                    'finalPayableAmount' => $final_payable_amount, 
                                                    'is_breakin' => $is_breakin_case,
                                                    'inspection_number' => $proposal_response->policyNumber,
                                                    'kyc_url' => $KycStatusApiResponse['message'],
                                                    'is_kyc_url_present' => true,
                                                    'kyc_message' => $message,
                                                    'kyc_status' => false,
                                                ]
                                            ]);
                                        }else
                                        {
                                            return response()->json([
                                                'status' => true,
                                                'msg' => "Proposal Submitted Successfully!",
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'data' => [                            
                                                    'proposalId' => $proposal->user_proposal_id,
                                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                                    'proposalNo' => $proposal_response->applicationId,
                                                    'finalPayableAmount' => $final_payable_amount, 
                                                    'is_breakin' => $is_breakin_case,
                                                    'inspection_number' => $proposal_response->policyNumber,
                                                    'kyc_url' => '',
                                                    'is_kyc_url_present' => false,
                                                    'kyc_message' => empty($message) ? 'CKYC verification failed. Redirection link found' : $message,
                                                    'kyc_status' => false,
                                                ]
                                            ]);
                                        }
                        
                                    }else
                                    {       
                    
                                        CkycGodigitFailedCasesData::updateOrCreate(
                                            ['user_product_journey_id' => $proposal->user_product_journey_id],
                                            [
                                                'policy_no' => $proposal_response->policyNumber,
                                                'status' => 'success',
                                                'return_url' => '',
                                                'kyc_url' => '',
                                                'post_data' => ''

                                            ]
                                        );
                    
                                        UserProposal::where('user_product_journey_id',$proposal->user_product_journey_id)
                                        ->where('user_proposal_id' ,$proposal->user_proposal_id)
                                        ->update(['is_ckyc_verified' => 'Y']);
                                        event(new \App\Events\CKYCInitiated($proposal->user_product_journey_id));

                                        $selected_addons = SelectedAddons::where('user_product_journey_id', $proposal->user_product_journey_id)
                                        ->select('addons','compulsory_personal_accident', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                        ->first();

                                        $sortedDetails = sortKeysAlphabetically(json_decode($proposal->additional_details));

                                        $hashData = collect($sortedDetails)->merge($proposal->ic_name)->merge($selected_addons->addons)->merge($selected_addons->compulsory_personal_accident)->merge($selected_addons->accessories)->merge($selected_addons->additional_covers)->merge($selected_addons->voluntary_insurer_discounts)->merge($selected_addons->discounts)->all();

                                        $kycHash = hash('sha256', json_encode($hashData));

                                        ProposalHash::create(
                                            [
                                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                                'user_proposal_id' => $proposal->user_proposal_id,
                                                'additional_details_data' => is_array($proposal->additional_details) ?  json_encode($proposal->additional_details) : $proposal->additional_details,
                                                'hash' => $kycHash ?? null,
                                            ]
                                        );
                        
                                    }
                                }

                            }
                        }
                                

                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [                            
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $proposal_response->applicationId,
                                'finalPayableAmount' => $final_payable_amount, 
                                'is_breakin' => $is_breakin_case,
                                'inspection_number' => $proposal_response->policyNumber,
                                'kyc_status' => (config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y') ? true : false,
                            ]
                        ]);

                    } 
                    elseif(!empty($proposal_response->error->validationMessages[0]))
                    {
                        return 
                        [
                            'premium_amount' => 0,
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => str_replace(",","",$proposal_response->error->validationMessages[0])
                        ];
                    } 
                    elseif(isset($proposal_response->error->errorCode) && $proposal_response->error->errorCode == '400')
                    {
                        return 
                        [
                            'premium_amount' => 0,
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => str_replace(",","",$proposal_response->error->validationMessages[0])
                        ];
                    } else {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $response->message ?? 'Something went wrong'
                        ];
                    }
                }
                else 
                {
                    return
                    [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable'
                    ];
                }
            }
            elseif(!empty($response->error->validationMessages[0]))
            {
                return 
                [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => str_replace(",","",$response->error->validationMessages[0])
                ];
            }
            elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
            {
                return 
                [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => str_replace(",","",$response->error->validationMessages[0])
                ];
            } else {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $response->message ?? 'Something went wrong'
                ];
            }
        }
        else 
        {
            return
            [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ];
        }
    }
}