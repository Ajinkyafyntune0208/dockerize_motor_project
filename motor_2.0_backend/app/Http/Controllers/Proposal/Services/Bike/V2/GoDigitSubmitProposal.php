<?php

namespace App\Http\Controllers\Proposal\Services\Bike\V2;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';
include_once app_path('Helpers/IcHelpers/GoDigitHelper.php');

use App\Http\Controllers\Proposal\ProposalController;
use DateTime;
use Carbon\Carbon;
use GoDigitHelper;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use App\Models\ProposalHash;
use Illuminate\Support\Facades\DB;
use App\Models\CkycGodigitFailedCasesData;
use App\Http\Controllers\SyncPremiumDetail\Bike\GodigitPremiumDetailController;

class GoDigitSubmitProposal
{

    public static function oneApiSubmit($proposal, $request)
    {

        if (strlen($proposal->engine_number) > 20) {
            return  [
                'premium_amount' => '0',
                'status' => false,
                'message' => 'Engine Number Not Greater than 20 Characters'
            ];
        }
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);
        $additional_data = $proposal->additonal_data;

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
                if ($proposal->is_breakin_case == 'Y') {
                    $responseArray["data"] = array_merge($responseArray['data'], ['is_breakin' => "Y"]);
                }

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
        $policy_holder_type = ($proposal->owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $mmv = get_mmv_details($productData, $requestData->version_id, 'godigit');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
        }
        if ($premium_type == 'own_damage_breakin') {
            $premium_type = 'own_damage';
        }
        if ($premium_type == 'third_party') {
            $insurance_product_code = '20202';
            $previousNoClaimBonus = 'ZERO';
        } elseif ($premium_type == 'own_damage') {
            $insurance_product_code = '20203';
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
            $previousNoClaimBonus = $no_claim_bonus[$ncb_percent];
        } else {
            $insurance_product_code = '20201';
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
            $previousNoClaimBonus = $no_claim_bonus[$ncb_percent];
        }
        $voluntary_deductible = [
            '0' => 'ZERO',
            '1000' => 'THOUSAND',
            '2000' => 'TWO_THOUSAND',
            '2500' => 'TWENTYFIVE_HUNDRED',
            '3000' => 'THREE_THOUSAND'
        ];
        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
        // bike age calculation
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $bike_age = ceil($age / 12);
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' || $premium_type == "own_damage") {
            $is_vehicle_new = 'false';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $sub_insurance_product_code = 'PB';
            $vehicle_registration_no  = explode("-", $proposal->vehicale_registration_number);

            if ($vehicle_registration_no[0] == 'DL') {
                $registration_no = RtoCodeWithOrWithoutZero($vehicle_registration_no[0] . $vehicle_registration_no[1], true);
                $vehicle_registration_no = str_replace("-", "", $registration_no) . $vehicle_registration_no[2] . $vehicle_registration_no[3];
            } else {
                $vehicle_registration_no = str_replace("-", "", $proposal->vehicale_registration_number);
            }
            if ($requestData->business_type == 'breakin') {
                $policy_start_date = date('Y-m-d', strtotime('+2 day', time())); //godigit new IRDA policy start date logic
                if ($premium_type == 'third_party') {
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
                }
            }
        } else if ($requestData->business_type == 'newbusiness') {
            $is_vehicle_new = 'true';
            $policy_start_date = Carbon::today()->format('Y-m-d');
            $sub_insurance_product_code = '51';
            $previousNoClaimBonus = 'ZERO';
            if ($requestData->vehicle_registration_no == 'NEW') {
                $vehicle_registration_no  = str_replace("-", "", godigitRtoCode($requestData->rto_code));
            } else {
                $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            }
        }
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $expdate = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $vehicle_in_90_days = $date_difference = get_date_diff('day', $expdate);

            if ($date_difference > 90) {
                $previousNoClaimBonus = 'ZERO';
            }
        }
        $voluntary_deductible_amount = 'ZERO';
        $isPreviousInsurerKnown = "false";
        $previousInsurerCode = "NA";
        if (!empty($proposal->previous_insurance_company) && ($requestData->previous_policy_type != 'Not sure') && !empty($proposal->previous_policy_number)) {
            $isPreviousInsurerKnown = "true";
            $previousInsurerCode = isset($proposal->previous_insurance_company) ? $proposal->previous_insurance_company : "NA";
        }

        $cng_lpg_amt = $non_electrical_amt = $electrical_amt = null;
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
        $is_tppd = false;
        $cpa_selected = 'false';
        $tenure = 0;
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                if (isset($data['name']) && $data['name']  == 'Compulsory Personal Accident') {
                    $cpa_selected = 'true';
                               $tenure = 1;
                    $tenure = isset($data['tenure'])? $data['tenure'] :$tenure;
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

        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
        {
            if($requestData->business_type == 'newbusiness')
            {
                $tenure = isset($tenure) ? $tenure : 5; 
            }
            else{
                $tenure = isset($tenure) ? $tenure : 1;
            }
        }

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = null;
        $no_of_driverLL = 0;
        $paidDriverLL = "false";
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $data) {
                if ($data['name'] == 'LL paid driver') {
                    $no_of_driverLL = 1;
                    $paidDriverLL = "true";
                }
                if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver = $data['sumInsured'];
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $cover_pa_unnamed_passenger = $data['sumInsured'];
                }
            }
        }
        $rsa = false;
        $zero_dep = false;
        $engine_protection = false;
        $return_to_invoice = false;
        $consumables = false;
        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Road Side Assistance') {
                    $rsa = true;
                }
                if ($data['name'] == 'Zero Depreciation') {
                    $zero_dep = true;
                }
                if ($data['name'] == 'Engine Protector') {
                    $engine_protection = true;
                }
                if ($data['name'] == 'Return To Invoice') {
                    $return_to_invoice = true;
                }

                if ($data['name'] == 'Consumable') {
                    $consumables = true;
                }
            }
        }
        //Allowing addons if previous policy type is third party according to git #32651
        // if ($requestData->previous_policy_type == 'Third-party') {
        //     $zero_dep = false;
        //     $rsa = false;
        //     $consumables = false;
        //     $return_to_invoice = false;
        //     $engine_protection = false;
        // }
                if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
            $sub_insurance_product_code = 50;
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
        $is_agent_float = 'N';

        $enquiry_id = getUUID();


        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('user_proposal_id', $proposal['user_proposal_id'])
            ->where('seller_type', 'P')
            ->first();

        $integrationId = config("IC.GODIGIT.V2.BIKE.QUOTE_INTEGRATION_ID");

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
            if ($pos_data) {

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
            if ($is_pos_testing_mode == 'Y') {
                $is_pos = 'true';
                $posp_name = 'test';
                $posp_unique_number = '9768574564';
                $posp_pan_number = 'ABGTY8890Z';
                $posp_aadhar_number = '569278616999';
                $posp_contact_number = '9768574564';
            }
        } else if ($is_pos_testing_mode == 'Y' && $quote->idv <= 5000000) {
            $is_pos = 'true';
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = 'ABGTY8890Z';
            $posp_aadhar_number = '569278616999';
            $posp_contact_number = '9768574564';
        }
        if (isset($pos_data->category) && $pos_data->category == 'Essone') {
            $is_agent_float = 'Y';
        }
        $access_token_resp = getToken($proposal->user_proposal_id, $productData, 'proposal', $requestData->business_type);
        $access_token = ($access_token_resp['token']);

        //Quick Quote - Premium              
        $quote_premium_request =
            [
                "motorQuickQuote" =>

                [
                    'enquiryId' => ($is_agent_float == 'Y') ? $enquiry_id : (($premium_type == "own_damage") ? 'GODIGIT_QQ_TWO_WHEELER_SAOD_01' : 'GODIGIT_QQ_TWO_WHEELER_PACKAGE_01'),
                    'contract' =>
                    [
                        'insuranceProductCode' => $insurance_product_code,
                        'subInsuranceProductCode' => $sub_insurance_product_code,
                        'startDate' => $policy_start_date,
                        'endDate' => $policy_end_date,
                        'policyHolderType' =>  $policy_holder_type,
                        'externalPolicyNumber' => null,
                        'isNCBTransfer' => null,
                        'coverages' =>
                        [
                            'voluntaryDeductible' => $voluntary_deductible_amount,
                            'thirdPartyLiability' =>
                            [
                                'isTPPD' => $is_tppd,
                            ],
                            'ownDamage' =>
                            [
                                'discount' =>
                                [
                                    'userSpecialDiscountPercent' => 0,
                                    'discounts' =>
                                    [],
                                ],
                                'surcharge' =>
                                [
                                    'loadings' =>
                                    [],
                                ],
                            ],
                            'personalAccident' =>
                            [
                                'selection' => ($cpa_selected == 'true' && $requestData->vehicle_owner_type == "I") ? true : false,
                                'insuredAmount' => ($cpa_selected == 'true') ? 1500000 : 0,
                                'coverTerm' => $tenure,
                            ],
                            'accessories' =>
                            [
                                'cng' =>
                                [
                                    'selection' => "false",
                                    'insuredAmount' => null,
                                ],
                                'electrical' =>
                                [
                                    'selection' => "false",
                                    'insuredAmount' => null,
                                ],
                                'nonElectrical' =>
                                [
                                    'selection' => "false",
                                    'insuredAmount' => null,
                                ],
                            ],
                            'addons' =>
                            [
                                'partsDepreciation' =>
                                [
                                    'claimsCovered' => null,
                                    'selection' => $zero_dep,
                                ],
                                'roadSideAssistance' =>
                                [
                                    'selection' => $rsa,
                                ],
                                'engineProtection' => [
                                    'selection' => $engine_protection,
                                ],
                                'rimProtection' => [
                                    'selection' => "false",
                                ],
                                'returnToInvoice' => [
                                    'selection' => $return_to_invoice,
                                ],
                                'consumables' => [
                                    'selection' => $consumables,
                                ]

                            ],
                            'legalLiability' =>
                            [
                                'paidDriverLL' =>
                                [
                                    'selection' => $paidDriverLL,
                                    'insuredCount' => $no_of_driverLL,
                                ],
                                'employeesLL' =>
                                [
                                    'selection' => 'false',
                                    'insuredCount' => null,
                                ],
                                'unnamedPaxLL' =>
                                [
                                    'selection' => 'false',
                                    'insuredCount' => null,
                                ],
                                'cleanersLL' =>
                                [
                                    'selection' => 'false',
                                    'insuredCount' => null,
                                ],
                                'nonFarePaxLL' =>
                                [
                                    'selection' => 'false',
                                    'insuredCount' => null,
                                ],
                                'workersCompensationLL' =>
                                [
                                    'selection' => 'false',
                                    'insuredCount' => null,
                                ],
                            ],
                            'unnamedPA' =>
                            [
                                'unnamedPax' => [
                                    'selection' => !empty($cover_pa_unnamed_passenger) ? 'true' : 'false',
                                    'insuredAmount' => !empty($cover_pa_unnamed_passenger) ? $cover_pa_unnamed_passenger : 0,
                                    'insuredCount' => NULL,
                                ],
                                'unnamedPaidDriver' => [
                                    'selection' => !empty($cover_pa_paid_driver) ? 'true' : 'false',
                                    'insuredAmount' => !empty($cover_pa_paid_driver) ? $cover_pa_paid_driver : 0,
                                    'insuredCount' => NULL,
                                ],
                                'unnamedHirer' =>
                                [
                                    'selection' => 'false',
                                    'insuredAmount' => null,
                                    'insuredCount' => null,
                                ],
                                'unnamedPillionRider' =>
                                [
                                    'selection' => 'false',
                                    'insuredAmount' => null,
                                    'insuredCount' => null,
                                ],
                                'unnamedCleaner' =>
                                [
                                    'selection' => 'false',
                                    'insuredAmount' => null,
                                    'insuredCount' => null,
                                ],
                                'unnamedConductor' =>
                                [
                                    'selection' => 'false',
                                    'insuredAmount' => null,
                                    'insuredCount' => null,
                                ],
                            ],
                        ],
                    ],
                    'vehicle' =>
                    [
                        'isVehicleNew' => $is_vehicle_new,
                        'vehicleMaincode' => $mmv->ic_version_code,
                        'licensePlateNumber' => $vehicle_registration_no,
                        'vehicleIdentificationNumber' => $proposal->chassis_number,
                        'engineNumber' => $proposal->engine_number,
                        'manufactureDate' =>  date('Y-m-d', strtotime('01-' . $requestData->manufacture_year)),
                        'registrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                        'vehicleIDV' => [
                            'idv' => $quote->idv,
                        ],
                        'usageType' => null,
                        'permitType' => null,
                        'motorType' => null,
                        'vehicleType' => null
                    ],
                    'previousInsurer' =>
                    [
                        'isPreviousInsurerKnown' => $isPreviousInsurerKnown,
                        'previousInsurerCode' => $previousInsurerCode,
                        'previousPolicyNumber' => !empty($proposal->previous_policy_number) ? removeSpecialCharactersFromString($proposal->previous_policy_number) : null,
                        'previousPolicyExpiryDate' => !empty($proposal->prev_policy_expiry_date) ? date('Y-m-d', strtotime($proposal->prev_policy_expiry_date)) : null,
                        'isClaimInLastYear' => ($requestData->is_claim == 'Y') ? 'true' : 'false',
                        'originalPreviousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',
                        'previousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',
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
        if ($premium_type == "own_damage") {
            $quote_premium_request['motorQuickQuote']['previousInsurer']['originalPreviousPolicyType'] = "1OD_5TP";
            $quote_premium_request['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = $proposal->tp_insurance_company;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = $proposal->tp_insurance_number;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = date('Y-m-d', strtotime($proposal->tp_start_date));
            $quote_premium_request['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = date('Y-m-d', strtotime($proposal->tp_end_date));
        }
        if ($isPreviousInsurerKnown == "false") {
            $quote_premium_request['motorQuickQuote']['previousInsurer']['isPreviousInsurerKnown'] = $isPreviousInsurerKnown;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousInsurerCode'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousPolicyNumber'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousPolicyExpiryDate'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['isClaimInLastYear'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['originalPreviousPolicyType'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousPolicyType'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['previousNoClaimBonus'] = null;
            $quote_premium_request['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy'] = null;
        }

        if(config('IC.GODIGIT.V2.BIKE.ENVIRONMENT') == 'UAT'){
            $quote_premium_request = $quote_premium_request['motorQuickQuote'];
        }

        $data = getWsData(
            config('IC.GODIGIT.V2.BIKE.END_POINT_URL'),
            $quote_premium_request,
            'godigit',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'godigit',
                'method'   => 'Premium Calculation',
                'section' => $productData->product_sub_type_code,
                'authorization' => $access_token,
                'integrationId' => $integrationId,
                'transaction_type' => 'proposal',
            ]
        );
        $data = $data['response'];
        if (!empty($data)) {
            $response = json_decode($data);
            if ($response->error->errorCode == '0') {
                if (optional($response->preInspection)->isPreInspectionRequired == 'true' && optional($response->preInspection)->isPreInspectionWaived == false && config('IC.GODIGIT.V2.BIKE.BREAKIN.ENABLE') === 'Y') {
                    $self_inspection = 'true';
                    $is_breakin_case = 'Y';
                } else {
                    $is_breakin_case = 'N';
                }

                $address = DB::table('godigit_pincode_state_city_master as gdstcm')
                ->where([
                    'pincode' => $proposal->pincode,
                    'city' => $proposal->city,
                    'state' => $proposal->state,
                ])
                    ->select('statecode', 'city', 'country', 'district')
                    ->first();

                $company = DB::table('master_company')
                ->where('company_id', '=', $productData->product_sub_type_id)
                    ->select('company_name')
                    ->first();
                $address_data = [
                    'address' => $proposal->address_line1,
                    'address_1_limit'   => 79,
                    'address_2_limit'   => 79
                ];
                $getAddress = getAddress($address_data);

                $person = [];
                if ($policy_holder_type == 'COMPANY') {
                    $person =
                        [
                            "personType" => "COMPANY",
                            "addresses" =>
                            [
                                [
                                    "addressType" => "HEAD_QUARTER",
                                    "streetNumber" => null,
                                    "street" => $getAddress['address_1'],
                                    "district" => $getAddress['address_2'],
                                    "state" => isset($address->statecode) ? $address->statecode : null,
                                    "city" => $proposal->city,
                                    "country" => "IN",
                                    "pincode" => $proposal->pincode,
                                    "geoCode" => null,
                                    "zone" => null
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
                                    "documentType" => "GST",
                                    "documentId" => isset($proposal->gst_number) ? $proposal->gst_number : null,
                                    "issuingAuthority" => "IN",
                                    "issuingPlace" => null,
                                    "issueDate" => "",
                                    "expiryDate" => ""
                                ]
                            ],
                            "isPolicyHolder" => "true",
                            "isPayer" => null,
                            "isVehicleOwner" => "true",
                            "companyName" => $proposal->first_name,
                        ];
                } else {
                    $person =
                        [
                            "personType" => "INDIVIDUAL",
                            "addresses" => [
                                [
                                    "addressType" => "PRIMARY_RESIDENCE",
                                    "flatNumber" => null,
                                    "streetNumber" => null,
                                    "street" => $getAddress['address_1'],
                                    "district" => $getAddress['address_2'],
                                    "state" => isset($address->statecode) ? trim($address->statecode) : null,
                                    "city" => isset($address->city) ? trim($address->city) : null,
                                    "country" => 'IN',
                                    "pincode" => $proposal->pincode,
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
                                    "issuingAuthority" => "IN",
                                    "issuingPlace" => null,
                                    "issueDate" => "",
                                    "expiryDate" => ""
                                ]
                            ],
                            "isPolicyHolder" => true,
                            "isVehicleOwner" => true,
                            "firstName" => $proposal->first_name,
                            "middleName" => null,
                            "lastName" => $proposal->last_name,
                            "dateOfBirth" => date('Y-m-d', strtotime($proposal->dob)),
                            "gender" => ($proposal->gender == 'MALE') ? 'MALE' : 'FEMALE',
                            "isDriver" => true,
                            "isInsuredPerson" => true
                        ];
                }
                $proposal_data =
                    [
                        "motorCreateQuote" =>
                        [
                            "enquiryId" => ($is_agent_float == 'Y') ? $enquiry_id : (($premium_type == "own_damage") ? 'GODIGIT_QQ_TWO_WHEELER_SAOD_01' : 'GODIGIT_QQ_TWO_WHEELER_PACKAGE_01'),
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
                                    "personalAccident" =>
                                    [
                                        "selection" => ($cpa_selected == 'true' && $requestData->vehicle_owner_type == "I") ? "true" : "false",
                                        "insuredAmount" => ($cpa_selected == 'true') ? 1500000 : 0,
                                        "coverTerm" => $tenure,
                                    ],
                                    "accessories" =>
                                    [
                                        "cng" =>
                                        [
                                            "selection" => 'false',
                                            "insuredAmount" => null,
                                        ],
                                        "electrical" =>
                                        [
                                            "selection" => 'false',
                                            "insuredAmount" => null,
                                        ],
                                        "nonElectrical" =>
                                        [
                                            "selection" => 'false',
                                            "insuredAmount" => null,
                                        ],
                                    ],
                                    "addons" => [
                                        "partsDepreciation" => [
                                            "claimsCovered" => null,
                                            "selection" => $zero_dep
                                        ],
                                        "roadSideAssistance" => [
                                            "selection" => $rsa
                                        ],
                                        "engineProtection" => [
                                            "selection" => $engine_protection
                                        ],
                                        "rimProtection" => [
                                            "selection" => null
                                        ],
                                        "returnToInvoice" => [
                                            "selection" => $return_to_invoice
                                        ],
                                        "consumables" => [
                                            "selection" => $consumables
                                        ]
                                    ],
                                    "legalLiability" => [
                                        "paidDriverLL" => [
                                            'selection' => $paidDriverLL,
                                            'insuredCount' => $no_of_driverLL,
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
                                            "selection" => null,
                                            "insuredCount" => null
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
                                            "insuredAmount" => !empty($cover_pa_unnamed_passenger) ? $cover_pa_unnamed_passenger : 0,
                                            "insuredCount" => NULL,
                                        ],
                                        "unnamedPaidDriver" => [
                                            "selection" => !empty($cover_pa_paid_driver) ? 'true' : 'false',
                                            "insuredAmount" => !empty($cover_pa_paid_driver) ? $cover_pa_paid_driver : 0,
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
                                            'selection' => 'false',
                                            'insuredAmount' => null,
                                            'insuredCount' => null,
                                        ],
                                        "unnamedConductor" => [
                                            'selection' => 'false',
                                            'insuredAmount' => null,
                                            'insuredCount' => null
                                        ]
                                    ]
                                ]
                            ],
                            "vehicle" => [
                                'isVehicleNew' => $is_vehicle_new,
                                'vehicleMaincode' => $mmv->ic_version_code,
                                'licensePlateNumber' => $vehicle_registration_no,
                                'vehicleIdentificationNumber' => $proposal->chassis_number,
                                'engineNumber' => $proposal->engine_number,
                                'manufactureDate' =>  date('Y-m-d', strtotime('01-' . $requestData->manufacture_year)),
                                'registrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                                "vehicleIDV" => [
                                    'idv' => $quote->idv,
                                ],
                                'usageType' => null,
                                "permitType" => null,
                                "motorType" => null,
                                'vehicleType' => null
                            ],
                            'hypothecation' => [
                                'isHypothecation' => $proposal->is_vehicle_finance ? true : false,
                                'hypothecationAgency' => $proposal->is_vehicle_finance ? $proposal->name_of_financer : '',
                                'hypothecationCIty' => $proposal->is_vehicle_finance ? $proposal->hypothecation_city : '',
                            ],
                            "previousInsurer" => [
                                "isPreviousInsurerKnown" => $isPreviousInsurerKnown,
                                "previousInsurerCode" => $previousInsurerCode,
                                "previousPolicyNumber" => !empty($proposal->previous_policy_number) ? removeSpecialCharactersFromString($proposal->previous_policy_number) : null,
                                "previousPolicyExpiryDate" => !empty($proposal->prev_policy_expiry_date) ? date('Y-m-d', strtotime($proposal->prev_policy_expiry_date)) : null,
                                "isClaimInLastYear" => ($requestData->is_claim == 'Y') ? 'true' : 'false',
                                "originalPreviousPolicyType" => $requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',
                                "previousPolicyType" => $requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',
                                "previousNoClaimBonus" =>  $previousNoClaimBonus,
                                "currentThirdPartyPolicy" => null
                            ],
                            "preInspection" => [
                                "isPreInspectionOpted" => false
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
                            'persons' =>
                            [
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
                            ]
                        ]
                    ];

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    if (config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y') {
                        if ($proposal->is_ckyc_verified && !empty($proposal->is_ckyc_verified)) {
                            $isKYCDone = ($proposal->is_ckyc_verified == 'N') ? false : true;
                            $kycDoclist =  ['ckyc_number', 'pan_card', 'passport', 'driving_license', 'voter_id'];
                            $icKycDoclist = [
                                "ckyc_number" => "D02",
                                "driving_license" => "D04",
                                "voter_id" => "D05",
                                "passport" => "D06",
                                "pan_card" => "D07",
                                "aadhar_card" => "D03"
                            ];
                            if (!isset($icKycDoclist[$proposal->ckyc_type])) {

                                return [
                                    'status' => false,
                                    'message' => 'Something went wrong while doing the KYC. Please fill the KYC details again.'
                                ];
                            }
                            $ckycReferenceDocId = $icKycDoclist[$proposal->ckyc_type];
                            $ckycReferenceNumber = $proposal->ckyc_type_value;
                            if ($proposal->ckyc_type == 'aadhar_card') {
                                $ckycReferenceNumber = substr($ckycReferenceNumber, -4);
                            }
                            $dateOfBirth = date('Y-m-d', strtotime($proposal->dob));
                            $photo = '';
                            $photos_list = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId']);
                            if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $request['userProductJourneyId'])) {
                                if (!isset($photos_list[0]) && empty($photos_list[0])) {
                                    return [
                                        'errorType' => 'INFO',
                                        'status' => false,
                                        'message' => 'Please upload photograph to complete proposal.'
                                    ];
                                } else {
                                    $photo = ProposalController::getCkycDocument($photos_list[0]);
                                    $photo = base64_encode($photo);
                                }
                            }

                            $kyc = [
                                "isKYCDone" => $isKYCDone,
                                "ckycReferenceDocId" => $ckycReferenceDocId,
                                "ckycReferenceNumber" => $ckycReferenceNumber,
                                "dateOfBirth" => $dateOfBirth,
                                "photo" => $photo
                            ];

                            if ($proposal->ckyc_type == 'aadhar_card') {
                                if (in_array(strtoupper($proposal->gender), ['M', 'MALE'])) {
                                    $gender = 'M';
                                } else {
                                    $gender = 'W';
                                }
                                $kyc['gender'] = $gender;
                            }
                            $proposal_data['motorCreateQuote']['kyc'] = $kyc;
                        } else {
                            return
                                [
                                    'status' => false,
                                    'message' => "CKYC verification needed"
                                ];
                        }
                    }
                }

                $splitName = explode(' ', $proposal->nominee_name, 2);
                $nominee_fname = $splitName[0];
                $nominee_lname = !empty($splitName[1]) ? $splitName[1] : '';
                if ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') {
                    $proposal_data['motorCreateQuote']['nominee']['firstName'] = $nominee_fname;
                    $proposal_data['motorCreateQuote']['nominee']['middleName'] = '';
                    $proposal_data['motorCreateQuote']['nominee']['lastName'] = $nominee_lname;
                    $proposal_data['motorCreateQuote']['nominee']['dateOfBirth'] = date('Y-m-d', strtotime($proposal->nominee_dob));
                    $proposal_data['motorCreateQuote']['nominee']['relation'] = strtoupper($proposal->nominee_relationship);
                    $proposal_data['motorCreateQuote']['nominee']['personType'] = 'INDIVIDUAL';
                }
                if ($premium_type == "own_damage") {
                    $proposal_data['motorCreateQuote']['previousInsurer']['originalPreviousPolicyType'] = "1OD_5TP";
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = $proposal->tp_insurance_company;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = $proposal->tp_insurance_number;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                }

                if ($isPreviousInsurerKnown == "false") {
                    $proposal_data['motorCreateQuote']['previousInsurer']['isPreviousInsurerKnown'] = $isPreviousInsurerKnown;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousInsurerCode'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousPolicyNumber'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousPolicyExpiryDate'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['isClaimInLastYear'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['originalPreviousPolicyType'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['previousNoClaimBonus'] = null;
                    $proposal_data['motorCreateQuote']['previousInsurer']['currentThirdPartyPolicy'] = null;
                }

                if(config('IC.GODIGIT.V2.BIKE.ENVIRONMENT') == 'UAT'){
                    $proposal_data = $proposal_data['motorCreateQuote'];
                }
                
                $data = getWsData(
                    config('IC.GODIGIT.V2.BIKE.END_POINT_URL'),
                    $proposal_data,
                    'godigit',
                    [
                        'enquiryId' => $proposal->user_product_journey_id,
                        'requestMethod' => 'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'godigit',
                        'method'   => 'Proposal Submit',
                        'section' => $productData->product_sub_type_code,
                        'authorization' => $access_token,
                        'integrationId' => config('IC.GODIGIT.V2.BIKE.SUBMIT_INTEGRATION_ID'),
                        'transaction_type' => 'proposal',
                    ]
                );

                $prem_calc_web_id = $data['webservice_id'];
              
                $data = $data['response'];
                if (!empty($data)) {
                    set_time_limit(400);
                    $proposal_response = json_decode($data);
                    if (isset($proposal_response->error->errorCode) && $proposal_response->error->errorCode == '0') {
                        $vehicle_idv = $proposal_response->vehicle->vehicleIDV->idv;
                        $contract = $proposal_response->contract;
                        $preInspection = $proposal_response->preInspection ?? null;
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
                        $od = 0;
                        $cng_lpg_tp = 0;
                        $partsDepreciation = 0;
                        $road_side_assistance = 0;
                        $tppd = 0;
                        $engine_protection_amt = 0;
                        $consumables_amt = 0;
                        $return_to_invoice_amt = 0;

                        foreach ($contract->coverages as $key => $value) {
                            switch ($key) {
                                case 'thirdPartyLiability':

                                    if (isset($value->netPremium)) {
                                        $tppd = (str_replace("INR ", "", $value->netPremium));
                                    }

                                    break;

                                case 'addons':
                                    if (isset($value->roadSideAssistance) && ($value->roadSideAssistance->selection == 'true')) {
                                        if (isset($value->roadSideAssistance->netPremium)) {
                                            $road_side_assistance = str_replace("INR ", "", $value->roadSideAssistance->netPremium);
                                        }
                                    }
                                    if (isset($value->partsDepreciation) && ($value->partsDepreciation->selection == 'true')) {
                                        if (isset($value->partsDepreciation->netPremium)) {
                                            $partsDepreciation = str_replace("INR ", "", $value->partsDepreciation->netPremium);
                                        }
                                    }
                                    if (isset($value->engineProtection) && ($value->engineProtection->selection == 'true')) {
                                        if (isset($value->engineProtection->netPremium)) {
                                            $engine_protection_amt = str_replace("INR ", "", $value->engineProtection->netPremium);
                                        }
                                    }
                                    if (isset($value->returnToInvoice) && ($value->returnToInvoice->selection == 'true')) {
                                        if (isset($value->returnToInvoice->netPremium)) {
                                            $return_to_invoice_amt = str_replace("INR ", "", $value->returnToInvoice->netPremium);
                                        }
                                    }
                                    if (isset($value->consumables) && ($value->consumables->selection == 'true')) {
                                        if (isset($value->consumables->netPremium)) {
                                            $consumables_amt = str_replace("INR ", "", $value->consumables->netPremium);
                                        }
                                    }
                                    break;

                                case 'ownDamage':

                                    if (isset($value->netPremium)) {
                                        $od = str_replace("INR ", "", $value->netPremium);
                                        foreach ($value->discount->discounts as $key => $type) {
                                            if ($type->discountType == "NCB_DISCOUNT") {
                                                $ncb_discount_amt = str_replace("INR ", "", $type->discountAmount);
                                            }
                                        }
                                    }
                                    break;

                                case 'legalLiability':
                                    foreach ($value as $cover => $subcover) {
                                        if ($cover == "paidDriverLL") {
                                            if ($subcover->selection == 1) {
                                                $llpaiddriver_premium = (str_replace("INR ", "", $subcover->netPremium));
                                            }
                                        }
                                    }
                                    break;

                                case 'personalAccident':
                                    // By default Complusory PA Cover for Owner Driver
                                    if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium))) {
                                        $cover_pa_owner_driver_premium = (str_replace("INR ", "", $value->netPremium));
                                    }
                                    break;

                                case 'accessories':
                                    break;

                                case 'unnamedPA':
                                    foreach ($value as $cover => $subcover) {
                                        if ($cover == 'unnamedPaidDriver') {
                                            if (isset($subcover->selection) && $subcover->selection == 1) {
                                                if (isset($subcover->netPremium)) {
                                                    $cover_pa_paid_driver_premium = (str_replace("INR ", "", $subcover->netPremium));
                                                }
                                            }
                                        }

                                        if ($cover == 'unnamedPax') {
                                            if (isset($subcover->selection) && $subcover->selection == 1) {
                                                if (isset($subcover->netPremium)) {
                                                    $cover_pa_unnamed_passenger_premium = (str_replace("INR ", "", $subcover->netPremium));
                                                }
                                            }
                                        }
                                    }
                                    break;
                            }
                        }
                        if (isset($cng_lpg_amt) && !empty($cng_lpg_amt)) {
                            $cng_lpg_tp = 60;
                            $tppd = $tppd - 60;
                        }

                        $addon_premium = 0;
                        $ncb_discount = $ncb_discount_amt;
                        $final_od_premium = $od;
                        $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium + $cover_pa_owner_driver_premium;
                        $addon_premium = $partsDepreciation + $road_side_assistance + $return_to_invoice_amt + $consumables_amt + $engine_protection_amt;
                        $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount;
                        $final_net_premium  = str_replace("INR ", "", $proposal_response->netPremium);
                        $final_gst_amount = str_replace("INR ", "", $proposal_response->serviceTax->totalTax); // 18% IC 
                        $final_payable_amount = str_replace("INR ", "", $proposal_response->grossPremium);

                        $vehicleDetails = [
                            'manufacture_name' => $mmv->make_name,
                            'model_name' => $mmv->model_name,
                            'version' => $mmv->variant_name,
                            'fuel_type' => $mmv->fuel_type,
                            'seating_capacity' => $mmv->seating_capacity,
                            'carrying_capacity' => $mmv->seating_capacity - 1,
                            'cubic_capacity' => $mmv->cubic_capacity,
                            'gross_vehicle_weight' => $mmv->vehicle_weight,
                            'vehicle_type' => 'Bike'
                        ];
                        $cpa_end_date = '';
                        if ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' && $requestData->business_type == 'newbusiness') {
                            $cpa_end_date = date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date)));
                        } else if ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                            $cpa_end_date = date('d-m-Y', strtotime($policy_end_date));
                        }
                        // UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                        //     ->update([
                        //         'od_premium' => $final_od_premium - $final_total_discount + $addon_premium,
                        //         'tp_premium' => $final_tp_premium,
                        //         'ncb_discount' => $ncb_discount,
                        //         'addon_premium' => $addon_premium,
                        //         'total_premium' => $final_net_premium,
                        //         'service_tax_amount' => $final_gst_amount,
                        //         'final_payable_amount' => $final_payable_amount,
                        //         'cpa_premium' => $cover_pa_owner_driver_premium,
                        //         //'policy_no' => $proposal_response->policyNumber,
                        //         'proposal_no' => $proposal_response->policyNumber,
                        //         'total_discount' => $final_total_discount,
                        //         'unique_proposal_id' => $proposal_response->applicationId,
                        //         'is_policy_issued' => $proposal_response->policyStatus,
                        //         'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                        //         'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                        //         'unique_quote' => $enquiry_id,
                        //         'tp_insurance_company' => !empty($proposal->tp_insurance_company) ? $proposal->tp_insurance_company : '',
                        //         'tp_insurance_company_name' => $additional_data['prepolicy']['tpInsuranceCompanyName'] ?? '',
                        //         'tp_insurance_number' => !empty($proposal->tp_insurance_number) ? $proposal->tp_insurance_number : '',
                        //         'tp_start_date' => !empty($proposal->tp_start_date) ? date('d-m-Y', strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime($policy_start_date)),
                        //         'tp_end_date' => !empty($proposal->tp_end_date) ? date('d-m-Y', strtotime($proposal->tp_end_date)) : (($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y', strtotime($policy_end_date))),
                        //         'cpa_start_date' => (($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') ? date('d-m-Y', strtotime($policy_start_date)) : ''),
                        //         'cpa_end_date'   => $cpa_end_date,
                        //         'is_cpa' => ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') ? 'Y' : 'N',
                        //         'ic_vehicle_details' => $vehicleDetails,
                        //         'is_breakin_case' => $is_breakin_case,
                        //         // 'is_inspection_done' => $is_breakin_case,
                        //     ]);

                        $update_proposal = [
                                'od_premium' => $final_od_premium - $final_total_discount + $addon_premium,
                                'tp_premium' => $final_tp_premium,
                                'ncb_discount' => $ncb_discount,
                                'addon_premium' => $addon_premium,
                                'total_premium' => $final_net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'final_payable_amount' => $final_payable_amount,
                                'cpa_premium' => $cover_pa_owner_driver_premium,
                                //'policy_no' => $proposal_response->policyNumber,
                                'proposal_no' => $proposal_response->policyNumber,
                                'total_discount' => $final_total_discount,
                                'unique_proposal_id' => $proposal_response->applicationId,
                                'is_policy_issued' => $proposal_response->policyStatus,
                                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                'unique_quote' => $enquiry_id,
                                'tp_insurance_company' => !empty($proposal->tp_insurance_company) ? $proposal->tp_insurance_company : '',
                                'tp_insurance_company_name' => $additional_data['prepolicy']['tpInsuranceCompanyName'] ?? '',
                                'tp_insurance_number' => !empty($proposal->tp_insurance_number) ? $proposal->tp_insurance_number : '',
                                'tp_start_date' => !empty($proposal->tp_start_date) ? date('d-m-Y', strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime($policy_start_date)),
                                'tp_end_date' => !empty($proposal->tp_end_date) ? date('d-m-Y', strtotime($proposal->tp_end_date)) : (($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y', strtotime($policy_end_date))),
                                'cpa_start_date' => (($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') ? date('d-m-Y', strtotime($policy_start_date)) : ''),
                                'cpa_end_date'   => $cpa_end_date,
                                'is_cpa' => ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') ? 'Y' : 'N',
                                'ic_vehicle_details' => $vehicleDetails,
                                'is_breakin_case' => $is_breakin_case,
                                // 'is_inspection_done' => $is_breakin_case,
                            ];
                            if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                                unset($update_proposal['tp_start_date']);
                                unset($update_proposal['tp_end_date']);
                            }
                            $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($update_proposal);
                            
                        if (($preInspection->isPreInspectionRequired ?? false) == true && ($preInspection->isPreInspectionWaived ?? true) == false && $is_breakin_case == 'Y' ) {
                            DB::table('cv_breakin_status')->insert([
                                'user_proposal_id' => $proposal->user_proposal_id,
                                'ic_id' => $productData->company_id,
                                'breakin_number' => $proposal_response->policyNumber,
                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);

                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                        } else {
                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                        }

                        GodigitPremiumDetailController::saveOneApiPremiumDetails($prem_calc_web_id);

                        if (config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y') {
                            if (config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y') {
                                sleep(10);
                                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                                    $KycStatusApiResponse = GetKycStatusGoDIgitOneapi( $proposal->user_product_journey_id,$proposal_response->policyNumber,  $productData->product_sub_type_name,$proposal->user_proposal_id,customEncrypt( $proposal->user_product_journey_id),$productData);
                                    if ($KycStatusApiResponse['status'] !== true) {
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
                                        UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                            ->where('user_proposal_id', $proposal->user_proposal_id)
                                            ->update(['is_ckyc_verified' => 'N']);

                                        $message = '';
                                        $KycError = [
                                            'S'    => 'Success',
                                            'F'    => 'Fail',
                                            'P'    => 'Name Mismatch',
                                            'A'    => 'Address Mismatch',
                                            'B'    => 'Name & Address Mismatch'
                                        ];
                                        if (isset($KycStatusApiResponse['response']) && !empty($KycStatusApiResponse['response']->mismatchType)) {
                                            $message = in_array($KycStatusApiResponse['response']->mismatchType, ['P', 'A', 'B']) ? "Your kyc verification failed due to " . $KycError[$KycStatusApiResponse['response']->mismatchType] . " , after successful kyc completion please fill proposal data as per KYC documents" : $KycError[$KycStatusApiResponse['response']->mismatchType];
                                        } else if (isset($KycStatusApiResponse['response']) && empty($KycStatusApiResponse['response']->mismatchType) && filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL)) {
                                            $message = 'Please fill correct proposal data as per documents provided for KYC verification';
                                        }

                                        $message = godigitProposalKycMessage($proposal, $message);

                                        if ((filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL))) {
                                            return response()->json([
                                                'status' => true,
                                                'msg' => "Proposal Submitted Successfully!",
                                                'data' => [
                                                    'proposalId' => $proposal->user_proposal_id,
                                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                                    'proposalNo' => $proposal_response->applicationId,
                                                    'finalPayableAmount' => $final_payable_amount,
                                                    'is_breakin' => (($preInspection->isPreInspectionRequired ?? false) == true && ($preInspection->isPreInspectionWaived ?? true) == false) ? $is_breakin_case : 'N',
                                                    'inspection_number' => $proposal_response->policyNumber,
                                                    'kyc_url' => $KycStatusApiResponse['message'],
                                                    'is_kyc_url_present' => true,
                                                    'kyc_message' => $message,
                                                    'kyc_status' => false,
                                                ]
                                            ]);
                                        } else {
                                            // if no URL RETURNED BY IC FOR KYC
                                            return response()->json([
                                                'status' => true,
                                                'msg' => "Proposal Submitted Successfully!",
                                                'data' => [
                                                    'proposalId' => $proposal->user_proposal_id,
                                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                                    'proposalNo' => $proposal_response->applicationId,
                                                    'finalPayableAmount' => $final_payable_amount,
                                                    'is_breakin' => (($preInspection->isPreInspectionRequired ?? false) == true && ($preInspection->isPreInspectionWaived ?? true) == false) ? $is_breakin_case : 'N',
                                                    'inspection_number' => $proposal_response->policyNumber,
                                                    'kyc_url' => '',
                                                    'is_kyc_url_present' => false,
                                                    'kyc_message' => empty($message) ? 'CKYC verification failed. Redirection link found' : $message,
                                                    'kyc_status' => false,
                                                ]
                                            ]);
                                        }
                                    } else {

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

                                        UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                            ->where('user_proposal_id', $proposal->user_proposal_id)
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
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $proposal_response->applicationId,
                                'finalPayableAmount' => $final_payable_amount,
                                'is_breakin' => (($preInspection->isPreInspectionRequired ?? false) == true && ($preInspection->isPreInspectionWaived ?? true) == false) ? $is_breakin_case : 'N',
                                'inspection_number' => $proposal_response->policyNumber,
                                'kyc_url' => '',
                                'is_kyc_url_present' => false,
                                'kyc_message' => empty($message) ? 'CKYC verification failed. Redirection link found' : $message,
                                'kyc_status' => (config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y') ? true : false,
                            ]
                        ]);
                    } elseif (!empty($proposal_response->error->validationMessages[0])) {
                        return
                            [
                                'premium_amount' => '0',
                                'status' => false,
                                'message' => str_replace(",", "", $proposal_response->error->validationMessages[0])
                            ];
                    } elseif (isset($proposal_response->error->errorCode) && $proposal_response->error->errorCode == '400') {
                        return
                            [
                                'premium_amount' => '0',
                                'status' => false,
                                'message' => str_replace(",", "", $proposal_response->error->validationMessages[0])
                            ];
                    }
                } else {
                    return
                        [
                            'premium_amount' => '0',
                            'status' => false,
                            'message' => 'Insurer not reachable'
                        ];
                }
            } elseif (!empty($response->error->validationMessages[0])) {
                return
                    [
                        'premium_amount' => '0',
                        'status' => false,
                        'message' => str_replace(",", "", $response->error->validationMessages[0])
                    ];
            } elseif (isset($response->error->errorCode) && $response->error->errorCode == '400') {
                return
                    [
                        'premium_amount' => '0',
                        'status' => false,
                        'message' => str_replace(",", "", $response->error->validationMessages[0])
                    ];
            }
        } else {
            return
                [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => 'Insurer not reachable'
                ];
        }
        // }
    }

    public static function renewalSubmitOneapi($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'godigit');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];

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
            'manf_name' => $mmv->make_name,
            'model_name' => $mmv->model_name,
            'version_name' => $mmv->variant_name,
            'seating_capacity' => $mmv->fyntune_version['seating_capacity'],
            'carrying_capacity' => ((int) $mmv->fyntune_version['seating_capacity']) - 1,
            'cubic_capacity' => $mmv->cubic_capacity,
            'fuel_type' =>  $mmv->fuel_type,
            'gross_vehicle_weight' => $mmv->vehicle_weight ?? 0,
            'vehicle_type' => 'BIKE',
            'version_id' => $mmv->ic_version_code,
        ];

        $access_token_resp = GoDigitHelper::getToken($enquiryId, $productData, "proposal");
        $tokenResponse = ($access_token_resp['token']);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $policynumber = $user_proposal->previous_policy_number;
        $requestdata = [
            "motorMotorrenewalgetquoteApi" => [
                "queryParam" =>  [
                    "policyNumber" => $policynumber
                ]
            ]
        ];
        if(config('IC.GODIGIT.V2.BIKE.REMOVE_GODIGIT_IDENTIFIER') == 'Y'){
            $requestdata = $requestdata['motorMotorrenewalgetquoteApi'];
        }
        $fetch_url = config('IC.GODIGIT.V2.BIKE.END_POINT_URL');
        $get_response = getWsData($fetch_url, $requestdata, 'godigit', [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'godigit',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Fetch Policy Details - Renewal',
            'transaction_type'  => 'proposal',
            'type'              => 'renewal',
            'headers' => [
                'Content-Type'  => "application/json",
                'Connection'    => "Keep-Alive",
                'Accept'        => "application/json",
                'Authorization' =>  "Bearer ".$tokenResponse,
                "integrationId" => config("IC.GODIGIT.V2.BIKE.FETCH_API_INTEGRATION_ID")
            ]    

        ]);
        $data = $get_response['response'];
        $response_data = json_decode($data);
        if (isset($response_data->error->errorCode) && $response_data->error->errorCode == '0') {

            $prev_pol_end_date = str_replace('/', '-', $response_data->contract->endDate);
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($prev_pol_end_date)));
            $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));

            if ($response_data->contract->insuranceProductCode == '20201') {
                $policyType = 'Comprehensive';
            } else if ($response_data->contract->insuranceProductCode == '20203') {
                $policyType = 'Own Damage';
            } else if ($response_data->contract->insuranceProductCode == '20202') {
                $policyType = 'Third Party';
            }

            $idv = $response_data->vehicle->vehicleIDV->idv;
            $contract = $response_data->contract;

            $tppd = false;
            $zero_depreciation = false;
            $road_side_assistance = false;
            $engine_protection = false;
            $return_to_invoice = false;
            $consumables = false;
            $personal_belonging = false;
            $key_and_lock_protection = false;
            $llpaiddriver = false;
            $cover_pa_owner_driver = false;
            $cover_pa_paid_drive = false;
            $zero_depreciation_claimsCovered = 'ONE';
            $discountPercent = 0;

            $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                ->first();
            foreach ($contract->coverages as $key => $value) {
                switch ($key) {
                    case 'thirdPartyLiability':

                        if (isset($value->netPremium)) {
                            $tppd = true;
                        }

                        break;

                    case 'addons':
                        foreach ($value as $key => $addon) {
                            switch ($key) {
                                case 'partsDepreciation':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $zero_depreciation = true;
                                        $zero_depreciation_claimsCovered = $addon->claimsCovered;
                                    }
                                    break;

                                case 'roadSideAssistance':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $road_side_assistance = true;
                                    }
                                    break;

                                case 'engineProtection':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $engine_protection = true;
                                    }
                                    break;

                                case 'returnToInvoice':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $return_to_invoice = true;
                                    }
                                    break;

                                case 'consumables':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $consumables = true;
                                    }
                                    break;

                                case 'personalBelonging':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $personal_belonging = true;
                                    }
                                    break;

                                case 'keyAndLockProtect':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $key_and_lock_protection = true;
                                    }
                                    break;
                            }
                        }
                        break;

                    case 'ownDamage':

                        if (isset($value->netPremium)) {
                            $od = (str_replace("INR ", "", $value->netPremium));
                            foreach ($value->discount->discounts as $key => $type) {
                                if ($type->discountType == "NCB_DISCOUNT") {
                                    $discountPercent = ($type->discountPercent);
                                }
                            }
                        }
                        break;

                    case 'legalLiability':
                        foreach ($value as $cover => $subcover) {
                            if ($cover == "paidDriverLL") {
                                if ($subcover->selection == 1) {
                                    $llpaiddriver = true;
                                }
                            }
                        }
                        break;

                    case 'personalAccident':
                        if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium))) {
                            $cover_pa_owner_driver = true;
                        }
                        break;

                    case 'accessories':
                        break;

                    case 'unnamedPA':

                        foreach ($value as $cover => $subcover) {
                            if ($cover == 'unnamedPaidDriver') {
                                if (isset($subcover->selection) && $subcover->selection == 1) {
                                    if (isset($subcover->netPremium)) {
                                        $cover_pa_paid_drive = true;
                                    }
                                }
                            }
                        }
                        break;
                }
            }
            $isKYCDone = ($proposal->is_ckyc_verified == 'N') ? false : true;
            $kycDoclist =  ['ckyc_number', 'pan_card', 'passport', 'driving_license', 'voter_id', 'aadhar_card'];
            $icKycDoclist = [
                "ckyc_number" => "D02",
                "driving_license" => "D04",
                "voter_id" => "D05",
                "passport" => "D06",
                "pan_card" => "D07",
                "aadhar_card" => "D03"
            ];
            if (!isset($icKycDoclist[$proposal->ckyc_type])) {
                return [
                    'status' => false,
                    'message' => 'This ckyc type is not available.'
                ];
            }
            $ckycReferenceDocId = $icKycDoclist[$proposal->ckyc_type];
            $ckycReferenceNumber = $proposal->ckyc_type_value;
            if ($proposal->ckyc_type == 'aadhar_card') {
                $ckycReferenceNumber = substr($ckycReferenceNumber, -4);
            }
            $dateOfBirth = date('Y-m-d', strtotime($proposal->dob));
            $photo = '';
            $photos_list = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId']);
            if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $request['userProductJourneyId'])) {
                if (!isset($photos_list[0]) && empty($photos_list[0])) {
                    return [
                        'errorType' => 'INFO',
                        'status' => false,
                        'premium' => '0',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Please upload photograph to complete proposal.'
                    ];
                } else {
                    $photo = ProposalController::getCkycDocument($photos_list[0]);
                    $photo = base64_encode($photo);
                }
            }

            $person = $response_data->persons[0];
            $address = $person->addresses[0];
            $communications1 = $person->communications[0] ?? '';
            $communications2 = $person->communications[1] ?? '';
            $isClaimInLastYear = false;
            if ($requestData->is_claim == 'Y') {
                $isClaimInLastYear = true;
            } elseif (!empty($response_data->previousInsurer->isClaimInLastYear)) {
                $isClaimInLastYear = $response_data->previousInsurer->isClaimInLastYear ?? "";
            }
            $premium_calculation_array = [
                "motorQuickQuote" => [
                    "pincode" => isset($response_data->persons[0]->addresses[0]->pincode) ? $response_data->persons[0]->addresses[0]->pincode : "400001",
                    "previousInsurer" => [
                        "previousPolicyExpiryDate" => $response_data->contract->endDate,
                        "isClaimInLastYear" => $isClaimInLastYear,
                        "previousInsurerCode" => "159",
                        "previousPolicyNumber" => $response_data->policyNumber,
                        "currentThirdPartyPolicy" => [
                            "currentThirdPartyPolicyExpiryDateTime" => $response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyExpiryDateTime ?? '',
                            "currentThirdPartyPolicyInsurerCode" =>  $response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyInsurerCode ?? '',
                            "currentThirdPartyPolicyStartDateTime" => $response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyStartDateTime ?? '',
                            "currentThirdPartyPolicyNumber" => $response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyNumber ?? ''
                        ],
                        "isPreviousInsurerKnown" => true,
                        "previousPolicyType" => $policyType,
                        "previousNoClaimBonus" => $response_data->contract->currentNoClaimBonus,
                        "originalPreviousPolicyType" => $response_data->previousInsurer->originalPreviousPolicyType ?? NULL
                    ],
                    "preInspection" => [
                        "isPreInspectionOpted" => false
                    ],
                    "contract" => [
                        "policyHolderType" => $response_data->contract->policyHolderType,
                        "insuranceProductCode" => $response_data->contract->insuranceProductCode,
                        "endDate" =>  date('Y-m-d', strtotime($response_data->contract->endDate)),
                        "externalPolicyNumber" =>  $response_data->contract->externalPolicyNumber ?? "",
                        "isNCBTransfer" => $response_data->contract->isNCBTransfer,
                        "subInsuranceProductCode" => $response_data->contract->subInsuranceProductCode,
                        "coverages" => [
                            "isIMT23" => false,
                            "personalAccident" => [
                                "selection" => $cover_pa_owner_driver,
                                "insuredAmount" => ($cover_pa_owner_driver == true) ? 1500000 : 0,
                                "coverTerm" => null
                            ],
                            "addons" => [
                                "personalBelonging" => [
                                    "selection" =>  $personal_belonging
                                ],
                                "returnToInvoice" => [
                                    "selection" => $return_to_invoice
                                ],
                                "rimProtection" => [
                                    "selection" => null
                                ],
                                "consumables" => [
                                    "selection" => $consumables
                                ],
                                "partsDepreciation" => [
                                    "selection" =>  $zero_depreciation,
                                    "claimsCovered" => $zero_depreciation_claimsCovered,
                                ],
                                "engineProtection" => [
                                    "selection" => $engine_protection
                                ],
                                "tyreProtection" => [
                                    "selection" => null
                                ],
                                "roadSideAssistance" => [
                                    "selection" => $road_side_assistance
                                ],
                                "keyAndLockProtect" => [
                                    "selection" => $key_and_lock_protection
                                ]
                            ],
                            "accessories" => [
                                "electrical" => [
                                    "selection" => null,
                                    "insuredAmount" => null
                                ],
                                "nonElectrical" => [
                                    "selection" => null,
                                    "insuredAmount" => null
                                ],
                                "cng" => [
                                    "selection" => null,
                                    "insuredAmount" => null
                                ]
                            ],
                            "voluntaryDeductible" => "ZERO",
                            "isGeoExt" => false,
                            "legalLiability" => [
                                "nonFarePaxLL" => [
                                    "selection" => null,
                                    "insuredCount" => null
                                ],
                                "unnamedPaxLL" => [
                                    "selection" => null,
                                    "insuredCount" => null
                                ],
                                "workersCompensationLL" => [
                                    "selection" => null,
                                    "insuredCount" => null
                                ],
                                "paidDriverLL" => [
                                    "selection" =>  $llpaiddriver,
                                    "insuredCount" => ($llpaiddriver == true) ? 1 : 0
                                ],
                                "employeesLL" => [
                                    "selection" => null,
                                    "insuredCount" => null
                                ],
                                "cleanersLL" => [
                                    "selection" => null,
                                    "insuredCount" => null
                                ]
                            ],
                            "thirdPartyLiability" => [
                                "isTPPD" => false
                            ],
                            "unnamedPA" => [
                                "unnamedPaidDriver" => [
                                    "selection" => null,
                                    "insuredAmount" => null,
                                    "insuredCount" => null
                                ],
                                "unnamedPax" => [
                                    "selection" => null,
                                    "insuredAmount" => null,
                                    "insuredCount" => null
                                ],
                                "unnamedConductor" => [
                                    "selection" => null,
                                    "insuredAmount" => null,
                                    "insuredCount" => null
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
                                    "selection" => null,
                                    "insuredAmount" => null,
                                    "insuredCount" => null
                                ]
                            ],
                            "isOverturningExclusionIMT47" => false,
                            "isTheftAndConversionRiskIMT43" => false,
                            "ownDamage" => [
                                "discount" => [
                                    "userSpecialDiscountPercent" => null
                                ]
                            ]
                        ],
                        "startDate" =>  date('Y-m-d', strtotime($response_data->contract->startDate))

                    ],
                    "enquiryId" => $response_data->enquiryId,
                    "vehicle" => [
                        "isVehicleNew" => $response_data->vehicle->isVehicleNew,
                        "licensePlateNumber" => $response_data->vehicle->licensePlateNumber,
                        // "permitType" => "DOMAIN <Support Values (PUBLIC,PRIVATE,PUBLIC_PRIVATE)>",//need clarification 
                        "registrationAuthority" => $response_data->vehicle->registrationAuthority,
                        "engineNumber" => $response_data->vehicle->engineNumber,
                        "vehicleIdentificationNumber" =>  $response_data->vehicle->vehicleIdentificationNumber,
                        "registrationDate" => $response_data->vehicle->registrationDate,
                        "manufactureDate" => $response_data->vehicle->manufactureDate,
                        "vehicleMaincode" => $response_data->vehicle->vehicleMaincode,
                        "vehicleIDV" => [
                            "minimumIdv" =>  $response_data->vehicle->vehicleIDV->minimumIdv ?? 0,
                            "defaultIdv" =>  $response_data->vehicle->vehicleIDV->defaultIdv ?? 0,
                            "maximumIdv" => $response_data->vehicle->vehicleIDV->maximumIdv ?? 0,
                            "idv" => $response_data->vehicle->vehicleIDV->idv
                        ],
                    ]
                ]
            ];
            $premium_calculation_array['motorQuickQuote']["pospInfo"] = [
                "isPOSP" => false
            ];

            if (!empty($response_data->pospInfo->isPOSP)) {
                $premium_calculation_array['motorQuickQuote']["pospInfo"] = [
                    "isPOSP" =>  $response_data->pospInfo->isPOSP ?? "",
                    "pospContactNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospContactNumber ?? '') : "",
                    "pospName" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospName ?? "") : "",
                    "pospAadhaarNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospAadhaarNumber ?? "") : "",
                    "pospLocation" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospLocation ?? "") : "",
                    "pospUniqueNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospUniqueNumber ?? "") : "",
                    "pospPanNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospPanNumber ?? "") : ""
                ];
            } 
            if(config('IC.GODIGIT.V2.BIKE.REMOVE_GODIGIT_IDENTIFIER') == 'Y'){
                $premium_calculation_array = $premium_calculation_array['motorQuickQuote'];
            }

            $get_response = getWsData(
                config('IC.GODIGIT.V2.BIKE.END_POINT_URL'),
                $premium_calculation_array,
                'godigit',
                [
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Premium Calculation Renewal',
                    'transaction_type' => 'proposal',
                    'type'              => 'renewal',
                    'headers' => [
                        'Content-Type'  => "application/json",
                        'Connection'    => "Keep-Alive",
                        'Accept'        => "application/json",
                        'Authorization' =>  "Bearer " . $tokenResponse,
                        "integrationId" => config("IC.GODIGIT.V2.BIKE.QUOTE_INTEGRATION_ID")
                    ]

                ]
            );

            $access_token_resp = GoDigitHelper::getToken($enquiryId, $productData);
            $tokenResponse = ($access_token_resp['token']);
            $data = $get_response['response'];
            if (!empty($data)) {
                $response = json_decode($data);

                if (isset($response->error->errorCode) && $response->error->errorCode == '0') {
                    $contract = $response_data->contract;
                    $tppd = false;
                    $zero_depreciation = false;
                    $road_side_assistance = false;
                    $engine_protection = false;
                    $return_to_invoice = false;
                    $consumables = false;
                    $personal_belonging = false;
                    $key_and_lock_protection = false;
                    $llpaiddriver = false;
                    $cover_pa_owner_driver = false;
                    $cover_pa_paid_drive = false;
                    $zero_depreciation_claimsCovered = 'ONE';
                    $discountPercent = 0;
                    foreach ($contract->coverages as $key => $value) {
                        switch ($key) {
                            case 'thirdPartyLiability':

                                if (isset($value->netPremium)) {
                                    $tppd = true;
                                }

                                break;

                            case 'addons':
                                foreach ($value as $key => $addon) {
                                    switch ($key) {
                                        case 'partsDepreciation':
                                            if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                $zero_depreciation = true;
                                                $zero_depreciation_claimsCovered = $addon->claimsCovered;
                                            }
                                            break;

                                        case 'roadSideAssistance':
                                            if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                $road_side_assistance = true;
                                            }
                                            break;

                                        case 'engineProtection':
                                            if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                $engine_protection = true;
                                            }
                                            break;

                                        case 'returnToInvoice':
                                            if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                $return_to_invoice = true;
                                            }
                                            break;

                                        case 'consumables':
                                            if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                $consumables = true;
                                            }
                                            break;
                                    }
                                }
                                break;

                            case 'ownDamage':

                                if (isset($value->netPremium)) {
                                    $od = (str_replace("INR ", "", $value->netPremium));
                                    foreach ($value->discount->discounts as $key => $type) {
                                        if ($type->discountType == "NCB_DISCOUNT") {
                                            $discountPercent = ($type->discountPercent);
                                        }
                                    }
                                }
                                break;

                            case 'legalLiability':
                                foreach ($value as $cover => $subcover) {
                                    if ($cover == "paidDriverLL") {
                                        if ($subcover->selection == 1) {
                                            $llpaiddriver = true;
                                        }
                                    }
                                }
                                break;

                            case 'personalAccident':
                                if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium))) {
                                    $cover_pa_owner_driver = true;
                                }
                                break;

                            case 'accessories':
                                break;

                            case 'unnamedPA':

                                foreach ($value as $cover => $subcover) {
                                    if ($cover == 'unnamedPaidDriver') {
                                        if (isset($subcover->selection) && $subcover->selection == 1) {
                                            if (isset($subcover->netPremium)) {
                                                $cover_pa_paid_drive = true;
                                            }
                                        }
                                    }
                                }
                                break;
                        }
                    }

                    $policy_holder_type = $response_data->contract->policyHolderType;
                    if ($policy_holder_type == 'COMPANY') {
                        $person = [
                            "personType" => $response_data->persons[0]->personType,
                            "addresses" => [
                                [
                                    "addressType" => $response_data->persons[0]->addresses[0]->addressType,
                                    "flatNumber" => $response_data->persons[0]->addresses[0]->flatNumber ?? '',
                                    "streetNumber" =>  $response_data->persons[0]->addresses[0]->streetNumber ?? '',
                                    "street" => $response_data->persons[0]->addresses[0]->street ?? '',
                                    "district" =>  $response_data->persons[0]->addresses[0]->district ?? '',
                                    "city" => $response_data->persons[0]->addresses[0]->city,
                                    "country" => $response_data->persons[0]->addresses[0]->country,
                                    "pincode" => $response_data->persons[0]->addresses[0]->pincode,
                                    "state" => $response_data->persons[0]->addresses[0]->state
                                ]
                            ],
                            "communications" => [
                                [
                                    "communicationType" => $response_data->persons[0]->communications[0]->communicationType,
                                    "communicationId" =>  $response_data->persons[0]->communications[0]->communicationId,
                                    "isPrefferedCommunication" => $response_data->persons[0]->communications[0]->isPrefferedCommunication
                                ],
                                [
                                    "communicationType" => $response_data->persons[0]->communications[1]->communicationType,
                                    "communicationId" =>  $response_data->persons[0]->communications[1]->communicationId,
                                    "isPrefferedCommunication" => $response_data->persons[0]->communications[1]->isPrefferedCommunication
                                ]
                            ],
                            "identificationDocuments" => [
                                [
                                    "documentType" => isset($response_data->persons[0]->identificationDocuments[0]->documentType) ? $response_data->persons[0]->identificationDocuments[0]->documentType : null,
                                    "documentId" => isset($response_data->persons[0]->identificationDocuments[0]->documentId) ? $response_data->persons[0]->identificationDocuments[0]->documentId : null,
                                    "issuingAuthority" => "IN",
                                    "issuingPlace" => null,
                                    "issueDate" => "",
                                    "expiryDate" => "",
                                    "issuingPlace" => "IN"
                                ]
                            ],
                            "isPolicyHolder" => true,
                            "isPayer" => null,
                            "isVehicleOwner" => true,
                            "companyName" => isset($response_data->persons[0]->companyName) ? $response_data->persons[0]->companyName : null,
                        ];
                    } else {
                        $person =
                            [
                                "firstName" => $response_data->persons[0]->firstName,
                                "identificationDocuments" => [],
                                "lastName" => $response_data->persons[0]->lastName,
                                "addresses" => [
                                    [
                                        "addressType" => $response_data->persons[0]->addresses[0]->addressType,
                                        "flatNumber" => $response_data->persons[0]->addresses[0]->flatNumber ?? '',
                                        "streetNumber" =>  $response_data->persons[0]->addresses[0]->streetNumber ?? '',
                                        "street" => $response_data->persons[0]->addresses[0]->street ?? '',
                                        "district" =>  $response_data->persons[0]->addresses[0]->district ?? '',
                                        "city" => $response_data->persons[0]->addresses[0]->city,
                                        "country" => $response_data->persons[0]->addresses[0]->country,
                                        "pincode" => $response_data->persons[0]->addresses[0]->pincode,
                                        "state" => $response_data->persons[0]->addresses[0]->state
                                    ]
                                ],
                                "communications" => [
                                    [
                                        "communicationType" => $response_data->persons[0]->communications[0]->communicationType,
                                        "communicationId" =>  $response_data->persons[0]->communications[0]->communicationId,
                                        "isPrefferedCommunication" => $response_data->persons[0]->communications[0]->isPrefferedCommunication
                                    ],
                                    [
                                        "communicationType" => $response_data->persons[0]->communications[1]->communicationType,
                                        "communicationId" =>  $response_data->persons[0]->communications[1]->communicationId,
                                        "isPrefferedCommunication" => $response_data->persons[0]->communications[1]->isPrefferedCommunication
                                    ]
                                ],
                                "isVehicleOwner" => true,
                                "isInsuredPerson" => $response_data->persons[0]->isInsuredPerson ??  $response_data->persons[1]->isInsuredPerson ?? "",
                                "gender" => $response_data->persons[0]->gender,
                                "isPolicyHolder" => true,
                                "dateOfBirth" => $response_data->persons[0]->dateOfBirth,
                                "isDriver" =>  $response_data->persons[0]->isDriver ?? $response_data->persons[1]->isDriver,
                                "personType" => $response_data->persons[0]->personType,
                            ];
                    }

                    $proposal_calculation_array = [
                        "motorCreateQuote" => [
                            "persons" => [
                                $person
                            ],
                            "pincode" => isset($response_data->persons[0]->addresses[0]->pincode) ? $response_data->persons[0]->addresses[0]->pincode : "",
                            "previousInsurer" => [
                                "previousPolicyExpiryDate" => $response_data->contract->endDate,
                                "isClaimInLastYear" => $isClaimInLastYear,
                                "previousInsurerCode" => "158",
                                "previousPolicyNumber" =>  $response_data->policyNumber,
                                "currentThirdPartyPolicy" => null,
                                "isPreviousInsurerKnown" => true,
                                "previousPolicyType" => null,
                                "previousNoClaimBonus" => $response_data->contract->currentNoClaimBonus,
                                "originalPreviousPolicyType" => $response_data->previousInsurer->originalPreviousPolicyType ?? NULL
                            ],
                            "preInspection" => [
                                "isPreInspectionOpted" => false
                            ],
                            "queryParam" => [
                                "isUserSpecialDiscountOpted" => false
                            ],
                            "kyc" => [
                                "ckycReferenceNumber" => $ckycReferenceNumber,
                                "isKYCDone" => $isKYCDone,
                                "ckycReferenceDocId" => $ckycReferenceDocId,
                                "photo" => "",
                                "dateOfBirth" => $dateOfBirth
                            ],
                            "contract" => [
                                "policyHolderType" => $response_data->contract->policyHolderType,
                                "insuranceProductCode" => $response_data->contract->insuranceProductCode,
                                "endDate" =>  date('Y-m-d', strtotime($policy_end_date)),
                                "externalPolicyNumber" => null,
                                "subInsuranceProductCode" => $response_data->contract->subInsuranceProductCode,
                                "coverages" => [
                                    "accessories" => [
                                        "cng" => [
                                            "selection" => false,
                                            "insuredAmount" => null
                                        ],
                                        "electrical" => [
                                            "selection" => false,
                                            "insuredAmount" => null
                                        ],
                                        "nonElectrical" => [
                                            "selection" => false,
                                            "insuredAmount" => null
                                        ]
                                    ],
                                    "addons" => [
                                        "partsDepreciation" => [
                                            "selection" => $zero_depreciation
                                        ],
                                        "roadSideAssistance" => [
                                            "selection" => $road_side_assistance
                                        ],
                                        "engineProtection" => [
                                            "selection" => $engine_protection
                                        ],
                                        "tyreProtection" => [
                                            "selection" => false
                                        ],
                                        "rimProtection" => [
                                            "selection" => false
                                        ],
                                        "returnToInvoice" => [
                                            "selection" => $return_to_invoice
                                        ],
                                        "consumables" => [
                                            "selection" => $consumables
                                        ],
                                        "personalBelonging" => [
                                            "selection" => $personal_belonging
                                        ],
                                        "keyAndLockProtect" => [
                                            "selection" => $key_and_lock_protection
                                        ]
                                    ],
                                    "voluntaryDeductible" => null,
                                    "isGeoExt" => false,
                                    "legalLiability" => [
                                        "paidDriverLL" => [
                                            "selection" => $llpaiddriver
                                        ],
                                        "employeesLL" => [
                                            "selection" => false
                                        ],
                                        "unnamedPaxLL" => [
                                            "selection" => false
                                        ],
                                        "cleanersLL" => [
                                            "selection" => false
                                        ],
                                        "nonFarePaxLL" => [
                                            "selection" => false
                                        ],
                                        "workersCompensationLL" => [
                                            "selection" => false
                                        ]
                                    ],
                                    "unnamedPA" => [
                                        "unnamedPax" => [
                                            "selection" => false,
                                            "insuredAmount" => null
                                        ],
                                        "unnamedPaidDriver" => [
                                            "selection" => false
                                        ],
                                        "unnamedHirer" => [
                                            "selection" => false
                                        ],
                                        "unnamedPillionRider" => [
                                            "selection" => false
                                        ],
                                        "unnamedCleaner" => [
                                            "selection" => false
                                        ],
                                        "unnamedConductor" => [
                                            "selection" => false
                                        ]
                                    ],
                                    "theft" => [
                                        "selection" => false
                                    ],
                                    "isTheftAndConversionRiskIMT43" => false,
                                    "ownDamage" => [
                                        "discount" => [
                                            "userSpecialDiscountPercent" => "0"
                                        ]
                                    ],
                                    "isIMT23" => false,
                                    "personalAccident" => [
                                        "selection" => $cover_pa_owner_driver,
                                        "insuredAmount" => ($cover_pa_owner_driver == true) ? 1500000 : 0,
                                        "coverTerm" => null
                                    ],
                                    "fire" => [
                                        "selection" => false
                                    ],
                                    "thirdPartyLiability" => [
                                        "isTPPD" => false
                                    ],
                                    "isOverturningExclusionIMT47" => false
                                ],
                                "startDate" =>  date('Y-m-d', strtotime($policy_start_date))
                            ],
                            "nominee" => [
                                "firstName" => "Shubham",
                                "lastName" => "Bhalerao",
                                "dateOfBirth" => "1999-09-09",
                                "middleName" => "",
                                "personType" => "INDIVIDUAL",
                                "relation" => "BROTHER"
                            ],
                            "motorQuestions" => [
                                "selfInspection" => isset($self_inspection) ? $self_inspection : false,
                                "furtherAgreement" => null,
                                "financer" => $proposal->is_vehicle_finance ? $proposal->name_of_financer : ''
                            ],
                            "enquiryId" => $response_data->enquiryId,
                            "vehicle" => [
                                "isVehicleNew" => $response_data->vehicle->isVehicleNew,
                                "licensePlateNumber" => $response_data->vehicle->licensePlateNumber,
                                "registrationAuthority" => $response_data->vehicle->registrationAuthority,
                                "engineNumber" => $response_data->vehicle->engineNumber,
                                "vehicleIdentificationNumber" => $response_data->vehicle->vehicleIdentificationNumber,
                                "registrationDate" =>  $response_data->vehicle->registrationDate,
                                "manufactureDate" => $response_data->vehicle->manufactureDate,
                                "vehicleMaincode" => $response_data->vehicle->vehicleMaincode,
                                "vehicleIDV" => [
                                    "minimumIdv" =>  $response_data->vehicle->vehicleIDV->minimumIdv ?? 0,
                                    "defaultIdv" =>  $response_data->vehicle->vehicleIDV->defaultIdv ?? 0,
                                    "maximumIdv" => $response_data->vehicle->vehicleIDV->maximumIdv ?? 0,
                                    "idv" => $response_data->vehicle->vehicleIDV->idv
                                ]
                            ]
                        ]
                    ];
                    $proposal_calculation_array['motorCreateQuote']["pospInfo"] = [
                        "isPOSP" => false
                    ];
                    if (!empty($response_data->pospInfo->isPOSP)) {
                        $proposal_calculation_array['motorCreateQuote']["pospInfo"] = [
                            "isPOSP" =>  $response_data->pospInfo->isPOSP ?? "",
                            "pospContactNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospContactNumber ?? '') : "",
                            "pospName" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospName ?? "") : "",
                            "pospAadhaarNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospAadhaarNumber ?? "") : "",
                            "pospLocation" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospLocation ?? "") : "",
                            "pospUniqueNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospUniqueNumber ?? "") : "",
                            "pospPanNumber" => ($response_data->pospInfo->isPOSP == true) ? ($response_data->pospInfo->pospPanNumber ?? "") : ""
                        ];
                    } 
                    if(config('IC.GODIGIT.V2.BIKE.REMOVE_GODIGIT_IDENTIFIER') == 'Y'){
                        $proposal_calculation_array = $proposal_calculation_array['motorCreateQuote'];
                    }
                    
                    $get_response = getWsData(
                        config('IC.GODIGIT.V2.BIKE.END_POINT_URL'),
                        $proposal_calculation_array,
                        'godigit',
                        [
                            'enquiryId' => $enquiryId,
                            'requestMethod' => 'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'godigit',
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Proposal Submit - Renewal',
                            'transaction_type' => 'proposal',
                            'type'              => 'renewal',
                            'headers' => [
                                'Content-Type'  => "application/json",
                                'Connection'    => "Keep-Alive",
                                'Accept'        => "application/json",
                                'Authorization' =>  "Bearer " . $tokenResponse,
                                "integrationId" => config("IC.GODIGIT.V2.BIKE.SUBMIT_INTEGRATION_ID") 
                            ]
                        ]

                    );                
                    $data = $get_response['response'];
                    if (!empty($data)) {
                        set_time_limit(400);
                        $proposal_response = json_decode($data);
                        if ($proposal_response->error->errorCode == '0') {
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
                            $od = 0;
                            $cng_lpg_tp = 0;
                            $partsDepreciation = 0;
                            $road_side_assistance = 0;
                            $tppd = 0;
                            $engine_protection_amt = 0;
                            $consumables_amt = 0;
                            $return_to_invoice_amt = 0;
                            foreach ($contract->coverages as $key => $value) {
                                switch ($key) {
                                    case 'thirdPartyLiability':

                                        if (isset($value->netPremium)) {
                                            $tppd = (str_replace("INR ", "", $value->netPremium));
                                        }

                                        break;

                                    case 'addons':
                                        if (isset($value->roadSideAssistance) && ($value->roadSideAssistance->selection == 'true')) {
                                            if (isset($value->roadSideAssistance->netPremium)) {
                                                $road_side_assistance = (str_replace("INR ", "", $value->roadSideAssistance->netPremium));
                                            }
                                        }
                                        if (isset($value->partsDepreciation) && ($value->partsDepreciation->selection == 'true')) {
                                            if (isset($value->partsDepreciation->netPremium)) {
                                                $partsDepreciation = (str_replace("INR ", "", $value->partsDepreciation->netPremium));
                                            }
                                        }
                                        if (isset($value->engineProtection) && ($value->engineProtection->selection == 'true')) {
                                            if (isset($value->engineProtection->netPremium)) {
                                                $engine_protection_amt = (str_replace("INR ", "", $value->engineProtection->netPremium));
                                            }
                                        }
                                        if (isset($value->returnToInvoice) && ($value->returnToInvoice->selection == 'true')) {
                                            if (isset($value->returnToInvoice->netPremium)) {
                                                $return_to_invoice_amt = (str_replace("INR ", "", $value->returnToInvoice->netPremium));
                                            }
                                        }
                                        if (isset($value->consumables) && ($value->consumables->selection == 'true')) {
                                            if (isset($value->consumables->netPremium)) {
                                                $consumables_amt = (str_replace("INR ", "", $value->consumables->netPremium));
                                            }
                                        }
                                        break;

                                    case 'ownDamage':

                                        if (isset($value->netPremium)) {
                                            $od = (str_replace("INR ", "", $value->netPremium));
                                            foreach ($value->discount->discounts as $key => $type) {
                                                if ($type->discountType == "NCB_DISCOUNT") {
                                                    $ncb_discount_amt = (str_replace("INR ", "", $type->discountAmount));
                                                }
                                            }
                                        }
                                        break;

                                    case 'legalLiability':
                                        foreach ($value as $cover => $subcover) {
                                            if ($cover == "paidDriverLL") {
                                                if ($subcover->selection == 1) {
                                                    $llpaiddriver_premium = (str_replace("INR ", "", $subcover->netPremium));
                                                }
                                            }
                                        }
                                        break;

                                    case 'personalAccident':
                                        if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium))) {
                                            $cover_pa_owner_driver_premium = (str_replace("INR ", "", $value->netPremium));
                                        }
                                        break;

                                    case 'accessories':
                                        break;

                                    case 'unnamedPA':

                                        break;
                                }
                            }
                            if (isset($cng_lpg_amt) && !empty($cng_lpg_amt)) {
                                $cng_lpg_tp = 60;
                                $tppd = $tppd - 60;
                            }

                            $addon_premium = 0;
                            $ncb_discount = $ncb_discount_amt;
                            $final_od_premium = $od;
                            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium + $cover_pa_owner_driver_premium;
                            $addon_premium = $partsDepreciation + $road_side_assistance + $return_to_invoice_amt + $consumables_amt + $engine_protection_amt;
                            $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount;
                            $final_net_premium  = (str_replace("INR ", "", $response->netPremium));
                            $final_gst_amount = (str_replace("INR ", "", $response->serviceTax->totalTax)); // 18% IC 
                            $final_payable_amount = (str_replace("INR ", "", $response->grossPremium));



                            $proposal->idv                  = $idv;
                            $proposal->proposal_no          = $proposal_response->policyNumber;
                            $proposal->unique_proposal_id   = $proposal_response->applicationId;
                            // $proposal->customer_id          = $response_data['clientCode'];
                            $proposal->od_premium           = $final_od_premium;
                            $proposal->tp_premium           = $final_tp_premium;
                            $proposal->ncb_discount         = $ncb_discount;
                            $proposal->addon_premium        = $addon_premium;
                            $proposal->total_premium        = $final_net_premium;
                            $proposal->service_tax_amount   = $final_gst_amount;
                            $proposal->final_payable_amount = $final_payable_amount;
                            $proposal->cpa_premium          = $cover_pa_owner_driver_premium;
                            $proposal->total_discount       = $final_total_discount;
                            $proposal->ic_vehicle_details   = $mmv_data;
                            $proposal->policy_start_date    = $policy_start_date;
                            $proposal->policy_end_date      = $policy_end_date;
                            $proposal->save();

                            $updateJourneyStage['user_product_journey_id'] = $enquiryId;
                            $updateJourneyStage['ic_id'] = '35';
                            $updateJourneyStage['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                            $updateJourneyStage['proposal_id'] = $proposal->user_proposal_id;
                            updateJourneyStage($updateJourneyStage);

                            GodigitPremiumDetailController::saveOneApiPremiumDetails($get_response['webservice_id']);

                            if (config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y') {
                                if (config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y') {
                                    sleep(10);
                                    if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                                        $KycStatusApiResponse = GetKycStatusGoDIgitOneapi( $enquiryId, $proposal_response->policyNumber,  $productData->product_sub_type_name, $proposal->user_proposal_id, customEncrypt( $proposal->user_product_journey_id ), $productData);
                                        if ($KycStatusApiResponse['status'] !== true) {
                                            CkycGodigitFailedCasesData::updateOrCreate(
                                                ['user_product_journey_id' => $proposal->user_product_journey_id],
                                                [
                                                    'policy_no' =>  $proposal_response->policyNumber,
                                                    'kyc_url' => $KycStatusApiResponse['message'] ?? '',
                                                    'return_url' => '',
                                                    'status' => 'failed',
                                                    'post_data' => ''
                                                ]
                                            );
                                            UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                                ->update(['is_ckyc_verified' => 'N']);

                                            $message = '';
                                            $KycError = [
                                                'S'    => 'Success',
                                                'F'    => 'Fail',
                                                'P'    => 'Name Mismatch',
                                                'A'    => 'Address Mismatch',
                                                'B'    => 'Name & Address Mismatch'
                                            ];
                                            if (isset($KycStatusApiResponse['response']) && !empty($KycStatusApiResponse['response']->mismatchType)) {
                                                $message = in_array($KycStatusApiResponse['response']->mismatchType, ['P', 'A', 'B']) ? "Your kyc verification failed due to " . $KycError[$KycStatusApiResponse['response']->mismatchType] . " , after successful kyc completion please fill proposal data as per KYC documents" : $KycError[$KycStatusApiResponse['response']->mismatchType];
                                            } else if (isset($KycStatusApiResponse['response']) && empty($KycStatusApiResponse['response']->mismatchType) && filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL)) {
                                                $message = 'Please fill correct proposal data as per documents provided for KYC verification';
                                            }

                                            $message = godigitProposalKycMessage($proposal, $message);

                                            if ((filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL))) {
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
                                                        'kyc_url' => $KycStatusApiResponse['message'],
                                                        'is_kyc_url_present' => true,
                                                        'kyc_message' => $message,
                                                        'kyc_status' => false,
                                                    ]
                                                ]);
                                            } else {
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
                                                        'kyc_url' => '',
                                                        'is_kyc_url_present' => false,
                                                        'kyc_message' => empty($message) ? 'CKYC verification failed. Redirection link found' : $message,
                                                        'kyc_status' => false,
                                                    ]
                                                ]);
                                            }
                                        } else {

                                            CkycGodigitFailedCasesData::updateOrCreate(
                                                ['user_product_journey_id' => $proposal->user_product_journey_id],
                                                [
                                                    'policy_no' =>  $proposal_response->policyNumber,
                                                    'status' => 'success',
                                                    'return_url' => '',
                                                    'kyc_url' => '',
                                                    'post_data' => ''

                                                ]
                                            );

                                            UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                                ->where('user_proposal_id', $proposal->user_proposal_id)
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
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => "Proposal Submitted Successfully!",
                                'data' => camelCase([
                                    'proposalNo' => $proposal_response->applicationId,
                                    'finalPayableAmount' => $final_payable_amount,
                                    'proposalId' => $proposal->user_proposal_id,
                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                    'proposalNo' => $proposal_response->applicationId,
                                    'kyc_url' => '',
                                    'is_kyc_url_present' => false,
                                    'kyc_message' => empty($message) ? 'CKYC verification failed. Redirection link found' : $message,
                                    'kyc_status' => false,
                                ]),
                            ]);
                        } elseif (!empty($proposal_response->error->validationMessages[0])) {
                            return
                                [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => str_replace(",", "", $proposal_response->error->validationMessages[0])
                                ];
                        } elseif (isset($proposal_response->error->errorCode) && $proposal_response->error->errorCode == '400') {
                            return
                                [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => str_replace(",", "", $proposal_response->error->validationMessages[0])
                                ];
                        }
                    } else {
                        return
                            [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => 'Insurer not reachable'
                            ];
                    }
                } elseif (!empty($response->error->validationMessages[0])) {
                    return
                        [
                            'premium_amount' => 0,
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => str_replace(",", "", $response->error->validationMessages[0])
                        ];
                } elseif (isset($response->error->errorCode) && $response->error->errorCode == '400') {
                    return
                        [
                            'premium_amount' => 0,
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => str_replace(",", "", $response->error->validationMessages[0])
                        ];
                } else {
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Something went wrong'
                    ];
                }
            } else {
                return
                    [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable'
                    ];
            }
        } else {
            return [
                'status' => false,
                'premium' => '0',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => !empty($response_data->error->validationMessages) ?? 'Insurer not reachable.'
            ];
        }
    }
}
